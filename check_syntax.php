<?php
$files = [
    'models/ApplicationQuestion.php',
    'models/ApplicationResponse.php',
    'models/ApplicationDocument.php',
    'controllers/ApplicationFormController.php',
    'candidate/application_form.php',
    'candidate/application_actions.php',
    'candidate/application_document.php',
    'candidate/application_start.php',
    'candidate/application_detail.php',
    'run_application_documents_migration.php',
    'js/application_form.js',
];

$output = '';
foreach ($files as $file) {
    $result = shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
    $output .= $file . ': ' . trim($result) . "\n";
}

file_put_contents('syntax_check_result.txt', $output);
echo "Done. Check syntax_check_result.txt\n";