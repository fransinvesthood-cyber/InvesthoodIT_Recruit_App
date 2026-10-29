<?php
/**
 * INVESTHOOD IT - Admin Learning Actions
 * Handles course assignment from the admin learning management page.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role(['admin', 'programme_manager', 'programme_officer']);
require_csrf();

$userId = (int) current_user_id();

$action = trim((string) ($_POST['action'] ?? 'assign'));
$programmeId = isset($_POST['programme_id']) ? (int) $_POST['programme_id'] : 0;
$candidateId = isset($_POST['candidate_id']) ? (int) $_POST['candidate_id'] : 0;
$dueDate = trim((string) ($_POST['due_date'] ?? '')) ?: null;

if ($action === 'save_programme') {
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '') {
        set_flash('error', 'Programme not saved', 'A programme title is required.');
        safe_redirect('admin/learning_programme_form.php' . ($programmeId ? '?id=' . $programmeId : ''));
    }

    try {
        $data = [
            'title' => $title,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'objectives' => trim((string) ($_POST['objectives'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? 'general')) ?: 'general',
            'provider' => trim((string) ($_POST['provider'] ?? '')) ?: null,
            'duration_hours' => max(0, (int) ($_POST['duration_hours'] ?? 0)),
            'status' => in_array($_POST['status'] ?? 'active', ['active', 'archived'], true) ? $_POST['status'] : 'active',
        ];
        if ($programmeId > 0) {
            LearningProgramme::update($programmeId, $data);
            set_flash('success', 'Programme updated', 'The learning programme was updated.');
        } else {
            $programmeId = LearningProgramme::create($data);
            set_flash('success', 'Programme created', 'The learning programme is ready for lessons and assignments.');
        }
        safe_redirect('admin/learning_programme_form.php?id=' . $programmeId);
    } catch (Throwable $e) {
        error_log('[Admin Learning] programme save: ' . $e->getMessage());
        set_flash('error', 'Programme not saved', 'The programme could not be saved.');
        safe_redirect('admin/learning.php');
    }
}

if ($action === 'add_lesson') {
    $lessonTitle = trim((string) ($_POST['lesson_title'] ?? ''));
    $lessonType = trim((string) ($_POST['lesson_type'] ?? 'lesson'));
    if ($programmeId <= 0 || $lessonTitle === '') {
        set_flash('error', 'Lesson not added', 'Select a programme and provide a lesson title.');
        safe_redirect('admin/learning_programme_form.php?id=' . $programmeId);
    }
    try {
        $programme = LearningProgramme::find($programmeId);
        if (!$programme) {
            set_flash('error', 'Programme not found', 'The programme no longer exists.');
            safe_redirect('admin/learning.php');
        }
        LearningProgramme::addLesson($programmeId, [
            'title' => $lessonTitle,
            'type' => $lessonType,
            'content_url' => trim((string) ($_POST['content_url'] ?? '')) ?: null,
            'is_required' => 1,
        ]);
        set_flash('success', 'Lesson added', 'The lesson was added to the programme.');
    } catch (Throwable $e) {
        error_log('[Admin Learning] lesson save: ' . $e->getMessage());
        set_flash('error', 'Lesson not added', 'The lesson could not be saved.');
    }
    safe_redirect('admin/learning_programme_form.php?id=' . $programmeId);
}

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
    if (($programme['status'] ?? '') !== 'active') {
        set_flash('error', 'Programme unavailable', 'Only published programmes can be assigned.');
        safe_redirect('admin/learning.php');
    }

    $candidate = User::find($candidateId);
    $candidateRole = $candidate ? Database::fetchOne(
        "SELECT slug FROM roles WHERE id = ? LIMIT 1", 'i', [(int) $candidate['role_id']]
    ) : null;
    if (!$candidate || !in_array($candidateRole['slug'] ?? '', ['candidate', 'consultant'], true)) {
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
