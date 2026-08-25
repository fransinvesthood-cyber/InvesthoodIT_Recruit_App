<?php
/**
 * ================================================
 * INVESTHOOD IT - Supervisor Reports
 * ================================================
 * Role: Supervisor
 *
 * Reports are restricted to cohorts assigned to
 * the currently authenticated Supervisor through
 * cohorts.supervisor_id.
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
| Report Statistics
|--------------------------------------------------------------------------
*/
$assignedCohorts     = 0;
$totalCandidates     = 0;
$selectedCandidates  = 0;
$onboardedCandidates = 0;
$activeCandidates    = 0;
$completedCandidates = 0;
$withdrawnCandidates = 0;
$completionRate      = 0;
/*
|--------------------------------------------------------------------------
| Assigned Cohorts
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare("
    SELECT COUNT(*) AS total
    FROM cohorts
    WHERE supervisor_id = ?
", 'i', [$supervisorId]);
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$assignedCohorts = (int) ($row['total'] ?? 0);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Candidate Status Summary
|--------------------------------------------------------------------------
|
| Candidates are counted only from cohorts assigned to the
| currently logged-in Supervisor.
|
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare("
    SELECT
        COUNT(DISTINCT CASE
            WHEN cp.status <> 'withdrawn'
            THEN cp.user_id
        END) AS total_candidates,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'selected'
            THEN cp.user_id
        END) AS selected_candidates,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'onboarded'
            THEN cp.user_id
        END) AS onboarded_candidates,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'active'
            THEN cp.user_id
        END) AS active_candidates,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'completed'
            THEN cp.user_id
        END) AS completed_candidates,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'withdrawn'
            THEN cp.user_id
        END) AS withdrawn_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    WHERE c.supervisor_id = ?
", 'i', [$supervisorId]);
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalCandidates     = (int) ($row['total_candidates'] ?? 0);
$selectedCandidates  = (int) ($row['selected_candidates'] ?? 0);
$onboardedCandidates = (int) ($row['onboarded_candidates'] ?? 0);
$activeCandidates    = (int) ($row['active_candidates'] ?? 0);
$completedCandidates = (int) ($row['completed_candidates'] ?? 0);
$withdrawnCandidates = (int) ($row['withdrawn_candidates'] ?? 0);
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
| Cohort Performance
|--------------------------------------------------------------------------
*/
$cohorts = [];
$stmt = Database::prepare("
    SELECT
        c.id,
        c.name AS cohort_name,
        c.start_date,
        c.end_date,
        c.status AS cohort_status,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        COUNT(DISTINCT CASE
            WHEN cp.status <> 'withdrawn'
            THEN cp.user_id
        END) AS candidate_count,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'selected'
            THEN cp.user_id
        END) AS selected_count,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'onboarded'
            THEN cp.user_id
        END) AS onboarded_count,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'active'
            THEN cp.user_id
        END) AS active_count,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'completed'
            THEN cp.user_id
        END) AS completed_count,
        COUNT(DISTINCT CASE
            WHEN cp.status = 'withdrawn'
            THEN cp.user_id
        END) AS withdrawn_count
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
        p.name,
        p.type
    ORDER BY
        c.start_date ASC,
        c.id ASC
