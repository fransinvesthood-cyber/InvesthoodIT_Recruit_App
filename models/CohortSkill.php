<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Skill Model
 * ================================================
 * Required/preferred technical and soft skills per cohort.
 */

class CohortSkill
{
    /**
     * Skills for a cohort.
     *
     * @param int $cohortId
     * @return array
     */
    public static function forCohort(int $cohortId): array
    {
        return Database::fetchAll(
            "SELECT * FROM cohort_skills WHERE cohort_id = ? ORDER BY skill_category, skill_name",
            'i',
            [$cohortId]
        );
    }

    /**
     * Add a skill.
     *
     * @param int    $cohortId
     * @param string $skillName
     * @param string $category
     * @return int  New ID
     */
public static function add(int $cohortId, string $skillName, string $category): int
    {
        Database::execute(
            "INSERT IGNORE INTO cohort_skills (cohort_id, skill_name, skill_category) VALUES (?, ?, ?)",
            'iss',
            [$cohortId, $skillName, $category]
        );
        return Database::lastInsertId();
    }

    /**
     * Delete a skill.
     *
     * @param int $id
     * @param int $cohortId
     * @return bool
     */
    public static function delete(int $id, int $cohortId): bool
    {
        return Database::execute(
            "DELETE FROM cohort_skills WHERE id = ? AND cohort_id = ?",
            'ii',
            [$id, $cohortId]
        ) > 0;
    }

    /**
     * Replace all skills for a cohort (idempotent sync).
     *
     * @param int   $cohortId
     * @param array $skills  [['name'=>..., 'category'=>...], ...]
     * @return void
     */
    public static function replaceForCohort(int $cohortId, array $skills): void
    {
        Database::execute("DELETE FROM cohort_skills WHERE cohort_id = ?", 'i', [$cohortId]);
        foreach ($skills as $skill) {
            $name = trim((string) ($skill['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $category = $skill['category'] ?? 'required_technical';
            if (!in_array($category, ['required_technical', 'preferred_technical', 'required_soft'], true)) {
                $category = 'required_technical';
            }
            self::add($cohortId, $name, $category);
        }
    }
}
