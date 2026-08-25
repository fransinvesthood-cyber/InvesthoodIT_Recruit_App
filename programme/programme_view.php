<?php
/**
 * ============================================================
 * INVESTHOOD IT - Programme Manager
 * Programme View
 * ============================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'programmes';
/*
|--------------------------------------------------------------------------
| Current Programme Manager
|--------------------------------------------------------------------------
*/
$managerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Validate Manager
|--------------------------------------------------------------------------
*/
if ($managerId <= 0) {
    http_response_code(403);
    exit('Invalid Programme Manager account.');
}
/*
|--------------------------------------------------------------------------
| Programme ID
|--------------------------------------------------------------------------
*/
$programmeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);
if (!$programmeId || $programmeId <= 0) {
    header('Location: ' . url('programme/programmes.php'));
    exit;
}
/*
|--------------------------------------------------------------------------
| Fetch Programme
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Programme Manager access is controlled by:
|
| programmes.programme_manager_id = users.id
|
|--------------------------------------------------------------------------
*/
$programme = null;
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
        p.updated_at,
        p.programme_manager_id,
        u.id AS manager_id,
        u.first_name AS manager_first_name,
        u.last_name AS manager_last_name,
        u.email AS manager_email
    FROM programmes p
    LEFT JOIN users u
        ON u.id = p.programme_manager_id
    WHERE p.id = ?
      AND p.programme_manager_id = ?
    LIMIT 1
");
if (!$stmt) {
    die('Unable to prepare programme query.');
}
$stmt->bind_param(
    'ii',
    $programmeId,
    $managerId
);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >
        <title>Programme Not Found | Investhood IT</title>
        <link
            rel="stylesheet"
            href="<?= url('css/styles.css') ?>"
        >
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
            crossorigin="anonymous"
        >
    </head>
    <body class="dashboard-page">
    <div class="dashboard">
        <?php require __DIR__ . '/sidebar.php'; ?>
        <main class="dashboard__main">
            <header class="dash-header">
                <div class="dash-header__left">
                    <h1 class="dash-header__title">
                        Programme Not Found
                    </h1>
                </div>
            </header>
            <div class="dash-content">
                <div
                    class="welcome-card"
                    style="margin-top:2rem;"
                >
                    <div class="welcome-card__content">
                        <h2>
                            <i class="fas fa-exclamation-triangle"></i>
                            Programme Not Found
                        </h2>
                        <p>
                            The programme does not exist, or it is not
                            assigned to your Programme Manager account.
                        </p>
                        <a
                            href="<?= url('programme/programmes.php') ?>"
                            class="sidebar__link"
                            style="
                                display:inline-flex;
                                align-items:center;
                                gap:0.5rem;
                                margin-top:1rem;
                            "
                        >
                            <i class="fas fa-arrow-left"></i>
                            Back to My Programmes
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    </body>
    </html>
    <?php
    exit;
}
$programme = $result->fetch_assoc();
$stmt->close();
/*
|--------------------------------------------------------------------------
| Programme Statistics
|--------------------------------------------------------------------------
*/
$cohortCount = 0;
$candidateCount = 0;
$activeCandidateCount = 0;
$completedCandidateCount = 0;
$withdrawnCandidateCount = 0;
/*
|--------------------------------------------------------------------------
| Cohort Count
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM cohorts
    WHERE programme_id = ?
");
$stmt->bind_param('i', $programmeId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$cohortCount = (int) ($row['total'] ?? 0);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Candidate Statistics
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(
            DISTINCT CASE
                WHEN cp.status <> 'withdrawn'
                THEN cp.user_id
            END
        ) AS total_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status IN (
                    'selected',
                    'onboarded',
                    'active'
                )
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
    FROM cohorts c
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE c.programme_id = ?
");
$stmt->bind_param('i', $programmeId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$candidateCount = (int) (
    $row['total_candidates'] ?? 0
);
$activeCandidateCount = (int) (
    $row['active_candidates'] ?? 0
);
$completedCandidateCount = (int) (
    $row['completed_candidates'] ?? 0
);
$withdrawnCandidateCount = (int) (
    $row['withdrawn_candidates'] ?? 0
);
$stmt->close();
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
$progress = max(
    0,
    min(
        100,
        $progress
    )
);
/*
|--------------------------------------------------------------------------
| Fetch Cohorts
|--------------------------------------------------------------------------
*/
$cohorts = [];
$stmt = $conn->prepare("
    SELECT
        c.id,
        c.name,
        c.programme_id,
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
        ) AS completed_candidate_count
    FROM cohorts c
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE c.programme_id = ?
    GROUP BY
        c.id,
        c.name,
        c.programme_id
    ORDER BY
        c.id ASC
