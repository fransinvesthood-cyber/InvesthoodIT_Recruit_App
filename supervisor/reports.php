<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
$user = current_user();
$flashes = render_flashes();
$currentPage = 'reports';
/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
$conn = Database::getConnection();
/*
|--------------------------------------------------------------------------
| Supervisor
|--------------------------------------------------------------------------
*/
$supervisorId = (int)(
    $user['id']
    ?? $user['user_id']
    ?? 0
);
if ($supervisorId <= 0) {
    http_response_code(403);
    exit('Invalid Supervisor account.');
}
/*
|--------------------------------------------------------------------------
| Supervisor Details
|--------------------------------------------------------------------------
*/
$firstName = trim(
    (string)(
        $user['first_name']
        ?? ''
    )
);
$lastName = trim(
    (string)(
        $user['last_name']
        ?? ''
    )
);
$supervisorName = trim(
    $firstName . ' ' . $lastName
);
if ($supervisorName === '') {
    $supervisorName = 'Supervisor';
}
/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$programmeId = (int)(
    $_GET['programme_id']
    ?? 0
);
$cohortId = (int)(
    $_GET['cohort_id']
    ?? 0
);
$cohortStatus = trim(
    (string)(
        $_GET['status']
        ?? ''
    )
);
$dateFrom = trim(
    (string)(
        $_GET['date_from']
        ?? ''
    )
);
$dateTo = trim(
    (string)(
        $_GET['date_to']
        ?? ''
    )
);
/*
|--------------------------------------------------------------------------
| Validate Dates
|--------------------------------------------------------------------------
*/
if (
    $dateFrom !== ''
    &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $dateFrom
    )
) {
    $dateFrom = '';
}
if (
    $dateTo !== ''
    &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $dateTo
    )
) {
    $dateTo = '';
}
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function supervisorReportDate(
    ?string $date
): string {
    if (
        empty($date)
        ||
        $date === '0000-00-00'
    ) {
        return '—';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return '—';
    }
    return date(
        'd M Y',
        $timestamp
    );
}
function supervisorReportStatusLabel(
    ?string $status
): string {
    $status = trim(
        (string)$status
    );
    if ($status === '') {
        return 'Unknown';
    }
    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}
function supervisorReportStatusClass(
    ?string $status
): string {
    switch (
        strtolower(
            trim(
                (string)$status
            )
        )
    ) {
        case 'active':
            return 'success';
        case 'completed':
            return 'primary';
        case 'withdrawn':
            return 'danger';
        case 'inactive':
        case 'closed':
            return 'secondary';
        case 'upcoming':
        case 'pending':
            return 'warning';
        default:
            return 'neutral';
    }
}
function supervisorReportCompletionRate(
    int $currentCandidates,
    int $completedCandidates
): int {
    if ($currentCandidates <= 0) {
        return 0;
    }
    return (int)round(
        (
            $completedCandidates
            /
            $currentCandidates
        )
        * 100
    );
}
function supervisorReportInitials(
    string $name
): string {
    $parts = preg_split(
        '/\s+/',
        trim($name)
    );
    $initials = '';
    foreach (
        array_slice(
            $parts,
            0,
            2
        )
        as $part
    ) {
        if ($part !== '') {
            $initials .= strtoupper(
                substr(
                    $part,
                    0,
                    1
                )
            );
        }
    }
    return $initials !== ''
        ? $initials
        : 'C';
}
/*
|--------------------------------------------------------------------------
| Assigned Programmes Filter
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT DISTINCT
            p.id,
            p.name
        FROM programmes p
        INNER JOIN cohorts c
            ON c.programme_id = p.id
        WHERE c.supervisor_id = ?
        ORDER BY p.name ASC
    ",
    'i',
    [$supervisorId]
);
$result = $stmt->get_result();
$programmes = [];
while (
    $row = $result->fetch_assoc()
) {
    $programmes[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Assigned Cohorts Filter
|--------------------------------------------------------------------------
*/
$cohortSql = "
    SELECT
        c.id,
        c.name,
        c.status,
        p.name AS programme_name
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE c.supervisor_id = ?
";
$cohortTypes = 'i';
$cohortParams = [
    $supervisorId
];
if ($programmeId > 0) {
    $cohortSql .= "
        AND p.id = ?
    ";
    $cohortTypes .= 'i';
    $cohortParams[] =
        $programmeId;
}
$cohortSql .= "
    ORDER BY
        CASE
            WHEN c.status = 'active'
            THEN 1
            ELSE 2
        END,
        c.name ASC
