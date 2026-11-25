<?php
/**
 * Assessment Scheduling & Assessor Workload Management
 * Shared schema, helpers, writers, and personal-calendar utilities.
 *
 * Design principles:
 *  - Non-destructive: create-if-missing; adapt to legacy schemas via SHOW COLUMNS.
 *  - Qualification-aware allocation: uses assessor_qualifications when populated,
 *    gracefully falls back to "any assessor" when it is empty or missing.
 *  - Conflict-safe: prevents overlapping schedules for the same assessor.
 *  - Programme / cohort aware: detects optional columns/tables and degrades
 *    cleanly to outcome-level reporting when they are unavailable.
 *  - Scope-aware: managers see everything; regular assessors see only their own
 *    data via sw_scope() / sw_assert_can_manage_*() guards.
 *  - Canonical: assessment_schedules is the single source of truth; legacy
 *    assessment_sessions rows are migrated once via sw_migrate_legacy_sessions().
 */

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */
if (!defined('SW_DEFAULT_CAPACITY')) define('SW_DEFAULT_CAPACITY', 15);   // active assessments per assessor
if (!defined('SW_BUSY_THRESHOLD'))   define('SW_BUSY_THRESHOLD', 0.75);   // 75% → amber
if (!defined('SW_FULL_THRESHOLD'))   define('SW_FULL_THRESHOLD', 0.95);   // 95% → red

