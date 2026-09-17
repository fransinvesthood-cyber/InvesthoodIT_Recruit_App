<?php
/**
 * ================================================
 * INVESTHOOD IT - Talent Intelligence Hub Model
 * ================================================
 * Real-time talent analytics for the Admin Dashboard
 * "Talent Intelligence Hub" section. All metrics are
 * calculated from the live database (candidates,
 * profiles, skills, qualifications, experience,
 * programmes, cohorts and activity logs).
 *
 * Every method degrades gracefully: if a migration has
 * not been run (table missing) it returns empty
 * defaults instead of breaking the dashboard.
 */

class TalentHub
{
    /** Base JOIN guaranteeing only active candidates */
    private const CANDIDATE_JOIN = "
        JOIN users u ON u.id = %s AND u.status = 'active'
        JOIN roles r ON r.id = u.role_id AND r.slug = 'candidate'";

    /**
     * Headline talent pool statistics.
     *
     * @return array
     */
    public static function poolStats(): array
    {
        try {
            $stats = Database::fetchOne("
                SELECT
                    (SELECT COUNT(*)
                       FROM users cu
                       JOIN roles cr ON cr.id = cu.role_id
                      WHERE cr.slug = 'candidate' AND cu.status = 'active') AS total_talent,
                    (SELECT COUNT(*)
                       FROM users lu
                       JOIN roles lr ON lr.id = lu.role_id
                      WHERE lr.slug = 'candidate' AND lu.status = 'active'
                        AND lu.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) AS new_this_month,
                    (SELECT COUNT(*)
                       FROM users vl
                       JOIN roles vr ON vr.id = vl.role_id
                      WHERE vr.slug = 'candidate' AND vl.status = 'active'
                        AND vl.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS active_last_30,
                    (SELECT COUNT(DISTINCT q.user_id)
                       FROM qualifications q
                       JOIN users qu ON qu.id = q.user_id AND qu.status = 'active'
                       JOIN roles qr ON qr.id = qu.role_id AND qr.slug = 'candidate'
                      WHERE q.verification_status = 'verified') AS verified_candidates,
                    (SELECT COUNT(*)
                       FROM candidate_profiles ap
                       JOIN availability_statuses a ON a.id = ap.availability_status_id
                       JOIN users au ON au.id = ap.user_id AND au.status = 'active'
                       JOIN roles ar ON ar.id = au.role_id AND ar.slug = 'candidate'
                      WHERE ap.is_active = 1 AND a.slug = 'available_now') AS available_now,
                    (SELECT COUNT(DISTINCT s.id)
                       FROM skills s
                       JOIN candidate_skills cs ON cs.skill_id = s.id) AS scarce_skill_categories,
                    (SELECT ROUND(AVG(completion_percent))
                       FROM candidate_profiles pp
                       JOIN users pu ON pu.id = pp.user_id AND pu.status = 'active'
                       JOIN roles pr ON pr.id = pu.role_id AND pr.slug = 'candidate'
                      WHERE pp.is_active = 1) AS avg_profile_score,
                    (SELECT COUNT(*)
                       FROM candidate_profiles tp
                       JOIN users tu ON tu.id = tp.user_id AND tu.status = 'active'
                       JOIN roles tr ON tr.id = tu.role_id AND tr.slug = 'candidate'
                      WHERE tp.is_active = 1 AND tp.completion_percent >= 80) AS strong_profiles
            ") ?? [];

            return [
                'total_talent'            => (int) ($stats['total_talent'] ?? 0),
                'new_this_month'          => (int) ($stats['new_this_month'] ?? 0),
                'active_last_30'          => (int) ($stats['active_last_30'] ?? 0),
                'verified_candidates'     => (int) ($stats['verified_candidates'] ?? 0),
                'available_now'           => (int) ($stats['available_now'] ?? 0),
                'scarce_skill_categories' => (int) ($stats['scarce_skill_categories'] ?? 0),
                'avg_profile_score'       => (int) ($stats['avg_profile_score'] ?? 0),
                'strong_profiles'         => (int) ($stats['strong_profiles'] ?? 0),
            ];
        } catch (Exception $e) {
            return [
                'total_talent' => 0, 'new_this_month' => 0, 'active_last_30' => 0,
                'verified_candidates' => 0, 'available_now' => 0, 'scarce_skill_categories' => 0,
                'avg_profile_score' => 0, 'strong_profiles' => 0,
            ];
        }
    }



    /**
     * Skill distribution: top skills by candidate count.
     *
     * @param int $limit
     * @return array[] ['name','category','candidate_count','expert_count']
     */
    public static function skillDistribution(int $limit = 10): array
    {
        try {
            $rows = Database::fetchAll("
                SELECT s.id, s.name, s.category,
                       COUNT(DISTINCT cs.user_id) AS candidate_count,
                       COUNT(DISTINCT CASE WHEN cs.proficiency IN ('advanced','expert')
                                           THEN cs.user_id END) AS expert_count
                FROM skills s
                JOIN candidate_skills cs ON cs.skill_id = s.id
                " . sprintf(self::CANDIDATE_JOIN, 'cs.user_id') . "
                WHERE s.is_active = 1
                GROUP BY s.id, s.name, s.category
                ORDER BY candidate_count DESC, s.name ASC
                LIMIT " . max(1, $limit)
            );
            return array_map(fn($r) => [
                'name'            => $r['name'],
                'category'        => $r['category'],
                'candidate_count' => (int) $r['candidate_count'],
                'expert_count'    => (int) $r['expert_count'],
            ], $rows);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Total skills held by candidates, split by category.
     *
     * @return array ['technical'=>int, 'soft'=>int, 'total'=>int]
     */
    public static function skillCategoryTotals(): array
    {
        try {
            $rows = Database::fetchAll("
                SELECT s.category, COUNT(DISTINCT cs.user_id) AS holder_count
                FROM candidate_skills cs
                JOIN skills s ON s.id = cs.skill_id
                " . sprintf(self::CANDIDATE_JOIN, 'cs.user_id') . "
                GROUP BY s.category
            ");
            $totals = ['technical' => 0, 'soft' => 0];
            $max = 0;
            foreach ($rows as $r) {
                $cat = $r['category'] ?? 'technical';
                if (isset($totals[$cat])) {
                    $totals[$cat] = (int) $r['holder_count'];
                    $max = max($max, (int) $r['holder_count']);
                }
            }
            $totals['total'] = max($totals['technical'], $totals['soft']);
            return $totals;
        } catch (Exception $e) {
            return ['technical' => 0, 'soft' => 0, 'total' => 0];
        }
    }

    /**
     * Qualification distribution by level (degree, diploma, ...).
     *
     * @param int $limit
     * @return array[] ['level'=>string,'count'=>int]
     */
    public static function qualificationTrends(int $limit = 8): array
    {
        try {
            $rows = Database::fetchAll("
                SELECT COALESCE(NULLIF(q.level, ''), 'Unspecified') AS level,
                       COUNT(*) AS cnt
                FROM qualifications q
                " . sprintf(self::CANDIDATE_JOIN, 'q.user_id') . "
                GROUP BY level
                ORDER BY cnt DESC, level ASC
                LIMIT " . max(1, $limit)
            );
            return array_map(fn($r) => [
                'level' => (string) $r['level'],
                'count' => (int) $r['cnt'],
            ], $rows);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Qualification verification breakdown.
     *
     * @return array ['verified'=>int,'pending'=>int,'unverified'=>int,'failed'=>int]
     */
    public static function qualificationVerification(): array
    {
        try {
            $rows = Database::fetchAll("
                SELECT q.verification_status, COUNT(*) AS cnt
                FROM qualifications q
                " . sprintf(self::CANDIDATE_JOIN, 'q.user_id') . "
                GROUP BY q.verification_status
            ");
            $out = ['verified' => 0, 'pending' => 0, 'unverified' => 0, 'failed' => 0];
            foreach ($rows as $r) {
                if (isset($out[$r['verification_status']])) {
                    $out[$r['verification_status']] = (int) $r['cnt'];
                }
            }
            return $out;
        } catch (Exception $e) {
            return ['verified' => 0, 'pending' => 0, 'unverified' => 0, 'failed' => 0];
        }
    }


    /**
     * Experience level distribution, calculated from work_experience
     * records (total years per candidate).
     *
     * @return array ['entry'=>int,'mid'=>int,'senior'=>int,'lead'=>int,'with_experience'=>int]
     */
    public static function experienceLevels(): array
    {
        try {
            $row = Database::fetchOne("
                SELECT
                    COALESCE(SUM(CASE WHEN t.yrs < 2 THEN 1 ELSE 0 END), 0) AS entry,
                    COALESCE(SUM(CASE WHEN t.yrs >= 2 AND t.yrs < 6 THEN 1 ELSE 0 END), 0) AS mid,
                    COALESCE(SUM(CASE WHEN t.yrs >= 6 AND t.yrs < 10 THEN 1 ELSE 0 END), 0) AS senior,
                    COALESCE(SUM(CASE WHEN t.yrs >= 10 THEN 1 ELSE 0 END), 0) AS lead,
                    COUNT(*) AS with_experience
                FROM (
                    SELECT we.user_id,
                           GREATEST(
                               ROUND(SUM(
                                   TIMESTAMPDIFF(
                                       MONTH,
                                       we.start_date,
                                       COALESCE(we.end_date, CASE WHEN we.is_current = 1 THEN CURDATE() END)
                                   )
                               ) / 12, 1), 0) AS yrs
                    FROM work_experience we
                    GROUP BY we.user_id
                ) t
            ") ?? [];
            return [
                'entry'            => (int) ($row['entry'] ?? 0),
                'mid'              => (int) ($row['mid'] ?? 0),
                'senior'           => (int) ($row['senior'] ?? 0),
                'lead'             => (int) ($row['lead'] ?? 0),
                'with_experience'  => (int) ($row['with_experience'] ?? 0),
            ];
        } catch (Exception $e) {
            return ['entry' => 0, 'mid' => 0, 'senior' => 0, 'lead' => 0, 'with_experience' => 0];
        }
    }

    /**
     * Candidate distribution by location (city, falling back to province).
     *
     * @param int $limit
     * @return array[] ['location'=>string,'count'=>int]
     */
    public static function locationDistribution(int $limit = 8): array
    {
        try {
            $rows = Database::fetchAll("
                SELECT COALESCE(NULLIF(cp.city, ''), NULLIF(u.province, ''), 'Unspecified') AS location,
                       COUNT(*) AS cnt
                FROM candidate_profiles cp
                " . sprintf(self::CANDIDATE_JOIN, 'cp.user_id') . "
                WHERE cp.is_active = 1
                GROUP BY location
                ORDER BY cnt DESC, location ASC
                LIMIT " . max(1, $limit)
            );
            return array_map(fn($r) => [
                'location' => (string) $r['location'],
                'count'    => (int) $r['cnt'],
            ], $rows);
        } catch (Exception $e) {
            return [];
        }
    }


    /**
     * Programme participation: distinct candidates per programme.
     *
     * @param int $limit
     * @return array[] ['programme_name','participants','status','capacity']
     */
    public static function programmeParticipation(int $limit = 8): array
    {
        try {
            $rows = Database::fetchAll("
                SELECT p.id, p.name AS programme_name, p.status,
                       COUNT(DISTINCT cp.user_id) AS participants,
                       COALESCE(SUM(c.max_capacity), 0) AS capacity
                FROM cohort_participants cp
                JOIN cohorts c ON c.id = cp.cohort_id
                JOIN programmes p ON p.id = c.programme_id
                WHERE cp.status IN ('selected','onboarded','active')
                GROUP BY p.id, p.name, p.status
                ORDER BY participants DESC, p.name ASC
                LIMIT " . max(1, $limit)
            );
            return array_map(fn($r) => [
                'programme_name' => (string) $r['programme_name'],
                'status'         => (string) $r['status'],
                'participants'   => (int) $r['participants'],
                'capacity'       => (int) $r['capacity'],
            ], $rows);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Cohort distribution: top cohorts by committed participants.
     *
     * @param int $limit
     * @return array[] ['cohort_name','programme_name','participants','capacity']
     */
    public static function cohortDistribution(int $limit = 6): array
    {
        try {
            $rows = Database::fetchAll("
                SELECT c.name AS cohort_name, p.name AS programme_name,
                       COUNT(cp.user_id) AS participants,
                       c.max_capacity AS capacity
                FROM cohorts c
                JOIN programmes p ON p.id = c.programme_id
                LEFT JOIN cohort_participants cp
                    ON cp.cohort_id = c.id
                    AND cp.status IN ('selected','onboarded','active')
                WHERE c.status IN ('active','open','closed')
                GROUP BY c.id, c.name, c.max_capacity, p.name
                ORDER BY participants DESC, c.name ASC
                LIMIT " . max(1, $limit)
            );
            return array_map(fn($r) => [
                'cohort_name'    => (string) $r['cohort_name'],
                'programme_name' => (string) $r['programme_name'],
                'participants'   => (int) $r['participants'],
                'capacity'       => (int) $r['capacity'],
            ], $rows);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Candidate registration trend for the last N months
     * (missing months are zero-filled in PHP).
     *
     * @param int $months
     * @return array[] ['label'=>string,'count'=>int]
     */
    public static function growthTrend(int $months = 6): array
    {
        $months = max(1, $months);
        try {
            $rows = Database::fetchAll("
                SELECT DATE_FORMAT(u.created_at, '%b %Y') AS label,
                       COUNT(*) AS cnt
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE r.slug = 'candidate'
                  AND u.created_at >= DATE_SUB(NOW(), INTERVAL " . $months . " MONTH)
                GROUP BY YEAR(u.created_at), MONTH(u.created_at), label
                ORDER BY YEAR(u.created_at), MONTH(u.created_at)
            ");

            $byLabel = [];
            foreach ($rows as $r) {
                $byLabel[$r['label']] = (int) $r['cnt'];
            }

            // Zero-fill every month in the window
            $trend = [];
            $cursor = new DateTime('first day of this month');
            $cursor->modify('-' . ($months - 1) . ' months');
            for ($i = 0; $i < $months; $i++) {
                $label = $cursor->format('M Y');
                $trend[] = ['label' => $label, 'count' => $byLabel[$label] ?? 0];
                $cursor->modify('+1 month');
            }
            return $trend;
        } catch (Exception $e) {
            return [];
        }
    }
}