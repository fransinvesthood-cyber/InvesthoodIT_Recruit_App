<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Actions Controller
 * ================================================
 * Handles opportunity create/update/status/duplicate/archive.
 * Admin-only. CSRF protected. Server-side validation.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');
enforce_csrf();

$userId = (int) current_user_id();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $errors = [];
        $id = OpportunityController::create($userId, $_POST, $errors);
        if ($id) {
            set_flash('success', 'Opportunity Created', 'The opportunity was created successfully.');
            safe_redirect('admin/opportunity_detail.php?id=' . $id);
        }
        $_SESSION['opportunity_form_old'] = $_POST;
        $_SESSION['opportunity_form_errors'] = $errors;
        set_flash('error', 'Validation Failed', 'Please correct the highlighted fields and try again.');
        safe_redirect('admin/opportunity_create.php');
        break;

    case 'update':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        if (OpportunityController::update($userId, $id, $_POST, $errors)) {
            set_flash('success', 'Opportunity Updated', 'The opportunity was updated successfully.');
            safe_redirect('admin/opportunity_detail.php?id=' . $id);
        }
        $_SESSION['opportunity_form_old'] = $_POST;
        $_SESSION['opportunity_form_errors'] = $errors;
        set_flash('error', 'Update Failed', isset($errors['status']) ? $errors['status'] : 'Please correct the highlighted fields and try again.');
        safe_redirect('admin/opportunity_edit.php?id=' . $id);
        break;

    case 'publish':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        if (OpportunityController::publish($userId, $id, $errors)) {
            set_flash('success', 'Opportunity Published', 'The opportunity is now live for candidates.');
        } else {
            set_flash('error', 'Publish Failed', $errors['status'] ?? $errors['general'] ?? 'Unable to publish opportunity.');
        }
        safe_redirect('admin/opportunity_detail.php?id=' . $id);
        break;

    case 'close':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        if (OpportunityController::close($userId, $id, $errors)) {
            set_flash('success', 'Opportunity Closed', 'The opportunity is now closed to new applications.');
        } else {
            set_flash('error', 'Close Failed', $errors['status'] ?? $errors['general'] ?? 'Unable to close opportunity.');
        }
        safe_redirect('admin/opportunity_detail.php?id=' . $id);
        break;

    case 'archive':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        if (OpportunityController::archive($userId, $id, $errors)) {
            set_flash('success', 'Opportunity Archived', 'The opportunity was archived.');
        } else {
            set_flash('error', 'Archive Failed', $errors['status'] ?? $errors['general'] ?? 'Unable to archive opportunity.');
        }
        safe_redirect('admin/opportunities.php');
        break;

    case 'duplicate':
        $id = (int) ($_POST['id'] ?? 0);
        $errors = [];
        $newId = OpportunityController::duplicate($userId, $id, $errors);
        if ($newId) {
            set_flash('success', 'Opportunity Duplicated', 'A copy of the opportunity was created as a draft.');
            safe_redirect('admin/opportunity_detail.php?id=' . $newId);
        }
        set_flash('error', 'Duplicate Failed', $errors['general'] ?? 'Unable to duplicate opportunity.');
        safe_redirect('admin/opportunities.php');
        break;

    default:
        set_flash('error', 'Invalid Action', 'The requested action is not recognised.');
        safe_redirect('admin/opportunities.php');
}
