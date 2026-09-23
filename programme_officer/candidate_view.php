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

$currentPage = 'candidate_view';
$pageTitle = 'Candidate Details';

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

/*
|--------------------------------------------------------------------------
| Request Parameters
|--------------------------------------------------------------------------
*/

$candidateId = (int) ($_GET['id'] ?? 0);
$cohortId = (int) ($_GET['cohort_id'] ?? 0);

if (
    $officerId <= 0
    || $candidateId <= 0
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

/*
|--------------------------------------------------------------------------
| Ensure A Valid Scope Exists
|--------------------------------------------------------------------------
*/

if ($scopeMode === 'none') {
    http_response_code(403);
    exit('Programme Officer access scope is unavailable.');
}

/*
|--------------------------------------------------------------------------
| Candidate
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,

        cp.status AS participant_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,

        c.id AS cohort_id,
        c.name AS cohort_name,
        c.status AS cohort_status,
        c.start_date AS cohort_start_date,
        c.end_date AS cohort_end_date,

        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type

    FROM cohort_participants cp

    INNER JOIN users u
        ON u.id = cp.user_id

    INNER JOIN cohorts c
        ON c.id = cp.cohort_id

    INNER JOIN programmes p
        ON p.id = c.programme_id

    WHERE u.id = ?
      AND c.id = ?
      AND {$scopeCondition}

    LIMIT 1
";

/*
|--------------------------------------------------------------------------
| Prepare Query
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!$stmt) {

    throw new RuntimeException(
        'Unable to prepare candidate query: '
        . $conn->error
    );
}

/*
|--------------------------------------------------------------------------
| Bind Parameters
|--------------------------------------------------------------------------
|
| po_scope() currently contributes one Programme Officer placeholder.
| Therefore:
|
| 1. Candidate ID
| 2. Cohort ID
| 3. Programme Officer ID
|
*/

$stmt->bind_param(
    'iii',
    $candidateId,
    $cohortId,
    $officerId
);

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    $error = $stmt->error;

    $stmt->close();

    throw new RuntimeException(
        'Unable to load candidate: '
        . $error
    );
}

$result = $stmt->get_result();

$candidate = $result->fetch_assoc();

$stmt->close();

/*
|--------------------------------------------------------------------------
| Candidate Not Available / Outside Officer Scope
|--------------------------------------------------------------------------
*/

if (!$candidate) {

    http_response_code(404);

    exit(
        'Candidate not found or you do not have permission '
        . 'to access this candidate.'
    );
}

/*
|--------------------------------------------------------------------------
| Candidate Name
|--------------------------------------------------------------------------
*/

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

$candidateName = trim(
    $firstName . ' ' . $lastName
);

if ($candidateName === '') {
    $candidateName = 'Candidate';
}

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

    <a
        href="<?= url(
            'programme_officer/candidates.php'
        ) ?>"
    >
        Candidates
    </a>

    <i class="fas fa-chevron-right"></i>

    <span>
        <?= e($candidateName) ?>
    </span>

</div>


<!-- =========================================================
     CANDIDATE PROFILE
========================================================= -->

<div class="po-profile-hero">

    <div class="po-profile-hero__avatar">

        <?= e(
            po_initials(
                $firstName,
                $lastName
            )
        ) ?>

    </div>


    <div class="po-profile-hero__copy">

        <span class="po-profile-hero__eyebrow">

            <?= e(
                $candidate['programme_name']
            ) ?>

        </span>


        <h2>

            <?= e(
                $candidateName
            ) ?>

        </h2>


        <p>

            <?= e(
                (string) (
                    $candidate['email']
                    ?? ''
                )
            ) ?>

        </p>


        <div class="po-profile-hero__badges">

            <span
                class="
                    po-status
                    po-status--<?= e(
                        po_status_class(
                            $candidate[
                                'participant_status'
                            ]
                        )
                    ) ?>
                "
            >

                <?= e(
                    po_status_label(
                        $candidate[
                            'participant_status'
                        ]
                    )
                ) ?>

            </span>


            <span class="po-chip">

                <i class="fas fa-layer-group"></i>

                <?= e(
                    $candidate['cohort_name']
                ) ?>

            </span>

        </div>

    </div>

</div>


<!-- =========================================================
     DETAILS GRID
========================================================= -->

<div class="po-grid po-grid--2">


    <!-- =====================================================
         PROGRAMME PLACEMENT
    ====================================================== -->

    <section class="po-card">

        <div class="po-card__header">

            <div>

                <h3>
                    Programme Placement
                </h3>

                <p>
                    Current programme and cohort context.
                </p>

            </div>

        </div>


        <div class="po-card__body">

            <div class="po-detail-list">


                <!-- PROGRAMME -->

                <div>

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


                <!-- PROGRAMME TYPE -->

                <div>

                    <span>
                        Programme Type
                    </span>

                    <strong>

                        <?= e(
                            (string) (
                                $candidate[
                                    'programme_type'
                                ]
                                ?? '—'
                            )
                        ) ?>

                    </strong>

                </div>


                <!-- COHORT -->

                <div>

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


                <!-- COHORT STATUS -->

                <div>

                    <span>
                        Cohort Status
                    </span>

                    <strong>

                        <?= e(
                            po_status_label(
                                $candidate[
                                    'cohort_status'
                                ]
                            )
                        ) ?>

                    </strong>

                </div>


                <!-- START DATE -->

                <div>

                    <span>
                        Cohort Start
                    </span>

                    <strong>

                        <?= e(
                            po_date(
                                $candidate[
                                    'cohort_start_date'
                                ]
                            )
                        ) ?>

                    </strong>

                </div>


                <!-- END DATE -->

                <div>

                    <span>
                        Cohort End
                    </span>

                    <strong>

                        <?= e(
                            po_date(
                                $candidate[
                                    'cohort_end_date'
                                ]
                            )
                        ) ?>

                    </strong>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         CANDIDATE TIMELINE
    ====================================================== -->

    <section class="po-card">

        <div class="po-card__header">

            <div>

                <h3>
                    Candidate Timeline
                </h3>

                <p>
                    Recorded participation milestones.
                </p>

            </div>

        </div>


        <div class="po-card__body">

            <div class="po-timeline">

                <?php

                $timeline = [

                    [
                        'Selected',
                        $candidate['selected_at']
                    ],

                    [
                        'Onboarded',
                        $candidate['onboarded_at']
                    ],

                    [
                        'Completed',
                        $candidate['completed_at']
                    ],

                ];

                ?>


                <?php foreach (
                    $timeline as $item
                ): ?>

                    <?php

                    $timelineLabel =
                        $item[0];

                    $timelineDate =
                        $item[1];

                    ?>

                    <div
                        class="
                            po-timeline__item
                            <?= $timelineDate
                                ? 'is-complete'
                                : ''
                            ?>
                        "
                    >

                        <span class="po-timeline__dot">

                            <i class="fas fa-check"></i>

                        </span>


                        <div>

                            <strong>

                                <?= e(
                                    $timelineLabel
                                ) ?>

                            </strong>

                            <span>

                                <?= e(
                                    po_datetime(
                                        $timelineDate
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

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
            Read-only candidate view
        </strong>

        <span>
            Candidate information is restricted to programmes
            available within your Programme Officer scope.
        </span>

    </div>

</div>


<?php

require __DIR__ . '/_layout_end.php';

?>