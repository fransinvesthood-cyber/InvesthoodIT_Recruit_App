<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/evidence_helpers.php';
require_once __DIR__ . '/_helpers.php';

$user = current_user();
if (!$user) { http_response_code(401); exit('Unauthenticated.'); }
$uid     = (int)($user['id'] ?? $user['user_id'] ?? 0);
$role    = $user['role'] ?? ($user['role_slug'] ?? '');
$id      = (int)($_GET['id'] ?? 0);
$forced  = isset($_GET['download']);
if ($id <= 0) { http_response_code(404); exit('Not found.'); }

$conn = Database::getConnection();
$s = $conn->prepare(
    "SELECT ae.file_path, ae.file_name, ae.file_mime, aa.assessor_id, aa.participant_id
       FROM assessment_evidence ae
       JOIN assessment_attempts aa ON aa.id = ae.attempt_id
      WHERE ae.id = ? LIMIT 1"
);
$s->bind_param('i', $id);
$s->execute();
$row = $s->get_result()->fetch_assoc();
$s->close();
if (!$row) { http_response_code(404); exit('Not found.'); }

$isOwner = $uid === (int)$row['assessor_id'] || $uid === (int)$row['participant_id'];
$isAdmin = in_array(strtolower($role), ['admin','super_admin','administrator'], true);
if (!$isOwner && !$isAdmin) { http_response_code(403); exit('Forbidden.'); }

$path = ev_resolve_file($row);
if (!$path) { http_response_code(404); exit('File missing.'); }

$name = $row['file_name'] ?: basename($path);
$mime = $row['file_mime'] ?: (function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream');

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
$disposition = $forced ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($name) . '"');
readfile($path);
exit;