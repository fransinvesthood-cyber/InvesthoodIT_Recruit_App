<?php
/**
 * ================================================
 * INVESTHOOD IT - Selection & Offer Model (Stage 11)
 * ================================================
 * Final candidate selection after interviews and the
 * management of offers for selected candidates.
 *
 * Design rules (see database/selection_offers.sql):
 *  - There is NO second application-status system. Selection
 *    outcomes are written to the existing `applications.status`
 *    ENUM pipeline:
 *        Interview Completed -> Selected   (selected)
 *        Interview Completed -> Waitlisted (on_hold)
 *        Interview Completed -> Rejected / Not Selected (rejected)
 *    Every change is recorded through the EXISTING
 *    `application_status_history` audit mechanism.
 *  - Offer status (draft/issued/accepted/declined/expired/
 *    withdrawn) is a separate concept and lives in `offers.status`
 *    with its own `offer_status_history` audit trail.
 *  - All state changes run inside database transactions with
 *    server-side re-validation.
 */

class Selection
{
    /** @var string[] Valid selection decisions */
    public const DECISIONS = ['selected', 'not_selected', 'waitlisted'];

    /** @var string[] Human-friendly decision labels */
    public const DECISION_LABELS = [
        'selected'     => 'Selected',
        'waitlisted'   => 'Waitlisted',
        'not_selected' => 'Not Selected',
    ];

    /** @var string[] Decision badge tones (shared badge component) */
    public const DECISION_BADGES = [
        'selected'     => 'success',
        'waitlisted'   => 'amber',
        'not_selected' => 'danger',
    ];

    /**
     * Application statuses that appear in the Selection module.
     * These are the post-interview stages of the EXISTING
     * applications.status pipeline (`waitlisted` is the legacy alias
     * of `on_hold` — see STATUS_ALIASES/FILTERABLE_STATUSES below).
     */
    public const SELECTION_STATUSES = [
        'interview_completed',
        'selected',
        'offer_sent',
        'offer_accepted',
        'offer_declined',
        'on_hold',
    ];

    /**
     * Application statuses that prove the application has reached
     * (or passed) the selection stage of the existing pipeline.
     * Includes the legacy `waitlisted` value so installs that never
     * ran the waitlisted -> on_hold remap still resolve.
     */
    private const REACHED_SELECTION = [
        'interview_completed',
        'selected',
        'offer_sent',
        'offer_accepted',
        'offer_declined',
        'on_hold',
        'waitlisted',
    ];

    /**
     * Which current application statuses allow each decision.
     * Waitlisted is stored as the existing pipeline value 'on_hold'.
     *
     * 'selected' is also allowed FROM 'selected': the application may
     * already be in the Selected status (e.g. seeded directly, or set
     * before the decision table existed) while the documented decision
     * record is still missing. Recording it then documents the decision
     * and internal note WITHOUT changing the status (see decide()).
     */
    private const DECISION_ALLOWED_FROM = [
        'selected'     => ['interview_completed', 'on_hold', 'selected'],
        'waitlisted'   => ['interview_completed', 'selected'],
        'not_selected' => ['interview_completed', 'selected', 'on_hold'],
    ];

    /** @var string[] Application status each decision writes */
    private const DECISION_TO_STATUS = [
        'selected'     => 'selected',
        'waitlisted'   => 'on_hold',
        'not_selected' => 'rejected',
    ];

    /**
     * Statuses accepted by the Application Status filter. This is
     * intentionally wider than SELECTION_STATUSES: older installs kept
     * `waitlisted` in applications.status (before the waitlisted ->
     * on_hold remap), and rejected rows are listed for post-interview
     * context. Keeping this list here (instead of inline in
     * buildListFilters()) guarantees the dropdown
     * (selectableStatuses()) and the query stay in sync — otherwise a
     * value shown in the dropdown can be silently ignored by the
     * filter and the page shows "No candidates" even though the
     * unfiltered list has rows.
     */
    private const FILTERABLE_STATUSES = [
        'interview_completed',
        'selected',
        'offer_sent',
        'offer_accepted',
        'offer_declined',
        'on_hold',
        'waitlisted',
        'rejected',
    ];

    /**
     * Legacy application statuses mapped to their canonical pipeline
     * value before filtering (e.g. `waitlisted` -> `on_hold`).
     */
    private const STATUS_ALIASES = [
        'waitlisted' => 'on_hold',
    ];

    /** @var string[] Valid offer statuses (mirrors offers.status ENUM) */
    public const OFFER_STATUSES = ['draft', 'issued', 'accepted', 'declined', 'expired', 'withdrawn'];

    /**
     * Cached table-existence checks for this request. LIST_SELECT joins
     * interviews / interview_feedback / offers — if any of those tables
     * is missing the whole list throws and adminList() returns 0 rows.
     * These helpers let the list degrade gracefully instead.
     *
     * @var array<string,bool>
     */
    private static array $tableCache = [];

    private static function tableExists(string $table): bool
    {
        if (!array_key_exists($table, self::$tableCache)) {
            try {
                self::$tableCache[$table] = Database::fetchOne("SHOW TABLES LIKE '" . $table . "'") !== null;
            } catch (Throwable $e) {
                self::$tableCache[$table] = false;
            }
        }
        return self::$tableCache[$table];
    }

    private static function interviewJoinsAvailable(): bool
    {
        return self::tableExists('interviews') && self::tableExists('interview_feedback');
    }

    private static function offerJoinAvailable(): bool
    {
        return self::tableExists('offers');
    }

    /** @var string[] Human-friendly offer status labels */
    public const OFFER_STATUS_LABELS = [
        'draft'     => 'Draft',
        'issued'    => 'Issued',
        'accepted'  => 'Accepted',
        'declined'  => 'Declined',
        'expired'   => 'Expired',
        'withdrawn' => 'Withdrawn',
    ];

    /** @var string[] Offer status badge tones */
    public const OFFER_STATUS_BADGES = [
        'draft'     => 'muted',
        'issued'    => 'primary',
        'accepted'  => 'success',
        'declined'  => 'danger',
        'expired'   => 'amber',
        'withdrawn' => 'muted',
    ];

    /**
     * Allowed offer status transitions: current => allowed next.
     * Accepted/declined/expired/withdrawn are terminal — historical
     * offer information can never be silently rewritten afterwards.
     */
    public const OFFER_TRANSITIONS = [
        'draft'     => ['issued', 'withdrawn'],
        'issued'    => ['accepted', 'declined', 'expired', 'withdrawn'],
        'accepted'  => [],
        'declined'  => [],
        'expired'   => [],
        'withdrawn' => [],
    ];

