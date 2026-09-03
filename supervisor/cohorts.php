<?php
/**
 * ================================================================
 * INVESTHOOD IT - SUPERVISOR / MY COHORTS
 * ================================================================
 *
 * Supervisor permissions:
 *  - View assigned cohorts only
 *  - View candidates inside assigned cohorts
 *  - Monitor cohort progress
 *
 * Supervisor cannot:
 *  - Create cohorts
 *  - Assign candidates
 *  - Reassign supervisors
 *  - Modify programme ownership
 *
 * ================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
$user = current_user();
$flashes = render_flashes();
$currentPage = 'cohorts';
/*
|--------------------------------------------------------------------------
| Current Supervisor
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
| Dashboard Statistics
|--------------------------------------------------------------------------
*/
$totalCohorts = 0;
$activeCohorts = 0;
$totalCandidates = 0;
$completedCandidates = 0;
/*
|--------------------------------------------------------------------------
| Total Assigned Cohorts
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT COUNT(*) AS total
        FROM cohorts
        WHERE supervisor_id = ?
    ",
    'i',
    [$supervisorId]
);
$row = $stmt->get_result()->fetch_assoc();
$totalCohorts = (int)(
    $row['total']
    ?? 0
);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Active Cohorts
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT COUNT(*) AS total
        FROM cohorts
        WHERE supervisor_id = ?
          AND status = 'active'
    ",
    'i',
    [$supervisorId]
);
$row = $stmt->get_result()->fetch_assoc();
$activeCohorts = (int)(
    $row['total']
    ?? 0
);
$stmt->close();
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
        WHERE c.supervisor_id = ?
          AND cp.status <> 'withdrawn'
    ",
    'i',
    [$supervisorId]
);
$row = $stmt->get_result()->fetch_assoc();
$totalCandidates = (int)(
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
        WHERE c.supervisor_id = ?
          AND cp.status = 'completed'
    ",
    'i',
    [$supervisorId]
);
$row = $stmt->get_result()->fetch_assoc();
$completedCandidates = (int)(
    $row['total']
    ?? 0
);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Cohort Status Options
|--------------------------------------------------------------------------
|
| Dynamically loaded rather than assuming every possible ENUM value.
|
|--------------------------------------------------------------------------
*/
$cohortStatuses = [];
$stmt = Database::prepare(
    "
        SELECT DISTINCT status
        FROM cohorts
        WHERE supervisor_id = ?
          AND status IS NOT NULL
          AND status <> ''
        ORDER BY status ASC
    ",
    'i',
    [$supervisorId]
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
        $cohortStatuses[] = $value;
    }
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Cohorts Query
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        c.id,
        c.name,
        c.status,
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
        ) AS candidate_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'active'
                THEN cp.user_id
            END
        ) AS active_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'completed'
                THEN cp.user_id
            END
        ) AS completed_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'withdrawn'
                THEN cp.user_id
            END
        ) AS withdrawn_count
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE c.supervisor_id = ?
";
$types = 'i';
$params = [$supervisorId];
/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
if ($search !== '') {
    $sql .= "
        AND (
            c.name LIKE ?
            OR p.name LIKE ?
            OR p.type LIKE ?
        )
    ";
    $searchTerm = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/
if ($statusFilter !== '') {
    $sql .= "
        AND c.status = ?
    ";
    $types .= 's';
    $params[] = $statusFilter;
}
/*
|--------------------------------------------------------------------------
| Group / Order
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
$cohorts = [];
while ($row = $result->fetch_assoc()) {
    $cohorts[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function supervisorCohortsStatusLabel(
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
function supervisorCohortsStatusClass(
    string $status
): string {
    $status = strtolower(
        trim($status)
    );
    switch ($status) {
        case 'active':
            return 'cohort-badge--active';
        case 'completed':
            return 'cohort-badge--completed';
        case 'inactive':
            return 'cohort-badge--inactive';
        case 'pending':
            return 'cohort-badge--pending';
        case 'cancelled':
            return 'cohort-badge--cancelled';
        default:
            return 'cohort-badge--default';
    }
}
function supervisorCohortsDate(
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
function supervisorCohortsProgress(
    int $candidateCount,
    int $completedCount
): int {
    if ($candidateCount <= 0) {
        return 0;
    }
    return min(
        100,
        max(
            0,
            (int)round(
                (
                    $completedCount
                    /
                    $candidateCount
                ) * 100
            )
        )
    );
}
function supervisorCohortsInitials(
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
        My Cohorts | Investhood IT
    </title>
    <!-- Apply theme immediately to avoid flashing -->
    <script>
        (function () {
            const theme =
                localStorage.getItem(
                    'investhood-supervisor-theme'
                );
            document.documentElement.setAttribute(
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
.cohorts-page {
    padding: 1.5rem;
    display: grid;
    gap: 1.4rem;
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
    -webkit-backdrop-filter:
        blur(18px);
    transition:
        background .25s ease,
        border-color .25s ease;
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
    font-size: .59rem;
    font-weight: 800;
    letter-spacing: .09em;
    text-transform: uppercase;
}
.supervisor-topbar__title {
    margin: 0;
    color:
        var(--sv-text, #111827);
    font-size: 1.2rem;
    font-weight: 800;
}
/* SEARCH */
.supervisor-topbar__search {
    min-width: 210px;
    min-height: 41px;
    padding:
        .55rem .8rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 12px;
    background:
        var(--sv-card-soft, #f8fafc);
    color:
        var(--sv-text-soft, #64748b);
    text-decoration: none;
    font-size: .71rem;
    transition:
        border-color .18s ease,
        transform .18s ease;
}
.supervisor-topbar__search:hover {
    border-color:
        rgba(37,99,235,.35);
    transform:
        translateY(-1px);
}
/* ICON BUTTONS */
.supervisor-topbar__icon {
    position: relative;
    width: 41px;
    height: 41px;
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
    transition:
        transform .18s ease,
        border-color .18s ease,
        background .18s ease;
}
.supervisor-topbar__icon:hover {
    transform:
        translateY(-2px);
    border-color:
        rgba(37,99,235,.35);
}
.supervisor-topbar__notification-dot {
    width: 7px;
    height: 7px;
    position: absolute;
    top: 8px;
    right: 8px;
    border-radius: 50%;
    background: #ef4444;
    border:
        2px solid
        var(--sv-card-bg, #fff);
}
/* USER */
.supervisor-topbar__user {
    padding-left: .2rem;
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
    box-shadow:
        0 5px 15px
        rgba(37,99,235,.18);
}
.supervisor-topbar__user strong {
    display: block;
    color:
        var(--sv-text, #111827);
    font-size: .7rem;
}
.supervisor-topbar__user span {
    display: block;
    margin-top: .1rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .59rem;
}
/* ================================================================
   HERO
================================================================ */
.cohorts-hero {
    position: relative;
    overflow: hidden;
    min-height: 190px;
    padding: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    border-radius: 22px;
    background:
        linear-gradient(
            135deg,
            #1d4ed8 0%,
            #4338ca 48%,
            #7c3aed 100%
        );
    color: #fff;
    box-shadow:
        0 16px 35px
        rgba(37,99,235,.18);
}
.cohorts-hero::before {
    content: '';
    position: absolute;
    width: 280px;
    height: 280px;
    right: -80px;
    top: -130px;
    border-radius: 50%;
    background:
        rgba(255,255,255,.08);
}
.cohorts-hero::after {
    content: '';
    position: absolute;
    width: 190px;
    height: 190px;
    right: 140px;
    bottom: -130px;
    border-radius: 50%;
    background:
        rgba(255,255,255,.05);
}
.cohorts-hero__content {
    position: relative;
    z-index: 2;
    max-width: 680px;
}
.cohorts-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    margin-bottom: .75rem;
    padding:
        .35rem .65rem;
    border:
        1px solid
        rgba(255,255,255,.18);
    border-radius: 999px;
    background:
        rgba(255,255,255,.1);
    font-size: .66rem;
    font-weight: 700;
}
.cohorts-hero h2 {
    margin:
        0 0 .55rem;
    font-size: 1.65rem;
    font-weight: 800;
}
.cohorts-hero p {
    max-width: 610px;
    margin: 0;
    opacity: .85;
    font-size: .82rem;
    line-height: 1.65;
}
.cohorts-hero__icon {
    width: 110px;
    height: 110px;
    position: relative;
    z-index: 2;
    flex: 0 0 110px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 27px;
    background:
        rgba(255,255,255,.1);
    border:
        1px solid
        rgba(255,255,255,.14);
    backdrop-filter:
        blur(10px);
    font-size: 2.5rem;
}
/* ================================================================
   STATS
================================================================ */
.cohorts-stats {
    display: grid;
    grid-template-columns:
        repeat(
            4,
            minmax(0, 1fr)
        );
    gap: 1rem;
}
.cohorts-stat {
    min-height: 120px;
    padding: 1.1rem;
    position: relative;
    overflow: hidden;
    border-radius: 17px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}
.cohorts-stat:hover {
    transform:
        translateY(-3px);
    box-shadow:
        var(--sv-shadow);
}
.cohorts-stat__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}
.cohorts-stat__icon {
    width: 43px;
    height: 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background:
        var(--sv-primary-soft, #eff6ff);
    color:
        var(--sv-primary, #2563eb);
}
.cohorts-stat__number {
    margin-top: .8rem;
    color:
        var(--sv-text, #111827);
    font-size: 1.55rem;
    font-weight: 800;
}
.cohorts-stat__label {
    display: block;
    margin-top: .18rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .72rem;
}
/* ================================================================
   FILTER CARD
================================================================ */
.cohorts-filter-card {
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
.cohorts-filter__heading strong {
    display: block;
    color:
        var(--sv-text, #111827);
    font-size: .86rem;
}
.cohorts-filter__heading span {
    display: block;
    margin-top: .2rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .67rem;
}
.cohorts-filter {
    display: flex;
    align-items: center;
    gap: .6rem;
    flex-wrap: wrap;
}
.cohorts-search {
    position: relative;
}
.cohorts-search i {
    position: absolute;
    left: .8rem;
    top: 50%;
    transform:
        translateY(-50%);
    color:
        var(--sv-text-soft, #64748b);
    font-size: .72rem;
}
.cohorts-search input {
    width: 235px;
    height: 42px;
    padding:
        0 .85rem
        0 2.25rem;
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
    font-size: .72rem;
}
.cohorts-filter select {
    height: 42px;
    min-width: 145px;
    padding:
        0 .7rem;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 11px;
    background:
        var(--sv-card-soft, #f8fafc);
    color:
        var(--sv-text, #111827);
    font-family: inherit;
    font-size: .72rem;
}
.cohorts-filter-button {
    height: 42px;
    padding:
        0 .9rem;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    border: 0;
    border-radius: 11px;
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );
    color: #fff;
    font-family: inherit;
    font-size: .71rem;
    font-weight: 700;
    cursor: pointer;
}
.cohorts-reset-button {
    height: 42px;
    padding:
        0 .8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 11px;
    background:
        var(--sv-card-bg, #fff);
    color:
        var(--sv-text-soft, #64748b);
    text-decoration: none;
    font-size: .7rem;
}
/* ================================================================
   SECTION HEADER
================================================================ */
.cohorts-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.cohorts-section-heading h3 {
    margin: 0;
    color:
        var(--sv-text, #111827);
    font-size: .98rem;
}
.cohorts-section-heading span {
    color:
        var(--sv-text-soft, #64748b);
    font-size: .68rem;
}
/* ================================================================
   COHORT GRID
================================================================ */
.cohorts-grid {
    display: grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );
    gap: 1rem;
}
/* ================================================================
   COHORT CARD
================================================================ */
.cohort-card {
    position: relative;
    overflow: hidden;
    padding: 1.2rem;
    border-radius: 19px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    transition:
        transform .22s ease,
        box-shadow .22s ease,
        border-color .22s ease;
}
.cohort-card:hover {
    transform:
        translateY(-4px);
    border-color:
        rgba(37,99,235,.25);
    box-shadow:
        var(--sv-shadow);
}
.cohort-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background:
        linear-gradient(
            180deg,
            #2563eb,
            #7c3aed
        );
}
.cohort-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.cohort-card__title-area {
    display: flex;
    align-items: center;
    gap: .75rem;
    min-width: 0;
}
.cohort-card__icon {
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.12),
            rgba(124,58,237,.10)
        );
    color:
        var(--sv-primary, #2563eb);
}
.cohort-card__title {
    min-width: 0;
}
.cohort-card__title h4 {
    margin: 0;
    overflow: hidden;
    color:
        var(--sv-text, #111827);
    font-size: .9rem;
    font-weight: 800;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.cohort-card__title p {
    margin:
        .22rem 0 0;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .68rem;
}
/* STATUS */
.cohort-badge {
    padding:
        .32rem .58rem;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    font-size: .62rem;
    font-weight: 800;
    white-space: nowrap;
}
.cohort-badge--active {
    background: #dcfce7;
    color: #166534;
}
.cohort-badge--completed {
    background: #dbeafe;
    color: #1d4ed8;
}
.cohort-badge--inactive {
    background: #f1f5f9;
    color: #475569;
}
.cohort-badge--pending {
    background: #fef3c7;
    color: #92400e;
}
.cohort-badge--cancelled {
    background: #fee2e2;
    color: #991b1b;
}
.cohort-badge--default {
    background: #ede9fe;
    color: #6d28d9;
}
/* INFO */
.cohort-card__info {
    margin-top: 1rem;
    display: grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );
    gap: .65rem;
}
.cohort-info-item {
    padding: .7rem;
    border-radius: 11px;
    background:
        var(--sv-card-soft, #f8fafc);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.cohort-info-item span {
    display: flex;
    align-items: center;
    gap: .3rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .6rem;
}
.cohort-info-item strong {
    display: block;
    margin-top: .28rem;
    color:
        var(--sv-text, #111827);
    font-size: .7rem;
}
/* METRICS */
.cohort-card__metrics {
    margin-top: .8rem;
    display: grid;
    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );
    gap: .45rem;
}
.cohort-metric {
    padding: .65rem .4rem;
    text-align: center;
    border-radius: 10px;
    background:
        var(--sv-card-soft, #f8fafc);
}
.cohort-metric strong {
    display: block;
    color:
        var(--sv-text, #111827);
    font-size: .85rem;
}
.cohort-metric span {
    display: block;
    margin-top: .15rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .56rem;
}
/* PROGRESS */
.cohort-progress {
    margin-top: .9rem;
}
.cohort-progress__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .4rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .62rem;
}
.cohort-progress__heading strong {
    color:
        var(--sv-text, #111827);
}
.cohort-progress__track {
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background:
        rgba(148,163,184,.18);
}
.cohort-progress__bar {
    height: 100%;
    border-radius: inherit;
    background:
        linear-gradient(
            90deg,
            #2563eb,
            #7c3aed
        );
}
/* FOOTER */
.cohort-card__footer {
    margin-top: 1rem;
    padding-top: .9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .7rem;
    border-top:
        1px solid
        var(--sv-border, #e5e7eb);
}
.cohort-card__programme-type {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .62rem;
}
.cohort-view-button {
    min-height: 38px;
    padding:
        .5rem .78rem;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    border-radius: 10px;
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );
    color: #fff;
    text-decoration: none;
    font-size: .67rem;
    font-weight: 700;
    transition:
        transform .18s ease,
        box-shadow .18s ease;
}
.cohort-view-button:hover {
    transform:
        translateY(-1px);
    box-shadow:
        0 7px 16px
        rgba(37,99,235,.20);
}
/* ================================================================
   ACCESS NOTICE
================================================================ */
.cohorts-access-notice {
    padding: 1rem;
    display: flex;
    align-items: flex-start;
    gap: .7rem;
    border-radius: 15px;
    background:
        rgba(37,99,235,.06);
    border:
        1px solid
        rgba(37,99,235,.12);
}
.cohorts-access-notice__icon {
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
.cohorts-access-notice strong {
    display: block;
    color:
        var(--sv-text, #111827);
    font-size: .72rem;
}
.cohorts-access-notice p {
    margin:
        .2rem 0 0;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .65rem;
    line-height: 1.5;
}
/* ================================================================
   EMPTY STATE
================================================================ */
.cohorts-empty {
    grid-column:
        1 / -1;
    padding: 3rem 1rem;
    text-align: center;
    border-radius: 18px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.cohorts-empty__icon {
    width: 64px;
    height: 64px;
    margin:
        0 auto .9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background:
        var(--sv-primary-soft, #eff6ff);
    color:
        var(--sv-primary, #2563eb);
    font-size: 1.3rem;
}
.cohorts-empty h3 {
    margin: 0;
    color:
        var(--sv-text, #111827);
}
.cohorts-empty p {
    margin:
        .35rem auto 0;
    max-width: 450px;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .72rem;
    line-height: 1.5;
}
/* ================================================================
   DARK MODE
================================================================ */
html[data-theme="dark"]
.cohort-badge--active {
    background:
        rgba(34,197,94,.12);
    color: #86efac;
}
html[data-theme="dark"]
.cohort-badge--completed {
    background:
        rgba(59,130,246,.13);
    color: #93c5fd;
}
html[data-theme="dark"]
.cohort-badge--inactive {
    background:
        rgba(148,163,184,.10);
    color: #cbd5e1;
}
html[data-theme="dark"]
.cohort-badge--pending {
    background:
        rgba(245,158,11,.13);
    color: #fcd34d;
}
html[data-theme="dark"]
.cohort-badge--cancelled {
    background:
        rgba(239,68,68,.13);
    color: #fca5a5;
}
html[data-theme="dark"]
.cohort-badge--default {
    background:
        rgba(124,58,237,.15);
    color: #c4b5fd;
}
/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:1100px) {
    .cohorts-stats {
        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }
}
@media(max-width:950px) {
    .cohorts-grid {
        grid-template-columns: 1fr;
    }
}
@media(max-width:900px) {
    .supervisor-topbar__menu {
        display: inline-flex;
    }
}
@media(max-width:760px) {
    .supervisor-topbar {
        padding:
            .7rem 1rem;
    }
    .supervisor-topbar__search {
        display: none;
    }
    .supervisor-topbar__user-copy {
        display: none;
    }
    .cohorts-page {
        padding: 1rem;
    }
    .cohorts-hero__icon {
        display: none;
    }
    .cohorts-filter-card {
        align-items: stretch;
        flex-direction: column;
    }
    .cohorts-filter {
        width: 100%;
    }
    .cohorts-search {
        flex: 1;
    }
    .cohorts-search input {
        width: 100%;
    }
}
@media(max-width:560px) {
    .cohorts-stats {
        grid-template-columns: 1fr 1fr;
    }
    .cohort-card__metrics {
        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }
    .cohorts-filter {
        display: grid;
        grid-template-columns: 1fr;
    }
    .cohorts-filter select,
    .cohorts-filter-button,
    .cohorts-reset-button {
        width: 100%;
    }
    .cohorts-filter-button,
    .cohorts-reset-button {
        justify-content: center;
    }
}
@media(max-width:420px) {
    .cohorts-stats {
        grid-template-columns: 1fr;
    }
    .cohort-card__info {
        grid-template-columns: 1fr;
    }
}
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard">
    <!-- ============================================================
         SIDEBAR
    ============================================================= -->
    <?php
    require_once __DIR__ . '/sidebar.php';
    ?>
    <!-- ============================================================
         MAIN
    ============================================================= -->
    <main class="dashboard__main">
        <!-- ========================================================
             NAVBAR
        ========================================================= -->
        <?php
        require_once __DIR__ . '/navbar.php';
        ?>
        <!-- ========================================================
             CONTENT
        ========================================================= -->
        <div class="cohorts-page">
            <?= $flashes ?>
            <!-- ====================================================
                 HERO
            ===================================================== -->
            <section class="cohorts-hero">
                <div class="cohorts-hero__content">
                    <span class="cohorts-hero__eyebrow">
                        <i class="fas fa-layer-group"></i>
                        Assigned Cohorts
                    </span>
                    <h2>
                        Manage your cohort workspace
                    </h2>
                    <p>
                        Track assigned cohorts, monitor candidate
                        participation and quickly open each cohort
                        to review candidate progress.
                    </p>
                </div>
                <div class="cohorts-hero__icon">
                    <i class="fas fa-people-group"></i>
                </div>
            </section>
            <!-- ====================================================
                 STATS
            ===================================================== -->
            <section class="cohorts-stats">
                <div class="cohorts-stat">
                    <div class="cohorts-stat__top">
                        <div class="cohorts-stat__icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                    <div class="cohorts-stat__number">
                        <?= number_format(
                            $totalCohorts
                        ) ?>
                    </div>
                    <span class="cohorts-stat__label">
                        Assigned Cohorts
                    </span>
                </div>
                <div class="cohorts-stat">
                    <div class="cohorts-stat__top">
                        <div class="cohorts-stat__icon">
                            <i class="fas fa-circle-play"></i>
                        </div>
                    </div>
                    <div class="cohorts-stat__number">
                        <?= number_format(
                            $activeCohorts
                        ) ?>
                    </div>
                    <span class="cohorts-stat__label">
                        Active Cohorts
                    </span>
                </div>
                <div class="cohorts-stat">
                    <div class="cohorts-stat__top">
                        <div class="cohorts-stat__icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="cohorts-stat__number">
                        <?= number_format(
                            $totalCandidates
                        ) ?>
                    </div>
                    <span class="cohorts-stat__label">
                        Current Candidates
                    </span>
                </div>
                <div class="cohorts-stat">
                    <div class="cohorts-stat__top">
                        <div class="cohorts-stat__icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                    </div>
                    <div class="cohorts-stat__number">
                        <?= number_format(
                            $completedCandidates
                        ) ?>
                    </div>
                    <span class="cohorts-stat__label">
                        Completed Candidates
                    </span>
                </div>
            </section>
            <!-- ====================================================
                 FILTERS
            ===================================================== -->
            <section class="cohorts-filter-card">
                <div class="cohorts-filter__heading">
                    <strong>
                        Find a cohort
                    </strong>
                    <span>
                        Search by cohort, programme or programme type.
                    </span>
                </div>
                <form
                    method="GET"
                    action=""
                    class="cohorts-filter"
                >
                    <div class="cohorts-search">
                        <i class="fas fa-magnifying-glass"></i>
                        <input
                            type="search"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Search cohorts..."
                            autocomplete="off"
                        >
                    </div>
                    <select name="status">
                        <option value="">
                            All statuses
                        </option>
                        <?php foreach (
                            $cohortStatuses
                            as $status
                        ): ?>
                            <option
                                value="<?= e($status) ?>"
                                <?= $statusFilter === $status
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e(
                                    supervisorCohortsStatusLabel(
                                        $status
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        type="submit"
                        class="cohorts-filter-button"
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
                                'supervisor/cohorts.php'
                            ) ?>"
                            class="cohorts-reset-button"
                        >
                            <i class="fas fa-rotate-left"></i>
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </section>
            <!-- ====================================================
                 SECTION HEADER
            ===================================================== -->
            <div class="cohorts-section-heading">
                <h3>
                    Your Cohorts
                </h3>
                <span>
                    <?= number_format(
                        count($cohorts)
                    ) ?>
                    <?= count($cohorts) === 1
                        ? 'cohort'
                        : 'cohorts' ?>
                    displayed
                </span>
            </div>
            <!-- ====================================================
                 COHORT CARDS
            ===================================================== -->
            <section class="cohorts-grid">
                <?php if (
                    empty($cohorts)
                ): ?>
                    <div class="cohorts-empty">
                        <div class="cohorts-empty__icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h3>
                            No cohorts found
                        </h3>
                        <p>
                            <?php if (
                                $search !== ''
                                ||
                                $statusFilter !== ''
                            ): ?>
                                No assigned cohorts matched the
                                filters you selected.
                            <?php else: ?>
                                There are currently no cohorts
                                assigned to your Supervisor account.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach (
                        $cohorts
                        as $cohort
                    ): ?>
                        <?php
                        $candidateCount = (int)(
                            $cohort[
                                'candidate_count'
                            ]
                            ?? 0
                        );
                        $activeCount = (int)(
                            $cohort[
                                'active_count'
                            ]
                            ?? 0
                        );
                        $completedCount = (int)(
                            $cohort[
                                'completed_count'
                            ]
                            ?? 0
                        );
                        $withdrawnCount = (int)(
                            $cohort[
                                'withdrawn_count'
                            ]
                            ?? 0
                        );
                        $progress =
                            supervisorCohortsProgress(
                                $candidateCount,
                                $completedCount
                            );
                        ?>
                        <article class="cohort-card">
                            <!-- HEADER -->
                            <div class="cohort-card__header">
                                <div class="cohort-card__title-area">
                                    <div class="cohort-card__icon">
                                        <i class="fas fa-users-viewfinder"></i>
                                    </div>
                                    <div class="cohort-card__title">
                                        <h4>
                                            <?= e(
                                                $cohort[
                                                    'name'
                                                ]
                                            ) ?>
                                        </h4>
                                        <p>
                                            <?= e(
                                                $cohort[
                                                    'programme_name'
                                                ]
                                            ) ?>
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="
                                        cohort-badge
                                        <?= supervisorCohortsStatusClass(
                                            (string)(
                                                $cohort[
                                                    'status'
                                                ]
                                                ?? ''
                                            )
                                        ) ?>
                                    "
                                >
                                    <?= e(
                                        supervisorCohortsStatusLabel(
                                            (string)(
                                                $cohort[
                                                    'status'
                                                ]
                                                ?? ''
                                            )
                                        )
                                    ) ?>
                                </span>
                            </div>
                            <!-- INFO -->
                            <div class="cohort-card__info">
                                <div class="cohort-info-item">
                                    <span>
                                        <i class="far fa-calendar"></i>
                                        Start Date
                                    </span>
                                    <strong>
                                        <?= e(
                                            supervisorCohortsDate(
                                                $cohort[
                                                    'start_date'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </strong>
                                </div>
                                <div class="cohort-info-item">
                                    <span>
                                        <i class="far fa-calendar-check"></i>
                                        End Date
                                    </span>
                                    <strong>
                                        <?= e(
                                            supervisorCohortsDate(
                                                $cohort[
                                                    'end_date'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </strong>
                                </div>
                            </div>
                            <!-- METRICS -->
                            <div class="cohort-card__metrics">
                                <div class="cohort-metric">
                                    <strong>
                                        <?= $candidateCount ?>
                                    </strong>
                                    <span>
                                        Candidates
                                    </span>
                                </div>
                                <div class="cohort-metric">
                                    <strong>
                                        <?= $activeCount ?>
                                    </strong>
                                    <span>
                                        Active
                                    </span>
                                </div>
                                <div class="cohort-metric">
                                    <strong>
                                        <?= $completedCount ?>
                                    </strong>
                                    <span>
                                        Completed
                                    </span>
                                </div>
                                <div class="cohort-metric">
                                    <strong>
                                        <?= $withdrawnCount ?>
                                    </strong>
                                    <span>
                                        Withdrawn
                                    </span>
                                </div>
                            </div>
                            <!-- PROGRESS -->
                            <div class="cohort-progress">
                                <div class="cohort-progress__heading">
                                    <span>
                                        Completion progress
                                    </span>
                                    <strong>
                                        <?= $progress ?>%
                                    </strong>
                                </div>
                                <div class="cohort-progress__track">
                                    <div
                                        class="cohort-progress__bar"
                                        style="
                                            width:
                                            <?= $progress ?>%;
                                        "
                                    ></div>
                                </div>
                            </div>
                            <!-- FOOTER -->
                            <div class="cohort-card__footer">
                                <span class="cohort-card__programme-type">
                                    <i class="fas fa-briefcase"></i>
                                    <?= e(
                                        supervisorCohortsStatusLabel(
                                            (string)(
                                                $cohort[
                                                    'programme_type'
                                                ]
                                                ?? 'Programme'
                                            )
                                        )
                                    ) ?>
                                </span>
                                <a
                                    href="<?= url(
                                        'supervisor/cohort_view.php?id=' .
                                        (int)$cohort['id']
                                    ) ?>"
                                    class="cohort-view-button"
                                >
                                    View Cohort
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
            <!-- ====================================================
                 ACCESS NOTICE
            ===================================================== -->
            <div class="cohorts-access-notice">
                <div class="cohorts-access-notice__icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <strong>
                        Supervisor access protection
                    </strong>
                    <p>
                        Only cohorts assigned to your account are
                        displayed. Candidate assignment, cohort
                        reassignment and Supervisor changes remain
                        restricted.
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
        function updateIcon() {
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
            /*
             * Synchronise sidebar icon too.
             */
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
        updateIcon();
        button?.addEventListener(
            'click',
            function () {
                const dark =
                    document.documentElement
                        .getAttribute(
                            'data-theme'
                        ) === 'dark';
                const next =
                    dark
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
                updateIcon();
            }
        );
    }
);
</script>
</body>
</html>