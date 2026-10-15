<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Model
 * ================================================
 * CRUD and reporting for cohorts. A cohort belongs
 * to exactly one programme.
 */

class Cohort
{
    /** @var string[] Valid cohort statuses in allowed transition order */
    public const STATUSES = ['draft', 'open', 'closed', 'active', 'completed', 'archived'];

    /** @var string[] Allowed status transitions: current => allowed next */
    public const TRANSITIONS = [
        'draft'     => ['open'],
        'open'      => ['closed', 'active'],
        'closed'    => ['open', 'active'],
        'active'    => ['closed', 'completed', 'archived'],
        'completed' => ['archived'],
        'archived'  => ['open'], // authorised restore action
    ];

    /** @var bool Whether the lazy schema migration has been checked this request */
    private static bool $schemaChecked = false;

    /** @var array<string,bool> Column-existence cache keyed by "table.column" */
    private static array $columnCache = [];

    /** @var array<string,bool> Foreign-key-existence cache keyed by "table.constraint" */
    private static array $foreignKeyCache = [];

    /**
     * Lazily apply the additive cohorts.supervisor_id migration
     * (database/cohort_supervisor.sql).
     *
     * The Supervisor portal and the Programme Manager "Assign Supervisor"
     * module query cohorts.supervisor_id, but databases imported before
     * this module shipped lack the column and fail with:
     *   Unknown column 'c.supervisor_id' in 'where clause'
     *
     * Invoked once per request from includes/bootstrap.php. Guaranteed
     * never to throw: a schema problem must not take down pages that do
     * not need the column.
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            if (!self::columnExists('cohorts', 'supervisor_id')) {
                Database::query(
                    "ALTER TABLE `cohorts`
                       ADD COLUMN `supervisor_id` INT UNSIGNED NULL DEFAULT NULL
                         COMMENT 'FK to users.id - cohort supervisor (roles.slug = supervisor)'
                         AFTER `created_by`,
                       ADD KEY `idx_cohorts_supervisor` (`supervisor_id`)"
                );
                error_log('[Cohort] Applied missing migration: cohorts.supervisor_id (database/cohort_supervisor.sql)');
            }

            // Best-effort referential integrity (mirrors fk_placements_supervisor).
            if (!self::foreignKeyExists('cohorts', 'fk_cohorts_supervisor')) {
                Database::query(
                    "ALTER TABLE `cohorts`
                       ADD CONSTRAINT `fk_cohorts_supervisor`
                         FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`)
                         ON UPDATE CASCADE ON DELETE SET NULL"
                );
            }
        } catch (Throwable $e) {
            // Never break the request: the column may already exist, the DB
            // user may lack ALTER privileges, or the cohorts table may not
            // be installed yet.
            error_log('[Cohort] schema migration skipped: ' . $e->getMessage());
        }
    }

    /**
     * Whether a column exists (cached per request).
     */
    private static function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, self::$columnCache)) {
            try {
                $safeTable  = preg_replace('/[^a-z0-9_]/i', '', $table);
                $safeColumn = preg_replace('/[^a-z0-9_]/i', '', $column);
                self::$columnCache[$key] = Database::fetchOne(
                    "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'"
                ) !== null;
            } catch (Throwable $e) {
                self::$columnCache[$key] = false;
            }
        }
        return self::$columnCache[$key];
    }

    /**
     * Whether a named foreign key constraint exists on a table (cached per request).
     */
    private static function foreignKeyExists(string $table, string $constraint): bool
    {
        $key = $table . '.' . $constraint;
        if (!array_key_exists($key, self::$foreignKeyCache)) {
            try {
                $safeTable      = preg_replace('/[^a-z0-9_]/i', '', $table);
                $safeConstraint = preg_replace('/[^a-z0-9_]/i', '', $constraint);
                self::$foreignKeyCache[$key] = Database::fetchOne(
                    "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE()
                       AND TABLE_NAME = '{$safeTable}'
                       AND CONSTRAINT_NAME = '{$safeConstraint}'
                     LIMIT 1"
                ) !== null;
            } catch (Throwable $e) {
                self::$foreignKeyCache[$key] = false;
            }
        }
        return self::$foreignKeyCache[$key];
    }

