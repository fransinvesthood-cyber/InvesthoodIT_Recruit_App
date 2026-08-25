<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Officer Reports
 * ================================================
 * Role: Programme Officer
 *
 * Provides programme-level reporting for programmes
 * associated with the current Programme Officer.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('programme_officer');

$user = current_user();
$flashes = render_flashes();

$officerId = (int) ($user['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Report Statistics
|--------------------------------------------------------------------------
*/

$totalProgrammes = 0;
$activeProgrammes = 0;
$totalCohorts = 0;
$totalCandidates = 0;
$activeCandidates = 0;
$completedCandidates = 0;
$withdrawnCandidates = 0;
$completionRate = 0;

/*
|--------------------------------------------------------------------------
| Total Programmes
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare("
    SELECT COUNT(*) AS total
    FROM programmes
    WHERE created_by = ?
", 'i', [$officerId]);

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$totalProgrammes = (int) ($row['total'] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Active Programmes
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare("
    SELECT COUNT(*) AS total
    FROM programmes
    WHERE created_by = ?
      AND status = 'active'
", 'i', [$officerId]);

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$activeProgrammes = (int) ($row['total'] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Total Cohorts
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare("
    SELECT COUNT(*) AS total
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.created_by = ?
", 'i', [$officerId]);

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$totalCohorts = (int) ($row['total'] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Total Candidates
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare("
    SELECT COUNT(DISTINCT cp.user_id) AS total
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.created_by = ?
      AND cp.status <> 'withdrawn'
", 'i', [$officerId]);

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$totalCandidates = (int) ($row['total'] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Active Candidates
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare("
    SELECT COUNT(DISTINCT cp.user_id) AS total
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.created_by = ?
      AND cp.status = 'active'
", 'i', [$officerId]);

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$activeCandidates = (int) ($row['total'] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Completed Candidates
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare("
    SELECT COUNT(DISTINCT cp.user_id) AS total
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.created_by = ?
      AND cp.status = 'completed'
", 'i', [$officerId]);

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$completedCandidates = (int) ($row['total'] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Withdrawn Candidates
|--------------------------------------------------------------------------
*/

$stmt = Database::prepare("
    SELECT COUNT(DISTINCT cp.user_id) AS total
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.created_by = ?
      AND cp.status = 'withdrawn'
", 'i', [$officerId]);

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$withdrawnCandidates = (int) ($row['total'] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Completion Rate
|--------------------------------------------------------------------------
*/

if ($totalCandidates > 0) {
    $completionRate = round(
        ($completedCandidates / $totalCandidates) * 100
    );
}

/*
|--------------------------------------------------------------------------
| Programme Report
|--------------------------------------------------------------------------
*/

$programmeReports = [];

$stmt = Database::prepare("
    SELECT
        p.id,
        p.name,
        p.type,
        p.status,
        p.start_date,
        p.end_date,

        COUNT(DISTINCT c.id) AS cohort_count,

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

    FROM programmes p

    LEFT JOIN cohorts c
        ON c.programme_id = p.id

    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id

    WHERE p.created_by = ?

    GROUP BY
        p.id,
        p.name,
        p.type,
        p.status,
        p.start_date,
        p.end_date

    ORDER BY
        p.start_date DESC,
        p.id DESC
", 'i', [$officerId]);

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $candidateCount = (int) $row['candidate_count'];
    $completedCount = (int) $row['completed_count'];

    $progress = 0;

    if ($candidateCount > 0) {
        $progress = round(
            ($completedCount / $candidateCount) * 100
        );
    }

    $row['progress'] = min(
        100,
        max(0, $progress)
    );

    $programmeReports[] = $row;
}

$stmt->close();

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
        Reports | Programme Officer | Investhood IT
    </title>

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
        crossorigin="anonymous"
    >

    <link
        rel="stylesheet"
        href="<?= url('css/styles.css') ?>"
    >

</head>

<body class="dashboard-page">

<div class="dashboard">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <?php require_once __DIR__ . '/sidebar.php'; ?>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <main class="dashboard__main">

        <!-- =================================================
             HEADER
        ================================================== -->

        <header class="dash-header">

            <div class="dash-header__left">

                <h1 class="dash-header__title">
                    Programme Reports
                </h1>

            </div>

            <div class="dash-header__right">

                <div class="dash-header__user">

                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Programme Officer') ?>&background=1a56db&color=fff&size=80"
                        alt=""
                        class="dash-header__avatar"
                    >

                </div>

            </div>

        </header>


        <!-- =================================================
             CONTENT
        ================================================== -->

        <div class="dash-content">

            <!-- =================================================
                 INTRO
            ================================================== -->

            <div class="welcome-card">

                <div class="welcome-card__bg"></div>

                <div class="welcome-card__content">

                    <h1 class="welcome-card__greeting">
                        Programme Reports
                    </h1>

                    <p>
                        Monitor programme performance, cohort activity,
                        candidate participation, and completion progress.
                    </p>

                </div>

            </div>


            <!-- =================================================
                 SUMMARY STATISTICS
            ================================================== -->

            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >

                <!-- Programmes -->

                <div class="overview-card">

                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-graduation-cap"></i>
                    </div>

                    <div class="overview-card__info">

                        <span class="overview-card__number">
                            <?= number_format($totalProgrammes) ?>
                        </span>

                        <span class="overview-card__label">
                            Total Programmes
                        </span>

                    </div>

                </div>


                <!-- Active Programmes -->

                <div class="overview-card">

                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-play-circle"></i>
                    </div>

                    <div class="overview-card__info">

                        <span class="overview-card__number">
                            <?= number_format($activeProgrammes) ?>
                        </span>

                        <span class="overview-card__label">
                            Active Programmes
                        </span>

                    </div>

                </div>


                <!-- Cohorts -->

                <div class="overview-card">

                    <div
                        class="overview-card__icon overview-card__icon--amber"
                    >
                        <i class="fas fa-layer-group"></i>
                    </div>

                    <div class="overview-card__info">

                        <span class="overview-card__number">
                            <?= number_format($totalCohorts) ?>
                        </span>

                        <span class="overview-card__label">
                            Total Cohorts
                        </span>

                    </div>

                </div>


                <!-- Candidates -->

                <div class="overview-card">

                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-users"></i>
                    </div>

                    <div class="overview-card__info">

                        <span class="overview-card__number">
                            <?= number_format($totalCandidates) ?>
                        </span>

                        <span class="overview-card__label">
                            Candidates
                        </span>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 CANDIDATE SUMMARY
            ================================================== -->

            <div style="margin-top:2rem;">

                <div class="welcome-card">

                    <div class="welcome-card__content">

                        <h2 style="margin-bottom:0.5rem;">
                            Candidate Performance
                        </h2>

                        <p>
                            Current candidate participation and completion
                            statistics across your programmes.
                        </p>

                    </div>

                </div>

            </div>


            <div
                class="overview-grid"
                style="margin-top:1rem;"
            >

                <!-- Active -->

                <div class="overview-card">

                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-user-check"></i>
                    </div>

                    <div class="overview-card__info">

                        <span class="overview-card__number">
                            <?= number_format($activeCandidates) ?>
                        </span>

                        <span class="overview-card__label">
                            Active Candidates
                        </span>

                    </div>

                </div>


                <!-- Completed -->

                <div class="overview-card">

                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-user-graduate"></i>
                    </div>

                    <div class="overview-card__info">

                        <span class="overview-card__number">
                            <?= number_format($completedCandidates) ?>
                        </span>

                        <span class="overview-card__label">
                            Completed Candidates
                        </span>

                    </div>

                </div>


                <!-- Withdrawn -->

                <div class="overview-card">

                    <div
                        class="overview-card__icon overview-card__icon--amber"
                    >
                        <i class="fas fa-user-minus"></i>
                    </div>

                    <div class="overview-card__info">

                        <span class="overview-card__number">
                            <?= number_format($withdrawnCandidates) ?>
                        </span>

                        <span class="overview-card__label">
                            Withdrawn Candidates
                        </span>

                    </div>

                </div>


                <!-- Completion -->

                <div class="overview-card">

                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-chart-line"></i>
                    </div>

                    <div class="overview-card__info">

                        <span class="overview-card__number">
                            <?= $completionRate ?>%
                        </span>

                        <span class="overview-card__label">
                            Completion Rate
                        </span>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 PROGRAMME PERFORMANCE
            ================================================== -->

            <div style="margin-top:2rem;">

                <div class="welcome-card">

                    <div class="welcome-card__content">

                        <h2 style="margin-bottom:0.5rem;">
                            Programme Performance
                        </h2>

                        <p>
                            Performance overview for each programme.
                        </p>

                    </div>

                </div>

            </div>


            <?php if (empty($programmeReports)): ?>

                <div
                    class="welcome-card"
                    style="margin-top:1rem;"
                >

                    <div class="welcome-card__content">

                        <h3>
                            No Programme Data
                        </h3>

                        <p>
                            There are currently no programmes available
                            for reporting.
                        </p>

                    </div>

                </div>

            <?php else: ?>

                <div
                    class="overview-grid"
                    style="margin-top:1rem;"
                >

                    <?php foreach ($programmeReports as $programme): ?>

                        <div class="overview-card">

                            <div
                                class="overview-card__icon overview-card__icon--primary"
                            >
                                <i class="fas fa-graduation-cap"></i>
                            </div>

                            <div class="overview-card__info">

                                <span
                                    class="overview-card__label"
                                    style="font-weight:600;"
                                >
                                    <?= e($programme['name']) ?>
                                </span>


                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Type:
                                    <?= e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $programme['type']
                                            )
                                        )
                                    ) ?>
                                </span>


                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Status:
                                    <?= e(
                                        ucfirst(
                                            $programme['status']
                                        )
                                    ) ?>
                                </span>


                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Cohorts:
                                    <?= number_format(
                                        (int) $programme['cohort_count']
                                    ) ?>
                                </span>


                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Candidates:
                                    <?= number_format(
                                        (int) $programme['candidate_count']
                                    ) ?>
                                </span>


                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Active:
                                    <?= number_format(
                                        (int) $programme['active_count']
                                    ) ?>
                                </span>


                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Completed:
                                    <?= number_format(
                                        (int) $programme['completed_count']
                                    ) ?>
                                </span>


                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Withdrawn:
                                    <?= number_format(
                                        (int) $programme['withdrawn_count']
                                    ) ?>
                                </span>


                                <!-- Progress -->

                                <?php
                                $progress = min(
                                    100,
                                    max(
                                        0,
                                        (int) ($programme['progress'] ?? 0)
                                    )
                                );

                                $progressWidth = $progress;
                                ?>

                                <div
                                    style="
                                        margin-top:1rem;
                                        width:100%;
                                        height:8px;
                                        background:#e5e7eb;
                                        border-radius:999px;
                                        overflow:hidden;
                                    "
                                >
                                    <div
                                        style="width: <?= $progressWidth ?>%; height:100%; background:#1a56db; border-radius:999px;"
                                    ></div>
                                </div>

                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.4rem;"
                                >
                                    Completion Progress:
                                    <?= $progress ?>%
                                </span>


                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.4rem;"
                                >
                                    Completion Progress:
                                    <?= $progress ?>%
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>

</html>