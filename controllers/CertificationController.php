<?php
/**
 * ================================================
 * INVESTHOOD IT - Certification Controller
 * ================================================
 * Handles add/edit/delete of a candidate's
 * certifications. All operations are ownership-scoped
 * to the authenticated user.
 */

class CertificationController
{
    /**
     * Add a certification.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors (by reference)
     * @return int|null  New certification id on success
     */
    public static function add(int $userId, array $input, array &$errors): ?int
    {
        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors)) {
            return null;
        }

        $id = Certification::create($userId, $clean);

        AuditLog::log($userId, 'certification_added', 'certification', $id, 'Certification added');
        CandidateProfile::refreshCompletion($userId);

        return $id;
    }

    /**
     * Update an owned certification.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function update(int $userId, int $id, array $input, array &$errors): bool
    {
        $owned = Certification::findOwned($id, $userId);
        if (!$owned) {
            $errors['general'] = 'Certification not found.';
            return false;
        }

        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors)) {
            return false;
        }

        Certification::update($id, $userId, $clean);

        AuditLog::log($userId, 'certification_updated', 'certification', $id, 'Certification updated');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Delete an owned certification.
     *
     * @param int $userId
     * @param int $id
     * @return bool
     */
    public static function delete(int $userId, int $id): bool
    {
        $owned = Certification::findOwned($id, $userId);
        if (!$owned) {
            return false;
        }

        Certification::delete($id, $userId);

        AuditLog::log($userId, 'certification_removed', 'certification', $id, 'Certification removed');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Sanitise certification input.
     *
     * @param array $input
     * @return array
     */
    private static function sanitise(array $input): array
    {
        return [
            'name'                 => Sanitizer::stripTags($input['name'] ?? ''),
            'issuing_organisation' => Sanitizer::stripTags($input['issuing_organisation'] ?? ''),
            'year_obtained'        => trim($input['year_obtained'] ?? ''),
            'expiry_date'          => trim($input['expiry_date'] ?? ''),
            'credential_id'        => Sanitizer::stripTags($input['credential_id'] ?? ''),
        ];
    }

    /**
     * Validate certification input.
     *
     * @param array $clean
     * @param array $errors (by reference)
     * @return bool
     */
    private static function validate(array $clean, array &$errors): bool
    {
        $validator = new Validator();
        $validator->validate($clean, [
            'name'                 => ['required', 'max:150'],
            'issuing_organisation' => ['max:150'],
            'year_obtained'        => ['max:4'],
            'expiry_date'          => ['date'],
            'credential_id'        => ['max:100'],
        ]);

        if (!$validator->passes()) {
            $errors = $validator->errors();
            return false;
        }

        // Validate year_obtained range
        $year = $clean['year_obtained'];
        if ($year !== '') {
            if (!preg_match('/^\d{4}$/', $year) || (int)$year < 1950 || (int)$year > (int)date('Y') + 1) {
                $errors['year_obtained'] = 'Please enter a valid year.';
                return false;
            }
        }

        return true;
    }
}
