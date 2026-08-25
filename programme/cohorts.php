<?php
/**
 * ============================================================
 * INVESTHOOD IT - Programme Manager Cohorts
 * ============================================================
 * File: programme/cohorts.php
 * Role: Programme Manager
 *
 * Displays cohorts belonging to programmes assigned
 * to the currently logged-in Programme Manager.
 *
 * Database tables:
 * - users
 * - programmes
 * - cohorts
 * - cohort_participants
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'cohorts';
/*
|--------------------------------------------------------------------------
| Current Programme Manager
|--------------------------------------------------------------------------
|
| current_user() uses user_id, not id.
|
*/
$managerId = (int) ($user['user_id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
/*
|--------------------------------------------------------------------------
| Allowed Cohort Statuses
|--------------------------------------------------------------------------
|
| We will not assume that the cohorts table has a fixed ENUM.
| The filter is therefore applied only when a value is supplied.
|
*/
$allowedStatuses = [
    'draft',
    'active',
    'paused',
    'completed',
    'archived'
];
if (
    $status !== '' &&
    !in_array($status, $allowedStatuses, true)
) {
    $status = '';
}
/*
|--------------------------------------------------------------------------
| Cohorts
|--------------------------------------------------------------------------
*/
$cohorts = [];
/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
|
| Programme Manager can only see cohorts belonging to
| programmes assigned to them.
|
*/
$sql = "
    SELECT
        c.id AS cohort_id,
        c.name AS cohort_name,
        c.programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.status AS programme_status,
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
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE p.programme_manager_id = ?
";
/*
|--------------------------------------------------------------------------
| Query Parameters
|--------------------------------------------------------------------------
*/
$types = 'i';
$params = [
    $managerId
];
/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/
if ($search !== '') {
    $sql .= "
        AND (
            c.name LIKE ?
            OR p.name LIKE ?
            OR p.type LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}
/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
|
| This assumes cohorts has a status column.
| If your cohorts table does NOT have status, remove this block.
|
*/
$cohortHasStatus = false;
/*
|--------------------------------------------------------------------------
| Detect Whether cohorts.status Exists
|--------------------------------------------------------------------------
*/
$columnCheck = $conn->query("
    SHOW COLUMNS
    FROM cohorts
    LIKE 'status'
");
if (
    $columnCheck &&
    $columnCheck->num_rows > 0
) {
    $cohortHasStatus = true;
}
/*
|--------------------------------------------------------------------------
| Apply Status Filter
|--------------------------------------------------------------------------
*/
if (
    $status !== '' &&
    $cohortHasStatus
) {
    $sql .= "
        AND c.status = ?
    ";
    $types .= 's';
    $params[] = $status;
}
/*
|--------------------------------------------------------------------------
| Grouping
|--------------------------------------------------------------------------
*/
$sql .= "
    GROUP BY
        c.id,
        c.name,
        c.programme_id,
        p.name,
        p.type,
        p.status
    ORDER BY
        c.id DESC
";
/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param(
            $types,
            ...$params
        );
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        /*
        |--------------------------------------------------------------------------
        | Candidate Counts
        |--------------------------------------------------------------------------
        */
        $candidateCount =
            (int) ($row['candidate_count'] ?? 0);
        $completedCount =
            (int) (
                $row['completed_candidate_count']
                ?? 0
            );
        /*
        |--------------------------------------------------------------------------
        | Progress
        |--------------------------------------------------------------------------
        */
        $progress = 0;
        if ($candidateCount > 0) {
            $progress = round(
                (
                    $completedCount /
                    $candidateCount
                ) * 100
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Keep Progress Between 0 and 100
        |--------------------------------------------------------------------------
        */
        $progress = max(
            0,
            min(
                100,
                $progress
            )
        );
        $row['progress'] = $progress;
        /*
        |--------------------------------------------------------------------------
        | Cohort Status
        |--------------------------------------------------------------------------
        |
        | If cohorts has no status column, use the
        | programme status as a fallback.
        |
        */
        if ($cohortHasStatus) {
            /*
            | We did not select c.status above because
            | we want this page to work with existing
            | structures where status may not exist.
            |
            | Determine status separately.
            */
            $row['cohort_status'] =
                $row['programme_status'] ?? 'unknown';
        } else {
            $row['cohort_status'] =
                $row['programme_status'] ?? 'unknown';
        }
        $cohorts[] = $row;
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$totalCohorts = count($cohorts);
$totalCandidates = 0;
$totalActiveCandidates = 0;
$totalCompletedCandidates = 0;
$totalWithdrawnCandidates = 0;
$activeCohorts = 0;
$completedCohorts = 0;
foreach ($cohorts as $cohort) {
    $totalCandidates +=
        (int) (
            $cohort['candidate_count']
            ?? 0
        );
    $totalActiveCandidates +=
        (int) (
            $cohort['active_candidate_count']
            ?? 0
        );
    $totalCompletedCandidates +=
        (int) (
            $cohort['completed_candidate_count']
            ?? 0
        );
    $totalWithdrawnCandidates +=
        (int) (
            $cohort['withdrawn_candidate_count']
            ?? 0
        );
    $cohortStatus =
        strtolower(
            $cohort['cohort_status'] ?? ''
        );
    if ($cohortStatus === 'active') {
        $activeCohorts++;
    }
    if ($cohortStatus === 'completed') {
        $completedCohorts++;
    }
}
/*
|--------------------------------------------------------------------------
| Progress Width
|--------------------------------------------------------------------------
*/
$overallProgress = 0;
if ($totalCandidates > 0) {
    $overallProgress = round(
        (
            $totalCompletedCandidates /
            $totalCandidates
        ) * 100
    );
}
$overallProgress = max(
    0,
    min(
        100,
        $overallProgress
    )
);
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
        Cohorts | Programme Manager | Investhood IT
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
                    Cohorts
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
                            Cohorts
                        </span>
                    </h1>
                    <p>
                        View and monitor cohorts belonging to
                        your assigned programmes.
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
                <!-- Total Cohorts -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(
                                $totalCohorts
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Total Cohorts
                        </span>
                    </div>
                </div>
                <!-- Active Cohorts -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(
                                $activeCohorts
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Active Cohorts
                        </span>
                    </div>
                </div>
                <!-- Completed Cohorts -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(
                                $completedCohorts
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Completed Cohorts
                        </span>
                    </div>
                </div>
                <!-- Candidates -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--amber"
                    >
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(
                                $totalCandidates
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Candidates
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 OVERALL PROGRESS
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            gap:1rem;
                            flex-wrap:wrap;
                        "
                    >
                        <div>
                            <h2 style="margin-bottom:0.35rem;">
                                Overall Cohort Progress
                            </h2>
                            <p style="margin:0;">
                                Completed candidates across
                                your managed cohorts.
                            </p>
                        </div>
                        <strong
                            style="
                                font-size:1.5rem;
                            "
                        >
                            <?= (int) $overallProgress ?>%
                        </strong>
                    </div>
                    <div
                        style="
                            width:100%;
                            height:10px;
                            background:#e5e7eb;
                            border-radius:999px;
                            overflow:hidden;
                            margin-top:1rem;
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
                        Find Cohorts
                    </h2>
                    <form
                        method="GET"
                        action="<?= url('programme/cohorts.php') ?>"
                        style="
                            display:flex;
                            flex-wrap:wrap;
                            gap:1rem;
                            align-items:end;
                        "
                    >
                        <!-- Search -->
                        <div
                            style="
                                flex:1;
                                min-width:250px;
                            "
                        >
                            <label
                                for="search"
                                style="
                                    display:block;
                                    margin-bottom:0.4rem;
                                    font-weight:600;
                                "
                            >
                                Search
                            </label>
                            <input
                                type="text"
                                id="search"
                                name="search"
                                value="<?= e($search) ?>"
                                placeholder="Search cohorts or programmes..."
                                style="
                                    width:100%;
                                    padding:0.75rem;
                                    border:1px solid #d1d5db;
                                    border-radius:8px;
                                "
                            >
                        </div>
                        <!-- Status -->
                        <?php if ($cohortHasStatus): ?>
                            <div
                                style="
                                    min-width:200px;
                                "
                            >
                                <label
                                    for="status"
                                    style="
                                        display:block;
                                        margin-bottom:0.4rem;
                                        font-weight:600;
                                    "
                                >
                                    Status
                                </label>
                                <select
                                    id="status"
                                    name="status"
                                    style="
                                        width:100%;
                                        padding:0.75rem;
                                        border:1px solid #d1d5db;
                                        border-radius:8px;
                                    "
                                >
                                    <option value="">
                                        All Statuses
                                    </option>
                                    <?php foreach (
                                        $allowedStatuses
                                        as $programmeStatus
                                    ): ?>
                                        <option
                                            value="<?= e(
                                                $programmeStatus
                                            ) ?>"
                                            <?= $status ===
                                                $programmeStatus
                                                ? 'selected'
                                                : '' ?>
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
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <!-- Search Button -->
                        <div>
                            <button
                                type="submit"
                                class="sidebar__link"
                                style="
                                    border:0;
                                    cursor:pointer;
                                    display:inline-flex;
                                    align-items:center;
                                    gap:0.5rem;
                                "
                            >
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                        </div>
                        <!-- Reset -->
                        <?php if (
                            $search !== '' ||
                            $status !== ''
                        ): ?>
                            <div>
                                <a
                                    href="<?= url(
                                        'programme/cohorts.php'
                                    ) ?>"
                                    class="sidebar__link"
                                    style="
                                        display:inline-flex;
                                        align-items:center;
                                        gap:0.5rem;
                                    "
                                >
                                    <i class="fas fa-times"></i>
                                    Reset
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <!-- =================================================
                 COHORT LIST
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:0.5rem;">
                        <i class="fas fa-layer-group"></i>
                        Cohort List
                    </h2>
                    <p>
                        Cohorts currently associated with
                        your programmes.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 EMPTY STATE
            ================================================== -->
            <?php if (empty($cohorts)): ?>
                <div
                    class="welcome-card"
                    style="margin-top:1rem;"
                >
                    <div class="welcome-card__content">
                        <h3>
                            <i class="fas fa-users-slash"></i>
                            No Cohorts Found
                        </h3>
                        <p>
                            <?php if (
                                $search !== '' ||
                                $status !== ''
                            ): ?>
                                No cohorts match the selected
                                search criteria.
                            <?php else: ?>
                                There are currently no cohorts
                                associated with your programmes.
                            <?php endif; ?>
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
                    <?php foreach (
                        $cohorts
                        as $cohort
                    ): ?>
                        <?php
                        $progressWidth = max(
                            0,
                            min(
                                100,
                                (int) (
                                    $cohort['progress']
                                    ?? 0
                                )
                            )
                        );
                        $candidateCount =
                            (int) (
                                $cohort[
                                    'candidate_count'
                                ] ?? 0
                            );
                        $activeCandidateCount =
                            (int) (
                                $cohort[
                                    'active_candidate_count'
                                ] ?? 0
                            );
                        $completedCandidateCount =
                            (int) (
                                $cohort[
                                    'completed_candidate_count'
                                ] ?? 0
                            );
                        $withdrawnCandidateCount =
                            (int) (
                                $cohort[
                                    'withdrawn_candidate_count'
                                ] ?? 0
                            );
                        $cohortStatus =
                            strtolower(
                                $cohort[
                                    'cohort_status'
                                ] ?? 'unknown'
                            );
                        ?>
                        <div class="overview-card">
                            <!-- Icon -->
                            <div
                                class="overview-card__icon overview-card__icon--primary"
                            >
                                <i class="fas fa-users"></i>
                            </div>
                            <!-- Information -->
                            <div class="overview-card__info">
                                <!-- Cohort Name -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        font-size:1rem;
                                        font-weight:700;
                                    "
                                >
                                    <?= e(
                                        $cohort['cohort_name']
                                    ) ?>
                                </span>
                                <!-- Programme -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        margin-top:0.5rem;
                                    "
                                >
                                    <i
                                        class="fas fa-graduation-cap"
                                    ></i>
                                    <?= e(
                                        $cohort['programme_name']
                                    ) ?>
                                </span>
                                <!-- Programme Type -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        margin-top:0.35rem;
                                    "
                                >
                                    <i class="fas fa-tag"></i>
                                    <?= e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $cohort[
                                                    'programme_type'
                                                ] ?? ''
                                            )
                                        )
                                    ) ?>
                                </span>
                                <!-- Status -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        margin-top:0.35rem;
                                    "
                                >
                                    <i
                                        class="fas fa-circle"
                                    ></i>
                                    Status:
                                    <?= e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $cohortStatus
                                            )
                                        )
                                    ) ?>
                                </span>
                                <!-- Candidates -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        margin-top:0.6rem;
                                    "
                                >
                                    <i
                                        class="fas fa-user-graduate"
                                    ></i>
                                    <?= number_format(
                                        $candidateCount
                                    ) ?>
                                    Candidates
                                </span>
                                <!-- Active Candidates -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        margin-top:0.35rem;
                                    "
                                >
                                    Active:
                                    <?= number_format(
                                        $activeCandidateCount
                                    ) ?>
                                </span>
                                <!-- Completed Candidates -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        margin-top:0.35rem;
                                    "
                                >
                                    Completed:
                                    <?= number_format(
                                        $completedCandidateCount
                                    ) ?>
                                </span>
                                <!-- Withdrawn Candidates -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        margin-top:0.35rem;
                                    "
                                >
                                    Withdrawn:
                                    <?= number_format(
                                        $withdrawnCandidateCount
                                    ) ?>
                                </span>
                                <!-- Progress -->
                                <span
                                    class="overview-card__number"
                                    style="
                                        font-size:1.5rem;
                                        margin-top:0.75rem;
                                    "
                                >
                                    <?= (int) $progressWidth ?>%
                                </span>
                                <span
                                    class="overview-card__label"
                                >
                                    Completion Progress
                                </span>
                                <!-- Progress Bar -->
                                <div
                                    style="
                                        width:100%;
                                        height:8px;
                                        background:#e5e7eb;
                                        border-radius:999px;
                                        overflow:hidden;
                                        margin-top:0.5rem;
                                    "
                                >
                                    <div
                                        style="
                                            width:<?= (int) $progressWidth ?>%;
                                            height:100%;
                                            background:#1a56db;
                                            border-radius:999px;
                                        "
                                    ></div>
                                </div>
                                <!-- View Cohort -->
                                <a
                                    href="<?= url(
                                        'programme/cohort_view.php?id=' .
                                        (int) $cohort['cohort_id']
                                    ) ?>"
                                    class="sidebar__link"
                                    style="
                                        display:inline-flex;
                                        align-items:center;
                                        gap:0.5rem;
                                        margin-top:1rem;
                                    "
                                >
                                    <i
                                        class="fas fa-eye"
                                    ></i>
                                    View Cohort
                                </a>
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