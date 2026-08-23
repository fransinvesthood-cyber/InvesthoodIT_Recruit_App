<?php
/**
 * Final verification of all Stage 2 changes.
 * Writes results to verify_final_log.txt for review.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$log = '';
$log .= "===== STAGE 2 VERIFICATION =====" . PHP_EOL . PHP_EOL;

// 1. Verify all modified files exist
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
];

$log .= "--- 1. File existence ---" . PHP_EOL;
foreach ($files as $file) {
    $log .= (file_exists($file) ? 'OK: ' : 'MISSING: ') . $file . PHP_EOL;
}

// 2. Run php -l on each modified file
$log .= PHP_EOL . "--- 2. PHP syntax lint ---" . PHP_EOL;
$failures = 0;
foreach ($files as $file) {
    $output = [];
    $exitCode = 0;
    exec('php -l "' . $file . '" 2>&1', $output, $exitCode);
    $log .= ($exitCode === 0 ? 'PASS: ' : 'FAIL: ') . $file . ' => ' . implode(' ', $output) . PHP_EOL;
    if ($exitCode !== 0) {
        $failures++;
    }
}

// 3. Verify Application model methods
$log .= PHP_EOL . "--- 3. Application model methods ---" . PHP_EOL;
$methods = ['forCandidate', 'find', 'findByCandidateAndOpportunity', 'findForCandidate', 'create', 'generateReference', 'countByStatus', 'label', 'badgeTone'];
foreach ($methods as $m) {
    $log .= 'Application::' . $m . ' defined: ' . (method_exists('Application', $m) ? 'YES' : 'NO') . PHP_EOL;
}

// 4. Verify CandidateApplicationsController methods
$log .= PHP_EOL . "--- 4. CandidateApplicationsController methods ---" . PHP_EOL;
$cmethods = ['index', 'show', 'startApplication', 'getApplicationAction'];
foreach ($cmethods as $m) {
    $log .= 'CandidateApplicationsController::' . $m . ' defined: ' . (method_exists('CandidateApplicationsController', $m) ? 'YES' : 'NO') . PHP_EOL;
}

// 5. Verify new files exist
$log .= PHP_EOL . "--- 5. New Stage 2 files ---" . PHP_EOL;
$newFiles = ['candidate/application_actions.php', 'candidate/application_start.php'];
foreach ($newFiles as $f) {
    $log .= (file_exists($f) ? 'OK: ' : 'MISSING: ') . $f . PHP_EOL;
}

$log .= PHP_EOL . "--- END ---" . PHP_EOL;
$log .= $failures === 0 ? "ALL SYNTAX CHECKS PASSED" : "$failures FILE(S) FAILED";

file_put_contents(__DIR__ . '/verify_final_log.txt', $log);
echo "Verification complete. Results in verify_final_log.txt\n";