<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');

$candidateId = (int) current_user_id();
$submissionIdValue = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$submissionId = $submissionIdValue === false ? 0 : (int) $submissionIdValue;
if ($candidateId <= 0 || $submissionId <= 0) {
    http_response_code(400);
    exit('Invalid file request.');
}

$stmt = Database::getConnection()->prepare("
    SELECT file_path, original_name, mime_type, file_size
    FROM deliverable_submissions
    WHERE id = ? AND candidate_id = ? AND file_path IS NOT NULL
    LIMIT 1
");
if (!$stmt) {
    throw new RuntimeException('Unable to prepare submission download: ' . Database::getConnection()->error);
}
$stmt->bind_param('ii', $submissionId, $candidateId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$submission) {
    http_response_code(404);
    exit('Submission file not found.');
}

$path = FileUploader::path('documents', (string) $submission['file_path']);
if (!$path) {
    http_response_code(404);
    exit('Submission file not found.');
}

$mime = in_array($submission['mime_type'], ALLOWED_DOCUMENT_MIMES, true)
    ? $submission['mime_type']
    : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode((string) $submission['original_name']) . '"');
header('Content-Length: ' . (int) $submission['file_size']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
