<?php
/**
 * ================================================================
 * INVESTHOOD IT - SUPERVISOR / UPDATE CANDIDATE STATUS
 * ================================================================
 *
 * Supervisor permissions:
 *  - Update participation status for candidates in assigned cohorts
 *
 * Supervisor cannot:
 *  - Assign candidates
 *  - Move candidates between cohorts
 *  - Reassign supervisors
 *  - Modify programmes
 *
 * Security:
 *  - Supervisor ownership check
 *  - CSRF protection
 *  - Status allowlist
 *  - Defence-in-depth ownership validation on UPDATE
 * ================================================================
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('supervisor');

$user = current_user();

$currentPage = 'candidates';

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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
| Flash Helpers
|--------------------------------------------------------------------------
*/

function supervisorStatusSetFlash(
    string $type,
    string $message
): void {

    $_SESSION[
        'supervisor_candidate_status_flash'
    ] = [
        'type' => $type,
        'message' => $message
    ];
}

function supervisorStatusGetFlash(): ?array {

    $flash =
        $_SESSION[
            'supervisor_candidate_status_flash'
        ]
        ?? null;

    unset(
        $_SESSION[
            'supervisor_candidate_status_flash'
        ]
    );

    return is_array($flash)
        ? $flash
        : null;
}

/*
|--------------------------------------------------------------------------
| Candidate / Cohort IDs
|--------------------------------------------------------------------------
*/

$candidateId = (int)(
    $_GET['id']
    ?? $_GET['candidate_id']
    ?? $_POST['candidate_id']
    ?? 0
);

$cohortId = (int)(
    $_GET['cohort_id']
    ?? $_POST['cohort_id']
    ?? 0
);

