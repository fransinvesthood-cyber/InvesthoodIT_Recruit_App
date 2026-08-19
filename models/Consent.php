<?php
/**
 * ================================================
 * INVESTHOOD IT - Consent Model
 * ================================================
 * Tracks candidate consent for each processing
 * purpose. Supports granting, withdrawal with
 * timestamp, and preserving lawful records.
 */

class Consent
{
    /**
     * All consent records for a user.
     *
     * @param int $userId
     * @return array
     */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM consents
             WHERE user_id = ?
             ORDER BY purpose ASC",
            'i',
            [$userId]
        );
    }

    /**
     * Find the current consent record for a user + purpose.
     *
     * @param int    $userId
     * @param string $purpose
     * @return array|null
     */
    public static function findForUser(int $userId, string $purpose): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM consents
             WHERE user_id = ? AND purpose = ?
             ORDER BY id DESC
             LIMIT 1",
            'is',
            [$userId, $purpose]
        );
    }

/**
     * Grant (or update) consent for a purpose.
     *
     * @param int    $userId
     * @param string $purpose
     * @return bool
     */
    public static function grant(int $userId, string $purpose): bool
    {
        $existing = self::findForUser($userId, $purpose);
        if ($existing && $existing['status'] === 'granted') {
            return true;
        }

        // Update the existing record to granted, or insert a new one.
        return Database::execute(
            "INSERT INTO consents (user_id, purpose, status, granted_at, withdrawn_at)
             VALUES (?, ?, 'granted', NOW(), NULL)
             ON DUPLICATE KEY UPDATE
                status = 'granted',
                granted_at = NOW(),
                withdrawn_at = NULL,
                updated_at = NOW()",
            'is',
            [$userId, $purpose]
        ) > 0;
    }

    /**
     * Withdraw consent for a purpose. Records the withdrawal
     * timestamp and purpose. The lawful record is preserved.
     *
     * @param int    $userId
     * @param string $purpose
     * @return bool
     */
    public static function withdraw(int $userId, string $purpose): bool
    {
        $existing = self::findForUser($userId, $purpose);
        if ($existing && $existing['status'] === 'withdrawn') {
            return true;
        }

        return Database::execute(
            "INSERT INTO consents (user_id, purpose, status, granted_at, withdrawn_at)
             VALUES (?, ?, 'withdrawn', NULL, NOW())
             ON DUPLICATE KEY UPDATE
                status = 'withdrawn',
                withdrawn_at = NOW(),
                updated_at = NOW()",
            'is',
            [$userId, $purpose]
        ) > 0;
    }

    /**
     * Whether a candidate has granted a given purpose.
     * Used by talent-pool search to exclude withdrawals.
     *
     * @param int    $userId
     * @param string $purpose
     * @return bool
     */
    public static function hasGranted(int $userId, string $purpose): bool
    {
        $existing = self::findForUser($userId, $purpose);
        return $existing && $existing['status'] === 'granted';
    }
}
