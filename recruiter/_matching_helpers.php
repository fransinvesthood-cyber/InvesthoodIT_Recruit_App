<?php
/*
|--------------------------------------------------------------------------
| Candidate-to-Vacancy Matching helpers (new file - nothing existing is modified)
|--------------------------------------------------------------------------
| Read-only. No tables are created and nothing is written.
|
| Candidate side (confirmed schema, same rules as candidate_view.php):
|   users + roles(slug='candidate') + candidate_consents('recruiter_discovery')
|   candidate_skills(user_id, skill_id, proficiency, verification_status)
|   skills(id, name, category, is_active)
|
| Vacancy side: vacancies are `opportunities` rows. Their required skills are
| resolved per vacancy, in the same places candidate/opportunity_detail.php
| shows them:
|   1. the vacancy skills table (opportunity_id + skill_name + skill_category, as saved by
|      admin/opportunity_create.php: required_technical / required_soft = mandatory,
|      preferred_technical = optional)
|   2. the eligibility lists required_skills / preferred_skills (comma-separated text)
|   Typed names are matched to the active `skills` taxonomy by exact name, then by any
|   taxonomy skill name found inside the typed text.
|   3. only if neither yields anything: skill names found in the vacancy's own text columns
| Table names are discovered from information_schema by their columns.
| Besides skills the overall suitability score uses the vacancy's qualification, minimum
| experience, province, start date and availability (see cm_assess()).
| A recruiter can also override the required skills for one comparison.
*/

/** Credit given to a matched skill, by verification status. */
const CM_CREDIT = [
    'verified'   => 1.0,
    'pending'    => 0.8,
    'unverified' => 0.6,
    'failed'     => 0.0,
];

const CM_STATUS_RANK       = ['verified' => 4, 'pending' => 3, 'unverified' => 2, 'failed' => 1];
const CM_MANDATORY_WEIGHT  = 2;
const CM_PAGE_LIMIT        = 100;

/** Weight of each criterion in the overall suitability score (criteria the vacancy does not state are left out). */
const CM_WEIGHTS = ['skills' => 60, 'qualification' => 20, 'experience' => 10, 'location' => 5, 'availability' => 5];

/** Numeric candidate-experience columns recognised on candidate_profiles / users (years). */
const CM_EXPERIENCE_COLUMNS = ['years_of_experience', 'experience_years', 'total_experience_years', 'years_experience'];
const CM_NUMERIC_TYPES      = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'];
const CM_TEXT_TYPES        = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'json'];
const CM_TEXT_COLUMN_HINT  = '/title|description|requirement|qualif|skill|responsib|summary|detail|dut(y|ies)|criteria|competenc|experience|overview|prerequisite|about/i';
const CM_LINK_TABLES       = ['opportunity_skills', 'vacancy_skills', 'opportunity_required_skills', 'opportunity_skill_requirements'];
const CM_REQUIRED_COLUMNS  = ['is_required', 'required', 'is_mandatory', 'mandatory'];

/* ==================================================================
 | Pure logic (no database)
 * ==================================================================*/

function cm_norm_status($status): string
{
    $s = strtolower(trim((string)$status));
    return isset(CM_CREDIT[$s]) ? $s : 'unverified';
}

/** Readable value of a "required" flag stored as 1/0, Y/N, yes/no, true/false. */
function cm_truthy($v): bool
{
    return in_array(strtolower(trim((string)$v)), ['1', 'y', 'yes', 'true', 'required', 'mandatory'], true);
}

/** [label, css modifier] for a 0-100 score. */
function cm_tier(int $score): array
{
    if ($score >= 80) return ['Strong match', 'cm-tier--strong'];
    if ($score >= 60) return ['Good match', 'cm-tier--good'];
    if ($score >= 35) return ['Partial match', 'cm-tier--partial'];
    return ['Low match', 'cm-tier--low'];
}

/** Regex for finding a skill name inside free text, or null if the name is too short to match safely. */
function cm_skill_pattern(string $name): ?array
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    $len  = mb_strlen($name);
    if ($len < 3) return null;   // "C", "R", "Go" etc. cannot be told apart from ordinary text

    $quoted = str_replace(' ', '\s+', preg_quote($name, '/'));
    $flags  = $len === 3 ? 'u' : 'ui'; // 3-letter names (SQL, PHP) must match case-sensitively as typed

    return ['re' => '/(?<![\p{L}\p{N}])' . $quoted . '(?![\p{L}\p{N}])/' . $flags];
}

/** Patterns for every catalog skill: [skill_id => ['re' => ...]]. */
function cm_skill_patterns(array $catalog): array
{
    $out = [];
    foreach ($catalog as $id => $skill) {
        $p = cm_skill_pattern((string)$skill['name']);
        if ($p !== null) $out[(int)$id] = $p;
    }
    return $out;
}

/** Skill ids whose name appears in the text. */
function cm_detect_skills(string $text, array $patterns): array
{
    $found = [];
    if (trim($text) === '') return $found;
    foreach ($patterns as $id => $p) {
        if (preg_match($p['re'], $text) === 1) $found[] = (int)$id;
    }
    return $found;
}

