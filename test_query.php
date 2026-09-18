<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Test the corrected query
$result = Database::fetchOne("SELECT COUNT(*) AS cnt FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = 'candidate'");
$totalCandidatesCount = (int) ($result['cnt'] ?? 0);

echo "Total registered candidates: " . $totalCandidatesCount . PHP_EOL;

// Also show the candidates for verification
$candidates = Database::fetchAll("SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = 'candidate'");
echo PHP_EOL . "Registered candidates:" . PHP_EOL;
foreach ($candidates as $candidate) {
    echo "- {$candidate['first_name']} {$candidate['last_name']} ({$candidate['email']})" . PHP_EOL;
}
