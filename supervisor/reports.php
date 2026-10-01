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

$currentPage = 'reports';
$pageTitle = 'Cohort Progress';

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

$programmeId = (int) (
    $_GET['programme_id']
    ?? 0
);

$cohortId = (int) (
    $_GET['cohort_id']
    ?? 0
);

$status = trim(
    (string) ($_GET['status'] ?? '')
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
| Programmes Available To Supervisor
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
    ORDER BY p.name
    ",
    'i',
    [$supervisorId]
);

$programmes = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $programmes[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Cohorts Available To Supervisor
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare(
    "
    SELECT
        id,
        name
    FROM cohorts
    WHERE supervisor_id = ?
    ORDER BY name
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
| Build Report Scope
|--------------------------------------------------------------------------
*/

$where = [
    'c.supervisor_id = ?'
];

$types = 'i';

$params = [
    $supervisorId
];

if ($programmeId > 0) {

    $where[] = 'p.id = ?';

    $types .= 'i';
    $params[] = $programmeId;
}

if ($cohortId > 0) {

    $where[] = 'c.id = ?';

    $types .= 'i';
    $params[] = $cohortId;
}

if ($status !== '') {

    $where[] = 'cp.status = ?';

    $types .= 's';
    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Cohort Progress Report
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.name AS programme_name,

        c.id AS cohort_id,
        c.name AS cohort_name,
        c.status AS cohort_status,
        c.start_date,
        c.end_date,

        COUNT(
            DISTINCT CASE
                WHEN cp.status <> 'withdrawn'
                THEN cp.user_id
            END
        ) AS total_candidates,

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

    WHERE " . implode(
        ' AND ',
        $where
    ) . "

    GROUP BY
        p.name,
        c.id,
        c.name,
        c.status,
        c.start_date,
        c.end_date

    ORDER BY
        p.name,
        c.name
";

$stmt = Database::prepare(
    $sql,
    $types,
    $params
);

$rows = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $row['rate'] = sv_completion_rate(
        (int) $row['total_candidates'],
        (int) $row['completed_candidates']
    );

    $rows[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/

$total = array_sum(
    array_map(
        static fn(array $row): int =>
            (int) $row['total_candidates'],
        $rows
    )
);

$completed = array_sum(
    array_map(
        static fn(array $row): int =>
            (int) $row['completed_candidates'],
        $rows
    )
);

$active = array_sum(
    array_map(
        static fn(array $row): int =>
            (int) $row['active_candidates'],
        $rows
    )
);

$withdrawn = array_sum(
    array_map(
        static fn(array $row): int =>
            (int) $row['withdrawn_candidates'],
        $rows
    )
);

$overall = sv_completion_rate(
    $total,
    $completed
);


/*
|--------------------------------------------------------------------------
| Modal Cohort Groups
|--------------------------------------------------------------------------
*/

$activeCohortRows = array_values(
    array_filter(
        $rows,
        static fn(array $row): bool =>
            (int) $row['active_candidates'] > 0
    )
);

$completedCohortRows = array_values(
    array_filter(
        $rows,
        static fn(array $row): bool =>
            (int) $row['completed_candidates'] > 0
    )
);


/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>


<style>

/* =========================================================
   CLICKABLE REPORT CARDS
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
}

.sv-stat--interactive:hover .sv-stat__view {
    opacity: 1;
}


/* =========================================================
   MODAL
========================================================= */

.sv-report-modal {
    display: none;

    position: fixed;
    inset: 0;

    z-index: 9999;

    padding: 22px;

    align-items: center;
    justify-content: center;
}

.sv-report-modal.is-open {
    display: flex;
}

.sv-report-modal__backdrop {
    position: absolute;
    inset: 0;

    background: rgba(15, 23, 42, .64);

    backdrop-filter: blur(5px);
}

.sv-report-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(840px, 100%);

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
   HEADER
========================================================= */

.sv-report-modal__header {
    padding: 20px 22px;

    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    border-bottom: 1px solid #e4e7ec;
}

.sv-report-modal__eyebrow {
    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 11px;
    font-weight: 800;

    text-transform: uppercase;
    letter-spacing: .08em;
}

.sv-report-modal__header h3 {
    margin: 0;

    font-size: 20px;
    font-weight: 800;
}

.sv-report-modal__header p {
    margin: 5px 0 0;

    color: #667085;

    font-size: 12px;
}

.sv-report-modal__close {
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
}


/* =========================================================
   BODY
========================================================= */

.sv-report-modal__body {
    padding: 10px;

    overflow-y: auto;
}

.sv-report-modal__panel {
    display: none;
}

.sv-report-modal__panel.is-active {
    display: block;
}


/* =========================================================
   SUMMARY
========================================================= */

.sv-report-modal__summary {
    padding: 28px 20px 22px;

    text-align: center;
}

.sv-report-modal__summary strong {
    display: block;

    font-size: 38px;
    font-weight: 900;

    line-height: 1;
}

.sv-report-modal__summary span {
    display: block;

    margin-top: 8px;

    color: #667085;

    font-size: 11px;
}

.sv-report-modal__summary-track {
    width: min(420px, 100%);
    height: 9px;

    margin: 18px auto 0;

    overflow: hidden;

    border-radius: 999px;

    background: #eef2f6;
}

.sv-report-modal__summary-bar {
    height: 100%;

    border-radius: inherit;

    background: #2563eb;
}


/* =========================================================
   COHORT ROW
========================================================= */

.sv-report-modal__row {
    padding: 14px;

    display: flex;
    align-items: center;

    gap: 13px;

    border-bottom: 1px solid #e4e7ec;
    border-radius: 11px;

    color: inherit;

    text-decoration: none;

    transition: background .15s ease;
}

.sv-report-modal__row:last-child {
    border-bottom: 0;
}

.sv-report-modal__row:hover {
    background: #f8fafc;
}

.sv-report-modal__icon {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border-radius: 12px;

    background: rgba(37, 99, 235, .10);
    color: #2563eb;

    font-size: 13px;
}

.sv-report-modal__copy {
    min-width: 0;
    flex: 1;
}

.sv-report-modal__copy strong {
    display: block;

    overflow: hidden;

    font-size: 12px;
    font-weight: 800;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.sv-report-modal__copy > span {
    display: block;

    margin-top: 4px;

    color: #667085;

    font-size: 10px;
}

.sv-report-modal__metrics {
    display: flex !important;

    flex-wrap: wrap;

    gap: 5px 12px;

    margin-top: 7px !important;
}

.sv-report-modal__metrics span {
    display: inline-flex;

    align-items: center;

    gap: 4px;

    margin: 0 !important;

    font-size: 9px;
}

.sv-report-modal__arrow {
    flex: 0 0 auto;

    color: #98a2b3;
}


/* =========================================================
   PROGRESS
========================================================= */

.sv-report-modal__progress {
    display: block !important;

    margin-top: 9px !important;
}

.sv-report-modal__progress-meta {
    display: flex !important;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 5px !important;

    color: #667085;

    font-size: 9px;
}

.sv-report-modal__progress-track {
    display: block !important;

    height: 6px;

    overflow: hidden;

    border-radius: 999px;

    background: #eef2f6;
}

.sv-report-modal__progress-bar {
    display: block !important;

    height: 100%;

    border-radius: inherit;

    background: #2563eb;
}


/* =========================================================
   EMPTY
========================================================= */

.sv-report-modal__empty {
    padding: 50px 20px;

    text-align: center;
}

.sv-report-modal__empty i {
    margin-bottom: 14px;

    color: #98a2b3;

    font-size: 30px;
}

.sv-report-modal__empty strong {
    display: block;

    font-size: 14px;
}

.sv-report-modal__empty span {
    display: block;

    margin-top: 5px;

    color: #667085;

    font-size: 11px;
}


/* =========================================================
   FOOTER
========================================================= */

.sv-report-modal__footer {
    padding: 14px 20px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    border-top: 1px solid #e4e7ec;
}

.sv-report-modal__footer button {
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

.sv-report-modal__view-all {
    padding: 9px 15px;

    border-radius: 9px;

    background: #2563eb;

    color: #ffffff !important;

    font-size: 11px;
    font-weight: 800;

    text-decoration: none;
}

body.sv-report-modal-open {
    overflow: hidden;
}


/* =========================================================
   DARK MODE
========================================================= */

body.dark-mode .sv-report-modal__dialog,
html[data-theme="dark"] .sv-report-modal__dialog {
    background: #101828;
    color: #f8fafc;

    border-color:
        rgba(255, 255, 255, .09);
}

body.dark-mode .sv-report-modal__header,
body.dark-mode .sv-report-modal__footer,
body.dark-mode .sv-report-modal__row,
html[data-theme="dark"] .sv-report-modal__header,
html[data-theme="dark"] .sv-report-modal__footer,
html[data-theme="dark"] .sv-report-modal__row {
    border-color:
        rgba(255, 255, 255, .09);
}

body.dark-mode .sv-report-modal__header p,
body.dark-mode .sv-report-modal__summary span,
body.dark-mode .sv-report-modal__copy > span,
body.dark-mode .sv-report-modal__progress-meta,
html[data-theme="dark"] .sv-report-modal__header p,
html[data-theme="dark"] .sv-report-modal__summary span,
html[data-theme="dark"] .sv-report-modal__copy > span,
html[data-theme="dark"] .sv-report-modal__progress-meta {
    color: #98a2b3;
}

body.dark-mode .sv-report-modal__row:hover,
html[data-theme="dark"] .sv-report-modal__row:hover {
    background:
        rgba(255, 255, 255, .05);
}

body.dark-mode .sv-report-modal__close,
html[data-theme="dark"] .sv-report-modal__close {
    background:
        rgba(255, 255, 255, .05);

    color: #ffffff;

    border-color:
        rgba(255, 255, 255, .09);
}

body.dark-mode .sv-report-modal__progress-track,
body.dark-mode .sv-report-modal__summary-track,
html[data-theme="dark"] .sv-report-modal__progress-track,
html[data-theme="dark"] .sv-report-modal__summary-track {
    background:
        rgba(255, 255, 255, .09);
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 620px) {

    .sv-report-modal {
        padding: 12px;
    }

    .sv-report-modal__dialog {
        max-height:
            calc(100vh - 24px);

        border-radius: 16px;
    }

    .sv-report-modal__header {
        padding: 16px;
    }

    .sv-report-modal__header h3 {
        font-size: 17px;
    }

    .sv-report-modal__footer {
        padding: 12px;

        flex-direction: column-reverse;
    }

    .sv-report-modal__footer button,
    .sv-report-modal__view-all {
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
            Reporting
        </span>

        <h2>
            Cohort Progress
        </h2>

        <p>
            Operational reporting for programmes, cohorts and
            participants assigned to you.
        </p>

    </div>

</div>


<!-- =========================================================
     REPORT CARDS
========================================================= -->

<section class="sv-stats sv-stats--5">


    <!-- COHORTS -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-report-modal="cohorts"
        aria-label="View cohort report details"
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
            <?= number_format(count($rows)) ?>
        </strong>

        <span>
            Cohorts
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
        data-report-modal="current"
        aria-label="View current candidate report"
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
            <?= number_format($total) ?>
        </strong>

        <span>
            Current Candidates
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
        data-report-modal="active"
        aria-label="View active candidate report"
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
            <?= number_format($active) ?>
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
        data-report-modal="completed"
        aria-label="View completed candidate report"
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
            <?= number_format($completed) ?>
        </strong>

        <span>
            Completed
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>


    <!-- COMPLETION RATE -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-report-modal="completion"
        aria-label="View completion rate details"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--red
            "
        >
            <i class="fas fa-chart-line"></i>
        </div>

        <strong>
            <?= (int) $overall ?>%
        </strong>

        <span>
            Completion Rate
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


        <div class="sv-field">

            <label for="reportProgramme">
                Programme
            </label>

            <select
                id="reportProgramme"
                class="sv-select"
                name="programme_id"
            >

                <option value="0">
                    All programmes
                </option>

                <?php foreach ($programmes as $programme): ?>

                    <option
                        value="<?= (int) $programme['id'] ?>"
                        <?= $programmeId === (int) $programme['id']
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= e($programme['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="sv-field">

            <label for="reportCohort">
                Cohort
            </label>

            <select
                id="reportCohort"
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


        <div class="sv-field">

            <label for="reportStatus">
                Candidate status
            </label>

            <select
                id="reportStatus"
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
                    'supervisor/reports.php'
                ) ?>"
            >
                Reset
            </a>

        </div>

    </div>

</form>


<!-- =========================================================
     REPORT TABLE
========================================================= -->

<section class="sv-card">

    <div class="sv-card__header">

        <div>

            <h3>
                Progress by Cohort
            </h3>

            <p>
                Participation and completion summary.
            </p>

        </div>

    </div>


    <?php if (!$rows): ?>

        <div class="sv-empty">

            <i class="fas fa-chart-column"></i>

            <strong>
                No report data
            </strong>

            <span>
                No cohorts match the selected filters.
            </span>

        </div>

    <?php else: ?>

        <div class="sv-table-wrap">

            <table class="sv-table">

                <thead>

                    <tr>
                        <th>Programme / Cohort</th>
                        <th>Status</th>
                        <th>Current</th>
                        <th>Active</th>
                        <th>Completed</th>
                        <th>Withdrawn</th>
                        <th>Progress</th>
                    </tr>

                </thead>


                <tbody>

                <?php foreach ($rows as $row): ?>

                    <tr>

                        <td>

                            <div class="sv-person">

                                <div class="sv-avatar">

                                    <i class="fas fa-chart-line"></i>

                                </div>


                                <div class="sv-person__copy">

                                    <strong>
                                        <?= e(
                                            $row['cohort_name']
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= e(
                                            $row['programme_name']
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
                                            $row[
                                                'cohort_status'
                                            ]
                                        )
                                    ) ?>
                                "
                            >
                                <?= e(
                                    sv_status_label(
                                        $row[
                                            'cohort_status'
                                        ]
                                    )
                                ) ?>
                            </span>

                        </td>


                        <td>
                            <?= number_format(
                                (int) $row[
                                    'total_candidates'
                                ]
                            ) ?>
                        </td>


                        <td>
                            <?= number_format(
                                (int) $row[
                                    'active_candidates'
                                ]
                            ) ?>
                        </td>


                        <td>
                            <?= number_format(
                                (int) $row[
                                    'completed_candidates'
                                ]
                            ) ?>
                        </td>


                        <td>
                            <?= number_format(
                                (int) $row[
                                    'withdrawn_candidates'
                                ]
                            ) ?>
                        </td>


                        <td style="min-width:150px">

                            <div class="sv-progress">

                                <div class="sv-progress__meta">

                                    <span>
                                        Completion
                                    </span>

                                    <strong>
                                        <?= (int) $row['rate'] ?>%
                                    </strong>

                                </div>


                                <div class="sv-progress__track">

                                    <div
                                        class="sv-progress__bar"
                                        style="width:<?= max(
                                            0,
                                            min(
                                                100,
                                                (int) $row['rate']
                                            )
                                        ) ?>%"
                                    ></div>

                                </div>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<!-- =========================================================
     REPORT CARD MODAL
========================================================= -->

<div
    class="sv-report-modal"
    id="reportCardModal"
    aria-hidden="true"

    data-url-cohorts="<?= e(
        url('supervisor/cohorts.php')
    ) ?>"

    data-url-current="<?= e(
        url('supervisor/candidates.php')
    ) ?>"

    data-url-active="<?= e(
        url('supervisor/candidates.php?status=active')
    ) ?>"

    data-url-completed="<?= e(
        url('supervisor/candidates.php?status=completed')
    ) ?>"

    data-url-completion="<?= e(
        url('supervisor/reports.php')
    ) ?>"
>

    <div
        class="sv-report-modal__backdrop"
        data-report-modal-close
    ></div>


    <div
        class="sv-report-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="reportModalTitle"
    >

        <!-- HEADER -->

        <div class="sv-report-modal__header">

            <div>

                <span class="sv-report-modal__eyebrow">
                    Cohort Progress Report
                </span>

                <h3 id="reportModalTitle">
                    Report Details
                </h3>

                <p id="reportModalSubtitle">
                    Operational report details.
                </p>

            </div>


            <button
                type="button"
                class="sv-report-modal__close"
                data-report-modal-close
                aria-label="Close report modal"
            >
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        <!-- BODY -->

        <div class="sv-report-modal__body">


            <!-- =================================================
                 COHORTS
            ================================================== -->

            <div
                class="sv-report-modal__panel"
                data-report-panel="cohorts"
            >

                <?php if (!$rows): ?>

                    <div class="sv-report-modal__empty">

                        <i class="fas fa-layer-group"></i>

                        <strong>
                            No cohorts found
                        </strong>

                        <span>
                            No cohorts match the current report filters.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach ($rows as $row): ?>

                        <a
                            class="sv-report-modal__row"
                            href="<?= url(
                                'supervisor/cohort_view.php?id='
                                . (int) $row['cohort_id']
                            ) ?>"
                        >

                            <span class="sv-report-modal__icon">

                                <i class="fas fa-layer-group"></i>

                            </span>


                            <span class="sv-report-modal__copy">

                                <strong>
                                    <?= e(
                                        $row['cohort_name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        $row['programme_name']
                                    ) ?>
                                    ·
                                    <?= e(
                                        sv_status_label(
                                            $row[
                                                'cohort_status'
                                            ]
                                        )
                                    ) ?>
                                </span>


                                <span class="sv-report-modal__metrics">

                                    <span>
                                        <i class="fas fa-users"></i>

                                        <?= number_format(
                                            (int) $row[
                                                'total_candidates'
                                            ]
                                        ) ?>
                                        current
                                    </span>

                                    <span>
                                        <i class="fas fa-play"></i>

                                        <?= number_format(
                                            (int) $row[
                                                'active_candidates'
                                            ]
                                        ) ?>
                                        active
                                    </span>

                                    <span>
                                        <i class="fas fa-circle-check"></i>

                                        <?= number_format(
                                            (int) $row[
                                                'completed_candidates'
                                            ]
                                        ) ?>
                                        completed
                                    </span>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-report-modal__arrow
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
                class="sv-report-modal__panel"
                data-report-panel="current"
            >

                <div class="sv-report-modal__summary">

                    <strong>
                        <?= number_format($total) ?>
                    </strong>

                    <span>
                        Current candidates across
                        <?= number_format(count($rows)) ?>
                        matching cohort(s)
                    </span>

                </div>


                <?php if (!$rows): ?>

                    <div class="sv-report-modal__empty">

                        <i class="fas fa-users"></i>

                        <strong>
                            No candidate data
                        </strong>

                        <span>
                            No candidate records match the current filters.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach ($rows as $row): ?>

                        <a
                            class="sv-report-modal__row"
                            href="<?= url(
                                'supervisor/candidates.php?cohort_id='
                                . (int) $row['cohort_id']
                            ) ?>"
                        >

                            <span class="sv-report-modal__icon">

                                <i class="fas fa-users"></i>

                            </span>


                            <span class="sv-report-modal__copy">

                                <strong>
                                    <?= e(
                                        $row['cohort_name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        $row['programme_name']
                                    ) ?>
                                </span>


                                <span class="sv-report-modal__metrics">

                                    <span>
                                        <i class="fas fa-users"></i>

                                        <?= number_format(
                                            (int) $row[
                                                'total_candidates'
                                            ]
                                        ) ?>
                                        current
                                    </span>

                                    <span>
                                        <i class="fas fa-play"></i>

                                        <?= number_format(
                                            (int) $row[
                                                'active_candidates'
                                            ]
                                        ) ?>
                                        active
                                    </span>

                                    <span>
                                        <i class="fas fa-circle-check"></i>

                                        <?= number_format(
                                            (int) $row[
                                                'completed_candidates'
                                            ]
                                        ) ?>
                                        completed
                                    </span>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-report-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 ACTIVE
            ================================================== -->

            <div
                class="sv-report-modal__panel"
                data-report-panel="active"
            >

                <div class="sv-report-modal__summary">

                    <strong>
                        <?= number_format($active) ?>
                    </strong>

                    <span>
                        Active candidates in the current report scope
                    </span>

                </div>


                <?php if (!$activeCohortRows): ?>

                    <div class="sv-report-modal__empty">

                        <i class="fas fa-play"></i>

                        <strong>
                            No active candidates
                        </strong>

                        <span>
                            No matching cohort currently has active candidates.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $activeCohortRows as $row
                    ): ?>

                        <a
                            class="sv-report-modal__row"
                            href="<?= url(
                                'supervisor/candidates.php?cohort_id='
                                . (int) $row['cohort_id']
                                . '&status=active'
                            ) ?>"
                        >

                            <span class="sv-report-modal__icon">

                                <i class="fas fa-play"></i>

                            </span>


                            <span class="sv-report-modal__copy">

                                <strong>
                                    <?= e(
                                        $row['cohort_name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        $row['programme_name']
                                    ) ?>
                                </span>


                                <span class="sv-report-modal__metrics">

                                    <span>
                                        <i class="fas fa-play"></i>

                                        <?= number_format(
                                            (int) $row[
                                                'active_candidates'
                                            ]
                                        ) ?>
                                        active candidates
                                    </span>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-report-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 COMPLETED
            ================================================== -->

            <div
                class="sv-report-modal__panel"
                data-report-panel="completed"
            >

                <div class="sv-report-modal__summary">

                    <strong>
                        <?= number_format($completed) ?>
                    </strong>

                    <span>
                        Completed candidates in the current report scope
                    </span>

                </div>


                <?php if (!$completedCohortRows): ?>

                    <div class="sv-report-modal__empty">

                        <i class="fas fa-circle-check"></i>

                        <strong>
                            No completed candidates
                        </strong>

                        <span>
                            No matching cohort currently has completed candidates.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $completedCohortRows as $row
                    ): ?>

                        <a
                            class="sv-report-modal__row"
                            href="<?= url(
                                'supervisor/candidates.php?cohort_id='
                                . (int) $row['cohort_id']
                                . '&status=completed'
                            ) ?>"
                        >

                            <span class="sv-report-modal__icon">

                                <i class="fas fa-circle-check"></i>

                            </span>


                            <span class="sv-report-modal__copy">

                                <strong>
                                    <?= e(
                                        $row['cohort_name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        $row['programme_name']
                                    ) ?>
                                </span>


                                <span class="sv-report-modal__metrics">

                                    <span>
                                        <i class="fas fa-circle-check"></i>

                                        <?= number_format(
                                            (int) $row[
                                                'completed_candidates'
                                            ]
                                        ) ?>
                                        completed candidates
                                    </span>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-report-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 COMPLETION RATE
            ================================================== -->

            <div
                class="sv-report-modal__panel"
                data-report-panel="completion"
            >

                <div class="sv-report-modal__summary">

                    <strong>
                        <?= (int) $overall ?>%
                    </strong>

                    <span>
                        Overall completion rate for the current
                        Supervisor report scope
                    </span>


                    <div class="sv-report-modal__summary-track">

                        <div
                            class="sv-report-modal__summary-bar"
                            style="width:<?= max(
                                0,
                                min(
                                    100,
                                    (int) $overall
                                )
                            ) ?>%"
                        ></div>

                    </div>

                </div>


                <?php if (!$rows): ?>

                    <div class="sv-report-modal__empty">

                        <i class="fas fa-chart-line"></i>

                        <strong>
                            No completion data
                        </strong>

                        <span>
                            No cohorts match the current report filters.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach ($rows as $row): ?>

                        <a
                            class="sv-report-modal__row"
                            href="<?= url(
                                'supervisor/cohort_view.php?id='
                                . (int) $row['cohort_id']
                            ) ?>"
                        >

                            <span class="sv-report-modal__icon">

                                <i class="fas fa-chart-line"></i>

                            </span>


                            <span class="sv-report-modal__copy">

                                <strong>
                                    <?= e(
                                        $row['cohort_name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        $row['programme_name']
                                    ) ?>
                                </span>


                                <span class="sv-report-modal__progress">

                                    <span
                                        class="
                                            sv-report-modal__progress-meta
                                        "
                                    >

                                        <span>
                                            Completion
                                        </span>

                                        <strong>
                                            <?= (int) $row['rate'] ?>%
                                        </strong>

                                    </span>


                                    <span
                                        class="
                                            sv-report-modal__progress-track
                                        "
                                    >

                                        <span
                                            class="
                                                sv-report-modal__progress-bar
                                            "
                                            style="width:<?= max(
                                                0,
                                                min(
                                                    100,
                                                    (int) $row['rate']
                                                )
                                            ) ?>%"
                                        ></span>

                                    </span>

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-report-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


        <!-- =====================================================
             FOOTER
        ====================================================== -->

        <div class="sv-report-modal__footer">

            <button
                type="button"
                data-report-modal-close
            >
                Close
            </button>


            <a
                class="sv-report-modal__view-all"
                id="reportModalViewAll"
                href="<?= url(
                    'supervisor/reports.php'
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
            Scoped reporting
        </strong>

        <p>
            Report calculations are based only on cohorts assigned
            to your Supervisor account.
        </p>

    </div>