/* -------------------------------------------------------------------------
 * Column / capability detection
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_has_table')) {
    function sw_has_table(mysqli $conn, string $t): bool
    {
        if (function_exists('as_has_table')) return as_has_table($conn, $t);
        $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
        return $r && $r->num_rows > 0;
    }
}

if (!function_exists('sw_has_column')) {
    function sw_has_column(mysqli $conn, string $table, string $column): bool
    {
        if (function_exists('as_table_has_column')) return as_table_has_column($conn, $table, $column);
        $r = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table)
                        . "` LIKE '" . $conn->real_escape_string($column) . "'");
        return $r && $r->num_rows > 0;
    }
}

if (!function_exists('sw_column_nullable')) {
    function sw_column_nullable(mysqli $conn, string $table, string $column): bool
    {
        $r = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table)
                        . "` LIKE '" . $conn->real_escape_string($column) . "'");
        if ($r && ($row = $r->fetch_assoc())) return strtoupper((string)$row['Null']) === 'YES';
        return false;
    }
}

if (!function_exists('sw_has_programme')) {
    function sw_has_programme(mysqli $conn): bool
    {
        return sw_has_column($conn, 'assessment_outcomes', 'programme_id')
            && sw_has_table($conn, 'programmes');
    }
}

if (!function_exists('sw_has_cohort')) {
    function sw_has_cohort(mysqli $conn): bool
    {
        return (sw_has_column($conn, 'assessment_attempts', 'cohort_id')
                || sw_has_column($conn, 'users', 'cohort_id'))
            && sw_has_table($conn, 'cohorts');
    }
}

if (!function_exists('sw_redirect')) {
    function sw_redirect(string $path): void
    {
        if (function_exists('as_redirect')) { as_redirect($path); }
        header('Location: ' . $path); exit;
    }
}

if (!function_exists('sw_parse_datetime')) {
    function sw_parse_datetime(string $value): string
    {
        $ts = strtotime(trim($value));
        if ($ts === false) throw new RuntimeException('Please provide a valid date and time.');
        return date('Y-m-d H:i:s', $ts);
    }
}

/* -------------------------------------------------------------------------
 * Schema
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_bootstrap')) {
    function sw_bootstrap(mysqli $conn): void
    {
        /* ---- Availability slots ---------------------------------------- */
        if (!sw_has_table($conn, 'assessment_slots')) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS assessment_slots (
                    id INT NOT NULL AUTO_INCREMENT,
                    programme_id INT NULL,
                    outcome_id INT NULL,
                    cohort_id INT NULL,
                    slot_date DATE NOT NULL,
                    start_time TIME NOT NULL,
                    end_time TIME NOT NULL,
                    capacity SMALLINT NOT NULL DEFAULT 1,
                    mode ENUM('in_person','online','phone') NOT NULL DEFAULT 'in_person',
                    location VARCHAR(255) NULL,
                    meeting_link VARCHAR(500) NULL,
                    status ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
                    notes VARCHAR(500) NULL,
                    created_by INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_date (slot_date),
                    KEY idx_status (status),
                    KEY idx_programme (programme_id),
                    KEY idx_outcome (outcome_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        /* ---- Scheduled candidate assessments -------------------------- */
        if (!sw_has_table($conn, 'assessment_schedules')) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS assessment_schedules (
                    id INT NOT NULL AUTO_INCREMENT,
                    attempt_id INT NOT NULL,
                    candidate_id INT NOT NULL,
                    assessor_id INT NULL,
                    slot_id INT NULL,
                    scheduled_at DATETIME NOT NULL,
                    duration_minutes SMALLINT NOT NULL DEFAULT 60,
                    mode ENUM('in_person','online','phone') NOT NULL DEFAULT 'in_person',
                    location VARCHAR(255) NULL,
                    meeting_link VARCHAR(500) NULL,
                    status ENUM('scheduled','confirmed','completed','cancelled','no_show','rescheduled')
                        NOT NULL DEFAULT 'scheduled',
                    notes TEXT NULL,
                    created_by INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_attempt (attempt_id),
                    KEY idx_candidate (candidate_id),
                    KEY idx_assessor (assessor_id),
                    KEY idx_scheduled (scheduled_at),
                    KEY idx_status (status),
                    KEY idx_slot (slot_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            // graceful evolution
            if (!sw_has_column($conn, 'assessment_schedules', 'slot_id')) {
                $conn->query("ALTER TABLE assessment_schedules
                              ADD COLUMN slot_id INT NULL AFTER assessor_id,
                              ADD INDEX idx_slot (slot_id)");
            }
            if (!sw_column_nullable($conn, 'assessment_schedules', 'assessor_id')) {
                $conn->query("ALTER TABLE assessment_schedules MODIFY assessor_id INT NULL");
            }
        }

        /* ---- Assessor qualifications (optional but supported) --------- */
        if (!sw_has_table($conn, 'assessor_qualifications')) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS assessor_qualifications (
                    id INT NOT NULL AUTO_INCREMENT,
                    assessor_id INT NOT NULL,
                    programme_id INT NULL,
                    outcome_id INT NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_assessor_outcome (assessor_id, outcome_id),
                    UNIQUE KEY uniq_assessor_programme (assessor_id, programme_id),
                    KEY idx_assessor (assessor_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
}

/* -------------------------------------------------------------------------
 * Reference data
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_fetch_assessors')) {
    function sw_fetch_assessors(mysqli $conn): array
    {
        $out = [];
        $sql = null;

        if (sw_has_column($conn, 'users', 'role')) {
            $sql = "SELECT id, first_name, last_name, email FROM users WHERE role='assessor' ORDER BY first_name, last_name";
        } elseif (sw_has_column($conn, 'users', 'role_slug')) {
            $sql = "SELECT id, first_name, last_name, email FROM users WHERE role_slug='assessor' ORDER BY first_name, last_name";
        } else {
            $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
                    FROM users u
                    JOIN assessment_attempts aa ON aa.assessor_id = u.id
                    ORDER BY u.first_name, u.last_name";
        }

        $r = $conn->query($sql);
        if ($r) {
            while ($row = $r->fetch_assoc()) $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('sw_fetch_qualified_assessors')) {
    function sw_fetch_qualified_assessors(mysqli $conn, ?int $outcomeId = null): array
    {
        $all = sw_fetch_assessors($conn);
        if (!sw_has_table($conn, 'assessor_qualifications') || empty($all)) return $all;

        $r = $conn->query("SELECT COUNT(*) c FROM assessor_qualifications WHERE active=1");
        if (!$r || (int)($r->fetch_assoc()['c'] ?? 0) === 0) return $all;

        $sql = "SELECT assessor_id FROM assessor_qualifications WHERE active=1";
        if ($outcomeId !== null) {
            $sql .= " AND (outcome_id=" . (int)$outcomeId . " OR outcome_id IS NULL)";
        }
        $r = $conn->query($sql);
        $qualified = [];
        if ($r) while ($row = $r->fetch_assoc()) $qualified[] = (int)$row['assessor_id'];

        if (!$qualified) return $all;
        return array_values(array_filter($all, fn($a) => in_array((int)$a['id'], $qualified, true)));
    }
}

if (!function_exists('sw_programmes')) {
    function sw_programmes(mysqli $conn): array
    {
        if (!sw_has_table($conn, 'programmes')) return [];
        $out = [];
        $r = $conn->query("SELECT id, code, name FROM programmes ORDER BY name");
        if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }
}

if (!function_exists('sw_cohorts')) {
    function sw_cohorts(mysqli $conn): array
    {
        if (!sw_has_table($conn, 'cohorts')) return [];
        $out = [];
        $col = sw_has_column($conn, 'cohorts', 'name') ? 'name' : (sw_has_column($conn, 'cohorts', 'title') ? 'title' : 'code');
        $r = $conn->query("SELECT id, code, $col AS name FROM cohorts ORDER BY $col");
        if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }
}

/* -------------------------------------------------------------------------
 * Stats (global)
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_stats')) {
    function sw_stats(mysqli $conn): array
    {
        $stats = [
            'active'        => 0,
            'upcoming_7d'   => 0,
            'unallocated'   => 0,
            'overdue'       => 0,
            'completed_30d' => 0,
            'pending_sched' => 0,
        ];

        $r = $conn->query("
            SELECT
                COALESCE(SUM(status IN ('scheduled','confirmed')),0)                                            AS active,
                COALESCE(SUM(status IN ('scheduled','confirmed') AND scheduled_at BETWEEN NOW()
                          AND DATE_ADD(NOW(), INTERVAL 7 DAY)),0)                                               AS upcoming_7d,
                COALESCE(SUM(status IN ('scheduled','confirmed') AND assessor_id IS NULL),0)                   AS unallocated,
                COALESCE(SUM(status IN ('scheduled','confirmed') AND scheduled_at < NOW()),0)                  AS overdue,
                COALESCE(SUM(status='completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)),0)         AS completed_30d
            FROM assessment_schedules
        ");
        if ($r) {
            $row = $r->fetch_assoc() ?: [];
            foreach ($stats as $k => $_) if (isset($row[$k])) $stats[$k] = (int)$row[$k];
        }

        $r = $conn->query("
            SELECT COUNT(DISTINCT aa.id) c
            FROM assessment_attempts aa
            WHERE aa.status IN ('submitted','in_review')
              AND NOT EXISTS (
                  SELECT 1 FROM assessment_schedules sch
                  WHERE sch.attempt_id = aa.id AND sch.status IN ('scheduled','confirmed')
              )
        ");
        if ($r) $stats['pending_sched'] = (int)($r->fetch_assoc()['c'] ?? 0);

        return $stats;
    }
}

/* -------------------------------------------------------------------------
 * Scope helpers
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_is_manager')) {
    function sw_is_manager(array $user): bool
    {
        $role = strtolower((string)($user['role'] ?? $user['role_slug'] ?? $user['user_role'] ?? 'assessor'));
        if ($role !== '' && $role !== 'assessor') return true;

        foreach (['is_manager', 'is_lead_assessor', 'is_admin', 'is_coordinator', 'can_manage_assessors'] as $flag) {
            if (!empty($user[$flag])) return true;
        }

        $level = strtolower((string)($user['assessor_level'] ?? $user['level'] ?? $user['grade'] ?? ''));
        if ($level !== '' && in_array($level, ['lead', 'senior', 'manager', 'head', 'principal'], true)) return true;

        return false;
    }
}

if (!function_exists('sw_scope')) {
    function sw_scope(array $user): ?int
    {
        if (sw_is_manager($user)) return null;
        $id = (int)($user['id'] ?? $user['user_id'] ?? 0);
        return $id > 0 ? $id : -1;
    }
}

if (!function_exists('sw_assert_can_manage_attempt')) {
    function sw_assert_can_manage_attempt(mysqli $conn, array $user, int $attemptId): void
    {
        $scope = sw_scope($user);
        if ($scope === null) return;
        if ($attemptId <= 0) throw new RuntimeException('Missing attempt reference.');

        $s = $conn->prepare("SELECT assessor_id FROM assessment_attempts WHERE id=? LIMIT 1");
        if (!$s) throw new RuntimeException('Lookup failed.');
        $s->bind_param('i', $attemptId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row) throw new RuntimeException('Assessment attempt not found.');

        $owner = (int)($row['assessor_id'] ?? 0);
        if ($owner > 0 && $owner !== $scope) {
            throw new RuntimeException('You can only manage assessments assigned to you.');
        }
    }
}

if (!function_exists('sw_assert_can_manage_schedule')) {
    function sw_assert_can_manage_schedule(mysqli $conn, array $user, int $scheduleId): void
    {
        $scope = sw_scope($user);
        if ($scope === null) return;
        if ($scheduleId <= 0) throw new RuntimeException('Missing schedule reference.');

        $s = $conn->prepare("SELECT assessor_id FROM assessment_schedules WHERE id=? LIMIT 1");
        if (!$s) throw new RuntimeException('Lookup failed.');
        $s->bind_param('i', $scheduleId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row) throw new RuntimeException('Schedule not found.');

        $owner = (int)($row['assessor_id'] ?? 0);
        if ($owner !== $scope) {
            throw new RuntimeException('You can only manage schedules allocated to you.');
        }
    }
}

if (!function_exists('sw_assert_can_manage_slot')) {
    function sw_assert_can_manage_slot(mysqli $conn, array $user, int $slotId): void
    {
        if (sw_is_manager($user)) return;
        if ($slotId <= 0) throw new RuntimeException('Missing slot reference.');
        $s = $conn->prepare("SELECT created_by FROM assessment_slots WHERE id=? LIMIT 1");
        if (!$s) throw new RuntimeException('Lookup failed.');
        $s->bind_param('i', $slotId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row) throw new RuntimeException('Slot not found.');
        $owner = (int)($row['created_by'] ?? 0);
        if ($owner !== (int)($user['id'] ?? $user['user_id'] ?? 0)) {
            throw new RuntimeException('You can only manage slots you created.');
        }
    }
}

/* -------------------------------------------------------------------------
 * Fetch helpers (scope-aware)
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_fetch_pending_attempts')) {
    function sw_fetch_pending_attempts(mysqli $conn, array $f = [], int $limit = 100): array
    {
        $where  = ["aa.status IN ('submitted','in_review')"];
        $where[] = "NOT EXISTS (SELECT 1 FROM assessment_schedules sch
                                 WHERE sch.attempt_id = aa.id
                                   AND sch.status IN ('scheduled','confirmed'))";
        $types  = '';
        $params = [];

        // Scope
        if (array_key_exists('assessor_scope', $f) && $f['assessor_scope'] !== null) {
            $where[] = "aa.assessor_id = ?";
            $types  .= 'i'; $params[] = (int)$f['assessor_scope'];
        }

        if (!empty($f['programme_id']) && sw_has_programme($conn)) {
            $where[] = "ao.programme_id = ?";
            $types .= 'i'; $params[] = (int)$f['programme_id'];
        }
        if (!empty($f['cohort_id']) && sw_has_column($conn, 'assessment_attempts', 'cohort_id')) {
            $where[] = "aa.cohort_id = ?";
            $types .= 'i'; $params[] = (int)$f['cohort_id'];
        }
        if (!empty($f['q'])) {
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR ao.code LIKE ? OR ao.title LIKE ?)";
            $like = '%' . $f['q'] . '%';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT aa.id AS attempt_id, aa.attempt_number, aa.status AS attempt_status,
                       aa.assessor_id, aa.submitted_at,
                       u.id AS candidate_id, u.first_name, u.last_name, u.email,
                       ao.id AS outcome_id, ao.code AS outcome_code, ao.title AS outcome_title";
        if (sw_has_programme($conn)) {
            $sql .= ", p.id AS programme_id, p.code AS programme_code, p.name AS programme_name";
        }
        if (sw_has_column($conn, 'assessment_attempts', 'cohort_id')) {
            $sql .= ", aa.cohort_id";
            if (sw_has_table($conn, 'cohorts')) {
                $col = sw_has_column($conn, 'cohorts', 'name') ? 'name' : 'code';
                $sql .= ", c.$col AS cohort_name";
            }
        }
        $sql .= " FROM assessment_attempts aa
                  JOIN users u                ON u.id  = aa.participant_id
                  JOIN assessment_outcomes ao ON ao.id = aa.outcome_id";
        if (sw_has_programme($conn)) {
            $sql .= " LEFT JOIN programmes p ON p.id = ao.programme_id";
        }
        if (sw_has_column($conn, 'assessment_attempts', 'cohort_id') && sw_has_table($conn, 'cohorts')) {
            $sql .= " LEFT JOIN cohorts c ON c.id = aa.cohort_id";
        }
        $sql .= " WHERE " . implode(' AND ', $where)
              . " ORDER BY aa.submitted_at ASC, aa.id ASC LIMIT " . (int)$limit;

        $s = $conn->prepare($sql);
        if (!$s) return [];
        if ($types !== '') $s->bind_param($types, ...$params);
        $s->execute();
        $res = $s->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) $out[] = $row;
        $s->close();
        return $out;
    }
}

if (!function_exists('sw_fetch_schedules')) {
    function sw_fetch_schedules(mysqli $conn, array $f = [], int $limit = 100): array
    {
        $where  = ['1=1'];
        $types  = '';
        $params = [];

        if (!empty($f['status'])) {
            $where[] = "sch.status = ?";
            $types .= 's'; $params[] = $f['status'];
        } else {
            $where[] = "sch.status IN ('scheduled','confirmed')";
        }

        // Scope
        if (array_key_exists('assessor_scope', $f) && $f['assessor_scope'] !== null) {
            $where[] = "sch.assessor_id = ?";
            $types  .= 'i'; $params[] = (int)$f['assessor_scope'];
        }

        if (!empty($f['assessor_id'])) {
            $where[] = "sch.assessor_id = ?";
            $types .= 'i'; $params[] = (int)$f['assessor_id'];
        }
        if (!empty($f['unallocated'])) {
            $where[] = "sch.assessor_id IS NULL";
        }
        if (!empty($f['programme_id']) && sw_has_programme($conn)) {
            $where[] = "ao.programme_id = ?";
            $types .= 'i'; $params[] = (int)$f['programme_id'];
        }
        if (!empty($f['q'])) {
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR ao.code LIKE ?)";
            $like = '%' . $f['q'] . '%';
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
        }

        $sql = "SELECT sch.*, aa.attempt_number, aa.status AS attempt_status,
                       aa.assessor_result,
                       u.id AS candidate_id, u.first_name, u.last_name, u.email,
                       ao.id AS outcome_id, ao.code AS outcome_code, ao.title AS outcome_title,
                       ast.first_name AS assessor_first, ast.last_name AS assessor_last";
        if (sw_has_programme($conn)) {
            $sql .= ", p.code AS programme_code, p.name AS programme_name";
        }
        $sql .= " FROM assessment_schedules sch
                  JOIN assessment_attempts aa   ON aa.id  = sch.attempt_id
                  JOIN users u                  ON u.id   = aa.participant_id
                  JOIN assessment_outcomes ao   ON ao.id  = aa.outcome_id
                  LEFT JOIN users ast           ON ast.id = sch.assessor_id";
        if (sw_has_programme($conn)) {
            $sql .= " LEFT JOIN programmes p ON p.id = ao.programme_id";
        }
        $sql .= " WHERE " . implode(' AND ', $where)
              . " ORDER BY sch.scheduled_at ASC, sch.id ASC LIMIT " . (int)$limit;

        $s = $conn->prepare($sql);
        if (!$s) return [];
        if ($types !== '') $s->bind_param($types, ...$params);
        $s->execute();
        $res = $s->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) $out[] = $row;
        $s->close();
        return $out;
    }
}

if (!function_exists('sw_fetch_slots')) {
    function sw_fetch_slots(mysqli $conn, array $f = [], int $limit = 100): array
    {
        $where  = ['1=1'];
        $types  = '';
        $params = [];

        if (!empty($f['status'])) {
            $where[] = "s.status = ?";
            $types .= 's'; $params[] = $f['status'];
        } else {
            $where[] = "s.status IN ('open','closed')";
        }
        if (!empty($f['from_date'])) {
            $where[] = "s.slot_date >= ?";
            $types .= 's'; $params[] = $f['from_date'];
        }
        if (!empty($f['programme_id']) && sw_has_column($conn, 'assessment_slots', 'programme_id')) {
            $where[] = "s.programme_id = ?";
            $types .= 'i'; $params[] = (int)$f['programme_id'];
        }

        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM assessment_schedules sch
                          WHERE sch.slot_id = s.id AND sch.status IN ('scheduled','confirmed')) AS used_count";
        if (sw_has_table($conn, 'programmes')) {
            $sql .= ", p.code AS programme_code, p.name AS programme_name";
        }
        $sql .= " FROM assessment_slots s";
        if (sw_has_table($conn, 'programmes') && sw_has_column($conn, 'assessment_slots', 'programme_id')) {
            $sql .= " LEFT JOIN programmes p ON p.id = s.programme_id";
        }
        $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY s.slot_date ASC, s.start_time ASC LIMIT " . (int)$limit;

        $s = $conn->prepare($sql);
        if (!$s) return [];
        if ($types !== '') $s->bind_param($types, ...$params);
        $s->execute();
        $res = $s->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) $out[] = $row;
        $s->close();
        return $out;
    }
}

/* -------------------------------------------------------------------------
 * Conflict detection
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_check_conflict')) {
    function sw_check_conflict(mysqli $conn, int $assessorId, string $scheduledAt, int $duration, ?int $excludeId = null): array
    {
        if ($assessorId <= 0) return [];
        $start = strtotime($scheduledAt);
        $end   = $start + max(1, $duration) * 60;

        $sql = "SELECT id, scheduled_at, duration_minutes
                FROM assessment_schedules
                WHERE assessor_id=? AND status IN ('scheduled','confirmed')
                  AND scheduled_at BETWEEN ? AND ?";
        if ($excludeId) $sql .= " AND id != " . (int)$excludeId;
        $s = $conn->prepare($sql);
        if (!$s) return [];
        $winFrom = date('Y-m-d H:i:s', $start - 86400);
        $winTo   = date('Y-m-d H:i:s', $end + 86400);
        $s->bind_param('iss', $assessorId, $winFrom, $winTo);
        $s->execute();
        $res = $s->get_result();
        $conflicts = [];
        while ($row = $res->fetch_assoc()) {
            $rs = strtotime($row['scheduled_at']);
            $re = $rs + (int)$row['duration_minutes'] * 60;
            if ($rs < $end && $re > $start) $conflicts[] = $row;
        }
        $s->close();
        return $conflicts;
    }
}

/* -------------------------------------------------------------------------
 * Writers — schedules
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_verify_attempt')) {
    function sw_verify_attempt(mysqli $conn, int $attemptId): array
    {
        $s = $conn->prepare("SELECT aa.id, aa.status, aa.participant_id, aa.outcome_id,
                                    ao.title AS outcome_title, ao.code AS outcome_code
                             FROM assessment_attempts aa
                             JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
                             WHERE aa.id = ? LIMIT 1");
        if (!$s) throw new RuntimeException('Lookup failed.');
        $s->bind_param('i', $attemptId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row) throw new RuntimeException('Assessment attempt not found.');
        return $row;
    }
}

if (!function_exists('sw_schedule_assessment')) {
    function sw_schedule_assessment(mysqli $conn, array $in, int $createdBy): int
    {
        $attemptId = (int)($in['attempt_id'] ?? 0);
        if ($attemptId <= 0) throw new RuntimeException('Select an assessment to schedule.');

        $attempt = sw_verify_attempt($conn, $attemptId);
        if (!in_array($attempt['status'], ['submitted', 'in_review', 'awaiting_moderation'], true)) {
            throw new RuntimeException('This attempt cannot be scheduled in its current status.');
        }

        $chk = $conn->query("SELECT id FROM assessment_schedules
                             WHERE attempt_id=$attemptId AND status IN ('scheduled','confirmed') LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            throw new RuntimeException('This attempt already has an active schedule. Reschedule it instead.');
        }

        $scheduledAt = sw_parse_datetime((string)($in['scheduled_at'] ?? ''));
        $duration    = max(15, min(480, (int)($in['duration_minutes'] ?? 60)));
        $mode        = in_array($in['mode'] ?? '', ['in_person','online','phone'], true) ? $in['mode'] : 'in_person';
        $assessorId  = (int)($in['assessor_id'] ?? 0);
        $slotId      = (int)($in['slot_id'] ?? 0) ?: null;
        $location    = trim((string)($in['location'] ?? ''));
        $link        = trim((string)($in['meeting_link'] ?? ''));
        $notes       = trim((string)($in['notes'] ?? ''));
        $candidateId = (int)$attempt['participant_id'];

        if ($assessorId > 0) {
            $conflicts = sw_check_conflict($conn, $assessorId, $scheduledAt, $duration);
            if ($conflicts) {
                throw new RuntimeException('This assessor already has an overlapping session. Choose another time or assessor.');
            }
        }

        if ($slotId) {
            $slot = $conn->query("SELECT capacity,
                    (SELECT COUNT(*) FROM assessment_schedules sch
                     WHERE sch.slot_id=$slotId AND sch.status IN ('scheduled','confirmed')) AS used
                FROM assessment_slots WHERE id=$slotId AND status='open' LIMIT 1")->fetch_assoc();
            if (!$slot) throw new RuntimeException('The selected availability slot is no longer open.');
            if ((int)$slot['used'] >= (int)$slot['capacity']) {
                throw new RuntimeException('This availability slot is full.');
            }
        }

        $s = $conn->prepare("INSERT INTO assessment_schedules
            (attempt_id, candidate_id, assessor_id, slot_id, scheduled_at, duration_minutes,
             mode, location, meeting_link, status, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,'scheduled',?,?)");
        if (!$s) throw new RuntimeException('Prepare failed: ' . $conn->error);
        $assessorParam = $assessorId > 0 ? $assessorId : null;
        $s->bind_param('iiii sisssi',
            $attemptId, $candidateId, $assessorParam, $slotId,
            $scheduledAt, $duration, $mode, $location, $link, $notes, $createdBy);
        if (!$s->execute()) { $e = $s->error; $s->close(); throw new RuntimeException('Could not schedule: ' . $e); }
        $id = (int)$s->insert_id;
        $s->close();

        if ($assessorId > 0) {
            $conn->query("UPDATE assessment_attempts SET assessor_id=$assessorId
                          WHERE id=$attemptId AND (assessor_id IS NULL OR assessor_id=0)");
        }
        return $id;
    }
}

if (!function_exists('sw_update_schedule')) {
    function sw_update_schedule(mysqli $conn, int $scheduleId, array $in): void
    {
        if ($scheduleId <= 0) throw new RuntimeException('Missing schedule reference.');

        $scheduledAt = sw_parse_datetime((string)($in['scheduled_at'] ?? ''));
        $duration    = max(15, min(480, (int)($in['duration_minutes'] ?? 60)));
        $mode        = in_array($in['mode'] ?? '', ['in_person','online','phone'], true) ? $in['mode'] : 'in_person';
        $assessorId  = (int)($in['assessor_id'] ?? 0) ?: null;
        $location    = trim((string)($in['location'] ?? ''));
        $link        = trim((string)($in['meeting_link'] ?? ''));
        $notes       = trim((string)($in['notes'] ?? ''));

        if ($assessorId) {
            $conflicts = sw_check_conflict($conn, $assessorId, $scheduledAt, $duration, $scheduleId);
            if ($conflicts) throw new RuntimeException('That assessor already has an overlapping session.');
        }

        $s = $conn->prepare("UPDATE assessment_schedules
                             SET scheduled_at=?, duration_minutes=?, mode=?, assessor_id=?,
                                 location=?, meeting_link=?, notes=?, status='scheduled'
                             WHERE id=? AND status IN ('scheduled','confirmed','rescheduled')
                             LIMIT 1");
        if (!$s) throw new RuntimeException('Prepare failed: ' . $conn->error);
        $s->bind_param('sisisssi', $scheduledAt, $duration, $mode, $assessorId, $location, $link, $notes, $scheduleId);
        $s->execute();
        if ($s->affected_rows < 1) { $s->close(); throw new RuntimeException('Schedule not found or unchanged.'); }
        $s->close();
    }
}

if (!function_exists('sw_change_schedule_status')) {
    function sw_change_schedule_status(mysqli $conn, int $scheduleId, string $status): void
    {
        $allowed = ['completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowed, true)) throw new RuntimeException('Invalid status.');
        $s = $conn->prepare("UPDATE assessment_schedules SET status=? WHERE id=? AND status IN ('scheduled','confirmed') LIMIT 1");
        if (!$s) throw new RuntimeException('Prepare failed.');
        $s->bind_param('si', $status, $scheduleId);
        $s->execute();
        $n = $s->affected_rows;
        $s->close();
        if ($n < 1) throw new RuntimeException('Schedule not found or already closed.');
    }
}

if (!function_exists('sw_allocate_assessor')) {
    function sw_allocate_assessor(mysqli $conn, int $scheduleId, int $assessorId): void
    {
        if ($scheduleId <= 0 || $assessorId <= 0) throw new RuntimeException('Missing schedule or assessor.');

        $row = $conn->query("SELECT scheduled_at, duration_minutes, status FROM assessment_schedules WHERE id=$scheduleId LIMIT 1");
        $sch = $row ? $row->fetch_assoc() : null;
        if (!$sch) throw new RuntimeException('Schedule not found.');
        if (!in_array($sch['status'], ['scheduled','confirmed'], true)) {
            throw new RuntimeException('Only active schedules can be allocated.');
        }

        $conflicts = sw_check_conflict($conn, $assessorId, $sch['scheduled_at'], (int)$sch['duration_minutes'], $scheduleId);
        if ($conflicts) throw new RuntimeException('That assessor has an overlapping session at this time.');

        $s = $conn->prepare("UPDATE assessment_schedules SET assessor_id=? WHERE id=? LIMIT 1");
        if (!$s) throw new RuntimeException('Prepare failed.');
        $s->bind_param('ii', $assessorId, $scheduleId);
        $s->execute();
        $s->close();
    }
}

/* -------------------------------------------------------------------------
 * Writers — availability slots
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_create_slot')) {
    function sw_create_slot(mysqli $conn, array $in, int $createdBy): int
    {
        $slotDate = trim((string)($in['slot_date'] ?? ''));
        $start    = trim((string)($in['start_time'] ?? ''));
        $end      = trim((string)($in['end_time'] ?? ''));
        if ($slotDate === '' || $start === '' || $end === '') throw new RuntimeException('Date, start and end are required.');
        if (strtotime("$slotDate $end") <= strtotime("$slotDate $start")) {
            throw new RuntimeException('End time must be after start time.');
        }

        $capacity = max(1, min(50, (int)($in['capacity'] ?? 1)));
        $mode     = in_array($in['mode'] ?? '', ['in_person','online','phone'], true) ? $in['mode'] : 'in_person';
        $progId   = (int)($in['programme_id'] ?? 0) ?: null;
        $outId    = (int)($in['outcome_id'] ?? 0) ?: null;
        $cohortId = (int)($in['cohort_id'] ?? 0) ?: null;
        $location = trim((string)($in['location'] ?? ''));
        $link     = trim((string)($in['meeting_link'] ?? ''));
        $notes    = trim((string)($in['notes'] ?? ''));

        $s = $conn->prepare("INSERT INTO assessment_slots
            (programme_id, outcome_id, cohort_id, slot_date, start_time, end_time,
             capacity, mode, location, meeting_link, status, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,'open',?,?)");
        if (!$s) throw new RuntimeException('Prepare failed: ' . $conn->error);
        $s->bind_param('iiisssissssi',
            $progId, $outId, $cohortId, $slotDate, $start, $end,
            $capacity, $mode, $location, $link, $notes, $createdBy);
        if (!$s->execute()) { $e = $s->error; $s->close(); throw new RuntimeException('Could not create slot: ' . $e); }
        $id = (int)$s->insert_id;
        $s->close();
        return $id;
    }
}

if (!function_exists('sw_close_slot')) {
    function sw_close_slot(mysqli $conn, int $slotId, string $status = 'closed'): void
    {
        if (!in_array($status, ['closed','cancelled'], true)) throw new RuntimeException('Invalid slot status.');
        $s = $conn->prepare("UPDATE assessment_slots SET status=? WHERE id=? AND status='open' LIMIT 1");
        if (!$s) throw new RuntimeException('Prepare failed.');
        $s->bind_param('si', $status, $slotId);
        $s->execute();
        $n = $s->affected_rows;
        $s->close();
        if ($n < 1) throw new RuntimeException('Slot not found or already closed.');
    }
}

/* -------------------------------------------------------------------------
 * Workload analytics (scope-aware)
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_assessor_workload')) {
    function sw_assessor_workload(mysqli $conn): array
    {
        $assessors = sw_fetch_assessors($conn);
        if (!$assessors) return [];

        $ids = array_map(fn($a) => (int)$a['id'], $assessors);
        if (!$ids) return [];
        $in = implode(',', $ids);

        $agg = [];
        $r = $conn->query("
            SELECT assessor_id,
                   SUM(status IN ('scheduled','confirmed'))                                      AS active,
                   SUM(status IN ('scheduled','confirmed') AND scheduled_at < NOW())            AS overdue,
                   SUM(status IN ('scheduled','confirmed') AND scheduled_at BETWEEN NOW()
                       AND DATE_ADD(NOW(), INTERVAL 7 DAY))                                     AS upcoming_7d,
                   SUM(status='completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))   AS completed_30d,
                   COUNT(*)                                                                     AS total
            FROM assessment_schedules
            WHERE assessor_id IN ($in)
            GROUP BY assessor_id
        ");
        if ($r) {
            while ($row = $r->fetch_assoc()) $agg[(int)$row['assessor_id']] = $row;
        }

        $out = [];
        foreach ($assessors as $a) {
            $id  = (int)$a['id'];
            $row = $agg[$id] ?? [];
            $active = (int)($row['active'] ?? 0);
            $pct    = min(100, (int)round($active / max(1, SW_DEFAULT_CAPACITY) * 100));
            $state  = $pct >= SW_FULL_THRESHOLD * 100 ? 'full' : ($pct >= SW_BUSY_THRESHOLD * 100 ? 'busy' : 'available');
            $out[] = $a + [
                'active'       => $active,
                'overdue'      => (int)($row['overdue'] ?? 0),
                'upcoming_7d'  => (int)($row['upcoming_7d'] ?? 0),
                'completed_30d'=> (int)($row['completed_30d'] ?? 0),
                'total'        => (int)($row['total'] ?? 0),
                'capacity_pct' => $pct,
                'state'        => $state,
            ];
        }
        usort($out, fn($a, $b) => $b['active'] <=> $a['active'] ?: $b['overdue'] <=> $a['overdue']);
        return $out;
    }
}

if (!function_exists('sw_workload_by_programme')) {
    function sw_workload_by_programme(mysqli $conn, ?int $assessorScope = null): array
    {
        if (sw_has_programme($conn)) {
            $sql = "SELECT p.id, p.code, p.name,
                        COUNT(DISTINCT aa.id)                                                         AS total_attempts,
                        COUNT(DISTINCT CASE WHEN sch.id IS NOT NULL THEN aa.id END)                   AS scheduled,
                        COUNT(DISTINCT CASE WHEN sch.id IS NOT NULL AND sch.assessor_id IS NULL
                                            THEN aa.id END)                                            AS unallocated,
                        COUNT(DISTINCT CASE WHEN sch.id IS NOT NULL AND sch.assessor_id IS NOT NULL
                                            THEN aa.id END)                                            AS allocated,
                        COUNT(DISTINCT CASE WHEN sch.status='completed' THEN aa.id END)               AS completed
                    FROM programmes p
                    LEFT JOIN assessment_outcomes ao ON ao.programme_id = p.id
                    LEFT JOIN assessment_attempts aa ON aa.outcome_id = ao.id
                        AND aa.status IN ('submitted','in_review','awaiting_moderation')
                        " . ($assessorScope !== null ? "AND aa.assessor_id = " . (int)$assessorScope : "") . "
                    LEFT JOIN assessment_schedules sch ON sch.attempt_id = aa.id
                        AND sch.status IN ('scheduled','confirmed','completed')
                    GROUP BY p.id, p.code, p.name
                    HAVING total_attempts > 0
                    ORDER BY total_attempts DESC";
        } else {
            $sql = "SELECT ao.id, ao.code, ao.title AS name,
                        COUNT(DISTINCT aa.id)                                                         AS total_attempts,
                        COUNT(DISTINCT CASE WHEN sch.id IS NOT NULL THEN aa.id END)                   AS scheduled,
                        COUNT(DISTINCT CASE WHEN sch.id IS NOT NULL AND sch.assessor_id IS NULL
                                            THEN aa.id END)                                            AS unallocated,
                        COUNT(DISTINCT CASE WHEN sch.id IS NOT NULL AND sch.assessor_id IS NOT NULL
                                            THEN aa.id END)                                            AS allocated,
                        COUNT(DISTINCT CASE WHEN sch.status='completed' THEN aa.id END)               AS completed
                    FROM assessment_outcomes ao
                    LEFT JOIN assessment_attempts aa ON aa.outcome_id = ao.id
                        AND aa.status IN ('submitted','in_review','awaiting_moderation')
                        " . ($assessorScope !== null ? "AND aa.assessor_id = " . (int)$assessorScope : "") . "
                    LEFT JOIN assessment_schedules sch ON sch.attempt_id = aa.id
                        AND sch.status IN ('scheduled','confirmed','completed')
                    GROUP BY ao.id, ao.code, ao.title
                    HAVING total_attempts > 0
                    ORDER BY total_attempts DESC";
        }

        $r = $conn->query($sql);
        $out = [];
        if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }
}

if (!function_exists('sw_workload_by_cohort')) {
    function sw_workload_by_cohort(mysqli $conn, ?int $assessorScope = null): array
    {
        if (!sw_has_cohort($conn)) return [];

        $fromAttempts = sw_has_column($conn, 'assessment_attempts', 'cohort_id');
        $joinClause   = $fromAttempts
            ? "LEFT JOIN assessment_attempts aa ON aa.cohort_id = c.id"
            : "LEFT JOIN users u ON u.cohort_id = c.id
               LEFT JOIN assessment_attempts aa ON aa.participant_id = u.id";

        $scopeCond = '';
        if ($assessorScope !== null) {
            $scopeCond = " AND aa.assessor_id = " . (int)$assessorScope . " ";
        }

        $nameCol = sw_has_column($conn, 'cohorts', 'name') ? 'name' : 'code';
        $sql = "SELECT c.id, c.code, c.$nameCol AS name,
                    COUNT(DISTINCT aa.id)                                                         AS total_attempts,
                    COUNT(DISTINCT CASE WHEN sch.id IS NOT NULL THEN aa.id END)                   AS scheduled,
                    COUNT(DISTINCT CASE WHEN sch.id IS NOT NULL AND sch.assessor_id IS NULL
                                        THEN aa.id END)                                            AS unallocated,
                    COUNT(DISTINCT CASE WHEN sch.status='completed' THEN aa.id END)               AS completed
                FROM cohorts c
                $joinClause
                LEFT JOIN assessment_schedules sch ON sch.attempt_id = aa.id
                    AND sch.status IN ('scheduled','confirmed','completed')
                " . ($scopeCond ? "WHERE 1=1 $scopeCond" : "") . "
                GROUP BY c.id, c.code, c.$nameCol
                HAVING total_attempts > 0
                ORDER BY total_attempts DESC
                LIMIT 50";

        $r = $conn->query($sql);
        $out = [];
        if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }
}

/* -------------------------------------------------------------------------
 * Legacy session migration (one-time, idempotent)
 * Copies assessment_sessions → assessment_schedules without duplication.
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_migrate_legacy_sessions')) {
    function sw_migrate_legacy_sessions(mysqli $conn): void
    {
        if (!sw_has_table($conn, 'assessment_sessions')) return;

        $r = $conn->query("SELECT 1 FROM assessment_sessions s
                           WHERE NOT EXISTS (
                               SELECT 1 FROM assessment_schedules sch
                               WHERE sch.attempt_id = s.attempt_id
                                 AND sch.scheduled_at = s.scheduled_at
                           ) LIMIT 1");
        if (!$r || !$r->num_rows) return;

        $conn->query("INSERT INTO assessment_schedules
            (attempt_id, candidate_id, assessor_id, scheduled_at, duration_minutes,
             mode, location, meeting_link, status, notes, created_by, created_at, updated_at)
            SELECT s.attempt_id, aa.participant_id, s.assessor_id, s.scheduled_at, s.duration_minutes,
                   s.mode, s.location, s.meeting_link,
                   CASE s.status
                       WHEN 'completed' THEN 'completed'
                       WHEN 'cancelled' THEN 'cancelled'
                       WHEN 'no_show'  THEN 'no_show'
                       ELSE 'scheduled'
                   END,
                   s.notes, s.created_by, s.created_at, s.updated_at
            FROM assessment_sessions s
            JOIN assessment_attempts aa ON aa.id = s.attempt_id
            WHERE NOT EXISTS (
                SELECT 1 FROM assessment_schedules sch
                WHERE sch.attempt_id = s.attempt_id
                  AND sch.scheduled_at = s.scheduled_at
            )");
    }
}

/* -------------------------------------------------------------------------
 * Personal calendar helpers (used by assessor/scheduling.php)
 * ---------------------------------------------------------------------- */
if (!function_exists('sw_personal_stats')) {
    function sw_personal_stats(mysqli $conn, int $assessorId): array
    {
        $stats = ['upcoming' => 0, 'today' => 0, 'week' => 0, 'unscheduled' => 0, 'completed' => 0, 'overdue' => 0];

        $r = $conn->query("
            SELECT
                COALESCE(SUM(status IN ('scheduled','confirmed') AND scheduled_at >= NOW()),0)          AS upcoming,
                COALESCE(SUM(status IN ('scheduled','confirmed') AND DATE(scheduled_at)=CURDATE()),0)   AS today,
                COALESCE(SUM(status IN ('scheduled','confirmed') AND scheduled_at BETWEEN NOW()
                          AND DATE_ADD(NOW(), INTERVAL 7 DAY)),0)                                        AS week,
                COALESCE(SUM(status='completed'),0)                                                      AS completed,
                COALESCE(SUM(status IN ('scheduled','confirmed') AND scheduled_at < NOW()),0)            AS overdue
            FROM assessment_schedules
            WHERE assessor_id = " . (int)$assessorId
        );
        if ($r) {
            $row = $r->fetch_assoc() ?: [];
            foreach ($stats as $k => $_) if (isset($row[$k])) $stats[$k] = (int)$row[$k];
        }

        $r = $conn->query("
            SELECT COUNT(DISTINCT aa.id) c
            FROM assessment_attempts aa
            WHERE aa.assessor_id = " . (int)$assessorId . "
              AND aa.status IN ('submitted','in_review')
              AND NOT EXISTS (
                  SELECT 1 FROM assessment_schedules sch
                  WHERE sch.attempt_id = aa.id
                    AND sch.status IN ('scheduled','confirmed')
                    AND sch.scheduled_at >= NOW()
              )
        ");
        if ($r) $stats['unscheduled'] = (int)($r->fetch_assoc()['c'] ?? 0);

        return $stats;
    }
}

if (!function_exists('sw_fetch_upcoming_schedules')) {
    function sw_fetch_upcoming_schedules(mysqli $conn, int $assessorId, int $limit = 100): array
    {
        $sql = "SELECT sch.*, aa.attempt_number, aa.status AS attempt_status, aa.assessor_result,
                       u.first_name, u.last_name, u.email,
                       ao.code AS outcome_code, ao.title AS outcome_title
                FROM assessment_schedules sch
                JOIN assessment_attempts aa   ON aa.id = sch.attempt_id
                JOIN users u                  ON u.id  = aa.participant_id
                JOIN assessment_outcomes ao   ON ao.id = aa.outcome_id
                WHERE sch.assessor_id = ?
                  AND sch.status IN ('scheduled','confirmed')
                  AND sch.scheduled_at >= NOW()
                ORDER BY sch.scheduled_at ASC
                LIMIT " . (int)$limit;
        $s = $conn->prepare($sql);
        if (!$s) return [];
        $s->bind_param('i', $assessorId);
        $s->execute();
        $res = $s->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) $out[] = $row;
        $s->close();
        return $out;
    }
}

if (!function_exists('sw_fetch_past_schedules')) {
    function sw_fetch_past_schedules(mysqli $conn, int $assessorId, int $limit = 50): array
    {
        $sql = "SELECT sch.*, aa.attempt_number, aa.status AS attempt_status, aa.assessor_result,
                       u.first_name, u.last_name, u.email,
                       ao.code AS outcome_code, ao.title AS outcome_title
                FROM assessment_schedules sch
                JOIN assessment_attempts aa   ON aa.id = sch.attempt_id
                JOIN users u                  ON u.id  = aa.participant_id
                JOIN assessment_outcomes ao   ON ao.id = aa.outcome_id
                WHERE sch.assessor_id = ?
                  AND (sch.status IN ('completed','cancelled','no_show')
                       OR sch.scheduled_at < NOW())
                ORDER BY sch.scheduled_at DESC
                LIMIT " . (int)$limit;
        $s = $conn->prepare($sql);
        if (!$s) return [];
        $s->bind_param('i', $assessorId);
        $s->execute();
        $res = $s->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) $out[] = $row;
        $s->close();
        return $out;
    }
}