<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'evidence';
$pageTitle   = 'Review Evidence';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/evidence_helpers.php';

$conn       = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$id         = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Evidence not found.'); }

$stmt = $conn->prepare(
    "SELECT ae.*, aa.attempt_number, aa.status AS attempt_status, aa.assessor_id,
            aa.participant_id AS attempt_participant, ao.code AS outcome_code, ao.title AS outcome_title,
            ao.id AS outcome_id_real,
            u.first_name, u.last_name, u.email,
            CONCAT(COALESCE(uv.first_name,''),' ',COALESCE(uv.last_name,'')) AS verifier_name,
            ub.id AS uploader_id, CONCAT(COALESCE(ub.first_name,''),' ',COALESCE(ub.last_name,'')) AS uploader_name
       FROM assessment_evidence ae
       JOIN assessment_attempts aa ON aa.id = ae.attempt_id
       LEFT JOIN assessment_outcomes ao ON ao.id = ae.outcome_id
       JOIN users u ON u.id = ae.participant_id
       LEFT JOIN users uv ON uv.id = ae.verified_by
       LEFT JOIN users ub ON ub.id = ae.uploaded_by
      WHERE ae.id = ? AND aa.assessor_id = ?"
);
$stmt->bind_param('ii', $id, $assessorId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) { http_response_code(404); exit('Evidence not found or not assigned to you.'); }

/* ----- Audit trail --------------------------------------------------- */
$log = [];
$ls = $conn->prepare(
    "SELECT l.*, CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS actor
       FROM assessment_evidence_log l
       LEFT JOIN users u ON u.id = l.actor_id
      WHERE l.evidence_id = ? ORDER BY l.id DESC"
);
if ($ls) {
    $ls->bind_param('i', $id);
    $ls->execute();
    $res = $ls->get_result();
    while ($x = $res->fetch_assoc()) $log[] = $x;
    $ls->close();
}

/* ----- Optional criterion linkage ------------------------------------ */
$criteria  = [];
$linkedIds = [];
$hasCriteria = as_has_table($conn, 'assessment_criteria');
if ($hasCriteria) {
    $oc = $conn->prepare("SELECT id, code, title FROM assessment_criteria WHERE outcome_id = ? ORDER BY id ASC");
    if ($oc && !empty($row['outcome_id_real'])) {
        $oc->bind_param('i', $row['outcome_id_real']);
        $oc->execute();
        $res = $oc->get_result();
        while ($x = $res->fetch_assoc()) $criteria[] = $x;
        $oc->close();
    }
    $lk = $conn->prepare("SELECT criterion_id FROM assessment_evidence_criteria WHERE evidence_id = ?");
    if ($lk) {
        $lk->bind_param('i', $id);
        $lk->execute();
        $res = $lk->get_result();
        while ($x = $res->fetch_assoc()) $linkedIds[] = (int)$x['criterion_id'];
        $lk->close();
    }
}

$filePath = ev_resolve_file($row);
$name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Participant';
$filePreviewUrl = $filePath ? url('assessor/evidence_download.php?id=' . $id) : null;

