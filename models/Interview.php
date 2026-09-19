<?php
/**
 * ================================================
 * INVESTHOOD IT - Interview Model
 * ================================================
 * CRUD operations for interviews, feedback, and status management.
 */

class Interview
{
    /** @var string[] Valid interview statuses */
    public const STATUSES = ['scheduled', 'confirmed', 'rescheduled', 'completed', 'cancelled', 'no_show'];

    /** @var string[] Valid interview types */
    public const TYPES = ['online', 'in_person', 'phone'];

    /** @var string[] Valid recommendation options */
    public const RECOMMENDATIONS = ['strongly_recommended', 'recommended', 'consider', 'not_recommended'];

    /** @var string[] Valid programme suitability options */
    public const SUITABILITY = ['excellent', 'good', 'fair', 'poor'];

    /** @var string[] Allowed status transitions */
    public const TRANSITIONS = [
        'scheduled'   => ['confirmed', 'rescheduled', 'completed', 'cancelled', 'no_show'],
        'confirmed'   => ['rescheduled', 'completed', 'cancelled', 'no_show'],
        'rescheduled' => ['confirmed', 'completed', 'cancelled', 'no_show'],
        'completed'   => [],
        'cancelled'   => ['scheduled'],
        'no_show'     => ['scheduled'],
    ];

    /** @var string[] Human-friendly status labels */
    public const STATUS_LABELS = [
        'scheduled'   => 'Scheduled',
        'confirmed'   => 'Confirmed',
        'rescheduled' => 'Rescheduled',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
        'no_show'     => 'No Show',
    ];

    /** @var string[] Status badge tones */
    public const STATUS_BADGES = [
        'scheduled'   => 'primary',
        'confirmed'   => 'success',
        'rescheduled' => 'amber',
        'completed'   => 'green',
        'cancelled'   => 'danger',
        'no_show'     => 'danger',
    ];

    /** @var string[] Human-friendly type labels */
    public const TYPE_LABELS = [
        'online'     => 'Online',
        'in_person'  => 'In-Person',
        'phone'      => 'Phone',
    ];

    /** @var string[] Human-friendly recommendation labels */
    public const RECOMMENDATION_LABELS = [
        'strongly_recommended' => 'Strongly Recommended',
        'recommended'          => 'Recommended',
        'consider'             => 'Consider',
        'not_recommended'      => 'Not Recommended',
    ];

    /** @var string[] Human-friendly suitability labels */
    public const SUITABILITY_LABELS = [
        'excellent' => 'Excellent',
        'good'      => 'Good',
        'fair'      => 'Fair',
        'poor'      => 'Poor',
    ];

    /** @var string[] Valid feedback interview outcome / decision values */
    public const OUTCOMES = ['selected', 'waitlisted', 'rejected', 'further_review', 'another_interview'];

    /** @var string[] Human-friendly outcome labels */
    public const OUTCOME_LABELS = [
        'selected'          => 'Selected',
        'waitlisted'        => 'Waitlisted',
        'rejected'          => 'Rejected',
        'further_review'    => 'Further Review',
        'another_interview' => 'Recommended for Another Interview',
    ];

    /** @var string[] Outcome badge tones */
    public const OUTCOME_BADGES = [
        'selected'          => 'green',
        'waitlisted'        => 'amber',
        'rejected'          => 'danger',
        'further_review'    => 'primary',
        'another_interview' => 'primary',
    ];

    /** @var array<string,string> Interview outcome => application status mapping */
    public const OUTCOME_APPLICATION_STATUS = [
        'selected'          => 'selected',
        'waitlisted'        => 'waitlisted',
        'rejected'          => 'rejected',
        'further_review'    => 'under_review',
        'another_interview' => 'interview_required',
    ];

    // --------------------------------------------------------
    // HELPERS
    // --------------------------------------------------------

