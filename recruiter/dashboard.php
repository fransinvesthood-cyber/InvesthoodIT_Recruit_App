<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'dashboard';
$pageTitle   = 'Recruiter Dashboard';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

/* ==================================================================
 | Smarter Shortlists — inline helpers
 * ==================================================================*/

if (!function_exists('dash_has_column')) {
    function dash_has_column(mysqli $conn, string $table, string $column): bool {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $stmt = $conn->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1"
        );
        if (!$stmt) return $cache[$key] = false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $cache[$key] = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $cache[$key];
    }
}

if (!function_exists('dash_consent_status')) {
    function dash_consent_status(mysqli $conn, int $cid): array {
        $out = ['valid' => false, 'status' => 'none'];
        if ($cid <= 0 || !rc_has_table($conn, 'candidate_consents')) return $out;

        $stmt = $conn->prepare(
            "SELECT status, expires_at FROM candidate_consents
             WHERE candidate_id = ?
               AND consent_type = 'recruiter_discovery'
             ORDER BY (status = 'consented') DESC,
                      (expires_at IS NULL) DESC,
                      expires_at DESC
             LIMIT 1"
        );
        if (!$stmt) return $out;

        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return $out;
        $expired = !empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time();
        $out['status'] = (string)$row['status'];
        $out['valid']  = ($row['status'] === 'consented') && !$expired;
        return $out;
    }
}

