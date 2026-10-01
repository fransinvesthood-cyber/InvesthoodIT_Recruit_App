<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('programme_officer');

require_once __DIR__ . '/_helpers.php';

/*
|--------------------------------------------------------------------------
| Current User / Page
|--------------------------------------------------------------------------
*/

$user = current_user();
$flashes = render_flashes();

$currentPage = 'reports';
$pageTitle = 'Programme Reports';

$conn = Database::getConnection();

/*
|--------------------------------------------------------------------------
| Programme Officer
|--------------------------------------------------------------------------
*/

$officerId = (int) (
    $user['id']
    ?? $user['user_id']
    ?? 0
);

if ($officerId <= 0) {
    http_response_code(403);
    exit('Invalid Programme Officer account.');
}

/*
|--------------------------------------------------------------------------
| Programme Officer Scope
|--------------------------------------------------------------------------
*/

$scope = po_scope(
    $conn,
    'p',
    'c'
);

$scopeMode = po_scope_mode($scope);
$scopeCondition = po_scope_condition($scope);

/*
|--------------------------------------------------------------------------
| Programme Report Data
|--------------------------------------------------------------------------
*/

$rows = [];

if ($scopeMode !== 'none') {

    $sql = "
        SELECT
            p.id,
            p.name,
            p.type,
            p.status,

            COUNT(
                DISTINCT c.id
            ) AS cohorts,

            COUNT(
                DISTINCT CASE
                    WHEN cp.status <> 'withdrawn'
                    THEN cp.user_id
                END
            ) AS candidates,

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

        FROM programmes p

        LEFT JOIN cohorts c
            ON c.programme_id = p.id

        LEFT JOIN cohort_participants cp
            ON cp.cohort_id = c.id

        WHERE {$scopeCondition}

        GROUP BY
            p.id,
            p.name,
            p.type,
            p.status

        ORDER BY
            CASE
                WHEN p.status = 'active'
                THEN 0
                ELSE 1
            END,
            p.name ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare Programme Officer report query: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $officerId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $row['cohorts'] =
            (int) ($row['cohorts'] ?? 0);

        $row['candidates'] =
            (int) ($row['candidates'] ?? 0);

        $row['active_candidates'] =
            (int) ($row['active_candidates'] ?? 0);

        $row['completed_candidates'] =
            (int) ($row['completed_candidates'] ?? 0);

        $row['withdrawn_candidates'] =
            (int) ($row['withdrawn_candidates'] ?? 0);

        $row['completion_rate'] =
            po_completion_rate(
                $row['candidates'],
                $row['completed_candidates']
            );

        $rows[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Portfolio Totals
|--------------------------------------------------------------------------
*/

$totalProgrammes = count($rows);

$totalCohorts = array_sum(
    array_column(
        $rows,
        'cohorts'
    )
);

$totalCandidates = array_sum(
    array_column(
        $rows,
        'candidates'
    )
);

$totalActive = array_sum(
    array_column(
        $rows,
        'active_candidates'
    )
);

$totalCompleted = array_sum(
    array_column(
        $rows,
        'completed_candidates'
    )
);

$totalWithdrawn = array_sum(
    array_column(
        $rows,
        'withdrawn_candidates'
    )
);

$completionRate = po_completion_rate(
    $totalCandidates,
    $totalCompleted
);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>

<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="po-page-header">

    <div>

        <span class="po-page-header__eyebrow">
            Insights & Reporting
        </span>

        <h2>
            Programme Reports
        </h2>

        <p>
            Portfolio-level delivery, participation and completion
            indicators for programmes available to your workspace.
        </p>

    </div>

</div>


<?php if ($scopeMode === 'none'): ?>

    <div class="po-alert">

        <i class="fas fa-triangle-exclamation"></i>

        <div>

            <strong>
                Programme Officer scope unavailable
            </strong>

            <span>
                No Programme Officer data scope is currently
                available for this account.
            </span>

        </div>

    </div>

<?php endif; ?>


<!-- =========================================================
     REPORT STATISTICS
========================================================= -->

<section class="po-stats po-stats--4">

    <!-- PROGRAMMES -->

    <article class="po-stat">

        <span
            class="
                po-stat__icon
                po-stat__icon--blue
            "
        >
            <i class="fas fa-diagram-project"></i>
        </span>

        <strong>
            <?= number_format(
                $totalProgrammes
            ) ?>
        </strong>

        <span>
            Programmes
        </span>

    </article>


    <!-- COHORTS -->

    <article class="po-stat">

        <span
            class="
                po-stat__icon
                po-stat__icon--purple
            "
        >
            <i class="fas fa-layer-group"></i>
        </span>

        <strong>
            <?= number_format(
                $totalCohorts
            ) ?>
        </strong>

        <span>
            Cohorts
        </span>

    </article>


    <!-- CANDIDATES -->

    <article class="po-stat">

        <span
            class="
                po-stat__icon
                po-stat__icon--orange
            "
        >
            <i class="fas fa-users"></i>
        </span>

        <strong>
            <?= number_format(
                $totalCandidates
            ) ?>
        </strong>

        <span>
            Candidates
        </span>

    </article>


    <!-- COMPLETION RATE -->

    <article class="po-stat">

        <span
            class="
                po-stat__icon
                po-stat__icon--green
            "
        >
            <i class="fas fa-chart-line"></i>
        </span>

        <strong>
            <?= (int) $completionRate ?>%
        </strong>

        <span>
            Completion Rate
        </span>

    </article>

</section>


<!-- =========================================================
     SECONDARY SUMMARY
========================================================= -->

<div
    class="po-grid po-grid--2"
    style="margin-bottom: 18px;"
>

    <!-- ACTIVE -->

    <section class="po-card">

        <div class="po-card__body">

            <div
                style="
                    display:flex;
                    align-items:center;
                    gap:14px;
                "
            >

                <span
                    class="
                        po-stat__icon
                        po-stat__icon--cyan
                    "
                    style="margin-bottom:0;"
                >
                    <i class="fas fa-person-running"></i>
                </span>

                <div>

                    <strong
                        style="
                            display:block;
                            font-size:1.5rem;
                        "
                    >
                        <?= number_format(
                            $totalActive
                        ) ?>
                    </strong>

                    <span
                        style="
                            color:var(--po-muted);
                            font-size:.8125rem;
                        "
                    >
                        Active Candidates
                    </span>

                </div>

            </div>

        </div>

    </section>


    <!-- WITHDRAWN -->

    <section class="po-card">

        <div class="po-card__body">

            <div
                style="
                    display:flex;
                    align-items:center;
                    gap:14px;
                "
            >

                <span
                    class="
                        po-stat__icon
                        po-stat__icon--orange
                    "
                    style="margin-bottom:0;"
                >
                    <i class="fas fa-user-minus"></i>
                </span>

                <div>

                    <strong
                        style="
                            display:block;
                            font-size:1.5rem;
                        "
                    >
                        <?= number_format(
                            $totalWithdrawn
                        ) ?>
                    </strong>

                    <span
                        style="
                            color:var(--po-muted);
                            font-size:.8125rem;
                        "
                    >
                        Withdrawn Candidates
                    </span>

                </div>

            </div>

        </div>

    </section>

</div>


<!-- =========================================================
     PROGRAMME PERFORMANCE
========================================================= -->

<div class="po-card">

    <div class="po-card__header">

        <div>

            <h3>
                Programme Performance
            </h3>

            <p>
                Consolidated programme delivery metrics.
            </p>

        </div>

    </div>


    <?php if (!$rows): ?>

        <!-- EMPTY STATE -->

        <div class="po-empty">

            <div class="po-empty__icon">

                <i class="fas fa-chart-column"></i>

            </div>

            <strong>
                No report data
            </strong>

            <span>
                Programme metrics will appear once assignments
                and participants are available.
            </span>

        </div>

    <?php else: ?>

        <!-- REPORT TABLE -->

        <div class="po-table-wrap">

            <table class="po-table">

                <thead>

                    <tr>

                        <th>
                            Programme
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Cohorts
                        </th>

                        <th>
                            Candidates
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

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $rows as $row
                    ): ?>

                        <tr>

                            <!-- PROGRAMME -->

                            <td>

                                <strong>
                                    <?= e(
                                        $row['name']
                                    ) ?>
                                </strong>

                                <span class="po-table-sub">

                                    <?= e(
                                        (string) (
                                            $row['type']
                                            ?? 'Programme'
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        po-status
                                        po-status--<?= e(
                                            po_status_class(
                                                $row['status']
                                            )
                                        ) ?>
                                    "
                                >

                                    <?= e(
                                        po_status_label(
                                            $row['status']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- COHORTS -->

                            <td>

                                <?= number_format(
                                    $row['cohorts']
                                ) ?>

                            </td>


                            <!-- CANDIDATES -->

                            <td>

                                <?= number_format(
                                    $row['candidates']
                                ) ?>

                            </td>


                            <!-- ACTIVE -->

                            <td>

                                <?= number_format(
                                    $row['active_candidates']
                                ) ?>

                            </td>


                            <!-- COMPLETED -->

                            <td>

                                <?= number_format(
                                    $row['completed_candidates']
                                ) ?>

                            </td>


                            <!-- WITHDRAWN -->

                            <td>

                                <?= number_format(
                                    $row['withdrawn_candidates']
                                ) ?>

                            </td>


                            <!-- COMPLETION -->

                            <td>

                                <div
                                    class="
                                        po-progress
                                        po-progress--table
                                    "
                                >

                                    <div
                                        class="
                                            po-progress__labels
                                        "
                                    >

                                        <span>
                                            Progress
                                        </span>

                                        <strong>

                                            <?= (int) $row[
                                                'completion_rate'
                                            ] ?>%

                                        </strong>

                                    </div>


                                    <div
                                        class="
                                            po-progress__track
                                        "
                                    >

                                        <span
                                            style="
                                                width:
                                                <?= (int) $row[
                                                    'completion_rate'
                                                ] ?>%;
                                            "
                                        ></span>

                                    </div>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     ACCESS INFORMATION
========================================================= -->

<div class="po-security">

    <i class="fas fa-shield-halved"></i>

    <div>

        <strong>
            Programme Officer scoped reports
        </strong>

        <span>

            <?php if ($scopeMode === 'programme'): ?>

                Report information is restricted using
                programmes.programme_officer_id.

            <?php elseif ($scopeMode === 'cohort'): ?>

                Report information is restricted using
                cohorts.programme_officer_id.

            <?php elseif ($scopeMode === 'portfolio'): ?>

                The current database does not contain a
                Programme Officer assignment column, so
                portfolio-level reporting access is being used.

            <?php else: ?>

                No Programme Officer report scope is
                currently available.

            <?php endif; ?>

        </span>

    </div>

</div>


<?php

require __DIR__ . '/_layout_end.php';

?>