function cm_clean_text($v): string
{
    $s = html_entity_decode(strip_tags((string)$v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
}

/**
 * Compare one candidate's skills with a vacancy's requirements.
 *
 * $req        [skill_id => bool mandatory]
 * $candSkills [skill_id => ['status' => verified|pending|unverified|failed, 'proficiency' => ?string]]
 * $catalog    [skill_id => ['name' => ...]]
 */
function cm_score(array $req, array $candSkills, array $catalog): array
{
    $totalWeight = 0;
    $earned      = 0.0;
    $matched     = 0;
    $verified    = 0;
    $missingMand = 0;
    $rows        = [];

    foreach ($req as $skillId => $mandatory) {
        $skillId   = (int)$skillId;
        $mandatory = (bool)$mandatory;
        $weight    = $mandatory ? CM_MANDATORY_WEIGHT : 1;
        $totalWeight += $weight;

        $cs     = $candSkills[$skillId] ?? null;
        $state  = 'missing';
        $status = null;
        $prof   = null;

        if ($cs !== null) {
            $status = cm_norm_status($cs['status'] ?? '');
            $prof   = $cs['proficiency'] ?? null;
            if ($status === 'failed') {
                $state = 'failed';
            } else {
                $state   = 'matched';
                $earned += $weight * CM_CREDIT[$status];
                $matched++;
                if ($status === 'verified') $verified++;
            }
        }

        if ($state !== 'matched' && $mandatory) $missingMand++;

        $rows[] = [
            'skill_id'    => $skillId,
            'name'        => (string)($catalog[$skillId]['name'] ?? ('Skill #' . $skillId)),
            'mandatory'   => $mandatory,
            'state'       => $state,
            'status'      => $status,
            'proficiency' => $prof,
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        if ($a['mandatory'] !== $b['mandatory']) return $a['mandatory'] ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });

    return [
        'score'             => $totalWeight > 0 ? (int)round($earned / $totalWeight * 100) : 0,
        'required'          => count($req),
        'matched'           => $matched,
        'verified'          => $verified,
        'missing_mandatory' => $missingMand,
        'rows'              => $rows,
    ];
}

/** Ordering for result lists: best score first, then verified depth, then breadth. */
function cm_compare_results(array $a, array $b): int
{
    $x = $a['res']; $y = $b['res'];
    if ($x['score']    !== $y['score'])    return $y['score']    <=> $x['score'];
    if ($x['verified'] !== $y['verified']) return $y['verified'] <=> $x['verified'];
    if ($x['matched']  !== $y['matched'])  return $y['matched']  <=> $x['matched'];
    return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
}

function cm_norm_place(?string $v): string
{
    return trim(preg_replace('/[\s_\-]+/u', ' ', mb_strtolower((string)$v)) ?? '');
}

function cm_same_place(?string $a, ?string $b): bool
{
    $a = cm_norm_place($a);
    return $a !== '' && $a === cm_norm_place($b);
}

/** Level words found in a text, as ranks: 1 certificate, 2 diploma, 3 degree, 4 honours, 5 masters, 6 doctorate. */
function cm_qual_ranks(string $text): array
{
    $t = ' ' . mb_strtolower(preg_replace('/[_\-]+/u', ' ', $text) ?? '') . ' ';
    $levels = [
        6 => '/\b(?:ph\.?d|doctorate|doctoral|dphil)\b/u',
        5 => '/\b(?:masters?|msc|mba|mtech|m\.tech|meng|mcom)\b/u',
        4 => '/\b(?:honou?rs|hons|postgraduate diploma|pgdip|nqf\s*8)\b/u',
        3 => '/\b(?:bachelors?|degree|bsc|btech|b\.tech|beng|bcom|advanced diploma|nqf\s*7)\b/u',
        2 => '/\b(?:diploma|nqf\s*6)\b/u',
        1 => '/\b(?:certificate|certification|matric|grade\s*12|nsc|nqf\s*[45])\b/u',
    ];
    $found = [];
    foreach ($levels as $rank => $re) {
        if (preg_match($re, $t) === 1) {
            $found[] = $rank;
            $t = preg_replace($re, ' ', $t) ?? $t;
        }
    }
    return $found;
}

/**
 * Lowest qualification level a vacancy accepts. "Diploma or degree" -> diploma; "BSc Honours" -> honours.
 * Alternatives are split on "or" and "/"; anything marked "preferred" is ignored. Null when no level is named.
 */
function cm_qual_requirement_rank(string $text): ?int
{
    $t = mb_strtolower($text);
    $t = preg_replace('/\([^)]*prefer[^)]*\)/u', ' ', $t) ?? $t;
    $parts = preg_split('/\bor\b|\/|;|\n/u', $t) ?: [];
    $ranks = [];
    foreach ($parts as $part) {
        if (mb_strpos($part, 'prefer') !== false) continue;
        $r = cm_qual_ranks($part);
        if ($r) $ranks[] = max($r);
    }
    return $ranks ? min($ranks) : null;
}

/** Candidate's highest recognised level, or null (e.g. "other" or empty). */
function cm_qual_candidate_rank(string $level): ?int
{
    $r = cm_qual_ranks($level);
    return $r ? max($r) : null;
}

/** Years of experience asked for in text such as "2 years", "3+ yrs", "6 months". Null when not stated. */
function cm_parse_years(string $text): ?float
{
    $t = mb_strtolower($text);
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*\+?\s*(?:years?|yrs?)\b/u', $t, $m) === 1) {
        return (float)str_replace(',', '.', $m[1]);
    }
    if (preg_match('/(\d+)\s*\+?\s*months?\b/u', $t, $m) === 1) {
        return round(((int)$m[1]) / 12, 2);
    }
    return null;
}

function cm_parse_ts(?string $date): ?int
{
    $date = trim((string)$date);
    if ($date === '') return null;
    $ts = strtotime(substr($date, 0, 10));
    return $ts ? $ts : null;
}

/** Vacancy criteria besides skills, from the vacancy row and its eligibility text. */
function cm_vacancy_criteria(array $vac, array $elig): array
{
    $qual  = cm_clean_text($elig['qual'] ?? '');
    $exp   = cm_clean_text($elig['exp'] ?? '');
    $avail = cm_clean_text($elig['avail'] ?? '');
    $other = cm_clean_text($elig['other'] ?? '');
    $arr   = strtolower(trim((string)($vac['work_arrangement'] ?? '')));

    return [
        'qual_text'  => $qual,
        'qual_rank'  => $qual !== '' ? cm_qual_requirement_rank($qual) : null,
        'exp_text'   => $exp,
        'exp_years'  => $exp !== '' ? cm_parse_years($exp) : null,
        'avail_text' => $avail,
        'other_text' => $other,
        'province'   => trim((string)($vac['province'] ?? '')),
        'remote'     => strpos($arr, 'remote') !== false,
        'start_date' => trim((string)($vac['start_date'] ?? '')),
    ];
}

/**
 * Overall suitability of one candidate for one vacancy.
 * Skills carry most of the weight; qualification, experience, location and availability count
 * only when the vacancy states them (and, for experience, when profiles record it). A stated
 * criterion the candidate has not provided earns no credit and is flagged "unknown".
 *
 * Returns the cm_score() result with 'score' replaced by the overall score, plus
 * 'skill_score' and 'criteria' rows [key,label,state met|unmet|unknown|info,required,candidate].
 */
function cm_assess(array $entry, array $candSkills, array $catalog, array $cand): array
{
    $res   = cm_score($entry['skills'], $candSkills, $catalog);
    $crit  = $entry['criteria'] ?? [];
    $w     = CM_WEIGHTS;
    $totalW = $w['skills'];
    $earned = $w['skills'] * $res['score'] / 100;
    $rows   = [];

    $pretty = static fn(string $v): string => ucwords(trim(str_replace('_', ' ', $v)));

    /* Qualification: level from the profile and from the names of recorded qualifications */
    $qText = (string)($crit['qual_text'] ?? '');
    $level = trim((string)($cand['qualification'] ?? ''));
    $cr    = cm_qual_candidate_rank($level);
    $names = [];
    foreach (($cand['qualifications'] ?? []) as $q) {
        $qn = trim((string)($q['name'] ?? ''));
        if ($qn === '') continue;
        $names[] = $qn;
        $qr = cm_qual_candidate_rank($qn);
        if ($qr !== null && ($cr === null || $qr > $cr)) $cr = $qr;
    }
    $candQual = implode(' · ', array_filter([
        $level !== '' ? $pretty($level) : '',
        implode('; ', array_slice($names, 0, 3)),
    ]));
    if ($candQual === '') $candQual = 'Not provided';

    if (($crit['qual_rank'] ?? null) !== null) {
        $totalW += $w['qualification'];
        if ($cr === null)                           { $state = 'unknown'; $credit = 0.0; }
        elseif ($cr >= (int)$crit['qual_rank'])     { $state = 'met';     $credit = 1.0; }
        else                                        { $state = 'unmet';   $credit = 0.0; }
        $earned += $w['qualification'] * $credit;
        $rows[] = ['key' => 'qualification', 'label' => 'Qualification', 'state' => $state,
                   'required' => $qText, 'candidate' => $candQual];
    } elseif ($qText !== '') {
        $rows[] = ['key' => 'qualification', 'label' => 'Qualification', 'state' => 'info',
                   'required' => $qText, 'candidate' => $candQual];
    }

    /* Experience */
    $expYears = $crit['exp_years'] ?? null;
    $expText  = (string)($crit['exp_text'] ?? '');
    if ($expYears !== null && $expYears > 0) {
        if (empty($cand['exp_supported'])) {
            $rows[] = ['key' => 'experience', 'label' => 'Experience', 'state' => 'info',
                       'required' => $expText, 'candidate' => 'Not recorded in candidate profiles'];
        } else {
            $totalW += $w['experience'];
            $have = $cand['experience'] ?? null;
            if ($have === null) {
                $state = 'unknown'; $credit = 0.0; $haveText = 'Not provided';
            } else {
                $have     = (float)$have;
                $credit   = min(1.0, $have / (float)$expYears);
                $state    = $have >= (float)$expYears ? 'met' : 'unmet';
                $haveText = rtrim(rtrim(number_format($have, 1, '.', ''), '0'), '.') . ' years';
            }
            $earned += $w['experience'] * $credit;
            $rows[] = ['key' => 'experience', 'label' => 'Experience', 'state' => $state,
                       'required' => $expText, 'candidate' => $haveText];
        }
    } elseif ($expText !== '') {
        $rows[] = ['key' => 'experience', 'label' => 'Experience', 'state' => 'info',
                   'required' => $expText, 'candidate' => '—'];
    }

    /* Location (province); not applicable to remote vacancies */
    $prov = (string)($crit['province'] ?? '');
    if ($prov !== '' && empty($crit['remote'])) {
        $cp = (string)($cand['province'] ?? '');
        $totalW += $w['location'];
        if (cm_norm_place($cp) === '')       { $state = 'unknown'; $credit = 0.0; }
        elseif (cm_same_place($cp, $prov))   { $state = 'met';     $credit = 1.0; }
        else                                 { $state = 'unmet';   $credit = 0.0; }
        $earned += $w['location'] * $credit;
        $rows[] = ['key' => 'location', 'label' => 'Location', 'state' => $state,
                   'required' => $pretty($prov), 'candidate' => cm_norm_place($cp) !== '' ? $pretty($cp) : 'Not provided'];
    }

    /* Availability: candidate must be free by the vacancy start (or today, if the start has passed) */
    $startTs = cm_parse_ts((string)($crit['start_date'] ?? ''));
    $aText   = (string)($crit['avail_text'] ?? '');
    $availTs = cm_parse_ts((string)($cand['availability'] ?? ''));
    if ($startTs !== null) {
        $totalW += $w['availability'];
        $target  = max($startTs, strtotime('today'));
        if ($availTs === null)          { $state = 'unknown'; $credit = 0.0; }
        elseif ($availTs <= $target)    { $state = 'met';     $credit = 1.0; }
        else                            { $state = 'unmet';   $credit = 0.0; }
        $earned += $w['availability'] * $credit;
        $rows[] = ['key' => 'availability', 'label' => 'Availability', 'state' => $state,
                   'required' => 'Start ' . date('d M Y', $startTs) . ($aText !== '' ? ' · ' . $aText : ''),
                   'candidate' => $availTs !== null ? 'From ' . date('d M Y', $availTs) : 'Not provided'];
    } elseif ($aText !== '') {
        $rows[] = ['key' => 'availability', 'label' => 'Availability', 'state' => 'info',
                   'required' => $aText, 'candidate' => $availTs !== null ? 'From ' . date('d M Y', $availTs) : 'Not provided'];
    }

    /* Other requirements: shown for the recruiter to judge, never scored */
    $oText = (string)($crit['other_text'] ?? '');
    if ($oText !== '') {
        $rows[] = ['key' => 'other', 'label' => 'Other requirements', 'state' => 'info', 'required' => $oText, 'candidate' => '—'];
    }

    $res['skill_score'] = $res['score'];
    $res['score']       = $totalW > 0 ? (int)round($earned / $totalW * 100) : 0;
    $res['criteria']    = $rows;
    return $res;
}

/* ==================================================================
 | Data loading (read-only)
 * ==================================================================*/

/** Consent rule identical to candidate_view.php, for a users alias `u`. */
function cm_consent_sql(): string
{
    return "EXISTS (
        SELECT 1 FROM candidate_consents cc
        WHERE cc.candidate_id = u.id
          AND cc.consent_type = 'recruiter_discovery'
          AND cc.status = 'consented'
          AND (cc.expires_at IS NULL OR cc.expires_at > NOW())
    )";
}

