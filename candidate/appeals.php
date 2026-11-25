<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
require_once __DIR__ . '/../includes/appeals_helpers.php';

$user     = current_user();
$flashes  = render_flashes();
$currentPage = 'appeals';
$pageTitle   = 'My Appeals';
$participantId = (int)($user['id'] ?? $user['user_id'] ?? 0);

$conn = Database::getConnection();

// ----- Lodge new appeal ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lodge') {
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    $grounds   = trim((string)($_POST['grounds'] ?? ''));
    $outcome   = $_POST['requested_outcome'] ?? 'reassessment';

    [$ok, $reason] = appeal_can_lodge($conn, $attemptId, $participantId);
    if (!$ok) {
        flash('error', $reason);
    } elseif (mb_strlen($grounds) < 40) {
        flash('error', 'Please give at least 40 characters explaining your grounds.');
    } elseif (!in_array($outcome, ['reassessment','remark','overturn','feedback'], true)) {
        flash('error', 'Choose a valid requested outcome.');
    } else {
        $ref = appeal_next_reference($conn);
        $origAssessor = (int)($conn->query("SELECT assessor_id FROM assessment_attempts WHERE id = ".(int)$attemptId)->fetch_assoc()['assessor_id'] ?? 0);
        $origResult   = (string)($conn->query("SELECT assessor_result FROM assessment_attempts WHERE id = ".(int)$attemptId)->fetch_assoc()['assessor_result'] ?? '');
        $deadline = date('Y-m-d H:i:s', strtotime('+' . APPEAL_WINDOW_DAYS . ' days'));

        $s = $conn->prepare(
            "INSERT INTO assessment_appeals
               (reference, attempt_id, participant_id, original_assessor_id,
                grounds, requested_outcome, status, original_result, deadline_at)
             VALUES (?,?,?,?,?,?, 'submitted', ?, ?)"
        );
        $s->bind_param('siiissss',
            $ref, $attemptId, $participantId, $origAssessor,
            $grounds, $outcome, $origResult, $deadline
        );
        if ($s->execute()) {
            $appealId = (int)$conn->insert_id;
            $s->close();
            appeal_log($conn, $appealId, $participantId, 'candidate', 'lodged', 'Appeal lodged');
            flash('success', "Appeal {$ref} submitted. You'll be notified when it's reviewed.");
            header('Location: ' . url('candidate/appeal_view.php?id=' . $appealId));
            exit;
        }
        $s->close();
        flash('error', 'Could not create appeal: ' . $conn->error);
    }
    header('Location: ' . url('candidate/appeals.php'));
    exit;
}

// ----- Eligible attempts --------------------------------------------------
$eligible = [];
$rs = $conn->query(
    "SELECT aa.id, aa.attempt_number, aa.status, aa.assessor_result,
            aa.final_approved_at, aa.moderated_at, aa.assessed_at,
            ao.code outcome_code, ao.title outcome_title
       FROM assessment_attempts aa
       JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
      WHERE aa.participant_id = " . $participantId . "
        AND (aa.final_approved_at IS NOT NULL
             OR aa.moderated_at IS NOT NULL
             OR aa.status IN ('approved','competent','not_yet_competent','finalized'))
      ORDER BY COALESCE(aa.final_approved_at, aa.moderated_at, aa.assessed_at) DESC
      LIMIT 50"
);
if ($rs) {
    while ($x = $rs->fetch_assoc()) {
        [$ok] = appeal_can_lodge($conn, (int)$x['id'], $participantId);
        if ($ok) $eligible[] = $x;
    }
}

// ----- Existing appeals ---------------------------------------------------
$appeals = [];
$rs = $conn->query(
    "SELECT a.*, ao.code outcome_code, ao.title outcome_title, aa.attempt_number
       FROM assessment_appeals a
       JOIN assessment_attempts aa ON aa.id = a.attempt_id
       JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
      WHERE a.participant_id = " . $participantId . "
      ORDER BY a.created_at DESC
      LIMIT 100"
);
if ($rs) while ($x = $rs->fetch_assoc()) $appeals[] = $x;

