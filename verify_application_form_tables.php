<?php
require_once __DIR__ . '/includes/bootstrap.php';

$conn = Database::getConnection();

foreach (['opportunity_questions', 'application_responses'] as $table) {
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table `{$table}` exists\n";
        $desc = $conn->query("DESCRIBE `{$table}`");
        if ($desc) {
            while ($row = $desc->fetch_assoc()) {
                echo "  - {$row['Field']} ({$row['Type']})\n";
            }
        }
    } else {
        echo "✗ Table `{$table}` does NOT exist\n";
    }
}