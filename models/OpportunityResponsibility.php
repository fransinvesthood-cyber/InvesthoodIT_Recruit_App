<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Responsibility Model
 * ================================================
 * Responsibilities, duties, activities and learning
 * outcomes configured per opportunity. Stored in a
 * relational table with a type discriminator.
 */

class OpportunityResponsibility
{
    /** @var string[] Allowed responsibility types */
    public const TYPES = ['key_responsibilities', 'duties', 'programme_activities', 'learning_outcomes'];

    /**
     * Responsibilities for an opportunity, grouped by type.
     *
     * @param int $opportunityId
     * @return array  ['key_responsibilities'=>[], 'duties'=>[], ...]
     */
    public static function forOpportunity(int $opportunityId): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM opportunity_responsibilities
             WHERE opportunity_id = ?
             ORDER BY type, sort_order, id",
            'i',
            [$opportunityId]
        );
        $result = array_fill_keys(self::TYPES, []);
        foreach ($rows as $row) {
            $result[$row['type']][] = $row;
        }
        return $result;
    }

    /**
     * Replace responsibilities for an opportunity.
     *
     * @param int   $opportunityId
     * @param array $data  ['key_responsibilities'=>"line1\nline2", 'duties'=>"..."]
     * @return void
     */
    public static function replaceForOpportunity(int $opportunityId, array $data): void
    {
        Database::execute("DELETE FROM opportunity_responsibilities WHERE opportunity_id = ?", 'i', [$opportunityId]);

        foreach (self::TYPES as $type) {
            $raw = $data[$type] ?? '';
            $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
            $sort = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                Database::execute(
                    "INSERT INTO opportunity_responsibilities (opportunity_id, type, content, sort_order)
                     VALUES (?, ?, ?, ?)",
                    'issi',
                    [$opportunityId, $type, $line, $sort]
                );
                $sort++;
            }
        }
    }
}
