<?php
/**
 * ================================================
 * INVESTHOOD IT - Document Model
 * ================================================
 * Stores secure metadata for uploaded documents. The
 * physical file lives outside the web root; only an
 * authorised reference is stored here.
 */

class Document
{
    /**
     * All documents for a user.
     *
     * @param int $userId
     * @return array
     */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM documents
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC",
            'i',
            [$userId]
        );
    }

    /**
     * Find an owned document record.
     *
     * @param int $id
     * @param int $userId
     * @return array|null
     */
    public static function findOwned(int $id, int $userId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM documents WHERE id = ? AND user_id = ? LIMIT 1",
            'ii',
            [$id, $userId]
        );
    }

    /**
     * Create a document metadata record.
     *
     * @param int   $userId
     * @param array $data
     * @return int
     */
public static function create(int $userId, array $data): int
    {
        Database::execute(
            "INSERT INTO documents
             (user_id, document_type, original_filename, stored_filename,
              mime_type, file_size, file_checksum, expiry_date, verification_status,
              uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'issssisssi',
            [
                $userId,
                $data['document_type'],
                $data['original_filename'],
                $data['stored_filename'],
                $data['mime_type'],
                $data['file_size'],
                $data['file_checksum'],
                $data['expiry_date'] ?? null,
                $data['verification_status'] ?? VERIFY_UNVERIFIED,
                $data['uploaded_by'] ?? (int) $userId,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update an owned document record.
     *
     * @param int   $id
     * @param int   $userId
     * @param array $data
     * @return bool
     */
    public static function update(int $id, int $userId, array $data): bool
    {
$allowed = [
            'document_type', 'original_filename', 'stored_filename',
            'mime_type', 'file_size', 'file_checksum', 'expiry_date',
            'verification_status', 'uploaded_by',
        ];
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

        $sql = "UPDATE documents SET " . implode(', ', $sets)
            . " WHERE id = ? AND user_id = ?";
        return Database::execute($sql, $types, $params) >= 0;
    }

/**
     * Delete a document record (ownership scoped).
     * The associated physical file is removed by the caller.
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public static function delete(int $id, int $userId): bool
    {
        return Database::execute(
            "DELETE FROM documents WHERE id = ? AND user_id = ?",
            'ii',
            [$id, $userId]
        ) > 0;
    }

    /**
     * Whether a user has an active CV uploaded.
     *
     * @param int $userId
     * @return bool
     */
    public static function hasCv(int $userId): bool
    {
        $row = Database::fetchOne(
            "SELECT id FROM documents
             WHERE user_id = ? AND document_type = 'cv'
             LIMIT 1",
            'i',
            [$userId]
        );
        return (bool) $row;
    }
}
