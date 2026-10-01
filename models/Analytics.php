<?php
/**
 * ================================================
 * INVESTHOOD IT - Analytics Model
 * ================================================
 * Dynamic, database-driven analytics for the Admin Dashboard.
 *
 * Every value is aggregated in SQL (COUNT / GROUP BY / date
 * filtering) - no record sets are loaded into PHP just to be
 * counted - and nothing is hard-coded. Relationships are respected:
 * an application is counted against a programme/cohort/opportunity
 * only through applications.opportunity_id -> opportunities.
 *
 * Every public method degrades gracefully: when an optional module
 * table has not been migrated yet the method returns zeroed
 * structures instead of throwing, so the dashboard never breaks.
 */

class Analytics
{
    /** @var string[] Selectable time periods for time-based analytics */
    public const PERIODS = ['7d', '30d', '90d', '6m', '12m', 'custom'];

    /** @var string Applications FROM clause shared by every aggregate */
    private const APP_FROM =
        "FROM applications a
         LEFT JOIN opportunities o ON o.id = a.opportunity_id
         LEFT JOIN programmes p ON p.id = o.programme_id
         LEFT JOIN cohorts c ON c.id = o.cohort_id";

    /** @var string[] Valid placement statuses (mirrors placements.status) */
    public const PLACEMENT_STATUSES = [
        'pending_placement', 'placement_in_progress', 'placed',
        'active', 'completed', 'withdrawn', 'cancelled',
    ];

    /** @var string[] Valid offer statuses (mirrors offers.status) */
    public const OFFER_STATUSES = ['draft', 'issued', 'accepted', 'declined', 'expired', 'withdrawn'];

    /** @var string[] Application statuses grouped for the status chart */
    public const STATUS_CHART_GROUPS = [
        'Draft'        => ['draft'],
        'Submitted'    => ['submitted'],
        'Under Review' => ['under_review'],
        'Shortlisted'  => ['shortlisted'],
        'Assessment'   => ['assessment'],
        'Interview'    => ['interview_scheduled', 'interview_required', 'interview_completed'],
        'Selected'     => ['selected', 'offer_sent', 'offer_accepted'],
        'Waitlisted'   => ['on_hold', 'waitlisted'],
        'Rejected'     => ['rejected'],
        'Withdrawn'    => ['withdrawn'],
    ];

    // ---------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------

