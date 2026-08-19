<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Controller
 * ================================================
 * Business logic, validation, status transitions and
 * audit logging for programmes.
 */

class ProgrammeController
{
    /** @var string[] Allowed programme types */
    private const TYPES = [
        'graduate_programme', 'internship', 'learnership', 'wil', 'skills_development', 'other',
    ];

    /** @var string[] Allowed qualification levels */
    public const QUALIFICATION_LEVELS = [
        'certificate', 'diploma', 'degree', 'honours', 'other',
    ];

    /**
     * Create a programme.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors (by reference)
     * @return int|null  New programme ID or null on failure
     */
    public static function create(int $userId, array $input, array &$errors): ?int
    {
        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors)) {
            return null;
        }

        $id = Programme::create($userId, $clean);
        AuditLog::log($userId, 'Programme Created', 'programme', $id, 'Programme created');
        return $id;
    }

    /**
     * Update a programme.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function update(int $userId, int $id, array $input, array &$errors): bool
    {
        $programme = Programme::find($id);
        if (!$programme) {
            $errors['general'] = 'Programme not found.';
            return false;
        }

        // Preserve current status for transition validation (status may change here)
        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors, true)) {
            return false;
        }

        $newStatus = $clean['status'];
        if ($newStatus !== $programme['status']) {
            if (!self::canTransition($programme['status'], $newStatus, $errors)) {
                return false;
            }
        }

        Programme::update($id, $clean);
        AuditLog::log($userId, 'Programme Updated', 'programme', $id, 'Programme details updated');
        return true;
    }

    /**
     * Change a programme's status with transition validation.
     *
     * @param int    $userId
     * @param int    $id
     * @param string $newStatus
     * @param array  $errors (by reference)
     * @return bool
     */
    public static function changeStatus(int $userId, int $id, string $newStatus, array &$errors): bool
    {
        $programme = Programme::find($id);
        if (!$programme) {
            $errors['general'] = 'Programme not found.';
            return false;
        }

        if (!in_array($newStatus, Programme::STATUSES, true)) {
            $errors['status'] = 'Invalid status.';
            return false;
        }

        if (!self::canTransition($programme['status'], $newStatus, $errors)) {
            return false;
        }

        Programme::setStatus($id, $newStatus);

        $action = 'Programme ' . ucfirst($newStatus);
        AuditLog::log($userId, $action, 'programme', $id, 'Status changed from ' . $programme['status'] . ' to ' . $newStatus);
        return true;
    }

    /**
     * Duplicate a programme (with its cohorts) into a new Draft.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $errors (by reference)
     * @return int|null  New programme ID
     */
    public static function duplicate(int $userId, int $id, array &$errors): ?int
    {
        $programme = Programme::find($id);
        if (!$programme) {
            $errors['general'] = 'Programme not found.';
            return null;
        }

        $data = [
            'name'        => $programme['name'] . ' (Copy) ' . date('Y'),
            'type'        => $programme['type'],
            'description' => $programme['description'],
            'objectives'  => $programme['objectives'],
            'duration'    => $programme['duration'],
            'start_date'  => $programme['start_date'],
            'end_date'    => $programme['end_date'],
            'status'      => 'draft',
        ];

        $newId = Programme::create($userId, $data);

        // Duplicate cohorts (as drafts) and their config
        $cohorts = Cohort::forProgramme($id);
        foreach ($cohorts as $cohort) {
            $copyCohort = [
                'programme_id'          => $newId,
                'name'                  => $cohort['name'],
                'description'           => $cohort['description'],
                'start_date'            => $cohort['start_date'],
                'end_date'              => $cohort['end_date'],
                'application_open_date' => $cohort['application_open_date'],
                'application_close_date' => $cohort['application_close_date'],
                'max_capacity'          => $cohort['max_capacity'],
                'location'              => $cohort['location'],
                'province'              => $cohort['province'],
                'delivery_mode'         => $cohort['delivery_mode'],
                'status'                => 'draft',
            ];
            $newCohortId = Cohort::create($userId, $copyCohort);

            // Copy eligibility
            CohortEligibility::inheritFromProgramme($newCohortId, $newId);

            // Copy skills
            $skills = CohortSkill::forCohort((int) $cohort['id']);
            if (!empty($skills)) {
                $skillData = [];
                foreach ($skills as $s) {
                    $skillData[] = ['name' => $s['skill_name'], 'category' => $s['skill_category']];
                }
                CohortSkill::replaceForCohort($newCohortId, $skillData);
            }

            // Copy documents
            $docs = CohortDocument::forCohort((int) $cohort['id']);
            foreach ($docs as $doc) {
                CohortDocument::add($newCohortId, [
                    'document_name'         => $doc['document_name'],
                    'is_required'           => $doc['is_required'],
                    'verification_required' => $doc['verification_required'],
                    'expiry_required'       => $doc['expiry_required'],
                ]);
            }

            // Copy workflow
            $workflow = CohortWorkflow::forCohort((int) $cohort['id']);
            $activeStages = [];
            foreach ($workflow as $wf) {
                if ((int) $wf['is_active'] === 1) {
                    $activeStages[] = $wf['stage'];
                }
            }
            CohortWorkflow::initForCohort($newCohortId);
            CohortWorkflow::replaceForCohort($newCohortId, $activeStages);
        }

        AuditLog::log($userId, 'Programme Duplicated', 'programme', $newId, 'Duplicated from programme #' . $id);
        return $newId;
    }

