<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');

$user         = current_user();
$currentPage  = 'documents';
$pageTitle    = 'Review Document';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_document_helpers.php';

$conn       = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

$docId = (int)($_GET['id'] ?? 0);
if ($docId <= 0) { http_response_code(400); exit('Invalid document.'); }

/* -------------------------------------------------------------------------
 * POST — verify / reject / request re-upload
 * ---------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? null;
    if (!doc_csrf_ok($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect(url('assessor/review_document.php?id='.$docId));
    }

    $action  = $_POST['action'] ?? '';
    $notes   = trim((string)($_POST['reviewer_notes'] ?? ''));
    $reason  = trim((string)($_POST['rejection_reason'] ?? ''));
    $expires = trim((string)($_POST['expires_on'] ?? ''));
    $expiresVal = $expires !== '' ? $expires : null;

    $newStatus = null;
    if ($action === 'verify')  $newStatus = 'verified';
    if ($action === 'reject') { if ($reason === '') { flash('error','A rejection reason is required.'); redirect(url('assessor/review_document.php?id='.$docId)); } $newStatus = 'rejected'; }
    if ($action === 'request'){ if ($reason === '') { flash('error','Please describe what is outstanding.'); redirect(url('assessor/review_document.php?id='.$docId)); } $newStatus = 'outstanding'; }

    if ($newStatus) {
        $now  = date('Y-m-d H:i:s');
        $rej  = ($newStatus === 'verified') ? null : $reason;
        $stmt = $conn->prepare(
            "UPDATE candidate_documents
                SET status=?, verified_by=?, verified_at=?, reviewer_notes=?,
                    rejection_reason=?, expires_on=?, updated_at=?
              WHERE id=?"
        );
        if ($stmt) {
            $stmt->bind_param('sisssssi',
                $newStatus, $assessorId, $now, $notes, $rej, $expiresVal, $now, $docId);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Document status updated to “'.doc_status_label($newStatus).'”.');
        } else {
            flash('error', 'Could not update document: '.$conn->error);
        }
        redirect(url('assessor/documents.php'));
    }
}

/* -------------------------------------------------------------------------
 * Load document
 * ---------------------------------------------------------------------- */
if (!as_has_table($conn, 'candidate_documents')) {
    http_response_code(503); exit('Document verification tables are not installed.');
}

$stmt = $conn->prepare(
    "SELECT cd.*, u.first_name, u.last_name, u.email,
            dt.code AS type_code, dt.title AS type_title, dt.description AS type_description,
            dt.is_required, dt.requires_expiry,
            v.first_name AS v_first, v.last_name AS v_last
       FROM candidate_documents cd
       JOIN users          u ON u.id = cd.participant_id
       JOIN document_types dt ON dt.id = cd.document_type_id
       LEFT JOIN users     v ON v.id = cd.verified_by
      WHERE cd.id = ? LIMIT 1"
);
$stmt->bind_param('i', $docId);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$doc) { http_response_code(404); exit('Document not found.'); }

