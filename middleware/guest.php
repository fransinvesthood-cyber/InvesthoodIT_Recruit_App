<?php
/**
 * ================================================
 * INVESTHOOD IT - Guest-Only Middleware
 * ================================================
 * Restricts pages (login, register, forgot-password)
 * to visitors who are NOT logged in. Authenticated
 * users are redirected to their role dashboard.
 */

function require_guest(): void
{
    if (is_logged_in()) {
        $dashboard = role_dashboard(current_role() ?? '');
        safe_redirect($dashboard ?? 'login.php');
    }
}

