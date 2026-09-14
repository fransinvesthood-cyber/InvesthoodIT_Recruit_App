<?php
/**
 * Investhood IT - Secure Document Download Endpoint
 * ================================================
 * Allows administrators to view and download candidate documents.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$documentId = (int) ($_GET['id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid document ID']);
    exit;
}

try {
    // Fetch document details
    $doc = Database::fetchOne(
        "SELECT d.id, d.user_id, d.original_filename, d.stored_filename, d.mime_type, d.file_size, d.verification_status
         FROM documents d
         INNER JOIN users u ON u.id = d.user_id
         INNER JOIN roles r ON r.id = u.role_id
         WHERE d.id = ? AND r.slug = 'candidate'
         LIMIT 1",
        'i',
        [$documentId]
    );

    if (!$doc) {
        http_response_code(404);
        echo json_encode(['error' => 'Document not found']);
        exit;
    }

    // Try multiple possible upload directories
    $possiblePaths = [
        __DIR__ . '/../uploads/documents/' . $doc['stored_filename'],
        __DIR__ . '/../uploads/' . $doc['stored_filename'],
        __DIR__ . '/../uploads/private/documents/' . $doc['stored_filename'],
    ];

    $filePath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $filePath = $path;
            break;
        }
    }

    if (!$filePath) {
        // Log the attempted paths for debugging
        error_log('Document not found. ID: ' . $documentId . ', stored_filename: ' . $doc['stored_filename']);
        error_log('Attempted paths: ' . implode(', ', $possiblePaths));
        http_response_code(404);
        echo json_encode(['error' => 'File not found on server']);
        exit;
    }

    // Determine action (view or download)
    $action = $_GET['action'] ?? 'view';

    // Set headers
    header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($filePath));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    if ($action === 'download') {
        header('Content-Disposition: attachment; filename="' . $doc['original_filename'] . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $doc['original_filename'] . '"');
    }

    // Output file
    readfile($filePath);
    exit;

} catch (Exception $ex) {
    error_log('Document download error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while retrieving the document']);
    exit;
}
