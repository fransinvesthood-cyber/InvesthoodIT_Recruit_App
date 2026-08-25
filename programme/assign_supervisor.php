<?php
/**
 * ============================================================
 * INVESTHOOD IT - Programme Manager
 * Assign Supervisor to Cohort
 * ============================================================
 *
 * File:
 * programme/assign_supervisor.php
 *
 * Role:
 * Programme Manager
 *
 * Purpose:
 * - Display the current supervisor assigned to a cohort
 * - Display active users with the supervisor role
 * - Allow the Programme Manager to assign/change supervisor
 * - Verify that the cohort belongs to the logged-in manager
 *
 * Database relationships:
 *
 * cohorts.supervisor_id
 *      ↓
 * users.id
 *
 * users.role_id
 *      ↓
 * roles.id
 *
 * roles.slug = 'supervisor'
 *
 * programmes.programme_manager_id
 *      ↓
 * users.id
 *
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
|
| Your application has used both:
|
| $user['id']
| $user['user_id']
|
| We support both here.
|
*/
$managerId = (int) (
    $user['id']
    ?? $user['user_id']
    ?? 0
);
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
    'cohort_id',
    FILTER_VALIDATE_INT
);
/*
|--------------------------------------------------------------------------
| Also support POST cohort_id
|--------------------------------------------------------------------------
|
| This makes the page more robust if the form submits cohort_id.
|
*/
if (!$cohortId) {
    $cohortId = filter_input(
        INPUT_POST,
        'cohort_id',
        FILTER_VALIDATE_INT
    );
}
if (!$cohortId || $cohortId <= 0) {
    $_SESSION['flash_error'] =
        'Invalid cohort selected.';
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
| The logged-in manager must own the programme.
|
*/
$cohort = null;
$stmt = $conn->prepare("
    SELECT
        c.id AS cohort_id,
        c.name AS cohort_name,
        c.description AS cohort_description,
        c.start_date AS cohort_start_date,
        c.end_date AS cohort_end_date,
        c.status AS cohort_status,
        c.supervisor_id,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.status AS programme_status
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE c.id = ?
      AND p.programme_manager_id = ?
    LIMIT 1
");
if (!$stmt) {
    die(
        'Unable to prepare cohort query.'
    );
}
$stmt->bind_param(
    'ii',
    $cohortId,
    $managerId
);
$stmt->execute();
$result = $stmt->get_result();
$cohort = $result->fetch_assoc();
$stmt->close();
/*
|--------------------------------------------------------------------------
| Cohort Not Found / Unauthorized
|--------------------------------------------------------------------------
*/
if (!$cohort) {
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
/*
|--------------------------------------------------------------------------
| Process Assignment
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /*
    |--------------------------------------------------------------------------
    | Selected Supervisor
    |--------------------------------------------------------------------------
    */
    $supervisorId = filter_input(
        INPUT_POST,
        'supervisor_id',
        FILTER_VALIDATE_INT
    );
    /*
    |--------------------------------------------------------------------------
    | CSRF Token
    |--------------------------------------------------------------------------
    |
    | If your bootstrap already provides CSRF helpers, they can be
    | integrated here later. For now we validate the POST request
    | against the cohort ownership and supervisor role.
    |
    */
    if (!$supervisorId || $supervisorId <= 0) {
        $_SESSION['flash_error'] =
            'Please select a supervisor.';
        header(
            'Location: ' .
            url(
                'programme/assign_supervisor.php?cohort_id=' .
                (int) $cohortId
            )
        );
        exit;
    }
    /*
    |--------------------------------------------------------------------------
    | Verify Supervisor
    |--------------------------------------------------------------------------
    |
    | The selected user must:
    |
    | - exist
    | - have role slug = supervisor
    | - have status = active
    |
    */
    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.status,
            r.slug AS role_slug
        FROM users u
        INNER JOIN roles r
            ON r.id = u.role_id
        WHERE u.id = ?
          AND r.slug = 'supervisor'
          AND u.status = 'active'
        LIMIT 1
    ");
    if (!$stmt) {
        die(
            'Unable to prepare supervisor validation query.'
        );
    }
    $stmt->bind_param(
        'i',
        $supervisorId
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $supervisor = $result->fetch_assoc();
    $stmt->close();
    /*
    |--------------------------------------------------------------------------
    | Invalid Supervisor
    |--------------------------------------------------------------------------
    */
    if (!$supervisor) {
        $_SESSION['flash_error'] =
            'The selected user is not an active supervisor.';
        header(
            'Location: ' .
            url(
                'programme/assign_supervisor.php?cohort_id=' .
                (int) $cohortId
            )
        );
        exit;
    }
    /*
    |--------------------------------------------------------------------------
    | Assign Supervisor
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE cohorts
        SET supervisor_id = ?
        WHERE id = ?
    ");
    if (!$stmt) {
        die(
            'Unable to prepare supervisor assignment query.'
        );
    }
    $stmt->bind_param(
        'ii',
        $supervisorId,
        $cohortId
    );
    $success = $stmt->execute();
    $stmt->close();
    /*
    |--------------------------------------------------------------------------
    | Assignment Result
    |--------------------------------------------------------------------------
    */
    if ($success) {
        $supervisorName = trim(
            ($supervisor['first_name'] ?? '')
            . ' '
            . ($supervisor['last_name'] ?? '')
        );
        if ($supervisorName === '') {
            $supervisorName = 'Supervisor';
        }
        $_SESSION['flash_success'] =
            'Supervisor "' .
            $supervisorName .
            '" has been assigned to the cohort.';
    } else {
        $_SESSION['flash_error'] =
            'Unable to assign the supervisor. Please try again.';
    }
    /*
    |--------------------------------------------------------------------------
    | Return to Cohort View
    |--------------------------------------------------------------------------
    */
    header(
        'Location: ' .
        url(
            'programme/cohort_view.php?id=' .
            (int) $cohortId
        )
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Fetch Current Supervisor
|--------------------------------------------------------------------------
*/
$currentSupervisor = null;
if (!empty($cohort['supervisor_id'])) {
    $currentSupervisorId = (int) $cohort['supervisor_id'];
    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.username,
            u.email,
            u.phone,
            u.status,
            r.slug AS role_slug
        FROM users u
        INNER JOIN roles r
            ON r.id = u.role_id
        WHERE u.id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param(
            'i',
            $currentSupervisorId
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $currentSupervisor =
            $result->fetch_assoc();
        $stmt->close();
    }
}
/*
|--------------------------------------------------------------------------
| Fetch Active Supervisors
|--------------------------------------------------------------------------
*/
$supervisors = [];
$stmt = $conn->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.username,
        u.email,
        u.phone
    FROM users u
    INNER JOIN roles r
        ON r.id = u.role_id
    WHERE r.slug = 'supervisor'
      AND u.status = 'active'
    ORDER BY
        u.first_name ASC,
        u.last_name ASC
");
if (!$stmt) {
    die(
        'Unable to prepare supervisors query.'
    );
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $supervisors[] = $row;
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
        Assign Supervisor |
        <?= e($cohort['cohort_name']) ?> |
        Investhood IT
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
         MAIN
    ====================================================== -->
    <main class="dashboard__main">
        <!-- =================================================
             HEADER
        ================================================== -->
        <header class="dash-header">
            <div class="dash-header__left">
                <h1 class="dash-header__title">
                    Assign Supervisor
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
            <div
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:0.75rem;
                    margin-bottom:1.5rem;
                "
            >
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
                    "
                >
                    <i class="fas fa-arrow-left"></i>
                    Back to Cohort
                </a>
            </div>
            <!-- =================================================
                 COHORT INFORMATION
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
                    <?php if (
                        !empty(
                            $cohort['cohort_description']
                        )
                    ): ?>
                        <p
                            style="
                                margin-top:0.5rem;
                            "
                        >
                            <?= e(
                                $cohort['cohort_description']
                            ) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- =================================================
                 CURRENT SUPERVISOR
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2
                        style="
                            margin-bottom:1.25rem;
                        "
                    >
                        <i class="fas fa-user-tie"></i>
                        Current Supervisor
                    </h2>
                    <?php if ($currentSupervisor): ?>
                        <div
                            style="
                                display:flex;
                                align-items:center;
                                gap:1rem;
                                flex-wrap:wrap;
                            "
                        >
                            <img
                                src="https://ui-avatars.com/api/?name=<?= urlencode(
                                    trim(
                                        ($currentSupervisor['first_name'] ?? '')
                                        . ' '
                                        . ($currentSupervisor['last_name'] ?? '')
                                    )
                                ) ?>&background=1a56db&color=fff&size=100"
                                alt=""
                                style="
                                    width:70px;
                                    height:70px;
                                    border-radius:50%;
                                "
                            >
                            <div>
                                <h3
                                    style="
                                        margin:0;
                                    "
                                >
                                    <?= e(
                                        trim(
                                            ($currentSupervisor['first_name'] ?? '')
                                            . ' '
                                            . ($currentSupervisor['last_name'] ?? '')
                                        )
                                    ) ?>
                                </h3>
                                <p
                                    style="
                                        margin:0.35rem 0 0;
                                        color:#64748b;
                                    "
                                >
                                    <?= e(
                                        $currentSupervisor['email']
                                        ?? 'No email'
                                    ) ?>
                                </p>
                                <?php if (
                                    !empty(
                                        $currentSupervisor['phone']
                                    )
                                ): ?>
                                    <p
                                        style="
                                            margin:0.25rem 0 0;
                                            color:#64748b;
                                        "
                                    >
                                        <i class="fas fa-phone"></i>
                                        <?= e(
                                            $currentSupervisor['phone']
                                        ) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div
                            style="
                                padding:1rem;
                                background: grey;
                                border-radius:10px;
                            "
                        >
                            <i
                                class="fas fa-info-circle"
                            ></i>
                            No supervisor has been assigned
                            to this cohort yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- =================================================
                 ASSIGN SUPERVISOR
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2
                        style="
                            margin-bottom:0.5rem;
                        "
                    >
                        <i class="fas fa-user-plus"></i>
                        <?= $currentSupervisor
                            ? 'Change Supervisor'
                            : 'Assign Supervisor'
                        ?>
                    </h2>
                    <p
                        style="
                            margin-bottom:1.5rem;
                        "
                    >
                        Select an active Programme Supervisor
                        to manage this cohort.
                    </p>
                    <?php if (empty($supervisors)): ?>
                        <div
                            style="
                                padding:1rem;
                                background:#fff7ed;
                                border:1px solid #fed7aa;
                                border-radius:10px;
                            "
                        >
                            <strong>
                                No active supervisors available.
                            </strong>
                            <p
                                style="
                                    margin:0.35rem 0 0;
                                "
                            >
                                There are currently no active users
                                with the Supervisor role.
                            </p>
                        </div>
                    <?php else: ?>
                        <form
                            method="POST"
                            action="<?= url(
                                'programme/assign_supervisor.php'
                            ) ?>"
                        >
                            <input
                                type="hidden"
                                name="cohort_id"
                                value="<?= (int) $cohort['cohort_id'] ?>"
                            >
                            <div
                                style="
                                    max-width:650px;
                                "
                            >
                                <label
                                    for="supervisor_id"
                                    style="
                                        display:block;
                                        margin-bottom:0.5rem;
                                        font-weight:600;
                                    "
                                >
                                    Supervisor
                                </label>
                                <select
                                    id="supervisor_id"
                                    name="supervisor_id"
                                    required
                                    style="
                                        width:100%;
                                        padding:0.85rem;
                                        border:1px solid #d1d5db;
                                        border-radius:8px;
                                        background:#fff;
                                        font-size:1rem;
                                    "
                                >
                                    <option value="">
                                        Select Supervisor
                                    </option>
                                    <?php foreach (
                                        $supervisors
                                        as $supervisor
                                    ): ?>
                                        <?php
                                        $isSelected =
                                            !empty(
                                                $cohort['supervisor_id']
                                            )
                                            &&
                                            (int)
                                            $cohort['supervisor_id']
                                            ===
                                            (int)
                                            $supervisor['id'];
                                        $supervisorName =
                                            trim(
                                                ($supervisor['first_name'] ?? '')
                                                . ' '
                                                . ($supervisor['last_name'] ?? '')
                                            );
                                        ?>
                                        <option
                                            value="<?= (int) $supervisor['id'] ?>"
                                            <?= $isSelected
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >
                                            <?= e(
                                                $supervisorName
                                            ) ?>
                                            <?php if (
                                                !empty(
                                                    $supervisor['email']
                                                )
                                            ): ?>
                                                —
                                                <?= e(
                                                    $supervisor['email']
                                                ) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div
                                style="
                                    display:flex;
                                    gap:0.75rem;
                                    flex-wrap:wrap;
                                    margin-top:1.5rem;
                                "
                            >
                                <button
                                    type="submit"
                                    class="sidebar__link"
                                    style="
                                        color:#fff;
                                        background-color: green;
                                        border:0;
                                        cursor:pointer;
                                        display:inline-flex;
                                        align-items:center;
                                        gap:0.5rem;
                                    "
                                >
                                    <i class="fas fa-save"></i>
                                    <?= $currentSupervisor
                                        ? 'Change Supervisor'
                                        : 'Assign Supervisor'
                                    ?>
                                </button>
                                <a
                                    href="<?= url(
                                        'programme/cohort_view.php?id=' .
                                        (int) $cohort['cohort_id']
                                    ) ?>"
                                    class="sidebar__link"
                                    style="
                                        color:#fff;
                                        background-color:red;
                                        display:inline-flex;
                                        align-items:center;
                                        gap:0.5rem;
                                    "
                                >
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>