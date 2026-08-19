<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Eligibility Model
 * ================================================
 * Eligibility requirements configured at programme level.
 * Cohorts inherit and may override these.
 */

class ProgrammeEligibility
{
    /**
     * Eligibility for a programme (one row per programme).
     *
     * @param int $programmeId
     * @return array|null
     */
    public static function forProgramme(int $programmeId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM programme_eligibility WHERE programme_id = ? LIMIT 1",
            'i',
            [$programmeId]
        );
    }

    /**
     * Insert or update eligibility for a programme.
     *
     * @param int   $programmeId
     * @param array $data
     * @return bool
     */
    public static function upsert(int $programmeId, array $data): bool
    {
        $existing = self::forProgramme($programmeId);
        if ($existing) {
            return self::update($existing['id'], $data);
        }
        return self::create($programmeId, $data);
    }

    /**
     * Create eligibility for a programme.
     *
     * @param int   $programmeId
     * @param array $data
     * @return int  New ID
     */
    public static function create(int $programmeId, array $data): int
    {
        Database::execute(
            "INSERT INTO programme_eligibility
             (programme_id, qualification_level, qualification_name, field_of_study,
              institution_requirements, min_completion_year, max_completion_year,
              min_experience, max_experience, province, city, location_restrictions,
availability, citizenship_residency, programme_specific)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'issssiiiissssss',
            [
                $programmeId,
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
     * Update eligibility for a programme.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
public static function update(int $id, array $data): bool
    {
        // Define which columns are integer/YEAR (bound as 'i') vs string (bound as 's')
        $intCols = [
            'min_completion_year', 'max_completion_year',
            'min_experience', 'max_experience',
        ];
        $stringCols = [
            'qualification_level', 'qualification_name', 'field_of_study',
            'institution_requirements', 'province', 'city',
            'location_restrictions', 'availability', 'citizenship_residency', 'programme_specific',
        ];

        $sets = [];
        $types = '';
        $params = [];

        foreach ($stringCols as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "`{$col}` = ?";
                $types .= 's';
                $params[] = ($data[$col] === '' || $data[$col] === null) ? null : (string) $data[$col];
            }
        }

        foreach ($intCols as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "`{$col}` = ?";
                $types .= 'i';
                $params[] = thisInt($data[$col]);
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "`updated_at` = NOW()";
        $types .= 'i';
        $params[] = $id;

        return Database::execute(
            "UPDATE programme_eligibility SET " . implode(', ', $sets) . " WHERE id = ?",
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
