<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Document Model
 * ================================================
 * Metadata for documents attached to a candidate's
 * application. The physical file lives outside the
 * web root in uploads_private/documents/; only the
 * secure reference and display metadata are stored.
 *
 * Ownership is always verified through the application
 * → candidate relationship, never from the document ID alone.
 */

class ApplicationDocument
{
    /**
     * All documents attached to an application.
     *
     * @param int $applicationId
     * @return array
     */
    public static function forApplication(int $applicationId): array
    {
        return Database::fetchAll(
            "SELECT * FROM application_documents
             WHERE application_id = ?
             ORDER BY document_type ASC, id ASC",
            'i',
            [$applicationId]
        );
    }

    /**
     * Find a document for an application (ownership scoped).
     *
     * @param int $applicationId
     * @param string $documentType
     * @return array|null
     */
    public static function findForApplication(int $applicationId, string $documentType): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM application_documents
             WHERE application_id = ? AND document_type = ?
             LIMIT 1",
            'is',
            [$applicationId, $documentType]
        );
    }

    /**
     * Find a document by ID that belongs to an application.
     * Ownership is enforced in the query itself.
     *
     * @param int $applicationDocumentId
     * @param int $applicationId
     * @return array|null
     */
    public static function findOwned(int $applicationDocumentId, int $applicationId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM application_documents
             WHERE id = ? AND application_id = ?
             LIMIT 1",
            'ii',
            [$applicationDocumentId, $applicationId]
        );
    }

    /**
     * Create an application document metadata record.
     *
     * @param int   $applicationId
     * @param array $data  ['document_type', 'original_filename', 'stored_filename',
     *                      'mime_type', 'file_size', 'file_checksum']
     * @return int  New ID
     */
    public static function create(int $applicationId, array $data): int
    {
        Database::execute(
            "INSERT INTO application_documents
             (application_id, document_type, original_filename, stored_filename,
              mime_type, file_size, file_checksum)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            'issssis',
            [
                $applicationId,
                $data['document_type'],
                $data['original_filename'],
                $data['stored_filename'],
                $data['mime_type'],
                $data['file_size'],
                $data['file_checksum'],
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update an application document record (ownership scoped).
     *
     * @param int   $applicationDocumentId
     * @param int   $applicationId
     * @param array $data
     * @return bool
     */
    public static function update(int $applicationDocumentId, int $applicationId, array $data): bool
    {
        $allowed = [
            'original_filename', 'stored_filename',
            'mime_type', 'file_size', 'file_checksum',
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
        $params[] = $applicationDocumentId;
        $params[] = $applicationId;

        $sql = "UPDATE application_documents SET " . implode(', ', $sets)
            . " WHERE id = ? AND application_id = ?";
        return Database::execute($sql, $types, $params) >= 0;
    }

    /**
     * Delete an application document record (ownership scoped).
     * The caller removes the physical file.
     *
     * @param int $applicationDocumentId
     * @param int $applicationId
     * @return bool
     */
    public static function delete(int $applicationDocumentId, int $applicationId): bool
    {
        return Database::execute(
            "DELETE FROM application_documents WHERE id = ? AND application_id = ?",
            'ii',
            [$applicationDocumentId, $applicationId]
        ) > 0;
    }

    /**
     * Check whether a required document has been uploaded.
     *
     * @param int    $applicationId
     * @param string $documentType
     * @return bool
     */
    public static function hasDocument(int $applicationId, string $documentType): bool
    {
        $row = Database::fetchOne(
            "SELECT id FROM application_documents
             WHERE application_id = ? AND document_type = ? LIMIT 1",
            'is',
            [$applicationId, $documentType]
        );
        return (bool) $row;
    }
}