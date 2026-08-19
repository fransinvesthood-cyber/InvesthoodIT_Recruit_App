<?php
/**
 * ================================================
 * INVESTHOOD IT - Notification Preference Model
 * ================================================
 * Stores per-category notification preferences for
 * a candidate. A separate email_enabled flag governs
 * email delivery independently of in-app delivery.
 */

class NotificationPreference
{
    /**
     * All preferences for a user, keyed by category.
     *
     * @param int $userId
     * @return array  category => ['enabled' => bool, 'email_enabled' => bool]
     */
    public static function allForUser(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT category, enabled, email_enabled
             FROM notification_preferences WHERE user_id = ?",
            'i',
            [$userId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['category']] = [
                'enabled'       => (bool) $row['enabled'],
                'email_enabled' => (bool) $row['email_enabled'],
            ];
        }
        return $map;
    }

    /**
     * Upsert a preference for a category.
     *
     * @param int    $userId
     * @param string $category
     * @param bool   $enabled
     * @param bool   $emailEnabled
     * @return bool
     */
    public static function upsert(int $userId, string $category, bool $enabled, bool $emailEnabled): bool
    {
        return Database::execute(
            "INSERT INTO notification_preferences (user_id, category, enabled, email_enabled)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                email_enabled = VALUES(email_enabled),
                updated_at = NOW()",
            'isii',
            [$userId, $category, (int) $enabled, (int) $emailEnabled]
        ) >= 0;
    }

    /**
     * Bulk upsert preferences.
     *
     * @param int   $userId
     * @param array $prefs  category => ['enabled'=>bool, 'email_enabled'=>bool]
     */
    public static function bulkUpsert(int $userId, array $prefs): void
    {
        foreach ($prefs as $category => $vals) {
            self::upsert(
                $userId,
                $category,
                (bool) ($vals['enabled'] ?? true),
                (bool) ($vals['email_enabled'] ?? true)
            );
        }
    }
}
