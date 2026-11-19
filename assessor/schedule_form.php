<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'scheduling';
$pageTitle = 'Schedule Session';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_schedule_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$attemptId = (int)($_GET['id'] ?? 0);

// Verify attempt belongs to this assessor
$stmt = $conn->prepare("SELECT aa.id, aa.attempt_number, u.first_name, u.last_name, ao.title AS outcome_title 
                        FROM assessment_attempts aa 
                        JOIN users u ON u.id = aa.participant_id 
                        JOIN assessment_outcomes ao ON ao.id = aa.outcome_id 
                        WHERE aa.id = ? AND aa.assessor_id = ?");
$stmt->bind_param('ii', $attemptId, $assessorId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) { http_response_code(404); exit('Assessment not found.'); }

// Handle Form Submission (Saves to JSON file)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $duration = (int)($_POST['duration'] ?? 60);
    $notes = trim($_POST['notes'] ?? '');
    
    if ($date && $time) {
        // Call the helper function from _schedule_helpers.php
        save_schedule($attemptId, $assessorId, $date, $time, $duration, $notes);
        flash('success', 'Evaluation session scheduled successfully.');
        redirect(url('assessor/scheduling.php'));
    }
}

$f = trim($attempt['first_name']); $l = trim($attempt['last_name']); $name = trim("$f $l");
require __DIR__ . '/_layout_start.php';
?>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-calendar-plus"></i> Book Evaluation</span>
    <h2>Schedule: <?=e($name)?></h2>
    <p><?=e($attempt['outcome_title'])?> · Attempt <?=(int)$attempt['attempt_number']?></p>
    <div class="as-hero__actions">
      <a class="as-btn as-btn--light" href="<?=url('assessor/scheduling.php')?>"><i class="fas fa-arrow-left"></i> Back to Workspace</a>
    </div>
  </div>
</section>

<div style="max-width:600px; margin:0 auto;">
  <div class="as-card" style="padding:30px;">
    <form method="post">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px;">Date</label>
          <input type="date" name="date" required min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(127,127,127,.3); background:transparent; color:inherit;">
        </div>
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px;">Time</label>
          <input type="time" name="time" required value="09:00" style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(127,127,127,.3); background:transparent; color:inherit;">
        </div>
      </div>

      <div style="margin-bottom:16px;">
        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px;">Duration (Minutes)</label>
        <select name="duration" style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(127,127,127,.3); background:transparent; color:inherit;">
          <option value="30">30 Minutes</option>
          <option value="60" selected>1 Hour</option>
          <option value="90">1.5 Hours</option>
          <option value="120">2 Hours</option>
        </select>
      </div>

      <div style="margin-bottom:24px;">
        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px;">Session Notes (Optional)</label>
        <textarea name="notes" rows="3" placeholder="E.g., Practical test to be conducted via Zoom..." style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(127,127,127,.3); background:transparent; color:inherit; font-family:inherit; resize:vertical;"></textarea>
      </div>

      <button type="submit" class="as-btn as-btn--primary" style="width:100%; padding:14px; font-size:15px; justify-content:center;">
        <i class="fas fa-check-circle"></i> Confirm Schedule
      </button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>