    /**
     * The EXISTING applications.status pipeline value each offer
     * status maps to. This keeps a single application-status
     * system: offer status changes are reflected on the
     * application timeline through the canonical mechanism.
     *
     *   issued    : Selected   -> Offer Sent
     *   accepted  : Offer Sent -> Offer Accepted
     *   declined  : Offer Sent -> Offer Declined
     *   expired   : Offer Sent -> Selected (still selected)
     *   withdrawn : Offer Sent -> Selected (still selected)
     *
     * @var string[] offer status => application status
     */
    public const OFFER_STATUS_TO_APPLICATION_STATUS = [
        'issued'    => 'offer_sent',
        'accepted'  => 'offer_accepted',
        'declined'  => 'offer_declined',
        'expired'   => 'selected',
        'withdrawn' => 'selected',
    ];

    // --------------------------------------------------------
    // HELPERS
    // --------------------------------------------------------

    /**
     * Human label for a selection decision.
     */
    public static function decisionLabel(?string $decision): string
    {
        return self::DECISION_LABELS[$decision ?? ''] ?? ucfirst(str_replace('_', ' ', (string) $decision));
    }

    /**
     * Badge tone for a selection decision.
     */
    public static function decisionBadgeTone(?string $decision): string
    {
        return self::DECISION_BADGES[$decision ?? ''] ?? 'muted';
    }

    /**
     * Human label for an offer status.
     */
    public static function offerStatusLabel(?string $status): string
    {
        return self::OFFER_STATUS_LABELS[$status ?? ''] ?? ucfirst((string) $status);
    }

    /**
     * Badge tone for an offer status.
     */
    public static function offerStatusBadgeTone(?string $status): string
    {
        return self::OFFER_STATUS_BADGES[$status ?? ''] ?? 'muted';
    }

    /**
     * Whether an application has reached the selection stage of the
     * existing pipeline (i.e. completed the interview stage), or was
     * rejected AFTER a completed interview.
     */
    public static function reachedSelectionStage(?string $status, bool $hasCompletedInterview = false): bool
    {
        if (in_array($status, self::REACHED_SELECTION, true)) {
            return true;
        }
        return $status === 'rejected' && $hasCompletedInterview;
    }

    /**
     * Assessment-stage summary derived from the existing pipeline
     * status (there is no separate assessment-result table in the
     * current schema). Returns 'completed' | 'pending' | 'none'.
     */
    public static function assessmentStage(?string $status): string
    {
        $stage = array_search($status, Application::STATUSES, true);
        if ($stage === false) {
            return 'none';
        }
        // Assessment is index 4 in the pipeline (draft, submitted,
        // under_review, shortlisted, assessment, interview_scheduled, ...).
        return $stage > 4 ? 'completed' : 'pending';
    }

    /**
     * In-scope application statuses for the status filter dropdown.
     *
     * @return array status slug => label
     */
    public static function selectableStatuses(): array
    {
        $labels = [];
        foreach (self::FILTERABLE_STATUSES as $status) {
            // `waitlisted` is a legacy alias of `on_hold` — show it once.
            if ($status === 'waitlisted') {
                continue;
            }
            $labels[$status] = Application::label($status);
        }
        return $labels;
    }

    // --------------------------------------------------------
    // SHARED SELECT FRAGMENTS
    // --------------------------------------------------------

    /**
     * The base WHERE condition shared by the selection list and
     * count queries: post-interview applications, plus rejected
     * applications that have a completed interview. Includes the
     * legacy `waitlisted` value (pre-remap installs).
     */
    private const LIST_BASE_WHERE =
        "(a.status IN ('interview_completed','selected','offer_sent','offer_accepted','offer_declined','on_hold','waitlisted')"
        . " OR (a.status = 'rejected' AND EXISTS ("
        . "     SELECT 1 FROM interviews ie"
        . "     WHERE ie.application_id = a.id AND ie.status = 'completed')))";

    /**
     * COUNT-only base: same as LIST_BASE_WHERE but WITHOUT the
     * interviews EXISTS branch, so the count query never touches the
     * interviews table (a missing interviews table would otherwise make
     * every count throw and show 0). Rejected-with-interview rows are
     * still listed — see adminList() fallback below.
     */
    private const COUNT_BASE_WHERE =
        "(a.status IN ('interview_completed','selected','offer_sent','offer_accepted','offer_declined','on_hold','waitlisted','rejected'))";


    /**
     * The SELECT shared by adminList() and findRecord(): one row per
     * in-scope application with candidate, opportunity, programme,
     * cohort, latest interview (+feedback), current decision and the
     * latest offer.
     */
    private const LIST_SELECT =
        "SELECT a.id, a.application_reference, a.candidate_id, a.opportunity_id,
                a.status, a.submitted_at, a.created_at, a.updated_at,
                CONCAT(u.first_name, ' ', u.last_name) AS candidate_name,
                u.email AS candidate_email, u.phone AS candidate_phone,
                o.title AS opportunity_title, o.programme_id, o.cohort_id,
                p.name AS programme_name,
                c.name AS cohort_name,
                sd.decision, sd.decided_at AS decision_at, sd.decision_note,
                sd.decided_by AS decided_by_id,
                CONCAT(dbu.first_name, ' ', dbu.last_name) AS decided_by_name,
                li.id AS interview_id, li.status AS interview_status,
                li.interview_date, li.start_time, li.end_time, li.interview_type,
                fb.overall_rating, fb.recommendation AS interview_recommendation,
                lot.id AS offer_id, lot.status AS offer_status,
                lot.expiry_date AS offer_expiry
         FROM applications a
         INNER JOIN users u ON u.id = a.candidate_id
         INNER JOIN roles ur ON ur.id = u.role_id AND ur.slug = 'candidate'
         LEFT JOIN opportunities o ON o.id = a.opportunity_id
         LEFT JOIN programmes p ON p.id = o.programme_id
         LEFT JOIN cohorts c ON c.id = o.cohort_id
         LEFT JOIN selection_decisions sd ON sd.application_id = a.id
         LEFT JOIN users dbu ON dbu.id = sd.decided_by
         LEFT JOIN (
             SELECT i1.* FROM interviews i1
             INNER JOIN (
                 SELECT application_id, MAX(id) AS max_id
                 FROM interviews GROUP BY application_id
             ) im ON im.application_id = i1.application_id AND im.max_id = i1.id
         ) li ON li.application_id = a.id
         LEFT JOIN interview_feedback fb ON fb.interview_id = li.id
         LEFT JOIN (
             SELECT of1.* FROM offers of1
             INNER JOIN (
                 SELECT application_id, MAX(id) AS max_id
                 FROM offers GROUP BY application_id
             ) om ON om.application_id = of1.application_id AND om.max_id = of1.id
         ) lot ON lot.application_id = a.id";

