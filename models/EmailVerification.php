<?php
/**
 * ================================================
 * INVESTHOOD IT - Email Verification Model
 * ================================================
 * Manages one-time email verification tokens.
 * Tokens are stored hashed (SHA-256) with expiry.
 */

class EmailVerification
{
    /**
     * Create a new verification token record.
     *
     * @param int    $userId
     * @param string $hashedToken
     * @param int    $expiryHours
     * @return int
     */
    public static function create(int $userId, string $hashedToken, int $expiryHours = EMAIL_VERIFY_EXPIRY_HOURS): int
    {
        Database::execute(
            "INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW())",
            'isi',
            [$userId, $hashedToken, $expiryHours]
        );
        return Database::lastInsertId();
    }

    /**
     * Find an active (unused, unexpired) verification by token hash.
     *
     * @param string $hashedToken
     * @return array|null
     */
    public static function findValid(string $hashedToken): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM email_verifications
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1",
            's',
            [$hashedToken]
        );
    }

    /**
     * Mark a verification token as used.
     *
     * @param int $id
     */
    public static function markUsed(int $id): void
    {
        Database::execute(
            "UPDATE email_verifications SET used_at = NOW() WHERE id = ?",
            'i',
            [$id]
        );
    }

    /**
     * Invalidate all outstanding verification tokens for a user.
     *
     * @param int $userId
     */
    public static function invalidateForUser(int $userId): void
    {
        Database::execute(
            "UPDATE email_verifications
             SET used_at = NOW()
             WHERE user_id = ? AND used_at IS NULL",
            'i',
            [$userId]
        );
    }

    /**
     * Check if a user has a valid, unused verification token.
     *
     * @param int $userId
     * @return bool
     */
    public static function hasPending(int $userId): bool
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM email_verifications
             WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()",
            'i',
            [$userId]
        );
        return $row && (int) $row['cnt'] > 0;
    }

    /**
     * Get the user's pending verification token (for resend).
     *
     * @param int $userId
     * @return array|null
     */
    public static function pendingForUser(int $userId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM email_verifications
             WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1",
            'i',
            [$userId]
        );
    }
}

