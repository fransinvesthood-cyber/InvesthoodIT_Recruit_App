<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Controller
 * ================================================
 * Business logic, validation, status transitions and
 * audit logging for opportunities.
 */

class OpportunityController
{
    /** @var string[] Allowed opportunity types */
    private const TYPES = [
        'graduate_programme', 'internship', 'learnership', 'wil', 'skills_development', 'mentorship', 'other',
    ];

    /** @var string[] Allowed work arrangements */
    private const WORK_ARRANGEMENTS = ['on_site', 'remote', 'hybrid'];

    /**
     * Create an opportunity (with skills, eligibility, documents, responsibilities).
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors
     * @return int|null
     */
    public static function create(int $userId, array $input, array &$errors): ?int
    {
        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors)) {
            return null;
        }

        $id = Opportunity::create($userId, $clean);

        // Save related configuration
        self::saveConfig($id, $input);

        AuditLog::log($userId, 'Opportunity Created', 'opportunity', $id, 'Opportunity created');
        return $id;
    }

    /**
     * Update an opportunity.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors
     * @return bool
     */
    public static function update(int $userId, int $id, array $input, array &$errors): bool
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return false;
        }

        $clean = self::sanitise($input);
        if (!self::validate($clean, $errors, true)) {
            return false;
        }

        // Status transition validation
        $newStatus = $clean['status'];
        if ($newStatus !== $opportunity['status']) {
            if (!self::canTransition($opportunity['status'], $newStatus, $errors)) {
                return false;
            }
        }

        // Capacity cannot be reduced below selected candidates
        $selected = Opportunity::selectedCount($id);
        if ((int) $clean['available_positions'] < $selected) {
            $errors['available_positions'] = "Capacity cannot be reduced below {$selected} selected candidate(s).";
            return false;
        }

        Opportunity::update($id, $clean);
        self::saveConfig($id, $input);

        AuditLog::log($userId, 'Opportunity Updated', 'opportunity', $id, 'Opportunity details updated');
        return true;
    }

    /**
     * Publish an opportunity (validated transition).
     *
     * @param int   $userId
     * @param int   $id
     * @param array $errors
     * @return bool
     */
    public static function publish(int $userId, int $id, array &$errors): bool
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return false;
        }
        if (!self::canTransition($opportunity['status'], 'published', $errors)) {
            return false;
        }
        Opportunity::setStatus($id, 'published');
        AuditLog::log($userId, 'Opportunity Published', 'opportunity', $id, 'Opportunity published');
        return true;
    }

    /**
     * Close an opportunity (validated transition).
     *
     * @param int   $userId
     * @param int   $id
     * @param array $errors
     * @return bool
     */
    public static function close(int $userId, int $id, array &$errors): bool
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return false;
        }
        if (!self::canTransition($opportunity['status'], 'closed', $errors)) {
            return false;
        }
        Opportunity::setStatus($id, 'closed');
        AuditLog::log($userId, 'Opportunity Closed', 'opportunity', $id, 'Opportunity closed');
        return true;
    }

    /**
     * Archive an opportunity (validated transition).
     *
     * @param int   $userId
     * @param int   $id
     * @param array $errors
     * @return bool
     */
    public static function archive(int $userId, int $id, array &$errors): bool
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return false;
        }
        if (!self::canTransition($opportunity['status'], 'archived', $errors)) {
            return false;
        }
        Opportunity::setStatus($id, 'archived');
        AuditLog::log($userId, 'Opportunity Archived', 'opportunity', $id, 'Opportunity archived');
        return true;
    }

    /**
     * Duplicate an opportunity (with skills, eligibility, documents,
     * responsibilities) into a new Draft. Applications/participants are NOT copied.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $errors
     * @return int|null
     */
    public static function duplicate(int $userId, int $id, array &$errors): ?int
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return null;
        }

        $newId = Opportunity::duplicate($userId, $opportunity);

        // Copy skills
        $skills = OpportunitySkill::forOpportunity($id);
        if (!empty($skills)) {
            $skillData = [];
            foreach ($skills as $s) {
                $skillData[] = ['name' => $s['skill_name'], 'category' => $s['skill_category']];
            }
            OpportunitySkill::replaceForOpportunity($newId, $skillData);
        }

        // Copy eligibility
        $elig = OpportunityEligibility::forOpportunity($id);
        if ($elig) {
            OpportunityEligibility::upsert($newId, [
                'qualification_requirements' => $elig['qualification_requirements'],
                'required_skills'            => $elig['required_skills'],
                'preferred_skills'           => $elig['preferred_skills'],
                'min_experience'             => $elig['min_experience'],
                'availability_requirements'  => $elig['availability_requirements'],
                'other_requirements'         => $elig['other_requirements'],
            ]);
        }

        // Copy documents
        $docs = OpportunityDocument::forOpportunity($id);
        if (!empty($docs)) {
            $docData = [];
            foreach ($docs as $d) {
                $docData[] = ['document_name' => $d['document_name'], 'is_required' => $d['is_required']];
            }
            OpportunityDocument::replaceForOpportunity($newId, $docData);
        }

        // Copy responsibilities
        $responsibilities = OpportunityResponsibility::forOpportunity($id);
        $respData = [];
        foreach ($responsibilities as $type => $items) {
            $lines = [];
            foreach ($items as $item) {
                $lines[] = $item['content'];
            }
            $respData[$type] = implode("\n", $lines);
        }
        OpportunityResponsibility::replaceForOpportunity($newId, $respData);

        AuditLog::log($userId, 'Opportunity Duplicated', 'opportunity', $newId, 'Duplicated from opportunity #' . $id);
        return $newId;
    }

    /**
     * Update opportunity eligibility.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors
     * @return bool
     */
    public static function updateEligibility(int $userId, int $id, array $input, array &$errors): bool
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return false;
        }
        $clean = [
            'qualification_requirements' => Sanitizer::stripTags($input['qualification_requirements'] ?? ''),
            'required_skills'            => Sanitizer::stripTags($input['required_skills'] ?? ''),
            'preferred_skills'           => Sanitizer::stripTags($input['preferred_skills'] ?? ''),
            'min_experience'             => Sanitizer::stripTags($input['min_experience'] ?? ''),
            'availability_requirements'  => Sanitizer::stripTags($input['availability_requirements'] ?? ''),
            'other_requirements'         => Sanitizer::stripTags($input['other_requirements'] ?? ''),
        ];
        OpportunityEligibility::upsert($id, $clean);
        AuditLog::log($userId, 'Eligibility Updated', 'opportunity', $id, 'Opportunity eligibility requirements updated');
        return true;
    }

    /**
     * Update opportunity skills.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors
     * @return bool
     */
    public static function updateSkills(int $userId, int $id, array $input, array &$errors): bool
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return false;
        }
        $skills = [];
        foreach (OpportunitySkill::CATEGORIES as $cat) {
            foreach (($input['skills'][$cat] ?? []) as $name) {
                $n = Sanitizer::stripTags($name);
                if (trim($n) !== '') {
                    $skills[] = ['name' => $n, 'category' => $cat];
                }
            }
        }
        OpportunitySkill::replaceForOpportunity($id, $skills);
        AuditLog::log($userId, 'Requirements Updated', 'opportunity', $id, 'Opportunity skill requirements updated');
        return true;
    }

    /**
     * Update opportunity documents.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors
     * @return bool
     */
    public static function updateDocuments(int $userId, int $id, array $input, array &$errors): bool
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return false;
        }
        $names = $input['document_name'] ?? [];
        $requiredFlags = $input['document_required'] ?? [];
        $docs = [];
        foreach ($names as $idx => $name) {
            $n = Sanitizer::stripTags($name);
            if (trim($n) === '') {
                continue;
            }
            $docs[] = [
                'document_name' => $n,
                'is_required'   => isset($requiredFlags[$idx]) && (int) $requiredFlags[$idx] === 1 ? 1 : 0,
            ];
        }
        OpportunityDocument::replaceForOpportunity($id, $docs);
        AuditLog::log($userId, 'Requirements Updated', 'opportunity', $id, 'Opportunity document requirements updated');
        return true;
    }

    /**
     * Update opportunity responsibilities.
     *
     * @param int   $userId
     * @param int   $id
     * @param array $input
     * @param array $errors
     * @return bool
     */
    public static function updateResponsibilities(int $userId, int $id, array $input, array &$errors): bool
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity) {
            $errors['general'] = 'Opportunity not found.';
            return false;
        }
        $data = [];
        foreach (OpportunityResponsibility::TYPES as $type) {
            $data[$type] = Sanitizer::stripTags($input[$type] ?? '');
        }
        OpportunityResponsibility::replaceForOpportunity($id, $data);
        AuditLog::log($userId, 'Requirements Updated', 'opportunity', $id, 'Opportunity responsibilities updated');
        return true;
    }

    /**
     * Validate a role-based status transition.
     *
     * @param string $current
     * @param string $next
     * @param array  $errors
     * @return bool
     */
    public static function canTransition(string $current, string $next, array &$errors): bool
    {
        $allowed = Opportunity::TRANSITIONS[$current] ?? [];
        if (!in_array($next, $allowed, true)) {
            $errors['status'] = "Cannot move opportunity from '{$current}' to '{$next}'.";
            return false;
        }
        return true;
    }

    /**
     * Save related configuration (skills, eligibility, documents, responsibilities)
     * from the create/update form.
     *
     * @param int   $id
     * @param array $input
     * @return void
     */
    private static function saveConfig(int $id, array $input): void
    {
        // Skills
        $skills = [];
        foreach (OpportunitySkill::CATEGORIES as $cat) {
            foreach (($input['skills'][$cat] ?? []) as $name) {
                $n = Sanitizer::stripTags($name);
                if (trim($n) !== '') {
                    $skills[] = ['name' => $n, 'category' => $cat];
                }
            }
        }
        OpportunitySkill::replaceForOpportunity($id, $skills);

        // Eligibility
        OpportunityEligibility::upsert($id, [
            'qualification_requirements' => Sanitizer::stripTags($input['qualification_requirements'] ?? ''),
            'required_skills'            => Sanitizer::stripTags($input['required_skills'] ?? ''),
            'preferred_skills'           => Sanitizer::stripTags($input['preferred_skills'] ?? ''),
            'min_experience'             => Sanitizer::stripTags($input['min_experience'] ?? ''),
            'availability_requirements'  => Sanitizer::stripTags($input['availability_requirements'] ?? ''),
            'other_requirements'         => Sanitizer::stripTags($input['other_requirements'] ?? ''),
        ]);

        // Documents
        $names = $input['document_name'] ?? [];
        $requiredFlags = $input['document_required'] ?? [];
        $docs = [];
        foreach ($names as $idx => $name) {
            $n = Sanitizer::stripTags($name);
            if (trim($n) === '') {
                continue;
            }
            $docs[] = [
                'document_name' => $n,
                'is_required'   => isset($requiredFlags[$idx]) && (int) $requiredFlags[$idx] === 1 ? 1 : 0,
            ];
        }
        OpportunityDocument::replaceForOpportunity($id, $docs);

        // Responsibilities
        $respData = [];
        foreach (OpportunityResponsibility::TYPES as $type) {
            $respData[$type] = Sanitizer::stripTags($input[$type] ?? '');
        }
        OpportunityResponsibility::replaceForOpportunity($id, $respData);
    }

    /**
     * Sanitise opportunity input.
     *
     * @param array $input
     * @return array
     */
    private static function sanitise(array $input): array
    {
        return [
            'title'                 => Sanitizer::stripTags($input['title'] ?? ''),
            'type'                  => $input['type'] ?? 'other',
            'programme_id'          => (int) ($input['programme_id'] ?? 0),
            'cohort_id'             => (int) ($input['cohort_id'] ?? 0) ?: null,
            'organisation'          => Sanitizer::stripTags($input['organisation'] ?? ''),
            'short_description'     => Sanitizer::stripTags($input['short_description'] ?? ''),
            'full_description'      => Sanitizer::stripTags($input['full_description'] ?? ''),
            'application_open_date' => $input['application_open_date'] ?? '',
            'application_close_date'=> $input['application_close_date'] ?? '',
            'start_date'            => $input['start_date'] ?? '',
            'end_date'              => $input['end_date'] ?? '',
            'available_positions'   => (int) ($input['available_positions'] ?? 0),
            'min_age'               => $input['min_age'] !== '' ? (int) $input['min_age'] : null,
            'max_age'               => $input['max_age'] !== '' ? (int) $input['max_age'] : null,
            'province'              => Sanitizer::stripTags($input['province'] ?? ''),
            'city'                  => Sanitizer::stripTags($input['city'] ?? ''),
            'physical_location'     => Sanitizer::stripTags($input['physical_location'] ?? ''),
            'work_arrangement'      => $input['work_arrangement'] ?? 'hybrid',
            'status'                => $input['status'] ?? 'draft',
        ];
    }

    /**
     * Validate opportunity input.
     *
     * @param array $clean
     * @param array $errors
     * @param bool  $isUpdate
     * @return bool
     */
    private static function validate(array $clean, array &$errors, bool $isUpdate = false): bool
    {
        $validator = new Validator();
        $validator->validate($clean, [
            'title'             => ['required', 'max:200'],
            'type'              => ['in:' . implode(',', self::TYPES)],
            'short_description' => ['required', 'max:500'],
            'full_description'  => ['max:10000'],
            'work_arrangement'  => ['in:' . implode(',', self::WORK_ARRANGEMENTS)],
            'status'            => ['in:' . implode(',', Opportunity::STATUSES)],
        ]);
        if (!$validator->passes()) {
            $errors = array_merge($errors, $validator->errors());
        }

        // Programme must exist
        if ($clean['programme_id'] <= 0 || !Programme::find($clean['programme_id'])) {
            $errors['programme_id'] = 'Please select a valid programme.';
        }

        // If a cohort is selected, it must exist and belong to the selected programme
        if (!empty($clean['cohort_id'])) {
            $cohort = Cohort::find((int) $clean['cohort_id']);
            if (!$cohort) {
                $errors['cohort_id'] = 'Please select a valid cohort.';
            } elseif ((int) $cohort['programme_id'] !== (int) $clean['programme_id']) {
                $errors['cohort_id'] = 'The selected cohort does not belong to the selected programme.';
            }
        }

        // Capacity
        if ($clean['available_positions'] <= 0) {
            $errors['available_positions'] = 'Available positions must be at least 1.';
        }

        // Date validation
        $requiredDates = ['application_open_date', 'application_close_date'];
        foreach ($requiredDates as $d) {
            if (empty($clean[$d])) {
                $errors[$d] = 'This date is required.';
            } elseif (!self::isValidDate($clean[$d])) {
                $errors[$d] = 'This date is invalid.';
            }
        }
        if (!empty($clean['start_date']) && !self::isValidDate($clean['start_date'])) {
            $errors['start_date'] = 'Start date is invalid.';
        }
        if (!empty($clean['end_date']) && !self::isValidDate($clean['end_date'])) {
            $errors['end_date'] = 'End date is invalid.';
        }

        // Closing cannot be before opening
        if (!empty($clean['application_open_date']) && !empty($clean['application_close_date'])
            && $clean['application_close_date'] < $clean['application_open_date']) {
            $errors['application_close_date'] = 'Application closing date cannot be before the opening date.';
        }

        // Programme start cannot be after programme end
        if (!empty($clean['start_date']) && !empty($clean['end_date'])
            && $clean['end_date'] < $clean['start_date']) {
            $errors['end_date'] = 'Programme end date cannot be before start date.';
        }

        // Where a cohort is selected, application close cannot exceed the cohort's applicable end date
        if (!empty($clean['cohort_id'])) {
            $cohort = Cohort::find((int) $clean['cohort_id']);
            if ($cohort && !empty($cohort['application_close_date'])) {
                if (!empty($clean['application_close_date'])
                    && $clean['application_close_date'] > $cohort['application_close_date']) {
                    $errors['application_close_date'] = 'Application closing date cannot be after the cohort\'s application close date.';
                }
            }
        }

        // Age range
        if ($clean['min_age'] !== null && $clean['max_age'] !== null && $clean['max_age'] < $clean['min_age']) {
            $errors['max_age'] = 'Maximum age cannot be less than minimum age.';
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
