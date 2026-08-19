<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Eligibility Actions
 * ================================================
 * Handles programme-level eligibility configuration.
 * Admin-only. CSRF protected.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');
enforce_csrf();

$userId = (int) current_user_id();
$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

switch ($action) {
    case 'eligibility':
        $programme = Programme::find($id);
        if (!$programme) {
            set_flash('error', 'Not Found', 'Programme not found.');
            safe_redirect('admin/programmes.php');
        }

        $errors = [];
        if (ProgrammeController::updateEligibility($userId, $id, $_POST, $errors)) {
            set_flash('success', 'Eligibility Updated', 'Programme eligibility requirements were updated.');
        } else {
            $msg = $errors['general'] ?? $errors['qualification_level'] ?? $errors['max_experience'] ?? $errors['max_completion_year'] ?? 'Unable to update eligibility requirements.';
            set_flash('error', 'Update Failed', $msg);
        }
safe_redirect('admin/programme_detail.php?id=' . $id . '#eligibility');
        break;

    case 'skills':
        $programme = Programme::find($id);
        if (!$programme) {
            set_flash('error', 'Not Found', 'Programme not found.');
            safe_redirect('admin/programmes.php');
        }

        $skills = $_POST['skills'] ?? [];
        $normalised = [];
        foreach (['required_technical', 'preferred_technical', 'required_soft'] as $cat) {
            $items = $skills[$cat] ?? [];
            if (is_array($items)) {
                foreach ($items as $name) {
                    $name = Sanitizer::stripTags($name ?? '');
                    if ($name !== '') {
                        $normalised[] = ['name' => $name, 'category' => $cat];
                    }
                }
            }
        }
        ProgrammeSkill::replaceForProgramme($id, $normalised);
        AuditLog::log($userId, 'Programme Skills Updated', 'programme', $id, 'Programme skills configuration updated');
        set_flash('success', 'Skills Updated', 'Programme skills were updated.');
        safe_redirect('admin/programme_detail.php?id=' . $id . '#eligibility');
        break;

    default:
        set_flash('error', 'Invalid Action', 'The requested action is not recognised.');
        safe_redirect('admin/programmes.php');
}
