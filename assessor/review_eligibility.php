<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'eligibility';
$pageTitle = 'Review Eligibility';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_document_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$participantId = (int)($_GET['id'] ?? 0);

if ($participantId <= 0) { http_response_code(400); exit('Invalid Candidate ID.'); }

$stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
$stmt->bind_param('i', $participantId);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$candidate) { http_response_code(404); exit('Candidate not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? null;
    if (!doc_csrf_ok($token)) {
        flash('error', 'Session expired. Please try again.');
        redirect(url('assessor/review_eligibility.php?id='.$participantId));
    }
    
    $criteriaData = $_POST['criteria'] ?? [];
    $now = date('Y-m-d H:i:s');
    
    foreach ($criteriaData as $critId => $data) {
        $critId = (int)$critId;
        $status = in_array($data['status'], ['pending','met','unmet','na']) ? $data['status'] : 'pending';
        $notes = trim($data['notes'] ?? '');
        
        $sql = "INSERT INTO candidate_eligibility 
                (participant_id, criterion_id, status, assessor_notes, evaluated_by, evaluated_at) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                status=VALUES(status), assessor_notes=VALUES(assessor_notes), evaluated_by=VALUES(evaluated_by), evaluated_at=VALUES(evaluated_at)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iissss', $participantId, $critId, $status, $notes, $assessorId, $now);
        $stmt->execute();
        $stmt->close();
    }
    flash('success', 'Eligibility checklist updated successfully.');
    redirect(url('assessor/review_eligibility.php?id='.$participantId));
}

$criteriaList = [];
$res = $conn->query("SELECT * FROM eligibility_criteria WHERE is_active = 1 ORDER BY sort_order ASC");
while ($row = $res->fetch_assoc()) $criteriaList[] = $row;

$eligStatus = [];
$stmt = $conn->prepare("SELECT * FROM candidate_eligibility WHERE participant_id = ?");
$stmt->bind_param('i', $participantId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $eligStatus[$row['criterion_id']] = $row;
$stmt->close();

$candidateDocs = [];
$stmt = $conn->prepare("SELECT * FROM candidate_documents WHERE participant_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param('i', $participantId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!isset($candidateDocs[$row['document_type_id']])) {
        $candidateDocs[$row['document_type_id']] = $row;
    }
}
$stmt->close();

$f = trim($candidate['first_name']); $l = trim($candidate['last_name']); 
$name = trim("$f $l") ?: 'Candidate';
require __DIR__ . '/_layout_start.php';
?>

<style>
.crit-card { background:var(--as-card,#fff); border:1px solid rgba(127,127,127,.15); border-radius:16px; padding:20px; margin-bottom:16px; }
.crit-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
.crit-title { margin:0 0 4px; font-size:16px; }
.crit-desc { margin:0; font-size:13px; opacity:.7; }
.crit-badge { font-size:10px; text-transform:uppercase; font-weight:800; padding:4px 10px; border-radius:20px; }
.crit-badge--req { background:rgba(239,68,68,.15); color:#b91c1c; }
.crit-badge--opt { background:rgba(127,127,127,.1); color:inherit; opacity:.7; }
.doc-widget { display:flex; align-items:center; gap:12px; background:rgba(127,127,127,.04); padding:12px 16px; border-radius:12px; margin-bottom:16px; font-size:12px; }
.doc-widget .badge { padding:3px 8px; border-radius:10px; font-weight:700; font-size:10px; text-transform:uppercase; }
.doc-widget a { font-weight:700; text-decoration:underline; margin-left:auto; }
.crit-actions { display:flex; gap:20px; align-items:center; margin-bottom:16px; }
.crit-actions label { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; cursor:pointer; }
.crit-actions input[type="radio"] { width:16px; height:16px; cursor:pointer; }
.crit-notes { width:100%; padding:10px; border-radius:10px; border:1px solid rgba(127,127,127,.25); background:transparent; font-size:13px; resize:vertical; color:inherit; font-family:inherit; }
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-user-check"></i> Candidate Evaluation</span>
    <h2>Evaluating: <?=e($name)?></h2>
    <p><?=e($candidate['email'])?></p>
    <div class="as-hero__actions">
      <a class="as-btn as-btn--light" href="<?=url('assessor/eligibility.php')?>"><i class="fas fa-arrow-left"></i> Back to Queue</a>
      <a class="as-btn as-btn--glass" href="<?=url('assessor/documents.php?q='.urlencode($candidate['email']))?>"><i class="fas fa-folder-open"></i> View All Documents</a>
    </div>
  </div>
</section>

<form method="post">
  <input type="hidden" name="_csrf" value="<?=e(function_exists('csrf_token') ? csrf_token() : '')?>">
  
  <div style="max-width:900px; margin:0 auto;">
    <?php foreach ($criteriaList as $crit): 
        $cid = $crit['id'];
        $savedStatus = $eligStatus[$cid]['status'] ?? 'pending';
        $savedNotes = $eligStatus[$cid]['assessor_notes'] ?? '';
        
        $linkedDoc = null;
        if ($crit['linked_document_type_id'] && isset($candidateDocs[$crit['linked_document_type_id']])) {
            $linkedDoc = $candidateDocs[$crit['linked_document_type_id']];
        }
    ?>
    <div class="crit-card">
      <div class="crit-header">
        <div>
          <h3 class="crit-title"><?=e($crit['title'])?></h3>
          <p class="crit-desc"><?=e($crit['description'])?></p>
        </div>
        <span class="crit-badge crit-badge--<?=(int)$crit['is_mandatory']===1?'req':'opt'?>">
            <?=(int)$crit['is_mandatory']===1?'Mandatory':'Optional'?>
        </span>
      </div>

      <?php if ($crit['linked_document_type_id']): ?>
        <div class="doc-widget">
          <i class="<?=e($linkedDoc ? doc_mime_icon($linkedDoc['mime_type']) : 'fas fa-file-circle-xmark')?>" style="font-size:18px; opacity:.6"></i>
          <div>
            <strong>Linked Evidence:</strong> 
            <?php if ($linkedDoc): ?>
                <?=e($linkedDoc['original_name'])?>
            <?php else: ?>
                No document uploaded
            <?php endif; ?>
          </div>
          
          <?php if ($linkedDoc): ?>
             <span class="badge doc-badge--<?=e(doc_status_class($linkedDoc['status']))?>"><?=e(doc_status_label($linkedDoc['status']))?></span>
             <?php if ($linkedDoc['status'] !== 'verified'): ?>
                <a href="<?=url('assessor/review_document.php?id='.$linkedDoc['id'])?>" target="_blank">Review Document &rarr;</a>
             <?php endif; ?>
          <?php else: ?>
             <span class="badge doc-badge--red">Missing</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="crit-actions">
        <label><input type="radio" name="criteria[<?=$cid?>][status]" value="met" <?=$savedStatus==='met'?'checked':''?>> Met</label>
        <label><input type="radio" name="criteria[<?=$cid?>][status]" value="unmet" <?=$savedStatus==='unmet'?'checked':''?>> Unmet</label>
        <label><input type="radio" name="criteria[<?=$cid?>][status]" value="na" <?=$savedStatus==='na'?'checked':''?>> N/A</label>
        <label style="margin-left:auto; opacity:.5"><input type="radio" name="criteria[<?=$cid?>][status]" value="pending" <?=$savedStatus==='pending'?'checked':''?>> Pending</label>
      </div>

      <textarea name="criteria[<?=$cid?>][notes]" class="crit-notes" rows="2" placeholder="Add evaluator notes for this requirement..."><?=e($savedNotes)?></textarea>
    </div>
    <?php endforeach; ?>

    <div style="display:flex; justify-content:flex-end; gap:12px; padding-bottom:40px;">
      <button type="submit" class="as-btn as-btn--light" style="padding:14px 24px; font-size:15px;"><i class="fas fa-save"></i> Save Eligibility Record</button>
    </div>
  </div>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>