if (!function_exists('dash_candidate_skills')) {
    function dash_candidate_skills(mysqli $conn, int $cid): array {
        if ($cid <= 0 ||
            !rc_has_table($conn, 'candidate_skills') ||
            !rc_has_table($conn, 'skills')) return [];

        $stmt = $conn->prepare(
            "SELECT s.name, cs.verification_status
             FROM candidate_skills cs
             INNER JOIN skills s ON s.id = cs.skill_id
             WHERE cs.user_id = ? AND s.is_active = 1
             ORDER BY s.name ASC"
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $out[] = [
                'name'     => (string)$r['name'],
                'verified' => strtolower((string)$r['verification_status']) === 'verified',
            ];
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('dash_parse_criteria')) {
    function dash_parse_criteria(?string $json): array {
        $c = json_decode((string)$json, true);
        if (!is_array($c)) $c = [];
        return [
            'skill'    => trim((string)($c['skill']    ?? '')),
            'province' => trim((string)($c['province'] ?? '')),
            'city'     => trim((string)($c['city']     ?? '')),
        ];
    }
}

if (!function_exists('dash_match_candidate')) {
    function dash_match_candidate(array $cand, array $skills, array $criteria): array {
        $index = [];
        foreach ($skills as $s) $index[strtolower($s['name'])] = $s;

        $matched = [];
        $missing = [];

        $skillReq = $criteria['skill'] ?? '';
        if ($skillReq !== '') {
            foreach (preg_split('/[,;|]+/', $skillReq) ?: [] as $part) {
                $part = trim($part);
                if ($part === '') continue;
                $key = strtolower($part);
                if (isset($index[$key])) {
                    $matched[] = ['name' => $index[$key]['name'], 'verified' => $index[$key]['verified']];
                } else {
                    $missing[] = $part;
                }
            }
        }

        $provinceMatch = null;
        if (!empty($criteria['province'])) {
            $provinceMatch = strcasecmp(
                trim((string)($cand['province'] ?? '')),
                $criteria['province']
            ) === 0;
        }

        $cityMatch = null;
        if (!empty($criteria['city'])) {
            $cityMatch = strcasecmp(
                trim((string)($cand['city'] ?? '')),
                $criteria['city']
            ) === 0;
        }

        $verifiedCount = 0;
        foreach ($skills as $s) if ($s['verified']) $verifiedCount++;

        $total = count($matched) + count($missing);
        $score = $total > 0 ? (int)round((count($matched) / $total) * 100) : 100;

        return [
            'matched'        => $matched,
            'missing'        => $missing,
            'province_match' => $provinceMatch,
            'city_match'     => $cityMatch,
            'verified_count' => $verifiedCount,
            'score'          => $score,
            'met'            => empty($missing)
                                && ($provinceMatch !== false)
                                && ($cityMatch !== false),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$stats = [
    'candidates'     => 0,
    'complete'       => 0,
    'consented'      => 0,
    'verifiedSkills' => 0,
    'gauteng'        => 0,
    'opportunities'  => 0,
    'applications'   => 0,
    'shortlists'     => 0,
];

/*
|--------------------------------------------------------------------------
| Modal Data
|--------------------------------------------------------------------------
*/

$modalData = [
    'candidates'     => [],
    'complete'       => [],
    'consented'      => [],
    'verifiedSkills' => [],
    'gauteng'        => [],
    'opportunities'  => [],
    'applications'   => [],
    'shortlists'     => [],
];

/*
|--------------------------------------------------------------------------
| Active Candidate Accounts
|--------------------------------------------------------------------------
|
| Candidate accounts are stored in users and identified through roles.
| candidate_profiles is optional.
|
*/

$hasCandidateProfiles = rc_has_table($conn, 'candidate_profiles');

if ($hasCandidateProfiles) {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province,
            u.qualification_level,
            u.employment_status,

            cp.professional_title AS profile_title,
            cp.city,
            cp.completion_percent,
            cp.is_active AS profile_active

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        LEFT JOIN candidate_profiles cp
            ON cp.user_id = u.id

        WHERE r.slug = 'candidate'
          AND u.status = 'active'

        ORDER BY
            u.first_name ASC,
            u.last_name ASC
    ";

} else {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province,
            u.qualification_level,
            u.employment_status,

            NULL AS profile_title,
            NULL AS city,
            0 AS completion_percent,
            0 AS profile_active

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        WHERE r.slug = 'candidate'
          AND u.status = 'active'

        ORDER BY
            u.first_name ASC,
            u.last_name ASC
    ";
}

$result = $conn->query($sql);

if (!$result) {
    throw new RuntimeException(
        'Candidate dashboard query failed: ' . $conn->error
    );
}

while ($row = $result->fetch_assoc()) {

    $modalData['candidates'][] = $row;

    /*
    |--------------------------------------------------------------------------
    | Complete Profile
    |--------------------------------------------------------------------------
    */

    if ((int)($row['completion_percent'] ?? 0) >= 100) {
        $modalData['complete'][] = $row;
    }

    /*
    |--------------------------------------------------------------------------
    | Gauteng Candidate
    |--------------------------------------------------------------------------
    |
    | Province belongs to users.
    |
    */

    if (
        strtolower(
            trim((string)($row['province'] ?? ''))
        ) === 'gauteng'
    ) {
        $modalData['gauteng'][] = $row;
    }
}

$result->free();

$stats['candidates'] = count($modalData['candidates']);
$stats['complete']   = count($modalData['complete']);
$stats['gauteng']    = count($modalData['gauteng']);

/*
|--------------------------------------------------------------------------
| Consent Eligible Candidates
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'candidate_consents')) {

    if ($hasCandidateProfiles) {

        $sql = "
            SELECT DISTINCT
                u.id,
                u.first_name,
                u.last_name,
                u.professional_title,
                u.province,

                cp.professional_title AS profile_title,
                cp.city,
                cp.completion_percent,

                cc.granted_at,
                cc.expires_at

            FROM candidate_consents cc

            INNER JOIN users u
                ON u.id = cc.candidate_id

            INNER JOIN roles r
                ON r.id = u.role_id

            LEFT JOIN candidate_profiles cp
                ON cp.user_id = u.id

            WHERE r.slug = 'candidate'
              AND u.status = 'active'
              AND cc.consent_type = 'recruiter_discovery'
              AND cc.status = 'consented'
              AND (
                    cc.expires_at IS NULL
                    OR cc.expires_at > NOW()
                  )

            ORDER BY
                u.first_name ASC,
                u.last_name ASC
        ";

    } else {

        $sql = "
            SELECT DISTINCT
                u.id,
                u.first_name,
                u.last_name,
                u.professional_title,
                u.province,

                NULL AS profile_title,
                NULL AS city,
                0 AS completion_percent,

                cc.granted_at,
                cc.expires_at

            FROM candidate_consents cc

            INNER JOIN users u
                ON u.id = cc.candidate_id

            INNER JOIN roles r
                ON r.id = u.role_id

            WHERE r.slug = 'candidate'
              AND u.status = 'active'
              AND cc.consent_type = 'recruiter_discovery'
              AND cc.status = 'consented'
              AND (
                    cc.expires_at IS NULL
                    OR cc.expires_at > NOW()
                  )

            ORDER BY
                u.first_name ASC,
                u.last_name ASC
        ";
    }

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Consent dashboard query failed: ' . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $modalData['consented'][] = $row;
    }

    $result->free();
}

$stats['consented'] = count($modalData['consented']);

/*
|--------------------------------------------------------------------------
| Candidates With Verified Skills
|--------------------------------------------------------------------------
|
| Confirmed schema:
| candidate_skills.user_id
| candidate_skills.skill_id
| candidate_skills.proficiency
| candidate_skills.verification_status
| candidate_skills.verified_at
|
| skills.name
| skills.category
| skills.is_active
|
*/

if (
    rc_has_table($conn, 'candidate_skills') &&
    rc_has_table($conn, 'skills')
) {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province,

            COUNT(DISTINCT cs.skill_id)
                AS verified_skill_count,

            GROUP_CONCAT(
                DISTINCT s.name
                ORDER BY s.name ASC
                SEPARATOR ', '
            ) AS verified_skills

        FROM candidate_skills cs

        INNER JOIN skills s
            ON s.id = cs.skill_id
           AND s.is_active = 1

        INNER JOIN users u
            ON u.id = cs.user_id

        INNER JOIN roles r
            ON r.id = u.role_id

        WHERE cs.verification_status = 'verified'
          AND r.slug = 'candidate'
          AND u.status = 'active'

        GROUP BY
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province

        ORDER BY
            verified_skill_count DESC,
            u.first_name ASC,
            u.last_name ASC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Verified skills dashboard query failed: ' . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $modalData['verifiedSkills'][] = $row;
    }

    $result->free();
}

$stats['verifiedSkills'] = count($modalData['verifiedSkills']);

/*
|--------------------------------------------------------------------------
| Published Opportunities
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'opportunities')) {

    $sql = "
        SELECT
            id,
            title,
            organisation,
            type,
            province,
            city,
            application_open_date,
            application_close_date,
            available_positions,
            status

        FROM opportunities

        WHERE status IN (
            'published',
            'closing_soon'
        )

        ORDER BY
            application_close_date ASC,
            id DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Opportunity dashboard query failed: ' . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $modalData['opportunities'][] = $row;
    }

    $result->free();
}

$stats['opportunities'] = count($modalData['opportunities']);

/*
|--------------------------------------------------------------------------
| Submitted Applications
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'applications')) {

    $sql = "
        SELECT
            a.id,
            a.application_reference,
            a.candidate_id,
            a.opportunity_id,
            a.status,
            a.created_at,
            a.submitted_at,

            u.first_name,
            u.last_name,

            o.title AS opportunity_title

        FROM applications a

        LEFT JOIN users u
            ON u.id = a.candidate_id

        LEFT JOIN opportunities o
            ON o.id = a.opportunity_id

        WHERE a.status <> 'draft'

        ORDER BY
            COALESCE(a.submitted_at, a.created_at) DESC,
            a.id DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Application dashboard query failed: ' . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $modalData['applications'][] = $row;
    }

    $result->free();
}

$stats['applications'] = count($modalData['applications']);

/*
|--------------------------------------------------------------------------
| Recruiter's Shortlists
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'recruiter_shortlists')) {

    $hasShortlistCandidates =
        rc_has_table($conn, 'recruiter_shortlist_candidates');

    if ($hasShortlistCandidates) {

        $sql = "
            SELECT
                rs.id,
                rs.name,
                rs.client_name,
                rs.search_criteria_json,
                rs.status,
                rs.taxonomy_version,
                rs.created_at,
                rs.updated_at,

                COUNT(
                    DISTINCT rsc.id
                ) AS candidate_count

            FROM recruiter_shortlists rs

            LEFT JOIN recruiter_shortlist_candidates rsc
                ON rsc.shortlist_id = rs.id

            WHERE rs.recruiter_id = ?

            GROUP BY
                rs.id,
                rs.name,
                rs.client_name,
                rs.search_criteria_json,
                rs.status,
                rs.taxonomy_version,
                rs.created_at,
                rs.updated_at

            ORDER BY
                rs.updated_at DESC,
                rs.id DESC
        ";

    } else {

        $sql = "
            SELECT
                rs.id,
                rs.name,
                rs.client_name,
                rs.search_criteria_json,
                rs.status,
                rs.taxonomy_version,
                rs.created_at,
                rs.updated_at,

                0 AS candidate_count

            FROM recruiter_shortlists rs

            WHERE rs.recruiter_id = ?

            GROUP BY
                rs.id,
                rs.name,
                rs.client_name,
                rs.search_criteria_json,
                rs.status,
                rs.taxonomy_version,
                rs.created_at,
                rs.updated_at

            ORDER BY
                rs.updated_at DESC,
                rs.id DESC
        ";
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Shortlist dashboard query failed: ' . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $modalData['shortlists'][] = $row;
    }

    $stmt->close();
}

$stats['shortlists'] = count($modalData['shortlists']);

/*
|--------------------------------------------------------------------------
| Recruiter / Taxonomy
|--------------------------------------------------------------------------
*/

$firstName = trim(
    (string)($user['first_name'] ?? 'Recruiter')
);

$taxonomy = rc_taxonomy_version($conn);

/*
|--------------------------------------------------------------------------
| Inline CSV export — dashboard.php?export_shortlist=ID
|--------------------------------------------------------------------------
*/

$exportShortlistId = (int)($_GET['export_shortlist'] ?? 0);

if ($exportShortlistId > 0
    && rc_has_table($conn, 'recruiter_shortlists')
    && rc_has_table($conn, 'recruiter_shortlist_candidates')) {

    $stmt = $conn->prepare(
        "SELECT id, name, client_name, search_criteria_json, taxonomy_version
         FROM recruiter_shortlists
         WHERE id = ? AND recruiter_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('ii', $exportShortlistId, $recruiterId);
    $stmt->execute();
    $exportSl = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$exportSl) {
        http_response_code(403);
        exit('Shortlist not found or access denied.');
    }

    $stmt = $conn->prepare(
        "SELECT rsc.candidate_id AS user_id,
                u.first_name, u.last_name, u.province,
                u.qualification_level, u.professional_title, u.employment_status
         FROM recruiter_shortlist_candidates rsc
         INNER JOIN users u ON u.id = rsc.candidate_id
         INNER JOIN roles r ON r.id = u.role_id
         WHERE rsc.shortlist_id = ? AND r.slug = 'candidate'
         ORDER BY rsc.added_at DESC"
    );
    $stmt->bind_param('i', $exportShortlistId);
    $stmt->execute();
    $exportRes = $stmt->get_result();
    $exportCandidates = [];
    while ($r = $exportRes->fetch_assoc()) $exportCandidates[] = $r;
    $stmt->close();

    $criteria  = dash_parse_criteria($exportSl['search_criteria_json'] ?? null);
    $rowsOut   = [];
    $blocked   = 0;

    foreach ($exportCandidates as $c) {
        $cid     = (int)$c['user_id'];
        $consent = dash_consent_status($conn, $cid);
        if (!$consent['valid']) { $blocked++; continue; }

        $skills = dash_candidate_skills($conn, $cid);
        $match  = dash_match_candidate($c, $skills, $criteria);

        $verified = [];
        foreach ($skills as $s) if ($s['verified']) $verified[] = $s['name'];

        $rowsOut[] = [
            'Name'               => trim($c['first_name'] . ' ' . $c['last_name']),
            'Professional Title' => (string)$c['professional_title'],
            'Province'           => (string)$c['province'],
            'Qualification'      => (string)$c['qualification_level'],
            'Employment Status'  => (string)$c['employment_status'],
            'Verified Skills'    => implode(' | ', $verified),
            'Match %'            => $match['score'],
            'Meets Criteria'     => $match['met'] ? 'Yes' : 'No',
            'Missing Skills'     => implode(' | ', $match['missing']),
        ];
    }

    rc_activity(
        $conn,
        $recruiterId,
        'shortlist_export',
        'Exported shortlist "' . $exportSl['name'] . '" — '
            . count($rowsOut) . ' exported, '
            . $blocked . ' blocked (no consent)',
        'recruiter_shortlist',
        $exportShortlistId
    );

    $safe = trim(
        preg_replace('/[^A-Za-z0-9\-_]+/', '_', (string)$exportSl['name']),
        '_'
    );
    if ($safe === '') $safe = 'shortlist';
    $filename = 'shortlist_' . $safe . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Shortlist', $exportSl['name']]);
    fputcsv($out, ['Client',    $exportSl['client_name'] ?: '—']);
    fputcsv($out, ['Taxonomy',  $exportSl['taxonomy_version']]);
    fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Notice',
        'Only candidates with valid recruiter-discovery consent are included. '
        . 'Candidates without consent were excluded (' . $blocked . ').']);
    fputcsv($out, []);

    if ($rowsOut) {
        fputcsv($out, array_keys($rowsOut[0]));
        foreach ($rowsOut as $row) {
            foreach ($row as $k => $v) {
                $v = (string)$v;
                if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
                    $v = "'" . $v;
                }
                $row[$k] = $v;
            }
            fputcsv($out, $row);
        }
    } else {
        fputcsv($out, ['No candidates with valid consent to export.']);
    }

    fclose($out);
    exit;
}

/*
|--------------------------------------------------------------------------
| Inline compare — dashboard.php?compare_shortlist=ID#compare
|--------------------------------------------------------------------------
*/

$compareShortlistId = (int)($_GET['compare_shortlist'] ?? 0);
$compareShortlist   = null;
$compareCandidates  = [];

if ($compareShortlistId > 0
    && rc_has_table($conn, 'recruiter_shortlists')
    && rc_has_table($conn, 'recruiter_shortlist_candidates')) {

    $stmt = $conn->prepare(
        "SELECT id, name, client_name, search_criteria_json, taxonomy_version
         FROM recruiter_shortlists
         WHERE id = ? AND recruiter_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('ii', $compareShortlistId, $recruiterId);
    $stmt->execute();
    $compareShortlist = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($compareShortlist) {

        $criteriaCmp = dash_parse_criteria($compareShortlist['search_criteria_json'] ?? null);

        $stmt = $conn->prepare(
            "SELECT rsc.candidate_id AS user_id,
                    u.first_name, u.last_name, u.province,
                    u.qualification_level, u.professional_title
             FROM recruiter_shortlist_candidates rsc
             INNER JOIN users u ON u.id = rsc.candidate_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE rsc.shortlist_id = ? AND r.slug = 'candidate'
             ORDER BY rsc.added_at DESC"
        );
        $stmt->bind_param('i', $compareShortlistId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($r = $res->fetch_assoc()) {
            $cid     = (int)$r['user_id'];
            $consent = dash_consent_status($conn, $cid);
            $skills  = $consent['valid'] ? dash_candidate_skills($conn, $cid) : [];

            $r['consent_valid'] = $consent['valid'];
            $r['skills']        = $skills;
            $r['match']         = dash_match_candidate($r, $skills, $criteriaCmp);

            $compareCandidates[] = $r;
        }
        $stmt->close();

        usort(
            $compareCandidates,
            fn($a, $b) => $b['match']['score'] <=> $a['match']['score']
        );

        rc_activity(
            $conn,
            $recruiterId,
            'shortlist_compared',
            'Compared ' . count($compareCandidates) . ' candidates in shortlist #' . $compareShortlistId,
            'recruiter_shortlist',
            $compareShortlistId
        );
    }
}

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';
?>


<!-- ================================================================
     PIPELINE SHORTCUT
================================================================ -->

<section class="rc-pipeline-shortcut" aria-labelledby="rcPipelineShortcutTitle">
    <div class="rc-pipeline-shortcut__copy">
        <span class="rc-eyebrow">Recruitment operations</span>
        <h2 id="rcPipelineShortcutTitle">Candidate Follow-Up &amp; Pipeline</h2>
        <p>Review submitted applications by recruitment stage, spot applications that may need follow-up, and open the existing candidate records.</p>
    </div>
    <a class="rc-pipeline-shortcut__button" href="<?= url('recruiter/follow_up.php') ?>">
        Open follow-up pipeline <i class="fas fa-arrow-right" aria-hidden="true"></i>
    </a>
</section>


<!-- ================================================================
     DASHBOARD MODAL + CARD STYLES
================================================================ -->

<style>

.rc-pipeline-shortcut{display:flex;align-items:center;justify-content:space-between;gap:22px;margin:0 0 24px;padding:24px 26px;border:1px solid #dce5f5;border-radius:18px;background:linear-gradient(115deg,#f7faff,#eef3ff)}
.rc-pipeline-shortcut__copy{max-width:760px}.rc-pipeline-shortcut h2{margin:7px 0 8px;font-size:clamp(19px,2vw,25px);color:#172b4d}.rc-pipeline-shortcut p{margin:0;color:#61718a;line-height:1.6}.rc-pipeline-shortcut__button{display:inline-flex;align-items:center;gap:10px;white-space:nowrap;padding:13px 17px;border-radius:11px;background:#2454d8;color:#fff;text-decoration:none;font-weight:700}.rc-pipeline-shortcut__button:hover{background:#183eae}
@media(max-width:700px){.rc-pipeline-shortcut{align-items:flex-start;flex-direction:column}.rc-pipeline-shortcut__button{white-space:normal}}
[data-theme="dark"] .rc-pipeline-shortcut{background:#17243a;border-color:#33435d}. [data-theme="dark"] .rc-pipeline-shortcut h2{color:#f0f5ff}[data-theme="dark"] .rc-pipeline-shortcut p{color:#b6c4da}


.rc-stat-card--interactive {
    position: relative;
    cursor: pointer;
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.rc-stat-card--interactive:hover {
    transform: translateY(-4px);
}

.rc-stat-card--interactive:focus-visible {
    outline: 3px solid currentColor;
    outline-offset: 3px;
}

.rc-stat-card--interactive small {
    display: block;
    margin-top: 7px;
    font-size: 11px;
    opacity: .65;
}


/* ================================================================
   MODAL
================================================================ */

.rc-dashboard-modal {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.rc-dashboard-modal.is-open {
    display: flex;
}

.rc-dashboard-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .70);
    backdrop-filter: blur(5px);
}

.rc-dashboard-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(780px, 100%);
    max-height: min(84vh, 850px);

    display: flex;
    flex-direction: column;

    overflow: hidden;

    border-radius: 24px;

    background: #ffffff;

    box-shadow:
        0 25px 80px
        rgba(0, 0, 0, .28);
}

.rc-dashboard-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    padding: 24px 26px 18px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .16);
}

.rc-dashboard-modal__header h3 {
    margin: 5px 0 4px;
}

.rc-dashboard-modal__header p {
    margin: 0;
    opacity: .70;
}

.rc-dashboard-modal__close {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border: 0;
    border-radius: 50%;

    cursor: pointer;

    background:
        rgba(15, 23, 42, .07);

    color: inherit;
}

.rc-dashboard-modal__body {
    flex: 1;

    padding: 20px 26px;

    overflow-y: auto;
}

.rc-dashboard-modal__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    padding: 16px 26px;

    border-top:
        1px solid
        rgba(127, 127, 127, .16);
}

.rc-modal-panel {
    display: none;
}

.rc-modal-panel.is-active {
    display: block;
}


/* ================================================================
   MODAL ROWS
================================================================ */

.rc-modal-row {
    display: flex;
    align-items: center;

    gap: 14px;

    padding: 15px 4px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .13);
}

.rc-modal-row:last-child {
    border-bottom: 0;
}

.rc-modal-row__icon {
    width: 44px;
    height: 44px;

    flex: 0 0 44px;

    display: grid;
    place-items: center;

    border-radius: 13px;

    background:
        rgba(127, 127, 127, .10);
}

.rc-modal-row__content {
    flex: 1;
    min-width: 0;
}

.rc-modal-row__content strong,
.rc-modal-row__content span,
.rc-modal-row__content small {
    display: block;
}

.rc-modal-row__content strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rc-modal-row__content span {
    margin-top: 3px;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rc-modal-row__content small {
    margin-top: 4px;
    opacity: .65;
}

.rc-modal-row__action {
    width: 40px;
    height: 40px;

    flex: 0 0 40px;

    display: grid;
    place-items: center;

    border-radius: 11px;

    text-decoration: none;

    background:
        rgba(127, 127, 127, .09);
}

.rc-modal-row__action:hover {
    transform: translateX(2px);
}


/* ================================================================
   EMPTY STATE
================================================================ */

.rc-modal-empty {
    min-height: 230px;

    display: flex;
    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 10px;

    text-align: center;
}

.rc-modal-empty > i {
    font-size: 36px;
    opacity: .55;
}

.rc-modal-empty strong {
    font-size: 17px;
}

.rc-modal-empty span {
    max-width: 430px;
    opacity: .70;
}


/* ================================================================
   BADGES
================================================================ */

.rc-modal-badge {
    display: inline-flex !important;
    align-items: center;

    width: fit-content;

    margin-top: 6px !important;

    padding: 4px 8px;

    border-radius: 999px;

    font-size: 11px;

    background:
        rgba(127, 127, 127, .10);
}


/* ================================================================
   BODY LOCK
================================================================ */

body.rc-modal-open {
    overflow: hidden;
}


/* ================================================================
   DARK MODE
================================================================ */

body.dark-mode .rc-dashboard-modal__dialog,
html[data-theme="dark"] .rc-dashboard-modal__dialog {
    background: #111827;
    color: #f8fafc;
}

body.dark-mode .rc-dashboard-modal__close,
html[data-theme="dark"] .rc-dashboard-modal__close {
    background:
        rgba(255, 255, 255, .08);

    color: #f8fafc;
}

body.dark-mode .rc-modal-row__icon,
html[data-theme="dark"] .rc-modal-row__icon,
body.dark-mode .rc-modal-row__action,
html[data-theme="dark"] .rc-modal-row__action,
body.dark-mode .rc-modal-badge,
html[data-theme="dark"] .rc-modal-badge {
    background:
        rgba(255, 255, 255, .08);
}


/* ================================================================
   RESPONSIVE
================================================================ */

@media (max-width: 768px) {

    .rc-dashboard-modal {
        padding: 12px;
    }

    .rc-dashboard-modal__dialog {
        width: 100%;
        max-height: 91vh;
        border-radius: 18px;
    }

    .rc-dashboard-modal__header {
        padding: 19px;
    }

    .rc-dashboard-modal__body {
        padding: 14px 19px;
    }

    .rc-dashboard-modal__footer {
        padding: 14px 19px;
    }

    .rc-modal-row {
        gap: 11px;
    }

    .rc-modal-row__icon {
        width: 40px;
        height: 40px;
        flex-basis: 40px;
    }

}


/* ================================================================
   SMARTER SHORTLISTS
================================================================ */

.rc-shortlist-smart {
    padding: 20px 4px;
    border-bottom: 1px solid rgba(127,127,127,.13);
}
.rc-shortlist-smart:last-child { border-bottom: 0; }

.rc-shortlist-smart__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 14px;
}
.rc-shortlist-smart__head strong { display: block; font-size: 16px; }
.rc-shortlist-smart__head span  { display: block; opacity: .7; font-size: 12px; margin-top: 3px; }

.rc-shortlist-smart__metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}
.rc-shortlist-smart__metrics > div {
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(127,127,127,.08);
    text-align: center;
}
.rc-shortlist-smart__metrics span   {
    display: block;
    font-size: 11px;
    opacity: .7;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.rc-shortlist-smart__metrics strong { display: block; font-size: 20px; margin-top: 2px; }
.rc-shortlist-smart__metrics .rc-good { background: rgba(20,184,123,.14); }
.rc-shortlist-smart__metrics .rc-warn { background: rgba(230,162,24,.14); }

.rc-shortlist-smart__criteria {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    margin-bottom: 12px;
}
.rc-shortlist-smart__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.rc-chip--danger { background: rgba(210,75,75,.14) !important; }

.rc-compare-table th:first-child {
    width: 160px;
    background: rgba(127,127,127,.05);
}
.rc-compare-table td small { display: block; opacity: .7; margin-top: 3px; }

</style>


<!-- ================================================================
     HERO
================================================================ -->

<section class="rc-hero">

    <div>

        <span class="rc-eyebrow">
            <i class="fas fa-bolt"></i>
            Talent Discovery
        </span>

        <h2>
            Find the right talent faster,
            <?= e($firstName ?: 'Recruiter') ?>.
        </h2>

        <p>
            Discover active candidate accounts, review consented
            talent profiles, identify verified skills and manage
            recruitment shortlists.
        </p>

        <div class="rc-hero__actions">

            <a
                class="rc-btn rc-btn--light"
                href="<?= url('recruiter/search.php') ?>"
            >
                <i class="fas fa-magnifying-glass"></i>
                Find Candidates
            </a>

            <a
                class="rc-btn rc-btn--glass"
                href="<?= url('recruiter/shortlists.php') ?>"
            >
                <i class="fas fa-list-check"></i>
                Open Shortlists
            </a>

        </div>

    </div>


    <div class="rc-taxonomy">

        <span>Active Taxonomy</span>

        <strong>
            <?= e($taxonomy) ?>
        </strong>

        <small>
            Matching decisions remain reproducible.
        </small>

    </div>

</section>


<!-- ================================================================
     DASHBOARD STATISTICS
================================================================ -->

<section class="rc-stats">

    <!-- Candidate Pool -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="candidates"
    >

        <i class="fas fa-users"></i>

        <strong>
            <?= number_format($stats['candidates']) ?>
        </strong>

        <span>Candidate Pool</span>

        <small>Click to view</small>

    </article>


    <!-- Complete Profiles -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="complete"
    >

        <i class="fas fa-circle-check"></i>

        <strong>
            <?= number_format($stats['complete']) ?>
        </strong>

        <span>Complete Profiles</span>

        <small>Click to view</small>

    </article>


    <!-- Consent Eligible -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="consented"
    >

        <i class="fas fa-user-shield"></i>

        <strong>
            <?= number_format($stats['consented']) ?>
        </strong>

        <span>Consent Eligible</span>

        <small>Click to view</small>

    </article>


    <!-- Verified Skills -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="verifiedSkills"
    >

        <i class="fas fa-certificate"></i>

        <strong>
            <?= number_format($stats['verifiedSkills']) ?>
        </strong>

        <span>Verified Skill Profiles</span>

        <small>Click to view</small>

    </article>


    <!-- Gauteng -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="gauteng"
    >

        <i class="fas fa-location-dot"></i>

        <strong>
            <?= number_format($stats['gauteng']) ?>
        </strong>

        <span>Gauteng Candidates</span>

        <small>Click to view</small>

    </article>


    <!-- Opportunities -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="opportunities"
    >

        <i class="fas fa-briefcase"></i>

        <strong>
            <?= number_format($stats['opportunities']) ?>
        </strong>

        <span>Published Opportunities</span>

        <small>Click to view</small>

    </article>


    <!-- Applications -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="applications"
    >

        <i class="fas fa-file-lines"></i>

        <strong>
            <?= number_format($stats['applications']) ?>
        </strong>

        <span>Applications</span>

        <small>Click to view</small>

    </article>


    <!-- Shortlists -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="shortlists"
    >

        <i class="fas fa-list-check"></i>

        <strong>
            <?= number_format($stats['shortlists']) ?>
        </strong>

        <span>My Shortlists</span>

        <small>Click to view</small>

    </article>

</section>


<!-- ================================================================
     QUICK ACTIONS
================================================================ -->

<div class="rc-grid">

    <a
        class="rc-action"
        href="<?= url('recruiter/search.php') ?>"
    >

        <i class="fas fa-users"></i>

        <div>

            <strong>Candidate Pool</strong>

            <span>
                <?= number_format($stats['candidates']) ?>
                active candidates
            </span>

        </div>

    </a>


    <a
        class="rc-action"
        href="<?= url(
            'recruiter/search.php?skill=Java&province=Gauteng'
        ) ?>"
    >

        <i class="fab fa-java"></i>

        <div>

            <strong>Java in Gauteng</strong>

            <span>
                Search Java candidates in Gauteng
            </span>

        </div>

    </a>


    <a
        class="rc-action"
        href="<?= url(
            'recruiter/search.php?skill=AWS&province=Gauteng'
        ) ?>"
    >

        <i class="fas fa-cloud"></i>

        <div>

            <strong>Cloud Talent</strong>

            <span>
                Search cloud candidates
            </span>

        </div>

    </a>


    <a
        class="rc-action"
        href="<?= url('recruiter/shortlists.php') ?>"
    >

        <i class="fas fa-list-check"></i>

        <div>

            <strong>Client Shortlists</strong>

            <span>
                Open stored search contexts
            </span>

        </div>

    </a>