require __DIR__ . '/_layout_start.php';
?>
<style>
.ev-rev{display:grid;grid-template-columns:1.55fr 1fr;gap:22px}
@media(max-width:1050px){.ev-rev{grid-template-columns:1fr}}
.ev-card{background:#fff;border-radius:20px;border:1px solid rgba(127,127,127,.14);padding:22px}
html[data-theme="dark"] .ev-card,body.dark-mode .ev-card{background:#111827;border-color:rgba(148,163,184,.18)}
.ev-card h3{margin:0 0 14px;font-size:16px;display:flex;align-items:center;gap:9px}
.ev-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:999px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.ev-badge--warning{background:rgba(217,119,6,.12);color:#b45309}
.ev-badge--success{background:rgba(22,163,74,.12);color:#15803d}
.ev-badge--danger {background:rgba(220,38,38,.12);color:#b91c1c}
.ev-badge--info   {background:rgba(2,132,199,.12);color:#0369a1}
.ev-badge--muted  {background:rgba(127,127,127,.14);color:inherit}
.ev-meta{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;margin-top:6px}
@media(max-width:600px){.ev-meta{grid-template-columns:1fr}}
.ev-meta div span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6;font-weight:700}
.ev-meta div strong{display:block;margin-top:4px;font-weight:600;font-size:13px;word-break:break-word}
.ev-preview{margin-top:16px;border-radius:14px;overflow:hidden;background:rgba(127,127,127,.06);display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:220px;padding:16px;text-align:center}
.ev-preview img{max-width:100%;max-height:360px;border-radius:10px}
.ev-preview video,.ev-preview audio{max-width:100%;border-radius:10px}
.ev-preview__file{display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px}
.ev-preview__file i{font-size:44px;color:#4f46e5}
.ev-preview a{padding:8px 14px;border-radius:10px;background:#4f46e5;color:#fff;text-decoration:none;font-weight:700;font-size:13px;margin-top:6px}
.ev-sidenote{margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(79,70,229,.06);font-size:12px;line-height:1.55;color:inherit;opacity:.85}
.ev-audit{margin-top:22px}
.ev-audit ol{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px}
.ev-audit li{display:flex;gap:12px;padding:10px 12px;border-radius:12px;background:rgba(127,127,127,.05)}
.ev-audit li i{width:32px;height:32px;flex:0 0 32px;display:grid;place-items:center;border-radius:50%;background:rgba(79,70,229,.12);color:#4f46e5}
.ev-audit li strong{font-size:13px}
.ev-audit li span{font-size:11px;opacity:.65;display:block;margin-top:2px}
.ev-audit li p{margin:6px 0 0;font-size:12px;opacity:.85}
.ev-form fieldset{border:0;margin:0 0 16px;padding:0}
.ev-form legend{font-size:12px;font-weight:800;opacity:.75;letter-spacing:.04em;text-transform:uppercase;margin-bottom:8px}
.ev-radio-group{display:flex;flex-wrap:wrap;gap:6px}
.ev-radio-group label{position:relative;display:inline-flex;align-items:center;padding:8px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);font-size:12px;cursor:pointer;font-weight:600}
.ev-radio-group input{position:absolute;opacity:0;pointer-events:none}
.ev-radio-group input:checked + span{color:#4f46e5}
.ev-radio-group label:has(input:checked){border-color:#4f46e5;background:rgba(79,70,229,.08)}
.ev-form textarea,.ev-form select{width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);background:transparent;color:inherit;font-family:inherit;font-size:13px}
.ev-form textarea{resize:vertical;min-height:80px}
.ev-criteria{display:flex;flex-direction:column;gap:8px;max-height:260px;overflow-y:auto;padding-right:6px}
.ev-criteria label{display:flex;gap:9px;padding:8px 10px;border-radius:10px;background:rgba(127,127,127,.05);font-size:12px;cursor:pointer}
.ev-criteria input{margin-top:2px}
.ev-submit{padding:12px 18px;border:0;border-radius:12px;background:#4f46e5;color:#fff;font-weight:800;cursor:pointer;width:100%;font-size:14px}
.ev-submit--danger{background:#dc2626}
.ev-submit--ghost{background:rgba(127,127,127,.14);color:inherit;margin-top:8px}
</style>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
  <a href="<?= url('assessor/evidence.php') ?>" class="as-btn as-btn--glass" style="padding:8px 14px"><i class="fas fa-arrow-left"></i> Back</a>
  <span class="ev-badge ev-badge--<?= e(ev_status_class($row['status'])) ?>">
    <i class="<?= e(ev_statuses()[$row['status']]['icon'] ?? 'fas fa-circle') ?>"></i>
    <?= e(ev_status_label($row['status'])) ?>
  </span>
</div>

<div class="ev-rev">

  <!-- LEFT: evidence -->
  <div>
    <section class="ev-card">
      <h3><i class="<?= e(ev_file_icon($row['file_mime'] ?? null, $row['evidence_type'])) ?>"></i> <?= e($row['title']) ?></h3>
      <div class="ev-meta">
        <div><span>Candidate</span><strong><?= e($name) ?></strong></div>
        <div><span>Outcome</span><strong><?= e($row['outcome_code'] ?: '—') ?> · <?= e($row['outcome_title'] ?: '—') ?></strong></div>
        <div><span>Attempt</span><strong>#<?= (int)$row['attempt_id'] ?> · Attempt <?= (int)$row['attempt_number'] ?></strong></div>
        <div><span>Type</span><strong><?= e(ev_type_label($row['evidence_type'])) ?></strong></div>
        <div><span>Captured</span><strong><?= e($row['captured_at'] ? as_datetime($row['captured_at']) : '—') ?></strong></div>
        <div><span>Uploaded by</span><strong><?= e(trim($row['uploader_name']) ?: '—') ?></strong></div>
        <?php if (!empty($row['file_name'])): ?>
          <div><span>File</span><strong><?= e($row['file_name']) ?> · <?= e(ev_human_size((int)$row['file_size'])) ?></strong></div>
        <?php endif; ?>
        <?php if (!empty($row['external_url'])): ?>
          <div style="grid-column:1/-1"><span>External link</span><strong><a href="<?= e($row['external_url']) ?>" target="_blank" rel="noopener"><?= e($row['external_url']) ?></a></strong></div>
        <?php endif; ?>
        <?php if (!empty($row['verified_at'])): ?>
          <div><span>Verified</span><strong><?= e(as_datetime($row['verified_at'])) ?></strong></div>
          <div><span>Verifier</span><strong><?= e(trim($row['verifier_name']) ?: '—') ?></strong></div>
        <?php endif; ?>
      </div>

      <?php if (!empty($row['description'])): ?>
        <div class="ev-sidenote"><?= nl2br(e($row['description'])) ?></div>
      <?php endif; ?>

      <div class="ev-preview">
        <?php if ($filePreviewUrl && ev_is_image($row['file_mime'])): ?>
          <img src="<?= e($filePreviewUrl) ?>" alt="<?= e($row['title']) ?>">
        <?php elseif ($filePreviewUrl && ev_is_video($row['file_mime'])): ?>
          <video controls src="<?= e($filePreviewUrl) ?>"></video>
        <?php elseif ($filePreviewUrl && ev_is_audio($row['file_mime'])): ?>
          <audio controls src="<?= e($filePreviewUrl) ?>"></audio>
        <?php elseif ($filePreviewUrl && ev_is_pdf($row['file_mime'])): ?>
          <iframe src="<?= e($filePreviewUrl) ?>" style="width:100%;height:420px;border:0;border-radius:10px"></iframe>
        <?php elseif ($filePreviewUrl): ?>
          <div class="ev-preview__file">
            <i class="<?= e(ev_file_icon($row['file_mime'] ?? null, $row['evidence_type'])) ?>"></i>
            <strong><?= e($row['file_name'] ?: 'Attached file') ?></strong>
            <span style="opacity:.6;font-size:12px"><?= e(ev_human_size((int)$row['file_size'])) ?></span>
            <a href="<?= e($filePreviewUrl . '&download=1') ?>"><i class="fas fa-download"></i> Download</a>
          </div>
        <?php elseif (!empty($row['external_url'])): ?>
          <div class="ev-preview__file">
            <i class="fas fa-link"></i>
            <strong>External evidence</strong>
            <a href="<?= e($row['external_url']) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Open link</a>
          </div>
        <?php else: ?>
          <div class="ev-preview__file">
            <i class="fas fa-pen-to-square"></i>
            <strong>Written / observational evidence</strong>
            <span style="opacity:.65;font-size:12px">No file attached – see description above.</span>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="ev-card ev-audit">
      <h3><i class="fas fa-clock-rotate-left"></i> Audit trail</h3>
      <?php if (!$log): ?>
        <p style="opacity:.65;font-size:13px;margin:0">No audit entries yet.</p>
      <?php else: ?>
        <ol>
          <?php foreach ($log as $l): ?>
            <li>
              <i class="fas fa-history"></i>
              <div>
                <strong><?= e(ev_action_label($l['action'])) ?></strong>
                <span><?= e(trim($l['actor']) ?: 'System') ?> · <?= e(as_datetime($l['created_at'])) ?></span>
                <?php if (!empty($l['notes'])): ?><p><?= nl2br(e($l['notes'])) ?></p><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
  </div>

  <!-- RIGHT: verification form -->
  <div>
    <form class="ev-card ev-form" method="post" action="<?= url('assessor/evidence_verify.php') ?>">
      <input type="hidden" name="_csrf" value="<?= e(ev_csrf_token()) ?>">
      <input type="hidden" name="evidence_id" value="<?= (int)$row['id'] ?>">
      <h3><i class="fas fa-gavel"></i> Verification decision</h3>

      <fieldset>
        <legend>Verification status</legend>
        <select name="status" required>
          <?php foreach (ev_statuses() as $k => $def): ?>
            <option value="<?= e($k) ?>" <?= $row['status'] === $k ? 'selected' : '' ?>><?= e($def['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <fieldset>
        <legend>Sufficiency</legend>
        <div class="ev-radio-group">
          <?php foreach (ev_sufficiencies() as $k => $lab): ?>
            <label><input type="radio" name="sufficiency" value="<?= e($k) ?>" <?= ($row['sufficiency'] ?? '') === $k ? 'checked' : '' ?>><span><?= e($lab) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Relevance</legend>
        <div class="ev-radio-group">
          <?php foreach (ev_relevance_levels() as $k => $lab): ?>
            <label><input type="radio" name="relevance" value="<?= e($k) ?>" <?= ($row['relevance'] ?? '') === $k ? 'checked' : '' ?>><span><?= e($lab) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Authenticity</legend>
        <div class="ev-radio-group">
          <?php foreach (ev_authenticity_levels() as $k => $lab): ?>
            <label><input type="radio" name="authenticity" value="<?= e($k) ?>" <?= ($row['authenticity'] ?? '') === $k ? 'checked' : '' ?>><span><?= e($lab) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Verification notes</legend>
        <textarea name="verification_notes" placeholder="Judgement rationale shown to the candidate and moderator."><?= e($row['verification_notes'] ?? '') ?></textarea>
      </fieldset>

      <fieldset>
        <legend>Internal assessor notes <span style="opacity:.6;font-weight:500;text-transform:none;letter-spacing:0">(not visible to candidate)</span></legend>
        <textarea name="assessor_notes" placeholder="Only visible to assessors and moderators."><?= e($row['assessor_notes'] ?? '') ?></textarea>
      </fieldset>

      <?php if ($hasCriteria && $criteria): ?>
        <fieldset>
          <legend>Linked criteria</legend>
          <div class="ev-criteria">
            <?php foreach ($criteria as $c): ?>
              <label>
                <input type="checkbox" name="criteria[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $linkedIds, true) ? 'checked' : '' ?>>
                <span><strong><?= e($c['code']) ?></strong> · <?= e($c['title']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endif; ?>

      <button type="submit" class="ev-submit"><i class="fas fa-check"></i> Save verification</button>
      <a href="<?= url('assessor/evidence.php') ?>" class="ev-submit ev-submit--ghost" style="display:block;text-align:center;text-decoration:none">Cancel</a>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>