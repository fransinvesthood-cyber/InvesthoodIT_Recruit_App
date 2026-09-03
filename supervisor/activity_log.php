<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
require_once __DIR__ . '/activity_helpers.php';
$user = current_user();
$flashes = render_flashes();
$currentPage = 'activity_log';
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
| Filters
|--------------------------------------------------------------------------
*/
$search = trim(
    (string)(
        $_GET['search']
        ?? ''
    )
);
$actionFilter = trim(
    (string)(
        $_GET['action']
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
| Date Validation
|--------------------------------------------------------------------------
*/
$datePattern =
    '/^\d{4}-\d{2}-\d{2}$/';
if (
    $dateFrom !== ''
    &&
    !preg_match(
        $datePattern,
        $dateFrom
    )
) {
    $dateFrom = '';
}
if (
    $dateTo !== ''
    &&
    !preg_match(
        $datePattern,
        $dateTo
    )
) {
    $dateTo = '';
}
/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$page = max(
    1,
    (int)(
        $_GET['page']
        ?? 1
    )
);
$perPage = 15;
$offset =
    ($page - 1)
    *
    $perPage;
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function supervisorActivityDate(
    ?string $date
): string {
    if (!$date) {
        return '—';
    }
    $timestamp =
        strtotime($date);
    if (!$timestamp) {
        return '—';
    }
    return date(
        'd M Y, H:i',
        $timestamp
    );
}
function supervisorActivityLabel(
    string $action
): string {
    return ucwords(
        str_replace(
            [
                '_',
                '-'
            ],
            ' ',
            $action
        )
    );
}
function supervisorActivityIcon(
    string $action
): string {
    $action =
        strtolower($action);
    if (
        str_contains(
            $action,
            'status'
        )
    ) {
        return 'fa-arrows-rotate';
    }
    if (
        str_contains(
            $action,
            'candidate'
        )
    ) {
        return 'fa-user';
    }
    if (
        str_contains(
            $action,
            'cohort'
        )
    ) {
        return 'fa-layer-group';
    }
    if (
        str_contains(
            $action,
            'report'
        )
    ) {
        return 'fa-chart-column';
    }
    if (
        str_contains(
            $action,
            'login'
        )
    ) {
        return 'fa-right-to-bracket';
    }
    if (
        str_contains(
            $action,
            'notification'
        )
    ) {
        return 'fa-bell';
    }
    return 'fa-clock-rotate-left';
}
function supervisorActivityIconClass(
    string $action
): string {
    $action =
        strtolower($action);
    if (
        str_contains(
            $action,
            'status'
        )
    ) {
        return 'purple';
    }
    if (
        str_contains(
            $action,
            'candidate'
        )
    ) {
        return 'blue';
    }
    if (
        str_contains(
            $action,
            'cohort'
        )
    ) {
        return 'cyan';
    }
    if (
        str_contains(
            $action,
            'report'
        )
    ) {
        return 'green';
    }
    if (
        str_contains(
            $action,
            'login'
        )
    ) {
        return 'orange';
    }
    return 'neutral';
}
/*
|--------------------------------------------------------------------------
| Available Actions
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT DISTINCT
            action
        FROM supervisor_activity_log
        WHERE supervisor_id = ?
        ORDER BY action ASC
    ",
    'i',
    [$supervisorId]
);
$result =
    $stmt->get_result();
$availableActions = [];
while (
    $row = $result->fetch_assoc()
) {
    if (
        !empty(
            $row['action']
        )
    ) {
        $availableActions[] =
            $row['action'];
    }
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Filter Query
|--------------------------------------------------------------------------
*/
$where = "
    WHERE supervisor_id = ?
";
$types = 'i';
$params = [
    $supervisorId
];
if ($search !== '') {
    $where .= "
        AND (
            action LIKE ?
            OR description LIKE ?
            OR entity_type LIKE ?
        )
    ";
    $like =
        '%' .
        $search .
        '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($actionFilter !== '') {
    $where .= "
        AND action = ?
    ";
    $types .= 's';
    $params[] =
        $actionFilter;
}
if ($dateFrom !== '') {
    $where .= "
        AND created_at >= ?
    ";
    $types .= 's';
    $params[] =
        $dateFrom .
        ' 00:00:00';
}
if ($dateTo !== '') {
    $where .= "
        AND created_at <= ?
    ";
    $types .= 's';
    $params[] =
        $dateTo .
        ' 23:59:59';
}
/*
|--------------------------------------------------------------------------
| Total Records
|--------------------------------------------------------------------------
*/
$countStmt =
    Database::prepare(
        "
            SELECT
                COUNT(*) AS total
            FROM supervisor_activity_log
            {$where}
        ",
        $types,
        $params
    );
$countRow =
    $countStmt
        ->get_result()
        ->fetch_assoc();
$totalRecords = (int)(
    $countRow['total']
    ?? 0
);
$countStmt->close();
$totalPages = max(
    1,
    (int)ceil(
        $totalRecords
        /
        $perPage
    )
);
if ($page > $totalPages) {
    $page =
        $totalPages;
    $offset =
        ($page - 1)
        *
        $perPage;
}
/*
|--------------------------------------------------------------------------
| Activities
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        id,
        action,
        description,
        entity_type,
        entity_id,
        ip_address,
        user_agent,
        created_at
    FROM supervisor_activity_log
    {$where}
    ORDER BY
        created_at DESC,
        id DESC
    LIMIT {$perPage}
    OFFSET {$offset}
";
$stmt =
    Database::prepare(
        $sql,
        $types,
        $params
    );
$result =
    $stmt->get_result();
$activities = [];
while (
    $row =
        $result->fetch_assoc()
) {
    $activities[] =
        $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            COUNT(*) AS total_actions,
            SUM(
                CASE
                    WHEN DATE(created_at) = CURDATE()
                    THEN 1
                    ELSE 0
                END
            ) AS today_actions,
            SUM(
                CASE
                    WHEN created_at >= DATE_SUB(
                        NOW(),
                        INTERVAL 7 DAY
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS week_actions,
            MAX(created_at) AS latest_activity
        FROM supervisor_activity_log
        WHERE supervisor_id = ?
    ",
    'i',
    [$supervisorId]
);
$stats =
    $stmt
        ->get_result()
        ->fetch_assoc()
    ?? [];
$stmt->close();
$totalActions =
    (int)(
        $stats['total_actions']
        ?? 0
    );
$todayActions =
    (int)(
        $stats['today_actions']
        ?? 0
    );
$weekActions =
    (int)(
        $stats['week_actions']
        ?? 0
    );
$latestActivity =
    $stats['latest_activity']
    ?? null;
/*
|--------------------------------------------------------------------------
| Pagination URL
|--------------------------------------------------------------------------
*/
function supervisorActivityPageUrl(
    int $page,
    string $search,
    string $action,
    string $dateFrom,
    string $dateTo
): string {
    $query = [
        'page' => $page
    ];
    if ($search !== '') {
        $query['search'] =
            $search;
    }
    if ($action !== '') {
        $query['action'] =
            $action;
    }
    if ($dateFrom !== '') {
        $query['date_from'] =
            $dateFrom;
    }
    if ($dateTo !== '') {
        $query['date_to'] =
            $dateTo;
    }
    return url(
        'supervisor/activity_log.php?' .
        http_build_query(
            $query
        )
    );
}
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
        Activity Log | Supervisor
    </title>
    <!-- EARLY THEME -->
    <script>
        (function () {
            try {
                const theme =
                    localStorage.getItem(
                        'investhood-supervisor-theme'
                    );
                document.documentElement
                    .setAttribute(
                        'data-theme',
                        theme === 'dark'
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
        href="<?= url(
            'css/styles.css'
        ) ?>"
    >
    <style>
        /* ============================================================
           PAGE
        ============================================================ */
        * {
            box-sizing: border-box;
        }
        html,
        body {
            margin: 0;
            padding: 0;
        }
        body.dashboard-page {
            background:
                var(--sv-page-bg,#f6f8fc);
            color:
                var(--sv-text,#101828);
            font-family:
                'Inter',
                sans-serif;
        }
        .dashboard {
            display: flex !important;
            gap: 0 !important;
            width: 100%;
            min-height: 100vh;
            margin: 0 !important;
        }
        .dashboard__main {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            width: auto !important;
            margin: 0 !important;
            background:
                var(--sv-page-bg,#f6f8fc);
        }
        /* ============================================================
           PAGE CONTAINER
        ============================================================ */
        .supervisor-page {
            width: 100%;
            max-width: 1520px;
            margin: 0 auto;
            padding:
                1.35rem
                1.45rem
                2rem;
        }
        /* ============================================================
           BREADCRUMB
        ============================================================ */
        .page-breadcrumb {
            margin-bottom: .85rem;
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
            color:
                var(--sv-text-soft,#667085);
            font-size: .63rem;
        }
        .page-breadcrumb a {
            color: inherit;
            text-decoration: none;
            transition:
                color .15s ease;
        }
        .page-breadcrumb a:hover {
            color:
                var(--sv-primary,#2563eb);
        }
        .page-breadcrumb i {
            color:
                #98a2b3;
            font-size: .42rem;
        }
        /* ============================================================
           HERO
        ============================================================ */
        .activity-hero {
            position: relative;
            margin-bottom: 1rem;
            padding:
                1.35rem
                1.45rem;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-radius: 18px;
            background:
                linear-gradient(
                    135deg,
                    #0f766e 0%,
                    #2563eb 53%,
                    #4f46e5 100%
                );
            color: #fff;
            box-shadow:
                0 13px 32px
                rgba(37,99,235,.15);
        }
        .activity-hero::before {
            content: '';
            width: 210px;
            height: 210px;
            position: absolute;
            right: -60px;
            top: -120px;
            border-radius: 50%;
            background:
                rgba(255,255,255,.08);
        }
        .activity-hero::after {
            content: '';
            width: 110px;
            height: 110px;
            position: absolute;
            right: 110px;
            bottom: -80px;
            border-radius: 50%;
            background:
                rgba(255,255,255,.06);
        }
        .activity-hero__content {
            position: relative;
            z-index: 2;
            max-width: 720px;
        }
        .activity-hero__eyebrow {
            display: block;
            margin-bottom: .3rem;
            color:
                rgba(255,255,255,.72);
            font-size: .54rem;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .activity-hero h1 {
            margin:
                0 0 .32rem;
            font-size:
                clamp(
                    1.2rem,
                    2.2vw,
                    1.65rem
                );
            font-weight: 800;
            letter-spacing: -.025em;
        }
        .activity-hero p {
            max-width: 650px;
            margin: 0;
            color:
                rgba(255,255,255,.78);
            font-size: .66rem;
            line-height: 1.55;
        }
        .activity-hero__icon {
            width: 66px;
            height: 66px;
            position: relative;
            z-index: 2;
            flex: 0 0 66px;
            display: flex;
            align-items: center;
            justify-content: center;
            border:
                1px solid
                rgba(255,255,255,.14);
            border-radius: 17px;
            background:
                rgba(255,255,255,.09);
            backdrop-filter:
                blur(10px);
            color: #fff;
            font-size: 1.2rem;
        }
        /* ============================================================
           KPI GRID
        ============================================================ */
        .activity-kpis {
            margin-bottom: 1rem;
            display: grid;
            grid-template-columns:
                repeat(
                    4,
                    minmax(0,1fr)
                );
            gap: .8rem;
        }
        .activity-kpi {
            min-width: 0;
            padding: .9rem;
            display: flex;
            align-items: center;
            gap: .72rem;
            border:
                1px solid
                var(--sv-border,#e4e7ec);
            border-radius: 14px;
            background:
                var(--sv-card-bg,#fff);
            box-shadow:
                0 5px 18px
                rgba(16,24,40,.035);
        }
        .activity-kpi__icon {
            width: 39px;
            height: 39px;
            flex: 0 0 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background:
                var(--sv-primary-soft,#eff6ff);
            color:
                var(--sv-primary,#2563eb);
            font-size: .66rem;
        }
        .activity-kpi__content {
            min-width: 0;
        }
        .activity-kpi strong {
            display: block;
            overflow: hidden;
            color:
                var(--sv-text,#101828);
            font-size: .95rem;
            font-weight: 800;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .activity-kpi span {
            display: block;
            margin-top: .08rem;
            color:
                var(--sv-text-soft,#667085);
            font-size: .54rem;
        }
        /* ============================================================
           STANDARD CARD
        ============================================================ */
        .activity-card {
            margin-bottom: 1rem;
            overflow: hidden;
            border:
                1px solid
                var(--sv-border,#e4e7ec);
            border-radius: 15px;
            background:
                var(--sv-card-bg,#fff);
            box-shadow:
                0 6px 20px
                rgba(16,24,40,.035);
        }
        .activity-card__header {
            min-height: 62px;
            padding:
                .85rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            border-bottom:
                1px solid
                var(--sv-border,#e4e7ec);
        }
        .activity-card__heading {
            min-width: 0;
        }
        .activity-card__heading strong {
            display: block;
            color:
                var(--sv-text,#101828);
            font-size: .68rem;
            font-weight: 750;
        }
        .activity-card__heading span {
            display: block;
            margin-top: .15rem;
            color:
                var(--sv-text-soft,#667085);
            font-size: .52rem;
        }
        .activity-card__header-icon {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background:
                var(--sv-primary-soft,#eff6ff);
            color:
                var(--sv-primary,#2563eb);
            font-size: .58rem;
        }
        /* ============================================================
           FILTERS
        ============================================================ */
        .activity-filter {
            padding: .9rem 1rem;
        }
        .activity-filter__grid {
            display: grid;
            grid-template-columns:
                minmax(180px,1.35fr)
                minmax(150px,.8fr)
                minmax(130px,.65fr)
                minmax(130px,.65fr)
                auto;
            gap: .65rem;
            align-items: end;
        }
        .activity-field {
            min-width: 0;
        }
        .activity-field label {
            display: block;
            margin-bottom: .3rem;
            color:
                var(--sv-text-soft,#667085);
            font-size: .49rem;
            font-weight: 750;
            letter-spacing: .055em;
            text-transform: uppercase;
        }
        .activity-field input,
        .activity-field select {
            width: 100%;
            height: 39px;
            padding:
                0 .7rem;
            border:
                1px solid
                var(--sv-border,#e4e7ec);
            border-radius: 9px;
            outline: none;
            background:
                var(--sv-card-soft,#f8fafc);
            color:
                var(--sv-text,#101828);
            font-family: inherit;
            font-size: .59rem;
            transition:
                border-color .15s ease,
                box-shadow .15s ease;
        }
        .activity-field input:focus,
        .activity-field select:focus {
            border-color:
                rgba(37,99,235,.42);
            box-shadow:
                0 0 0 3px
                rgba(37,99,235,.07);
        }
        .activity-filter__actions {
            display: flex;
            gap: .4rem;
        }
        .activity-button {
            height: 39px;
            padding:
                0 .72rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            border: 0;
            border-radius: 9px;
            font-family: inherit;
            font-size: .54rem;
            font-weight: 700;
            white-space: nowrap;
            text-decoration: none;
            cursor: pointer;
            transition:
                transform .15s ease,
                box-shadow .15s ease,
                background .15s ease;
        }
        .activity-button:hover {
            transform:
                translateY(-1px);
        }
        .activity-button--primary {
            background:
                var(--sv-primary,#2563eb);
            color: #fff;
            box-shadow:
                0 4px 10px
                rgba(37,99,235,.15);
        }
        .activity-button--secondary {
            border:
                1px solid
                var(--sv-border,#e4e7ec);
            background:
                var(--sv-card-bg,#fff);
            color:
                var(--sv-text,#101828);
        }
        /* ============================================================
           ACTIVITY LIST
        ============================================================ */
        .activity-list {
            padding:
                .15rem 1rem;
        }
        .activity-item {
            position: relative;
            padding:
                .9rem 0;
            display: grid;
            grid-template-columns:
                38px
                minmax(0,1fr)
                auto;
            gap: .72rem;
            align-items: flex-start;
            border-bottom:
                1px solid
                var(--sv-border,#e4e7ec);
        }
        .activity-item:last-child {
            border-bottom: 0;
        }
        .activity-item__icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: .61rem;
        }
        .activity-item__icon--blue {
            background:
                rgba(37,99,235,.09);
            color: #2563eb;
        }
        .activity-item__icon--purple {
            background:
                rgba(124,58,237,.09);
            color: #7c3aed;
        }
        .activity-item__icon--green {
            background:
                rgba(22,163,74,.09);
            color: #16a34a;
        }
        .activity-item__icon--orange {
            background:
                rgba(217,119,6,.09);
            color: #d97706;
        }
        .activity-item__icon--cyan {
            background:
                rgba(8,145,178,.09);
            color: #0891b2;
        }
        .activity-item__icon--neutral {
            background:
                var(--sv-card-soft,#f8fafc);
            color:
                var(--sv-text-soft,#667085);
        }
        .activity-item__body {
            min-width: 0;
        }
        .activity-item__heading {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
        }
        .activity-item__heading strong {
            color:
                var(--sv-text,#101828);
            font-size: .62rem;
            font-weight: 700;
        }
        .activity-badge {
            padding:
                .18rem .37rem;
            border-radius: 999px;
            background:
                var(--sv-card-soft,#f8fafc);
            color:
                var(--sv-text-soft,#667085);
            font-size: .43rem;
            font-weight: 750;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .activity-item__description {
            max-width: 850px;
            margin-top: .22rem;
            color:
                var(--sv-text-soft,#667085);
            font-size: .56rem;
            line-height: 1.5;
        }
        .activity-item__meta {
            margin-top: .38rem;
            display: flex;
            align-items: center;
            gap: .8rem;
            flex-wrap: wrap;
            color:
                #98a2b3;
            font-size: .47rem;
        }
        .activity-item__meta span {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
        }
        .activity-item__date {
            min-width: 115px;
            padding-top: .06rem;
            color:
                var(--sv-text-soft,#667085);
            font-size: .49rem;
            text-align: right;
            white-space: nowrap;
        }
        /* ============================================================
           EMPTY
        ============================================================ */
        .activity-empty {
            padding:
                2.6rem 1rem;
            text-align: center;
        }
        .activity-empty__icon {
            width: 52px;
            height: 52px;
            margin:
                0 auto .7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background:
                var(--sv-primary-soft,#eff6ff);
            color:
                var(--sv-primary,#2563eb);
            font-size: .85rem;
        }
        .activity-empty strong {
            display: block;
            color:
                var(--sv-text,#101828);
            font-size: .66rem;
        }
        .activity-empty span {
            display: block;
            max-width: 370px;
            margin:
                .25rem auto 0;
            color:
                var(--sv-text-soft,#667085);
            font-size: .53rem;
            line-height: 1.5;
        }
        /* ============================================================
           PAGINATION
        ============================================================ */
        .activity-pagination {
            min-height: 58px;
            padding:
                .7rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            border-top:
                1px solid
                var(--sv-border,#e4e7ec);
            background:
                var(--sv-card-soft,#f8fafc);
        }
        .activity-pagination__text {
            color:
                var(--sv-text-soft,#667085);
            font-size: .5rem;
        }
        .activity-pagination__actions {
            display: flex;
            gap: .35rem;
        }
        /* ============================================================
           SECURITY NOTICE
        ============================================================ */
        .activity-security {
            padding:
                .75rem .9rem;
            display: flex;
            align-items: flex-start;
            gap: .58rem;
            border:
                1px solid
                rgba(37,99,235,.11);
            border-radius: 12px;
            background:
                var(--sv-primary-soft,#eff6ff);
        }
        .activity-security__icon {
            width: 28px;
            height: 28px;
            flex: 0 0 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background:
                rgba(37,99,235,.10);
            color:
                var(--sv-primary,#2563eb);
            font-size: .55rem;
        }
        .activity-security strong {
            display: block;
            color:
                var(--sv-text,#101828);
            font-size: .56rem;
        }
        .activity-security span {
            display: block;
            max-width: 750px;
            margin-top: .15rem;
            color:
                var(--sv-text-soft,#667085);
            font-size: .49rem;
            line-height: 1.5;
        }
        /* ============================================================
           DARK MODE
        ============================================================ */
        html[data-theme="dark"]
        .activity-field input,
        html[data-theme="dark"]
        .activity-field select {
            color-scheme: dark;
        }
        html[data-theme="dark"]
        .activity-pagination {
            background:
                rgba(255,255,255,.018);
        }
        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:1200px) {
            .activity-kpis {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0,1fr)
                    );
            }
            .activity-filter__grid {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0,1fr)
                    );
            }
            .activity-filter__actions {
                grid-column:
                    1 / -1;
            }
        }
        @media(max-width:900px) {
            .dashboard__main {
                width: 100% !important;
                margin-left: 0 !important;
            }
            .supervisor-page {
                padding:
                    1rem;
            }
        }
        @media(max-width:650px) {
            .activity-hero {
                padding: 1.15rem;
            }
            .activity-hero__icon {
                display: none;
            }
            .activity-kpis {
                grid-template-columns: 1fr;
            }
            .activity-filter__grid {
                grid-template-columns: 1fr;
            }
            .activity-filter__actions {
                width: 100%;
            }
            .activity-filter__actions
            .activity-button {
                flex: 1;
            }
            .activity-item {
                grid-template-columns:
                    36px
                    minmax(0,1fr);
            }
            .activity-item__date {
                grid-column: 2;
                min-width: 0;
                padding-top: 0;
                text-align: left;
            }
            .activity-pagination {
                align-items: stretch;
                flex-direction: column;
            }
            .activity-pagination__actions {
                width: 100%;
            }
            .activity-pagination__actions
            .activity-button {
                flex: 1;
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
        <div class="supervisor-page">
            <!-- ====================================================
                 FLASHES
            ===================================================== -->
            <?= $flashes ?>
            <!-- ====================================================
                 BREADCRUMB
            ===================================================== -->
            <nav class="page-breadcrumb">
                <a
                    href="<?= url(
                        'supervisor/dashboard.php'
                    ) ?>"
                >
                    Dashboard
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>
                    Activity Log
                </span>
            </nav>
            <!-- ====================================================
                 HERO
            ===================================================== -->
            <section class="activity-hero">
                <div class="activity-hero__content">
                    <span class="activity-hero__eyebrow">
                        Audit & Accountability
                    </span>
                    <h1>
                        Supervisor Activity Log
                    </h1>
                    <p>
                        Review actions recorded across your assigned
                        candidates, cohorts and Supervisor reporting
                        activities.
                    </p>
                </div>
                <div class="activity-hero__icon">
                    <i class="fas fa-clock-rotate-left"></i>
                </div>
            </section>
            <!-- ====================================================
                 KPIS
            ===================================================== -->
            <section class="activity-kpis">
                <!-- TOTAL -->
                <article class="activity-kpi">
                    <div class="activity-kpi__icon">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <div class="activity-kpi__content">
                        <strong>
                            <?= number_format(
                                $totalActions
                            ) ?>
                        </strong>
                        <span>
                            Total Recorded Actions
                        </span>
                    </div>
                </article>
                <!-- TODAY -->
                <article class="activity-kpi">
                    <div class="activity-kpi__icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="activity-kpi__content">
                        <strong>
                            <?= number_format(
                                $todayActions
                            ) ?>
                        </strong>
                        <span>
                            Actions Today
                        </span>
                    </div>
                </article>
                <!-- WEEK -->
                <article class="activity-kpi">
                    <div class="activity-kpi__icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="activity-kpi__content">
                        <strong>
                            <?= number_format(
                                $weekActions
                            ) ?>
                        </strong>
                        <span>
                            Last 7 Days
                        </span>
                    </div>
                </article>
                <!-- LATEST -->
                <article class="activity-kpi">
                    <div class="activity-kpi__icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="activity-kpi__content">
                        <strong
                            style="
                                font-size:.65rem;
                            "
                        >
                            <?= e(
                                supervisorActivityDate(
                                    $latestActivity
                                )
                            ) ?>
                        </strong>
                        <span>
                            Latest Activity
                        </span>
                    </div>
                </article>
            </section>
            <!-- ====================================================
                 FILTERS
            ===================================================== -->
            <section class="activity-card">
                <div class="activity-card__header">
                    <div class="activity-card__heading">
                        <strong>
                            Filter Activity
                        </strong>
                        <span>
                            Search or narrow down your recorded actions.
                        </span>
                    </div>
                    <div class="activity-card__header-icon">
                        <i class="fas fa-filter"></i>
                    </div>
                </div>
                <form
                    method="GET"
                    action=""
                    class="activity-filter"
                >
                    <div class="activity-filter__grid">
                        <!-- SEARCH -->
                        <div class="activity-field">
                            <label for="activitySearch">
                                Search
                            </label>
                            <input
                                type="search"
                                name="search"
                                id="activitySearch"
                                value="<?= e(
                                    $search
                                ) ?>"
                                placeholder="Search activity..."
                            >
                        </div>
                        <!-- ACTION -->
                        <div class="activity-field">
                            <label for="activityAction">
                                Action
                            </label>
                            <select
                                name="action"
                                id="activityAction"
                            >
                                <option value="">
                                    All Actions
                                </option>
                                <?php foreach (
                                    $availableActions
                                    as $availableAction
                                ): ?>
                                    <option
                                        value="<?= e(
                                            $availableAction
                                        ) ?>"
                                        <?= $actionFilter === $availableAction
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e(
                                            supervisorActivityLabel(
                                                $availableAction
                                            )
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- FROM -->
                        <div class="activity-field">
                            <label for="activityDateFrom">
                                From
                            </label>
                            <input
                                type="date"
                                name="date_from"
                                id="activityDateFrom"
                                value="<?= e(
                                    $dateFrom
                                ) ?>"
                            >
                        </div>
                        <!-- TO -->
                        <div class="activity-field">
                            <label for="activityDateTo">
                                To
                            </label>
                            <input
                                type="date"
                                name="date_to"
                                id="activityDateTo"
                                value="<?= e(
                                    $dateTo
                                ) ?>"
                            >
                        </div>
                        <!-- ACTIONS -->
                        <div class="activity-filter__actions">
                            <button
                                type="submit"
                                class="
                                    activity-button
                                    activity-button--primary
                                "
                            >
                                <i class="fas fa-filter"></i>
                                Apply
                            </button>
                            <?php if (
                                $search !== ''
                                ||
                                $actionFilter !== ''
                                ||
                                $dateFrom !== ''
                                ||
                                $dateTo !== ''
                            ): ?>
                                <a
                                    href="<?= url(
                                        'supervisor/activity_log.php'
                                    ) ?>"
                                    class="
                                        activity-button
                                        activity-button--secondary
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
                 ACTIVITY LIST
            ===================================================== -->
            <section class="activity-card">
                <div class="activity-card__header">
                    <div class="activity-card__heading">
                        <strong>
                            Recorded Activity
                        </strong>
                        <span>
                            <?= number_format(
                                $totalRecords
                            ) ?>
                            matching record<?= $totalRecords === 1
                                ? ''
                                : 's' ?>
                        </span>
                    </div>
                    <div class="activity-card__header-icon">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                </div>
                <?php if (
                    empty($activities)
                ): ?>
                    <!-- EMPTY -->
                    <div class="activity-empty">
                        <div class="activity-empty__icon">
                            <i class="fas fa-clock-rotate-left"></i>
                        </div>
                        <strong>
                            No activity found
                        </strong>
                        <span>
                            Recorded Supervisor actions will appear
                            here once activity logging begins.
                        </span>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach (
                            $activities
                            as $activity
                        ): ?>
                            <?php
                            $iconClass =
                                supervisorActivityIconClass(
                                    $activity['action']
                                );
                            ?>
                            <article class="activity-item">
                                <!-- ICON -->
                                <div
                                    class="
                                        activity-item__icon
                                        activity-item__icon--<?= e(
                                            $iconClass
                                        ) ?>
                                    "
                                >
                                    <i
                                        class="
                                            fas
                                            <?= e(
                                                supervisorActivityIcon(
                                                    $activity['action']
                                                )
                                            ) ?>
                                        "
                                    ></i>
                                </div>
                                <!-- CONTENT -->
                                <div class="activity-item__body">
                                    <div class="activity-item__heading">
                                        <strong>
                                            <?= e(
                                                supervisorActivityLabel(
                                                    $activity[
                                                        'action'
                                                    ]
                                                )
                                            ) ?>
                                        </strong>
                                        <?php if (
                                            !empty(
                                                $activity[
                                                    'entity_type'
                                                ]
                                            )
                                        ): ?>
                                            <span class="activity-badge">
                                                <?= e(
                                                    $activity[
                                                        'entity_type'
                                                    ]
                                                ) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-item__description">
                                        <?= e(
                                            $activity[
                                                'description'
                                            ]
                                        ) ?>
                                    </div>
                                    <div class="activity-item__meta">
                                        <?php if (
                                            !empty(
                                                $activity[
                                                    'entity_id'
                                                ]
                                            )
                                        ): ?>
                                            <span>
                                                <i class="fas fa-hashtag"></i>
                                                <?= (int)$activity[
                                                    'entity_id'
                                                ] ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (
                                            !empty(
                                                $activity[
                                                    'ip_address'
                                                ]
                                            )
                                        ): ?>
                                            <span>
                                                <i class="fas fa-network-wired"></i>
                                                <?= e(
                                                    $activity[
                                                        'ip_address'
                                                    ]
                                                ) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- DATE -->
                                <time class="activity-item__date">
                                    <?= e(
                                        supervisorActivityDate(
                                            $activity[
                                                'created_at'
                                            ]
                                        )
                                    ) ?>
                                </time>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <!-- ====================================================
                         PAGINATION
                    ===================================================== -->
                    <?php if (
                        $totalPages > 1
                    ): ?>
                        <div class="activity-pagination">
                            <span class="activity-pagination__text">
                                Showing page
                                <strong>
                                    <?= number_format(
                                        $page
                                    ) ?>
                                </strong>
                                of
                                <strong>
                                    <?= number_format(
                                        $totalPages
                                    ) ?>
                                </strong>
                            </span>
                            <div class="activity-pagination__actions">
                                <?php if (
                                    $page > 1
                                ): ?>
                                    <a
                                        href="<?= e(
                                            supervisorActivityPageUrl(
                                                $page - 1,
                                                $search,
                                                $actionFilter,
                                                $dateFrom,
                                                $dateTo
                                            )
                                        ) ?>"
                                        class="
                                            activity-button
                                            activity-button--secondary
                                        "
                                    >
                                        <i class="fas fa-chevron-left"></i>
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if (
                                    $page < $totalPages
                                ): ?>
                                    <a
                                        href="<?= e(
                                            supervisorActivityPageUrl(
                                                $page + 1,
                                                $search,
                                                $actionFilter,
                                                $dateFrom,
                                                $dateTo
                                            )
                                        ) ?>"
                                        class="
                                            activity-button
                                            activity-button--secondary
                                        "
                                    >
                                        Next
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
            <!-- ====================================================
                 SECURITY
            ===================================================== -->
            <div class="activity-security">
                <div class="activity-security__icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <strong>
                        Supervisor-scoped audit history
                    </strong>
                    <span>
                        Only actions associated with your Supervisor
                        account are displayed. Activities belonging
                        to other Supervisors are excluded by the
                        server-side account restriction.
                    </span>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>