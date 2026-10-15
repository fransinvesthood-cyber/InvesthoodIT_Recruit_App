<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Offer Action Handler
 * ================================================
 * POST endpoint for the candidate Accept / Decline workflow.
 *
 * Security:
 *  - require_role('candidate') rejects non-candidates.
 *  - Candidate id is taken ONLY from the session (never from POST/GET).
 *  - Offer ownership + deadline + transition rules are enforced
 *    server-side inside CandidateOffer::respond(), which delegates to
 *    Selection::changeOfferStatus() — the same code path the Admin
 *    Selection & Offers module uses. No duplicate workflow.
 *  - CSRF protected via enforce_csrf().
 */

require_once __DIR__ . '/../includes/bootstrap.php';

enforce_csrf();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    set_flash('error', 'Invalid Request', 'Method not allowed.');
    safe_redirect('candidate/offers.php');
}

require_role('candidate');

$candidateId = (int) current_user_id();
$offerId     = (int) ($_POST['offer_id'] ?? 0);
$action      = strtolower(trim((string) ($_POST['response'] ?? ($_POST['action'] ?? ''))));
$reason      = trim((string) ($_POST['decline_reason'] ?? ($_POST['reason'] ?? '')));
$returnTo    = trim((string) ($_POST['return_to'] ?? ''));

function candidate_offer_action_fail(string $message, int $offerId, string $returnTo): void
{
    set_flash('error', 'Action Not Completed', $message);
    if ($offerId > 0 && str_starts_with($returnTo, 'detail')) {
        safe_redirect('candidate/offer_detail.php?id=' . $offerId);
    }
    if ($offerId > 0 && str_starts_with($returnTo, 'dashboard')) {
        safe_redirect('candidate/dashboard.php#dashboard-offers');
    }
    if ($offerId > 0) {
        safe_redirect('candidate/offer_detail.php?id=' . $offerId);
    }
    safe_redirect('candidate/offers.php');
}

try {
    if ($offerId <= 0) {
        candidate_offer_action_fail('No offer was provided.', 0, $returnTo);
    }

    $response = match ($action) {
        'accept', 'accepted' => 'accepted',
        'decline', 'declined' => 'declined',
        default => null,
    };

    if ($response === null) {
        candidate_offer_action_fail('Please choose a valid response (Accept or Decline).', $offerId, $returnTo);
    }

    $result = CandidateOffer::respond($offerId, $candidateId, $response, $reason !== '' ? $reason : null);

    $label = (string) ($result['label'] ?? ucfirst($response));
    set_flash(
        'success',
        'Offer ' . $label,
        $response === 'accepted'
            ? 'You accepted the offer. The programme team has been notified and will arrange your placement.'
            : 'You declined the offer. The programme team has been notified.'
    );

    if (str_starts_with($returnTo, 'dashboard')) {
        safe_redirect('candidate/dashboard.php#dashboard-offers');
    }
    safe_redirect('candidate/offer_detail.php?id=' . $offerId);
} catch (RuntimeException $e) {
    candidate_offer_action_fail($e->getMessage(), $offerId, $returnTo);
} catch (Throwable $e) {
    error_log('[Candidate Offer Action] Error: ' . $e->getMessage());
    set_flash('error', 'Error', 'An error occurred while recording your response. Please try again.');
    safe_redirect($offerId > 0 ? 'candidate/offer_detail.php?id=' . $offerId : 'candidate/offers.php');
}
