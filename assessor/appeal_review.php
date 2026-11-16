<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
require_once __DIR__ . '/../includes/appeals_helpers.php';

$user    = current_user();
$flashes = render_flashes();
$currentPage = 'appeals';
$pageTitle   = 'Review appeal';
$assessorId  = (int)($user['id'] ?? $user['user_id'] ?? 0);
$appealId    = (int)($_GET['id'] ?? 0);
$conn = Database::getConnection();

function appeal_fetch(mysqli $conn, int $id): ?array {
    $s = $conn->prepare(
        "SELECT a.*, u.first_name, u.last_name, u.email,
                aa.attempt_number, aa.assessor_result, aa.moderator_result,
                aa.submitted_at, aa.assessed_at,
                ao.code outcome_code, ao.title outcome_title
           FROM assessment_appeals a
           JOIN users u ON u.id = a.participant_id
           JOIN assessment_attempts aa ON aa.id = a.attempt_id
           JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
          WHERE a.id = ? LIMIT 1"
    );
    $s->bind_param('i', $id); $s->execute();
    $row = $s->get_result()->fetch_assoc() ?: null;
    $s->close();
    return $row;
}

$appeal = appeal_fetch($conn, $appealId);
if (!$appeal) { http_response_code(404); exit('Appeal not found.'); }

// Segregation of duties: original assessor cannot review their own decision.
$isOriginal = ((int)$appeal['original_assessor_id'] === $assessorId);
$isOwner    = ((int)$appeal['assigned_assessor_id'] === $assessorId);
$isUnassigned = $appeal['assigned_assessor_id'] === null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isOriginal && !$isOwner) {
        flash('error', 'You cannot review an appeal against your own assessment.');
        header('Location: ' . url('assessor/appeals.php')); exit;
    }

    $action = $_POST['action'] ?? '';
    $note   = trim((string)($_POST['note'] ?? ''));

    if ($action === 'claim' && $isUnassigned && !$isOriginal) {
        $s = $conn->prepare(
            "UPDATE assessment_appeals
                SET assigned_assessor_id = ?, status = 'under_review'
              WHERE id = ? AND assigned_assessor_id IS NULL"
        );
        $s->bind_param('ii', $assessorId, $appealId);
        $s->execute(); $s->close();
        appeal_log($conn, $appealId, $assessorId, 'assessor', 'claimed', 'Appeal claimed for review');
        flash('success', 'Appeal claimed.');
        header('Location: ' . url('assessor/appeal_review.php?id=' . $appealId)); exit;
    }

    if (!$isOwner) {
        flash('error', 'You must claim this appeal before acting on it.');
        header('Location: ' . url('assessor/appeal_review.php?id=' . $appealId)); exit;
    }

    if ($action === 'internal_note' && $note !== '') {
        $s = $conn->prepare(
            "INSERT INTO appeal_messages (appeal_id, author_id, author_role, body, is_internal)
             VALUES (?,?,'assessor',?,1)"
        );
        $s->bind_param('iis', $appealId, $assessorId, $note);
        $s->execute(); $s->close();
        appeal_log($conn, $appealId, $assessorId, 'assessor', 'internal_note', 'Added internal note');
        flash('success', 'Internal note saved.');
    } elseif ($action === 'reply' && $note !== '') {
        $s = $conn->prepare(
            "INSERT INTO appeal_messages (appeal_id, author_id, author_role, body, is_internal)
             VALUES (?,?,'assessor',?,0)"
        );
        $s->bind_param('iis', $appealId, $assessorId, $note);
        $s->execute(); $s->close();
        appeal_log($conn, $appealId, $assessorId, 'assessor', 'message', 'Replied to candidate');
        flash('success', 'Reply sent.');
    } elseif ($action === 'request_info') {
        $s = $conn->prepare(
            "UPDATE assessment_appeals SET status='info_requested' WHERE id = ? AND assigned_assessor_id = ?"
        );
        $s->bind_param('ii', $appealId, $assessorId);
        $s->execute(); $s->close();
        appeal_log($conn, $appealId, $assessorId, 'assessor', 'info_requested', $note);
        flash('success', 'Information requested from candidate.');
    } elseif ($action === 'schedule_reassessment' && $note !== '') {
        // Create a NEW attempt of the same outcome for the participant.
        $s = $conn->prepare(
            "SELECT outcome_id, participant_id, attempt_number
               FROM assessment_attempts WHERE id = ?"
        );
        $s->bind_param('i', $appeal['attempt_id']);
        $s->execute();
        $src = $s->get_result()->fetch_assoc(); $s->close();

        if ($src) {
            $next = (int)$src['attempt_number'] + 1;
            $s = $conn->prepare(
                "INSERT INTO assessment_attempts
                    (participant_id, outcome_id, assessor_id, attempt_number, status, created_at)
                 VALUES (?, ?, ?, ?, 'assigned', NOW())"
            );
            $s->bind_param('iiii', $src['participant_id'], $src['outcome_id'], $assessorId, $next);
            if ($s->execute()) {
                $newAttemptId = (int)$conn->insert_id;
                $s->close();
                $u = $conn->prepare(
                    "UPDATE assessment_appeals
                        SET status='reassessment_scheduled', reassessment_attempt_id=?, decision_notes=?
                      WHERE id = ? AND assigned_assessor_id = ?"
                );
                $u->bind_param('isii', $newAttemptId, $note, $appealId, $assessorId);
                $u->execute(); $u->close();
                appeal_log($conn, $appealId, $assessorId, 'assessor', 'reassessment_scheduled',
                           "New attempt #$newAttemptId (attempt number $next)");
                flash('success', 'Reassessment scheduled (attempt #' . $next . ').');
            } else {
                flash('error', 'Could not create reassessment attempt: ' . $conn->error);
            }
        }
    } elseif (in_array($action, ['uphold','overturn'], true) && $note !== '') {
        $newStatus = $action === 'uphold' ? 'upheld' : 'overturned';
        $newResult = $action === 'uphold' ? $appeal['original_result'] : 'competent';

        $s = $conn->prepare(
            "UPDATE assessment_appeals
                SET status=?, decision_notes=?, outcome_result=?,
                    decided_by=?, decided_at=NOW()
              WHERE id = ? AND assigned_assessor_id = ?"
        );
        $s->bind_param('sssiii', $newStatus, $note, $newResult, $assessorId, $appealId, $assessorId);
        $s->execute(); $s->close();

        if ($action === 'overturn') {
            $s = $conn->prepare(
                "UPDATE assessment_attempts
                    SET assessor_result = 'competent', assessed_at = NOW()
                  WHERE id = ?"
            );
            $s->bind_param('i', $appeal['attempt_id']);
            $s->execute(); $s->close();
        }

        appeal_log($conn, $appealId, $assessorId, 'assessor', $newStatus,
                   $action === 'uphold' ? 'Original decision upheld' : 'Original decision overturned');
        flash('success', 'Appeal ' . $newStatus . '.');
    }

    header('Location: ' . url('assessor/appeal_review.php?id=' . $appealId));
    exit;
}

