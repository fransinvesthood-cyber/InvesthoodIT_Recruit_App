<?php
/**
 * ================================================
 * INVESTHOOD IT - Profile Controller
 * ================================================
 * Business logic for a candidate's personal and
 * professional information, plus profile picture.
 * All operations are scoped to the authenticated
 * user's ID (ownership enforced server-side).
 */

class ProfileController
{
    /**
     * Update personal information (users table).
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function updatePersonal(int $userId, array $input, array &$errors): bool
    {
        // ---- 1. Sanitise ----
        $clean = [
            'first_name'    => Sanitizer::name($input['first_name'] ?? ''),
            'last_name'     => Sanitizer::name($input['last_name'] ?? ''),
            'email'         => Sanitizer::email($input['email'] ?? ''),
            'phone'         => Sanitizer::phone($input['phone'] ?? ''),
            'date_of_birth' => trim($input['date_of_birth'] ?? ''),
            'gender'        => trim($input['gender'] ?? ''),
            'address'       => Sanitizer::stripTags($input['address'] ?? ''),
            'province'      => trim($input['province'] ?? ''),
            'city'          => Sanitizer::stripTags($input['city'] ?? ''),
        ];

        // ---- 2. Validate ----
        $validator = new Validator();
        $validator->validate($clean, [
            'first_name'    => ['required', 'min:2', 'max:50'],
            'last_name'     => ['required', 'min:2', 'max:50'],
            'email'         => ['required', 'email', 'max:100'],
            'phone'         => ['required', 'phone'],
            'date_of_birth' => ['required'],
            'province'      => ['required', 'in:' . implode(',', ALLOWED_PROVINCES)],
            'gender'        => ['in:' . implode(',', ALLOWED_GENDERS)],
        ]);

        if (!$validator->passes()) {
            $errors = $validator->errors();
            return false;
        }

        // Validate date of birth (past, plausible age)
        $dob = $clean['date_of_birth'];
        if (!self::isValidDate($dob) || $dob >= date('Y-m-d') || $dob < date('Y-m-d', strtotime('-100 years'))) {
            $errors['date_of_birth'] = 'Please enter a valid date of birth.';
            return false;
        }

        // ---- 3. Email uniqueness (exclude self) ----
        if (User::emailExists($clean['email'], $userId)) {
            $errors['email'] = 'This email address is already in use.';
            return false;
        }

        // ---- 4. Persist ----
        User::update($userId, [
            'first_name'    => $clean['first_name'],
            'last_name'     => $clean['last_name'],
            'email'         => $clean['email'],
            'phone'         => $clean['phone'],
            'date_of_birth' => $clean['date_of_birth'],
            'gender'        => $clean['gender'] !== '' ? $clean['gender'] : null,
            'province'      => $clean['province'],
        ]);

        // Address & city live on the candidate profile
        CandidateProfile::update($userId, [
            'address' => $clean['address'] !== '' ? $clean['address'] : null,
            'city'    => $clean['city'] !== '' ? $clean['city'] : null,
        ]);

        // Refresh session name/email
        self::refreshSession($userId);

        AuditLog::log($userId, 'profile_updated', 'candidate_profile', null, 'Personal information updated');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Update professional information (candidate_profiles table).
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function updateProfessional(int $userId, array $input, array &$errors): bool
    {
        $clean = [
            'professional_title'   => Sanitizer::stripTags($input['professional_title'] ?? ''),
            'professional_summary' => Sanitizer::stripTags($input['professional_summary'] ?? ''),
            'career_interests'     => Sanitizer::stripTags($input['career_interests'] ?? ''),
            'employment_status'    => trim($input['employment_status'] ?? ''),
            'availability_status'  => trim($input['availability_status'] ?? ''),
            'availability_date'    => trim($input['availability_date'] ?? ''),
        ];

        $validator = new Validator();
        $validator->validate($clean, [
            'professional_title'   => ['required', 'max:100'],
            'professional_summary' => ['max:2000'],
            'career_interests'     => ['max:1000'],
            'employment_status'    => ['in:' . implode(',', ALLOWED_EMPLOYMENT_STATUS)],
        ]);

        if (!$validator->passes()) {
            $errors = $validator->errors();
            return false;
        }

        // Resolve availability status id from slug
        $availabilityId = null;
        if ($clean['availability_status'] !== '') {
            $status = AvailabilityStatus::findBySlug($clean['availability_status']);
            if (!$status) {
                $errors['availability_status'] = 'Please select a valid availability status.';
                return false;
            }
            $availabilityId = (int) $status['id'];
        }

        // Validate availability date only when available_from_date is chosen
        $availabilityDate = null;
        if ($clean['availability_status'] === AVAILABILITY_AVAILABLE_DATE) {
            if ($clean['availability_date'] === '' || !self::isValidDate($clean['availability_date'])) {
                $errors['availability_date'] = 'Please enter a valid availability date.';
                return false;
            }
            $availabilityDate = $clean['availability_date'];
        }

        CandidateProfile::update($userId, [
            'professional_title'    => $clean['professional_title'] !== '' ? $clean['professional_title'] : null,
            'professional_summary'  => $clean['professional_summary'] !== '' ? $clean['professional_summary'] : null,
            'career_interests'      => $clean['career_interests'] !== '' ? $clean['career_interests'] : null,
            'employment_status'     => $clean['employment_status'] !== '' ? $clean['employment_status'] : null,
            'availability_status_id'=> $availabilityId,
            'availability_date'     => $availabilityDate,
        ]);

        // Mirrors professional title into the users table for consistency
        if ($clean['professional_title'] !== '') {
            User::update($userId, ['professional_title' => $clean['professional_title']]);
        }

        AuditLog::log($userId, 'profile_updated', 'candidate_profile', null, 'Professional information updated');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Upload a new profile picture.
     *
     * @param int   $userId
     * @param array $file
     * @param array $errors (by reference)
     * @return bool
     */
    public static function uploadPicture(int $userId, array $file, array &$errors): bool
    {
        $result = FileUploader::uploadProfileImage($file);
        if (!$result['success']) {
            $errors['profile_picture'] = $result['message'];
            return false;
        }

        // Remove the old picture file if there is one
        $profile = CandidateProfile::findByUser($userId);
        $oldFile = $profile['profile_picture'] ?? null;
        if ($oldFile) {
            FileUploader::delete('avatars', $oldFile);
        }

        CandidateProfile::update($userId, ['profile_picture' => $result['stored_filename']]);

        AuditLog::log($userId, 'profile_picture_uploaded', 'candidate_profile', null, 'Profile picture uploaded');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Remove the profile picture.
     *
     * @param int $userId
     * @return bool
     */
    public static function removePicture(int $userId): bool
    {
        $profile = CandidateProfile::findByUser($userId);
        $oldFile = $profile['profile_picture'] ?? null;
        if ($oldFile) {
            FileUploader::delete('avatars', $oldFile);
        }

        CandidateProfile::update($userId, ['profile_picture' => null]);

        AuditLog::log($userId, 'profile_picture_removed', 'candidate_profile', null, 'Profile picture removed');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Refresh the session name/email after a personal update.
     *
     * @param int $userId
     */
    private static function refreshSession(int $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $_SESSION['fullname'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['email']    = $user['email'];
        }
    }

    /**
     * Validate a Y-m-d date string.
     *
     * @param string $date
     * @return bool
     */
    private static function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
