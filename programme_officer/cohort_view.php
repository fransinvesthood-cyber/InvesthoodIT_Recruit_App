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

$currentPage = 'cohort_view';
$pageTitle = 'Cohort Details';

$conn = Database::getConnection();

/*
|--------------------------------------------------------------------------
| Programme Officer / Cohort
|--------------------------------------------------------------------------
*/

$officerId = (int) (
    $user['id']
    ?? $user['user_id']
    ?? 0
);

$cohortId = (int) (
    $_GET['id']
    ?? 0
);

if (
    $officerId <= 0
    || $cohortId <= 0
) {
    http_response_code(400);
    exit('Invalid request.');
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

if ($scopeMode === 'none') {
    http_response_code(403);
    exit('Programme Officer access scope is unavailable.');
}

/*
|--------------------------------------------------------------------------
| Load Cohort
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        c.*,
        p.name AS programme_name,
        p.type AS programme_type

    FROM cohorts c

    INNER JOIN programmes p
        ON p.id = c.programme_id

    WHERE c.id = ?
      AND {$scopeCondition}

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    throw new RuntimeException(
        'Unable to prepare cohort query: '
        . $conn->error
    );
}

$stmt->bind_param(
    'ii',
    $cohortId,
    $officerId
);

if (!$stmt->execute()) {

    $error = $stmt->error;

    $stmt->close();

    throw new RuntimeException(
        'Unable to load cohort: '
        . $error
    );
}

$result = $stmt->get_result();

$cohort = $result->fetch_assoc();

$stmt->close();

/*
|--------------------------------------------------------------------------
| Cohort Not Found / Access Denied
|--------------------------------------------------------------------------
*/

if (!$cohort) {
    http_response_code(404);

    exit(
        'Cohort not found or you do not have permission '
        . 'to access this cohort.'
    );
}

/*
|--------------------------------------------------------------------------
| Load Cohort Participants
|--------------------------------------------------------------------------
*/

$participants = [];

$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,

        cp.status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at

    FROM cohort_participants cp

    INNER JOIN users u
        ON u.id = cp.user_id

    INNER JOIN cohorts c
        ON c.id = cp.cohort_id

    INNER JOIN programmes p
        ON p.id = c.programme_id

    WHERE cp.cohort_id = ?
      AND {$scopeCondition}

    ORDER BY
        u.first_name ASC,
        u.last_name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    throw new RuntimeException(
        'Unable to prepare cohort participants query: '
        . $conn->error
    );
}

$stmt->bind_param(
    'ii',
    $cohortId,
    $officerId
);

if (!$stmt->execute()) {

    $error = $stmt->error;

    $stmt->close();

    throw new RuntimeException(
        'Unable to load cohort participants: '
        . $error
    );
}

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Cohort Statistics
|--------------------------------------------------------------------------
*/

$total = count(
    array_filter(
        $participants,
        static fn(array $participant): bool =>
            ($participant['status'] ?? '') !== 'withdrawn'
    )
);

$active = count(
    array_filter(
        $participants,
        static fn(array $participant): bool =>
            ($participant['status'] ?? '') === 'active'
    )
);

$completed = count(
    array_filter(
        $participants,
        static fn(array $participant): bool =>
            ($participant['status'] ?? '') === 'completed'
    )
);

$withdrawn = count(
    array_filter(
        $participants,
        static fn(array $participant): bool =>
            ($participant['status'] ?? '') === 'withdrawn'
    )
);

$rate = po_completion_rate(
    $total,
    $completed
);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>


<!-- =========================================================
     BREADCRUMB
========================================================= -->

<div class="po-breadcrumb">

    <a href="<?= url(
        'programme_officer/cohorts.php'
    ) ?>">
        Cohorts
    </a>

    <i class="fas fa-chevron-right"></i>

    <span>
        <?= e($cohort['name']) ?>
    </span>

</div>


<!-- =========================================================
     COHORT HERO
========================================================= -->

