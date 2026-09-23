<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('supervisor');

require_once __DIR__ . '/_helpers.php';

/*
|--------------------------------------------------------------------------
| Current User / Page
|--------------------------------------------------------------------------
*/

$user = current_user();
$flashes = render_flashes();

$currentPage = 'dashboard';
$pageTitle   = 'Dashboard';

$supervisorId = (int) (
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
| Logged-In Supervisor Name
|--------------------------------------------------------------------------
*/

$firstName = trim(
    (string) ($user['first_name'] ?? '')
);

$lastName = trim(
    (string) ($user['last_name'] ?? '')
);

$fullName = trim(
    $firstName . ' ' . $lastName
);

if ($fullName === '') {
    $fullName = trim(
        (string) (
            $user['full_name']
            ?? $user['username']
            ?? 'Supervisor'
        )
    );
}

if ($fullName === '') {
    $fullName = 'Supervisor';
}


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare(
    "
    SELECT
        COUNT(
            DISTINCT c.id
        ) AS assigned_cohorts,

        COUNT(
            DISTINCT CASE
                WHEN c.status = 'active'
                THEN c.id
            END
        ) AS active_cohorts,

        COUNT(
            DISTINCT CASE
                WHEN cp.status <> 'withdrawn'
                THEN cp.user_id
            END
        ) AS current_candidates,

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

    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id

    WHERE c.supervisor_id = ?
    ",
    'i',
    [$supervisorId]
);

$stats = $stmt->get_result()->fetch_assoc() ?: [];

$stmt->close();

$assigned = (int) (
    $stats['assigned_cohorts']
    ?? 0
);

$active = (int) (
    $stats['active_cohorts']
    ?? 0
);

$current = (int) (
    $stats['current_candidates']
    ?? 0
);

$completed = (int) (
    $stats['completed_candidates']
    ?? 0
);

$withdrawn = (int) (
    $stats['withdrawn_candidates']
    ?? 0
);

$rate = sv_completion_rate(
    $current,
    $completed
);


/*
|--------------------------------------------------------------------------
| Dashboard Cohorts
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare(
    "
    SELECT
        c.id,
        c.name AS cohort_name,
        c.status AS cohort_status,
        c.start_date,
        c.end_date,

        p.name AS programme_name,

        COUNT(
            DISTINCT CASE
                WHEN cp.status <> 'withdrawn'
                THEN cp.user_id
            END
        ) AS current_candidates,

        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'completed'
                THEN cp.user_id
            END
        ) AS completed_candidates

    FROM cohorts c

    INNER JOIN programmes p
        ON p.id = c.programme_id

    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id

    WHERE c.supervisor_id = ?

    GROUP BY
        c.id,
        c.name,
        c.status,
        c.start_date,
        c.end_date,
        p.name

    ORDER BY
        CASE
            WHEN c.status = 'active'
            THEN 0
            ELSE 1
        END,
        c.start_date DESC

    LIMIT 5
    ",
    'i',
    [$supervisorId]
);

$cohorts = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $row['rate'] = sv_completion_rate(
        (int) $row['current_candidates'],
        (int) $row['completed_candidates']
    );

    $cohorts[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Recent Candidates
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare(
    "
    SELECT
        u.id AS candidate_id,
        u.first_name,
        u.last_name,
        u.email,

        cp.status AS participant_status,

        c.id AS cohort_id,
        c.name AS cohort_name,

        p.name AS programme_name

    FROM cohort_participants cp

    INNER JOIN cohorts c
        ON c.id = cp.cohort_id

    INNER JOIN programmes p
        ON p.id = c.programme_id

    INNER JOIN users u
        ON u.id = cp.user_id

    WHERE c.supervisor_id = ?

    ORDER BY
        COALESCE(
            cp.completed_at,
            cp.onboarded_at,
            cp.selected_at
        ) DESC,
        cp.id DESC

    LIMIT 6
    ",
    'i',
    [$supervisorId]
);

$candidates = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Modal Data - Assigned Cohorts
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare(
    "
    SELECT
        c.id,
        c.name AS cohort_name,
        c.status AS cohort_status,
        c.start_date,
        c.end_date,

        p.name AS programme_name,

        COUNT(
            DISTINCT CASE
                WHEN cp.status <> 'withdrawn'
                THEN cp.user_id
            END
        ) AS candidates

    FROM cohorts c

    INNER JOIN programmes p
        ON p.id = c.programme_id

    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id

    WHERE c.supervisor_id = ?

    GROUP BY
        c.id,
        c.name,
        c.status,
        c.start_date,
        c.end_date,
        p.name

    ORDER BY
        CASE
            WHEN c.status = 'active'
            THEN 0
            ELSE 1
        END,
        c.start_date DESC,
        c.id DESC
    ",
    'i',
    [$supervisorId]
);

$modalAssignedCohorts = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $modalAssignedCohorts[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Modal Data - Active Cohorts
|--------------------------------------------------------------------------
*/

$modalActiveCohorts = array_values(
    array_filter(
        $modalAssignedCohorts,
        static fn(array $cohort): bool =>
            ($cohort['cohort_status'] ?? '') === 'active'
    )
);


/*
|--------------------------------------------------------------------------
| Modal Data - Candidates
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare(
    "
    SELECT
        cp.id AS participant_id,
        cp.status AS participant_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,

        u.id AS candidate_id,
        u.first_name,
        u.last_name,
        u.email,

        c.id AS cohort_id,
        c.name AS cohort_name,

        p.id AS programme_id,
        p.name AS programme_name

    FROM cohort_participants cp

    INNER JOIN cohorts c
        ON c.id = cp.cohort_id

    INNER JOIN programmes p
        ON p.id = c.programme_id

    INNER JOIN users u
        ON u.id = cp.user_id

    WHERE c.supervisor_id = ?

    ORDER BY
        COALESCE(
            cp.completed_at,
            cp.onboarded_at,
            cp.selected_at
        ) DESC,
        cp.id DESC
    ",
    'i',
    [$supervisorId]
);

$modalAllParticipants = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $modalAllParticipants[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Split Candidate Modal Data
|--------------------------------------------------------------------------
*/

$modalCurrentCandidates = [];
$modalCompletedCandidates = [];
$modalWithdrawnCandidates = [];

$currentSeen = [];
$completedSeen = [];
$withdrawnSeen = [];

foreach ($modalAllParticipants as $participant) {

    $candidateId = (int) (
        $participant['candidate_id']
        ?? 0
    );

    $status = (string) (
        $participant['participant_status']
        ?? ''
    );

    if (
        $status !== 'withdrawn'
        && $candidateId > 0
        && !isset($currentSeen[$candidateId])
    ) {
        $modalCurrentCandidates[] = $participant;
        $currentSeen[$candidateId] = true;
    }

    if (
        $status === 'completed'
        && $candidateId > 0
        && !isset($completedSeen[$candidateId])
    ) {
        $modalCompletedCandidates[] = $participant;
        $completedSeen[$candidateId] = true;
    }

    if (
        $status === 'withdrawn'
        && $candidateId > 0
        && !isset($withdrawnSeen[$candidateId])
    ) {
        $modalWithdrawnCandidates[] = $participant;
        $withdrawnSeen[$candidateId] = true;
    }
}


/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>

<style>

/* =========================================================
   CLICKABLE DASHBOARD CARDS
========================================================= */

.sv-stat--interactive {
    position: relative;
    cursor: pointer;

    transition:
        transform .18s ease,
        box-shadow .18s ease,
        border-color .18s ease;
}

.sv-stat--interactive:hover {
    transform: translateY(-3px);
}

.sv-stat--interactive:focus-visible {
    outline: 3px solid rgba(37, 99, 235, .22);
    outline-offset: 3px;
}

.sv-stat__view {
    display: block;

    margin-top: 8px;

    font-size: 10px;
    font-weight: 700;

    opacity: .7;
}

.sv-stat--interactive:hover .sv-stat__view {
    opacity: 1;
}


/* =========================================================
   SUPERVISOR DASHBOARD MODAL
========================================================= */

.sv-dashboard-modal {
    display: none;

    position: fixed;
    inset: 0;

    z-index: 6000;

    align-items: center;
    justify-content: center;

    padding: 22px;
}

.sv-dashboard-modal.is-open {
    display: flex;
}

.sv-dashboard-modal__backdrop {
    position: absolute;
    inset: 0;

    background: rgba(15, 23, 42, .62);

    backdrop-filter: blur(5px);
}

.sv-dashboard-modal__dialog {
    width: min(760px, 100%);
    max-height: min(
        760px,
        calc(100vh - 44px)
    );

    position: relative;
    z-index: 1;

    display: flex;
    flex-direction: column;

    border: 1px solid var(
        --sv-border,
        #e4e7ec
    );

    border-radius: 20px;

    background: var(
        --sv-surface,
        #ffffff
    );

    color: var(
        --sv-text,
        #101828
    );

    box-shadow:
        0 30px 90px
        rgba(15, 23, 42, .30);

    overflow: hidden;
}

.sv-dashboard-modal__header {
    padding: 20px 22px;

    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    border-bottom: 1px solid var(
        --sv-border,
        #e4e7ec
    );
}

.sv-dashboard-modal__eyebrow {
    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 11px;
    font-weight: 800;

    text-transform: uppercase;
    letter-spacing: .08em;
}

.sv-dashboard-modal__header h3 {
    margin: 0;

    font-size: 20px;
    font-weight: 800;
}

.sv-dashboard-modal__header p {
    margin: 5px 0 0;

    color: var(
        --sv-muted,
        #667085
    );

    font-size: 12px;
}

.sv-dashboard-modal__close {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: grid;
    place-items: center;

    border: 1px solid var(
        --sv-border,
        #e4e7ec
    );

    border-radius: 10px;

    background: var(
        --sv-soft,
        #f8fafc
    );

    color: inherit;

    cursor: pointer;
}

.sv-dashboard-modal__body {
    padding: 10px;

    overflow-y: auto;
}

.sv-dashboard-modal__panel {
    display: none;
}

.sv-dashboard-modal__panel.is-active {
    display: block;
}


/* =========================================================
   MODAL ROWS
========================================================= */

.sv-dashboard-modal__row {
    padding: 14px;

    display: flex;
    align-items: center;

    gap: 13px;

    border-bottom: 1px solid var(
        --sv-border,
        #e4e7ec
    );

    border-radius: 11px;

    color: inherit;

    text-decoration: none;

    transition: background .15s ease;
}

.sv-dashboard-modal__row:last-child {
    border-bottom: 0;
}

.sv-dashboard-modal__row:hover {
    background: var(
        --sv-soft,
        #f8fafc
    );
}

.sv-dashboard-modal__row-icon {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border-radius: 12px;

    background: rgba(
        37,
        99,
        235,
        .10
    );

    color: #2563eb;
}

.sv-dashboard-modal__row-main {
    min-width: 0;
    flex: 1;
}

.sv-dashboard-modal__row-main strong {
    display: block;

    overflow: hidden;

    font-size: 12px;
    font-weight: 800;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.sv-dashboard-modal__row-main span {
    display: block;

    margin-top: 4px;

    color: var(
        --sv-muted,
        #667085
    );

    font-size: 10px;
}

.sv-dashboard-modal__arrow {
    color: var(
        --sv-muted,
        #667085
    );
}


/* =========================================================
   MODAL EMPTY STATE
========================================================= */

.sv-dashboard-modal__empty {
    padding: 48px 20px;

    text-align: center;
}

.sv-dashboard-modal__empty i {
    margin-bottom: 14px;

    color: var(
        --sv-muted,
        #667085
    );

    font-size: 30px;
}

.sv-dashboard-modal__empty strong {
    display: block;

    font-size: 14px;
}

.sv-dashboard-modal__empty span {
    display: block;

    margin-top: 5px;

    color: var(
        --sv-muted,
        #667085
    );

    font-size: 11px;
}


/* =========================================================
   MODAL FOOTER
========================================================= */

.sv-dashboard-modal__footer {
    padding: 14px 20px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    border-top: 1px solid var(
        --sv-border,
        #e4e7ec
    );
}

.sv-dashboard-modal__footer button {
    padding: 9px 15px;

    border: 1px solid var(
        --sv-border,
        #e4e7ec
    );

    border-radius: 9px;

    background: transparent;

    color: inherit;

    font: inherit;
    font-size: 11px;
    font-weight: 700;

    cursor: pointer;
}

.sv-dashboard-modal__view-all {
    padding: 9px 15px;

    border-radius: 9px;

    background: #2563eb;

    color: #ffffff !important;

    font-size: 11px;
    font-weight: 800;

    text-decoration: none;
}

body.sv-modal-open {
    overflow: hidden;
}


/* =========================================================
   DARK MODE
========================================================= */

html[data-theme="dark"]
.sv-dashboard-modal__dialog {
    background: var(
        --sv-surface,
        #101828
    );

    color: var(
        --sv-text,
        #f8fafc
    );

    border-color: rgba(
        255,
        255,
        255,
        .09
    );
}

html[data-theme="dark"]
.sv-dashboard-modal__header,

html[data-theme="dark"]
.sv-dashboard-modal__footer,

html[data-theme="dark"]
.sv-dashboard-modal__row {
    border-color: rgba(
        255,
        255,
        255,
        .09
    );
}

html[data-theme="dark"]
.sv-dashboard-modal__row:hover,

html[data-theme="dark"]
.sv-dashboard-modal__close {
    background: rgba(
        255,
        255,
        255,
        .05
    );
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 620px) {

    .sv-dashboard-modal {
        padding: 12px;
    }

    .sv-dashboard-modal__dialog {
        max-height:
            calc(100vh - 24px);

        border-radius: 16px;
    }

    .sv-dashboard-modal__header {
        padding: 16px;
    }

    .sv-dashboard-modal__header h3 {
        font-size: 17px;
    }

    .sv-dashboard-modal__footer {
        padding: 12px;

        flex-direction: column-reverse;
    }

    .sv-dashboard-modal__footer button,
    .sv-dashboard-modal__view-all {
        width: 100%;

        text-align: center;
    }
}

</style>


<!-- =========================================================
     HERO
========================================================= -->

<section class="sv-hero">

    <div class="sv-hero__content">

        <span class="sv-hero__eyebrow">

            <i class="fas fa-sparkles"></i>

            Supervisor Workspace

        </span>

        <h2>
            Welcome back, <?= e($fullName) ?>.
        </h2>

        <p>
            Track your assigned cohorts, monitor candidate progress
            and keep programme delivery moving with a clear
            operational view.
        </p>

    </div>


    <div class="sv-hero__metric">

        <strong>
            <?= (int) $rate ?>%
        </strong>

        <span>
            Overall completion
        </span>

    </div>

</section>


<!-- =========================================================
     DASHBOARD CARDS
========================================================= -->

<section class="sv-stats sv-stats--5">


    <!-- ASSIGNED COHORTS -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-sv-modal="assigned"
        aria-label="View assigned cohorts"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--blue
            "
        >
            <i class="fas fa-layer-group"></i>
        </div>

        <strong>
            <?= number_format($assigned) ?>
        </strong>

        <span>
            Assigned Cohorts
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>


    <!-- ACTIVE COHORTS -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-sv-modal="active"
        aria-label="View active cohorts"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--purple
            "
        >
            <i class="fas fa-bolt"></i>
        </div>

        <strong>
            <?= number_format($active) ?>
        </strong>

        <span>
            Active Cohorts
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>


    <!-- CURRENT CANDIDATES -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-sv-modal="current"
        aria-label="View current candidates"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--orange
            "
        >
            <i class="fas fa-users"></i>
        </div>

        <strong>
            <?= number_format($current) ?>
        </strong>

        <span>
            Current Candidates
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>


    <!-- COMPLETED -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-sv-modal="completed"
        aria-label="View completed candidates"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--green
            "
        >
            <i class="fas fa-circle-check"></i>
        </div>

        <strong>
            <?= number_format($completed) ?>
        </strong>

        <span>
            Completed
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>


    <!-- WITHDRAWN -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-sv-modal="withdrawn"
        aria-label="View withdrawn candidates"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--red
            "
        >
            <i class="fas fa-user-minus"></i>
        </div>

        <strong>
            <?= number_format($withdrawn) ?>
        </strong>

        <span>
            Withdrawn
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>

</section>


<!-- =========================================================
     MAIN GRID
========================================================= -->

<div class="sv-grid sv-grid--main">


    <!-- =====================================================
         MY COHORTS
    ====================================================== -->

    <section class="sv-card">

        <div class="sv-card__header">

            <div>

                <h3>
                    My Cohorts
                </h3>

                <p>
                    Your most relevant assigned cohorts.
                </p>

            </div>


            <a
                class="sv-card__link"
                href="<?= url(
                    'supervisor/cohorts.php'
                ) ?>"
            >
                View all

                <i class="fas fa-arrow-right"></i>
            </a>

        </div>


        <?php if (!$cohorts): ?>

            <div class="sv-empty">

                <i class="fas fa-layer-group"></i>

                <strong>
                    No cohorts assigned
                </strong>

                <span>
                    Your assigned cohorts will appear here.
                </span>

            </div>

        <?php else: ?>

            <div class="sv-table-wrap">

                <table class="sv-table">

                    <thead>

                        <tr>
                            <th>Cohort</th>
                            <th>Status</th>
                            <th>Candidates</th>
                            <th>Progress</th>
                            <th></th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($cohorts as $cohort): ?>

                        <tr>

                            <td>

                                <div class="sv-person">

                                    <div class="sv-avatar">

                                        <i class="fas fa-layer-group"></i>

                                    </div>

                                    <div class="sv-person__copy">

                                        <strong>
                                            <?= e(
                                                $cohort['cohort_name']
                                            ) ?>
                                        </strong>

                                        <span>
                                            <?= e(
                                                $cohort['programme_name']
                                            ) ?>
                                        </span>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="
                                        sv-status
                                        sv-status--<?= e(
                                            sv_status_class(
                                                $cohort['cohort_status']
                                            )
                                        ) ?>
                                    "
                                >
                                    <?= e(
                                        sv_status_label(
                                            $cohort['cohort_status']
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td>
                                <?= number_format(
                                    (int) $cohort[
                                        'current_candidates'
                                    ]
                                ) ?>
                            </td>


                            <td style="min-width:140px">

                                <div class="sv-progress">

                                    <div class="sv-progress__meta">

                                        <span>
                                            Completed
                                        </span>

                                        <strong>
                                            <?= (int) $cohort['rate'] ?>%
                                        </strong>

                                    </div>

                                    <div class="sv-progress__track">

                                        <div
                                            class="sv-progress__bar"
                                            style="width:<?= (int) $cohort['rate'] ?>%"
                                        ></div>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <a
                                    class="sv-icon-btn"
                                    href="<?= url(
                                        'supervisor/cohort_view.php?id='
                                        . (int) $cohort['id']
                                    ) ?>"
                                    title="View cohort"
                                >
                                    <i class="fas fa-arrow-right"></i>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


    <!-- =====================================================
         QUICK ACTIONS
    ====================================================== -->

    <section class="sv-card">

        <div class="sv-card__header">

            <div>

                <h3>
                    Quick Actions
                </h3>

                <p>
                    Common Supervisor tasks.
                </p>

            </div>

        </div>


        <div
            class="sv-card__body"
            style="display:grid;gap:10px"
        >

            <a
                class="sv-btn sv-btn--secondary"
                href="<?= url(
                    'supervisor/cohorts.php'
                ) ?>"
            >
                <i class="fas fa-layer-group"></i>
                Review My Cohorts
            </a>


            <a
                class="sv-btn sv-btn--secondary"
                href="<?= url(
                    'supervisor/candidates.php'
                ) ?>"
            >
                <i class="fas fa-users"></i>
                Review Candidates
            </a>


            <a
                class="sv-btn sv-btn--secondary"
                href="<?= url(
                    'supervisor/reports.php'
                ) ?>"
            >
                <i class="fas fa-chart-column"></i>
                View Progress Report
            </a>


            <a
                class="sv-btn sv-btn--secondary"
                href="<?= url(
                    'supervisor/activity_log.php'
                ) ?>"
            >
                <i class="fas fa-clock-rotate-left"></i>
                Activity Log
            </a>

        </div>

    </section>

