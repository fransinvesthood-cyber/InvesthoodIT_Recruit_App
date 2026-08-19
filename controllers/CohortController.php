<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Controller
 * ================================================
 * Business logic, validation, status transitions,
 * capacity rules and audit logging for cohorts,
 * eligibility, documents and workflow configuration.
 */

class CohortController
{
    /** @var string[] Allowed delivery modes */
    private const DELIVERY_MODES = ['on_site', 'remote', 'hybrid'];

    /** @var string[] Allowed qualification levels */
    public const QUALIFICATION_LEVELS = [
        'certificate', 'diploma', 'degree', 'honours', 'other',
    ];

    /**
     * Create a cohort.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors (by reference)
     * @return int|null  New cohort ID or null on failure
     */
    public static function create(int $userId, array $input, array &$errors): ?int
    {
        $clean = self::sanitise($input);

        // Validate against parent programme dates
        if (!self::validateAgainstProgramme($clean, $errors)) {
            return null;
        }

        if (!self::validate($clean, $errors)) {
            return null;
        }

        $id = Cohort::create($userId, $clean);

        // Initialise default workflow stages
        CohortWorkflow::initForCohort($id);

        // Inherit eligibility from programme if available
        CohortEligibility::inheritFromProgramme($id, (int) $clean['programme_id']);

        AuditLog::log($userId, 'Cohort Created', 'cohort', $id, 'Cohort created under programme #' . $clean['programme_id']);
        return $id;
    }

    /**
     * Update a cohort.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function update(int $userId, int $id, array $input, array &$errors): bool
    {
        $cohort = Cohort::findWithProgramme($id);
        if (!$cohort) {
            $errors['general'] = 'Cohort not found.';
            return false;
        }

        $clean = self::sanitise($input);
        $clean['programme_id'] = (int) $cohort['programme_id'];

        if (!self::validateAgainstProgramme($clean, $errors, $cohort)) {
            return false;
        }

        if (!self::validate($clean, $errors, true, $cohort)) {
            return false;
        }

        // Status transition check
        if ($clean['status'] !== $cohort['status']) {
            if (!self::canTransition($cohort['status'], $clean['status'], $errors)) {
                return false;
            }
        }

        // Capacity cannot be reduced below committed participants
        $committed = Cohort::committedCount($id);
        if ((int) $clean['max_capacity'] < $committed) {
            $errors['max_capacity'] = "Capacity cannot be reduced below {$committed} already committed participant(s).";
            return false;
        }

        Cohort::update($id, $clean);
        AuditLog::log($userId, 'Cohort Updated', 'cohort', $id, 'Cohort details updated');
        return true;
    }

    /**
     * Change cohort status with transition + business rule validation.
     *
     * @param int    $userId
     * @param int    $id
     * @param string $newStatus
     * @param array  $errors (by reference)
     * @return bool
     */
    public static function changeStatus(int $userId, int $id, string $newStatus, array &$errors): bool
    {
        $cohort = Cohort::find($id);
        if (!$cohort) {
            $errors['general'] = 'Cohort not found.';
            return false;
        }

        if (!in_array($newStatus, Cohort::STATUSES, true)) {
            $errors['status'] = 'Invalid status.';
            return false;
        }

        if (!self::canTransition($cohort['status'], $newStatus, $errors)) {
            return false;
        }

        // A completed/closed cohort should not be reopened for new applications
        if ($newStatus === 'open' && in_array($cohort['status'], ['completed'], true)) {
            $errors['status'] = 'A completed cohort cannot be reopened for applications.';
            return false;
        }

        // Required fields must be complete before activation
        if ($newStatus === 'active') {
            if (empty($cohort['max_capacity']) || (int) $cohort['max_capacity'] <= 0) {
                $errors['max_capacity'] = 'Maximum capacity must be greater than zero before activation.';
                return false;
            }
            if (empty($cohort['start_date']) || empty($cohort['end_date'])) {
                $errors['dates'] = 'Cohort start and end dates are required before activation.';
                return false;
            }
        }

        // Opening applications requires relevant dates
        if ($newStatus === 'open') {
            if (empty($cohort['application_open_date'])) {
                $errors['application_open_date'] = 'Application opening date is required before opening applications.';
                return false;
            }
        }

        Cohort::setStatus($id, $newStatus);
        $action = 'Cohort ' . ucfirst($newStatus);
        AuditLog::log($userId, $action, 'cohort', $id, 'Status changed from ' . $cohort['status'] . ' to ' . $newStatus);
        return true;
    }