/** Active skill taxonomy: [skill_id => ['id','name','category']]. */
function cm_load_catalog(mysqli $conn): array
{
    $out = [];
    if (!rc_has_table($conn, 'skills')) return $out;

    $res = $conn->query('SELECT id, name, category FROM skills WHERE is_active = 1 ORDER BY name ASC');
    if (!$res) return $out;
    while ($r = $res->fetch_assoc()) {
        $name = trim((string)$r['name']);
        if ($name === '') continue;
        $out[(int)$r['id']] = ['id' => (int)$r['id'], 'name' => $name, 'category' => (string)($r['category'] ?? '')];
    }
    $res->free();
    return $out;
}

/** Column names (and data types) of a table in the current database: [name => data_type]. */
function cm_table_columns(mysqli $conn, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    $cache[$table] = [];
    $stmt = $conn->prepare(
        'SELECT COLUMN_NAME AS name, DATA_TYPE AS dtype FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    if (!$stmt) return $cache[$table];
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($r = $rs->fetch_assoc()) $cache[$table][(string)$r['name']] = strtolower((string)$r['dtype']);
    $stmt->close();
    return $cache[$table];
}

/** Numeric years-of-experience column on candidate_profiles or users, if the project has one: ['alias','col']. */
function cm_experience_source(mysqli $conn): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;

    $cache = null;
    $sources = [];
    if (rc_has_table($conn, 'candidate_profiles')) $sources['cp'] = cm_table_columns($conn, 'candidate_profiles');
    $sources['u'] = cm_table_columns($conn, 'users');

    foreach ($sources as $alias => $cols) {
        foreach (CM_EXPERIENCE_COLUMNS as $name) {
            if (isset($cols[$name]) && in_array($cols[$name], CM_NUMERIC_TYPES, true)) {
                $cache = ['alias' => $alias, 'col' => $name];
                return $cache;
            }
        }
    }
    return $cache;
}

