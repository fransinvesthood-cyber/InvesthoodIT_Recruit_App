<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$officerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$submissionIdValue = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$submissionId = $submissionIdValue === false ? 0 : (int) $submissionIdValue;
if ($officerId <= 0 || $submissionId <= 0) {
    http_response_code(400);
    exit('Invalid file request.');
}

$conn = Database::getConnection();
$scope = po_scope($conn, 'p', 'c');
if (po_scope_mode($scope) === 'none') {
    http_response_code(403);
    exit('Access scope unavailable.');
}
$scopeCondition = po_scope_condition($scope);
$stmt = $conn->prepare("
    SELECT s.file_path, s.original_name, s.mime_type, s.file_size
    FROM deliverable_submissions s
    INNER JOIN programme_deliverables d ON d.id = s.deliverable_id
    INNER JOIN programmes p ON p.id = d.programme_id
    LEFT JOIN cohorts c ON c.id = d.cohort_id
    WHERE s.id = ? AND {$scopeCondition} AND s.file_path IS NOT NULL
    LIMIT 1
");
if (!$stmt) {
    throw new RuntimeException('Unable to prepare submission download: ' . $conn->error);
}
$stmt->bind_param('ii', $submissionId, $officerId);
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