</div>


<!-- ================================================================
     SMARTER SHORTLISTS OVERVIEW
================================================================ -->

<?php if (
    rc_has_table($conn, 'recruiter_shortlists') &&
    rc_has_table($conn, 'recruiter_shortlist_candidates')
): ?>

<section class="rc-card rc-space" id="smart-shortlists">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Smarter Shortlists</span>
            <h3>Manage, compare and export — inline</h3>
            <p>
                Each shortlist stores the search context used to build it.
                Match scores are computed against that context using each
                candidate's verified skills, province, and consent status.
            </p>
        </div>
        <a class="rc-btn rc-btn--secondary rc-btn--small"
           href="<?= url('recruiter/shortlists.php') ?>">
            <i class="fas fa-plus"></i> New shortlist
        </a>
    </div>

    <div class="rc-card__body">

        <?php if (!$modalData['shortlists']): ?>

            <div class="rc-empty">
                <i class="fas fa-list-check"></i>
                <strong>No shortlists yet</strong>
                <span>Create your first shortlist from the Shortlists page.</span>
            </div>

        <?php else: ?>

            <?php foreach ($modalData['shortlists'] as $sl):

                $criteriaSl = dash_parse_criteria($sl['search_criteria_json'] ?? null);
                $hasCriteria =
                    $criteriaSl['skill'] !== '' ||
                    $criteriaSl['province'] !== '' ||
                    $criteriaSl['city'] !== '';

                $metCount       = 0;
                $partialCount   = 0;
                $unmetCount     = 0;
                $consentedCount = 0;
                $blockedCount   = 0;

                $stmt = $conn->prepare("
                    SELECT rsc.candidate_id AS user_id,
                           u.first_name, u.last_name,
                           u.province, u.qualification_level
                    FROM recruiter_shortlist_candidates rsc
                    INNER JOIN users u ON u.id = rsc.candidate_id
                    INNER JOIN roles r ON r.id = u.role_id
                    WHERE rsc.shortlist_id = ? AND r.slug = 'candidate'
                ");
                $stmt->bind_param('i', $sl['id']);
                $stmt->execute();
                $res = $stmt->get_result();

                while ($row = $res->fetch_assoc()) {
                    $cid     = (int)$row['user_id'];
                    $consent = dash_consent_status($conn, $cid);

                    if (!$consent['valid']) {
                        $blockedCount++;
                        continue;
                    }

                    $consentedCount++;
                    $skills = dash_candidate_skills($conn, $cid);
                    $m      = dash_match_candidate($row, $skills, $criteriaSl);

                    if ($m['met']) {
                        $metCount++;
                    } elseif ($m['score'] >= 50) {
                        $partialCount++;
                    } else {
                        $unmetCount++;
                    }
                }
                $stmt->close();

                $candidateCount = (int)$sl['candidate_count'];
            ?>

                <article class="rc-shortlist-smart">

                    <div class="rc-shortlist-smart__head">
                        <div>
                            <strong><?= e($sl['name']) ?></strong>
                            <span>
                                <?= e($sl['client_name'] ?: 'No client specified') ?>
                                · Taxonomy <?= e($sl['taxonomy_version']) ?>
                            </span>
                        </div>
                        <span class="rc-status">
                            <?= e(rc_label((string)$sl['status'])) ?>
                        </span>
                    </div>

                    <div class="rc-shortlist-smart__metrics">
                        <div>
                            <span>Total</span>
                            <strong><?= number_format($candidateCount) ?></strong>
                        </div>
                        <div>
                            <span>Consented</span>
                            <strong><?= number_format($consentedCount) ?></strong>
                        </div>
                        <div class="<?= $blockedCount ? 'rc-warn' : '' ?>">
                            <span>Blocked</span>
                            <strong><?= number_format($blockedCount) ?></strong>
                        </div>
                        <?php if ($hasCriteria): ?>
                            <div class="<?= $metCount ? 'rc-good' : '' ?>">
                                <span>Meets</span>
                                <strong><?= number_format($metCount) ?></strong>
                            </div>
                            <div>
                                <span>Partial</span>
                                <strong><?= number_format($partialCount) ?></strong>
                            </div>
                            <div>
                                <span>Unmet</span>
                                <strong><?= number_format($unmetCount) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasCriteria): ?>
                        <div class="rc-shortlist-smart__criteria">
                            <span class="rc-eyebrow">Match context</span>

                            <?php if ($criteriaSl['skill'] !== ''): ?>
                                <span class="rc-chip">
                                    <i class="fas fa-certificate"></i>
                                    <?= e($criteriaSl['skill']) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($criteriaSl['province'] !== ''): ?>
                                <span class="rc-chip">
                                    <i class="fas fa-location-dot"></i>
                                    <?= e($criteriaSl['province']) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($criteriaSl['city'] !== ''): ?>
                                <span class="rc-chip">
                                    <i class="fas fa-city"></i>
                                    <?= e($criteriaSl['city']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="rc-shortlist-smart__actions">
                        <a class="rc-btn rc-btn--secondary rc-btn--small"
                           href="<?= url('recruiter/shortlist_view.php?id=' . (int)$sl['id']) ?>">
                            <i class="fas fa-folder-open"></i> Open
                        </a>

                        <a class="rc-btn rc-btn--secondary rc-btn--small"
                           href="<?= url('recruiter/dashboard.php?compare_shortlist=' . (int)$sl['id']) ?>#compare">
                            <i class="fas fa-code-compare"></i> Compare
                        </a>

                        <a class="rc-btn rc-btn--primary rc-btn--small"
                           href="<?= url('recruiter/dashboard.php?export_shortlist=' . (int)$sl['id']) ?>">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>

<?php endif; ?>


<!-- ================================================================
     INLINE COMPARE (dashboard.php?compare_shortlist=ID)
================================================================ -->

<?php if ($compareShortlist): ?>

<section class="rc-card rc-space" id="compare">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Side-by-side comparison</span>
            <h3><?= e($compareShortlist['name']) ?></h3>
            <p>
                <?= e($compareShortlist['client_name'] ?: 'No client specified') ?>
                · Taxonomy <?= e($compareShortlist['taxonomy_version']) ?>
            </p>
        </div>
        <a class="rc-btn rc-btn--secondary rc-btn--small"
           href="<?= url('recruiter/dashboard.php#smart-shortlists') ?>">
            <i class="fas fa-xmark"></i> Close
        </a>
    </div>

    <?php if (!$compareCandidates): ?>

        <div class="rc-card__body">
            <div class="rc-empty">
                <i class="fas fa-code-compare"></i>
                <strong>No candidates to compare</strong>
                <span>This shortlist has no candidates yet.</span>
            </div>
        </div>

    <?php else: ?>

        <div class="rc-table-wrap">
            <table class="rc-table rc-compare-table">
                <thead>
                    <tr>
                        <th>Attribute</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <th>
                                <strong>
                                    <?= $c['consent_valid']
                                        ? e(trim($c['first_name'] . ' ' . $c['last_name']))
                                        : '<i class="fas fa-lock"></i> Consent required' ?>
                                </strong>
                                <?php if ($c['consent_valid']): ?>
                                    <span class="rc-status <?= $c['match']['met'] ? 'rc-status--success' : '' ?>">
                                        Match <?= (int)$c['match']['score'] ?>%
                                    </span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <th>Consent</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td>
                                <?php if ($c['consent_valid']): ?>
                                    <span class="rc-status rc-status--success">Valid</span>
                                <?php else: ?>
                                    <span class="rc-status rc-status--danger">Unavailable</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <th>Professional Title</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td><?= $c['consent_valid']
                                ? e($c['professional_title'] ?: '—')
                                : '<i class="fas fa-lock"></i>' ?></td>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <th>Province</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td><?= $c['consent_valid']
                                ? e($c['province'] ?: '—')
                                : '<i class="fas fa-lock"></i>' ?></td>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <th>Qualification</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td><?= $c['consent_valid']
                                ? e($c['qualification_level'] ?: '—')
                                : '<i class="fas fa-lock"></i>' ?></td>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <th>Verified Skills</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td>
                                <?php if (!$c['consent_valid']): ?>
                                    <i class="fas fa-lock"></i>
                                <?php else:
                                    $v = array_values(array_filter(
                                        $c['skills'], fn($s) => $s['verified']
                                    ));
                                ?>
                                    <?php if ($v): ?>
                                        <?= number_format(count($v)) ?>
                                        <small><?= e(implode(', ', array_column($v, 'name'))) ?></small>
                                    <?php else: ?>
                                        None
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <th>Matched</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td>
                                <?php if (!$c['consent_valid']): ?>—
                                <?php elseif ($c['match']['matched']): ?>
                                    <?php foreach ($c['match']['matched'] as $m): ?>
                                        <span class="rc-chip">
                                            <i class="fas <?= $m['verified'] ? 'fa-certificate' : 'fa-check' ?>"></i>
                                            <?= e($m['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <th>Missing</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td>
                                <?php if (!$c['consent_valid']): ?>—
                                <?php elseif ($c['match']['missing']): ?>
                                    <?php foreach ($c['match']['missing'] as $m): ?>
                                        <span class="rc-chip rc-chip--danger">
                                            <i class="fas fa-xmark"></i> <?= e($m) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="rc-status rc-status--success">None</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <th>Overall</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td>
                                <?php if ($c['match']['met']): ?>
                                    <span class="rc-status rc-status--success">Meets criteria</span>
                                <?php else: ?>
                                    <span class="rc-status">Partial match</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <th>Action</th>
                        <?php foreach ($compareCandidates as $c): ?>
                            <td>
                                <?php if ($c['consent_valid']): ?>
                                    <a class="rc-btn rc-btn--secondary rc-btn--small"
                                       href="<?= url('recruiter/candidate_view.php?id=' . (int)$c['user_id']) ?>">
                                        Open
                                    </a>
                                <?php else: ?>
                                    <span class="rc-status rc-status--danger">
                                        <i class="fas fa-lock"></i> Blocked
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>

<?php endif; ?>


<!-- ================================================================
     PRIVACY / CONSENT
================================================================ -->

<div class="rc-card">

    <div class="rc-card__body rc-info">

        <i class="fas fa-shield-halved"></i>

        <div>

            <strong>
                Candidate discovery respects profile consent.
            </strong>

            <p>
                Candidate accounts can appear in aggregate
                recruitment statistics, while detailed recruiter
                profile access remains restricted to candidates
                with valid recruiter-discovery consent.
            </p>

        </div>

    </div>

</div>


<!-- ================================================================
     DASHBOARD MODAL
================================================================ -->

<div
    class="rc-dashboard-modal"
    id="rcDashboardModal"
    aria-hidden="true"
>

    <div
        class="rc-dashboard-modal__backdrop"
        data-rc-modal-close
    ></div>


    <div
        class="rc-dashboard-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rcDashboardModalTitle"
    >

        <!-- Header -->

        <div class="rc-dashboard-modal__header">

            <div>

                <span class="rc-eyebrow">
                    Recruiter Dashboard
                </span>

                <h3 id="rcDashboardModalTitle">
                    Details
                </h3>

                <p id="rcDashboardModalDescription">
                    Dashboard information.
                </p>

            </div>


            <button
                type="button"
                class="rc-dashboard-modal__close"
                data-rc-modal-close
                aria-label="Close"
            >
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        <!-- Body -->

        <div class="rc-dashboard-modal__body">


            <!-- ====================================================
                 CANDIDATE POOL
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="candidates"
            >

                <?php if (!$modalData['candidates']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-users"></i>

                        <strong>
                            No candidates
                        </strong>

                        <span>
                            No active Candidate accounts are
                            currently available.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['candidates']
                        as $candidate
                    ): ?>

                        <?php

                        $candidateName = trim(
                            (string)$candidate['first_name']
                            . ' '
                            . (string)$candidate['last_name']
                        );

                        $candidateTitle = trim(
                            (string)(
                                $candidate['profile_title']
                                ?: $candidate['professional_title']
                                ?: 'Candidate'
                            )
                        );

                        $location = trim(
                            (string)($candidate['city'] ?? '')
                            . ', '
                            . (string)($candidate['province'] ?? ''),
                            ' ,'
                        );

                        ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-user"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        $candidateName
                                        ?: 'Candidate'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e($candidateTitle) ?>
                                </span>

                                <small>
                                    <?= e(
                                        $location
                                        ?: 'Location not provided'
                                    ) ?>
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/search.php'
                                ) ?>"
                                title="Open Candidate Search"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 COMPLETE PROFILES
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="complete"
            >

                <?php if (!$modalData['complete']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-circle-check"></i>

                        <strong>
                            No complete profiles
                        </strong>

                        <span>
                            No active candidate profile has
                            reached 100% completion.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['complete']
                        as $candidate
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-circle-check"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        trim(
                                            (string)$candidate['first_name']
                                            . ' '
                                            . (string)$candidate['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    Complete Candidate Profile
                                </span>

                                <small>
                                    <?= number_format(
                                        (int)$candidate[
                                            'completion_percent'
                                        ]
                                    ) ?>% complete
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/search.php?profile_status=complete'
                                ) ?>"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 CONSENT ELIGIBLE
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="consented"
            >

                <?php if (!$modalData['consented']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-user-shield"></i>

                        <strong>
                            No consent-eligible candidates
                        </strong>

                        <span>
                            No active Candidate currently has
                            valid recruiter-discovery consent.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['consented']
                        as $candidate
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-user-shield"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        trim(
                                            (string)$candidate['first_name']
                                            . ' '
                                            . (string)$candidate['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    Recruiter discovery consent
                                </span>

                                <small>
                                    Granted:
                                    <?= e(
                                        rc_date(
                                            $candidate['granted_at']
                                            ?? null
                                        )
                                    ) ?>
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/candidate_view.php?id='
                                    . (int)$candidate['id']
                                ) ?>"
                                title="View Candidate"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 VERIFIED SKILLS
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="verifiedSkills"
            >

                <?php if (!$modalData['verifiedSkills']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-certificate"></i>

                        <strong>
                            No verified skill profiles
                        </strong>

                        <span>
                            No active Candidate currently has
                            a verified skill.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['verifiedSkills']
                        as $candidate
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-certificate"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        trim(
                                            (string)$candidate['first_name']
                                            . ' '
                                            . (string)$candidate['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= number_format(
                                        (int)$candidate[
                                            'verified_skill_count'
                                        ]
                                    ) ?>
                                    verified
                                    <?= (int)$candidate[
                                        'verified_skill_count'
                                    ] === 1
                                        ? 'skill'
                                        : 'skills' ?>
                                </span>

                                <small>
                                    <?= e(
                                        (string)(
                                            $candidate[
                                                'verified_skills'
                                            ]
                                            ?: 'No skill names available'
                                        )
                                    ) ?>
                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 GAUTENG CANDIDATES
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="gauteng"
            >

                <?php if (!$modalData['gauteng']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-location-dot"></i>

                        <strong>
                            No Gauteng candidates
                        </strong>

                        <span>
                            No active Candidate account is
                            currently registered in Gauteng.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['gauteng']
                        as $candidate
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-location-dot"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        trim(
                                            (string)$candidate['first_name']
                                            . ' '
                                            . (string)$candidate['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $candidate['profile_title']
                                            ?: $candidate[
                                                'professional_title'
                                            ]
                                            ?: 'Candidate'
                                        )
                                    ) ?>
                                </span>

                                <small>
                                    <?= e(
                                        trim(
                                            (string)($candidate['city'] ?? '')
                                            . ', '
                                            . (string)$candidate['province'],
                                            ' ,'
                                        )
                                        ?: 'Gauteng'
                                    ) ?>
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/search.php?province=Gauteng'
                                ) ?>"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 OPPORTUNITIES
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="opportunities"
            >

                <?php if (!$modalData['opportunities']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-briefcase"></i>

                        <strong>
                            No published opportunities
                        </strong>

                        <span>
                            No opportunities are currently
                            marked as published.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['opportunities']
                        as $opportunity
                    ): ?>

                        <?php

                        $opportunityLocation = trim(
                            (string)($opportunity['city'] ?? '')
                            . ', '
                            . (string)($opportunity['province'] ?? ''),
                            ' ,'
                        );

                        ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-briefcase"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        (string)$opportunity['title']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $opportunity['organisation']
                                            ?: 'Organisation not specified'
                                        )
                                    ) ?>
                                </span>

                                <small>

                                    <?= number_format(
                                        (int)(
                                            $opportunity[
                                                'available_positions'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>
                                    positions

                                    <?php if (
                                        $opportunityLocation !== ''
                                    ): ?>

                                        ·
                                        <?= e($opportunityLocation) ?>

                                    <?php endif; ?>

                                </small>

                                <span class="rc-modal-badge">
                                    <?= e(
                                        rc_label(
                                            $opportunity['status']
                                        )
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 APPLICATIONS
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="applications"
            >

                <?php if (!$modalData['applications']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-file-lines"></i>

                        <strong>
                            No submitted applications
                        </strong>

                        <span>
                            No non-draft applications are
                            currently recorded.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['applications']
                        as $application
                    ): ?>

                        <?php

                        $applicationCandidate = trim(
                            (string)(
                                $application['first_name']
                                ?? ''
                            )
                            . ' '
                            . (string)(
                                $application['last_name']
                                ?? ''
                            )
                        );

                        ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-file-lines"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        $applicationCandidate
                                        ?: 'Candidate'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $application[
                                                'opportunity_title'
                                            ]
                                            ?: 'Opportunity'
                                        )
                                    ) ?>
                                </span>

                                <small>

                                    <?php if (
                                        !empty(
                                            $application[
                                                'application_reference'
                                            ]
                                        )
                                    ): ?>

                                        <?= e(
                                            $application[
                                                'application_reference'
                                            ]
                                        ) ?>

                                        ·

                                    <?php endif; ?>

                                    <?= e(
                                        rc_label(
                                            $application['status']
                                        )
                                    ) ?>

                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 SHORTLISTS
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="shortlists"
            >

                <?php if (!$modalData['shortlists']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-list-check"></i>

                        <strong>
                            No shortlists
                        </strong>

                        <span>
                            You have not created a Recruiter
                            shortlist yet.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['shortlists']
                        as $shortlist
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-list-check"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        (string)$shortlist['name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $shortlist['client_name']
                                            ?: 'No client specified'
                                        )
                                    ) ?>
                                </span>

                                <small>
                                    <?= number_format(
                                        (int)$shortlist[
                                            'candidate_count'
                                        ]
                                    ) ?>
                                    <?= (int)$shortlist[
                                        'candidate_count'
                                    ] === 1
                                        ? 'candidate'
                                        : 'candidates' ?>
                                    ·
                                    <?= e(
                                        rc_label(
                                            $shortlist['status']
                                        )
                                    ) ?>
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/shortlist_view.php?id='
                                    . (int)$shortlist['id']
                                ) ?>"
                                title="Open Shortlist"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


        </div>


        <!-- Footer -->

        <div class="rc-dashboard-modal__footer">

            <button
                type="button"
                class="rc-btn rc-btn--secondary"
                data-rc-modal-close
            >
                <i class="fas fa-xmark"></i>
                Close
            </button>

        </div>

    </div>

</div>


<!-- ================================================================
     MODAL JAVASCRIPT
================================================================ -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const modal =
        document.getElementById('rcDashboardModal');

    if (!modal) {
        return;
    }

    const cards =
        document.querySelectorAll(
            '[data-rc-modal]'
        );

    const panels =
        modal.querySelectorAll(
            '[data-rc-panel]'
        );

    const closeButtons =
        modal.querySelectorAll(
            '[data-rc-modal-close]'
        );

    const title =
        document.getElementById(
            'rcDashboardModalTitle'
        );

    const description =
        document.getElementById(
            'rcDashboardModalDescription'
        );

    let lastTrigger = null;


    /*
    |--------------------------------------------------------------------------
    | Modal Configuration
    |--------------------------------------------------------------------------
    */

    const config = {

        candidates: {
            title: 'Candidate Pool',
            description:
                'Active Candidate accounts available to recruitment.'
        },

        complete: {
            title: 'Complete Profiles',
            description:
                'Candidate profiles that have reached 100% completion.'
        },

        consented: {
            title: 'Consent Eligible Candidates',
            description:
                'Candidates with valid recruiter-discovery consent.'
        },

        verifiedSkills: {
            title: 'Verified Skill Profiles',
            description:
                'Active candidates with one or more verified skills.'
        },

        gauteng: {
            title: 'Gauteng Candidates',
            description:
                'Active Candidate accounts registered in Gauteng.'
        },

        opportunities: {
            title: 'Published Opportunities',
            description:
                'Recruitment opportunities currently marked as published.'
        },

        applications: {
            title: 'Applications',
            description:
                'Submitted applications currently recorded in the platform.'
        },

        shortlists: {
            title: 'My Shortlists',
            description:
                'Shortlists owned by your Recruiter account.'
        }

    };


    /*
    |--------------------------------------------------------------------------
    | Open Modal
    |--------------------------------------------------------------------------
    */

    function openModal(type, trigger) {

        const selected = config[type];

        if (!selected) {
            return;
        }

        lastTrigger = trigger || null;

        panels.forEach(function (panel) {

            panel.classList.toggle(
                'is-active',
                panel.dataset.rcPanel === type
            );

        });

        title.textContent =
            selected.title;

        description.textContent =
            selected.description;

        modal.classList.add(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'rc-modal-open'
        );

        const closeButton =
            modal.querySelector(
                '.rc-dashboard-modal__close'
            );

        if (closeButton) {
            closeButton.focus();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Close Modal
    |--------------------------------------------------------------------------
    */

    function closeModal() {

        modal.classList.remove(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'rc-modal-open'
        );

        panels.forEach(function (panel) {
            panel.classList.remove(
                'is-active'
            );
        });

        if (lastTrigger) {
            lastTrigger.focus();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Card Click
    |--------------------------------------------------------------------------
    */

    cards.forEach(function (card) {

        card.addEventListener(
            'click',
            function () {

                openModal(
                    card.dataset.rcModal,
                    card
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Keyboard Accessibility
        |--------------------------------------------------------------------------
        */

        card.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Enter' ||
                    event.key === ' '
                ) {

                    event.preventDefault();

                    openModal(
                        card.dataset.rcModal,
                        card
                    );
                }

            }
        );

    });


    /*
    |--------------------------------------------------------------------------
    | Close Buttons / Backdrop
    |--------------------------------------------------------------------------
    */

    closeButtons.forEach(
        function (button) {

            button.addEventListener(
                'click',
                closeModal
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Escape
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains(
                    'is-open'
                )
            ) {
                closeModal();
            }

        }
    );

});

</script>


<?php

require __DIR__ . '/_layout_end.php';

?>