<?php
/**
 * ================================================
 * INVESTHOOD IT - Work Experience Controller
 * ================================================
 * Handles add/edit/delete/reorder of a candidate's
 * work experience records. All operations are
 * ownership-scoped and validate date ranges.
 */

class WorkExperienceController
{
    /**
     * Add a work experience record.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors (by reference)
     * @return int|null
     */
    public static function add(int $userId, array $input, array &$errors): ?int
    {
        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors)) {
            return null;
        }

        $id = WorkExperience::create($userId, $clean);

        AuditLog::log($userId, 'experience_added', 'work_experience', $id, 'Work experience added');
        CandidateProfile::refreshCompletion($userId);

        return $id;
    }

    /**
     * Update an owned work experience record.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function update(int $userId, int $id, array $input, array &$errors): bool
    {
        $owned = WorkExperience::findOwned($id, $userId);
        if (!$owned) {
            $errors['general'] = 'Work experience record not found.';
            return false;
        }

        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors)) {
            return false;
        }

        WorkExperience::update($id, $userId, $clean);

        AuditLog::log($userId, 'experience_updated', 'work_experience', $id, 'Work experience updated');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Delete an owned work experience record.
     *
     * @param int $userId
     * @param int $id
     * @return bool
     */
    public static function delete(int $userId, int $id): bool
    {
        $deleted = WorkExperience::delete($id, $userId);
        if (!$deleted) {
            return false;
        }

        AuditLog::log($userId, 'experience_removed', 'work_experience', $id, 'Work experience removed');
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Reorder a candidate's work experience records.
     *
     * @param int   $userId
     * @param array $orderedIds
     * @return bool
     */
    public static function reorder(int $userId, array $orderedIds): bool
    {
        // Validate all IDs belong to the user
        $owned = WorkExperience::forUser($userId);
        $ownedIds = array_map('intval', array_column($owned, 'id'));
        $incoming = array_map('intval', $orderedIds);

        $diff = array_diff($incoming, $ownedIds);
        if (!empty($diff)) {
            return false;
        }

        WorkExperience::reorder($userId, $incoming);
        AuditLog::log($userId, 'experience_reordered', 'work_experience', null, 'Work experience reordered');

        return true;
    }

    /**
     * Sanitise work experience input.
     *
     * @param array $input
     * @return array
     */
    private static function sanitise(array $input): array
    {
        $isCurrent = !empty($input['is_current']) && $input['is_current'] === 'on';

        return [
            'job_title'   => Sanitizer::stripTags($input['job_title'] ?? ''),
            'company'     => Sanitizer::stripTags($input['company'] ?? ''),
            'start_date'  => trim($input['start_date'] ?? ''),
            'end_date'    => $isCurrent ? null : trim($input['end_date'] ?? ''),
            'is_current'  => $isCurrent,
            'description' => Sanitizer::stripTags($input['description'] ?? ''),
        ];
    }

    /**
     * Validate work experience input.
     *
     * @param array $clean
     * @param array $errors (by reference)
     * @return bool
     */
    private static function validate(array $clean, array &$errors): bool
    {
        $validator = new Validator();
        $validator->validate($clean, [
            'job_title'  => ['required', 'max:150'],
            'company'    => ['required', 'max:150'],
            'start_date' => ['required'],
            'description'=> ['max:3000'],
        ]);

        if (!$validator->passes()) {
            $errors = $validator->errors();
            return false;
        }

        // Validate date format & range
        if (!self::isValidDate($clean['start_date'])) {
            $errors['start_date'] = 'Please enter a valid start date.';
            return false;
        }

        if (!$clean['is_current']) {
            if (empty($clean['end_date'])) {
                $errors['end_date'] = 'Please provide an end date.';
                return false;
            }
            if (!self::isValidDate($clean['end_date'])) {
                $errors['end_date'] = 'Please enter a valid end date.';
                return false;
            }
            if ($clean['end_date'] < $clean['start_date']) {
                $errors['end_date'] = 'End date cannot be before the start date.';
                return false;
            }
            if ($clean['end_date'] > date('Y-m-d')) {
                $errors['end_date'] = 'End date cannot be in the future.';
                return false;
            }
        }

        return true;
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