</div>


<!-- =========================================================
     MODAL JAVASCRIPT
========================================================= -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById(
        'reportCardModal'
    );

    if (!modal) {
        return;
    }

    const title = document.getElementById(
        'reportModalTitle'
    );

    const subtitle = document.getElementById(
        'reportModalSubtitle'
    );

    const viewAll = document.getElementById(
        'reportModalViewAll'
    );

    const cards = document.querySelectorAll(
        '[data-report-modal]'
    );

    const panels = modal.querySelectorAll(
        '[data-report-panel]'
    );

    const closeButtons = modal.querySelectorAll(
        '[data-report-modal-close]'
    );

    let lastTrigger = null;


    /*
    |--------------------------------------------------------------------------
    | Modal Configuration
    |--------------------------------------------------------------------------
    */

    const config = {

        cohorts: {
            title: 'Cohorts',
            subtitle:
                'Cohorts included in the current progress report.',
            url: modal.dataset.urlCohorts
        },

        current: {
            title: 'Current Candidates',
            subtitle:
                'Candidate totals across the cohorts included in this report.',
            url: modal.dataset.urlCurrent
        },

        active: {
            title: 'Active Candidates',
            subtitle:
                'Active participants across your current report scope.',
            url: modal.dataset.urlActive
        },

        completed: {
            title: 'Completed Candidates',
            subtitle:
                'Participants who have completed their cohort participation.',
            url: modal.dataset.urlCompleted
        },

        completion: {
            title: 'Completion Rate',
            subtitle:
                'Overall and cohort-level completion performance.',
            url: modal.dataset.urlCompletion
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


        panels.forEach(function (panel) {

            const activePanel =
                panel.dataset.reportPanel === type;

            panel.classList.toggle(
                'is-active',
                activePanel
            );

        });


        if (title) {
            title.textContent =
                settings.title;
        }

        if (subtitle) {
            subtitle.textContent =
                settings.subtitle;
        }

        if (viewAll) {
            viewAll.href =
                settings.url || '#';
        }


        modal.classList.add(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'sv-report-modal-open'
        );


        const closeButton =
            modal.querySelector(
                '.sv-report-modal__close'
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
            'sv-report-modal-open'
        );


        panels.forEach(function (panel) {

            panel.classList.remove(
                'is-active'
            );

        });


        if (
            lastTrigger
            && typeof lastTrigger.focus === 'function'
        ) {
            lastTrigger.focus();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Card Click / Keyboard
    |--------------------------------------------------------------------------
    */

    cards.forEach(function (card) {

        card.addEventListener(
            'click',
            function () {

                const type =
                    card.dataset.reportModal;

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
                        card.dataset.reportModal;

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
    | Close Controls
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

require __DIR__ . '/_layout_end.php';

?>