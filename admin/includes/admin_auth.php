<?php
/**
 * Investhood IT - Admin Authentication Helper
 * ==========================================
 * Ensures only authenticated administrators can access admin pages.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the current user is an authenticated administrator
 * @return bool
 */
function isAuthenticatedAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
        return false;
    }

    // Role ID 1 = Platform Administrator
    // Additional admin roles can be added as needed
    $adminRoles = [1, 2, 3, 4, 5, 6, 7, 8];
    return in_array($_SESSION['role_id'], $adminRoles);
}

/**
 * Require admin authentication or redirect to login
 */
function requireAdminAuth() {
    if (!isAuthenticatedAdmin()) {
        $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
            "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['SCRIPT_NAME'], 2) . "/login.php";
        header("Location: " . $loginUrl);
        exit;
    }
}

/**
 * Get the current admin user ID
 * @return int|null
 */
function getCurrentAdminId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Validate and sanitize a candidate ID from request
 * @param mixed $id
 * @return int|false
 */
function validateCandidateId($id) {
    if ($id === null || $id === '') {
        return false;
    }
    $id = filter_var($id, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    return $id !== false ? $id : false;
}

/**
 * Generate CSRF token if not exists
 * @return string
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
