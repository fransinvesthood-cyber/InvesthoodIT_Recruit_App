<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Skill Model
 * ================================================
 * Required/preferred technical and soft skills at
 * programme level. Cohorts may inherit these.
 */

class ProgrammeSkill
{
    /**
     * Skills for a programme.
     *
     * @param int $programmeId
     * @return array
     */
    public static function forProgramme(int $programmeId): array
    {
        return Database::fetchAll(
            "SELECT * FROM programme_skills WHERE programme_id = ? ORDER BY skill_category, skill_name",
            'i',
            [$programmeId]
        );
    }

    /**
     * Add a skill.
     *
     * @param int    $programmeId
     * @param string $skillName
     * @param string $category
     * @return int  New ID
     */
    public static function add(int $programmeId, string $skillName, string $category): int
    {
        Database::execute(
            "INSERT IGNORE INTO programme_skills (programme_id, skill_name, skill_category) VALUES (?, ?, ?)",
            'iss',
            [$programmeId, $skillName, $category]
        );
        return Database::lastInsertId();
    }

    /**
     * Replace all skills for a programme (idempotent sync).
     *
     * @param int   $programmeId
     * @param array $skills  [['name'=>..., 'category'=>...], ...]
     * @return void
     */
    public static function replaceForProgramme(int $programmeId, array $skills): void
    {
        Database::execute("DELETE FROM programme_skills WHERE programme_id = ?", 'i', [$programmeId]);
        foreach ($skills as $skill) {
            $name = trim((string) ($skill['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $category = $skill['category'] ?? 'required_technical';
            if (!in_array($category, ['required_technical', 'preferred_technical', 'required_soft'], true)) {
                $category = 'required_technical';
            }
            self::add($programmeId, $name, $category);
        }
    }
}
