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

$currentPage = 'cohorts';
$pageTitle = 'My Cohorts';

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

$allowedStatuses = [
    'active',
    'completed',
    'upcoming',
    'inactive'
];

if (
    $status !== ''
    && !in_array($status, $allowedStatuses, true)
) {
    $status = '';
}


/*
|--------------------------------------------------------------------------
| Filtered Cohorts
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

    $where[] = '
        (
            c.name LIKE ?
            OR p.name LIKE ?
        )
    ';

    $like = '%' . $search . '%';

    $types .= 'ss';

    $params[] = $like;
    $params[] = $like;
}

if ($status !== '') {

    $where[] = 'c.status = ?';

    $types .= 's';

    $params[] = $status;
}

$sql = "
    SELECT
        c.id,
        c.name AS cohort_name,
        c.status AS cohort_status,
        c.start_date,
        c.end_date,

        p.name AS programme_name,
        p.type AS programme_type,

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

    WHERE " . implode(
        ' AND ',
        $where
    ) . "

    GROUP BY
        c.id,
        c.name,
        c.status,
        c.start_date,
        c.end_date,
        p.name,
        p.type

    ORDER BY
        c.start_date DESC,
        c.id DESC
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
        (int) $row['current_candidates'],
        (int) $row['completed_candidates']
    );

    $rows[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Overall Cohort Summary
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare(
    "
    SELECT
        COUNT(*) AS total,

        COUNT(
            CASE
                WHEN status = 'active'
                THEN 1
            END
        ) AS active

    FROM cohorts

    WHERE supervisor_id = ?
    ",
    'i',
    [$supervisorId]
);

$summary = $stmt->get_result()->fetch_assoc() ?: [];

$stmt->close();

$totalAssigned = (int) (
    $summary['total']
    ?? 0
);

$activeCohorts = (int) (
    $summary['active']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| Complete Cohort Dataset For Modals
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
        p.type AS programme_type,

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
        p.name,
        p.type

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

$allCohorts = [];

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $row['rate'] = sv_completion_rate(
        (int) $row['current_candidates'],
        (int) $row['completed_candidates']
    );

    $allCohorts[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Active Cohorts For Modal
|--------------------------------------------------------------------------
*/

$activeCohortRows = array_values(
    array_filter(
        $allCohorts,
        static fn(array $row): bool =>
            ($row['cohort_status'] ?? '') === 'active'
    )
);


/*
|--------------------------------------------------------------------------
| Visible Candidate Count
|--------------------------------------------------------------------------
*/

$visibleCandidates = array_sum(
    array_map(
        static fn(array $row): int =>
            (int) ($row['current_candidates'] ?? 0),
        $rows
    )
);


/*
|--------------------------------------------------------------------------
| Average Completion
|--------------------------------------------------------------------------
*/

$averageCompletion = 0;

if ($rows) {

    $totalRate = array_sum(
        array_map(
            static fn(array $row): int =>
                (int) ($row['rate'] ?? 0),
            $rows
        )
    );

    $averageCompletion = (int) round(
        $totalRate / count($rows)
    );
}


/*
|--------------------------------------------------------------------------
| Visible Candidate Details
|--------------------------------------------------------------------------
*/

$visibleCandidateRows = [];

if ($rows) {

    $visibleCohortIds = array_values(
        array_filter(
            array_map(
                static fn(array $row): int =>
                    (int) ($row['id'] ?? 0),
                $rows
            )
        )
    );

    if ($visibleCohortIds) {

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($visibleCohortIds),
                '?'
            )
        );

        $candidateTypes =
            'i'
            . str_repeat(
                'i',
                count($visibleCohortIds)
            );

        $candidateParams = array_merge(
            [$supervisorId],
            $visibleCohortIds
        );

        $candidateSql = "
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

              AND c.id IN (
                  {$placeholders}
              )

              AND cp.status <> 'withdrawn'

            ORDER BY
                c.name ASC,
                u.first_name ASC,
                u.last_name ASC
        ";

        $stmt = Database::prepare(
            $candidateSql,
            $candidateTypes,
            $candidateParams
        );

        $result = $stmt->get_result();

        $seenCandidates = [];

        while ($row = $result->fetch_assoc()) {

            $candidateId = (int) (
                $row['candidate_id']
                ?? 0
            );

            if ($candidateId <= 0) {
                continue;
            }

            /*
             * Dashboard card counts distinct candidates.
             * Keep modal rows distinct too.
             */
            if (isset($seenCandidates[$candidateId])) {
                continue;
            }

            $seenCandidates[$candidateId] = true;

            $visibleCandidateRows[] = $row;
        }

        $stmt->close();
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
   CLICKABLE STAT CARDS
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
   CARD MODAL