</div>


<!-- =========================================================
     RECENT CANDIDATES
========================================================= -->

<section
    class="sv-card"
    style="margin-top:18px"
>

    <div class="sv-card__header">

        <div>

            <h3>
                Recent Candidates
            </h3>

            <p>
                Recent participants across your assigned cohorts.
            </p>

        </div>


        <a
            class="sv-card__link"
            href="<?= url(
                'supervisor/candidates.php'
            ) ?>"
        >
            View candidates

            <i class="fas fa-arrow-right"></i>
        </a>

    </div>


    <?php if (!$candidates): ?>

        <div class="sv-empty">

            <i class="fas fa-users"></i>

            <strong>
                No candidates found
            </strong>

            <span>
                Candidate activity will appear here.
            </span>

        </div>

    <?php else: ?>

        <div class="sv-table-wrap">

            <table class="sv-table">

                <thead>

                    <tr>
                        <th>Candidate</th>
                        <th>Programme</th>
                        <th>Cohort</th>
                        <th>Status</th>
                        <th></th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($candidates as $candidate): ?>

                    <?php

                    $candidateFirstName = trim(
                        (string) (
                            $candidate['first_name']
                            ?? ''
                        )
                    );

                    $candidateLastName = trim(
                        (string) (
                            $candidate['last_name']
                            ?? ''
                        )
                    );

                    $candidateName = trim(
                        $candidateFirstName
                        . ' '
                        . $candidateLastName
                    );

                    if ($candidateName === '') {
                        $candidateName = 'Candidate';
                    }

                    ?>

                    <tr>

                        <td>

                            <div class="sv-person">

                                <div class="sv-avatar">

                                    <?= e(
                                        sv_initials(
                                            $candidateFirstName,
                                            $candidateLastName
                                        )
                                    ) ?>

                                </div>


                                <div class="sv-person__copy">

                                    <strong>
                                        <?= e($candidateName) ?>
                                    </strong>

                                    <span>
                                        <?= e(
                                            (string) (
                                                $candidate['email']
                                                ?? ''
                                            )
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        </td>


                        <td>
                            <?= e(
                                $candidate['programme_name']
                            ) ?>
                        </td>


                        <td>
                            <?= e(
                                $candidate['cohort_name']
                            ) ?>
                        </td>


                        <td>

                            <span
                                class="
                                    sv-status
                                    sv-status--<?= e(
                                        sv_status_class(
                                            $candidate[
                                                'participant_status'
                                            ]
                                        )
                                    ) ?>
                                "
                            >
                                <?= e(
                                    sv_status_label(
                                        $candidate[
                                            'participant_status'
                                        ]
                                    )
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <a
                                class="sv-icon-btn"
                                href="<?= url(
                                    'supervisor/candidate_view.php?id='
                                    . (int) $candidate['candidate_id']
                                    . '&cohort_id='
                                    . (int) $candidate['cohort_id']
                                ) ?>"
                                title="View candidate"
                            >
                                <i class="fas fa-eye"></i>
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<!-- =========================================================
     DASHBOARD CARD MODAL
========================================================= -->

<div
    class="sv-dashboard-modal"
    id="svDashboardModal"
    aria-hidden="true"
>

    <div
        class="sv-dashboard-modal__backdrop"
        data-sv-modal-close
    ></div>


    <div
        class="sv-dashboard-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="svDashboardModalTitle"
    >

        <!-- HEADER -->

        <div class="sv-dashboard-modal__header">

            <div>

                <span
                    class="sv-dashboard-modal__eyebrow"
                    id="svDashboardModalEyebrow"
                >
                    Supervisor Dashboard
                </span>

                <h3 id="svDashboardModalTitle">
                    Dashboard Details
                </h3>

                <p id="svDashboardModalSubtitle">
                    Detailed information from your assigned cohorts.
                </p>

            </div>


            <button
                type="button"
                class="sv-dashboard-modal__close"
                data-sv-modal-close
                aria-label="Close"
            >
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        <!-- BODY -->

        <div class="sv-dashboard-modal__body">


            <!-- =================================================
                 ASSIGNED COHORTS
            ================================================== -->

            <div
                class="sv-dashboard-modal__panel"
                data-sv-panel="assigned"
            >

                <?php if (!$modalAssignedCohorts): ?>

                    <div class="sv-dashboard-modal__empty">

                        <i class="fas fa-layer-group"></i>

                        <strong>
                            No cohorts assigned
                        </strong>

                        <span>
                            Your assigned cohorts will appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalAssignedCohorts as $cohort
                    ): ?>

                        <a
                            class="sv-dashboard-modal__row"
                            href="<?= url(
                                'supervisor/cohort_view.php?id='
                                . (int) $cohort['id']
                            ) ?>"
                        >

                            <span
                                class="sv-dashboard-modal__row-icon"
                            >
                                <i class="fas fa-layer-group"></i>
                            </span>


                            <span
                                class="sv-dashboard-modal__row-main"
                            >

                                <strong>
                                    <?= e(
                                        $cohort['cohort_name']
                                    ) ?>
                                </strong>

                                <span>

                                    <?= e(
                                        $cohort['programme_name']
                                    ) ?>

                                    ·

                                    <?= number_format(
                                        (int) $cohort['candidates']
                                    ) ?>

                                    candidates

                                    ·

                                    <?= e(
                                        sv_status_label(
                                            $cohort[
                                                'cohort_status'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-dashboard-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 ACTIVE COHORTS
            ================================================== -->

            <div
                class="sv-dashboard-modal__panel"
                data-sv-panel="active"
            >

                <?php if (!$modalActiveCohorts): ?>

                    <div class="sv-dashboard-modal__empty">

                        <i class="fas fa-bolt"></i>

                        <strong>
                            No active cohorts
                        </strong>

                        <span>
                            You currently have no active cohorts.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalActiveCohorts as $cohort
                    ): ?>

                        <a
                            class="sv-dashboard-modal__row"
                            href="<?= url(
                                'supervisor/cohort_view.php?id='
                                . (int) $cohort['id']
                            ) ?>"
                        >

                            <span
                                class="sv-dashboard-modal__row-icon"
                            >
                                <i class="fas fa-bolt"></i>
                            </span>


                            <span
                                class="sv-dashboard-modal__row-main"
                            >

                                <strong>
                                    <?= e(
                                        $cohort['cohort_name']
                                    ) ?>
                                </strong>

                                <span>

                                    <?= e(
                                        $cohort['programme_name']
                                    ) ?>

                                    ·

                                    <?= number_format(
                                        (int) $cohort['candidates']
                                    ) ?>

                                    candidates

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-dashboard-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 CURRENT CANDIDATES
            ================================================== -->

            <div
                class="sv-dashboard-modal__panel"
                data-sv-panel="current"
            >

                <?php if (!$modalCurrentCandidates): ?>

                    <div class="sv-dashboard-modal__empty">

                        <i class="fas fa-users"></i>

                        <strong>
                            No current candidates
                        </strong>

                        <span>
                            Current candidates will appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalCurrentCandidates as $candidate
                    ): ?>

                        <?php

                        $fn = trim(
                            (string) (
                                $candidate['first_name']
                                ?? ''
                            )
                        );

                        $ln = trim(
                            (string) (
                                $candidate['last_name']
                                ?? ''
                            )
                        );

                        $name = trim(
                            $fn . ' ' . $ln
                        );

                        if ($name === '') {
                            $name = 'Candidate';
                        }

                        ?>

                        <a
                            class="sv-dashboard-modal__row"
                            href="<?= url(
                                'supervisor/candidate_view.php?id='
                                . (int) $candidate['candidate_id']
                                . '&cohort_id='
                                . (int) $candidate['cohort_id']
                            ) ?>"
                        >

                            <span
                                class="sv-dashboard-modal__row-icon"
                            >
                                <?= e(
                                    sv_initials(
                                        $fn,
                                        $ln
                                    )
                                ) ?>
                            </span>


                            <span
                                class="sv-dashboard-modal__row-main"
                            >

                                <strong>
                                    <?= e($name) ?>
                                </strong>

                                <span>

                                    <?= e(
                                        $candidate[
                                            'programme_name'
                                        ]
                                    ) ?>

                                    ·

                                    <?= e(
                                        $candidate[
                                            'cohort_name'
                                        ]
                                    ) ?>

                                    ·

                                    <?= e(
                                        sv_status_label(
                                            $candidate[
                                                'participant_status'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-dashboard-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 COMPLETED CANDIDATES
            ================================================== -->

            <div
                class="sv-dashboard-modal__panel"
                data-sv-panel="completed"
            >

                <?php if (!$modalCompletedCandidates): ?>

                    <div class="sv-dashboard-modal__empty">

                        <i class="fas fa-circle-check"></i>

                        <strong>
                            No completed candidates
                        </strong>

                        <span>
                            Completed candidates will appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalCompletedCandidates as $candidate
                    ): ?>

                        <?php

                        $fn = trim(
                            (string) (
                                $candidate['first_name']
                                ?? ''
                            )
                        );

                        $ln = trim(
                            (string) (
                                $candidate['last_name']
                                ?? ''
                            )
                        );

                        $name = trim(
                            $fn . ' ' . $ln
                        );

                        if ($name === '') {
                            $name = 'Candidate';
                        }

                        ?>

                        <a
                            class="sv-dashboard-modal__row"
                            href="<?= url(
                                'supervisor/candidate_view.php?id='
                                . (int) $candidate['candidate_id']
                                . '&cohort_id='
                                . (int) $candidate['cohort_id']
                            ) ?>"
                        >

                            <span
                                class="sv-dashboard-modal__row-icon"
                            >
                                <i class="fas fa-circle-check"></i>
                            </span>


                            <span
                                class="sv-dashboard-modal__row-main"
                            >

                                <strong>
                                    <?= e($name) ?>
                                </strong>

                                <span>

                                    <?= e(
                                        $candidate[
                                            'programme_name'
                                        ]
                                    ) ?>

                                    ·

                                    <?= e(
                                        $candidate[
                                            'cohort_name'
                                        ]
                                    ) ?>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-dashboard-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 WITHDRAWN CANDIDATES
            ================================================== -->

            <div
                class="sv-dashboard-modal__panel"
                data-sv-panel="withdrawn"
            >

                <?php if (!$modalWithdrawnCandidates): ?>

                    <div class="sv-dashboard-modal__empty">

                        <i class="fas fa-user-minus"></i>

                        <strong>
                            No withdrawn candidates
                        </strong>

                        <span>
                            Withdrawn candidates will appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalWithdrawnCandidates as $candidate
                    ): ?>

                        <?php

                        $fn = trim(
                            (string) (
                                $candidate['first_name']
                                ?? ''
                            )
                        );

                        $ln = trim(
                            (string) (
                                $candidate['last_name']
                                ?? ''
                            )
                        );

                        $name = trim(
                            $fn . ' ' . $ln
                        );

                        if ($name === '') {
                            $name = 'Candidate';
                        }

                        ?>

                        <a
                            class="sv-dashboard-modal__row"
                            href="<?= url(
                                'supervisor/candidate_view.php?id='
                                . (int) $candidate['candidate_id']
                                . '&cohort_id='
                                . (int) $candidate['cohort_id']
                            ) ?>"
                        >

                            <span
                                class="sv-dashboard-modal__row-icon"
                            >
                                <i class="fas fa-user-minus"></i>
                            </span>


                            <span
                                class="sv-dashboard-modal__row-main"
                            >

                                <strong>
                                    <?= e($name) ?>
                                </strong>

                                <span>

                                    <?= e(
                                        $candidate[
                                            'programme_name'
                                        ]
                                    ) ?>

                                    ·

                                    <?= e(
                                        $candidate[
                                            'cohort_name'
                                        ]
                                    ) ?>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-dashboard-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="sv-dashboard-modal__footer">

            <button
                type="button"
                data-sv-modal-close
            >
                Close
            </button>


            <a
                href="<?= url(
                    'supervisor/cohorts.php'
                ) ?>"
                class="sv-dashboard-modal__view-all"
                id="svDashboardModalViewAll"
            >
                View All
            </a>

        </div>

    </div>

