<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'moderation';
$pageTitle = 'Review Moderation';
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$attemptId = (int)($_GET['id'] ?? 0);

if ($attemptId <= 0) { http_response_code(400); exit('Invalid attempt.'); }

// Handle Form Submission (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['moderation_notes'] ?? '');
    $now = date('Y-m-d H:i:s');
    
    if ($action === 'approve') {
        $newStatus = 'approved';
        $newModStatus = 'approved';
        flash('success', 'Assessment approved and finalized.');
    } elseif ($action === 'reject') {
        $newStatus = 'in_review'; // Send back to assessor
        $newModStatus = 'rejected';
        flash('warning', 'Assessment rejected and returned to the assessor.');
    } else {
        flash('error', 'Invalid action.');
        redirect(url('assessor/review_moderation.php?id='.$attemptId));
    }

    $stmt = $conn->prepare("UPDATE assessment_attempts 
                            SET status = ?, moderation_status = ?, moderator_id = ?, moderated_at = ?, moderator_result = ? 
                            WHERE id = ?");
    $stmt->bind_param('ssisss', $newStatus, $newModStatus, $assessorId, $now, $notes, $attemptId);
    $stmt->execute();
    $stmt->close();
    
    redirect(url('assessor/moderation.php'));
}

// Load attempt details
$stmt = $conn->prepare("SELECT aa.*, u.first_name, u.last_name, u.email, ao.title AS outcome_title, ao.code AS outcome_code,
                               assessor.first_name AS assessor_first, assessor.last_name AS assessor_last
                        FROM assessment_attempts aa 
                        JOIN users u ON u.id = aa.participant_id 
                        JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
                        LEFT JOIN users assessor ON assessor.id = aa.assessor_id
                        WHERE aa.id = ?");
$stmt->bind_param('i', $attemptId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) { http_response_code(404); exit('Assessment not found.'); }

// HARDCODED CRITERIA (Matching the previous step)
$criteriaList = [
    ['id' => 1, 'title' => 'Knowledge & Understanding', 'max_score' => 20],
    ['id' => 2, 'title' => 'Practical Application', 'max_score' => 30],
    ['id' => 3, 'title' => 'Communication', 'max_score' => 10],
    ['id' => 4, 'title' => 'Professional Conduct', 'max_score' => 10]
];

// Pull the Assessor's scores from Session
$savedScores = $_SESSION['mock_scores'][$attemptId] ?? [];
$totalScore = 0;
$totalMaxScore = 0;
$scores = [];

foreach ($criteriaList as $c) {
    $cid = $c['id'];
    $scoreData = $savedScores[$cid] ?? ['score' => 0, 'feedback' => 'No feedback provided.'];
    $score = (float)$scoreData['score'];
    $feedback = $scoreData['feedback'];
    
    $totalScore += $score;
    $totalMaxScore += (float)$c['max_score'];
    
    $scores[] = [
        'title' => $c['title'],
        'score' => $score,
        'max_score' => $c['max_score'],
        'feedback' => $feedback
    ];
}

$percentage = $totalMaxScore > 0 ? round(($totalScore / $totalMaxScore) * 100, 1) : 0;
$candidateName = trim($attempt['first_name'] . ' ' . $attempt['last_name']);
$assessorName = trim($attempt['assessor_first'] . ' ' . $attempt['assessor_last']);
require __DIR__ . '/_layout_start.php';
?>

<style>
.mod-grid { display:grid; grid-template-columns: 1.5fr 1fr; gap:20px; align-items:start; }
@media(max-width: 960px) { .mod-grid { grid-template-columns: 1fr; } }
.mod-score-row { display:flex; justify-content:space-between; align-items:center; padding:12px; border-bottom:1px solid rgba(127,127,127,.1); }
.mod-score-row:last-child { border-bottom:none; }
.mod-score-title { font-weight:700; font-size:14px; }
.mod-score-feedback { font-size:13px; color:#555; margin-top:4px; font-style:italic; }
.mod-score-val { font-weight:800; font-size:16px; color:#1a56db; }
.mod-actions { background:var(--as-card,#fff); padding:20px; border-radius:16px; border:1px solid rgba(127,127,127,.15); }
.mod-actions textarea { width:100%; padding:12px; border-radius:10px; border:1px solid rgba(127,127,127,.25); background:transparent; font-size:13px; resize:vertical; color:inherit; font-family:inherit; margin-bottom:16px; }
.mod-btn { display:block; width:100%; padding:12px; border:0; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer; margin-bottom:10px; transition:opacity 0.2s; }
.mod-btn:hover { opacity:0.9; }
.mod-btn--approve { background:#16a34a; color:#fff; }
.mod-btn--reject { background:#dc2626; color:#fff; }
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-scale-balanced"></i> Quality Assurance Review</span>
    <h2>Moderate: <?=e($candidateName)?></h2>
    <p><?=e($attempt['outcome_title'])?> · Attempt <?=(int)$attempt['attempt_number']?></p>
    <div class="as-hero__actions">
      <a class="as-btn as-btn--light" href="<?=url('assessor/moderation.php')?>"><i class="fas fa-arrow-left"></i> Back to Queue</a>
    </div>
  </div>
</section>

<div class="mod-grid">
  
  <!-- Left Side: Assessor's Scores -->
  <div class="as-card">
    <div class="as-card__header">
      <div>
        <h3>Assessor Evaluation</h3>
        <p>Scored by: <strong><?=e($assessorName)?></strong></p>
      </div>
      <div style="text-align:right;">
        <span style="font-size:24px; font-weight:800; color:#1a56db;"><?=number_format($percentage, 1)?>%</span>
        <div style="font-size:12px; opacity:.6;"><?=number_format($totalScore, 1)?> / <?=number_format($totalMaxScore, 1)?> pts</div>
      </div>
    </div>

    <div>
      <?php foreach ($scores as $s): ?>
      <div class="mod-score-row">
        <div>
          <div class="mod-score-title"><?=e($s['title'])?></div>
          <div class="mod-score-feedback">"<?=e($s['feedback'])?>"</div>
        </div>
        <div class="mod-score-val"><?=number_format($s['score'], 1)?> <span style="font-size:12px; color:#888;">/ <?=number_format($s['max_score'], 1)?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right Side: Moderation Actions -->
  <div class="mod-actions">
    <h3 style="margin:0 0 10px; font-size:16px;">Moderation Decision</h3>
    <p style="font-size:13px; opacity:.7; margin-bottom:16px;">Review the scores above. If the evaluation is consistent with the criteria, approve it. Otherwise, reject it and provide feedback for the assessor.</p>
    
    <form method="post">
      <label style="font-size:12px; font-weight:700; display:block; margin-bottom:6px;">Moderation Notes (Required for rejection)</label>
      <textarea name="moderation_notes" rows="4" placeholder="Explain any inconsistencies or reasons for rejection..."></textarea>
      
      <button type="submit" name="action" value="approve" class="mod-btn mod-btn--approve" onclick="return confirm('Approve this assessment and finalize the candidate result?');">
        <i class="fas fa-check-circle"></i> Approve & Finalize
      </button>
      <button type="submit" name="action" value="reject" class="mod-btn mod-btn--reject" onclick="return confirm('Reject this assessment and send it back to the assessor?');">
        <i class="fas fa-xmark-circle"></i> Reject & Return
      </button>
    </form>
  </div>

</div>

<?php require __DIR__ . '/_layout_end.php'; ?>