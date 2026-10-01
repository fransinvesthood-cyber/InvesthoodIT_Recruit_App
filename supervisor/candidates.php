<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
require_once __DIR__ . '/_helpers.php';

/*
|--------------------------------------------------------------------------
| User / Page
|--------------------------------------------------------------------------
*/

$user = current_user();
$flashes = render_flashes();

$currentPage = 'candidates';
$pageTitle = 'Candidates';

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
| Filters
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['search'] ?? '')
);

$status = trim(
    (string) ($_GET['status'] ?? '')
);

$cohortId = (int) (
    $_GET['cohort_id']
    ?? 0
);

$allowedStatuses = [
    'active',
    'completed',
    'withdrawn'
];

if (
    $status !== ''
    && !in_array($status, $allowedStatuses, true)
) {
    $status = '';
}


/*
|--------------------------------------------------------------------------
| Supervisor Cohorts
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare(
    "
    SELECT
        c.id,
        c.name
    FROM cohorts c
    WHERE c.supervisor_id = ?
    ORDER BY c.name
    ",
    'i',
    [$supervisorId]
);

$cohorts = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $cohorts[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Candidate Query
|--------------------------------------------------------------------------
*/

$where = [
    'c.supervisor_id = ?'
];

$types = 'i';

$params = [
    $supervisorId
];

if ($search !== '') {

    $where[] = "
        (
            u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $like = '%' . $search . '%';

    $types .= 'sss';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== '') {

    $where[] = 'cp.status = ?';

    $types .= 's';

    $params[] = $status;
}

if ($cohortId > 0) {

    $where[] = 'c.id = ?';

    $types .= 'i';

    $params[] = $cohortId;
}

$sql = "
    SELECT
        cp.id AS participation_id,

        u.id AS candidate_id,
        u.first_name,
        u.last_name,
        u.email,

        cp.status AS participant_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,

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

    WHERE " . implode(
        ' AND ',
        $where
    ) . "

    ORDER BY
        u.first_name,
        u.last_name
";

$stmt = Database::prepare(
    $sql,
    $types,
    $params
);

$rows = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Card Statistics
|--------------------------------------------------------------------------
*/

$matchingCount = count($rows);

$activeRows = array_values(
    array_filter(
        $rows,
        static fn(array $row): bool =>
            ($row['participant_status'] ?? '') === 'active'
    )
);

$completedRows = array_values(
    array_filter(
        $rows,
        static fn(array $row): bool =>
            ($row['participant_status'] ?? '') === 'completed'
    )
);

$withdrawnRows = array_values(
    array_filter(
        $rows,
        static fn(array $row): bool =>
            ($row['participant_status'] ?? '') === 'withdrawn'
    )
);

$activeCount = count($activeRows);
$completedCount = count($completedRows);
$withdrawnCount = count($withdrawnRows);


/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>


<style>

/* =========================================================
   CLICKABLE CARDS
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

    margin-top: 7px;

    font-size: 10px;
    font-weight: 700;

    opacity: .7;

    transition: opacity .18s ease;
}

.sv-stat--interactive:hover .sv-stat__view {
    opacity: 1;
}


/* =========================================================
   MODAL
========================================================= */

.sv-candidate-modal {
    display: none;

    position: fixed;
    inset: 0;

    z-index: 9999;

    padding: 22px;

    align-items: center;
    justify-content: center;
}

.sv-candidate-modal.is-open {
    display: flex;
}

.sv-candidate-modal__backdrop {
    position: absolute;
    inset: 0;

    background: rgba(15, 23, 42, .64);

    backdrop-filter: blur(5px);
}

.sv-candidate-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(820px, 100%);
    max-height: min(
        780px,
        calc(100vh - 44px)
    );

    display: flex;
    flex-direction: column;

    overflow: hidden;

    border: 1px solid #e4e7ec;
    border-radius: 20px;

    background: #ffffff;
    color: #101828;

    box-shadow:
        0 30px 90px
        rgba(15, 23, 42, .30);
}


/* =========================================================
   MODAL HEADER
========================================================= */

.sv-candidate-modal__header {
    padding: 20px 22px;

    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    border-bottom: 1px solid #e4e7ec;
}

.sv-candidate-modal__eyebrow {
    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 11px;
    font-weight: 800;

    text-transform: uppercase;
    letter-spacing: .08em;
}