/**
     * Update programme eligibility configuration.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function updateEligibility(int $userId, int $id, array $input, array &$errors): bool
    {
        $programme = Programme::find($id);
        if (!$programme) {
            $errors['general'] = 'Programme not found.';
            return false;
        }

        $clean = [
            'qualification_level'     => Sanitizer::stripTags($input['qualification_level'] ?? ''),
            'qualification_name'      => Sanitizer::stripTags($input['qualification_name'] ?? ''),
            'field_of_study'          => Sanitizer::stripTags($input['field_of_study'] ?? ''),
            'institution_requirements' => Sanitizer::stripTags($input['institution_requirements'] ?? ''),
            'min_completion_year'     => $input['min_completion_year'] ?? '',
            'max_completion_year'     => $input['max_completion_year'] ?? '',
            'min_experience'          => $input['min_experience'] ?? '',
            'max_experience'          => $input['max_experience'] ?? '',
            'province'                => $input['province'] ?? '',
            'city'                    => Sanitizer::stripTags($input['city'] ?? ''),
            'location_restrictions'   => Sanitizer::stripTags($input['location_restrictions'] ?? ''),
            'availability'            => Sanitizer::stripTags($input['availability'] ?? ''),
            'citizenship_residency'   => Sanitizer::stripTags($input['citizenship_residency'] ?? ''),
            'programme_specific'      => Sanitizer::stripTags($input['programme_specific'] ?? ''),
        ];

        // Qualification level must be one of the allowed values
        if (!empty($clean['qualification_level']) && !in_array($clean['qualification_level'], self::QUALIFICATION_LEVELS, true)) {
            $errors['qualification_level'] = 'Invalid qualification level.';
        }

        // min must be <= max
        if ($clean['min_experience'] !== '' && $clean['max_experience'] !== '' && (int) $clean['min_experience'] > (int) $clean['max_experience']) {
            $errors['max_experience'] = 'Maximum experience cannot be less than minimum experience.';
        }

        if ($clean['min_completion_year'] !== '' && $clean['max_completion_year'] !== '' && (int) $clean['min_completion_year'] > (int) $clean['max_completion_year']) {
            $errors['max_completion_year'] = 'Maximum completion year cannot be before minimum completion year.';
        }

        if (!empty($errors)) {
            return false;
        }

        ProgrammeEligibility::upsert($id, $clean);
        AuditLog::log($userId, 'Eligibility Updated', 'programme', $id, 'Programme eligibility requirements updated');
        return true;
    }

    /**
     * Validate a role-based status transition.
     *
     * @param string $current
     * @param string $next
     * @param array  $errors (by reference)
     * @return bool
     */
    public static function canTransition(string $current, string $next, array &$errors): bool
    {
        $allowed = Programme::TRANSITIONS[$current] ?? [];
        if (!in_array($next, $allowed, true)) {
            $errors['status'] = "Cannot move programme from '{$current}' to '{$next}'.";
            return false;
        }
        return true;
    }

    /**
     * Validate field requirements before activation.
     *
     * @param array $clean
     * @param array $errors (by reference)
     * @return bool
     */
    public static function validateForActivation(array $clean, array &$errors): bool
    {
        if (($clean['status'] ?? '') === 'active') {
            if (empty($clean['name'])) {
                $errors['name'] = 'Programme name is required before activation.';
            }
            if (empty($clean['type'])) {
                $errors['type'] = 'Programme type is required before activation.';
            }
            if (empty($clean['start_date']) || empty($clean['end_date'])) {
                $errors['dates'] = 'Both start and end dates are required before activation.';
            }
            return empty($errors);
        }
        return true;
    }

    /**
     * Sanitise programme input.
     *
     * @param array $input
     * @return array
     */
    private static function sanitise(array $input): array
    {
        return [
            'name'        => Sanitizer::stripTags($input['name'] ?? ''),
            'type'        => $input['type'] ?? 'other',
            'description' => Sanitizer::stripTags($input['description'] ?? ''),
            'objectives'  => Sanitizer::stripTags($input['objectives'] ?? ''),
            'duration'    => Sanitizer::stripTags($input['duration'] ?? ''),
            'start_date'  => $input['start_date'] ?? '',
            'end_date'    => $input['end_date'] ?? '',
            'status'      => $input['status'] ?? 'draft',
        ];
    }

    /**
     * Validate programme input.
     *
     * @param array $clean
     * @param array $errors (by reference)
     * @param bool  $isUpdate
     * @return bool
     */
    private static function validate(array $clean, array &$errors, bool $isUpdate = false): bool
    {
        $validator = new Validator();
        $validator->validate($clean, [
            'name'        => ['required', 'max:200'],
            'type'        => ['in:' . implode(',', self::TYPES)],
            'description' => ['max:5000'],
            'objectives'  => ['max:5000'],
            'duration'    => ['max:100'],
            'status'      => ['in:' . implode(',', Programme::STATUSES)],
        ]);

        // Date validation
        if (!empty($clean['start_date'])) {
            if (!self::isValidDate($clean['start_date'])) {
                $errors['start_date'] = 'Start date is invalid.';
            }
        }
        if (!empty($clean['end_date'])) {
            if (!self::isValidDate($clean['end_date'])) {
                $errors['end_date'] = 'End date is invalid.';
            }
        }
        if (!empty($clean['start_date']) && !empty($clean['end_date'])) {
            if ($clean['end_date'] < $clean['start_date']) {
                $errors['end_date'] = 'End date cannot be before start date.';
            }
        }

        if (!$validator->passes()) {
            $errors = array_merge($errors, $validator->errors());
            return false;
        }

        if (!$isUpdate || ($clean['status'] !== '' && $clean['status'] !== 'draft')) {
            self::validateForActivation($clean, $errors);
        }

        return empty($errors);
    }

    /**
     * Validate a YYYY-MM-DD date.
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
