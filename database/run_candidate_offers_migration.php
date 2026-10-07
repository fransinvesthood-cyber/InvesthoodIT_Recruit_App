<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Offers Migration Runner
 * ================================================
 * Executes database/candidate_offers.sql using the existing MySQLi
 * connection. The script is idempotent:
 *   - CREATE TABLE IF NOT EXISTS statements are safe to re-run
 *   - the offers.decline_reason ALTER is skipped when the column
 *     already exists (MySQL has no conditional ADD COLUMN)
 *
 * Usage (CLI):  php database/run_candidate_offers_migration.php
 * Usage (web):  /database/run_candidate_offers_migration.php (development only)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$sqlFile = __DIR__ . '/candidate_offers.sql';
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    exit("Could not read migration file.\n");
}

$conn = Database::getConnection();

/**
 * Whether a column already exists on a table.
 */
function column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable  = preg_replace('/[^a-z0-9_]/i', '', $table);
    $safeColumn = preg_replace('/[^a-z0-9_]/i', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

$statements = array_filter(array_map('trim', explode(';', $sql)));

$executed = 0;
$skipped  = 0;

foreach ($statements as $statement) {
    if ($statement === '' || str_starts_with($statement, '--') || str_starts_with($statement, 'SET') || str_starts_with($statement, 'USE')) {
        continue;
    }

    // Conditional column addition (offers.decline_reason).
    if (stripos($statement, 'ALTER TABLE') === 0 && stripos($statement, 'ADD COLUMN') !== false) {
        if (preg_match('/ADD COLUMN\s+`([a-z0-9_]+)`/i', $statement, $m) === 1
            && preg_match('/ALTER TABLE\s+`([a-z0-9_]+)`/i', $statement, $t) === 1
            && column_exists($conn, $t[1], $m[1])) {
            $skipped++;
            continue;
        }
    }

    if (!$conn->query($statement)) {
        exit("Migration failed: " . $conn->error . "\nSQL: " . substr($statement, 0, 200) . "\n");
    }
    $executed++;
}

echo "Candidate Offers migration complete. {$executed} statement(s) executed, {$skipped} skipped (already applied).\n";
