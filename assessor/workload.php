<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'workload';
$pageTitle = 'Scheduling & Workload';
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

// SLA Configuration (Days)
$slaDays = 7; 

$stats = ['pending' => 0, 'overdue' => 0, 'due_soon' => 0, 'completed_month' => 0];
$programmes = [];
$tasks = [];

if (as_has_table($conn, 'assessment_attempts')) {
    // 1. Fetch Core Stats
    $sql = "SELECT 
                SUM(CASE WHEN status IN ('submitted', 'in_review') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status IN ('submitted', 'in_review') AND DATEDIFF(NOW(), COALESCE(submitted_at, created_at)) > ? THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN status IN ('submitted', 'in_review') AND DATEDIFF(NOW(), COALESCE(submitted_at, created_at)) BETWEEN 4 AND ? THEN 1 ELSE 0 END) AS due_soon,
                SUM(CASE WHEN status NOT IN ('submitted', 'in_review') AND MONTH(assessed_at) = MONTH(NOW()) AND YEAR(assessed_at) = YEAR(NOW()) THEN 1 ELSE 0 END) AS completed_month
            FROM assessment_attempts 
            WHERE assessor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $slaDays, $slaDays, $assessorId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        foreach ($stats as $k => $v) $stats[$k] = (int)($res[$k] ?? 0);
    }
    $stmt->close();

    // 2. Fetch Workload Breakdown by Programme (Outcome)
    $sql = "SELECT ao.code, ao.title, COUNT(aa.id) AS active_count
            FROM assessment_attempts aa
            JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
            WHERE aa.assessor_id = ? AND aa.status IN ('submitted', 'in_review')
            GROUP BY ao.id
            ORDER BY active_count DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $assessorId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $programmes[] = $row;
    $stmt->close();

    // 3. Fetch Actionable Task List (Pending & Overdue)
    $sql = "SELECT aa.id AS attempt_id, aa.status, aa.submitted_at, aa.created_at,
                   DATEDIFF(NOW(), COALESCE(aa.submitted_at, aa.created_at)) AS days_waiting,
                   u.first_name, u.last_name, ao.code AS outcome_code, ao.title AS outcome_title
            FROM assessment_attempts aa
            JOIN users u ON u.id = aa.participant_id
            JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
            WHERE aa.assessor_id = ? AND aa.status IN ('submitted', 'in_review')
            ORDER BY days_waiting DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $assessorId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $tasks[] = $row;
    $stmt->close();
}

require __DIR__ . '/_layout_start.php';
?>

