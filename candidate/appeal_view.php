<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
require_once __DIR__ . '/../includes/appeals_helpers.php';

$user = current_user();
$flashes = render_flashes();
$currentPage = 'appeals';
$pageTitle   = 'Appeal details';
$participantId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$appealId      = (int)($_GET['id'] ?? 0);

$conn = Database::getConnection();

function appeal_load_for(mysqli $conn, int $id, int $participantId): ?array {
    $s = $conn->prepare(
        "SELECT a.*, aa.attempt_number, aa.assessor_result, aa.moderator_result,
                ao.code outcome_code, ao.title outcome_title
           FROM assessment_appeals a
           JOIN assessment_attempts aa ON aa.id = a.attempt_id
           JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
          WHERE a.id = ? AND a.participant_id = ? LIMIT 1"
    );
    $s->bind_param('ii', $id, $participantId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc() ?: null;
    $s->close();
    return $row;
}

$appeal = appeal_load_for($conn, $appealId, $participantId);
if (!$appeal) { http_response_code(404); exit('Appeal not found.'); }

// --- POST actions ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'message') {
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body !== '') {
            $s = $conn->prepare(
                "INSERT INTO appeal_messages (appeal_id, author_id, author_role, body, is_internal)
                 VALUES (?,?, 'candidate', ?, 0)"
            );
            $s->bind_param('iis', $appealId, $participantId, $body);
            $s->execute(); $s->close();
            appeal_log($conn, $appealId, $participantId, 'candidate', 'message', 'Posted message');
            flash('success', 'Message posted.');
        }
    } elseif ($action === 'upload') {
        if (!empty($_FILES['evidence'])) {
            $stored = appeal_store_upload($_FILES['evidence'], $appealId, $participantId, 'candidate');
            if ($stored) {
                $s = $conn->prepare(
                    "INSERT INTO appeal_evidence
                       (appeal_id, uploaded_by, uploaded_by_role, file_name, stored_path, mime_type, file_size)
                     VALUES (?,?, 'candidate', ?,?,?,?)"
                );
                $s->bind_param('iisssi', $appealId, $participantId,
                    $stored['file_name'], $stored['stored_path'],
                    $stored['mime_type'], $stored['file_size']);
                $s->execute(); $s->close();
                appeal_log($conn, $appealId, $participantId, 'candidate', 'evidence', $stored['file_name']);
                flash('success', 'Evidence uploaded.');
            } else {
                flash('error', 'Upload rejected (size or type not permitted).');
            }
        }
    } elseif ($action === 'withdraw') {
        if (!appeal_is_terminal($appeal['status'])) {
            $s = $conn->prepare(
                "UPDATE assessment_appeals SET status='withdrawn' WHERE id = ? AND participant_id = ?"
            );
            $s->bind_param('ii', $appealId, $participantId);
            $s->execute(); $s->close();
            appeal_log($conn, $appealId, $participantId, 'candidate', 'withdrawn', 'Appeal withdrawn');
            flash('success', 'Appeal withdrawn.');
        }
    }
    header('Location: ' . url('candidate/appeal_view.php?id=' . $appealId));
    exit;
}

// Reload with full detail
$appeal = appeal_load_for($conn, $appealId, $participantId);

$messages = [];
$s = $conn->prepare(
    "SELECT m.*, u.first_name, u.last_name
       FROM appeal_messages m
       LEFT JOIN users u ON u.id = m.author_id
      WHERE m.appeal_id = ? AND m.is_internal = 0
      ORDER BY m.created_at ASC"
);
$s->bind_param('i', $appealId); $s->execute();
$r = $s->get_result(); while ($x = $r->fetch_assoc()) $messages[] = $x; $s->close();

$evidence = [];
$s = $conn->prepare("SELECT * FROM appeal_evidence WHERE appeal_id = ? ORDER BY created_at ASC");
$s->bind_param('i', $appealId); $s->execute();
$r = $s->get_result(); while ($x = $r->fetch_assoc()) $evidence[] = $x; $s->close();

require __DIR__ . '/../assessor/_layout_start.php';
?>
<style>
.ap-detail{display:grid;grid-template-columns:1fr 340px;gap:18px}
.ap-thread{display:flex;flex-direction:column;gap:12px;max-height:520px;overflow-y:auto;padding-right:6px}
.ap-msg{padding:12px 14px;border-radius:14px;background:rgba(127,127,127,.06);border:1px solid rgba(127,127,127,.1)}
.ap-msg--assessor{background:#eef2ff;border-color:#c7d2fe}
.ap-msg__head{display:flex;justify-content:space-between;font-size:12px;opacity:.7;margin-bottom:4px}
.ap-ev-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}
.ap-ev-list li{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:rgba(127,127,127,.05)}
@media(max-width:900px){.ap-detail{grid-template-columns:1fr}}
</style>

