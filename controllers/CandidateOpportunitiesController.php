<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Opportunities Controller
 * ================================================
 * Business logic for candidate-side opportunity discovery,
 * search, filtering, sorting, and pagination.
 */

class CandidateOpportunitiesController
{
    const RESULTS_PER_PAGE = 12;

    const SORT_OPTIONS = [
        'recent'       => 'Most Recent',
        'closing_soon' => 'Closing Soon',
        'title'        => 'Opportunity Name',
        'programme'    => 'Programme',
        'location'     => 'Location',
    ];

    const OPPORTUNITY_TYPES_DISPLAY = [
        'graduate_programme' => 'Graduate Programme',
        'internship'         => 'Internship',
        'learnership'        => 'Learnership',
        'wil'                => 'Work Integrated Learning',
        'skills_development' => 'Skills Development',
        'mentorship'         => 'Mentorship',
        'other'              => 'Other',
    ];

    const WORK_ARRANGEMENTS_DISPLAY = [
        'on_site' => 'On-site',
        'remote'  => 'Remote',
        'hybrid'  => 'Hybrid',
    ];

    /**
     * Build the WHERE clause and parameters for opportunity searches and filters.
     * Only displays published, active, open opportunities to candidates.
     *
     * @param array $filters
     * @param array &$params
     * @return string SQL WHERE clause
     */
    private static function buildWhereClause(array $filters, array &$params): string
    {
        $today = date('Y-m-d');
        $conditions = [];

        // VISIBILITY RULES for candidates
        $conditions[] = "o.status IN ('published')";

        // Must be within application dates (or dates not yet set)
        $conditions[] = "(o.application_open_date IS NULL OR o.application_open_date <= ?)";
        $params[] = $today;

        // Search term: title, organisation, programme name, cohort name
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $conditions[] = "(o.title LIKE ? OR o.organisation LIKE ? OR p.name LIKE ? OR c.name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Opportunity Type
        if (!empty($filters['type'])) {
            $types = is_array($filters['type']) ? $filters['type'] : [$filters['type']];
            $types = array_filter($types); // Remove empty values
            if (!empty($types)) {
                $placeholders = implode(',', array_fill(0, count($types), '?'));
                $conditions[] = "o.type IN ($placeholders)";
                array_push($params, ...$types);
            }
        }

        // Programme
        if (!empty($filters['programme'])) {
            $programmes = is_array($filters['programme']) ? $filters['programme'] : [$filters['programme']];
            $programmes = array_filter($programmes);
            if (!empty($programmes)) {
                $placeholders = implode(',', array_fill(0, count($programmes), '?'));
                $conditions[] = "o.programme_id IN ($placeholders)";
                array_push($params, ...array_map('intval', $programmes));
            }
        }

        // Cohort
        if (!empty($filters['cohort'])) {
            $cohorts = is_array($filters['cohort']) ? $filters['cohort'] : [$filters['cohort']];
            $cohorts = array_filter($cohorts);
            if (!empty($cohorts)) {
                $placeholders = implode(',', array_fill(0, count($cohorts), '?'));
                $conditions[] = "o.cohort_id IN ($placeholders)";
                array_push($params, ...array_map('intval', $cohorts));
            }
        }

        // Province
        if (!empty($filters['province'])) {
            $provinces = is_array($filters['province']) ? $filters['province'] : [$filters['province']];
            $provinces = array_filter($provinces);
            if (!empty($provinces)) {
                $placeholders = implode(',', array_fill(0, count($provinces), '?'));
                $conditions[] = "o.province IN ($placeholders)";
                array_push($params, ...$provinces);
            }
        }

        // City
        if (!empty($filters['city'])) {
            $cities = is_array($filters['city']) ? $filters['city'] : [$filters['city']];
            $cities = array_filter($cities);
            if (!empty($cities)) {
                $placeholders = implode(',', array_fill(0, count($cities), '?'));
                $conditions[] = "o.city IN ($placeholders)";
                array_push($params, ...$cities);
            }
        }

        // Work Arrangement
        if (!empty($filters['work_arrangement'])) {
            $arrangements = is_array($filters['work_arrangement']) ? $filters['work_arrangement'] : [$filters['work_arrangement']];
            $arrangements = array_filter($arrangements);
            if (!empty($arrangements)) {
                $placeholders = implode(',', array_fill(0, count($arrangements), '?'));
                $conditions[] = "o.work_arrangement IN ($placeholders)";
                array_push($params, ...$arrangements);
            }
        }

        // Qualification Level
        if (!empty($filters['qualification'])) {
            $quals = is_array($filters['qualification']) ? $filters['qualification'] : [$filters['qualification']];
            $quals = array_filter($quals);
            if (!empty($quals)) {
                $placeholders = implode(',', array_fill(0, count($quals), '?'));
                $conditions[] = "oe.qualification_requirements LIKE ? OR " .
                               "(" . implode(' OR ', array_fill(0, count($quals), "oe.qualification_requirements LIKE ?")) . ")";
                foreach ($quals as $qual) {
                    $params[] = '%' . $qual . '%';
                }
            }
        }

        // Skills
        if (!empty($filters['skills'])) {
            $skills = is_array($filters['skills']) ? $filters['skills'] : [$filters['skills']];
            $skills = array_filter($skills);
            if (!empty($skills)) {
                // Skills are in opportunity_skills table
                $skillPlaceholders = implode(',', array_fill(0, count($skills), '?'));
                $conditions[] = "o.id IN (
                    SELECT opportunity_id FROM opportunity_skills
                    WHERE skill_name IN ($skillPlaceholders)
                    GROUP BY opportunity_id
                    HAVING COUNT(DISTINCT skill_name) >= ?
                )";
                array_push($params, ...$skills);
                $params[] = count($skills); // Match all requested skills
            }
        }