", 'i', [$supervisorId]);
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
    $cohorts[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Status Helper
|--------------------------------------------------------------------------
*/
function reportStatusClass(string $status): string
{
    return match (strtolower($status)) {
        'active'     => 'status-active',
        'completed'  => 'status-completed',
        'withdrawn'  => 'status-withdrawn',
        'onboarded'  => 'status-onboarded',
        'selected'   => 'status-selected',
        'open'        => 'status-active',
        'closed'      => 'status-withdrawn',
        default      => 'status-default',
    };
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
        Supervisor Reports | Investhood IT
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
    <style>
        .report-section {
            margin-top: 2rem;
        }
        .report-table-wrapper {
            overflow-x: auto;
            margin-top: 1rem;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }
        .report-table th,
        .report-table td {
            padding: 0.9rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .report-table th {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #6b7280;
            background: #f9fafb;
        }
        .report-table td {
            font-size: 0.9rem;
            color: #374151;
        }
        .report-table tr:last-child td {
            border-bottom: none;
        }
        .report-status {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        .status-completed {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .status-withdrawn {
            background: #fee2e2;
            color: #b91c1c;
        }
        .status-onboarded {
            background: #e0f2fe;
            color: #0369a1;
        }
        .status-selected {
            background: #fef3c7;
            color: #92400e;
        }
        .status-default {
            background: #f3f4f6;
            color: #374151;
        }
        .progress-container {
            min-width: 120px;
        }
        .progress-bar {
            width: 100%;
            height: 7px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }
        .progress-bar__fill {
            height: 100%;
            background: #1a56db;
            border-radius: 999px;
        }
        .progress-value {
            display: block;
            margin-top: 0.3rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .report-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .report-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            color: #374151;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
        }
        .report-button:hover {
            background: #f9fafb;
        }
        @media print {
            .sidebar,
            .dash-header__right,
            .report-actions {
                display: none !important;
            }
            .dashboard__main {
                width: 100%;
            }
            .dash-content {
                padding: 0;
            }
            .welcome-card {
                box-shadow: none;
                border: none;
            }
            .overview-card {
                break-inside: avoid;
            }
            .report-table {
                font-size: 11px;
            }
            body {
                background: #fff;
            }
        }
    </style>
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
                    Supervisor Reports
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
                 REPORT HEADER
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        Supervisor
                        <span class="text-gradient">
                            Reports
                        </span>
                    </h1>
                    <p>
                        Review the performance of your assigned cohorts
                        and monitor candidate progress.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 REPORT ACTIONS
            ================================================== -->
            <div class="report-actions">
                <button
                    type="button"
                    class="report-button"
                    onclick="window.print()"
                >
                    <i class="fas fa-print"></i>
                    Print Report
                </button>
            </div>
            <!-- =================================================
                 OVERVIEW
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:1rem;"
            >
                <!-- Assigned Cohorts -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($assignedCohorts) ?>
                        </span>
                        <span class="overview-card__label">
                            Assigned Cohorts
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
                            Candidates
                        </span>
                    </div>
                </div>
                <!-- Active -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--amber">
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
                    <div class="overview-card__icon overview-card__icon--primary">
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
            </div>
            <!-- =================================================
                 STATUS SUMMARY
            ================================================== -->
            <div class="report-section">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2 style="margin-bottom:0.5rem;">
                            Candidate Status Summary
                        </h2>
                        <p>
                            Current status distribution across your
                            assigned cohorts.
                        </p>
                    </div>
                </div>
                <div
                    class="overview-grid"
                    style="margin-top:1rem;"
                >
                    <div class="overview-card">
                        <div class="overview-card__icon overview-card__icon--amber">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="overview-card__info">
                            <span class="overview-card__number">
                                <?= number_format($selectedCandidates) ?>
                            </span>
                            <span class="overview-card__label">
                                Selected
                            </span>
                        </div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-card__icon overview-card__icon--cyan">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="overview-card__info">
                            <span class="overview-card__number">
                                <?= number_format($onboardedCandidates) ?>
                            </span>
                            <span class="overview-card__label">
                                Onboarded
                            </span>
                        </div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-card__icon overview-card__icon--primary">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="overview-card__info">
                            <span class="overview-card__number">
                                <?= number_format($activeCandidates) ?>
                            </span>
                            <span class="overview-card__label">
                                Active
                            </span>
                        </div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-card__icon overview-card__icon--primary">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="overview-card__info">
                            <span class="overview-card__number">
                                <?= number_format($completedCandidates) ?>
                            </span>
                            <span class="overview-card__label">
                                Completed
                            </span>
                        </div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-card__icon overview-card__icon--amber">
                            <i class="fas fa-user-minus"></i>
                        </div>
                        <div class="overview-card__info">
                            <span class="overview-card__number">
                                <?= number_format($withdrawnCandidates) ?>
                            </span>
                            <span class="overview-card__label">
                                Withdrawn
                            </span>
                        </div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-card__icon overview-card__icon--primary">
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
            </div>
            <!-- =================================================
                 COHORT PERFORMANCE
            ================================================== -->
            <div class="report-section">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2 style="margin-bottom:0.5rem;">
                            Cohort Performance
                        </h2>
                        <p>
                            Performance summary for cohorts assigned
                            to you.
                        </p>
                    </div>
                </div>
                <?php if (empty($cohorts)): ?>
                    <div
                        class="welcome-card"
                        style="margin-top:1rem;"
                    >
                        <div class="welcome-card__content">
                            <h3>
                                No Assigned Cohorts
                            </h3>
                            <p>
                                You currently have no cohorts assigned
                                to you.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="report-table-wrapper">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>
                                        Cohort
                                    </th>
                                    <th>
                                        Programme
                                    </th>
                                    <th>
                                        Status
                                    </th>
                                    <th>
                                        Candidates
                                    </th>
                                    <th>
                                        Selected
                                    </th>
                                    <th>
                                        Onboarded
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
                                        Progress
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cohorts as $cohort): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= e($cohort['cohort_name']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?= e($cohort['programme_name']) ?>
                                    </td>
                                    <td>
                                        <span
                                            class="report-status <?= e(reportStatusClass($cohort['cohort_status'])) ?>"
                                        >
                                            <?= e(
                                                ucfirst(
                                                    $cohort['cohort_status']
                                                )
                                            ) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            (int) $cohort['candidate_count']
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            (int) $cohort['selected_count']
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            (int) $cohort['onboarded_count']
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            (int) $cohort['active_count']
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            (int) $cohort['completed_count']
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= number_format(
                                            (int) $cohort['withdrawn_count']
                                        ) ?>
                                    </td>
                                    <td>
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div
                                                    class="progress-bar__fill"
                                                    style="width:<?= (int) $cohort['progress'] ?>%;"
                                                ></div>
                                            </div>
                                            <span class="progress-value">
                                                <?= (int) $cohort['progress'] ?>%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>