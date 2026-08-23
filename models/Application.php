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
     * @var string[]
     */
    public const STATUSES = [
        'draft',
        'submitted',
        'eligibility_review',
        'screened',
        'assessment',
        'interview',
        'waitlisted',
        'selected',
        'rejected',
        'withdrawn',
        'expired',
    ];

    /**
     * Human-friendly labels for statuses.
     *
     * @var string[]
     */
    public const STATUS_LABELS = [
        'draft'              => 'Draft',
        'submitted'           => 'Submitted',
        'eligibility_review'  => 'Eligibility Review',
        'screened'            => 'Screened',
        'assessment'          => 'Assessment',
        'interview'           => 'Interview',
        'waitlisted'          => 'Waitlisted',
        'selected'            => 'Selected',
        'rejected'            => 'Rejected',
        'withdrawn'           => 'Withdrawn',
        'expired'             => 'Expired',
    ];

    /**
     * Status badge tone for each status.
     *
     * @var string[]
     */
    public const STATUS_BADGES = [
        'draft'              => 'muted',
        'submitted'           => 'primary',
        'eligibility_review'  => 'primary',
        'screened'            => 'primary',
        'assessment'          => 'amber',
        'interview'           => 'amber',
        'waitlisted'          => 'amber',
        'selected'            => 'success',
        'rejected'            => 'danger',
        'withdrawn'           => 'muted',
        'expired'             => 'muted',
    ];

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
     *   under_review – status in ('eligibility_review','screened','assessment','interview','waitlisted')
     *   selected     – status = 'selected'
     *   rejected     – status = 'rejected'
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
                case 'eligibility_review':
                case 'screened':
                case 'assessment':
                case 'interview':
                case 'waitlisted':
                    $counts['under_review'] += $count;
                    break;
                case 'selected':
                    $counts['selected'] = $count;
                    break;
                case 'rejected':
                    $counts['rejected'] = $count;
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
     * Format: APP-YYYY-XXXXXX (year + 6 random alphanumeric chars).
     *
     * @return string
     */
    public static function generateReference(): string
    {
        $year = date('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return 'APP-' . $year . '-' . $random;
    }
}
