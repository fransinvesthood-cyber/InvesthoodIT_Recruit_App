<?php
/**
 * ================================================
 * INVESTHOOD IT - Document Download Handler
 * ================================================
 * Securely serves application and candidate documents
 * to authorized administrators. Files are stored outside web root.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

// Validate CSRF token
$token = $_GET['token'] ?? '';
if (!hash_equals(csrf_token(), $token)) {
    http_response_code(403);
    exit('Invalid security token.');
}

// Get document ID
$documentId = (int) ($_GET['id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid document ID.');
}

// Try to fetch from application_documents first
$doc = Database::fetchOne(
    "SELECT ad.*, a.candidate_id, 'application_documents' as source
     FROM application_documents ad
     INNER JOIN applications a ON a.id = ad.application_id
     WHERE ad.id = ?
     LIMIT 1",
    'i',
    [$documentId]
);

// If not found, try candidate documents table
if (!$doc) {
    $doc = Database::fetchOne(
        "SELECT d.*, d.user_id as candidate_id, 'documents' as source
         FROM documents d
         WHERE d.id = ?
         LIMIT 1",
        'i',
        [$documentId]
    );
}

if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

// Build secure file path (outside web root)
$uploadDir = __DIR__ . '/../uploads_private/documents/';
$filePath = $uploadDir . $doc['stored_filename'];

// Check if file exists
if (!file_exists($filePath)) {
    // Try alternative upload directory
    $uploadDir = __DIR__ . '/../uploads/documents/';
    $filePath = $uploadDir . $doc['stored_filename'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('File not found on server.');
    }
}

// Validate file checksum
if (!empty($doc['file_checksum'])) {
    $actualChecksum = hash_file('sha256', $filePath);
    if (!hash_equals($doc['file_checksum'], $actualChecksum)) {
        error_log("[Document Download] Checksum mismatch for document ID: {$documentId}");
        http_response_code(500);
        exit('File integrity check failed.');
    }
}

// Set headers for download
header('Content-Type: ' . ($doc['mime_type'] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($doc['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-cache, must-revalidate');
header('Pragma: no-cache');

// Output file
readfile($filePath);
exit;