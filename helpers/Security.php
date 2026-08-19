<?php
/**
 * ================================================
 * INVESTHOOD IT - Security Utilities
 * ================================================
 * Password policy enforcement, strength checking,
 * and brute-force protection helpers.
 */

class Security
{
    /**
     * Check if a password meets the minimum strength policy.
     * Policy: >= 8 chars, uppercase, lowercase, number, special.
     *
     * @param string $password
     * @return array [pass => bool, message => string]
     */
    public static function checkPasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        if (empty($errors)) {
            return ['pass' => true, 'message' => 'Password strength is sufficient.'];
        }

        return ['pass' => false, 'message' => implode(' ', $errors)];
    }

    /**
     * Check if the given IP has exceeded the max login attempts.
     * Implements a sliding window lockout.
     *
     * @param string $ip
     * @return bool true if locked out
     */
    public static function isIpLockedOut(string $ip): bool
    {
        $windowStart = date('Y-m-d H:i:s', time() - (LOGIN_LOCKOUT_MINUTES * 60));

        $sql = "SELECT COUNT(*) as cnt FROM login_attempts
                WHERE ip_address = ? AND attempted_at >= ? AND successful = 0";
        $row = Database::fetchOne($sql, 'ss', [$ip, $windowStart]);

        return $row && (int) $row['cnt'] >= LOGIN_MAX_ATTEMPTS;
    }

    /**
     * Record a login attempt (successful or failed).
     *
     * @param int|null $userId
     * @param string   $ip
     * @param bool     $successful
     */
    public static function recordLoginAttempt(?int $userId, string $ip, bool $successful): void
    {
        $sql = "INSERT INTO login_attempts (user_id, ip_address, successful, attempted_at)
                VALUES (?, ?, ?, NOW())";
        Database::execute($sql, 'isi', [$userId, $ip, (int) $successful]);
    }

    /**
     * Clear login attempts for a given IP after successful login.
     *
     * @param string $ip
     */
    public static function clearLoginAttempts(string $ip): void
    {
        $sql = "DELETE FROM login_attempts WHERE ip_address = ?";
        Database::execute($sql, 's', [$ip]);
    }

    /**
     * Temporarily lock an account (set status to suspended).
     *
     * @param int $userId
     */
    public static function lockAccount(int $userId): void
    {
        $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
        Database::execute($sql, 'i', [$userId]);
    }

    /**
     * Check if account is locked (suspended).
     *
     * @param int $userId
     * @return bool
     */
    public static function isAccountLocked(int $userId): bool
    {
        $row = Database::fetchOne("SELECT status FROM users WHERE id = ?", 'i', [$userId]);
        return $row && $row['status'] === 'suspended';
    }
}