<style>
.wl-stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px; }
@media(max-width: 900px) { .wl-stats { grid-template-columns:repeat(2, 1fr); } }
.wl-stat-card { padding:20px; border-radius:16px; background:var(--as-card,#fff); border:1px solid rgba(127,127,127,.15); display:flex; flex-direction:column; gap:6px; }
.wl-stat-card strong { font-size:32px; line-height:1; }
.wl-stat-card span { font-size:13px; font-weight:600; opacity:.7; }
.wl-stat-card small { font-size:11px; opacity:.5; }

.wl-stat-card--danger { border-color: rgba(239,68,68,.3); background: rgba(239,68,68,.03); }
.wl-stat-card--danger strong { color:#dc2626; }
.wl-stat-card--warning { border-color: rgba(245,158,11,.3); background: rgba(245,158,11,.03); }
.wl-stat-card--warning strong { color:#d97706; }

.wl-grid { display:grid; grid-template-columns: 1fr 2fr; gap:24px; align-items:start; }
@media(max-width: 1000px) { .wl-grid { grid-template-columns: 1fr; } }

.wl-programme-list { display:flex; flex-direction:column; gap:10px; }
.wl-programme-item { padding:14px; border-radius:12px; background:rgba(127,127,127,.04); border:1px solid rgba(127,127,127,.08); display:flex; justify-content:space-between; align-items:center; }
.wl-programme-item strong { font-size:14px; display:block; }
.wl-programme-item span { font-size:12px; opacity:.6; }
.wl-programme-count { background:#1a56db; color:#fff; font-weight:700; font-size:12px; padding:4px 10px; border-radius:20px; }

.wl-task-list { display:flex; flex-direction:column; }
.wl-task-row { display:flex; justify-content:space-between; align-items:center; padding:16px 12px; border-bottom:1px solid rgba(127,127,127,.1); }
.wl-task-row:last-child { border-bottom:none; }
.wl-task-info h4 { margin:0 0 4px; font-size:15px; }
.wl-task-info span { font-size:12px; opacity:.7; display:block; }
.wl-task-time { text-align:right; margin-right:20px; }
.wl-task-time strong { font-size:16px; display:block; }
.wl-task-time span { font-size:11px; opacity:.6; }

.time-badge { display:inline-block; padding:3px 8px; border-radius:6px; font-size:10px; font-weight:800; text-transform:uppercase; margin-top:6px; }
.time-badge--danger { background:rgba(239,68,68,.15); color:#b91c1c; }
.time-badge--warning { background:rgba(245,158,11,.15); color:#b45309; }
.time-badge--success { background:rgba(34,197,94,.15); color:#15803d; }
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-calendar-check"></i> Capacity Planning</span>
    <h2>Workload & Scheduling</h2>
    <p>Monitor your active assessment queue, track SLA deadlines, and manage your evaluation capacity across different programmes.</p>
    <div class="as-hero__actions">
      <a class="as-btn as-btn--light" href="<?=url('assessor/assessments.php')?>"><i class="fas fa-list-check"></i> Go to Assessment Queue</a>
    </div>
  </div>
</section>

<div class="wl-stats">
  <div class="wl-stat-card">
    <strong><?=number_format($stats['pending'])?></strong>
    <span>Active Assignments</span>
    <small>Awaiting your evaluation</small>
  </div>
  <div class="wl-stat-card <?=$stats['overdue'] > 0 ? 'wl-stat-card--danger' : ''?>">
    <strong><?=number_format($stats['overdue'])?></strong>
    <span>Overdue Tasks</span>
    <small>Exceeded <?=$slaDays?>-day SLA</small>
  </div>
  <div class="wl-stat-card <?=$stats['due_soon'] > 0 ? 'wl-stat-card--warning' : ''?>">
    <strong><?=number_format($stats['due_soon'])?></strong>
    <span>Due Soon</span>
    <small>Approaching SLA limit</small>
  </div>
  <div class="wl-stat-card">
    <strong><?=number_format($stats['completed_month'])?></strong>
    <span>Completed This Month</span>
    <small>Successfully assessed</small>
  </div>
</div>

<div class="wl-grid">
  
  <!-- Left: Programme Breakdown -->
  <div class="as-card">
    <div class="as-card__header">
      <div><h3>Workload by Programme</h3><p>Active tasks grouped by outcome.</p></div>
    </div>
    
    <?php if (!$programmes): ?>
      <div class="as-empty" style="padding:20px; text-align:center; opacity:.6;">No active workload by programme.</div>
    <?php else: ?>
      <div class="wl-programme-list">
        <?php foreach ($programmes as $p): ?>
          <div class="wl-programme-item">
            <div>
              <strong><?=e($p['code'])?></strong>
              <span><?=e($p['title'])?></span>
            </div>
            <div class="wl-programme-count"><?=(int)$p['active_count']?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right: Task List -->
  <div class="as-card">
    <div class="as-card__header">
      <div><h3>Upcoming & Overdue Tasks</h3><p>Sorted by time waiting.</p></div>
    </div>

    <?php if (!$tasks): ?>
      <div class="as-empty" style="padding:40px; text-align:center; opacity:.6;"><i class="fas fa-check-circle" style="font-size:32px; color:#16a34a; margin-bottom:10px;"></i><br>You are all caught up!</div>
    <?php else: ?>
      <div class="wl-task-list">
        <?php foreach ($tasks as $t): 
            $f = trim($t['first_name']); $l = trim($t['last_name']); $name = trim("$f $l") ?: 'Candidate';
            $days = (int)$t['days_waiting'];
            
            // Determine urgency
            if ($days > $slaDays) {
                $badgeClass = 'danger';
                $badgeText = 'Overdue';
            } elseif ($days >= 4) {
                $badgeClass = 'warning';
                $badgeText = 'Due Soon';
            } else {
                $badgeClass = 'success';
                $badgeText = 'On Track';
            }
        ?>
        <div class="wl-task-row">
          <div class="wl-task-info">
            <h4><?=e($name)?></h4>
            <span><?=e($t['outcome_code'])?> · <?=e($t['outcome_title'])?></span>
            <span class="time-badge time-badge--<?=$badgeClass?>"><?=$badgeText?></span>
          </div>
          <div class="wl-task-time">
            <strong><?=$days?> days</strong>
            <span>Waiting</span>
          </div>
          <a href="<?=url('assessor/review_assessment.php?id='.$t['attempt_id'])?>" class="as-btn as-btn--primary" style="padding:8px 16px; font-size:12px;">
            Evaluate
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require __DIR__ . '/_layout_end.php'; ?>