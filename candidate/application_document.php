<?php
/**
 * ================================================
 * INVESTHOOD IT - Secure Application Document Serving
 * ================================================
 * Streams a document attached to a candidate's own
 * application (download or inline preview).
 *
 * Ownership chain verified before every request:
 *   Authenticated Candidate
 *     ↓
 *   Owns Application (application_id in URL)
 *     ↓
 *   Document belongs to Application (application_documents.id)
 *     ↓
 *   Document can be accessed
 *
 * A candidate can NEVER access another candidate's
 * documents by changing an ID in the URL.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$userId = (int) current_user_id();
$appDocId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'download';

if ($appDocId <= 0) {
    http_response_code(400);
    exit('Invalid document.');
}

// Load the application-document record and verify it belongs to
// an application owned by the authenticated candidate.
$appDoc = Database::fetchOne(
    "SELECT ad.*, a.candidate_id, a.status
     FROM application_documents ad
     INNER JOIN applications a ON a.id = ad.application_id
     WHERE ad.id = ? LIMIT 1",
    'i',
    [$appDocId]
);

if (!$appDoc || (int) $appDoc['candidate_id'] !== $userId) {
    http_response_code(404);
    exit('Document not found.');
}

// Resolve the physical file (secure storage outside web root)
$path = FileUploader::path('documents', $appDoc['stored_filename']);
if (!$path) {
    http_response_code(404);
    exit('Document file not found.');
}

$mime = $appDoc['mime_type'];
$originalName = $appDoc['original_filename'];

if ($mode === 'preview' && strtolower((string) $mime) === 'application/pdf') {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . rawurlencode($originalName) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

// Default: secure authenticated download
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($originalName) . '"');
header('Content-Length: ' . (int) $appDoc['file_size']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
exit;