<section class="po-detail-hero">

    <div>

        <span class="po-detail-hero__eyebrow">

            <?= e(
                $cohort['programme_name']
            ) ?>

        </span>


        <h2>

            <?= e(
                $cohort['name']
            ) ?>

        </h2>


        <div class="po-detail-hero__meta">

            <!-- STATUS -->

            <span
                class="
                    po-status
                    po-status--<?= e(
                        po_status_class(
                            $cohort['status']
                        )
                    ) ?>
                "
            >

                <?= e(
                    po_status_label(
                        $cohort['status']
                    )
                ) ?>

            </span>


            <!-- DATES -->

            <span>

                <i class="fas fa-calendar"></i>

                <?= e(
                    po_date(
                        $cohort['start_date']
                    )
                ) ?>

                –

                <?= e(
                    po_date(
                        $cohort['end_date']
                    )
                ) ?>

            </span>

        </div>

    </div>


    <!-- VIEW CANDIDATES -->

    <a
        class="po-btn po-btn--secondary"
        href="<?= url(
            'programme_officer/candidates.php?cohort_id='
            . $cohortId
        ) ?>"
    >

        <i class="fas fa-users"></i>

        View Candidates

    </a>

</section>


<!-- =========================================================
     COHORT STATISTICS
========================================================= -->

<section class="po-stats po-stats--4">


    <!-- CURRENT CANDIDATES -->

    <article class="po-stat">

        <span
            class="
                po-stat__icon
                po-stat__icon--blue
            "
        >

            <i class="fas fa-users"></i>

        </span>

        <strong>

            <?= number_format(
                $total
            ) ?>

        </strong>

        <span>
            Current Candidates
        </span>

    </article>


    <!-- ACTIVE -->

    <article class="po-stat">

        <span
            class="
                po-stat__icon
                po-stat__icon--cyan
            "
        >

            <i class="fas fa-person-running"></i>

        </span>

        <strong>

            <?= number_format(
                $active
            ) ?>

        </strong>

        <span>
            Active
        </span>

    </article>


    <!-- COMPLETED -->

    <article class="po-stat">

        <span
            class="
                po-stat__icon
                po-stat__icon--green
            "
        >

            <i class="fas fa-circle-check"></i>

        </span>

        <strong>

            <?= number_format(
                $completed
            ) ?>

        </strong>

        <span>
            Completed
        </span>

    </article>


    <!-- COMPLETION RATE -->

    <article class="po-stat">

        <span
            class="
                po-stat__icon
                po-stat__icon--purple
            "
        >

            <i class="fas fa-chart-line"></i>

        </span>

        <strong>

            <?= (int) $rate ?>%

        </strong>

        <span>
            Completion
        </span>

    </article>

</section>


<!-- =========================================================
     PARTICIPANTS
========================================================= -->

