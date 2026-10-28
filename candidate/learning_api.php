<?php
/**
 * INVESTHOOD IT - Learning & Skills AJAX API
 * Handles: mark lesson complete, start course, mark notification read.
 * All endpoints are CSRF-protected and scope writes to the authenticated
 * candidate. Server-side validation only.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId = (int) current_user_id();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Only POST actions are supported.
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_csrf();

$input = [];
parse_str(file_get_contents('php://input'), $input);

$action = $input['action'] ?? '';

// ---- MARK LESSON COMPLETE ----
if ($action === 'mark_lesson_complete') {
    $assignmentId = isset($input['assignment_id']) ? (int) $input['assignment_id'] : 0;
    $lessonId = isset($input['lesson_id']) ? (int) $input['lesson_id'] : 0;

    if ($assignmentId <= 0 || $lessonId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing assignment or lesson id.']);
        exit;
    }

    $result = CandidateLearningController::markLessonComplete($candidateId, $assignmentId, $lessonId);
    if ($result === false || !($result['success'] ?? false)) {
        echo json_encode(['success' => false, 'message' => 'Failed to update progress.']);
        exit;
    }
    echo json_encode(['success' => true] + $result);
    exit;
}

// ---- START COURSE ----
if ($action === 'start_course') {
    $assignmentId = isset($input['assignment_id']) ? (int) $input['assignment_id'] : 0;
    if ($assignmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing course id.']);
        exit;
    }
    $result = CandidateLearningController::start($candidateId, $assignmentId);
    echo json_encode($result);
    exit;
}

// ---- MARK NOTIFICATION READ (reuse existing pattern) ----
if ($action === 'mark_read') {
    $notificationId = isset($input['notification_id']) ? (int) $input['notification_id'] : 0;
    if ($notificationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing id.']);
        exit;
    }
    // Generic owner-scoped mark-as-read (works for any category).
    $row = Database::fetchOne(
        "UPDATE candidate_notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
        'ii', [$notificationId, $candidateId]
    );
    echo json_encode(['success' => (int) $row > 0]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
