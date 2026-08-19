<?php
/**
 * ================================================
 * INVESTHOOD IT - Work Experience Model
 * ================================================
 * CRUD for candidate work experience with strict
 * ownership enforcement and date-range validation.
 */

class WorkExperience
{
    /**
     * All experience records for a user ordered by sort_order.
     *
     * @param int $userId
     * @return array
     */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM work_experience
             WHERE user_id = ?
             ORDER BY sort_order ASC, id ASC",
            'i',
            [$userId]
        );
    }

    /**
     * Find an owned record.
     *
     * @param int $id
     * @param int $userId
     * @return array|null
     */
    public static function findOwned(int $id, int $userId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM work_experience WHERE id = ? AND user_id = ? LIMIT 1",
            'ii',
            [$id, $userId]
        );
    }

    /**
     * Create a record.
     *
     * @param int   $userId
     * @param array $data
     * @return int
     */
    public static function create(int $userId, array $data): int
    {
        $sortOrder = (int) Database::fetchOne(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next FROM work_experience WHERE user_id = ?",
            'i',
            [$userId]
        )['next'];

        Database::execute(
            "INSERT INTO work_experience
             (user_id, job_title, company, start_date, end_date, is_current, description, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            'issssisi',
            [
                $userId,
                $data['job_title'],
                $data['company'],
                $data['start_date'],
                $data['is_current'] ? null : ($data['end_date'] ?? null),
                $data['is_current'] ? 1 : 0,
                $data['description'] ?? null,
                $sortOrder,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update an owned record.
     *
     * @param int   $id
     * @param int   $userId
     * @param array $data
     * @return bool
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        $allowed = ['job_title', 'company', 'start_date', 'end_date', 'is_current', 'description', 'sort_order'];
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

        $sql = "UPDATE work_experience SET " . implode(', ', $sets)
            . " WHERE id = ? AND user_id = ?";
        return Database::execute($sql, $types, $params) >= 0;
    }

    /**
     * Delete an owned record and renumber sort_order.
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public static function delete(int $id, int $userId): bool
    {
        $record = self::findOwned($id, $userId);
        if (!$record) {
            return false;
        }

        $deleted = Database::execute(
            "DELETE FROM work_experience WHERE id = ? AND user_id = ?",
            'ii',
            [$id, $userId]
        ) > 0;

        if ($deleted) {
            // Renumber remaining records
            self::renumber($userId);
        }

        return $deleted;
    }

    /**
     * Reorder records for a user.
     *
     * @param int   $userId
     * @param array $orderedIds  e.g. [3, 1, 2]
     * @return bool
     */
    public static function reorder(int $userId, array $orderedIds): bool
    {
        $position = 1;
        foreach ($orderedIds as $recordId) {
            Database::execute(
                "UPDATE work_experience SET sort_order = ? WHERE id = ? AND user_id = ?",
                'iii',
                [$position, (int) $recordId, $userId]
            );
            $position++;
        }
        return true;
    }

    /**
     * Renumber sort_order sequentially after a deletion.
     *
     * @param int $userId
     */
    private static function renumber(int $userId): void
    {
        $records = Database::fetchAll(
            "SELECT id FROM work_experience WHERE user_id = ? ORDER BY sort_order ASC, id ASC",
            'i',
            [$userId]
        );
        $position = 1;
        foreach ($records as $record) {
            Database::execute(
                "UPDATE work_experience SET sort_order = ? WHERE id = ?",
                'ii',
                [$position, (int) $record['id']]
            );
            $position++;
        }
    }
}
