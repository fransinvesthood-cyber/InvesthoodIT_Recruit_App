<?php
/**
 * Debug script to check document file paths
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

header('Content-Type: text/html; charset=utf-8');

echo '<h2>Document Path Debug</h2>';

// Get all documents
$docs = Database::fetchAll("SELECT id, user_id, original_filename, stored_filename, document_type FROM documents LIMIT 10");

echo '<table border="1" cellpadding="8" cellspacing="0">';
echo '<tr><th>ID</th><th>User</th><th>Original Name</th><th>Stored Name</th><th>Type</th><th>Exists?</th><th>Path</th></tr>';

foreach ($docs as $doc) {
    $paths = [
        'uploads/documents/' => __DIR__ . '/../uploads/documents/' . $doc['stored_filename'],
        'uploads/' => __DIR__ . '/../uploads/' . $doc['stored_filename'],
        'uploads/private/documents/' => __DIR__ . '/../uploads/private/documents/' . $doc['stored_filename'],
    ];
    
    $found = false;
    $foundPath = '';
    
    foreach ($paths as $key => $path) {
        if (file_exists($path)) {
            $found = true;
            $foundPath = $key;
            break;
        }
    }
    
    echo '<tr>';
    echo '<td>' . $doc['id'] . '</td>';
    echo '<td>' . $doc['user_id'] . '</td>';
    echo '<td>' . htmlspecialchars($doc['original_filename']) . '</td>';
    echo '<td>' . htmlspecialchars($doc['stored_filename']) . '</td>';
    echo '<td>' . $doc['document_type'] . '</td>';
    echo '<td style="color:' . ($found ? 'green' : 'red') . '">' . ($found ? 'YES' : 'NO') . '</td>';
    echo '<td>' . ($found ? $foundPath : 'Not found in any location') . '</td>';
    echo '</tr>';
}

echo '</table>';

echo '<h3>Uploads Directory Contents</h3>';
$uploadDirs = [
    'uploads/' => __DIR__ . '/../uploads/',
    'uploads/documents/' => __DIR__ . '/../uploads/documents/',
    'uploads/private/' => __DIR__ . '/../uploads/private/',
    'uploads/private/documents/' => __DIR__ . '/../uploads/private/documents/',
];

foreach ($uploadDirs as $label => $dir) {
    echo '<h4>' . $label . '</h4>';
    if (is_dir($dir)) {
        $files = scandir($dir);
        if (count($files) > 2) {
            echo '<ul>';
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo '<li>' . htmlspecialchars($file) . '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p>Directory is empty</p>';
        }
    } else {
        echo '<p style="color:red">Directory does not exist</p>';
    }
}
