<?php
/**
 * ================================================================
 * INVESTHOOD IT - Programme Manager Dashboard
 * ================================================================
 * Role: Programme Manager
 *
 * Purpose:
 * - Portfolio-level programme management
 * - Programme statistics
 * - Cohort statistics
 * - Candidate statistics
 * - Programme progress
 * - Upcoming programme dates
 * - Recent programme activity
 *
 * Data relationships:
 *
 * users
 *   -> programmes.programme_manager_id
 *   -> cohorts.programme_id
 *   -> cohort_participants.cohort_id
 *
 * IMPORTANT:
 * All programme-related data is restricted to the
 * currently logged-in Programme Manager.
 * ================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$conn = Database::getConnection();
$currentPage = 'dashboard';
/*
|--------------------------------------------------------------------------
| Current Programme Manager
|--------------------------------------------------------------------------
|
| current_user() returns:
|
| user_id
| username
| fullname
| email
| role
| role_name
|
| Your current Programme Manager has user_id = 2.
|
*/
$managerId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Initialise Dashboard Statistics
|--------------------------------------------------------------------------
*/
$totalProgrammes      = 0;
$activeProgrammes     = 0;
$draftProgrammes      = 0;
$pausedProgrammes     = 0;
$completedProgrammes  = 0;
$totalCohorts         = 0;
$totalCandidates      = 0;
$activeCandidates     = 0;
$completedCandidates  = 0;
$withdrawnCandidates  = 0;
/*
|--------------------------------------------------------------------------
| Manager Validation
|--------------------------------------------------------------------------
*/
if ($managerId <= 0) {
    die('Unable to determine the logged-in Programme Manager.');
}
/*
|--------------------------------------------------------------------------
| Programme Statistics
|--------------------------------------------------------------------------
|
| These statistics are based ONLY on programmes assigned
| to the logged-in Programme Manager.
|
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_programmes,
        SUM(
            CASE
                WHEN status = 'active'
                THEN 1
                ELSE 0
            END
        ) AS active_programmes,
        SUM(
            CASE
                WHEN status = 'draft'
                THEN 1
                ELSE 0
            END
        ) AS draft_programmes,
        SUM(
            CASE
                WHEN status = 'paused'
                THEN 1
                ELSE 0
            END
        ) AS paused_programmes,
        SUM(
            CASE
                WHEN status = 'completed'
                THEN 1
                ELSE 0
            END
        ) AS completed_programmes
    FROM programmes
    WHERE programme_manager_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalProgrammes = (int) (
            $row['total_programmes'] ?? 0
        );
        $activeProgrammes = (int) (
            $row['active_programmes'] ?? 0
        );
        $draftProgrammes = (int) (
            $row['draft_programmes'] ?? 0
        );
        $pausedProgrammes = (int) (
            $row['paused_programmes'] ?? 0
        );
        $completedProgrammes = (int) (
            $row['completed_programmes'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Cohort Statistics
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT c.id) AS total_cohorts
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalCohorts = (int) (
            $row['total_cohorts'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Total Candidates
|--------------------------------------------------------------------------
|
| DISTINCT user_id prevents the same candidate from being
| counted more than once if they appear in multiple records.
|
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS total_candidates
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
    if ($row = $result->fetch_assoc()) {
        $totalCandidates = (int) (
            $row['total_candidates'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Active Candidates
|--------------------------------------------------------------------------
|
| Active lifecycle statuses currently used by your system:
|
| selected
| onboarded
| active
|
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS active_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
      AND cp.status IN (
          'selected',
          'onboarded',
          'active'
      )
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $activeCandidates = (int) (
            $row['active_candidates'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Completed Candidates
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS completed_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
      AND cp.status = 'completed'
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $completedCandidates = (int) (
            $row['completed_candidates'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Withdrawn Candidates
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS withdrawn_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
      AND cp.status = 'withdrawn'
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $withdrawnCandidates = (int) (
            $row['withdrawn_candidates'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Overall Completion Percentage
|--------------------------------------------------------------------------
*/
$overallProgress = 0;
if ($totalCandidates > 0) {
    $overallProgress = round(
        ($completedCandidates / $totalCandidates) * 100
    );
}
$overallProgress = min(
    100,
    max(
        0,
        $overallProgress
    )
);
/*
|--------------------------------------------------------------------------
| Fetch Programme Portfolio
|--------------------------------------------------------------------------
|
| This is the main management table.
|
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
        p.programme_manager_id,
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
                WHEN cp.status IN (
                    'selected',
                    'onboarded',
                    'active'
                )
                THEN cp.user_id
            END
        ) AS active_candidate_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'completed'
                THEN cp.user_id
            END
        ) AS completed_candidate_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'withdrawn'
                THEN cp.user_id
            END
        ) AS withdrawn_candidate_count
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
        p.programme_manager_id,
        p.created_at
    ORDER BY
        CASE
            WHEN p.status = 'active' THEN 1
            WHEN p.status = 'draft' THEN 2
            WHEN p.status = 'paused' THEN 3
            WHEN p.status = 'completed' THEN 4
            WHEN p.status = 'archived' THEN 5
            ELSE 6
        END,
        p.start_date ASC,
        p.id ASC
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $candidateCount = (int) (
            $row['candidate_count'] ?? 0
        );
        $completedCount = (int) (
            $row['completed_candidate_count'] ?? 0
        );
        $progress = 0;
        if ($candidateCount > 0) {
            $progress = round(
                ($completedCount / $candidateCount) * 100
            );
        }
        $progress = min(
            100,
            max(
                0,
                $progress
            )
        );
        /*
        |--------------------------------------------------------------------------
        | Precalculate Progress Width
        |--------------------------------------------------------------------------
        |
        | We deliberately calculate this in PHP instead of putting
        | min()/casts directly inside HTML attributes.
        |
        | This avoids the "identifier expected" issue you encountered.
        |
        */
        $row['progress'] = $progress;
        $row['progress_width'] = $progress;
        $programmes[] = $row;
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Upcoming Programmes
|--------------------------------------------------------------------------
*/
$upcomingProgrammes = [];
$stmt = $conn->prepare("
    SELECT
        id,
        name,
        type,
        start_date,
        end_date,
        status
    FROM programmes
    WHERE programme_manager_id = ?
      AND start_date IS NOT NULL
    ORDER BY start_date ASC
    LIMIT 5
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $upcomingProgrammes[] = $row;
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Helper Values
|--------------------------------------------------------------------------
*/
$managerName = $user['fullname'] ?? 'Programme Manager';
if (trim($managerName) === '') {
    $managerName = 'Programme Manager';
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
        Programme Manager Dashboard | Investhood IT
    </title>
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
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
    <style>
        .pm-stat-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(220px, 1fr)
            );
            gap: 1rem;
            margin-top: 2rem;
        }
        .pm-section {
            margin-top: 2rem;
        }
        .pm-section-header {
            margin-bottom: 1rem;
        }
        .pm-section-header h2 {
            margin-bottom: 0.35rem;
        }
        .pm-section-header p {
            margin: 0;
        }
        .pm-programme-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(300px, 1fr)
            );
            gap: 1rem;
        }
        .pm-programme-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 4px 18px rgba(
                0,
                0,
                0,
                0.06
            );
        }
        .pm-programme-card__header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
        }
        .pm-programme-card__title {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.4;
        }
        .pm-programme-card__meta {
            margin-top: 0.5rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .pm-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .pm-badge--active {
            background: #dcfce7;
            color: #166534;
        }
        .pm-badge--draft {
            background: #fef3c7;
            color: #92400e;
        }
        .pm-badge--paused {
            background: #e0e7ff;
            color: #3730a3;
        }
        .pm-badge--completed {
            background: #dbeafe;
            color: #1e40af;
        }
        .pm-badge--archived {
            background: #e5e7eb;
            color: #374151;
        }
        .pm-progress {
            margin-top: 1rem;
        }
        .pm-progress__header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.45rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .pm-progress__track {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }
        .pm-progress__bar {
            height: 100%;
            background: #1a56db;
            border-radius: 999px;
        }
        .pm-programme-stats {
            display: grid;
            grid-template-columns: repeat(
                2,
                1fr
            );
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .pm-programme-stat {
            background: #f8fafc;
            border-radius: 10px;
            padding: 0.75rem;
        }
        .pm-programme-stat strong {
            display: block;
            font-size: 1.1rem;
        }
        .pm-programme-stat span {
            color: #6b7280;
            font-size: 0.78rem;
        }
        .pm-action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .pm-action {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 0.85rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            background: #1a56db;
            color: #fff;
        }
        .pm-action--secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .pm-upcoming-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .pm-upcoming-item:last-child {
            border-bottom: 0;
        }
        .pm-date {
            font-weight: 700;
            color: #1a56db;
            white-space: nowrap;
        }
        .pm-empty {
            padding: 2rem;
            text-align: center;
            color: #6b7280;
        }
        @media (max-width: 700px) {
            .pm-programme-grid {
                grid-template-columns: 1fr;
            }
            .pm-programme-card__header {
                flex-direction: column;
            }
            .pm-upcoming-item {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard">
    <!-- =========================================================
         SIDEBAR
    ========================================================== -->
    <?php require __DIR__ . '/sidebar.php'; ?>
    <!-- =========================================================
         MAIN CONTENT
    ========================================================== -->
    <main class="dashboard__main">
        <!-- =====================================================
             HEADER
        ====================================================== -->
        <header class="dash-header">
            <div class="dash-header__left">
                <h1 class="dash-header__title">
                    Dashboard
                </h1>
            </div>
            <div class="dash-header__right">
                <div class="dash-header__user">
                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($managerName) ?>&background=1a56db&color=fff&size=80"
                        alt=""
                        class="dash-header__avatar"
                    >
                </div>
            </div>
        </header>
        <!-- =====================================================
             CONTENT
        ====================================================== -->
        <div class="dash-content">
            <!-- =================================================
                 WELCOME
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        Welcome,
                        <span class="text-gradient">
                            <?= e($managerName) ?>
                        </span>
                    </h1>
                    <p>
                        Manage and monitor the programmes,
                        cohorts, candidates, and overall programme
                        performance under your responsibility.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 PROGRAMME PORTFOLIO
            ================================================== -->
            <div class="pm-stat-grid">
                <!-- Total Programmes -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($totalProgrammes) ?>
                        </span>
                        <span class="overview-card__label">
                            My Programmes
                        </span>
                    </div>
                </div>
                <!-- Active Programmes -->
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
                <!-- Draft Programmes -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--amber">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($draftProgrammes) ?>
                        </span>
                        <span class="overview-card__label">
                            Draft Programmes
                        </span>
                    </div>
                </div>
                <!-- Completed Programmes -->
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
            </div>
            <!-- =================================================
                 PEOPLE / COHORT STATISTICS
            ================================================== -->
            <div class="pm-stat-grid">
                <!-- Cohorts -->
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
                <!-- Candidates -->
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
                <!-- Active Candidates -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
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
                    <div class="overview-card__icon overview-card__icon--amber">
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
                    <div class="overview-card__icon overview-card__icon--amber">
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
                <!-- Overall Progress -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= (int) $overallProgress ?>%
                        </span>
                        <span class="overview-card__label">
                            Overall Completion
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 PROGRAMME PORTFOLIO
            ================================================== -->
            <section class="pm-section">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <div class="pm-section-header">
                            <h2>
                                <i class="fas fa-briefcase"></i>
                                My Programme Portfolio
                            </h2>
                            <p>
                                Programmes currently assigned to
                                you as Programme Manager.
                            </p>
                        </div>
                    </div>
                </div>
                <?php if (empty($programmes)): ?>
                    <div
                        class="welcome-card"
                        style="margin-top:1rem;"
                    >
                        <div class="welcome-card__content pm-empty">
                            <i
                                class="fas fa-graduation-cap"
                                style="font-size:2rem;"
                            ></i>
                            <h3>
                                No Programmes Assigned
                            </h3>
                            <p>
                                There are currently no programmes
                                assigned to your Programme Manager
                                account.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div
                        class="pm-programme-grid"
                        style="margin-top:1rem;"
                    >
                        <?php foreach ($programmes as $programme): ?>
                            <?php
                            $programmeStatus = strtolower(
                                $programme['status'] ?? ''
                            );
                            $statusClass =
                                'pm-badge--'
                                . $programmeStatus;
                            $progressWidth =
                                (int) (
                                    $programme['progress_width'] ?? 0
                                );
                            ?>
                            <div class="pm-programme-card">
                                <!-- ============================
                                     CARD HEADER
                                ============================= -->
                                <div class="pm-programme-card__header">
                                    <div>
                                        <div class="pm-programme-card__title">
                                            <?= e(
                                                $programme['name']
                                            ) ?>
                                        </div>
                                        <div class="pm-programme-card__meta">
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
                                        </div>
                                    </div>
                                    <span
                                        class="pm-badge <?= e($statusClass) ?>"
                                    >
                                        <?= e(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $programmeStatus
                                                )
                                            )
                                        ) ?>
                                    </span>
                                </div>
                                <!-- ============================
                                     DATES
                                ============================= -->
                                <div
                                    class="pm-programme-card__meta"
                                    style="margin-top:1rem;"
                                >
                                    <i class="fas fa-calendar"></i>
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
                                </div>
                                <!-- ============================
                                     PROGRAMME STATISTICS
                                ============================= -->
                                <div class="pm-programme-stats">
                                    <div class="pm-programme-stat">
                                        <strong>
                                            <?= number_format(
                                                (int) $programme['cohort_count']
                                            ) ?>
                                        </strong>
                                        <span>
                                            Cohorts
                                        </span>
                                    </div>
                                    <div class="pm-programme-stat">
                                        <strong>
                                            <?= number_format(
                                                (int) $programme['candidate_count']
                                            ) ?>
                                        </strong>
                                        <span>
                                            Candidates
                                        </span>
                                    </div>
                                    <div class="pm-programme-stat">
                                        <strong>
                                            <?= number_format(
                                                (int) $programme['active_candidate_count']
                                            ) ?>
                                        </strong>
                                        <span>
                                            Active
                                        </span>
                                    </div>
                                    <div class="pm-programme-stat">
                                        <strong>
                                            <?= number_format(
                                                (int) $programme['completed_candidate_count']
                                            ) ?>
                                        </strong>
                                        <span>
                                            Completed
                                        </span>
                                    </div>
                                </div>
                                <!-- ============================
                                     PROGRESS
                                ============================= -->
                                <div class="pm-progress">
                                    <div class="pm-progress__header">
                                        <span>
                                            Completion Progress
                                        </span>
                                        <strong>
                                            <?= (int) $programme['progress'] ?>%
                                        </strong>
                                    </div>
                                    <div class="pm-progress__track">
                                        <div
                                            class="pm-progress__bar"
                                            style="width:<?= $progressWidth ?>%;"
                                        ></div>
                                    </div>
                                </div>
                                <!-- ============================
                                     ACTIONS
                                ============================= -->
                                <div class="pm-action-row">
                                    <a
                                        href="<?= url(
                                            'programme/programme_view.php?id='
                                            . (int) $programme['id']
                                        ) ?>"
                                        class="pm-action"
                                    >
                                        <i class="fas fa-eye"></i>
                                        View Programme
                                    </a>
                                    <a
                                        href="<?= url(
                                            'programme/cohorts.php?programme_id='
                                            . (int) $programme['id']
                                        ) ?>"
                                        class="pm-action pm-action--secondary"
                                    >
                                        <i class="fas fa-layer-group"></i>
                                        Cohorts
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <!-- =================================================
                 UPCOMING PROGRAMMES
            ================================================== -->
            <section class="pm-section">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <div class="pm-section-header">
                            <h2>
                                <i class="fas fa-calendar-alt"></i>
                                Programme Schedule
                            </h2>
                            <p>
                                Upcoming programme start dates
                                within your portfolio.
                            </p>
                        </div>
                        <?php if (empty($upcomingProgrammes)): ?>
                            <div class="pm-empty">
                                No programme dates are currently
                                available.
                            </div>
                        <?php else: ?>
                            <div>
                                <?php foreach (
                                    $upcomingProgrammes
                                    as $upcoming
                                ): ?>
                                    <div class="pm-upcoming-item">
                                        <div>
                                            <strong>
                                                <?= e(
                                                    $upcoming['name']
                                                ) ?>
                                            </strong>
                                            <div
                                                style="
                                                    margin-top:0.25rem;
                                                    color:#6b7280;
                                                    font-size:0.85rem;
                                                "
                                            >
                                                <?= e(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $upcoming['type']
                                                        )
                                                    )
                                                ) ?>
                                                ·
                                                <?= e(
                                                    ucfirst(
                                                        $upcoming['status']
                                                    )
                                                ) ?>
                                            </div>
                                        </div>
                                        <div class="pm-date">
                                            <?php if (
                                                !empty(
                                                    $upcoming['start_date']
                                                )
                                            ): ?>
                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $upcoming[
                                                                'start_date'
                                                            ]
                                                        )
                                                    )
                                                ) ?>
                                            <?php else: ?>
                                                Date not set
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <!-- =================================================
                 MANAGEMENT SUMMARY
            ================================================== -->
            <section class="pm-section">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2>
                            <i class="fas fa-chart-pie"></i>
                            Management Summary
                        </h2>
                        <p style="margin-top:0.5rem;">
                            You currently manage
                            <strong>
                                <?= number_format($totalProgrammes) ?>
                            </strong>
                            programme(s) across
                            <strong>
                                <?= number_format($totalCohorts) ?>
                            </strong>
                            cohort(s).
                            <?php if ($totalCandidates > 0): ?>
                                There are currently
                                <strong>
                                    <?= number_format($totalCandidates) ?>
                                </strong>
                                candidate(s) participating in
                                your programmes.
                            <?php else: ?>
                                No candidates have currently been
                                assigned to your programme cohorts.
                            <?php endif; ?>
                        </p>
                        <div
                            style="
                                color:#374151;
                                margin-top:1.25rem;
                                padding:1rem;
                                background:#f8fafc;
                                border-radius:10px;
                            "
                        >
                            <strong>
                                Portfolio Completion
                            </strong>
                            <div
                                style="
                                    margin-top:0.6rem;
                                    width:100%;
                                    height:10px;
                                    background:#e5e7eb;
                                    border-radius:999px;
                                    overflow:hidden;
                                "
                            >
                                <div
                                    style="
                                        width:<?= (int) $overallProgress ?>%;
                                        height:100%;
                                        background:#1a56db;
                                        border-radius:999px;
                                    "
                                ></div>
                            </div>
                            <div
                                style="
                                    margin-top:0.4rem;
                                    font-size:0.85rem;
                                    color:#6b7280;
                                "
                            >
                                <?= (int) $overallProgress ?>%
                                overall candidate completion
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>