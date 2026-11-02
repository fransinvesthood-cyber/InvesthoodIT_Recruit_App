<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'eligibility';
$pageTitle = 'Eligibility Checklist';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_document_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

$candidates = [];
if (as_has_table($conn, 'eligibility_criteria') && as_has_table($conn, 'candidate_eligibility')) {
    $sql = "SELECT 
                u.id AS participant_id, u.first_name, u.last_name, u.email,
                (SELECT COUNT(*) FROM eligibility_criteria WHERE is_active = 1) AS total_criteria,
                (SELECT COUNT(*) FROM candidate_eligibility ce JOIN eligibility_criteria ec ON ec.id = ce.criterion_id 
                 WHERE ce.participant_id = u.id AND ec.is_active = 1 AND ce.status = 'met') AS met_criteria,
                (SELECT COUNT(*) FROM candidate_eligibility ce JOIN eligibility_criteria ec ON ec.id = ce.criterion_id 
                 WHERE ce.participant_id = u.id AND ec.is_active = 1 AND ce.status = 'unmet') AS unmet_criteria,
                (SELECT COUNT(*) FROM candidate_documents cd 
                 WHERE cd.participant_id = u.id AND cd.status IN ('pending','outstanding','rejected')) AS docs_needing_action
            FROM users u
            WHERE u.id IN (
                SELECT DISTINCT participant_id FROM assessment_attempts WHERE assessor_id = ?
            )
            ORDER BY docs_needing_action DESC, unmet_criteria DESC, u.last_name ASC";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $assessorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $candidates[] = $row;
        $stmt->close();
    }
}

require __DIR__ . '/_layout_start.php';
?>

<style>
.elig-queue { display:flex; flex-direction:column; gap:12px; }
.elig-card { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; background:var(--as-card,#fff); border:1px solid rgba(127,127,127,.15); border-radius:16px; }
.elig-card__info h4 { margin:0 0 4px; font-size:15px; }
.elig-card__info span { font-size:12px; opacity:.65; }
.elig-stats { display:flex; gap:16px; align-items:center; }
.elig-pill { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; background:rgba(127,127,127,.08); }
.elig-pill--success { background:rgba(34,197,94,.15); color:#15803d; }
.elig-pill--warning { background:rgba(245,158,11,.15); color:#b45309; }
.elig-pill--danger { background:rgba(239,68,68,.15); color:#b91c1c; }
.elig-pill--info { background:rgba(59,130,246,.15); color:#1d4ed8; }
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-clipboard-user"></i> Candidate Screening</span>
    <h2>Eligibility & Requirements Checklist</h2>
    <p>Evaluate candidates against programme criteria. Verify missing documents and ensure all requirements are met before progressing to the next stage.</p>
  </div>
</section>

<div class="as-card">
  <div class="as-card__header"><div><h3>Assigned Candidates</h3><p>Overview of requirement completion and document issues.</p></div></div>
  
  <?php if (!$candidates): ?>
    <div class="as-empty"><i class="fas fa-clipboard-check"></i><p>No candidates assigned to you yet.</p></div>
  <?php else: ?>
    <div class="elig-queue">
      <?php foreach ($candidates as $c): 
          $f = trim($c['first_name']); $l = trim($c['last_name']); $name = trim("$f $l") ?: 'Candidate';
          $docs = (int)$c['docs_needing_action'];
          $unmet = (int)$c['unmet_criteria'];
          $met = (int)$c['met_criteria'];
          $total = (int)$c['total_criteria'];
          
          if ($unmet > 0) { $statusText = 'Not Eligible'; $statusClass = 'danger'; }
          elseif ($docs > 0) { $statusText = 'Action Needed'; $statusClass = 'warning'; }
          elseif ($met === $total) { $statusText = 'Fully Eligible'; $statusClass = 'success'; }
          else { $statusText = 'In Progress'; $statusClass = 'info'; }
      ?>
      <div class="elig-card">
        <div class="elig-card__info">
          <h4><?=e($name)?> <span class="elig-pill elig-pill--<?=$statusClass?>"><?=$statusText?></span></h4>
          <span><?=e($c['email'])?></span>
        </div>
        <div class="elig-stats">
          <div style="text-align:center">
              <strong style="display:block; font-size:16px;"><?=$met?>/<?=$total?></strong>
              <span style="font-size:10px; opacity:.6">Criteria Met</span>
          </div>
          <?php if ($docs > 0): ?>
          <div style="text-align:center">
              <strong style="display:block; font-size:16px; color:#b45309;"><?=$docs?></strong>
              <span style="font-size:10px; opacity:.6">Doc Issues</span>
          </div>
          <?php endif; ?>
          <a class="as-btn as-btn--light" href="<?=url('assessor/review_eligibility.php?id='.$c['participant_id'])?>">Review</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>