    /**
     * Build the WHERE clause + bound parameters from admin filters.
     * $needDecisionJoin is set by reference: true when the WHERE
     * references the sd alias, so the caller knows to add the
     * selection_decisions join (and only then).
     *
     * @param array $filters
     * @return array [string $whereSql, string $types, array $params]
     */
    private static function buildListFilters(array $filters, bool $forCount = false, ?bool &$needDecisionJoin = null): array
    {
        // Count query uses COUNT_BASE_WHERE (no interviews EXISTS) so a
        // missing interviews table can never zero the count. List query
        // uses the full LIST_BASE_WHERE.
        $where  = [$forCount ? self::COUNT_BASE_WHERE : self::LIST_BASE_WHERE];
        $needDecisionJoin = false;
        $params = [];
        $types  = '';

        // ---- Selection Management page scope (admin/selection.php) ----
        // The page only surfaces candidates whose CURRENT application
        // status is 'selected' AND whose CURRENT (latest) interview is
        // marked 'completed' — the same "latest interview per application"
        // rule LIST_SELECT uses for the rendered interview_status column
        // (MAX(id)). An interview still 'scheduled' (or cancelled/no_show)
        // keeps the candidate OFF this page until it is marked completed.
        // Enforcing it here (query level) keeps the count, pagination and
        // row set consistent — a PHP-level array_filter() applied after
        // pagination would silently drop in-scope rows landing on later
        // pages. The interviews subquery branch is guarded with
        // tableExists() (same graceful-degradation rule as
        // COUNT_BASE_WHERE: a missing interviews table must never throw
        // or zero the count).
        if (($filters['scope'] ?? '') === 'selected_completed') {
            $where[] = "a.status = 'selected'";
            if (self::tableExists('interviews')) {
                $where[] = "EXISTS (SELECT 1 FROM interviews sie"
                         . " WHERE sie.application_id = a.id AND sie.status = 'completed'"
                         . " AND sie.id = (SELECT MAX(i2.id) FROM interviews i2"
                         . "               WHERE i2.application_id = a.id))";
            }
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(a.application_reference LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?"
                     . " OR u.email LIKE ? OR o.title LIKE ? OR p.name LIKE ?)";
            array_push($params, $search, $search, $search, $search, $search);
            $types .= 'sssss';
        }

        // Normalise the status filter (trim + lowercase + legacy alias)
        // so `Selected`, ` selected ` and the legacy `waitlisted` value
        // all resolve. Unknown values are IGNORED so a stale/tampered
        // query string falls back to the unfiltered workflow list
        // instead of the misleading "No candidates" empty state.
        // NOTE: `waitlisted` maps to `on_hold`, so match BOTH the live
        // ENUM value and any legacy `waitlisted` rows that were never
        // remapped (otherwise filtering by On Hold hides old rows).
        $rawStatus = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($rawStatus !== '') {
            $statusFilter = self::STATUS_ALIASES[$rawStatus] ?? $rawStatus;
            if (in_array($statusFilter, self::FILTERABLE_STATUSES, true)) {
                // `rejected` stays in scope only with a completed
                // interview (base condition already enforces it), so no
                // extra EXISTS clause is needed here.
                if ($statusFilter === 'on_hold') {
                    $where[] = "(a.status IN ('on_hold','waitlisted'))";
                } else {
                    $where[]  = 'a.status = ?';
                    $params[] = $statusFilter;
                    $types   .= 's';
                }
            }
        }

        if (!empty($filters['decision'])) {
            $decisionFilter = strtolower(trim((string) $filters['decision']));
            if ($decisionFilter === 'none') {
                $where[] = 'sd.application_id IS NULL';
            } elseif (in_array($decisionFilter, self::DECISIONS, true)) {
                $where[]  = 'sd.decision = ?';
                $params[] = $decisionFilter;
                $types   .= 's';
            }
        }

        foreach (['programme_id' => 'p.id', 'cohort_id' => 'c.id', 'opportunity_id' => 'o.id'] as $key => $column) {
            if (!empty($filters[$key]) && (int) $filters[$key] > 0) {
                $where[]  = $column . ' = ?';
                $params[] = (int) $filters[$key];
                $types   .= 'i';
            }
        }

        return ['(' . implode(') AND (', $where) . ')', $types, $params];
    }

    // --------------------------------------------------------
    // SELECTION LIST
    // --------------------------------------------------------

    /**
     * Paginated selection list for administrators.
     *
     * @param array $filters search/status/decision/programme/cohort/opportunity
     * @param int   $page    1-based page number
     * @param int   $perPage Records per page
     * @return array ['records'=>[], 'total'=>int, 'pages'=>int, 'page'=>int, 'perPage'=>int]
     */
    public static function adminList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        // COUNT never touches interviews (COUNT_BASE_WHERE) so missing
        // tables can't zero it. The decision join is only added when the
        // decision filter is actually used — otherwise a missing
        // selection_decisions table would zero EVERY count/list.
        $needDecisionJoin = false;
        if (!empty($filters['decision'])) {
            $df = strtolower(trim((string) $filters['decision']));
            $needDecisionJoin = ($df === 'none' || in_array($df, self::DECISIONS, true));
        }
        if ($needDecisionJoin && !self::tableExists('selection_decisions')) {
            // Migration not run yet: ignore decision filter, show rows.
            $filters['decision'] = '';
            $needDecisionJoin = false;
        }
        [$countWhere, $countTypes, $countParams] = self::buildListFilters($filters, true);
        [$listWhere, $listTypes, $listParams] = self::buildListFilters($filters, false);
        $decisionJoin = $needDecisionJoin ? ' LEFT JOIN selection_decisions sd ON sd.application_id = a.id' : '';

        try {
            $countRow = Database::fetchOne(
                "SELECT COUNT(*) AS total
                 FROM applications a
                 INNER JOIN users u ON u.id = a.candidate_id
                 INNER JOIN roles ur ON ur.id = u.role_id AND ur.slug = 'candidate'
                 LEFT JOIN opportunities o ON o.id = a.opportunity_id
                 LEFT JOIN programmes p ON p.id = o.programme_id
                 LEFT JOIN cohorts c ON c.id = o.cohort_id"
                . $decisionJoin
                . " WHERE {$countWhere}",
                $countTypes,
                $countParams
            );
            $total = (int) ($countRow['total'] ?? 0);
            $pages = max(1, (int) ceil($total / $perPage));
            $page  = min(max(1, $page), $pages);
            $offset = ($page - 1) * $perPage;

            $orderLimit = ' ORDER BY a.updated_at DESC, a.id DESC LIMIT ?, ?';
            $records = [];
            // If COUNT and LIST disagree (e.g. count=0 but list has rows, or
            // vice versa due to a scope mismatch), always run the list query
            // — never trust a 0 count alone, or the page falsely says
            // "No candidates" when a row exists.
            $needList = ($total > 0);
            if (!$needList) {
                try {
                    $probe = Database::fetchOne(
                        self::LIST_SELECT . " WHERE {$listWhere} LIMIT 1",
                        $listTypes,
                        $listParams
                    );
                    $needList = ($probe !== null);
                    if ($needList) {
                        error_log('[Selection] adminList count/list mismatch: count=0 but list has rows. CountWhere=' . $countWhere . ' ListWhere=' . $listWhere);
                    }
                } catch (Throwable $probeEx) {
                    error_log('[Selection] adminList probe failed: ' . $probeEx->getMessage());
                }
            }
            if ($needList) {
                try {
                    $records = Database::fetchAll(
                        self::LIST_SELECT . " WHERE {$listWhere}"
                        . ' ORDER BY a.updated_at DESC, a.id DESC LIMIT ?, ?',
                        $listTypes . 'ii',
                        array_merge($listParams, [$offset, $perPage])
                    );
                } catch (Throwable $listEx) {
                    error_log('[Selection] adminList rich select failed, using fallback: ' . $listEx->getMessage());
                    $records = Database::fetchAll(
                        "SELECT a.id, a.application_reference, a.candidate_id, a.opportunity_id,"
                        . " a.status, a.submitted_at, a.created_at, a.updated_at,"
                        . " CONCAT(u.first_name, ' ', u.last_name) AS candidate_name,"
                        . " u.email AS candidate_email, u.phone AS candidate_phone,"
                        . " o.title AS opportunity_title, o.programme_id, o.cohort_id,"
                        . " p.name AS programme_name, c.name AS cohort_name,"
                        . " sd.decision, NULL AS decision_at, NULL AS decision_note,"
                        . " NULL AS decided_by_id, NULL AS decided_by_name,"
                        . " NULL AS interview_id, NULL AS interview_status,"
                        . " NULL AS interview_date, NULL AS start_time, NULL AS end_time, NULL AS interview_type,"
                        . " NULL AS overall_rating, NULL AS interview_recommendation,"
                        . " NULL AS offer_id, NULL AS offer_status, NULL AS offer_expiry"
                        . " FROM applications a"
                        . " INNER JOIN users u ON u.id = a.candidate_id"
                        . " INNER JOIN roles ur ON ur.id = u.role_id AND ur.slug = 'candidate'"
                        . " LEFT JOIN opportunities o ON o.id = a.opportunity_id"
                        . " LEFT JOIN programmes p ON p.id = o.programme_id"
                        . " LEFT JOIN cohorts c ON c.id = o.cohort_id"
                        . " LEFT JOIN selection_decisions sd ON sd.application_id = a.id"
                        . " WHERE {$listWhere}"
                        . ' ORDER BY a.updated_at DESC, a.id DESC LIMIT ?, ?',
                        $listTypes . 'ii',
                        array_merge($listParams, [$offset, $perPage])
                    );
                }
            }
            // Total must reflect what is actually displayed, not just the
            // COUNT query — otherwise the header says "0 of 0" while rows
            // (or the probe hit) prove otherwise.
            $shown = count($records);
            if ($shown > 0 && $total < $shown) {
                $total = $shown;
                $pages = max(1, (int) ceil($total / $perPage));
            }

            return [
                'records' => $records,
                'total'   => $total,
                'pages'   => $pages,
                'page'    => $page,
                'perPage' => $perPage,
            ];
        } catch (Throwable $e) {
            error_log('[Selection] adminList failed: ' . $e->getMessage());
            return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1, 'perPage' => $perPage];
        }
    }

    /**
     * Find a single in-scope application record (same shape as
     * adminList rows) for the decision / offer pages.
     */
    public static function findRecord(int $applicationId): ?array
    {
        try {
            $row = Database::fetchOne(
                self::LIST_SELECT . ' WHERE a.id = ? LIMIT 1',
                'i',
                [$applicationId]
            );
            if (!$row) {
                return null;
            }
            // Applications outside the selection scope are not accessible here.
            if (!self::reachedSelectionStage(
                $row['status'],
                ($row['interview_status'] ?? null) === 'completed'
            )) {
                return null;
            }
            return $row;
        } catch (Exception $e) {
            error_log('[Selection] findRecord failed: ' . $e->getMessage());
            return null;
        }
    }

    // --------------------------------------------------------
    // COUNTS & STATS
    // --------------------------------------------------------

    /**
     * Current decision for an application (from selection_decisions).
     */
    public static function findDecision(int $applicationId): ?array
    {
        try {
            return Database::fetchOne(
                "SELECT sd.*, CONCAT(u.first_name, ' ', u.last_name) AS decided_by_name
                 FROM selection_decisions sd
                 LEFT JOIN users u ON u.id = sd.decided_by
                 WHERE sd.application_id = ?
                 LIMIT 1",
                'i',
                [$applicationId]
            );
        } catch (Exception $e) {
            error_log('[Selection] findDecision failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Counts by decision for the selection module stats.
     *
     * @return array ['selected'=>int, 'waitlisted'=>int, 'not_selected'=>int]
     */
    public static function decisionCounts(): array
    {
        $counts = array_fill_keys(self::DECISIONS, 0);
        try {
            $rows = Database::fetchAll(
                'SELECT decision, COUNT(*) AS cnt FROM selection_decisions GROUP BY decision'
            );
            foreach ($rows as $row) {
                $decision = (string) ($row['decision'] ?? '');
                if (isset($counts[$decision])) {
                    $counts[$decision] = (int) $row['cnt'];
                }
            }
        } catch (Exception $e) {
            error_log('[Selection] decisionCounts failed: ' . $e->getMessage());
        }
        return $counts;
    }

    /**
     * Counts by offer status for the offer module stats.
     *
     * @return array ['draft'=>int, 'issued'=>int, ...]
     */
    public static function offerCounts(): array
    {
        $counts = array_fill_keys(self::OFFER_STATUSES, 0);
        try {
            $rows = Database::fetchAll('SELECT status, COUNT(*) AS cnt FROM offers GROUP BY status');
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                if (isset($counts[$status])) {
                    $counts[$status] = (int) $row['cnt'];
                }
            }
        } catch (Exception $e) {
            error_log('[Selection] offerCounts failed: ' . $e->getMessage());
        }
        return $counts;
    }

    /**
     * Combined dashboard summary for the Selection & Offers module.
     * All values come from the database; zeros when the migration
     * has not been imported yet.
     *
     * @return array
     */
    public static function dashboardStats(): array
    {
        $stats = [
            'awaiting_decision' => 0,   // interview_completed, no decision yet
            'selected'          => 0,   // selection decisions: selected
            'waitlisted'        => 0,   // selection decisions: waitlisted
            'not_selected'      => 0,   // selection decisions: not selected
            'pending_offers'    => 0,   // offers in draft (not yet issued)
            'offers_issued'     => 0,
            'offers_accepted'   => 0,
            'offers_declined'   => 0,
        ];

        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM applications a
                 WHERE a.status = 'interview_completed'
                   AND NOT EXISTS (
                       SELECT 1 FROM selection_decisions sd WHERE sd.application_id = a.id
                   )"
            );
            $stats['awaiting_decision'] = (int) ($row['cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('[Selection] dashboardStats awaiting_decision: ' . $e->getMessage());
        }

        $decisionCounts = self::decisionCounts();
        $stats['selected']     = $decisionCounts['selected'];
        $stats['waitlisted']   = $decisionCounts['waitlisted'];
        $stats['not_selected'] = $decisionCounts['not_selected'];

        $offerCounts = self::offerCounts();
        $stats['pending_offers']  = $offerCounts['draft'];
        $stats['offers_issued']   = $offerCounts['issued'];
        $stats['offers_accepted'] = $offerCounts['accepted'];
        $stats['offers_declined'] = $offerCounts['declined'];

        return $stats;
    }

    // --------------------------------------------------------
    // ELIGIBILITY & DECISIONS
    // --------------------------------------------------------

    /**
     * Server-side eligibility checks for a selection decision
     * (requirement 2). Never trusts browser input — everything is
     * re-validated from the database inside the deciding
     * transaction as well.
     *
     * @param  int   $applicationId
     * @param  array $record        Optional pre-fetched findRecord() row
     * @return array ['ok'=>bool, 'reasons'=>string[], 'record'=>array|null]
     */
    public static function eligibility(int $applicationId, ?array $record = null): array
    {
        $record = $record ?? self::findRecord($applicationId);
        $reasons = [];

        if (!$record) {
            $reasons[] = 'Application not found in the selection workflow (it may not have reached the interview stage).';
            return ['ok' => false, 'reasons' => $reasons, 'record' => null];
        }

        $status = (string) ($record['status'] ?? '');

        // Application exists, candidate owns it (join guarantees a
        // candidate-role owner) and is associated with the opportunity.
        if (empty($record['candidate_id']) || empty($record['candidate_name'])) {
            $reasons[] = 'The application does not belong to an active candidate account.';
        }
        if (empty($record['opportunity_title']) || empty($record['opportunity_id'])) {
            $reasons[] = 'The application is not associated with a valid opportunity.';
        }

        if ($status === 'draft') {
            $reasons[] = 'Draft applications cannot be processed for selection.';
        }
        if (in_array($status, ['submitted', 'under_review', 'shortlisted', 'assessment', 'interview_scheduled'], true)) {
            $reasons[] = 'The application has not completed the interview stage yet.';
        }
        if ($status === 'withdrawn') {
            $reasons[] = 'The application has been withdrawn by the candidate.';
        }
        if ($status === 'rejected') {
            $reasons[] = 'The application has already been rejected.';
        }

        return [
            'ok'      => $reasons === [],
            'reasons' => $reasons,
            'record'  => $record,
        ];
    }

    /**
     * Record (or update) the selection decision for an application.
     *
     * Transaction steps (requirement 19):
     *   BEGIN
     *     validate application (row-locked, re-read from DB)
     *     validate current status against the decision
     *     update applications.status (existing pipeline value)
     *     write the existing application_status_history record
     *     save the selection decision + internal note
     *   COMMIT
     *
     * @param int         $applicationId
     * @param string      $decision  selected | waitlisted | not_selected
     * @param int|null    $adminId   Authenticated administrator id
     * @param string|null $note      Internal selection note (admin only)
     * @param string|null $reason    Short reason stored on status history
     * @return array ['previous_status'=>string,'new_status'=>string]
     * @throws RuntimeException on any validation failure or DB error
     */
    public static function decide(
        int $applicationId,
        string $decision,
        ?int $adminId,
        ?string $note = null,
        ?string $reason = null
    ): array {
        if (!in_array($decision, self::DECISIONS, true)) {
            throw new RuntimeException('Invalid selection decision.');
        }

        $note   = $note !== null ? trim(mb_substr($note, 0, 2000)) : null;
        $reason = $reason !== null ? trim(mb_substr($reason, 0, 200)) : null;
        if ($note === '') {
            $note = null;
        }

        // Make sure the live applications.status column supports every
        // pipeline status BEFORE the transaction opens (DDL would
        // implicitly commit an open transaction).
        Application::ensureStatusColumnSupport();

        Database::beginTransaction();

        try {
            // ---- Validate application (row-locked) ----
            $app = Database::fetchOne(
                "SELECT a.id, a.candidate_id, a.opportunity_id, a.status,
                        ur.slug AS candidate_role
                 FROM applications a
                 INNER JOIN users u ON u.id = a.candidate_id
                 INNER JOIN roles ur ON ur.id = u.role_id
                 WHERE a.id = ?
                 FOR UPDATE",
                'i',
                [$applicationId]
            );

            if (!$app) {
                throw new RuntimeException('Application not found.');
            }

            $currentStatus = (string) $app['status'];

            // ---- Server-side eligibility (mirrors requirement 2) ----
            if (($app['candidate_role'] ?? '') !== 'candidate') {
                throw new RuntimeException('The application does not belong to a candidate account.');
            }
            if ((int) $app['opportunity_id'] <= 0) {
                throw new RuntimeException('The application is not associated with a valid opportunity.');
            }
            if ($currentStatus === 'draft' || $currentStatus === 'submitted') {
                throw new RuntimeException('The application has not progressed beyond the screening stages.');
            }
            if ($currentStatus === 'withdrawn') {
                throw new RuntimeException('The application has been withdrawn and can no longer be processed.');
            }
            if ($currentStatus === 'rejected') {
                throw new RuntimeException('The application has already been rejected.');
            }
            if (!self::reachedSelectionStage($currentStatus)) {
                throw new RuntimeException('The application has not completed the interview stage yet.');
            }

            // ---- Duplicate / same-decision guard ----
            $existing = Database::fetchOne(
                'SELECT decision FROM selection_decisions WHERE application_id = ?',
                'i',
                [$applicationId]
            );
            if ($existing && (string) $existing['decision'] === $decision) {
                throw new RuntimeException(
                    'The candidate has already been recorded as "'
                    . self::decisionLabel($decision) . '" for this application.'
                );
            }

            // ---- Validate the status transition for this decision ----
            $allowedFrom = self::DECISION_ALLOWED_FROM[$decision];
            if (!in_array($currentStatus, $allowedFrom, true)) {
                throw new RuntimeException(
                    'A "' . self::decisionLabel($decision)
                    . '" decision cannot be recorded while the application status is "'
                    . Application::label($currentStatus) . '".'
                );
            }
        } catch (Exception $e) {
            Database::rollback();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            error_log('[Selection] decide (validation) failed: ' . $e->getMessage());
            throw new RuntimeException('Failed to save the selection decision. Please try again.');
        }

        // ---- State changes continue in the SAME open transaction ----
        try {
            // ---- Guard: cannot un-select a candidate with an active offer ----
            if ($decision !== 'selected') {
                $activeOffer = Database::fetchOne(
                    "SELECT id FROM offers
                     WHERE application_id = ? AND status IN ('draft','issued')
                     FOR UPDATE",
                    'i',
                    [$applicationId]
                );
                if ($activeOffer) {
                    throw new RuntimeException(
                        'An active offer exists for this application. Withdraw the offer before changing the selection decision.'
                    );
                }
            }

            $newStatus = self::DECISION_TO_STATUS[$decision];

            if ($newStatus !== $currentStatus) {
                // ---- 1. Update application status (existing pipeline) ----
                Database::execute(
                    'UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?',
                    'si',
                    [$newStatus, $applicationId]
                );

                // ---- 2. Record status history through the EXISTING mechanism ----
                $historyReason = 'Selection decision: ' . self::decisionLabel($decision)
                    . ($reason !== null && $reason !== '' ? ' — ' . $reason : '');
                Application::recordStatusHistory(
                    $applicationId,
                    $currentStatus,
                    $newStatus,
                    $adminId,
                    mb_substr($historyReason, 0, 500)
                );
            }
            // When the decision's target status equals the current status
            // (e.g. 'selected' on an already-'selected' application), the
            // pipeline is left untouched — no redundant status rewrite and
            // no "Selected → Selected" history row. Only the decision
            // record below is written.

            // ---- 3. Save the selection decision + internal note ----
            Database::execute(
                "INSERT INTO selection_decisions
                    (application_id, candidate_id, opportunity_id, decision, decided_by, decision_note, decided_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    decision = VALUES(decision),
                    decided_by = VALUES(decided_by),
                    decision_note = VALUES(decision_note),
                    decided_at = NOW()",
                'iiisis',
                [$applicationId, (int) $app['candidate_id'], (int) $app['opportunity_id'], $decision, $adminId, $note]
            );

            Database::commit();

            return [
                'previous_status' => $currentStatus,
                'new_status'      => $newStatus,
            ];
        } catch (Exception $e) {
            Database::rollback();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            error_log('[Selection] decide failed: ' . $e->getMessage());
            throw new RuntimeException('Failed to save the selection decision. Please try again.');
        }
    }

    // --------------------------------------------------------
    // OFFERS
    // --------------------------------------------------------

    /**
     * Find a single offer with full application/candidate/opportunity/
     * programme/cohort context for the admin offer pages.
     */
    public static function findOffer(int $offerId): ?array
    {
        try {
            return Database::fetchOne(
                "SELECT off.*,
                        a.application_reference, a.status AS application_status,
                        CONCAT(u.first_name, ' ', u.last_name) AS candidate_name,
                        u.email AS candidate_email, u.phone AS candidate_phone,
                        o.title AS opportunity_title, o.type AS opportunity_type,
                        o.organisation,
                        p.name AS programme_name, p.type AS programme_type,
                        c.name AS cohort_name,
                        CONCAT(ib.first_name, ' ', ib.last_name) AS issued_by_name,
                        CONCAT(cb.first_name, ' ', cb.last_name) AS created_by_name
                 FROM offers off
                 INNER JOIN applications a ON a.id = off.application_id
                 INNER JOIN users u ON u.id = off.candidate_id
                 LEFT JOIN opportunities o ON o.id = off.opportunity_id
                 LEFT JOIN programmes p ON p.id = off.programme_id
                 LEFT JOIN cohorts c ON c.id = off.cohort_id
                 LEFT JOIN users ib ON ib.id = off.issued_by
                 LEFT JOIN users cb ON cb.id = off.created_by
                 WHERE off.id = ?
                 LIMIT 1",
                'i',
                [$offerId]
            );
        } catch (Exception $e) {
            error_log('[Selection] findOffer failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Latest offer for an application (any status), if any.
     */
    public static function latestOfferForApplication(int $applicationId): ?array
    {
        try {
            $row = Database::fetchOne(
                'SELECT * FROM offers WHERE application_id = ? ORDER BY id DESC LIMIT 1',
                'i',
                [$applicationId]
            );
            return $row ?: null;
        } catch (Exception $e) {
            error_log('[Selection] latestOfferForApplication failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Whether an ACTIVE offer (draft or issued) exists for an application.
     */
    public static function activeOfferExists(int $applicationId): bool
    {
        try {
            $row = Database::fetchOne(
                "SELECT id FROM offers WHERE application_id = ? AND status IN ('draft','issued') LIMIT 1",
                'i',
                [$applicationId]
            );
            return $row !== null;
        } catch (Exception $e) {
            error_log('[Selection] activeOfferExists failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Audit trail of offer status changes (offer_status_history).
     */
    public static function offerHistory(int $offerId): array
    {
        try {
            return Database::fetchAll(
                "SELECT osh.*, CONCAT(u.first_name, ' ', u.last_name) AS changed_by_name
                 FROM offer_status_history osh
                 LEFT JOIN users u ON u.id = osh.changed_by
                 WHERE osh.offer_id = ?
                 ORDER BY osh.created_at ASC, osh.id ASC",
                'i',
                [$offerId]
            );
        } catch (Exception $e) {
            error_log('[Selection] offerHistory failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build the WHERE clause + bound parameters for the offers list.
     *
     * @param array $filters
     * @return array [string $whereSql, string $types, array $params]
     */
    private static function buildOfferFilters(array $filters): array
    {
        $where  = ['1 = 1'];
        $params = [];
        $types  = '';

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(off.title LIKE ? OR off.position LIKE ? OR a.application_reference LIKE ?"
                     . " OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR o.title LIKE ?)";
            array_push($params, $search, $search, $search, $search, $search, $search);
            $types .= 'ssssss';
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::OFFER_STATUSES, true)) {
            $where[]  = 'off.status = ?';
            $params[] = $filters['status'];
            $types   .= 's';
        }

        foreach (['programme_id' => 'off.programme_id', 'cohort_id' => 'off.cohort_id', 'opportunity_id' => 'off.opportunity_id'] as $key => $column) {
            if (!empty($filters[$key]) && (int) $filters[$key] > 0) {
                $where[]  = $column . ' = ?';
                $params[] = (int) $filters[$key];
                $types   .= 'i';
            }
        }

        return [implode(' AND ', $where), $types, $params];
    }

    /**
     * Paginated offer list for administrators (Offer Management).
     *
     * @param array $filters search/status/programme/cohort/opportunity
     * @param int   $page    1-based page number
     * @param int   $perPage Records per page
     * @return array ['records'=>[], 'total'=>int, 'pages'=>int, 'page'=>int, 'perPage'=>int]
     */
    public static function offersList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        try {
            [$whereSql, $types, $params] = self::buildOfferFilters($filters);

            $countRow = Database::fetchOne(
                "SELECT COUNT(*) AS total
                 FROM offers off
                 INNER JOIN applications a ON a.id = off.application_id
                 INNER JOIN users u ON u.id = off.candidate_id
                 LEFT JOIN opportunities o ON o.id = off.opportunity_id
                 WHERE {$whereSql}",
                $types,
                $params
            );
            $total = (int) ($countRow['total'] ?? 0);
            $pages = max(1, (int) ceil($total / $perPage));
            $page  = min(max(1, $page), $pages);
            $offset = ($page - 1) * $perPage;

            $records = $total > 0
                ? Database::fetchAll(
                    "SELECT off.*,
                            a.application_reference, a.status AS application_status,
                            CONCAT(u.first_name, ' ', u.last_name) AS candidate_name,
                            u.email AS candidate_email,
                            o.title AS opportunity_title,
                            p.name AS programme_name,
                            c.name AS cohort_name,
                            CONCAT(ib.first_name, ' ', ib.last_name) AS issued_by_name
                     FROM offers off
                     INNER JOIN applications a ON a.id = off.application_id
                     INNER JOIN users u ON u.id = off.candidate_id
                     LEFT JOIN opportunities o ON o.id = off.opportunity_id
                     LEFT JOIN programmes p ON p.id = off.programme_id
                     LEFT JOIN cohorts c ON c.id = off.cohort_id
                     LEFT JOIN users ib ON ib.id = off.issued_by
                     WHERE {$whereSql}
                     ORDER BY off.updated_at DESC, off.id DESC
                     LIMIT ?, ?",
                    $types . 'ii',
                    array_merge($params, [$offset, $perPage])
                )
                : [];

            return [
                'records' => $records,
                'total'   => $total,
                'pages'   => $pages,
                'page'    => $page,
                'perPage' => $perPage,
            ];
        } catch (Exception $e) {
            // The offers table may not exist yet (migration not run)
            error_log('[Selection] offersList failed: ' . $e->getMessage());
            return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1, 'perPage' => $perPage];
        }
    }

    /**
     * Validate offer form input (requirement 8 + 10).
     *
     * @param array $data Raw input
     * @return array Clean data ready for the database
     * @throws RuntimeException when a required field is missing/invalid
     */
    public static function validateOfferData(array $data): array
    {
        $clean = [];

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 150) {
            throw new RuntimeException('Please provide an offer title (max 150 characters).');
        }
        $clean['title'] = $title;

        $position = trim((string) ($data['position'] ?? ''));
        if ($position === '' || mb_strlen($position) > 150) {
            throw new RuntimeException('Please provide the offered position (max 150 characters).');
        }
        $clean['position'] = $position;

        foreach (['start_date', 'end_date', 'expiry_date'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                if ($field === 'expiry_date') {
                    throw new RuntimeException('Please provide the offer expiry date.');
                }
                $clean[$field] = null;
                continue;
            }
            $dt = DateTime::createFromFormat('Y-m-d', $value);
            if (!$dt || $dt->format('Y-m-d') !== $value) {
                throw new RuntimeException('Please provide valid dates (YYYY-MM-DD).');
            }
            $clean[$field] = $value;
        }

        if ($clean['start_date'] !== null && $clean['end_date'] !== null
            && $clean['end_date'] < $clean['start_date']) {
            throw new RuntimeException('The offer end date cannot be before the start date.');
        }

        if ($clean['expiry_date'] < date('Y-m-d')) {
            throw new RuntimeException('The offer expiry date cannot be in the past.');
        }
        if ($clean['start_date'] !== null && $clean['expiry_date'] < $clean['start_date']) {
            throw new RuntimeException('The offer expiry date cannot be before the start date.');
        }

        $clean['location']     = self::cleanOptionalText($data['location'] ?? '', 255);
        $clean['compensation'] = self::cleanOptionalText($data['compensation'] ?? '', 255);
        $clean['terms']        = self::cleanOptionalText($data['terms'] ?? '', 5000);

        return $clean;
    }

    /**
     * Trim and length-cap an optional text field.
     */
    private static function cleanOptionalText($value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return mb_substr($value, 0, $max);
    }

    /**
     * Create a Draft offer for a selected candidate (requirement 10).
     *
     * Transaction steps:
     *   BEGIN
     *     lock + re-validate the application is SELECTED
     *     check for an existing active offer (duplicate prevention)
     *     insert the draft offer
     *   COMMIT
     *
     * @param int      $applicationId
     * @param array    $data       Raw offer input (validated inside)
     * @param int|null $adminId    Authenticated administrator id
     * @return int The new offer id
     * @throws RuntimeException on validation failure
     */
    public static function createOffer(int $applicationId, array $data, ?int $adminId): int
    {
        $clean = self::validateOfferData($data);

        Database::beginTransaction();

        try {
            // ---- Lock and re-validate the application (selected only) ----
            $app = Database::fetchOne(
                "SELECT a.id, a.candidate_id, a.opportunity_id, a.status,
                        o.programme_id, o.cohort_id
                 FROM applications a
                 INNER JOIN opportunities o ON o.id = a.opportunity_id
                 WHERE a.id = ?
                 FOR UPDATE",
                'i',
                [$applicationId]
            );

            if (!$app) {
                throw new RuntimeException('Application not found.');
            }
            if ((string) $app['status'] !== 'selected') {
                throw new RuntimeException('An offer can only be created for a candidate whose decision is "Selected".');
            }

            // ---- Duplicate active-offer prevention (row-locked) ----
            $activeOffer = Database::fetchOne(
                "SELECT id FROM offers
                 WHERE application_id = ? AND status IN ('draft','issued')
                 FOR UPDATE",
                'i',
                [$applicationId]
            );
            if ($activeOffer) {
                throw new RuntimeException('An active offer already exists for this application.');
            }

            // ---- Insert the draft offer ----
            Database::execute(
                "INSERT INTO offers
                    (application_id, candidate_id, opportunity_id, programme_id, cohort_id,
                     title, position, start_date, end_date, location, compensation,
                     expiry_date, terms, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)",
                'iiiiissssssssi',
                [
                    $applicationId,
                    (int) $app['candidate_id'],
                    (int) $app['opportunity_id'],
                    (int) $app['programme_id'],
                    // cohort_id is nullable on the opportunity — keep NULL
                    // (never cast to 0, which would violate fk_offers_cohort).
                    // mysqli binds PHP NULL as SQL NULL with the 'i' type.
                    $app['cohort_id'] !== null ? (int) $app['cohort_id'] : null,
                    $clean['title'],
                    $clean['position'],
                    $clean['start_date'],
                    $clean['end_date'],
                    $clean['location'],
                    $clean['compensation'],
                    $clean['expiry_date'],
                    $clean['terms'],
                    $adminId,
                ]
            );

            $offerId = Database::lastInsertId();
            Database::commit();
            return $offerId;
        } catch (Exception $e) {
            Database::rollback();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            error_log('[Selection] createOffer failed: ' . $e->getMessage());
            throw new RuntimeException('Failed to create the offer. Please try again.');
        }
    }

    /**
     * Edit a DRAFT offer. Once an offer has been issued the important
     * fields can no longer be silently changed (requirement 12).
     *
     * @param int      $offerId
     * @param array    $data     Raw offer input (validated inside)
     * @param int|null $adminId
     * @throws RuntimeException when the offer is not a draft
     */
    public static function updateOffer(int $offerId, array $data, ?int $adminId): void
    {
        $clean = self::validateOfferData($data);

        Database::beginTransaction();

        try {
            $offer = Database::fetchOne(
                'SELECT id, status FROM offers WHERE id = ? FOR UPDATE',
                'i',
                [$offerId]
            );

            if (!$offer) {
                throw new RuntimeException('Offer not found.');
            }
            if ((string) $offer['status'] !== 'draft') {
                throw new RuntimeException(
                    'Only draft offers can be edited. This offer has been '
                    . self::offerStatusLabel((string) $offer['status']) . '.'
                );
            }

            Database::execute(
                'UPDATE offers
                 SET title = ?, position = ?, start_date = ?, end_date = ?, location = ?,
                     compensation = ?, expiry_date = ?, terms = ?
                 WHERE id = ?',
                'ssssssssi',
                [
                    $clean['title'],
                    $clean['position'],
                    $clean['start_date'],
                    $clean['end_date'],
                    $clean['location'],
                    $clean['compensation'],
                    $clean['expiry_date'],
                    $clean['terms'],
                    $offerId,
                ]
            );

            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            error_log('[Selection] updateOffer failed: ' . $e->getMessage());
            throw new RuntimeException('Failed to update the offer. Please try again.');
        }
    }

    /**
     * Change an offer's status (issue / accept / decline / expire /
     * withdraw). The matching application pipeline status is synced
     * through the EXISTING applications.status + history mechanism,
     * all inside one transaction.
     *
     *   draft  -> issued     : application selected      -> offer_sent
     *   issued -> accepted   : application offer_sent    -> offer_accepted
     *   issued -> declined   : application offer_sent    -> offer_declined
     *   issued -> expired    : application offer_sent    -> selected (still selected)
     *   issued -> withdrawn  : application offer_sent    -> selected (still selected)
     *   draft  -> withdrawn  : application stays selected (voided draft)
     *
     * @param int      $offerId
     * @param string   $newStatus One of OFFER_STATUSES
     * @param int|null $adminId   Authenticated administrator id
     * @param string|null $reason Optional reason (recorded on history)
     * @return array ['previous_status'=>string,'new_status'=>string]
     * @throws RuntimeException on invalid transition or DB error
     */
    public static function changeOfferStatus(
        int $offerId,
        string $newStatus,
        ?int $adminId,
        ?string $reason = null
    ): array {
        if (!in_array($newStatus, self::OFFER_STATUSES, true)) {
            throw new RuntimeException('Invalid offer status.');
        }

        $reason = $reason !== null ? trim(mb_substr($reason, 0, 200)) : null;

        Application::ensureStatusColumnSupport();

        Database::beginTransaction();

        try {
            // ---- Locate the offer, then always lock the APPLICATION row
            //      first to keep a consistent lock order ----
            $offer = Database::fetchOne(
                'SELECT id, application_id, status FROM offers WHERE id = ?',
                'i',
                [$offerId]
            );
            if (!$offer) {
                throw new RuntimeException('Offer not found.');
            }

            $app = Database::fetchOne(
                'SELECT id, status FROM applications WHERE id = ? FOR UPDATE',
                'i',
                [(int) $offer['application_id']]
            );
            if (!$app) {
                throw new RuntimeException('The application linked to this offer no longer exists.');
            }

            // Re-read the offer under lock.
            $offer = Database::fetchOne(
                'SELECT id, application_id, status FROM offers WHERE id = ? FOR UPDATE',
                'i',
                [$offerId]
            );

            $currentStatus   = (string) $offer['status'];
            $applicationId   = (int) $offer['application_id'];
            $applicationPrev = (string) $app['status'];

            if ($currentStatus === $newStatus) {
                throw new RuntimeException('The offer is already ' . self::offerStatusLabel($newStatus) . '.');
            }

            $allowed = self::OFFER_TRANSITIONS[$currentStatus] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                throw new RuntimeException(
                    'An offer cannot change from ' . self::offerStatusLabel($currentStatus)
                    . ' to ' . self::offerStatusLabel($newStatus) . '.'
                );
            }
        } catch (Exception $e) {
            Database::rollback();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            error_log('[Selection] changeOfferStatus (validation) failed: ' . $e->getMessage());
            throw new RuntimeException('Failed to update the offer. Please try again.');
        }

        // ---- State changes continue in the SAME open transaction ----
        try {
            // ---- 1. Update the offer ----
            if ($newStatus === 'issued') {
                Database::execute(
                    "UPDATE offers SET status = 'issued', issued_by = ?, issued_at = NOW() WHERE id = ?",
                    'ii',
                    [$adminId, $offerId]
                );
            } elseif (in_array($newStatus, ['accepted', 'declined'], true)) {
                Database::execute(
                    'UPDATE offers SET status = ?, responded_at = NOW() WHERE id = ?',
                    'si',
                    [$newStatus, $offerId]
                );
            } else {
                Database::execute(
                    'UPDATE offers SET status = ? WHERE id = ?',
                    'si',
                    [$newStatus, $offerId]
                );
            }

            // ---- 2. Offer audit trail ----
            self::recordOfferHistory($offerId, $currentStatus, $newStatus, $adminId, $reason);

            // ---- 3. Sync the application pipeline status (existing system) ----
            $appNewStatus = self::OFFER_STATUS_TO_APPLICATION_STATUS[$newStatus] ?? null;
            if ($appNewStatus !== null && $applicationPrev !== $appNewStatus) {
                $syncAllowed = [
                    'issued'    => ['selected'],
                    'accepted'  => ['offer_sent', 'selected'],
                    'declined'  => ['offer_sent', 'selected'],
                    'expired'   => ['offer_sent'],
                    'withdrawn' => ['offer_sent', 'selected'],
                ][$newStatus] ?? [];

                if (in_array($applicationPrev, $syncAllowed, true)) {
                    Database::execute(
                        'UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?',
                        'si',
                        [$appNewStatus, $applicationId]
                    );
                    Application::recordStatusHistory(
                        $applicationId,
                        $applicationPrev,
                        $appNewStatus,
                        $adminId,
                        'Offer ' . self::offerStatusLabel($newStatus)
                            . ($reason !== null && $reason !== '' ? ' — ' . $reason : '')
                    );
                }
            }

            Database::commit();

            return [
                'previous_status' => $currentStatus,
                'new_status'      => $newStatus,
            ];
        } catch (Exception $e) {
            Database::rollback();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            error_log('[Selection] changeOfferStatus failed: ' . $e->getMessage());
            throw new RuntimeException('Failed to update the offer. Please try again.');
        }
    }

    /**
     * Best-effort insert into offer_status_history (mirrors the
     * canonical application_status_history layout).
     */
    private static function recordOfferHistory(
        int $offerId,
        ?string $previousStatus,
        string $newStatus,
        ?int $adminId,
        ?string $reason
    ): void {
        try {
            Database::execute(
                'INSERT INTO offer_status_history
                    (offer_id, previous_status, new_status, changed_by, change_reason)
                 VALUES (?, ?, ?, ?, ?)',
                'issis',
                [$offerId, $previousStatus, $newStatus, $adminId, $reason]
            );
        } catch (Exception $e) {
            // The audit trail must never break the status change.
            error_log('[Selection] Offer history entry skipped: ' . $e->getMessage());
        }
    }
}