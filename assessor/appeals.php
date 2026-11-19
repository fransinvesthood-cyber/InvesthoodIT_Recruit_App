<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
require_once __DIR__ . '/../includes/appeals_helpers.php';

$user    = current_user();
$flashes = render_flashes();
$currentPage = 'appeals';
$pageTitle   = 'Assessment Appeals';
$assessorId  = (int)($user['id'] ?? $user['user_id'] ?? 0);
$conn = Database::getConnection();

// Stats for the four working tabs
$stats = ['assigned'=>0,'unassigned'=>0,'info'=>0,'scheduled'=>0,'closed'=>0];
if (as_has_table($conn, 'assessment_appeals')) {
    $s = $conn->prepare(
        "SELECT
           SUM(assigned_assessor_id = ? AND status IN ('submitted','under_review')) assigned,
           SUM(assigned_assessor_id IS NULL AND status = 'submitted') unassigned,
           SUM(assigned_assessor_id = ? AND status = 'info_requested') info,
           SUM(assigned_assessor_id = ? AND status = 'reassessment_scheduled') scheduled,
           SUM(assigned_assessor_id = ? AND status IN ('upheld','overturned','closed','withdrawn')) closed
         FROM assessment_appeals"
    );
    $s->bind_param('iiii', $assessorId, $assessorId, $assessorId, $assessorId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc() ?: [];
    foreach ($stats as $k => $_) $stats[$k] = (int)($r[$k] ?? 0);
    $s->close();
}

// Tab filter
$tab = $_GET['tab'] ?? 'assigned';
$where = match ($tab) {
    'unassigned' => "a.assigned_assessor_id IS NULL AND a.status = 'submitted'",
    'info'       => "a.assigned_assessor_id = $assessorId AND a.status = 'info_requested'",
    'scheduled'  => "a.assigned_assessor_id = $assessorId AND a.status = 'reassessment_scheduled'",
    'closed'     => "a.assigned_assessor_id = $assessorId AND a.status IN ('upheld','overturned','closed','withdrawn')",
    default      => "a.assigned_assessor_id = $assessorId AND a.status IN ('submitted','under_review')",
};

$rows = [];
$sql = "SELECT a.*, u.first_name, u.last_name, u.email,
               ao.code outcome_code, ao.title outcome_title,
               aa.attempt_number
          FROM assessment_appeals a
          JOIN users u ON u.id = a.participant_id
          JOIN assessment_attempts aa ON aa.id = a.attempt_id
          JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
         WHERE {$where}
         ORDER BY a.created_at ASC
         LIMIT 200";
if ($res = $conn->query($sql)) while ($x = $res->fetch_assoc()) $rows[] = $x;

require __DIR__ . '/_layout_start.php';
?>
<style>
.apq-tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.apq-tab{padding:9px 16px;border-radius:999px;border:1px solid rgba(127,127,127,.2);background:transparent;font-weight:600;text-decoration:none;color:inherit}
.apq-tab.is-active{background:#4f46e5;color:#fff;border-color:#4f46e5}
.apq-tab small{opacity:.75;margin-left:6px;font-weight:400}
.apq-row{display:grid;grid-template-columns:1fr auto;gap:14px;padding:14px 16px;border:1px solid rgba(127,127,127,.15);border-radius:14px;margin-bottom:10px}
.apq-row__head{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.apq-row__meta{display:flex;flex-wrap:wrap;gap:14px;margin-top:6px;font-size:12px;opacity:.65}
.ap-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase}
.ap-badge--info{background:#e0e7ff;color:#3730a3}
.ap-badge--warning{background:#fef3c7;color:#92400e}
.ap-badge--success{background:#dcfce7;color:#166534}
.ap-badge--muted{background:#f1f5f9;color:#475569}
.ap-empty{padding:26px;text-align:center;border:1px dashed rgba(127,127,127,.25);border-radius:16px;opacity:.7}
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-scale-balanced"></i> Appeals</span>
    <h2>Review, investigate and decide</h2>
    <p>Assigned appeals appear here. You cannot assess an appeal against your own original decision — it will be routed to another assessor.</p>
  </div>
</section>

<div class="apq-tabs">
  <?php
  $tabs = [
    'assigned'   => ['Assigned to me',  $stats['assigned']],
    'unassigned' => ['Unassigned',      $stats['unassigned']],
    'info'       => ['Info requested',  $stats['info']],
    'scheduled'  => ['Reassessments',   $stats['scheduled']],
    'closed'     => ['Closed',          $stats['closed']],
  ];
  foreach ($tabs as $k => [$label, $n]): ?>
    <a class="apq-tab <?= $tab === $k ? 'is-active' : '' ?>"
       href="<?=url('assessor/appeals.php?tab=' . $k)?>">
      <?=e($label)?> <small><?=number_format($n)?></small>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <div class="ap-empty"><i class="fas fa-inbox"></i><br>No appeals in this view.</div>
<?php else: ?>
  <?php foreach ($rows as $a): ?>
    <div class="apq-row">
      <div>
        <div class="apq-row__head">
          <strong><?=e($a['reference'])?></strong>
          <span class="ap-badge ap-badge--<?=e(appeal_status_class($a['status']))?>"><?=e(appeal_status_label($a['status']))?></span>
          <?php if ($a['assigned_assessor_id'] === null): ?>
            <span class="ap-badge ap-badge--muted">Unassigned</span>
          <?php endif; ?>
        </div>
        <div style="margin-top:4px"><?=e(trim($a['first_name'].' '.$a['last_name']))?> — <?=e($a['outcome_code'])?> · <?=e($a['outcome_title'])?></div>
        <div class="apq-row__meta">
          <span><i class="fas fa-rotate"></i> Attempt <?=(int)$a['attempt_number']?></span>
          <span><i class="fas fa-hourglass-half"></i> Lodged <?=e(as_datetime($a['created_at']))?></span>
          <span><i class="fas fa-flag"></i> Requested: <?=e(appeal_outcome_label($a['requested_outcome']))?></span>
        </div>
      </div>
      <div style="align-self:center">
        <a class="as-btn as-btn--primary" href="<?=url('assessor/appeal_review.php?id='.(int)$a['id'])?>">
          Open <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>