<?php
/**
 * ================================================
 * INVESTHOOD IT - Registration Processor
 * ================================================
 * Handles the registration form POST request.
 * On success, redirects to login with a success
 * message. On failure, returns to the registration
 * page with validation errors.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// CSRF protection
enforce_csrf();

// Guest only
require_guest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_redirect('register.php');
}

$result = AuthController::register($_POST);

if ($result['success']) {
    set_flash(
        'success',
        'Registration Successful!',
        'Welcome to the Investhood IT Talent Community. A verification email has been sent to ' . $result['user']['email'] . '. Please verify your email to activate your account.'
    );
    safe_redirect('login.php');
}

// Registration failed — return with errors
if (!empty($result['errors']['general'])) {
    set_flash('error', 'Registration Failed', $result['errors']['general']);
}

// Store errors & old input in session for re-display
$_SESSION['_form_errors']  = $result['errors'];
$_SESSION['_form_old']     = $_POST;
safe_redirect('register.php');

