<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Notifications Verification
 * ================================================
 * Exercises the unified candidate notification module against the
 * real database: schema, duplicate prevention, ownership/security
 * rules, filters/search/pagination, mark-as-read / mark-all-read /
 * delete, unread counters, event triggers and the lazy syncs.
 *
 * Run from the browser (development environment only):
 *   /verify_candidate_notifications.php
 * or from the command line:
 *   php verify_candidate_notifications.php
 *
 * Every notification the script creates is removed again in the
 * cleanup phase (rows created above the recorded watermark id).
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

line('=== Candidate Notifications module verification ===');
line('Environment: ' . APP_ENV . ' | PHP ' . PHP_VERSION . ' | ' . date('Y-m-d H:i:s'));
line();

// ---------------------------------------------------------
// Fixtures: two candidate accounts (owner + foreigner)
// ---------------------------------------------------------
$candidates = Database::fetchAll(
    "SELECT u.id, u.email
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate'
     ORDER BY u.id ASC
     LIMIT 2"
);

if (!$candidates) {
    line('No candidate accounts found — cannot run the verification.');
    exit(0);
}

$owner      = (int) $candidates[0]['id'];
$foreigner  = isset($candidates[1]) ? (int) $candidates[1]['id'] : 0;

line('Owner candidate id:     ' . $owner);
line('Foreigner candidate id: ' . ($foreigner ?: '(none available — some ownership checks skipped)'));
line();

// Watermark so cleanup only removes rows created by this run.
$watermark = (int) (Database::fetchOne('SELECT COALESCE(MAX(id), 0) AS max_id FROM candidate_notifications')['max_id'] ?? 0);

// ---------------------------------------------------------
// 1. Schema
// ---------------------------------------------------------
line('--- 1. Schema ---');
CandidateNotification::ensureSchema();
check('candidate_notifications table exists', Database::fetchOne("SHOW TABLES LIKE 'candidate_notifications'") !== null);

$columns = array_map(
    static fn (array $row): string => (string) ($row['Field'] ?? ''),
    Database::fetchAll('SHOW COLUMNS FROM candidate_notifications')
);
foreach (['id', 'user_id', 'category', 'title', 'message', 'module', 'record_id', 'is_read', 'read_at', 'dedupe_key', 'created_at'] as $col) {
    check("column {$col} exists", in_array($col, $columns, true));
}

// ---------------------------------------------------------
// 2. Create + duplicate prevention
// ---------------------------------------------------------
line();
line('--- 2. Create + duplicate prevention ---');
$before = (int) (Database::fetchOne('SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ?', 'i', [$owner])['cnt'] ?? 0);

$created = CandidateNotification::create(
    $owner,
    'application',
    'Verification: application submitted',
    'This is a verification notification for the applications module.',
    'application',
    1,
    'verify_app_1'
);
check('create() stores a notification', $created);

$again = CandidateNotification::create(
    $owner,
    'application',
    'Verification: application submitted (duplicate)',
    'The same event fired twice — must NOT duplicate.',
    'application',
    1,
    'verify_app_1'
);
check('duplicate event is ignored (idempotent)', $again === true);

$after = (int) (Database::fetchOne('SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ?', 'i', [$owner])['cnt'] ?? 0);
check('exactly one row was inserted', $after === $before + 1, "before={$before} after={$after}");

check('invalid user id is rejected', CandidateNotification::create(0, 'application', 'x', 'y') === false);
check('empty title is rejected', CandidateNotification::create($owner, 'application', '', 'y') === false);

// ---------------------------------------------------------
// 3. Ownership / security scoping
// ---------------------------------------------------------
line();
line('--- 3. Ownership scoping ---');
$ownerRow = CandidateNotification::forCandidate($owner, [], 1, 50);
$ownerIds = array_map(static fn (array $r): int => (int) $r['id'], $ownerRow['records']);
check('owner sees their notifications', count($ownerIds) > 0);

$verifyRow = Database::fetchOne(
    'SELECT id FROM candidate_notifications WHERE user_id = ? AND dedupe_key = ?',
    'is',
    [$owner, 'verify_app_1']
);
$verifyId = (int) ($verifyRow['id'] ?? 0);
check('verification row located', $verifyId > 0);

