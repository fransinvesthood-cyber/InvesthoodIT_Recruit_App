<?php
/**
 * ================================================
 * INVESTHOOD IT - Login Attempt Model
 * ================================================
 * Records login attempts (success/failure) to
 * support brute-force protection and lockouts.
 */

class LoginAttempt
{
    /**
     * Record a login attempt.
     *
     * @param int|null $userId
     * @param string   $ip
     * @param string   $username    Submitted login identifier
     * @param bool     $successful
     */
    public static function record(?int $userId, string $ip, string $username, bool $successful): void
    {
        Database::execute(
            "INSERT INTO login_attempts (user_id, ip_address, username, successful, attempted_at)
             VALUES (?, ?, ?, ?, NOW())",
            'issi',
            [$userId, $ip, $username, (int) $successful]
        );
    }

    /**
     * Count failed attempts for an IP within the lockout window.
     *
     * @param string $ip
     * @return int
     */
    public static function countFailedForIp(string $ip): int
    {
        $mins = LOGIN_LOCKOUT_MINUTES;
        if (class_exists('SystemSettings', false)) {
            try {
                $mins = SystemSettings::getInt('login_lockout_minutes', LOGIN_LOCKOUT_MINUTES, 1, 120);
            } catch (Throwable $ex) {
            }
        }
        $windowStart = date('Y-m-d H:i:s', time() - ($mins * 60));
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE ip_address = ? AND successful = 0 AND attempted_at >= ?",
            'ss',
            [$ip, $windowStart]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Count failed attempts for a user account within the lockout window.
     *
     * @param int $userId
     * @return int
     */
    public static function countFailedForUser(int $userId): int
    {
        $mins = LOGIN_LOCKOUT_MINUTES;
        if (class_exists('SystemSettings', false)) {
            try {
                $mins = SystemSettings::getInt('login_lockout_minutes', LOGIN_LOCKOUT_MINUTES, 1, 120);
            } catch (Throwable $ex) {
            }
        }
        $windowStart = date('Y-m-d H:i:s', time() - ($mins * 60));
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE user_id = ? AND successful = 0 AND attempted_at >= ?",
            'is',
            [$userId, $windowStart]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Check whether an IP is currently locked out.
     *
     * @param string $ip
     * @return bool
     */
    public static function isIpLocked(string $ip): bool
    {
        $max = LOGIN_MAX_ATTEMPTS;
        if (class_exists('SystemSettings', false)) {
            try {
                $max = SystemSettings::getInt('login_max_attempts', LOGIN_MAX_ATTEMPTS, 3, 20);
            } catch (Throwable $ex) {
            }
        }
        return self::countFailedForIp($ip) >= $max;
    }

    /**
     * Clear all failed attempts for an IP after successful login.
     *
     * @param string $ip
     */
    public static function clearForIp(string $ip): void
    {
        Database::execute(
            "DELETE FROM login_attempts WHERE ip_address = ?",
            's',
            [$ip]
        );
    }

    /**
     * Clear all failed attempts for a user after successful login.
     *
     * @param int $userId
     */
    public static function clearForUser(int $userId): void
    {
        Database::execute(
            "DELETE FROM login_attempts WHERE user_id = ?",
            'i',
            [$userId]
        );
    }
}