require __DIR__ . '/../assessor/_layout_start.php';
?>
<style>
.ap-list{display:flex;flex-direction:column;gap:12px}
.ap-card{border:1px solid rgba(127,127,127,.15);border-radius:16px;padding:16px 18px;background:#fff;display:flex;justify-content:space-between;gap:16px}
.ap-card__main{flex:1;min-width:0}
.ap-card__title{font-weight:700;margin:0 0 4px}
.ap-card__meta{font-size:12px;opacity:.65;margin-top:6px;display:flex;flex-wrap:wrap;gap:12px}
.ap-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
.ap-badge--info{background:#e0e7ff;color:#3730a3}
.ap-badge--warning{background:#fef3c7;color:#92400e}
.ap-badge--success{background:#dcfce7;color:#166534}
.ap-badge--muted{background:#f1f5f9;color:#475569}
.ap-empty{padding:26px;text-align:center;border:1px dashed rgba(127,127,127,.25);border-radius:16px;opacity:.7}
.ap-form textarea{width:100%;min-height:120px;padding:12px;border-radius:12px;border:1px solid rgba(127,127,127,.25);font:inherit;resize:vertical}
.ap-form label{display:block;margin:14px 0 6px;font-weight:600;font-size:13px}
.ap-form select{padding:9px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);font:inherit}
.ap-tabs{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap}
.ap-tab{padding:8px 14px;border-radius:999px;border:1px solid rgba(127,127,127,.2);background:transparent;font-weight:600;cursor:pointer}
.ap-tab.is-active{background:#4f46e5;color:#fff;border-color:#4f46e5}
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-scale-balanced"></i> Appeals</span>
    <h2>Challenge an assessment outcome</h2>
    <p>You may appeal a finalised assessment within <?=APPEAL_WINDOW_DAYS?> days. Provide clear grounds and any supporting evidence.</p>
  </div>
</section>

<div class="as-tabs ap-tabs" role="tablist">
  <button class="ap-tab is-active" data-tab="open" role="tab">My appeals (<?=count($appeals)?>)</button>
  <button class="ap-tab" data-tab="lodge" role="tab">Lodge a new appeal</button>
</div>

<!-- My appeals -->
<section id="panel-open" class="ap-panel">
  <?php if (!$appeals): ?>
    <div class="ap-empty"><i class="fas fa-inbox"></i><br>You have not lodged any appeals.</div>
  <?php else: ?>
    <div class="ap-list">
      <?php foreach ($appeals as $a): ?>
        <div class="ap-card">
          <div class="ap-card__main">
            <p class="ap-card__title"><?=e($a['reference'])?> · <?=e($a['outcome_code'])?> – <?=e($a['outcome_title'])?></p>
            <div><span class="ap-badge ap-badge--<?=e(appeal_status_class($a['status']))?>"><?=e(appeal_status_label($a['status']))?></span></div>
            <div class="ap-card__meta">
              <span><i class="fas fa-rotate"></i> Attempt <?=(int)$a['attempt_number']?></span>
              <span><i class="fas fa-calendar"></i> Lodged <?=e(as_datetime($a['created_at']))?></span>
              <span><i class="fas fa-flag-checkered"></i> Requested: <?=e(appeal_outcome_label($a['requested_outcome']))?></span>
            </div>
          </div>
          <div>
            <a class="as-btn as-btn--light" href="<?=url('candidate/appeal_view.php?id='.(int)$a['id'])?>">Open <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Lodge new appeal -->
<section id="panel-lodge" class="ap-panel" style="display:none">
  <?php if (!$eligible): ?>
    <div class="ap-empty"><i class="fas fa-circle-info"></i><br>There are no finalised assessments eligible for appeal at this time.</div>
  <?php else: ?>
    <form method="post" class="ap-form as-card" style="padding:22px">
      <input type="hidden" name="action" value="lodge">
      <label>Which assessment are you appealing?</label>
      <select name="attempt_id" required>
        <option value="">— Select an attempt —</option>
        <?php foreach ($eligible as $x): ?>
          <option value="<?=(int)$x['id']?>">
            <?=e($x['outcome_code'])?> · <?=e($x['outcome_title'])?>
            — Attempt <?=(int)$x['attempt_number']?>
            (<?=e(appeal_status_label((string)$x['assessor_result']))?>)
          </option>
        <?php endforeach; ?>
      </select>

      <label>What outcome are you seeking?</label>
      <select name="requested_outcome" required>
        <option value="reassessment">Arrange a reassessment</option>
        <option value="remark">Request a re-mark by a different assessor</option>
        <option value="overturn">Request the result be overturned</option>
        <option value="feedback">Request more detailed feedback</option>
      </select>

      <label>Grounds for appeal (minimum 40 characters)</label>
      <textarea name="grounds" required minlength="40"
        placeholder="Explain clearly which criteria you disagree with and why, referencing evidence you submitted."></textarea>

      <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
        <button type="submit" class="as-btn as-btn--primary"><i class="fas fa-paper-plane"></i> Submit appeal</button>
        <span style="font-size:12px;opacity:.6">You will be able to attach evidence on the next screen.</span>
      </div>
    </form>
  <?php endif; ?>
</section>

<script>
document.querySelectorAll('.ap-tab').forEach(t => t.addEventListener('click', () => {
  document.querySelectorAll('.ap-tab').forEach(x => x.classList.toggle('is-active', x === t));
  document.querySelectorAll('.ap-panel').forEach(p => p.style.display = (p.id === 'panel-' + t.dataset.tab) ? '' : 'none');
}));
</script>
<?php require __DIR__ . '/../assessor/_layout_end.php'; ?>