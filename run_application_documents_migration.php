<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Documents Migration Runner
 * ================================================
 * Executes database/application_documents.sql using the existing MySQLi.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$sql = file_get_contents(__DIR__ . '/database/application_documents.sql');
if ($sql === false) {
    fwrite(STDERR, "Could not read migration file.\n");
    exit(1);
}

$conn = Database::getConnection();
$queries = array_filter(array_map('trim', explode(';', $sql)));

$count = 0;
foreach ($queries as $query) {
    if ($query === '') {
        continue;
    }
    if (str_starts_with($query, '--') || str_starts_with($query, 'SET') || str_starts_with($query, 'USE')) {
        continue;
    }
    if (!$conn->query($query)) {
        fwrite(STDERR, "Migration failed: " . $conn->error . "\nSQL: " . substr($query, 0, 200) . "\n");
        exit(1);
    }
    $count++;
}

echo "Migration complete. {$count} statements executed.\n";