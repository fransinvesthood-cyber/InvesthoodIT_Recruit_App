<?php
/**
 * ================================================
 * INVESTHOOD IT - SavedOpportunity Model
 * ================================================
 * CRUD operations for candidate saved opportunities.
 * Allows candidates to bookmark and track opportunities.
 */

class SavedOpportunity
{
    /**
     * Get all saved opportunities for a candidate.
     *
     * @param int $candidateId
     * @return array
     */
    public static function forCandidate(int $candidateId): array
    {
        return Database::fetchAll(
            "SELECT so.id, so.created_at,
                    o.id AS opportunity_id, o.title, o.type, o.organisation,
                    o.short_description, o.application_open_date, o.application_close_date,
                    o.start_date, o.end_date, o.available_positions, o.province, o.city,
                    o.physical_location, o.work_arrangement, o.status,
                    p.name AS programme_name, c.name AS cohort_name
             FROM candidate_saved_opportunities so
             JOIN opportunities o ON o.id = so.opportunity_id
             JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             WHERE so.candidate_id = ?
             ORDER BY so.created_at DESC",
            'i',
            [$candidateId]
        );
    }

    /**
     * Check if a candidate has saved a specific opportunity.
     *
     * @param int $candidateId
     * @param int $opportunityId
     * @return bool
     */
    public static function isSaved(int $candidateId, int $opportunityId): bool
    {
        $row = Database::fetchOne(
            "SELECT id FROM candidate_saved_opportunities
             WHERE candidate_id = ? AND opportunity_id = ?
             LIMIT 1",
            'ii',
            [$candidateId, $opportunityId]
        );
        return $row !== null;
    }

    /**
     * Get a saved opportunity record by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT so.*, o.title, o.programme_id, o.cohort_id
             FROM candidate_saved_opportunities so
             JOIN opportunities o ON o.id = so.opportunity_id
             WHERE so.id = ?
             LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Save an opportunity for a candidate.
     * Returns the ID of the saved record or null if duplicate.
     *
     * @param int $candidateId
     * @param int $opportunityId
     * @return int|null
     */
    public static function save(int $candidateId, int $opportunityId): ?int
    {
        // Check if already saved
        if (self::isSaved($candidateId, $opportunityId)) {
            return null;
        }

        Database::execute(
            "INSERT INTO candidate_saved_opportunities (candidate_id, opportunity_id)
             VALUES (?, ?)",
            'ii',
            [$candidateId, $opportunityId]
        );

        return Database::lastInsertId();
    }

    /**
     * Unsave (remove) an opportunity for a candidate.
     *
     * @param int $candidateId
     * @param int $opportunityId
     * @return bool
     */
    public static function unsave(int $candidateId, int $opportunityId): bool
    {
        Database::execute(
            "DELETE FROM candidate_saved_opportunities
             WHERE candidate_id = ? AND opportunity_id = ?",
            'ii',
            [$candidateId, $opportunityId]
        );
        return Database::affectedRows() > 0;
    }

    /**
     * Delete a saved record by ID (for audit purposes).
     *
     * @param int $id
     * @return bool
     */
    public static function deleteById(int $id): bool
    {
        Database::execute(
            "DELETE FROM candidate_saved_opportunities WHERE id = ?",
            'i',
            [$id]
        );
        return Database::affectedRows() > 0;
    }

    /**
     * Count saved opportunities for a candidate.
     *
     * @param int $candidateId
     * @return int
     */
    public static function countForCandidate(int $candidateId): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM candidate_saved_opportunities
             WHERE candidate_id = ?",
            'i',
            [$candidateId]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