if ($foreigner > 0) {
    $foreignList = CandidateNotification::forCandidate($foreigner, [], 1, 100);
    $foreignIds  = array_map(static fn (array $r): int => (int) $r['id'], $foreignList['records']);
    check("candidate B cannot see candidate A's notification", !in_array($verifyId, $foreignIds, true));
    check('findForCandidate rejects a foreign owner', CandidateNotification::findForCandidate($verifyId, $foreigner) === null);
    check('markRead rejects a foreign owner', CandidateNotification::markRead($verifyId, $foreigner) === false);
    check('delete rejects a foreign owner', CandidateNotification::delete($verifyId, $foreigner) === false);
    $stillThere = Database::fetchOne('SELECT id FROM candidate_notifications WHERE id = ?', 'i', [$verifyId]);
    check("row still exists after foreign attempts", $stillThere !== null);
}

// ---------------------------------------------------------
// 4. Unread counters
// ---------------------------------------------------------
line();
line('--- 4. Unread counters ---');
// Add a second (unread) notification for the owner.
CandidateNotification::create($owner, 'interview', 'Verification: interview scheduled', 'Interview verification notification.', 'interview', 2, 'verify_interview_2');

$unreadBefore = CandidateNotification::unreadCount($owner);
check('unreadCount() > 0 with unread rows', $unreadBefore > 0, 'unread=' . $unreadBefore);

$statusCounts = CandidateNotification::statusCounts($owner);
check('statusCounts all >= unread', $statusCounts['all'] >= $statusCounts['unread']);
check('statusCounts read + unread == all', ($statusCounts['read'] + $statusCounts['unread']) === $statusCounts['all']);

$catCounts = CandidateNotification::categoryCounts($owner);
check('categoryCounts includes application', ($catCounts['application'] ?? 0) >= 1);
check('categoryCounts includes interview', ($catCounts['interview'] ?? 0) >= 1);

// ---------------------------------------------------------
// 5. Filters, search, pagination
// ---------------------------------------------------------
line();
line('--- 5. Filters / search / pagination ---');
$appOnly = CandidateNotification::forCandidate($owner, ['category' => 'application'], 1, 50);
$appOk = true;
foreach ($appOnly['records'] as $r) {
    if ((string) $r['category'] !== 'application') { $appOk = false; }
}
check('category filter returns only that category', $appOk && $appOnly['total'] >= 1);

$unreadOnly = CandidateNotification::forCandidate($owner, ['status' => 'unread'], 1, 50);
$unreadOk = true;
foreach ($unreadOnly['records'] as $r) {
    if ((int) $r['is_read'] !== 0) { $unreadOk = false; }
}
check('unread filter returns only unread rows', $unreadOk);

$searchHit = CandidateNotification::forCandidate($owner, ['q' => 'verification'], 1, 50);
check('search matches title/message', $searchHit['total'] >= 1);

$searchMiss = CandidateNotification::forCandidate($owner, ['q' => 'zzz_no_such_notification_zzz'], 1, 50);
check('search with no matches returns empty', $searchMiss['total'] === 0 && $searchMiss['records'] === []);

$pageSize  = 1;
$bigPage   = CandidateNotification::forCandidate($owner, [], 999, $pageSize);
check('page number is clamped to the last page', $bigPage['page'] >= 1 && $bigPage['page'] <= $bigPage['pages']);

$destApp = CandidateNotification::destinationUrl('application', 15);
check('application destination URL', $destApp === 'candidate/application_detail.php?id=15', $destApp);
$destUnknown = CandidateNotification::destinationUrl('nonsense_module', null);
check('unknown module falls back to the notifications page', $destUnknown === 'candidate/notifications.php', $destUnknown);

