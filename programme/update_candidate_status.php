<?php
/**
 * ============================================================
 * INVESTHOOD IT - Programme Manager
 * Update Candidate Status
 * ============================================================
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
*/
$managerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
if ($managerId <= 0) {
    http_response_code(403);
    exit('Invalid Programme Manager account.');
}
/*
|--------------------------------------------------------------------------
| Participant ID
|--------------------------------------------------------------------------
*/
$participantId = filter_input(
    INPUT_GET,
    'participant_id',
    FILTER_VALIDATE_INT
);
if (!$participantId || $participantId <= 0) {
    header(
        'Location: ' .
        url('programme/cohorts.php')
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Fetch Participant
|--------------------------------------------------------------------------
|
| Security:
|
| participant
|     ↓
| cohort
|     ↓
| programme
|     ↓
| programme_manager_id
|
| The current manager must own the programme.
|
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        cp.id AS participant_id,
        cp.cohort_id,
        cp.user_id,
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
        c.name AS cohort_name,
        p.id AS programme_id,
        p.name AS programme_name
    FROM cohort_participants cp
    INNER JOIN users u
        ON u.id = cp.user_id
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE cp.id = ?
      AND p.programme_manager_id = ?
    LIMIT 1
");
if (!$stmt) {
    die('Unable to prepare participant query.');
}
$stmt->bind_param(
    'ii',
    $participantId,
    $managerId
);
$stmt->execute();
$result = $stmt->get_result();
$participant = $result->fetch_assoc();
$stmt->close();
/*
|--------------------------------------------------------------------------
| Participant Not Found
|--------------------------------------------------------------------------
*/
if (!$participant) {
    $_SESSION['flash_error'] =
        'Candidate not found or you do not have permission to update this candidate.';
    header(
        'Location: ' .
        url('programme/cohorts.php')
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Handle POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = trim(
        $_POST['status'] ?? ''
    );
    /*
    |--------------------------------------------------------------------------
    | Allowed Statuses
    |--------------------------------------------------------------------------
    */
    $allowedStatuses = [
        'selected',
        'onboarded',
        'active',
        'completed',
        'withdrawn'
    ];
    if (!in_array(
        $newStatus,
        $allowedStatuses,
        true
    )) {
        $_SESSION['flash_error'] =
            'Invalid candidate status selected.';
        header(
            'Location: ' .
            url(
                'programme/update_candidate_status.php?participant_id='
                . $participantId
            )
        );
        exit;
    }
    /*
    |--------------------------------------------------------------------------
    | Update Participation
    |--------------------------------------------------------------------------
    |
    | We preserve existing lifecycle dates unless the relevant
    | status has never been reached before.
    |
    */
    $currentSelectedAt =
        $participant['selected_at'];
    $currentOnboardedAt =
        $participant['onboarded_at'];
    $currentCompletedAt =
        $participant['completed_at'];
    /*
    |--------------------------------------------------------------------------
    | Set Lifecycle Dates
    |--------------------------------------------------------------------------
    */
    $selectedAt = $currentSelectedAt;
    $onboardedAt = $currentOnboardedAt;
    $completedAt = $currentCompletedAt;
    /*
    |--------------------------------------------------------------------------
    | Selected
    |--------------------------------------------------------------------------
    */
    if (
        $newStatus === 'selected' &&
        empty($selectedAt)
    ) {
        $selectedAt = date('Y-m-d H:i:s');
    }
    /*
    |--------------------------------------------------------------------------
    | Onboarded
    |--------------------------------------------------------------------------
    */
    if (
        $newStatus === 'onboarded' &&
        empty($selectedAt)
    ) {
        $selectedAt = date('Y-m-d H:i:s');
    }
    if (
        $newStatus === 'onboarded' &&
        empty($onboardedAt)
    ) {
        $onboardedAt = date('Y-m-d H:i:s');
    }
    /*
    |--------------------------------------------------------------------------
    | Active
    |--------------------------------------------------------------------------
    */
    if (
        $newStatus === 'active' &&
        empty($selectedAt)
    ) {
        $selectedAt = date('Y-m-d H:i:s');
    }
    if (
        $newStatus === 'active' &&
        empty($onboardedAt)
    ) {
        $onboardedAt = date('Y-m-d H:i:s');
    }
    /*
    |--------------------------------------------------------------------------
    | Completed
    |--------------------------------------------------------------------------
    */
    if (
        $newStatus === 'completed'
    ) {
        if (empty($selectedAt)) {
            $selectedAt =
                date('Y-m-d H:i:s');
        }
        if (empty($onboardedAt)) {
            $onboardedAt =
                date('Y-m-d H:i:s');
        }
        if (empty($completedAt)) {
            $completedAt =
                date('Y-m-d H:i:s');
        }
    }
    /*
    |--------------------------------------------------------------------------
    | Withdrawn
    |--------------------------------------------------------------------------
    |
    | We do not clear previous lifecycle dates.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Update Database
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE cohort_participants
        SET
            status = ?,
            selected_at = ?,
            onboarded_at = ?,
            completed_at = ?
        WHERE id = ?
    ");
    if (!$stmt) {
        die('Unable to prepare status update.');
    }
    $stmt->bind_param(
        'ssssi',
        $newStatus,
        $selectedAt,
        $onboardedAt,
        $completedAt,
        $participantId
    );
    if ($stmt->execute()) {
        $stmt->close();
        $_SESSION['flash_success'] =
            'Candidate status updated successfully.';
        header(
            'Location: ' .
            url(
                'programme/cohort_candidates.php?cohort_id='
                . (int) $participant['cohort_id']
            )
        );
        exit;
    }
    $stmt->close();
    $_SESSION['flash_error'] =
        'Unable to update candidate status.';
    header(
        'Location: ' .
        url(
            'programme/update_candidate_status.php?participant_id='
            . $participantId
        )
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Candidate Name
|--------------------------------------------------------------------------
*/
$candidateName = trim(
    ($participant['first_name'] ?? '')
    . ' '
    . ($participant['last_name'] ?? '')
);
if ($candidateName === '') {
    $candidateName = 'Candidate';
}
/*
|--------------------------------------------------------------------------
| Current Status
|--------------------------------------------------------------------------
*/
$currentStatus =
    $participant['participation_status']
    ?? 'selected';
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
        Update Candidate Status |
        <?= e($candidateName) ?> |
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
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="dashboard__main">
        <!-- =================================================
             HEADER
        ================================================== -->
        <header class="dash-header">
            <div class="dash-header__left">
                <h1 class="dash-header__title">
                    Update Candidate Status
                </h1>
            </div>
            <div class="dash-header__right">
                <div class="dash-header__user">
                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($candidateName) ?>&background=1a56db&color=fff&size=80"
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
                 NAVIGATION
            ================================================== -->
            <div
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:.75rem;
                    margin-bottom:1.5rem;
                "
            >
                <a
                    href="<?= url(
                        'programme/cohort_candidates.php?cohort_id='
                        . (int) $participant['cohort_id']
                    ) ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:.5rem;
                    "
                >
                    <i class="fas fa-arrow-left"></i>
                    Back to Cohort Candidates
                </a>
                <a
                    href="<?= url(
                        'programme/candidate_view.php?id='
                        . (int) $participant['user_id']
                    ) ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:.5rem;
                    "
                >
                    <i class="fas fa-eye"></i>
                    View Candidate
                </a>
            </div>
            <!-- =================================================
                 CANDIDATE
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        <?= e($candidateName) ?>
                    </h1>
                    <p>
                        <?= e(
                            $participant['email']
                            ?? 'No email available'
                        ) ?>
                    </p>
                    <p style="margin-top:.5rem;">
                        Programme:
                        <strong>
                            <?= e(
                                $participant['programme_name']
                            ) ?>
                        </strong>
                    </p>
                    <p style="margin-top:.35rem;">
                        Cohort:
                        <strong>
                            <?= e(
                                $participant['cohort_name']
                            ) ?>
                        </strong>
                    </p>
                </div>
            </div>
            <!-- =================================================
                 STATUS FORM
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2>
                        <i class="fas fa-user-pen"></i>
                        Participation Status
                    </h2>
                    <p style="margin-top:.5rem;">
                        Update the candidate's current
                        participation status.
                    </p>
                    <form
                        method="POST"
                        style="margin-top:1.5rem;"
                    >
                        <div
                            style="
                                max-width:500px;
                            "
                        >
                            <label
                                for="status"
                                style="
                                    display:block;
                                    font-weight:600;
                                    margin-bottom:.5rem;
                                "
                            >
                                Status
                            </label>
                            <select
                                id="status"
                                name="status"
                                required
                                style="
                                    width:100%;
                                    padding:.8rem;
                                    border:1px solid #d1d5db;
                                    border-radius:8px;
                                    background:#fff;
                                "
                            >
                                <option
                                    value="selected"
                                    <?= $currentStatus === 'selected'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Selected
                                </option>
                                <option
                                    value="onboarded"
                                    <?= $currentStatus === 'onboarded'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Onboarded
                                </option>
                                <option
                                    value="active"
                                    <?= $currentStatus === 'active'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Active
                                </option>
                                <option
                                    value="completed"
                                    <?= $currentStatus === 'completed'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Completed
                                </option>
                                <option
                                    value="withdrawn"
                                    <?= $currentStatus === 'withdrawn'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Withdrawn
                                </option>
                            </select>
                        </div>
                        <!-- =================================================
                             CURRENT DATES
                        ================================================== -->
                        <div
                            style="
                                display:grid;
                                grid-template-columns:
                                    repeat(
                                        auto-fit,
                                        minmax(180px,1fr)
                                    );
                                gap:1rem;
                                margin-top:1.5rem;
                            "
                        >
                            <div>
                                <strong>
                                    Selected
                                </strong>
                                <div
                                    style="
                                        margin-top:.35rem;
                                    "
                                >
                                    <?= !empty(
                                        $participant['selected_at']
                                    )
                                        ? e(
                                            date(
                                                'd M Y H:i',
                                                strtotime(
                                                    $participant[
                                                        'selected_at'
                                                    ]
                                                )
                                            )
                                        )
                                        : 'Not recorded'
                                    ?>
                                </div>
                            </div>
                            <div>
                                <strong>
                                    Onboarded
                                </strong>
                                <div
                                    style="
                                        margin-top:.35rem;
                                    "
                                >
                                    <?= !empty(
                                        $participant['onboarded_at']
                                    )
                                        ? e(
                                            date(
                                                'd M Y H:i',
                                                strtotime(
                                                    $participant[
                                                        'onboarded_at'
                                                    ]
                                                )
                                            )
                                        )
                                        : 'Not recorded'
                                    ?>
                                </div>
                            </div>
                            <div>
                                <strong>
                                    Completed
                                </strong>
                                <div
                                    style="
                                        margin-top:.35rem;
                                    "
                                >
                                    <?= !empty(
                                        $participant['completed_at']
                                    )
                                        ? e(
                                            date(
                                                'd M Y H:i',
                                                strtotime(
                                                    $participant[
                                                        'completed_at'
                                                    ]
                                                )
                                            )
                                        )
                                        : 'Not recorded'
                                    ?>
                                </div>
                            </div>
                        </div>
                        <!-- =================================================
                             ACTIONS
                        ================================================== -->
                        <div
                            style="
                                display:flex;
                                flex-wrap:wrap;
                                gap:.75rem;
                                margin-top:2rem;
                            "
                        >
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
                                <i class="fas fa-save"></i>
                                Save Status
                            </button>
                            <a
                                href="<?= url(
                                    'programme/cohort_candidates.php?cohort_id='
                                    . (int) $participant['cohort_id']
                                ) ?>"
                                class="sidebar__link"
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:.5rem;
                                "
                            >
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>