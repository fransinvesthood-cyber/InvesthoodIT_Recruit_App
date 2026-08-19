<?php
/**
 * ================================================
 * INVESTHOOD IT - Certification Model
 * ================================================
 * CRUD for candidate certifications with strict
 * ownership enforcement.
 */

class Certification
{
    /**
     * All certifications for a user (most recent first).
     *
     * @param int $userId
     * @return array
     */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM certifications
             WHERE user_id = ?
             ORDER BY year_obtained DESC, id DESC",
            'i',
            [$userId]
        );
    }

    /**
     * Find one certification, scoped to the owning user.
     *
     * @param int $id
     * @param int $userId
     * @return array|null
     */
    public static function findOwned(int $id, int $userId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM certifications WHERE id = ? AND user_id = ? LIMIT 1",
            'ii',
            [$id, $userId]
        );
    }

    /**
     * Create a certification.
     *
     * @param int   $userId
     * @param array $data
     * @return int  New ID
     */
    public static function create(int $userId, array $data): int
    {
        Database::execute(
            "INSERT INTO certifications
             (user_id, name, issuing_organisation, year_obtained, expiry_date, credential_id, verification_status)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            'issssss',
            [
                $userId,
                $data['name'],
                $data['issuing_organisation'] ?? null,
                $data['year_obtained'] ?? null,
                $data['expiry_date'] ?? null,
                $data['credential_id'] ?? null,
                $data['verification_status'] ?? VERIFY_UNVERIFIED,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update an owned certification.
     *
     * @param int   $id
     * @param int   $userId
     * @param array $data
     * @return bool
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        $allowed = ['name', 'issuing_organisation', 'year_obtained', 'expiry_date', 'credential_id', 'verification_status'];
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

        $sql = "UPDATE certifications SET " . implode(', ', $sets)
            . " WHERE id = ? AND user_id = ?";
        return Database::execute($sql, $types, $params) >= 0;
    }

    /**
     * Delete an owned certification.
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public static function delete(int $id, int $userId): bool
    {
        return Database::execute(
            "DELETE FROM certifications WHERE id = ? AND user_id = ?",
            'ii',
            [$id, $userId]
        ) > 0;
    }
}
