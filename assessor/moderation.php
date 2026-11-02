<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'moderation';
$pageTitle = 'Moderation Queue';
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

$attempts = [];
if (as_has_table($conn, 'assessment_attempts')) {
    // Fetch assessments waiting for moderation
    $sql = "SELECT aa.id AS attempt_id, aa.attempt_number, aa.status, aa.assessor_result, aa.assessed_at,
                   u.first_name, u.last_name, u.email,
                   ao.code AS outcome_code, ao.title AS outcome_title,
                   assessor.first_name AS assessor_first, assessor.last_name AS assessor_last
            FROM assessment_attempts aa
            JOIN users u ON u.id = aa.participant_id
            JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
            LEFT JOIN users assessor ON assessor.id = aa.assessor_id
            WHERE aa.status IN ('awaiting_moderation', 'in_review') 
              AND aa.moderation_status = 'pending'
              AND aa.assessor_id != ?  -- Prevent assessors from moderating their own work
            ORDER BY aa.assessed_at ASC";
            
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
    <span class="as-hero__eyebrow"><i class="fas fa-scale-balanced"></i> Quality Assurance</span>
    <h2>Moderation Queue</h2>
    <p>Review assessor scoring, verify adherence to criteria, and finalize candidate results.</p>
  </div>
</section>

<div class="as-card">
  <div class="as-card__header">
    <div><h3>Awaiting Moderation</h3><p>Assessments submitted by assessors for QA sign-off.</p></div>
  </div>
  
  <?php if (!$attempts): ?>
    <div class="as-empty"><i class="fas fa-shield-check"></i><p>No assessments are currently awaiting moderation.</p></div>
  <?php else: ?>
    <div class="as-list">
      <?php foreach ($attempts as $a): 
          $f = trim($a['first_name']); $l = trim($a['last_name']); $name = trim("$f $l") ?: 'Candidate';
          $af = trim($a['assessor_first']); $al = trim($a['assessor_last']); $assessorName = trim("$af $al") ?: 'Assessor';
      ?>
      <div class="as-list-item" style="display:flex; justify-content:space-between; align-items:center; padding:16px; border-bottom:1px solid rgba(127,127,127,.1);">
        <div>
          <h4 style="margin:0 0 4px; font-size:15px;"><?=e($name)?> <span class="as-badge as-badge--blue">Attempt <?=(int)$a['attempt_number']?></span></h4>
          <span style="font-size:12px; opacity:.7; display:block; margin-bottom:4px;"><?=e($a['outcome_code'])?> · <?=e($a['outcome_title'])?></span>
          <span style="font-size:11px; opacity:.6;"><i class="fas fa-user-pen"></i> Scored by: <?=e($assessorName)?> on <?=e(as_datetime($a['assessed_at']))?></span>
        </div>
        <div style="text-align:right;">
          <a href="<?=url('assessor/review_moderation.php?id='.$a['attempt_id'])?>" class="as-btn as-btn--primary" style="padding:8px 16px; font-size:12px;">
            <i class="fas fa-magnifying-glass"></i> Review Scores
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>