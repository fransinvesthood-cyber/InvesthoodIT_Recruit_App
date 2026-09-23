<?php
/** INVESTHOOD IT - Interview Feedback Save Handler */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    set_flash('error', 'Invalid Request', 'Method not allowed.');
    safe_redirect('admin/interviews.php');
}
$interviewId = (int)($_POST['interview_id'] ?? 0);
$adminId = (int)current_user_id();
if ($interviewId <= 0) {
    set_flash('error', 'Invalid Interview', 'No interview ID was provided.');
    safe_redirect('admin/interviews.php');
}
$interview = Interview::find($interviewId);
if (!$interview) {
    set_flash('error', 'Interview Not Found', 'The requested interview does not exist.');
    safe_redirect('admin/interviews.php');
}
if (($interview['status'] ?? '') !== 'completed') {
    set_flash('error', 'Not Allowed', 'Feedback can only be recorded for completed interviews.');
    safe_redirect('admin/interview.php?id=' . $interviewId);
}
require_csrf();
$input = [
    'outcome' => trim((string)($_POST['outcome'] ?? '')),
    'rating' => $_POST['rating'] ?? null,
    'strengths' => trim((string)($_POST['strengths'] ?? '')),
    'areas_of_concern' => trim((string)($_POST['areas_of_concern'] ?? '')),
    'general_feedback' => trim((string)($_POST['general_feedback'] ?? '')),
    'internal_notes' => trim((string)($_POST['internal_notes'] ?? '')),
];
$errors = InterviewFeedback::validate($input);
if ($errors !== []) {
    $_SESSION['_feedback_old'] = $input;
    $_SESSION['_feedback_errors'] = $errors;
    $_SESSION['_feedback_open'] = true;
    $first = reset($errors);
    set_flash('error', 'Validation Failed', (string)$first);
    safe_redirect('admin/interview.php?id=' . $interviewId);
}
try {
    $clean = InterviewFeedback::sanitise($input);
    $result = InterviewFeedback::save($interviewId, $clean, $adminId);
    unset($_SESSION['_feedback_old'], $_SESSION['_feedback_errors'], $_SESSION['_feedback_open']);
    $label = InterviewFeedback::label($result['outcome']);
    $appLabel = $result['target_status'] ? Application::label($result['target_status']) : null;
    $msg = ($result['is_update'] ? 'Feedback updated. Outcome: ' : 'Feedback saved. Outcome: ') . $label . '.';
    if ($appLabel) { $msg .= ' Application status is now ' . $appLabel . '.'; }
    set_flash('success', $result['is_update'] ? 'Feedback Updated' : 'Feedback Saved', $msg);
} catch (RuntimeException $e) {
    $_SESSION['_feedback_old'] = $input;
    $_SESSION['_feedback_open'] = true;
    set_flash('error', 'Could Not Save Feedback', $e->getMessage());
} catch (Exception $e) {
    error_log('[Interview Feedback] ' . $e->getMessage());
    $_SESSION['_feedback_old'] = $input;
    $_SESSION['_feedback_open'] = true;
    set_flash('error', 'Error', 'An unexpected error occurred. Please try again.');
}
safe_redirect('admin/interview.php?id=' . $interviewId);
