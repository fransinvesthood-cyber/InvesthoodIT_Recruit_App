<?php
/**
 * INVESTHOOD IT - Admin Learning Actions
 * Handles course assignment from the admin learning management page.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role(['admin', 'programme_manager', 'programme_officer']);
require_csrf();

$userId = (int) current_user_id();

$programmeId = isset($_POST['programme_id']) ? (int) $_POST['programme_id'] : 0;
$candidateId = isset($_POST['candidate_id']) ? (int) $_POST['candidate_id'] : 0;
$dueDate = isset($_POST['due_date']) ? trim($_POST['due_date']) : null;

if ($programmeId <= 0 || $candidateId <= 0) {
    set_flash('error', 'Missing data', 'Please select both a programme and a candidate.');
    safe_redirect('admin/learning.php');
}

try {
    $programme = LearningProgramme::find($programmeId);
    if (!$programme) {
        set_flash('error', 'Programme not found', 'The selected programme does not exist.');
        safe_redirect('admin/learning.php');
    }

    $candidate = User::find($candidateId);
    if (!$candidate || $candidate['role_id'] !== (int) (Role::findBySlug('candidate')['id'] ?? 0)) {
        set_flash('error', 'Candidate not found', 'The selected candidate does not exist or is not a candidate.');
        safe_redirect('admin/learning.php');
    }

    $assignmentId = LearningProgramme::assign($programmeId, $candidateId, $userId, $dueDate);
    set_flash('success', 'Course assigned', "<strong>{$programme['title']}</strong> was assigned to {$candidate['first_name']} {$candidate['last_name']}.");

    // Best-effort candidate notification.
    try {
        CandidateNotification::addLearningNotification(
            $candidateId,
            'assignment_created',
            'New course assigned',
            '<strong>' . e($programme['title']) . '</strong> has been assigned to you.' . ($dueDate ? ' The completion deadline is <strong>' . e(format_date($dueDate, 'd M Y')) . '</strong>.' : '.'),
            $assignmentId,
            'assign_' . $programmeId . '_' . $candidateId
        );
    } catch (Throwable $e) {
        error_log('[Admin Learning] candidate notification: ' . $e->getMessage());
    }

    safe_redirect('admin/learning.php');
} catch (Throwable $e) {
    error_log('[Admin Learning Actions] ' . $e->getMessage());
    set_flash('error', 'Assignment failed', 'Could not assign the course. Please try again.');
    safe_redirect('admin/learning.php');
}
