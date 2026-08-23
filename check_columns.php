<?php
require_once __DIR__ . '/includes/bootstrap.php';

echo 'DB: ' . DB_NAME . PHP_EOL;

$cols = Database::fetchAll('SHOW COLUMNS FROM applications');
foreach ($cols as $c) {
    echo $c['Field'] . ' => ' . $c['Type'] . PHP_EOL;
}

exit(0);