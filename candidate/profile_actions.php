<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Profile AJAX Dispatcher
 * ================================================
 * Single entry point for all candidate profile
 * mutations (personal, professional, picture,
 * qualifications, skills, experience, documents,
 * consent) and skill search. All actions are
 * ownership-scoped to the authenticated user.
 *
 * Every request:
 *   - requires login (candidate)
 *   - enforces CSRF (server-side)
 *   - resolves the user from the session (never from
 *     a client-supplied user_id)
 *   - returns JSON for the frontend
 * ================================================
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// ---- Helper to emit a JSON response then stop ----
function json_response(array $payload): void
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// ---- Detect AJAX requests (the profile frontend uses fetch) ----
$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || str_contains(($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'fetch');

// ---- Authentication & role (return JSON for AJAX, redirect otherwise) ----
if (!is_logged_in()) {
    if ($isAjax || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        http_response_code(401);
        json_response(['success' => false, 'message' => 'Your session has expired. Please sign in again.']);
    }
    set_flash('warning', 'Authentication Required', 'Please sign in to access that page.');
    safe_redirect('login.php');
}

if (current_role() !== 'candidate') {
    if ($isAjax || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        http_response_code(403);
        json_response(['success' => false, 'message' => 'Access denied.']);
    }
    set_flash('error', 'Access Denied', 'You do not have permission to access that page.');
    safe_redirect(role_dashboard(current_role() ?? '') ?? 'login.php');
}

// ---- CSRF enforcement for all state-changing requests ----
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!validate_csrf()) {
        http_response_code(403);
        json_response(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    }
}

// ---- The authenticated user is the ONLY profile owner ----
$userId = (int) current_user_id();
if ($userId <= 0) {
    http_response_code(401);
    json_response(['success' => false, 'message' => 'Authentication required.']);
}

