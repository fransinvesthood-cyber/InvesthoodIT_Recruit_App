<?php
/**
 * ============================================================
 * INVESTHOOD IT - Programme Manager
 * Cohort View
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
if ($managerId <= 0) {
    http_response_code(403);
    exit('Invalid Programme Manager account.');
}
/*
|--------------------------------------------------------------------------
| Cohort ID
|--------------------------------------------------------------------------
*/
$cohortId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);
if (!$cohortId || $cohortId <= 0) {
    header(
        'Location: ' .
        url('programme/programmes.php')
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Fetch Cohort
|--------------------------------------------------------------------------
|
| Security:
|
| cohort
|   ↓
| programme
|   ↓
| programme_manager_id
|
| The current manager must own the programme.
|
|--------------------------------------------------------------------------
*/
$cohort = null;
$stmt = $conn->prepare("
    SELECT
        c.id AS cohort_id,
        c.name AS cohort_name,
        c.programme_id,
        c.supervisor_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.description AS programme_description,
        p.status AS programme_status,
        p.start_date AS programme_start_date,
        p.end_date AS programme_end_date,
        p.programme_manager_id
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE c.id = ?
      AND p.programme_manager_id = ?
    LIMIT 1
");
if (!$stmt) {
    die('Unable to prepare cohort query.');
}
$stmt->bind_param(
    'ii',
    $cohortId,
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
        <title>
            Cohort Not Found | Investhood IT
        </title>
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
                        Cohort Not Found
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
                            Cohort Not Found
                        </h2>
                        <p>
                            The cohort does not exist, or it does not
                            belong to one of your assigned programmes.
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
$cohort = $result->fetch_assoc();
$stmt->close();

/*
|--------------------------------------------------------------------------
| Current Supervisor
|--------------------------------------------------------------------------
*/

$currentSupervisor = null;

if (!empty($cohort['supervisor_id'])) {

    $supervisorId = (int) $cohort['supervisor_id'];

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone
        FROM users u
        INNER JOIN roles r
            ON r.id = u.role_id
        WHERE u.id = ?
          AND r.slug = 'supervisor'
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('i', $supervisorId);
        $stmt->execute();
        $currentSupervisor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
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
| Candidate Statistics Query
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
    WHERE cp.cohort_id = ?
");
if (!$stmt) {
    die('Unable to prepare candidate statistics query.');
}
$stmt->bind_param(
    'i',
    $cohortId
);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalCandidates = (int) (
    $row['total_candidates'] ?? 0
);
$selectedCandidates = (int) (
    $row['selected_candidates'] ?? 0
);
$onboardedCandidates = (int) (
    $row['onboarded_candidates'] ?? 0
);
$activeCandidates = (int) (
    $row['active_candidates'] ?? 0
);
$completedCandidates = (int) (
    $row['completed_candidates'] ?? 0
);
$withdrawnCandidates = (int) (
    $row['withdrawn_candidates'] ?? 0
);
$stmt->close();
/*
|--------------------------------------------------------------------------
| Completion Progress
|--------------------------------------------------------------------------
*/
$progress = 0;
if ($totalCandidates > 0) {
    $progress = round(
        ($completedCandidates / $totalCandidates) * 100
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
| Fetch Candidates
|--------------------------------------------------------------------------
*/
$candidates = [];
$stmt = $conn->prepare("
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
        u.status AS user_status
    FROM cohort_participants cp
    INNER JOIN users u
        ON u.id = cp.user_id
    WHERE cp.cohort_id = ?
    ORDER BY
        u.first_name ASC,
        u.last_name ASC,
        cp.id ASC
");
if (!$stmt) {
    die('Unable to prepare candidates query.');
}
$stmt->bind_param(
    'i',
    $cohortId
);
$stmt->execute();
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
        <?= e($cohort['cohort_name']) ?>
        | Investhood IT
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
         MAIN
    ====================================================== -->
    <main class="dashboard__main">
        <!-- =================================================
             HEADER
        ================================================== -->
        <header class="dash-header">
            <div class="dash-header__left">
                <h1 class="dash-header__title">
                    Cohort Details
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
                 BACK
            ================================================== -->
<div style="margin-bottom:1rem; display:flex; gap:.75rem; flex-wrap:wrap;">

    <a
        href="<?= url(
            'programme/programme_view.php?id='
            . (int) $cohort['programme_id']
        ) ?>"
        class="sidebar__link"
        style="
            display:inline-flex;
            align-items:center;
            gap:.5rem;
        "
    >
        <i class="fas fa-arrow-left"></i>
        Back to Programme
    </a>
    <a
        href="<?= url(
            'programme/assign_candidates.php?cohort_id='
            . (int) $cohort['cohort_id']
        ) ?>"
        class="sidebar__link"
        style="
            display:inline-flex;
            align-items:center;
            gap:.5rem;
        "
    >
        <i class="fas fa-user-plus"></i>
        Assign Candidates
    </a>
    <a
        href="<?= url(
            'programme/assign_supervisor.php?cohort_id=' .
            (int) $cohort['cohort_id']
        ) ?>"
        class="sidebar__link"
        style="
            display:inline-flex;
            align-items:center;
            gap:0.5rem;
        "
    >
        <i class="fas fa-user-tie"></i>
        <?= !empty($cohort['supervisor_id'])
            ? 'Change Supervisor'
            : 'Assign Supervisor'
        ?>
    </a>
    <a
        href="<?= url(
            'programme/cohort_candidates.php?cohort_id='
            . (int) $cohort['cohort_id']
        ) ?>"
        class="sidebar__link"
        style="
            display:inline-flex;
            align-items:center;
            gap:.5rem;
        "
    >
        <i class="fas fa-users"></i>
        Manage Candidates
    </a>
</div>
            <!-- =================================================
                 COHORT HEADER
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        <?= e(
                            $cohort['cohort_name']
                        ) ?>
                    </h1>
                    <p>
                        Programme:
                        <strong>
                            <?= e(
                                $cohort['programme_name']
                            ) ?>
                        </strong>
                    </p>
                    <p style="margin-top:0.5rem;">
                        <?= e(
                            $cohort['programme_type']
                            ?? ''
                        ) ?>
                    </p>
                </div>
            </div>
            <div class="welcome-card" style="margin-top:2rem;">
                <div class="welcome-card__content">

                    <row justify=between align=center wrap=wrap gap=3>
                        <box gap=1>
                            <title size=md>Supervisor</title>

                            <?php if ($currentSupervisor): ?>

                                <row gap=3 align=center>
                                    <img
                                        src="https://ui-avatars.com/api/?name=<?= urlencode($currentSupervisor['first_name'].' '.$currentSupervisor['last_name']) ?>&background=1a56db&color=fff&size=100"
                                        alt=""
                                        style="width:64px;height:64px;border-radius:50%;"
                                    >

                                    <box gap=0>
                                        <title size=sm weight=bold><?= e($currentSupervisor['first_name'].' '.$currentSupervisor['last_name']) ?></title>
                                        <caption><?= e($currentSupervisor['email']) ?></caption>

                                        <?php if (!empty($currentSupervisor['phone'])): ?>
                                            <caption><?= e($currentSupervisor['phone']) ?></caption>
                                        <?php endif; ?>
                                    </box>
                                </row>

                            <?php else: ?>

                                <text color=secondary>No supervisor assigned to this cohort yet.</text>

                            <?php endif; ?>
                        </box>

                        <a
                            href="<?= url('programme/assign_supervisor.php?cohort_id='.(int)$cohort['cohort_id']) ?>"
                            class="sidebar__link"
                            style="display:inline-flex;align-items:center;gap:.5rem;"
                        >
                            <i class="fas fa-user-tie"></i>
                            <?= $currentSupervisor ? 'Change Supervisor' : 'Assign Supervisor' ?>
                        </a>
                    </row>

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
                            <?= number_format(
                                $totalCandidates
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Candidates
                        </span>
                    </div>
                </div>
                <!-- Selected -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(
                                $selectedCandidates
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Selected
                        </span>
                    </div>
                </div>
                <!-- Onboarded -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(
                                $onboardedCandidates
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Onboarded
                        </span>
                    </div>
                </div>
                <!-- Active -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(
                                $activeCandidates
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Active
                        </span>
                    </div>
                </div>
                <!-- Completed -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format(
                                $completedCandidates
                            ) ?>
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
                            <?= number_format(
                                $withdrawnCandidates
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Withdrawn
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 PROGRESS
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2>
                        <i class="fas fa-chart-line"></i>
                        Cohort Progress
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
                 CANDIDATES
            ================================================== -->
            <div style="margin-top:2rem;">
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2>
                            <i class="fas fa-users"></i>
                            Cohort Candidates
                        </h2>
                        <p>
                            Candidates currently assigned to this cohort.
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
                            <i class="fas fa-users-slash"></i>
                            No Candidates
                        </h3>
                        <p>
                            There are currently no candidates assigned
                            to this cohort.
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
                                <th
                                    style="
                                        text-align:left;
                                        padding:1rem;
                                    "
                                >
                                    Candidate
                                </th>
                                <th
                                    style="
                                        text-align:left;
                                        padding:1rem;
                                    "
                                >
                                    Email
                                </th>
                                <th
                                    style="
                                        text-align:left;
                                        padding:1rem;
                                    "
                                >
                                    Phone
                                </th>
                                <th
                                    style="
                                        text-align:left;
                                        padding:1rem;
                                    "
                                >
                                    Status
                                </th>
                                <th
                                    style="
                                        text-align:left;
                                        padding:1rem;
                                    "
                                >
                                    Selected
                                </th>
                                <th
                                    style="
                                        text-align:left;
                                        padding:1rem;
                                    "
                                >
                                    Onboarded
                                </th>
                                <th
                                    style="
                                        text-align:left;
                                        padding:1rem;
                                    "
                                >
                                    Completed
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (
                                $candidates
                                as $candidate
                            ): ?>
                                <tr>
                                    <!-- Candidate -->
                                    <td style="padding:1rem;">
                                        <strong>
                                            <?= e(
                                                trim(
                                                    ($candidate['first_name'] ?? '')
                                                    . ' '
                                                    . ($candidate['last_name'] ?? '')
                                                )
                                            ) ?>
                                        </strong>
                                    </td>
                                    <!-- Email -->
                                    <td style="padding:1rem;">
                                        <?= e(
                                            $candidate['email']
                                            ?? '—'
                                        ) ?>
                                    </td>
                                    <!-- Phone -->
                                    <td style="padding:1rem;">
                                        <?= e(
                                            $candidate['phone']
                                            ?? '—'
                                        ) ?>
                                    </td>
                                    <!-- Status -->
                                    <td style="padding:1rem;">
                                        <?= e(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $candidate[
                                                        'participation_status'
                                                    ]
                                                    ?? ''
                                                )
                                            )
                                        ) ?>
                                    </td>
                                    <!-- Selected -->
                                    <td style="padding:1rem;">
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
                                    <td style="padding:1rem;">
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
                                    <td style="padding:1rem;">
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