/**
 * Candidate-owned child table (work experience, qualifications) found by its columns, since the
 * table names are not known. Returns ['table' => ..., 'owner' => user_id|candidate_id] or null.
 */
function cm_find_owner_table(mysqli $conn, array $required, string $hint): ?array
{
    static $cache = [];
    $key = $hint;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $cache[$key] = null;

    $want = array_values(array_unique(array_merge($required, ['user_id', 'candidate_id'])));
    $in   = "'" . implode("','", array_map(static fn($c) => str_replace("'", '', $c), $want)) . "'";
    $res  = $conn->query(
        "SELECT TABLE_NAME AS tname, COLUMN_NAME AS cname FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN ($in)"
    );
    $tables = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $t = (string)$r['tname'];
            if (preg_match('/^[A-Za-z0-9_]+$/', $t)) $tables[$t][] = (string)$r['cname'];
        }
        $res->free();
    }

    $best = null;
    $bestScore = -1;
    foreach ($tables as $t => $cols) {
        if (stripos($t, 'application') !== false) continue; // per-application snapshots, not the profile
        if (array_diff($required, $cols)) continue;
        $owner = in_array('user_id', $cols, true) ? 'user_id' : (in_array('candidate_id', $cols, true) ? 'candidate_id' : null);
        if ($owner === null) continue;
        $score = (stripos($t, 'candidate') !== false ? 2 : 0) + (stripos($t, $hint) !== false ? 1 : 0);
        if ($score > $bestScore) { $best = ['table' => $t, 'owner' => $owner]; $bestScore = $score; }
    }
    $cache[$key] = $best;
    return $best;
}

