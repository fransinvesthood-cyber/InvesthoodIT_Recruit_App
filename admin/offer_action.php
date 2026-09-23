<?php
/**
 * ================================================
 * INVESTHOOD IT - Offer Action Handler (Stage 11)
 * ================================================
 * POST endpoint for the Offer Management module.
 * Handles three server-authorised actions:
 *
 *   create : create a Draft offer for a SELECTED candidate
 *   update : edit a Draft offer (issued offers are locked)
 *   status : move an offer through its allowed status flow
 *            (draft -> issued -> accepted/declined/expired/withdrawn)
 *
 * The admin id is always taken from the session — never from
 * the browser. All state changes run inside database
 * transactions inside the Selection model.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
enforce_csrf();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid Request', 'Method not allowed.');
    safe_redirect('admin/offers.php');
}

// Protect — only Administrator
require_role('admin');

$adminId = (int) current_user_id();
$action  = (string) ($_POST['action'] ?? '');

// Used to send the administrator back to the right page on failure.
$fallbackApplication = (int) ($_POST['application_id'] ?? 0);
$fallbackOffer       = (int) ($_POST['offer_id'] ?? 0);

/**
 * Send the administrator back with the validation message.
 */
function offer_action_fail(string $message, int $offerId, int $applicationId): void
{
    set_flash('error', 'Action Not Completed', $message);
    if ($offerId > 0) {
        safe_redirect('admin/offer_form.php?id=' . $offerId);
    }
    if ($applicationId > 0) {
        safe_redirect('admin/offer_form.php?application=' . $applicationId);
    }
    safe_redirect('admin/offers.php');
}

try {
    switch ($action) {
        // --------------------------------------------------
        // CREATE — Draft offer for a selected candidate
        // --------------------------------------------------
        case 'create':
            if ($fallbackApplication <= 0) {
                offer_action_fail('No application was provided for this offer.', 0, 0);
            }

            $clean   = Selection::validateOfferData($_POST);
            $offerId = Selection::createOffer($fallbackApplication, $clean, $adminId);

            set_flash(
                'success',
                'Draft Offer Created',
                'The draft offer was created. Review the details and issue it when the candidate is ready to receive it.'
            );
            safe_redirect('admin/offer.php?id=' . $offerId);
            break;

        // --------------------------------------------------
        // UPDATE — Draft offers only
        // --------------------------------------------------
        case 'update':
            if ($fallbackOffer <= 0) {
                offer_action_fail('No offer was provided.', 0, 0);
            }

            $clean = Selection::validateOfferData($_POST);
            Selection::updateOffer($fallbackOffer, $clean, $adminId);

            set_flash('success', 'Offer Updated', 'The draft offer details were saved.');
            safe_redirect('admin/offer.php?id=' . $fallbackOffer);
            break;

        // --------------------------------------------------
        // STATUS — allowed offer-status transitions
        // --------------------------------------------------
        case 'status':
            $newStatus = (string) ($_POST['status'] ?? '');
            $reason    = trim((string) ($_POST['reason'] ?? ''));

            if ($fallbackOffer <= 0) {
                offer_action_fail('No offer was provided.', 0, 0);
            }

            if (!in_array($newStatus, Selection::OFFER_STATUSES, true)) {
                offer_action_fail('Please choose a valid offer status.', $fallbackOffer, 0);
            }

            $result = Selection::changeOfferStatus(
                $fallbackOffer,
                $newStatus,
                $adminId,
                $reason !== '' ? $reason : null
            );

            set_flash(
                'success',
                'Offer Status Updated',
                'The offer status changed from "' . Selection::offerStatusLabel($result['previous_status'])
                . '" to "' . Selection::offerStatusLabel($result['new_status'])
                . '". The application timeline has been updated accordingly.'
            );
            safe_redirect('admin/offer.php?id=' . $fallbackOffer);
            break;

        default:
            offer_action_fail('Unrecognised offer action.', 0, 0);
    }
} catch (RuntimeException $e) {
    // Validation / business-rule failure — safe to show the message.
    offer_action_fail($e->getMessage(), $fallbackOffer, $fallbackApplication);
} catch (Exception $e) {
    error_log('[Admin Offer Action] Error: ' . $e->getMessage());
    set_flash('error', 'Error', 'An error occurred while processing the offer. Please try again.');
    safe_redirect('admin/offers.php');
}
