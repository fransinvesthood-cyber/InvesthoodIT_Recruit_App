<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Model
 * ================================================
 * CRUD operations for candidate applications.
 */

class Application
{
    /**
     * Valid application statuses (mirrors the `applications.status`
     * ENUM in the existing database).
     *
     * 'draft' and 'submitted' are candidate-side states; the remaining
     * values make up the admin recruitment pipeline.
     *
     * @var string[]
     */
    public const STATUSES = [
        'draft',
        'submitted',
        'under_review',
        'shortlisted',
        'assessment',
        'interview_scheduled',
        'interview_required',
        'interview_completed',
        'selected',
        'waitlisted',
        'offer_sent',
        'offer_accepted',
        'offer_declined',
        'rejected',
        'withdrawn',
        'on_hold',
    ];

    /**
     * Human-friendly labels for statuses.
     *
     * @var string[]
     */
    public const STATUS_LABELS = [
        'draft'               => 'Draft',
        'submitted'           => 'Submitted',
        'under_review'        => 'Under Review',
        'shortlisted'         => 'Shortlisted',
        'assessment'          => 'Assessment',
        'interview_scheduled' => 'Interview Scheduled',
        'interview_required'  => 'Interview Required',
        'interview_completed' => 'Interview Completed',
        'selected'            => 'Selected',
        'waitlisted'          => 'Waitlisted',
        'offer_sent'          => 'Offer Sent',
        'offer_accepted'      => 'Offer Accepted',
        'offer_declined'      => 'Offer Declined',
        'rejected'            => 'Rejected',
        'withdrawn'           => 'Withdrawn',
        'on_hold'             => 'On Hold',
    ];

    /**
     * Status badge tone for each status.
     *
     * @var string[]
     */
    public const STATUS_BADGES = [
        'draft'               => 'muted',
        'submitted'           => 'primary',
        'under_review'        => 'primary',
        'shortlisted'         => 'primary',
        'assessment'          => 'amber',
        'interview_scheduled' => 'amber',
        'interview_required'  => 'amber',
        'interview_completed' => 'amber',
        'selected'            => 'success',
        'waitlisted'          => 'amber',
        'offer_sent'          => 'success',
        'offer_accepted'      => 'success',
        'offer_declined'      => 'danger',
        'rejected'            => 'danger',
        'withdrawn'           => 'muted',
        'on_hold'             => 'amber',
    ];

    /**
     * Cached result of ensureStatusColumnSupport() for this request —
     * avoids re-inspecting the schema on every status update.
     *
     * @var bool|null
     */
    private static ?bool $statusColumnVerified = null;

