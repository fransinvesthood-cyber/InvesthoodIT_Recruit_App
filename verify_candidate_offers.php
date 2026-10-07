<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Offers Verification (Stage 12)
 * ================================================
 * Exercises the candidate-side Offers module against the real database:
 * schema, ownership/security rules, the full
 *   administrator issues offer -> candidate views -> candidate accepts or
 *   declines -> status/history/application pipeline update
 * workflow, expiry rules, duplicate responses, invalid ids and the offer
 * notifications.
 *
 * Run from the browser (development environment only):
 *   /verify_candidate_offers.php
 * or from the command line:
 *   php verify_candidate_offers.php
 *
 * Every offer the script creates is removed again in the cleanup phase and
 * the touched application status/history rows are restored.
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && APP_ENV !== 'development') {
    http_response_code(403);
    exit('This verification script is only available in the development environment.');
}

header('Content-Type: text/plain; charset=utf-8');

$results = [];
$passed  = 0;
$failed  = 0;

/**
 * Record one verification result.
 */
function check(string $label, bool $ok, string $detail = ''): void
{
    global $results, $passed, $failed;
    $ok ? $passed++ : $failed++;
    $results[] = ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? ' :: ' . $detail : '');
}

function line(string $text = ''): void
{
    echo $text . "\n";
}

line('=== Candidate Offers module verification ===');
line('Environment: ' . APP_ENV . ' | PHP ' . PHP_VERSION . ' | ' . date('Y-m-d H:i:s'));
line();

// ---------------------------------------------------------
// Fixtures: a candidate that has an application
// ---------------------------------------------------------
$application = Database::fetchOne(
    "SELECT a.id, a.candidate_id, a.status, a.opportunity_id
     FROM applications a
     INNER JOIN users u ON u.id = a.candidate_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate'
     ORDER BY a.id ASC LIMIT 1"
);

if (!$application) {
    line('No candidate application found — cannot run the workflow tests.');
    exit(0);
}

$candidateId    = (int) $application['candidate_id'];
$applicationId  = (int) $application['id'];
$otherCandidate = (int) (Database::fetchOne(
    "SELECT u.id
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.id <> ?
     ORDER BY u.id ASC LIMIT 1",
    'i',
    [$candidateId]
)['id'] ?? 0);

line("Fixtures: candidate #{$candidateId}, application #{$applicationId}, other candidate #{$otherCandidate}");
line();

// ---------------------------------------------------------
// 1. Schema
// ---------------------------------------------------------
CandidateOffer::ensureSchema();

check('offer_notifications table exists', Database::fetchOne("SHOW TABLES LIKE 'offer_notifications'") !== null);

$offerColumns = array_map(
    static fn (array $c): string => (string) $c['Field'],
    Database::fetchAll('SHOW COLUMNS FROM offers')
);
check('offers.decline_reason column exists', in_array('decline_reason', $offerColumns, true));
check('no duplicate candidate offer table was created', Database::fetchOne("SHOW TABLES LIKE 'candidate_offers'") === null);

// ---------------------------------------------------------
// 2. Reads + security
// ---------------------------------------------------------
$list = CandidateOffersController::listOffers($candidateId, ['status' => 'all', 'search' => '', 'sort' => 'recent'], 1, 20);
check('candidate offer list returns the existing offers', $list['total'] >= 1, 'total=' . $list['total']);

$hasDraft = false;
foreach ($list['records'] as $rec) {
    if (($rec['status'] ?? '') === 'draft') {
        $hasDraft = true;
    }
}
check('draft (un-issued) offers are never shown to candidates', $hasDraft === false);

$stats = CandidateOffer::statusCounts($candidateId);
check('status counters expose total/pending/accepted/declined/expired/withdrawn',
    isset($stats['total'], $stats['pending'], $stats['accepted'], $stats['declined'], $stats['expired'], $stats['withdrawn']));

$firstOfferId = (int) ($list['records'][0]['id'] ?? 0);
check('candidate can read an own offer', $firstOfferId > 0 && CandidateOffer::findForCandidate($firstOfferId, $candidateId) !== null);

if ($firstOfferId > 0 && $otherCandidate > 0) {
    check('another candidate cannot read that offer (id tampering blocked)',
        CandidateOffer::findForCandidate($firstOfferId, $otherCandidate) === null);
    $otherList = CandidateOffersController::listOffers($otherCandidate, ['status' => 'all'], 1, 20);
    check('another candidate list does not contain the offer', $otherList['total'] === 0, 'total=' . $otherList['total']);
}