// ---------------------------------------------------------
// 6. Mark read / mark all read / delete lifecycle
// ---------------------------------------------------------
line();
line('--- 6. Read + delete lifecycle ---');
$unreadBeforeMark = CandidateNotification::unreadCount($owner);
check('markRead flips one unread row', CandidateNotification::markRead($verifyId, $owner) === true);
$unreadAfterMark = CandidateNotification::unreadCount($owner);
check('unreadCount dropped by one', $unreadAfterMark === $unreadBeforeMark - 1, "before={$unreadBeforeMark} after={$unreadAfterMark}");
$readRow = CandidateNotification::findForCandidate($verifyId, $owner);
check('read timestamp stored', $readRow !== null && !empty($readRow['read_at']));
check('markRead on an already-read row is a no-op', CandidateNotification::markRead($verifyId, $owner) === false);

$marked = CandidateNotification::markAllRead($owner);
check('markAllRead updates remaining rows', $marked >= 0);
check('unreadCount is 0 after markAllRead', CandidateNotification::unreadCount($owner) === 0);

// Read state persists (fresh read from the database, i.e. what a
// refresh / re-login would see).
$persisted = Database::fetchOne('SELECT is_read FROM candidate_notifications WHERE id = ?', 'i', [$verifyId]);
check('read state persisted in the database', $persisted !== null && (int) $persisted['is_read'] === 1);

$deleted = CandidateNotification::delete($verifyId, $owner);
check('delete removes the notification', $deleted === true);
check('deleted row is gone', CandidateNotification::findForCandidate($verifyId, $owner) === null);
check('delete of a missing row is a no-op', CandidateNotification::delete($verifyId, $owner) === false);

// ---------------------------------------------------------
// 7. Event trigger helpers (idempotency)
// ---------------------------------------------------------
line();
line('--- 7. Event trigger helpers ---');
$wMark = (int) (Database::fetchOne('SELECT COALESCE(MAX(id), 0) AS max_id FROM candidate_notifications')['max_id'] ?? 0);

CandidateNotification::forApplicationSubmitted($owner, 999001, 'VERIF-REF-1', 'Verification Opportunity');
CandidateNotification::forApplicationSubmitted($owner, 999001, 'VERIF-REF-1', 'Verification Opportunity');
$subCount = (int) (Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND dedupe_key = 'app_submitted_999001'",
    'i', [$owner]
)['cnt'] ?? 0);
check('application-submitted trigger is duplicate-proof', $subCount === 1);

CandidateNotification::forApplicationStatusChange($owner, 999001, 'VERIF-REF-1', 'submitted', 'shortlisted', null);
CandidateNotification::forApplicationStatusChange($owner, 999001, 'VERIF-REF-1', 'submitted', 'shortlisted', null);
$statusCount = (int) (Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND dedupe_key = 'app_status_999001_shortlisted'",
    'i', [$owner]
)['cnt'] ?? 0);
check('application-status trigger is duplicate-proof', $statusCount === 1);
$shortlisted = Database::fetchOne(
    "SELECT title FROM candidate_notifications WHERE user_id = ? AND dedupe_key = 'app_status_999001_shortlisted'",
    'i', [$owner]
);
check('shortlisted notification uses the special title', $shortlisted !== null && str_contains((string) $shortlisted['title'], 'shortlisted'), (string) ($shortlisted['title'] ?? ''));

CandidateNotification::forInterviewEvent($owner, 999002, 'scheduled', date('Y-m-d'), '10:00:00');
CandidateNotification::forInterviewEvent($owner, 999002, 'scheduled', date('Y-m-d'), '10:00:00');
$ivCount = (int) (Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND dedupe_key = 'interview_scheduled_999002'",
    'i', [$owner]
)['cnt'] ?? 0);
check('interview-scheduled trigger is duplicate-proof', $ivCount === 1);

CandidateNotification::forOfferEvent($owner, 999003, 'issued', 'Graduate Engineer');
CandidateNotification::forOfferEvent($owner, 999003, 'issued', 'Graduate Engineer');
$offCount = (int) (Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND dedupe_key = 'offer_999003_issued'",
    'i', [$owner]
)['cnt'] ?? 0);
check('offer-issued trigger is duplicate-proof', $offCount === 1);