<div class="po-card">

    <div class="po-card__header">

        <div>

            <h3>
                Cohort Participants
            </h3>

            <p>
                Candidate participation and current status.
            </p>

        </div>


        <?php if ($participants): ?>

            <span class="po-chip">

                <i class="fas fa-users"></i>

                <?= number_format(
                    count($participants)
                ) ?>

                Participants

            </span>

        <?php endif; ?>

    </div>


    <?php if (!$participants): ?>

        <!-- EMPTY STATE -->

        <div class="po-empty">

            <div class="po-empty__icon">

                <i class="fas fa-users"></i>

            </div>

            <strong>
                No participants
            </strong>

            <span>
                No candidates are attached to this cohort.
            </span>

        </div>

    <?php else: ?>

        <!-- PARTICIPANTS TABLE -->

        <div class="po-table-wrap">

            <table class="po-table">

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
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $participants as $participant
                    ): ?>

                        <?php

                        $firstName = trim(
                            (string) (
                                $participant[
                                    'first_name'
                                ]
                                ?? ''
                            )
                        );

                        $lastName = trim(
                            (string) (
                                $participant[
                                    'last_name'
                                ]
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


                            <!-- CANDIDATE -->

                            <td>

                                <div class="po-person">

                                    <div class="po-avatar">

                                        <?= e(
                                            po_initials(
                                                $firstName,
                                                $lastName
                                            )
                                        ) ?>

                                    </div>


                                    <div>

                                        <strong>

                                            <?= e(
                                                $candidateName
                                            ) ?>

                                        </strong>

                                        <span>

                                            <?= e(
                                                (string) (
                                                    $participant[
                                                        'email'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>

                                        </span>

                                    </div>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        po-status
                                        po-status--<?= e(
                                            po_status_class(
                                                $participant[
                                                    'status'
                                                ]
                                            )
                                        ) ?>
                                    "
                                >

                                    <?= e(
                                        po_status_label(
                                            $participant[
                                                'status'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- SELECTED -->

                            <td>

                                <?= e(
                                    po_date(
                                        $participant[
                                            'selected_at'
                                        ],
                                        '—'
                                    )
                                ) ?>

                            </td>


                            <!-- ONBOARDED -->

                            <td>

                                <?= e(
                                    po_date(
                                        $participant[
                                            'onboarded_at'
                                        ],
                                        '—'
                                    )
                                ) ?>

                            </td>


                            <!-- COMPLETED -->

                            <td>

                                <?= e(
                                    po_date(
                                        $participant[
                                            'completed_at'
                                        ],
                                        '—'
                                    )
                                ) ?>

                            </td>


                            <!-- ACTION -->

                            <td class="po-table__action">

                                <a
                                    class="po-icon-btn"
                                    href="<?= url(
                                        'programme_officer/candidate_view.php?id='
                                        . (int) $participant['id']
                                        . '&cohort_id='
                                        . $cohortId
                                    ) ?>"
                                    title="View candidate"
                                    aria-label="View <?= e(
                                        $candidateName
                                    ) ?>"
                                >

                                    <i
                                        class="
                                            fas
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     COHORT INFORMATION
========================================================= -->

<div class="po-grid po-grid--2">


    <!-- PROGRAMME -->

    <section class="po-card">

        <div class="po-card__header">

            <div>

                <h3>
                    Programme
                </h3>

                <p>
                    Programme associated with this cohort.
                </p>

            </div>

        </div>


        <div class="po-card__body">

            <div class="po-detail-list">

                <div>

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


                <div>

                    <span>
                        Programme Type
                    </span>

                    <strong>

                        <?= e(
                            (string) (
                                $cohort[
                                    'programme_type'
                                ]
                                ?? '—'
                            )
                        ) ?>

                    </strong>

                </div>

            </div>

        </div>

    </section>


    <!-- COHORT SUMMARY -->

    <section class="po-card">

        <div class="po-card__header">

            <div>

                <h3>
                    Cohort Summary
                </h3>

                <p>
                    Current participation overview.
                </p>

            </div>

        </div>


        <div class="po-card__body">

            <div class="po-detail-list">

                <div>

                    <span>
                        Current Candidates
                    </span>

                    <strong>
                        <?= number_format($total) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Active
                    </span>

                    <strong>
                        <?= number_format($active) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Completed
                    </span>

                    <strong>
                        <?= number_format($completed) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Withdrawn
                    </span>

                    <strong>
                        <?= number_format($withdrawn) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Completion Rate
                    </span>

                    <strong>
                        <?= (int) $rate ?>%
                    </strong>

                </div>

            </div>

        </div>

    </section>

</div>


<!-- =========================================================
     ACCESS INFORMATION
========================================================= -->

<div class="po-security">

    <i class="fas fa-shield-halved"></i>

    <div>

        <strong>
            Programme Officer scoped cohort
        </strong>

        <span>

            <?php if ($scopeMode === 'programme'): ?>

                Access to this cohort is restricted through
                its assigned Programme Officer programme.

            <?php elseif ($scopeMode === 'cohort'): ?>

                Access to this cohort is restricted directly
                through its Programme Officer assignment.

            <?php else: ?>

                Cohort access follows the Programme Officer
                workspace scope configured by the system.

            <?php endif; ?>

        </span>

    </div>

</div>


<?php

require __DIR__ . '/_layout_end.php';

?>