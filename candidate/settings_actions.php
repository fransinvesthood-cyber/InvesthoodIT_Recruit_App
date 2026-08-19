<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Settings AJAX Dispatcher
 * ================================================
 * Single secure entry point for all candidate settings
 * mutations. Mirrors the pattern used by
 * candidate/profile_actions.php.
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

function json_response(array $payload): void
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || str_contains(($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'fetch');

// ---- Authentication & role ----
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

// ---- CSRF enforcement ----
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!validate_csrf()) {
        http_response_code(403);
        json_response(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    }
}

$userId = (int) current_user_id();
if ($userId <= 0) {
    http_response_code(401);
    json_response(['success' => false, 'message' => 'Authentication required.']);
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {

        // ============================================
        // ACCOUNT
        // ============================================
        case 'update_account':
            $errors = [];
            if (SettingsController::updateAccount($userId, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Your account settings have been updated.', 'emailChanged' => !empty($errors['_email_changed'])]);
            }
            json_response(['success' => false, 'message' => 'Please fix the highlighted errors.', 'errors' => $errors]);

        case 'resend_verification':
            $errors = [];
            if (SettingsController::resendVerification($userId, $errors)) {
                json_response(['success' => true, 'message' => 'A new verification link has been sent to your email address.']);
            }
            json_response(['success' => false, 'message' => $errors['general'] ?? 'Could not resend the verification email.', 'errors' => $errors]);

        // ============================================
        // SECURITY
        // ============================================
        case 'change_password':
            $errors = [];
            if (SettingsController::changePassword($userId, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Password updated successfully. Other sessions have been logged out.']);
            }
            json_response(['success' => false, 'message' => 'Please fix the highlighted errors.', 'errors' => $errors]);

        // ============================================
        // SESSIONS
        // ============================================
        case 'logout_other_sessions':
            SettingsController::logoutOtherSessions($userId);
            json_response(['success' => true, 'message' => 'Other sessions have been logged out successfully.']);

        case 'logout_everywhere':
            SettingsController::logoutEverywhere($userId);
            json_response(['success' => true, 'message' => 'All sessions have been logged out. Please sign in again on your devices.', 'relogin' => true]);

        // ============================================
        // TRUSTED DEVICES
        // ============================================
        case 'revoke_device':
            $id = (int) ($_POST['device_id'] ?? 0);
            if (SettingsController::revokeTrustedDevice($userId, $id)) {
                json_response(['success' => true, 'message' => 'Trusted device revoked successfully.']);
            }
            json_response(['success' => false, 'message' => 'Could not revoke that device.']);

        case 'revoke_all_devices':
            SettingsController::revokeAllTrustedDevices($userId);
            json_response(['success' => true, 'message' => 'All trusted devices have been revoked.']);

        // ============================================
        // NOTIFICATIONS
        // ============================================
        case 'save_notifications':
            $errors = [];
            if (SettingsController::saveNotificationPreferences($userId, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Notification preferences saved.']);
            }
            json_response(['success' => false, 'message' => 'Could not save notification preferences.', 'errors' => $errors]);

        // ============================================
        // CONSENT
        // ============================================
        case 'consent_update':
            $errors = [];
            $purpose = trim($_POST['purpose'] ?? '');
            $granted = !empty($_POST['granted']) && $_POST['granted'] === 'true';
            if (SettingsController::updateConsent($userId, $purpose, $granted, $errors)) {
                json_response([
                    'success' => true,
                    'message' => $granted ? 'Consent granted.' : 'Consent withdrawn.',
                    'state'   => SettingsController::consentStateForUser($userId),
                ]);
            }
            json_response(['success' => false, 'message' => $errors['consent'] ?? 'Could not update consent.', 'errors' => $errors]);

        // ============================================
        // PREFERENCES
        // ============================================
        case 'save_preferences':
            $errors = [];
            if (SettingsController::savePreferences($userId, $_POST, $errors)) {
                json_response(['success' => true, 'message' => 'Your preferences have been updated.']);
            }
            json_response(['success' => false, 'message' => 'Please fix the highlighted errors.', 'errors' => $errors]);

        // ============================================
        // ACCOUNT LIFECYCLE
        // ============================================
        case 'deactivate_account':
            $errors = [];
            $password = (string) ($_POST['password'] ?? '');
            if (SettingsController::deactivateAccount($userId, $password, $errors)) {
                json_response(['success' => true, 'message' => 'Your account has been temporarily deactivated.', 'redirect' => url('login.php')]);
            }
            json_response(['success' => false, 'message' => 'Please check your password and try again.', 'errors' => $errors]);

        case 'request_deletion':
            $errors = [];
            $password = (string) ($_POST['password'] ?? '');
            $reason   = trim($_POST['reason'] ?? '');
            if (SettingsController::requestDeletion($userId, $password, $reason, $errors)) {
                json_response(['success' => true, 'message' => 'Your deletion request has been submitted. Our team will contact you.']);
            }
            json_response(['success' => false, 'message' => $errors['general'] ?? 'Please check your password and try again.', 'errors' => $errors]);

        // ============================================
        // DATA EXPORT
        // ============================================
        case 'export_data':
            $data = SettingsController::exportData($userId);
            // Build a simple JSON payload; the frontend triggers a download.
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            json_response(['success' => true, 'message' => 'Your data has been prepared.', 'data' => $data, 'payload' => $json]);

        default:
            json_response(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Throwable $ex) {
    error_log('[SettingsActions] Error: ' . $ex->getMessage());
    json_response(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
