<?php
/**
 * ================================================
 * INVESTHOOD IT - CSRF Protection
 * ================================================
 * Generates and validates per-session CSRF tokens
 * to protect all state-changing POST requests.
 */

/**
 * Ensure a CSRF token exists for the session and return it.
 *
 * @return string
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Output a hidden CSRF input field.
 */
function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate a submitted CSRF token (POST field or header).
 *
 * @return bool
 */
function validate_csrf(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent)
        && !empty($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $sent);
}

/**
 * Require a valid CSRF token; abort with 403 otherwise.
 */
function require_csrf(): void
{
    if (!validate_csrf()) {
        http_response_code(403);
        exit('Invalid security token. Please go back, refresh the page, and try again.');
    }
}

