<?php
/**
 * ================================================
 * INVESTHOOD IT - User Actions Processor
 * ================================================
 * Handles all user management POST actions:
 * - activate / deactivate
 * - delete
 * All actions require admin role and CSRF token.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

// Enforce CSRF on all POST requests
enforce_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_redirect('users.php');
}

$action = $_POST['action'] ?? '';
$userId = (int) ($_POST['id'] ?? 0);

// Redirect target (preserve filters)
$redirectUrl = url('admin/users.php') . (!empty($_GET) ? '?' . http_build_query($_GET) : '');

// ---- Validate action ----
$allowedActions = ['activate', 'deactivate', 'delete'];
if (!in_array($action, $allowedActions, true)) {
    set_flash('error', 'Invalid Action', 'The requested action is not recognised.');
    safe_redirect($redirectUrl);
}

// ---- Validate user ID ----
if ($userId <= 0) {
    set_flash('error', 'Invalid User', 'No user was specified for this action.');
    safe_redirect($redirectUrl);
}

// ---- Fetch target user ----
$targetUser = User::findWithRole($userId);
if (!$targetUser) {
    set_flash('error', 'User Not Found', 'The specified user does not exist.');
    safe_redirect($redirectUrl);
}

// ---- Prevent self-deactivation or self-deletion ----
$currentUserId = current_user_id();
if ($userId === $currentUserId && in_array($action, ['deactivate', 'delete'], true)) {
    set_flash('error', 'Action Denied', 'You cannot deactivate or delete your own account.');
    safe_redirect($redirectUrl);
}

// ---- Process action ----
switch ($action) {
    case 'activate':
        handleActivate($targetUser, $redirectUrl);
        break;

    case 'deactivate':
        handleDeactivate($targetUser, $redirectUrl);
        break;

    case 'delete':
        handleDelete($targetUser, $redirectUrl);
        break;
}

// If we get here, something went wrong
set_flash('error', 'Action Failed', 'An unexpected error occurred.');
safe_redirect($redirectUrl);

// ================================================================
// ACTION HANDLERS
// ================================================================

/**
 * Activate a user account.
 */
function handleActivate(array $targetUser, string $redirectUrl): void
{
    if ($targetUser['status'] === STATUS_ACTIVE) {
        set_flash('info', 'Already Active', 'This user account is already active.');
        safe_redirect($redirectUrl);
    }

    $success = User::updateStatus($targetUser['id'], STATUS_ACTIVE);

    if ($success) {
        set_flash('success', 'User Activated', e($targetUser['first_name'] . ' ' . $targetUser['last_name']) . ' has been activated and can now log in.');
    } else {
        set_flash('error', 'Activation Failed', 'Could not activate the user. Please try again.');
    }

    safe_redirect($redirectUrl);
}

/**
 * Deactivate a user account (set to suspended).
 */
function handleDeactivate(array $targetUser, string $redirectUrl): void
{
    if ($targetUser['status'] !== STATUS_ACTIVE) {
        set_flash('info', 'Not Active', 'This user account is not currently active.');
        safe_redirect($redirectUrl);
    }

    $success = User::updateStatus($targetUser['id'], STATUS_SUSPENDED);

    if ($success) {
        set_flash('success', 'User Deactivated', e($targetUser['first_name'] . ' ' . $targetUser['last_name']) . ' has been deactivated and can no longer log in.');
    } else {
        set_flash('error', 'Deactivation Failed', 'Could not deactivate the user. Please try again.');
    }

    safe_redirect($redirectUrl);
}

/**
 * Delete a user account permanently.
 */
function handleDelete(array $targetUser, string $redirectUrl): void
{
    // Check if user can be safely deleted
    $canDelete = User::canDelete($targetUser['id']);

    if (!$canDelete['can_delete']) {
        // Cannot delete — has related records
        $reasons = implode(', ', $canDelete['reasons']);
        set_flash(
            'error',
            'Cannot Delete User',
            e($targetUser['first_name'] . ' ' . $targetUser['last_name']) . ' has related records (' . e($reasons) . '). Please deactivate this account instead to preserve data integrity.'
        );
        safe_redirect($redirectUrl);
    }

    $userName = $targetUser['first_name'] . ' ' . $targetUser['last_name'];
    $success = User::delete($targetUser['id']);

    if ($success) {
        set_flash('success', 'User Deleted', e($userName) . ' has been permanently deleted from the platform.');
    } else {
        set_flash('error', 'Deletion Failed', 'Could not delete the user. Please try again.');
    }

    safe_redirect($redirectUrl);
}