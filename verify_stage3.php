<?php
require_once __DIR__ . '/includes/bootstrap.php';

$output = "=== STAGE 3 VERIFICATION ===\n\n";

// 1. Check database tables
$conn = Database::getConnection();
foreach (['opportunity_questions', 'application_responses'] as $table) {
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    $output .= ($result && $result->num_rows > 0)
        ? "✓ Table `{$table}` exists\n"
        : "✗ Table `{$table}` does NOT exist\n";
}

// 2. Check PHP syntax of new files
$files = [
    'models/ApplicationQuestion.php',
    'models/ApplicationResponse.php',
    'controllers/ApplicationFormController.php',
    'candidate/application_form.php',
    'candidate/application_actions.php',
];

foreach ($files as $file) {
    $cmd = 'php -l ' . escapeshellarg($file) . ' 2>&1';
    $result = shell_exec($cmd);
    $output .= $file . ': ' . trim($result) . "\n";
}

// 3. Check classes load
$output .= "\n=== CLASS LOADING ===\n";
try {
    $q = new ApplicationQuestion();
    $output .= "✓ ApplicationQuestion class loads\n";
} catch (Throwable $e) {
    $output .= "✗ ApplicationQuestion: " . $e->getMessage() . "\n";
}

try {
    $r = new ApplicationResponse();
    $output .= "✓ ApplicationResponse class loads\n";
} catch (Throwable $e) {
    $output .= "✗ ApplicationResponse: " . $e->getMessage() . "\n";
}

try {
    $c = new ApplicationFormController();
    $output .= "✓ ApplicationFormController class loads\n";
} catch (Throwable $e) {
    $output .= "✗ ApplicationFormController: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/stage3_verification.txt', $output);
echo "Verification complete. Check stage3_verification.txt\n";