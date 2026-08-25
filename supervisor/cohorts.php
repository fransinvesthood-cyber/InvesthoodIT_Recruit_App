<?php
/**
 * ================================================
 * INVESTHOOD IT - Supervisor Cohorts
 * ================================================
 * Role: Supervisor
 *
 * Displays cohorts assigned to the logged-in
 * Supervisor through cohorts.supervisor_id.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
$user = current_user();
$flashes = render_flashes();
/*
|--------------------------------------------------------------------------
| Supervisor
|--------------------------------------------------------------------------
*/
$supervisorId = (int) ($user['id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Cohorts
|--------------------------------------------------------------------------
|
| Only cohorts assigned to the logged-in Supervisor are returned.
|
*/
$cohorts = [];
$stmt = Database::prepare("
    SELECT
        c.id,
        c.name,
        c.start_date,
        c.end_date,
        c.status,
        p.id AS programme_id,
        p.name AS programme_name,
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
        ) AS completed_count
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE c.supervisor_id = ?
    GROUP BY
        c.id,
        c.name,
        c.start_date,
        c.end_date,
        c.status,
        p.id,
        p.name
    ORDER BY
        c.start_date ASC,
        c.id ASC
", 'i', [$supervisorId]);
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidateCount = (int) ($row['candidate_count'] ?? 0);
    $completedCount = (int) ($row['completed_count'] ?? 0);
    $progress = 0;
    if ($candidateCount > 0) {
        $progress = round(
            ($completedCount / $candidateCount) * 100
        );
    }
    $row['progress'] = $progress;
    $cohorts[] = $row;
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
        My Cohorts | Supervisor | Investhood IT
    </title>
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >
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
                    My Cohorts
                </h1>
            </div>
            <div class="dash-header__right">
                <div class="dash-header__user">
                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Supervisor') ?>&background=1a56db&color=fff&size=80"
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
                 WELCOME / INTRO
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        My
                        <span class="text-gradient">
                            Cohorts
                        </span>
                    </h1>
                    <p>
                        View and monitor the cohorts assigned to you,
                        including candidate participation and completion
                        progress.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 COHORT SUMMARY
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >
                <!-- Total Cohorts -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(count($cohorts)) ?>
                        </span>
                        <span class="overview-card__label">
                            Assigned Cohorts
                        </span>
                    </div>
                </div>
                <!-- Total Candidates -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?php
                            $totalCandidates = 0;
                            foreach ($cohorts as $cohort) {
                                $totalCandidates += (int) $cohort['candidate_count'];
                            }
                            echo number_format($totalCandidates);
                            ?>
                        </span>
                        <span class="overview-card__label">
                            Candidates
                        </span>
                    </div>
                </div>
                <!-- Active Candidates -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--amber"
                    >
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?php
                            $totalActive = 0;
                            foreach ($cohorts as $cohort) {
                                $totalActive += (int) $cohort['active_count'];
                            }
                            echo number_format($totalActive);
                            ?>
                        </span>
                        <span class="overview-card__label">
                            Active Candidates
                        </span>
                    </div>
                </div>
                <!-- Completed Candidates -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?php
                            $totalCompleted = 0;
                            foreach ($cohorts as $cohort) {
                                $totalCompleted += (int) $cohort['completed_count'];
                            }
                            echo number_format($totalCompleted);
                            ?>
                        </span>
                        <span class="overview-card__label">
                            Completed Candidates
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 COHORT LIST
            ================================================== -->
            <div style="margin-top:2rem;">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2 style="margin-bottom:0.5rem;">
                            Assigned Cohorts
                        </h2>
                        <p>
                            Monitor the programmes and candidates
                            assigned to your cohorts.
                        </p>
                    </div>
                </div>
            </div>
            <?php if (empty($cohorts)): ?>
                <!-- =================================================
                     EMPTY STATE
                ================================================== -->
                <div
                    class="welcome-card"
                    style="margin-top:1rem;"
                >
                    <div class="welcome-card__content">
                        <h3>
                            No Cohorts Assigned
                        </h3>
                        <p>
                            You currently have no cohorts assigned
                            to you.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- =================================================
                     COHORT CARDS
                ================================================== -->
                <div
                    class="overview-grid"
                    style="margin-top:1rem;"
                >
                    <?php foreach ($cohorts as $cohort): ?>
                        <div class="overview-card">
                            <div
                                class="overview-card__icon overview-card__icon--primary"
                            >
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="overview-card__info">
                                <!-- Cohort Name -->
                                <span
                                    class="overview-card__label"
                                    style="font-weight:600;"
                                >
                                    <?= e($cohort['name']) ?>
                                </span>
                                <!-- Programme -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-graduation-cap"></i>
                                    <?= e($cohort['programme_name']) ?>
                                </span>
                                <!-- Dates -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-calendar"></i>
                                    <?php if (!empty($cohort['start_date'])): ?>
                                        <?= e($cohort['start_date']) ?>
                                    <?php else: ?>
                                        Start date not set
                                    <?php endif; ?>
                                    &nbsp;–&nbsp;
                                    <?php if (!empty($cohort['end_date'])): ?>
                                        <?= e($cohort['end_date']) ?>
                                    <?php else: ?>
                                        End date not set
                                    <?php endif; ?>
                                </span>
                                <!-- Status -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-circle"></i>
                                    Status:
                                    <?= e(
                                        ucfirst(
                                            $cohort['status']
                                        )
                                    ) ?>
                                </span>
                                <!-- Candidates -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-users"></i>
                                    <?= number_format(
                                        (int) $cohort['candidate_count']
                                    ) ?>
                                    Candidates
                                </span>
                                <!-- Active -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-user-check"></i>
                                    <?= number_format(
                                        (int) $cohort['active_count']
                                    ) ?>
                                    Active
                                </span>
                                <!-- Completed -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-check-circle"></i>
                                    <?= number_format(
                                        (int) $cohort['completed_count']
                                    ) ?>
                                    Completed
                                </span>
                                <!-- Progress -->
                                <span
                                    class="overview-card__number"
                                    style="
                                        font-size:1.5rem;
                                        margin-top:0.75rem;
                                    "
                                >
                                    <?= (int) $cohort['progress'] ?>%
                                </span>
                                <span class="overview-card__label">
                                    Completion Progress
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