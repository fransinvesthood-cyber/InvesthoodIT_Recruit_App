<?php
/**
 * ================================================
 * INVESTHOOD IT - Audit Log Model
 * ================================================
 * Records material profile changes. Stores only
 * safe metadata (no sensitive personal data).
 */

class AuditLog
{
    /**
     * Record an audit entry.
     *
     * @param int         $userId
     * @param string      $action
     * @param string|null $recordType
     * @param int|null    $recordId
     * @param string|null $reason
     */
    public static function log(
        int $userId,
        string $action,
        ?string $recordType = null,
        ?int $recordId = null,
        ?string $reason = null
    ): void {
        Database::execute(
            "INSERT INTO audit_logs (user_id, action, record_type, record_id, reason, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            'ississ',
            [
                $userId,
                $action,
                $recordType,
                $recordId,
                $reason,
                client_ip(),
            ]
        );
    }

    /**
     * Fetch recent audit entries for a user.
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function recentForUser(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT * FROM audit_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            'ii',
            [$userId, $limit]
        );
    }
}