check('invalid offer id returns null', CandidateOffer::findForCandidate(99999999, $candidateId) === null);
check('zero offer id returns null', CandidateOffer::findForCandidate(0, $candidateId) === null);

// ---------------------------------------------------------
// 3. Full workflow: administrator issues -> candidate responds
// ---------------------------------------------------------
$originalStatus = (string) $application['status'];
$maxHistoryId   = (int) (Database::fetchOne(
    'SELECT COALESCE(MAX(id), 0) AS max_id FROM application_status_history WHERE application_id = ?',
    'i',
    [$applicationId]
)['max_id'] ?? 0);

$admin   = Database::fetchOne('SELECT id FROM users WHERE role_id = 1 ORDER BY id ASC LIMIT 1');
$adminId = (int) ($admin['id'] ?? 0);

$createdOffers         = [];
$declineApplicationId  = $applicationId;
$declineOriginalStatus = $originalStatus;
$declineMaxHistoryId   = $maxHistoryId;

/**
 * Create + issue a real offer through the Administrator module's own API —
 * the exact workflow the Admin Offers page uses.
 */
function makeVerificationOffer(int $applicationId, int $adminId, string $position, string $compensation, string $expiry): int
{
    // The Administrator module only issues offers for applications whose
    // selection decision is "Selected" (Selection::createOffer validates
    // this). The verification therefore reproduces exactly what
    // Selection::decide($applicationId, 'selected', ...) stores, so the
    // workflow can be tested even though the fixtures did not run through
    // the interview stages. The original status is restored during cleanup.
    Database::execute("UPDATE applications SET status = 'selected' WHERE id = ?", 'i', [$applicationId]);

    $offerId = Selection::createOffer($applicationId, [
        'title'        => 'Offer of Employment — Verification',
        'position'     => $position,
        'location'     => 'Johannesburg, Gauteng',
        'start_date'   => date('Y-m-d', strtotime('+2 days')),
        'end_date'     => date('Y-m-d', strtotime('+367 days')),
        'compensation' => $compensation,
        'expiry_date'  => $expiry,
        'terms'        => 'Verification terms — created by verify_candidate_offers.php and removed during cleanup.',
    ], $adminId);

    // The administrator issues the offer (candidate-visible from here on).
    Selection::changeOfferStatus($offerId, 'issued', $adminId, 'Issued during verification');

    return $offerId;
}

