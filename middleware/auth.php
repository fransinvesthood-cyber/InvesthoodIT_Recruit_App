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
        set_flash('warning', 'Authentication Required', 'Please sign in to access that page.');
        safe_redirect('login.php');
    }
}