    /**
     * Update cohort eligibility configuration.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function updateEligibility(int $userId, int $id, array $input, array &$errors): bool
    {
        $cohort = Cohort::find($id);
        if (!$cohort) {
            $errors['general'] = 'Cohort not found.';
            return false;
        }

        $clean = self::sanitiseEligibility($input);
        if (!self::validateEligibility($clean, $errors)) {
            return false;
        }

        CohortEligibility::upsert($id, $clean);
        AuditLog::log($userId, 'Eligibility Updated', 'cohort', $id, 'Eligibility requirements updated');
        return true;
    }

    /**
     * Update cohort skills configuration.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input  ['skills' => [['name'=>, 'category'=>], ...]]
     * @param array $errors (by reference)
     * @return bool
     */
    public static function updateSkills(int $userId, int $id, array $input, array &$errors): bool
    {
        $cohort = Cohort::find($id);
        if (!$cohort) {
            $errors['general'] = 'Cohort not found.';
            return false;
        }

        $skills = $input['skills'] ?? [];
        $validCategories = ['required_technical', 'preferred_technical', 'required_soft'];
        $clean = [];
        foreach ($skills as $skill) {
            $name = Sanitizer::stripTags($skill['name'] ?? '');
            $category = $skill['category'] ?? 'required_technical';
            if ($name === '') {
                continue;
            }
            if (!in_array($category, $validCategories, true)) {
                $category = 'required_technical';
            }
            $clean[] = ['name' => $name, 'category' => $category];
        }

        CohortSkill::replaceForCohort($id, $clean);
        AuditLog::log($userId, 'Evidence Updated', 'cohort', $id, 'Skill requirements updated');
        return true;
    }

