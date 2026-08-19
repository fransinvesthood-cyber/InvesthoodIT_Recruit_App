<?php
/**
 * ================================================
 * INVESTHOOD IT - Qualification Controller
 * ================================================
 * Handles add/edit/delete of a candidate's
 * qualifications. All operations are ownership-scoped
 * to the authenticated user.
 */

class QualificationController
{
    /**
     * Add a qualification.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors (by reference)
     * @return int|null  New qualification id on success
     */
    public static function add(int $userId, array $input, array &$errors): ?int
    {
        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors)) {
            return null;
        }

        $id = Qualification::create($userId, $clean);

        AuditLog::log($userId, 'qualification_added', 'qualification', $id, 'Qualification added');
        CandidateProfile::refreshCompletion($userId);

        return $id;
    }

    /**
     * Update an owned qualification.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function update(int $userId, int $id, array $input, array &$errors): bool
    {
        $owned = Qualification::findOwned($id, $userId);
        if (!$owned) {
            $errors['general'] = 'Qualification not found.';
            return false;
        }

        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors)) {
            return false;
        }

        Qualification::update($id, $userId, $clean);

        AuditLog::log($userId, 'qualification_updated', 'qualification', $id, 'Qualification updated');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Delete an owned qualification.
     *
     * @param int $userId
     * @param int $id
     * @return bool
     */
    public static function delete(int $userId, int $id): bool
    {
        $owned = Qualification::findOwned($id, $userId);
        if (!$owned) {
            return false;
        }

        Qualification::delete($id, $userId);

        AuditLog::log($userId, 'qualification_removed', 'qualification', $id, 'Qualification removed');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Sanitise qualification input.
     *
     * @param array $input
     * @return array
     */
    private static function sanitise(array $input): array
    {
        return [
            'name'           => Sanitizer::stripTags($input['name'] ?? ''),
            'institution'    => Sanitizer::stripTags($input['institution'] ?? ''),
            'year_completed' => trim($input['year_completed'] ?? ''),
            'level'          => trim($input['level'] ?? ''),
        ];
    }

    /**
     * Validate qualification input.
     *
     * @param array $clean
     * @param array $errors (by reference)
     * @return bool
     */
    private static function validate(array $clean, array &$errors): bool
    {
        $validator = new Validator();
        $validator->validate($clean, [
            'name'           => ['required', 'max:150'],
            'institution'    => ['max:150'],
            'year_completed' => ['max:4'],
            'level'          => ['in:' . implode(',', ALLOWED_QUALIFICATIONS)],
        ]);

        if (!$validator->passes()) {
            $errors = $validator->errors();
            return false;
        }

        // Validate year_completed range
        $year = $clean['year_completed'];
        if ($year !== '') {
            if (!preg_match('/^\d{4}$/', $year) || (int)$year < 1950 || (int)$year > (int)date('Y') + 1) {
                $errors['year_completed'] = 'Please enter a valid year.';
                return false;
            }
        }

        return true;
    }
}