// ---- Data for the view --------------------------------------------------
$messages = [];
$s = $conn->prepare(
    "SELECT m.*, u.first_name, u.last_name
       FROM appeal_messages m LEFT JOIN users u ON u.id = m.author_id
      WHERE m.appeal_id = ? ORDER BY m.created_at ASC"
);
$s->bind_param('i', $appealId); $s->execute();
$r = $s->get_result(); while ($x = $r->fetch_assoc()) $messages[] = $x; $s->close();

$evidence = [];
$s = $conn->prepare("SELECT * FROM appeal_evidence WHERE appeal_id = ? ORDER BY created_at ASC");
$s->bind_param('i', $appealId); $s->execute();
$r = $s->get_result(); while ($x = $r->fetch_assoc()) $evidence[] = $x; $s->close();

$activity = [];
if (as_has_table($conn, 'appeal_activity')) {
    $s = $conn->prepare("SELECT * FROM appeal_activity WHERE appeal_id = ? ORDER BY created_at ASC");
    $s->bind_param('i', $appealId); $s->execute();
    $r = $s->get_result(); while ($x = $r->fetch_assoc()) $activity[] = $x; $s->close();
}

$criteria = [];
$s = $conn->prepare(
    "SELECT * FROM assessment_decisions WHERE attempt_id = ? ORDER BY id ASC"
);
if ($s) {
    $s->bind_param('i', $appeal['attempt_id']);
    $s->execute();
    $r = $s->get_result(); while ($x = $r->fetch_assoc()) $criteria[] = $x; $s->close();
}

