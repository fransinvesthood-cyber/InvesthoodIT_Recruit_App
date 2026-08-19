<?php
/**
 * ================================================
 * INVESTHOOD IT - Email Verification Processor
 * ================================================
 * Verifies a user's email address using the token
 * from the verification link. On success, redirects
 * to a confirmation page. Prevents token reuse.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$token = $_GET['token'] ?? '';

$result = AuthController::verifyEmail($token);

if ($result['success']) {
    set_flash('success', 'Email Verified!', $result['message']);
} else {
    set_flash('error', 'Verification Failed', $result['message']);
}

safe_redirect('email-verified.php');

