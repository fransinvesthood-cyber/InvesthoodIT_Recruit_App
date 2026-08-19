<?php
/**
 * ================================================
 * INVESTHOOD IT - Secure Session Management
 * ================================================
 * Configures hardened PHP session settings and
 * provides helpers for login state, role checks
 * and session regeneration (fixation prevention).
 */

/**
 * Start the application session with hardened settings.
 * Call this once at bootstrap.
 */
function start_session(): void
{
    // Avoid duplicate starts
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // ---- Secure cookie parameters ----
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => defined('COOKIE_SECURE') ? COOKIE_SECURE : false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('INVESTHOODSESSID');

    // Prevent session fixation
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_start();

    // Enforce idle timeout
    enforce_session_timeout();
}

/**
 * Enforce idle session timeout.
 * If the session has been idle beyond SESSION_TIMEOUT_MINUTES,
 * destroy it and redirect to login with a notification.
 */
function enforce_session_timeout(): void
{
    if (!isset($_SESSION['_last_activity'])) {
        $_SESSION['_last_activity'] = time();
        return;
    }

    $timeoutSeconds = SESSION_TIMEOUT_MINUTES * 60;
    if (time() - $_SESSION['_last_activity'] > $timeoutSeconds) {
        // Expire the session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();

        // Notify and redirect
        set_flash('error', 'Session Expired', 'Your session has timed out. Please sign in again.');
        safe_redirect('login.php');
    }

    $_SESSION['_last_activity'] = time();
}

/**
 * Regenerate the session ID (session fixation prevention).
 * Optionally delete old session data.
 */
function regenerate_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
}

/**
 * Store the logged-in user data in the session.
 *
 * @param array $user  Associative user row
 * @param array $role  Role row (id, slug, name)
 */
function set_logged_in(array $user, array $role): void
{
    $_SESSION['user_id']        = (int) $user['id'];
    $_SESSION['username']       = $user['username'];
    $_SESSION['fullname']       = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['email']          = $user['email'];
    $_SESSION['role']           = $role['slug'];
    $_SESSION['role_name']      = $role['name'];
    $_SESSION['profile_picture']= $user['profile_picture'] ?? '';
    $_SESSION['last_login']     = $user['last_login'] ?? '';
    $_SESSION['logged_in']      = true;
}

/**
 * Check whether a user is logged in.
 *
 * @return bool
 */
function is_logged_in(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get the current session user's role slug.
 *
 * @return string|null
 */
function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Get the current session user's ID.
 *
 * @return int|null
 */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get all session user data as array.
 *
 * @return array
 */
function current_user(): array
{
    return [
        'user_id'         => $_SESSION['user_id'] ?? null,
        'username'        => $_SESSION['username'] ?? null,
        'fullname'        => $_SESSION['fullname'] ?? null,
        'email'           => $_SESSION['email'] ?? null,
        'role'            => $_SESSION['role'] ?? null,
        'role_name'       => $_SESSION['role_name'] ?? null,
        'profile_picture' => $_SESSION['profile_picture'] ?? null,
        'last_login'      => $_SESSION['last_login'] ?? null,
    ];
}

/**
 * Destroy the session completely.
 */
function destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

