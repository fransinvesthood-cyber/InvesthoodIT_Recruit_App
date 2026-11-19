<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
require_once __DIR__ . '/../includes/evidence_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . url('assessor/evidence.php')); exit; }
if (!ev_csrf_ok($_POST['_csrf'] ?? null)) { flash('error', 'Security token expired.'); header('Location: ' . url('assessor/evidence.php')); exit; }

$conn       = Database::getConnection();
$user       = current_user();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$attemptId  = (int)($_POST['attempt_id'] ?? 0);
$type       = trim((string)($_POST['evidence_type'] ?? 'document'));
$title      = trim((string)($_POST['title'] ?? ''));
$desc       = trim((string)($_POST['description'] ?? ''));
$url        = trim((string)($_POST['external_url'] ?? ''));
$captured   = trim((string)($_POST['captured_at'] ?? ''));

if ($attemptId <= 0 || $title === '') { flash('error', 'Attempt and title are required.'); header('Location: ' . url('assessor/evidence.php')); exit; }
if (!array_key_exists($type, ev_types())) $type = 'other';

/* Confirm attempt belongs to this assessor and is not closed */
$chk = $conn->prepare(
    "SELECT aa.id, aa.participant_id, aa.outcome_id
       FROM assessment_attempts aa
      WHERE aa.id = ? AND aa.assessor_id = ?
        AND aa.status NOT IN ('finalised','finalized','completed','cancelled','withdrawn','certified')"
);
$chk->bind_param('ii', $attemptId, $assessorId);
$chk->execute();
$attempt = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$attempt) { flash('error', 'Attempt not available for evidence capture.'); header('Location: ' . url('assessor/evidence.php')); exit; }

/* Handle file upload */
$filePath = null; $fileName = null; $fileMime = null; $fileSize = null;

if (!empty($_FILES['evidence_file']['name']) && (int)$_FILES['evidence_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['evidence_file'];
    if ((int)$f['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'File upload failed (code ' . (int)$f['error'] . ').');
        header('Location: ' . url('assessor/evidence.php')); exit;
    }
    if ((int)$f['size'] > 20 * 1024 * 1024) {
        flash('error', 'File exceeds the 20 MB limit.');
        header('Location: ' . url('assessor/evidence.php')); exit;
    }
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $fileMime = $finfo ? finfo_file($finfo, $f['tmp_name']) : ($f['type'] ?? null);
    if ($finfo) finfo_close($finfo);

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/ogg' => 'ogg', 'audio/webm' => 'weba',
        'application/zip' => 'zip',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
    ];
    if (!isset($allowed[$fileMime])) {
        flash('error', 'Unsupported file type: ' . e((string)$fileMime));
        header('Location: ' . url('assessor/evidence.php')); exit;
    }

    $ext     = $allowed[$fileMime];
    $baseDir = ev_store_dir() . '/' . $attemptId;
    if (!is_dir($baseDir)) @mkdir($baseDir, 0755, true);

    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($f['name'], PATHINFO_FILENAME));
    $safe = substr($safe, 0, 60) ?: 'file';
    $stored = bin2hex(random_bytes(8)) . '_' . $safe . '.' . $ext;
    $dest   = $baseDir . '/' . $stored;

    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        flash('error', 'Could not save uploaded file.'); header('Location: ' . url('assessor/evidence.php')); exit;
    }
    $filePath = 'uploads/evidence/' . $attemptId . '/' . $stored;
    $fileName = $f['name'];
    $fileSize = (int)$f['size'];
}

if (!$filePath && $url === '' && $type !== 'written_statement' && $type !== 'observation' && $type !== 'witness_testimony') {
    flash('error', 'Please attach a file or provide an external URL.');
    header('Location: ' . url('assessor/evidence.php')); exit;
}

if ($captured !== '') {
    $ts = strtotime($captured);
    $captured = $ts ? date('Y-m-d H:i:s', $ts) : null;
} else {
    $captured = null;
}

try {
    $ins = $conn->prepare(
        "INSERT INTO assessment_evidence
            (attempt_id, participant_id, outcome_id, evidence_type, title, description,
             file_path, file_name, file_mime, file_size, external_url, captured_at, uploaded_by, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')"
    );
    $ins->bind_param('iiissssssissi',
        $attemptId, $attempt['participant_id'], $attempt['outcome_id'],
        $type, $title, $desc,
        $filePath, $fileName, $fileMime, $fileSize, $url, $captured, $assessorId
    );
    $ins->execute();
    $newId = (int)$ins->insert_id;
    $ins->close();

    $log = $conn->prepare(
        "INSERT INTO assessment_evidence_log (evidence_id, actor_id, action, to_status, notes)
         VALUES (?,?,'uploaded','pending',?)"
    );
    $note = 'Uploaded by assessor: ' . $title;
    $log->bind_param('iis', $newId, $assessorId, $note);
    $log->execute();
    $log->close();

    flash('success', 'Evidence captured. Review and verify at your convenience.');
    header('Location: ' . url('assessor/evidence_review.php?id=' . $newId));
    exit;
} catch (Throwable $e) {
    flash('error', 'Could not save evidence: ' . $e->getMessage());
    header('Location: ' . url('assessor/evidence.php'));
    exit;
}