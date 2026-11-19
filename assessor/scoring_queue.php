<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'scoring';
$pageTitle = 'Scoring & Evaluation';
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

$attempts = [];
if (as_has_table($conn, 'assessment_attempts')) {
    // We removed the subquery checking attempt_scores
    $sql = "SELECT aa.id AS attempt_id, aa.attempt_number, aa.status, aa.submitted_at,
                   u.first_name, u.last_name, u.email,
                   ao.code AS outcome_code, ao.title AS outcome_title
            FROM assessment_attempts aa
            JOIN users u ON u.id = aa.participant_id
            JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
            WHERE aa.assessor_id = ? AND aa.status IN ('submitted', 'in_review')
            ORDER BY aa.submitted_at ASC";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $assessorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $attempts[] = $row;
        $stmt->close();
    }
}

require __DIR__ . '/_layout_start.php';
?>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-star-half-stroke"></i> Assessment Scoring</span>
    <h2>Scoring & Evaluation Queue</h2>
    <p>Evaluate candidate evidence against predefined criteria, record scores, and generate final evaluation reports.</p>
  </div>
</section>

<div class="as-card">
  <div class="as-card__header">
    <div><h3>Awaiting Evaluation</h3><p>Submissions requiring scoring.</p></div>
  </div>
  
  <?php if (!$attempts): ?>
    <div class="as-empty"><i class="fas fa-clipboard-check"></i><p>No assessments are currently awaiting scoring.</p></div>
  <?php else: ?>
    <div class="as-list">
      <?php foreach ($attempts as $a): 
          $f = trim($a['first_name']); $l = trim($a['last_name']); $name = trim("$f $l") ?: 'Candidate';
          
          // Check if scores exist in the Session
          $hasScores = isset($_SESSION['mock_scores'][$a['attempt_id']]);
      ?>
      <div class="as-list-item" style="display:flex; justify-content:space-between; align-items:center; padding:16px; border-bottom:1px solid rgba(127,127,127,.1);">
        <div>
          <h4 style="margin:0 0 4px; font-size:15px;"><?=e($name)?> <span class="as-badge as-badge--blue">Attempt <?=(int)$a['attempt_number']?></span></h4>
          <span style="font-size:12px; opacity:.7;"><?=e($a['outcome_code'])?> · <?=e($a['outcome_title'])?></span>
        </div>
        <div style="text-align:right;">
          <?php if ($hasScores): ?>
            <span class="as-badge as-badge--green" style="margin-bottom:6px; display:inline-block;">Scoring Started</span>
          <?php else: ?>
            <span class="as-badge as-badge--orange" style="margin-bottom:6px; display:inline-block;">Not Started</span>
          <?php endif; ?>
          <br>
          <a href="<?=url('assessor/score_assessment.php?id='.$a['attempt_id'])?>" class="as-btn as-btn--primary" style="padding:8px 16px; font-size:12px; margin-top:6px;">
            <i class="fas fa-pen"></i> Score Assessment
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>