<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Model
 * ================================================
 * CRUD and reporting for opportunities. An opportunity
 * belongs to a programme and optionally a cohort.
 */

class Opportunity
{
    /** @var string[] Valid opportunity statuses */
    public const STATUSES = ['draft', 'published', 'closing_soon', 'closed', 'archived'];

    /** @var string[] Allowed status transitions: current => allowed next */
    public const TRANSITIONS = [
        'draft'        => ['published', 'archived', 'closed'],
        'published'    => ['closing_soon', 'closed', 'archived'],
        'closing_soon' => ['closed', 'published', 'archived'],
        'closed'       => ['archived', 'published'],
        'archived'     => ['published'], // authorised restore action
    ];

    /**
     * All opportunities joined with their programme and cohort.
     *
     * @return array
     */
    public static function all(): array
    {
        return Database::fetchAll(
            "SELECT o.*,
                    p.name AS programme_name, p.type AS programme_type, p.status AS programme_status,
                    p.start_date AS programme_start, p.end_date AS programme_end,
                    c.name AS cohort_name, c.status AS cohort_status,
                    c.start_date AS cohort_start, c.end_date AS cohort_end
             FROM opportunities o
             LEFT JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             ORDER BY o.created_at DESC"
        );
    }

    /**
     * Find a single opportunity with programme/cohort context.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT o.*,
                    p.name AS programme_name, p.type AS programme_type, p.status AS programme_status,
                    p.start_date AS programme_start, p.end_date AS programme_end,
                    c.name AS cohort_name, c.status AS cohort_status,
                    c.start_date AS cohort_start, c.end_date AS cohort_end
             FROM opportunities o
             LEFT JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             WHERE o.id = ?
             LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Count opportunities by status.
     *
     * @return array
     */
    public static function countsByStatus(): array
    {
        $results = Database::fetchAll("SELECT status, COUNT(*) AS cnt FROM opportunities GROUP BY status");
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($results as $r) {
            if (isset($counts[$r['status']])) {
                $counts[$r['status']] = (int) $r['cnt'];
            }
        }
        return $counts;
    }

    /**
     * Aggregate positions / applications statistics.
     *
     * @return array ['total_positions'=>int, 'total_applications'=>int, 'avg_applications'=>float]
     */
    public static function aggregateStats(): array
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(available_positions),0) AS total_positions,
                    COALESCE(SUM(applications_count),0) AS total_applications,
                    COUNT(*) AS total_opps
             FROM opportunities"
        );
        $totalPositions = (int) ($row['total_positions'] ?? 0);
        $totalApplications = (int) ($row['total_applications'] ?? 0);
        $totalOpps = (int) ($row['total_opps'] ?? 0);
        return [
            'total_positions'   => $totalPositions,
            'total_applications' => $totalApplications,
            'avg_applications'  => $totalOpps > 0 ? round($totalApplications / $totalOpps, 1) : 0,
        ];
    }

    /**
     * Application statistics for an opportunity.
     * Zeroed until the Application module is implemented.
     *
     * @param int $id
     * @return array
     */
    public static function applicationStats(int $id): array
    {
        $stats = [
            'applications' => 0,
            'under_review' => 0,
            'shortlisted'  => 0,
            'interview'    => 0,
            'selected'     => 0,
            'rejected'     => 0,
        ];
        // Future: join opportunity_applications table here.
        return $stats;
    }

    /**
     * Number of selected/onboarded candidates for an opportunity.
     * Placeholder until the Application module is built.
     *
     * @param int $id
     * @return int
     */
    public static function selectedCount(int $id): int
    {
        return 0;
    }

    /**
     * Create an opportunity.
     *
     * @param int   $createdBy
     * @param array $data
     * @return int  New ID
     */
    public static function create(int $createdBy, array $data): int
    {
        Database::execute(
            "INSERT INTO opportunities
             (programme_id, cohort_id, title, type, organisation, short_description, full_description,
              application_open_date, application_close_date, start_date, end_date,
              available_positions, min_age, max_age,
              province, city, physical_location, work_arrangement,
              status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'iisssssssssiiisssssi',
            [
                $data['programme_id'],
                $data['cohort_id'] ?? null,
                $data['title'],
                $data['type'],
                $data['organisation'] ?? null,
                $data['short_description'] ?? null,
                $data['full_description'] ?? null,
                $data['application_open_date'] ?? null,
                $data['application_close_date'] ?? null,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['available_positions'],
                $data['min_age'] ?? null,
                $data['max_age'] ?? null,
                $data['province'] ?? null,
                $data['city'] ?? null,
                $data['physical_location'] ?? null,
                $data['work_arrangement'],
                $data['status'],
                $createdBy,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update an opportunity.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $stringCols = [
            'title', 'type', 'organisation', 'short_description', 'full_description',
            'application_open_date', 'application_close_date', 'start_date', 'end_date',
            'province', 'city', 'physical_location', 'work_arrangement', 'status',
        ];
        $intCols = ['programme_id', 'cohort_id', 'available_positions', 'min_age', 'max_age'];

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
                $params[] = ($data[$col] === '' || $data[$col] === null) ? null : (int) $data[$col];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "`updated_at` = NOW()";
        $types .= 'i';
        $params[] = $id;

        return Database::execute(
            "UPDATE opportunities SET " . implode(', ', $sets) . " WHERE id = ?",
            $types,
            $params
        ) >= 0;
    }

    /**
     * Update only the status (validated transitions handled by controller).
     *
     * @param int    $id
     * @param string $status
     * @return bool
     */
    public static function setStatus(int $id, string $status): bool
    {
        return Database::execute(
            "UPDATE opportunities SET status = ?, updated_at = NOW() WHERE id = ?",
            'si',
            [$status, $id]
        ) >= 0;
    }

    /**
     * Update the applications count for an opportunity.
     *
     * @param int $id
     * @param int $count
     * @return bool
     */
    public static function setApplicationsCount(int $id, int $count): bool
    {
        return Database::execute(
            "UPDATE opportunities SET applications_count = ?, updated_at = NOW() WHERE id = ?",
            'ii',
            [$count, $id]
        ) >= 0;
    }

    /**
     * Duplicate an opportunity (with skills, eligibility, documents,
     * responsibilities) into a new Draft. Applications/participants
     * are NOT copied.
     *
     * @param int   $createdBy
     * @param array $o
     * @return int  New ID
     */
    public static function duplicate(int $createdBy, array $o): int
    {
        $data = [
            'programme_id'          => $o['programme_id'],
            'cohort_id'             => $o['cohort_id'] ?? null,
            'title'                 => $o['title'] . ' (Copy)',
            'type'                  => $o['type'],
            'organisation'          => $o['organisation'],
            'short_description'     => $o['short_description'],
            'full_description'      => $o['full_description'],
            'application_open_date' => $o['application_open_date'],
            'application_close_date' => $o['application_close_date'],
            'start_date'            => $o['start_date'],
            'end_date'              => $o['end_date'],
            'available_positions'   => $o['available_positions'],
            'min_age'               => $o['min_age'],
            'max_age'               => $o['max_age'],
            'province'              => $o['province'],
            'city'                  => $o['city'],
            'physical_location'     => $o['physical_location'],
            'work_arrangement'      => $o['work_arrangement'],
            'status'                => 'draft',
        ];
        return self::create($createdBy, $data);
    }
}