    /**
     * Update cohort required documents configuration.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input  ['documents' => [['document_name'=>, 'is_required'=>, 'verification_required'=>, 'expiry_required'=>], ...]]
     * @param array $errors (by reference)
     * @return bool
     */
    public static function updateDocuments(int $userId, int $id, array $input, array &$errors): bool
    {
        $cohort = Cohort::find($id);
        if (!$cohort) {
            $errors['general'] = 'Cohort not found.';
            return false;
        }

        $documents = $input['documents'] ?? [];
        $clean = [];
        foreach ($documents as $doc) {
            $name = Sanitizer::stripTags($doc['document_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $clean[] = [
                'document_name'         => $name,
                'is_required'           => !empty($doc['is_required']) ? 1 : 0,
                'verification_required' => !empty($doc['verification_required']) ? 1 : 0,
                'expiry_required'       => !empty($doc['expiry_required']) ? 1 : 0,
            ];
        }

        // Replace existing documents
        $existing = CohortDocument::forCohort($id);
        foreach ($existing as $doc) {
            CohortDocument::delete((int) $doc['id'], $id);
        }
        foreach ($clean as $doc) {
            CohortDocument::add($id, $doc);
        }

        AuditLog::log($userId, 'Evidence Requirement Updated', 'cohort', $id, 'Required evidence configuration updated');
        return true;
    }

    /**
     * Update cohort workflow configuration.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input  ['active_stages' => [...], 'stages' => [['stage'=>, 'is_active'=>], ...]]
     * @param array $errors (by reference)
     * @return bool
     */
    public static function updateWorkflow(int $userId, int $id, array $input, array &$errors): bool
    {
        $cohort = Cohort::find($id);
        if (!$cohort) {
            $errors['general'] = 'Cohort not found.';
            return false;
        }

        $activeStages = [];
        if (isset($input['stages']) && is_array($input['stages'])) {
            foreach ($input['stages'] as $stage) {
                if (!empty($stage['is_active'])) {
                    $activeStages[] = $stage['stage'];
                }
            }
        } elseif (isset($input['active_stages']) && is_array($input['active_stages'])) {
            $activeStages = $input['active_stages'];
        }

        CohortWorkflow::replaceForCohort($id, $activeStages);
        AuditLog::log($userId, 'Workflow Configuration Updated', 'cohort', $id, 'Workflow stages configuration updated');
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
        $allowed = Cohort::TRANSITIONS[$current] ?? [];
        if (!in_array($next, $allowed, true)) {
            $errors['status'] = "Cannot move cohort from '{$current}' to '{$next}'.";
            return false;
        }
        return true;
    }

    /**
     * Validate cohort dates against parent programme dates.
     *
     * @param array      $clean
     * @param array      $errors (by reference)
     * @param array|null $existing  Existing cohort row (for update)
     * @return bool
     */
    private static function validateAgainstProgramme(array $clean, array &$errors, ?array $existing = null): bool
    {
        $programmeId = (int) ($existing['programme_id'] ?? $clean['programme_id'] ?? 0);
        $programme = Programme::find($programmeId);
        if (!$programme) {
            $errors['programme_id'] = 'Selected programme does not exist.';
            return false;
        }

        $start = $clean['start_date'] ?? '';
        $end = $clean['end_date'] ?? '';
        $appClose = $clean['application_close_date'] ?? '';

        // Cohort dates should generally fall within programme dates where programme dates are set
        if (!empty($programme['start_date']) && !empty($start) && $start < $programme['start_date']) {
            $errors['start_date'] = 'Cohort start date cannot be before the programme start date (' . $programme['start_date'] . ').';
        }
        if (!empty($programme['end_date']) && !empty($end) && $end > $programme['end_date']) {
            $errors['end_date'] = 'Cohort end date cannot be after the programme end date (' . $programme['end_date'] . ').';
        }

        return empty($errors);
    }

    /**
     * Sanitise cohort input.
     *
     * @param array $input
     * @return array
     */
    private static function sanitise(array $input): array
    {
        return [
            'programme_id'          => (int) ($input['programme_id'] ?? 0),
            'name'                  => Sanitizer::stripTags($input['name'] ?? ''),
            'description'           => Sanitizer::stripTags($input['description'] ?? ''),
            'start_date'            => $input['start_date'] ?? '',
            'end_date'              => $input['end_date'] ?? '',
            'application_open_date' => $input['application_open_date'] ?? '',
            'application_close_date' => $input['application_close_date'] ?? '',
            'max_capacity'          => (int) ($input['max_capacity'] ?? 0),
            'location'              => Sanitizer::stripTags($input['location'] ?? ''),
            'province'              => $input['province'] ?? '',
            'delivery_mode'         => $input['delivery_mode'] ?? 'hybrid',
            'status'                => $input['status'] ?? 'draft',
        ];
    }

    /**
     * Sanitise eligibility input.
     *
     * @param array $input
     * @return array
     */
    private static function sanitiseEligibility(array $input): array
    {
        return [
            'qualification_level'     => $input['qualification_level'] ?? '',
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
    }

    /**
     * Validate eligibility input.
     *
     * @param array $clean
     * @param array $errors (by reference)
     * @return bool
     */
    private static function validateEligibility(array $clean, array &$errors): bool
    {
        if (!empty($clean['qualification_level']) && !in_array($clean['qualification_level'], self::QUALIFICATION_LEVELS, true)) {
            $errors['qualification_level'] = 'Invalid qualification level.';
        }

        // min must be <= max
        if ($clean['min_experience'] !== '' && $clean['max_experience'] !== '' && (int)$clean['min_experience'] > (int)$clean['max_experience']) {
            $errors['max_experience'] = 'Maximum experience cannot be less than minimum experience.';
        }

        if ($clean['min_completion_year'] !== '' && $clean['max_completion_year'] !== '' && (int)$clean['min_completion_year'] > (int)$clean['max_completion_year']) {
            $errors['max_completion_year'] = 'Maximum completion year cannot be before minimum completion year.';
        }

        return empty($errors);
    }

    /**
     * Validate cohort input.
     *
     * @param array      $clean
     * @param array      $errors (by reference)
     * @param bool       $isUpdate
     * @param array|null $existing
     * @return bool
     */
    private static function validate(array $clean, array &$errors, bool $isUpdate = false, ?array $existing = null): bool
    {
        $validator = new Validator();
        $validator->validate($clean, [
            'name'                  => ['required', 'max:200'],
            'description'           => ['max:5000'],
            'location'              => ['max:150'],
            'province'              => ['in:' . implode(',', ALLOWED_PROVINCES)],
            'delivery_mode'         => ['in:' . implode(',', self::DELIVERY_MODES)],
            'status'                => ['in:' . implode(',', Cohort::STATUSES)],
        ]);

        foreach (['start_date', 'end_date', 'application_open_date', 'application_close_date'] as $dateField) {
            if (!empty($clean[$dateField]) && !self::isValidDate($clean[$dateField])) {
                $errors[$dateField] = ucfirst(str_replace('_', ' ', $dateField)) . ' is invalid.';
            }
        }

        if (!empty($clean['start_date']) && !empty($clean['end_date']) && $clean['end_date'] < $clean['start_date']) {
            $errors['end_date'] = 'End date cannot be before start date.';
        }

        if (!empty($clean['application_open_date']) && !empty($clean['application_close_date'])
            && $clean['application_close_date'] < $clean['application_open_date']) {
            $errors['application_close_date'] = 'Application closing date cannot be before the opening date.';
        }

        if (!empty($clean['application_close_date']) && !empty($clean['end_date'])
            && $clean['application_close_date'] > $clean['end_date']) {
            $errors['application_close_date'] = 'Application closing date cannot be after the cohort end date.';
        }

        if ((int) $clean['max_capacity'] <= 0) {
            $errors['max_capacity'] = 'Maximum capacity must be greater than zero.';
        }

        if (!$validator->passes()) {
            $errors = array_merge($errors, $validator->errors());
            return false;
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