/**
 * Years of work experience per consented candidate, from their work history:
 * [user_id => years]. Overlapping jobs are counted once; a job with no end date counts only
 * when marked current. Null when the project has no work-experience table.
 */
function cm_experience_years_by_user(mysqli $conn): ?array
{
    $src = cm_find_owner_table($conn, ['job_title', 'company', 'start_date'], 'experience');
    if ($src === null) return null;

    $cols   = cm_table_columns($conn, $src['table']);
    $endSel = isset($cols['end_date'])   ? 't.end_date AS end_date'     : 'NULL AS end_date';
    $curSel = isset($cols['is_current']) ? 't.is_current AS is_current' : '0 AS is_current';

    $sql = "SELECT t.`{$src['owner']}` AS uid, t.start_date AS start_date, $endSel, $curSel
            FROM `{$src['table']}` t
            INNER JOIN users u ON u.id = t.`{$src['owner']}` AND u.status = 'active'
            WHERE " . cm_consent_sql();
    $res = $conn->query($sql);
    if (!$res) return null;

    $today = strtotime('today');
    $spans = [];
    while ($r = $res->fetch_assoc()) {
        $start = cm_parse_ts($r['start_date']);
        if ($start === null) continue;
        $end = cm_truthy($r['is_current']) ? $today : cm_parse_ts($r['end_date']);
        if ($end === null) continue;
        $end = min($end, $today);
        if ($end <= $start) continue;
        $spans[(int)$r['uid']][] = [$start, $end];
    }
    $res->free();

    $out = [];
    foreach ($spans as $uid => $list) {
        usort($list, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $secs = 0;
        $cs = null;
        $ce = null;
        foreach ($list as [$st, $en]) {
            if ($cs === null)      { $cs = $st; $ce = $en; }
            elseif ($st <= $ce)    { $ce = max($ce, $en); }
            else                   { $secs += $ce - $cs; $cs = $st; $ce = $en; }
        }
        if ($cs !== null) $secs += $ce - $cs;
        $out[$uid] = round($secs / (365.25 * 86400), 2);
    }
    return $out;
}

/** Qualification records per consented candidate: [user_id => [['name','institution','year'], ...]]. */
function cm_qualifications_by_user(mysqli $conn): array
{
    $src = cm_find_owner_table($conn, ['name', 'institution', 'year_completed'], 'qualification');
    if ($src === null) return [];

    $sql = "SELECT t.`{$src['owner']}` AS uid, t.`name` AS qname, t.institution AS inst, t.year_completed AS yr
            FROM `{$src['table']}` t
            INNER JOIN users u ON u.id = t.`{$src['owner']}` AND u.status = 'active'
            WHERE " . cm_consent_sql() . "
            ORDER BY t.year_completed DESC";
    $res = $conn->query($sql);
    $out = [];
    if (!$res) return $out;
    while ($r = $res->fetch_assoc()) {
        $out[(int)$r['uid']][] = [
            'name'        => trim((string)($r['qname'] ?? '')),
            'institution' => trim((string)($r['inst'] ?? '')),
            'year'        => trim((string)($r['yr'] ?? '')),
        ];
    }
    $res->free();
    return $out;
}

/** Active candidates with valid recruiter-discovery consent: [user_id => profile row]. */
function cm_load_candidates(mysqli $conn): array
{
    $out = [];
    foreach (['users', 'roles', 'candidate_consents'] as $t) {
        if (!rc_has_table($conn, $t)) return $out;
    }

    $hasProfile  = rc_has_table($conn, 'candidate_profiles');
    $profileSel  = $hasProfile
        ? 'cp.professional_title AS profile_title, cp.city AS city, cp.availability_date AS availability_date'
        : 'NULL AS profile_title, NULL AS city, NULL AS availability_date';
    $profileJoin = $hasProfile ? 'LEFT JOIN candidate_profiles cp ON cp.user_id = u.id' : '';

    $exp    = cm_experience_source($conn);
    $expSel = $exp !== null ? ', ' . $exp['alias'] . '.`' . $exp['col'] . '` AS experience' : ', NULL AS experience';

    $sql = "SELECT u.id, u.first_name, u.last_name, u.province, u.qualification_level,
                   u.professional_title AS user_title, $profileSel $expSel
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            $profileJoin
            WHERE r.slug = 'candidate'
              AND u.status = 'active'
              AND " . cm_consent_sql() . "
            ORDER BY u.last_name ASC, u.first_name ASC, u.id ASC";

    $res = $conn->query($sql);
    if (!$res) return $out;

    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['id'];
        if (isset($out[$id])) continue; // duplicate profile rows

        $first = trim((string)($r['first_name'] ?? ''));
        $last  = trim((string)($r['last_name'] ?? ''));
        $name  = trim($first . ' ' . $last);
        $title = trim((string)($r['profile_title'] ?? ''));
        if ($title === '') $title = trim((string)($r['user_title'] ?? ''));

        $out[$id] = [
            'id'            => $id,
            'first'         => $first,
            'last'          => $last,
            'name'          => $name !== '' ? $name : ('Candidate #' . $id),
            'title'         => $title,
            'province'      => trim((string)($r['province'] ?? '')),
            'city'          => trim((string)($r['city'] ?? '')),
            'qualification' => trim((string)($r['qualification_level'] ?? '')),
            'qualifications' => [],
            'availability'  => trim((string)($r['availability_date'] ?? '')),
            'exp_supported' => $exp !== null,
            'experience'    => ($exp !== null && $r['experience'] !== null && $r['experience'] !== '') ? (float)$r['experience'] : null,
        ];
    }
    $res->free();

    /* Work history and qualification records, when the project stores them */
    $years = cm_experience_years_by_user($conn);
    if ($years !== null) {
        foreach ($out as $id => $_) {
            $out[$id]['exp_supported'] = true;
            $out[$id]['experience']    = $years[$id] ?? 0.0;
        }
    }
    foreach (cm_qualifications_by_user($conn) as $uid => $list) {
        if (isset($out[$uid])) $out[$uid]['qualifications'] = $list;
    }

    return $out;
}

