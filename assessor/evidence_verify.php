<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
require_once __DIR__ . '/../includes/evidence_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . url('assessor/evidence.php')); exit; }

if (!ev_csrf_ok($_POST['_csrf'] ?? null)) {
    flash('error', 'Security token expired. Please try again.');
    header('Location: ' . url('assessor/evidence.php')); exit;
}

$conn       = Database::getConnection();
$user       = current_user();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$id         = (int)($_POST['evidence_id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid evidence.'); header('Location: ' . url('assessor/evidence.php')); exit; }

$status = trim((string)($_POST['status'] ?? ''));
$allowedStat = array_keys(ev_statuses());
if (!in_array($status, $allowedStat, true)) { flash('error', 'Invalid status.'); header('Location: ' . url('assessor/evidence_review.php?id=' . $id)); exit; }

$sufficiency  = in_array($_POST['sufficiency']  ?? '', array_keys(ev_sufficiencies()), true)      ? $_POST['sufficiency']  : null;
$relevance    = in_array($_POST['relevance']    ?? '', array_keys(ev_relevance_levels()), true)   ? $_POST['relevance']    : null;
$authenticity = in_array($_POST['authenticity'] ?? '', array_keys(ev_authenticity_levels()), true)? $_POST['authenticity'] : null;
$verNotes     = trim((string)($_POST['verification_notes'] ?? ''));
$assNotes     = trim((string)($_POST['assessor_notes']     ?? ''));
$criteriaIds  = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['criteria'] ?? [])))));

/* Ownership */
$own = $conn->prepare(
    "SELECT ae.id, ae.status AS current_status
       FROM assessment_evidence ae
       JOIN assessment_attempts aa ON aa.id = ae.attempt_id
      WHERE ae.id = ? AND aa.assessor_id = ? LIMIT 1"
);
$own->bind_param('ii', $id, $assessorId);
$own->execute();
$current = $own->get_result()->fetch_assoc();
$own->close();
if (!$current) { flash('error', 'Evidence not found or not assigned to you.'); header('Location: ' . url('assessor/evidence.php')); exit; }

$from = (string)$current['current_status'];
$verifiedAt   = in_array($status, ['verified','rejected','needs_more','outstanding'], true) ? date('Y-m-d H:i:s') : null;
$verifiedById = $verifiedAt ? $assessorId : null;

$conn->begin_transaction();
try {
    $up = $conn->prepare(
        "UPDATE assessment_evidence
            SET status = ?, sufficiency = ?, relevance = ?, authenticity = ?,
                verification_notes = ?, assessor_notes = ?,
                verified_by = ?, verified_at = ?
          WHERE id = ?"
    );
    $up->bind_param('sssssssii',
        $status, $sufficiency, $relevance, $authenticity,
        $verNotes, $assNotes, $verifiedById, $verifiedAt, $id
    );
    $up->execute();
    $up->close();

    /* Criterion links – only if the table exists */
    if (as_has_table($conn, 'assessment_evidence_criteria')) {
        $del = $conn->prepare("DELETE FROM assessment_evidence_criteria WHERE evidence_id = ?");
        $del->bind_param('i', $id); $del->execute(); $del->close();
        if ($criteriaIds) {
            $ins = $conn->prepare("INSERT IGNORE INTO assessment_evidence_criteria (evidence_id, criterion_id) VALUES (?,?)");
            foreach ($criteriaIds as $cid) { $ins->bind_param('ii', $id, $cid); $ins->execute(); }
            $ins->close();
        }
    }

    /* Audit log */
    $actionMap = [
        'verified' => 'verified', 'rejected' => 'rejected', 'needs_more' => 'requested_more',
        'outstanding' => 'marked_outstanding', 'archived' => 'archived', 'pending' => 'reopened',
    ];
    $action = $actionMap[$status] ?? 'commented';
    $logNotes = trim($verNotes . ($assNotes !== '' ? "\n\nInternal: " . $assNotes : ''));
    $log = $conn->prepare(
        "INSERT INTO assessment_evidence_log (evidence_id, actor_id, action, from_status, to_status, notes)
         VALUES (?,?,?,?,?,?)"
    );
    $log->bind_param('iissss', $id, $assessorId, $action, $from, $status, $logNotes);
    $log->execute();
    $log->close();

    $conn->commit();
    flash('success', 'Evidence verification saved.');
} catch (Throwable $e) {
    $conn->rollback();
    flash('error', 'Could not save verification: ' . $e->getMessage());
}

header('Location: ' . url('assessor/evidence_review.php?id=' . $id));
exit;