.sv-candidate-modal__header h3 {
    margin: 0;

    font-size: 20px;
    font-weight: 800;
}

.sv-candidate-modal__header p {
    margin: 5px 0 0;

    color: #667085;

    font-size: 12px;
}

.sv-candidate-modal__close {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: grid;
    place-items: center;

    border: 1px solid #e4e7ec;
    border-radius: 10px;

    background: #f8fafc;
    color: #101828;

    cursor: pointer;

    transition:
        background .15s ease,
        transform .15s ease;
}

.sv-candidate-modal__close:hover {
    background: #eef2f6;
    transform: rotate(3deg);
}


/* =========================================================
   MODAL BODY
========================================================= */

.sv-candidate-modal__body {
    padding: 10px;

    overflow-y: auto;
}

.sv-candidate-modal__panel {
    display: none;
}

.sv-candidate-modal__panel.is-active {
    display: block;
}


/* =========================================================
   CANDIDATE ROW
========================================================= */

.sv-candidate-modal__row {
    padding: 14px;

    display: flex;
    align-items: center;

    gap: 13px;

    border-bottom: 1px solid #e4e7ec;
    border-radius: 11px;

    color: inherit;

    text-decoration: none;

    transition:
        background .15s ease,
        transform .15s ease;
}

.sv-candidate-modal__row:last-child {
    border-bottom: 0;
}

.sv-candidate-modal__row:hover {
    background: #f8fafc;
}


/* =========================================================
   CANDIDATE AVATAR
========================================================= */

.sv-candidate-modal__avatar {
    width: 44px;
    height: 44px;

    flex: 0 0 44px;

    display: grid;
    place-items: center;

    border-radius: 12px;

    background: rgba(37, 99, 235, .10);
    color: #2563eb;

    font-size: 11px;
    font-weight: 900;

    text-transform: uppercase;
}


/* =========================================================
   CANDIDATE INFORMATION
========================================================= */

.sv-candidate-modal__copy {
    min-width: 0;
    flex: 1;
}

