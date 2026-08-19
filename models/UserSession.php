<?php
/**
 * ================================================
 * INVESTHOOD IT - User Session Model
 * ================================================
 * Persists session metadata in the database for
 * tracking active sessions, enabling remote
 * termination and audit.
 */

class UserSession
{
    /**
     * Create a session record.
     *
     * @param int    $userId
     * @param string $sessionId  Hashed session ID
     * @param string $ip
     * @param string $userAgent
     * @return int
     */
    public static function create(int $userId, string $sessionId, string $ip, string $userAgent): int
    {
        Database::execute(
            "INSERT INTO user_sessions (user_id, session_hash, ip_address, user_agent, login_time, last_activity, is_active)
             VALUES (?, ?, ?, ?, NOW(), NOW(), 1)",
            'isss',
            [$userId, $sessionId, $ip, $userAgent]
        );
        return Database::lastInsertId();
    }

    /**
     * Update last activity time for a session.
     *
     * @param string $sessionHash
     */
    public static function touch(string $sessionHash): void
    {
        Database::execute(
            "UPDATE user_sessions
             SET last_activity = NOW()
             WHERE session_hash = ? AND is_active = 1",
            's',
            [$sessionHash]
        );
    }

    /**
     * Mark a session as inactive (logout).
     *
     * @param string $sessionHash
     */
    public static function deactivate(string $sessionHash): void
    {
        Database::execute(
            "UPDATE user_sessions
             SET is_active = 0, logout_time = NOW()
             WHERE session_hash = ?",
            's',
            [$sessionHash]
        );
    }

/**
     * Deactivate all sessions for a user.
     *
     * @param int $userId
     */
    public static function deactivateAllForUser(int $userId): void
    {
        Database::execute(
            "UPDATE user_sessions
             SET is_active = 0, logout_time = NOW()
             WHERE user_id = ? AND is_active = 1",
            'i',
            [$userId]
        );
    }

    /**
     * All session records for a user (active + recent history),
     * newest first. Returns session_hash so the caller may identify
     * the current session; callers must not expose it in the UI.
     *
     * @param int $userId
     * @return array
     */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT id, session_hash, ip_address, user_agent, login_time,
                    last_activity, logout_time, is_active
             FROM user_sessions
             WHERE user_id = ?
             ORDER BY is_active DESC, last_activity DESC
             LIMIT 50",
            'i',
            [$userId]
        );
    }

    /**
     * Deactivate a single session belonging to the user.
     *
     * @param int $sessionId
     * @param int $userId
     * @return bool
     */
    public static function deactivateById(int $sessionId, int $userId): bool
    {
        return Database::execute(
            "UPDATE user_sessions
             SET is_active = 0, logout_time = NOW()
             WHERE id = ? AND user_id = ? AND is_active = 1",
            'ii',
            [$sessionId, $userId]
        ) > 0;
    }

    /**
     * Deactivate all sessions for a user except the supplied one.
     * Used by "Log Out Other Sessions".
     *
     * @param int    $userId
     * @param string $exceptSessionHash
     * @return int affected rows
     */
    public static function deactivateAllExcept(int $userId, string $exceptSessionHash): int
    {
        return Database::execute(
            "UPDATE user_sessions
             SET is_active = 0, logout_time = NOW()
             WHERE user_id = ? AND is_active = 1 AND session_hash <> ?",
            'is',
            [$userId, $exceptSessionHash]
        );
    }

    /**
     * Count active sessions for a user.
     *
     * @param int $userId
     * @return int
     */
    public static function countActiveForUser(int $userId): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM user_sessions
             WHERE user_id = ? AND is_active = 1",
            'i',
            [$userId]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Clean up stale sessions.
     */
    public static function purgeStale(): void
    {
        Database::execute(
            "DELETE FROM user_sessions
             WHERE is_active = 1 AND last_activity < NOW() - INTERVAL " . SESSION_TIMEOUT_MINUTES . " MINUTE"
        );
    }
}

