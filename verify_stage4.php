<?php
/**
 * ================================================
 * INVESTHOOD IT - Stage 4 Verification
 * ================================================
 * Verifies the application_documents table exists
 * and the key Stage 4 files are syntactically valid.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$conn = Database::getConnection();

echo "=== Stage 4 Verification ===\n\n";

// 1. Check application_documents table
echo "--- Database ---\n";
$result = $conn->query("SHOW TABLES LIKE 'application_documents'");
if ($result && $result->num_rows > 0) {
    echo "✓ Table `application_documents` exists\n";
    $desc = $conn->query("DESCRIBE `application_documents`");
    if ($desc) {
        while ($row = $desc->fetch_assoc()) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
    }
} else {
    echo "✗ Table `application_documents` does NOT exist\n";
}

echo "\n--- Files ---\n";

// 2. Check key files exist
$files = [
    'models/ApplicationDocument.php',
    'controllers/ApplicationFormController.php',
    'candidate/application_form.php',
    'candidate/application_actions.php',
    'candidate/application_document.php',
    'js/application_form.js',
    'css/application_form.css',
    'database/application_documents.sql',
    'run_application_documents_migration.php',
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ {$file}\n";
    } else {
        echo "✗ {$file} MISSING\n";
    }
}

echo "\n--- PHP Syntax ---\n";

// 3. Check PHP syntax of modified PHP files
$phpFiles = [
    'models/ApplicationDocument.php',
    'controllers/ApplicationFormController.php',
    'candidate/application_form.php',
    'candidate/application_actions.php',
    'candidate/application_document.php',
    'run_application_documents_migration.php',
];

foreach ($phpFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "✗ {$file} MISSING\n";
        continue;
    }
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code === 0) {
        echo "✓ {$file} syntax OK\n";
    } else {
        echo "✗ {$file} syntax ERROR:\n";
        echo implode("\n", $output) . "\n";
    }
}

echo "\n--- Done ---\n";