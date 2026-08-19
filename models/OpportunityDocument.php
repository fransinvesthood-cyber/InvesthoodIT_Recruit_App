<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Document Model
 * ================================================
 * Required/optional document configuration per opportunity.
 */

class OpportunityDocument
{
    /**
     * Documents for an opportunity.
     *
     * @param int $opportunityId
     * @return array
     */
    public static function forOpportunity(int $opportunityId): array
    {
        return Database::fetchAll(
            "SELECT * FROM opportunity_documents WHERE opportunity_id = ? ORDER BY id",
            'i',
            [$opportunityId]
        );
    }

    /**
     * Add a document requirement.
     *
     * @param int    $opportunityId
     * @param array  $data  ['document_name','is_required']
     * @return int  New ID
     */
    public static function add(int $opportunityId, array $data): int
    {
        Database::execute(
            "INSERT INTO opportunity_documents (opportunity_id, document_name, is_required)
             VALUES (?, ?, ?)",
            'isi',
            [
                $opportunityId,
                $data['document_name'],
                (int) ($data['is_required'] ?? 1),
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Replace all documents for an opportunity (idempotent sync).
     *
     * @param int   $opportunityId
     * @param array $documents  [['document_name'=>..., 'is_required'=>int], ...]
     * @return void
     */
    public static function replaceForOpportunity(int $opportunityId, array $documents): void
    {
        Database::execute("DELETE FROM opportunity_documents WHERE opportunity_id = ?", 'i', [$opportunityId]);
        foreach ($documents as $doc) {
            $name = trim((string) ($doc['document_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            self::add($opportunityId, [
                'document_name' => $name,
                'is_required'   => (int) ($doc['is_required'] ?? 1),
            ]);
        }
    }
}
