<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Officer Candidates
 * ================================================
 * Role: Programme Officer
 *
 * Displays candidates participating in programmes
 * managed/overseen by the current Programme Officer.
 *
 * Existing database relationships are used.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');
$user = current_user();
$flashes = render_flashes();
$officerId = (int) ($user['id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Candidate Statistics
|--------------------------------------------------------------------------
*/
$totalCandidates = 0;
$activeCandidates = 0;
$completedCandidates = 0;
$withdrawnCandidates = 0;
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
| Candidate List
|--------------------------------------------------------------------------
*/
$candidates = [];
$stmt = Database::prepare("
    SELECT
        cp.user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        cp.status AS participation_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,
        c.id AS cohort_id,
        c.name AS cohort_name,
        c.start_date AS cohort_start_date,
        c.end_date AS cohort_end_date,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    INNER JOIN users u
        ON u.id = cp.user_id
    WHERE p.created_by = ?
    ORDER BY
        p.name ASC,
        c.start_date ASC,
        u.first_name ASC,
        u.last_name ASC
", 'i', [$officerId]);
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
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
        Candidates | Programme Officer | Investhood IT
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
                    Candidates
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
                 WELCOME
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        Candidate Management
                    </h1>
                    <p>
                        View and monitor candidates participating
                        in your programmes.
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
                            Total Candidates
                        </span>
                    </div>
                </div>
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
                            Completed
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
                            Withdrawn
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 CANDIDATES
            ================================================== -->
            <div style="margin-top:2rem;">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2 style="margin-bottom:0.5rem;">
                            Programme Candidates
                        </h2>
                        <p>
                            Candidates currently associated with
                            your programmes.
                        </p>
                    </div>
                </div>
            </div>
            <?php if (empty($candidates)): ?>
                <div
                    class="welcome-card"
                    style="margin-top:1rem;"
                >
                    <div class="welcome-card__content">
                        <h3>
                            No Candidates Found
                        </h3>
                        <p>
                            There are currently no candidates
                            associated with your programmes.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div
                    class="overview-grid"
                    style="margin-top:1rem;"
                >
                    <?php foreach ($candidates as $candidate): ?>
                        <?php
                        $candidateName = trim(
                            $candidate['first_name'] .
                            ' ' .
                            $candidate['last_name']
                        );
                        $status = $candidate['participation_status'];
                        $statusLabel = ucfirst($status);
                        ?>
                        <div class="overview-card">
                            <div
                                class="overview-card__icon overview-card__icon--primary"
                            >
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="overview-card__info">
                                <!-- Candidate -->
                                <span
                                    class="overview-card__label"
                                    style="font-weight:600;"
                                >
                                    <?= e($candidateName) ?>
                                </span>
                                <!-- Email -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <?= e($candidate['email']) ?>
                                </span>
                                <!-- Programme -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <strong>
                                        Programme:
                                    </strong>
                                    <?= e($candidate['programme_name']) ?>
                                </span>
                                <!-- Cohort -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <strong>
                                        Cohort:
                                    </strong>
                                    <?= e($candidate['cohort_name']) ?>
                                </span>
                                <!-- Status -->
                                <span
                                    class="overview-card__label"
                                    style="margin-top:0.35rem;"
                                >
                                    <strong>
                                        Status:
                                    </strong>
                                    <?= e($statusLabel) ?>
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