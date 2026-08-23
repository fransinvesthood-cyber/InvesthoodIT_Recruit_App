<?php
require_once __DIR__ . '/includes/bootstrap.php';

$out = [];

// Check tables
$conn = Database::getConnection();
foreach (['opportunity_questions', 'application_responses'] as $table) {
    $r = $conn->query("SHOW TABLES LIKE '{$table}'");
    $out[] = ($r && $r->num_rows > 0) ? "OK: {$table} exists" : "FAIL: {$table} missing";
}

// Check classes
$out[] = class_exists('ApplicationQuestion') ? "OK: ApplicationQuestion" : "FAIL: ApplicationQuestion";
$out[] = class_exists('ApplicationResponse') ? "OK: ApplicationResponse" : "FAIL: ApplicationResponse";
$out[] = class_exists('ApplicationFormController') ? "OK: ApplicationFormController" : "FAIL: ApplicationFormController";

// Check files exist
$files = [
    'candidate/application_form.php',
    'candidate/application_actions.php',
    'js/application_form.js',
    'css/application_form.css',
    'database/application_form.sql',
];
foreach ($files as $f) {
    $out[] = file_exists($f) ? "OK: {$f}" : "FAIL: {$f}";
}

file_put_contents(__DIR__ . '/verify_result.txt', implode("\n", $out));