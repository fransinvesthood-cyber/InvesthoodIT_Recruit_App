<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Eligibility Model
 * ================================================
 * Eligibility requirements configured per cohort.
 * Stored relationally, not as hard-coded lists.
 */

class CohortEligibility
{
    /**
     * Eligibility for a cohort (one row per cohort).
     *
     * @param int $cohortId
     * @return array|null
     */
    public static function forCohort(int $cohortId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM cohort_eligibility WHERE cohort_id = ? LIMIT 1",
            'i',
            [$cohortId]
        );
    }

    /**
     * Inherit eligibility from the parent programme.
     *
     * @param int $cohortId
     * @param int $programmeId
     * @return bool
     */
    public static function inheritFromProgramme(int $cohortId, int $programmeId): bool
    {
        $prog = Database::fetchOne(
            "SELECT * FROM programme_eligibility WHERE programme_id = ? LIMIT 1",
            'i',
            [$programmeId]
        );
        if (!$prog) {
            return false;
        }

        unset($prog['id'], $prog['programme_id'], $prog['created_at'], $prog['updated_at']);
        $prog['cohort_id'] = $cohortId;

        return self::upsert($cohortId, $prog);
    }

    /**
     * Insert or update eligibility for a cohort.
     *
     * @param int   $cohortId
     * @param array $data
     * @return bool
     */
    public static function upsert(int $cohortId, array $data): bool
    {
        $existing = self::forCohort($cohortId);
        if ($existing) {
            return self::update($existing['id'], $data);
        }
        return self::create($cohortId, $data);
    }

    /**
     * Create eligibility for a cohort.
     *
     * @param int   $cohortId
     * @param array $data
     * @return int  New ID
     */
    public static function create(int $cohortId, array $data): int
    {
        Database::execute(
            "INSERT INTO cohort_eligibility
             (cohort_id, qualification_level, qualification_name, field_of_study,
              institution_requirements, min_completion_year, max_completion_year,
              min_experience, max_experience, province, city, location_restrictions,
              availability, citizenship_residency, programme_specific)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'issssisssssssss',
            [
                $cohortId,
                $data['qualification_level'] ?? null,
                $data['qualification_name'] ?? null,
                $data['field_of_study'] ?? null,
                $data['institution_requirements'] ?? null,
thisYear($data['min_completion_year']),
                thisYear($data['max_completion_year']),
                thisInt($data['min_experience']),
                thisInt($data['max_experience']),
                $data['province'] ?? null,
                $data['city'] ?? null,
                $data['location_restrictions'] ?? null,
                $data['availability'] ?? null,
                $data['citizenship_residency'] ?? null,
                $data['programme_specific'] ?? null,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update eligibility for a cohort.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = [
            'qualification_level', 'qualification_name', 'field_of_study',
            'institution_requirements', 'min_completion_year', 'max_completion_year',
            'min_experience', 'max_experience', 'province', 'city',
            'location_restrictions', 'availability', 'citizenship_residency', 'programme_specific',
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
        $params[] = $id;

        return Database::execute(
            "UPDATE cohort_eligibility SET " . implode(', ', $sets) . " WHERE id = ?",
            $types,
            $params
        ) >= 0;
    }
}

/**
 * Local helper: return int or null for a numeric value.
 */
if (!function_exists('thisInt')) {
    function thisInt($value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}

/**
 * Local helper: return a year value or null.
 */
if (!function_exists('thisYear')) {
    function thisYear($value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}
