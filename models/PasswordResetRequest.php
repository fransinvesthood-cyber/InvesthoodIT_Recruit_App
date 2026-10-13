<?php
/**
 * ================================================
 * INVESTHOOD IT - Password Reset Request Throttle
 * ================================================
 * Rate-limits password-reset requests per email
 * address and per IP so the endpoint cannot be
 * abused for spam or user enumeration. Backed by
 * the `password_reset_requests` table (see
 * database/migrations/001_password_reset_hardening.sql).
 */

class PasswordResetRequest
{
    /**
     * Record a reset request attempt (called for every submission,
     * including unknown emails, so throttling cannot leak existence).
     */
    public static function record(string $email, string $ip): void
    {
        try {
            Database::execute(
                "INSERT INTO password_reset_requests (email, ip_address, created_at)
                 VALUES (?, ?, NOW())",
                'ss',
                [strtolower(trim($email)), $ip]
            );
        } catch (Throwable $ex) {
            // Throttle table missing (migration not run) — fail open but log.
            error_log('[PasswordResetRequest] record failed: ' . $ex->getMessage());
        }
    }

    /**
     * Count requests for an email address within the window.
     */
    public static function countRecentByEmail(string $email, int $minutes = 60): int
    {
        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM password_reset_requests
                  WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                'si',
                [strtolower(trim($email)), $minutes]
            );
            return $row ? (int) $row['cnt'] : 0;
        } catch (Throwable $ex) {
            return 0;
        }
    }

    /**
     * Count requests from an IP address within the window.
     */
    public static function countRecentByIp(string $ip, int $minutes = 60): int
    {
        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM password_reset_requests
                  WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                'si',
                [$ip, $minutes]
            );
            return $row ? (int) $row['cnt'] : 0;
        } catch (Throwable $ex) {
            return 0;
        }
    }

    /**
     * Check whether a new request is allowed under both limits.
     */
    public static function isAllowed(string $email, string $ip): bool
    {
        $perEmail = defined('PASSWORD_RESET_MAX_REQUESTS_PER_HOUR')
            ? (int) PASSWORD_RESET_MAX_REQUESTS_PER_HOUR : 5;
        $perIp = defined('PASSWORD_RESET_MAX_REQUESTS_PER_IP_HOUR')
            ? (int) PASSWORD_RESET_MAX_REQUESTS_PER_IP_HOUR : 10;
        if (self::countRecentByEmail($email) >= $perEmail) {
            return false;
        }
        if (self::countRecentByIp($ip) >= $perIp) {
            return false;
        }
        return true;
    }

    /**
     * Delete throttle rows older than 24h (housekeeping).
     */
    public static function prune(): void
    {
        try {
            Database::execute(
                "DELETE FROM password_reset_requests WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
            );
        } catch (Throwable $ex) {
            error_log('[PasswordResetRequest] prune failed: ' . $ex->getMessage());
        }
    }
}
