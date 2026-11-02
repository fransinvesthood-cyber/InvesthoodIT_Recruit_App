<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_document_helpers.php';

$conn = Database::getConnection();
$id   = (int)($_GET['id'] ?? 0);
$inline = isset($_GET['inline']);

if ($id <= 0 || !as_has_table($conn, 'candidate_documents')) { http_response_code(400); exit; }

$stmt = $conn->prepare("SELECT file_path, original_name, mime_type FROM candidate_documents WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$doc) { http_response_code(404); exit; }

// Resolve against your storage root; adjust as needed.
$storageRoot = defined('DOCUMENT_STORAGE_PATH') ? DOCUMENT_STORAGE_PATH : (__DIR__.'/../storage/documents');
$fullPath    = realpath($storageRoot . '/' . $doc['file_path']);
if (!$fullPath || !str_starts_with($fullPath, realpath($storageRoot))) {
    http_response_code(404); exit;
}

header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="'.basename($doc['original_name']).'"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;