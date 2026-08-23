<?php
$files = [
    'models/Application.php',
    'controllers/CandidateApplicationsController.php',
    'candidate/application_actions.php',
    'candidate/application_start.php',
    'candidate/opportunity_detail.php',
    'candidate/applications.php',
    'candidate/application_detail.php',
    'candidate/dashboard.php',
    'middleware/auth.php',
    'auth/login.php',
    'smoke_application_test.php',
];

$log = '';
$failures = 0;

foreach ($files as $file) {
    $result = @exec('php -l "' . $file . '" 2>&1', $output, $exitCode);
    $line = implode("\n", $output) . "\n";
    $log .= $line;
    if ($exitCode !== 0) {
        $failures++;
    }
    $output = [];
}

$log .= $failures === 0 ? "ALL SYNTAX CHECKS PASSED\n" : "$failures FILE(S) FAILED\n";

file_put_contents(__DIR__ . '/verify_syntax_log.txt', $log);
echo $log;
exit($failures > 0 ? 1 : 0);