    public static function label(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    public static function badgeTone(?string $status): string
    {
        return self::STATUS_BADGES[$status ?? ''] ?? 'muted';
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPE_LABELS[$type ?? ''] ?? ucfirst(str_replace('_', ' ', (string) $type));
    }

    public static function outcomeLabel(?string $outcome): string
    {
        return self::OUTCOME_LABELS[$outcome ?? ''] ?? ($outcome ? ucfirst(str_replace('_', ' ', (string) $outcome)) : '—');
    }

    public static function outcomeBadgeTone(?string $outcome): string
    {
        return self::OUTCOME_BADGES[$outcome ?? ''] ?? 'muted';
    }

    public static function outcomeApplicationStatus(?string $outcome): ?string
    {
        return self::OUTCOME_APPLICATION_STATUS[$outcome ?? ''] ?? null;
    }

    public static function recommendationLabel(?string $rec): string
    {
        return self::RECOMMENDATION_LABELS[$rec ?? ''] ?? ucfirst(str_replace('_', ' ', (string) $rec));
    }

    public static function suitabilityLabel(?string $suit): string
    {
        return self::SUITABILITY_LABELS[$suit ?? ''] ?? ucfirst((string) $suit);
    }

    // --------------------------------------------------------
    // LISTING & SEARCH
    // --------------------------------------------------------

    public static function adminList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $types = '';
        $params = [];

        // Feedback enrichment (tolerant: read-only, never migrates on list page).
        $fbCols = self::feedbackColumns();
        $fbTable = in_array('interview_id', $fbCols, true);
        $fbHas = static function (string $c) use ($fbCols): bool { return in_array($c, $fbCols, true); };
        $fbSelect = '';
        $fbJoin = '';
        if ($fbTable) {
            $adminExpr = $fbHas('admin_id') ? 'fb.admin_id' : 'fb.interviewer_id';
            $outcomeExpr = $fbHas('outcome') && $fbHas('recommendation')
                ? 'COALESCE(fb.outcome, fb.recommendation)'
                : ($fbHas('outcome') ? 'fb.outcome' : ($fbHas('recommendation') ? 'fb.recommendation' : 'NULL'));
            $ratingExpr = $fbHas('overall_rating') ? 'fb.overall_rating' : 'NULL';
            $dateExpr = $fbHas('submitted_at') ? 'fb.submitted_at' : ($fbHas('created_at') ? 'fb.created_at' : 'NULL');
            $fbSelect = ", {$outcomeExpr} AS feedback_outcome, {$ratingExpr} AS feedback_rating,"
                . " {$dateExpr} AS feedback_submitted_at,"
                . " TRIM(CONCAT(COALESCE(fbu.first_name, ''), ' ', COALESCE(fbu.last_name, ''))) AS feedback_admin_name";
            $fbJoin = " LEFT JOIN interview_feedback fb ON fb.interview_id = i.id"
                . " LEFT JOIN users fbu ON fbu.id = {$adminExpr}";
        }

        if (!empty($filters['search'])) {
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.application_reference LIKE ? OR o.title LIKE ? OR p.name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $types .= 'ssssss';
            $params = array_merge($params, [$search, $search, $search, $search, $search, $search]);
        }
        if (!empty($filters['status'])) {
            $where[] = "i.status = ?";
            $types .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['interview_type'])) {
            $where[] = "i.interview_type = ?";
            $types .= 's';
            $params[] = $filters['interview_type'];
        }
        if (!empty($filters['programme_id'])) {
            $where[] = "p.id = ?";
            $types .= 'i';
            $params[] = (int) $filters['programme_id'];
        }
        if (!empty($filters['cohort_id'])) {
            $where[] = "c.id = ?";
            $types .= 'i';
            $params[] = (int) $filters['cohort_id'];
        }
        if (!empty($filters['opportunity_id'])) {
            $where[] = "o.id = ?";
            $types .= 'i';
            $params[] = (int) $filters['opportunity_id'];
        }
        if (!empty($filters['interviewer_id'])) {
            $where[] = "i.interviewer_id = ?";
            $types .= 'i';
            $params[] = (int) $filters['interviewer_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "i.interview_date >= ?";
            $types .= 's';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "i.interview_date <= ?";
            $types .= 's';
            $params[] = $filters['date_to'];
        }
        // Virtual "Upcoming" filter: active statuses that are still to happen
        if (!empty($filters['upcoming_only'])) {
            $where[] = self::stillUpcomingSql('i');
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN users u ON u.id = a.candidate_id
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             INNER JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             LEFT JOIN users iu ON iu.id = i.interviewer_id
             $whereSql",
            $types, $params
        );
        $total = (int) ($countRow['cnt'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT i.*, a.application_reference, a.status AS application_status,
                    a.candidate_id,
                    u.first_name AS candidate_first_name, u.last_name AS candidate_last_name,
                    u.email AS candidate_email, u.phone AS candidate_phone,
                    o.id AS opportunity_id, o.title AS opportunity_title,
                    p.id AS programme_id, p.name AS programme_name,
                    c.id AS cohort_id, c.name AS cohort_name,
                    iu.first_name AS interviewer_first_name, iu.last_name AS interviewer_last_name,
                    iu.email AS interviewer_email, "
            . ($fbTable ? "(SELECT COUNT(*) FROM interview_feedback f WHERE f.interview_id = i.id) AS has_feedback" : "0 AS has_feedback")
            . " {$fbSelect}
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN users u ON u.id = a.candidate_id
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             INNER JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             LEFT JOIN users iu ON iu.id = i.interviewer_id{$fbJoin}
             {$whereSql}
             ORDER BY i.interview_date DESC, i.start_time DESC
             LIMIT {$perPage} OFFSET {$offset}";
        $records = Database::fetchAll($sql, $types, $params);

        return ['records' => $records, 'total' => $total, 'pages' => $pages];
    }

    public static function countByStatus(): array
    {
        $results = Database::fetchAll("SELECT status, COUNT(*) AS cnt FROM interviews GROUP BY status");
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($results as $r) {
            if (isset($counts[$r['status']])) {
                $counts[$r['status']] = (int) $r['cnt'];
            }
        }
        return $counts;
    }

    public static function upcoming(int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT i.*, a.application_reference,
                    u.first_name AS candidate_first_name, u.last_name AS candidate_last_name,
                    u.email AS candidate_email,
                    o.title AS opportunity_title, p.name AS programme_name,
                    c.name AS cohort_name,
                    iu.first_name AS interviewer_first_name, iu.last_name AS interviewer_last_name
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN users u ON u.id = a.candidate_id
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             INNER JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             LEFT JOIN users iu ON iu.id = i.interviewer_id
             WHERE i.interview_date >= CURDATE()
               AND i.status IN ('scheduled','confirmed','rescheduled')
             ORDER BY i.interview_date ASC, i.start_time ASC
             LIMIT ?",
            'i', [$limit]
        );
    }

    public static function recent(int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT i.*, a.application_reference,
                    u.first_name AS candidate_first_name, u.last_name AS candidate_last_name,
                    u.email AS candidate_email,
                    o.title AS opportunity_title, p.name AS programme_name,
                    c.name AS cohort_name,
                    iu.first_name AS interviewer_first_name, iu.last_name AS interviewer_last_name
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN users u ON u.id = a.candidate_id
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             INNER JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             LEFT JOIN users iu ON iu.id = i.interviewer_id
             WHERE i.status IN ('completed','cancelled','no_show')
             ORDER BY i.updated_at DESC
             LIMIT ?",
            'i', [$limit]
        );
    }

