<?php

class LearningProgramme
{
    // --------------------------------------------------------
    // Status constants
    // --------------------------------------------------------
    public const STATUS_ASSIGNED   = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_OVERDUE    = 'overdue';

    public const ALL_STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_OVERDUE,
    ];

    public const LESSON_TYPES = ['pdf', 'video', 'link', 'document', 'lesson', 'quiz'];

    // --------------------------------------------------------
    // SCHEMA (additive, idempotent)
    // --------------------------------------------------------

    /**
     * Create the learning tables when they are missing.
     * Safe to call on every request: the check is cached per request.
     *
     * Tables created:
     *  learning_programmes        - course/programme catalogue
     *  course_lessons             - lessons & learning resources per programme
     *  candidate_learning_assignments - programmes assigned to a candidate
     *  candidate_lesson_progress  - per-lesson completion tracking
     *  programme_skills           - skills targeted/gained by a programme
     *
     * Also adds an additive `source` column to candidate_skills so skills
     * gained through completed training can be distinguished from
     * self-reported skills (existing column is untouched).
     */
    public static function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $checked = true;

        $tables = Database::fetchAll("SHOW TABLES");
        $have = [];
        foreach ($tables as $row) {
            $have[] = reset($row);
        }

        // --- learning_programmes ------------------------------------------------
        if (!in_array('learning_programmes', $have, true)) {
            Database::query(
                'CREATE TABLE `learning_programmes` (
                  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `title`          VARCHAR(160) NOT NULL,
                  `slug`           VARCHAR(180) NOT NULL DEFAULT \'\',
                  `description`    TEXT,
                  `objectives`     TEXT,
                  `category`       VARCHAR(60) NOT NULL DEFAULT \'general\',
                  `provider`       VARCHAR(160) DEFAULT NULL,
                  `duration_hours` INT UNSIGNED DEFAULT 0,
                  `status`         ENUM(\'active\',\'archived\') NOT NULL DEFAULT \'active\',
                  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_learning_programmes_slug` (`slug`),
                  KEY `idx_learning_programmes_category_status` (`category`, `status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        // --- course_lessons -----------------------------------------------------
        if (!in_array('course_lessons', $have, true)) {
            Database::query(
                'CREATE TABLE `course_lessons` (
                  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `programme_id`  INT UNSIGNED NOT NULL,
                  `title`         VARCHAR(160) NOT NULL,
                  `type`          ENUM(\'pdf\',\'video\',\'link\',\'document\',\'lesson\',\'quiz\') NOT NULL DEFAULT \'lesson\',
                  `content_url`   VARCHAR(500) DEFAULT NULL,
                  `content_text`  TEXT,
                  `duration_min`  INT UNSIGNED DEFAULT 0,
                  `lesson_order`  INT UNSIGNED DEFAULT 0,
                  `is_required`   TINYINT(1) NOT NULL DEFAULT 1,
                  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_course_lessons_programme` (`programme_id`, `lesson_order`),
                  CONSTRAINT `fk_course_lessons_programme`
                    FOREIGN KEY (`programme_id`) REFERENCES `learning_programmes` (`id`)
                    ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        // --- candidate_learning_assignments (candidate-scoped) --------------------
        if (!in_array('candidate_learning_assignments', $have, true)) {
            Database::query(
                'CREATE TABLE `candidate_learning_assignments` (
                  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `programme_id` INT UNSIGNED NOT NULL,
                  `candidate_id` INT UNSIGNED NOT NULL,
                  `assigned_by`  INT UNSIGNED NULL,
                  `assigned_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `start_date`   DATE DEFAULT NULL,
                  `due_date`     DATE DEFAULT NULL,
                  `status`       ENUM(\'assigned\',\'in_progress\',\'completed\',\'overdue\') NOT NULL DEFAULT \'assigned\',
                  `started_at`   DATETIME NULL,
                  `completed_at` DATETIME NULL,
                  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_assign_programme_candidate` (`programme_id`, `candidate_id`),
                  KEY `idx_assign_candidate` (`candidate_id`, `status`),
                  CONSTRAINT `fk_cla_programme` FOREIGN KEY (`programme_id`) REFERENCES `learning_programmes` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `fk_cla_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        // --- candidate_lesson_progress (candidate-scoped) -------------------------
        if (!in_array('candidate_lesson_progress', $have, true)) {
            Database::query(
                'CREATE TABLE `candidate_lesson_progress` (
                  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `assignment_id` INT UNSIGNED NOT NULL,
                  `lesson_id`     INT UNSIGNED NOT NULL,
                  `candidate_id`  INT UNSIGNED NOT NULL,
                  `completed`     TINYINT(1) NOT NULL DEFAULT 0,
                  `completed_at`  DATETIME NULL,
                  `updated_at`    DATETIME NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_progress_assignment_lesson` (`assignment_id`, `lesson_id`),
                  KEY `idx_progress_assignment` (`assignment_id`, `completed`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        // --- programme_skills (programme curriculum) ------------------------------
        if (!in_array('programme_skills', $have, true)) {
            Database::query(
                'CREATE TABLE `programme_skills` (
                  `programme_id` INT UNSIGNED NOT NULL,
                  `skill_id`     INT UNSIGNED NOT NULL,
                  PRIMARY KEY (`programme_id`, `skill_id`),
                  CONSTRAINT `fk_ps_programme` FOREIGN KEY (`programme_id`) REFERENCES `learning_programmes` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        // --- candidate_skills.source (additive; table owned by the Skill model) --
        try {
            if (Database::fetchOne("SHOW TABLES LIKE 'candidate_skills'") !== null) {
                $skillCols = Database::fetchAll("SHOW COLUMNS FROM `candidate_skills`");
                $skillColNames = is_array($skillCols) ? array_column($skillCols, 'Field') : [];
                if (!in_array('source', $skillColNames, true)) {
                    Database::query(
                        "ALTER TABLE `candidate_skills` ADD COLUMN `source` VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER `verification_status`"
                    );
                }
            }
        } catch (Throwable $e) {
            error_log('[LearningProgramme] ensureSchema candidate_skills.source: ' . $e->getMessage());
        }
    }

    /**
     * Return the column names of a table, or null if the table does not exist.
     */
    private static function tableColumns(string $table): ?array
    {
        $row = Database::fetchOne("SHOW TABLES LIKE ?", 's', [$table]);
        if (!$row) {
            return null;
        }
        $cols = Database::fetchAll("SHOW COLUMNS FROM `$table`");
        $out = [];
        foreach ($cols as $c) {
            $out[] = $c['Field'];
        }
        return $out;
    }

    // --------------------------------------------------------
    // PROGRAMMES (catalogue)
    // --------------------------------------------------------

    /**
     * All catalogue records for administrators, including archived programmes.
     */
    public static function allForAdmin(array $filters = []): array
    {
        self::ensureSchema();
        $where = ['1 = 1'];
        $types = '';
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? 'all'));
        if ($search !== '') {
            $where[] = '(lp.title LIKE ? OR lp.category LIKE ? OR lp.provider LIKE ?)';
            $types .= 'sss';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        if (in_array($status, ['active', 'archived'], true)) {
            $where[] = 'lp.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        return Database::fetchAll(
            'SELECT lp.*, COUNT(DISTINCT cla.id) AS assignment_count, COUNT(DISTINCT cl.id) AS lesson_count
             FROM learning_programmes lp
             LEFT JOIN candidate_learning_assignments cla ON cla.programme_id = lp.id
             LEFT JOIN course_lessons cl ON cl.programme_id = lp.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY lp.id ORDER BY lp.updated_at DESC, lp.id DESC',
            $types,
            $params
        );
    }

    /**
     * All assignments for administrators, with candidate identity and progress.
     */
    public static function assignmentsForAdmin(array $filters = []): array
    {
        self::ensureSchema();
        $where = ['1 = 1'];
        $types = '';
        $params = [];
        $status = trim((string) ($filters['status'] ?? 'all'));
        $programmeId = (int) ($filters['programme_id'] ?? 0);
        if (in_array($status, self::ALL_STATUSES, true)) {
            $where[] = 'cla.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($programmeId > 0) {
            $where[] = 'cla.programme_id = ?';
            $types .= 'i';
            $params[] = $programmeId;
        }
        $rows = Database::fetchAll(
            'SELECT cla.id AS assignment_id, cla.programme_id, cla.candidate_id,
                    cla.assigned_at, cla.due_date, cla.status, cla.started_at, cla.completed_at,
                    lp.title, lp.category, u.first_name, u.last_name, u.email,
                    COUNT(DISTINCT cl.id) AS total_lessons,
                    COUNT(DISTINCT CASE WHEN cl.is_required = 1 THEN cl.id END) AS required_lessons,
                    COUNT(DISTINCT CASE WHEN cp.completed = 1 THEN cl.id END) AS completed_lessons
             FROM candidate_learning_assignments cla
             INNER JOIN learning_programmes lp ON lp.id = cla.programme_id
             INNER JOIN users u ON u.id = cla.candidate_id
             LEFT JOIN course_lessons cl ON cl.programme_id = lp.id
             LEFT JOIN candidate_lesson_progress cp ON cp.assignment_id = cla.id
                 AND cp.lesson_id = cl.id AND cp.completed = 1
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY cla.id
             ORDER BY cla.due_date IS NULL, cla.due_date ASC, cla.assigned_at DESC
             LIMIT 250',
            $types,
            $params
        );
        $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
        foreach ($rows as &$row) {
            $required = (int) $row['total_lessons'];
            $completed = (int) $row['completed_lessons'];
            $row['progress'] = $required > 0 ? (int) round(($completed / $required) * 100)
                : ((string) $row['status'] === self::STATUS_COMPLETED ? 100 : 0);
            $due = !empty($row['due_date']) ? new DateTime($row['due_date'], new DateTimeZone(date_default_timezone_get())) : null;
            $row['computed_status'] = self::computeStatus(
                (string) $row['status'],
                $row['due_date'] ?? null,
                $row['started_at'] ?? null,
                $row['progress'],
                $required,
                $completed
            );
            $row['is_overdue'] = $due !== null && $due < $now && $row['computed_status'] !== self::STATUS_COMPLETED;
        }
        unset($row);
        return $rows;
    }

    /**
     * Aggregate learning metrics for the administrator dashboard.
     */
    public static function adminStats(): array
    {
        self::ensureSchema();
        $row = Database::fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM learning_programmes WHERE status = 'active') AS programmes,
                (SELECT COUNT(*) FROM candidate_learning_assignments) AS assignments,
                (SELECT COUNT(DISTINCT candidate_id) FROM candidate_learning_assignments) AS learners"
        );
        $stats = array_merge([
            'programmes' => 0,
            'assignments' => 0,
            'learners' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
        ], array_map('intval', $row ?: []));

        foreach (self::assignmentsForAdmin() as $assignment) {
            $status = $assignment['computed_status'] ?? self::STATUS_ASSIGNED;
            if ($status === self::STATUS_COMPLETED) {
                $stats['completed']++;
            } elseif ($status === self::STATUS_IN_PROGRESS) {
                $stats['in_progress']++;
            } elseif ($status === self::STATUS_OVERDUE) {
                $stats['overdue']++;
            }
        }

        return $stats;
    }

    /**
     * All active learning programmes, newest first.
     */
    public static function allActive(): array
    {
        self::ensureSchema();
        return Database::fetchAll(
            "SELECT * FROM learning_programmes
             WHERE status = 'active'
             ORDER BY created_at DESC, id DESC"
        );
    }

    /**
     * Find one programme by ID.
     */
    public static function find(int $id): ?array
    {
        self::ensureSchema();
        return Database::fetchOne(
            "SELECT * FROM learning_programmes WHERE id = ? LIMIT 1",
            'i', [$id]
        );
    }

    /**
     * Find one programme by slug.
     */
    public static function findBySlug(string $slug): ?array
    {
        self::ensureSchema();
        return Database::fetchOne(
            "SELECT * FROM learning_programmes WHERE slug = ? LIMIT 1",
            's', [$slug]
        );
    }

    /**
     * Create a new programme (admin side).
     */
    public static function create(array $data): int
    {
        self::ensureSchema();
        $slug = !empty($data['slug']) ? $data['slug'] : self::makeSlug($data['title']);
        $slug = self::uniqueSlug($slug);
        Database::execute(
            "INSERT INTO learning_programmes
              (title, slug, description, objectives, category, provider, duration_hours, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
            'ssssssi',
            [
                $data['title'],
                $slug,
                $data['description'] ?? null,
                $data['objectives'] ?? null,
                $data['category'] ?? 'general',
                $data['provider'] ?? null,
                (int) ($data['duration_hours'] ?? 0),
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Update an existing programme (admin side).
     */
    public static function update(int $id, array $data): bool
    {
        self::ensureSchema();
        $allowed = ['title', 'description', 'objectives', 'category', 'provider', 'duration_hours', 'status'];
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
        if (isset($data['title']) && !isset($data['slug'])) {
            $newSlug = self::uniqueSlug(self::makeSlug($data['title']), $id);
            $sets[] = "`slug` = ?";
            $types .= 's';
            $params[] = $newSlug;
        }
        $types .= 'i';
        $params[] = $id;
        $sql = "UPDATE learning_programmes SET " . implode(', ', $sets)
            . " WHERE id = ?";
        return Database::execute($sql, $types, $params) >= 0;
    }

    /**
     * Delete a programme and its lessons (admin side). Assignments cascade.
     */
    public static function delete(int $id): bool
    {
        self::ensureSchema();
        return Database::execute("DELETE FROM learning_programmes WHERE id = ?", 'i', [$id]) >= 0;
    }

    // --------------------------------------------------------
    // LESSONS (programme curriculum)
    // --------------------------------------------------------

    /**
     * All lessons for a programme, ordered.
     */
    public static function lessonsForProgramme(int $programmeId): array
    {
        self::ensureSchema();
        return Database::fetchAll(
            "SELECT * FROM course_lessons
             WHERE programme_id = ?
             ORDER BY lesson_order ASC, id ASC",
            'i', [$programmeId]
        );
    }

    /**
     * Find one lesson, verifying it belongs to the programme.
     */
    public static function findLesson(int $lessonId, int $programmeId): ?array
    {
        self::ensureSchema();
        return Database::fetchOne(
            "SELECT * FROM course_lessons WHERE id = ? AND programme_id = ? LIMIT 1",
            'ii', [$lessonId, $programmeId]
        );
    }

    /**
     * Add a lesson to a programme (admin side).
     */
    public static function addLesson(int $programmeId, array $data): int
    {
        self::ensureSchema();
        $order = (int) ($data['lesson_order'] ?? 0);
        if ($order <= 0) {
            $max = Database::fetchOne(
                "SELECT COALESCE(MAX(lesson_order), 0) AS m FROM course_lessons WHERE programme_id = ?",
                'i', [$programmeId]
            );
            $order = (int) ($max['m'] ?? 0) + 1;
        }
        Database::execute(
            "INSERT INTO course_lessons
              (programme_id, title, type, content_url, content_text, duration_min, lesson_order, is_required)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            'issssiii',
            [
                $programmeId,
                $data['title'],
                in_array($data['type'] ?? 'lesson', self::LESSON_TYPES, true) ? $data['type'] : 'lesson',
                $data['content_url'] ?? null,
                $data['content_text'] ?? null,
                (int) ($data['duration_min'] ?? 0),
                $order,
                !empty($data['is_required']) ? 1 : 0,
            ]
        );
        return Database::lastInsertId();
    }

    /**
     * Link a skill to a programme (skills targeted/gained by completing it).
     */
    public static function linkSkill(int $programmeId, int $skillId): bool
    {
        self::ensureSchema();
        return Database::execute(
            "INSERT IGNORE INTO programme_skills (programme_id, skill_id) VALUES (?, ?)",
            'ii', [$programmeId, $skillId]
        ) >= 0;
    }

    /**
     * Skills targeted/gained by a programme.
     */
    public static function skillsForProgramme(int $programmeId): array
    {
        self::ensureSchema();
        return Database::fetchAll(
            "SELECT s.id, s.name, s.category
             FROM programme_skills ps
             INNER JOIN skills s ON s.id = ps.skill_id
             WHERE ps.programme_id = ?
             ORDER BY s.category ASC, s.name ASC",
            'i', [$programmeId]
        );
    }

    // --------------------------------------------------------
    // ASSIGNMENTS (candidate-scoped)
    // --------------------------------------------------------

    /**
     * All programmes assigned to a candidate, with real progress computed
     * from course_lessons / candidate_lesson_progress.
     */
    public static function forCandidate(int $candidateId, array $filters = []): array
    {
        self::ensureSchema();
        if ($candidateId <= 0) {
            return [];
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $sort = trim((string) ($filters['sort'] ?? 'assigned_at'));

        $where = ['cla.candidate_id = ?'];
        $types = 'i';
        $params = [$candidateId];

        if ($search !== '') {
            $where[] = '(lp.title LIKE ? OR lp.category LIKE ? OR lp.provider LIKE ?)';
            $types .= 'sss';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($category !== '' && $category !== 'general') {
            $where[] = 'lp.category = ?';
            $types .= 's';
            $params[] = $category;
        }
        if ($status !== '' && $status !== 'all' && in_array($status, self::ALL_STATUSES, true)) {
            $where[] = 'cla.status = ?';
            $types .= 's';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $orderBy = 'cla.assigned_at DESC';
        if ($sort === 'due_date') {
            $orderBy = 'cla.due_date IS NULL, cla.due_date ASC, cla.assigned_at DESC';
        } elseif ($sort === 'title') {
            $orderBy = 'lp.title ASC';
        }

        $sql = "
            SELECT cla.id AS assignment_id,
                   cla.programme_id, cla.candidate_id, cla.assigned_by,
                   cla.assigned_at, cla.start_date, cla.due_date, cla.status,
                   cla.started_at, cla.completed_at,
                   lp.title, lp.slug, lp.description, lp.objectives, lp.category,
                   lp.provider, lp.duration_hours, lp.status AS programme_status,
                   COUNT(DISTINCT cl.id) AS total_lessons,
                   COUNT(DISTINCT CASE WHEN cl.is_required = 1 THEN cl.id END) AS required_lessons,
                   COUNT(DISTINCT CASE WHEN cp.id IS NOT NULL THEN cl.id END) AS completed_required_lessons
            FROM candidate_learning_assignments cla
            INNER JOIN learning_programmes lp ON lp.id = cla.programme_id
            LEFT JOIN course_lessons cl ON cl.programme_id = lp.id
            LEFT JOIN candidate_lesson_progress cp
              ON cp.lesson_id = cl.id
             AND cp.candidate_id = cla.candidate_id
             AND cp.completed = 1
            WHERE {$whereSql}
            GROUP BY cla.id, lp.id
            ORDER BY {$orderBy}
        ";

        try {
            $rows = Database::fetchAll($sql, $types, $params);
        } catch (Throwable $e) {
            error_log('[LearningProgramme] forCandidate failed: ' . $e->getMessage());
            return [];
        }

        $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
        $out = [];
        foreach ($rows as $row) {
            $required = (int) ($row['total_lessons'] ?? 0);
            $completedRequired = (int) ($row['completed_required_lessons'] ?? 0);
            $progress = $required > 0
                ? (int) round(($completedRequired / $required) * 100)
                : (($row['status'] === self::STATUS_COMPLETED ? 100 : 0));

            $computedStatus = self::computeStatus(
                (string) $row['status'],
                $row['due_date'] ?? null,
                $row['started_at'] ?? null,
                $progress,
                $required,
                $completedRequired
            );

            $due = $row['due_date'] ? new DateTime($row['due_date'], new DateTimeZone(date_default_timezone_get())) : null;
            $daysLeft = $due ? (int) $due->diff($now)->format('%r%a') : null;
            $isOverdue = $due !== null && $due < $now && $computedStatus !== self::STATUS_COMPLETED;

            $out[] = [
                'assignment_id' => (int) $row['assignment_id'],
                'programme_id' => (int) $row['programme_id'],
                'candidate_id' => (int) $row['candidate_id'],
                'assigned_by' => $row['assigned_by'] !== null ? (int) $row['assigned_by'] : null,
                'assigned_at' => $row['assigned_at'],
                'start_date' => $row['start_date'],
                'due_date' => $row['due_date'],
                'status' => (string) $row['status'],
                'computed_status' => $computedStatus,
                'started_at' => $row['started_at'],
                'completed_at' => $row['completed_at'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'objectives' => $row['objectives'],
                'category' => $row['category'],
                'provider' => $row['provider'],
                'duration_hours' => (int) ($row['duration_hours'] ?? 0),
                'programme_status' => $row['programme_status'],
                'total_lessons' => (int) ($row['total_lessons'] ?? 0),
                'required_lessons' => $required,
                'completed_lessons' => $completedRequired,
                'progress' => $progress,
                'days_left' => $daysLeft,
                'is_overdue' => $isOverdue,
            ];
        }
        return $out;
    }

    /**
     * Counts per computed status for a candidate's assignments.
     */
    public static function candidateStats(int $candidateId): array
    {
        $stats = [
            'total' => 0,
            'assigned' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
            'progress' => 0,
        ];
        $rows = self::forCandidate($candidateId);
        $stats['total'] = count($rows);
        $totalProgress = 0;
        foreach ($rows as $r) {
            $key = $r['computed_status'];
            if (array_key_exists($key, $stats)) {
                $stats[$key]++;
            }
            $totalProgress += (int) $r['progress'];
        }
        $stats['progress'] = $stats['total'] > 0 ? (int) round($totalProgress / $stats['total']) : 0;
        return $stats;
    }

    /**
     * Find one assignment, scoped to the owning candidate.
     */
    public static function findAssignmentForCandidate(int $assignmentId, int $candidateId): ?array
    {
        $rows = self::forCandidate($candidateId);
        foreach ($rows as $r) {
            if ((int) $r['assignment_id'] === $assignmentId) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Assign a programme to a candidate (admin side). Idempotent on the
     * unique (programme_id, candidate_id) key.
     */
    public static function assign(int $programmeId, int $candidateId, int $assignedBy, ?string $dueDate = null): int
    {
        self::ensureSchema();
        $conn = Database::getConnection();
        $conn->begin_transaction();
        try {
            Database::execute(
                "INSERT IGNORE INTO candidate_learning_assignments
                  (programme_id, candidate_id, assigned_by, due_date)
                 VALUES (?, ?, ?, ?)",
                'iiis', [$programmeId, $candidateId, $assignedBy, $dueDate]
            );
            $row = Database::fetchOne(
                "SELECT id FROM candidate_learning_assignments
                 WHERE programme_id = ? AND candidate_id = ? LIMIT 1",
                'ii', [$programmeId, $candidateId]
            );
            $assignmentId = (int) ($row['id'] ?? 0);

            if ($assignmentId > 0) {
                $lessons = self::lessonsForProgramme($programmeId);
                foreach ($lessons as $lesson) {
                    Database::execute(
                        "INSERT IGNORE INTO candidate_lesson_progress
                          (assignment_id, lesson_id, candidate_id, completed, completed_at)
                         VALUES (?, ?, ?, 0, NULL)",
                        'iii', [$assignmentId, (int) $lesson['id'], $candidateId]
                    );
                }
            }
            $conn->commit();
            return $assignmentId;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('[LearningProgramme] assign failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start an assignment (candidate side): sets started_at + status in_progress.
     */
    public static function start(int $assignmentId, int $candidateId): bool
    {
        self::ensureSchema();
        $row = Database::fetchOne(
            "SELECT id, status FROM candidate_learning_assignments
             WHERE id = ? AND candidate_id = ? LIMIT 1",
            'ii', [$assignmentId, $candidateId]
        );
        if (!$row) {
            return false;
        }
        return Database::execute(
            "UPDATE candidate_learning_assignments
             SET started_at = COALESCE(started_at, NOW()),
                 status = 'in_progress'
             WHERE id = ? AND candidate_id = ? AND status != 'completed'",
            'ii', [$assignmentId, $candidateId]
        ) >= 0;
    }

    /**
     * Mark a lesson complete for a candidate (candidate side).
     * Returns the new progress percentage, or null on failure.
     *
     * Prevents duplicates via the unique (assignment_id, lesson_id) key and
     * only updates when the lesson actually changed state.
     */
    public static function markLessonComplete(int $assignmentId, int $lessonId, int $candidateId): ?array
    {
        self::ensureSchema();
        $assignment = Database::fetchOne(
            "SELECT id, programme_id FROM candidate_learning_assignments
             WHERE id = ? AND candidate_id = ? LIMIT 1",
            'ii', [$assignmentId, $candidateId]
        );
        if (!$assignment) {
            return null;
        }
        $lesson = self::findLesson($lessonId, (int) $assignment['programme_id']);
        if (!$lesson) {
            return null;
        }

        $conn = Database::getConnection();
        $conn->begin_transaction();
        try {
            $now = date('Y-m-d H:i:s');
            Database::execute(
                "INSERT INTO candidate_lesson_progress
                  (assignment_id, lesson_id, candidate_id, completed, completed_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   completed = VALUES(completed),
                   completed_at = IF(VALUES(completed) = 1 AND completed_at IS NULL, VALUES(updated_at), completed_at),
                   updated_at = VALUES(updated_at)",
                'iiiss', [$assignmentId, $lessonId, $candidateId, $now, $now]
            );

            $agg = Database::fetchOne(
                "SELECT
                    COUNT(DISTINCT CASE WHEN cl.is_required = 1 THEN cl.id END) AS required,
                    COUNT(DISTINCT CASE WHEN cl.is_required = 1 AND cp.id IS NOT NULL THEN cl.id END) AS completed_required
                 FROM course_lessons cl
                 LEFT JOIN candidate_lesson_progress cp
                   ON cp.lesson_id = cl.id
                  AND cp.candidate_id = ?
                  AND cp.completed = 1
                 WHERE cl.programme_id = ?",
                'ii', [$candidateId, (int) $assignment['programme_id']]
            );
            $required = (int) ($agg['required'] ?? 0);
            $completedRequired = (int) ($agg['completed_required'] ?? 0);
            $progress = $required > 0 ? (int) round(($completedRequired / $required) * 100) : 0;

            $newStatus = self::STATUS_IN_PROGRESS;
            if ($required > 0 && $completedRequired >= $required) {
                $newStatus = self::STATUS_COMPLETED;
            }
            $sets = "status = ?";
            $types = 's';
            $params = [$newStatus];
            if ($newStatus === self::STATUS_COMPLETED) {
                $sets .= ", completed_at = ?";
                $types .= 's';
                $params[] = $now;
            }
            $types .= 'ii';
            $params[] = $assignmentId;
            $params[] = $candidateId;
            Database::execute(
                "UPDATE candidate_learning_assignments SET {$sets} WHERE id = ? AND candidate_id = ?",
                $types, $params
            );

            $conn->commit();

            if ($newStatus === self::STATUS_COMPLETED) {
                try {
                    self::awardProgrammeSkills((int) $assignment['programme_id'], $candidateId);
                } catch (Throwable $e) {
                    error_log('[LearningProgramme] Programme skill award failed after completion: ' . $e->getMessage());
                }
            }

            return [
                'progress' => $progress,
                'status' => $newStatus,
                'completed_required' => $completedRequired,
                'required' => $required,
                'lesson_completed' => true,
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('[LearningProgramme] markLessonComplete failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Recompute + resave the computed status for an assignment (admin/sync).
     */
    public static function refreshAssignmentStatus(int $assignmentId, int $candidateId): bool
    {
        $rows = self::forCandidate($candidateId);
        foreach ($rows as $r) {
            if ((int) $r['assignment_id'] !== $assignmentId) {
                continue;
            }
            $target = $r['computed_status'];
            if ($target === $r['status']) {
                return true;
            }
            return Database::execute(
                "UPDATE candidate_learning_assignments
                 SET status = ?
                 WHERE id = ? AND candidate_id = ?",
                'sii', [$target, $assignmentId, $candidateId]
            ) >= 0;
        }
        return false;
    }

    // --------------------------------------------------------
    // SKILLS AWARDED THROUGH TRAINING
    // --------------------------------------------------------

    /**
     * Award a programme's linked skills to a candidate as verified training
     * skills. Skills already present are left untouched (no duplicates);
     * existing self-reported skills are upgraded to verified/training.
     */
    private static function awardProgrammeSkills(int $programmeId, int $candidateId): void
    {
        $skills = self::skillsForProgramme($programmeId);
        $skillColumns = self::tableColumns('candidate_skills') ?? [];
        $hasSourceColumn = in_array('source', $skillColumns, true);
        foreach ($skills as $skill) {
            $exists = Database::fetchOne(
                "SELECT id, verification_status FROM candidate_skills
                 WHERE user_id = ? AND skill_id = ? LIMIT 1",
                'ii', [$candidateId, (int) $skill['id']]
            );
            if ($exists) {
                if ($hasSourceColumn) {
                    Database::execute(
                        "UPDATE candidate_skills
                         SET source = 'training', verification_status = 'verified'
                         WHERE id = ? AND user_id = ?",
                        'ii', [(int) $exists['id'], $candidateId]
                    );
                } else {
                    Database::execute(
                        "UPDATE candidate_skills
                         SET verification_status = 'verified'
                         WHERE id = ? AND user_id = ?",
                        'ii', [(int) $exists['id'], $candidateId]
                    );
                }
            } else {
                if ($hasSourceColumn) {
                    Database::execute(
                        "INSERT INTO candidate_skills
                          (user_id, skill_id, proficiency, verification_status, source)
                         VALUES (?, ?, 'intermediate', 'verified', 'training')",
                        'ii', [$candidateId, (int) $skill['id']]
                    );
                } else {
                    Database::execute(
                        "INSERT INTO candidate_skills
                          (user_id, skill_id, proficiency, verification_status)
                         VALUES (?, ?, 'intermediate', 'verified')",
                        'ii', [$candidateId, (int) $skill['id']]
                    );
                }
            }
        }
    }

    // --------------------------------------------------------
    // HELPERS
    // --------------------------------------------------------

    private static function computeStatus(
        string $stored,
        ?string $dueDate,
        ?string $startedAt,
        int $progress,
        int $requiredLessons = 0,
        int $completedLessons = 0
    ): string
    {
        $lessonsComplete = $requiredLessons > 0
            ? $completedLessons >= $requiredLessons
            : $stored === self::STATUS_COMPLETED;

        if ($lessonsComplete) {
            return self::STATUS_COMPLETED;
        }
        if ($dueDate !== null) {
            $due = new DateTime($dueDate, new DateTimeZone(date_default_timezone_get()));
            $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
            if ($due < $now) {
                return self::STATUS_OVERDUE;
            }
        }
        if ($startedAt !== null || $progress > 0 || $stored === self::STATUS_IN_PROGRESS) {
            return self::STATUS_IN_PROGRESS;
        }
        return self::STATUS_ASSIGNED;
    }

    public static function statusLabel(string $status): string
    {
        $labels = [
            self::STATUS_ASSIGNED => 'Assigned',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_OVERDUE => 'Overdue',
        ];
        return $labels[$status] ?? ucfirst($status);
    }

    public static function statusTone(string $status): string
    {
        $tones = [
            self::STATUS_ASSIGNED => 'primary',
            self::STATUS_IN_PROGRESS => 'secondary',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_OVERDUE => 'danger',
        ];
        return $tones[$status] ?? 'muted';
    }

    public static function lessonTypeLabel(string $type): string
    {
        $labels = [
            'pdf' => 'PDF Document',
            'video' => 'Training Video',
            'link' => 'External Link',
            'document' => 'Downloadable Material',
            'lesson' => 'Online Lesson',
            'quiz' => 'Quiz / Assessment',
        ];
        return $labels[$type] ?? ucfirst($type);
    }

    public static function lessonTypeIcon(string $type): string
    {
        $icons = [
            'pdf' => 'fa-file-pdf',
            'video' => 'fa-play-circle',
            'link' => 'fa-external-link-alt',
            'document' => 'fa-file-download',
            'lesson' => 'fa-book-open',
            'quiz' => 'fa-question-circle',
        ];
        return $icons[$type] ?? 'fa-file';
    }

    private static function makeSlug(string $title): string
    {
        $slug = mb_strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug === '' ? 'programme' : $slug;
    }

    private static function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $candidate = $slug;
        $suffix = 1;
        while (true) {
            $sql = $excludeId !== null
                ? "SELECT id FROM learning_programmes WHERE slug = ? AND id != ? LIMIT 1"
                : "SELECT id FROM learning_programmes WHERE slug = ? LIMIT 1";
            $types = $excludeId !== null ? 'si' : 's';
            $params = $excludeId !== null ? [$candidate, $excludeId] : [$candidate];
            $exists = Database::fetchOne($sql, $types, $params);
            if (!$exists) {
                return $candidate;
            }
            $candidate = $slug . '-' . $suffix++;
        }
    }
}
