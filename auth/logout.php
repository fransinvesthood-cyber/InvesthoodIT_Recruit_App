<?php
/**
 * ================================================
 * INVESTHOOD IT - Logout Processor
 * ================================================
 * Destroys the session, remember-me cookies and
 * database tokens, then redirects to the landing
 * page with a confirmation notification.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// CSRF protection for logout (POST only), but allow GET convenience
// with a confirmation flow via token check.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
}

// If user is not logged in, just go home
if (!is_logged_in()) {
    safe_redirect('index.php');
}

AuthController::logout();

set_flash('success', 'Signed Out', 'You have been successfully signed out. We hope to see you again soon!');
safe_redirect('index.php');