    // --------------------------------------------------------
    // DASHBOARD / AGGREGATES
    // --------------------------------------------------------

    /**
     * SQL fragment matching interviews that are still to take place,
     * based on the current date AND time.
     */
    private static function stillUpcomingSql(string $alias = 'i'): string
    {
        return "{$alias}.status IN ('scheduled','confirmed','rescheduled')
                AND ({$alias}.interview_date > CURDATE()
                     OR ({$alias}.interview_date = CURDATE() AND {$alias}.end_time >= CURTIME()))";
    }

    /**
     * Real number of upcoming interviews (still to happen, nothing finished).
     */
    public static function countUpcoming(): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM interviews i WHERE " . self::stillUpcomingSql('i')
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Number of interviews (any status) booked on a specific date.
     */
    public static function countOnDate(string $date): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM interviews i WHERE i.interview_date = ?",
            's', [$date]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Per-day interview totals between two dates (cancelled interviews excluded).
     *
     * @return array<string,int> Map of 'Y-m-d' => count
     */
    public static function countsByDateRange(string $startDate, string $endDate): array
    {
        $rows = Database::fetchAll(
            "SELECT i.interview_date, COUNT(*) AS cnt
             FROM interviews i
             WHERE i.interview_date BETWEEN ? AND ?
               AND i.status <> 'cancelled'
             GROUP BY i.interview_date
             ORDER BY i.interview_date ASC",
            'ss', [$startDate, $endDate]
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['interview_date']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * Aggregate interview totals for a date window (single query).
     *
     * @return array{total:int,completed:int,upcoming:int,cancelled:int,no_show:int}
     */
    public static function rangeSummary(string $startDate, string $endDate): array
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(i.status = 'completed'), 0) AS completed,
                    COALESCE(SUM(i.status IN ('scheduled','confirmed','rescheduled')), 0) AS upcoming,
                    COALESCE(SUM(i.status = 'cancelled'), 0) AS cancelled,
                    COALESCE(SUM(i.status = 'no_show'), 0) AS no_show
             FROM interviews i
             WHERE i.interview_date BETWEEN ? AND ?",
            'ss', [$startDate, $endDate]
        );

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'upcoming'  => (int) ($row['upcoming'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'no_show'   => (int) ($row['no_show'] ?? 0),
        ];
    }

    /**
     * Upcoming interviews (still to happen) with every related record needed
     * for dashboard display. Ordered with the nearest interview first.
     *
     * @param string|null $fromDate Optional lower date bound (Y-m-d)
     * @param string|null $toDate   Optional upper date bound (Y-m-d)
     */
    public static function dashboardUpcoming(?string $fromDate = null, ?string $toDate = null, int $limit = 9): array
    {
        $sql = "SELECT i.id, i.application_id, i.interview_date, i.start_time, i.end_time,
                       i.interview_type, i.status, i.location, i.reschedule_count,
                       a.application_reference,
                       u.first_name AS candidate_first_name, u.last_name AS candidate_last_name,
                       u.email AS candidate_email,
                       o.id AS opportunity_id, o.title AS opportunity_title,
                       p.id AS programme_id, p.name AS programme_name,
                       c.id AS cohort_id, c.name AS cohort_name,
                       iu.first_name AS interviewer_first_name, iu.last_name AS interviewer_last_name,
                       iu.email AS interviewer_email
                FROM interviews i
                INNER JOIN applications a ON a.id = i.application_id
                INNER JOIN users u ON u.id = a.candidate_id
                INNER JOIN opportunities o ON o.id = a.opportunity_id
                INNER JOIN programmes p ON p.id = o.programme_id
                LEFT JOIN cohorts c ON c.id = o.cohort_id
                LEFT JOIN users iu ON iu.id = i.interviewer_id
                WHERE " . self::stillUpcomingSql('i');

        $types  = '';
        $params = [];

        if ($fromDate !== null) {
            $sql .= " AND i.interview_date >= ?";
            $types .= 's';
            $params[] = $fromDate;
        }
        if ($toDate !== null) {
            $sql .= " AND i.interview_date <= ?";
            $types .= 's';
            $params[] = $toDate;
        }

        $sql .= " ORDER BY i.interview_date ASC, i.start_time ASC LIMIT ?";
        $types .= 'i';
        $params[] = max(1, $limit);

        return Database::fetchAll($sql, $types, $params);
    }

