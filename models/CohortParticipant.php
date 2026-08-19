<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Participant Model
 * ================================================
 * Tracks participants per cohort to support capacity
 * tracking and future application/selection/onboarding
 * module integration.
 */

class CohortParticipant
{
    /**
     * Participants for a cohort.
     *
     * @param int $cohortId
     * @return array
     */
    public static function forCohort(int $cohortId): array
    {
        return Database::fetchAll(
            "SELECT cp.*, u.first_name, u.last_name, u.email
             FROM cohort_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.cohort_id = ?
             ORDER BY cp.created_at DESC",
            'i',
            [$cohortId]
        );
    }

    /**
     * Add a participant to a cohort.
     *
     * @param int    $cohortId
     * @param int    $userId
     * @param string $status
     * @return int  New ID
     */
    public static function add(int $cohortId, int $userId, string $status = 'selected'): int
    {
        $now = date('Y-m-d H:i:s');
        Database::execute(
            "INSERT INTO cohort_participants (cohort_id, user_id, status, selected_at, onboarded_at, completed_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), selected_at = COALESCE(selected_at, VALUES(selected_at))",
            'iissss',
            [$cohortId, $userId, $status, $status === 'selected' ? $now : null, null, null]
        );
        return Database::lastInsertId();
    }

    /**
     * Update a participant's status.
     *
     * @param int    $cohortId
     * @param int    $userId
     * @param string $status
     * @return bool
     */
    public static function setStatus(int $cohortId, int $userId, string $status): bool
    {
        $allowed = ['selected', 'onboarded', 'active', 'completed', 'withdrawn'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $sets = ['status = ?'];
        $params = [$status];
        $types = 's';

        if ($status === 'onboarded') {
            $sets[] = 'onboarded_at = COALESCE(onboarded_at, ?)';
            $params[] = $now;
            $types .= 's';
        } elseif ($status === 'completed') {
            $sets[] = 'completed_at = COALESCE(completed_at, ?)';
            $params[] = $now;
            $types .= 's';
        }

        $types .= 'ii';
        $params[] = $cohortId;
        $params[] = $userId;

        return Database::execute(
            "UPDATE cohort_participants SET " . implode(', ', $sets) . " WHERE cohort_id = ? AND user_id = ?",
            $types,
            $params
        ) >= 0;
    }

    /**
     * Remove a participant from a cohort.
     *
     * @param int $cohortId
     * @param int $userId
     * @return bool
     */
    public static function remove(int $cohortId, int $userId): bool
    {
        return Database::execute(
            "DELETE FROM cohort_participants WHERE cohort_id = ? AND user_id = ?",
            'ii',
            [$cohortId, $userId]
        ) > 0;
    }
}