.sv-candidate-modal__copy strong {
    display: block;

    overflow: hidden;

    font-size: 12px;
    font-weight: 800;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.sv-candidate-modal__email {
    display: block;

    margin-top: 3px;

    overflow: hidden;

    color: #667085;

    font-size: 10px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.sv-candidate-modal__meta {
    display: flex;

    flex-wrap: wrap;

    gap: 5px 10px;

    margin-top: 6px;

    color: #667085;

    font-size: 9px;
}

.sv-candidate-modal__meta span {
    display: inline-flex;
    align-items: center;

    gap: 4px;
}


/* =========================================================
   STATUS
========================================================= */

.sv-candidate-modal__status {
    flex: 0 0 auto;
}


/* =========================================================
   ARROW
========================================================= */

.sv-candidate-modal__arrow {
    flex: 0 0 auto;

    margin-left: 3px;

    color: #98a2b3;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.sv-candidate-modal__empty {
    padding: 52px 20px;

    text-align: center;
}

.sv-candidate-modal__empty i {
    margin-bottom: 14px;

    color: #98a2b3;

    font-size: 30px;
}

.sv-candidate-modal__empty strong {
    display: block;

    font-size: 14px;
}

.sv-candidate-modal__empty span {
    display: block;

    margin-top: 5px;

    color: #667085;

    font-size: 11px;
}


/* =========================================================
   MODAL FOOTER
========================================================= */

.sv-candidate-modal__footer {
    padding: 14px 20px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    border-top: 1px solid #e4e7ec;
}

.sv-candidate-modal__footer button {
    padding: 9px 15px;

    border: 1px solid #e4e7ec;
    border-radius: 9px;

    background: transparent;
    color: inherit;

    font: inherit;
    font-size: 11px;
    font-weight: 700;

    cursor: pointer;
}

.sv-candidate-modal__view-all {
    padding: 9px 15px;

    border-radius: 9px;

    background: #2563eb;

    color: #ffffff !important;

    font-size: 11px;
    font-weight: 800;

    text-decoration: none;
}

body.sv-candidate-modal-open {
    overflow: hidden;
}


/* =========================================================
   DARK MODE
========================================================= */

body.dark-mode .sv-candidate-modal__dialog,
html[data-theme="dark"] .sv-candidate-modal__dialog {
    background: #101828;
    color: #f8fafc;

    border-color:
        rgba(255, 255, 255, .09);
}

body.dark-mode .sv-candidate-modal__header,
body.dark-mode .sv-candidate-modal__footer,
body.dark-mode .sv-candidate-modal__row,
html[data-theme="dark"] .sv-candidate-modal__header,
html[data-theme="dark"] .sv-candidate-modal__footer,
html[data-theme="dark"] .sv-candidate-modal__row {
    border-color:
        rgba(255, 255, 255, .09);
}

body.dark-mode .sv-candidate-modal__header p,
body.dark-mode .sv-candidate-modal__email,
body.dark-mode .sv-candidate-modal__meta,
html[data-theme="dark"] .sv-candidate-modal__header p,
html[data-theme="dark"] .sv-candidate-modal__email,
html[data-theme="dark"] .sv-candidate-modal__meta {
    color: #98a2b3;
}

body.dark-mode .sv-candidate-modal__row:hover,
html[data-theme="dark"] .sv-candidate-modal__row:hover {
    background:
        rgba(255, 255, 255, .05);
}

body.dark-mode .sv-candidate-modal__close,
html[data-theme="dark"] .sv-candidate-modal__close {
    background:
        rgba(255, 255, 255, .05);

    color: #ffffff;

    border-color:
        rgba(255, 255, 255, .09);
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 620px) {

    .sv-candidate-modal {
        padding: 12px;
    }

    .sv-candidate-modal__dialog {
        max-height:
            calc(100vh - 24px);

        border-radius: 16px;
    }

    .sv-candidate-modal__header {
        padding: 16px;
    }

    .sv-candidate-modal__header h3 {
        font-size: 17px;
    }

    .sv-candidate-modal__row {
        align-items: flex-start;
    }

    .sv-candidate-modal__status {
        display: none;
    }

    .sv-candidate-modal__footer {
        padding: 12px;

        flex-direction: column-reverse;
    }

    .sv-candidate-modal__footer button,
    .sv-candidate-modal__view-all {
        width: 100%;

        box-sizing: border-box;

        text-align: center;
    }
}

</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="sv-page-header">

    <div>

        <span class="sv-eyebrow">
            Candidate Monitoring
        </span>

        <h2>
            Candidates
        </h2>

        <p>
            Review participants in your assigned cohorts and open
            a candidate record to inspect or update participation
            status.
        </p>

    </div>

</div>


<!-- =========================================================
     STAT CARDS
========================================================= -->

<section class="sv-stats">


    <!-- MATCHING CANDIDATES -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-candidate-modal="matching"
        aria-label="View matching candidates"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--blue
            "
        >
            <i class="fas fa-users"></i>
        </div>

        <strong>
            <?= number_format($matchingCount) ?>
        </strong>

        <span>
            Matching Candidates
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>


    <!-- ACTIVE -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-candidate-modal="active"
        aria-label="View active candidates"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--green
            "
        >
            <i class="fas fa-play"></i>
        </div>

        <strong>
            <?= number_format($activeCount) ?>
        </strong>

        <span>
            Active
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
        data-candidate-modal="completed"
        aria-label="View completed candidates"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--purple
            "
        >
            <i class="fas fa-circle-check"></i>
        </div>

        <strong>
            <?= number_format($completedCount) ?>
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
        data-candidate-modal="withdrawn"
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
            <?= number_format($withdrawnCount) ?>
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
     FILTERS
========================================================= -->

<form
    class="sv-filter"
    method="get"
>

    <div class="sv-filter-grid">


        <!-- SEARCH -->

        <div class="sv-field">

            <label for="candidateSearch">
                Search candidate
            </label>

            <input
                id="candidateSearch"
                class="sv-input"
                type="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Name or email"
            >

        </div>


        <!-- COHORT -->

        <div class="sv-field">

            <label for="candidateCohort">
                Cohort
            </label>

            <select
                id="candidateCohort"
                class="sv-select"
                name="cohort_id"
            >

                <option value="0">
                    All cohorts
                </option>

                <?php foreach ($cohorts as $cohort): ?>

                    <option
                        value="<?= (int) $cohort['id'] ?>"
                        <?= $cohortId === (int) $cohort['id']
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= e($cohort['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- STATUS -->

        <div class="sv-field">

            <label for="candidateStatus">
                Status
            </label>

            <select
                id="candidateStatus"
                class="sv-select"
                name="status"
            >

                <option value="">
                    All statuses
                </option>

                <?php foreach (
                    $allowedStatuses as $filterStatus
                ): ?>

                    <option
                        value="<?= e($filterStatus) ?>"
                        <?= $status === $filterStatus
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= e(
                            sv_status_label(
                                $filterStatus
                            )
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- ACTIONS -->

        <div class="sv-filter-actions">

            <button
                type="submit"
                class="sv-btn sv-btn--primary"
            >
                <i class="fas fa-filter"></i>
                Apply
            </button>

            <a
                class="sv-btn sv-btn--secondary"
                href="<?= url(
                    'supervisor/candidates.php'
                ) ?>"
            >
                Reset
            </a>

        </div>

    </div>

</form>


<!-- =========================================================
     CANDIDATE DIRECTORY
========================================================= -->

<section class="sv-card">

    <div class="sv-card__header">

        <div>

            <h3>
                Candidate Directory
            </h3>

            <p>
                Scoped to your assigned cohorts.
            </p>

        </div>

    </div>


    <?php if (!$rows): ?>

        <div class="sv-empty">

            <i class="fas fa-users"></i>

            <strong>
                No candidates found
            </strong>

            <span>
                Try another filter or cohort.
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
                        <th>Selected</th>
                        <th></th>
                    </tr>

                </thead>


                <tbody>

                <?php foreach ($rows as $row): ?>

                    <?php

                    $firstName = trim(
                        (string) (
                            $row['first_name']
                            ?? ''
                        )
                    );

                    $lastName = trim(
                        (string) (
                            $row['last_name']
                            ?? ''
                        )
                    );

                    $candidateName = trim(
                        $firstName
                        . ' '
                        . $lastName
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
                                            $firstName,
                                            $lastName
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
                                                $row['email']
                                                ?? ''
                                            )
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        </td>


                        <td>
                            <?= e(
                                $row['programme_name']
                            ) ?>
                        </td>


                        <td>
                            <?= e(
                                $row['cohort_name']
                            ) ?>
                        </td>


                        <td>

                            <span
                                class="
                                    sv-status
                                    sv-status--<?= e(
                                        sv_status_class(
                                            $row[
                                                'participant_status'
                                            ]
                                        )
                                    ) ?>
                                "
                            >
                                <?= e(
                                    sv_status_label(
                                        $row[
                                            'participant_status'
                                        ]
                                    )
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <?= e(
                                sv_date(
                                    $row['selected_at']
                                )
                            ) ?>

                        </td>


                        <td>

                            <a
                                class="sv-icon-btn"
                                href="<?= url(
                                    'supervisor/candidate_view.php?id='
                                    . (int) $row[
                                        'candidate_id'
                                    ]
                                    . '&cohort_id='
                                    . (int) $row[
                                        'cohort_id'
                                    ]
                                ) ?>"
                                title="View candidate"
                                aria-label="View candidate"
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
     CARD MODAL
========================================================= -->

<div
    class="sv-candidate-modal"
    id="candidateCardModal"
    aria-hidden="true"

    data-url-matching="<?= e(
        url('supervisor/candidates.php')
    ) ?>"

    data-url-active="<?= e(
        url('supervisor/candidates.php?status=active')
    ) ?>"

    data-url-completed="<?= e(
        url('supervisor/candidates.php?status=completed')
    ) ?>"

    data-url-withdrawn="<?= e(
        url('supervisor/candidates.php?status=withdrawn')
    ) ?>"
