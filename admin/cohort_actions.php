<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Actions Controller
 * ================================================
 * Handles cohort create/update/status.
 * Admin-only. CSRF protected. Server-side validation.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');
enforce_csrf();

$userId = (int) current_user_id();
$action = $_POST['action'] ?? '';
$resolveAssignedStaff = static function ($rawId, string $roleSlug): ?int {
    $staffId = (int) $rawId;
    if ($staffId <= 0) return null;
    $valid = Database::fetchOne(
        "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND r.slug = ? AND u.status = 'active' LIMIT 1",
        'is',
        [$staffId, $roleSlug]
    );
    return $valid ? $staffId : null;
};
$saveCohortTeam = static function (int $cohortId) use ($resolveAssignedStaff): void {
    Database::execute(
        "UPDATE cohorts SET programme_manager_id = ?, programme_officer_id = ? WHERE id = ?",
        'iii',
        [
            $resolveAssignedStaff($_POST['programme_manager_id'] ?? 0, 'programme_manager'),
            $resolveAssignedStaff($_POST['programme_officer_id'] ?? 0, 'programme_officer'),
            $cohortId
        ]
    );
};

switch ($action) {
    case 'create':
        $errors = [];
        $id = CohortController::create($userId, $_POST, $errors);
        if ($id) {
            $saveCohortTeam((int) $id);
            $cohort = Cohort::find($id);
            set_flash('success', 'Cohort Created', 'The cohort was created successfully.');
            safe_redirect('admin/cohort_detail.php?id=' . $id);
        }
        $_SESSION['cohort_form_old'] = $_POST;
        $_SESSION['cohort_form_errors'] = $errors;
        set_flash('error', 'Validation Failed', 'Please correct the highlighted fields and try again.');
        $pid = (int) ($_POST['programme_id'] ?? 0);
        safe_redirect('admin/cohort_create.php?programme_id=' . $pid);
        break;

    case 'update':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        if (CohortController::update($userId, $id, $_POST, $errors)) {
            $saveCohortTeam($id);
            set_flash('success', 'Cohort Updated', 'The cohort was updated successfully.');
            safe_redirect('admin/cohort_detail.php?id=' . $id);
        }
        $_SESSION['cohort_form_old'] = $_POST;
        $_SESSION['cohort_form_errors'] = $errors;
        set_flash('error', 'Update Failed', $errors['status'] ?? $errors['general'] ?? 'Please correct the highlighted fields and try again.');
        safe_redirect('admin/cohort_edit.php?id=' . $id);
        break;

    case 'status':
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $errors = [];
        if (CohortController::changeStatus($userId, $id, $status, $errors)) {
            set_flash('success', 'Cohort ' . ucfirst($status), 'The cohort status was updated.');
        } else {
            set_flash('error', 'Status Change Failed', $errors['status'] ?? 'Unable to change cohort status.');
        }
        safe_redirect('admin/cohort_detail.php?id=' . $id);
        break;

    default:
        set_flash('error', 'Invalid Action', 'The requested action is not recognised.');
        safe_redirect('admin/programmes.php');
}