    /**
     * Get a human label for a status.
     *
     * @param string|null $status
     * @return string
     */
    public static function label(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    /**
     * Get a badge tone for a status.
     *
     * @param string|null $status
     * @return string
     */
    public static function badgeTone(?string $status): string
    {
        return self::STATUS_BADGES[$status ?? ''] ?? 'muted';
    }

    /**
     * Get all applications for a candidate.
     *
     * @param int $candidateId
     * @return array
     */
    public static function forCandidate(int $candidateId): array
    {
        return Database::fetchAll(
            "SELECT a.id, a.application_reference, a.status, a.submitted_at,
                    a.created_at, a.updated_at,
                    o.id AS opportunity_id, o.title AS opportunity_title, o.type,
                    o.organisation,
                    o.application_open_date, o.application_close_date,
                    o.available_positions, o.province, o.city, o.work_arrangement,
                    p.name AS programme_name, c.name AS cohort_name
             FROM applications a
             LEFT JOIN opportunities o ON o.id = a.opportunity_id
             LEFT JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             WHERE a.candidate_id = ?
             ORDER BY a.created_at DESC",
            'i',
            [$candidateId]
        );
    }

    /**
     * Find a single application by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT a.*, o.title AS opportunity_title, o.type AS opportunity_type,
                    o.organisation,
                    o.application_open_date, o.application_close_date,
                    o.start_date, o.end_date,
                    o.city, o.province, o.work_arrangement, o.available_positions,
                    p.name AS programme_name, c.name AS cohort_name
             FROM applications a
             LEFT JOIN opportunities o ON o.id = a.opportunity_id
             LEFT JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             WHERE a.id = ?
             LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Count applications for a candidate by display category.
     *
     * Returns:
     *   total        – all applications
     *   draft        – status = 'draft'
     *   submitted    – status = 'submitted'
     *   under_review – status in ('under_review','shortlisted','assessment','interview_scheduled','interview_completed','on_hold')
     *   selected     – status in ('selected','offer_sent','offer_accepted')
     *   rejected     – status in ('rejected','offer_declined')
     *
     * @param int $candidateId
     * @return array
     */
    public static function countByStatus(int $candidateId): array
    {
        $rows = Database::fetchAll(
            "SELECT a.status, COUNT(*) AS cnt
             FROM applications a
             WHERE a.candidate_id = ?
             GROUP BY a.status",
            'i',
            [$candidateId]
        );

        $counts = [
            'total'        => 0,
            'draft'        => 0,
            'submitted'    => 0,
            'under_review' => 0,
            'selected'     => 0,
            'rejected'     => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $count  = (int) ($row['cnt'] ?? 0);
            $counts['total'] += $count;

            switch ($status) {
                case 'draft':
                    $counts['draft'] = $count;
                    break;
                case 'submitted':
                    $counts['submitted'] = $count;
                    break;
                case 'under_review':
                case 'shortlisted':
                case 'assessment':
                case 'interview_scheduled':
                case 'interview_completed':
                case 'on_hold':
                    $counts['under_review'] += $count;
                    break;
                case 'selected':
                case 'offer_sent':
                case 'offer_accepted':
                    $counts['selected'] += $count;
                    break;
                case 'rejected':
                case 'offer_declined':
                    $counts['rejected'] += $count;
                    break;
            }
        }

        return $counts;
    }

    /**
     * Create a new application (e.g., candidate applies).
     *
     * @param int    $candidateId
     * @param int    $opportunityId
     * @param string $reference
     * @param string $status
     * @return int  New application ID
     */
    public static function create(int $candidateId, int $opportunityId, string $reference, string $status = 'draft'): int
    {
        Database::execute(
            "INSERT INTO applications
                (candidate_id, opportunity_id, application_reference, status, submitted_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, NOW(), NOW())",
            'iiss',
            [$candidateId, $opportunityId, $reference, $status]
        );
        return Database::lastInsertId();
    }

    /**
     * Find an application for a candidate + opportunity combination.
     *
     * @param int $candidateId
     * @param int $opportunityId
     * @return array|null
     */
    public static function findByCandidateAndOpportunity(int $candidateId, int $opportunityId): ?array
    {
        return Database::fetchOne(
            "SELECT a.*, o.title AS opportunity_title, o.type AS opportunity_type,
                    o.organisation,
                    o.application_open_date, o.application_close_date,
                    o.start_date, o.end_date,
                    o.city, o.province, o.work_arrangement, o.available_positions,
                    p.name AS programme_name, c.name AS cohort_name
             FROM applications a
             LEFT JOIN opportunities o ON o.id = a.opportunity_id
             LEFT JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             WHERE a.candidate_id = ? AND a.opportunity_id = ?
             LIMIT 1",
            'ii',
            [$candidateId, $opportunityId]
        );
    }

    /**
     * Find an application by ID that belongs to a specific candidate.
     * Ownership is enforced in the query itself.
     *
     * @param int $candidateId
     * @param int $applicationId
     * @return array|null
     */
    public static function findForCandidate(int $candidateId, int $applicationId): ?array
    {
        return Database::fetchOne(
            "SELECT a.*, o.title AS opportunity_title, o.type AS opportunity_type,
                    o.organisation,
                    o.application_open_date, o.application_close_date,
                    o.start_date, o.end_date,
                    o.city, o.province, o.work_arrangement, o.available_positions,
                    p.name AS programme_name, c.name AS cohort_name
             FROM applications a
             LEFT JOIN opportunities o ON o.id = a.opportunity_id
             LEFT JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             WHERE a.id = ? AND a.candidate_id = ?
             LIMIT 1",
            'ii',
            [$applicationId, $candidateId]
        );
    }

    /**
     * Generate a unique application reference.
     * Format: APP-YYYY-000001
     *
     * @param int|null $excludeApplicationId
     * @return string
     */
    public static function generateReference(?int $excludeApplicationId = null): string
    {
        $year = (string) date('Y');
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $sequenceBase = (int) Database::fetchOne(
                "SELECT COALESCE(MAX(id), 0) AS max_id FROM applications"
            )['max_id'] ?? 0;
            $suffix = str_pad((string) ($sequenceBase + $attempt + 1), 6, '0', STR_PAD_LEFT);
            $reference = 'APP-' . $year . '-' . $suffix;

            $sql = "SELECT id FROM applications WHERE application_reference = ?";
            $types = 's';
            $params = [$reference];

            if ($excludeApplicationId !== null) {
                $sql .= " AND id != ?";
                $types .= 'i';
                $params[] = $excludeApplicationId;
            }

            $existing = Database::fetchOne($sql, $types, $params);
            if (!$existing) {
                return $reference;
            }
        }

        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return 'APP-' . $year . '-' . $random;
    }

