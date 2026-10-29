<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'productivity';
$pageTitle   = 'Activity Dashboard & Productivity';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

$self = url('recruiter/productivity.php');

/* ==================================================================
 | Constants
 * ==================================================================*/

// Vacancies that are open to applications (same statuses the dashboard lists).
const PA_OPEN_STATUSES = ['published', 'closing_soon'];

// Applications still waiting for a recruiter to make a first review.
const PA_AWAITING_REVIEW = ['submitted', 'under_review'];

// Applications in these states are finished (no further recruiter work).
const PA_FINISHED_STATUSES = ['draft', 'rejected', 'withdrawn', 'offer_accepted', 'offer_declined'];

// An application waiting this many days (or more) is flagged as overdue.
const PA_STALE_DAYS = 7;

// Selectable reporting windows (days).
const PA_RANGES = [7, 30, 90];

// Maximum rows listed in each outstanding-task table.
const PA_LIST_LIMIT = 15;

// Actions that are views of reporting pages, not recruitment work.
const PA_EXCLUDED_ACTIONS = ['analytics_viewed', 'analytics_exported'];

/* ==================================================================
 | Helpers (page-local, all prefixed pa_)
 * ==================================================================*/

if (!function_exists('pa_rows')) {
    /**
     * Runs a prepared SELECT and returns all rows. Never throws: a failing
     * query is logged and yields an empty result so the page still renders.
     */
    function pa_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        try {
            if ($types === '') {
                $res = $conn->query($sql);
                if (!$res) {
                    error_log('productivity.php: ' . $conn->error);
                    return [];
                }
                $rows = [];
                while ($r = $res->fetch_assoc()) $rows[] = $r;
                $res->free();
                return $rows;
            }

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('productivity.php: ' . $conn->error);
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
            error_log('productivity.php: ' . $t->getMessage());
            return [];
        }
    }
}

