<?php
/**
 * ================================================================
 * INVESTHOOD IT - SUPERVISOR / COHORT VIEW
 * ================================================================
 *
 * Supervisor permissions:
 *  - View only cohorts assigned to them
 *  - View participants in assigned cohorts
 *  - Open candidate profiles
 *  - Update candidate participation status
 *
 * Supervisor cannot:
 *  - Assign candidates
 *  - Move candidates
 *  - Reassign supervisors
 *  - Modify programme ownership
 * ================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
$user = current_user();
$flashes = render_flashes();
$currentPage = 'cohorts';
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
    (string)(
        $user['fullname']
        ?? $user['full_name']
        ?? ($firstName . ' ' . $lastName)
    )
);
if ($supervisorName === '') {
    $supervisorName = 'Supervisor';
}
/*
|--------------------------------------------------------------------------
| Cohort ID
|--------------------------------------------------------------------------
*/
$cohortId = (int)(
    $_GET['id']
    ?? $_GET['cohort_id']
    ?? 0
);
if ($cohortId <= 0) {
    header(
        'Location: ' .
        url('supervisor/cohorts.php')
    );
    exit;
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
$statusFilter = trim(
    (string)(
        $_GET['status']
        ?? ''
    )
);
/*
|--------------------------------------------------------------------------
| Load Cohort
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            c.id,
            c.name AS cohort_name,
            c.programme_id,
            c.supervisor_id,
            c.status AS cohort_status,
            c.start_date,
            c.end_date,
            p.name AS programme_name,
            p.type AS programme_type,
            p.status AS programme_status
        FROM cohorts c
        INNER JOIN programmes p
            ON p.id = c.programme_id
        WHERE c.id = ?
          AND c.supervisor_id = ?
        LIMIT 1
    ",
    'ii',
    [
        $cohortId,
        $supervisorId
    ]
);
$cohort = $stmt
    ->get_result()
    ->fetch_assoc();
$stmt->close();
if (!$cohort) {
    http_response_code(404);
    exit(
        'Cohort not found or you do not have access.'
    );
}
/*
|--------------------------------------------------------------------------
| Cohort Statistics
|--------------------------------------------------------------------------
*/
$totalCandidates = 0;
$activeCandidates = 0;
$completedCandidates = 0;
$withdrawnCandidates = 0;
$completionRate = 0;
/*
|--------------------------------------------------------------------------
| Total Current Candidates
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            COUNT(
                DISTINCT cp.user_id
            ) AS total
        FROM cohort_participants cp
        INNER JOIN cohorts c
            ON c.id = cp.cohort_id
        WHERE cp.cohort_id = ?
          AND c.supervisor_id = ?
          AND cp.status <> 'withdrawn'
    ",
    'ii',
    [
        $cohortId,
        $supervisorId
    ]
);
$row = $stmt
    ->get_result()
    ->fetch_assoc();
$totalCandidates = (int)(
    $row['total']
    ?? 0
);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Active Candidates
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            COUNT(
                DISTINCT cp.user_id
            ) AS total
        FROM cohort_participants cp
        INNER JOIN cohorts c
            ON c.id = cp.cohort_id
        WHERE cp.cohort_id = ?
          AND c.supervisor_id = ?
          AND cp.status = 'active'
    ",
    'ii',
    [
        $cohortId,
        $supervisorId
    ]
);
$row = $stmt
    ->get_result()
    ->fetch_assoc();
$activeCandidates = (int)(
    $row['total']
    ?? 0
);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Completed Candidates
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            COUNT(
                DISTINCT cp.user_id
            ) AS total
        FROM cohort_participants cp
        INNER JOIN cohorts c
            ON c.id = cp.cohort_id
        WHERE cp.cohort_id = ?
          AND c.supervisor_id = ?
          AND cp.status = 'completed'
    ",
    'ii',
    [
        $cohortId,
        $supervisorId
    ]
);
$row = $stmt
    ->get_result()
    ->fetch_assoc();
$completedCandidates = (int)(
    $row['total']
    ?? 0
);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Withdrawn Candidates
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            COUNT(
                DISTINCT cp.user_id
            ) AS total
        FROM cohort_participants cp
        INNER JOIN cohorts c
            ON c.id = cp.cohort_id
        WHERE cp.cohort_id = ?
          AND c.supervisor_id = ?
          AND cp.status = 'withdrawn'
    ",
    'ii',
    [
        $cohortId,
        $supervisorId
    ]
);
$row = $stmt
    ->get_result()
    ->fetch_assoc();
$withdrawnCandidates = (int)(
    $row['total']
    ?? 0
);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Completion Rate
|--------------------------------------------------------------------------
*/
if ($totalCandidates > 0) {
    $completionRate = (int)round(
        (
            $completedCandidates
            /
            $totalCandidates
        ) * 100
    );
}
/*
|--------------------------------------------------------------------------
| Available Participant Statuses
|--------------------------------------------------------------------------
*/
$participantStatuses = [];
$stmt = Database::prepare(
    "
        SELECT DISTINCT cp.status
        FROM cohort_participants cp
        INNER JOIN cohorts c
            ON c.id = cp.cohort_id
        WHERE cp.cohort_id = ?
          AND c.supervisor_id = ?
          AND cp.status IS NOT NULL
          AND cp.status <> ''
        ORDER BY cp.status ASC
    ",
    'ii',
    [
        $cohortId,
        $supervisorId
    ]
);
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $value = trim(
        (string)(
            $row['status']
            ?? ''
        )
    );
    if ($value !== '') {
        $participantStatuses[] = $value;
    }
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Participant Query
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        cp.id AS participant_id,
        cp.user_id,
        cp.cohort_id,
        cp.status AS participation_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,
        u.first_name,
        u.last_name,
        u.email
    FROM cohort_participants cp
    INNER JOIN users u
        ON u.id = cp.user_id
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    WHERE cp.cohort_id = ?
      AND c.supervisor_id = ?
";
$types = 'ii';
$params = [
    $cohortId,
    $supervisorId
];
/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
if ($search !== '') {
    $sql .= "
        AND (
            u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
        )
    ";
    $term =
        '%' .
        $search .
        '%';
    $types .= 'sss';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/
if ($statusFilter !== '') {
    $sql .= "
        AND cp.status = ?
    ";
    $types .= 's';
    $params[] = $statusFilter;
}
/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/
$sql .= "
    ORDER BY
        CASE cp.status
            WHEN 'active' THEN 1
            WHEN 'completed' THEN 2
            WHEN 'withdrawn' THEN 3
            ELSE 4
        END,
        u.first_name ASC,
        u.last_name ASC
";
$stmt = Database::prepare(
    $sql,
    $types,
    $params
);
$result = $stmt->get_result();
$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function cohortViewStatusLabel(
    string $status
): string {
    if ($status === '') {
        return 'Unknown';
    }
    return ucwords(
        str_replace(
            '_',
            ' ',
            strtolower($status)
        )
    );
}
function cohortViewStatusClass(
    string $status
): string {
    $status = strtolower(
        trim($status)
    );
    switch ($status) {
        case 'active':
            return 'status-pill--active';
        case 'completed':
            return 'status-pill--completed';
        case 'withdrawn':
            return 'status-pill--withdrawn';
        case 'inactive':
            return 'status-pill--inactive';
        case 'pending':
            return 'status-pill--pending';
        default:
            return 'status-pill--default';
    }
}
function cohortViewDate(
    ?string $date
): string {
    if (empty($date)) {
        return 'Not set';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return 'Not set';
    }
    return date(
        'd M Y',
        $timestamp
    );
}
function cohortViewInitials(
    string $firstName,
    string $lastName
): string {
    $initials = '';
    if ($firstName !== '') {
        $initials .= substr(
            $firstName,
            0,
            1
        );
    }
    if ($lastName !== '') {
        $initials .= substr(
            $lastName,
            0,
            1
        );
    }
    if ($initials === '') {
        $initials = 'C';
    }
    return strtoupper($initials);
}
function cohortViewSupervisorInitials(
    string $firstName,
    string $lastName
): string {
    $initials = '';
    if ($firstName !== '') {
        $initials .= substr(
            $firstName,
            0,
            1
        );
    }
    if ($lastName !== '') {
        $initials .= substr(
            $lastName,
            0,
            1
        );
    }
    if ($initials === '') {
        $initials = 'S';
    }
    return strtoupper($initials);
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
        <?= e($cohort['cohort_name']) ?>
        | Supervisor
    </title>
    <script>
        (function () {
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
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
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
/* ================================================================
   PAGE
================================================================ */
.cohort-view-page {
    padding: 1.5rem;
    display: grid;
    gap: 1.3rem;
}
/* ================================================================
   NAVBAR
================================================================ */
.supervisor-topbar {
    min-height: 78px;
    position: sticky;
    top: 0;
    z-index: 900;
    padding:
        .75rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background:
        rgba(255,255,255,.88);
    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);
    backdrop-filter:
        blur(18px);
    transition:
        background .25s ease;
}
html[data-theme="dark"]
.supervisor-topbar {
    background:
        rgba(15,23,42,.88);
}
.supervisor-topbar__left,
.supervisor-topbar__right {
    display: flex;
    align-items: center;
    gap: .8rem;
}
.supervisor-topbar__menu {
    width: 42px;
    height: 42px;
    display: none;
    align-items: center;
    justify-content: center;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 12px;
    background:
        var(--sv-card-bg, #fff);
    color:
        var(--sv-text, #111827);
    cursor: pointer;
}
.supervisor-topbar__eyebrow {
    display: block;
    margin-bottom: .1rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .58rem;
    font-weight: 800;
    letter-spacing: .09em;
    text-transform: uppercase;
}
.supervisor-topbar__title {
    margin: 0;
    color:
        var(--sv-text, #111827);
    font-size: 1.15rem;
}
.supervisor-topbar__icon {
    width: 41px;
    height: 41px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 12px;
    background:
        var(--sv-card-bg, #fff);
    color:
        var(--sv-text, #111827);
    cursor: pointer;
}
.supervisor-topbar__notification-dot {
    width: 7px;
    height: 7px;
    position: absolute;
    top: 8px;
    right: 8px;
    border-radius: 50%;
    background: #ef4444;
}
.supervisor-topbar__user {
    display: flex;
    align-items: center;
    gap: .6rem;
}
.supervisor-topbar__avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );
    color: #fff;
    font-size: .68rem;
    font-weight: 800;
}
.supervisor-topbar__user-copy strong {
    display: block;
    color:
        var(--sv-text, #111827);
    font-size: .7rem;
}
.supervisor-topbar__user-copy span {
    display: block;
    margin-top: .1rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .59rem;
}
/* ================================================================
   BREADCRUMB
================================================================ */
.cohort-breadcrumb {
    display: flex;
    align-items: center;
    gap: .5rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .67rem;
}
.cohort-breadcrumb a {
    color:
        var(--sv-text-soft, #64748b);
    text-decoration: none;
}
.cohort-breadcrumb a:hover {
    color:
        var(--sv-primary, #2563eb);
}
/* ================================================================
   HERO
================================================================ */
.cohort-view-hero {
    position: relative;
    overflow: hidden;
    padding: 1.6rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    border-radius: 22px;
    background:
        linear-gradient(
            135deg,
            #0f4bc8,
            #4338ca 52%,
            #7c3aed
        );
    color: #fff;
    box-shadow:
        0 16px 35px
        rgba(37,99,235,.17);
}
.cohort-view-hero::after {
    content: '';
    position: absolute;
    width: 250px;
    height: 250px;
    right: -70px;
    top: -110px;
    border-radius: 50%;
    background:
        rgba(255,255,255,.08);
}
.cohort-view-hero__content {
    position: relative;
    z-index: 2;
}
.cohort-view-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding:
        .35rem .65rem;
    border-radius: 999px;
    background:
        rgba(255,255,255,.11);
    border:
        1px solid
        rgba(255,255,255,.16);
    font-size: .64rem;
    font-weight: 700;
}
.cohort-view-hero h2 {
    margin:
        .75rem 0 .35rem;
    font-size: 1.6rem;
}
.cohort-view-hero p {
    margin: 0;
    opacity: .82;
    font-size: .8rem;
}
.cohort-view-hero__meta {
    margin-top: 1rem;
    display: flex;
    gap: .55rem;
    flex-wrap: wrap;
}
.cohort-view-hero__meta span {
    padding:
        .4rem .6rem;
    border-radius: 9px;
    background:
        rgba(255,255,255,.10);
    border:
        1px solid
        rgba(255,255,255,.11);
    font-size: .62rem;
}
.cohort-view-hero__icon {
    width: 100px;
    height: 100px;
    position: relative;
    z-index: 2;
    flex: 0 0 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 25px;
    background:
        rgba(255,255,255,.10);
    border:
        1px solid
        rgba(255,255,255,.15);
    font-size: 2rem;
}
/* ================================================================
   STATS
================================================================ */
.cohort-view-stats {
    display: grid;
    grid-template-columns:
        repeat(4,minmax(0,1fr));
    gap: 1rem;
}
.cohort-view-stat {
    padding: 1.1rem;
    border-radius: 17px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    transition:
        transform .2s ease,
        box-shadow .2s ease;
}
.cohort-view-stat:hover {
    transform:
        translateY(-3px);
    box-shadow:
        var(--sv-shadow);
}
.cohort-view-stat__icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background:
        var(--sv-primary-soft, #eff6ff);
    color:
        var(--sv-primary, #2563eb);
}
.cohort-view-stat strong {
    display: block;
    margin-top: .7rem;
    color:
        var(--sv-text, #111827);
    font-size: 1.5rem;
}
.cohort-view-stat span {
    display: block;
    margin-top: .2rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .7rem;
}
/* ================================================================
   PROGRESS
================================================================ */
.cohort-overview-grid {
    display: grid;
    grid-template-columns:
        minmax(0,1fr)
        300px;
    gap: 1rem;
}
.cohort-progress-card,
.cohort-summary-card {
    padding: 1.2rem;
    border-radius: 17px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.cohort-progress-card h3,
.cohort-summary-card h3 {
    margin:
        0 0 .9rem;
    color:
        var(--sv-text, #111827);
    font-size: .85rem;
}
.cohort-progress-card__heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: .45rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .7rem;
}
.cohort-progress-card__heading strong {
    color:
        var(--sv-text, #111827);
}
.cohort-progress-card__track {
    height: 9px;
    overflow: hidden;
    border-radius: 999px;
    background:
        rgba(148,163,184,.18);
}
.cohort-progress-card__bar {
    height: 100%;
    border-radius: inherit;
    background:
        linear-gradient(
            90deg,
            #2563eb,
            #7c3aed
        );
}
.cohort-progress-card__caption {
    margin:
        .75rem 0 0;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .67rem;
    line-height: 1.5;
}
.cohort-summary-list {
    display: grid;
    gap: .65rem;
}
.cohort-summary-item {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    font-size: .67rem;
}
.cohort-summary-item span {
    color:
        var(--sv-text-soft, #64748b);
}
.cohort-summary-item strong {
    color:
        var(--sv-text, #111827);
    text-align: right;
}
/* ================================================================
   FILTERS
================================================================ */
.participant-filter {
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 17px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.participant-filter__heading strong {
    display: block;
    color:
        var(--sv-text, #111827);
    font-size: .82rem;
}
.participant-filter__heading span {
    display: block;
    margin-top: .18rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .65rem;
}
.participant-filter form {
    display: flex;
    gap: .55rem;
    align-items: center;
    flex-wrap: wrap;
}
.participant-search {
    position: relative;
}
.participant-search i {
    position: absolute;
    left: .8rem;
    top: 50%;
    transform:
        translateY(-50%);
    color:
        var(--sv-text-soft, #64748b);
    font-size: .68rem;
}
.participant-search input,
.participant-filter select {
    height: 41px;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 10px;
    background:
        var(--sv-card-soft, #f8fafc);
    color:
        var(--sv-text, #111827);
    font-family: inherit;
    font-size: .7rem;
}
.participant-search input {
    width: 220px;
    padding:
        0 .8rem 0 2.15rem;
}
.participant-filter select {
    min-width: 135px;
    padding:
        0 .7rem;
}
.participant-filter__button {
    height: 41px;
    padding:
        0 .85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    border: 0;
    border-radius: 10px;
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );
    color: #fff;
    font-family: inherit;
    font-size: .68rem;
    font-weight: 700;
    cursor: pointer;
}
.participant-filter__reset {
    height: 41px;
    padding:
        0 .75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 10px;
    background:
        var(--sv-card-bg, #fff);
    color:
        var(--sv-text-soft, #64748b);
    text-decoration: none;
    font-size: .68rem;
}
/* ================================================================
   PARTICIPANT TABLE
================================================================ */
.participant-section {
    overflow: hidden;
    border-radius: 17px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.participant-section__header {
    padding: 1rem 1.1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);
}
.participant-section__header h3 {
    margin: 0;
    color:
        var(--sv-text, #111827);
    font-size: .85rem;
}
.participant-section__header span {
    color:
        var(--sv-text-soft, #64748b);
    font-size: .65rem;
}
.participant-table-wrapper {
    overflow-x: auto;
}
.participant-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}
.participant-table th,
.participant-table td {
    padding:
        .85rem 1rem;
    text-align: left;
    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);
    vertical-align: middle;
}
.participant-table tbody tr:last-child td {
    border-bottom: 0;
}
.participant-table th {
    color:
        var(--sv-text-soft, #64748b);
    font-size: .61rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.participant-table td {
    color:
        var(--sv-text, #111827);
    font-size: .7rem;
}
.participant-profile {
    display: flex;
    align-items: center;
    gap: .65rem;
}
.participant-avatar {
    width: 39px;
    height: 39px;
    flex: 0 0 39px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background:
        var(--sv-primary-soft, #eff6ff);
    color:
        var(--sv-primary, #2563eb);
    font-size: .67rem;
    font-weight: 800;
}
.participant-profile strong {
    display: block;
    font-size: .71rem;
}
.participant-profile span {
    display: block;
    margin-top: .12rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .61rem;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    padding:
        .32rem .56rem;
    border-radius: 999px;
    font-size: .61rem;
    font-weight: 800;
}
.status-pill--active {
    background: #dcfce7;
    color: #166534;
}
.status-pill--completed {
    background: #dbeafe;
    color: #1d4ed8;
}
.status-pill--withdrawn {
    background: #fee2e2;
    color: #991b1b;
}
.status-pill--inactive {
    background: #f1f5f9;
    color: #475569;
}
.status-pill--pending {
    background: #fef3c7;
    color: #92400e;
}
.status-pill--default {
    background: #ede9fe;
    color: #6d28d9;
}
.participant-actions {
    display: flex;
    gap: .4rem;
}
.participant-action {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    background:
        var(--sv-card-bg, #fff);
    color:
        var(--sv-text-soft, #64748b);
    text-decoration: none;
}
.participant-action--primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}
/* ================================================================
   EMPTY STATE
================================================================ */
.participant-empty {
    padding: 3rem 1rem;
    text-align: center;
}
.participant-empty__icon {
    width: 62px;
    height: 62px;
    margin:
        0 auto .8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 17px;
    background:
        var(--sv-primary-soft, #eff6ff);
    color:
        var(--sv-primary, #2563eb);
    font-size: 1.2rem;
}
.participant-empty h4 {
    margin: 0;
    color:
        var(--sv-text, #111827);
}
.participant-empty p {
    margin:
        .35rem 0 0;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .68rem;
}
/* ================================================================
   SECURITY NOTICE
================================================================ */
.cohort-security {
    padding: 1rem;
    display: flex;
    gap: .7rem;
    border-radius: 15px;
    background:
        rgba(37,99,235,.06);
    border:
        1px solid
        rgba(37,99,235,.12);
}
.cohort-security__icon {
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background:
        rgba(37,99,235,.12);
    color:
        var(--sv-primary, #2563eb);
}
.cohort-security strong {
    display: block;
    color:
        var(--sv-text, #111827);
    font-size: .7rem;
}
.cohort-security p {
    margin:
        .2rem 0 0;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .64rem;
    line-height: 1.5;
}
/* ================================================================
   DARK MODE STATUS
================================================================ */
html[data-theme="dark"]
.status-pill--active {
    background:
        rgba(34,197,94,.12);
    color: #86efac;
}
html[data-theme="dark"]
.status-pill--completed {
    background:
        rgba(59,130,246,.13);
    color: #93c5fd;
}
html[data-theme="dark"]
.status-pill--withdrawn {
    background:
        rgba(239,68,68,.13);
    color: #fca5a5;
}
html[data-theme="dark"]
.status-pill--inactive {
    background:
        rgba(148,163,184,.1);
    color: #cbd5e1;
}
/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:1100px) {
    .cohort-view-stats {
        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }
}
@media(max-width:900px) {
    .supervisor-topbar__menu {
        display: inline-flex;
    }
    .cohort-overview-grid {
        grid-template-columns: 1fr;
    }
}
@media(max-width:700px) {
    .supervisor-topbar {
        padding:
            .7rem 1rem;
    }
    .supervisor-topbar__user-copy {
        display: none;
    }
    .cohort-view-page {
        padding: 1rem;
    }
    .cohort-view-hero__icon {
        display: none;
    }
    .participant-filter {
        align-items: stretch;
        flex-direction: column;
    }
    .participant-filter form {
        width: 100%;
    }
    .participant-search {
        flex: 1;
    }
    .participant-search input {
        width: 100%;
    }
}
@media(max-width:500px) {
    .cohort-view-stats {
        grid-template-columns: 1fr 1fr;
    }
    .participant-filter form {
        display: grid;
        grid-template-columns: 1fr;
    }
    .participant-filter select,
    .participant-filter__button,
    .participant-filter__reset {
        width: 100%;
    }
}
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="dashboard__main">
        <!-- ========================================================
             NAVBAR
        ========================================================= -->
        <?php
        require_once __DIR__ . '/navbar.php';
        ?>
        <div class="cohort-view-page">
            <?= $flashes ?>
            <!-- BREADCRUMB -->
            <div class="cohort-breadcrumb">
                <a
                    href="<?= url(
                        'supervisor/dashboard.php'
                    ) ?>"
                >
                    Dashboard
                </a>
                <i class="fas fa-chevron-right"></i>
                <a
                    href="<?= url(
                        'supervisor/cohorts.php'
                    ) ?>"
                >
                    My Cohorts
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>
                    <?= e($cohort['cohort_name']) ?>
                </span>
            </div>
            <!-- ====================================================
                 HERO
            ===================================================== -->
            <section class="cohort-view-hero">
                <div class="cohort-view-hero__content">
                    <span class="cohort-view-hero__badge">
                        <i class="fas fa-layer-group"></i>
                        Assigned Cohort
                    </span>
                    <h2>
                        <?= e(
                            $cohort['cohort_name']
                        ) ?>
                    </h2>
                    <p>
                        <?= e(
                            $cohort['programme_name']
                        ) ?>
                    </p>
                    <div class="cohort-view-hero__meta">
                        <span>
                            <i class="fas fa-briefcase"></i>
                            <?= e(
                                cohortViewStatusLabel(
                                    (string)(
                                        $cohort[
                                            'programme_type'
                                        ]
                                        ?? 'Programme'
                                    )
                                )
                            ) ?>
                        </span>
                        <span>
                            <i class="fas fa-circle"></i>
                            <?= e(
                                cohortViewStatusLabel(
                                    (string)(
                                        $cohort[
                                            'cohort_status'
                                        ]
                                        ?? ''
                                    )
                                )
                            ) ?>
                        </span>
                        <span>
                            <i class="far fa-calendar"></i>
                            <?= e(
                                cohortViewDate(
                                    $cohort[
                                        'start_date'
                                    ]
                                    ?? null
                                )
                            ) ?>
                            -
                            <?= e(
                                cohortViewDate(
                                    $cohort[
                                        'end_date'
                                    ]
                                    ?? null
                                )
                            ) ?>
                        </span>
                    </div>
                </div>
                <div class="cohort-view-hero__icon">
                    <i class="fas fa-people-group"></i>
                </div>
            </section>
            <!-- ====================================================
                 STATS
            ===================================================== -->
            <section class="cohort-view-stats">
                <div class="cohort-view-stat">
                    <div class="cohort-view-stat__icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <strong>
                        <?= number_format(
                            $totalCandidates
                        ) ?>
                    </strong>
                    <span>
                        Current Candidates
                    </span>
                </div>
                <div class="cohort-view-stat">
                    <div class="cohort-view-stat__icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <strong>
                        <?= number_format(
                            $activeCandidates
                        ) ?>
                    </strong>
                    <span>
                        Active Candidates
                    </span>
                </div>
                <div class="cohort-view-stat">
                    <div class="cohort-view-stat__icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <strong>
                        <?= number_format(
                            $completedCandidates
                        ) ?>
                    </strong>
                    <span>
                        Completed
                    </span>
                </div>
                <div class="cohort-view-stat">
                    <div class="cohort-view-stat__icon">
                        <i class="fas fa-user-minus"></i>
                    </div>
                    <strong>
                        <?= number_format(
                            $withdrawnCandidates
                        ) ?>
                    </strong>
                    <span>
                        Withdrawn
                    </span>
                </div>
            </section>
            <!-- ====================================================
                 PROGRESS / SUMMARY
            ===================================================== -->
            <section class="cohort-overview-grid">
                <div class="cohort-progress-card">
                    <h3>
                        Cohort Completion
                    </h3>
                    <div class="cohort-progress-card__heading">
                        <span>
                            Overall completion progress
                        </span>
                        <strong>
                            <?= $completionRate ?>%
                        </strong>
                    </div>
                    <div class="cohort-progress-card__track">
                        <div
                            class="cohort-progress-card__bar"
                            style="
                                width:
                                <?= min(
                                    100,
                                    max(
                                        0,
                                        $completionRate
                                    )
                                ) ?>%;
                            "
                        ></div>
                    </div>
                    <p class="cohort-progress-card__caption">
                        <?= number_format(
                            $completedCandidates
                        ) ?>
                        of
                        <?= number_format(
                            $totalCandidates
                        ) ?>
                        current candidates are marked as completed.
                    </p>
                </div>
                <div class="cohort-summary-card">
                    <h3>
                        Cohort Information
                    </h3>
                    <div class="cohort-summary-list">
                        <div class="cohort-summary-item">
                            <span>
                                Programme
                            </span>
                            <strong>
                                <?= e(
                                    $cohort[
                                        'programme_name'
                                    ]
                                ) ?>
                            </strong>
                        </div>
                        <div class="cohort-summary-item">
                            <span>
                                Programme Type
                            </span>
                            <strong>
                                <?= e(
                                    cohortViewStatusLabel(
                                        (string)(
                                            $cohort[
                                                'programme_type'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div class="cohort-summary-item">
                            <span>
                                Start
                            </span>
                            <strong>
                                <?= e(
                                    cohortViewDate(
                                        $cohort[
                                            'start_date'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div class="cohort-summary-item">
                            <span>
                                End
                            </span>
                            <strong>
                                <?= e(
                                    cohortViewDate(
                                        $cohort[
                                            'end_date'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </section>
            <!-- ====================================================
                 FILTER
            ===================================================== -->
            <section class="participant-filter">
                <div class="participant-filter__heading">
                    <strong>
                        Find a candidate
                    </strong>
                    <span>
                        Search candidates in this cohort.
                    </span>
                </div>
                <form method="GET">
                    <input
                        type="hidden"
                        name="id"
                        value="<?= $cohortId ?>"
                    >
                    <div class="participant-search">
                        <i class="fas fa-magnifying-glass"></i>
                        <input
                            type="search"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Name or email..."
                        >
                    </div>
                    <select name="status">
                        <option value="">
                            All statuses
                        </option>
                        <?php foreach (
                            $participantStatuses
                            as $status
                        ): ?>
                            <option
                                value="<?= e($status) ?>"
                                <?= $statusFilter === $status
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e(
                                    cohortViewStatusLabel(
                                        $status
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        type="submit"
                        class="participant-filter__button"
                    >
                        <i class="fas fa-filter"></i>
                        Filter
                    </button>
                    <?php if (
                        $search !== ''
                        ||
                        $statusFilter !== ''
                    ): ?>
                        <a
                            href="<?= url(
                                'supervisor/cohort_view.php?id=' .
                                $cohortId
                            ) ?>"
                            class="participant-filter__reset"
                        >
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </section>
            <!-- ====================================================
                 PARTICIPANTS
            ===================================================== -->
            <section class="participant-section">
                <div class="participant-section__header">
                    <h3>
                        Cohort Candidates
                    </h3>
                    <span>
                        <?= number_format(
                            count($participants)
                        ) ?>
                        displayed
                    </span>
                </div>
                <?php if (
                    empty($participants)
                ): ?>
                    <div class="participant-empty">
                        <div class="participant-empty__icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>
                            No candidates found
                        </h4>
                        <p>
                            No candidates matched the selected filters.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="participant-table-wrapper">
                        <table class="participant-table">
                            <thead>
                                <tr>
                                    <th>
                                        Candidate
                                    </th>
                                    <th>
                                        Status
                                    </th>
                                    <th>
                                        Selected
                                    </th>
                                    <th>
                                        Onboarded
                                    </th>
                                    <th>
                                        Completed
                                    </th>
                                    <th>
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (
                                $participants
                                as $participant
                            ): ?>
                                <?php
                                $participantFirstName =
                                    trim(
                                        (string)(
                                            $participant[
                                                'first_name'
                                            ]
                                            ?? ''
                                        )
                                    );
                                $participantLastName =
                                    trim(
                                        (string)(
                                            $participant[
                                                'last_name'
                                            ]
                                            ?? ''
                                        )
                                    );
                                $participantName =
                                    trim(
                                        $participantFirstName .
                                        ' ' .
                                        $participantLastName
                                    );
                                if (
                                    $participantName === ''
                                ) {
                                    $participantName =
                                        'Candidate';
                                }
                                $participationStatus =
                                    strtolower(
                                        trim(
                                            (string)(
                                                $participant[
                                                    'participation_status'
                                                ]
                                                ?? ''
                                            )
                                        )
                                    );
                                ?>
                                <tr>
                                    <td>
                                        <div class="participant-profile">
                                            <div class="participant-avatar">
                                                <?= e(
                                                    cohortViewInitials(
                                                        $participantFirstName,
                                                        $participantLastName
                                                    )
                                                ) ?>
                                            </div>
                                            <div>
                                                <strong>
                                                    <?= e(
                                                        $participantName
                                                    ) ?>
                                                </strong>
                                                <span>
                                                    <?= e(
                                                        $participant[
                                                            'email'
                                                        ]
                                                        ?: 'No email'
                                                    ) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="
                                                status-pill
                                                <?= cohortViewStatusClass(
                                                    $participationStatus
                                                ) ?>
                                            "
                                        >
                                            <?= e(
                                                cohortViewStatusLabel(
                                                    $participationStatus
                                                )
                                            ) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= e(
                                            cohortViewDate(
                                                $participant[
                                                    'selected_at'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= e(
                                            cohortViewDate(
                                                $participant[
                                                    'onboarded_at'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= e(
                                            cohortViewDate(
                                                $participant[
                                                    'completed_at'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </td>
                                    <td>
                                        <div class="participant-actions">
                                            <a
                                                href="<?= url(
                                                    'supervisor/candidate_view.php?id=' .
                                                    (int)$participant[
                                                        'user_id'
                                                    ] .
                                                    '&cohort_id=' .
                                                    $cohortId
                                                ) ?>"
                                                class="participant-action"
                                                title="View candidate"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (
                                                $participationStatus
                                                !== 'withdrawn'
                                            ): ?>
                                                <a
                                                    href="<?= url(
                                                        'supervisor/update_candidate_status.php?id=' .
                                                        (int)$participant[
                                                            'user_id'
                                                        ] .
                                                        '&cohort_id=' .
                                                        $cohortId
                                                    ) ?>"
                                                    class="
                                                        participant-action
                                                        participant-action--primary
                                                    "
                                                    title="Update status"
                                                >
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <!-- ====================================================
                 SECURITY NOTICE
            ===================================================== -->
            <div class="cohort-security">
                <div class="cohort-security__icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <strong>
                        Supervisor access protection
                    </strong>
                    <p>
                        This cohort is visible because it is assigned
                        to your Supervisor account. Candidate
                        assignment, cohort reassignment and programme
                        administration remain restricted.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
/* ================================================================
   NAVBAR DARK MODE
================================================================ */
document.addEventListener(
    'DOMContentLoaded',
    function () {
        const button =
            document.getElementById(
                'navbarDarkToggle'
            );
        const icon =
            document.getElementById(
                'navbarDarkIcon'
            );
        function updateThemeIcons() {
            const dark =
                document.documentElement
                    .getAttribute(
                        'data-theme'
                    ) === 'dark';
            if (icon) {
                icon.className =
                    dark
                    ? 'fas fa-sun'
                    : 'fas fa-moon';
            }
            const sidebarIcon =
                document.getElementById(
                    'supervisorDarkIcon'
                );
            if (sidebarIcon) {
                sidebarIcon.className =
                    dark
                    ? 'fas fa-sun'
                    : 'fas fa-moon';
            }
        }
        updateThemeIcons();
        button?.addEventListener(
            'click',
            function () {
                const current =
                    document.documentElement
                        .getAttribute(
                            'data-theme'
                        );
                const next =
                    current === 'dark'
                    ? 'light'
                    : 'dark';
                document.documentElement
                    .setAttribute(
                        'data-theme',
                        next
                    );
                localStorage.setItem(
                    'investhood-supervisor-theme',
                    next
                );
                updateThemeIcons();
            }
        );
    }
);
</script>
</body>
</html>