$f = trim((string)$doc['first_name']); $l = trim((string)$doc['last_name']);
$name = trim($f.' '.$l) ?: 'Candidate';
require __DIR__.'/_layout_start.php';
?>
<style>
.doc-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:20px;align-items:start}
@media(max-width:960px){.doc-grid{grid-template-columns:1fr}}
.doc-preview{display:flex;flex-direction:column;gap:14px;padding:20px;border-radius:18px;background:var(--as-card,#fff);border:1px solid rgba(127,127,127,.12)}
.doc-preview__head{display:flex;align-items:center;gap:14px}
.doc-preview__icon{width:56px;height:56px;flex:0 0 56px;display:grid;place-items:center;border-radius:16px;background:rgba(79,70,229,.1);font-size:22px}
.doc-preview__body{flex:1;min-width:0}
.doc-preview__body strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.doc-preview__body span{display:block;font-size:12px;opacity:.65;margin-top:3px}
.doc-frame{width:100%;height:520px;border:1px solid rgba(127,127,127,.15);border-radius:14px;background:#f8fafc}
.doc-actions{display:flex;flex-direction:column;gap:14px;padding:20px;border-radius:18px;background:var(--as-card,#fff);border:1px solid rgba(127,127,127,.12)}
.doc-actions h3{margin:0 0 4px}
.doc-actions p{margin:0 0 6px;font-size:13px;opacity:.7}
.doc-actions textarea,.doc-actions input[type="date"]{width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);background:transparent;color:inherit;font-family:inherit;font-size:13px;resize:vertical}
.doc-actions .field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.doc-actions label{font-size:12px;font-weight:700;opacity:.8}
.doc-actions .buttons{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
.doc-actions button{padding:11px 18px;border:0;border-radius:11px;font-weight:700;cursor:pointer;font-size:13px}
.doc-actions button.btn-verify  {background:#16a34a;color:#fff}
.doc-actions button.btn-reject  {background:#dc2626;color:#fff}
.doc-actions button.btn-request {background:#f59e0b;color:#fff}
.doc-timeline{font-size:12px;line-height:1.7;opacity:.8;border-top:1px solid rgba(127,127,127,.12);padding-top:14px;margin-top:4px}
.doc-timeline span{display:block}
.doc-meta{display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;font-size:12px;margin-top:8px}
.doc-meta div span{display:block;opacity:.55;font-size:11px;margin-bottom:2px}
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="<?=e(doc_status_icon($doc['status']))?>"></i> <?=e($doc['type_title'])?></span>
    <h2><?=e($name)?> — <?=e($doc['original_name'])?></h2>
    <p>Review the uploaded evidence, confirm it satisfies the requirement, and record your decision. Every action is auditable.</p>
    <div class="as-hero__actions">
      <a class="as-btn as-btn--light" href="<?=url('assessor/documents.php')?>"><i class="fas fa-arrow-left"></i> Back to queue</a>
      <?php if (!empty($doc['email'])): ?>
        <a class="as-btn as-btn--glass" href="mailto:<?=e($doc['email'])?>"><i class="fas fa-envelope"></i> Contact candidate</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="as-hero__visual">
    <div class="as-quality-orb">
      <div class="as-quality-orb__icon"><i class="<?=e(doc_status_icon($doc['status']))?>"></i></div>
      <strong><?=e(doc_status_label($doc['status']))?></strong>
      <span><?=e($doc['type_code'])?></span>
    </div>
  </div>
</section>

<div class="doc-grid">

  <section class="doc-preview">
    <div class="doc-preview__head">
      <div class="doc-preview__icon"><i class="<?=e(doc_mime_icon($doc['mime_type']))?>"></i></div>
      <div class="doc-preview__body">
        <strong><?=e($doc['original_name'])?></strong>
        <span><?=e($doc['type_title'])?> · <?=e(doc_size((int)$doc['file_size']))?> · <?=e($doc['mime_type'])?></span>
      </div>
    </div>

    <?php if (str_contains(strtolower($doc['mime_type']), 'pdf')): ?>
      <iframe class="doc-frame" src="<?=url('assessor/download_document.php?id='.(int)$doc['id'].'&inline=1')?>" title="Document preview"></iframe>
    <?php elseif (str_starts_with(strtolower($doc['mime_type']), 'image/')): ?>
      <img class="doc-frame" style="height:auto;max-height:520px;object-fit:contain" src="<?=url('assessor/download_document.php?id='.(int)$doc['id'].'&inline=1')?>" alt="Document preview">
    <?php else: ?>
      <div class="doc-empty" style="border:1px dashed rgba(127,127,127,.25);border-radius:14px;padding:40px;text-align:center;">
        <i class="fas fa-file-arrow-down" style="font-size:32px;opacity:.5"></i>
        <p>Preview not available for this file type.</p>
        <a class="as-btn as-btn--light" href="<?=url('assessor/download_document.php?id='.(int)$doc['id'])?>">
          <i class="fas fa-download"></i> Download to review
        </a>
      </div>
    <?php endif; ?>

    <div class="doc-meta">
      <div><span>Candidate</span><strong><?=e($name)?></strong></div>
      <div><span>Email</span><strong><?=e($doc['email'] ?: '—')?></strong></div>
      <div><span>Document type</span><strong><?=e($doc['type_code'])?> — <?=e($doc['type_title'])?></strong></div>
      <div><span>Required</span><strong><?=(int)$doc['is_required']===1?'Yes':'Optional'?></strong></div>
      <div><span>Uploaded</span><strong><?=e(as_datetime($doc['uploaded_at']))?></strong></div>
      <div><span>Expires</span><strong><?=!empty($doc['expires_on'])?e(as_datetime($doc['expires_on'],'d M Y')):'—'?></strong></div>
    </div>

    <?php if (!empty($doc['type_description'])): ?>
      <p style="font-size:12px;opacity:.7;margin:0"><?=e($doc['type_description'])?></p>
    <?php endif; ?>
  </section>

  <aside class="doc-actions">
    <h3>Record your decision</h3>
    <p>Select an outcome. Verified documents become part of the candidate's evidence pack; rejected or outstanding documents are returned to the candidate for re-upload.</p>

    <form method="post">
      <input type="hidden" name="_csrf" value="<?=e(function_exists('csrf_token') ? csrf_token() : '')?>">

      <div class="field">
        <label for="reviewer_notes">Reviewer notes (internal)</label>
        <textarea id="reviewer_notes" name="reviewer_notes" rows="3" placeholder="Observations, cross-checks, caveats…"><?=e($doc['reviewer_notes'] ?? '')?></textarea>
      </div>

      <div class="field">
        <label for="expires_on">Expiry date <?=((int)$doc['requires_expiry']===1)?'(recommended for this type)':'(optional)'?></label>
        <input type="date" id="expires_on" name="expires_on" value="<?=e($doc['expires_on'] ?? '')?>">
      </div>

      <div class="field">
        <label for="rejection_reason">Reason (required when rejecting or requesting re-upload)</label>
        <textarea id="rejection_reason" name="rejection_reason" rows="3" placeholder="What is missing, illegible, expired or incorrect?"><?=e($doc['rejection_reason'] ?? '')?></textarea>
      </div>

      <div class="buttons">
        <button type="submit" name="action" value="verify"  class="btn-verify"  onclick="return confirm('Mark this document as verified?');">
          <i class="fas fa-circle-check"></i> Verify
        </button>
        <button type="submit" name="action" value="reject"  class="btn-reject">
          <i class="fas fa-circle-xmark"></i> Reject
        </button>
        <button type="submit" name="action" value="request" class="btn-request">
          <i class="fas fa-rotate"></i> Mark Outstanding
        </button>
      </div>
    </form>

    <div class="doc-timeline">
      <span><strong>Audit trail</strong></span>
      <span>Uploaded: <?=e(as_datetime($doc['uploaded_at']))?></span>
      <?php if (!empty($doc['verified_at'])): ?>
        <span>Reviewed: <?=e(as_datetime($doc['verified_at']))?></span>
      <?php endif; ?>
      <?php if (!empty($doc['v_first'])): ?>
        <span>Reviewed by: <?=e(trim($doc['v_first'].' '.$doc['v_last']))?></span>
      <?php endif; ?>
      <span>Last updated: <?=e(as_datetime($doc['updated_at']))?></span>
    </div>
  </aside>

</div>

<?php require __DIR__.'/_layout_end.php'; ?>