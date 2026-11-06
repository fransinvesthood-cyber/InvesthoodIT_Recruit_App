<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'scheduling';
$pageTitle = 'Scheduling & Evaluations';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_schedule_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

// 1. Load all schedules from the JSON flat-file
$allSchedules = get_schedules();
$mySchedules = [];
foreach ($allSchedules as $attemptId => $sched) {
    if ($sched['assessor_id'] == $assessorId) {
        $mySchedules[$attemptId] = $sched;
    }
}

$scheduled = [];
$unscheduled = [];

// 2. Fetch active assessments from the database
if (as_has_table($conn, 'assessment_attempts')) {
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
        while ($row = $res->fetch_assoc()) {
            $id = $row['attempt_id'];
            
            // Check if this attempt has a schedule in our JSON file
            if (isset($mySchedules[$id])) {
                $row['scheduled_at'] = $mySchedules[$id]['scheduled_at'];
                $row['duration_minutes'] = $mySchedules[$id]['duration_minutes'];
                $scheduled[] = $row;
            } else {
                $unscheduled[] = $row;
            }
        }
        $stmt->close();
    }
}

// Sort scheduled sessions by date
usort($scheduled, function($a, $b) {
    return strtotime($a['scheduled_at']) - strtotime($b['scheduled_at']);
});

require __DIR__ . '/_layout_start.php';
?>

<style>
/* ---------- Theme Tokens ---------- */
:root {
    --sched-bg: rgba(127,127,127,.08);
    --sched-hover: rgba(127,127,127,.04);
    --sched-border: rgba(127,127,127,.15);
    --sched-divider: rgba(127,127,127,.1);
    --sched-dashed: rgba(127,127,127,.15);
    --sched-blue: #1a56db;
    --sched-orange: #d97706;
}

/* Automatic Dark Mode */
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]):not(.light) {
        --sched-bg: rgba(255,255,255,.06);
        --sched-hover: rgba(255,255,255,.03);
        --sched-border: rgba(255,255,255,.12);
        --sched-divider: rgba(255,255,255,.08);
        --sched-dashed: rgba(255,255,255,.15);
        --sched-blue: #60a5fa;
        --sched-orange: #fbbf24;
        color-scheme: dark;
    }
}

/* Manual Dark Mode Toggles */
[data-theme="dark"], .dark, .dark-mode {
    --sched-bg: rgba(255,255,255,.06);
    --sched-hover: rgba(255,255,255,.03);
    --sched-border: rgba(255,255,255,.12);
    --sched-divider: rgba(255,255,255,.08);
    --sched-dashed: rgba(255,255,255,.15);
    --sched-blue: #60a5fa;
    --sched-orange: #fbbf24;
    color-scheme: dark;
}

/* ---------- Layout ---------- */
.sched-grid { display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start; }
@media(max-width: 900px) { .sched-grid { grid-template-columns: 1fr; } }

/* FIXED: Removed var(--as-card, #fff) and replaced with --sched-bg */
.sched-card { background:var(--sched-bg); border:1px solid var(--sched-border); border-radius:16px; margin-bottom:16px; overflow:hidden; color:inherit; }

.sched-header { padding:16px 20px; border-bottom:1px solid var(--sched-divider); display:flex; justify-content:space-between; align-items:center; background:var(--sched-hover); }
.sched-header h3 { margin:0; font-size:15px; color:inherit; }

.sched-body { padding:16px 20px; }
.sched-item { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px dashed var(--sched-dashed); }
.sched-item:last-child { border-bottom:none; padding-bottom:0; }

/* FIXED: Replaced hardcoded #1a56db with --sched-blue */
.sched-time { font-weight:800; font-size:14px; color:var(--sched-blue); min-width:120px; }

.sched-info strong { display:block; font-size:14px; margin-bottom:2px; color:inherit; }
.sched-info span { font-size:12px; opacity:.75; display:block; }
.sched-actions { display:flex; gap:8px; }
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-calendar-days"></i> Central Workspace</span>
    <h2>Scheduling & Evaluation Management</h2>
    <p>Book evaluation sessions, manage your calendar, and record assessment results.</p>
  </div>
</section>

<div class="sched-grid">
  
  <!-- LEFT COLUMN: Upcoming Schedule -->
  <div>
    <div class="sched-card">
      <div class="sched-header">
        <h3><i class="fas fa-clock"></i> Upcoming Sessions</h3>
        <span class="as-badge as-badge--blue"><?=count($scheduled)?> Scheduled</span>
      </div>
      <div class="sched-body">
        <?php if (!$scheduled): ?>
          <div style="text-align:center; padding:20px; opacity:.6;">No sessions scheduled.</div>
        <?php else: ?>
          <?php foreach ($scheduled as $s): 
              $f = trim($s['first_name']); $l = trim($s['last_name']); $name = trim("$f $l");
              $date = date('D, d M Y @ H:i', strtotime($s['scheduled_at']));
          ?>
          <div class="sched-item">
            <div class="sched-time">
              <i class="far fa-calendar-alt"></i> <?=e($date)?>
              <div style="font-size:11px; font-weight:400; opacity:.6; margin-top:4px;"><?=(int)$s['duration_minutes']?> mins</div>
            </div>
            <div class="sched-info" style="flex:1; padding-left:20px;">
              <strong><?=e($name)?></strong>
              <span><?=e($s['outcome_code'])?> · Attempt <?=(int)$s['attempt_number']?></span>
            </div>
            <div class="sched-actions">
              <a href="<?=url('assessor/score_assessment.php?id='.$s['attempt_id'])?>" class="as-btn as-btn--primary" style="padding:6px 12px; font-size:12px;" title="Evaluate Now"><i class="fas fa-pen"></i></a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RIGHT COLUMN: Action Required / Unscheduled -->
  <div>
    <div class="sched-card">
      <div class="sched-header">
        <h3><i class="fas fa-triangle-exclamation"></i> Action Required</h3>
        <span class="as-badge as-badge--orange"><?=count($unscheduled)?> Pending</span>
      </div>
      <div class="sched-body">
        <?php if (!$unscheduled): ?>
          <div style="text-align:center; padding:20px; opacity:.6;">All submissions have been scheduled!</div>
        <?php else: ?>
          <?php foreach ($unscheduled as $u): 
              $f = trim($u['first_name']); $l = trim($u['last_name']); $name = trim("$f $l");
          ?>
          <div class="sched-item">
            <div class="sched-info">
              <strong><?=e($name)?></strong>
              <span><?=e($u['outcome_code'])?> · Attempt <?=(int)$u['attempt_number']?></span>
              <!-- FIXED: Replaced hardcoded #d97706 with --sched-orange -->
              <span style="font-size:11px; color:var(--sched-orange); margin-top:4px;"><i class="fas fa-clock"></i> Submitted: <?=e(as_datetime($u['submitted_at'], 'd M Y'))?></span>
            </div>
            <div class="sched-actions">
              <a href="<?=url('assessor/schedule_form.php?id='.$u['attempt_id'])?>" class="as-btn as-btn--light" style="padding:6px 12px; font-size:12px;"><i class="fas fa-calendar-plus"></i> Schedule</a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php require __DIR__ . '/_layout_end.php'; ?>