/**
 * Skills of consented candidates: [user_id => [skill_id => ['status','proficiency']]].
 * Pass a user id to load just one candidate. If a skill is recorded twice the better status wins.
 */
function cm_load_candidate_skills(mysqli $conn, ?int $userId = null): array
{
    $out = [];
    foreach (['candidate_skills', 'skills', 'users', 'roles', 'candidate_consents'] as $t) {
        if (!rc_has_table($conn, $t)) return $out;
    }

    $sql = "SELECT cs.user_id, cs.skill_id, cs.proficiency, cs.verification_status
            FROM candidate_skills cs
            INNER JOIN skills s ON s.id = cs.skill_id AND s.is_active = 1
            INNER JOIN users u  ON u.id = cs.user_id AND u.status = 'active'
            INNER JOIN roles r  ON r.id = u.role_id AND r.slug = 'candidate'
            WHERE " . cm_consent_sql();
    if ($userId !== null) $sql .= ' AND cs.user_id = ?';

    $stmt = $conn->prepare($sql);
    if (!$stmt) return $out;
    if ($userId !== null) $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $uid    = (int)$r['user_id'];
        $sid    = (int)$r['skill_id'];
        $status = cm_norm_status($r['verification_status'] ?? '');

        $existing = $out[$uid][$sid] ?? null;
        if ($existing !== null && CM_STATUS_RANK[$existing['status']] >= CM_STATUS_RANK[$status]) continue;

        $out[$uid][$sid] = ['status' => $status, 'proficiency' => $r['proficiency'] ?? null];
    }
    $stmt->close();
    return $out;
}

/** Vacancies (latest 1000, as vacancies.php does). */
function cm_load_vacancies(mysqli $conn): array
{
    $out = [];
    if (!rc_has_table($conn, 'opportunities')) return $out;

    $res = $conn->query('SELECT * FROM opportunities ORDER BY id DESC LIMIT 1000');
    if (!$res) return $out;
    while ($r = $res->fetch_assoc()) $out[(int)$r['id']] = $r;
    $res->free();
    return $out;
}

/** Open for applications: open status and closing date not in the past. */
function cm_is_open(array $v): bool
{
    if (!in_array((string)($v['status'] ?? ''), VM_OPEN_STATUSES, true)) return false;
    $d = vm_days_until($v['application_close_date'] ?? null);
    return $d === null || $d >= 0;
}

/** Column of `applications` that holds the candidate's user id, or null. */
function cm_applicant_column(mysqli $conn): ?string
{
    static $cache = false;
    if ($cache !== false) return $cache;

    $cache = null;
    if (!rc_has_table($conn, 'applications')) return $cache;

    $names = [];
    $res = $conn->query(
        "SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications'"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
        $res->free();
    }
    foreach (['candidate_id', 'user_id', 'applicant_id'] as $c) {
        if (in_array($c, $names, true)) { $cache = $c; break; }
    }
    return $cache;
}

/**
 * Existing applications (drafts excluded): [opportunity_id => [user_id => status]].
 * Filter by vacancy or by candidate. The most recently updated application wins.
 */
function cm_applications(mysqli $conn, ?int $opportunityId, ?int $userId): array
{
    $out = [];
    $col = cm_applicant_column($conn);
    if ($col === null) return $out;

    $names = [];
    $res = $conn->query(
        "SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications'"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
        $res->free();
    }
    $orderParts = array_values(array_intersect(['updated_at', 'submitted_at', 'created_at'], $names));

    $sql = "SELECT opportunity_id AS oid, `$col` AS uid, status
            FROM applications
            WHERE status <> 'draft' AND opportunity_id IS NOT NULL AND `$col` IS NOT NULL";
    $types  = '';
    $params = [];
    if ($opportunityId !== null) { $sql .= ' AND opportunity_id = ?'; $types .= 'i'; $params[] = $opportunityId; }
    if ($userId !== null)        { $sql .= " AND `$col` = ?";         $types .= 'i'; $params[] = $userId; }
    if ($orderParts) $sql .= ' ORDER BY COALESCE(' . implode(', ', $orderParts) . ') ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) return $out;
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($r = $rs->fetch_assoc()) {
        $out[(int)$r['oid']][(int)$r['uid']] = (string)$r['status'];
    }
    $stmt->close();
    return $out;
}