// ---- Determine the requested action ----
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {

        // ============================================
        // PERSONAL INFORMATION
        // ============================================
        case 'update_personal':
            $errors = [];
            if (ProfileController::updatePersonal($userId, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Profile updated successfully!']);
            }
            json_response(['success' => false, 'message' => 'Please fix the highlighted errors.', 'errors' => $errors]);

        // ============================================
        // PROFESSIONAL INFORMATION
        // ============================================
        case 'update_professional':
            $errors = [];
            if (ProfileController::updateProfessional($userId, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Profile updated successfully!']);
            }
            json_response(['success' => false, 'message' => 'Please fix the highlighted errors.', 'errors' => $errors]);

        // ============================================
        // PROFILE PICTURE
        // ============================================
        case 'upload_picture':
            $errors = [];
            if (ProfileController::uploadPicture($userId, $_FILES['profile_picture'] ?? [], $errors)) {
                json_response(['success' => true, 'message' => 'Profile picture updated successfully!', 'avatar' => url('candidate/avatar.php')]);
            }
            json_response(['success' => false, 'message' => $errors['profile_picture'] ?? 'Picture upload failed.', 'errors' => $errors]);

        case 'remove_picture':
            if (ProfileController::removePicture($userId)) {
                json_response(['success' => true, 'message' => 'Profile picture removed.', 'avatar' => url('candidate/avatar.php')]);
            }
            json_response(['success' => false, 'message' => 'Could not remove the picture.']);

        // ============================================
        // QUALIFICATIONS
        // ============================================
        case 'qualification_add':
            $errors = [];
            $id = QualificationController::add($userId, $_POST, $errors);
            if ($id) {
                json_response(['success' => true, 'message' => 'Qualification added successfully!', 'id' => $id]);
            }
            json_response(['success' => false, 'message' => 'Please fix the highlighted errors.', 'errors' => $errors]);

        case 'qualification_update':
            $errors = [];
            $id = (int) ($_POST['id'] ?? 0);
            if (QualificationController::update($userId, $id, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Qualification updated successfully!']);
            }
            json_response(['success' => false, 'message' => $errors['general'] ?? 'Please fix the highlighted errors.', 'errors' => $errors]);

case 'qualification_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (QualificationController::delete($userId, $id)) {
                json_response(['success' => true, 'message' => 'Qualification removed.']);
            }
            json_response(['success' => false, 'message' => 'Could not remove the qualification.']);

        // ============================================
        // CERTIFICATIONS
        // ============================================
        case 'certification_add':
            $errors = [];
            $id = CertificationController::add($userId, $_POST, $errors);
            if ($id) {
                json_response(['success' => true, 'message' => 'Certification added successfully!', 'id' => $id]);
            }
            json_response(['success' => false, 'message' => 'Please fix the highlighted errors.', 'errors' => $errors]);

        case 'certification_update':
            $errors = [];
            $id = (int) ($_POST['id'] ?? 0);
            if (CertificationController::update($userId, $id, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Certification updated successfully!']);
            }
            json_response(['success' => false, 'message' => $errors['general'] ?? 'Please fix the highlighted errors.', 'errors' => $errors]);

        case 'certification_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (CertificationController::delete($userId, $id)) {
                json_response(['success' => true, 'message' => 'Certification removed.']);
            }
            json_response(['success' => false, 'message' => 'Could not remove the certification.']);

        // ============================================
        // SKILLS
        // ============================================
        case 'skill_add':
            $errors = [];
            $skillId = (int) ($_POST['skill_id'] ?? 0);
            $proficiency = trim($_POST['proficiency'] ?? 'intermediate');
            if (SkillController::add($userId, $skillId, $proficiency, $errors)) {
                $skill = Skill::find($skillId);
                json_response([
                    'success' => true,
                    'message' => 'Skill added successfully!',
                    'skill'   => $skill ? ['id' => (int) $skill['id'], 'name' => $skill['name'], 'category' => $skill['category']] : null,
                ]);
            }
            json_response(['success' => false, 'message' => $errors['skill'] ?? 'Could not add the skill.', 'errors' => $errors]);

        case 'skill_remove':
            $skillId = (int) ($_POST['skill_id'] ?? 0);
            if (SkillController::remove($userId, $skillId)) {
                json_response(['success' => true, 'message' => 'Skill removed.']);
            }
            json_response(['success' => false, 'message' => 'Could not remove the skill.']);

        // ============================================
        // WORK EXPERIENCE
        // ============================================
        case 'experience_add':
            $errors = [];
            $id = WorkExperienceController::add($userId, $_POST, $errors);
            if ($id) {
                json_response(['success' => true, 'message' => 'Work experience added successfully!', 'id' => $id]);
            }
            json_response(['success' => false, 'message' => 'Please fix the highlighted errors.', 'errors' => $errors]);

        case 'experience_update':
            $errors = [];
            $id = (int) ($_POST['id'] ?? 0);
            if (WorkExperienceController::update($userId, $id, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Work experience updated successfully!']);
            }
            json_response(['success' => false, 'message' => $errors['general'] ?? 'Please fix the highlighted errors.', 'errors' => $errors]);

        case 'experience_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (WorkExperienceController::delete($userId, $id)) {
                json_response(['success' => true, 'message' => 'Work experience removed.']);
            }
            json_response(['success' => false, 'message' => 'Could not remove the record.']);

        case 'experience_reorder':
            $ordered = $_POST['ordered_ids'] ?? [];
            if (!is_array($ordered)) {
                $ordered = [];
            }
            if (WorkExperienceController::reorder($userId, $ordered)) {
                json_response(['success' => true, 'message' => 'Order updated.']);
            }
            json_response(['success' => false, 'message' => 'Could not reorder the records.']);

        // ============================================
        // DOCUMENTS
        // ============================================
        case 'document_upload':
            $errors = [];
            $docType = trim($_POST['document_type'] ?? 'supporting');
            $id = DocumentController::upload($userId, $_FILES['document'] ?? [], $docType, $userId, $errors);
            if ($id) {
                json_response(['success' => true, 'message' => 'Document uploaded successfully!', 'id' => $id]);
            }
            json_response(['success' => false, 'message' => $errors['document'] ?? $errors['document_type'] ?? 'Document upload failed.', 'errors' => $errors]);

        case 'document_replace':
            $errors = [];
            $docId = (int) ($_POST['id'] ?? 0);
            if (DocumentController::replace($userId, $docId, $_FILES['document'] ?? [], $userId, $errors)) {
                json_response(['success' => true, 'message' => 'Document replaced successfully!']);
            }
            json_response(['success' => false, 'message' => $errors['document'] ?? $errors['general'] ?? 'Document replacement failed.', 'errors' => $errors]);

        case 'document_delete':
            $docId = (int) ($_POST['id'] ?? 0);
            if (DocumentController::delete($userId, $docId)) {
                json_response(['success' => true, 'message' => 'Document removed.']);
            }
            json_response(['success' => false, 'message' => 'Could not remove the document.']);

        // ============================================
        // CONSENT
        // ============================================
        case 'consent_update':
            $errors = [];
            $purpose = trim($_POST['purpose'] ?? '');
            $granted = !empty($_POST['granted']) && $_POST['granted'] === 'true';
            if (ConsentController::update($userId, $purpose, $granted, $errors)) {
                json_response(['success' => true, 'message' => $granted ? 'Consent granted.' : 'Consent withdrawn.', 'state' => ConsentController::stateForUser($userId)]);
            }
            json_response(['success' => false, 'message' => $errors['consent'] ?? 'Could not update consent.', 'errors' => $errors]);

        // ============================================
        // SKILL SEARCH (canonical catalogue)
        // ============================================
        case 'search_skills':
            $q = trim($_GET['q'] ?? '');
            $category = trim($_GET['category'] ?? 'all');
            $skills = Skill::search($q, $category, 30);
            json_response(['success' => true, 'skills' => $skills]);

        default:
            json_response(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Throwable $ex) {
    error_log('[ProfileActions] Error: ' . $ex->getMessage());
    json_response(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
