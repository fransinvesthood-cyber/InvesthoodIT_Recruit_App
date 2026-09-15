<?php
/**
 * Temporary lint checker - writes results to a file
 * because terminal output capture is unreliable.
 */
$results = [];

// Check candidate/application_actions.php
$file = __DIR__ . '/candidate/application_actions.php';
$output = [];
$code = 0;
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
$results[] = 'application_actions.php: ' . ($code === 0 ? 'SYNTAX OK' : 'SYNTAX ERROR: ' . implode("\n", $output));

// Check js/application_form.js
$file = __DIR__ . '/js/application_form.js';
$output = [];
$code = 0;
exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $code);
$results[] = 'application_form.js: ' . ($code === 0 ? 'SYNTAX OK' : 'SYNTAX ERROR: ' . implode("\n", $output));

file_put_contents(__DIR__ . '/lint_result.txt', implode("\n", $results));