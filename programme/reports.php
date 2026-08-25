<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Manager Reports
 * ================================================
 * Role: Programme Manager
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'reports';
$managerId = (int) ($user['user_id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Programme Statistics
|--------------------------------------------------------------------------
*/
$totalProgrammes = 0;
$activeProgrammes = 0;
$completedProgrammes = 0;
$pausedProgrammes = 0;
$archivedProgrammes = 0;
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_programmes,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_programmes,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_programmes,
        SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) AS paused_programmes,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived_programmes
    FROM programmes
    WHERE programme_manager_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalProgrammes = (int) ($row['total_programmes'] ?? 0);
    $activeProgrammes = (int) ($row['active_programmes'] ?? 0);
    $completedProgrammes = (int) ($row['completed_programmes'] ?? 0);
    $pausedProgrammes = (int) ($row['paused_programmes'] ?? 0);
    $archivedProgrammes = (int) ($row['archived_programmes'] ?? 0);
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Cohort Statistics
|--------------------------------------------------------------------------
*/
$totalCohorts = 0;
$activeCohorts = 0;
$completedCohorts = 0;
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_cohorts,
        SUM(
            CASE
                WHEN c.status = 'active'
                THEN 1
                ELSE 0
            END
        ) AS active_cohorts,
        SUM(
            CASE
                WHEN c.status = 'completed'
                THEN 1
                ELSE 0
            END
        ) AS completed_cohorts
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalCohorts = (int) ($row['total_cohorts'] ?? 0);
    $activeCohorts = (int) ($row['active_cohorts'] ?? 0);
    $completedCohorts = (int) ($row['completed_cohorts'] ?? 0);
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Candidate Statistics
|--------------------------------------------------------------------------
*/
$totalCandidates = 0;
$activeCandidates = 0;
$completedCandidates = 0;
$withdrawnCandidates = 0;
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS total_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status IN ('selected', 'onboarded', 'active')
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
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalCandidates = (int) ($row['total_candidates'] ?? 0);
    $activeCandidates = (int) ($row['active_candidates'] ?? 0);
    $completedCandidates = (int) ($row['completed_candidates'] ?? 0);
    $withdrawnCandidates = (int) ($row['withdrawn_candidates'] ?? 0);
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Overall Completion Rate
|--------------------------------------------------------------------------
*/
$completionRate = 0;
$eligibleCandidates =
    $totalCandidates - $withdrawnCandidates;
if ($eligibleCandidates > 0) {
    $completionRate = round(
        ($completedCandidates / $eligibleCandidates) * 100
    );
}
/*
|--------------------------------------------------------------------------
| Programme Performance
|--------------------------------------------------------------------------
*/
$programmeReports = [];
$stmt = $conn->prepare("
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
    WHERE p.programme_manager_id = ?
    GROUP BY
        p.id,
        p.name,
        p.type,
        p.status,
        p.start_date,
        p.end_date
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
        $programmeReports[] = $row;
    }
    $stmt->close();
}
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
        Reports | Investhood IT
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
                    Programme Reports
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
                        Programme
                        <span class="text-gradient">
                            Reports
                        </span>
                    </h1>
                    <p>
                        Monitor programme delivery, cohort performance,
                        candidate participation and completion.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 PROGRAMME STATISTICS
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
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
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--cyan">
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
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($completedProgrammes) ?>
                        </span>
                        <span class="overview-card__label">
                            Completed Programmes
                        </span>
                    </div>
                </div>
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--amber">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($pausedProgrammes) ?>
                        </span>
                        <span class="overview-card__label">
                            Paused Programmes
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 COHORT / CANDIDATE STATISTICS
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:1rem;"
            >
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
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
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--cyan">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($totalCandidates) ?>
                        </span>
                        <span class="overview-card__label">
                            Total Candidates
                        </span>
                    </div>
                </div>
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
                        <i class="fas fa-user-check"></i>
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
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--amber">
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
                            Performance overview for programmes assigned
                            to you.
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
                            There are currently no programmes assigned
                            to you.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div
                    style="
                        margin-top:1rem;
                        overflow-x:auto;
                    "
                >
                    <table
                        style="
                            width:100%;
                            border-collapse:collapse;
                            background:#fff;
                            border-radius:12px;
                            overflow:hidden;
                        "
                    >
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:1rem;">
                                    Programme
                                </th>
                                <th style="text-align:left;padding:1rem;">
                                    Type
                                </th>
                                <th style="text-align:left;padding:1rem;">
                                    Status
                                </th>
                                <th style="text-align:left;padding:1rem;">
                                    Cohorts
                                </th>
                                <th style="text-align:left;padding:1rem;">
                                    Candidates
                                </th>
                                <th style="text-align:left;padding:1rem;">
                                    Completed
                                </th>
                                <th style="text-align:left;padding:1rem;">
                                    Progress
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programmeReports as $programme): ?>
                                <tr>
                                    <td style="padding:1rem;">
                                        <strong>
                                            <?= e($programme['name']) ?>
                                        </strong>
                                        <?php if (!empty($programme['start_date'])): ?>
                                            <br>
                                            <small>
                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $programme['start_date']
                                                        )
                                                    )
                                                ) ?>
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
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:1rem;">
                                        <?= e(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $programme['type']
                                                )
                                            )
                                        ) ?>
                                    </td>
                                    <td style="padding:1rem;">
                                        <?= e(
                                            ucfirst(
                                                $programme['status']
                                            )
                                        ) ?>
                                    </td>
                                    <td style="padding:1rem;">
                                        <?= number_format(
                                            (int) $programme['cohort_count']
                                        ) ?>
                                    </td>
                                    <td style="padding:1rem;">
                                        <?= number_format(
                                            (int) $programme['candidate_count']
                                        ) ?>
                                    </td>
                                    <td style="padding:1rem;">
                                        <?= number_format(
                                            (int) $programme['completed_count']
                                        ) ?>
                                    </td>
                                    <td style="padding:1rem;">
                                        <strong>
                                            <?= (int) $programme['progress'] ?>%
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <!-- =================================================
                 ADDITIONAL SUMMARY
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h3>
                            Active Candidates
                        </h3>
                        <p>
                            Candidates currently participating in your
                            programmes.
                        </p>
                        <span
                            class="overview-card__number"
                            style="display:block;margin-top:1rem;"
                        >
                            <?= number_format($activeCandidates) ?>
                        </span>
                    </div>
                </div>
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h3>
                            Withdrawn Candidates
                        </h3>
                        <p>
                            Candidates who have withdrawn from your
                            programmes.
                        </p>
                        <span
                            class="overview-card__number"
                            style="display:block;margin-top:1rem;"
                        >
                            <?= number_format($withdrawnCandidates) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>