if (
    $candidateId <= 0
    ||
    $cohortId <= 0
) {

    header(
        'Location: ' .
        url('supervisor/candidates.php')
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

$csrfSessionKey =
    'supervisor_candidate_status_csrf';

if (
    empty($_SESSION[$csrfSessionKey])
    ||
    !is_string(
        $_SESSION[$csrfSessionKey]
    )
) {

    $_SESSION[$csrfSessionKey] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION[$csrfSessionKey];

/*
|--------------------------------------------------------------------------
| Load Candidate + Ownership Check
|--------------------------------------------------------------------------
|
| Critical:
| Candidate must be inside this exact cohort and that cohort must be
| assigned to the logged-in Supervisor.
|
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

            u.first_name,
            u.last_name,
            u.email,

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

        INNER JOIN users u
            ON u.id = cp.user_id

        WHERE cp.user_id = ?
          AND cp.cohort_id = ?
          AND c.supervisor_id = ?

        LIMIT 1
    ",
    'iii',
    [
        $candidateId,
        $cohortId,
        $supervisorId
    ]
);

$candidate = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();

if (!$candidate) {

    http_response_code(403);

    exit(
        'You do not have permission to update this candidate.'
    );
}

/*
|--------------------------------------------------------------------------
| Allowed Statuses
|--------------------------------------------------------------------------
|
| Keep writes conservative until exact ENUM values are confirmed.
|
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'active',
    'completed',
    'withdrawn'
];

/*
|--------------------------------------------------------------------------
| Process Update
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    /*
    |--------------------------------------------------------------------------
    | Validate CSRF
    |--------------------------------------------------------------------------
    */

    $postedToken = (string)(
        $_POST['csrf_token']
        ?? ''
    );

    if (
        $postedToken === ''
        ||
        !hash_equals(
            $csrfToken,
            $postedToken
        )
    ) {

        supervisorStatusSetFlash(
            'danger',
            'Your session token is invalid or expired. Please try again.'
        );

        header(
            'Location: ' .
            url(
                'supervisor/update_candidate_status.php?id=' .
                $candidateId .
                '&cohort_id=' .
                $cohortId
            )
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    $newStatus = strtolower(
        trim(
            (string)(
                $_POST['status']
                ?? ''
            )
        )
    );

    if (
        !in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {

        supervisorStatusSetFlash(
            'danger',
            'The selected candidate status is not allowed.'
        );

        header(
            'Location: ' .
            url(
                'supervisor/update_candidate_status.php?id=' .
                $candidateId .
                '&cohort_id=' .
                $cohortId
            )
        );

        exit;
    }

    $currentStatus = strtolower(
        trim(
            (string)(
                $candidate[
                    'participation_status'
                ]
                ?? ''
            )
        )
    );

    /*
    |--------------------------------------------------------------------------
    | No Change
    |--------------------------------------------------------------------------
    */

    if ($newStatus === $currentStatus) {

        supervisorStatusSetFlash(
            'info',
            'No changes were made because the candidate already has this status.'
        );

        header(
            'Location: ' .
            url(
                'supervisor/update_candidate_status.php?id=' .
                $candidateId .
                '&cohort_id=' .
                $cohortId
            )
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Secure Update
    |--------------------------------------------------------------------------
    |
    | Defence-in-depth:
    | ownership is checked again inside the UPDATE.
    |
    |--------------------------------------------------------------------------
    */

    $stmt = Database::prepare(
        "
            UPDATE cohort_participants cp

            INNER JOIN cohorts c
                ON c.id = cp.cohort_id

            SET
                cp.status = ?,

                cp.completed_at =
                    CASE
                        WHEN ? = 'completed'
                            THEN COALESCE(
                                cp.completed_at,
                                NOW()
                            )

                        ELSE NULL
                    END

            WHERE cp.user_id = ?
              AND cp.cohort_id = ?
              AND c.supervisor_id = ?

            LIMIT 1
        ",
        'ssiii',
        [
            $newStatus,
            $newStatus,
            $candidateId,
            $cohortId,
            $supervisorId
        ]
    );

    $affectedRows =
        $stmt->affected_rows;

    $stmt->close();

    if ($affectedRows < 1) {

        supervisorStatusSetFlash(
            'danger',
            'The candidate status could not be updated. Please try again.'
        );

        header(
            'Location: ' .
            url(
                'supervisor/update_candidate_status.php?id=' .
                $candidateId .
                '&cohort_id=' .
                $cohortId
            )
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Rotate CSRF Token
    |--------------------------------------------------------------------------
    */

    $_SESSION[$csrfSessionKey] =
        bin2hex(
            random_bytes(32)
        );

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    supervisorStatusSetFlash(
        'success',
        'Candidate status updated successfully.'
    );

    header(
        'Location: ' .
        url(
            'supervisor/candidate_view.php?id=' .
            $candidateId .
            '&cohort_id=' .
            $cohortId
        )
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Flash
|--------------------------------------------------------------------------
*/

$statusFlash =
    supervisorStatusGetFlash();

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function supervisorStatusLabel(
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

function supervisorStatusBadgeClass(
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

        default:
            return 'status-pill--default';
    }
}

function supervisorStatusDateTime(
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

function supervisorStatusDate(
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

function supervisorStatusInitials(
    string $firstName,
    string $lastName,
    string $fallback = 'C'
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
        $initials = $fallback;
    }

    return strtoupper($initials);
}

/*
|--------------------------------------------------------------------------
| Candidate Display
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

$currentStatus = strtolower(
    trim(
        (string)(
            $candidate[
                'participation_status'
            ]
            ?? ''
        )
    )
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
        Update Candidate Status | Supervisor
    </title>

    <!-- ============================================================
         APPLY SAVED THEME
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

.status-page {

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

.status-breadcrumb {

    display: flex;

    align-items: center;

    gap: .5rem;

    flex-wrap: wrap;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .66rem;
}

.status-breadcrumb a {

    color:
        var(--sv-text-soft, #64748b);

    text-decoration: none;
}

.status-breadcrumb a:hover {

    color:
        var(--sv-primary, #2563eb);
}

.status-breadcrumb i {

    font-size: .5rem;
}

/* ================================================================
   HERO
================================================================ */

.status-hero {

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
        rgba(37,99,235,.18);
}

.status-hero::before {

    content: '';

    position: absolute;

    width: 280px;
    height: 280px;

    right: -80px;
    top: -140px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);
}

.status-hero__candidate {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    gap: 1rem;
}

.status-hero__avatar {

    width: 76px;
    height: 76px;

    flex: 0 0 76px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 21px;

    background:
        rgba(255,255,255,.12);

    border:
        1px solid
        rgba(255,255,255,.17);

    font-size: 1.1rem;

    font-weight: 800;
}

.status-hero__eyebrow {

    display: inline-flex;

    align-items: center;

    gap: .35rem;

    padding:
        .32rem .58rem;

    border-radius: 999px;

    background:
        rgba(255,255,255,.10);

    border:
        1px solid
        rgba(255,255,255,.14);

    font-size: .6rem;

    font-weight: 700;
}

.status-hero h2 {

    margin:
        .55rem 0 .2rem;

    font-size: 1.5rem;
}

.status-hero__email {

    opacity: .82;

    font-size: .69rem;
}

.status-hero__context {

    position: relative;

    z-index: 2;

    display: grid;

    gap: .45rem;

    min-width: 230px;
}

.status-hero__context-row {

    padding:
        .48rem .65rem;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 1rem;

    border-radius: 10px;

    background:
        rgba(255,255,255,.09);

    border:
        1px solid
        rgba(255,255,255,.12);

    font-size: .61rem;
}

.status-hero__context-row span {

    opacity: .72;
}

.status-hero__context-row strong {

    text-align: right;
}

/* ================================================================
   MAIN GRID
================================================================ */

.status-layout {

    display: grid;

    grid-template-columns:
        minmax(0,1fr)
        330px;

    gap: 1rem;

    align-items: start;
}

/* ================================================================
   STATUS FORM
================================================================ */

.status-form-card {

    padding: 1.2rem;

    border-radius: 18px;

    background:
        var(--sv-card-bg, #fff);

    border:
        1px solid
        var(--sv-border, #e5e7eb);
}

.status-form-card__header {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 1rem;

    margin-bottom: 1.15rem;
}

.status-form-card__header h3 {

    margin: 0;

    color:
        var(--sv-text, #111827);

    font-size: .9rem;
}

.status-form-card__header p {

    margin:
        .25rem 0 0;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .65rem;

    line-height: 1.5;
}

/* ================================================================
   CURRENT STATUS
================================================================ */

.current-status-box {

    margin-bottom: 1rem;

    padding: .9rem;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 1rem;

    border-radius: 13px;

    background:
        var(--sv-card-soft, #f8fafc);

    border:
        1px solid
        var(--sv-border, #e5e7eb);
}

.current-status-box span {

    color:
        var(--sv-text-soft, #64748b);

    font-size: .64rem;
}

/* ================================================================
   STATUS PILLS
================================================================ */

.status-pill {

    display: inline-flex;

    align-items: center;

    gap: .32rem;

    padding:
        .34rem .6rem;

    border-radius: 999px;

    font-size: .6rem;

    font-weight: 800;
}

.status-pill::before {

    content: '';

    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: currentColor;
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

.status-pill--default {

    background: #f1f5f9;
    color: #475569;
}

/* ================================================================
   STATUS OPTIONS
================================================================ */

.status-options {

    display: grid;

    gap: .8rem;
}

.status-option {

    position: relative;
}

.status-option input {

    position: absolute;

    opacity: 0;

    pointer-events: none;
}

.status-option label {

    min-height: 92px;

    padding: 1rem;

    display: flex;

    align-items: center;

    gap: .85rem;

    border:
        1px solid
        var(--sv-border, #e5e7eb);

    border-radius: 15px;

    background:
        var(--sv-card-bg, #fff);

    cursor: pointer;

    transition:
        transform .18s ease,
        border-color .18s ease,
        background .18s ease,
        box-shadow .18s ease;
}

.status-option label:hover {

    transform:
        translateY(-2px);

    border-color:
        rgba(37,99,235,.28);

    box-shadow:
        var(--sv-shadow);
}

.status-option input:checked + label {

    border-color:
        #2563eb;

    background:
        rgba(37,99,235,.055);

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.08);
}

.status-option__icon {

    width: 48px;
    height: 48px;

    flex: 0 0 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    font-size: .9rem;
}

.status-option--active
.status-option__icon {

    background:
        rgba(34,197,94,.10);

    color: #16a34a;
}

.status-option--completed
.status-option__icon {

    background:
        rgba(59,130,246,.10);

    color: #2563eb;
}

.status-option--withdrawn
.status-option__icon {

    background:
        rgba(239,68,68,.10);

    color: #dc2626;
}

.status-option__copy {

    flex: 1;
}

.status-option__copy strong {

    display: block;

    color:
        var(--sv-text, #111827);

    font-size: .73rem;
}

.status-option__copy span {

    display: block;

    margin-top: .2rem;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .61rem;

    line-height: 1.45;
}

.status-option__check {

    width: 22px;
    height: 22px;

    flex: 0 0 22px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    border:
        2px solid
        var(--sv-border, #e5e7eb);

    color: transparent;

    font-size: .55rem;
}

.status-option input:checked
+ label
.status-option__check {

    background: #2563eb;

    border-color: #2563eb;

    color: #fff;
}

/* ================================================================
   WARNING
================================================================ */

.status-warning {

    margin-top: 1rem;

    padding: .85rem;

    display: flex;

    align-items: flex-start;

    gap: .65rem;

    border-radius: 12px;

    background:
        rgba(245,158,11,.08);

    border:
        1px solid
        rgba(245,158,11,.18);
}

.status-warning__icon {

    width: 32px;
    height: 32px;

    flex: 0 0 32px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    background:
        rgba(245,158,11,.13);

    color: #d97706;
}

.status-warning strong {

    display: block;

    color:
        var(--sv-text, #111827);

    font-size: .65rem;
}

.status-warning p {

    margin:
        .2rem 0 0;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .6rem;

    line-height: 1.5;
}

/* ================================================================
   FORM ACTIONS
================================================================ */

.status-form-actions {

    margin-top: 1rem;

    padding-top: 1rem;

    display: flex;

    justify-content: flex-end;

    gap: .55rem;

    border-top:
        1px solid
        var(--sv-border, #e5e7eb);
}

.status-cancel-button,
.status-save-button {

    min-height: 42px;

    padding:
        .55rem .9rem;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: .4rem;

    border-radius: 11px;

    font-family: inherit;

    font-size: .68rem;

    font-weight: 700;
}

.status-cancel-button {

    border:
        1px solid
        var(--sv-border, #e5e7eb);

    background:
        var(--sv-card-bg, #fff);

    color:
        var(--sv-text-soft, #64748b);

    text-decoration: none;
}

.status-save-button {

    border: 0;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: #fff;

    cursor: pointer;

    box-shadow:
        0 7px 16px
        rgba(37,99,235,.18);
}

.status-save-button:disabled {

    cursor: not-allowed;

    opacity: .7;
}

/* ================================================================
   SUMMARY CARD
================================================================ */

.status-summary-card {

    padding: 1.15rem;

    border-radius: 18px;

    background:
        var(--sv-card-bg, #fff);

    border:
        1px solid
        var(--sv-border, #e5e7eb);
}

.status-summary-card h3 {

    margin:
        0 0 1rem;

    color:
        var(--sv-text, #111827);

    font-size: .82rem;
}

.status-summary-list {

    display: grid;

    gap: .75rem;
}

.status-summary-item {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 1rem;

    padding-bottom: .7rem;

    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);
}

.status-summary-item:last-child {

    border-bottom: 0;

    padding-bottom: 0;
}

.status-summary-item span {

    color:
        var(--sv-text-soft, #64748b);

    font-size: .61rem;
}

.status-summary-item strong {

    max-width: 60%;

    color:
        var(--sv-text, #111827);

    font-size: .64rem;

    text-align: right;
}

/* ================================================================
   TIMESTAMP CARD
================================================================ */

.status-timeline-card {

    margin-top: 1rem;

    padding: 1.15rem;

    border-radius: 18px;

    background:
        var(--sv-card-bg, #fff);

    border:
        1px solid
        var(--sv-border, #e5e7eb);
}

.status-timeline-card h3 {

    margin:
        0 0 .9rem;

    color:
        var(--sv-text, #111827);

    font-size: .82rem;
}

.status-timeline-item {

    position: relative;

    padding:
        0 0 1rem 1.65rem;
}

.status-timeline-item:last-child {

    padding-bottom: 0;
}

.status-timeline-item::before {

    content: '';

    width: 1px;

    position: absolute;

    left: 7px;

    top: 15px;

    bottom: -3px;

    background:
        var(--sv-border, #e5e7eb);
}

.status-timeline-item:last-child::before {

    display: none;
}

.status-timeline-dot {

    width: 15px;
    height: 15px;

    position: absolute;

    left: 0;
    top: 1px;

    border-radius: 50%;

    background:
        var(--sv-card-bg, #fff);

    border:
        3px solid
        #2563eb;
}

.status-timeline-item strong {

    display: block;

    color:
        var(--sv-text, #111827);

    font-size: .64rem;
}

.status-timeline-item span {

    display: block;

    margin-top: .18rem;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .59rem;
}

/* ================================================================
   SECURITY
================================================================ */

.status-security {

    grid-column:
        1 / -1;

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

.status-security__icon {

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

.status-security strong {

    display: block;

    color:
        var(--sv-text, #111827);

    font-size: .7rem;
}

.status-security p {

    margin:
        .2rem 0 0;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .64rem;

    line-height: 1.5;
}

/* ================================================================
   FLASH
================================================================ */

.status-flash {

    padding: .85rem 1rem;

    display: flex;

    align-items: center;

    gap: .65rem;

    border-radius: 13px;

    font-size: .68rem;

    font-weight: 600;
}

.status-flash--success {

    background:
        rgba(34,197,94,.10);

    border:
        1px solid
        rgba(34,197,94,.18);

    color: #15803d;
}

.status-flash--danger {

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.18);

    color: #b91c1c;
}

.status-flash--info {

    background:
        rgba(59,130,246,.09);

    border:
        1px solid
        rgba(59,130,246,.17);

    color: #1d4ed8;
}

/* ================================================================
   DARK MODE
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
.status-pill--default {

    background:
        rgba(148,163,184,.10);

    color: #cbd5e1;
}

html[data-theme="dark"]
.status-flash--success {

    color: #86efac;
}

html[data-theme="dark"]
.status-flash--danger {

    color: #fca5a5;
}

html[data-theme="dark"]
.status-flash--info {

    color: #93c5fd;
}

/* ================================================================
   RESPONSIVE
================================================================ */

@media(max-width:1000px) {

    .status-layout {

        grid-template-columns: 1fr;
    }

    .status-side-column {

        display: grid;

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );

        gap: 1rem;
    }

    .status-timeline-card {

        margin-top: 0;
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

    .status-page {

        padding: 1rem;
    }

    .status-hero {

        align-items: flex-start;

        flex-direction: column;
    }

    .status-hero__context {

        width: 100%;

        min-width: 0;
    }

    .status-side-column {

        grid-template-columns: 1fr;
    }

}

@media(max-width:500px) {

    .status-hero__candidate {

        align-items: flex-start;

        flex-direction: column;
    }

    .status-form-actions {

        flex-direction: column-reverse;
    }

    .status-cancel-button,
    .status-save-button {

        width: 100%;
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

        <div class="status-page">

            <!-- ====================================================
                 FLASH
            ===================================================== -->

            <?php if ($statusFlash): ?>

                <?php

                $flashType = (
                    in_array(
                        $statusFlash['type'],
                        [
                            'success',
                            'danger',
                            'info'
                        ],
                        true
                    )
                )
                    ? $statusFlash['type']
                    : 'info';

                ?>

                <div
                    class="
                        status-flash
                        status-flash--<?= e($flashType) ?>
                    "
                >

                    <?php if (
                        $flashType === 'success'
                    ): ?>

                        <i class="fas fa-circle-check"></i>

                    <?php elseif (
                        $flashType === 'danger'
                    ): ?>

                        <i class="fas fa-circle-exclamation"></i>

                    <?php else: ?>

                        <i class="fas fa-circle-info"></i>

                    <?php endif; ?>

                    <span>
                        <?= e(
                            $statusFlash['message']
                            ?? ''
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>

            <!-- ====================================================
                 BREADCRUMB
            ===================================================== -->

            <div class="status-breadcrumb">

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
                        'supervisor/candidate_view.php?id=' .
                        $candidateId .
                        '&cohort_id=' .
                        $cohortId
                    ) ?>"
                >
                    <?= e($candidateName) ?>
                </a>

                <i class="fas fa-chevron-right"></i>

                <span>
                    Update Status
                </span>

            </div>

            <!-- ====================================================
                 HERO
            ===================================================== -->

            <section class="status-hero">

                <div class="status-hero__candidate">

                    <div class="status-hero__avatar">

                        <?= e(
                            supervisorStatusInitials(
                                $candidateFirstName,
                                $candidateLastName,
                                'C'
                            )
                        ) ?>

                    </div>

                    <div>

                        <span class="status-hero__eyebrow">

                            <i class="fas fa-user-pen"></i>

                            Candidate Status Management

                        </span>

                        <h2>
                            <?= e($candidateName) ?>
                        </h2>

                        <div class="status-hero__email">

                            <i class="fas fa-envelope"></i>

                            <?= e(
                                $candidateEmail !== ''
                                    ? $candidateEmail
                                    : 'No email available'
                            ) ?>

                        </div>

                    </div>

                </div>

                <div class="status-hero__context">

                    <div class="status-hero__context-row">

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

                    <div class="status-hero__context-row">

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

                    <div class="status-hero__context-row">

                        <span>
                            Current Status
                        </span>

                        <strong>
                            <?= e(
                                supervisorStatusLabel(
                                    $currentStatus
                                )
                            ) ?>
                        </strong>

                    </div>

                </div>

            </section>

            <!-- ====================================================
                 MAIN LAYOUT
            ===================================================== -->

            <section class="status-layout">

                <!-- =================================================
                     STATUS FORM
                ================================================== -->

                <div class="status-form-card">

                    <div class="status-form-card__header">

                        <div>

                            <h3>
                                Change Participation Status
                            </h3>

                            <p>
                                Select the candidate's new status.
                                The update applies only to this
                                assigned cohort participation.
                            </p>

                        </div>

                    </div>

                    <!-- CURRENT STATUS -->

                    <div class="current-status-box">

                        <span>
                            Current participation status
                        </span>

                        <span
                            class="
                                status-pill
                                <?= supervisorStatusBadgeClass(
                                    $currentStatus
                                ) ?>
                            "
                        >

                            <?= e(
                                supervisorStatusLabel(
                                    $currentStatus
                                )
                            ) ?>

                        </span>

                    </div>

                    <!-- =================================================
                         FORM
                    ================================================== -->

                    <form
                        method="POST"
                        action=""
                        id="candidateStatusForm"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(
                                $csrfToken
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="candidate_id"
                            value="<?= $candidateId ?>"
                        >

                        <input
                            type="hidden"
                            name="cohort_id"
                            value="<?= $cohortId ?>"
                        >

                        <div class="status-options">

                            <!-- ACTIVE -->

                            <div
                                class="
                                    status-option
                                    status-option--active
                                "
                            >

                                <input
                                    type="radio"
                                    name="status"
                                    id="statusActive"
                                    value="active"
                                    <?= $currentStatus
                                        === 'active'
                                        ? 'checked'
                                        : '' ?>
                                    required
                                >

                                <label for="statusActive">

                                    <div class="status-option__icon">

                                        <i class="fas fa-user-check"></i>

                                    </div>

                                    <div class="status-option__copy">

                                        <strong>
                                            Active
                                        </strong>

                                        <span>
                                            The candidate is actively
                                            participating in the cohort.
                                        </span>

                                    </div>

                                    <div class="status-option__check">

                                        <i class="fas fa-check"></i>

                                    </div>

                                </label>

                            </div>

                            <!-- COMPLETED -->

                            <div
                                class="
                                    status-option
                                    status-option--completed
                                "
                            >

                                <input
                                    type="radio"
                                    name="status"
                                    id="statusCompleted"
                                    value="completed"
                                    <?= $currentStatus
                                        === 'completed'
                                        ? 'checked'
                                        : '' ?>
                                    required
                                >

                                <label for="statusCompleted">

                                    <div class="status-option__icon">

                                        <i class="fas fa-graduation-cap"></i>

                                    </div>

                                    <div class="status-option__copy">

                                        <strong>
                                            Completed
                                        </strong>

                                        <span>
                                            Mark the candidate as having
                                            successfully completed this
                                            cohort participation.
                                        </span>

                                    </div>

                                    <div class="status-option__check">

                                        <i class="fas fa-check"></i>

                                    </div>

                                </label>

                            </div>

                            <!-- WITHDRAWN -->

                            <div
                                class="
                                    status-option
                                    status-option--withdrawn
                                "
                            >

                                <input
                                    type="radio"
                                    name="status"
                                    id="statusWithdrawn"
                                    value="withdrawn"
                                    <?= $currentStatus
                                        === 'withdrawn'
                                        ? 'checked'
                                        : '' ?>
                                    required
                                >

                                <label for="statusWithdrawn">

                                    <div class="status-option__icon">

                                        <i class="fas fa-user-minus"></i>

                                    </div>

                                    <div class="status-option__copy">

                                        <strong>
                                            Withdrawn
                                        </strong>

                                        <span>
                                            The candidate will no longer
                                            count as a current participant
                                            in this cohort.
                                        </span>

                                    </div>

                                    <div class="status-option__check">

                                        <i class="fas fa-check"></i>

                                    </div>

                                </label>

                            </div>

                        </div>

                        <!-- WARNING -->

                        <div class="status-warning">

                            <div class="status-warning__icon">

                                <i class="fas fa-triangle-exclamation"></i>

                            </div>

                            <div>

                                <strong>
                                    Confirm the status carefully
                                </strong>

                                <p>
                                    Changing a candidate to Completed
                                    records the completion date. Changing
                                    away from Completed clears the current
                                    completion timestamp.
                                </p>

                            </div>

                        </div>

                        <!-- ACTIONS -->

                        <div class="status-form-actions">

                            <a
                                href="<?= url(
                                    'supervisor/candidate_view.php?id=' .
                                    $candidateId .
                                    '&cohort_id=' .
                                    $cohortId
                                ) ?>"
                                class="status-cancel-button"
                            >

                                <i class="fas fa-arrow-left"></i>

                                Cancel

                            </a>

                            <button
                                type="submit"
                                class="status-save-button"
                                id="saveStatusButton"
                            >

                                <i
                                    class="fas fa-floppy-disk"
                                    id="saveStatusIcon"
                                ></i>

                                <span id="saveStatusText">
                                    Save Status
                                </span>

                            </button>

                        </div>

                    </form>

                </div>

                <!-- =================================================
                     RIGHT COLUMN
                ================================================== -->

                <div class="status-side-column">

                    <!-- SUMMARY -->

                    <div class="status-summary-card">

                        <h3>
                            Participation Summary
                        </h3>

                        <div class="status-summary-list">

                            <div class="status-summary-item">

                                <span>
                                    Candidate
                                </span>

                                <strong>
                                    <?= e($candidateName) ?>
                                </strong>

                            </div>

                            <div class="status-summary-item">

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

                            <div class="status-summary-item">

                                <span>
                                    Programme Type
                                </span>

                                <strong>
                                    <?= e(
                                        supervisorStatusLabel(
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

                            <div class="status-summary-item">

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

                            <div class="status-summary-item">

                                <span>
                                    Cohort Status
                                </span>

                                <strong>
                                    <?= e(
                                        supervisorStatusLabel(
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

                            <div class="status-summary-item">

                                <span>
                                    Cohort Dates
                                </span>

                                <strong>

                                    <?= e(
                                        supervisorStatusDate(
                                            $candidate[
                                                'cohort_start_date'
                                            ]
                                            ?? null
                                        )
                                    ) ?>

                                    -

                                    <?= e(
                                        supervisorStatusDate(
                                            $candidate[
                                                'cohort_end_date'
                                            ]
                                            ?? null
                                        )
                                    ) ?>

                                </strong>

                            </div>

                        </div>

                    </div>

                    <!-- TIMELINE -->

                    <div class="status-timeline-card">

                        <h3>
                            Participation Timeline
                        </h3>

                        <div class="status-timeline-item">

                            <span class="status-timeline-dot"></span>

                            <strong>
                                Selected
                            </strong>

                            <span>
                                <?= e(
                                    supervisorStatusDateTime(
                                        $candidate[
                                            'selected_at'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </span>

                        </div>

                        <div class="status-timeline-item">

                            <span class="status-timeline-dot"></span>

                            <strong>
                                Onboarded
                            </strong>

                            <span>
                                <?= e(
                                    supervisorStatusDateTime(
                                        $candidate[
                                            'onboarded_at'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </span>

                        </div>

                        <div class="status-timeline-item">

                            <span class="status-timeline-dot"></span>

                            <strong>
                                Completed
                            </strong>

                            <span>
                                <?= e(
                                    supervisorStatusDateTime(
                                        $candidate[
                                            'completed_at'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </span>

                        </div>

                    </div>

                </div>

                <!-- =================================================
                     SECURITY
                ================================================== -->

                <div class="status-security">

                    <div class="status-security__icon">

                        <i class="fas fa-shield-halved"></i>

                    </div>

                    <div>

                        <strong>
                            Secure Supervisor update
                        </strong>

                        <p>
                            This status update is restricted to a
                            candidate in a cohort assigned to your
                            Supervisor account. The request is protected
                            by CSRF validation and the database update
                            independently checks Supervisor ownership
                            again before changing the participation
                            record.
                        </p>

                    </div>

                </div>

            </section>

        </div>

    </main>

</div>
</body>

</html>