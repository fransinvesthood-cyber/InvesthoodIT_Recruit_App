<?php
/**
 * ================================================
 * INVESTHOOD IT - User Setting Model
 * ================================================
 * Key/value platform & display preferences for a
 * candidate (theme, language, timezone, date format).
 * Extended set of keys can be added later without
 * schema changes.
 */

class UserSetting
{
    /**
     * All settings for a user as a key => value map.
     *
     * @param int $userId
     * @return array
     */
    public static function allForUser(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?",
            'i',
            [$userId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['setting_key']] = $row['setting_value'];
        }
        return $map;
    }

    /**
     * Get a single setting value or a default.
     *
     * @param int    $userId
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function get(int $userId, string $key, string $default = ''): string
    {
        $row = Database::fetchOne(
            "SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ? LIMIT 1",
            'is',
            [$userId, $key]
        );
        return $row['setting_value'] ?? $default;
    }

    /**
     * Set (insert or update) a setting value.
     *
     * @param int    $userId
     * @param string $key
     * @param string $value
     * @return bool
     */
    public static function set(int $userId, string $key, string $value): bool
    {
        return Database::execute(
            "INSERT INTO user_settings (user_id, setting_key, setting_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
            'iss',
            [$userId, $key, $value]
        ) >= 0;
    }

    /**
     * Bulk set a set of settings.
     *
     * @param int   $userId
     * @param array $map  key => value
     */
    public static function bulkSet(int $userId, array $map): void
    {
        foreach ($map as $key => $value) {
            self::set($userId, $key, $value);
        }
    }
}

