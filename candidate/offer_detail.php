<?php
/**
 * Candidate Offer Detail — bootstrap + ownership check.
 * Body markup lives in offer_detail_body.php (keeps edits small).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');

$candidateId = (int) current_user_id();
$user        = current_user();
$offerId     = (int) ($_GET['id'] ?? 0);

if ($offerId <= 0) {
    set_flash('error', 'Invalid Offer', 'No offer ID was provided.');
    redirect('candidate/offers.php');
}

try {
    CandidateOffer::refreshForCandidate($candidateId);
} catch (Throwable $e) {
    error_log('[Candidate Offer Detail] refresh failed: ' . $e->getMessage());
}

$offer = CandidateOffersController::find($offerId, $candidateId);
if ($offer === null) {
    set_flash('error', 'Offer not found.', 'The requested offer does not exist or you are not authorised to view it.');
    redirect('candidate/offers.php');
}

$offerLabel = CandidateOffersController::statusLabel($offer);
$offerTone  = CandidateOffersController::statusTone($offer);
$offerTagTone = match ($offerTone) {
    'success' => 'green',
    'danger'  => 'red',
    'primary' => 'primary',
    'amber'   => 'amber',
    default   => 'muted',
};
$offerExpired     = CandidateOffersController::statusSlug($offer) === 'expired';
$offerRespondable = CandidateOffersController::canRespond($offer);
$offerHistory = CandidateOffersController::history($offerId);
$adminNotes   = CandidateOffersController::adminNotes($offer);
$flashes = render_flashes();

$respondableMessage = null;
if (!$offerRespondable) {
    $respondableMessage = match ((string) ($offer['status'] ?? '')) {
        'accepted'  => 'You have already accepted this offer. The programme team will contact you about the next placement stage.',
        'declined'  => 'You have already declined this offer.',
        'expired'   => 'This offer has expired and can no longer be responded to.',
        'withdrawn' => 'This offer has been withdrawn by the programme team and can no longer be responded to.',
        default     => 'This offer is not currently awaiting a response.',
    };
    if ($offerExpired && ($offer['status'] ?? '') === 'issued') {
        $respondableMessage = 'The response deadline for this offer has passed. The offer is now expired.';
    }
}

require __DIR__ . '/offer_detail_body.php';
