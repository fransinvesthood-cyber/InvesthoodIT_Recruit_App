<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'pipeline_bottlenecks';
$pageTitle   = 'Pipeline Bottlenecks & Conversion';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

$self = url('recruiter/pipeline_bottlenecks.php');

/* ==================================================================
 | Constants
 * ==================================================================*/

// Vacancies that are open to applications (same statuses the dashboard lists).
const PB_OPEN_STATUSES = ['published', 'closing_soon'];

// Pipeline stages, in the order a candidate moves through them.
const PB_STAGES = [
    1 => 'Applied',
    2 => 'Under review',
    3 => 'Shortlisted',
    4 => 'Assessment',
    5 => 'Interview',
    6 => 'Selected',
    7 => 'Offer',
    8 => 'Hired',
];

// Label for applications paused outside the normal flow (key 0 below).
const PB_HOLD_LABEL = 'On hold / waitlisted';

// Days an application may sit untouched in a stage before it counts as stuck.
// Key 0 is the on-hold / waitlisted group.
const PB_STUCK_DAYS = [0 => 21, 1 => 7, 2 => 10, 3 => 7, 4 => 14, 5 => 14, 6 => 5, 7 => 7];

// Selectable reporting windows (days of application submissions). 0 = all time.
const PB_RANGES = [30, 90, 180, 365, 0];

// Smallest number of observations before a step or stage is judged.
const PB_MIN_SAMPLE = 3;

// Rows listed in the stuck-application table and the vacancy table.
const PB_LIST_LIMIT    = 15;
const PB_VACANCY_LIMIT = 25;

// Safety cap on applications analysed in one request.
const PB_ROW_CAP = 50000;

/* ==================================================================
 | Helpers (page-local, all prefixed pb_)
 * ==================================================================*/

if (!function_exists('pb_rows')) {
    /**
     * Runs a prepared SELECT and returns all rows. Never throws: a failing
     * query is logged and yields an empty result so the page still renders.
     */
    function pb_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        try {
            if ($types === '') {
                $res = $conn->query($sql);
                if (!$res) {
                    error_log('pipeline_bottlenecks.php: ' . $conn->error);
                    return [];
                }
                $rows = [];
                while ($r = $res->fetch_assoc()) $rows[] = $r;
                $res->free();
                return $rows;
            }

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('pipeline_bottlenecks.php: ' . $conn->error);
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res  = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
            return $rows;
        } catch (Throwable $t) {
            error_log('pipeline_bottlenecks.php: ' . $t->getMessage());
            return [];
        }
    }
}

if (!function_exists('pb_has_column')) {
    function pb_has_column(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $rows = pb_rows(
            $conn,
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1',
            'ss',
            [$table, $column]
        );
        return $cache[$key] = (bool)$rows;
    }
}

if (!function_exists('pb_history_columns')) {
    /** Finds the usable columns of application_status_history (names can differ between installs). */
    function pb_history_columns(mysqli $conn): array
    {
        $out = ['time' => null, 'id' => false, 'usable' => false];

        $rows = pb_rows(
            $conn,
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application_status_history'"
        );
        if (!$rows) return $out;

        $cols = [];
        foreach ($rows as $r) $cols[] = strtolower((string)reset($r));

        foreach (['created_at', 'changed_at', 'updated_at', 'timestamp'] as $c) {
            if (in_array($c, $cols, true)) { $out['time'] = $c; break; }
        }
        $out['id']     = in_array('id', $cols, true);
        $out['usable'] = count(array_intersect(['application_id', 'previous_status', 'new_status'], $cols)) === 3;

        return $out;
    }
}

if (!function_exists('pb_person')) {
    function pb_person(array $row): string
    {
        $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        return $name !== '' ? $name : 'Candidate';
    }
}

if (!function_exists('pb_age_days')) {
    /** Whole days between a date/time string and now (never negative). */
    function pb_age_days(?string $value): ?int
    {
        if ($value === null || trim($value) === '') return null;
        $ts = strtotime($value);
        if ($ts === false) return null;
        return max(0, (int)floor((time() - $ts) / 86400));
    }
}

if (!function_exists('pb_days_between')) {
    /** Whole days from one date/time to another (never negative). */
    function pb_days_between(?string $from, ?string $to): ?int
    {
        if ($from === null || $to === null || trim($from) === '' || trim($to) === '') return null;
        $a = strtotime($from);
        $b = strtotime($to);
        if ($a === false || $b === false) return null;
        return max(0, (int)floor(($b - $a) / 86400));
    }
}

if (!function_exists('pb_pct')) {
    function pb_pct(int $part, int $whole): int
    {
        if ($whole <= 0) return 0;
        return (int)min(100, round(($part / $whole) * 100));
    }
}

if (!function_exists('pb_median')) {
    function pb_median(array $values): ?float
    {
        $n = count($values);
        if ($n === 0) return null;
        sort($values);
        $mid = intdiv($n, 2);
        return $n % 2 === 1
            ? (float)$values[$mid]
            : ((float)$values[$mid - 1] + (float)$values[$mid]) / 2;
    }
}

if (!function_exists('pb_days_text')) {
    function pb_days_text(?float $days): string
    {
        if ($days === null) return 'n/a';
        $rounded = round($days, 1);
        return ($rounded == floor($rounded) ? (string)(int)$rounded : number_format($rounded, 1)) . ' d';
    }
}

if (!function_exists('pb_stage_label')) {
    function pb_stage_label(int $stage): string
    {
        return $stage === 0 ? PB_HOLD_LABEL : (PB_STAGES[$stage] ?? 'Unknown');
    }
}

