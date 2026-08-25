<?php
/**
 * ============================================================
 * INVESTHOOD IT - Programme Officer Programmes
 * ============================================================
 * Role: Programme Officer
 *
 * Displays all programmes using the existing database structure.
 * No new database relationship is introduced.
 * ============================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');
$user = current_user();
$flashes = render_flashes();
/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
/*
|--------------------------------------------------------------------------
| Allowed Statuses
|--------------------------------------------------------------------------
*/
$allowedStatuses = [
    'draft',
    'active',
    'paused',
    'completed',
    'archived'
];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}
/*
|--------------------------------------------------------------------------
| Programme Data
|--------------------------------------------------------------------------
*/
$programmes = [];
/*
|--------------------------------------------------------------------------
| Base Query
|--------------------------------------------------------------------------
|
| We intentionally DO NOT filter by programme_manager_id here.
|
| Programme Officer must be able to monitor all programmes.
|
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        p.id,
        p.name,
        p.type,
        p.description,
        p.duration,
        p.start_date,
        p.end_date,
        p.status,
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
        ) AS active_candidate_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'completed'
                THEN cp.user_id
            END
        ) AS completed_candidate_count
    FROM programmes p
    LEFT JOIN cohorts c
        ON c.programme_id = p.id
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE 1 = 1
";
$types = '';
$params = [];
/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
if ($search !== '') {
    $sql .= "
        AND (
            p.name LIKE ?
            OR p.description LIKE ?
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
*/
if ($status !== '') {
    $sql .= "
        AND p.status = ?
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
        p.id,
        p.name,
        p.type,
        p.description,
        p.duration,
        p.start_date,
        p.end_date,
        p.status
    ORDER BY
        CASE
            WHEN p.status = 'active' THEN 1
            WHEN p.status = 'draft' THEN 2
            WHEN p.status = 'paused' THEN 3
            WHEN p.status = 'completed' THEN 4
            WHEN p.status = 'archived' THEN 5
            ELSE 6
        END,
        p.start_date DESC,
        p.id DESC
";
/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    $sql,
    $types,
    $params
);
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    /*
    |--------------------------------------------------------------------------
    | Candidate Counts
    |--------------------------------------------------------------------------
    */
    $candidateCount = (int) (
        $row['candidate_count'] ?? 0
    );
    $activeCandidateCount = (int) (
        $row['active_candidate_count'] ?? 0
    );
    $completedCandidateCount = (int) (
        $row['completed_candidate_count'] ?? 0
    );
    /*
    |--------------------------------------------------------------------------
    | Completion Progress
    |--------------------------------------------------------------------------
    */
    $progress = 0;
    if ($candidateCount > 0) {
        $progress = round(
            ($completedCandidateCount / $candidateCount) * 100
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Protect Progress Value
    |--------------------------------------------------------------------------
    */
    $progress = max(
        0,
        min(100, $progress)
    );
    /*
    |--------------------------------------------------------------------------
    | Progress Bar Width
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Calculate this BEFORE the HTML.
    |
    |--------------------------------------------------------------------------
    */
    $progressWidth = $progress;
    /*
    |--------------------------------------------------------------------------
    | Add Calculated Values
    |--------------------------------------------------------------------------
    */
    $row['progress'] = $progress;
    $row['progress_width'] = $progressWidth;
    $programmes[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$totalDisplayed = count($programmes);
$activeDisplayed = 0;
$completedDisplayed = 0;
$pausedDisplayed = 0;
$draftDisplayed = 0;
foreach ($programmes as $programme) {
    if ($programme['status'] === 'active') {
        $activeDisplayed++;
    }
    if ($programme['status'] === 'completed') {
        $completedDisplayed++;
    }
    if ($programme['status'] === 'paused') {
        $pausedDisplayed++;
    }
    if ($programme['status'] === 'draft') {
        $draftDisplayed++;
    }
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
        Programmes | Programme Officer | Investhood IT
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
                    Programmes
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
                 INTRODUCTION
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        Programme Management
                    </h1>
                    <p>
                        View and monitor programmes, cohorts,
                        candidate participation, and programme progress.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 SUMMARY
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >
                <!-- TOTAL -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($totalDisplayed) ?>
                        </span>
                        <span class="overview-card__label">
                            Programmes
                        </span>
                    </div>
                </div>
                <!-- ACTIVE -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($activeDisplayed) ?>
                        </span>
                        <span class="overview-card__label">
                            Active
                        </span>
                    </div>
                </div>
                <!-- COMPLETED -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--amber"
                    >
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($completedDisplayed) ?>
                        </span>
                        <span class="overview-card__label">
                            Completed
                        </span>
                    </div>
                </div>
                <!-- PAUSED -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($pausedDisplayed) ?>
                        </span>
                        <span class="overview-card__label">
                            Paused
                        </span>
                    </div>
                </div>
                <!-- DRAFT -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($draftDisplayed) ?>
                        </span>
                        <span class="overview-card__label">
                            Draft
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
                        Find Programmes
                    </h2>
                    <form
                        method="GET"
                        action="<?= url('programme_officer/programmes.php') ?>"
                        style="
                            display:flex;
                            flex-wrap:wrap;
                            gap:1rem;
                            align-items:end;
                        "
                    >
                        <!-- SEARCH -->
                        <div style="flex:1;min-width:250px;">
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
                                placeholder="Search programmes..."
                                style="
                                    width:100%;
                                    padding:0.75rem;
                                    border:1px solid #d1d5db;
                                    border-radius:8px;
                                "
                            >
                        </div>
                        <!-- STATUS -->
                        <div style="min-width:200px;">
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
                                <?php foreach ($allowedStatuses as $programmeStatus): ?>
                                    <option
                                        value="<?= e($programmeStatus) ?>"
                                        <?= $status === $programmeStatus ? 'selected' : '' ?>
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
                        <!-- SEARCH BUTTON -->
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
                        <!-- RESET -->
                        <?php if ($search !== '' || $status !== ''): ?>
                            <div>
                                <a
                                    href="<?= url('programme_officer/programmes.php') ?>"
                                    class="sidebar__link"
                                    style="display:inline-flex;"
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
                 PROGRAMME LIST HEADER
            ================================================== -->
            <div style="margin-top:2rem;">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2 style="margin-bottom:0.5rem;">
                            Programme List
                        </h2>
                        <p>
                            Programmes currently available in the platform.
                        </p>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 EMPTY STATE
            ================================================== -->
            <?php if (empty($programmes)): ?>
                <div
                    class="welcome-card"
                    style="margin-top:1rem;"
                >
                    <div class="welcome-card__content">
                        <h3>
                            No Programmes Found
                        </h3>
                        <p>
                            <?php if ($search !== '' || $status !== ''): ?>
                                No programmes match the selected
                                search criteria.
                            <?php else: ?>
                                There are currently no programmes
                                available.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- =================================================
                     PROGRAMME CARDS
                ================================================== -->
                <div
                    class="overview-grid"
                    style="margin-top:1rem;"
                >
                    <?php foreach ($programmes as $programme): ?>
                        <?php
                        /*
                        |--------------------------------------------------------------------------
                        | Safe Values
                        |--------------------------------------------------------------------------
                        */
                        $programmeName = $programme['name'] ?? '';
                        $programmeType = $programme['type'] ?? 'other';
                        $programmeStatus = $programme['status'] ?? 'draft';
                        $cohortCount = (int) (
                            $programme['cohort_count'] ?? 0
                        );
                        $candidateCount = (int) (
                            $programme['candidate_count'] ?? 0
                        );
                        $activeCandidateCount = (int) (
                            $programme['active_candidate_count'] ?? 0
                        );
                        $completedCandidateCount = (int) (
                            $programme['completed_candidate_count'] ?? 0
                        );
                        $progress = (int) (
                            $programme['progress'] ?? 0
                        );
                        $progressWidth = (int) (
                            $programme['progress_width'] ?? 0
                        );
                        ?>

                        <?php
                        $progress = (int) ($programme['progress'] ?? 0);

                        if ($progress < 0) {
                            $progress = 0;
                        }

                        if ($progress > 100) {
                            $progress = 100;
                        }

                        $progressStyle = 'width: ' . $progress . '%;';
                        ?>

                        <div class="overview-card">
                            <!-- ICON -->
                            <div
                                class="overview-card__icon overview-card__icon--primary"
                            >
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <!-- INFORMATION -->
                            <div class="overview-card__info">
                                <!-- NAME -->
                                <span
                                    class="overview-card__label"
                                    style="
                                        font-weight:700;
                                        font-size:1rem;
                                    "
                                >
                                    <?= e($programmeName) ?>
                                </span>
                                <!-- TYPE -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-tag"></i>
                                    <?= e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $programmeType
                                            )
                                        )
                                    ) ?>
                                </span>
                                <!-- STATUS -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-circle"></i>
                                    Status:
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
                                <!-- DATES -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
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
                                </span>
                                <!-- COHORTS / CANDIDATES -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.5rem;"
                                >
                                    <i class="fas fa-layer-group"></i>
                                    <?= number_format($cohortCount) ?>
                                    Cohorts
                                    ·
                                    <i class="fas fa-users"></i>
                                    <?= number_format($candidateCount) ?>
                                    Candidates
                                </span>
                                <!-- ACTIVE CANDIDATES -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-user-check"></i>
                                    Active Candidates:
                                    <?= number_format(
                                        $activeCandidateCount
                                    ) ?>
                                </span>
                                <!-- COMPLETED CANDIDATES -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <i class="fas fa-check"></i>
                                    Completed:
                                    <?= number_format(
                                        $completedCandidateCount
                                    ) ?>
                                </span>
                                <!-- PROGRESS -->
                                <span
                                    class="overview-card__number"
                                    style="
                                        font-size:1.5rem;
                                        margin-top:0.75rem;
                                    "
                                >
                                    <?= $progress ?>%
                                </span>
                                <span class="overview-card__label">
                                    Completion Progress
                                </span>
                                <!-- PROGRESS BAR -->
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
                                    style="<?= e($progressStyle) ?> height:100%; background:#1a56db; border-radius:999px;"
                                ></div>
                                
                                </div>
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