/**
 * Tables that carry an opportunity_id, with their columns: [table => [column, ...]].
 * Used to find the vacancy skills table and the eligibility table without guessing their names.
 */
function cm_opportunity_tables(mysqli $conn): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    $res = $conn->query(
        "SELECT TABLE_NAME AS tname, COLUMN_NAME AS cname
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME NOT IN ('applications', 'candidate_skills')
           AND TABLE_NAME IN (
               SELECT TABLE_NAME FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'opportunity_id'
           )"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $t = (string)$r['tname'];
            if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) continue;
            $cache[$t][] = (string)$r['cname'];
        }
        $res->free();
    }
    return $cache;
}

/**
 * Table listing a vacancy's skills (opportunity_id + skill_name and/or skill_id), as shown
 * on candidate/opportunity_detail.php under "Required Skills". Null if the project has none.
 */
function cm_link_table(mysqli $conn): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;

    $cache = null;
    $found = [];
    foreach (cm_opportunity_tables($conn) as $t => $cols) {
        if (in_array('skill_name', $cols, true) || in_array('skill_id', $cols, true)) $found[$t] = $cols;
    }
    if (!$found) return $cache;

    $table = null;
    foreach (CM_LINK_TABLES as $preferred) {
        if (isset($found[$preferred])) { $table = $preferred; break; }
    }
    if ($table === null) {
        foreach ($found as $t => $cols) {
            if (stripos($t, 'skill') !== false && stripos($t, 'candidate') === false) { $table = $t; break; }
        }
    }
    if ($table === null) return $cache;

    $cols = $found[$table];
    $cache = [
        'table'        => $table,
        'has_id'       => in_array('skill_id', $cols, true),
        'has_name'     => in_array('skill_name', $cols, true),
        'category_col' => in_array('skill_category', $cols, true) ? 'skill_category' : null,
        'required_col' => null,
    ];
    foreach (CM_REQUIRED_COLUMNS as $c) {
        if (in_array($c, $cols, true)) { $cache['required_col'] = $c; break; }
    }
    return $cache;
}

/** Table holding the free-text eligibility fields (required_skills / preferred_skills), or null. */
function cm_eligibility_table(mysqli $conn): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;

    $cache = null;
    foreach (cm_opportunity_tables($conn) as $t => $cols) {
        $req  = in_array('required_skills', $cols, true);
        $pref = in_array('preferred_skills', $cols, true);
        if ($req || $pref) {
            $cache = ['table' => $t, 'required' => $req, 'preferred' => $pref, 'cols' => $cols];
            break;
        }
    }
    return $cache;
}

/**
 * Turn typed skill text ("PHP, React.js; Strong SQL") into taxonomy skill ids.
 * Each comma/semicolon/line separated item is matched by exact name first, then by any
 * taxonomy skill name appearing inside it. Items matching nothing are returned as labels.
 * Returns [int[] skill ids, string[] unmatched labels].
 */
function cm_resolve_skill_text(string $text, array $byName, array $patterns): array
{
    $ids      = [];
    $unmapped = [];
    $items    = preg_split('/[,;\n\r|]+/u', $text) ?: [];

    foreach ($items as $item) {
        $item = cm_clean_text($item);
        if ($item === '') continue;

        $key = mb_strtolower($item);
        if (isset($byName[$key])) { $ids[$byName[$key]] = true; continue; }

        $found = cm_detect_skills($item, $patterns);
        if ($found) {
            foreach ($found as $sid) $ids[$sid] = true;
            continue;
        }
        $unmapped[] = $item;
    }
    return [array_keys($ids), $unmapped];
}

/** Record a vacancy skill that is not in the taxonomy (short label, no duplicates, capped). */
function cm_add_unmapped(array &$entry, string $label): void
{
    $label = mb_strlen($label) > 60 ? mb_substr($label, 0, 57) . '...' : $label;
    if ($label === '' || count($entry['unmapped']) >= 15) return;
    if (!in_array($label, $entry['unmapped'], true)) $entry['unmapped'][] = $label;
}

/** Add a skill to a requirement set; a skill listed twice is mandatory if any entry says so. */
function cm_add_requirement(array &$entry, int $skillId, bool $mandatory): void
{
    $entry['skills'][$skillId] = ($entry['skills'][$skillId] ?? false) || $mandatory;
    $entry['source'] = 'table';
}

/**
 * Required skills for each vacancy:
 * [opportunity_id => ['source' => table|text|none, 'skills' => [skill_id => bool mandatory], 'unmapped' => [names]]]
 *
 * Sources, in order (1 and 2 are combined when both exist):
 *   1. the vacancy skills table  - "preferred_technical" skills are optional, all others mandatory
 *   2. eligibility required_skills (mandatory) / preferred_skills (optional) text
 *   3. skill names in the vacancy's own text columns, only when 1 and 2 found nothing
 * Skills named on a vacancy but absent from the active skills taxonomy cannot be held by any
 * candidate, so they are listed under 'unmapped' and not scored.
 */
