<?php
/**
 * CLI smoke test for the Candidate Applications module.
 * Boots app and exercises Application::forCandidate, countByStatus, find.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$candidateRow = Database::fetchOne(
    "SELECT u.id FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate'
     ORDER BY u.id ASC
     LIMIT 1"
);

if (!$candidateRow) {
    fwrite(STDERR, "No candidate user found.\n");
    exit(1);
}

$candidateId = (int) $candidateRow['id'];

// 1) forCandidate() JOIN query
$apps = Application::forCandidate($candidateId);
echo 'forCandidate OK (' . count($apps) . " rows)\n";

// 2) countByStatus() grouped stats
$counts = Application::countByStatus($candidateId);
echo 'countByStatus total=' . $counts['total']
   . ' draft=' . $counts['draft']
   . ' submitted=' . $counts['submitted']
   . ' under_review=' . $counts['under_review']
   . ' selected=' . $counts['selected']
   . ' rejected=' . $counts['rejected'] . "\n";

// 3) find() – single app detail
if (!empty($apps)) {
    $app = Application::find((int) $apps[0]['id']);
    echo $app ? 'find OK (ref=' . ($app['application_reference'] ?? 'N/A') . ")\n" : "find returned null\n";
} else {
    echo "No rows to test find()\n";
}

echo "SMOKE TEST PASSED\n";