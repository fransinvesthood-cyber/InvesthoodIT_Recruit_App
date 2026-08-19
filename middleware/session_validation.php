<?php
/**
 * ================================================
 * INVESTHOOD IT - Session Validation Middleware
 * ================================================
 * Validates that the current session is fresh, the
 * user still exists in the database, and the account
 * remains active. Also refreshes activity timestamps.
 */

function validate_session(): void
{
    if (!is_logged_in()) {
        return;
    }

    // 1. Refresh activity & DB session record
    $sessionHash = hash('sha256', session_id());
    UserSession::touch($sessionHash);

    // 2. Verify the user still exists and is active
    $user = User::find((int) $_SESSION['user_id']);
    if (!$user || !in_array($user['status'], [STATUS_ACTIVE], true)) {
        destroy_session();
        RememberMeToken::deleteAllForUser((int) ($_SESSION['user_id'] ?? 0));
        set_flash('error', 'Session Ended', 'Your account is no longer active. Please contact support.');
        safe_redirect('login.php');
    }
}