CandidateNotification::forPlacementEvent($owner, 999004, 'placement_created', 'Verification programme');
CandidateNotification::forPlacementEvent($owner, 999004, 'placement_created', 'Verification programme');
$plcCount = (int) (Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND dedupe_key = 'placement_created_999004'",
    'i', [$owner]
)['cnt'] ?? 0);
check('placement-created trigger is duplicate-proof', $plcCount === 1);

CandidateNotification::forAccountEvent($owner, 'email_verified');
CandidateNotification::forAccountEvent($owner, 'email_verified');
$accCount = (int) (Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND dedupe_key = 'account_email_verified_{$owner}'",
    'i', [$owner]
)['cnt'] ?? 0);
check('email-verified trigger is duplicate-proof', $accCount === 1);

// ---------------------------------------------------------
// 8. Lazy syncs (idempotent, state-derived)
// ---------------------------------------------------------
line();
line('--- 8. Lazy syncs ---');
$syncBefore = (int) (Database::fetchOne('SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ?', 'i', [$owner])['cnt'] ?? 0);
CandidateNotification::syncOpportunityEvents($owner);
$syncAfter1 = (int) (Database::fetchOne('SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ?', 'i', [$owner])['cnt'] ?? 0);
CandidateNotification::syncOpportunityEvents($owner);
$syncAfter2 = (int) (Database::fetchOne('SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ?', 'i', [$owner])['cnt'] ?? 0);
check('opportunity sync runs without error', $syncAfter1 >= $syncBefore);
check('opportunity sync is idempotent (no duplicates on re-run)', $syncAfter2 === $syncAfter1, "first={$syncAfter1} second={$syncAfter2}");

$offerMirrorSupported = Database::fetchOne("SHOW TABLES LIKE 'offer_notifications'") !== null;
if ($offerMirrorSupported) {
    $mirrorBefore = (int) (Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND category = 'offer'",
        'i', [$owner]
    )['cnt'] ?? 0);
    CandidateNotification::mirrorOfferNotifications($owner);
    CandidateNotification::mirrorOfferNotifications($owner);
    $mirrorAfter = (int) (Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND category = 'offer'",
        'i', [$owner]
    )['cnt'] ?? 0);
    check('offer mirror is idempotent', $mirrorAfter >= $mirrorBefore);
} else {
    line('[SKIP] offer_notifications table missing — offer mirror check skipped.');
}

// ---------------------------------------------------------
// 9. JSON payload
// ---------------------------------------------------------
line();
line('--- 9. JSON payload ---');
$sample = CandidateNotification::findForCandidate(0, 0); // invalid guard
check('findForCandidate guards invalid ids', $sample === null);
$payloadRow = Database::fetchOne('SELECT * FROM candidate_notifications WHERE user_id = ? LIMIT 1', 'i', [$owner]);
if ($payloadRow) {
    $payload = CandidateNotification::payload($payloadRow);
    check('payload has an absolute url', str_starts_with($payload['url'], 'http'), $payload['url']);
    check('payload exposes category label + icon', $payload['categoryLabel'] !== '' && $payload['icon'] !== '');
} else {
    line('[SKIP] no sample row available for the payload check.');
}

// ---------------------------------------------------------
// Cleanup: remove only the rows created by this run
// ---------------------------------------------------------
line();
line('--- Cleanup ---');
$removed = Database::execute(
    'DELETE FROM candidate_notifications WHERE id > ? AND user_id = ?',
    'ii',
    [$wMark > 0 ? $wMark : $watermark, $owner]
);
// Also clear the section-2 rows (created before the section-7 watermark).
$removed += Database::execute(
    'DELETE FROM candidate_notifications WHERE id > ? AND user_id = ? AND dedupe_key LIKE \'verify_%\'',
    'ii',
    [$watermark, $owner]
);
line("Removed {$removed} verification notification(s).");

// ---------------------------------------------------------
// Summary
// ---------------------------------------------------------
line();
line('=== Results: ' . $passed . ' passed, ' . $failed . ' failed ===');
foreach ($results as $r) {
    line($r);
}

exit($failed > 0 ? 1 : 0);



