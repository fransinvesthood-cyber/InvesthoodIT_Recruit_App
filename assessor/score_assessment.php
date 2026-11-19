<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'scoring';
$pageTitle = 'Score Assessment';
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$attemptId = (int)($_GET['id'] ?? 0);

if ($attemptId <= 0) { http_response_code(400); exit('Invalid attempt.'); }

// Load attempt details from existing DB tables
$stmt = $conn->prepare("SELECT aa.id, aa.outcome_id, aa.attempt_number, u.first_name, u.last_name, u.email, ao.title AS outcome_title 
                        FROM assessment_attempts aa 
                        JOIN users u ON u.id = aa.participant_id 
                        JOIN assessment_outcomes ao ON ao.id = aa.outcome_id 
                        WHERE aa.id = ? AND aa.assessor_id = ?");
$stmt->bind_param('ii', $attemptId, $assessorId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) { http_response_code(404); exit('Assessment not found or not assigned to you.'); }

// Handle Form Submission (Save to Session)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scores = $_POST['scores'] ?? [];
    
    // Save this attempt's scores in the session array
    if (!isset($_SESSION['mock_scores'])) {
        $_SESSION['mock_scores'] = [];
    }
    $_SESSION['mock_scores'][$attemptId] = $scores;
    
    flash('success', 'Scores and feedback saved successfully (Session Mock Mode).');
    redirect(url('assessor/score_assessment.php?id='.$attemptId));
}

// HARDCODED CRITERIA (Because we aren't using the database table)
$criteria = [
    ['id' => 1, 'title' => 'Knowledge & Understanding', 'description' => 'Demonstrates clear understanding of core concepts.', 'max_score' => 20],
    ['id' => 2, 'title' => 'Practical Application', 'description' => 'Applies skills effectively in a practical scenario.', 'max_score' => 30],
    ['id' => 3, 'title' => 'Communication', 'description' => 'Communicates clearly and professionally.', 'max_score' => 10],
    ['id' => 4, 'title' => 'Professional Conduct', 'description' => 'Adheres to ethical and professional standards.', 'max_score' => 10]
];

// Load existing scores from Session
$existingScores = $_SESSION['mock_scores'][$attemptId] ?? [];

$candidateName = trim($attempt['first_name'] . ' ' . $attempt['last_name']);
require __DIR__ . '/_layout_start.php';
?>

<style>
.score-card { background:var(--as-card,#fff); border:1px solid rgba(127,127,127,.15); border-radius:16px; padding:20px; margin-bottom:16px; }
.score-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
.score-title { margin:0 0 4px; font-size:16px; }
.score-desc { margin:0; font-size:13px; opacity:.7; }
.score-input-group { display:flex; align-items:center; gap:12px; background:rgba(127,127,127,.04); padding:12px 16px; border-radius:12px; margin-bottom:16px; }
.score-input-group input { width:80px; padding:8px; border-radius:8px; border:1px solid rgba(127,127,127,.3); text-align:center; font-weight:bold; font-size:16px; background:transparent; color:inherit; }
.score-feedback { width:100%; padding:10px; border-radius:10px; border:1px solid rgba(127,127,127,.25); background:transparent; font-size:13px; resize:vertical; color:inherit; font-family:inherit; }
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-pen-nib"></i> Scoring Workspace</span>
    <h2>Evaluate: <?=e($candidateName)?></h2>
    <p><?=e($attempt['outcome_title'])?> · Attempt <?=(int)$attempt['attempt_number']?></p>
    <div class="as-hero__actions">
      <a class="as-btn as-btn--light" href="<?=url('assessor/scoring_queue.php')?>"><i class="fas fa-arrow-left"></i> Back to Queue</a>
      <a class="as-btn as-btn--glass" href="<?=url('assessor/assessment_report.php?id='.$attemptId)?>" target="_blank"><i class="fas fa-file-pdf"></i> View Report</a>
    </div>
  </div>
</section>

<form method="post">
  <div style="max-width:900px; margin:0 auto;">
      <?php foreach ($criteria as $c): 
          $cid = $c['id'];
          $saved = $existingScores[$cid] ?? ['score' => 0, 'feedback' => ''];
      ?>
      <div class="score-card">
        <div class="score-header">
          <div>
            <h3 class="score-title"><?=e($c['title'])?> <span style="font-size:12px; opacity:.5; font-weight:normal;">(Max: <?=number_format((float)$c['max_score'], 1)?> pts)</span></h3>
            <p class="score-desc"><?=e($c['description'])?></p>
          </div>
        </div>

        <div class="score-input-group">
          <label style="font-size:13px; font-weight:700;">Awarded Score:</label>
          <input type="number" name="scores[<?=$cid?>][score]" value="<?=e($saved['score'])?>" step="0.5" min="0" max="<?=e($c['max_score'])?>" required>
          <span style="opacity:.5;">/ <?=e($c['max_score'])?></span>
        </div>

        <textarea name="scores[<?=$cid?>][feedback]" class="score-feedback" rows="2" placeholder="Provide specific feedback related to this criterion..."><?=e($saved['feedback'] ?? '')?></textarea>
      </div>
      <?php endforeach; ?>

      <div style="display:flex; justify-content:flex-end; gap:12px; padding-bottom:40px;">
        <button type="submit" class="as-btn as-btn--primary" style="padding:14px 24px; font-size:15px;"><i class="fas fa-save"></i> Save Scores</button>
      </div>
  </div>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>