<div class="as-card" style="padding:22px;margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <h2 style="margin:0 0 4px"><?=e($appeal['reference'])?></h2>
      <div style="font-size:13px;opacity:.7">
        <?=e($appeal['outcome_code'])?> · <?=e($appeal['outcome_title'])?>
        — Attempt <?=(int)$appeal['attempt_number']?>
      </div>
    </div>
    <div>
      <span class="ap-badge ap-badge--<?=e(appeal_status_class($appeal['status']))?>"><?=e(appeal_status_label($appeal['status']))?></span>
    </div>
  </div>
  <hr style="margin:16px 0;border:none;border-top:1px solid rgba(127,127,127,.15)">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;font-size:13px">
    <div><strong>Original result</strong><br><?=e(appeal_status_label((string)($appeal['original_result'] ?? '')))?: '—'?></div>
    <div><strong>Requested</strong><br><?=e(appeal_outcome_label($appeal['requested_outcome']))?></div>
    <div><strong>Lodged</strong><br><?=e(as_datetime($appeal['created_at']))?></div>
    <div><strong>Deadline</strong><br><?=e(as_datetime($appeal['deadline_at']))?></div>
  </div>
  <hr style="margin:16px 0;border:none;border-top:1px solid rgba(127,127,127,.15)">
  <strong>Your grounds</strong>
  <p style="white-space:pre-line;margin:6px 0 0"><?=e($appeal['grounds'])?></p>
  <?php if (!empty($appeal['decision_notes'])): ?>
    <hr style="margin:16px 0;border:none;border-top:1px solid rgba(127,127,127,.15)">
    <strong>Outcome</strong>
    <p style="white-space:pre-line;margin:6px 0 0"><?=e($appeal['decision_notes'])?></p>
  <?php endif; ?>
</div>

<div class="ap-detail">
  <div>
    <section class="as-card" style="padding:20px">
      <h3 style="margin-top:0">Conversation</h3>
      <?php if (!$messages): ?>
        <div class="ap-empty">No messages yet.</div>
      <?php else: ?>
        <div class="ap-thread">
          <?php foreach ($messages as $m): ?>
            <div class="ap-msg <?= $m['author_role']==='assessor'?'ap-msg--assessor':'' ?>">
              <div class="ap-msg__head">
                <span><?=e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: ucfirst($m['author_role']))?></span>
                <span><?=e(as_datetime($m['created_at']))?></span>
              </div>
              <div style="white-space:pre-line"><?=e($m['body'])?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!appeal_is_terminal($appeal['status'])): ?>
        <form method="post" style="margin-top:16px">
          <input type="hidden" name="action" value="message">
          <textarea name="body" required placeholder="Add a message…"
            style="width:100%;min-height:80px;padding:12px;border-radius:12px;border:1px solid rgba(127,127,127,.25);font:inherit"></textarea>
          <button type="submit" class="as-btn as-btn--primary" style="margin-top:8px">
            <i class="fas fa-paper-plane"></i> Send
          </button>
        </form>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <section class="as-card" style="padding:20px;margin-bottom:16px">
      <h3 style="margin-top:0">Evidence</h3>
      <?php if (!$evidence): ?>
        <div style="font-size:13px;opacity:.7">No files uploaded.</div>
      <?php else: ?>
        <ul class="ap-ev-list">
          <?php foreach ($evidence as $ev): ?>
            <li>
              <i class="fas fa-paperclip"></i>
              <a href="<?=url($ev['stored_path'])?>" target="_blank" rel="noopener"><?=e($ev['file_name'])?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if (!appeal_is_terminal($appeal['status'])): ?>
        <form method="post" enctype="multipart/form-data" style="margin-top:14px">
          <input type="hidden" name="action" value="upload">
          <input type="file" name="evidence" required
                 accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
          <button type="submit" class="as-btn as-btn--light" style="margin-top:8px">
            <i class="fas fa-upload"></i> Upload
          </button>
        </form>
      <?php endif; ?>
    </section>

    <?php if (!appeal_is_terminal($appeal['status'])): ?>
      <section class="as-card" style="padding:20px">
        <h3 style="margin-top:0">Withdraw appeal</h3>
        <p style="font-size:13px;opacity:.7">Withdrawing is final and cannot be undone.</p>
        <form method="post" onsubmit="return confirm('Withdraw this appeal?');">
          <input type="hidden" name="action" value="withdraw">
          <button type="submit" class="as-btn as-btn--glass"><i class="fas fa-xmark"></i> Withdraw</button>
        </form>
      </section>
    <?php endif; ?>
  </aside>
</div>
<?php require __DIR__ . '/../assessor/_layout_end.php'; ?>