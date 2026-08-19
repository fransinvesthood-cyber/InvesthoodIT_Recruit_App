<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Workflow Model
 * ================================================
 * Configuration foundation for future workflow modules.
 * Defines which stages are active for a cohort.
 */

class CohortWorkflow
{
    /** @var string[] Canonical workflow stages in order */
    public const STAGES = [
        'application',
        'eligibility_review',
        'screening',
        'assessment',
        'interview',
        'selection',
        'onboarding',
        'active_participant',
    ];

    /**
     * Workflow config for a cohort (all stages).
     *
     * @param int $cohortId
     * @return array
     */
    public static function forCohort(int $cohortId): array
    {
        return Database::fetchAll(
            "SELECT * FROM cohort_workflow WHERE cohort_id = ? ORDER BY sort_order",
            'i',
            [$cohortId]
        );
    }

    /**
     * Initialise default workflow stages for a new cohort.
     *
     * @param int $cohortId
     * @return void
     */
    public static function initForCohort(int $cohortId): void
    {
        foreach (self::STAGES as $i => $stage) {
            Database::execute(
                "INSERT IGNORE INTO cohort_workflow (cohort_id, stage, is_active, sort_order)
                 VALUES (?, ?, 1, ?)",
                'isi',
                [$cohortId, $stage, $i + 1]
            );
        }
    }

    /**
     * Set whether a stage is active.
     *
     * @param int    $cohortId
     * @param string $stage
     * @param bool   $active
     * @return bool
     */
    public static function setStageActive(int $cohortId, string $stage, bool $active): bool
    {
        if (!in_array($stage, self::STAGES, true)) {
            return false;
        }
        return Database::execute(
            "INSERT INTO cohort_workflow (cohort_id, stage, is_active, sort_order)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)",
            'isii',
            [$cohortId, $stage, (int) $active, array_search($stage, self::STAGES, true) + 1]
        ) >= 0;
    }

    /**
     * Replace the full workflow configuration for a cohort.
     *
     * @param int   $cohortId
     * @param array $activeStages  List of stage slugs that are active
     * @return void
     */
    public static function replaceForCohort(int $cohortId, array $activeStages): void
    {
        foreach (self::STAGES as $i => $stage) {
            $active = in_array($stage, $activeStages, true);
            Database::execute(
                "INSERT INTO cohort_workflow (cohort_id, stage, is_active, sort_order)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), sort_order = VALUES(sort_order)",
                'isii',
                [$cohortId, $stage, (int) $active, $i + 1]
            );
        }
    }
}
