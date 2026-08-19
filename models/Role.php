<?php
/**
 * ================================================
 * INVESTHOOD IT - Role Model
 * ================================================
 * Handles user role records.
 */

class Role
{
    /**
     * Find a role by its primary key.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM roles WHERE id = ? LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Find a role by its slug.
     *
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug(string $slug): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM roles WHERE slug = ? LIMIT 1",
            's',
            [$slug]
        );
    }

    /**
     * Get the default role (candidate) used for new registrations.
     *
     * @return array|null
     */
    public static function defaultRole(): ?array
    {
        return self::findBySlug(ROLE_CANDIDATE);
    }

    /**
     * Fetch all roles.
     *
     * @return array
     */
    public static function all(): array
    {
        return Database::fetchAll("SELECT * FROM roles ORDER BY id ASC");
    }

    /**
     * Get a role's dashboard path.
     *
     * @param string $slug
     * @return string|null
     */
    public static function dashboardFor(string $slug): ?string
    {
        return role_dashboard($slug);
    }
}

