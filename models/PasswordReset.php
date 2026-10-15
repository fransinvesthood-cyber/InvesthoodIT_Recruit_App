<?php
/**
 * ================================================
 * INVESTHOOD IT - Password Reset Model
 * ================================================
 * Manages expiring password reset tokens.
 * Tokens are stored hashed (SHA-256).
 */

class PasswordReset
{
    /**
     * Create a new password reset token.
     * Expiry is PASSWORD_RESET_EXPIRY_MINUTES (spec: 30 minutes).
     *
     * @param int    $userId
     * @param string $hashedToken  SHA-256 hash — never store raw tokens
     * @return int
     */
    public static function create(int $userId, string $hashedToken): int
    {
        $minutes = defined('PASSWORD_RESET_EXPIRY_MINUTES')
            ? (int) PASSWORD_RESET_EXPIRY_MINUTES
            : ((int) PASSWORD_RESET_EXPIRY_HOURS * 60);
        $minutes = $minutes > 0 ? $minutes : 30;
        Database::execute(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())",
            'isi',
            [$userId, $hashedToken, $minutes]
        );
        return Database::lastInsertId();
    }

    /**
     * Find a valid, unused, unexpired reset token.
     *
     * @param string $hashedToken
     * @return array|null
     */
    public static function findValid(string $hashedToken): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM password_resets
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1",
            's',
            [$hashedToken]
        );
    }

    /**
     * Mark a reset token as used.
     *
     * @param int $id
     */
    public static function markUsed(int $id): void
    {
        Database::execute(
            "UPDATE password_resets SET used_at = NOW() WHERE id = ?",
            'i',
            [$id]
        );
    }

    /**
     * Atomically claim a reset token for use.
     * The UPDATE only succeeds when the token is still unused and
     * unexpired, so two simultaneous requests cannot both consume
     * the same token (prevents reuse races).
     *
     * @param string $hashedToken  SHA-256 of the presented token
     * @return array|null  The claimed row, or null when invalid/expired/used
     */
    public static function claimValid(string $hashedToken): ?array
    {
        if ($hashedToken === '') {
            return null;
        }
        // Database::execute() returns $stmt->affected_rows — the only
        // reliable signal that exactly one unused/unexpired row was claimed.
        $affected = Database::execute(
            "UPDATE password_resets
              SET used_at = NOW()
              WHERE token_hash = ?
                AND used_at IS NULL
                AND expires_at > NOW()",
            's',
            [$hashedToken]
        );
        if ($affected < 1) {
            return null;
        }
        return Database::fetchOne(
            "SELECT * FROM password_resets WHERE token_hash = ? LIMIT 1",
            's',
            [$hashedToken]
        );
    }

    /**
     * Count reset tokens created for a user within the last hour.
     * Used for per-account rate limiting.
     */
    public static function countRecentForUser(int $userId, int $minutes = 60): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM password_resets
              WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            'ii',
            [$userId, $minutes]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Invalidate all reset tokens for a user.
     *
     * @param int $userId
     */
    public static function invalidateForUser(int $userId): void
    {
        Database::execute(
            "UPDATE password_resets
             SET used_at = NOW()
             WHERE user_id = ? AND used_at IS NULL",
            'i',
            [$userId]
        );
    }
}