");
$stmt->bind_param(
    'i',
    $programmeId
);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $cohortCandidateCount = (int) (
        $row['candidate_count'] ?? 0
    );
    $cohortCompletedCount = (int) (
        $row['completed_candidate_count'] ?? 0
    );
    $cohortProgress = 0;
    if ($cohortCandidateCount > 0) {
        $cohortProgress = round(
            (
                $cohortCompletedCount
                / $cohortCandidateCount
            ) * 100
        );
    }
    $row['progress'] = max(
        0,
        min(
            100,
            $cohortProgress
        )
    );
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
        <?= e($programme['name']) ?>
        | Programme Manager | Investhood IT
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
                    Programme Details
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
                 BACK BUTTON
            ================================================== -->
            <div style="margin-bottom:1rem;">
                <a
                    href="<?= url('programme/programmes.php') ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:0.5rem;
                    "
                >
                    <i class="fas fa-arrow-left"></i>
                    Back to My Programmes
                </a>
            </div>
            <!-- =================================================
                 PROGRAMME HEADER
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        <?= e($programme['name']) ?>
                    </h1>
                    <p>
                        <?= e(
                            $programme['description']
                            ?? 'Programme details and monitoring information.'
                        ) ?>
                    </p>
                    <div
                        style="
                            margin-top:1rem;
                            display:flex;
                            flex-wrap:wrap;
                            gap:0.75rem;
                        "
                    >
                        <span>
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
                        <span>
                            <i class="fas fa-circle"></i>
                            <?= e(
                                ucfirst(
                                    $programme['status']
                                )
                            ) ?>
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 STATISTICS
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >
                <!-- Cohorts -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($cohortCount) ?>
                        </span>
                        <span class="overview-card__label">
                            Cohorts
                        </span>
                    </div>
                </div>
                <!-- Candidates -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($candidateCount) ?>
                        </span>
                        <span class="overview-card__label">
                            Candidates
                        </span>
                    </div>
                </div>
                <!-- Active -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($activeCandidateCount) ?>
                        </span>
                        <span class="overview-card__label">
                            Active Candidates
                        </span>
                    </div>
                </div>
                <!-- Completed -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--amber"
                    >
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($completedCandidateCount) ?>
                        </span>
                        <span class="overview-card__label">
                            Completed
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 PROGRAMME INFORMATION
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2>
                        <i class="fas fa-info-circle"></i>
                        Programme Information
                    </h2>
                    <p>
                        <strong>Duration:</strong>
                        <?= e(
                            $programme['duration']
                            ?? 'Not specified'
                        ) ?>
                    </p>
                    <p>
                        <strong>Start Date:</strong>
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
                            Not set
                        <?php endif; ?>
                    </p>
                    <p>
                        <strong>End Date:</strong>
                        <?php if (!empty($programme['end_date'])): ?>
                            <?= e(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $programme['end_date']
                                    )
                                )
                            ) ?>
                        <?php else: ?>
                            Not set
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($programme['objectives'])): ?>
                        <p>
                            <strong>Objectives:</strong>
                        </p>
                        <div>
                            <?= nl2br(
                                e(
                                    $programme['objectives']
                                )
                            ) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- =================================================
                 COMPLETION PROGRESS
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2>
                        <i class="fas fa-chart-line"></i>
                        Programme Progress
                    </h2>
                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            margin-top:1rem;
                        "
                    >
                        <span>
                            Completion Progress
                        </span>
                        <strong>
                            <?= (int) $progress ?>%
                        </strong>
                    </div>
                    <div
                        style="
                            width:100%;
                            height:10px;
                            background:#e5e7eb;
                            border-radius:999px;
                            overflow:hidden;
                            margin-top:0.5rem;
                        "
                    >
                        <div
                            style="
                                width:<?= (int) $progress ?>%;
                                height:100%;
                                background:#1a56db;
                                border-radius:999px;
                            "
                        ></div>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 COHORTS
            ================================================== -->
            <div style="margin-top:2rem;">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2>
                            <i class="fas fa-layer-group"></i>
                            Programme Cohorts
                        </h2>
                        <p>
                            Cohorts belonging to this programme.
                        </p>
                    </div>
                </div>
            </div>
            <?php if (empty($cohorts)): ?>
                <div
                    class="welcome-card"
                    style="margin-top:1rem;"
                >
                    <div class="welcome-card__content">
                        <h3>
                            No Cohorts
                        </h3>
                        <p>
                            No cohorts have been created for this
                            programme yet.
                        </p>
                    </div>
                </div>
            <?php else: ?>
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
                                <span
                                    class="overview-card__label"
                                    style="
                                        font-size:1rem;
                                        font-weight:700;
                                    "
                                >
                                    <?= e(
                                        $cohort['name']
                                    ) ?>
                                </span>
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.5rem;"
                                >
                                    <?= number_format(
                                        (int) $cohort['candidate_count']
                                    ) ?>
                                    Candidates
                                </span>
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Active:
                                    <?= number_format(
                                        (int) $cohort['active_candidate_count']
                                    ) ?>
                                </span>
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    Completed:
                                    <?= number_format(
                                        (int) $cohort['completed_candidate_count']
                                    ) ?>
                                </span>
                                <span
                                    class="overview-card__number"
                                    style="
                                        font-size:1.4rem;
                                        margin-top:0.75rem;
                                    "
                                >
                                    <?= (int) $cohort['progress'] ?>%
                                </span>
                                <span class="overview-card__label">
                                    Completion
                                </span>
                            </div>
                            <a
                            href="<?= url(
                                'programme/cohort_view.php?id='
                                . (int) $cohort['id']
                            ) ?>"
                            class="sidebar__link"
                            style="
                                display:inline-flex;
                                align-items:center;
                                gap:0.5rem;
                                margin-top:1rem;
                            "
                        >
                            <i class="fas fa-eye"></i>
                            View Cohort
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