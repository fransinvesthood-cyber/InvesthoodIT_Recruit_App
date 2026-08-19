<?php
/**
 * ================================================
 * INVESTHOOD IT - Role Validation Middleware
 * ================================================
 * Restricts pages to one or more allowed roles.
 * Unauthorised users are redirected to their own
 * dashboard (or login if not authenticated).
 *
 * Usage: require_role('admin');
 *        require_role(['admin', 'programme_manager']);
 */

function require_role($allowedRoles): void
{
    require_login();

    $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $role = current_role();

    if (!in_array($role, $allowed, true)) {
        set_flash('error', 'Access Denied', 'You do not have permission to access that page.');
        $dashboard = role_dashboard($role ?? '');
        safe_redirect($dashboard ?? 'login.php');
    }
}

