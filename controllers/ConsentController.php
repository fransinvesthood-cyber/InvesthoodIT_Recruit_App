<?php
/**
 * ================================================
 * INVESTHOOD IT - Consent Controller
 * ================================================
 * Manages candidate consent for each processing
 * purpose. Grants, withdraws (with timestamp), and
 * records the purpose for audit. Preserves lawful
 * programme/audit records on withdrawal.
 */

class ConsentController
{
    /**
     * Apply a consent change for a purpose.
     *
     * @param int    $userId
     * @param string $purpose
     * @param bool   $granted
     * @param array  $errors (by reference)
     * @return bool
     */
    public static function update(int $userId, string $purpose, bool $granted, array &$errors): bool
    {
        // Validate purpose against the canonical consent purposes
        if (!in_array($purpose, ALLOWED_CONSENT_PURPOSES, true)) {
            $errors['consent'] = 'Invalid consent purpose.';
            return false;
        }

        if ($granted) {
            Consent::grant($userId, $purpose);
            AuditLog::log($userId, 'consent_granted', 'consent', null, 'Consent granted for: ' . $purpose);
        } else {
            Consent::withdraw($userId, $purpose);
            AuditLog::log($userId, 'consent_withdrawn', 'consent', null, 'Consent withdrawn for: ' . $purpose);
        }

        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Get the current consent state for all purposes for a user.
     *
     * @param int $userId
     * @return array  purpose => ['status' => 'granted'|'withdrawn'|'never', 'granted_at' => ..., 'withdrawn_at' => ...]
     */
    public static function stateForUser(int $userId): array
    {
        $records = Consent::forUser($userId);
        $state = [];

        foreach (ALLOWED_CONSENT_PURPOSES as $purpose) {
            $state[$purpose] = [
                'status'       => 'never',
                'granted_at'   => null,
                'withdrawn_at' => null,
            ];
        }

        foreach ($records as $record) {
            $purpose = $record['purpose'];
            if (!isset($state[$purpose])) {
                continue;
            }
            $state[$purpose] = [
                'status'       => $record['status'],
                'granted_at'   => $record['granted_at'],
                'withdrawn_at' => $record['withdrawn_at'],
            ];
        }

        return $state;
    }
}
