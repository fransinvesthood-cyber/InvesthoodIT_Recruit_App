<?php
/**
 * ================================================
 * INVESTHOOD IT - Update User Processor
 * ================================================
 * Handles the edit user form submission.
 * Validates input, checks for duplicate email/username,
 * and updates the user account.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

// Enforce CSRF on all POST requests
enforce_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_redirect('users.php');
}

// ---- Validate user ID ----
$userId = (int) ($_POST['id'] ?? 0);
if ($userId <= 0) {
    set_flash('error', 'Invalid User', 'No user was specified.');
    safe_redirect('users.php');
}

// ---- Fetch target user ----
$targetUser = User::findWithRole($userId);
if (!$targetUser) {
    set_flash('error', 'User Not Found', 'The specified user does not exist.');
    safe_redirect('users.php');
}

// ---- Sanitise input ----
$clean = [
    'first_name'      => Sanitizer::name($_POST['first_name'] ?? ''),
    'last_name'       => Sanitizer::name($_POST['last_name'] ?? ''),
    'email'           => Sanitizer::email($_POST['email'] ?? ''),
    'username'        => Sanitizer::username($_POST['username'] ?? ''),
    'role_id'         => (int) ($_POST['role_id'] ?? 0),
    'status'          => $_POST['status'] ?? $targetUser['status'],
];

// ---- Validate ----
$validator = new Validator();
$validator->validate($clean, [
    'first_name'      => ['required', 'min:2', 'max:50'],
    'last_name'       => ['required', 'min:2', 'max:50'],
    'username'        => ['required', 'min:3', 'max:30', 'alphanumeric', 'unique:users,username,' . $userId],
    'email'           => ['required', 'email', 'max:100', 'unique:users,email,' . $userId],
]);

// Validate role
if ($clean['role_id'] <= 0) {
    $_SESSION['user_form_errors'] = ['role_id' => 'Please select a valid role.'];
    $_SESSION['user_form_old'] = $_POST;
    set_flash('error', 'Validation Error', 'Please correct the errors below.');
    safe_redirect('user_edit.php?id=' . $userId);
}

// Verify role exists
$role = Role::find($clean['role_id']);
if (!$role) {
    $_SESSION['user_form_errors'] = ['role_id' => 'The selected role does not exist.'];
    $_SESSION['user_form_old'] = $_POST;
    set_flash('error', 'Invalid Role', 'The selected role does not exist.');
    safe_redirect('user_edit.php?id=' . $userId);
}

// Validate status
$allowedStatuses = [STATUS_ACTIVE, STATUS_PENDING, STATUS_SUSPENDED, STATUS_DISABLED];
if (!in_array($clean['status'], $allowedStatuses, true)) {
    $clean['status'] = $targetUser['status'];
}

// Check if validation failed
if (!$validator->passes()) {
    $_SESSION['user_form_errors'] = $validator->errors();
    $_SESSION['user_form_old'] = $_POST;
    set_flash('error', 'Validation Error', 'Please correct the errors below.');
    safe_redirect('user_edit.php?id=' . $userId);
}

// ---- Prevent self-deactivation ----
$currentUserId = current_user_id();
if ($userId === $currentUserId && in_array($clean['status'], [STATUS_SUSPENDED, STATUS_DISABLED], true)) {
    set_flash('error', 'Action Denied', 'You cannot deactivate your own account.');
    safe_redirect('user_edit.php?id=' . $userId);
}

// ---- Update the user ----
try {
    $updateData = [
        'first_name'   => $clean['first_name'],
        'last_name'    => $clean['last_name'],
        'username'     => $clean['username'],
        'email'        => $clean['email'],
        'role_id'      => $clean['role_id'],
        'status'       => $clean['status'],
    ];

    $success = User::update($userId, $updateData);

    if ($success) {
        // If deactivating, also deactivate all sessions
        if (in_array($clean['status'], [STATUS_SUSPENDED, STATUS_DISABLED], true)) {
            UserSession::deactivateAllForUser($userId);
        }

        set_flash(
            'success',
            'User Updated',
            e($clean['first_name'] . ' ' . $clean['last_name']) . ' has been successfully updated.'
        );
        safe_redirect('users.php');
    } else {
        throw new RuntimeException('Failed to update user record.');
    }
} catch (Exception $e) {
    error_log('[Admin User Update] Error: ' . $e->getMessage());
    $_SESSION['user_form_errors'] = ['general' => 'An error occurred while updating the user. Please try again.'];
    $_SESSION['user_form_old'] = $_POST;
    set_flash('error', 'Update Failed', 'An unexpected error occurred. Please try again.');
    safe_redirect('user_edit.php?id=' . $userId);
}