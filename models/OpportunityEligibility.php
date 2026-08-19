<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Eligibility Model
 * ================================================
 * Eligibility requirements configured per opportunity.
 * Stored relationally, not as hard-coded lists.
 */

class OpportunityEligibility
{
    /**
     * Eligibility for an opportunity (one row per opportunity).
     *
     * @param int $opportunityId
     * @return array|null
     */
    public static function forOpportunity(int $opportunityId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM opportunity_eligibility WHERE opportunity_id = ? LIMIT 1",
            'i',
            [$opportunityId]
        );
    }

    /**
     * Insert or update eligibility for an opportunity.
     *
     * @param int   $opportunityId
     * @param array $data
     * @return bool
     */
    public static function upsert(int $opportunityId, array $data): bool
    {
        $existing = self::forOpportunity($opportunityId);
        if ($existing) {
            return self::update($existing['id'], $data);
        }
        return self::create($opportunityId, $data);
    }

    /**
     * Create eligibility for an opportunity.
     *
     * @param int   $opportunityId
     * @param array $data
     * @return int  New ID
     */
    public static function create(int $opportunityId, array $data): int
    {
        Database::execute(
            "INSERT INTO opportunity_eligibility
             (opportunity_id, qualification_requirements, required_skills, preferred_skills,
              min_experience, availability_requirements, other_requirements)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            'issssss',
            [
                $opportunityId,
                $data['qualification_requirements'] ?? null,
                $data['required_skills'] ?? null,
                $data['preferred_skills'] ?? null,
                $data['min_experience'] ?? null,
                $data['availability_requirements'] ?? null,
                $data['other_requirements'] ?? null,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update eligibility for an opportunity.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = [
            'qualification_requirements', 'required_skills', 'preferred_skills',
            'min_experience', 'availability_requirements', 'other_requirements',
        ];
        $sets = [];
        $types = '';
        $params = [];

        foreach ($data as $col => $val) {
            if (in_array($col, $allowed, true)) {
                $sets[] = "`{$col}` = ?";
                $types .= 's';
                $params[] = ($val === '' || $val === null) ? null : (string) $val;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "`updated_at` = NOW()";
        $types .= 'i';
        $params[] = $id;

        return Database::execute(
            "UPDATE opportunity_eligibility SET " . implode(', ', $sets) . " WHERE id = ?",
            $types,
            $params
        ) >= 0;
    }
}