if (!function_exists('pb_stage_of')) {
    /**
     * Pipeline stage (1-8) a status belongs to, or null for statuses that do
     * not sit on the main path (rejected, withdrawn, on hold, waitlisted...).
     */
    function pb_stage_of(?string $status): ?int
    {
        $s = strtolower(trim((string)$status));
        if ($s === '') return null;

        switch ($s) {
            case 'submitted':           return 1;
            case 'under_review':        return 2;
            case 'shortlisted':         return 3;
            case 'assessment':          return 4;
            case 'interview_required':
            case 'interview_scheduled':
            case 'interview_completed': return 5;
            case 'selected':            return 6;
            case 'offer_sent':
            case 'offer_declined':      return 7;
            case 'offer_accepted':      return 8;
            case 'draft':
            case 'rejected':
            case 'withdrawn':
            case 'on_hold':
            case 'waitlisted':          return null;
        }

        // Fallbacks for statuses added later.
        if (strpos($s, 'hired') !== false || strpos($s, 'placed') !== false) return 8;
        if (strpos($s, 'offer') !== false)       return 7;
        if (strpos($s, 'select') !== false)      return 6;
        if (strpos($s, 'interview') !== false)   return 5;
        if (strpos($s, 'assess') !== false)      return 4;
        if (strpos($s, 'shortlist') !== false)   return 3;
        if (strpos($s, 'review') !== false)      return 2;
        return null;
    }
}

if (!function_exists('pb_outcome_of')) {
    /** active (moving), paused (on hold / waitlisted), hired, exited, or other (unknown status). */
    function pb_outcome_of(string $status): string
    {
        $s = strtolower(trim($status));

        if (in_array($s, ['rejected', 'withdrawn', 'offer_declined'], true)) return 'exited';
        if (in_array($s, ['on_hold', 'waitlisted'], true))                    return 'paused';

        $stage = pb_stage_of($s);
        if ($stage === 8) return 'hired';
        if ($stage !== null) return 'active';
        return 'other';
    }
}

/* ==================================================================
 | Filters
 * ==================================================================*/

$range = isset($_GET['range']) ? (int)$_GET['range'] : 90;
if (!in_array($range, PB_RANGES, true)) $range = 90;

$vacancyId = (int)($_GET['vacancy'] ?? 0);

$today     = new DateTimeImmutable('today');
$fromDay   = $range > 0 ? $today->modify('-' . ($range - 1) . ' days') : null;
$fromStart = $fromDay ? $fromDay->format('Y-m-d 00:00:00') : null;
$toEnd     = $today->modify('+1 day')->format('Y-m-d 00:00:00');
$now       = date('Y-m-d H:i:s');

/* ==================================================================
 | Table availability
 * ==================================================================*/

$hasOpp        = rc_has_table($conn, 'opportunities');
$hasApp        = rc_has_table($conn, 'applications');
$hasInterviews = rc_has_table($conn, 'recruiter_interviews');
$hasOffers     = rc_has_table($conn, 'recruiter_offers');
$hasHistTable  = rc_has_table($conn, 'application_status_history');
$ready         = $hasOpp && $hasApp;

/* ==================================================================
 | Vacancy filter options
 * ==================================================================*/

$vacancyOptions = [];
if ($hasOpp) {
    $vacancyOptions = pb_rows(
        $conn,
        'SELECT id, title, status FROM opportunities ORDER BY id DESC LIMIT 500'
    );
}

$vacancyKnown = false;
foreach ($vacancyOptions as $vo) {
    if ((int)$vo['id'] === $vacancyId) { $vacancyKnown = true; break; }
}
if (!$vacancyKnown) $vacancyId = 0;

/* ==================================================================
 | Load applications in scope
 * ==================================================================*/

$appRows    = [];
$truncated  = false;
$hasUpdated = false;

if ($ready) {

    $openedExpr = 'COALESCE(a.submitted_at, a.created_at)';
    $hasUpdated = pb_has_column($conn, 'applications', 'updated_at');
    $updExpr    = $hasUpdated ? 'a.updated_at' : 'NULL';

    if ($hasInterviews) {
        $ivJoin = "LEFT JOIN (
                       SELECT application_id,
                              COUNT(*)          AS iv_n,
                              MIN(scheduled_at) AS first_iv,
                              MAX(scheduled_at) AS last_iv
                       FROM recruiter_interviews
                       WHERE application_id IS NOT NULL
                         AND status IN ('scheduled', 'completed', 'no_show')
                       GROUP BY application_id
                   ) iv ON iv.application_id = a.id";
        $ivSelect = 'iv.iv_n, iv.first_iv, iv.last_iv';
    } else {
        $ivJoin   = '';
        $ivSelect = '0 AS iv_n, NULL AS first_iv, NULL AS last_iv';
    }

    if ($hasOffers) {
        $ofJoin = "LEFT JOIN (
                       SELECT application_id, COUNT(*) AS offer_n
                       FROM recruiter_offers
                       WHERE status IN ('sent', 'accepted', 'declined')
                       GROUP BY application_id
                   ) ofr ON ofr.application_id = a.id";
        $ofSelect = 'ofr.offer_n';
    } else {
        $ofJoin   = '';
        $ofSelect = '0 AS offer_n';
    }

    $where  = ["a.status <> 'draft'"];
    $types  = '';
    $params = [];

    if ($fromStart !== null) {
        $where[]  = "$openedExpr >= ? AND $openedExpr < ?";
        $types   .= 'ss';
        $params[] = $fromStart;
        $params[] = $toEnd;
    }
    if ($vacancyId > 0) {
        $where[]  = 'a.opportunity_id = ?';
        $types   .= 'i';
        $params[] = $vacancyId;
    }

    $appRows = pb_rows(
        $conn,
        "SELECT a.id, a.application_reference, a.status, a.opportunity_id,
                $openedExpr AS opened_at, $updExpr AS updated_at,
                $ivSelect, $ofSelect,
                o.title AS opportunity_title, o.status AS opportunity_status,
                u.first_name, u.last_name
         FROM applications a
         INNER JOIN opportunities o ON o.id = a.opportunity_id
         LEFT JOIN users u ON u.id = a.candidate_id
         $ivJoin
         $ofJoin
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.id ASC
         LIMIT " . (int)PB_ROW_CAP,
        $types,
        $params
    );

    $truncated = count($appRows) >= PB_ROW_CAP;
}

