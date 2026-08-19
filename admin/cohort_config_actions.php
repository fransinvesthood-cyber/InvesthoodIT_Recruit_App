<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Configuration Actions
 * ================================================
 * Handles cohort eligibility, skills, required
 * documents and workflow configuration.
 * Admin-only. CSRF protected.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');
enforce_csrf();

$userId = (int) current_user_id();
$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

$cohort = Cohort::find($id);
if (!$cohort) {
    set_flash('error', 'Not Found', 'The requested cohort does not exist.');
    safe_redirect('admin/programmes.php');
}

switch ($action) {

case 'eligibility':
        $errors = [];
        if (CohortController::updateEligibility($userId, $id, $_POST, $errors)) {
            set_flash('success', 'Eligibility Updated', 'Cohort eligibility requirements were updated.');
        } else {
            $msg = $errors['general'] ?? $errors['qualification_level'] ?? $errors['max_experience'] ?? $errors['max_completion_year'] ?? 'Unable to update eligibility requirements.';
            set_flash('error', 'Update Failed', $msg);
        }
        safe_redirect('admin/cohort_detail.php?id=' . $id . '#eligibility');
        break;

    case 'skills':
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
        CohortSkill::replaceForCohort($id, $normalised);
        AuditLog::log($userId, 'Cohort Skills Updated', 'cohort', $id, 'Cohort skills configuration updated');
        set_flash('success', 'Skills Updated', 'Cohort skills were updated.');
        safe_redirect('admin/cohort_detail.php?id=' . $id . '#eligibility');
        break;

case 'document_add':
        $name = Sanitizer::stripTags($_POST['document_name'] ?? '');
        if ($name === '') {
            set_flash('error', 'Validation Failed', 'A document type is required.');
            safe_redirect('admin/cohort_detail.php?id=' . $id . '#documents');
        }

        // Prevent duplicate key error: check if this document already exists for the cohort
        $existing = CohortDocument::forCohort($id);
        foreach ($existing as $doc) {
            if (strcasecmp((string) $doc['document_name'], $name) === 0) {
                set_flash('error', 'Duplicate Document', 'The document "' . $name . '" has already been added to this cohort.');
                safe_redirect('admin/cohort_detail.php?id=' . $id . '#documents');
            }
        }

        try {
            CohortDocument::add($id, [
                'document_name'         => $name,
                'is_required'           => isset($_POST['is_required']) ? 1 : 0,
                'verification_required' => isset($_POST['verification_required']) ? 1 : 0,
                'expiry_required'       => isset($_POST['expiry_required']) ? 1 : 0,
            ]);
        } catch (RuntimeException $e) {
            // Fallback in case of a race / unexpected DB error on duplicate
            set_flash('error', 'Duplicate Document', 'The document "' . $name . '" has already been added to this cohort.');
            safe_redirect('admin/cohort_detail.php?id=' . $id . '#documents');
        }

        AuditLog::log($userId, 'Evidence Requirement Added', 'cohort', $id, 'Added required document: ' . $name);
        set_flash('success', 'Document Added', 'The required document was added.');
        safe_redirect('admin/cohort_detail.php?id=' . $id . '#documents');
        break;

    case 'document_delete':
        $docId = (int) ($_POST['document_id'] ?? 0);
        CohortDocument::delete($docId, $id);
        AuditLog::log($userId, 'Evidence Requirement Removed', 'cohort', $id, 'Removed required document');
        set_flash('success', 'Document Removed', 'The required document was removed.');
        safe_redirect('admin/cohort_detail.php?id=' . $id . '#documents');
        break;

    case 'workflow':
        $stages = $_POST['stages'] ?? [];
        $stages = is_array($stages) ? array_map('strval', $stages) : [];
        CohortWorkflow::initForCohort($id);
        CohortWorkflow::replaceForCohort($id, $stages);
        AuditLog::log($userId, 'Workflow Configuration Updated', 'cohort', $id, 'Cohort workflow stages updated');
        set_flash('success', 'Workflow Updated', 'Cohort workflow configuration was updated.');
        safe_redirect('admin/cohort_detail.php?id=' . $id . '#workflow');
        break;

    default:
        set_flash('error', 'Invalid Action', 'The requested action is not recognised.');
        safe_redirect('admin/programmes.php');
}