    // =====================================================================
    // ADMIN-SPECIFIC METHODS (Stage 9)
    // =====================================================================

    /**
     * Get all applications for admin view with candidate, programme,
     * cohort, and opportunity information.
     *
     * @param array $filters Associative array of filters
     * @param int   $page    Current page number (1-based)
     * @param int   $perPage Records per page
     * @return array ['records' => [], 'total' => int, 'pages' => int]
     */
    public static function adminList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        try {
            $where = [];
            $params = [];
            $types = '';

            if (!empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $where[] = "(a.application_reference LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR o.title LIKE ?)";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $types .= 'ssss';
            }

            if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
                $where[] = "a.status = ?";
                $params[] = $filters['status'];
                $types .= 's';
            }

            if (!empty($filters['programme_id']) && (int) $filters['programme_id'] > 0) {
                $where[] = "o.programme_id = ?";
                $params[] = (int) $filters['programme_id'];
                $types .= 'i';
            }

            if (!empty($filters['cohort_id']) && (int) $filters['cohort_id'] > 0) {
                $where[] = "o.cohort_id = ?";
                $params[] = (int) $filters['cohort_id'];
                $types .= 'i';
            }

            if (!empty($filters['opportunity_id']) && (int) $filters['opportunity_id'] > 0) {
                $where[] = "a.opportunity_id = ?";
                $params[] = (int) $filters['opportunity_id'];
                $types .= 'i';
            }

