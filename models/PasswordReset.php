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
     *
     * @param int    $userId
     * @param string $hashedToken
     * @return int
     */
    public static function create(int $userId, string $hashedToken): int
    {
        Database::execute(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW())",
            'isi',
            [$userId, $hashedToken, PASSWORD_RESET_EXPIRY_HOURS]
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