    /**
     * Whether a table exists (used to skip optional module tables).
     */
    public static function tableExists(string $table): bool
    {
        try {
            $row = Database::fetchOne("SHOW TABLES LIKE ?", 's', [$table]);
            return !empty($row);
        } catch (Exception $e) {
            error_log('[ANALYTICS] tableExists(' . $table . '): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve a period key into a concrete inclusive [from, to] date range.
     *
     * @return array{period:string, from:string, to:string}
     */
    public static function resolveRange(string $period, ?string $from = null, ?string $to = null): array
    {
        if (!in_array($period, self::PERIODS, true)) {
            $period = '30d';
        }

        $today = date('Y-m-d');

        switch ($period) {
            case '7d':
                $from = date('Y-m-d', strtotime($today . ' -6 days'));
                $to   = $today;
                break;
            case '30d':
                $from = date('Y-m-d', strtotime($today . ' -29 days'));
                $to   = $today;
                break;
            case '90d':
                $from = date('Y-m-d', strtotime($today . ' -89 days'));
                $to   = $today;
                break;
            case '6m':
                $from = date('Y-m-d', strtotime(date('Y-m-01') . ' -5 months'));
                $to   = $today;
                break;
            case '12m':
                $from = date('Y-m-d', strtotime(date('Y-m-01') . ' -11 months'));
                $to   = $today;
                break;
            case 'custom':
            default:
                $from = self::sanitizeDate($from) ?? date('Y-m-d', strtotime($today . ' -29 days'));
                $to   = self::sanitizeDate($to) ?? $today;
                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }
                break;
        }

        return ['period' => $period, 'from' => $from, 'to' => $to];
    }

    /**
     * Validate a Y-m-d string, returning null when invalid.
     */
    private static function sanitizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
    }

    /**
     * Normalise raw request input into a clean, whitelisted filter set.
     *
     * @return array{period:string,date_from:string,date_to:string,programme_id:int,cohort_id:int,opportunity_id:int,status:string}
     */
    public static function normalizeFilters(array $input): array
    {
        $period = strtolower(trim((string) ($input['period'] ?? '30d')));
        if (!in_array($period, self::PERIODS, true)) {
            $period = '30d';
        }

        $range = self::resolveRange(
            $period,
            $input['date_from'] ?? null,
            $input['date_to'] ?? null
        );

        $status = trim((string) ($input['status'] ?? ''));
        if ($status !== '' && !in_array($status, Application::STATUSES, true)) {
            $status = '';
        }

        return [
            'period'         => $period,
            'date_from'      => $range['from'],
            'date_to'        => $range['to'],
            'programme_id'   => max(0, (int) ($input['programme_id'] ?? 0)),
            'cohort_id'      => max(0, (int) ($input['cohort_id'] ?? 0)),
            'opportunity_id' => max(0, (int) ($input['opportunity_id'] ?? 0)),
            'status'         => $status,
        ];
    }

    /**
     * Build the WHERE clause + bound params for application aggregates.
     *
     * @return array{0:string,1:string,2:array} [whereSql, types, params]
     */
    private static function applicationWhere(array $filters, bool $useDate): array
    {
        $where  = [];
        $types  = '';
        $params = [];

        if (($filters['programme_id'] ?? 0) > 0) {
            $where[]  = 'o.programme_id = ?';
            $types   .= 'i';
            $params[] = (int) $filters['programme_id'];
        }
        if (($filters['cohort_id'] ?? 0) > 0) {
            $where[]  = 'o.cohort_id = ?';
            $types   .= 'i';
            $params[] = (int) $filters['cohort_id'];
        }
        if (($filters['opportunity_id'] ?? 0) > 0) {
            $where[]  = 'a.opportunity_id = ?';
            $types   .= 'i';
            $params[] = (int) $filters['opportunity_id'];
        }
        if (($filters['status'] ?? '') !== '') {
            $where[]  = 'a.status = ?';
            $types   .= 's';
            $params[] = (string) $filters['status'];
        }
        if ($useDate) {
            $where[]  = 'DATE(COALESCE(a.submitted_at, a.created_at)) BETWEEN ? AND ?';
            $types   .= 'ss';
            $params[] = $filters['date_from'];
            $params[] = $filters['date_to'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $types, $params];
    }

    /**
     * Count applications grouped by status.
     *
     * @return array<string,int> status => count (all valid statuses present)
     */
    public static function applicationStatusCounts(array $filters, bool $useDate = false): array
    {
        $counts = array_fill_keys(Application::STATUSES, 0);
        try {
            [$where, $types, $params] = self::applicationWhere($filters, $useDate);
            $rows = Database::fetchAll(
                "SELECT a.status, COUNT(*) AS cnt " . self::APP_FROM . " $where GROUP BY a.status",
                $types,
                $params
            );
            foreach ($rows as $r) {
                if (isset($counts[$r['status']])) {
                    $counts[$r['status']] = (int) $r['cnt'];
                }
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] applicationStatusCounts: ' . $e->getMessage());
        }
        return $counts;
    }

    /**
     * Collapse a raw status count map into the grouped chart series.
     *
     * @return array{labels:string[], values:int[], total:int}
     */
    public static function applicationStatusSeries(array $statusCounts): array
    {
        $labels = [];
        $values = [];
        $total  = 0;
        foreach (self::STATUS_CHART_GROUPS as $label => $statuses) {
            $sum = 0;
            foreach ($statuses as $st) {
                $sum += (int) ($statusCounts[$st] ?? 0);
            }
            $labels[] = $label;
            $values[] = $sum;
            $total   += $sum;
        }
        return ['labels' => $labels, 'values' => $values, 'total' => $total];
    }

    /**
     * Count active candidate accounts (role = candidate, status active).
     */
    public static function totalCandidates(): int
    {
        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.slug = 'candidate' AND u.status = 'active'"
            );
            return (int) ($row['cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('[ANALYTICS] totalCandidates: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Count candidate registrations within the date range.
     */
    public static function candidatesInRange(array $filters): int
    {
        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.slug = 'candidate'
                   AND DATE(u.created_at) BETWEEN ? AND ?",
                'ss',
                [$filters['date_from'], $filters['date_to']]
            );
            return (int) ($row['cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('[ANALYTICS] candidatesInRange: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Average candidate profile completeness (0-100).
     */
    public static function averageProfileCompleteness(): int
    {
        try {
            $row = Database::fetchOne("SELECT ROUND(AVG(completion_percent)) AS avg_pct FROM candidate_profiles");
            return (int) ($row['avg_pct'] ?? 0);
        } catch (Exception $e) {
            error_log('[ANALYTICS] averageProfileCompleteness: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Programme analytics (totals, cohorts, enrolled participants and
     * the programmes with the highest real application activity).
     */
    public static function programmeStats(): array
    {
        $out = [
            'total'            => 0,
            'active'           => 0,
            'completed'        => 0,
            'draft'            => 0,
            'total_cohorts'    => 0,
            'active_cohorts'   => 0,
            'enrolled'         => 0,
            'top'              => [], // [{id,name,applications}]
        ];

        try {
            $rows = Database::fetchAll("SELECT status, COUNT(*) AS cnt FROM programmes GROUP BY status");
            foreach ($rows as $r) {
                $st = (string) $r['status'];
                $out['total'] += (int) $r['cnt'];
                if (isset($out[$st])) {
                    $out[$st] = (int) $r['cnt'];
                }
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] programmeStats(statuses): ' . $e->getMessage());
        }

        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_cnt
                 FROM cohorts"
            );
            $out['total_cohorts']  = (int) ($row['total'] ?? 0);
            $out['active_cohorts'] = (int) ($row['active_cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('[ANALYTICS] programmeStats(cohorts): ' . $e->getMessage());
        }

        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM cohort_participants
                 WHERE status IN ('selected','onboarded','active')"
            );
            $out['enrolled'] = (int) ($row['cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('[ANALYTICS] programmeStats(participants): ' . $e->getMessage());
        }

        // Applications per programme - counted only where the application
        // is genuinely linked to an opportunity of that programme.
        try {
            $rows = Database::fetchAll(
                "SELECT p.id, p.name, COUNT(a.id) AS applications
                 FROM programmes p
                 LEFT JOIN opportunities o ON o.programme_id = p.id
                 LEFT JOIN applications a ON a.opportunity_id = o.id
                 GROUP BY p.id, p.name
                 HAVING applications > 0
                 ORDER BY applications DESC, p.name ASC
                 LIMIT 8"
            );
            foreach ($rows as $r) {
                $out['top'][] = [
                    'id'           => (int) $r['id'],
                    'name'         => (string) $r['name'],
                    'applications' => (int) $r['applications'],
                ];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] programmeStats(top): ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Applications per programme within the selected date range (used by
     * the Programme Performance chart).
     *
     * @return array{labels:string[], values:int[]}
     */
    public static function applicationsPerProgramme(array $filters, int $limit = 8): array
    {
        $labels = [];
        $values = [];
        try {
            $where = ['1=1'];
            $types = 'ss';
            $params = [$filters['date_from'], $filters['date_to']];
            if (($filters['programme_id'] ?? 0) > 0) {
                $where[]  = 'p.id = ?';
                $types   .= 'i';
                $params[] = (int) $filters['programme_id'];
            }
            $sql = "SELECT p.name, COUNT(a.id) AS applications
                    FROM programmes p
                    LEFT JOIN opportunities o ON o.programme_id = p.id
                    LEFT JOIN applications a ON a.opportunity_id = o.id
                        AND DATE(COALESCE(a.submitted_at, a.created_at)) BETWEEN ? AND ?
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY p.id, p.name
                    ORDER BY applications DESC, p.name ASC
                    LIMIT " . (int) $limit;
            $rows = Database::fetchAll($sql, $types, $params);
            foreach ($rows as $r) {
                $labels[] = (string) $r['name'];
                $values[] = (int) $r['applications'];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] applicationsPerProgramme: ' . $e->getMessage());
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Opportunity analytics: totals by status, applications per
     * opportunity (real relationship only) and the most recent records.
     */
    public static function opportunityStats(array $filters): array
    {
        $out = [
            'total'              => 0,
            'active'             => 0,
            'closed'             => 0,
            'draft'              => 0,
            'total_applications' => 0,
            'per_opportunity'    => [], // [{id,title,applications}]
            'recent'             => [], // [{id,title,status,created_at,applications}]
        ];

        try {
            $rows = Database::fetchAll("SELECT status, COUNT(*) AS cnt FROM opportunities GROUP BY status");
            foreach ($rows as $r) {
                $st  = (string) $r['status'];
                $cnt = (int) $r['cnt'];
                $out['total'] += $cnt;
                if ($st === 'published' || $st === 'closing_soon') {
                    $out['active'] += $cnt;
                } elseif ($st === 'closed' || $st === 'archived') {
                    $out['closed'] += $cnt;
                } elseif ($st === 'draft') {
                    $out['draft'] += $cnt;
                }
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] opportunityStats(statuses): ' . $e->getMessage());
        }

        try {
            $rows = Database::fetchAll(
                "SELECT o.id, o.title, o.status, o.created_at,
                        COUNT(a.id) AS applications
                 FROM opportunities o
                 LEFT JOIN applications a ON a.opportunity_id = o.id
                 GROUP BY o.id, o.title, o.status, o.created_at
                 ORDER BY applications DESC, o.created_at DESC
                 LIMIT 8"
            );
            foreach ($rows as $r) {
                $out['total_applications'] += (int) $r['applications'];
                $out['per_opportunity'][] = [
                    'id'           => (int) $r['id'],
                    'title'        => (string) $r['title'],
                    'applications' => (int) $r['applications'],
                ];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] opportunityStats(per): ' . $e->getMessage());
        }

        try {
            $out['recent'] = Database::fetchAll(
                "SELECT o.id, o.title, o.status, o.created_at,
                        COALESCE(ac.applications, 0) AS applications
                 FROM opportunities o
                 LEFT JOIN (
                     SELECT opportunity_id, COUNT(*) AS applications
                     FROM applications GROUP BY opportunity_id
                 ) ac ON ac.opportunity_id = o.id
                 ORDER BY o.created_at DESC
                 LIMIT 5"
            );
        } catch (Exception $e) {
            error_log('[ANALYTICS] opportunityStats(recent): ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Interview analytics (by status, upcoming, completed, cancelled).
     * Respects programme/cohort/opportunity filters through
     * interviews -> applications -> opportunities.
     */
    public static function interviewStats(array $filters): array
    {
        $byStatus = array_fill_keys(Interview::STATUSES, 0);
        $out = [
            'total'       => 0,
            'upcoming'    => 0,
            'completed'   => 0,
            'cancelled'   => 0,
            'rescheduled' => 0,
            'by_status'   => $byStatus,
        ];

        try {
            $where  = [];
            $types  = '';
            $params = [];
            if (($filters['programme_id'] ?? 0) > 0) {
                $where[]  = 'o.programme_id = ?';
                $types   .= 'i';
                $params[] = (int) $filters['programme_id'];
            }
            if (($filters['cohort_id'] ?? 0) > 0) {
                $where[]  = 'o.cohort_id = ?';
                $types   .= 'i';
                $params[] = (int) $filters['cohort_id'];
            }
            if (($filters['opportunity_id'] ?? 0) > 0) {
                $where[]  = 'a.opportunity_id = ?';
                $types   .= 'i';
                $params[] = (int) $filters['opportunity_id'];
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $rows = Database::fetchAll(
                "SELECT i.status, COUNT(*) AS cnt
                 FROM interviews i
                 LEFT JOIN applications a ON a.id = i.application_id
                 LEFT JOIN opportunities o ON o.id = a.opportunity_id
                 $whereSql
                 GROUP BY i.status",
                $types,
                $params
            );
            foreach ($rows as $r) {
                $st = (string) $r['status'];
                if (isset($byStatus[$st])) {
                    $byStatus[$st] = (int) $r['cnt'];
                }
            }

            $out['by_status']   = $byStatus;
            $out['total']       = array_sum($byStatus);
            $out['completed']   = (int) $byStatus['completed'];
            $out['cancelled']   = (int) $byStatus['cancelled'] + (int) $byStatus['no_show'];
            $out['rescheduled'] = (int) $byStatus['rescheduled'];

            $upWhere = ["i.interview_date >= CURDATE()", "i.status IN ('scheduled','confirmed','rescheduled')"];
            if ($where) {
                $upWhere = array_merge($upWhere, $where);
            }
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM interviews i
                 LEFT JOIN applications a ON a.id = i.application_id
                 LEFT JOIN opportunities o ON o.id = a.opportunity_id
                 WHERE " . implode(' AND ', $upWhere),
                $types,
                $params
            );
            $out['upcoming'] = (int) ($row['cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('[ANALYTICS] interviewStats: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Selection & offers analytics. Decisions come from the real
     * selection_decisions records; offers from the offers table.
     */
    public static function selectionStats(array $filters): array
    {
        $out = [
            'selected'           => 0,
            'rejected'           => 0,
            'waitlisted'         => 0,
            'awaiting_decision'  => 0,
            'offers_generated'   => 0,
            'offers_accepted'    => 0,
            'offers_declined'    => 0,
            'offers_pending'     => 0,
            'offers_expired'     => 0,
        ];

        // ---- Documented selection decisions ----
        try {
            $rows = Database::fetchAll(
                "SELECT decision, COUNT(*) AS cnt FROM selection_decisions GROUP BY decision"
            );
            foreach ($rows as $r) {
                $d = (string) $r['decision'];
                if ($d === 'selected') {
                    $out['selected'] = (int) $r['cnt'];
                } elseif ($d === 'not_selected') {
                    $out['rejected'] = (int) $r['cnt'];
                } elseif ($d === 'waitlisted') {
                    $out['waitlisted'] = (int) $r['cnt'];
                }
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] selectionStats(decisions): ' . $e->getMessage());
        }

        // ---- Offers generated / accepted / declined / pending ----
        try {
            $rows = Database::fetchAll("SELECT status, COUNT(*) AS cnt FROM offers GROUP BY status");
            foreach ($rows as $r) {
                $st  = (string) $r['status'];
                $cnt = (int) $r['cnt'];
                $out['offers_generated'] += $cnt;
                if ($st === 'accepted') {
                    $out['offers_accepted'] = $cnt;
                } elseif ($st === 'declined') {
                    $out['offers_declined'] = $cnt;
                } elseif ($st === 'expired') {
                    $out['offers_expired'] = $cnt;
                } elseif ($st === 'draft' || $st === 'issued') {
                    $out['offers_pending'] += $cnt;
                }
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] selectionStats(offers): ' . $e->getMessage());
        }

        // ---- Candidates that reached selection but have no decision yet ----
        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM applications a
                 LEFT JOIN selection_decisions sd ON sd.application_id = a.id
                 WHERE a.status = 'interview_completed' AND sd.id IS NULL"
            );
            $out['awaiting_decision'] = (int) ($row['cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('[ANALYTICS] selectionStats(awaiting): ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Placement analytics from the real placements module data.
     */
    public static function placementStats(): array
    {
        $byStatus = array_fill_keys(self::PLACEMENT_STATUSES, 0);
        $out = [
            'total'          => 0,
            'pending'        => 0,
            'completed'      => 0,
            'active'         => 0,
            'withdrawn'      => 0,
            'awaiting'       => 0,
            'by_status'      => $byStatus,
            'by_programme'   => [], // [{name,count}]
            'by_cohort'      => [], // [{id,name,programme,count}]
        ];

        try {
            $rows = Database::fetchAll("SELECT status, COUNT(*) AS cnt FROM placements GROUP BY status");
            foreach ($rows as $r) {
                $st = (string) $r['status'];
                if (isset($byStatus[$st])) {
                    $byStatus[$st] = (int) $r['cnt'];
                }
                $out['total'] += (int) $r['cnt'];
            }
            $out['by_status']  = $byStatus;
            $out['pending']    = $byStatus['pending_placement'] + $byStatus['placement_in_progress'];
            $out['completed']  = $byStatus['completed'];
            $out['active']     = $byStatus['placed'] + $byStatus['active'];
            $out['withdrawn']  = $byStatus['withdrawn'] + $byStatus['cancelled'];
        } catch (Exception $e) {
            error_log('[ANALYTICS] placementStats(statuses): ' . $e->getMessage());
        }

        // Candidates that accepted an offer but have no placement record yet.
        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM offers o
                 LEFT JOIN placements pl ON pl.offer_id = o.id
                 WHERE o.status = 'accepted' AND pl.id IS NULL"
            );
            $out['awaiting'] = (int) ($row['cnt'] ?? 0);
        } catch (Exception $e) {
            error_log('[ANALYTICS] placementStats(awaiting): ' . $e->getMessage());
        }

        // Placements grouped by programme (real relationship).
        try {
            $rows = Database::fetchAll(
                "SELECT COALESCE(p.name, 'Unassigned') AS name, COUNT(*) AS cnt
                 FROM placements pl
                 LEFT JOIN programmes p ON p.id = pl.programme_id
                 GROUP BY pl.programme_id, p.name
                 ORDER BY cnt DESC
                 LIMIT 8"
            );
            foreach ($rows as $r) {
                $out['by_programme'][] = [
                    'name'  => (string) $r['name'],
                    'count' => (int) $r['cnt'],
                ];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] placementStats(by programme): ' . $e->getMessage());
        }

        // Placements grouped by cohort (real relationship).
        try {
            $rows = Database::fetchAll(
                "SELECT pl.cohort_id AS id,
                        COALESCE(c.name, 'Unassigned') AS name,
                        COALESCE(p.name, '') AS programme,
                        COUNT(*) AS cnt
                 FROM placements pl
                 LEFT JOIN cohorts c ON c.id = pl.cohort_id
                 LEFT JOIN programmes p ON p.id = pl.programme_id
                 GROUP BY pl.cohort_id, c.name, p.name
                 ORDER BY cnt DESC
                 LIMIT 8"
            );
            foreach ($rows as $r) {
                $out['by_cohort'][] = [
                    'id'        => (int) ($r['id'] ?? 0),
                    'name'      => (string) $r['name'],
                    'programme' => (string) ($r['programme'] ?? ''),
                    'count'     => (int) $r['cnt'],
                ];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] placementStats(by cohort): ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Placement status distribution for the Placement Statistics chart.
     *
     * @return array{labels:string[], values:int[]}
     */
    public static function placementSeries(array $byStatus): array
    {
        $labels = [];
        $values = [];
        foreach (self::PLACEMENT_STATUSES as $st) {
            $labels[] = ucwords(str_replace('_', ' ', $st));
            $values[] = (int) ($byStatus[$st] ?? 0);
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Applications per opportunity series (top opportunities by volume).
     *
     * @param array $perOpportunity [{id,title,applications}]
     * @return array{labels:string[], values:int[]}
     */
    public static function opportunitySeries(array $perOpportunity, int $limit = 8): array
    {
        $labels = [];
        $values = [];
        foreach (array_slice($perOpportunity, 0, $limit) as $row) {
            $labels[] = (string) ($row['title'] ?? 'Untitled');
            $values[] = (int) ($row['applications'] ?? 0);
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Interview status distribution series.
     *
     * @return array{labels:string[], values:int[]}
     */
    public static function interviewSeries(array $byStatus): array
    {
        $labels = [];
        $values = [];
        foreach (Interview::STATUSES as $st) {
            $labels[] = ucwords(str_replace('_', ' ', (string) $st));
            $values[] = (int) ($byStatus[$st] ?? 0);
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Selection & offers series (decisions + offer outcomes).
     *
     * @return array{labels:string[], values:int[]}
     */
    public static function selectionSeries(array $selection): array
    {
        return [
            'labels' => ['Selected', 'Rejected', 'Waitlisted', 'Offers Generated', 'Offers Accepted', 'Offers Declined', 'Pending Offers'],
            'values' => [
                (int) ($selection['selected'] ?? 0),
                (int) ($selection['rejected'] ?? 0),
                (int) ($selection['waitlisted'] ?? 0),
                (int) ($selection['offers_generated'] ?? 0),
                (int) ($selection['offers_accepted'] ?? 0),
                (int) ($selection['offers_declined'] ?? 0),
                (int) ($selection['offers_pending'] ?? 0),
            ],
        ];
    }

    /**
     * Placements by programme series.
     *
     * @param array $byProgramme [{name,count}]
     * @return array{labels:string[], values:int[]}
     */
    public static function placementProgrammeSeries(array $byProgramme): array
    {
        $labels = [];
        $values = [];
        foreach ($byProgramme as $row) {
            $labels[] = (string) ($row['name'] ?? 'Unassigned');
            $values[] = (int) ($row['count'] ?? 0);
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Placements by cohort series.
     *
     * @param array $byCohort [{id,name,programme,count}]
     * @return array{labels:string[], values:int[]}
     */
    public static function placementCohortSeries(array $byCohort): array
    {
        $labels = [];
        $values = [];
        foreach ($byCohort as $row) {
            $label = (string) ($row['name'] ?? 'Unassigned');
            if (!empty($row['programme'])) {
                $label .= ' (' . (string) $row['programme'] . ')';
            }
            $labels[] = $label;
            $values[] = (int) ($row['count'] ?? 0);
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Build a continuous list of date buckets between two dates.
     *
     * @return string[] Ordered bucket keys (Y-m-d for daily, Y-m for monthly)
     */
    private static function dateBuckets(string $from, string $to, bool $monthly): array
    {
        $buckets = [];
        $fmt = $monthly ? 'Y-m' : 'Y-m-d';

        $start = new DateTime($monthly ? substr($from, 0, 7) . '-01' : $from);
        $end   = new DateTime($monthly ? substr($to, 0, 7) . '-01' : $to);
        $step  = $monthly ? '+1 month' : '+1 day';

        while ($start <= $end) {
            $buckets[] = $start->format($fmt);
            $start->modify($step);
        }
        return $buckets;
    }

    /**
     * Time-based application trend. Buckets automatically switch between
     * daily (short ranges) and monthly (long ranges).
     *
     * @return array{labels:string[], values:int[], total:int, monthly:bool}
     */
    public static function applicationTrend(array $filters): array
    {
        $from = $filters['date_from'];
        $to   = $filters['date_to'];
        $days = (int) floor((strtotime($to) - strtotime($from)) / 86400);

        // <= ~3 months -> daily buckets; otherwise monthly.
        $monthly = $days > 92;
        $mysqlFmt = $monthly ? '%Y-%m' : '%Y-%m-%d';

        $buckets = self::dateBuckets($from, $to, $monthly);

        $values = array_fill_keys($buckets, 0);
        $total  = 0;

        try {
            [$where, $types, $params] = self::applicationWhere($filters, true);
            $rows = Database::fetchAll(
                "SELECT DATE_FORMAT(COALESCE(a.submitted_at, a.created_at), '$mysqlFmt') AS bucket,
                        COUNT(*) AS cnt
                 " . self::APP_FROM . " $where
                 GROUP BY bucket",
                $types,
                $params
            );
            foreach ($rows as $r) {
                $b = (string) $r['bucket'];
                if (array_key_exists($b, $values)) {
                    $values[$b] = (int) $r['cnt'];
                }
                $total += (int) $r['cnt'];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] applicationTrend: ' . $e->getMessage());
        }

        return [
            'labels'  => $buckets,
            'values'  => array_values($values),
            'total'   => $total,
            'monthly' => $monthly,
        ];
    }

    /**
     * Candidate growth (registrations) over the selected range.
     *
     * @return array{labels:string[], values:int[]}
     */
    public static function candidateGrowth(array $filters): array
    {
        $from = $filters['date_from'];
        $to   = $filters['date_to'];
        $days = (int) floor((strtotime($to) - strtotime($from)) / 86400);
        $monthly = $days > 92;
        $mysqlFmt = $monthly ? '%Y-%m' : '%Y-%m-%d';

        $buckets = self::dateBuckets($from, $to, $monthly);
        $values  = array_fill_keys($buckets, 0);

        try {
            $rows = Database::fetchAll(
                "SELECT DATE_FORMAT(u.created_at, '$mysqlFmt') AS bucket, COUNT(*) AS cnt
                 FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.slug = 'candidate'
                   AND DATE(u.created_at) BETWEEN ? AND ?
                 GROUP BY bucket",
                'ss',
                [$from, $to]
            );
            foreach ($rows as $r) {
                $b = (string) $r['bucket'];
                if (array_key_exists($b, $values)) {
                    $values[$b] = (int) $r['cnt'];
                }
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] candidateGrowth: ' . $e->getMessage());
        }

        return ['labels' => $buckets, 'values' => array_values($values)];
    }

    /**
     * Skills supply (candidate_skills) versus demand (opportunity_skills).
     *
     * @return array{labels:string[], supply:int[], demand:int[]}
     */
    public static function skillsSupplyDemand(int $limit = 8): array
    {
        $supply = [];
        $demand = [];

        try {
            $rows = Database::fetchAll(
                "SELECT s.name, COUNT(cs.id) AS cnt
                 FROM skills s
                 LEFT JOIN candidate_skills cs ON cs.skill_id = s.id
                 GROUP BY s.id, s.name
                 ORDER BY cnt DESC, s.name ASC
                 LIMIT " . (int) $limit
            );
            foreach ($rows as $r) {
                $supply[strtolower(trim((string) $r['name']))] = [
                    'name' => (string) $r['name'],
                    'cnt'  => (int) $r['cnt'],
                ];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] skillsSupplyDemand(supply): ' . $e->getMessage());
        }

        try {
            $rows = Database::fetchAll(
                "SELECT skill_name, COUNT(*) AS cnt
                 FROM opportunity_skills
                 GROUP BY skill_name
                 ORDER BY cnt DESC, skill_name ASC
                 LIMIT " . (int) $limit
            );
            foreach ($rows as $r) {
                $demand[strtolower(trim((string) $r['skill_name']))] = [
                    'name' => (string) $r['skill_name'],
                    'cnt'  => (int) $r['cnt'],
                ];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] skillsSupplyDemand(demand): ' . $e->getMessage());
        }

        // Merge both sides so supply-only and demand-only skills both appear.
        $merged = [];
        foreach ($supply as $key => $s) {
            $merged[$key] = ['name' => $s['name'], 'supply' => $s['cnt'], 'demand' => 0];
        }
        foreach ($demand as $key => $d) {
            if (!isset($merged[$key])) {
                $merged[$key] = ['name' => $d['name'], 'supply' => 0, 'demand' => 0];
            }
            $merged[$key]['demand'] = $d['cnt'];
        }

        // Sort by combined activity, keep the top N.
        uasort($merged, static function ($a, $b) {
            return ($b['supply'] + $b['demand']) <=> ($a['supply'] + $a['demand']);
        });

        $labels = [];
        $supplyVals = [];
        $demandVals = [];
        foreach (array_slice($merged, 0, $limit, true) as $row) {
            $labels[]     = $row['name'];
            $supplyVals[] = $row['supply'];
            $demandVals[] = $row['demand'];
        }

        return ['labels' => $labels, 'supply' => $supplyVals, 'demand' => $demandVals];
    }

    /**
     * Talent pool distribution - top cities by candidate count.
     *
     * @return array{labels:string[], values:int[]}
     */
    public static function talentPool(int $limit = 6): array
    {
        $labels = [];
        $values = [];
        try {
            $rows = Database::fetchAll(
                "SELECT COALESCE(NULLIF(TRIM(cp.city), ''), 'Unspecified') AS city,
                        COUNT(*) AS cnt
                 FROM candidate_profiles cp
                 INNER JOIN users u ON u.id = cp.user_id
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.slug = 'candidate'
                 GROUP BY city
                 ORDER BY cnt DESC, city ASC
                 LIMIT " . (int) $limit
            );
            foreach ($rows as $r) {
                $labels[] = (string) $r['city'];
                $values[] = (int) $r['cnt'];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] talentPool: ' . $e->getMessage());
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Learning & Assessment stage distribution (derived from the real
     * application pipeline).
     *
     * @return array{labels:string[], values:int[]}
     */
    public static function assessmentStage(array $statusCounts): array
    {
        $pending  = (int) ($statusCounts['draft'] ?? 0)
                  + (int) ($statusCounts['submitted'] ?? 0)
                  + (int) ($statusCounts['under_review'] ?? 0)
                  + (int) ($statusCounts['shortlisted'] ?? 0);
        $inProgress = (int) ($statusCounts['assessment'] ?? 0);
        $inInterview = (int) ($statusCounts['interview_scheduled'] ?? 0)
                  + (int) ($statusCounts['interview_required'] ?? 0);
        $completed = (int) ($statusCounts['interview_completed'] ?? 0)
                  + (int) ($statusCounts['selected'] ?? 0)
                  + (int) ($statusCounts['offer_sent'] ?? 0)
                  + (int) ($statusCounts['offer_accepted'] ?? 0)
                  + (int) ($statusCounts['offer_declined'] ?? 0)
                  + (int) ($statusCounts['on_hold'] ?? 0)
                  + (int) ($statusCounts['waitlisted'] ?? 0)
                  + (int) ($statusCounts['rejected'] ?? 0);

        return [
            'labels' => ['Pending', 'In Assessment', 'Interview Stage', 'Past Assessment'],
            'values' => [$pending, $inProgress, $inInterview, $completed],
        ];
    }

    /**
     * Recruitment funnel (cumulative stage counts from the real pipeline).
     *
     * @return array{labels:string[], values:int[]}
     */
    public static function funnel(array $statusCounts, int $placed): array
    {
        $applications = array_sum($statusCounts);
        $review = $applications - (int) ($statusCounts['draft'] ?? 0);
        $shortlisted = $review - (int) ($statusCounts['submitted'] ?? 0) - (int) ($statusCounts['under_review'] ?? 0)
                     - (int) ($statusCounts['withdrawn'] ?? 0);
        $interview = (int) ($statusCounts['assessment'] ?? 0)
                   + (int) ($statusCounts['interview_scheduled'] ?? 0)
                   + (int) ($statusCounts['interview_required'] ?? 0)
                   + (int) ($statusCounts['interview_completed'] ?? 0)
                   + (int) ($statusCounts['selected'] ?? 0)
                   + (int) ($statusCounts['offer_sent'] ?? 0)
                   + (int) ($statusCounts['offer_accepted'] ?? 0)
                   + (int) ($statusCounts['offer_declined'] ?? 0)
                   + (int) ($statusCounts['on_hold'] ?? 0)
                   + (int) ($statusCounts['waitlisted'] ?? 0)
                   + (int) ($statusCounts['rejected'] ?? 0);
        $selected = (int) ($statusCounts['selected'] ?? 0)
                  + (int) ($statusCounts['offer_sent'] ?? 0)
                  + (int) ($statusCounts['offer_accepted'] ?? 0);

        return [
            'labels' => ['Applications', 'Under Review', 'Shortlisted', 'Interview', 'Selected', 'Placed'],
            'values' => [max(0, $applications), max(0, $review), max(0, $shortlisted), max(0, $interview), max(0, $selected), max(0, $placed)],
        ];
    }

    /**
     * Filter dropdown options (programmes, cohorts, opportunities, statuses).
     */
    public static function filterOptions(): array
    {
        $options = [
            'programmes'    => [],
            'cohorts'       => [],
            'opportunities' => [],
            'statuses'      => [],
        ];

        try {
            $options['programmes'] = Database::fetchAll(
                "SELECT id, name FROM programmes ORDER BY name ASC"
            );
        } catch (Exception $e) {
            error_log('[ANALYTICS] filterOptions(programmes): ' . $e->getMessage());
        }

        try {
            $options['cohorts'] = Database::fetchAll(
                "SELECT id, programme_id, name FROM cohorts ORDER BY name ASC"
            );
        } catch (Exception $e) {
            error_log('[ANALYTICS] filterOptions(cohorts): ' . $e->getMessage());
        }

        try {
            $options['opportunities'] = Database::fetchAll(
                "SELECT id, programme_id, cohort_id, title FROM opportunities ORDER BY title ASC"
            );
        } catch (Exception $e) {
            error_log('[ANALYTICS] filterOptions(opportunities): ' . $e->getMessage());
        }

        try {
            $rows = Database::fetchAll("SELECT a.status, COUNT(*) AS cnt FROM applications a GROUP BY a.status");
            foreach ($rows as $r) {
                $st = (string) $r['status'];
                $options['statuses'][] = [
                    'value' => $st,
                    'label' => Application::label($st),
                    'count' => (int) $r['cnt'],
                ];
            }
        } catch (Exception $e) {
            error_log('[ANALYTICS] filterOptions(statuses): ' . $e->getMessage());
        }

        return $options;
    }

    /**
     * Assemble the complete analytics payload consumed by the dashboard.
     *
     * @param array $filters Normalised filters (from normalizeFilters()).
     * @return array Structured data ready for json_encode().
     */
    public static function build(array $filters): array
    {
        $statusAllTime = self::applicationStatusCounts($filters, false);
        $statusInRange = self::applicationStatusCounts($filters, true);

        $totalApplications = array_sum($statusAllTime);
        $newApplications   = array_sum($statusInRange);
        $newCandidates     = self::candidatesInRange($filters);

        $programme   = self::programmeStats();
        $opportunity = self::opportunityStats($filters);
        $interview   = self::interviewStats($filters);
        $selection   = self::selectionStats($filters);
        $placement   = self::placementStats();

        $talentPool     = self::totalCandidates();
        $placed         = $placement['active'] + $placement['completed'];
        $completionRate = $placement['total'] > 0
            ? (int) round(($placement['completed'] / $placement['total']) * 100)
            : 0;

        // Candidate/application headline totals required by the task spec.
        $underReview = (int) ($statusAllTime['submitted'] ?? 0) + (int) ($statusAllTime['under_review'] ?? 0);
        $shortlisted = (int) ($statusAllTime['shortlisted'] ?? 0);
        $rejectedApps = (int) ($statusAllTime['rejected'] ?? 0);
        $selectedApps = (int) ($statusAllTime['selected'] ?? 0)
            + (int) ($statusAllTime['offer_sent'] ?? 0)
            + (int) ($statusAllTime['offer_accepted'] ?? 0);
        $waitlistedApps = (int) ($statusAllTime['on_hold'] ?? 0) + (int) ($statusAllTime['waitlisted'] ?? 0);

        // Interview headline totals (upcoming / completed / cancelled-rescheduled).
        $upcomingInterviews = (int) ($interview['upcoming'] ?? 0);
        $completedInterviews = (int) ($interview['completed'] ?? 0);
        $cancelledInterviews = (int) ($interview['cancelled'] ?? 0) + (int) ($interview['rescheduled'] ?? 0);

        return [
            'generated_at' => date('c'),
            'range' => [
                'period' => $filters['period'],
                'from'   => $filters['date_from'],
                'to'     => $filters['date_to'],
            ],
            'filters' => $filters,
            'summary' => [
                'total_candidates'   => $talentPool,
                'new_candidates'     => $newCandidates,
                'total_applications' => $totalApplications,
                'new_applications'   => $newApplications,
                'under_review'       => $underReview,
                'shortlisted'        => $shortlisted,
                'rejected'           => $rejectedApps,
                'selected'           => $selectedApps,
                'waitlisted'         => $waitlistedApps,
                'hired_placed'       => $placed,
            ],
            'programme'   => $programme,
            'opportunity' => $opportunity,
            'interview'   => $interview,
            'selection'   => $selection,
            'placement'   => $placement,
            'stats' => [
                'active_programmes'    => (int) $programme['active'],
                'total_programmes'     => (int) $programme['total'],
                'completed_programmes' => (int) $programme['completed'],
                'total_cohorts'        => (int) $programme['total_cohorts'],
                'active_cohorts'       => (int) $programme['active_cohorts'],
                'enrolled'             => (int) $programme['enrolled'],
                'total_candidates'     => $talentPool,
                'new_candidates'       => $newCandidates,
                'total_applications'   => $totalApplications,
                'new_applications'     => $newApplications,
                'under_review'         => $underReview,
                'shortlisted'          => $shortlisted,
                'rejected'             => $rejectedApps,
                'selected'             => $selectedApps,
                'waitlisted'           => $waitlistedApps,
                'hired_placed'         => $placed,
                'total_opportunities'  => (int) $opportunity['total'],
                'active_opportunities' => (int) $opportunity['active'],
                'closed_opportunities' => (int) $opportunity['closed'],
                'total_interviews'     => (int) $interview['total'],
                'upcoming_interviews'  => $upcomingInterviews,
                'completed_interviews' => $completedInterviews,
                'cancelled_interviews' => $cancelledInterviews,
                'offers_generated'     => (int) $selection['offers_generated'],
                'offers_accepted'      => (int) $selection['offers_accepted'],
                'offers_declined'      => (int) $selection['offers_declined'],
                'offers_pending'       => (int) $selection['offers_pending'],
                'total_placements'     => (int) $placement['total'],
                'active_placements'    => (int) $placement['active'],
                'pending_placements'   => (int) $placement['pending'],
                'completed_placements' => (int) $placement['completed'],
                'awaiting_placement'   => (int) $placement['awaiting'],
                'talent_pool'          => $talentPool,
                'completion_rate'      => $completionRate,
                'profile_completeness' => self::averageProfileCompleteness(),
            ],
            'charts' => [
                'programme'          => self::applicationsPerProgramme($filters),
                'application_status' => self::applicationStatusSeries($statusAllTime),
                'opportunity'        => self::opportunitySeries($opportunity['per_opportunity'] ?? []),
                'interview'          => self::interviewSeries($interview['by_status'] ?? []),
                'selection'          => self::selectionSeries($selection),
                'placement'          => self::placementSeries($placement['by_status']),
                'placement_programme'=> self::placementProgrammeSeries($placement['by_programme'] ?? []),
                'placement_cohort'   => self::placementCohortSeries($placement['by_cohort'] ?? []),
                'talent'             => self::talentPool(),
                'skills'             => self::skillsSupplyDemand(),
                'trend'              => self::applicationTrend($filters),
                'growth'             => self::candidateGrowth($filters),
                'learning'           => self::assessmentStage($statusAllTime),
                'outcome'            => self::placementSeries($placement['by_status']),
                'funnel'             => self::funnel($statusAllTime, $placed),
            ],
        ];
    }
}
