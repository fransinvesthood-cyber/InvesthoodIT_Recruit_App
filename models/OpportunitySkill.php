<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Skill Model
 * ================================================
 * Required/preferred technical and soft skills per opportunity.
 */

class OpportunitySkill
{
    /** @var string[] Allowed skill categories */
    public const CATEGORIES = ['required_technical', 'preferred_technical', 'required_soft'];

    /**
     * Skills for an opportunity.
     *
     * @param int $opportunityId
     * @return array
     */
    public static function forOpportunity(int $opportunityId): array
    {
        return Database::fetchAll(
            "SELECT * FROM opportunity_skills
             WHERE opportunity_id = ?
             ORDER BY skill_category, skill_name",
            'i',
            [$opportunityId]
        );
    }

    /**
     * Add a skill.
     *
     * @param int    $opportunityId
     * @param string $skillName
     * @param string $category
     * @return int  New ID
     */
    public static function add(int $opportunityId, string $skillName, string $category): int
    {
        Database::execute(
            "INSERT IGNORE INTO opportunity_skills (opportunity_id, skill_name, skill_category)
             VALUES (?, ?, ?)",
            'iss',
            [$opportunityId, $skillName, $category]
        );
        return Database::lastInsertId();
    }

    /**
     * Replace all skills for an opportunity (idempotent sync).
     *
     * @param int   $opportunityId
     * @param array $skills  [['name'=>..., 'category'=>...], ...]
     * @return void
     */
    public static function replaceForOpportunity(int $opportunityId, array $skills): void
    {
        Database::execute("DELETE FROM opportunity_skills WHERE opportunity_id = ?", 'i', [$opportunityId]);
        foreach ($skills as $skill) {
            $name = trim((string) ($skill['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $category = $skill['category'] ?? 'required_technical';
            if (!in_array($category, self::CATEGORIES, true)) {
                $category = 'required_technical';
            }
            self::add($opportunityId, $name, $category);
        }
    }
}