========================================================= */

.sv-card-modal {
    display: none;

    position: fixed;
    inset: 0;

    z-index: 9999;

    padding: 22px;

    align-items: center;
    justify-content: center;
}

.sv-card-modal.is-open {
    display: flex;
}

.sv-card-modal__backdrop {
    position: absolute;
    inset: 0;

    background: rgba(15, 23, 42, .62);

    backdrop-filter: blur(5px);
}

.sv-card-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(780px, 100%);
    max-height: min(
        760px,
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

.sv-card-modal__header {
    padding: 20px 22px;

    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    border-bottom: 1px solid #e4e7ec;
}

.sv-card-modal__eyebrow {
    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 11px;
    font-weight: 800;

    text-transform: uppercase;
    letter-spacing: .08em;
}

.sv-card-modal__header h3 {
    margin: 0;

    font-size: 20px;
    font-weight: 800;
}

.sv-card-modal__header p {
    margin: 5px 0 0;

    color: #667085;

    font-size: 12px;
}

.sv-card-modal__close {
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
   MODAL BODY
========================================================= */

.sv-card-modal__body {
    padding: 10px;

    overflow-y: auto;
}

.sv-card-modal__panel {
    display: none;
}

.sv-card-modal__panel.is-active {
    display: block;
}


/* =========================================================
   MODAL ROWS
========================================================= */

.sv-card-modal__row {
    padding: 14px;

    display: flex;
    align-items: center;

    gap: 13px;

    border-bottom: 1px solid #e4e7ec;
    border-radius: 11px;

    color: inherit;

    text-decoration: none;

    transition:
        background .15s ease;
}

.sv-card-modal__row:last-child {
    border-bottom: 0;
}

.sv-card-modal__row:hover {
    background: #f8fafc;
}

.sv-card-modal__icon {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border-radius: 12px;

    background: rgba(37, 99, 235, .10);
    color: #2563eb;

    font-size: 12px;
    font-weight: 800;
}

.sv-card-modal__copy {
    min-width: 0;
    flex: 1;
}

.sv-card-modal__copy strong {
    display: block;

    overflow: hidden;

    font-size: 12px;
    font-weight: 800;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.sv-card-modal__copy > span {
    display: block;

    margin-top: 4px;

    color: #667085;

    font-size: 10px;
}

.sv-card-modal__arrow {
    color: #98a2b3;
}


/* =========================================================
   MODAL PROGRESS
========================================================= */

.sv-card-modal__progress {
    display: block;

    margin-top: 9px;
}

.sv-card-modal__progress-meta {
    margin-bottom: 5px;

    display: flex !important;
    justify-content: space-between;

    gap: 10px;

    color: #667085;

    font-size: 9px !important;
}

.sv-card-modal__progress-track {
    display: block !important;

    height: 6px;

    overflow: hidden;

    border-radius: 999px;

    background: #eef2f6;
}

.sv-card-modal__progress-bar {
    display: block !important;

    height: 100%;

    border-radius: inherit;

    background: #2563eb;
}


/* =========================================================
   SUMMARY
========================================================= */

.sv-card-modal__summary {
    padding: 30px 20px 22px;

    text-align: center;
}

.sv-card-modal__summary-value {
    display: block;

    font-size: 38px;
    font-weight: 900;

    line-height: 1;
}

.sv-card-modal__summary-label {
    display: block;

    margin-top: 8px;

    color: #667085;

    font-size: 11px;
}

.sv-card-modal__summary-track {
    width: min(420px, 100%);
    height: 9px;

    margin: 18px auto 0;

    overflow: hidden;

    border-radius: 999px;

    background: #eef2f6;
}

.sv-card-modal__summary-bar {
    height: 100%;

    border-radius: inherit;

    background: #2563eb;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.sv-card-modal__empty {
    padding: 48px 20px;

    text-align: center;
}

.sv-card-modal__empty i {
    margin-bottom: 14px;

    color: #98a2b3;

    font-size: 30px;
}

.sv-card-modal__empty strong {
    display: block;

    font-size: 14px;
}

.sv-card-modal__empty span {
    display: block;

    margin-top: 5px;

    color: #667085;

    font-size: 11px;
}


/* =========================================================
   MODAL FOOTER
========================================================= */

.sv-card-modal__footer {
    padding: 14px 20px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    border-top: 1px solid #e4e7ec;
}

.sv-card-modal__footer button {
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

.sv-card-modal__view-all {
    padding: 9px 15px;

    border-radius: 9px;

    background: #2563eb;

    color: #ffffff !important;

    font-size: 11px;
    font-weight: 800;

    text-decoration: none;
}

body.sv-card-modal-open {
    overflow: hidden;
}


/* =========================================================
   DARK MODE
========================================================= */

body.dark-mode .sv-card-modal__dialog,
html[data-theme="dark"] .sv-card-modal__dialog {
    background: #101828;
    color: #f8fafc;

    border-color:
        rgba(255, 255, 255, .09);
}

body.dark-mode .sv-card-modal__header,
body.dark-mode .sv-card-modal__footer,
body.dark-mode .sv-card-modal__row,
html[data-theme="dark"] .sv-card-modal__header,
html[data-theme="dark"] .sv-card-modal__footer,
html[data-theme="dark"] .sv-card-modal__row {
    border-color:
        rgba(255, 255, 255, .09);
}

body.dark-mode .sv-card-modal__header p,
body.dark-mode .sv-card-modal__copy > span,
body.dark-mode .sv-card-modal__summary-label,
html[data-theme="dark"] .sv-card-modal__header p,
html[data-theme="dark"] .sv-card-modal__copy > span,
html[data-theme="dark"] .sv-card-modal__summary-label {
    color: #98a2b3;
}

body.dark-mode .sv-card-modal__row:hover,
body.dark-mode .sv-card-modal__close,
html[data-theme="dark"] .sv-card-modal__row:hover,
html[data-theme="dark"] .sv-card-modal__close {
    background: rgba(255, 255, 255, .05);
}

body.dark-mode .sv-card-modal__close,
html[data-theme="dark"] .sv-card-modal__close {
    color: #ffffff;

    border-color:
        rgba(255, 255, 255, .09);
}

body.dark-mode .sv-card-modal__progress-track,
body.dark-mode .sv-card-modal__summary-track,
html[data-theme="dark"] .sv-card-modal__progress-track,
html[data-theme="dark"] .sv-card-modal__summary-track {
    background:
        rgba(255, 255, 255, .09);
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 620px) {

    .sv-card-modal {
        padding: 12px;
    }

    .sv-card-modal__dialog {
        max-height:
            calc(100vh - 24px);

        border-radius: 16px;
    }

    .sv-card-modal__header {
        padding: 16px;
    }

    .sv-card-modal__header h3 {
        font-size: 17px;
    }

    .sv-card-modal__footer {
        padding: 12px;

        flex-direction: column-reverse;
    }

    .sv-card-modal__footer button,
    .sv-card-modal__view-all {
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
            Cohort Management
        </span>

        <h2>
            My Cohorts
        </h2>

        <p>
            Review the programmes and cohorts assigned to you,
            including participant volume and completion progress.
        </p>

    </div>

</div>


<!-- =========================================================
     STAT CARDS
========================================================= -->

<section class="sv-stats">


    <!-- TOTAL ASSIGNED -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-cohort-modal="total"
        aria-label="View all assigned cohorts"
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
            <?= number_format($totalAssigned) ?>
        </strong>

        <span>
            Total Assigned
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
        data-cohort-modal="active"
        aria-label="View active cohorts"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--green
            "
        >
            <i class="fas fa-bolt"></i>
        </div>

        <strong>
            <?= number_format($activeCohorts) ?>
        </strong>

        <span>
            Active Cohorts
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>


    <!-- VISIBLE CANDIDATES -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-cohort-modal="candidates"
        aria-label="View visible candidates"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--purple
            "
        >
            <i class="fas fa-users"></i>
        </div>

        <strong>
            <?= number_format($visibleCandidates) ?>
        </strong>

        <span>
            Visible Candidates
        </span>

        <small class="sv-stat__view">
            View details
        </small>

    </article>


    <!-- AVERAGE COMPLETION -->

    <article
        class="sv-stat sv-stat--interactive"
        role="button"
        tabindex="0"
        data-cohort-modal="completion"
        aria-label="View average completion"
    >

        <div
            class="
                sv-stat__icon
                sv-stat__icon--orange
            "
        >
            <i class="fas fa-chart-line"></i>
        </div>

        <strong>
            <?= $averageCompletion ?>%
        </strong>

        <span>
            Average Completion
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

            <label for="cohortSearch">
                Search
            </label>

            <input
                id="cohortSearch"
                class="sv-input"
                type="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Cohort or programme"
            >

        </div>


        <div class="sv-field">

            <label for="cohortStatus">
                Status
            </label>

            <select
                id="cohortStatus"
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
                    'supervisor/cohorts.php'
                ) ?>"
            >
                Reset
            </a>

        </div>

    </div>

</form>


<!-- =========================================================
     COHORT TABLE
========================================================= -->

<section class="sv-card">

    <div class="sv-card__header">

        <div>

            <h3>
                Assigned Cohorts
            </h3>

            <p>
                <?= number_format(count($rows)) ?>
                cohort(s) match your filters.
            </p>

        </div>

    </div>


    <?php if (!$rows): ?>

        <div class="sv-empty">

            <i class="fas fa-layer-group"></i>

            <strong>
                No cohorts found
            </strong>

            <span>
                Try changing your filters.
            </span>

        </div>

    <?php else: ?>

        <div class="sv-table-wrap">

            <table class="sv-table">

                <thead>

                    <tr>
                        <th>Cohort</th>
                        <th>Status</th>
                        <th>Dates</th>
                        <th>Candidates</th>
                        <th>Progress</th>
                        <th></th>
                    </tr>

                </thead>


                <tbody>

                <?php foreach ($rows as $row): ?>

                    <tr>

                        <td>

                            <div class="sv-person">

                                <div class="sv-avatar">

                                    <i class="fas fa-layer-group"></i>

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

                            <?= e(
                                sv_date(
                                    $row['start_date']
                                )
                            ) ?>

                            —

                            <?= e(
                                sv_date(
                                    $row['end_date']
                                )
                            ) ?>

                        </td>


                        <td>
                            <?= number_format(
                                (int) $row[
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


                        <td>

                            <a
                                class="sv-icon-btn"
                                href="<?= url(
                                    'supervisor/cohort_view.php?id='
                                    . (int) $row['id']
                                ) ?>"
                                title="View cohort"
                                aria-label="View cohort"
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


<!-- =========================================================
     CARD DETAILS MODAL
========================================================= -->

<div
    class="sv-card-modal"
    id="cohortCardModal"
    aria-hidden="true"

    data-url-total="<?= e(
        url('supervisor/cohorts.php')
    ) ?>"

    data-url-active="<?= e(
        url('supervisor/cohorts.php?status=active')
    ) ?>"

    data-url-candidates="<?= e(
        url('supervisor/candidates.php')
    ) ?>"

    data-url-completion="<?= e(
        url('supervisor/reports.php')
    ) ?>"
>

    <div
        class="sv-card-modal__backdrop"
        data-cohort-modal-close
    ></div>


    <div
        class="sv-card-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cohortModalTitle"
    >

        <!-- HEADER -->

        <div class="sv-card-modal__header">

            <div>

                <span class="sv-card-modal__eyebrow">
                    Supervisor Cohorts
                </span>

                <h3 id="cohortModalTitle">
                    Cohort Details
                </h3>

                <p id="cohortModalSubtitle">
                    Details from your assigned cohorts.
                </p>

            </div>


            <button
                type="button"
                class="sv-card-modal__close"
                data-cohort-modal-close
                aria-label="Close modal"
            >
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        <!-- BODY -->

        <div class="sv-card-modal__body">


            <!-- =================================================
                 TOTAL ASSIGNED
            ================================================== -->

            <div
                class="sv-card-modal__panel"
                data-cohort-panel="total"
            >

                <?php if (!$allCohorts): ?>

                    <div class="sv-card-modal__empty">

                        <i class="fas fa-layer-group"></i>

                        <strong>
                            No cohorts assigned
                        </strong>

                        <span>
                            Assigned cohorts will appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $allCohorts as $cohort
                    ): ?>

                        <a
                            class="sv-card-modal__row"
                            href="<?= url(
                                'supervisor/cohort_view.php?id='
                                . (int) $cohort['id']
                            ) ?>"
                        >

                            <span class="sv-card-modal__icon">

                                <i class="fas fa-layer-group"></i>

                            </span>


                            <span class="sv-card-modal__copy">

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

                                    <?= e(
                                        sv_status_label(
                                            $cohort[
                                                'cohort_status'
                                            ]
                                        )
                                    ) ?>

                                    ·

                                    <?= number_format(
                                        (int) $cohort[
                                            'current_candidates'
                                        ]
                                    ) ?>

                                    candidates

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-card-modal__arrow
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
                class="sv-card-modal__panel"
                data-cohort-panel="active"
            >

                <?php if (!$activeCohortRows): ?>

                    <div class="sv-card-modal__empty">

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
                        $activeCohortRows as $cohort
                    ): ?>

                        <a
                            class="sv-card-modal__row"
                            href="<?= url(
                                'supervisor/cohort_view.php?id='
                                . (int) $cohort['id']
                            ) ?>"
                        >

                            <span class="sv-card-modal__icon">

                                <i class="fas fa-bolt"></i>

                            </span>


                            <span class="sv-card-modal__copy">

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
                                        (int) $cohort[
                                            'current_candidates'
                                        ]
                                    ) ?>

                                    candidates

                                    ·

                                    <?= (int) $cohort['rate'] ?>%
                                    completion

                                </span>

                            </span>


                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    sv-card-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 VISIBLE CANDIDATES
            ================================================== -->

            <div
                class="sv-card-modal__panel"
                data-cohort-panel="candidates"
            >

                <?php if (!$visibleCandidateRows): ?>

                    <div class="sv-card-modal__empty">

                        <i class="fas fa-users"></i>

                        <strong>
                            No visible candidates
                        </strong>

                        <span>
                            No current candidates match the
                            selected cohort filters.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $visibleCandidateRows as $candidate
                    ): ?>

                        <?php

                        $candidateFirstName = trim(
                            (string) (
                                $candidate[
                                    'first_name'
                                ]
                                ?? ''
                            )
                        );

                        $candidateLastName = trim(
                            (string) (
                                $candidate[
                                    'last_name'
                                ]
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

                        <a
                            class="sv-card-modal__row"
                            href="<?= url(
                                'supervisor/candidate_view.php?id='
                                . (int) $candidate[
                                    'candidate_id'
                                ]
                                . '&cohort_id='
                                . (int) $candidate[
                                    'cohort_id'
                                ]
                            ) ?>"
                        >

                            <span class="sv-card-modal__icon">

                                <?= e(
                                    sv_initials(
                                        $candidateFirstName,
                                        $candidateLastName
                                    )
                                ) ?>

                            </span>


                            <span class="sv-card-modal__copy">

                                <strong>
                                    <?= e($candidateName) ?>
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
                                    sv-card-modal__arrow
                                "
                            ></i>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 AVERAGE COMPLETION
            ================================================== -->

            <div
                class="sv-card-modal__panel"
                data-cohort-panel="completion"
            >

                <div class="sv-card-modal__summary">

                    <strong
                        class="sv-card-modal__summary-value"
                    >
                        <?= $averageCompletion ?>%
                    </strong>

                    <span
                        class="sv-card-modal__summary-label"
                    >
                        Average completion across
                        <?= number_format(count($rows)) ?>
                        visible cohort(s)
                    </span>


                    <div
                        class="sv-card-modal__summary-track"
                    >

                        <div
                            class="sv-card-modal__summary-bar"
                            style="width:<?= max(
                                0,
                                min(
                                    100,
                                    $averageCompletion
                                )
                            ) ?>%"
                        ></div>

                    </div>

                </div>


                <?php if (!$rows): ?>

                    <div class="sv-card-modal__empty">

                        <i class="fas fa-chart-line"></i>

                        <strong>
                            No completion data
                        </strong>

                        <span>
                            No cohorts currently match your filters.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $rows as $cohort
                    ): ?>

                        <a
                            class="sv-card-modal__row"
                            href="<?= url(
                                'supervisor/cohort_view.php?id='
                                . (int) $cohort['id']
                            ) ?>"
                        >

                            <span class="sv-card-modal__icon">

                                <i class="fas fa-chart-line"></i>

                            </span>


                            <span class="sv-card-modal__copy">

                                <strong>
                                    <?= e(
                                        $cohort['cohort_name']
                                    ) ?>
                                </strong>

                                <span>

                                    <?= number_format(
                                        (int) $cohort[
                                            'completed_candidates'
                                        ]
                                    ) ?>

                                    completed of

                                    <?= number_format(
                                        (int) $cohort[
                                            'current_candidates'
                                        ]
                                    ) ?>

                                    current candidates

                                </span>


                                <span class="sv-card-modal__progress">

                                    <span
                                        class="
                                            sv-card-modal__progress-meta
                                        "
                                    >

                                        <span>
                                            Completion
                                        </span>

                                        <strong>
                                            <?= (int) $cohort['rate'] ?>%
                                        </strong>

                                    </span>


                                    <span
                                        class="
                                            sv-card-modal__progress-track
                                        "
                                    >

                                        <span
                                            class="
                                                sv-card-modal__progress-bar
                                            "
                                            style="width:<?= max(
                                                0,
                                                min(
                                                    100,
                                                    (int) $cohort['rate']
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
                                    sv-card-modal__arrow
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

        <div class="sv-card-modal__footer">

            <button
                type="button"
                data-cohort-modal-close
            >
                Close
            </button>


            <a
                class="sv-card-modal__view-all"
                id="cohortModalViewAll"
                href="<?= url(
                    'supervisor/cohorts.php'
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
            Assignment boundary enforced
        </strong>

        <p>
            Only cohorts where your user ID is recorded as the
            assigned Supervisor are returned.
        </p>

    </div>

</div>


<!-- =========================================================
     CARD MODAL JAVASCRIPT
========================================================= -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('cohortCardModal');

    if (!modal) {
        return;
    }

    const title = document.getElementById(
        'cohortModalTitle'
    );

    const subtitle = document.getElementById(
        'cohortModalSubtitle'
    );

    const viewAll = document.getElementById(
        'cohortModalViewAll'
    );

    const cards = document.querySelectorAll(
        '[data-cohort-modal]'
    );

    const panels = modal.querySelectorAll(
        '[data-cohort-panel]'
    );

    const closeButtons = modal.querySelectorAll(
        '[data-cohort-modal-close]'
    );

    let lastTrigger = null;


    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    const config = {

        total: {
            title: 'Total Assigned Cohorts',
            subtitle:
                'All cohorts currently assigned to your Supervisor account.',
            url: modal.dataset.urlTotal
        },

        active: {
            title: 'Active Cohorts',
            subtitle:
                'Cohorts currently marked as active within your assignment.',
            url: modal.dataset.urlActive
        },

        candidates: {
            title: 'Visible Candidates',
            subtitle:
                'Current candidates within the cohorts matching your selected filters.',
            url: modal.dataset.urlCandidates
        },

        completion: {
            title: 'Average Completion',
            subtitle:
                'Completion performance for the cohorts currently visible on this page.',
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


        /*
        |--------------------------------------------------------------------------
        | Activate Correct Panel
        |--------------------------------------------------------------------------
        */

        panels.forEach(function (panel) {

            const isActive =
                panel.dataset.cohortPanel === type;

            panel.classList.toggle(
                'is-active',
                isActive
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Update Header
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
        | Update View All URL
        |--------------------------------------------------------------------------
        */

        if (viewAll) {

            viewAll.href =
                settings.url || '#';

        }


        /*
        |--------------------------------------------------------------------------
        | Display Modal
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
            'sv-card-modal-open'
        );


        /*
        |--------------------------------------------------------------------------
        | Focus Close Button
        |--------------------------------------------------------------------------
        */

        const closeButton =
            modal.querySelector(
                '.sv-card-modal__close'
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
            'sv-card-modal-open'
        );


        panels.forEach(function (panel) {

            panel.classList.remove(
                'is-active'
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Return Focus To Card
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
    | Card Click / Keyboard
    |--------------------------------------------------------------------------
    */

    cards.forEach(function (card) {

        card.addEventListener(
            'click',
            function () {

                const type =
                    card.dataset.cohortModal;

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
                        card.dataset.cohortModal;

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

    closeButtons.forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    closeModal();

                }
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