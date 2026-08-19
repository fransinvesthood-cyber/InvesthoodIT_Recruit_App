<?php
/**
 * ================================================
 * INVESTHOOD IT - Availability Status Model
 * ================================================
 * Retrieves canonical availability statuses stored
 * in the database (not hard-coded in the UI).
 */

class AvailabilityStatus
{
    /**
     * All statuses ordered by sort_order.
     *
     * @return array
     */
    public static function all(): array
    {
        return Database::fetchAll(
            "SELECT * FROM availability_statuses
             WHERE is_active = 1
             ORDER BY sort_order ASC"
        );
    }

    /**
     * Find a status by slug.
     *
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug(string $slug): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM availability_statuses WHERE slug = ? LIMIT 1",
            's',
            [$slug]
        );
    }

    /**
     * Find a status by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM availability_statuses WHERE id = ? LIMIT 1",
            'i',
            [$id]
        );
    }
}