/* ==================================================================
 | Status history (real stage transitions, when the table is usable)
 * ==================================================================*/

$hist        = [];     // application id => history rows, oldest first
$histCols    = ['time' => null, 'id' => false, 'usable' => false];
$historyOk   = false;  // history rows can be used for stages
$historyTime = false;  // ...and for timing

if ($ready && $hasHistTable && $appRows) {

    $histCols = pb_history_columns($conn);

    if ($histCols['usable']) {
        $historyOk   = true;
        $historyTime = $histCols['time'] !== null;

        $select = 'application_id, previous_status, new_status'
                . ($historyTime ? ', `' . $histCols['time'] . '` AS ev_time' : ', NULL AS ev_time');

        $order = [];
        if ($historyTime)     $order[] = '`' . $histCols['time'] . '` ASC';
        if ($histCols['id'])  $order[] = 'id ASC';

        $ids = [];
        foreach ($appRows as $r) $ids[] = (int)$r['id'];

        foreach (array_chunk($ids, 1000) as $chunk) {
            $rows = pb_rows(
                $conn,
                "SELECT $select FROM application_status_history
                 WHERE application_id IN (" . implode(',', $chunk) . ')'
                . ($order ? ' ORDER BY ' . implode(', ', $order) : '')
            );
            foreach ($rows as $h) $hist[(int)$h['application_id']][] = $h;
        }
    }
}

/* ==================================================================
 | Funnel, stuck and vacancy aggregation
 * ==================================================================*/

$stageKeys = array_keys(PB_STAGES);                 // 1..8
$liveKeys  = array_merge([0], $stageKeys);          // 0 = on hold / waitlisted

$reached = array_fill_keys($stageKeys, 0);   // applications that got at least this far
$here    = array_fill_keys($stageKeys, 0);   // active applications currently at this stage
$dropped = array_fill_keys($stageKeys, 0);   // rejected / withdrawn / declined after reaching this stage
$paused  = 0;                                // on hold / waitlisted (all vacancies)

$live = [];                                   // live pipeline (open vacancies)
foreach ($liveKeys as $k) {
    $live[$k] = ['n' => 0, 'stuck' => 0, 'idleSum' => 0, 'idleKnown' => 0, 'idleMax' => 0];
}

$dwell          = array_fill_keys($stageKeys, []);   // completed time-in-stage samples (days)
$stuckRows      = [];
$vacancies      = [];
$otherStatuses  = [];
$toFirstIv      = [];
$toHire         = [];
$totalApps      = count($appRows);
$totalLive      = 0;
$totalStuck     = 0;
$openStatusSet  = array_flip(PB_OPEN_STATUSES);

foreach ($appRows as $r) {

    $appId   = (int)$r['id'];
    $status  = (string)$r['status'];
    $ivN     = (int)($r['iv_n'] ?? 0);
    $offerN  = (int)($r['offer_n'] ?? 0);
    $oid     = (int)$r['opportunity_id'];
    $opened  = $r['opened_at'] !== null ? (string)$r['opened_at'] : null;
    $outcome = pb_outcome_of($status);
    $hRows   = $hist[$appId] ?? [];

    // --- Furthest stage reached ---------------------------------------
    $base    = pb_stage_of($status) ?? 0;
    $histMax = 0;
    foreach ($hRows as $h) {
        foreach ([$h['previous_status'] ?? null, $h['new_status'] ?? null] as $sx) {
            $sg = pb_stage_of($sx === null ? null : (string)$sx);
            if ($sg !== null) $histMax = max($histMax, $sg);
        }
    }
    $evidence = 0;
    if ($ivN > 0)    $evidence = 5;      // an interview is on record
    if ($offerN > 0) $evidence = 7;      // an offer was sent / answered

    $stage = max($base, $histMax, $evidence);
    if ($stage === 0) {
        // Rejected with no history is assumed to have been reviewed first.
        $stage = ($status === 'rejected' && !$hRows) ? 2 : 1;
    }

    // Stage the application sits in right now (active only).
    $current = max($base, $evidence);
    if ($current > 7) $current = 7;

    for ($i = 1; $i <= $stage; $i++) $reached[$i]++;

    if ($outcome === 'active')       $here[$current]++;
    elseif ($outcome === 'exited')   $dropped[$stage]++;
    elseif ($outcome === 'paused')   $paused++;
    elseif ($outcome === 'other') {
        $label = rc_label($status);
        $otherStatuses[$label] = ($otherStatuses[$label] ?? 0) + 1;
    }

    // --- Timing from status history -----------------------------------
    $enteredCurrent = null;
    $hireAt         = null;

    if ($historyTime && $hRows) {
        $segStart = $opened;
        foreach ($hRows as $h) {
            $t = $h['ev_time'] !== null ? (string)$h['ev_time'] : null;
            if ($t === null) continue;

            $prevStage = pb_stage_of(isset($h['previous_status']) ? (string)$h['previous_status'] : null);
            if ($prevStage !== null && $segStart !== null) {
                $d = pb_days_between($segStart, $t);
                if ($d !== null) $dwell[$prevStage][] = $d;
            }
            $segStart = $t;

            if (strtolower((string)$h['new_status']) === strtolower($status)) $enteredCurrent = $t;
            if (strtolower((string)$h['new_status']) === 'offer_accepted' && $hireAt === null) $hireAt = $t;
        }
    }

    // --- Time to first interview / to hire ----------------------------
    if ($ivN > 0 && $r['first_iv'] !== null) {
        $d = pb_days_between($opened, (string)$r['first_iv']);
        if ($d !== null) $toFirstIv[] = $d;
    }
    if ($outcome === 'hired') {
        $end = $hireAt ?? ($r['updated_at'] !== null ? (string)$r['updated_at'] : null);
        $d   = pb_days_between($opened, $end);
        if ($d !== null) $toHire[] = $d;
    }

    // --- Vacancy rollup ------------------------------------------------
    if (!isset($vacancies[$oid])) {
        $vacancies[$oid] = [
            'id'      => $oid,
            'title'   => (string)($r['opportunity_title'] ?? 'Vacancy'),
            'status'  => (string)($r['opportunity_status'] ?? ''),
            'r'       => array_fill_keys($stageKeys, 0),
            'live'    => 0,
            'stuck'   => 0,
            'maxIdle' => 0,
        ];
    }
    for ($i = 1; $i <= $stage; $i++) $vacancies[$oid]['r'][$i]++;

    // --- Live pipeline: active / paused applications on open vacancies ---
    $isOpen = isset($openStatusSet[(string)($r['opportunity_status'] ?? '')]);
    if (($outcome !== 'active' && $outcome !== 'paused') || !$isOpen) continue;

    $slot = $outcome === 'paused' ? 0 : $current;

    if ($enteredCurrent !== null) {
        $moved = $enteredCurrent;
    } else {
        $moved = $opened;
        if ($r['updated_at'] !== null && ($moved === null || (string)$r['updated_at'] > $moved)) {
            $moved = (string)$r['updated_at'];
        }
    }

    $upcoming = false;
    if ($slot === 5 && $r['last_iv'] !== null) {
        if ((string)$r['last_iv'] > $now) {
            $upcoming = true;                       // an interview is still ahead
        } elseif ($moved === null || (string)$r['last_iv'] > $moved) {
            $moved = (string)$r['last_iv'];         // last interview already happened
        }
    }

    $idle = $upcoming ? 0 : pb_age_days($moved);

    $live[$slot]['n']++;
    $totalLive++;
    $vacancies[$oid]['live']++;

    if ($idle !== null) {
        $live[$slot]['idleSum']   += $idle;
        $live[$slot]['idleKnown']++;
        $live[$slot]['idleMax']    = max($live[$slot]['idleMax'], $idle);
        $vacancies[$oid]['maxIdle'] = max($vacancies[$oid]['maxIdle'], $idle);

        if (array_key_exists($slot, PB_STUCK_DAYS) && $idle >= PB_STUCK_DAYS[$slot]) {
            $live[$slot]['stuck']++;
            $totalStuck++;
            $vacancies[$oid]['stuck']++;
            $stuckRows[] = [
                'id'        => $appId,
                'reference' => (string)($r['application_reference'] ?? ''),
                'person'    => pb_person($r),
                'opp_id'    => $oid,
                'opp_title' => (string)($r['opportunity_title'] ?? 'Vacancy'),
                'stage'     => $slot,
                'idle'      => $idle,
            ];
        }
    }
}

usort($stuckRows, static function (array $a, array $b): int {
    return [$b['idle'], $a['id']] <=> [$a['idle'], $b['id']];
});

uasort($vacancies, static function (array $a, array $b): int {
    return [$b['stuck'], $b['r'][1], $a['id']] <=> [$a['stuck'], $a['r'][1], $b['id']];
});

$hired = $reached[8];

/* ==================================================================
 | Bottleneck insights
 * ==================================================================*/

// Stage (or hold group) holding the most stuck applications.
$stuckStage = null;
foreach ([1, 2, 3, 4, 5, 6, 7, 0] as $k) {
    if ($live[$k]['stuck'] > 0 && ($stuckStage === null || $live[$k]['stuck'] > $live[$stuckStage]['stuck'])) {
        $stuckStage = $k;
    }
}

// Step with the lowest pass rate among resolved applications.
$worstStep = null;
$worstRate = 101;
foreach ([1, 2, 3, 4, 5, 6, 7] as $k) {
    $advanced = $reached[$k + 1];
    $resolved = $advanced + $dropped[$k];
    if ($resolved < PB_MIN_SAMPLE) continue;
    $rate = pb_pct($advanced, $resolved);
    if ($rate < $worstRate) {
        $worstRate = $rate;
        $worstStep = $k;
    }
}

// Stage where applications historically spend the longest (needs history timing).
$dwellMedian = [];
foreach ($stageKeys as $k) {
    $dwellMedian[$k] = count($dwell[$k]) >= PB_MIN_SAMPLE ? pb_median($dwell[$k]) : null;
}
$slowStage = null;
foreach ([1, 2, 3, 4, 5, 6, 7] as $k) {
    if ($dwellMedian[$k] !== null && ($slowStage === null || $dwellMedian[$k] > $dwellMedian[$slowStage])) {
        $slowStage = $k;
    }
}

$medianFirstIv = pb_median($toFirstIv);
$medianHire    = pb_median($toHire);

/* ==================================================================
 | Interview follow-ups (this recruiter only)
 * ==================================================================*/

$ivOverdue = 0;
$ivPending = 0;

if ($hasInterviews) {
    $r = pb_rows(
        $conn,
        "SELECT COUNT(*) AS n FROM recruiter_interviews
         WHERE recruiter_id = ? AND status = 'scheduled' AND scheduled_at < ?",
        'is',
        [$recruiterId, $now]
    );
    $ivOverdue = (int)($r[0]['n'] ?? 0);

    $r = pb_rows(
        $conn,
        "SELECT COUNT(*) AS n FROM recruiter_interviews
         WHERE recruiter_id = ? AND status = 'completed' AND outcome = 'pending'",
        'i',
        [$recruiterId]
    );
    $ivPending = (int)($r[0]['n'] ?? 0);
}

/* ==================================================================
 | View helpers
 * ==================================================================*/

$statusClass = static function (string $s): string {
    if (in_array($s, PB_OPEN_STATUSES, true)) return 'rc-status--success';
    if ($s === 'closed') return 'rc-status--danger';
    return '';
};

$rangeLabel = static function (int $r): string {
    return $r === 0 ? 'All time' : 'Last ' . $r . ' days';
};

$stageColors = [
    1 => '#2563eb', 2 => '#7c3aed', 3 => '#c026d3', 4 => '#db2777',
    5 => '#d97706', 6 => '#0891b2', 7 => '#0d9488', 8 => '#16a34a',
];

$vacancyShown = array_slice($vacancies, 0, PB_VACANCY_LIMIT, true);
$stuckShown   = array_slice($stuckRows, 0, PB_LIST_LIMIT);

require __DIR__ . '/_layout_start.php';
?>

<style>
.pb-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px}
.pb-stat{padding:20px;border-radius:18px;background:rgba(127,127,127,.08);border:1px solid rgba(127,127,127,.18)}
.pb-stat i{display:block;margin-bottom:12px;font-size:20px}
.pb-stat strong{display:block;font-size:26px;line-height:1}
.pb-stat span{display:block;margin-top:8px;font-size:13px;opacity:.7}
.pb-stat small{display:block;margin-top:5px;font-size:11px;opacity:.6}
.pb-filters{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;margin-bottom:18px}
.pb-filters label{display:block;font-size:12px;font-weight:700;margin-bottom:5px;opacity:.75}
.pb-filters select{min-width:190px;max-width:320px;padding:9px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit}
.pb-filters select option{color:#111}
.pb-insights{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.pb-insight{padding:16px;border-radius:14px;border:1px solid rgba(127,127,127,.2);background:rgba(127,127,127,.06)}
.pb-insight.is-hot{border-color:rgba(217,119,6,.55)}
.pb-insight strong{display:block;font-size:16px}
.pb-insight span{display:block;margin-top:6px;font-size:13px;opacity:.75}
.pb-insight small{display:block;margin-bottom:6px;font-size:12px;font-weight:700;opacity:.65}
.pb-cell small{display:block;opacity:.65;margin-top:3px}
.pb-bar{height:10px;border-radius:999px;background:rgba(127,127,127,.2);overflow:hidden;min-width:140px}
.pb-bar>span{display:block;height:100%;border-radius:999px}
.pb-warn{color:#d97706;font-weight:700}
.pb-danger{color:#dc2626;font-weight:700}
.pb-ok{color:#16a34a;font-weight:700}
.pb-tasks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:18px}
.pb-task{padding:14px;border-radius:14px;border:1px solid rgba(127,127,127,.2);background:rgba(127,127,127,.06)}
.pb-task.is-hot{border-color:rgba(217,119,6,.55)}
.pb-task strong{display:block;font-size:22px;line-height:1}
.pb-task span{display:block;margin-top:6px;font-size:12px;opacity:.75}
.pb-note{margin-top:14px;font-size:12px;opacity:.65}
@media(max-width:1000px){.pb-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.pb-insights{grid-template-columns:1fr}}
@media(max-width:560px){.pb-stats,.pb-tasks{grid-template-columns:1fr}}
</style>

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-filter"></i> Pipeline analytics</span>
        <h2>Pipeline Bottlenecks &amp; Conversion</h2>
        <p>
            Follow candidates through each recruitment stage, measure how many
            move forward, and spot the stages where applications are getting stuck.
        </p>
        <div class="rc-hero__actions">
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/follow_up.php')) ?>">
                <i class="fas fa-route"></i> Follow-Up Pipeline
            </a>
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/interviews.php')) ?>">
                <i class="fas fa-calendar-check"></i> Interviews
            </a>
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/offers.php')) ?>">
                <i class="fas fa-file-signature"></i> Offers
            </a>
        </div>
    </div>
</section>

<?php if (!$ready): ?>

<section class="rc-card rc-space">
    <div class="rc-card__body">
        <div class="rc-empty">
            <i class="fas fa-database"></i>
            <strong>Pipeline data not available</strong>
            <span>The <code>applications</code> and <code>opportunities</code> tables are required for this report.</span>
        </div>
    </div>
</section>

<?php else: ?>

<form method="get" class="pb-filters" action="<?= e($self) ?>">
    <div>
        <label for="pbRange">Applications submitted</label>
        <select id="pbRange" name="range">
            <?php foreach (PB_RANGES as $rg): ?>
                <option value="<?= (int)$rg ?>" <?= $rg === $range ? 'selected' : '' ?>><?= e($rangeLabel($rg)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="pbVacancy">Vacancy</label>
        <select id="pbVacancy" name="vacancy">
            <option value="0">All vacancies</option>
            <?php foreach ($vacancyOptions as $vo): ?>
                <option value="<?= (int)$vo['id'] ?>" <?= (int)$vo['id'] === $vacancyId ? 'selected' : '' ?>>
                    <?= e((string)$vo['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <button type="submit" class="rc-btn rc-btn--primary"><i class="fas fa-filter"></i> Apply</button>
    </div>
</form>

<section class="pb-stats" aria-label="Pipeline summary">
    <div class="pb-stat">
        <i class="fas fa-inbox"></i><strong><?= number_format($totalApps) ?></strong>
        <span>Applications analysed</span>
        <small><?= e($rangeLabel($range)) ?><?= $vacancyId > 0 ? ' · one vacancy' : '' ?></small>
    </div>
    <div class="pb-stat">
        <i class="fas fa-bullseye"></i>
        <strong><?= $reached[1] > 0 ? pb_pct($hired, $reached[1]) . '%' : '—' ?></strong>
        <span>Applied to hired</span>
        <small><?= number_format($hired) ?> of <?= number_format($reached[1]) ?> applications</small>
    </div>
    <div class="pb-stat">
        <i class="fas fa-hourglass-half"></i>
        <strong class="<?= $totalStuck > 0 ? 'pb-warn' : '' ?>"><?= number_format($totalStuck) ?></strong>
        <span>Stuck applications</span>
        <small>of <?= number_format($totalLive) ?> in the live pipeline</small>
    </div>
    <div class="pb-stat">
        <i class="fas fa-stopwatch"></i><strong><?= e(pb_days_text($medianFirstIv)) ?></strong>
        <span>Median days to first interview</span>
        <small><?= number_format(count($toFirstIv)) ?> interviewed application<?= count($toFirstIv) === 1 ? '' : 's' ?></small>
    </div>
</section>

<?php if ($totalApps === 0): ?>

<section class="rc-card rc-space">
    <div class="rc-card__body">
        <div class="rc-empty">
            <i class="fas fa-chart-column"></i>
            <strong>No applications in this view</strong>
            <span>Try a longer period or a different vacancy.</span>
        </div>
    </div>
</section>

<?php else: ?>

<!-- ===================== Bottleneck findings ===================== -->
<section class="rc-card rc-space" id="findings">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Findings</span>
            <h3>Where the pipeline is losing time and candidates</h3>
            <p>Based on the applications shown above.</p>
        </div>
    </div>
    <div class="rc-card__body">
        <div class="pb-insights">

            <div class="pb-insight <?= $stuckStage !== null ? 'is-hot' : '' ?>">
                <small>Most stuck applications</small>
                <?php if ($stuckStage === null): ?>
                    <strong>No stage is stuck</strong>
                    <span>No live application has waited past its stage limit.</span>
                <?php else: ?>
                    <strong><?= e(pb_stage_label($stuckStage)) ?></strong>
                    <span>
                        <?= number_format($live[$stuckStage]['stuck']) ?> of <?= number_format($live[$stuckStage]['n']) ?>
                        live application<?= $live[$stuckStage]['n'] === 1 ? '' : 's' ?> idle for
                        <?= (int)PB_STUCK_DAYS[$stuckStage] ?>+ days; the longest has waited
                        <?= (int)$live[$stuckStage]['idleMax'] ?> days.
                    </span>
                <?php endif; ?>
            </div>

            <div class="pb-insight <?= ($worstStep !== null && $worstRate < 50) ? 'is-hot' : '' ?>">
                <small>Largest drop-off</small>
                <?php if ($worstStep === null): ?>
                    <strong>Not enough data yet</strong>
                    <span>A step needs at least <?= (int)PB_MIN_SAMPLE ?> resolved applications to be judged.</span>
                <?php else: ?>
                    <strong><?= e(PB_STAGES[$worstStep]) ?> → <?= e(PB_STAGES[$worstStep + 1]) ?></strong>
                    <span>
                        Only <?= (int)$worstRate ?>% of applications that left <?= e(PB_STAGES[$worstStep]) ?>
                        moved forward; <?= number_format($dropped[$worstStep]) ?> dropped out.
                    </span>
                <?php endif; ?>
            </div>

            <div class="pb-insight">
                <small>Slowest stage</small>
                <?php if ($slowStage === null): ?>
                    <strong>Not available</strong>
                    <span>
                        <?= $historyTime
                            ? 'Needs at least ' . (int)PB_MIN_SAMPLE . ' completed moves out of a stage.'
                            : 'Needs a usable application status history with timestamps.' ?>
                    </span>
                <?php else: ?>
                    <strong><?= e(PB_STAGES[$slowStage]) ?></strong>
                    <span>
                        Applications typically spend <?= e(pb_days_text($dwellMedian[$slowStage])) ?> here
                        (median of <?= number_format(count($dwell[$slowStage])) ?> completed moves).
                    </span>
                <?php endif; ?>
            </div>

            <div class="pb-insight">
                <small>Time to first interview</small>
                <strong><?= e(pb_days_text($medianFirstIv)) ?> median</strong>
                <span>From application submission to the earliest interview on record.</span>
            </div>

            <div class="pb-insight">
                <small>Time to hire</small>
                <strong><?= $medianHire === null ? 'n/a' : e(pb_days_text($medianHire)) . ' median' ?></strong>
                <span>
                    <?= $historyTime
                        ? 'From submission to the recorded offer acceptance.'
                        : 'Estimated from the last update of each accepted application.' ?>
                </span>
            </div>

            <div class="pb-insight <?= ($ivOverdue + $ivPending) > 0 ? 'is-hot' : '' ?>">
                <small>Interview follow-ups (yours)</small>
                <strong><?= number_format($ivOverdue + $ivPending) ?> outstanding</strong>
                <span>
                    <?= number_format($ivOverdue) ?> past their date and not marked completed,
                    <?= number_format($ivPending) ?> completed and awaiting an outcome.
                </span>
            </div>

        </div>
    </div>
</section>

<!-- ===================== Conversion funnel ===================== -->
<section class="rc-card rc-space" id="funnel">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Conversion</span>
            <h3>Stage-by-stage funnel</h3>
            <p>How many applications reached each stage and what happened to them.</p>
        </div>
    </div>
    <div class="rc-card__body">
        <div class="rc-table-wrap">
            <table class="rc-table">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Reached</th>
                        <th>Share of applied</th>
                        <th>Moved forward</th>
                        <th>Currently here</th>
                        <th>Dropped out</th>
                        <th>Conversion</th>
                        <th>Pass rate</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (PB_STAGES as $k => $label):
                    $isLast   = $k === 8;
                    $advanced = $isLast ? 0 : $reached[$k + 1];
                    $resolved = $isLast ? 0 : $advanced + $dropped[$k];
                ?>
                    <tr>
                        <td><strong><?= e($label) ?></strong></td>
                        <td><strong><?= number_format($reached[$k]) ?></strong></td>
                        <td class="pb-cell">
                            <div class="pb-bar"><span style="width:<?= pb_pct($reached[$k], $reached[1]) ?>%;background:<?= e($stageColors[$k]) ?>"></span></div>
                            <small><?= pb_pct($reached[$k], $reached[1]) ?>%</small>
                        </td>
                        <?php if ($isLast): ?>
                            <td>—</td><td>—</td><td>—</td><td>—</td><td>—</td>
                        <?php else: ?>
                            <td><?= number_format($advanced) ?></td>
                            <td><?= number_format($here[$k]) ?></td>
                            <td><?= number_format($dropped[$k]) ?></td>
                            <td><?= $reached[$k] > 0 ? pb_pct($advanced, $reached[$k]) . '%' : '—' ?></td>
                            <td><?= $resolved > 0 ? pb_pct($advanced, $resolved) . '%' : '—' ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="pb-note">
            "Reached" counts applications that got to a stage or beyond, so an application that skipped an optional
            stage such as Assessment still counts as having passed it. Conversion is the share of those that reached
            the next stage. Pass rate ignores applications still in progress and compares only those that moved
            forward with those that dropped out, which makes it fairer for recent applications.
            <?php if ($paused > 0): ?>
                <?= number_format($paused) ?> application<?= $paused === 1 ? ' is' : 's are' ?> on hold or waitlisted and not counted as moving or dropped.
            <?php endif; ?>
        </p>
    </div>
</section>

<!-- ===================== Stuck analysis ===================== -->
<section class="rc-card rc-space" id="stuck">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Delays</span>
            <h3>Where applications are getting stuck</h3>
            <p>Applications still in progress on vacancies that are open for applications.</p>
        </div>
    </div>
    <div class="rc-card__body">
        <div class="rc-table-wrap">
            <table class="rc-table">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>In progress</th>
                        <th>Average idle time</th>
                        <th>Longest idle</th>
                        <th>Typical time in stage</th>
                        <th>Stuck after</th>
                        <th>Stuck</th>
                        <th>Share stuck</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ([1, 2, 3, 4, 5, 6, 7, 0] as $k):
                    $L   = $live[$k];
                    $avg = $L['idleKnown'] > 0 ? $L['idleSum'] / $L['idleKnown'] : null;
                    $sh  = pb_pct($L['stuck'], $L['n']);
                ?>
                    <tr>
                        <td><strong><?= e(pb_stage_label($k)) ?></strong></td>
                        <td><?= number_format($L['n']) ?></td>
                        <td><?= e(pb_days_text($avg)) ?></td>
                        <td><?= $L['n'] > 0 ? (int)$L['idleMax'] . ' d' : '—' ?></td>
                        <td><?= $k === 0 ? '—' : e(pb_days_text($dwellMedian[$k])) ?></td>
                        <td><?= (int)PB_STUCK_DAYS[$k] ?> days</td>
                        <td class="<?= $L['stuck'] > 0 ? 'pb-warn' : '' ?>"><?= number_format($L['stuck']) ?></td>
                        <td class="pb-cell">
                            <div class="pb-bar"><span style="width:<?= $sh ?>%;background:<?= $sh >= 50 ? '#dc2626' : '#d97706' ?>"></span></div>
                            <small><?= $L['n'] > 0 ? $sh . '%' : '—' ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($hasInterviews): ?>
        <div class="pb-tasks">
            <div class="pb-task <?= $ivOverdue > 0 ? 'is-hot' : '' ?>">
                <strong><?= number_format($ivOverdue) ?></strong>
                <span>Your interviews past their date and not marked completed</span>
            </div>
            <div class="pb-task <?= $ivPending > 0 ? 'is-hot' : '' ?>">
                <strong><?= number_format($ivPending) ?></strong>
                <span>Your completed interviews still waiting for an outcome</span>
            </div>
        </div>
        <?php endif; ?>

        <h3 style="margin:26px 0 10px">Longest-waiting applications</h3>
        <?php if (!$stuckShown): ?>
            <div class="rc-empty">
                <i class="fas fa-circle-check"></i>
                <strong>Nothing is stuck</strong>
                <span>Every live application has moved within its stage limit.</span>
            </div>
        <?php else: ?>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr><th>Candidate</th><th>Vacancy</th><th>Stage</th><th>Idle</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stuckShown as $s): ?>
                        <tr>
                            <td class="pb-cell">
                                <strong><?= e($s['person']) ?></strong>
                                <?php if ($s['reference'] !== ''): ?><small><?= e($s['reference']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= e(url('recruiter/vacancy_view.php?id=' . (int)$s['opp_id'])) ?>"><?= e($s['opp_title']) ?></a>
                            </td>
                            <td><?= e(pb_stage_label($s['stage'])) ?></td>
                            <td><span class="<?= $s['idle'] >= (PB_STUCK_DAYS[$s['stage']] * 2) ? 'pb-danger' : 'pb-warn' ?>"><?= (int)$s['idle'] ?> days</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalStuck > count($stuckShown)): ?>
                <p class="pb-note">Showing the longest-waiting <?= (int)PB_LIST_LIMIT ?> of <?= number_format($totalStuck) ?> stuck applications.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== Vacancy breakdown ===================== -->
<section class="rc-card rc-space" id="vacancies">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">By vacancy</span>
            <h3>Pipeline health per vacancy</h3>
            <p>Vacancies with the most stuck applications first.</p>
        </div>
    </div>
    <div class="rc-card__body">
        <div class="rc-table-wrap">
            <table class="rc-table">
                <thead>
                    <tr>
                        <th>Vacancy</th>
                        <th>Applied</th>
                        <th>Reviewed</th>
                        <th>Shortlisted</th>
                        <th>Interviewed</th>
                        <th>Offers</th>
                        <th>Hired</th>
                        <th>Applied to hired</th>
                        <th>Stuck</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($vacancyShown as $v): ?>
                    <tr>
                        <td class="pb-cell">
                            <a href="<?= e(url('recruiter/vacancy_view.php?id=' . (int)$v['id'])) ?>"><strong><?= e($v['title']) ?></strong></a>
                            <small><span class="rc-status <?= e($statusClass($v['status'])) ?>"><?= e(rc_label($v['status'])) ?></span></small>
                        </td>
                        <td><?= number_format($v['r'][1]) ?></td>
                        <td><?= number_format($v['r'][2]) ?></td>
                        <td><?= number_format($v['r'][3]) ?></td>
                        <td><?= number_format($v['r'][5]) ?></td>
                        <td><?= number_format($v['r'][7]) ?></td>
                        <td><?= number_format($v['r'][8]) ?></td>
                        <td><?= $v['r'][1] > 0 ? pb_pct($v['r'][8], $v['r'][1]) . '%' : '—' ?></td>
                        <td class="pb-cell">
                            <?php if (!isset($openStatusSet[$v['status']])): ?>
                                <small>Closed to applications</small>
                            <?php elseif ($v['stuck'] > 0): ?>
                                <span class="pb-warn"><?= number_format($v['stuck']) ?></span>
                                <small>of <?= number_format($v['live']) ?> live · longest <?= (int)$v['maxIdle'] ?> d</small>
                            <?php else: ?>
                                <span class="pb-ok">0</span>
                                <small>of <?= number_format($v['live']) ?> live</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($vacancies) > count($vacancyShown)): ?>
            <p class="pb-note">Showing <?= (int)PB_VACANCY_LIMIT ?> of <?= number_format(count($vacancies)) ?> vacancies. Filter by vacancy to see another.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== Method ===================== -->
<section class="rc-card rc-space" id="method">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">About these numbers</span>
            <h3>How the pipeline is measured</h3>
        </div>
    </div>
    <div class="rc-card__body">
        <p class="pb-note" style="margin-top:0">
            Applications are grouped by the date they were submitted and exclude drafts. The furthest stage reached
            comes from the current status, the application status history (when available), any interview on record
            (scheduled, completed or no-show) and any offer that was sent or answered. Rejected applications with no
            history are assumed to have been reviewed; withdrawn ones count only as applied unless later stages are on record.
            Interviews from every recruiter are used so the funnel is complete.
        </p>
        <p class="pb-note">
            <?php if ($historyTime): ?>
                Time in stage comes from the application status history, so idle time runs from the moment the
                application entered its current stage.
            <?php elseif ($historyOk): ?>
                The application status history has no timestamp column, so idle time runs from the application's
                last update<?= $hasUpdated ? '' : ' (or its submission date, because no updated_at column exists)' ?>.
            <?php else: ?>
                No usable application status history was found, so idle time runs from the application's last
                update<?= $hasUpdated ? '' : ' (or its submission date, because no updated_at column exists)' ?>.
            <?php endif; ?>
            Applications without history rows fall back to that last update. An application with an interview still ahead
            is not counted as idle. Stuck limits: <?php
                $limits = [];
                foreach (PB_STUCK_DAYS as $k => $d) $limits[] = pb_stage_label($k) . ' ' . $d . ' days';
                echo e(implode(', ', $limits));
            ?>. Only applications on vacancies open for applications are checked for delays.
            Recent applications have had less time to progress, so favour the pass rate when comparing short periods.
        </p>
        <?php if ($otherStatuses): ?>
            <p class="pb-note">
                <?php
                    $parts = [];
                    foreach ($otherStatuses as $label => $n) $parts[] = $label . ' (' . number_format($n) . ')';
                ?>
                Statuses outside the known pipeline were not counted as progress or drop-out: <?= e(implode(', ', $parts)) ?>.
            </p>
        <?php endif; ?>
        <?php if ($truncated): ?>
            <p class="pb-note">This view is limited to the first <?= number_format(PB_ROW_CAP) ?> applications. Narrow the period or choose a vacancy for complete figures.</p>
        <?php endif; ?>
    </div>
</section>

<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
