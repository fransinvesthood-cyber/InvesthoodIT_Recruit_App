<?php
/**
 * INVESTHOOD IT - Candidate Placement Detail
 * Shows one placement only if it belongs to the logged-in candidate.
 * Ownership enforced server-side via CandidatePlacement::findForCandidate().
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId = (int) current_user_id();
$user = current_user();
$placementId = (int) ($_GET['id'] ?? 0);

$placement = CandidatePlacement::findForCandidate($placementId, $candidateId);
if ($placement === null) {
    set_flash('error', 'Placement not found.', 'The requested placement does not exist or you are not authorised to view it.');
    redirect('candidate/dashboard.php#dashboard-placements');
}

$statusLabel = CandidatePlacement::statusLabel($placement['status'] ?? null);
$org = CandidatePlacement::organisationFor($placement);
$role = CandidatePlacement::roleFor($placement);
$start = (string)($placement['start_date'] ?? '');
$end = (string)($placement['end_date'] ?? '');
$msg = CandidatePlacement::statusMessage($placement);
$plLoc = trim((string)($placement['location'] ?? ''));
$plDept = trim((string)($placement['department'] ?? ''));
$plCohort = trim((string)($placement['cohort_name'] ?? ''));
$flashes = render_flashes();
require __DIR__ . '/placement_detail_body.php';