<?php
/**
 * ================================================
 * INVESTHOOD IT - Interview Status Update Handler
 * ================================================
 * Handles interview status changes and cancellation.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
enforce_csrf();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid Request', 'Method not allowed.');
    safe_redirect('admin/interviews.php');
}

// Protect — only Administrator
require_role('admin');

$action = $_POST['action'] ?? '';
$interviewId = (int) ($_POST['interview_id'] ?? 0);
$adminId = (int) current_user_id();

if ($interviewId <= 0) {
    set_flash('error', 'Invalid Interview', 'No interview ID was provided.');
    safe_redirect('admin/interviews.php');
}

$interview = Interview::find($interviewId);
if (!$interview) {
    set_flash('error', 'Interview Not Found', 'The requested interview does not exist.');
    safe_redirect('admin/interviews.php');
}

try {
    switch ($action) {
        case 'cancel':
            $reason = trim($_POST['reason'] ?? '');
            if (empty($reason)) {
                set_flash('error', 'Reason Required', 'Please provide a cancellation reason.');
                safe_redirect('admin/interview.php?id=' . $interviewId);
            }
            Interview::cancel($interviewId, $adminId, $reason);
            set_flash('success', 'Interview Cancelled', 'The interview has been cancelled successfully.');
            break;

        case 'completed':
            Interview::changeStatus($interviewId, 'completed', $adminId, 'Marked as completed by administrator.');
            set_flash('success', 'Interview Completed', 'The interview has been marked as completed.');
            break;

        case 'no_show':
            Interview::changeStatus($interviewId, 'no_show', $adminId, 'Marked as no-show by administrator.');
            set_flash('success', 'No-Show Recorded', 'The candidate has been marked as no-show.');
            break;

        case 'scheduled':
            Interview::changeStatus($interviewId, 'scheduled', $adminId, 'Interview re-scheduled.');
            set_flash('success', 'Interview Re-scheduled', 'The interview has been re-scheduled.');
            break;

        case 'confirmed':
            Interview::changeStatus($interviewId, 'confirmed', $adminId, 'Interview confirmed by administrator.');
            set_flash('success', 'Interview Confirmed', 'The interview has been confirmed.');
            break;

        default:
            set_flash('error', 'Invalid Action', 'The requested action is not supported.');
            break;
    }
} catch (RuntimeException $e) {
    set_flash('error', 'Action Failed', $e->getMessage());
} catch (Exception $e) {
    error_log('[Admin Interview Status] Error: ' . $e->getMessage());
    set_flash('error', 'Error', 'An error occurred while updating the interview. Please try again.');
}

safe_redirect('admin/interview.php?id=' . $interviewId);
