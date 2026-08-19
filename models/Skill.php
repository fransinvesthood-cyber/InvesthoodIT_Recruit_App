<?php
/**
 * ================================================
 * INVESTHOOD IT - Skill Model
 * ================================================
 * Canonical skills catalogue + candidate skill pivot.
 * Design allows future categorisation, versioning,
 * verification, and talent-pool search.
 */

class Skill
{
    /**
     * Search the canonical skills catalogue.
     *
     * @param string $query
     * @param string $category  'technical' | 'soft' | 'all'
     * @param int    $limit
     * @return array
     */
    public static function search(string $query, string $category = 'all', int $limit = 30): array
    {
        $sql = "SELECT * FROM skills WHERE is_active = 1";
        $types = '';
        $params = [];

        if ($category !== 'all' && in_array($category, [SKILL_TECHNICAL, SKILL_SOFT], true)) {
            $sql .= " AND category = ?";
            $types .= 's';
            $params[] = $category;
        }

        if ($query !== '') {
            $sql .= " AND name LIKE ?";
            $types .= 's';
            $params[] = '%' . $query . '%';
        }

        $sql .= " ORDER BY category ASC, name ASC LIMIT ?";
        $types .= 'i';
        $params[] = $limit;

        return Database::fetchAll($sql, $types, $params);
    }

    /**
     * All skills grouped by category.
     *
     * @return array ['technical' => [], 'soft' => []]
     */
    public static function allByCategory(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM skills WHERE is_active = 1 ORDER BY category ASC, name ASC"
        );
        $result = ['technical' => [], 'soft' => []];
        foreach ($rows as $row) {
            $result[$row['category']][] = $row;
        }
        return $result;
    }

    /**
     * Find a skill by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM skills WHERE id = ? LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Skills a candidate has selected, with category.
     *
     * @param int $userId
     * @return array
     */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT cs.id AS cs_id, cs.skill_id, cs.proficiency, cs.verification_status,
                    s.name, s.category
             FROM candidate_skills cs
             INNER JOIN skills s ON s.id = cs.skill_id
             WHERE cs.user_id = ?
             ORDER BY s.category ASC, s.name ASC",
            'i',
            [$userId]
        );
    }

    /**
     * Add a skill to a candidate (prevents duplicates).
     *
     * @param int    $userId
     * @param int    $skillId
     * @param string $proficiency
     * @return bool
     */
    public static function addToUser(int $userId, int $skillId, string $proficiency = 'intermediate'): bool
    {
        // Ensure skill exists
        if (!self::find($skillId)) {
            return false;
        }

        // Prevent duplicate
        $exists = Database::fetchOne(
            "SELECT id FROM candidate_skills WHERE user_id = ? AND skill_id = ? LIMIT 1",
            'ii',
            [$userId, $skillId]
        );
        if ($exists) {
            return false;
        }

        return Database::execute(
            "INSERT INTO candidate_skills (user_id, skill_id, proficiency)
             VALUES (?, ?, ?)",
            'iis',
            [$userId, $skillId, $proficiency]
        ) > 0;
    }

    /**
     * Remove a skill from a candidate (ownership scoped).
     *
     * @param int $userId
     * @param int $skillId
     * @return bool
     */
    public static function removeFromUser(int $userId, int $skillId): bool
    {
        return Database::execute(
            "DELETE FROM candidate_skills WHERE user_id = ? AND skill_id = ?",
            'ii',
            [$userId, $skillId]
        ) > 0;
    }

    /**
     * Count verified skills for a candidate.
     *
     * @param int $userId
     * @return int
     */
    public static function countVerifiedForUser(int $userId): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM candidate_skills
             WHERE user_id = ? AND verification_status = 'verified'",
            'i',
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
