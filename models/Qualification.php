<?php
/**
 * ================================================
 * INVESTHOOD IT - Qualification Model
 * ================================================
 * CRUD for candidate qualifications with strict
 * ownership enforcement.
 */

class Qualification
{
    /**
     * All qualifications for a user (most recent first).
     *
     * @param int $userId
     * @return array
     */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM qualifications
             WHERE user_id = ?
             ORDER BY year_completed DESC, id DESC",
            'i',
            [$userId]
        );
    }

    /**
     * Find one qualification, scoped to the owning user.
     *
     * @param int $id
     * @param int $userId
     * @return array|null
     */
    public static function findOwned(int $id, int $userId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM qualifications WHERE id = ? AND user_id = ? LIMIT 1",
            'ii',
            [$id, $userId]
        );
    }

    /**
     * Create a qualification.
     *
     * @param int   $userId
     * @param array $data
     * @return int  New ID
     */
    public static function create(int $userId, array $data): int
    {
        Database::execute(
            "INSERT INTO qualifications
             (user_id, name, institution, year_completed, level, verification_status)
             VALUES (?, ?, ?, ?, ?, ?)",
            'isssss',
            [
                $userId,
                $data['name'],
                $data['institution'] ?? null,
                $data['year_completed'] ?? null,
                $data['level'] ?? null,
                $data['verification_status'] ?? VERIFY_UNVERIFIED,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update an owned qualification.
     *
     * @param int   $id
     * @param int   $userId
     * @param array $data
     * @return bool
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        $allowed = ['name', 'institution', 'year_completed', 'level', 'verification_status'];
        $sets = [];
        $types = '';
        $params = [];

        foreach ($data as $col => $val) {
            if (in_array($col, $allowed, true)) {
                $sets[] = "`{$col}` = ?";
                $types .= 's';
                $params[] = $val;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "`updated_at` = NOW()";
        $types .= 'ii';
        $params[] = $id;
        $params[] = $userId;

        $sql = "UPDATE qualifications SET " . implode(', ', $sets)
            . " WHERE id = ? AND user_id = ?";
        return Database::execute($sql, $types, $params) >= 0;
    }

    /**
     * Delete an owned qualification.
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public static function delete(int $id, int $userId): bool
    {
        return Database::execute(
            "DELETE FROM qualifications WHERE id = ? AND user_id = ?",
            'ii',
            [$id, $userId]
        ) > 0;
    }
}