            if (!empty($filters['date_from'])) {
                $where[] = "DATE(a.submitted_at) >= ?";
                $params[] = $filters['date_from'];
                $types .= 's';
            }
            if (!empty($filters['date_to'])) {
                $where[] = "DATE(a.submitted_at) <= ?";
                $params[] = $filters['date_to'];
                $types .= 's';
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $countSql = "SELECT COUNT(*) AS total
                         FROM applications a
                         INNER JOIN users u ON u.id = a.candidate_id
                         LEFT JOIN opportunities o ON o.id = a.opportunity_id
                         LEFT JOIN programmes p ON p.id = o.programme_id
                         LEFT JOIN cohorts c ON c.id = o.cohort_id
                         $whereClause";
            $countRow = Database::fetchOne($countSql, $types, $params);
            $total = (int) ($countRow['total'] ?? 0);

            $page = max(1, $page);
            $perPage = max(1, min(100, $perPage));
            $pages = (int) ceil($total / $perPage);
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT a.id, a.application_reference, a.status, a.submitted_at,
                           a.created_at, a.updated_at,
                           a.candidate_id,
                           CONCAT(u.first_name, ' ', u.last_name) AS candidate_name,
                           u.email AS candidate_email,
                           u.phone AS candidate_phone,
                           o.id AS opportunity_id, o.title AS opportunity_title,
                           o.application_close_date,
                           p.id AS programme_id, p.name AS programme_name,
                           c.id AS cohort_id, c.name AS cohort_name
                    FROM applications a
                    INNER JOIN users u ON u.id = a.candidate_id
                    LEFT JOIN opportunities o ON o.id = a.opportunity_id
                    LEFT JOIN programmes p ON p.id = o.programme_id
                    LEFT JOIN cohorts c ON c.id = o.cohort_id
                    $whereClause
                    ORDER BY a.updated_at DESC
                    LIMIT ? OFFSET ?";

            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

            $records = Database::fetchAll($sql, $types, $params);

            return [
                'records' => $records,
                'total'   => $total,
                'pages'   => $pages,
                'page'    => $page,
                'perPage' => $perPage,
            ];
        } catch (Exception $e) {
            // Return empty result on error
            return [
                'records' => [],
                'total'   => 0,
                'pages'   => 0,
                'page'    => 1,
                'perPage' => $perPage,
            ];
        }
    }

    /**
     * Get application counts by status for admin dashboard stats.
     *
     * @return array Associative array with status => count
     */
    public static function adminCountByStatus(): array
    {
        $counts = [
            'total'               => 0,
            'draft'               => 0,
            'submitted'           => 0,
            'under_review'        => 0,
            'shortlisted'         => 0,
            'assessment'          => 0,
            'interview_scheduled' => 0,
            'interview_required'  => 0,
            'interview_completed' => 0,
            'selected'            => 0,
            'waitlisted'          => 0,
            'offer_sent'          => 0,
            'offer_accepted'      => 0,
            'offer_declined'      => 0,
            'rejected'            => 0,
            'withdrawn'           => 0,
            'on_hold'             => 0,
        ];

        try {
            $rows = Database::fetchAll(
                "SELECT status, COUNT(*) AS cnt FROM applications GROUP BY status"
            );

            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                $count  = (int) ($row['cnt'] ?? 0);
                $counts['total'] += $count;
                if (array_key_exists($status, $counts)) {
                    $counts[$status] = $count;
                }
            }
        } catch (Exception $e) {
            // Table may not exist yet
        }

        return $counts;
    }

    /**
     * Find an application by ID for admin view (no ownership check).
     * Includes full candidate, opportunity, programme, and cohort details.
     *
     * @param int $id
     * @return array|null
     */
    public static function adminFind(int $id): ?array
    {
        try {
            return Database::fetchOne(
                "SELECT a.*,
                        CONCAT(u.first_name, ' ', u.last_name) AS candidate_name,
                        u.email AS candidate_email,
                        u.phone AS candidate_phone,
                        u.province AS candidate_province,
                        cp.city AS candidate_city,
                        cp.professional_title,
                        cp.professional_summary,
                        cp.address AS candidate_address,
                        cp.city AS profile_city,
                        o.title AS opportunity_title,
                        o.type AS opportunity_type,
                        o.organisation,
                        o.short_description,
                        o.full_description,
                        o.application_open_date,
                        o.application_close_date,
                        o.start_date AS opportunity_start_date,
                        o.end_date AS opportunity_end_date,
                        o.city AS opportunity_city,
                        o.province AS opportunity_province,
                        o.work_arrangement,
                        o.available_positions,
                        p.id AS programme_id,
                        p.name AS programme_name,
                        p.type AS programme_type,
                        p.duration AS programme_duration,
                        c.id AS cohort_id,
                        c.name AS cohort_name,
                        c.start_date AS cohort_start_date,
                        c.end_date AS cohort_end_date
                 FROM applications a
                 INNER JOIN users u ON u.id = a.candidate_id
                 LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
                 LEFT JOIN opportunities o ON o.id = a.opportunity_id
                 LEFT JOIN programmes p ON p.id = o.programme_id
                 LEFT JOIN cohorts c ON c.id = o.cohort_id
                 WHERE a.id = ?
                 LIMIT 1",
                'i',
                [$id]
            );
        } catch (Exception $e) {
            // Tables may not exist yet (migrations not run)
            return null;
        }
    }

    /**
     * Get application status history for admin view.
     *
     * @param int $applicationId
     * @return array
     */
    public static function statusHistory(int $applicationId): array
    {
        try {
            return Database::fetchAll(
                "SELECT ash.*,
                        CONCAT(u.first_name, ' ', u.last_name) AS changed_by_name
                 FROM application_status_history ash
                 LEFT JOIN users u ON u.id = ash.changed_by
                 WHERE ash.application_id = ?
                 ORDER BY ash.created_at ASC",
                'i',
                [$applicationId]
            );
        } catch (Exception $e) {
            // Table may not exist yet (migration not run)
            return [];
        }
    }

    /**
     * Update application status with history tracking.
     * Uses a database transaction to ensure consistency.
     *
     * @param int         $applicationId
     * @param string      $newStatus
     * @param int|null    $adminId    Admin user ID making the change
     * @param string|null $reason     Optional reason for the change
     * @return bool
     * @throws RuntimeException on invalid status or failure
     */
    public static function updateStatus(int $applicationId, string $newStatus, ?int $adminId = null, ?string $reason = null): bool
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new RuntimeException('Invalid application status: ' . $newStatus);
        }

        $app = self::find($applicationId);
        if (!$app) {
            throw new RuntimeException('Application not found.');
        }

        $currentStatus = $app['status'];

        if ($currentStatus === $newStatus) {
            return false;
        }

        if (!self::isValidTransition($currentStatus, $newStatus)) {
            throw new RuntimeException("Invalid status transition: {$currentStatus} → {$newStatus}");
        }

        // Make sure the live `applications.status` column supports every
        // pipeline status. If database/update_application_statuses.sql has
        // not been imported yet, the migration (ENUM widening + legacy remap
        // + audit table) is applied automatically here.
        try {
            self::ensureStatusColumnSupport();
        } catch (Exception $e) {
            error_log('[Application] Status column auto-migration failed: ' . $e->getMessage());
            throw new RuntimeException(
                'The applications.status column could not be updated to support the status "'
                . self::label($newStatus) . '" automatically (' . $e->getMessage()
                . '). Please import database/update_application_statuses.sql in phpMyAdmin, then try again.'
            );
        }

        try {
            Database::beginTransaction();

            // ---- 1. Core status update ----
            Database::execute(
                "UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?",
                'si',
                [$newStatus, $applicationId]
            );

            // ---- 2. Audit trail (best-effort) ----
            self::recordStatusHistory($applicationId, $currentStatus, $newStatus, $adminId, $reason);

            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollback();
            throw new RuntimeException('Failed to update application status: ' . $e->getMessage());
        }
    }

    /**
     * Best-effort insert into application_status_history.
     *
     * The history table lives in its own migration
     * (database/application_status_history.sql) and may be missing, or use
     * an older column layout (actor_id/reason/changed_at), on some installs.
     * This detects the table and its columns at runtime, records the change
     * when possible, and never breaks the status update itself.
     *
     * @param int         $applicationId
     * @param string      $previousStatus
     * @param string      $newStatus
     * @param int|null    $adminId
     * @param string|null $reason
     * @return void
     */
    public static function recordStatusHistory(
        int $applicationId,
        string $previousStatus,
        string $newStatus,
        ?int $adminId,
        ?string $reason
    ): void {
        try {
            $tableExists = Database::fetchOne("SHOW TABLES LIKE 'application_status_history'");
            if (!$tableExists) {
                error_log('[Application] application_status_history table is missing — run database/application_status_history.sql');
                return;
            }

            $columns = Database::fetchAll("SHOW COLUMNS FROM application_status_history");
            $names   = array_map(
                static fn (array $row): string => (string) ($row['Field'] ?? ''),
                $columns
            );

            if (in_array('changed_by', $names, true)) {
                // Canonical Stage 9 layout (created_at defaults to CURRENT_TIMESTAMP).
                Database::execute(
                    "INSERT INTO application_status_history
                        (application_id, previous_status, new_status, changed_by, change_reason)
                     VALUES (?, ?, ?, ?, ?)",
                    'iisis',
                    [$applicationId, $previousStatus, $newStatus, $adminId, $reason]
                );
            } elseif (in_array('actor_id', $names, true)) {
                // Legacy layout with actor_id/reason/changed_at columns.
                Database::execute(
                    "INSERT INTO application_status_history
                        (application_id, previous_status, new_status, actor_id, reason, changed_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    'iiiss',
                    [$applicationId, $previousStatus, $newStatus, $adminId, $reason]
                );
            } else {
                error_log('[Application] application_status_history has an unexpected column layout — history entry skipped.');
            }
        } catch (Exception $e) {
            // The audit trail must never break the status change.
            error_log('[Application] Status history entry skipped: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the live `applications.status` column supports every status in
     * Application::STATUSES, and that the application_status_history audit
     * table exists. Applies the database/update_application_statuses.sql
     * migration automatically when it has not been imported yet:
     *   1. widens the ENUM to include the legacy AND new values (a value can
     *      only be assigned to an ENUM column once it exists in the column),
     *   2. remaps legacy values to their pipeline replacements,
     *   3. narrows the ENUM down to the final pipeline set,
     *   4. creates the audit table when missing.
     *
     * @return void
     * @throws RuntimeException when the automatic migration fails
     */
    public static function ensureStatusColumnSupport(): void
    {
        if (self::$statusColumnVerified === true) {
            return;
        }

        // ---- Inspect the live column (best-effort) ----
        try {
            $column = Database::fetchOne(
                "SELECT COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'applications'
                   AND COLUMN_NAME  = 'status'
                 LIMIT 1"
            );
        } catch (Exception $e) {
            // Cannot inspect — let the UPDATE proceed and surface any real error.
            error_log('[Application] Status column inspection skipped: ' . $e->getMessage());
            return;
        }

        if (!$column) {
            return;
        }

        $columnType = (string) $column['COLUMN_TYPE'];

        $missing = [];
        foreach (self::STATUSES as $status) {
            if (!str_contains($columnType, "'" . $status . "'")) {
                $missing[] = $status;
            }
        }

        if ($missing !== []) {
            // New App::STATUSES may collide with historic legacy values.
            // 'waitlisted' is now a first-class status, so only remap legacies that
            // are NOT in the final set (and never remap a missing value onto itself).
            $legacyRemaps = [
                'eligibility_review' => 'under_review',
                'screened'           => 'shortlisted',
                'interview'          => 'interview_scheduled',
                'expired'            => 'rejected',
            ];

            $quote = static fn (string $status): string => "'" . $status . "'";

            // ---- 1. Widen the ENUM to include the legacy AND new values.
            //         Remapping to a value not yet in the ENUM would fail in
            //         strict mode (or truncate to '' otherwise). ----
            $union = array_values(array_unique(array_merge(array_keys($legacyRemaps), self::STATUSES)));
            Database::query(
                "ALTER TABLE `applications`
                 MODIFY COLUMN `status` ENUM(" . implode(',', array_map($quote, $union)) . ")
                 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft'"
            );

            // ---- 2. Remap legacy values to their pipeline replacements. ----
            foreach ($legacyRemaps as $legacy => $replacement) {
                Database::execute(
                    "UPDATE applications SET status = ? WHERE status = ?",
                    'ss',
                    [$replacement, $legacy]
                );
            }

            // ---- 3. Narrow the ENUM down to the final pipeline set. ----
            Database::query(
                "ALTER TABLE `applications`
                 MODIFY COLUMN `status` ENUM(" . implode(',', array_map($quote, self::STATUSES)) . ")
                 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft'"
            );

            error_log('[Application] Auto-migrated applications.status ENUM (added: ' . implode(', ', $missing) . ').');
        }

        // ---- Ensure the status-history audit table exists (best-effort) ----
        try {
            $historyTable = Database::fetchOne("SHOW TABLES LIKE 'application_status_history'");
            if (!$historyTable) {
                Database::query(self::historyTableCreateSql());
                error_log('[Application] Created missing application_status_history table.');
            }
        } catch (Exception $e) {
            // The audit trail must never break the status change.
            error_log('[Application] application_status_history creation skipped: ' . $e->getMessage());
        }

        self::$statusColumnVerified = true;
    }

    /**
     * Canonical CREATE TABLE statement for application_status_history
     * (mirrors database/application_status_history.sql).
     *
     * @return string
     */
    private static function historyTableCreateSql(): string
    {
        return <<<SQL
CREATE TABLE IF NOT EXISTS `application_status_history` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id`  INT UNSIGNED NOT NULL,
  `previous_status` VARCHAR(50)  NULL,
  `new_status`      VARCHAR(50)  NOT NULL,
  `changed_by`      INT UNSIGNED NULL,
  `change_reason`   VARCHAR(500) NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_app_status_history_application` (`application_id`, `created_at`),
  KEY `idx_app_status_history_new_status` (`new_status`),
  KEY `idx_app_status_history_changed_by` (`changed_by`),
  KEY `idx_app_status_history_created` (`created_at`),
  CONSTRAINT `fk_app_status_history_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_app_status_history_changed_by`
    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    }

    /**
     * Validate if a status transition is allowed.
     *
     * @param string $fromStatus
     * @param string $toStatus
     * @return bool
     */
    public static function isValidTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        // Both statuses must be defined.
        if (!in_array($fromStatus, self::STATUSES, true)
            || !in_array($toStatus, self::STATUSES, true)) {
            return false;
        }

        // Candidate-owned states ('draft', 'submitted') can never be
        // re-entered from the admin status picker — submission itself is
        // handled by the candidate application flow.
        if (in_array($toStatus, ['draft', 'submitted'], true)) {
            return false;
        }

        // Admins may freely pick any of the recruitment pipeline
        // statuses (Under Review … On Hold) from any current state.
        return true;
    }

    /**
     * Get application documents for admin view.
     *
     * @param int $applicationId
     * @return array
     */
    public static function documents(int $applicationId): array
    {
        try {
            return Database::fetchAll(
                "SELECT ad.*
                 FROM application_documents ad
                 WHERE ad.application_id = ?
                 ORDER BY ad.document_type ASC",
                'i',
                [$applicationId]
            );
        } catch (Exception $e) {
            // Table may not exist yet (migration not run)
            return [];
        }
    }

    /**
     * Get application responses (questions and answers) for admin view.
     *
     * @param int $applicationId
     * @return array
     */
    public static function responses(int $applicationId): array
    {
        try {
            return Database::fetchAll(
                "SELECT ar.response, oq.question_text, oq.question_type, oq.section, oq.is_required, oq.sort_order
                 FROM application_responses ar
                 INNER JOIN opportunity_questions oq ON oq.id = ar.question_id
                 WHERE ar.application_id = ?
                 ORDER BY oq.section ASC, oq.sort_order ASC",
                'i',
                [$applicationId]
            );
        } catch (Exception $e) {
            // Table may not exist yet (migration not run)
            return [];
        }
    }

    /**
     * Get opportunity eligibility information for an application.
     *
     * @param int $opportunityId
     * @return array|null
     */
    public static function opportunityEligibility(int $opportunityId): ?array
    {
        try {
            return Database::fetchOne(
                "SELECT * FROM opportunity_eligibility WHERE opportunity_id = ? LIMIT 1",
                'i',
                [$opportunityId]
            );
        } catch (Exception $e) {
            // Table may not exist yet (migration not run)
            return null;
        }
    }

    /**
     * Get candidate qualifications for admin view.
     *
     * @param int $candidateId
     * @return array
     */
    public static function candidateQualifications(int $candidateId): array
    {
        try {
            return Database::fetchAll(
                "SELECT q.*
                 FROM qualifications q
                 WHERE q.user_id = ?
                 ORDER BY q.year_completed DESC, q.id ASC",
                'i',
                [$candidateId]
            );
        } catch (Exception $e) {
            // Table may not exist yet (migration not run)
            return [];
        }
    }

    /**
     * Get candidate skills for admin view.
     * Returns skills separated by category (technical and soft).
     *
     * @param int $candidateId
     * @return array ['technical' => [], 'soft' => []]
     */
    public static function candidateSkills(int $candidateId): array
    {
        $skills = ['technical' => [], 'soft' => []];

        try {
            $rows = Database::fetchAll(
                "SELECT s.name, s.category, cs.proficiency
                 FROM candidate_skills cs
                 INNER JOIN skills s ON s.id = cs.skill_id
                 WHERE cs.user_id = ?
                 ORDER BY s.category ASC, s.name ASC",
                'i',
                [$candidateId]
            );

            foreach ($rows as $row) {
                $category = $row['category'] ?? 'technical';
                if (isset($skills[$category])) {
                    $skills[$category][] = $row;
                }
            }
        } catch (Exception $e) {
            // Table may not exist yet (migration not run)
        }

        return $skills;
    }

    /**
     * Get candidate work experience for admin view.
     *
     * @param int $candidateId
     * @return array
     */
    public static function candidateWorkExperience(int $candidateId): array
    {
        try {
            return Database::fetchAll(
                "SELECT we.*
                 FROM work_experience we
                 WHERE we.user_id = ?
                 ORDER BY we.is_current DESC, we.start_date DESC, we.id ASC",
                'i',
                [$candidateId]
            );
        } catch (Exception $e) {
            // Table may not exist yet (migration not run)
            return [];
        }
    }

    /**
     * Get candidate documents for admin view.
     * This fetches documents associated with the candidate (user-level documents).
     *
     * @param int $candidateId
     * @return array
     */
    public static function candidateDocuments(int $candidateId): array
    {
        try {
            return Database::fetchAll(
                "SELECT d.*
                 FROM documents d
                 WHERE d.user_id = ?
                 ORDER BY d.document_type ASC, d.created_at DESC",
                'i',
                [$candidateId]
            );
        } catch (Exception $e) {
            // Table may not exist yet (migration not run)
            return [];
        }
    }
}
