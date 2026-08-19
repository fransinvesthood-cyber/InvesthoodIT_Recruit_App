<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Profile Model
 * ================================================
 * Manages the single active master profile for a
 * candidate, including dynamic completion scoring.
 */

class CandidateProfile
{
    /**
     * Create a candidate profile for a user (idempotent).
     *
     * @param int $userId
     * @return array  The profile row
     */
    public static function ensureForUser(int $userId): array
    {
        $existing = self::findByUser($userId);
        if ($existing) {
            return $existing;
        }

        Database::execute(
            "INSERT INTO candidate_profiles (user_id)
             VALUES (?)",
            'i',
            [$userId]
        );

        return self::findByUser($userId);
    }

    /**
     * Find the active master profile for a user.
     *
     * @param int $userId
     * @return array|null
     */
    public static function findByUser(int $userId): ?array
    {
        return Database::fetchOne(
            "SELECT cp.*, a.slug AS availability_slug, a.label AS availability_label
             FROM candidate_profiles cp
             LEFT JOIN availability_statuses a ON a.id = cp.availability_status_id
             WHERE cp.user_id = ? AND cp.is_active = 1
             LIMIT 1",
            'i',
            [$userId]
        );
    }

    /**
     * Update profile fields (whitelist).
     *
     * @param int   $userId
     * @param array $data
     * @return bool
     */
    public static function update(int $userId, array $data): bool
    {
        $allowed = [
            'professional_title',
            'professional_summary',
            'career_interests',
            'employment_status',
            'availability_status_id',
            'availability_date',
            'address',
            'city',
            'profile_picture',
            'completion_percent',
        ];

        $sets = [];
        $types = '';
        $params = [];

        foreach ($data as $col => $val) {
            if (in_array($col, $allowed, true)) {
                $sets[] = "`{$col}` = ?";
                $types .= 's';
                $params[] = $val;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "`updated_at` = NOW()";
        $types .= 'i';
        $params[] = $userId;

        $sql = "UPDATE candidate_profiles SET " . implode(', ', $sets) . " WHERE user_id = ?";
        return Database::execute($sql, $types, $params) >= 0;
    }

    /**
     * Compute the candidates' profile completion percentage.
     *
     * Completion is based on nine weighted sections:
     *   - Personal information (users table)
     *   - Professional information (candidate_profiles)
     *   - Profile picture
     *   - Qualifications (>= 1)
     *   - Technical skills (>= 3)
     *   - Soft skills (>= 1)
     *   - Work experience (>= 1)
     *   - CV document uploaded
     *   - Required consent (future-opportunities granted)
     *
     * @param int $userId
     * @return int  0-100
     */
    public static function calculateCompletion(int $userId): int
    {
        $user = User::find($userId);
        $profile = self::findByUser($userId);

        $score = 0;
        $total = 9;

        // 1. Personal information
        if ($user && $user['first_name'] && $user['last_name'] && $user['email']
            && $user['phone'] && $user['date_of_birth'] && $user['province']) {
            $score++;
        }

        // 2. Professional information
        if ($profile && $profile['professional_title'] && $profile['professional_summary']
            && $profile['availability_status_id']) {
            $score++;
        }

        // 3. Profile picture (users table or profile)
        $picture = $user['profile_picture'] ?? ($profile['profile_picture'] ?? null);
        if ($picture) {
            $score++;
        }

        // 4. Qualifications
        $qualCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM qualifications WHERE user_id = ?",
            'i',
            [$userId]
        )['cnt'];
        if ($qualCount > 0) {
            $score++;
        }

        // 5. Technical skills (>= 3)
        $techCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM candidate_skills cs
             INNER JOIN skills s ON s.id = cs.skill_id
             WHERE cs.user_id = ? AND s.category = 'technical'",
            'i',
            [$userId]
        )['cnt'];
        if ($techCount >= 3) {
            $score++;
        }

        // 6. Soft skills (>= 1)
        $softCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM candidate_skills cs
             INNER JOIN skills s ON s.id = cs.skill_id
             WHERE cs.user_id = ? AND s.category = 'soft'",
            'i',
            [$userId]
        )['cnt'];
        if ($softCount >= 1) {
            $score++;
        }