";
$stmt = Database::prepare(
    $cohortSql,
    $cohortTypes,
    $cohortParams
);
$result = $stmt->get_result();
$cohorts = [];
while (
    $row = $result->fetch_assoc()
) {
    $cohorts[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Available Cohort Statuses
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT DISTINCT
            c.status
        FROM cohorts c
        WHERE c.supervisor_id = ?
          AND c.status IS NOT NULL
          AND c.status <> ''
        ORDER BY c.status ASC
    ",
    'i',
    [$supervisorId]
);
$result = $stmt->get_result();
$statuses = [];
while (
    $row = $result->fetch_assoc()
) {
    $statuses[] =
        $row['status'];
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Report Query
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        c.id,
        c.name AS cohort_name,
        c.status AS cohort_status,
        c.start_date,
        c.end_date,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.status AS programme_status,
        COUNT(
            DISTINCT CASE
                WHEN cp.status <> 'withdrawn'
                THEN cp.user_id
            END
        ) AS current_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'active'
                THEN cp.user_id
            END
        ) AS active_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'completed'
                THEN cp.user_id
            END
        ) AS completed_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'withdrawn'
                THEN cp.user_id
            END
        ) AS withdrawn_candidates
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE c.supervisor_id = ?
";
$types = 'i';
$params = [
    $supervisorId
];
/*
|--------------------------------------------------------------------------
| Programme Filter
|--------------------------------------------------------------------------
*/
if ($programmeId > 0) {
    $sql .= "
        AND p.id = ?
    ";
    $types .= 'i';
    $params[] =
        $programmeId;
}
/*
|--------------------------------------------------------------------------
| Cohort Filter
|--------------------------------------------------------------------------
*/
if ($cohortId > 0) {
    $sql .= "
        AND c.id = ?
    ";
    $types .= 'i';
    $params[] =
        $cohortId;
}
/*
|--------------------------------------------------------------------------
| Cohort Status Filter
|--------------------------------------------------------------------------
*/
if ($cohortStatus !== '') {
    $sql .= "
        AND c.status = ?
    ";
    $types .= 's';
    $params[] =
        $cohortStatus;
}
/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/
if ($dateFrom !== '') {
    $sql .= "
        AND (
            c.end_date IS NULL
            OR c.end_date >= ?
        )
    ";
    $types .= 's';
    $params[] =
        $dateFrom;
}
if ($dateTo !== '') {
    $sql .= "
        AND (
            c.start_date IS NULL
            OR c.start_date <= ?
        )
    ";
    $types .= 's';
    $params[] =
        $dateTo;
}
/*
|--------------------------------------------------------------------------
| Group / Sort
|--------------------------------------------------------------------------
*/
$sql .= "
    GROUP BY
        c.id,
        c.name,
        c.status,
        c.start_date,
        c.end_date,
        p.id,
        p.name,
        p.type,
        p.status
    ORDER BY
        CASE
            WHEN c.status = 'active'
            THEN 1
            ELSE 2
        END,
        c.start_date DESC,
        c.name ASC