>

    <!-- BACKDROP -->

    <div
        class="sv-candidate-modal__backdrop"
        data-candidate-modal-close
    ></div>


    <!-- DIALOG -->

    <div
        class="sv-candidate-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="candidateModalTitle"
    >

        <!-- HEADER -->

        <div class="sv-candidate-modal__header">

            <div>

                <span class="sv-candidate-modal__eyebrow">
                    Candidate Monitoring
                </span>

                <h3 id="candidateModalTitle">
                    Candidates
                </h3>

                <p id="candidateModalSubtitle">
                    Candidate details from your assigned cohorts.
                </p>

            </div>


            <button
                type="button"
                class="sv-candidate-modal__close"
                data-candidate-modal-close
                aria-label="Close candidate modal"
            >
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        <!-- BODY -->

        <div class="sv-candidate-modal__body">


            <!-- =================================================
                 MATCHING CANDIDATES
            ================================================== -->

            <div
                class="sv-candidate-modal__panel"
                data-candidate-panel="matching"
            >

                <?php
                sv_render_candidate_modal_rows(
                    $rows
                );
                ?>

            </div>


            <!-- =================================================
                 ACTIVE
            ================================================== -->

            <div
                class="sv-candidate-modal__panel"
                data-candidate-panel="active"
            >

                <?php
                sv_render_candidate_modal_rows(
                    $activeRows
                );
                ?>

            </div>


            <!-- =================================================
                 COMPLETED
            ================================================== -->

            <div
                class="sv-candidate-modal__panel"
                data-candidate-panel="completed"
            >

                <?php
                sv_render_candidate_modal_rows(
                    $completedRows
                );
                ?>

            </div>


            <!-- =================================================
                 WITHDRAWN
            ================================================== -->

            <div
                class="sv-candidate-modal__panel"
                data-candidate-panel="withdrawn"
            >

                <?php
                sv_render_candidate_modal_rows(
                    $withdrawnRows
                );
                ?>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="sv-candidate-modal__footer">

            <button
                type="button"
                data-candidate-modal-close
            >
                Close
            </button>


            <a
                class="sv-candidate-modal__view-all"
                id="candidateModalViewAll"
                href="<?= url(
                    'supervisor/candidates.php'
                ) ?>"
            >
                View All
            </a>

        </div>

    </div>