</div>


<!-- =========================================================
     SECURITY INFORMATION
========================================================= -->

<div class="sv-security">

    <i class="fas fa-shield-halved"></i>

    <div>

        <strong>
            Supervisor-scoped access
        </strong>

        <p>
            All dashboard data is restricted to cohorts assigned
            to your Supervisor account and their participants.
        </p>

    </div>

</div>


<!-- =========================================================
     DASHBOARD MODAL JAVASCRIPT
========================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const modal =
        document.getElementById(
            'svDashboardModal'
        );

    if (!modal) {
        return;
    }


    const title =
        document.getElementById(
            'svDashboardModalTitle'
        );

    const subtitle =
        document.getElementById(
            'svDashboardModalSubtitle'
        );

    const viewAll =
        document.getElementById(
            'svDashboardModalViewAll'
        );

    const cards =
        document.querySelectorAll(
            '[data-sv-modal]'
        );

    const panels =
        modal.querySelectorAll(
            '[data-sv-panel]'
        );

    const closeButtons =
        modal.querySelectorAll(
            '[data-sv-modal-close]'
        );


    /*
    |--------------------------------------------------------------------------
    | Modal Configuration
    |--------------------------------------------------------------------------
    */

    const config = {

        assigned: {
            title: 'Assigned Cohorts',
            subtitle:
                'All cohorts currently assigned to your Supervisor account.',
            url:
                '<?= e(
                    url('supervisor/cohorts.php')
                ) ?>'
        },

        active: {
            title: 'Active Cohorts',
            subtitle:
                'Cohorts currently marked as active within your assignments.',
            url:
                '<?= e(
                    url('supervisor/cohorts.php?status=active')
                ) ?>'
        },

        current: {
            title: 'Current Candidates',
            subtitle:
                'Candidates currently participating across your assigned cohorts.',
            url:
                '<?= e(
                    url('supervisor/candidates.php')
                ) ?>'
        },

        completed: {
            title: 'Completed Candidates',
            subtitle:
                'Candidates recorded as completed across your assigned cohorts.',
            url:
                '<?= e(
                    url('supervisor/candidates.php?status=completed')
                ) ?>'
        },

        withdrawn: {
            title: 'Withdrawn Candidates',
            subtitle:
                'Candidates recorded as withdrawn from your assigned cohorts.',
            url:
                '<?= e(
                    url('supervisor/candidates.php?status=withdrawn')
                ) ?>'
        }

    };


    let lastTrigger = null;


    /*
    |--------------------------------------------------------------------------
    | Open Modal
    |--------------------------------------------------------------------------
    */

    function openModal(type, trigger) {

        const item = config[type];

        if (!item) {
            return;
        }


        lastTrigger = trigger || null;


        panels.forEach(function (panel) {

            panel.classList.toggle(
                'is-active',
                panel.dataset.svPanel === type
            );

        });


        title.textContent =
            item.title;

        subtitle.textContent =
            item.subtitle;

        viewAll.href =
            item.url;


        modal.classList.add(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'sv-modal-open'
        );


        const closeButton =
            modal.querySelector(
                '.sv-dashboard-modal__close'
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
            'sv-modal-open'
        );


        panels.forEach(function (panel) {
            panel.classList.remove(
                'is-active'
            );
        });


        if (
            lastTrigger
            && typeof lastTrigger.focus
                === 'function'
        ) {
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
                    card.dataset.svModal,
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
                    event.key === 'Enter'
                    || event.key === ' '
                ) {

                    event.preventDefault();

                    openModal(
                        card.dataset.svModal,
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
    | Escape Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape'
                && modal.classList.contains(
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