/**
     * Cohorts for a programme.
     *
     * @param int $programmeId
     * @return array
     */
    public static function forProgramme(int $programmeId): array
    {
        return Database::fetchAll(
            "SELECT c.*
             FROM cohorts c
             WHERE c.programme_id = ?
             ORDER BY c.created_at DESC",
            'i',
            [$programmeId]
        );
    }

    /**
     * All active cohorts across every programme, joined with their
     * parent programme name plus committed participant counts.
     *
     * @return array
     */
    public static function activeAll(): array
    {
        return Database::fetchAll(
            "SELECT c.*, p.name AS programme_name,
                    COALESCE(cp.committed, 0) AS committed
             FROM cohorts c
             JOIN programmes p ON p.id = c.programme_id
             LEFT JOIN (
                 SELECT cohort_id, COUNT(*) AS committed
                 FROM cohort_participants
                 WHERE status IN ('selected','onboarded','active')
                 GROUP BY cohort_id
             ) cp ON cp.cohort_id = c.id
             WHERE c.status = 'active'
             ORDER BY c.start_date ASC, c.name ASC"
        );
    }

    /**
     * Find a single cohort by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT c.*
             FROM cohorts c
             WHERE c.id = ?
             LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Cohort with its parent programme.
     *
     * @param int $id
     * @return array|null
     */
    public static function findWithProgramme(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT c.*, p.name AS programme_name, p.status AS programme_status,
                     p.start_date AS programme_start, p.end_date AS programme_end
             FROM cohorts c
             JOIN programmes p ON p.id = c.programme_id
             WHERE c.id = ?
             LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Participant summary counts for a cohort.
     *
     * @param int $cohortId
     * @return array  ['selected'=>int, 'onboarded'=>int, 'active'=>int, 'completed'=>int, 'withdrawn'=>int]
     */
    public static function participantCounts(int $cohortId): array
    {
        $rows = Database::fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM cohort_participants
             WHERE cohort_id = ? GROUP BY status",
            'i',
            [$cohortId]
        );
        $counts = ['selected' => 0, 'onboarded' => 0, 'active' => 0, 'completed' => 0, 'withdrawn' => 0];
        foreach ($rows as $r) {
            if (isset($counts[$r['status']])) {
                $counts[$r['status']] = (int) $r['cnt'];
            }
        }
        return $counts;
    }

    /**
     * Total committed participants (selected + onboarded + active).
     *
     * @param int $cohortId
     * @return int
     */
    public static function committedCount(int $cohortId): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM cohort_participants
             WHERE cohort_id = ? AND status IN ('selected','onboarded','active')",
            'i',
            [$cohortId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Available places = max_capacity - committed participants.
     *
     * @param int $cohortId
     * @return int
     */
    public static function availablePlaces(int $cohortId): int
    {
        $cohort = self::find($cohortId);
        if (!$cohort) {
            return 0;
        }
        $max = (int) $cohort['max_capacity'];
        $committed = self::committedCount($cohortId);
        return max(0, $max - $committed);
    }

    /**
     * Create a cohort.
     *
     * @param int   $createdBy
     * @param array $data
     * @return int  New ID
     */
    public static function create(int $createdBy, array $data): int
    {
        Database::execute(
            "INSERT INTO cohorts
             (programme_id, name, description, start_date, end_date,
              application_open_date, application_close_date, max_capacity,
              location, province, delivery_mode, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'issssssissssi',
            [
                $data['programme_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['application_open_date'] ?? null,
                $data['application_close_date'] ?? null,
                $data['max_capacity'],
                $data['location'] ?? null,
                $data['province'] ?? null,
                $data['delivery_mode'],
                $data['status'],
                $createdBy,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update a cohort.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = [
            'name', 'description', 'start_date', 'end_date', 'application_open_date',
            'application_close_date', 'max_capacity', 'location', 'province', 'delivery_mode', 'status',
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
            "UPDATE cohorts SET " . implode(', ', $sets) . " WHERE id = ?",
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
            "UPDATE cohorts SET status = ?, updated_at = NOW() WHERE id = ?",
            'si',
            [$status, $id]
        ) >= 0;
    }

    /**
     * Update the applications count for a cohort.
     *
     * @param int $cohortId
     * @param int $count
     * @return bool
     */
    public static function setApplicationsCount(int $cohortId, int $count): bool
    {
        return Database::execute(
            "UPDATE cohorts SET applications_count = ?, updated_at = NOW() WHERE id = ?",
            'ii',
            [$count, $cohortId]
        ) >= 0;
    }
}
