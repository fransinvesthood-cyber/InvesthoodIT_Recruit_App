<?php
/**
 * ================================================================
 * INVESTHOOD IT - Assign Candidates
 * ================================================================
 * Role: Programme Manager
 *
 * Allows a Programme Manager to assign candidates to a cohort.
 *
 * Database tables used:
 *   users
 *   programmes
 *   cohorts
 *   cohort_participants
 * ================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$conn = Database::getConnection();
$currentPage = 'cohorts';
/*
|--------------------------------------------------------------------------
| Programme Manager
|--------------------------------------------------------------------------
*/
$managerId = (int) ($user['user_id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function assign_candidate_flash(string $type, string $message): void
{
    if (!isset($_SESSION['assign_candidate_flash'])) {
        $_SESSION['assign_candidate_flash'] = [];
    }
    $_SESSION['assign_candidate_flash'][] = [
        'type' => $type,
        'message' => $message
    ];
}
function get_assign_candidate_flashes(): string
{
    if (
        empty($_SESSION['assign_candidate_flash']) ||
        !is_array($_SESSION['assign_candidate_flash'])
    ) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['assign_candidate_flash'] as $flash) {
        $type = $flash['type'] ?? 'info';
        $message = $flash['message'] ?? '';
        $class = 'alert-info';
        if ($type === 'success') {
            $class = 'alert-success';
        } elseif ($type === 'error') {
            $class = 'alert-danger';
        } elseif ($type === 'warning') {
            $class = 'alert-warning';
        }
        $html .= '
            <div
                class="alert ' . e($class) . '"
                style="
                    margin-bottom:1rem;
                    padding:1rem 1.25rem;
                    border-radius:10px;
                    font-weight:500;
                "
            >
                ' . e($message) . '
            </div>
        ';
    }
    unset($_SESSION['assign_candidate_flash']);
    return $html;
}
/*
|--------------------------------------------------------------------------
| Get Cohort ID
|--------------------------------------------------------------------------
*/
$cohortId = (int) ($_GET['cohort_id'] ?? $_POST['cohort_id'] ?? 0);
if ($cohortId <= 0) {
    assign_candidate_flash(
        'error',
        'Invalid cohort selected.'
    );
    header(
        'Location: ' . url('programme/cohorts.php')
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Verify Cohort Belongs To Programme Manager
|--------------------------------------------------------------------------
*/
$cohort = null;
$stmt = $conn->prepare("
    SELECT
        c.id,
        c.name AS cohort_name,
        c.programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.status AS programme_status,
        p.programme_manager_id
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE c.id = ?
      AND p.programme_manager_id = ?
    LIMIT 1
");
if (!$stmt) {
    die(
        'Database error while preparing cohort query: '
        . e($conn->error)
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
if (!$cohort) {
    assign_candidate_flash(
        'error',
        'The selected cohort does not exist or is not assigned to you.'
    );
    header(
        'Location: ' . url('programme/cohorts.php')
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
$search = trim(
    $_GET['search']
    ?? $_POST['search']
    ?? ''
);
/*
|--------------------------------------------------------------------------
| Process Assignment
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    |
    | If your project already has a CSRF implementation, use it here.
    | This check is intentionally conditional so this page does not
    | depend on an unknown helper name.
    |
    */
    if (
        function_exists('verify_csrf_token') &&
        isset($_POST['csrf_token'])
    ) {
        if (!verify_csrf_token($_POST['csrf_token'])) {
            assign_candidate_flash(
                'error',
                'Your session has expired. Please try again.'
            );
            header(
                'Location: ' . url(
                    'programme/assign_candidates.php?cohort_id='
                    . $cohortId
                )
            );
            exit;
        }
    }
    /*
    |--------------------------------------------------------------------------
    | Selected Candidates
    |--------------------------------------------------------------------------
    */
    $selectedCandidates = $_POST['candidate_ids'] ?? [];
    if (!is_array($selectedCandidates)) {
        $selectedCandidates = [];
    }
    /*
    |--------------------------------------------------------------------------
    | Clean Candidate IDs
    |--------------------------------------------------------------------------
    */
    $candidateIds = [];
    foreach ($selectedCandidates as $candidateId) {
        $candidateId = (int) $candidateId;
        if ($candidateId > 0) {
            $candidateIds[$candidateId] = $candidateId;
        }
    }
    $candidateIds = array_values($candidateIds);
    /*
    |--------------------------------------------------------------------------
    | Nothing Selected
    |--------------------------------------------------------------------------
    */
    if (empty($candidateIds)) {
        assign_candidate_flash(
            'warning',
            'Please select at least one candidate to assign.'
        );
        header(
            'Location: ' . url(
                'programme/assign_candidates.php?cohort_id='
                . $cohortId
            )
        );
        exit;
    }
    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */
    $conn->begin_transaction();
    try {
        $assignedCount = 0;
        $alreadyAssignedCount = 0;
        $invalidCandidateCount = 0;
        /*
        |--------------------------------------------------------------------------
        | Verify Candidate Exists
        |--------------------------------------------------------------------------
        */
        $candidateCheck = $conn->prepare("
            SELECT
                id,
                first_name,
                last_name,
                email,
                status
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        if (!$candidateCheck) {
            throw new Exception(
                'Unable to prepare candidate verification query.'
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Check Existing Assignment
        |--------------------------------------------------------------------------
        */
        $existingCheck = $conn->prepare("
            SELECT id
            FROM cohort_participants
            WHERE cohort_id = ?
              AND user_id = ?
            LIMIT 1
        ");
        if (!$existingCheck) {
            throw new Exception(
                'Unable to prepare duplicate assignment check.'
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Insert Assignment
        |--------------------------------------------------------------------------
        */
        $insertParticipant = $conn->prepare("
            INSERT INTO cohort_participants (
                cohort_id,
                user_id,
                status,
                selected_at,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                ?,
                'selected',
                NOW(),
                NOW(),
                NOW()
            )
        ");
        if (!$insertParticipant) {
            throw new Exception(
                'Unable to prepare candidate assignment query.'
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Process Each Candidate
        |--------------------------------------------------------------------------
        */
        foreach ($candidateIds as $candidateId) {
            /*
            |--------------------------------------------------------------
            | Verify User
            |--------------------------------------------------------------
            */
            $candidateCheck->bind_param(
                'i',
                $candidateId
            );
            $candidateCheck->execute();
            $candidateResult = $candidateCheck->get_result();
            $candidate = $candidateResult->fetch_assoc();
            if (!$candidate) {
                $invalidCandidateCount++;
                continue;
            }
            /*
            |--------------------------------------------------------------
            | Check Duplicate
            |--------------------------------------------------------------
            */
            $existingCheck->bind_param(
                'ii',
                $cohortId,
                $candidateId
            );
            $existingCheck->execute();
            $existingResult = $existingCheck->get_result();
            $existing = $existingResult->fetch_assoc();
            if ($existing) {
                $alreadyAssignedCount++;
                continue;
            }
            /*
            |--------------------------------------------------------------
            | Insert
            |--------------------------------------------------------------
            */
            $insertParticipant->bind_param(
                'ii',
                $cohortId,
                $candidateId
            );
            if (!$insertParticipant->execute()) {
                throw new Exception(
                    'Failed to assign candidate ID '
                    . $candidateId
                    . '.'
                );
            }
            $assignedCount++;
        }
        /*
        |--------------------------------------------------------------------------
        | Close Statements
        |--------------------------------------------------------------------------
        */
        $candidateCheck->close();
        $existingCheck->close();
        $insertParticipant->close();
        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */
        $conn->commit();
        /*
        |--------------------------------------------------------------------------
        | Success Message
        |--------------------------------------------------------------------------
        */
        if ($assignedCount > 0) {
            $message =
                $assignedCount
                . ' candidate'
                . ($assignedCount === 1 ? '' : 's')
                . ' successfully assigned to '
                . $cohort['cohort_name']
                . '.';
            if ($alreadyAssignedCount > 0) {
                $message .=
                    ' '
                    . $alreadyAssignedCount
                    . ' candidate'
                    . ($alreadyAssignedCount === 1 ? '' : 's')
                    . ' were already assigned.';
            }
            assign_candidate_flash(
                'success',
                $message
            );
        } elseif ($alreadyAssignedCount > 0) {
            assign_candidate_flash(
                'warning',
                'All selected candidates are already assigned to this cohort.'
            );
        } else {
            assign_candidate_flash(
                'warning',
                'No candidates were assigned.'
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Redirect To Cohort
        |--------------------------------------------------------------------------
        */
        header(
            'Location: ' . url(
                'programme/cohort_view.php?id='
                . $cohortId
            )
        );
        exit;
    } catch (Throwable $e) {
        /*
        |--------------------------------------------------------------------------
        | Rollback
        |--------------------------------------------------------------------------
        */
        $conn->rollback();
        assign_candidate_flash(
            'error',
            'Candidate assignment failed: '
            . $e->getMessage()
        );
        header(
            'Location: ' . url(
                'programme/assign_candidates.php?cohort_id='
                . $cohortId
            )
        );
        exit;
    }
}
/*
|--------------------------------------------------------------------------
| Fetch Candidates Already Assigned To This Cohort
|--------------------------------------------------------------------------
*/
$assignedCandidateIds = [];
$stmt = $conn->prepare("
    SELECT user_id
    FROM cohort_participants
    WHERE cohort_id = ?
");
if ($stmt) {
    $stmt->bind_param(
        'i',
        $cohortId
    );
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignedCandidateIds[] = (int) $row['user_id'];
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Fetch Available Candidates
|--------------------------------------------------------------------------
|
| Candidates already assigned to THIS cohort are excluded.
|
| We intentionally do not filter by a role column because your
| confirmed users structure has been using first_name, last_name,
| email, phone and status, while the exact role implementation
| may differ in your database.
|
*/
$candidates = [];
$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.status
    FROM users u
    WHERE u.role = 9
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
            CONCAT(u.first_name,' ',u.last_name) LIKE ?
            OR u.email LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $types .= 'ss';
    $params[] = $searchValue;
    $params[] = $searchValue;
}
/*
|--------------------------------------------------------------------------
| Exclude Existing Participants
|--------------------------------------------------------------------------
*/
$sql .= "
    AND NOT EXISTS (
        SELECT 1
        FROM cohort_participants cp
        WHERE cp.cohort_id = ?
          AND cp.user_id = u.id
    )
    ORDER BY u.first_name,u.last_name
";
$types .= 'i';
$params[] = $cohortId;
/*
|--------------------------------------------------------------------------
| Prepare Candidate Query
|--------------------------------------------------------------------------
*/
$candidates = [];
$sql = "
SELECT
    u.id,
    u.first_name,
    u.last_name,
    u.email,
    u.phone,
    u.status
FROM users u
WHERE u.role_id = 9
";
$types = '';
$params = [];
if ($search !== '') {
    $sql .= "
        AND (
            CONCAT(u.first_name,' ',u.last_name) LIKE ?
            OR u.email LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $types .= 'ss';
    $params[] = $searchValue;
    $params[] = $searchValue;
}
$sql .= "
AND NOT EXISTS (
    SELECT 1
    FROM cohort_participants cp
    WHERE cp.cohort_id = ?
      AND cp.user_id = u.id
)
ORDER BY u.first_name,u.last_name
";
$types .= 'i';
$params[] = $cohortId;
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $bind = [$types];
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$stmt,'bind_param'],$bind);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/
$flashes = get_assign_candidate_flashes();
/*
|--------------------------------------------------------------------------
| Candidate Count
|--------------------------------------------------------------------------
*/
$candidateCount = count($candidates);
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
        Assign Candidates | Investhood IT
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
                    Assign Candidates
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
                 PAGE INTRODUCTION
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        <i class="fas fa-user-plus"></i>
                        Assign
                        <span class="text-gradient">
                            Candidates
                        </span>
                    </h1>
                    <p>
                        Select candidates to assign them to this cohort.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 COHORT INFORMATION
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
                            <h2 style="margin-bottom:0.4rem;">
                                <i class="fas fa-users"></i>
                                <?= e($cohort['cohort_name']) ?>
                            </h2>
                            <p style="margin:0;">
                                <strong>
                                    Programme:
                                </strong>
                                <?= e($cohort['programme_name']) ?>
                            </p>
                        </div>
                        <div>
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
                                "
                            >
                                <i class="fas fa-arrow-left"></i>
                                Back to Cohort
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 SEARCH
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:1rem;">
                        <i class="fas fa-search"></i>
                        Find Candidates
                    </h2>
                    <form
                        method="GET"
                        action="<?= url('programme/assign_candidates.php') ?>"
                    >
                        <input
                            type="hidden"
                            name="cohort_id"
                            value="<?= (int) $cohortId ?>"
                        >
                        <div
                            style="
                                display:flex;
                                gap:1rem;
                                align-items:end;
                                flex-wrap:wrap;
                            "
                        >
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
                                    Search Candidate
                                </label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    value="<?= e($search) ?>"
                                    placeholder="Name, email or phone..."
                                    style="
                                        width:100%;
                                        padding:0.8rem;
                                        border:1px solid #d1d5db;
                                        border-radius:8px;
                                        box-sizing:border-box;
                                    "
                                >
                            </div>
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
                            <?php if ($search !== ''): ?>
                                <div>
                                    <a
                                        href="<?= url(
                                            'programme/assign_candidates.php?cohort_id='
                                            . $cohortId
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
                        </div>
                    </form>
                </div>
            </div>
            <!-- =================================================
                 CANDIDATE SELECTION
            ================================================== -->
            <form
                method="POST"
                action="<?= url('programme/assign_candidates.php') ?>"
                id="assignmentForm"
            >
                <input
                    type="hidden"
                    name="cohort_id"
                    value="<?= (int) $cohortId ?>"
                >
                <input
                    type="hidden"
                    name="search"
                    value="<?= e($search) ?>"
                >
                <?php if (function_exists('csrf_token')): ?>
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >
                <?php endif; ?>
                <div
                    class="welcome-card"
                    style="margin-top:2rem;"
                >
                    <div class="welcome-card__content">
                        <!-- =================================================
                             SELECTION HEADER
                        ================================================== -->
                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                align-items:center;
                                gap:1rem;
                                flex-wrap:wrap;
                                margin-bottom:1rem;
                            "
                        >
                            <div>
                                <h2 style="margin-bottom:0.3rem;">
                                    <i class="fas fa-users"></i>
                                    Available Candidates
                                </h2>
                                <p style="margin:0;">
                                    <?= number_format($candidateCount) ?>
                                    candidate
                                    <?= $candidateCount === 1 ? '' : 's' ?>
                                    available for assignment.
                                </p>
                            </div>
                            <?php if ($candidateCount > 0): ?>
                                <div
                                    style="
                                        display:flex;
                                        gap:0.5rem;
                                        flex-wrap:wrap;
                                    "
                                >
                                    <button
                                        type="button"
                                        id="selectAllBtn"
                                        class="sidebar__link"
                                        style="
                                            border:0;
                                            cursor:pointer;
                                            display:inline-flex;
                                            align-items:center;
                                            gap:0.5rem;
                                        "
                                    >
                                        <i class="fas fa-check-double"></i>
                                        Select All
                                    </button>
                                    <button
                                        type="button"
                                        id="clearAllBtn"
                                        class="sidebar__link"
                                        style="
                                            border:0;
                                            cursor:pointer;
                                            display:inline-flex;
                                            align-items:center;
                                            gap:0.5rem;
                                        "
                                    >
                                        <i class="fas fa-times"></i>
                                        Clear
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- =================================================
                             CANDIDATES
                        ================================================== -->
                        <?php if (empty($candidates)): ?>
                        <div class="welcome-card" style="margin-top:1rem;">
                            <div class="welcome-card__content" style="text-align:center;">
                                <i class="fas fa-user-slash" style="font-size:2rem;color:#94a3b8;"></i>
                                <h3>No Candidates Available</h3>
                                <p>All available candidates have already been assigned.</p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="overview-grid" style="margin-top:1rem;">
                        <?php foreach ($candidates as $candidate): ?>
                        <div class="overview-card candidate-card">
                        <label
                            for="candidate_<?= (int)$candidate['id'] ?>"
                            style="cursor:pointer;display:block;width:100%;"
                        >
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                            <div style="display:flex;gap:1rem;align-items:center;">
                                <img
                                    src="https://ui-avatars.com/api/?name=<?= urlencode($candidate['first_name'].' '.$candidate['last_name']) ?>&background=1a56db&color=fff"
                                    style="width:56px;height:56px;border-radius:50%;"
                                >
                                <div>
                                    <br>
                                    <div style="font-weight:700; color:blue">
                                        <?= e($candidate['first_name'].' '.$candidate['last_name']) ?>
                                    </div>
                                    <div style="font-size:.9rem;color:#64748b;">
                                        <?= e($candidate['email']) ?>
                                    </div>
                                    <div style="font-size:.9rem;color:#64748b;">
                                        <?= e($candidate['phone'] ?: 'No phone') ?>
                                    </div>
                                </div>
                            </div>
                            <input
                                id="candidate_<?= (int)$candidate['id'] ?>"
                                class="candidate-checkbox"
                                type="checkbox"
                                name="candidate_ids[]"
                                value="<?= (int)$candidate['id'] ?>"
                                style="width:24px;height:24px;cursor:pointer;accent-color:#1a56db;"
                            >
                        </div>
                        </label>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <div style="margin-top:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                        <div>
                            <strong><span id="selectedCount">0</span></strong>
                            candidate(s) selected
                        </div>
                        <button
                            id="assignButton"
                            type="submit"
                            class="sidebar__link"
                            style="color:green;display:inline-flex;align-items:center;gap:.5rem;border:0;cursor:pointer;opacity:.6;"
                            disabled
                        >
                        <i class="fas fa-user-plus"></i>
                        Assign Selected Candidates
                        </button>
                        </div>
                        <?php endif; ?>
                            <!-- =================================================
                                 ASSIGN BUTTON
                            ================================================== -->

                            </div>
                        
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>
<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll(
        '.candidate-checkbox'
    );
    const selectAllButton = document.getElementById(
        'selectAllBtn'
    );
    const clearAllButton = document.getElementById(
        'clearAllBtn'
    );
    const selectedCountElement = document.getElementById(
        'selectedCount'
    );
    const assignButton = document.getElementById(
        'assignButton'
    );
    /*
    |--------------------------------------------------------------------------
    | Update Selection Count
    |--------------------------------------------------------------------------
    */
    function updateSelection() {
        let count = 0;
        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                count++;
            }
        });
        if (selectedCountElement) {
            selectedCountElement.textContent = count;
        }
        if (assignButton) {
            if (count > 0) {
                assignButton.disabled = false;
                assignButton.style.opacity = '1';
            } else {
                assignButton.disabled = true;
                assignButton.style.opacity = '0.6';
            }
        }
    }
    /*
    |--------------------------------------------------------------------------
    | Individual Checkbox
    |--------------------------------------------------------------------------
    */
    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener(
            'change',
            updateSelection
        );
    });
    /*
    |--------------------------------------------------------------------------
    | Select All
    |--------------------------------------------------------------------------
    */
    if (selectAllButton) {
        selectAllButton.addEventListener(
            'click',
            function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = true;
                });
                updateSelection();
            }
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Clear All
    |--------------------------------------------------------------------------
    */
    if (clearAllButton) {
        clearAllButton.addEventListener(
            'click',
            function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = false;
                });
                updateSelection();
            }
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Initial State
    |--------------------------------------------------------------------------
    */
    updateSelection();
    /*
    |--------------------------------------------------------------------------
    | Confirmation
    |--------------------------------------------------------------------------
    */
    const assignmentForm = document.getElementById(
        'assignmentForm'
    );
    if (assignmentForm) {
        assignmentForm.addEventListener(
            'submit',
            function (event) {
                let count = 0;
                checkboxes.forEach(function (checkbox) {
                    if (checkbox.checked) {
                        count++;
                    }
                });
                if (count === 0) {
                    event.preventDefault();
                    alert(
                        'Please select at least one candidate.'
                    );
                    return;
                }
                const confirmed = confirm(
                    'Assign '
                    + count
                    + ' candidate'
                    + (count === 1 ? '' : 's')
                    + ' to this cohort?'
                );
                if (!confirmed) {
                    event.preventDefault();
                }
            }
        );
    }
});
</script>
</body>
</html>