    /**
     * Every interview booked on a given date (all statuses), earliest first.
     */
    public static function dashboardOnDate(string $date, int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT i.id, i.application_id, i.interview_date, i.start_time, i.end_time,
                    i.interview_type, i.status, i.location,
                    a.application_reference,
                    u.first_name AS candidate_first_name, u.last_name AS candidate_last_name,
                    u.email AS candidate_email,
                    o.id AS opportunity_id, o.title AS opportunity_title,
                    p.id AS programme_id, p.name AS programme_name,
                    c.id AS cohort_id, c.name AS cohort_name,
                    iu.first_name AS interviewer_first_name, iu.last_name AS interviewer_last_name,
                    iu.email AS interviewer_email
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN users u ON u.id = a.candidate_id
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             INNER JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             LEFT JOIN users iu ON iu.id = i.interviewer_id
             WHERE i.interview_date = ?
             ORDER BY i.start_time ASC
             LIMIT ?",
            'si', [$date, max(1, $limit)]
        );
    }

    // --------------------------------------------------------
    // FIND
    // --------------------------------------------------------

    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT i.*, a.application_reference, a.status AS application_status,
                    a.candidate_id,
                    u.first_name AS candidate_first_name, u.last_name AS candidate_last_name,
                    u.email AS candidate_email, u.phone AS candidate_phone,
                    o.id AS opportunity_id, o.title AS opportunity_title,
                    p.id AS programme_id, p.name AS programme_name,
                    c.id AS cohort_id, c.name AS cohort_name,
                    iu.first_name AS interviewer_first_name, iu.last_name AS interviewer_last_name,
                    iu.email AS interviewer_email,
                    cu.first_name AS created_by_first_name, cu.last_name AS created_by_last_name,
                    ca.first_name AS cancelled_by_first_name, ca.last_name AS cancelled_by_last_name
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN users u ON u.id = a.candidate_id
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             INNER JOIN programmes p ON p.id = o.programme_id
             LEFT JOIN cohorts c ON c.id = o.cohort_id
             LEFT JOIN users iu ON iu.id = i.interviewer_id
             LEFT JOIN users cu ON cu.id = i.created_by
             LEFT JOIN users ca ON ca.id = i.cancelled_by
             WHERE i.id = ?
             LIMIT 1",
            'i', [$id]
        );
    }

    public static function findByApplication(int $applicationId): array
    {
        return Database::fetchAll(
            "SELECT i.*,
                    iu.first_name AS interviewer_first_name, iu.last_name AS interviewer_last_name,
                    iu.email AS interviewer_email
             FROM interviews i
             LEFT JOIN users iu ON iu.id = i.interviewer_id
             WHERE i.application_id = ?
             ORDER BY i.interview_date DESC, i.start_time DESC",
            'i', [$applicationId]
        );
    }
    // --------------------------------------------------------
    // CANDIDATE-FACING QUERIES
    // --------------------------------------------------------
    // Interviews are reached through the candidate's applications:
    // users (candidate) -> applications.candidate_id -> interviews.application_id
    // Only candidate-appropriate columns are selected. Internal
    // recruitment data (feedback, ratings, cancellation metadata,
    // created_by / cancelled_by) is never exposed here.

    private const CANDIDATE_SELECT = '
        SELECT i.id, i.application_id, i.interview_date, i.start_time, i.end_time,
               i.interview_type, i.location, i.status, i.notes, i.reschedule_count,
               i.previous_date, i.previous_start_time, i.previous_end_time,
               i.created_at, i.updated_at,
               a.application_reference, a.status AS application_status,
               o.id AS opportunity_id, o.title AS opportunity_title,
               p.id AS programme_id, p.name AS programme_name,
               c.id AS cohort_id, c.name AS cohort_name,
               iu.first_name AS interviewer_first_name, iu.last_name AS interviewer_last_name,
               (SELECT COUNT(*) FROM interview_feedback f WHERE f.interview_id = i.id) AS has_feedback
        FROM interviews i
        INNER JOIN applications a ON a.id = i.application_id
        INNER JOIN opportunities o ON o.id = a.opportunity_id
        INNER JOIN programmes p ON p.id = o.programme_id
        LEFT JOIN cohorts c ON c.id = o.cohort_id
        LEFT JOIN users iu ON iu.id = i.interviewer_id';

    /**
     * Get all interviews for a candidate (via their applications),
     * most recent first.
     *
     * @param int $candidateId Authenticated user id
     * @return array
     */
    public static function forCandidate(int $candidateId): array
    {
        return Database::fetchAll(
            self::CANDIDATE_SELECT . '
             WHERE a.candidate_id = ?
             ORDER BY i.interview_date DESC, i.start_time DESC, i.id DESC',
            'i', [$candidateId]
        );
    }

    /**
     * Get the candidate's genuinely upcoming interviews (nearest first).
     * Only active statuses (scheduled/confirmed/rescheduled) whose
     * scheduled end time has not passed are returned. Completed,
     * cancelled and no-show interviews are never included.
     *
     * @param int $candidateId Authenticated user id
     * @param int $limit
     * @return array
     */
    public static function candidateUpcoming(int $candidateId, int $limit = 1): array
    {
        return Database::fetchAll(
            self::CANDIDATE_SELECT . "
             WHERE a.candidate_id = ?
               AND i.status IN ('scheduled', 'confirmed', 'rescheduled')
               AND (i.interview_date > CURDATE()
                    OR (i.interview_date = CURDATE() AND i.end_time >= CURTIME()))
             ORDER BY i.interview_date ASC, i.start_time ASC, i.id ASC
             LIMIT ?",
            'ii', [$candidateId, $limit]
        );
    }


    /**
     * Interview statistics for a candidate, used by dashboard summary cards.
     *
     * Returns counts keyed by status plus:
     *   - upcoming: active interviews whose date/time is still in the future
     *   - total:    all interviews for the candidate
     *
     * @param int $candidateId Authenticated user id
     * @return array
     */
    public static function candidateStatusCounts(int $candidateId): array
    {
        $rows = Database::fetchAll(
            'SELECT i.status, COUNT(*) AS cnt
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             WHERE a.candidate_id = ?
             GROUP BY i.status',
            'i', [$candidateId]
        );

        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row['cnt'];
            }
        }

        // Upcoming = active status AND scheduled slot still in the future
        $upcomingRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             WHERE a.candidate_id = ?
               AND i.status IN ('scheduled', 'confirmed', 'rescheduled')
               AND (i.interview_date > CURDATE()
                    OR (i.interview_date = CURDATE() AND i.end_time >= CURTIME()))",
            'i', [$candidateId]
                );

        // Total = sum of all status counts (computed BEFORE the 'upcoming'
        // pseudo-count is added, so upcoming interviews are not double counted).
        $counts['total']   = array_sum($counts);
        $counts['upcoming'] = (int) ($upcomingRow['cnt'] ?? 0);

        return $counts;
    }

    /**
     * Find a single interview for a candidate with an ownership check.
     * The join on applications.candidate_id guarantees the interview
     * belongs to the authenticated candidate, regardless of the id
     * passed in the URL.
     *
     * @param int $interviewId
     * @param int $candidateId Authenticated user id
     * @return array|null
     */
    public static function findForCandidate(int $interviewId, int $candidateId): ?array
    {
        return Database::fetchOne(
            self::CANDIDATE_SELECT . '
             WHERE i.id = ?
               AND a.candidate_id = ?
             LIMIT 1',
            'ii', [$interviewId, $candidateId]
        );
    }

    // --------------------------------------------------------
    // CANDIDATE-FACING HELPERS
    // --------------------------------------------------------

    /**
     * Format a start/end time pair as a friendly range,
     * e.g. "10:00 AM – 11:00 AM".
     *
     * @param string|null $start
     * @param string|null $end
     * @return string
     */
    public static function timeRange(?string $start, ?string $end): string
    {
        if (empty($start) || empty($end)) {
            return '—';
        }
        $startTs = strtotime((string) $start);
        $endTs   = strtotime((string) $end);
        if ($startTs === false || $endTs === false) {
            return '—';
        }
        return date('g:i A', $startTs) . ' – ' . date('g:i A', $endTs);
    }

    /**
     * Friendly countdown label for an upcoming interview,
     * e.g. "In 4 days", "Tomorrow at 10:00 AM", "Today at 2:00 PM".
     *
     * @param string|null $date
     * @param string|null $startTime
     * @return string
     */
    public static function countdownLabel(?string $date, ?string $startTime): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $today        = new DateTime('today', new DateTimeZone(APP_TIMEZONE));
            $interviewDay = new DateTime((string) $date, new DateTimeZone(APP_TIMEZONE));
        } catch (Exception $ex) {
            return '';
        }

        $days = (int) $today->diff($interviewDay)->format('%r%a');
        $timeLabel = !empty($startTime) ? date('g:i A', (int) strtotime((string) $startTime)) : '';

        if ($days > 1) {
            return 'In ' . $days . ' days';
        }
        if ($days === 1) {
            return 'Tomorrow' . ($timeLabel !== '' ? ' at ' . $timeLabel : '');
        }
        if ($days === 0) {
            if ($timeLabel !== '') {
                $startTs = strtotime((string) $date . ' ' . (string) $startTime);
                if ($startTs !== false && $startTs > time()) {
                    return 'Today at ' . $timeLabel;
                }
                return 'In progress';
            }
            return 'Today';
        }

        $past = abs($days);
        return $past . ' day' . ($past === 1 ? '' : 's') . ' ago';
    }

    /**
     * Return the joinable meeting URL for an online interview, if the
     * stored location is a valid http(s) URL. Never invents a link.
     *
     * @param array $interview
     * @return string|null
     */
    public static function joinUrl(array $interview): ?string
    {
        if (($interview['interview_type'] ?? '') !== 'online') {
            return null;
        }

        $location = trim((string) ($interview['location'] ?? ''));
        if ($location === '') {
            return null;
        }
        if (!filter_var($location, FILTER_VALIDATE_URL)) {
            return null;
        }
        if (!preg_match('#^https?://#i', $location)) {
            return null;
        }

        return $location;
    }

    /**
     * Font Awesome icon for an interview type (format).
     *
     * @param string|null $type
     * @return string
     */
    public static function typeIcon(?string $type): string
    {
        return match ($type ?? '') {
            'in_person' => 'fa-map-marker-alt',
            'phone'     => 'fa-phone',
            default     => 'fa-video',
        };
    }


    // --------------------------------------------------------
    // CREATE
    // --------------------------------------------------------

    public static function create(array $data): int
    {
        Database::execute(
            "INSERT INTO interviews
             (application_id, interviewer_id, interview_date, start_time, end_time,
              interview_type, location, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'iisssssssi',
            [
                $data['application_id'],
                $data['interviewer_id'] ?? null,
                $data['interview_date'],
                $data['start_time'],
                $data['end_time'],
                $data['interview_type'],
                $data['location'] ?? null,
                $data['status'] ?? 'scheduled',
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
            ]
        );
        $id = Database::lastInsertId();
        self::logStatusHistory($id, null, $data['status'] ?? 'scheduled', $data['created_by'] ?? null, 'Interview scheduled');
        return $id;
    }

    // --------------------------------------------------------
    // UPDATE / RESCHEDULE
    // --------------------------------------------------------

    public static function update(int $id, array $data): bool
    {
        $allowed = ['interviewer_id', 'interview_date', 'start_time', 'end_time', 'interview_type', 'location', 'notes'];
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
            "UPDATE interviews SET " . implode(', ', $sets) . " WHERE id = ?",
            $types, $params
        ) >= 0;
    }

    public static function reschedule(int $id, array $data, int $adminId): bool
    {
        $interview = self::find($id);
        if (!$interview) {
            return false;
        }

        $sets = [
            "`previous_date` = ?",
            "`previous_start_time` = ?",
            "`previous_end_time` = ?",
            "`reschedule_count` = `reschedule_count` + 1",
            "`status` = 'rescheduled'",
            "`updated_at` = NOW()",
        ];
        $types = 'sss';
        $params = [
            $interview['interview_date'],
            $interview['start_time'],
            $interview['end_time'],
        ];

        $updatable = ['interview_date', 'start_time', 'end_time', 'interview_type', 'location', 'notes', 'interviewer_id'];
        foreach ($updatable as $col) {
            if (isset($data[$col])) {
                $sets[] = "`{$col}` = ?";
                $types .= 's';
                $params[] = $data[$col];
            }
        }

        $types .= 'i';
        $params[] = $id;

        $result = Database::execute(
            "UPDATE interviews SET " . implode(', ', $sets) . " WHERE id = ?",
            $types, $params
        ) >= 0;

        if ($result) {
            self::logStatusHistory($id, $interview['status'], 'rescheduled', $adminId, $data['reschedule_reason'] ?? null);
        }

        return $result;
    }

    // --------------------------------------------------------
    // STATUS MANAGEMENT
    // --------------------------------------------------------

    public static function changeStatus(int $id, string $newStatus, int $adminId, ?string $reason = null): bool
    {
        $interview = self::find($id);
        if (!$interview) {
            throw new RuntimeException('Interview not found.');
        }

        $currentStatus = $interview['status'];

        if ($currentStatus !== $newStatus) {
            $allowed = self::TRANSITIONS[$currentStatus] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                throw new RuntimeException(
                    'Invalid status transition from "' . self::label($currentStatus) . '" to "' . self::label($newStatus) . '".'
                );
            }
        }

        $sets = ["`status` = ?", "`updated_at` = NOW()"];
        $types = 's';
        $params = [$newStatus];

        if ($newStatus === 'cancelled') {
            $sets[] = "`cancellation_reason` = ?";
            $sets[] = "`cancelled_by` = ?";
            $sets[] = "`cancelled_at` = NOW()";
            $types .= 'si';
            $params[] = $reason;
            $params[] = $adminId;
        }

        $types .= 'i';
        $params[] = $id;

        $result = Database::execute(
            "UPDATE interviews SET " . implode(', ', $sets) . " WHERE id = ?",
            $types, $params
        ) >= 0;

        if ($result) {
            self::logStatusHistory($id, $currentStatus, $newStatus, $adminId, $reason);
        }

        return $result;
    }

    public static function cancel(int $id, int $adminId, string $reason): bool
    {
        return self::changeStatus($id, 'cancelled', $adminId, $reason);
    }

    // --------------------------------------------------------
    // STATUS HISTORY
    // --------------------------------------------------------

    public static function statusHistory(int $interviewId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    u.first_name AS changed_by_first_name, u.last_name AS changed_by_last_name
             FROM interview_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.interview_id = ?
             ORDER BY h.created_at DESC",
            'i', [$interviewId]
        );
    }

    private static function logStatusHistory(int $interviewId, ?string $previousStatus, string $newStatus, ?int $changedBy, ?string $reason = null): void
    {
        Database::execute(
            "INSERT INTO interview_status_history (interview_id, previous_status, new_status, changed_by, change_reason)
             VALUES (?, ?, ?, ?, ?)",
            'issis',
            [$interviewId, $previousStatus, $newStatus, $changedBy, $reason]
        );
    }

    // --------------------------------------------------------
    // FEEDBACK
    // --------------------------------------------------------

    public static function getFeedback(int $interviewId): ?array
    {
        try {
            $cols = self::feedbackColumns();
            if (!in_array('interview_id', $cols, true)) { return null; }
            $has  = static fn (string $c): bool => in_array($c, $cols, true);
            $adminJoin = 'fb.interviewer_id';
            if ($has('admin_id') && $has('interviewer_id')) { $adminJoin = 'COALESCE(fb.admin_id, fb.interviewer_id)'; }
            elseif ($has('admin_id')) { $adminJoin = 'fb.admin_id'; }
            return Database::fetchOne(
                "SELECT fb.*,
                        CONCAT(u.first_name, ' ', u.last_name) AS reviewer_name,
                        u.email AS reviewer_email
                 FROM interview_feedback fb
                 LEFT JOIN users u ON u.id = {$adminJoin}
                 WHERE fb.interview_id = ?
                 LIMIT 1",
                'i', [$interviewId]
            );
        } catch (Exception $e) {
            return null;
        }
    }

    public static function rawFeedbackColumns($tableExists = null): array
    {
        if (empty($tableExists)) { return []; }
        try {
            $rows = Database::fetchAll('SHOW COLUMNS FROM interview_feedback');
            return array_map(static fn (array $r): string => (string) ($r['Field'] ?? ''), $rows);
        } catch (Exception $e) { return []; }
    }

    public static function feedbackColumns(): array
    {
        try {
            $rows = Database::fetchAll('SHOW COLUMNS FROM interview_feedback');
            return array_map(static fn (array $r): string => (string) ($r['Field'] ?? ''), $rows);
        } catch (Exception $e) {
            return [];
        }
    }

    public static function ensureFeedbackColumns(): void
    {
        static $done = false;
        if ($done) { return; }
        try {
            $exists = Database::fetchOne("SHOW TABLES LIKE 'interview_feedback'");
            if (!$exists) {
                Database::query(
                    'CREATE TABLE IF NOT EXISTS `interview_feedback` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `interview_id` INT UNSIGNED NOT NULL,
                      `application_id` INT UNSIGNED NOT NULL,
                      `interviewer_id` INT UNSIGNED NULL,
                      `admin_id` INT UNSIGNED NULL,
                      `overall_rating` TINYINT NULL,
                      `technical_rating` TINYINT NULL,
                      `communication_rating` TINYINT NULL,
                      `problem_solving_rating` TINYINT NULL,
                      `programme_suitability` VARCHAR(50) NULL,
                      `strengths` TEXT NULL,
                      `areas_for_improvement` TEXT NULL,
                      `areas_of_concern` TEXT NULL,
                      `general_comments` TEXT NULL,
                      `general_feedback` TEXT NULL,
                      `internal_notes` TEXT NULL,
                      `recommendation` VARCHAR(50) NULL,
                      `outcome` VARCHAR(50) NULL,
                      `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uq_interview_feedback_interview` (`interview_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                $done = true;
                return;
            }
            $cols = self::rawFeedbackColumns(!empty($exists));
            $add = static function (string $col, string $def) use ($cols): void {
                if (!in_array($col, $cols, true)) {
                    Database::query("ALTER TABLE `interview_feedback` ADD COLUMN {$def}");
                }
            };
            $add('admin_id', '`admin_id` INT UNSIGNED NULL AFTER `interviewer_id`');
            $add('outcome', "`outcome` VARCHAR(50) NULL AFTER `recommendation`");
            $add('areas_of_concern', '`areas_of_concern` TEXT NULL AFTER `areas_for_improvement`');
            $add('general_feedback', '`general_feedback` TEXT NULL AFTER `general_comments`');
            $add('internal_notes', '`internal_notes` TEXT NULL AFTER `general_feedback`');
            $colsAfter = self::feedbackColumns();
            if (in_array('interview_id', $colsAfter, true)) {
                try {
                    $idxRows = Database::fetchAll('SHOW INDEX FROM interview_feedback');
                    $hasUq = false;
                    foreach ($idxRows as $ix) {
                        if (($ix['Key_name'] ?? '') === 'uq_interview_feedback_interview') { $hasUq = true; break; }
                    }
                    if (!$hasUq) {
                        Database::query('ALTER TABLE `interview_feedback` ADD UNIQUE KEY `uq_interview_feedback_interview` (`interview_id`)');
                    }
                } catch (Exception $e) { /* unique key is best-effort */ }
            }
            $done = true;
        } catch (Exception $e) {
            error_log('[Interview] feedback migration skipped: ' . $e->getMessage());
        }
    }

    public static function saveFeedback(array $data): int
    {
        $existing = self::getFeedback((int) $data['interview_id']);

        if ($existing) {
            Database::execute(
                "UPDATE interview_feedback SET
                    overall_rating = ?, technical_rating = ?, communication_rating = ?,
                    problem_solving_rating = ?, programme_suitability = ?,
                    strengths = ?, areas_for_improvement = ?, general_comments = ?,
                    recommendation = ?, interviewer_id = ?
                 WHERE interview_id = ?",
                'iiiisssssii',
                [
                    $data['overall_rating'] ?? null,
                    $data['technical_rating'] ?? null,
                    $data['communication_rating'] ?? null,
                    $data['problem_solving_rating'] ?? null,
                    $data['programme_suitability'] ?? null,
                    $data['strengths'] ?? null,
                    $data['areas_for_improvement'] ?? null,
                    $data['general_comments'] ?? null,
                    $data['recommendation'] ?? null,
                    $data['interviewer_id'] ?? null,
                    $data['interview_id'],
                ]
            );
            return (int) $existing['id'];
        }

        Database::execute(
            "INSERT INTO interview_feedback
             (interview_id, application_id, interviewer_id, overall_rating, technical_rating,
              communication_rating, problem_solving_rating, programme_suitability,
              strengths, areas_for_improvement, general_comments, recommendation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'iiiiiissssss',
            [
                $data['interview_id'],
                $data['application_id'],
                $data['interviewer_id'] ?? null,
                $data['overall_rating'] ?? null,
                $data['technical_rating'] ?? null,
                $data['communication_rating'] ?? null,
                $data['problem_solving_rating'] ?? null,
                $data['programme_suitability'] ?? null,
                $data['strengths'] ?? null,
                $data['areas_for_improvement'] ?? null,
                $data['general_comments'] ?? null,
                $data['recommendation'] ?? null,
            ]
        );
        return Database::lastInsertId();
    }

    // --------------------------------------------------------
    // INTERVIEWERS
    // --------------------------------------------------------

    /**
     * Get users who can serve as interviewers.
     */
    public static function availableInterviewers(): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.email, r.name AS role_name,
                    (SELECT COUNT(*) FROM interviews i WHERE i.interviewer_id = u.id AND i.status IN ('scheduled','confirmed','rescheduled')) AS assigned_count
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.slug IN ('admin','recruiter','programme_manager','assessor')
               AND u.status = 'active'
             ORDER BY u.first_name ASC, u.last_name ASC"
        );
    }

    // --------------------------------------------------------
    // CALENDAR
    // --------------------------------------------------------

    /**
     * Get interviews for calendar view within a date range.
     */
    public static function calendarRange(string $startDate, string $endDate): array
    {
        return Database::fetchAll(
            "SELECT i.id, i.interview_date, i.start_time, i.end_time,
                    i.interview_type, i.status,
                    u.first_name AS candidate_first_name, u.last_name AS candidate_last_name,
                    o.title AS opportunity_title
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN users u ON u.id = a.candidate_id
             INNER JOIN opportunities o ON o.id = a.opportunity_id
             WHERE i.interview_date BETWEEN ? AND ?
               AND i.status NOT IN ('cancelled')
             ORDER BY i.interview_date ASC, i.start_time ASC",
            'ss', [$startDate, $endDate]
        );
    }

    // --------------------------------------------------------
    // CONFLICT CHECK
    // --------------------------------------------------------

    /**
     * Check for scheduling conflicts for an interviewer.
     */
    public static function hasConflict(int $interviewerId, string $date, string $startTime, string $endTime, ?int $excludeInterviewId = null): bool
    {
        $sql = "SELECT COUNT(*) AS cnt FROM interviews
                WHERE interviewer_id = ?
                  AND interview_date = ?
                  AND status NOT IN ('cancelled')
                  AND (
                    (start_time < ? AND end_time > ?) OR
                    (start_time < ? AND end_time > ?) OR
                    (start_time >= ? AND end_time <= ?)
                  )";
        $types = 'isssssss';
        $params = [$interviewerId, $date, $endTime, $startTime, $endTime, $startTime, $startTime, $endTime];

        if ($excludeInterviewId) {
            $sql .= " AND id != ?";
            $types .= 'i';
            $params[] = $excludeInterviewId;
        }

        $row = Database::fetchOne($sql, $types, $params);
        return (int) ($row['cnt'] ?? 0) > 0;
    }
}
