<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Actions Controller
 * ================================================
 * Handles programme create/update/status/duplicate/archive.
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
$saveProgrammeTeam = static function (int $programmeId) use ($resolveAssignedStaff): void {
    Database::execute(
        "UPDATE programmes SET programme_manager_id = ?, programme_officer_id = ? WHERE id = ?",
        'iii',
        [
            $resolveAssignedStaff($_POST['programme_manager_id'] ?? 0, 'programme_manager'),
            $resolveAssignedStaff($_POST['programme_officer_id'] ?? 0, 'programme_officer'),
            $programmeId
        ]
    );
};

switch ($action) {
    case 'create':
        $errors = [];
        $id = ProgrammeController::create($userId, $_POST, $errors);
        if ($id) {
            $saveProgrammeTeam((int) $id);
            set_flash('success', 'Programme Created', 'The programme was created successfully.');
            safe_redirect('admin/programme_detail.php?id=' . $id);
        }
        $_SESSION['programme_form_old'] = $_POST;
        $_SESSION['programme_form_errors'] = $errors;
        set_flash('error', 'Validation Failed', 'Please correct the highlighted fields and try again.');
        safe_redirect('admin/programme_create.php');
        break;

    case 'update':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        if (ProgrammeController::update($userId, $id, $_POST, $errors)) {
            $saveProgrammeTeam($id);
            set_flash('success', 'Programme Updated', 'The programme was updated successfully.');
            safe_redirect('admin/programme_detail.php?id=' . $id);
        }
        $_SESSION['programme_form_old'] = $_POST;
        $_SESSION['programme_form_errors'] = $errors;
        set_flash('error', 'Update Failed', isset($errors['status']) ? $errors['status'] : 'Please correct the highlighted fields and try again.');
        safe_redirect('admin/programme_edit.php?id=' . $id);
        break;

    case 'change_status':
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $errors = [];
        if (ProgrammeController::changeStatus($userId, $id, $status, $errors)) {
            set_flash('success', 'Programme ' . ucfirst($status), 'The programme status was updated.');
        } else {
            set_flash('error', 'Status Change Failed', $errors['status'] ?? 'Unable to change programme status.');
        }
        safe_redirect('admin/programme_detail.php?id=' . $id);
        break;

    case 'duplicate':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        $newId = ProgrammeController::duplicate($userId, $id, $errors);
        if ($newId) {
            set_flash('success', 'Programme Duplicated', 'A copy of the programme was created as a draft.');
            safe_redirect('admin/programme_detail.php?id=' . $newId);
        }
        set_flash('error', 'Duplicate Failed', $errors['general'] ?? 'Unable to duplicate programme.');
        safe_redirect('admin/programmes.php');
        break;

    case 'archive':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        if (ProgrammeController::changeStatus($userId, $id, 'archived', $errors)) {
            set_flash('success', 'Programme Archived', 'The programme was archived.');
        } else {
            set_flash('error', 'Archive Failed', $errors['status'] ?? 'Unable to archive programme.');
        }
        safe_redirect('admin/programmes.php');
        break;

    default:
        set_flash('error', 'Invalid Action', 'The requested action is not recognised.');
        safe_redirect('admin/programmes.php');
}
