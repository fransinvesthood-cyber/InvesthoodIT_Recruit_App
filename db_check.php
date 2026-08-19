<?php
require_once __DIR__ . '/includes/bootstrap.php';
$tables = ['availability_statuses','candidate_profiles','qualifications','skills','candidate_skills','work_experience','documents','consents','audit_logs'];
$out = '';
foreach ($tables as $t) {
    $r = Database::getConnection()->query('SHOW TABLES LIKE "' . $t . '"');
    $out .= $t . ': ' . ($r && $r->num_rows ? 'EXISTS' : 'MISSING') . PHP_EOL;
}
// skill row count
$out .= 'skills_count: ' . (int) Database::fetchOne('SELECT COUNT(*) AS cnt FROM skills')['cnt'] . PHP_EOL;
$out .= 'availability_count: ' . (int) Database::fetchOne('SELECT COUNT(*) AS cnt FROM availability_statuses')['cnt'] . PHP_EOL;
echo nl2br($out);