require __DIR__ . '/_layout_start.php';
?>
<style>
.apd{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px}
.apd-panel{padding:20px}
.apd-actions>form{margin-bottom:12px;padding:14px;border:1px solid rgba(127,127,127,.15);border-radius:14px}
.apd-actions textarea{width:100%;min-height:80px;padding:10px;border-radius:10px;border:1px solid rgba(127,127,127,.25);font:inherit;resize:vertical}
.apd-msg{padding:12px 14px;border-radius:12px;background:rgba(127,127,127,.06);margin-bottom:10px}
.apd-msg--internal{background:#fef3c7;border:1px dashed #f59e0b}
.apd-msg--assessor{background:#eef2ff}
.apd-msg__head{font-size:12px;opacity:.7;display:flex;justify-content:space-between;margin-bottom:4px}
.apd-crit{display:flex;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid rgba(127,127,127,.1);font-size:13px}
.apd-crit:last-child{border:0}
.apd-timeline{list-style:none;padding:0;margin:0;font-size:12px}
.apd-timeline li{padding:7px 0 7px 16px;border-left:2px solid rgba(127,127,127,.2);position:relative}
.apd-timeline li::before{content:'';position:absolute;left:-6px;top:12px;width:10px;height:10px;border-radius:50%;background:#6366f1}
.ap-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase}
.ap-badge--info{background:#e0e7ff;color:#3730a3}
.ap-badge--warning{background:#fef3c7;color:#92400e}
.ap-badge--success{background:#dcfce7;color:#166534}
.ap-badge--muted{background:#f1f5f9;color:#475569}
@media(max-width:1000px){.apd{grid-template-columns:1fr}}
</style>

<?php if ($isOriginal): ?>
  <div class="as-card" style="padding:14px;background:#fef3c7;border-color:#f59e0b;margin-bottom:16px">
    <strong><i class="fas fa-triangle-exclamation"></i> Segregation notice</strong>
    — You are the original assessor on this attempt. This appeal must be reviewed by another assessor.
  </div>
<?php endif; ?>

<div class="as-card" style="padding:22px;margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
      <h2 style="margin:0 0 4px"><?=e($appeal['reference'])?></h2>
      <div style="font-size:13px;opacity:.7">
        <?=e(trim($appeal['first_name'].' '.$appeal['last_name']))?>
        · <?=e($appeal['outcome_code'])?> — <?=e($appeal['outcome_title'])?>
        · Attempt <?=(int)$appeal['attempt_number']?>
      </div>
    </div>
    <div>
      <span class="ap-badge ap-badge--<?=e(appeal_status_class($appeal['status']))?>"><?=e(appeal_status_label($appeal['status']))?></span>
    </div>
  </div>
</div>

<div class="apd">
  <div>
    <section class="as-card apd-panel" style="margin-bottom:16px">
      <h3 style="margin-top:0">Grounds</h3>
      <p style="white-space:pre-line"><?=e($appeal['grounds'])?></p>
      <div style="font-size:12px;opacity:.65;margin-top:8px">
        Requested outcome: <strong><?=e(appeal_outcome_label($appeal['requested_outcome']))?></strong>
      </div>
    </section>

    <?php if ($criteria): ?>
      <section class="as-card apd-panel" style="margin-bottom:16px">
        <h3 style="margin-top:0">Criterion decisions (original assessment)</h3>
        <?php foreach ($criteria as $c):
            $label = $c['criterion_title'] ?? $c['criterion'] ?? $c['title'] ?? 'Criterion';
            $res   = $c['result'] ?? $c['decision'] ?? $c['assessor_result'] ?? '—';
        ?>
          <div class="apd-crit">
            <span><?=e($label)?></span>
            <strong><?=e(as_label((string)$res))?></strong>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <section class="as-card apd-panel" style="margin-bottom:16px">
      <h3 style="margin-top:0">Conversation</h3>
      <?php if (!$messages): ?>
        <div style="font-size:13px;opacity:.7">No messages yet.</div>
      <?php endif; ?>
      <?php foreach ($messages as $m): ?>
        <div class="apd-msg <?= $m['is_internal'] ? 'apd-msg--internal' : ($m['author_role']==='assessor' ? 'apd-msg--assessor' : '') ?>">
          <div class="apd-msg__head">
            <span>
              <?=e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: ucfirst($m['author_role']))?>
              <?= $m['is_internal'] ? ' · <em>internal</em>' : '' ?>
            </span>
            <span><?=e(as_datetime($m['created_at']))?></span>
          </div>
          <div style="white-space:pre-line"><?=e($m['body'])?></div>
        </div>
      <?php endforeach; ?>
    </section>

    <?php if ($evidence): ?>
      <section class="as-card apd-panel">
        <h3 style="margin-top:0">Evidence</h3>
        <ul style="padding-left:18px">
          <?php foreach ($evidence as $ev): ?>
            <li><a href="<?=url($ev['stored_path'])?>" target="_blank" rel="noopener"><?=e($ev['file_name'])?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>

  <aside class="as-card apd-panel apd-actions">
    <?php if ($isUnassigned && !$isOriginal): ?>
      <form method="post">
        <input type="hidden" name="action" value="claim">
        <button type="submit" class="as-btn as-btn--primary" style="width:100%">
          <i class="fas fa-hand"></i> Claim this appeal
        </button>
      </form>
    <?php elseif ($isOwner && !appeal_is_terminal($appeal['status'])): ?>

      <form method="post">
        <input type="hidden" name="action" value="reply">
        <textarea name="note" required placeholder="Reply to candidate…"></textarea>
        <button class="as-btn as-btn--light" style="margin-top:8px;width:100%">
          <i class="fas fa-paper-plane"></i> Send reply
        </button>
      </form>

      <form method="post">
        <input type="hidden" name="action" value="internal_note">
        <textarea name="note" required placeholder="Internal note (not visible to candidate)…"></textarea>
        <button class="as-btn as-btn--glass" style="margin-top:8px;width:100%">
          <i class="fas fa-lock"></i> Save internal note
        </button>
      </form>

      <form method="post">
        <input type="hidden" name="action" value="request_info">
        <textarea name="note" placeholder="Describe what you need from the candidate…"></textarea>
        <button class="as-btn as-btn--glass" style="margin-top:8px;width:100%">
          <i class="fas fa-circle-info"></i> Request more info
        </button>
      </form>

      <form method="post">
        <input type="hidden" name="action" value="schedule_reassessment">
        <textarea name="note" required placeholder="Reason and reassessment instructions…"></textarea>
        <button class="as-btn as-btn--light" style="margin-top:8px;width:100%">
          <i class="fas fa-calendar-plus"></i> Schedule reassessment
        </button>
      </form>

      <form method="post" onsubmit="return confirm('Uphold the original decision?');">
        <input type="hidden" name="action" value="uphold">
        <textarea name="note" required placeholder="Justification for upholding…"></textarea>
        <button class="as-btn as-btn--primary" style="margin-top:8px;width:100%">
          <i class="fas fa-circle-check"></i> Uphold decision
        </button>
      </form>

      <form method="post" onsubmit="return confirm('Overturn the original decision? This updates the attempt.');">
        <input type="hidden" name="action" value="overturn">
        <textarea name="note" required placeholder="Justification for overturning…"></textarea>
        <button class="as-btn as-btn--primary" style="margin-top:8px;width:100%">
          <i class="fas fa-rotate-left"></i> Overturn decision
        </button>
      </form>
    <?php else: ?>
      <p style="font-size:13px;opacity:.7;margin:0">No further action available.</p>
    <?php endif; ?>

    <?php if ($activity): ?>
      <hr style="margin:18px 0;border:none;border-top:1px solid rgba(127,127,127,.15)">
      <h4 style="margin:0 0 8px">Audit trail</h4>
      <ul class="apd-timeline">
        <?php foreach ($activity as $act): ?>
          <li>
            <strong><?=e($act['action'])?></strong>
            <div style="opacity:.7"><?=e(as_datetime($act['created_at']))?>
              <?= $act['actor_role'] ? ' · ' . e($act['actor_role']) : '' ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>