function cm_build_requirements(mysqli $conn, array $vacancies, array $catalog, array $oppColumns): array
{
    $out = [];
    foreach ($vacancies as $id => $_) {
        $out[(int)$id] = ['source' => 'none', 'skills' => [], 'unmapped' => []];
    }

    $byName = [];
    foreach ($catalog as $id => $skill) {
        $byName[mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$skill['name']) ?? ''))] = (int)$id;
    }

    $patterns = cm_skill_patterns($catalog);

    /* 1. vacancy skills table */
    $link = cm_link_table($conn);
    if ($link !== null) {
        $sel = ['opportunity_id AS oid'];
        $sel[] = $link['has_id']       ? 'skill_id AS sid'                          : 'NULL AS sid';
        $sel[] = $link['has_name']     ? 'skill_name AS sname'                      : 'NULL AS sname';
        $sel[] = $link['category_col'] !== null ? '`' . $link['category_col'] . '` AS scat' : 'NULL AS scat';
        $sel[] = $link['required_col'] !== null ? '`' . $link['required_col'] . '` AS sreq' : 'NULL AS sreq';

        $res = $conn->query(
            'SELECT ' . implode(', ', $sel) . ' FROM `' . $link['table'] . '` WHERE opportunity_id IS NOT NULL'
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $oid = (int)$r['oid'];
                if (!isset($out[$oid])) continue;

                $ids    = [];
                $labels = [];
                $sid    = (int)($r['sid'] ?? 0);
                if ($sid > 0 && isset($catalog[$sid])) {
                    $ids = [$sid];
                } else {
                    [$ids, $labels] = cm_resolve_skill_text((string)($r['sname'] ?? ''), $byName, $patterns);
                }

                // required_technical / required_soft are mandatory; preferred_technical is optional
                $mandatory = true;
                if ($link['required_col'] !== null) {
                    $mandatory = cm_truthy($r['sreq']);
                } elseif ($link['category_col'] !== null) {
                    $mandatory = strtolower(trim((string)$r['scat'])) !== 'preferred_technical';
                }

                foreach ($ids as $matchedId)  cm_add_requirement($out[$oid], (int)$matchedId, $mandatory);
                foreach ($labels as $label)   cm_add_unmapped($out[$oid], $label);
            }
            $res->free();
        }
    }

    /* 2. eligibility: skill lists (comma-separated text) and the other stated criteria */
    $eligRows = [];
    $elig = cm_eligibility_table($conn);
    if ($elig !== null) {
        $sel = ['opportunity_id AS oid'];
        $sel[] = $elig['required']  ? 'required_skills AS rs'  : 'NULL AS rs';
        $sel[] = $elig['preferred'] ? 'preferred_skills AS ps' : 'NULL AS ps';
        foreach (['qualification_requirements' => 'q', 'min_experience' => 'x',
                  'availability_requirements' => 'v', 'other_requirements' => 'o'] as $col => $alias) {
            $sel[] = in_array($col, $elig['cols'], true) ? '`' . $col . '` AS ' . $alias : 'NULL AS ' . $alias;
        }

        $res = $conn->query(
            'SELECT ' . implode(', ', $sel) . ' FROM `' . $elig['table'] . '` WHERE opportunity_id IS NOT NULL'
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $oid = (int)$r['oid'];
                if (!isset($out[$oid])) continue;

                [$ids, $labels] = cm_resolve_skill_text((string)($r['rs'] ?? ''), $byName, $patterns);
                foreach ($ids as $matchedId) cm_add_requirement($out[$oid], (int)$matchedId, true);
                foreach ($labels as $label)  cm_add_unmapped($out[$oid], $label);

                [$ids, $labels] = cm_resolve_skill_text((string)($r['ps'] ?? ''), $byName, $patterns);
                foreach ($ids as $matchedId) cm_add_requirement($out[$oid], (int)$matchedId, false);
                foreach ($labels as $label)  cm_add_unmapped($out[$oid], $label);

                foreach (['qual' => 'q', 'exp' => 'x', 'avail' => 'v', 'other' => 'o'] as $key => $alias) {
                    if (empty($eligRows[$oid][$key]) && trim((string)($r[$alias] ?? '')) !== '') {
                        $eligRows[$oid][$key] = (string)$r[$alias];
                    }
                }
            }
            $res->free();
        }
    }

    /* 3. the vacancy's own text, only where nothing structured was found */
    $textCols = [];
    foreach ($oppColumns as $name => $meta) {
        if (in_array(strtolower((string)$meta['dtype']), CM_TEXT_TYPES, true) && preg_match(CM_TEXT_COLUMN_HINT, (string)$name)) {
            $textCols[] = (string)$name;
        }
    }

    if ($textCols) {
        foreach ($vacancies as $id => $v) {
            $id = (int)$id;
            if ($out[$id]['skills']) continue;

            $parts = [];
            foreach ($textCols as $c) {
                $parts[] = cm_clean_text($v[$c] ?? '');
            }
            foreach (cm_detect_skills(implode("\n", $parts), $patterns) as $sid) {
                $out[$id]['skills'][$sid] = false;
            }
            if ($out[$id]['skills']) $out[$id]['source'] = 'text';
        }
    }

    foreach ($vacancies as $id => $v) {
        $out[(int)$id]['criteria'] = cm_vacancy_criteria($v, $eligRows[(int)$id] ?? []);
    }

    return $out;
}
