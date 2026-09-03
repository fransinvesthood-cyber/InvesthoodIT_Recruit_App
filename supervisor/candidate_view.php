<?php
/**
 * ================================================================
 * INVESTHOOD IT - SUPERVISOR / CANDIDATE VIEW
 * ================================================================
 *
 * Supervisor permissions:
 *  - View candidates in assigned cohorts only
 *  - View candidate participation history
 *  - View programme/cohort context
 *  - Update participation status for current assigned cohort
 *
 * Supervisor cannot:
 *  - Assign candidates
 *  - Move candidates between cohorts
 *  - Reassign supervisors
 *  - Modify programmes or cohort ownership
 * ================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
$user = current_user();
$flashes = render_flashes();
$currentPage = 'candidates';
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
| Supervisor Profile
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
| Candidate / Cohort IDs
|--------------------------------------------------------------------------
*/
$candidateId = (int)(
    $_GET['id']
    ?? $_GET['candidate_id']
    ?? 0
);
$cohortId = (int)(
    $_GET['cohort_id']
    ?? 0
);
if ($candidateId <= 0) {
    header(
        'Location: ' .
        url('supervisor/candidates.php')
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Candidate Query
|--------------------------------------------------------------------------
|
| Critical:
| Candidate must belong to a cohort assigned to this Supervisor.
|
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
        u.email,
        c.id AS cohort_id,
        c.name AS cohort_name,
        c.status AS cohort_status,
        c.start_date AS cohort_start_date,
        c.end_date AS cohort_end_date,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.status AS programme_status,
        p.start_date AS programme_start_date,
        p.end_date AS programme_end_date
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    INNER JOIN users u
        ON u.id = cp.user_id
    WHERE cp.user_id = ?
      AND c.supervisor_id = ?
";
$types = 'ii';
$params = [
    $candidateId,
    $supervisorId
];
if ($cohortId > 0) {
    $sql .= "
        AND c.id = ?
    ";
    $types .= 'i';
    $params[] = $cohortId;
}
$sql .= "
    ORDER BY
        CASE cp.status
            WHEN 'active' THEN 1
            WHEN 'onboarded' THEN 2
            WHEN 'selected' THEN 3
            WHEN 'completed' THEN 4
            WHEN 'withdrawn' THEN 5
            ELSE 6
        END,
        c.start_date DESC,
        cp.id DESC
    LIMIT 1
";
$stmt = Database::prepare(
    $sql,
    $types,
    $params
);
$candidate = $stmt
    ->get_result()
    ->fetch_assoc();
$stmt->close();
if (!$candidate) {
    http_response_code(404);
    exit(
        'Candidate not found or you do not have access to this candidate.'
    );
}
/*
|--------------------------------------------------------------------------
| Resolve Active Cohort
|--------------------------------------------------------------------------
*/
$activeCohortId = (int)(
    $candidate['cohort_id']
    ?? 0
);
/*
|--------------------------------------------------------------------------
| Participation History
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            cp.id AS participant_id,
            cp.user_id,
            cp.cohort_id,
            cp.status AS participation_status,
            cp.selected_at,
            cp.onboarded_at,
            cp.completed_at,
            c.name AS cohort_name,
            c.status AS cohort_status,
            c.start_date AS cohort_start_date,
            c.end_date AS cohort_end_date,
            p.id AS programme_id,
            p.name AS programme_name,
            p.type AS programme_type,
            p.status AS programme_status
        FROM cohort_participants cp
        INNER JOIN cohorts c
            ON c.id = cp.cohort_id
        INNER JOIN programmes p
            ON p.id = c.programme_id
        WHERE cp.user_id = ?
          AND c.supervisor_id = ?
        ORDER BY
            c.start_date DESC,
            cp.id DESC
    ",
    'ii',
    [
        $candidateId,
        $supervisorId
    ]
);
$result = $stmt->get_result();
$participationHistory = [];
while ($row = $result->fetch_assoc()) {
    $participationHistory[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function candidateViewStatusLabel(
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
function candidateViewStatusClass(
    string $status
): string {
    $status = strtolower(
        trim($status)
    );
    switch ($status) {
        case 'active':
            return 'status-badge--active';
        case 'completed':
            return 'status-badge--completed';
        case 'withdrawn':
            return 'status-badge--withdrawn';
        case 'selected':
            return 'status-badge--selected';
        case 'onboarded':
            return 'status-badge--onboarded';
        case 'pending':
            return 'status-badge--pending';
        default:
            return 'status-badge--default';
    }
}
function candidateViewDate(
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
function candidateViewDateTime(
    ?string $date
): string {
    if (empty($date)) {
        return 'Not recorded';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return 'Not recorded';
    }
    return date(
        'd M Y, H:i',
        $timestamp
    );
}
function candidateViewInitials(
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
function candidateViewProgress(
    ?string $selectedAt,
    ?string $onboardedAt,
    ?string $completedAt,
    string $status
): int {
    $progress = 0;
    if (!empty($selectedAt)) {
        $progress = 30;
    }
    if (!empty($onboardedAt)) {
        $progress = 65;
    }
    if (
        !empty($completedAt)
        ||
        strtolower($status) === 'completed'
    ) {
        $progress = 100;
    }
    if (strtolower($status) === 'active' && $progress < 65) {
        $progress = 65;
    }
    return $progress;
}
/*
|--------------------------------------------------------------------------
| Candidate Details
|--------------------------------------------------------------------------
*/
$candidateFirstName = trim(
    (string)(
        $candidate['first_name']
        ?? ''
    )
);
$candidateLastName = trim(
    (string)(
        $candidate['last_name']
        ?? ''
    )
);
$candidateName = trim(
    $candidateFirstName .
    ' ' .
    $candidateLastName
);
if ($candidateName === '') {
    $candidateName = 'Candidate';
}
$candidateEmail = trim(
    (string)(
        $candidate['email']
        ?? ''
    )
);
$participationStatus = strtolower(
    trim(
        (string)(
            $candidate['participation_status']
            ?? ''
        )
    )
);
$progress = candidateViewProgress(
    $candidate['selected_at'] ?? null,
    $candidate['onboarded_at'] ?? null,
    $candidate['completed_at'] ?? null,
    $participationStatus
);
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
        <?= e($candidateName) ?>
        | Supervisor
    </title>
    <!-- ============================================================
         APPLY THEME BEFORE RENDERING
    ============================================================= -->
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
.candidate-view-page {
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
    font-weight: 800;
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
    transition:
        transform .18s ease,
        border-color .18s ease;
}
.supervisor-topbar__icon:hover {
    transform:
        translateY(-2px);
    border-color:
        rgba(37,99,235,.3);
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
.candidate-breadcrumb {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .66rem;
}
.candidate-breadcrumb a {
    color:
        var(--sv-text-soft, #64748b);
    text-decoration: none;
}
.candidate-breadcrumb a:hover {
    color:
        var(--sv-primary, #2563eb);
}
.candidate-breadcrumb i {
    font-size: .5rem;
}
/* ================================================================
   PROFILE HERO
================================================================ */
.candidate-profile-hero {
    position: relative;
    overflow: hidden;
    min-height: 220px;
    padding: 1.7rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    border-radius: 22px;
    background:
        linear-gradient(
            135deg,
            #0f4bc8 0%,
            #4338ca 52%,
            #7c3aed 100%
        );
    color: #fff;
    box-shadow:
        0 16px 35px
        rgba(37,99,235,.18);
}
.candidate-profile-hero::before {
    content: '';
    position: absolute;
    width: 300px;
    height: 300px;
    right: -100px;
    top: -150px;
    border-radius: 50%;
    background:
        rgba(255,255,255,.08);
}
.candidate-profile-hero::after {
    content: '';
    position: absolute;
    width: 190px;
    height: 190px;
    right: 160px;
    bottom: -140px;
    border-radius: 50%;
    background:
        rgba(255,255,255,.05);
}
.candidate-profile-hero__main {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 1.15rem;
    min-width: 0;
}
.candidate-profile-hero__avatar {
    width: 88px;
    height: 88px;
    flex: 0 0 88px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 24px;
    background:
        rgba(255,255,255,.12);
    border:
        1px solid
        rgba(255,255,255,.17);
    backdrop-filter:
        blur(8px);
    font-size: 1.35rem;
    font-weight: 800;
}
.candidate-profile-hero__copy {
    min-width: 0;
}
.candidate-profile-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding:
        .35rem .65rem;
    border-radius: 999px;
    background:
        rgba(255,255,255,.10);
    border:
        1px solid
        rgba(255,255,255,.15);
    font-size: .62rem;
    font-weight: 700;
}
.candidate-profile-hero h2 {
    margin:
        .65rem 0 .25rem;
    font-size: 1.7rem;
    font-weight: 800;
}
.candidate-profile-hero__email {
    display: flex;
    align-items: center;
    gap: .35rem;
    opacity: .83;
    font-size: .72rem;
}
.candidate-profile-hero__meta {
    margin-top: .9rem;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}
.candidate-profile-hero__meta span {
    padding:
        .37rem .58rem;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border-radius: 9px;
    background:
        rgba(255,255,255,.09);
    border:
        1px solid
        rgba(255,255,255,.12);
    font-size: .6rem;
}
.candidate-profile-hero__actions {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    gap: .6rem;
    min-width: 155px;
}
.candidate-hero-action {
    min-height: 41px;
    padding:
        .55rem .8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    border-radius: 11px;
    text-decoration: none;
    font-size: .66rem;
    font-weight: 700;
}
.candidate-hero-action--primary {
    background: #fff;
    color: #1d4ed8;
}
.candidate-hero-action--secondary {
    background:
        rgba(255,255,255,.09);
    border:
        1px solid
        rgba(255,255,255,.15);
    color: #fff;
}
/* ================================================================
   STATUS BADGE
================================================================ */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: .32rem;
    padding:
        .34rem .6rem;
    border-radius: 999px;
    font-size: .6rem;
    font-weight: 800;
}
.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}
.status-badge--active {
    background: #dcfce7;
    color: #166534;
}
.status-badge--completed {
    background: #dbeafe;
    color: #1d4ed8;
}
.status-badge--withdrawn {
    background: #fee2e2;
    color: #991b1b;
}
.status-badge--selected {
    background: #ede9fe;
    color: #6d28d9;
}
.status-badge--onboarded {
    background: #cffafe;
    color: #155e75;
}
.status-badge--pending {
    background: #fef3c7;
    color: #92400e;
}
.status-badge--default {
    background: #f1f5f9;
    color: #475569;
}
/* ================================================================
   OVERVIEW GRID
================================================================ */
.candidate-overview-grid {
    display: grid;
    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );
    gap: 1rem;
}
.candidate-info-card {
    padding: 1.15rem;
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
.candidate-info-card:hover {
    transform:
        translateY(-3px);
    box-shadow:
        var(--sv-shadow);
}
.candidate-info-card__header {
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-bottom: .9rem;
}
.candidate-info-card__icon {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background:
        var(--sv-primary-soft, #eff6ff);
    color:
        var(--sv-primary, #2563eb);
}
.candidate-info-card__header strong {
    color:
        var(--sv-text, #111827);
    font-size: .78rem;
}
.candidate-info-card__header span {
    display: block;
    margin-top: .12rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .6rem;
}
.candidate-info-list {
    display: grid;
    gap: .65rem;
}
.candidate-info-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.candidate-info-row span {
    color:
        var(--sv-text-soft, #64748b);
    font-size: .63rem;
}
.candidate-info-row strong {
    max-width: 65%;
    color:
        var(--sv-text, #111827);
    font-size: .66rem;
    text-align: right;
}
/* ================================================================
   PROGRESS CARD
================================================================ */
.candidate-progress-card {
    padding: 1.2rem;
    border-radius: 17px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.candidate-progress-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}
.candidate-progress-card__header h3 {
    margin: 0;
    color:
        var(--sv-text, #111827);
    font-size: .85rem;
}
.candidate-progress-card__header span {
    color:
        var(--sv-text-soft, #64748b);
    font-size: .64rem;
}
.candidate-progress-card__bar-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .45rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .66rem;
}
.candidate-progress-card__bar-heading strong {
    color:
        var(--sv-text, #111827);
}
.candidate-progress-track {
    height: 9px;
    overflow: hidden;
    border-radius: 999px;
    background:
        rgba(148,163,184,.17);
}
.candidate-progress-bar {
    height: 100%;
    border-radius: inherit;
    background:
        linear-gradient(
            90deg,
            #2563eb,
            #7c3aed
        );
}
/* ================================================================
   TIMELINE
================================================================ */
.candidate-timeline {
    margin-top: 1.2rem;
    display: grid;
    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );
    gap: .8rem;
}
.candidate-timeline-step {
    position: relative;
    padding: .9rem;
    border-radius: 13px;
    background:
        var(--sv-card-soft, #f8fafc);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.candidate-timeline-step__icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background:
        var(--sv-card-bg, #fff);
    color:
        var(--sv-text-soft, #64748b);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.candidate-timeline-step--done
.candidate-timeline-step__icon {
    background:
        rgba(34,197,94,.12);
    border-color:
        rgba(34,197,94,.18);
    color: #22c55e;
}
.candidate-timeline-step strong {
    display: block;
    margin-top: .65rem;
    color:
        var(--sv-text, #111827);
    font-size: .68rem;
}
.candidate-timeline-step span {
    display: block;
    margin-top: .2rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .6rem;
    line-height: 1.4;
}
/* ================================================================
   PARTICIPATION HISTORY
================================================================ */
.candidate-history-card {
    overflow: hidden;
    border-radius: 17px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
}
.candidate-history-card__header {
    padding: 1rem 1.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);
}
.candidate-history-card__header h3 {
    margin: 0;
    color:
        var(--sv-text, #111827);
    font-size: .84rem;
}
.candidate-history-card__header span {
    color:
        var(--sv-text-soft, #64748b);
    font-size: .63rem;
}
.candidate-history-table-wrap {
    overflow-x: auto;
}
.candidate-history-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}
.candidate-history-table th,
.candidate-history-table td {
    padding:
        .85rem 1rem;
    text-align: left;
    vertical-align: middle;
    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);
}
.candidate-history-table tbody tr:last-child td {
    border-bottom: 0;
}
.candidate-history-table th {
    color:
        var(--sv-text-soft, #64748b);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.candidate-history-table td {
    color:
        var(--sv-text, #111827);
    font-size: .67rem;
}
.history-context strong {
    display: block;
    font-size: .67rem;
}
.history-context span {
    display: block;
    margin-top: .12rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .59rem;
}
.history-cohort-link {
    color:
        var(--sv-primary, #2563eb);
    text-decoration: none;
    font-weight: 700;
}
.history-cohort-link:hover {
    text-decoration: underline;
}
.history-view-button {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background:
        var(--sv-card-bg, #fff);
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    color:
        var(--sv-text-soft, #64748b);
    text-decoration: none;
}
/* ================================================================
   SECURITY NOTICE
================================================================ */
.candidate-security {
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
.candidate-security__icon {
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
.candidate-security strong {
    display: block;
    color:
        var(--sv-text, #111827);
    font-size: .7rem;
}
.candidate-security p {
    margin:
        .2rem 0 0;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .64rem;
    line-height: 1.5;
}
/* ================================================================
   DARK MODE
================================================================ */
html[data-theme="dark"]
.status-badge--active {
    background:
        rgba(34,197,94,.12);
    color: #86efac;
}
html[data-theme="dark"]
.status-badge--completed {
    background:
        rgba(59,130,246,.13);
    color: #93c5fd;
}
html[data-theme="dark"]
.status-badge--withdrawn {
    background:
        rgba(239,68,68,.13);
    color: #fca5a5;
}
html[data-theme="dark"]
.status-badge--selected {
    background:
        rgba(124,58,237,.15);
    color: #c4b5fd;
}
html[data-theme="dark"]
.status-badge--onboarded {
    background:
        rgba(6,182,212,.13);
    color: #67e8f9;
}
html[data-theme="dark"]
.status-badge--pending {
    background:
        rgba(245,158,11,.13);
    color: #fcd34d;
}
html[data-theme="dark"]
.status-badge--default {
    background:
        rgba(148,163,184,.10);
    color: #cbd5e1;
}
/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:1050px) {
    .candidate-overview-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }
    .candidate-overview-grid
    .candidate-info-card:last-child {
        grid-column:
            1 / -1;
    }
}
@media(max-width:900px) {
    .supervisor-topbar__menu {
        display: inline-flex;
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
    .candidate-view-page {
        padding: 1rem;
    }
    .candidate-profile-hero {
        align-items: flex-start;
        flex-direction: column;
    }
    .candidate-profile-hero__actions {
        width: 100%;
        flex-direction: row;
        flex-wrap: wrap;
    }
    .candidate-hero-action {
        flex: 1;
    }
    .candidate-timeline {
        grid-template-columns: 1fr;
    }
}
@media(max-width:560px) {
    .candidate-profile-hero__main {
        align-items: flex-start;
        flex-direction: column;
    }
    .candidate-overview-grid {
        grid-template-columns: 1fr;
    }
    .candidate-overview-grid
    .candidate-info-card:last-child {
        grid-column: auto;
    }
    .candidate-profile-hero h2 {
        font-size: 1.35rem;
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
        <div class="candidate-view-page">
            <?= $flashes ?>
            <!-- ====================================================
                 BREADCRUMB
            ===================================================== -->
            <div class="candidate-breadcrumb">
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
                        'supervisor/candidates.php'
                    ) ?>"
                >
                    Candidates
                </a>
                <i class="fas fa-chevron-right"></i>
                <a
                    href="<?= url(
                        'supervisor/cohort_view.php?id=' .
                        $activeCohortId
                    ) ?>"
                >
                    <?= e(
                        $candidate['cohort_name']
                    ) ?>
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>
                    <?= e($candidateName) ?>
                </span>
            </div>
            <!-- ====================================================
                 PROFILE HERO
            ===================================================== -->
            <section class="candidate-profile-hero">
                <div class="candidate-profile-hero__main">
                    <div class="candidate-profile-hero__avatar">
                        <?= e(
                            candidateViewInitials(
                                $candidateFirstName,
                                $candidateLastName
                            )
                        ) ?>
                    </div>
                    <div class="candidate-profile-hero__copy">
                        <span class="candidate-profile-hero__eyebrow">
                            <i class="fas fa-user-graduate"></i>
                            Candidate Profile
                        </span>
                        <h2>
                            <?= e($candidateName) ?>
                        </h2>
                        <div class="candidate-profile-hero__email">
                            <i class="fas fa-envelope"></i>
                            <?= e(
                                $candidateEmail !== ''
                                    ? $candidateEmail
                                    : 'No email available'
                            ) ?>
                        </div>
                        <div class="candidate-profile-hero__meta">
                            <span>
                                <i class="fas fa-briefcase"></i>
                                <?= e(
                                    $candidate[
                                        'programme_name'
                                    ]
                                ) ?>
                            </span>
                            <span>
                                <i class="fas fa-layer-group"></i>
                                <?= e(
                                    $candidate[
                                        'cohort_name'
                                    ]
                                ) ?>
                            </span>
                            <span>
                                <i class="fas fa-circle"></i>
                                <?= e(
                                    candidateViewStatusLabel(
                                        $participationStatus
                                    )
                                ) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="candidate-profile-hero__actions">
                    <?php if (
                        $participationStatus
                        !== 'withdrawn'
                    ): ?>
                        <a
                            href="<?= url(
                                'supervisor/update_candidate_status.php?id=' .
                                $candidateId .
                                '&cohort_id=' .
                                $activeCohortId
                            ) ?>"
                            class="
                                candidate-hero-action
                                candidate-hero-action--primary
                            "
                        >
                            <i class="fas fa-pen"></i>
                            Update Status
                        </a>
                    <?php endif; ?>
                    <a
                        href="<?= url(
                            'supervisor/cohort_view.php?id=' .
                            $activeCohortId
                        ) ?>"
                        class="
                            candidate-hero-action
                            candidate-hero-action--secondary
                        "
                    >
                        <i class="fas fa-arrow-left"></i>
                        Back to Cohort
                    </a>
                </div>
            </section>
            <!-- ====================================================
                 OVERVIEW CARDS
            ===================================================== -->
            <section class="candidate-overview-grid">
                <!-- PROFILE -->
                <article class="candidate-info-card">
                    <div class="candidate-info-card__header">
                        <div class="candidate-info-card__icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <strong>
                                Candidate
                            </strong>
                            <span>
                                Personal information
                            </span>
                        </div>
                    </div>
                    <div class="candidate-info-list">
                        <div class="candidate-info-row">
                            <span>
                                Full Name
                            </span>
                            <strong>
                                <?= e($candidateName) ?>
                            </strong>
                        </div>
                        <div class="candidate-info-row">
                            <span>
                                Email
                            </span>
                            <strong>
                                <?= e(
                                    $candidateEmail !== ''
                                        ? $candidateEmail
                                        : 'Not available'
                                ) ?>
                            </strong>
                        </div>
                        <div class="candidate-info-row">
                            <span>
                                Participation
                            </span>
                            <strong>
                                <span
                                    class="
                                        status-badge
                                        <?= candidateViewStatusClass(
                                            $participationStatus
                                        ) ?>
                                    "
                                >
                                    <?= e(
                                        candidateViewStatusLabel(
                                            $participationStatus
                                        )
                                    ) ?>
                                </span>
                            </strong>
                        </div>
                    </div>
                </article>
                <!-- PROGRAMME -->
                <article class="candidate-info-card">
                    <div class="candidate-info-card__header">
                        <div class="candidate-info-card__icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div>
                            <strong>
                                Programme
                            </strong>
                            <span>
                                Programme assignment
                            </span>
                        </div>
                    </div>
                    <div class="candidate-info-list">
                        <div class="candidate-info-row">
                            <span>
                                Programme
                            </span>
                            <strong>
                                <?= e(
                                    $candidate[
                                        'programme_name'
                                    ]
                                ) ?>
                            </strong>
                        </div>
                        <div class="candidate-info-row">
                            <span>
                                Type
                            </span>
                            <strong>
                                <?= e(
                                    candidateViewStatusLabel(
                                        (string)(
                                            $candidate[
                                                'programme_type'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div class="candidate-info-row">
                            <span>
                                Status
                            </span>
                            <strong>
                                <?= e(
                                    candidateViewStatusLabel(
                                        (string)(
                                            $candidate[
                                                'programme_status'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div class="candidate-info-row">
                            <span>
                                Programme Dates
                            </span>
                            <strong>
                                <?= e(
                                    candidateViewDate(
                                        $candidate[
                                            'programme_start_date'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                                -
                                <?= e(
                                    candidateViewDate(
                                        $candidate[
                                            'programme_end_date'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </strong>
                        </div>
                    </div>
                </article>
                <!-- COHORT -->
                <article class="candidate-info-card">
                    <div class="candidate-info-card__header">
                        <div class="candidate-info-card__icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <strong>
                                Cohort
                            </strong>
                            <span>
                                Current assigned cohort
                            </span>
                        </div>
                    </div>
                    <div class="candidate-info-list">
                        <div class="candidate-info-row">
                            <span>
                                Cohort
                            </span>
                            <strong>
                                <?= e(
                                    $candidate[
                                        'cohort_name'
                                    ]
                                ) ?>
                            </strong>
                        </div>
                        <div class="candidate-info-row">
                            <span>
                                Status
                            </span>
                            <strong>
                                <?= e(
                                    candidateViewStatusLabel(
                                        (string)(
                                            $candidate[
                                                'cohort_status'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div class="candidate-info-row">
                            <span>
                                Start
                            </span>
                            <strong>
                                <?= e(
                                    candidateViewDate(
                                        $candidate[
                                            'cohort_start_date'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div class="candidate-info-row">
                            <span>
                                End
                            </span>
                            <strong>
                                <?= e(
                                    candidateViewDate(
                                        $candidate[
                                            'cohort_end_date'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </strong>
                        </div>
                    </div>
                </article>
            </section>
            <!-- ====================================================
                 PARTICIPATION PROGRESS
            ===================================================== -->
            <section class="candidate-progress-card">
                <div class="candidate-progress-card__header">
                    <div>
                        <h3>
                            Participation Progress
                        </h3>
                        <span>
                            Candidate progress within this cohort
                        </span>
                    </div>
                    <span
                        class="
                            status-badge
                            <?= candidateViewStatusClass(
                                $participationStatus
                            ) ?>
                        "
                    >
                        <?= e(
                            candidateViewStatusLabel(
                                $participationStatus
                            )
                        ) ?>
                    </span>
                </div>
                <div class="candidate-progress-card__bar-heading">
                    <span>
                        Progress
                    </span>
                    <strong>
                        <?= $progress ?>%
                    </strong>
                </div>
                <div class="candidate-progress-track">
                    <div
                        class="candidate-progress-bar"
                        style="
                            width:
                            <?= min(
                                100,
                                max(
                                    0,
                                    $progress
                                )
                            ) ?>%;
                        "
                    ></div>
                </div>
                <!-- TIMELINE -->
                <div class="candidate-timeline">
                    <div
                        class="
                            candidate-timeline-step
                            <?= !empty(
                                $candidate[
                                    'selected_at'
                                ]
                            )
                                ? 'candidate-timeline-step--done'
                                : '' ?>
                        "
                    >
                        <div class="candidate-timeline-step__icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <strong>
                            Selected
                        </strong>
                        <span>
                            <?= e(
                                candidateViewDateTime(
                                    $candidate[
                                        'selected_at'
                                    ]
                                    ?? null
                                )
                            ) ?>
                        </span>
                    </div>
                    <div
                        class="
                            candidate-timeline-step
                            <?= !empty(
                                $candidate[
                                    'onboarded_at'
                                ]
                            )
                                ? 'candidate-timeline-step--done'
                                : '' ?>
                        "
                    >
                        <div class="candidate-timeline-step__icon">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <strong>
                            Onboarded
                        </strong>
                        <span>
                            <?= e(
                                candidateViewDateTime(
                                    $candidate[
                                        'onboarded_at'
                                    ]
                                    ?? null
                                )
                            ) ?>
                        </span>
                    </div>
                    <div
                        class="
                            candidate-timeline-step
                            <?= (
                                !empty(
                                    $candidate[
                                        'completed_at'
                                    ]
                                )
                                ||
                                $participationStatus
                                === 'completed'
                            )
                                ? 'candidate-timeline-step--done'
                                : '' ?>
                        "
                    >
                        <div class="candidate-timeline-step__icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <strong>
                            Completed
                        </strong>
                        <span>
                            <?= e(
                                candidateViewDateTime(
                                    $candidate[
                                        'completed_at'
                                    ]
                                    ?? null
                                )
                            ) ?>
                        </span>
                    </div>
                </div>
            </section>
            <!-- ====================================================
                 PARTICIPATION HISTORY
            ===================================================== -->
            <section class="candidate-history-card">
                <div class="candidate-history-card__header">
                    <h3>
                        Participation History
                    </h3>
                    <span>
                        <?= number_format(
                            count(
                                $participationHistory
                            )
                        ) ?>
                        <?= count(
                            $participationHistory
                        ) === 1
                            ? 'record'
                            : 'records' ?>
                    </span>
                </div>
                <div class="candidate-history-table-wrap">
                    <table class="candidate-history-table">
                        <thead>
                            <tr>
                                <th>
                                    Programme
                                </th>
                                <th>
                                    Cohort
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
                                    View
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (
                            $participationHistory
                            as $history
                        ): ?>
                            <?php
                            $historyStatus =
                                strtolower(
                                    trim(
                                        (string)(
                                            $history[
                                                'participation_status'
                                            ]
                                            ?? ''
                                        )
                                    )
                                );
                            ?>
                            <tr>
                                <td>
                                    <div class="history-context">
                                        <strong>
                                            <?= e(
                                                $history[
                                                    'programme_name'
                                                ]
                                            ) ?>
                                        </strong>
                                        <span>
                                            <?= e(
                                                candidateViewStatusLabel(
                                                    (string)(
                                                        $history[
                                                            'programme_type'
                                                        ]
                                                        ?? ''
                                                    )
                                                )
                                            ) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <a
                                        href="<?= url(
                                            'supervisor/cohort_view.php?id=' .
                                            (int)$history[
                                                'cohort_id'
                                            ]
                                        ) ?>"
                                        class="history-cohort-link"
                                    >
                                        <?= e(
                                            $history[
                                                'cohort_name'
                                            ]
                                        ) ?>
                                    </a>
                                </td>
                                <td>
                                    <span
                                        class="
                                            status-badge
                                            <?= candidateViewStatusClass(
                                                $historyStatus
                                            ) ?>
                                        "
                                    >
                                        <?= e(
                                            candidateViewStatusLabel(
                                                $historyStatus
                                            )
                                        ) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= e(
                                        candidateViewDate(
                                            $history[
                                                'selected_at'
                                            ]
                                            ?? null
                                        )
                                    ) ?>
                                </td>
                                <td>
                                    <?= e(
                                        candidateViewDate(
                                            $history[
                                                'onboarded_at'
                                            ]
                                            ?? null
                                        )
                                    ) ?>
                                </td>
                                <td>
                                    <?= e(
                                        candidateViewDate(
                                            $history[
                                                'completed_at'
                                            ]
                                            ?? null
                                        )
                                    ) ?>
                                </td>
                                <td>
                                    <a
                                        href="<?= url(
                                            'supervisor/candidate_view.php?id=' .
                                            $candidateId .
                                            '&cohort_id=' .
                                            (int)$history[
                                                'cohort_id'
                                            ]
                                        ) ?>"
                                        class="history-view-button"
                                        title="View this participation"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <!-- ====================================================
                 SECURITY
            ===================================================== -->
            <div class="candidate-security">
                <div class="candidate-security__icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <strong>
                        Supervisor candidate access
                    </strong>
                    <p>
                        This profile and participation history include
                        only records from cohorts assigned to your
                        Supervisor account. Candidate assignment,
                        cohort transfers, programme administration and
                        Supervisor reassignment remain restricted.
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
        const navbarIcon =
            document.getElementById(
                'navbarDarkIcon'
            );
        function updateThemeIcons() {
            const dark =
                document.documentElement
                    .getAttribute(
                        'data-theme'
                    ) === 'dark';
            if (navbarIcon) {
                navbarIcon.className =
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