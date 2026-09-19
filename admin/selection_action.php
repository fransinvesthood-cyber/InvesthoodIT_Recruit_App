<?php
/**
 * ================================================
 * INVESTHOOD IT - Selection Decision Handler (Stage 11)
 * ================================================
 * POST endpoint for recording a selection decision
 * (Selected / Waitlisted / Not Selected). Validates the admin
 * role, CSRF token and server-side eligibility, then delegates
 * to the transactional Selection::decide() method.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
enforce_csrf();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid Request', 'Method not allowed.');
    safe_redirect('admin/selection.php');
}

// Protect — only Administrator
require_role('admin');

$adminId       = (int) current_user_id();
$applicationId = (int) ($_POST['application_id'] ?? 0);
$decision      = (string) ($_POST['decision'] ?? '');
$note          = trim((string) ($_POST['note'] ?? ''));
$reason        = trim((string) ($_POST['reason'] ?? ''));

if ($applicationId <= 0) {
    set_flash('error', 'Invalid Application', 'No application ID was provided.');
    safe_redirect('admin/selection.php');
}

if (!in_array($decision, Selection::DECISIONS, true)) {
    set_flash('error', 'Invalid Decision', 'Please choose Selected, Waitlisted or Not Selected.');
    safe_redirect('admin/selection_decision.php?id=' . $applicationId);
}

$decisionLabel = Selection::decisionLabel($decision);

try {
    $result = Selection::decide($applicationId, $decision, $adminId, $note, $reason);

    set_flash(
        'success',
        'Selection Decision Recorded',
        $decisionLabel . ' recorded — application status updated from "'
        . Application::label($result['previous_status']) . '" to "'
        . Application::label($result['new_status']) . '". The change is recorded on the application timeline.'
    );
} catch (RuntimeException $e) {
    set_flash('error', 'Decision Not Saved', $e->getMessage());
} catch (Exception $e) {
    error_log('[Admin Selection Action] Error: ' . $e->getMessage());
    set_flash('error', 'Error', 'An error occurred while saving the selection decision. Please try again.');
}

safe_redirect('admin/selection_decision.php?id=' . $applicationId);