<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Document Model
 * ================================================
 * Required evidence / document configuration per cohort.
 */

class CohortDocument
{
    /**
     * Documents for a cohort.
     *
     * @param int $cohortId
     * @return array
     */
    public static function forCohort(int $cohortId): array
    {
        return Database::fetchAll(
            "SELECT * FROM cohort_documents WHERE cohort_id = ? ORDER BY id",
            'i',
            [$cohortId]
        );
    }

    /**
     * Add a document requirement.
     *
     * @param int    $cohortId
     * @param array  $data  ['document_name','is_required','verification_required','expiry_required']
     * @return int  New ID
     */
    public static function add(int $cohortId, array $data): int
    {
        Database::execute(
            "INSERT INTO cohort_documents
             (cohort_id, document_name, is_required, verification_required, expiry_required)
             VALUES (?, ?, ?, ?, ?)",
            'isiii',
            [
                $cohortId,
                $data['document_name'],
                (int) ($data['is_required'] ?? 1),
                (int) ($data['verification_required'] ?? 0),
                (int) ($data['expiry_required'] ?? 0),
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update a document requirement.
     *
     * @param int   $id
     * @param int   $cohortId
     * @param array $data
     * @return bool
     */
    public static function update(int $id, int $cohortId, array $data): bool
    {
        $allowed = ['document_name', 'is_required', 'verification_required', 'expiry_required'];
        $sets = [];
        $types = '';
        $params = [];

foreach ($data as $col => $val) {
            if (in_array($col, $allowed, true)) {
                $sets[] = "`{$col}` = ?";
                if ($col === 'document_name') {
                    $types .= 's';
                    $params[] = $val;
                } else {
                    $types .= 'i';
                    $params[] = (int) $val;
                }
            }
        }

        if (empty($sets)) {
            return false;
        }

        $types .= 'ii';
        $params[] = $id;
        $params[] = $cohortId;

        return Database::execute(
            "UPDATE cohort_documents SET " . implode(', ', $sets) . " WHERE id = ? AND cohort_id = ?",
            $types,
            $params
        ) >= 0;
    }

    /**
     * Delete a document requirement.
     *
     * @param int $id
     * @param int $cohortId
     * @return bool
     */
    public static function delete(int $id, int $cohortId): bool
    {
        return Database::execute(
            "DELETE FROM cohort_documents WHERE id = ? AND cohort_id = ?",
            'ii',
            [$id, $cohortId]
        ) > 0;
    }
}