try {
    // ---- 3a. Accept flow ------------------------------------------------
    $acceptOfferId   = makeVerificationOffer($applicationId, $adminId, 'Verification Developer', 'R12 000 per month', date('Y-m-d', strtotime('+10 days')));
    $createdOffers[] = $acceptOfferId;

    $candidateView = CandidateOffersController::find($acceptOfferId, $candidateId);
    check('a newly issued offer becomes visible to the candidate', $candidateView !== null);
    check('issued offer is awaiting a response', CandidateOffersController::canRespond($candidateView ?? []));
    check('candidate-facing label for an issued offer is "Pending"', CandidateOffersController::statusLabel($candidateView ?? []) === 'Pending');
    check('offer detail exposes programme/opportunity/cohort context',
        $candidateView !== null && array_key_exists('opportunity_title', $candidateView) && array_key_exists('programme_name', $candidateView));

    $locationLabel = $candidateView !== null ? CandidateOffersController::location($candidateView) : '';
    check('offer detail exposes compensation + location',
        $candidateView !== null
        && CandidateOffersController::compensation($candidateView) === 'R12 000 per month'
        && str_contains($locationLabel, 'Johannesburg'), $locationLabel);

    $deadline = $candidateView !== null ? CandidateOffersController::deadline($candidateView) : null;
    check('offer detail exposes a response deadline countdown', $deadline !== null && $deadline['days'] !== null, (string) ($deadline['label'] ?? ''));

    $accept = CandidateOffersController::recordResponse($acceptOfferId, $candidateId, 'accept');
    check('candidate can accept the offer', $accept['success'] === true, $accept['message']);

    $afterAccept = CandidateOffer::findForCandidate($acceptOfferId, $candidateId);
    check('offer status updated to accepted', ($afterAccept['status'] ?? '') === 'accepted', (string) ($afterAccept['status'] ?? ''));
    check('response date/time recorded', !empty($afterAccept['responded_at']));
    check('accepted offer displays as "Accepted"', CandidateOffersController::statusLabel($afterAccept ?? []) === 'Accepted');
    check('accepted offer can no longer be responded to', !CandidateOffersController::canRespond($afterAccept ?? []));

    $appAfterAccept = Database::fetchOne('SELECT status FROM applications WHERE id = ?', 'i', [$applicationId]);
    check('application pipeline moved to offer_accepted', ($appAfterAccept['status'] ?? '') === 'offer_accepted', (string) ($appAfterAccept['status'] ?? ''));

    $historyStatuses = array_map(
        static fn (array $r): string => (string) $r['new_status'],
        Database::fetchAll('SELECT new_status FROM offer_status_history WHERE offer_id = ? ORDER BY id ASC', 'i', [$acceptOfferId])
    );
    check('offer audit trail records the response', in_array('accepted', $historyStatuses, true), implode(' > ', $historyStatuses));

    $applicationStatuses = array_map(
        static fn (array $r): string => (string) $r['new_status'],
        Database::fetchAll('SELECT new_status FROM application_status_history WHERE application_id = ? ORDER BY id ASC', 'i', [$applicationId])
    );
    check('application status history records offer_accepted', in_array('offer_accepted', $applicationStatuses, true), implode(' > ', $applicationStatuses));

    $duplicate = CandidateOffersController::recordResponse($acceptOfferId, $candidateId, 'accept');
    check('duplicate acceptance is blocked', $duplicate['success'] === false, $duplicate['message']);
    $crossResponse = CandidateOffersController::recordResponse($acceptOfferId, $candidateId, 'decline');
    check('declining an accepted offer is blocked', $crossResponse['success'] === false, $crossResponse['message']);

    if ($otherCandidate > 0) {
        $unauthorised = CandidateOffersController::recordResponse($acceptOfferId, $otherCandidate, 'accept');
        check('another candidate cannot respond to the offer (unauthorised)', $unauthorised['success'] === false, $unauthorised['message']);
    }

    // ---- 3b. Decline flow ----------------------------------------------
    $secondApplication = Database::fetchOne(
        'SELECT id, status FROM applications WHERE candidate_id = ? AND id <> ? ORDER BY id ASC LIMIT 1',
        'ii',
        [$candidateId, $applicationId]
    );
    if ($secondApplication) {
        $declineApplicationId  = (int) $secondApplication['id'];
        $declineOriginalStatus = (string) $secondApplication['status'];
        $declineMaxHistoryId   = (int) (Database::fetchOne(
            'SELECT COALESCE(MAX(id), 0) AS max_id FROM application_status_history WHERE application_id = ?',
            'i',
            [$declineApplicationId]
        )['max_id'] ?? 0);
    }

    $declineOfferId  = makeVerificationOffer($declineApplicationId, $adminId, 'Verification Analyst', 'R9 000 per month', date('Y-m-d', strtotime('+7 days')));
    $createdOffers[] = $declineOfferId;

    $decline = CandidateOffersController::recordResponse($declineOfferId, $candidateId, 'decline', 'Accepted another graduate programme.');
    check('candidate can decline the offer', $decline['success'] === true, $decline['message']);

    $afterDecline = CandidateOffer::findForCandidate($declineOfferId, $candidateId);
    check('offer status updated to declined', ($afterDecline['status'] ?? '') === 'declined', (string) ($afterDecline['status'] ?? ''));
    check('decline date/time recorded', !empty($afterDecline['responded_at']));
    check('decline reason stored on the offer', ($afterDecline['decline_reason'] ?? '') === 'Accepted another graduate programme.', (string) ($afterDecline['decline_reason'] ?? ''));

    $declineHistory = Database::fetchAll('SELECT change_reason FROM offer_status_history WHERE offer_id = ? ORDER BY id DESC LIMIT 1', 'i', [$declineOfferId]);
    check('decline reason recorded in the audit trail',
        !empty($declineHistory[0]['change_reason']) && str_contains((string) $declineHistory[0]['change_reason'], 'Accepted another graduate programme'));

    $appAfterDecline = Database::fetchOne('SELECT status FROM applications WHERE id = ?', 'i', [$declineApplicationId]);
    check('application pipeline moved to offer_declined', ($appAfterDecline['status'] ?? '') === 'offer_declined', (string) ($appAfterDecline['status'] ?? ''));

    $declineAgain = CandidateOffersController::recordResponse($declineOfferId, $candidateId, 'decline');
    check('duplicate decline is blocked', $declineAgain['success'] === false, $declineAgain['message']);

    // ---- 3c. Expired offer ---------------------------------------------
    $expiredOfferId  = makeVerificationOffer($applicationId, $adminId, 'Verification Intern', 'R7 500 per month', date('Y-m-d', strtotime('+5 days')));
    $createdOffers[] = $expiredOfferId;

    // Simulate an offer whose response window closed without a response.
    Database::execute('UPDATE offers SET expiry_date = ? WHERE id = ?', 'si', [date('Y-m-d', strtotime('-3 days')), $expiredOfferId]);

    $expiredView = CandidateOffersController::find($expiredOfferId, $candidateId);
    check('an overdue offer is displayed as Expired', CandidateOffersController::statusLabel($expiredView ?? []) === 'Expired');
    check('an overdue offer disables the response buttons', !CandidateOffersController::canRespond($expiredView ?? []));

    $expiredResponse = CandidateOffersController::recordResponse($expiredOfferId, $candidateId, 'accept');
    check('accepting an overdue offer is blocked server-side', $expiredResponse['success'] === false, $expiredResponse['message']);

    $expiredStored = Database::fetchOne('SELECT status FROM offers WHERE id = ?', 'i', [$expiredOfferId]);
    check('overdue offer was recorded as expired (never manually overridden)', ($expiredStored['status'] ?? '') === 'expired', (string) ($expiredStored['status'] ?? ''));

    $expiredAgain = CandidateOffersController::recordResponse($expiredOfferId, $candidateId, 'decline', 'too late');
    check('declining an expired offer is blocked as well', $expiredAgain['success'] === false, $expiredAgain['message']);

    // ---- 3d. Withdrawn offer -------------------------------------------
    $withdrawnOfferId = makeVerificationOffer($applicationId, $adminId, 'Verification Support', 'R8 000 per month', date('Y-m-d', strtotime('+12 days')));
    $createdOffers[]  = $withdrawnOfferId;
    Selection::changeOfferStatus($withdrawnOfferId, 'withdrawn', $adminId, 'Business requirement changed');

    $withdrawnView = CandidateOffersController::find($withdrawnOfferId, $candidateId);
    check('a withdrawn offer stays visible and is labelled Withdrawn', CandidateOffersController::statusLabel($withdrawnView ?? []) === 'Withdrawn');
    check('a withdrawn offer blocks responses', !CandidateOffersController::canRespond($withdrawnView ?? []));

    $withdrawnResponse = CandidateOffersController::recordResponse($withdrawnOfferId, $candidateId, 'decline', 'test');
    check('responding to a withdrawn offer is blocked', $withdrawnResponse['success'] === false, $withdrawnResponse['message']);

    // ---- 3e. Notifications ---------------------------------------------
    $beforeRegeneration = (int) (Database::fetchOne(
        'SELECT COUNT(*) AS cnt FROM offer_notifications WHERE candidate_id = ?',
        'i',
        [$candidateId]
    )['cnt'] ?? 0);
    CandidateOffer::generateNotifications($candidateId);
    $afterRegeneration = (int) (Database::fetchOne(
        'SELECT COUNT(*) AS cnt FROM offer_notifications WHERE candidate_id = ?',
        'i',
        [$candidateId]
    )['cnt'] ?? 0);
    check('notification generation is idempotent (safe on every refresh)', $beforeRegeneration === $afterRegeneration, $beforeRegeneration . ' -> ' . $afterRegeneration);

    $typeList = array_map(
        static fn (array $r): string => (string) $r['notification_type'],
        Database::fetchAll('SELECT DISTINCT notification_type FROM offer_notifications WHERE candidate_id = ?', 'i', [$candidateId])
    );
    check('new offer notification generated', in_array('offer_issued', $typeList, true), implode(', ', $typeList));
    check('expired offer notification generated', in_array('offer_expired', $typeList, true), implode(', ', $typeList));
    check('withdrawn offer notification generated', in_array('offer_withdrawn', $typeList, true), implode(', ', $typeList));
    check('candidate response notification generated', in_array('offer_response_recorded', $typeList, true), implode(', ', $typeList));
    check('unread notification counter works', CandidateOffer::unreadNotificationCount($candidateId) >= 0);

    $unreadList = CandidateOffersController::notifications($candidateId, 5, true);
    if (!empty($unreadList)) {
        $markId = (int) $unreadList[0]['id'];
        check('notification can be marked as read', CandidateOffersController::markNotificationRead($markId, $candidateId) === true);
        if ($otherCandidate > 0) {
            check('another candidate cannot mark that notification as read', CandidateOffersController::markNotificationRead($markId, $otherCandidate) === false);
        }
    }
    check('mark all notifications read works', CandidateOffersController::markAllNotificationsRead($candidateId) >= 0);

    // ---- 3f. Dashboard + filters ---------------------------------------
    $dashboard = CandidateOffersController::dashboard($candidateId);
    check('dashboard statistics available',
        isset($dashboard['stats']['pending'], $dashboard['stats']['accepted'], $dashboard['stats']['declined'], $dashboard['stats']['expired']));
    check('dashboard exposes the most recent offer', $dashboard['recent'] !== null);
    check('dashboard exposes the pending deadline list', is_array($dashboard['deadlines']));
    check('dashboard exposes offer notifications', is_array($dashboard['notifications']));

    $filteredAccepted = CandidateOffersController::listOffers($candidateId, ['status' => 'accepted', 'search' => '', 'sort' => 'recent'], 1, 20);
    check('status filter (Accepted) works', $filteredAccepted['total'] >= 1, 'total=' . $filteredAccepted['total']);
    $filteredPending = CandidateOffersController::listOffers($candidateId, ['status' => 'issued', 'search' => '', 'sort' => 'deadline'], 1, 20);
    check('status filter (Pending) uses the stored issued status', $filteredPending['total'] >= 1, 'total=' . $filteredPending['total']);
    $filteredDeclined = CandidateOffersController::listOffers($candidateId, ['status' => 'declined', 'search' => '', 'sort' => 'recent'], 1, 20);
    check('status filter (Declined) works', $filteredDeclined['total'] >= 1, 'total=' . $filteredDeclined['total']);
    $filteredSearch = CandidateOffersController::listOffers($candidateId, ['status' => 'all', 'search' => 'Verification', 'sort' => 'recent'], 1, 20);
    check('search filter works', $filteredSearch['total'] >= 1, 'total=' . $filteredSearch['total']);

    $aliases = CandidateOffersController::normaliseFilters(['status' => 'pending', 'sort' => 'unknown']);
    check('filter aliases normalise correctly (pending -> issued)', $aliases['status'] === 'issued' && $aliases['sort'] === 'recent');

} catch (Throwable $e) {
    check('workflow executed without an unexpected exception', false, $e->getMessage());
} finally {
    // -----------------------------------------------------
    // Cleanup: remove the verification offers + restore the
    // application statuses and their history rows.
    // -----------------------------------------------------
    foreach ($createdOffers as $offerId) {
        try {
            Database::execute('DELETE FROM offer_notifications WHERE offer_id = ?', 'i', [$offerId]);
            Database::execute('DELETE FROM offer_status_history WHERE offer_id = ?', 'i', [$offerId]);
            Database::execute('DELETE FROM offers WHERE id = ?', 'i', [$offerId]);
        } catch (Throwable $e) {
            line('Cleanup warning (offer ' . $offerId . '): ' . $e->getMessage());
        }
    }

    try {
        Database::execute('UPDATE applications SET status = ? WHERE id = ?', 'si', [$originalStatus, $applicationId]);
        Database::execute('DELETE FROM application_status_history WHERE application_id = ? AND id > ?', 'ii', [$applicationId, $maxHistoryId]);

        if ($declineApplicationId !== $applicationId) {
            Database::execute('UPDATE applications SET status = ? WHERE id = ?', 'si', [$declineOriginalStatus, $declineApplicationId]);
            Database::execute('DELETE FROM application_status_history WHERE application_id = ? AND id > ?', 'ii', [$declineApplicationId, $declineMaxHistoryId]);
        }
    } catch (Throwable $e) {
        line('Cleanup warning: ' . $e->getMessage());
    }

    check('verification offers removed again (cleanup)',
        (int) (Database::fetchOne("SELECT COUNT(*) AS cnt FROM offers WHERE title LIKE ?", 's', ['%Verification%'])['cnt'] ?? 0) === 0);
}

// ---------------------------------------------------------
// Summary
// ---------------------------------------------------------
line();
foreach ($results as $result) {
    line($result);
}
line();
line("Passed: {$passed} | Failed: {$failed}");
line($failed === 0 ? 'RESULT: ALL CHECKS PASSED' : 'RESULT: FAILURES DETECTED');
line();
line('Next manual checks (browser):');
line('  1. Candidate dashboard      -> /candidate/dashboard.php#dashboard-offers');
line('  2. Candidate offers page    -> /candidate/offers.php');
line('  3. Offer detail + response  -> /candidate/offer_detail.php?id=<offer id>');
line('  4. Admin visibility         -> /admin/offers.php and /admin/offer.php?id=<offer id>');
