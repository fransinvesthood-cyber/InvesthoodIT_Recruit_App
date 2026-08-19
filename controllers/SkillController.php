<?php
/**
 * ================================================
 * INVESTHOOD IT - Skill Controller
 * ================================================
 * Manages a candidate's skills using the canonical
 * skill catalogue. Prevents duplicate skills via a
 * unique (user, skill) constraint. Professionals are
 * selected from the `skills` table (never free-text).
 */

class SkillController
{
    /**
     * Add a skill (by canonical skill id) to a candidate.
     *
     * @param int        $userId
     * @param int        $skillId
     * @param string     $proficiency
     * @param array      $errors (by reference)
     * @return bool
     */
    public static function add(int $userId, int $skillId, string $proficiency, array &$errors): bool
    {
        // Validate skill exists and is active
        $skill = Skill::find($skillId);
        if (!$skill || (int) $skill['is_active'] !== 1) {
            $errors['skill'] = 'Please select a valid skill.';
            return false;
        }

        // Validate proficiency value
        $allowed = ['beginner', 'intermediate', 'advanced', 'expert'];
        if (!in_array($proficiency, $allowed, true)) {
            $proficiency = 'intermediate';
        }

        // addToUser returns false if the skill already exists (duplicate prevention)
        $added = Skill::addToUser($userId, $skillId, $proficiency);
        if (!$added) {
            $errors['skill'] = 'This skill is already on your profile.';
            return false;
        }

        AuditLog::log($userId, 'skill_added', 'skill', $skillId, 'Skill added: ' . $skill['name']);
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Remove a skill from a candidate.
     *
     * @param int $userId
     * @param int $skillId
     * @return bool
     */
    public static function remove(int $userId, int $skillId): bool
    {
        $skill = Skill::find($skillId);
        $removed = Skill::removeFromUser($userId, $skillId);
        if (!$removed) {
            return false;
        }

        AuditLog::log($userId, 'skill_removed', 'skill', $skillId, $skill ? 'Skill removed: ' . $skill['name'] : 'Skill removed');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }
}