        // Availability
        if (!empty($filters['availability'])) {
            // This would filter based on start/end dates or availability_requirements
            $availTypes = is_array($filters['availability']) ? $filters['availability'] : [$filters['availability']];
            $availTypes = array_filter($availTypes);
            // Simplified: just filter by start_date >= today for "available now"
            if (in_array('available_now', $availTypes, true)) {
                $conditions[] = "(o.start_date IS NULL OR o.start_date <= ?)";
                $params[] = $today;
            }
        }

        // Application Deadline
        if (!empty($filters['deadline_days'])) {
            $deadlineDays = (int) $filters['deadline_days'];
            $futureDate = date('Y-m-d', strtotime("+{$deadlineDays} days"));
            $conditions[] = "(o.application_close_date IS NULL OR o.application_close_date <= ?)";
            $params[] = $futureDate;
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Search and filter opportunities for candidates.
     * Returns paginated results with metadata.
     *
     * @param array $filters Search/filter parameters
     * @param int   $page    Page number (1-based)
     * @param int   $perPage Results per page
     * @return array ['opportunities'=>[...], 'total'=>int, 'pages'=>int, 'current_page'=>int]
     */
    public static function searchOpportunities(array $filters, int $page = 1, int $perPage = self::RESULTS_PER_PAGE): array
    {
        $page = max(1, (int) $page);
        $params = [];

        // Build WHERE clause
        $whereClause = self::buildWhereClause($filters, $params);

        // Count total matching opportunities
        $countSql = "SELECT COUNT(DISTINCT o.id) AS total
                    FROM opportunities o
                    JOIN programmes p ON p.id = o.programme_id
                    LEFT JOIN cohorts c ON c.id = o.cohort_id
                    LEFT JOIN opportunity_eligibility oe ON oe.opportunity_id = o.id
                    WHERE $whereClause";

        $countRow = Database::fetchOne($countSql, self::getParamTypes($params), $params);
        $total = (int) ($countRow['total'] ?? 0);

        // Fetch paginated results
        $offset = ($page - 1) * $perPage;
        $sortOrder = self::getSortOrder($filters['sort'] ?? 'recent');

        $sql = "SELECT DISTINCT o.id, o.programme_id, o.cohort_id, o.title, o.type, o.organisation,
                       o.short_description, o.application_open_date, o.application_close_date,
                       o.start_date, o.end_date, o.available_positions, o.province, o.city,
                       o.physical_location, o.work_arrangement, o.status,
                       p.name AS programme_name, c.name AS cohort_name
                FROM opportunities o
                JOIN programmes p ON p.id = o.programme_id
                LEFT JOIN cohorts c ON c.id = o.cohort_id
                LEFT JOIN opportunity_eligibility oe ON oe.opportunity_id = o.id
                LEFT JOIN opportunity_skills os ON os.opportunity_id = o.id
                WHERE $whereClause
                ORDER BY $sortOrder
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;

        $opportunities = Database::fetchAll($sql, self::getParamTypes($params), $params);

        return [
            'opportunities' => $opportunities,
            'total'         => $total,
            'pages'         => (int) ceil($total / $perPage),
            'current_page'  => $page,
            'per_page'      => $perPage,
        ];
    }

    /**
     * Get a single opportunity with all its related data.
     *
     * @param int $opportunityId
     * @return array|null
     */
    public static function getOpportunityDetail(int $opportunityId): ?array
    {
        $opportunity = Opportunity::find($opportunityId);
        if (!$opportunity) {
            return null;
        }

        // Get skills
        $skills = Database::fetchAll(
            "SELECT skill_name, skill_category FROM opportunity_skills
             WHERE opportunity_id = ?
             ORDER BY skill_category, skill_name",
            'i',
            [$opportunityId]
        );

        // Get eligibility
        $eligibility = Database::fetchOne(
            "SELECT * FROM opportunity_eligibility WHERE opportunity_id = ?",
            'i',
            [$opportunityId]
        );

        // Get documents
        $documents = Database::fetchAll(
            "SELECT document_name, is_required FROM opportunity_documents
             WHERE opportunity_id = ?
             ORDER BY is_required DESC, document_name",
            'i',
            [$opportunityId]
        );

        // Get responsibilities/duties/activities/learning outcomes
        $responsibilities = Database::fetchAll(
            "SELECT type, content FROM opportunity_responsibilities
             WHERE opportunity_id = ?
             ORDER BY type, sort_order",
            'i',
            [$opportunityId]
        );

        $opportunity['skills'] = $skills;
        $opportunity['eligibility'] = $eligibility ?? [];
        $opportunity['documents'] = $documents;
        $opportunity['responsibilities'] = $responsibilities;

        return $opportunity;
    }

    /**
     * Get available filter options (provinces, cohorts, programmes, etc.).
     *
     * @return array
     */
    public static function getFilterOptions(): array
    {
        $today = date('Y-m-d');

        // Published programmes with active opportunities
        $programmes = Database::fetchAll(
            "SELECT DISTINCT p.id, p.name
             FROM programmes p
             JOIN opportunities o ON o.programme_id = p.id
             WHERE o.status = 'published'
               AND (o.application_open_date IS NULL OR o.application_open_date <= ?)
             ORDER BY p.name",
            's',
            [$today]
        );

        // Available provinces
        $provinces = Database::fetchAll(
            "SELECT DISTINCT province
             FROM opportunities
             WHERE status = 'published'
               AND (application_open_date IS NULL OR application_open_date <= ?)
               AND province IS NOT NULL
             ORDER BY province",
            's',
            [$today]
        );

        // Available cities
        $cities = Database::fetchAll(
            "SELECT DISTINCT city
             FROM opportunities
             WHERE status = 'published'
               AND (application_open_date IS NULL OR application_open_date <= ?)
               AND city IS NOT NULL
             ORDER BY city",
            's',
            [$today]
        );

        return [
            'opportunity_types'   => self::OPPORTUNITY_TYPES_DISPLAY,
            'work_arrangements'   => self::WORK_ARRANGEMENTS_DISPLAY,
            'programmes'          => $programmes,
            'provinces'           => $provinces,
            'cities'              => $cities,
            'sort_options'        => self::SORT_OPTIONS,
        ];
    }

    /**
     * Get the SQL ORDER BY clause based on sort option.
     *
     * @param string $sortBy
     * @return string
     */
    private static function getSortOrder(string $sortBy): string
    {
        $today = date('Y-m-d');
        return match ($sortBy) {
            'closing_soon' => "CASE
                                WHEN o.application_close_date IS NULL THEN 1
                                WHEN o.application_close_date < '$today' THEN 2
                                ELSE 0
                              END ASC,
                              o.application_close_date ASC",
            'title'        => "o.title ASC",
            'programme'    => "p.name ASC, o.title ASC",
            'location'     => "o.province ASC, o.city ASC, o.title ASC",
            'recent'       => "o.created_at DESC",
            default        => "o.created_at DESC",
        };
    }

    /**
     * Generate MySQLi type string for prepared statement parameters.
     *
     * @param array $params
     * @return string
     */
    private static function getParamTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Check if a candidate meets basic eligibility for an opportunity.
     * Returns a readiness score and any issues found.
     *
     * @param int $candidateId
     * @param int $opportunityId
     * @return array ['eligible'=>bool, 'score'=>int, 'issues'=>[...]]
     */
    public static function checkProfileReadiness(int $candidateId, int $opportunityId): array
    {
        $issues = [];
        $score = 100;

        $candidate = User::find($candidateId);
        if (!$candidate) {
            return ['eligible' => false, 'score' => 0, 'issues' => ['Candidate not found']];
        }

        $profile = CandidateProfile::ensureForUser($candidateId);
        $opportunity = Opportunity::find($opportunityId);

        if (!$opportunity) {
            return ['eligible' => false, 'score' => 0, 'issues' => ['Opportunity not found']];
        }

        // Check qualifications
        if (!empty($opportunity['qualification_level'])) {
            $candidateQual = $candidate['qualification_level'] ?? null;
            if (empty($candidateQual)) {
                $issues[] = 'Qualification level not specified in your profile';
                $score -= 20;
            }
        }

        // Check CV/Resume
        $hasCv = Document::hasCv($candidateId);
        if (!$hasCv) {
            $issues[] = 'CV/Resume not uploaded';
            $score -= 15;
        }

        // Check skills
        $candidateSkills = Skill::forUser($candidateId);
        if (empty($candidateSkills)) {
            $issues[] = 'No skills added to your profile';
            $score -= 15;
        }

        // Check province/location match
        if (!empty($opportunity['province']) && empty($candidate['province'])) {
            $issues[] = 'Location not specified in your profile';
            $score -= 10;
        }

        // Check availability
        if (empty($profile['availability_label'])) {
            $issues[] = 'Availability not set in your profile';
            $score -= 10;
        }

        $score = max(0, $score);

        return [
            'eligible' => count($issues) === 0,
            'score'    => $score,
            'issues'   => $issues,
        ];
    }
}
