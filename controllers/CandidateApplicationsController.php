<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Applications Controller
 * ================================================
 * Handles listing and viewing applications for the logged-in candidate.
 */

class CandidateApplicationsController
{
    /**
     * Display all applications for the current candidate.
     *
     * @param int $candidateId
     * @return array
     */
    public static function index(int $candidateId): array
    {
        $applications = Application::forCandidate($candidateId);

        $statusCounts = Application::countByStatus($candidateId);

        return [
            'applications' => $applications,
            'counts'        => $statusCounts,
        ];
    }

    /**
     * Show a single application (ensure it belongs to the candidate).
     *
     * @param int $candidateId
     * @param int $applicationId
     * @return array|null
     */
    public static function show(int $candidateId, int $applicationId): ?array
    {
        $application = Application::find($applicationId);

        // Ownership check
        if (!$application || (int) $application['candidate_id'] !== $candidateId) {
            return null;
        }

        return $application;
    }

    /**
     * Start a new application for a candidate on an opportunity.
     *
     * Performs server-side validation:
     *  - Opportunity exists
     *  - Opportunity is published
     *  - Opportunity is active (not closed/archived)
     *  - Applications are open (within open/close dates)
     *  - Candidate does not already have an application
     *
     * @param int $candidateId
     * @param int $opportunityId
     * @return array ['success' => bool, 'application' => array|null, 'message' => string, 'redirect' => string|null]
     */
    public static function startApplication(int $candidateId, int $opportunityId): array
    {
        // ---- 1. Fetch the opportunity ----
        $opportunity = Opportunity::find($opportunityId);
        if (!$opportunity) {
            return [
                'success'     => false,
                'application' => null,
                'message'     => 'Opportunity not found.',
                'redirect'    => 'candidate/opportunities.php',
            ];
        }

        // ---- 2. Validate opportunity is published ----
        if (($opportunity['status'] ?? '') !== 'published') {
            return [
                'success'     => false,
                'application' => null,
                'message'     => 'This opportunity is no longer available.',
                'redirect'    => 'candidate/opportunity_detail.php?id=' . (int) $opportunityId,
            ];
        }

        // ---- 3. Validate application dates ----
        $today = date('Y-m-d');

        // Not yet open
        if (!empty($opportunity['application_open_date']) && $opportunity['application_open_date'] > $today) {
            return [
                'success'     => false,
                'application' => null,
                'message'     => 'Applications for this opportunity have not opened yet.',
                'redirect'    => 'candidate/opportunity_detail.php?id=' . (int) $opportunityId,
            ];
        }

        // Already closed
        if (!empty($opportunity['application_close_date']) && $opportunity['application_close_date'] < $today) {
            return [
                'success'     => false,
                'application' => null,
                'message'     => 'Applications for this opportunity are currently closed.',
                'redirect'    => 'candidate/opportunity_detail.php?id=' . (int) $opportunityId,
            ];
        }

        // ---- 4. Check for existing application ----
        $existing = Application::findByCandidateAndOpportunity($candidateId, $opportunityId);

        if ($existing) {
            $status = (string) ($existing['status'] ?? '');

            // Draft exists - continue it
            if ($status === 'draft') {
                return [
                    'success'     => true,
                    'application' => $existing,
                    'message'     => 'You already have an application in progress.',
                    'redirect'    => 'candidate/application_start.php?id=' . (int) $existing['id'],
                ];
            }

            // Withdrawn - business rule allows reapplication by resetting the
            // application back to a Draft (single application per opportunity).
            if ($status === 'withdrawn') {
                Database::execute(
                    "UPDATE applications
                     SET status = 'draft', submitted_at = NULL, updated_at = NOW()
                     WHERE id = ? AND candidate_id = ?",
                    'ii',
                    [(int) $existing['id'], $candidateId]
                );

                $refreshed = Application::findForCandidate($candidateId, (int) $existing['id']);

                return [
                    'success'     => true,
                    'application' => $refreshed ?: $existing,
                    'message'     => 'You can now apply again for this opportunity.',
                    'redirect'    => 'candidate/application_start.php?id=' . (int) $existing['id'],
                ];
            }

            // Any other status - cannot create another application
            return [
                'success'     => false,
                'application' => $existing,
                'message'     => 'You have already applied for this opportunity.',
                'redirect'    => 'candidate/application_detail.php?id=' . (int) $existing['id'],
            ];
        }

        // ---- 5. Create the draft application ----
        try {
            Database::beginTransaction();

            // Generate a unique reference (Stage 1 already generates at creation)
            $reference = Application::generateReference();

            $applicationId = Application::create($candidateId, $opportunityId, $reference, 'draft');

            Database::commit();

            $application = Application::findForCandidate($candidateId, $applicationId);

            if (!$application) {
                throw new RuntimeException('Failed to retrieve created application.');
            }

            return [
                'success'     => true,
                'application' => $application,
                'message'     => 'Your draft application has been created.',
                'redirect'    => 'candidate/application_start.php?id=' . (int) $applicationId,
            ];

        } catch (Throwable $ex) {
            if (Database::getConnection()->errno) {
                Database::rollback();
            }
            error_log('[Applications] Failed to create draft: ' . $ex->getMessage());

            // Check for duplicate key violation (race condition)
            if (Database::getConnection()->errno === 1062) {
                $existing = Application::findByCandidateAndOpportunity($candidateId, $opportunityId);
                if ($existing) {
                    return [
                        'success'     => true,
                        'application' => $existing,
                        'message'     => 'You already have an application in progress.',
                        'redirect'    => 'candidate/application_start.php?id=' . (int) $existing['id'],
                    ];
                }
            }

            return [
                'success'     => false,
                'application' => null,
                'message'     => "We couldn't start your application. Please try again.",
                'redirect'    => 'candidate/opportunity_detail.php?id=' . (int) $opportunityId,
            ];
        }
    }

    /**
     * Get the application action state for an opportunity detail page.
     *
     * @param int $candidateId
     * @param int $opportunityId
     * @return array ['action' => string, 'application' => array|null]
     */
    public static function getApplicationAction(int $candidateId, int $opportunityId): array
    {
        $existing = Application::findByCandidateAndOpportunity($candidateId, $opportunityId);

        if (!$existing) {
            return ['action' => 'apply', 'application' => null];
        }

        $status = (string) ($existing['status'] ?? '');

        switch ($status) {
            case 'draft':
                return ['action' => 'continue', 'application' => $existing];
            case 'submitted':
            case 'eligibility_review':
            case 'screened':
            case 'assessment':
            case 'interview':
            case 'waitlisted':
            case 'selected':
            case 'rejected':
            case 'expired':
                return ['action' => 'view', 'application' => $existing];
            case 'withdrawn':
                // Business rule: allow reapplication if withdrawn
                return ['action' => 'apply_again', 'application' => $existing];
            default:
                return ['action' => 'view', 'application' => $existing];
        }
    }
}