if (!function_exists('pa_has_column')) {
    function pa_has_column(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $rows = pa_rows(
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

if (!function_exists('pa_placeholders')) {
    function pa_placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}

if (!function_exists('pa_person')) {
    function pa_person(array $row): string
    {
        $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        return $name !== '' ? $name : 'Candidate';
    }
}

if (!function_exists('pa_age_days')) {
    /** Whole days between a date/time string and now (never negative). */
    function pa_age_days(?string $value): ?int
    {
        if ($value === null || trim($value) === '') return null;
        $ts = strtotime($value);
        if ($ts === false) return null;
        return max(0, (int)floor((time() - $ts) / 86400));
    }
}

if (!function_exists('pa_days_until')) {
    /** Calendar days from today until a date (negative when past). */
    function pa_days_until(?string $date): ?int
    {
        if ($date === null || trim($date) === '') return null;
        try {
            $target = new DateTimeImmutable(substr($date, 0, 10));
            $today  = new DateTimeImmutable('today');
        } catch (Throwable $t) {
            return null;
        }
        return (int)$today->diff($target)->format('%r%a');
    }
}

if (!function_exists('pa_pct')) {
    function pa_pct(int $part, int $whole): int
    {
        if ($whole <= 0) return 0;
        return (int)min(100, round(($part / $whole) * 100));
    }
}

if (!function_exists('pa_delta')) {
    /** Describes the change between the current and previous period. */
    function pa_delta(int $now, int $before): array
    {
        if ($before === 0 && $now === 0) return ['text' => 'No change', 'dir' => 'flat'];
        if ($before === 0)               return ['text' => 'New activity', 'dir' => 'up'];
        $pct = (int)round((($now - $before) / $before) * 100);
        if ($pct === 0)                  return ['text' => 'No change', 'dir' => 'flat'];
        return ['text' => ($pct > 0 ? '+' : '') . $pct . '% vs previous period', 'dir' => $pct > 0 ? 'up' : 'down'];
    }
}

/* ==================================================================
 | Filters and reporting window
 * ==================================================================*/

$range = (int)($_GET['range'] ?? 30);
if (!in_array($range, PA_RANGES, true)) $range = 30;

$today     = new DateTimeImmutable('today');
$fromDay   = $today->modify('-' . ($range - 1) . ' days');
$fromStart = $fromDay->format('Y-m-d 00:00:00');
$toEnd     = $today->modify('+1 day')->format('Y-m-d 00:00:00');
$prevStart = $fromDay->modify('-' . $range . ' days')->format('Y-m-d 00:00:00');
$now       = date('Y-m-d H:i:s');
$weekAhead = date('Y-m-d H:i:s', strtotime('+7 days'));

/* ==================================================================
 | Table availability
 * ==================================================================*/

$hasActivity   = rc_has_table($conn, 'recruiter_activity_log');
$hasOpp        = rc_has_table($conn, 'opportunities');
$hasApp        = rc_has_table($conn, 'applications');
$hasInterviews = rc_has_table($conn, 'recruiter_interviews');
$hasShortlists = rc_has_table($conn, 'recruiter_shortlists');
$hasShortCands = rc_has_table($conn, 'recruiter_shortlist_candidates');

/* ==================================================================
 | Activity metrics (this recruiter only)
 * ==================================================================*/

$actionLabels = [
    'candidate_search'         => 'Candidate searches',
    'candidate_profile_viewed' => 'Profiles viewed',
    'candidate_shortlisted'    => 'Candidates shortlisted',
    'shortlist_created'        => 'Shortlists created',
    'shortlist_view'           => 'Shortlists viewed',
    'talent_pool_viewed'       => 'Talent pool views',
    'recruiter_reports_viewed' => 'Report views',
    'interview_scheduled'      => 'Interviews scheduled',
    'interview_rescheduled'    => 'Interviews rescheduled',
    'vacancy_created'          => 'Vacancies created',
    'vacancy_status_changed'   => 'Vacancy status changes',
];

$actionRows    = [];
$activityTotal = 0;
$activePrev    = 0;
$activeDays    = 0;
$trend         = [];
$lastActivity  = null;

if ($hasActivity) {

    $exPh = pa_placeholders(PA_EXCLUDED_ACTIONS);
    $exTy = str_repeat('s', count(PA_EXCLUDED_ACTIONS));

    $actionRows = pa_rows(
        $conn,
        "SELECT action, COUNT(*) AS total, MAX(created_at) AS last_at
         FROM recruiter_activity_log
         WHERE recruiter_id = ? AND created_at >= ? AND created_at < ?
           AND action NOT IN ($exPh)
         GROUP BY action
         ORDER BY total DESC, action ASC",
        'iss' . $exTy,
        array_merge([$recruiterId, $fromStart, $toEnd], PA_EXCLUDED_ACTIONS)
    );

    foreach ($actionRows as $r) {
        $activityTotal += (int)$r['total'];
        if ($lastActivity === null || (string)$r['last_at'] > $lastActivity) {
            $lastActivity = (string)$r['last_at'];
        }
    }

    $r = pa_rows(
        $conn,
        "SELECT COUNT(*) AS n FROM recruiter_activity_log
         WHERE recruiter_id = ? AND created_at >= ? AND created_at < ?
           AND action NOT IN ($exPh)",
        'iss' . $exTy,
        array_merge([$recruiterId, $prevStart, $fromStart], PA_EXCLUDED_ACTIONS)
    );
    $activePrev = (int)($r[0]['n'] ?? 0);

    $r = pa_rows(
        $conn,
        "SELECT COUNT(DISTINCT DATE(created_at)) AS n FROM recruiter_activity_log
         WHERE recruiter_id = ? AND created_at >= ? AND created_at < ?
           AND action NOT IN ($exPh)",
        'iss' . $exTy,
        array_merge([$recruiterId, $fromStart, $toEnd], PA_EXCLUDED_ACTIONS)
    );
    $activeDays = (int)($r[0]['n'] ?? 0);

    $trendRows = pa_rows(
        $conn,
        "SELECT DATE(created_at) AS d, COUNT(*) AS n FROM recruiter_activity_log
         WHERE recruiter_id = ? AND created_at >= ? AND created_at < ?
           AND action NOT IN ($exPh)
         GROUP BY DATE(created_at)",
        'iss' . $exTy,
        array_merge([$recruiterId, $fromStart, $toEnd], PA_EXCLUDED_ACTIONS)
    );
    $trendMap = [];
    foreach ($trendRows as $r) $trendMap[(string)$r['d']] = (int)$r['n'];

    for ($i = 0; $i < $range; $i++) {
        $d = $fromDay->modify('+' . $i . ' days');
        $k = $d->format('Y-m-d');
        $trend[] = ['date' => $d, 'n' => $trendMap[$k] ?? 0];
    }
}

$trendMax = 0;
foreach ($trend as $t) $trendMax = max($trendMax, $t['n']);

$activityDelta = pa_delta($activityTotal, $activePrev);

$countFor = static function (string $action) use ($actionRows): int {
    foreach ($actionRows as $r) {
        if ((string)$r['action'] === $action) return (int)$r['total'];
    }
    return 0;
};

/* ==================================================================
 | Shortlist metrics
 * ==================================================================*/

$shortlistsCreated = 0;
$emptyShortlists   = 0;

if ($hasShortlists) {
    $r = pa_rows(
        $conn,
        'SELECT COUNT(*) AS n FROM recruiter_shortlists
         WHERE recruiter_id = ? AND created_at >= ? AND created_at < ?',
        'iss',
        [$recruiterId, $fromStart, $toEnd]
    );
    $shortlistsCreated = (int)($r[0]['n'] ?? 0);

    if ($hasShortCands) {
        $r = pa_rows(
            $conn,
            'SELECT COUNT(*) AS n FROM (
                 SELECT rs.id
                 FROM recruiter_shortlists rs
                 LEFT JOIN recruiter_shortlist_candidates rsc ON rsc.shortlist_id = rs.id
                 WHERE rs.recruiter_id = ?
                 GROUP BY rs.id
                 HAVING COUNT(rsc.id) = 0
             ) x',
            'i',
            [$recruiterId]
        );
        $emptyShortlists = (int)($r[0]['n'] ?? 0);
    }
}

/* ==================================================================
 | Interview metrics and outstanding interview tasks
 * ==================================================================*/

$ivCounts = ['scheduled' => 0, 'completed' => 0, 'cancelled' => 0, 'no_show' => 0];
$ivTotal  = 0;

$ivOverdue  = [];   // scheduled time has passed but not marked completed / no-show
$ivPending  = [];   // completed but outcome still pending
$ivUpcoming = [];   // scheduled within the next 7 days

$ivOverdueCount  = 0;
$ivPendingCount  = 0;
$ivUpcomingCount = 0;

if ($hasInterviews) {

    $rows = pa_rows(
        $conn,
        'SELECT status, COUNT(*) AS n FROM recruiter_interviews
         WHERE recruiter_id = ? AND scheduled_at >= ? AND scheduled_at < ?
         GROUP BY status',
        'iss',
        [$recruiterId, $fromStart, $toEnd]
    );
    foreach ($rows as $r) {
        $s = (string)$r['status'];
        if (isset($ivCounts[$s])) $ivCounts[$s] = (int)$r['n'];
        $ivTotal += (int)$r['n'];
    }

    // Vacancy title and candidate name for the task lists.
    if ($hasOpp && $hasApp) {
        $ivJoin = 'LEFT JOIN applications ap ON ap.id = ri.application_id
                   LEFT JOIN opportunities o ON o.id = COALESCE(ri.opportunity_id, ap.opportunity_id)';
        $ivOppSelect = 'o.id AS opportunity_id, o.title AS opportunity_title';
    } elseif ($hasOpp) {
        $ivJoin = 'LEFT JOIN opportunities o ON o.id = ri.opportunity_id';
        $ivOppSelect = 'o.id AS opportunity_id, o.title AS opportunity_title';
    } else {
        $ivJoin = '';
        $ivOppSelect = 'NULL AS opportunity_id, NULL AS opportunity_title';
    }

    $ivSelect = "SELECT ri.id, ri.application_id, ri.round_label, ri.scheduled_at, ri.mode,
                        u.first_name, u.last_name, $ivOppSelect
                 FROM recruiter_interviews ri
                 LEFT JOIN users u ON u.id = ri.candidate_id
                 $ivJoin";

    $r = pa_rows(
        $conn,
        "SELECT COUNT(*) AS n FROM recruiter_interviews
         WHERE recruiter_id = ? AND status = 'scheduled' AND scheduled_at < ?",
        'is',
        [$recruiterId, $now]
    );
    $ivOverdueCount = (int)($r[0]['n'] ?? 0);

    $ivOverdue = pa_rows(
        $conn,
        $ivSelect . " WHERE ri.recruiter_id = ? AND ri.status = 'scheduled' AND ri.scheduled_at < ?
                      ORDER BY ri.scheduled_at ASC, ri.id ASC LIMIT " . (int)PA_LIST_LIMIT,
        'is',
        [$recruiterId, $now]
    );

    $r = pa_rows(
        $conn,
        "SELECT COUNT(*) AS n FROM recruiter_interviews
         WHERE recruiter_id = ? AND status = 'completed' AND outcome = 'pending'",
        'i',
        [$recruiterId]
    );
    $ivPendingCount = (int)($r[0]['n'] ?? 0);

    $ivPending = pa_rows(
        $conn,
        $ivSelect . " WHERE ri.recruiter_id = ? AND ri.status = 'completed' AND ri.outcome = 'pending'
                      ORDER BY ri.scheduled_at ASC, ri.id ASC LIMIT " . (int)PA_LIST_LIMIT,
        'i',
        [$recruiterId]
    );

    $r = pa_rows(
        $conn,
        "SELECT COUNT(*) AS n FROM recruiter_interviews
         WHERE recruiter_id = ? AND status = 'scheduled' AND scheduled_at >= ? AND scheduled_at < ?",
        'iss',
        [$recruiterId, $now, $weekAhead]
    );
    $ivUpcomingCount = (int)($r[0]['n'] ?? 0);

    $ivUpcoming = pa_rows(
        $conn,
        $ivSelect . " WHERE ri.recruiter_id = ? AND ri.status = 'scheduled'
                        AND ri.scheduled_at >= ? AND ri.scheduled_at < ?
                      ORDER BY ri.scheduled_at ASC, ri.id ASC LIMIT " . (int)PA_LIST_LIMIT,
        'iss',
        [$recruiterId, $now, $weekAhead]
    );
}

/* ==================================================================
 | Open vacancies: progress, workload and outstanding applications
 * ==================================================================*/

$vacancies      = [];
$awaitingTotal  = 0;
$awaitingStale  = 0;
$awaitingRows   = [];
$openApplicants = 0;

if ($hasOpp) {

    $openPh = pa_placeholders(PA_OPEN_STATUSES);
    $openTy = str_repeat('s', count(PA_OPEN_STATUSES));

    $vacancies = pa_rows(
        $conn,
        "SELECT id, title, organisation, province, city, application_close_date,
                available_positions, status
         FROM opportunities
         WHERE status IN ($openPh)
         ORDER BY (application_close_date IS NULL) ASC, application_close_date ASC, id DESC
         LIMIT 200",
        $openTy,
        PA_OPEN_STATUSES
    );

    $byId = [];
    foreach ($vacancies as $i => $v) {
        $vacancies[$i]['_total']    = 0;
        $vacancies[$i]['_awaiting'] = 0;
        $vacancies[$i]['_active']   = 0;
        $vacancies[$i]['_accepted'] = 0;
        $vacancies[$i]['_ivDone']   = 0;
        $vacancies[$i]['_ivOpen']   = 0;
        $byId[(int)$v['id']]        = $i;
    }

    if ($hasApp && $byId) {

        $ids  = array_keys($byId);
        $idPh = pa_placeholders($ids);
        $idTy = str_repeat('i', count($ids));

        $rows = pa_rows(
            $conn,
            "SELECT opportunity_id, status, COUNT(*) AS n
             FROM applications
             WHERE status <> 'draft' AND opportunity_id IN ($idPh)
             GROUP BY opportunity_id, status",
            $idTy,
            $ids
        );

        foreach ($rows as $r) {
            $oid = (int)$r['opportunity_id'];
            if (!isset($byId[$oid])) continue;
            $i = $byId[$oid];
            $n = (int)$r['n'];
            $s = (string)$r['status'];

            $vacancies[$i]['_total'] += $n;
            if (!in_array($s, PA_FINISHED_STATUSES, true))   $vacancies[$i]['_active']   += $n;
            if (in_array($s, PA_AWAITING_REVIEW, true))      $vacancies[$i]['_awaiting'] += $n;
            if ($s === 'offer_accepted')                     $vacancies[$i]['_accepted'] += $n;
        }

        foreach ($vacancies as $v) {
            $awaitingTotal  += $v['_awaiting'];
            $openApplicants += $v['_active'];
        }

        // Oldest applications still waiting for a first review.
        $awPh = pa_placeholders(PA_AWAITING_REVIEW);
        $awTy = str_repeat('s', count(PA_AWAITING_REVIEW));

        $waitAge = "COALESCE(a.submitted_at, a.created_at)";

        $r = pa_rows(
            $conn,
            "SELECT COUNT(*) AS n
             FROM applications a
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             WHERE o.status IN ($openPh) AND a.status IN ($awPh)
               AND $waitAge < ?",
            $openTy . $awTy . 's',
            array_merge(
                PA_OPEN_STATUSES,
                PA_AWAITING_REVIEW,
                [date('Y-m-d H:i:s', strtotime('-' . PA_STALE_DAYS . ' days'))]
            )
        );
        $awaitingStale = (int)($r[0]['n'] ?? 0);

        $awaitingRows = pa_rows(
            $conn,
            "SELECT a.id, a.application_reference, a.status, a.opportunity_id,
                    $waitAge AS waiting_since,
                    u.first_name, u.last_name, o.title AS opportunity_title
             FROM applications a
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             LEFT JOIN users u ON u.id = a.candidate_id
             WHERE o.status IN ($openPh) AND a.status IN ($awPh)
             ORDER BY $waitAge ASC, a.id ASC
             LIMIT " . (int)PA_LIST_LIMIT,
            $openTy . $awTy,
            array_merge(PA_OPEN_STATUSES, PA_AWAITING_REVIEW)
        );
    }

    // This recruiter's interview workload per open vacancy.
    if ($hasInterviews && $byId) {

        $ids  = array_keys($byId);
        $idPh = pa_placeholders($ids);
        $idTy = str_repeat('i', count($ids));

        if ($hasApp) {
            $oppExpr = 'COALESCE(ri.opportunity_id, ap.opportunity_id)';
            $from    = 'recruiter_interviews ri LEFT JOIN applications ap ON ap.id = ri.application_id';
        } else {
            $oppExpr = 'ri.opportunity_id';
            $from    = 'recruiter_interviews ri';
        }

        $rows = pa_rows(
            $conn,
            "SELECT $oppExpr AS opp_id, ri.status, COUNT(*) AS n
             FROM $from
             WHERE ri.recruiter_id = ? AND $oppExpr IN ($idPh)
             GROUP BY $oppExpr, ri.status",
            'i' . $idTy,
            array_merge([$recruiterId], $ids)
        );

        foreach ($rows as $r) {
            $oid = (int)$r['opp_id'];
            if (!isset($byId[$oid])) continue;
            $i = $byId[$oid];
            if ((string)$r['status'] === 'completed') $vacancies[$i]['_ivDone'] += (int)$r['n'];
            if ((string)$r['status'] === 'scheduled') $vacancies[$i]['_ivOpen'] += (int)$r['n'];
        }
    }
}

/* ==================================================================
 | Vacancy attention flags
 * ==================================================================*/

$vacanciesFlagged = 0;

foreach ($vacancies as $i => $v) {
    $flags = [];
    $days  = pa_days_until($v['application_close_date'] ?? null);

    if ($days !== null && $days < 0) {
        $flags[] = 'Past closing date but still open';
    } elseif ($days !== null && $days <= 7) {
        $flags[] = $days === 0 ? 'Closes today' : 'Closes in ' . $days . ' day' . ($days === 1 ? '' : 's');
    }
    if ($hasApp && (int)$v['_total'] === 0) {
        $flags[] = 'No applications yet';
    }
    if ((int)$v['_awaiting'] > 0) {
        $flags[] = (int)$v['_awaiting'] . ' awaiting review';
    }
    $positions = (int)($v['available_positions'] ?? 0);
    if ($positions > 0 && (int)$v['_accepted'] >= $positions) {
        $flags[] = 'All positions filled';
    }

    $vacancies[$i]['_days']  = $days;
    $vacancies[$i]['_flags'] = $flags;
    if ($flags) $vacanciesFlagged++;
}

/* ==================================================================
 | Outstanding task summary
 * ==================================================================*/

$outstanding = [
    'awaiting'  => $awaitingTotal,
    'overdue'   => $ivOverdueCount,
    'pending'   => $ivPendingCount,
    'closing'   => 0,
    'empty'     => $emptyShortlists,
];
foreach ($vacancies as $v) {
    if ($v['_days'] !== null && $v['_days'] >= 0 && $v['_days'] <= 7) $outstanding['closing']++;
}
$outstandingTotal = array_sum($outstanding);

$statusClass = static function (string $s): string {
    if (in_array($s, ['published', 'closing_soon'], true)) return 'rc-status--success';
    if ($s === 'closed') return 'rc-status--danger';
    return '';
};

$rangeLink = static function (int $r) use ($self): string {
    return $self . '?range=' . $r;
};

require __DIR__ . '/_layout_start.php';
?>

<style>
.pa-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px}
.pa-stat{padding:20px;border-radius:18px;background:rgba(127,127,127,.08);border:1px solid rgba(127,127,127,.18);color:inherit;text-decoration:none;display:block}
.pa-stat i{display:block;margin-bottom:12px;font-size:20px}
.pa-stat strong{display:block;font-size:26px;line-height:1}
.pa-stat span{display:block;margin-top:8px;font-size:13px;opacity:.7}
.pa-stat small{display:block;margin-top:5px;font-size:11px;opacity:.6}
.pa-up{color:#16a34a;opacity:1!important;font-weight:700}
.pa-down{color:#dc2626;opacity:1!important;font-weight:700}
.pa-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:16px}
.pa-pill{padding:7px 14px;border-radius:999px;border:1px solid rgba(127,127,127,.35);color:inherit;text-decoration:none;font-size:13px;font-weight:700}
.pa-pill.is-active{background:rgba(127,127,127,.2)}
.pa-cell small{display:block;opacity:.65;margin-top:3px}
.pa-warn{color:#d97706;font-weight:700}
.pa-danger{color:#dc2626;font-weight:700}
.pa-bar{height:8px;border-radius:999px;background:rgba(127,127,127,.2);overflow:hidden;min-width:120px}
.pa-bar>span{display:block;height:100%;background:#16a34a;border-radius:999px}
.pa-flags{display:flex;flex-direction:column;gap:3px}
.pa-flags small{display:block;font-size:12px;font-weight:600;color:#d97706}
.pa-trend{display:flex;align-items:flex-end;gap:2px;height:140px;padding:8px 0}
.pa-trend span{flex:1;min-width:2px;border-radius:3px 3px 0 0;background:#2563eb;opacity:.85}
.pa-trend span.is-zero{background:rgba(127,127,127,.3);opacity:1}
.pa-axis{display:flex;justify-content:space-between;font-size:11px;opacity:.6}
.pa-tasks{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:6px}
.pa-task{padding:14px;border-radius:14px;border:1px solid rgba(127,127,127,.2);background:rgba(127,127,127,.06)}
.pa-task strong{display:block;font-size:22px;line-height:1}
.pa-task span{display:block;margin-top:6px;font-size:12px;opacity:.75}
.pa-task.is-hot{border-color:rgba(217,119,6,.55)}
.pa-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}
.pa-note{margin-top:14px;font-size:12px;opacity:.65}
@media(max-width:1100px){.pa-tasks{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:1000px){.pa-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.pa-grid{grid-template-columns:1fr}}
@media(max-width:560px){.pa-stats,.pa-tasks{grid-template-columns:1fr}}
</style>

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-gauge-high"></i> Activity dashboard</span>
        <h2>Recruiter Activity Dashboard &amp; Productivity</h2>
        <p>
            Monitor your recruitment activity, track progress on open vacancies
            and see the tasks that still need your attention.
        </p>
        <div class="rc-hero__actions">
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/vacancies.php')) ?>">
                <i class="fas fa-briefcase"></i> Vacancies
            </a>
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/interviews.php')) ?>">
                <i class="fas fa-calendar-check"></i> Interviews
            </a>
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/activity_log.php')) ?>">
                <i class="fas fa-clock-rotate-left"></i> Activity log
            </a>
        </div>
    </div>
</section>

<div class="pa-toolbar" role="group" aria-label="Reporting period">
    <?php foreach (PA_RANGES as $r): ?>
        <a class="pa-pill <?= $r === $range ? 'is-active' : '' ?>" href="<?= e($rangeLink($r)) ?>">Last <?= (int)$r ?> days</a>
    <?php endforeach; ?>
</div>

<section class="pa-stats" aria-label="Productivity summary">
    <div class="pa-stat">
        <i class="fas fa-bolt"></i><strong><?= number_format($activityTotal) ?></strong>
        <span>Recorded activities</span>
        <small class="<?= $activityDelta['dir'] === 'up' ? 'pa-up' : ($activityDelta['dir'] === 'down' ? 'pa-down' : '') ?>"><?= e($activityDelta['text']) ?></small>
    </div>
    <div class="pa-stat">
        <i class="fas fa-calendar-day"></i><strong><?= number_format($activeDays) ?></strong>
        <span>Active days</span>
        <small>of <?= (int)$range ?> days<?= $lastActivity ? ' · last ' . e(rc_date($lastActivity, '')) : '' ?></small>
    </div>
    <div class="pa-stat">
        <i class="fas fa-calendar-check"></i><strong><?= number_format($ivTotal) ?></strong>
        <span>Interviews in period</span>
        <small><?= number_format($ivCounts['completed']) ?> completed · <?= number_format($ivCounts['cancelled'] + $ivCounts['no_show']) ?> cancelled or no-show</small>
    </div>
    <div class="pa-stat">
        <i class="fas fa-list-check"></i><strong><?= number_format($shortlistsCreated) ?></strong>
        <span>Shortlists created</span>
        <small><?= number_format($countFor('candidate_shortlisted')) ?> candidates shortlisted</small>
    </div>
</section>

<!-- ===================== Outstanding tasks ===================== -->
<section class="rc-card rc-space" id="tasks">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Outstanding</span>
            <h3>Tasks needing your attention</h3>
            <p><?= $outstandingTotal > 0
                ? number_format($outstandingTotal) . ' item' . ($outstandingTotal === 1 ? '' : 's') . ' outstanding right now.'
                : 'You are all caught up.' ?></p>
        </div>
    </div>

    <div class="rc-card__body">
        <div class="pa-tasks">
            <div class="pa-task <?= $outstanding['awaiting'] > 0 ? 'is-hot' : '' ?>">
                <strong><?= number_format($outstanding['awaiting']) ?></strong>
                <span>Applications awaiting review<?= $awaitingStale > 0 ? ' (' . number_format($awaitingStale) . ' waiting ' . (int)PA_STALE_DAYS . '+ days)' : '' ?></span>
            </div>
            <div class="pa-task <?= $outstanding['overdue'] > 0 ? 'is-hot' : '' ?>">
                <strong><?= number_format($outstanding['overdue']) ?></strong>
                <span>Past interviews not yet closed out</span>
            </div>
            <div class="pa-task <?= $outstanding['pending'] > 0 ? 'is-hot' : '' ?>">
                <strong><?= number_format($outstanding['pending']) ?></strong>
                <span>Completed interviews without an outcome</span>
            </div>
            <div class="pa-task <?= $outstanding['closing'] > 0 ? 'is-hot' : '' ?>">
                <strong><?= number_format($outstanding['closing']) ?></strong>
                <span>Vacancies closing within 7 days</span>
            </div>
            <div class="pa-task <?= $outstanding['empty'] > 0 ? 'is-hot' : '' ?>">
                <strong><?= number_format($outstanding['empty']) ?></strong>
                <span>Shortlists with no candidates</span>
            </div>
        </div>
    </div>
</section>

<?php if ($hasInterviews): ?>
<section class="pa-grid rc-space">

    <div class="rc-card">
        <div class="rc-card__header">
            <div>
                <span class="rc-eyebrow">Interviews</span>
                <h3>Needs closing out</h3>
                <p>Interviews whose time has passed but are still marked scheduled, and completed interviews with no outcome.</p>
            </div>
        </div>
        <div class="rc-card__body">
            <?php if (!$ivOverdue && !$ivPending): ?>
                <div class="rc-empty">
                    <i class="fas fa-circle-check"></i>
                    <strong>Nothing to close out</strong>
                    <span>All past interviews have been recorded.</span>
                </div>
            <?php else: ?>
                <div class="rc-table-wrap">
                    <table class="rc-table">
                        <thead>
                            <tr><th>Candidate</th><th>Interview</th><th>What is needed</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ivOverdue as $iv): ?>
                            <tr>
                                <td class="pa-cell">
                                    <strong><?= e(pa_person($iv)) ?></strong>
                                    <small><?= e((string)($iv['opportunity_title'] ?? 'Vacancy not linked')) ?></small>
                                </td>
                                <td class="pa-cell">
                                    <?= e((string)$iv['round_label']) ?>
                                    <small><?= e(rc_datetime((string)$iv['scheduled_at'])) ?></small>
                                </td>
                                <td><span class="pa-danger">Mark as completed or no-show</span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($ivPending as $iv): ?>
                            <tr>
                                <td class="pa-cell">
                                    <strong><?= e(pa_person($iv)) ?></strong>
                                    <small><?= e((string)($iv['opportunity_title'] ?? 'Vacancy not linked')) ?></small>
                                </td>
                                <td class="pa-cell">
                                    <?= e((string)$iv['round_label']) ?>
                                    <small><?= e(rc_datetime((string)$iv['scheduled_at'])) ?></small>
                                </td>
                                <td><span class="pa-warn">Record the outcome</span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($ivOverdueCount > count($ivOverdue) || $ivPendingCount > count($ivPending)): ?>
                    <p class="pa-note">Showing the oldest <?= (int)PA_LIST_LIMIT ?> of each. <a href="<?= e(url('recruiter/interviews.php')) ?>">Open interviews</a> to see them all.</p>
                <?php else: ?>
                    <p class="pa-note"><a href="<?= e(url('recruiter/interviews.php')) ?>">Open interviews</a> to update these.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="rc-card">
        <div class="rc-card__header">
            <div>
                <span class="rc-eyebrow">Next 7 days</span>
                <h3>Upcoming interviews</h3>
                <p><?= number_format($ivUpcomingCount) ?> scheduled.</p>
            </div>
        </div>
        <div class="rc-card__body">
            <?php if (!$ivUpcoming): ?>
                <div class="rc-empty">
                    <i class="fas fa-calendar"></i>
                    <strong>No interviews coming up</strong>
                    <span>Nothing is scheduled in the next 7 days.</span>
                </div>
            <?php else: ?>
                <div class="rc-table-wrap">
                    <table class="rc-table">
                        <thead>
                            <tr><th>When</th><th>Candidate</th><th>Round</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ivUpcoming as $iv): ?>
                            <tr>
                                <td class="pa-cell"><strong><?= e(rc_date((string)$iv['scheduled_at'], '')) ?></strong>
                                    <small><?= e(date('H:i', (int)strtotime((string)$iv['scheduled_at']))) ?> · <?= e(rc_label((string)$iv['mode'])) ?></small></td>
                                <td class="pa-cell">
                                    <strong><?= e(pa_person($iv)) ?></strong>
                                    <small><?= e((string)($iv['opportunity_title'] ?? 'Vacancy not linked')) ?></small>
                                </td>
                                <td><?= e((string)$iv['round_label']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===================== Vacancy progress ===================== -->
<section class="rc-card rc-space" id="vacancies">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Assigned vacancies</span>
            <h3>Progress on open vacancies</h3>
            <p>
                All vacancies open for applications are assigned to every recruiter.
                <?= number_format(count($vacancies)) ?> open
                <?= $hasApp ? ' · ' . number_format($openApplicants) . ' applications in progress' : '' ?>
                · <?= number_format($vacanciesFlagged) ?> flagged.
            </p>
        </div>
    </div>

    <div class="rc-card__body">
        <?php if (!$hasOpp): ?>
            <div class="rc-empty">
                <i class="fas fa-database"></i>
                <strong>Opportunities table not found</strong>
                <span>Vacancies are the platform's <code>opportunities</code> records.</span>
            </div>
        <?php elseif (!$vacancies): ?>
            <div class="rc-empty">
                <i class="fas fa-briefcase"></i>
                <strong>No open vacancies</strong>
                <span>There are no vacancies open for applications right now.</span>
            </div>
        <?php else: ?>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Vacancy</th>
                            <th>Closing</th>
                            <th>Applications</th>
                            <th>Positions filled</th>
                            <th>My interviews</th>
                            <th>Attention</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vacancies as $v):
                        $id        = (int)$v['id'];
                        $positions = (int)($v['available_positions'] ?? 0);
                        $loc       = trim((string)($v['city'] ?? '') . ', ' . (string)($v['province'] ?? ''), ' ,');
                    ?>
                        <tr>
                            <td class="pa-cell">
                                <strong><?= e((string)($v['title'] ?? 'Untitled vacancy')) ?></strong>
                                <?php if (!empty($v['organisation'])): ?><small><?= e((string)$v['organisation']) ?></small><?php endif; ?>
                                <?php if ($loc !== ''): ?><small><?= e($loc) ?></small><?php endif; ?>
                                <small><span class="rc-status <?= e($statusClass((string)$v['status'])) ?>"><?= e(rc_label((string)$v['status'])) ?></span></small>
                            </td>
                            <td class="pa-cell">
                                <?= e(rc_date((string)($v['application_close_date'] ?? ''), 'Not set')) ?>
                                <?php if ($v['_days'] !== null): ?>
                                    <small><?= $v['_days'] < 0
                                        ? e((string)abs($v['_days']) . ' day' . (abs($v['_days']) === 1 ? '' : 's') . ' overdue')
                                        : e($v['_days'] === 0 ? 'Closes today' : $v['_days'] . ' day' . ($v['_days'] === 1 ? '' : 's') . ' left') ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="pa-cell">
                                <strong><?= number_format((int)$v['_total']) ?></strong> total
                                <small><?= number_format((int)$v['_active']) ?> in progress</small>
                                <small><?= number_format((int)$v['_awaiting']) ?> awaiting review</small>
                            </td>
                            <td class="pa-cell">
                                <?php if ($positions > 0): ?>
                                    <?= (int)$v['_accepted'] ?> of <?= $positions ?>
                                    <div class="pa-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                                         aria-valuenow="<?= pa_pct((int)$v['_accepted'], $positions) ?>">
                                        <span style="width:<?= pa_pct((int)$v['_accepted'], $positions) ?>%"></span>
                                    </div>
                                <?php else: ?>
                                    <small>No position count set</small>
                                <?php endif; ?>
                            </td>
                            <td class="pa-cell">
                                <?= number_format((int)$v['_ivDone']) ?> completed
                                <small><?= number_format((int)$v['_ivOpen']) ?> scheduled</small>
                            </td>
                            <td>
                                <?php if ($v['_flags']): ?>
                                    <div class="pa-flags">
                                        <?php foreach ($v['_flags'] as $f): ?>
                                            <small><i class="fas fa-triangle-exclamation"></i> <?= e($f) ?></small>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <small>On track</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="rc-btn rc-btn--primary rc-btn--small" href="<?= e(url('recruiter/vacancy_view.php?id=' . $id)) ?>">
                                    <i class="fas fa-chart-simple"></i> Track
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($vacancies) >= 200): ?>
                <p class="pa-note">Showing the 200 vacancies closing soonest.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== Applications awaiting review ===================== -->
<?php if ($hasApp && $hasOpp): ?>
<section class="rc-card rc-space" id="awaiting">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Outstanding</span>
            <h3>Applications awaiting review</h3>
            <p>Oldest first, on vacancies that are open for applications.</p>
        </div>
    </div>
    <div class="rc-card__body">
        <?php if (!$awaitingRows): ?>
            <div class="rc-empty">
                <i class="fas fa-circle-check"></i>
                <strong>No applications waiting</strong>
                <span>Every application on an open vacancy has been picked up.</span>
            </div>
        <?php else: ?>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr><th>Candidate</th><th>Vacancy</th><th>Status</th><th>Waiting</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($awaitingRows as $a):
                        $age = pa_age_days((string)$a['waiting_since']);
                    ?>
                        <tr>
                            <td class="pa-cell">
                                <strong><?= e(pa_person($a)) ?></strong>
                                <?php if (!empty($a['application_reference'])): ?><small><?= e((string)$a['application_reference']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= e(url('recruiter/vacancy_view.php?id=' . (int)$a['opportunity_id'])) ?>"><?= e((string)($a['opportunity_title'] ?? 'Vacancy')) ?></a>
                            </td>
                            <td><?= e(rc_label((string)$a['status'])) ?></td>
                            <td class="pa-cell">
                                <?php if ($age === null): ?>
                                    <small>Unknown</small>
                                <?php else: ?>
                                    <span class="<?= $age >= PA_STALE_DAYS ? 'pa-danger' : '' ?>"><?= (int)$age ?> day<?= $age === 1 ? '' : 's' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($awaitingTotal > count($awaitingRows)): ?>
                <p class="pa-note">Showing the oldest <?= (int)PA_LIST_LIMIT ?> of <?= number_format($awaitingTotal) ?>. Use "Track" on a vacancy to see the rest.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===================== Activity breakdown ===================== -->
<section class="rc-card rc-space" id="activity">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Productivity</span>
            <h3>Activity over the last <?= (int)$range ?> days</h3>
            <p>Your recorded actions per day. Report and analytics views are not counted.</p>
        </div>
    </div>
    <div class="rc-card__body">
        <?php if (!$hasActivity): ?>
            <div class="rc-empty">
                <i class="fas fa-database"></i>
                <strong>Activity log not available</strong>
                <span>The <code>recruiter_activity_log</code> table was not found.</span>
            </div>
        <?php elseif ($activityTotal === 0): ?>
            <div class="rc-empty">
                <i class="fas fa-chart-column"></i>
                <strong>No activity in this period</strong>
                <span>Searches, shortlists and interviews you complete will appear here.</span>
            </div>
        <?php else: ?>
            <div class="pa-trend" aria-label="Daily activity">
                <?php foreach ($trend as $t):
                    $h = $trendMax > 0 ? max(2, (int)round(($t['n'] / $trendMax) * 100)) : 2; ?>
                    <span class="<?= $t['n'] === 0 ? 'is-zero' : '' ?>"
                          style="height:<?= $t['n'] === 0 ? 2 : $h ?>%"
                          title="<?= e($t['date']->format('d M Y') . ': ' . $t['n']) ?>"></span>
                <?php endforeach; ?>
            </div>
            <div class="pa-axis">
                <span><?= e($fromDay->format('d M')) ?></span>
                <span><?= e($today->format('d M')) ?></span>
            </div>

            <div class="rc-table-wrap" style="margin-top:18px">
                <table class="rc-table">
                    <thead>
                        <tr><th>Action</th><th>Count</th><th>Share</th><th>Last done</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($actionRows as $r):
                        $key   = (string)$r['action'];
                        $label = $actionLabels[$key] ?? rc_label($key);
                        $n     = (int)$r['total'];
                    ?>
                        <tr>
                            <td><?= e($label) ?></td>
                            <td><strong><?= number_format($n) ?></strong></td>
                            <td class="pa-cell">
                                <div class="pa-bar"><span style="width:<?= pa_pct($n, $activityTotal) ?>%;background:#2563eb"></span></div>
                                <small><?= pa_pct($n, $activityTotal) ?>%</small>
                            </td>
                            <td><?= e(rc_date((string)$r['last_at'], '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <p class="pa-note">
            Activities are counted from your own activity log. An application is "awaiting review"
            while its status is Submitted or Under review; it is flagged after <?= (int)PA_STALE_DAYS ?> or more days.
            Positions filled counts applications with an accepted offer.
        </p>
    </div>
</section>

<?php require __DIR__ . '/_layout_end.php'; ?>
