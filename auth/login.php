<?php
/**
 * ================================================
 * INVESTHOOD IT - Login Processor
 * ================================================
 * Handles the login form POST request. Validates
 * credentials, applies brute-force protections,
 * creates the secure session and redirects to the
 * role-appropriate dashboard.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// CSRF protection
enforce_csrf();

// Guest only
require_guest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_redirect('login.php');
}

$result = AuthController::login($_POST);

if ($result['success']) {
    set_flash('success', 'Welcome Back!', 'You have successfully signed in. Redirecting to your dashboard...');
    safe_redirect($result['redirect']);
}

// Login failed
foreach ($result['errors'] as $error) {
    // Render error as flash (the 'unverified' message contains a link)
    set_flash('error', 'Sign In Failed', $error);
}

$_SESSION['_form_old'] = $_POST;
safe_redirect('login.php');