";
$stmt = Database::prepare(
    $sql,
    $types,
    $params
);
$result = $stmt->get_result();
$reportRows = [];
while (
    $row = $result->fetch_assoc()
) {
    $row['current_candidates'] =
        (int)$row['current_candidates'];
    $row['active_candidates'] =
        (int)$row['active_candidates'];
    $row['completed_candidates'] =
        (int)$row['completed_candidates'];
    $row['withdrawn_candidates'] =
        (int)$row['withdrawn_candidates'];
    $row['completion_rate'] =
        supervisorReportCompletionRate(
            $row['current_candidates'],
            $row['completed_candidates']
        );
    $reportRows[] =
        $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Aggregate Report KPIs
|--------------------------------------------------------------------------
*/
$totalCohorts =
    count($reportRows);
$totalCurrentCandidates = 0;
$totalActiveCandidates = 0;
$totalCompletedCandidates = 0;
$totalWithdrawnCandidates = 0;
foreach (
    $reportRows
    as $row
) {
    $totalCurrentCandidates +=
        $row['current_candidates'];
    $totalActiveCandidates +=
        $row['active_candidates'];
    $totalCompletedCandidates +=
        $row['completed_candidates'];
    $totalWithdrawnCandidates +=
        $row['withdrawn_candidates'];
}
$overallCompletionRate =
    supervisorReportCompletionRate(
        $totalCurrentCandidates,
        $totalCompletedCandidates
    );
/*
|--------------------------------------------------------------------------
| Attention Needed
|--------------------------------------------------------------------------
|
| Active candidates in cohorts ending within the next 30 days.
|
|--------------------------------------------------------------------------
*/
$attentionSql = "
    SELECT
        c.id AS cohort_id,
        c.name AS cohort_name,
        c.end_date,
        p.name AS programme_name,
        COUNT(
            DISTINCT cp.user_id
        ) AS active_candidates
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    INNER JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE c.supervisor_id = ?
      AND cp.status = 'active'
      AND c.end_date IS NOT NULL
      AND c.end_date >= CURDATE()
      AND c.end_date <= DATE_ADD(
            CURDATE(),
            INTERVAL 30 DAY
      )
    GROUP BY
        c.id,
        c.name,
        c.end_date,
        p.name
    ORDER BY
        c.end_date ASC
    LIMIT 6
";
$stmt = Database::prepare(
    $attentionSql,
    'i',
    [$supervisorId]
);
$result = $stmt->get_result();
$attentionRows = [];
while (
    $row = $result->fetch_assoc()
) {
    $row['active_candidates'] =
        (int)(
            $row['active_candidates']
            ?? 0
        );
    $attentionRows[] =
        $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        Cohort Progress Report
    </title>
    <!-- ============================================================
         EARLY THEME
    ============================================================= -->
    <script>
        (function () {
            try {
                const savedTheme =
                    localStorage.getItem(
                        'investhood-supervisor-theme'
                    );
                document.documentElement
                    .setAttribute(
                        'data-theme',
                        savedTheme === 'dark'
                            ? 'dark'
                            : 'light'
                    );
            } catch (error) {
                document.documentElement
                    .setAttribute(
                        'data-theme',
                        'light'
                    );
            }
        })();
    </script>
    <!-- ============================================================
         FONTS / ICONS
    ============================================================= -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >
    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >
    <link
        rel="stylesheet"
        href="<?= url('css/styles.css') ?>"
    >
    <style>
        /* ============================================================
           PAGE
        ============================================================ */
        * {
            box-sizing: border-box;
        }
        body.dashboard-page {
            margin: 0;
            font-family:
                'Inter',
                sans-serif;
            background:
                var(--sv-page-bg, #f5f7fb);
            color:
                var(--sv-text, #111827);
        }
        .dashboard {
            display: flex !important;
            gap: 0 !important;
            width: 100%;
            min-height: 100vh;
        }
        .dashboard__main {
            flex: 1;
            min-width: 0;
            width: auto !important;
            margin-left: 0 !important;
            overflow-x: hidden;
        }
        /* ============================================================
           CONTENT
        ============================================================ */
        .report-page {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            padding:
                1.5rem;
        }
        /* ============================================================
           BREADCRUMB
        ============================================================ */
        .report-breadcrumb {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .45rem;
            flex-wrap: wrap;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .7rem;
        }
        .report-breadcrumb a {
            color:
                var(--sv-text-soft, #64748b);
            text-decoration: none;
        }
        .report-breadcrumb a:hover {
            color:
                var(--sv-primary, #2563eb);
        }
        .report-breadcrumb i {
            font-size: .5rem;
        }
        /* ============================================================
           HERO
        ============================================================ */
        .report-hero {
            position: relative;
            margin-bottom: 1.25rem;
            padding:
                1.55rem 1.65rem;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            border-radius: 22px;
            background:
                linear-gradient(
                    135deg,
                    #2563eb 0%,
                    #4f46e5 50%,
                    #7c3aed 100%
                );
            color: #fff;
            box-shadow:
                0 16px 40px
                rgba(37,99,235,.18);
        }
        .report-hero::after {
            content: '';
            width: 260px;
            height: 260px;
            position: absolute;
            right: -80px;
            top: -130px;
            border-radius: 50%;
            background:
                rgba(255,255,255,.08);
        }
        .report-hero__content {
            position: relative;
            z-index: 2;
        }
        .report-hero__eyebrow {
            display: block;
            margin-bottom: .4rem;
            color:
                rgba(255,255,255,.78);
            font-size: .63rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .report-hero h2 {
            margin:
                0 0 .45rem;
            font-size:
                clamp(
                    1.35rem,
                    3vw,
                    2rem
                );
            font-weight: 800;
        }
        .report-hero p {
            max-width: 700px;
            margin: 0;
            color:
                rgba(255,255,255,.82);
            font-size: .75rem;
            line-height: 1.6;
        }
        .report-hero__score {
            min-width: 150px;
            position: relative;
            z-index: 2;
            padding: 1rem;
            border:
                1px solid
                rgba(255,255,255,.16);
            border-radius: 18px;
            background:
                rgba(255,255,255,.10);
            backdrop-filter:
                blur(12px);
            text-align: center;
        }
        .report-hero__score strong {
            display: block;
            font-size: 1.65rem;
            font-weight: 800;
        }
        .report-hero__score span {
            display: block;
            margin-top: .2rem;
            color:
                rgba(255,255,255,.78);
            font-size: .61rem;
        }
        /* ============================================================
           KPI GRID
        ============================================================ */
        .report-kpis {
            margin-bottom: 1.25rem;
            display: grid;
            grid-template-columns:
                repeat(
                    5,
                    minmax(0, 1fr)
                );
            gap: 1rem;
        }
        .report-kpi {
            padding: 1rem;
            border:
                1px solid
                var(--sv-border, #e5e7eb);
            border-radius: 17px;
            background:
                var(--sv-card-bg, #fff);
            box-shadow:
                var(
                    --sv-shadow,
                    0 8px 25px
                    rgba(15,23,42,.05)
                );
        }
        .report-kpi__top {
            margin-bottom: .8rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
        }
        .report-kpi__icon {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            font-size: .72rem;
        }
        .report-kpi__icon--blue {
            background:
                rgba(37,99,235,.10);
            color: #2563eb;
        }
        .report-kpi__icon--purple {
            background:
                rgba(124,58,237,.10);
            color: #7c3aed;
        }
        .report-kpi__icon--green {
            background:
                rgba(34,197,94,.10);
            color: #16a34a;
        }
        .report-kpi__icon--orange {
            background:
                rgba(245,158,11,.12);
            color: #d97706;
        }
        .report-kpi__icon--red {
            background:
                rgba(239,68,68,.10);
            color: #dc2626;
        }
        .report-kpi strong {
            display: block;
            color:
                var(--sv-text, #111827);
            font-size: 1.25rem;
            font-weight: 800;
        }
        .report-kpi span {
            color:
                var(--sv-text-soft, #64748b);
            font-size: .62rem;
        }
        /* ============================================================
           CARD
        ============================================================ */
        .report-card {
            margin-bottom: 1.25rem;
            overflow: hidden;
            border:
                1px solid
                var(--sv-border, #e5e7eb);
            border-radius: 18px;
            background:
                var(--sv-card-bg, #fff);
            box-shadow:
                var(
                    --sv-shadow,
                    0 8px 25px
                    rgba(15,23,42,.05)
                );
        }
        .report-card__header {
            padding:
                1rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom:
                1px solid
                var(--sv-border, #e5e7eb);
        }
        .report-card__title strong {
            display: block;
            color:
                var(--sv-text, #111827);
            font-size: .78rem;
        }
        .report-card__title span {
            display: block;
            margin-top: .2rem;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .58rem;
        }
        /* ============================================================
           FILTERS
        ============================================================ */
        .report-filters {
            padding: 1rem;
        }
        .report-filter-grid {
            display: grid;
            grid-template-columns:
                1.1fr
                1.1fr
                .8fr
                .8fr
                .8fr
                auto;
            gap: .8rem;
            align-items: end;
        }
        .report-field label {
            display: block;
            margin-bottom: .35rem;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .56rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .report-field select,
        .report-field input {
            width: 100%;
            min-height: 42px;
            padding:
                .65rem .75rem;
            border:
                1px solid
                var(--sv-border, #e5e7eb);
            border-radius: 11px;
            outline: none;
            background:
                var(--sv-card-soft, #f8fafc);
            color:
                var(--sv-text, #111827);
            font-family: inherit;
            font-size: .67rem;
        }
        .report-field select:focus,
        .report-field input:focus {
            border-color:
                rgba(37,99,235,.45);
            box-shadow:
                0 0 0 3px
                rgba(37,99,235,.08);
        }
        .report-filter-actions {
            display: flex;
            gap: .5rem;
        }
        .report-button {
            min-height: 42px;
            padding:
                .65rem .9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            border: 0;
            border-radius: 11px;
            font-family: inherit;
            font-size: .62rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .report-button--primary {
            background:
                var(--sv-primary, #2563eb);
            color: #fff;
        }
        .report-button--secondary {
            border:
                1px solid
                var(--sv-border, #e5e7eb);
            background:
                var(--sv-card-bg, #fff);
            color:
                var(--sv-text, #111827);
        }
        /* ============================================================
           TABLE
        ============================================================ */
        .report-table-wrap {
            width: 100%;
            overflow-x: auto;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th {
            padding:
                .7rem .8rem;
            border-bottom:
                1px solid
                var(--sv-border, #e5e7eb);
            background:
                var(--sv-card-soft, #f8fafc);
            color:
                var(--sv-text-soft, #64748b);
            font-size: .54rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .report-table td {
            padding:
                .85rem .8rem;
            border-bottom:
                1px solid
                var(--sv-border, #e5e7eb);
            color:
                var(--sv-text, #111827);
            font-size: .63rem;
            vertical-align: middle;
        }
        .report-table tbody tr:last-child td {
            border-bottom: 0;
        }
        .report-table tbody tr:hover {
            background:
                var(--sv-card-soft, #f8fafc);
        }
        /* ============================================================
           COHORT
        ============================================================ */
        .report-cohort {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-width: 175px;
        }
        .report-cohort__avatar {
            width: 37px;
            height: 37px;
            flex:
                0 0 37px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #7c3aed
                );
            color: #fff;
            font-size: .62rem;
            font-weight: 800;
        }
        .report-cohort strong {
            display: block;
            color:
                var(--sv-text, #111827);
            font-size: .65rem;
        }
        .report-cohort span {
            display: block;
            margin-top: .15rem;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .55rem;
        }
        /* ============================================================
           BADGE
        ============================================================ */
        .report-badge {
            display: inline-flex;
            align-items: center;
            padding:
                .28rem .48rem;
            border-radius: 999px;
            font-size: .53rem;
            font-weight: 700;
        }
        .report-badge--success {
            background:
                rgba(34,197,94,.10);
            color: #16a34a;
        }
        .report-badge--primary {
            background:
                rgba(37,99,235,.10);
            color: #2563eb;
        }
        .report-badge--danger {
            background:
                rgba(239,68,68,.10);
            color: #dc2626;
        }
        .report-badge--warning {
            background:
                rgba(245,158,11,.12);
            color: #d97706;
        }
        .report-badge--secondary,
        .report-badge--neutral {
            background:
                var(--sv-card-soft, #f8fafc);
            color:
                var(--sv-text-soft, #64748b);
        }
        /* ============================================================
           PROGRESS
        ============================================================ */
        .report-progress {
            min-width: 150px;
        }
        .report-progress__header {
            margin-bottom: .35rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .54rem;
        }
        .report-progress__header strong {
            color:
                var(--sv-text, #111827);
            font-size: .56rem;
        }
        .report-progress__track {
            width: 100%;
            height: 7px;
            overflow: hidden;
            border-radius: 999px;
            background:
                var(--sv-card-soft, #f1f5f9);
        }
        .report-progress__bar {
            height: 100%;
            border-radius: inherit;
            background:
                linear-gradient(
                    90deg,
                    #2563eb,
                    #7c3aed
                );
        }
        /* ============================================================
           GRID LOWER
        ============================================================ */
        .report-lower-grid {
            display: grid;
            grid-template-columns:
                1.35fr
                .65fr;
            gap: 1rem;
        }
        /* ============================================================
           OUTCOME SUMMARY
        ============================================================ */
        .report-outcomes {
            padding: 1rem;
        }
        .report-outcome {
            padding:
                .85rem 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom:
                1px solid
                var(--sv-border, #e5e7eb);
        }
        .report-outcome:last-child {
            border-bottom: 0;
        }
        .report-outcome__left {
            display: flex;
            align-items: center;
            gap: .65rem;
        }
        .report-outcome__icon {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: .65rem;
        }
        .report-outcome strong {
            display: block;
            color:
                var(--sv-text, #111827);
            font-size: .64rem;
        }
        .report-outcome span {
            display: block;
            margin-top: .15rem;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .54rem;
        }
        .report-outcome__value {
            font-size: .8rem !important;
            font-weight: 800;
        }
        /* ============================================================
           ATTENTION
        ============================================================ */
        .report-attention {
            padding: 1rem;
        }
        .report-attention__item {
            padding:
                .75rem;
            margin-bottom: .65rem;
            border:
                1px solid
                rgba(245,158,11,.18);
            border-radius: 12px;
            background:
                rgba(245,158,11,.055);
        }
        .report-attention__item:last-child {
            margin-bottom: 0;
        }
        .report-attention__item strong {
            display: block;
            color:
                var(--sv-text, #111827);
            font-size: .63rem;
        }
        .report-attention__item span {
            display: block;
            margin-top: .2rem;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .55rem;
            line-height: 1.5;
        }
        .report-attention__action {
            margin-top: .5rem;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            color:
                var(--sv-primary, #2563eb);
            font-size: .55rem;
            font-weight: 700;
            text-decoration: none;
        }
        /* ============================================================
           EMPTY
        ============================================================ */
        .report-empty {
            padding:
                3rem 1rem;
            text-align: center;
        }
        .report-empty__icon {
            width: 58px;
            height: 58px;
            margin:
                0 auto .8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background:
                var(--sv-primary-soft, #eff6ff);
            color:
                var(--sv-primary, #2563eb);
            font-size: 1rem;
        }
        .report-empty strong {
            display: block;
            color:
                var(--sv-text, #111827);
            font-size: .75rem;
        }
        .report-empty span {
            display: block;
            max-width: 400px;
            margin:
                .35rem auto 0;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .6rem;
            line-height: 1.5;
        }
        /* ============================================================
           SECURITY NOTICE
        ============================================================ */
        .report-security {
            margin-top: 1.25rem;
            padding:
                .9rem 1rem;
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            border:
                1px solid
                rgba(37,99,235,.12);
            border-radius: 14px;
            background:
                var(--sv-primary-soft, #eff6ff);
        }
        .report-security i {
            margin-top: .1rem;
            color:
                var(--sv-primary, #2563eb);
        }
        .report-security strong {
            display: block;
            color:
                var(--sv-text, #111827);
            font-size: .62rem;
        }
        .report-security span {
            display: block;
            margin-top: .2rem;
            color:
                var(--sv-text-soft, #64748b);
            font-size: .55rem;
            line-height: 1.5;
        }
        /* ============================================================
           DARK MODE
        ============================================================ */
        html[data-theme="dark"]
        .report-table tbody tr:hover {
            background:
                rgba(255,255,255,.025);
        }
        html[data-theme="dark"]
        .report-field select,
        html[data-theme="dark"]
        .report-field input {
            color-scheme: dark;
        }
        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:1250px) {
            .report-kpis {
                grid-template-columns:
                    repeat(
                        3,
                        minmax(0, 1fr)
                    );
            }
            .report-filter-grid {
                grid-template-columns:
                    repeat(
                        3,
                        minmax(0, 1fr)
                    );
            }
        }
        @media(max-width:900px) {
            .dashboard__main {
                width: 100% !important;
                margin-left: 0 !important;
            }
            .report-page {
                padding: 1rem;
            }
            .report-hero {
                align-items: flex-start;
                flex-direction: column;
            }
            .report-hero__score {
                width: 100%;
            }
            .report-lower-grid {
                grid-template-columns: 1fr;
            }
        }
        @media(max-width:700px) {
            .report-kpis {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }
            .report-filter-grid {
                grid-template-columns:
                    1fr;
            }
            .report-filter-actions {
                width: 100%;
            }
            .report-filter-actions
            .report-button {
                flex: 1;
            }
        }
        @media(max-width:430px) {
            .report-kpis {
                grid-template-columns: 1fr;
            }
            .report-hero {
                padding: 1.2rem;
            }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard">
    <?php
    require_once __DIR__ . '/sidebar.php';
    ?>
    <main class="dashboard__main">
        <?php
        require_once __DIR__ . '/navbar.php';
        ?>
        <div class="report-page">
            <!-- ====================================================
                 FLASH MESSAGES
            ===================================================== -->
            <?= $flashes ?>
            <!-- ====================================================
                 BREADCRUMB
            ===================================================== -->
            <nav class="report-breadcrumb">
                <a
                    href="<?= url(
                        'supervisor/dashboard.php'
                    ) ?>"
                >
                    Dashboard
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>
                    Cohort Progress
                </span>
            </nav>
            <!-- ====================================================
                 HERO
            ===================================================== -->
            <section class="report-hero">
                <div class="report-hero__content">
                    <span class="report-hero__eyebrow">
                        Supervisor Reporting
                    </span>
                    <h2>
                        Cohort Progress Report
                    </h2>
                    <p>
                        Monitor candidate participation, completion,
                        withdrawals and overall progress across the
                        cohorts assigned to you.
                    </p>
                </div>
                <div class="report-hero__score">
                    <strong>
                        <?= $overallCompletionRate ?>%
                    </strong>
                    <span>
                        Overall completion rate
                    </span>
                </div>
            </section>
            <!-- ====================================================
                 KPI CARDS
            ===================================================== -->
            <section class="report-kpis">
                <article class="report-kpi">
                    <div class="report-kpi__top">
                        <div
                            class="
                                report-kpi__icon
                                report-kpi__icon--blue
                            "
                        >
                            <i class="fas fa-people-group"></i>
                        </div>
                    </div>
                    <strong>
                        <?= number_format(
                            $totalCohorts
                        ) ?>
                    </strong>
                    <span>
                        Assigned Cohorts
                    </span>
                </article>
                <article class="report-kpi">
                    <div class="report-kpi__top">
                        <div
                            class="
                                report-kpi__icon
                                report-kpi__icon--purple
                            "
                        >
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <strong>
                        <?= number_format(
                            $totalCurrentCandidates
                        ) ?>
                    </strong>
                    <span>
                        Current Candidates
                    </span>
                </article>
                <article class="report-kpi">
                    <div class="report-kpi__top">
                        <div
                            class="
                                report-kpi__icon
                                report-kpi__icon--orange
                            "
                        >
                            <i class="fas fa-person-running"></i>
                        </div>
                    </div>
                    <strong>
                        <?= number_format(
                            $totalActiveCandidates
                        ) ?>
                    </strong>
                    <span>
                        Active Candidates
                    </span>
                </article>
                <article class="report-kpi">
                    <div class="report-kpi__top">
                        <div
                            class="
                                report-kpi__icon
                                report-kpi__icon--green
                            "
                        >
                            <i class="fas fa-circle-check"></i>
                        </div>
                    </div>
                    <strong>
                        <?= number_format(
                            $totalCompletedCandidates
                        ) ?>
                    </strong>
                    <span>
                        Completed
                    </span>
                </article>
                <article class="report-kpi">
                    <div class="report-kpi__top">
                        <div
                            class="
                                report-kpi__icon
                                report-kpi__icon--red
                            "
                        >
                            <i class="fas fa-user-minus"></i>
                        </div>
                    </div>
                    <strong>
                        <?= number_format(
                            $totalWithdrawnCandidates
                        ) ?>
                    </strong>
                    <span>
                        Withdrawn
                    </span>
                </article>
            </section>
            <!-- ====================================================
                 FILTERS
            ===================================================== -->
            <section class="report-card">
                <div class="report-card__header">
                    <div class="report-card__title">
                        <strong>
                            Report Filters
                        </strong>
                        <span>
                            Refine the report using your assigned
                            programmes and cohorts.
                        </span>
                    </div>
                    <i
                        class="fas fa-filter"
                        style="
                            color:
                            var(--sv-text-soft,#64748b)
                        "
                    ></i>
                </div>
                <form
                    method="GET"
                    action=""
                    class="report-filters"
                >
                    <div class="report-filter-grid">
                        <!-- PROGRAMME -->
                        <div class="report-field">
                            <label for="programme_id">
                                Programme
                            </label>
                            <select
                                name="programme_id"
                                id="programme_id"
                            >
                                <option value="0">
                                    All Programmes
                                </option>
                                <?php foreach (
                                    $programmes
                                    as $programme
                                ): ?>
                                    <option
                                        value="<?= (int)$programme['id'] ?>"
                                        <?= $programmeId
                                            ===
                                            (int)$programme['id']
                                                ? 'selected'
                                                : '' ?>
                                    >
                                        <?= e(
                                            $programme['name']
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- COHORT -->
                        <div class="report-field">
                            <label for="cohort_id">
                                Cohort
                            </label>
                            <select
                                name="cohort_id"
                                id="cohort_id"
                            >
                                <option value="0">
                                    All Cohorts
                                </option>
                                <?php foreach (
                                    $cohorts
                                    as $cohort
                                ): ?>
                                    <option
                                        value="<?= (int)$cohort['id'] ?>"
                                        <?= $cohortId
                                            ===
                                            (int)$cohort['id']
                                                ? 'selected'
                                                : '' ?>
                                    >
                                        <?= e(
                                            $cohort['name']
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- STATUS -->
                        <div class="report-field">
                            <label for="status">
                                Cohort Status
                            </label>
                            <select
                                name="status"
                                id="status"
                            >
                                <option value="">
                                    All Statuses
                                </option>
                                <?php foreach (
                                    $statuses
                                    as $status
                                ): ?>
                                    <option
                                        value="<?= e($status) ?>"
                                        <?= $cohortStatus
                                            === $status
                                                ? 'selected'
                                                : '' ?>
                                    >
                                        <?= e(
                                            supervisorReportStatusLabel(
                                                $status
                                            )
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- FROM -->
                        <div class="report-field">
                            <label for="date_from">
                                From
                            </label>
                            <input
                                type="date"
                                name="date_from"
                                id="date_from"
                                value="<?= e(
                                    $dateFrom
                                ) ?>"
                            >
                        </div>
                        <!-- TO -->
                        <div class="report-field">
                            <label for="date_to">
                                To
                            </label>
                            <input
                                type="date"
                                name="date_to"
                                id="date_to"
                                value="<?= e(
                                    $dateTo
                                ) ?>"
                            >
                        </div>
                        <!-- ACTIONS -->
                        <div class="report-filter-actions">
                            <button
                                type="submit"
                                class="
                                    report-button
                                    report-button--primary
                                "
                            >
                                <i class="fas fa-filter"></i>
                                Filter
                            </button>
                            <?php if (
                                $programmeId > 0
                                ||
                                $cohortId > 0
                                ||
                                $cohortStatus !== ''
                                ||
                                $dateFrom !== ''
                                ||
                                $dateTo !== ''
                            ): ?>
                                <a
                                    href="<?= url(
                                        'supervisor/reports.php'
                                    ) ?>"
                                    class="
                                        report-button
                                        report-button--secondary
                                    "
                                >
                                    Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </section>
            <!-- ====================================================
                 COHORT REPORT
            ===================================================== -->
            <section class="report-card">
                <div class="report-card__header">
                    <div class="report-card__title">
                        <strong>
                            Cohort Performance
                        </strong>
                        <span>
                            <?= number_format(
                                count($reportRows)
                            ) ?>
                            cohort<?= count($reportRows) === 1
                                ? ''
                                : 's' ?>
                            in this report
                        </span>
                    </div>
                    <i
                        class="fas fa-chart-column"
                        style="
                            color:
                            var(--sv-primary,#2563eb)
                        "
                    ></i>
                </div>
                <?php if (
                    empty($reportRows)
                ): ?>
                    <div class="report-empty">
                        <div class="report-empty__icon">
                            <i class="fas fa-chart-column"></i>
                        </div>
                        <strong>
                            No cohort data found
                        </strong>
                        <span>
                            No assigned cohorts match the current
                            report filters.
                        </span>
                    </div>
                <?php else: ?>
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead>
                            <tr>
                                <th>
                                    Cohort
                                </th>
                                <th>
                                    Status
                                </th>
                                <th>
                                    Dates
                                </th>
                                <th>
                                    Current
                                </th>
                                <th>
                                    Active
                                </th>
                                <th>
                                    Completed
                                </th>
                                <th>
                                    Withdrawn
                                </th>
                                <th>
                                    Completion
                                </th>
                                <th>
                                    Action
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (
                                $reportRows
                                as $row
                            ): ?>
                                <tr>
                                    <!-- COHORT -->
                                    <td>
                                        <div class="report-cohort">
                                            <div class="report-cohort__avatar">
                                                <?= e(
                                                    supervisorReportInitials(
                                                        $row[
                                                            'cohort_name'
                                                        ]
                                                    )
                                                ) ?>
                                            </div>
                                            <div>
                                                <strong>
                                                    <?= e(
                                                        $row[
                                                            'cohort_name'
                                                        ]
                                                    ) ?>
                                                </strong>
                                                <span>
                                                    <?= e(
                                                        $row[
                                                            'programme_name'
                                                        ]
                                                    ) ?>
                                                    <?php if (
                                                        !empty(
                                                            $row[
                                                                'programme_type'
                                                            ]
                                                        )
                                                    ): ?>
                                                        ·
                                                        <?= e(
                                                            $row[
                                                                'programme_type'
                                                            ]
                                                        ) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- STATUS -->
                                    <td>
                                        <span
                                            class="
                                                report-badge
                                                report-badge--<?= e(
                                                    supervisorReportStatusClass(
                                                        $row[
                                                            'cohort_status'
                                                        ]
                                                    )
                                                ) ?>
                                            "
                                        >
                                            <?= e(
                                                supervisorReportStatusLabel(
                                                    $row[
                                                        'cohort_status'
                                                    ]
                                                )
                                            ) ?>
                                        </span>
                                    </td>
                                    <!-- DATES -->
                                    <td>
                                        <?= e(
                                            supervisorReportDate(
                                                $row[
                                                    'start_date'
                                                ]
                                            )
                                        ) ?>
                                        <br>
                                        <small
                                            style="
                                                color:
                                                var(--sv-text-soft,#64748b)
                                            "
                                        >
                                            to
                                            <?= e(
                                                supervisorReportDate(
                                                    $row[
                                                        'end_date'
                                                    ]
                                                )
                                            ) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            $row[
                                                'current_candidates'
                                            ]
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            $row[
                                                'active_candidates'
                                            ]
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            $row[
                                                'completed_candidates'
                                            ]
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            $row[
                                                'withdrawn_candidates'
                                            ]
                                        ) ?>
                                    </td>
                                    <!-- PROGRESS -->
                                    <td>
                                        <div class="report-progress">
                                            <div class="report-progress__header">
                                                <span>
                                                    Progress
                                                </span>
                                                <strong>
                                                    <?= (int)$row[
                                                        'completion_rate'
                                                    ] ?>%
                                                </strong>
                                            </div>
                                            <div class="report-progress__track">
                                                <div
                                                    class="report-progress__bar"
                                                    style="
                                                        width:
                                                        <?= max(
                                                            0,
                                                            min(
                                                                100,
                                                                (int)$row[
                                                                    'completion_rate'
                                                                ]
                                                            )
                                                        ) ?>%;
                                                    "
                                                ></div>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- ACTION -->
                                    <td>
                                        <a
                                            href="<?= url(
                                                'supervisor/cohort_view.php?id=' .
                                                (int)$row['id']
                                            ) ?>"
                                            class="
                                                report-button
                                                report-button--secondary
                                            "
                                        >
                                            <i class="fas fa-eye"></i>
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <!-- ====================================================
                 LOWER ANALYTICS
            ===================================================== -->
            <div class="report-lower-grid">
                <!-- OUTCOMES -->
                <section class="report-card">
                    <div class="report-card__header">
                        <div class="report-card__title">
                            <strong>
                                Candidate Outcome Summary
                            </strong>
                            <span>
                                Participation distribution across
                                this report.
                            </span>
                        </div>
                    </div>
                    <div class="report-outcomes">
                        <!-- ACTIVE -->
                        <div class="report-outcome">
                            <div class="report-outcome__left">
                                <div
                                    class="
                                        report-outcome__icon
                                        report-kpi__icon--orange
                                    "
                                >
                                    <i class="fas fa-person-running"></i>
                                </div>
                                <div>
                                    <strong>
                                        Active Candidates
                                    </strong>
                                    <span>
                                        Currently participating
                                    </span>
                                </div>
                            </div>
                            <strong class="report-outcome__value">
                                <?= number_format(
                                    $totalActiveCandidates
                                ) ?>
                            </strong>
                        </div>
                        <!-- COMPLETED -->
                        <div class="report-outcome">
                            <div class="report-outcome__left">
                                <div
                                    class="
                                        report-outcome__icon
                                        report-kpi__icon--green
                                    "
                                >
                                    <i class="fas fa-circle-check"></i>
                                </div>
                                <div>
                                    <strong>
                                        Completed Candidates
                                    </strong>
                                    <span>
                                        Successfully completed
                                    </span>
                                </div>
                            </div>
                            <strong class="report-outcome__value">
                                <?= number_format(
                                    $totalCompletedCandidates
                                ) ?>
                            </strong>
                        </div>
                        <!-- WITHDRAWN -->
                        <div class="report-outcome">
                            <div class="report-outcome__left">
                                <div
                                    class="
                                        report-outcome__icon
                                        report-kpi__icon--red
                                    "
                                >
                                    <i class="fas fa-user-minus"></i>
                                </div>
                                <div>
                                    <strong>
                                        Withdrawn Candidates
                                    </strong>
                                    <span>
                                        No longer participating
                                    </span>
                                </div>
                            </div>
                            <strong class="report-outcome__value">
                                <?= number_format(
                                    $totalWithdrawnCandidates
                                ) ?>
                            </strong>
                        </div>
                        <!-- COMPLETION RATE -->
                        <div class="report-outcome">
                            <div class="report-outcome__left">
                                <div
                                    class="
                                        report-outcome__icon
                                        report-kpi__icon--blue
                                    "
                                >
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div>
                                    <strong>
                                        Overall Completion Rate
                                    </strong>
                                    <span>
                                        Completed ÷ current candidates
                                    </span>
                                </div>
                            </div>
                            <strong class="report-outcome__value">
                                <?= $overallCompletionRate ?>%
                            </strong>
                        </div>
                    </div>
                </section>
                <!-- ATTENTION -->
                <section class="report-card">
                    <div class="report-card__header">
                        <div class="report-card__title">
                            <strong>
                                Attention Needed
                            </strong>
                            <span>
                                Cohorts ending within 30 days
                                with active candidates.
                            </span>
                        </div>
                    </div>
                    <div class="report-attention">
                        <?php if (
                            empty($attentionRows)
                        ): ?>
                            <div class="report-empty">
                                <div class="report-empty__icon">
                                    <i class="fas fa-circle-check"></i>
                                </div>
                                <strong>
                                    Nothing urgent
                                </strong>
                                <span>
                                    No active cohorts currently
                                    require immediate attention.
                                </span>
                            </div>
                        <?php else: ?>
                            <?php foreach (
                                $attentionRows
                                as $attention
                            ): ?>
                                <article class="report-attention__item">
                                    <strong>
                                        <?= e(
                                            $attention[
                                                'cohort_name'
                                            ]
                                        ) ?>
                                    </strong>
                                    <span>
                                        <?= number_format(
                                            $attention[
                                                'active_candidates'
                                            ]
                                        ) ?>
                                        active candidate<?= $attention[
                                            'active_candidates'
                                        ] === 1
                                            ? ''
                                            : 's' ?>.
                                        Cohort ends
                                        <?= e(
                                            supervisorReportDate(
                                                $attention[
                                                    'end_date'
                                                ]
                                            )
                                        ) ?>.
                                    </span>
                                    <a
                                        href="<?= url(
                                            'supervisor/cohort_view.php?id=' .
                                            (int)$attention[
                                                'cohort_id'
                                            ]
                                        ) ?>"
                                        class="report-attention__action"
                                    >
                                        Review cohort
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            <!-- ====================================================
                 SECURITY NOTICE
            ===================================================== -->
            <div class="report-security">
                <i class="fas fa-shield-halved"></i>
                <div>
                    <strong>
                        Supervisor-scoped reporting
                    </strong>
                    <span>
                        This report only includes cohorts assigned
                        to your Supervisor account. Candidate
                        assignment, cohort transfer, programme
                        administration and Supervisor reassignment
                        remain restricted.
                    </span>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>