<?php
/**
 * ================================================
 * INVESTHOOD IT - Remember Me Token Model
 * ================================================
 * Manages persistent login tokens using the
 * selector/validator split-token pattern.
 * Only the validator hash is stored in the DB.
 */

class RememberMeToken
{
    /**
     * Store a new remember-me token.
     *
     * @param int    $userId
     * @param string $selector
     * @param string $hashedValidator
     * @return int
     */
    public static function create(int $userId, string $selector, string $hashedValidator): int
    {
        Database::execute(
            "INSERT INTO remember_me_tokens (user_id, selector, validator_hash, expires_at, created_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())",
            'issi',
            [$userId, $selector, $hashedValidator, REMEMBER_ME_DAYS]
        );
        return Database::lastInsertId();
    }

    /**
     * Find a valid token by selector (validator checked later).
     *
     * @param string $selector
     * @return array|null
     */
    public static function findBySelector(string $selector): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM remember_me_tokens
             WHERE selector = ? AND expires_at > NOW()
             LIMIT 1",
            's',
            [$selector]
        );
    }

    /**
     * Delete a token record.
     *
     * @param int $id
     */
    public static function delete(int $id): void
    {
        Database::execute(
            "DELETE FROM remember_me_tokens WHERE id = ?",
            'i',
            [$id]
        );
    }

    /**
     * Delete all tokens for a user (used on logout / password change).
     *
     * @param int $userId
     */
    public static function deleteAllForUser(int $userId): void
    {
        Database::execute(
            "DELETE FROM remember_me_tokens WHERE user_id = ?",
            'i',
            [$userId]
        );
    }

    /**
     * Delete a token by selector (cookie-based logout).
     *
     * @param string $selector
     */
    public static function deleteBySelector(string $selector): void
    {
        Database::execute(
            "DELETE FROM remember_me_tokens WHERE selector = ?",
            's',
            [$selector]
        );
    }

    /**
     * List active remembered devices for a user (metadata only;
     * never exposes any token/validator material).
     *
     * @param int $userId
     * @return array
     */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT id, created_at, expires_at
             FROM remember_me_tokens
             WHERE user_id = ? AND expires_at > NOW()
             ORDER BY created_at DESC
             LIMIT 30",
            'i',
            [$userId]
        );
    }

    /**
     * Delete a single remembered device (scoped to the user).
     *
     * @param int $tokenId
     * @param int $userId
     * @return bool
     */
    public static function deleteById(int $tokenId, int $userId): bool
    {
        return Database::execute(
            "DELETE FROM remember_me_tokens WHERE id = ? AND user_id = ?",
            'ii',
            [$tokenId, $userId]
        ) > 0;
    }

    /**
     * Delete every remembered device for a user,
     * including the one tied to the current cookie.
     *
     * @param int $userId
     */
    public static function revokeAllForUser(int $userId): void
    {
        self::deleteAllForUser($userId);
    }

    /**
     * Purge all expired tokens.
     */
    public static function purgeExpired(): void
    {
        Database::execute(
            "DELETE FROM remember_me_tokens WHERE expires_at <= NOW()"
        );
    }
}

