<?php
/**
 * ================================================================
 * INVESTHOOD IT - Programme Manager Candidates
 * ================================================================
 * File:
 *     programme/candidates.php
 *
 * Role:
 *     Programme Manager
 *
 * Purpose:
 *     Displays candidates participating in programmes managed
 *     by the currently logged-in Programme Manager.
 *
 * Database structure:
 *     users
 *     programmes
 *     cohorts
 *     cohort_participants
 * ================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'candidates';
/*
|--------------------------------------------------------------------------
| Current Programme Manager
|--------------------------------------------------------------------------
*/
$managerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$programmeId = (int) ($_GET['programme_id'] ?? 0);
$cohortId = (int) ($_GET['cohort_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
/*
|--------------------------------------------------------------------------
| Allowed Candidate Statuses
|--------------------------------------------------------------------------
|
| These correspond to the cohort_participants.status field.
|--------------------------------------------------------------------------
*/
$allowedStatuses = [
    'selected',
    'onboarded',
    'active',
    'completed',
    'withdrawn'
];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}
/*
|--------------------------------------------------------------------------
| Manager's Programmes
|--------------------------------------------------------------------------
*/
$programmes = [];
$stmt = $conn->prepare("
    SELECT
        id,
        name,
        type,
        status
    FROM programmes
    WHERE programme_manager_id = ?
    ORDER BY name ASC
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $programmes[] = $row;
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Manager's Cohorts
|--------------------------------------------------------------------------
*/
$cohorts = [];
$stmt = $conn->prepare("
    SELECT
        c.id,
        c.name,
        c.programme_id,
        p.name AS programme_name
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
    ORDER BY
        p.name ASC,
        c.name ASC
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cohorts[] = $row;
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Candidate Statistics
|--------------------------------------------------------------------------
*/
$totalCandidates = 0;
$selectedCandidates = 0;
$onboardedCandidates = 0;
$activeCandidates = 0;
$completedCandidates = 0;
$withdrawnCandidates = 0;
/*
|--------------------------------------------------------------------------
| Base Statistics Query
|--------------------------------------------------------------------------
*/
$statsSql = "
    SELECT
        COUNT(DISTINCT cp.user_id) AS total_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'selected'
                THEN cp.user_id
            END
        ) AS selected_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'onboarded'
                THEN cp.user_id
            END
        ) AS onboarded_candidates,
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
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    INNER JOIN users u
        ON u.id = cp.user_id
    WHERE p.programme_manager_id = ?
";
$statsTypes = 'i';
$statsParams = [$managerId];
/*
|--------------------------------------------------------------------------
| Programme Filter
|--------------------------------------------------------------------------
*/
if ($programmeId > 0) {
    $statsSql .= "
        AND p.id = ?
    ";
    $statsTypes .= 'i';
    $statsParams[] = $programmeId;
}
/*
|--------------------------------------------------------------------------
| Cohort Filter
|--------------------------------------------------------------------------
*/
if ($cohortId > 0) {
    $statsSql .= "
        AND c.id = ?
    ";
    $statsTypes .= 'i';
    $statsParams[] = $cohortId;
}
/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/
if ($status !== '') {
    $statsSql .= "
        AND cp.status = ?
    ";
    $statsTypes .= 's';
    $statsParams[] = $status;
}
/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/
if ($search !== '') {
    $statsSql .= "
        AND (
            u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $statsTypes .= 'ssss';
    $statsParams[] = $searchValue;
    $statsParams[] = $searchValue;
    $statsParams[] = $searchValue;
    $statsParams[] = $searchValue;
}
/*
|--------------------------------------------------------------------------
| Execute Statistics
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    $statsSql,
    $statsTypes,
    $statsParams
);
if ($stmt) {
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalCandidates = (int) ($row['total_candidates'] ?? 0);
        $selectedCandidates = (int) ($row['selected_candidates'] ?? 0);
        $onboardedCandidates = (int) ($row['onboarded_candidates'] ?? 0);
        $activeCandidates = (int) ($row['active_candidates'] ?? 0);
        $completedCandidates = (int) ($row['completed_candidates'] ?? 0);
        $withdrawnCandidates = (int) ($row['withdrawn_candidates'] ?? 0);
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Fetch Candidates
|--------------------------------------------------------------------------
*/
$candidates = [];
$sql = "
    SELECT
        cp.id AS participant_id,
        cp.user_id,
        cp.cohort_id,
        cp.status AS participation_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,
        cp.created_at,
        cp.updated_at,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.status AS user_status,
        c.name AS cohort_name,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.status AS programme_status
    FROM cohort_participants cp
    INNER JOIN users u
        ON u.id = cp.user_id
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
";
$types = 'i';
$params = [$managerId];
/*
|--------------------------------------------------------------------------
| Programme Filter
|--------------------------------------------------------------------------
*/
if ($programmeId > 0) {
    $sql .= "
        AND p.id = ?
    ";
    $types .= 'i';
    $params[] = $programmeId;
}
/*
|--------------------------------------------------------------------------
| Cohort Filter
|--------------------------------------------------------------------------
*/
if ($cohortId > 0) {
    $sql .= "
        AND c.id = ?
    ";
    $types .= 'i';
    $params[] = $cohortId;
}
/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/
if ($status !== '') {
    $sql .= "
        AND cp.status = ?
    ";
    $types .= 's';
    $params[] = $status;
}
/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/
if ($search !== '') {
    $sql .= "
        AND (
            u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $types .= 'ssss';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}
/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/
$sql .= "
    ORDER BY
        u.first_name ASC,
        u.last_name ASC,
        p.name ASC,
        c.name ASC
";
/*
|--------------------------------------------------------------------------
| Execute Candidate Query
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    $sql,
    $types,
    $params
);
if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Helper - Status Label
|--------------------------------------------------------------------------
*/
function candidate_status_label($status)
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}
/*
|--------------------------------------------------------------------------
| Helper - Status Class
|--------------------------------------------------------------------------
*/
function candidate_status_class($status)
{
    switch ($status) {
        case 'active':
            return 'status-active';
        case 'onboarded':
            return 'status-onboarded';
        case 'selected':
            return 'status-selected';
        case 'completed':
            return 'status-completed';
        case 'withdrawn':
            return 'status-withdrawn';
        default:
            return 'status-default';
    }
}
/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/
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
    <title>
        Candidates | Programme Manager | Investhood IT
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
        .candidate-filters {
            display: grid;
            grid-template-columns:
                minmax(220px, 1fr)
                minmax(180px, 220px)
                minmax(180px, 220px)
                minmax(180px, 220px)
                auto;
            gap: 1rem;
            align-items: end;
        }
        .filter-group label {
            display: block;
            margin-bottom: .45rem;
            font-size: .85rem;
            font-weight: 600;
        }
        .filter-control {
            width: 100%;
            padding: .75rem .85rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
        }
        .candidate-table-wrapper {
            margin-top: 1rem;
            overflow-x: auto;
            background: #fff;
            border-radius: 12px;
        }
        .candidate-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }
        .candidate-table th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .candidate-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .candidate-table tr:hover {
            background: #f8fafc;
        }
        .candidate-name {
            font-weight: 700;
        }
        .candidate-contact {
            font-size: .8rem;
            color: #6b7280;
            margin-top: .2rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: .35rem .7rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
        }
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        .status-onboarded {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-selected {
            background: #fef3c7;
            color: #92400e;
        }
        .status-completed {
            background: #e0e7ff;
            color: #3730a3;
        }
        .status-withdrawn {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-default {
            background: #f3f4f6;
            color: #374151;
        }
        .candidate-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            text-decoration: none;
            background: #eff6ff;
            color: #1a56db;
        }
        .candidate-action:hover {
            background: #dbeafe;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #9ca3af;
        }
        @media (max-width: 1100px) {
            .candidate-filters {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 650px) {
            .candidate-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                    Candidates
                </h1>
            </div>
            <div class="dash-header__right">
                <div class="dash-header__user">
                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Programme Manager') ?>&background=1a56db&color=fff&size=80"
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
                            Candidates
                        </span>
                    </h1>
                    <p>
                        Monitor candidates participating in programmes
                        managed by you.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 STATISTICS
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >
                <!-- Total -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
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
                <!-- Selected -->
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
                <!-- Onboarded -->
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
                <!-- Active -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--cyan">
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
                <!-- Completed -->
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
                            Withdrawn
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 FILTERS
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:1rem;">
                        <i class="fas fa-filter"></i>
                        Find Candidates
                    </h2>
                    <form
                        method="GET"
                        action="<?= url('programme/candidates.php') ?>"
                    >
                        <div class="candidate-filters">
                            <!-- Search -->
                            <div class="filter-group">
                                <label for="search">
                                    Search Candidate
                                </label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    class="filter-control"
                                    value="<?= e($search) ?>"
                                    placeholder="Name, email or phone..."
                                >
                            </div>
                            <!-- Programme -->
                            <div class="filter-group">
                                <label for="programme_id">
                                    Programme
                                </label>
                                <select
                                    id="programme_id"
                                    name="programme_id"
                                    class="filter-control"
                                >
                                    <option value="">
                                        All Programmes
                                    </option>
                                    <?php foreach ($programmes as $programme): ?>
                                        <option
                                            value="<?= (int) $programme['id'] ?>"
                                            <?= $programmeId === (int) $programme['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e($programme['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Cohort -->
                            <div class="filter-group">
                                <label for="cohort_id">
                                    Cohort
                                </label>
                                <select
                                    id="cohort_id"
                                    name="cohort_id"
                                    class="filter-control"
                                >
                                    <option value="">
                                        All Cohorts
                                    </option>
                                    <?php foreach ($cohorts as $cohort): ?>
                                        <option
                                            value="<?= (int) $cohort['id'] ?>"
                                            <?= $cohortId === (int) $cohort['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e($cohort['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Status -->
                            <div class="filter-group">
                                <label for="status">
                                    Status
                                </label>
                                <select
                                    id="status"
                                    name="status"
                                    class="filter-control"
                                >
                                    <option value="">
                                        All Statuses
                                    </option>
                                    <?php foreach ($allowedStatuses as $candidateStatus): ?>
                                        <option
                                            value="<?= e($candidateStatus) ?>"
                                            <?= $status === $candidateStatus ? 'selected' : '' ?>
                                        >
                                            <?= e(
                                                candidate_status_label(
                                                    $candidateStatus
                                                )
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Actions -->
                            <div>
                                <button
                                    type="submit"
                                    class="sidebar__link"
                                    style="
                                        border:0;
                                        cursor:pointer;
                                        display:inline-flex;
                                        align-items:center;
                                        gap:.5rem;
                                    "
                                >
                                    <i class="fas fa-search"></i>
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php if (
                        $search !== ''
                        || $programmeId > 0
                        || $cohortId > 0
                        || $status !== ''
                    ): ?>
                        <div style="margin-top:1rem;">
                            <a
                                href="<?= url('programme/candidates.php') ?>"
                                class="sidebar__link"
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:.5rem;
                                "
                            >
                                <i class="fas fa-times"></i>
                                Clear Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- =================================================
                 CANDIDATE LIST HEADER
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:.5rem;">
                        <i class="fas fa-users"></i>
                        Candidate List
                    </h2>
                    <p>
                        <?= number_format(count($candidates)) ?>
                        candidate(s) found.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 EMPTY STATE
            ================================================== -->
            <?php if (empty($candidates)): ?>
                <div
                    class="welcome-card"
                    style="margin-top:1rem;"
                >
                    <div class="welcome-card__content empty-state">
                        <i class="fas fa-users"></i>
                        <h3>
                            No Candidates Found
                        </h3>
                        <?php if (
                            $search !== ''
                            || $programmeId > 0
                            || $cohortId > 0
                            || $status !== ''
                        ): ?>
                            <p>
                                No candidates match the selected
                                filters.
                            </p>
                            <a
                                href="<?= url('programme/candidates.php') ?>"
                                class="sidebar__link"
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:.5rem;
                                    margin-top:1rem;
                                "
                            >
                                <i class="fas fa-refresh"></i>
                                Clear Filters
                            </a>
                        <?php else: ?>
                            <p>
                                There are currently no candidates
                                assigned to your programmes.
                            </p>
                            <p style="color:#6b7280;font-size:.9rem;">
                                Candidates will appear here once they
                                are assigned to a cohort.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- =================================================
                     CANDIDATE TABLE
                ================================================== -->
                <div class="candidate-table-wrapper">
                    <table class="candidate-table">
                        <thead>
                            <tr>
                                <th>
                                    Candidate
                                </th>
                                <th>
                                    Programme
                                </th>
                                <th>
                                    Cohort
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
                                <th style="text-align:left;padding:1rem;">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <!-- Candidate -->
                                    <td>
                                        <div class="candidate-name">
                                            <?= e(
                                                trim(
                                                    ($candidate['first_name'] ?? '')
                                                    . ' '
                                                    . ($candidate['last_name'] ?? '')
                                                )
                                            ) ?>
                                        </div>
                                        <div class="candidate-contact">
                                            <?= e(
                                                $candidate['email'] ?? ''
                                            ) ?>
                                        </div>
                                        <?php if (
                                            !empty($candidate['phone'])
                                        ): ?>
                                            <div class="candidate-contact">
                                                <?= e(
                                                    $candidate['phone']
                                                ) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Programme -->
                                    <td>
                                        <strong>
                                            <?= e(
                                                $candidate['programme_name']
                                            ) ?>
                                        </strong>
                                        <?php if (
                                            !empty(
                                                $candidate['programme_type']
                                            )
                                        ): ?>
                                            <div class="candidate-contact">
                                                <?= e(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $candidate[
                                                                'programme_type'
                                                            ]
                                                        )
                                                    )
                                                ) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Cohort -->
                                    <td>
                                        <?= e(
                                            $candidate['cohort_name']
                                        ) ?>
                                    </td>
                                    <!-- Status -->
                                    <td>
                                        <span
                                            class="status-badge <?= e(
                                                candidate_status_class(
                                                    $candidate[
                                                        'participation_status'
                                                    ]
                                                )
                                            ) ?>"
                                        >
                                            <?= e(
                                                candidate_status_label(
                                                    $candidate[
                                                        'participation_status'
                                                    ]
                                                )
                                            ) ?>
                                        </span>
                                    </td>
                                    <!-- Selected -->
                                    <td>
                                        <?php if (
                                            !empty(
                                                $candidate['selected_at']
                                            )
                                        ): ?>
                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate[
                                                            'selected_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <!-- Onboarded -->
                                    <td>
                                        <?php if (
                                            !empty(
                                                $candidate['onboarded_at']
                                            )
                                        ): ?>
                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate[
                                                            'onboarded_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <!-- Completed -->
                                    <td>
                                        <?php if (
                                            !empty(
                                                $candidate['completed_at']
                                            )
                                        ): ?>
                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate[
                                                            'completed_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <!-- Action -->
                                    <td style="padding:1rem;">
                                        <a
                                            href="<?= url(
                                                'programme/candidate_view.php?id=' .
                                                (int) $candidate['user_id']
                                            ) ?>"
                                            class="sidebar__link"
                                            style="
                                                display:inline-flex;
                                                align-items:center;
                                                gap:0.5rem;
                                            "
                                        >
                                            <i class="fas fa-eye"></i>
                                            View Candidate
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>