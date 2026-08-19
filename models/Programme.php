<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Model
 * ================================================
 * CRUD and reporting for programmes.
 * A Programme can contain multiple cohorts.
 */

class Programme
{
    /** @var string[] Valid programme statuses in allowed transition order */
    public const STATUSES = ['draft', 'active', 'paused', 'completed', 'archived'];

    /** @var string[] Allowed status transitions: current => allowed next */
    public const TRANSITIONS = [
        'draft'     => ['active'],
        'active'    => ['paused', 'completed', 'archived'],
        'paused'    => ['active', 'completed', 'archived'],
        'completed' => ['archived'],
        'archived'  => ['active'], // authorised restore action
    ];

/**
     * All programmes with key cohort aggregates.
     *
     * @return array
     */
    public static function all(): array
    {
        return Database::fetchAll(
            "SELECT p.*,
                COUNT(DISTINCT c.id) AS cohort_count,
                COALESCE(SUM(c.max_capacity), 0) AS total_capacity
             FROM programmes p
             LEFT JOIN cohorts c ON c.programme_id = p.id
             GROUP BY p.id
             ORDER BY p.created_at DESC"
        );
    }

    /**
     * Find a single programme by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT p.*,
                COUNT(c.id) AS cohort_count,
                COALESCE(SUM(c.max_capacity), 0) AS total_capacity
             FROM programmes p
             LEFT JOIN cohorts c ON c.programme_id = p.id
             WHERE p.id = ?
             GROUP BY p.id
             LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Participants across all cohorts of a programme.
     *
     * @param int $programmeId
     * @return int  Count of selected/onboarded/active participants
     */
    public static function participantCount(int $programmeId): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(cp.id) AS cnt
             FROM cohorts c
             JOIN cohort_participants cp ON cp.cohort_id = c.id
               AND cp.status IN ('selected','onboarded','active')
             WHERE c.programme_id = ?",
            'i',
            [$programmeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Total applications across all cohorts of a programme.
     *
     * @param int $programmeId
     * @return int
     */
    public static function applicationCount(int $programmeId): int
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(applications_count), 0) AS cnt FROM cohorts WHERE programme_id = ?",
            'i',
            [$programmeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count programmes by status.
     *
     * @return array  ['draft'=>int, 'active'=>int, ...]
     */
    public static function countsByStatus(): array
    {
        $results = Database::fetchAll("SELECT status, COUNT(*) AS cnt FROM programmes GROUP BY status");
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($results as $r) {
            if (isset($counts[$r['status']])) {
                $counts[$r['status']] = (int) $r['cnt'];
            }
        }
        return $counts;
    }

    /**
     * Create a programme.
     *
     * @param int   $createdBy
     * @param array $data
     * @return int  New ID
     */
    public static function create(int $createdBy, array $data): int
    {
        Database::execute(
            "INSERT INTO programmes
             (name, type, description, objectives, duration, start_date, end_date, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'ssssssssi',
            [
                $data['name'],
                $data['type'],
                $data['description'] ?? null,
                $data['objectives'] ?? null,
                $data['duration'] ?? null,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['status'],
                $createdBy,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update a programme.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = ['name', 'type', 'description', 'objectives', 'duration', 'start_date', 'end_date', 'status'];
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
            "UPDATE programmes SET " . implode(', ', $sets) . " WHERE id = ?",
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
            "UPDATE programmes SET status = ?, updated_at = NOW() WHERE id = ?",
            'si',
            [$status, $id]
        ) >= 0;
    }

    /**
     * Soft-archive a programme.
     *
     * @param int $id
     * @return bool
     */
    public static function archive(int $id): bool
    {
        return self::setStatus($id, 'archived');
    }
}

