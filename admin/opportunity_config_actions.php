<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Config Actions Controller
 * ================================================
 * Handles eligibility, skills, documents and responsibilities
 * updates for a single opportunity. Admin-only. CSRF protected.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');
enforce_csrf();

$userId = (int) current_user_id();
$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

switch ($action) {
    case 'eligibility':
        $errors = [];
        if (OpportunityController::updateEligibility($userId, $id, $_POST, $errors)) {
            set_flash('success', 'Eligibility Updated', 'Opportunity eligibility requirements were saved.');
        } else {
            set_flash('error', 'Update Failed', $errors['general'] ?? 'Unable to update eligibility.');
        }
        safe_redirect('admin/opportunity_detail.php?id=' . $id . '#eligibility');
        break;

    case 'skills':
        $errors = [];
        if (OpportunityController::updateSkills($userId, $id, $_POST, $errors)) {
            set_flash('success', 'Skills Updated', 'Opportunity skill requirements were saved.');
        } else {
            set_flash('error', 'Update Failed', $errors['general'] ?? 'Unable to update skills.');
        }
        safe_redirect('admin/opportunity_detail.php?id=' . $id . '#skills');
        break;

    case 'documents':
        $errors = [];
        if (OpportunityController::updateDocuments($userId, $id, $_POST, $errors)) {
            set_flash('success', 'Documents Updated', 'Required document configuration was saved.');
        } else {
            set_flash('error', 'Update Failed', $errors['general'] ?? 'Unable to update documents.');
        }
        safe_redirect('admin/opportunity_detail.php?id=' . $id . '#documents');
        break;

    case 'responsibilities':
        $errors = [];
        if (OpportunityController::updateResponsibilities($userId, $id, $_POST, $errors)) {
            set_flash('success', 'Responsibilities Updated', 'Opportunity responsibilities were saved.');
        } else {
            set_flash('error', 'Update Failed', $errors['general'] ?? 'Unable to update responsibilities.');
        }
        safe_redirect('admin/opportunity_detail.php?id=' . $id . '#responsibilities');
        break;

    default:
        set_flash('error', 'Invalid Action', 'The requested action is not recognised.');
        safe_redirect('admin/opportunity_detail.php?id=' . $id);
}