</div>


<!-- =========================================================
     SECURITY
========================================================= -->

<div class="sv-security">

    <i class="fas fa-shield-halved"></i>

    <div>

        <strong>
            Candidate access is cohort-scoped
        </strong>

        <p>
            Candidate records are returned only through cohorts
            assigned to your Supervisor account.
        </p>

    </div>

</div>


<!-- =========================================================
     MODAL JAVASCRIPT
========================================================= -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById(
        'candidateCardModal'
    );

    if (!modal) {
        return;
    }

    const title = document.getElementById(
        'candidateModalTitle'
    );

    const subtitle = document.getElementById(
        'candidateModalSubtitle'
    );

    const viewAll = document.getElementById(
        'candidateModalViewAll'
    );

    const cards = document.querySelectorAll(
        '[data-candidate-modal]'
    );

    const panels = modal.querySelectorAll(
        '[data-candidate-panel]'
    );

    const closeButtons = modal.querySelectorAll(
        '[data-candidate-modal-close]'
    );

    let lastTrigger = null;


    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    const config = {

        matching: {
            title: 'Matching Candidates',
            subtitle:
                'Candidates matching the filters currently applied to this page.',
            url: modal.dataset.urlMatching
        },

        active: {
            title: 'Active Candidates',
            subtitle:
                'Candidates currently marked as active in the visible results.',
            url: modal.dataset.urlActive
        },

        completed: {
            title: 'Completed Candidates',
            subtitle:
                'Candidates who have completed their participation.',
            url: modal.dataset.urlCompleted
        },

        withdrawn: {
            title: 'Withdrawn Candidates',
            subtitle:
                'Candidates whose participation is marked as withdrawn.',
            url: modal.dataset.urlWithdrawn
        }

    };


    /*
    |--------------------------------------------------------------------------
    | Open Modal
    |--------------------------------------------------------------------------
    */

    function openModal(type, trigger) {

        const settings = config[type];

        if (!settings) {
            return;
        }

        lastTrigger = trigger || null;


        /*
        |--------------------------------------------------------------------------
        | Select Correct Panel
        |--------------------------------------------------------------------------
        */

        panels.forEach(function (panel) {

            const active =
                panel.dataset.candidatePanel
                === type;

            panel.classList.toggle(
                'is-active',
                active
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        if (title) {
            title.textContent =
                settings.title;
        }

        if (subtitle) {
            subtitle.textContent =
                settings.subtitle;
        }


        /*
        |--------------------------------------------------------------------------
        | View All URL
        |--------------------------------------------------------------------------
        */

        if (viewAll) {

            viewAll.href =
                settings.url || '#';

        }


        /*
        |--------------------------------------------------------------------------
        | Show Modal
        |--------------------------------------------------------------------------
        */

        modal.classList.add(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'sv-candidate-modal-open'
        );


        /*
        |--------------------------------------------------------------------------
        | Focus Close Button
        |--------------------------------------------------------------------------
        */

        const closeButton =
            modal.querySelector(
                '.sv-candidate-modal__close'
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
            'sv-candidate-modal-open'
        );


        panels.forEach(function (panel) {

            panel.classList.remove(
                'is-active'
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Restore Focus
        |--------------------------------------------------------------------------
        */

        if (
            lastTrigger
            && typeof lastTrigger.focus === 'function'
        ) {
            lastTrigger.focus();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Card Events
    |--------------------------------------------------------------------------
    */

    cards.forEach(function (card) {

        card.addEventListener(
            'click',
            function () {

                const type =
                    card.dataset.candidateModal;

                openModal(
                    type,
                    card
                );

            }
        );


        card.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Enter'
                    || event.key === ' '
                ) {

                    event.preventDefault();

                    const type =
                        card.dataset.candidateModal;

                    openModal(
                        type,
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

    closeButtons.forEach(function (button) {

        button.addEventListener(
            'click',
            function () {

                closeModal();

            }
        );

    });


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

/*
|--------------------------------------------------------------------------
| Modal Row Renderer
|--------------------------------------------------------------------------
|
| Declared at the end because PHP functions are available throughout the
| request regardless of declaration position.
|
*/

function sv_render_candidate_modal_rows(
    array $candidateRows
): void {

    if (!$candidateRows) {
        ?>

        <div class="sv-candidate-modal__empty">

            <i class="fas fa-users"></i>

            <strong>
                No candidates found
            </strong>

            <span>
                There are no candidate records in this category.
            </span>

        </div>

        <?php

        return;
    }


    foreach ($candidateRows as $candidate) {

        $firstName = trim(
            (string) (
                $candidate['first_name']
                ?? ''
            )
        );

        $lastName = trim(
            (string) (
                $candidate['last_name']
                ?? ''
            )
        );

        $name = trim(
            $firstName
            . ' '
            . $lastName
        );

        if ($name === '') {
            $name = 'Candidate';
        }

        $email = trim(
            (string) (
                $candidate['email']
                ?? ''
            )
        );

        $status = (string) (
            $candidate['participant_status']
            ?? ''
        );

        $candidateId = (int) (
            $candidate['candidate_id']
            ?? 0
        );

        $candidateCohortId = (int) (
            $candidate['cohort_id']
            ?? 0
        );

        ?>

        <a
            class="sv-candidate-modal__row"
            href="<?= url(
                'supervisor/candidate_view.php?id='
                . $candidateId
                . '&cohort_id='
                . $candidateCohortId
            ) ?>"
        >

            <span class="sv-candidate-modal__avatar">

                <?= e(
                    sv_initials(
                        $firstName,
                        $lastName
                    )
                ) ?>

            </span>


            <span class="sv-candidate-modal__copy">

                <strong>
                    <?= e($name) ?>
                </strong>

                <span class="sv-candidate-modal__email">
                    <?= e(
                        $email !== ''
                            ? $email
                            : 'No email address'
                    ) ?>
                </span>


                <span class="sv-candidate-modal__meta">

                    <span>

                        <i class="fas fa-graduation-cap"></i>

                        <?= e(
                            (string) (
                                $candidate[
                                    'programme_name'
                                ]
                                ?? 'Programme'
                            )
                        ) ?>

                    </span>


                    <span>

                        <i class="fas fa-layer-group"></i>

                        <?= e(
                            (string) (
                                $candidate[
                                    'cohort_name'
                                ]
                                ?? 'Cohort'
                            )
                        ) ?>

                    </span>


                    <?php if (
                        !empty(
                            $candidate[
                                'selected_at'
                            ]
                        )
                    ): ?>

                        <span>

                            <i class="fas fa-calendar"></i>

                            Selected
                            <?= e(
                                sv_date(
                                    $candidate[
                                        'selected_at'
                                    ]
                                )
                            ) ?>

                        </span>

                    <?php endif; ?>

                </span>

            </span>


            <span class="sv-candidate-modal__status">

                <span
                    class="
                        sv-status
                        sv-status--<?= e(
                            sv_status_class(
                                $status
                            )
                        ) ?>
                    "
                >
                    <?= e(
                        sv_status_label(
                            $status
                        )
                    ) ?>
                </span>

            </span>


            <i
                class="
                    fas
                    fa-arrow-right
                    sv-candidate-modal__arrow
                "
            ></i>

        </a>

        <?php
    }
}


/*
|--------------------------------------------------------------------------
| End Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_end.php';

?>