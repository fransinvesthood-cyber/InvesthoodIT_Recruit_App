<?php
/**
 * ================================================
 * INVESTHOOD IT - Authentication Middleware
 * ================================================
 * Protects pages that require a logged-in user.
 * If not authenticated, redirect to login page.
 */

function require_login(): void
{
    if (!is_logged_in()) {
        // Store the intended destination so we can return after login
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($requestUri !== '') {
            $_SESSION['_intended_url'] = $requestUri;
        }

        set_flash('warning', 'Authentication Required', 'Please sign in to access that page.');
        safe_redirect('login.php');
    }
}

