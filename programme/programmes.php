<?php
/**
 * ================================================
 * INVESTHOOD IT - My Programmes
 * ================================================
 * Role: Programme Manager
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'programmes';
$managerId = (int) ($user['user_id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Fetch Assigned Programmes
|--------------------------------------------------------------------------
*/
$programmes = [];
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.type,
        p.description,
        p.objectives,
        p.duration,
        p.start_date,
        p.end_date,
        p.status,
        p.created_at,
        COUNT(DISTINCT c.id) AS cohort_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status <> 'withdrawn'
                THEN cp.user_id
            END
        ) AS candidate_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'completed'
                THEN cp.user_id
            END
        ) AS completed_count
    FROM programmes p
    LEFT JOIN cohorts c
        ON c.programme_id = p.id
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE p.programme_manager_id = ?
    GROUP BY
        p.id,
        p.name,
        p.type,
        p.description,
        p.objectives,
        p.duration,
        p.start_date,
        p.end_date,
        p.status,
        p.created_at
    ORDER BY
        p.start_date ASC,
        p.id ASC
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
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
        $row['progress'] = $progress;
        $programmes[] = $row;
    }
    $stmt->close();
}
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>My Programmes | Investhood IT</title>
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
    <?php require __DIR__ . '/sidebar.php'; ?>
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
                    My Programmes
                </h1>
            </div>
            <div class="dash-header__right">
                <div class="dash-header__user">
                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'PM+User') ?>&background=1a56db&color=fff&size=80"
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
            <?= $flashes ?>
            <!-- =================================================
                 WELCOME
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        My
                        <span class="text-gradient">
                            Programmes
                        </span>
                    </h1>
                    <p>
                        View and monitor programmes assigned to you.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 PROGRAMME LIST
            ================================================== -->
            <?php if (empty($programmes)): ?>
                <div
                    class="welcome-card"
                    style="margin-top:2rem;"
                >
                    <div class="welcome-card__content">
                        <h3>
                            <i class="fas fa-graduation-cap"></i>
                            No Programmes Assigned
                        </h3>
                        <p>
                            You currently do not have any programmes
                            assigned to you.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div
                    class="overview-grid"
                    style="margin-top:2rem;"
                >
                    <?php foreach ($programmes as $programme): ?>

                        <div class="overview-card">
                            <!-- Programme Icon -->
                            <div class="overview-card__icon overview-card__icon--primary">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <!-- Programme Information -->
                            <div class="overview-card__info">
                                <span
                                    class="overview-card__label"
                                    style="font-size:1rem;font-weight:700;"
                                >
                                    <?= e($programme['name']) ?>
                                </span>
                                <!-- Type -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.5rem;"
                                >
                                    <i class="fas fa-tag"></i>
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
                                <!-- Status -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-circle"></i>
                                    <?= e(
                                        ucfirst(
                                            $programme['status']
                                        )
                                    ) ?>
                                </span>
                                <!-- Candidates / Cohorts -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.5rem;"
                                >
                                    <?= number_format(
                                        (int) $programme['candidate_count']
                                    ) ?>
                                    Candidates
                                    ·
                                    <?= number_format(
                                        (int) $programme['cohort_count']
                                    ) ?>
                                    Cohorts
                                </span>
                                <!-- Progress -->
                                <span
                                    class="overview-card__number"
                                    style="font-size:1.5rem;margin-top:0.75rem;"
                                >
                                    <?= (int) $programme['progress'] ?>%
                                </span>
                                <span class="overview-card__label">
                                    Completion Progress
                                </span>
                                <!-- Dates -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.5rem;"
                                >
                                    <?php if (!empty($programme['start_date'])): ?>
                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $programme['start_date']
                                                )
                                            )
                                        ) ?>
                                    <?php else: ?>
                                        Start date not set
                                    <?php endif; ?>
                                    <?php if (!empty($programme['end_date'])): ?>
                                        -
                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $programme['end_date']
                                                )
                                            )
                                        ) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <a
                            href="<?= url('programme/programme_view.php?id=' . (int) $programme['id']) ?>"
                            class="sidebar__link"
                            style="
                                display:inline-flex;
                                align-items:center;
                                gap:0.5rem;
                                margin-top:1rem;
                            "
                        >
                            <i class="fas fa-eye"></i>
                            View
                        </a>
                        
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>