        // 7. Work experience (>= 1)
        $expCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM work_experience WHERE user_id = ?",
            'i',
            [$userId]
        )['cnt'];
        if ($expCount > 0) {
            $score++;
        }

        // 8. CV document uploaded
        $cvCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM documents WHERE user_id = ? AND document_type = 'cv'",
            'i',
            [$userId]
        )['cnt'];
        if ($cvCount > 0) {
            $score++;
        }

        // 9. Required consent (future-opportunities granted)
        $consent = Consent::findForUser($userId, CONSENT_FUTURE_OPPORTUNITIES);
        if ($consent && $consent['status'] === 'granted') {
            $score++;
        }

        $percent = (int) round(($score / $total) * 100);
        $percent = max(0, min(100, $percent));

        return $percent;
    }

    /**
     * Recalculate and persist completion percentage.
     *
     * @param int $userId
     * @return int
     */
    public static function refreshCompletion(int $userId): int
    {
        $percent = self::calculateCompletion($userId);
        self::update($userId, ['completion_percent' => $percent]);
        return $percent;
    }

    /**
     * Build a detailed breakdown of completion by section.
     * Used by the profile page to list incomplete sections.
     *
     * @param int $userId
     * @return array
     */
    public static function completionBreakdown(int $userId): array
    {
        $user = User::find($userId);
        $profile = self::findByUser($userId);

        $personalDone = !empty($user['first_name']) && !empty($user['last_name'])
            && !empty($user['email']) && !empty($user['phone'])
            && !empty($user['date_of_birth']) && !empty($user['province']);

        $professionalDone = !empty($profile['professional_title'])
            && !empty($profile['professional_summary'])
            && !empty($profile['availability_status_id']);

        $picture = $user['profile_picture'] ?? ($profile['profile_picture'] ?? null);

        $qualCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM qualifications WHERE user_id = ?", 'i', [$userId]
        )['cnt'];
        $techCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM candidate_skills cs
             INNER JOIN skills s ON s.id = cs.skill_id
             WHERE cs.user_id = ? AND s.category = 'technical'", 'i', [$userId]
        )['cnt'];
        $softCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM candidate_skills cs
             INNER JOIN skills s ON s.id = cs.skill_id
             WHERE cs.user_id = ? AND s.category = 'soft'", 'i', [$userId]
        )['cnt'];
        $expCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM work_experience WHERE user_id = ?", 'i', [$userId]
        )['cnt'];
        $cvCount = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM documents WHERE user_id = ? AND document_type = 'cv'", 'i', [$userId]
        )['cnt'];
        $consent = Consent::findForUser($userId, CONSENT_FUTURE_OPPORTUNITIES);

        return [
            'personal' => [
                'label' => 'Personal Information',
                'done'  => $personalDone,
                'detail'=> $personalDone ? 'Completed' : 'Complete your personal details',
                'tab'   => 'personal',
            ],
            'professional' => [
                'label' => 'Professional Information',
                'done'  => $professionalDone,
                'detail'=> $professionalDone ? 'Completed' : 'Add your professional title & summary',
                'tab'   => 'professional',
            ],
            'picture' => [
                'label' => 'Profile Picture',
                'done'  => !empty($picture),
                'detail'=> !empty($picture) ? 'Uploaded' : 'Upload a profile picture',
                'tab'   => 'personal',
            ],
            'qualifications' => [
                'label' => 'Qualifications',
                'done'  => $qualCount > 0,
                'detail'=> $qualCount > 0 ? $qualCount . ' added' : 'Add at least one qualification',
                'tab'   => 'qualifications',
            ],
            'technical_skills' => [
                'label' => 'Technical Skills',
                'done'  => $techCount >= 3,
                'detail'=> $techCount >= 3 ? $techCount . ' added' : 'Add at least 3 technical skills',
                'tab'   => 'skills',
            ],
            'soft_skills' => [
                'label' => 'Soft Skills',
                'done'  => $softCount >= 1,
                'detail'=> $softCount >= 1 ? $softCount . ' added' : 'Add at least 1 soft skill',
                'tab'   => 'skills',
            ],
            'experience' => [
                'label' => 'Work Experience',
                'done'  => $expCount > 0,
                'detail'=> $expCount > 0 ? $expCount . ' record(s)' : 'Add at least one work experience',
                'tab'   => 'experience',
            ],
            'cv' => [
                'label' => 'CV / Resume',
                'done'  => $cvCount > 0,
                'detail'=> $cvCount > 0 ? 'Uploaded' : 'Upload your CV',
                'tab'   => 'documents',
            ],
            'consent' => [
                'label' => 'Consent',
                'done'  => $consent && $consent['status'] === 'granted',
                'detail'=> ($consent && $consent['status'] === 'granted') ? 'Provided' : 'Grant future-opportunity consent',
                'tab'   => 'consent',
            ],
        ];
    }
}
