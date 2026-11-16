<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'evidence';
$pageTitle   = 'Evidence & Competency Verification';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/evidence_helpers.php';

$conn        = Database::getConnection();
$assessorId  = (int)($user['id'] ?? $user['user_id'] ?? 0);
$installed   = as_has_table($conn, 'assessment_evidence');

/* ------------------------------------------------------------------ Filters */
$filterStatus = trim((string)($_GET['status'] ?? ''));
$filterType   = trim((string)($_GET['type']   ?? ''));
$filterQ      = trim((string)($_GET['q']      ?? ''));
$allowedStat  = array_keys(ev_statuses());
$allowedType  = array_keys(ev_types());
if ($filterStatus !== '' && !in_array($filterStatus, $allowedStat, true)) $filterStatus = '';
if ($filterType   !== '' && !in_array($filterType,   $allowedType, true)) $filterType   = '';

$stats = ['total'=>0,'pending'=>0,'verified'=>0,'rejected'=>0,'needs_more'=>0,'outstanding'=>0,'archived'=>0];
$rows  = [];

if ($installed) {
    /* ----- Stats (scoped to this assessor's attempts) --------------- */
    $sql = "SELECT COUNT(*) total,
                   COALESCE(SUM(ae.status='pending'),0)     pending,
                   COALESCE(SUM(ae.status='verified'),0)    verified,
                   COALESCE(SUM(ae.status='rejected'),0)    rejected,
                   COALESCE(SUM(ae.status='needs_more'),0)  needs_more,
                   COALESCE(SUM(ae.status='outstanding'),0) outstanding,
                   COALESCE(SUM(ae.status='archived'),0)    archived
              FROM assessment_evidence ae
              JOIN assessment_attempts aa ON aa.id = ae.attempt_id
             WHERE aa.assessor_id = ?";
    $s = $conn->prepare($sql);
    if ($s) {
        $s->bind_param('i', $assessorId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc() ?: [];
        foreach ($stats as $k => $_) $stats[$k] = (int)($r[$k] ?? 0);
        $s->close();
    }

    /* ----- List ------------------------------------------------------ */
    $where  = ["aa.assessor_id = ?"];
    $types  = "i";
    $params = [$assessorId];
    if ($filterStatus !== '') { $where[] = "ae.status = ?";        $types .= "s"; $params[] = $filterStatus; }
    if ($filterType   !== '') { $where[] = "ae.evidence_type = ?"; $types .= "s"; $params[] = $filterType; }
    if ($filterQ !== '') {
        $like = '%' . $filterQ . '%';
        $where[] = "(ae.title LIKE ? OR ae.description LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
        $types  .= "sss";
        array_push($params, $like, $like, $like);
    }
    $whereSql = implode(' AND ', $where);

    $list = $conn->prepare(
        "SELECT ae.*,
                aa.attempt_number, aa.status AS attempt_status,
                ao.code AS outcome_code, ao.title AS outcome_title,
                u.first_name, u.last_name, u.email,
                CONCAT(COALESCE(uv.first_name,''), ' ', COALESCE(uv.last_name,'')) AS verifier_name
           FROM assessment_evidence ae
           JOIN assessment_attempts aa ON aa.id = ae.attempt_id
           LEFT JOIN assessment_outcomes ao ON ao.id = ae.outcome_id
           JOIN users u ON u.id = ae.participant_id
           LEFT JOIN users uv ON uv.id = ae.verified_by
          WHERE {$whereSql}
       ORDER BY FIELD(ae.status,'pending','needs_more','outstanding','verified','rejected','archived'),
                ae.created_at DESC
          LIMIT 200"
    );
    if ($list) {
        $list->bind_param($types, ...$params);
        $list->execute();
        $res = $list->get_result();
        while ($x = $res->fetch_assoc()) $rows[] = $x;
        $list->close();
    }
}

/* ----- Attempts for the upload modal (scoped to this assessor) ------- */
$uploadAttempts = [];
if ($installed) {
    $ua = $conn->prepare(
        "SELECT aa.id, aa.attempt_number, ao.code, ao.title,
                CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS candidate
           FROM assessment_attempts aa
           JOIN users u ON u.id = aa.participant_id
           LEFT JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
          WHERE aa.assessor_id = ?
            AND aa.status NOT IN ('finalised','finalized','completed','cancelled','withdrawn','certified')
       ORDER BY aa.id DESC
          LIMIT 100"
    );
    if ($ua) {
        $ua->bind_param('i', $assessorId);
        $ua->execute();
        $res = $ua->get_result();
        while ($x = $res->fetch_assoc()) $uploadAttempts[] = $x;
        $ua->close();
    }
}

require __DIR__ . '/_layout_start.php';
?>
<style>
.ev-hero{--ev-accent:#4f46e5}
.ev-stats{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin:18px 0}
@media(max-width:1100px){.ev-stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:600px){.ev-stats{grid-template-columns:repeat(2,1fr)}}
.ev-stat{display:flex;flex-direction:column;gap:6px;padding:16px;border-radius:18px;background:rgba(127,127,127,.06);cursor:pointer;text-decoration:none;color:inherit;transition:transform .15s ease,background .15s ease;border:1px solid rgba(127,127,127,.08)}
.ev-stat:hover{transform:translateY(-3px);background:rgba(79,70,229,.08)}
.ev-stat__icon{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:rgba(79,70,229,.12);font-size:15px}
.ev-stat strong{font-size:23px;line-height:1}
.ev-stat span{font-size:12px;opacity:.7}
.ev-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:14px 16px;border-radius:16px;background:rgba(127,127,127,.05);margin-bottom:16px}
.ev-tabs{display:flex;flex-wrap:wrap;gap:6px}
.ev-tab{padding:7px 12px;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;color:inherit;background:rgba(127,127,127,.08)}
.ev-tab.is-active{background:#4f46e5;color:#fff}
.ev-field{display:flex;gap:8px;flex-wrap:wrap;margin-left:auto}
.ev-field select,.ev-field input{padding:8px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);background:transparent;color:inherit;font-size:13px}
.ev-field button{padding:8px 14px;border-radius:10px;border:0;background:#4f46e5;color:#fff;font-weight:700;cursor:pointer;font-size:13px}
.ev-list{display:flex;flex-direction:column}
.ev-row{display:flex;align-items:center;gap:14px;padding:14px 6px;border-bottom:1px solid rgba(127,127,127,.1);text-decoration:none;color:inherit}
.ev-row:hover{background:rgba(79,70,229,.04)}
.ev-row__icon{width:44px;height:44px;flex:0 0 44px;display:grid;place-items:center;border-radius:12px;background:rgba(79,70,229,.1);font-size:15px}
.ev-row__body{flex:1;min-width:0}
.ev-row__top{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ev-row__top strong{font-size:14px}
.ev-row__meta{display:flex;flex-wrap:wrap;gap:12px;margin-top:5px;font-size:11px;opacity:.65}
.ev-pill{padding:3px 9px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
.ev-pill--warning{background:rgba(217,119,6,.12);color:#b45309}
.ev-pill--success{background:rgba(22,163,74,.12);color:#15803d}
.ev-pill--danger {background:rgba(220,38,38,.12);color:#b91c1c}
.ev-pill--info   {background:rgba(2,132,199,.12);color:#0369a1}
.ev-pill--muted  {background:rgba(127,127,127,.14);color:inherit}
.ev-empty{min-height:260px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:20px}
.ev-empty i{font-size:34px;opacity:.45}
.ev-modal{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;padding:20px}
.ev-modal.is-open{display:flex}
.ev-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(5px)}
.ev-modal__dialog{position:relative;z-index:1;width:min(640px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border-radius:22px;background:#fff;box-shadow:0 30px 90px rgba(0,0,0,.3)}
.ev-modal__header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid rgba(127,127,127,.14)}
.ev-modal__header h3{margin:0;font-size:17px}
.ev-modal__close{width:38px;height:38px;border:0;border-radius:50%;background:rgba(127,127,127,.1);cursor:pointer;color:inherit}
.ev-modal__body{padding:18px 22px;overflow-y:auto}
.ev-modal__footer{padding:14px 22px;border-top:1px solid rgba(127,127,127,.14);display:flex;justify-content:flex-end;gap:10px}
.ev-form-row{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.ev-form-row label{font-size:12px;font-weight:700;opacity:.8}
.ev-form-row input,.ev-form-row select,.ev-form-row textarea{padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);background:transparent;color:inherit;font-size:13px;font-family:inherit}
.ev-form-row textarea{resize:vertical;min-height:70px}
.ev-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
@media(max-width:600px){.ev-form-grid{grid-template-columns:1fr}}
.ev-btn{padding:10px 16px;border:0;border-radius:10px;font-weight:700;cursor:pointer;font-size:13px}
.ev-btn--primary{background:#4f46e5;color:#fff}
.ev-btn--ghost{background:rgba(127,127,127,.12);color:inherit}
body.dark-mode .ev-modal__dialog,html[data-theme="dark"] .ev-modal__dialog{background:#111827;color:#f8fafc}
</style>

<section class="as-hero ev-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-file-shield"></i> Evidence &amp; Competency Verification</span>
    <h2>Verify evidence. Confirm competence.</h2>
    <p>Capture, review and verify candidate evidence against assessment outcomes and criteria. Every verification is logged for audit and moderation.</p>
    <div class="as-hero__actions">
      <button type="button" class="as-btn as-btn--light" data-ev-open>
        <i class="fas fa-cloud-arrow-up"></i> Capture evidence
      </button>
      <a class="as-btn as-btn--glass" href="<?= url('assessor/evidence.php?status=pending') ?>">
        <i class="fas fa-list-check"></i> Review queue
      </a>
    </div>
  </div>
  <div class="as-hero__visual">
    <div class="as-quality-orb">
      <div class="as-quality-orb__icon"><i class="fas fa-fingerprint"></i></div>
      <strong>Traceable</strong>
      <span>Evidence-led verification</span>
    </div>
  </div>
</section>

<?php if (!$installed): ?>
  <section class="as-card" style="margin-top:18px">
    <div class="ev-empty">
      <i class="fas fa-database"></i>
      <strong>Evidence module not installed</strong>
      <span>Run <code>database/evidence_module.sql</code> to create the required tables.</span>
    </div>
  </section>
<?php else: ?>

<section class="ev-stats">
  <a class="ev-stat" href="?"><span class="ev-stat__icon"><i class="fas fa-layer-group"></i></span><strong><?= number_format($stats['total']) ?></strong><span>All evidence</span></a>
  <a class="ev-stat" href="?status=pending"><span class="ev-stat__icon"><i class="fas fa-hourglass-half"></i></span><strong><?= number_format($stats['pending']) ?></strong><span>Pending review</span></a>
  <a class="ev-stat" href="?status=verified"><span class="ev-stat__icon"><i class="fas fa-circle-check"></i></span><strong><?= number_format($stats['verified']) ?></strong><span>Verified</span></a>
  <a class="ev-stat" href="?status=rejected"><span class="ev-stat__icon"><i class="fas fa-circle-xmark"></i></span><strong><?= number_format($stats['rejected']) ?></strong><span>Rejected</span></a>
  <a class="ev-stat" href="?status=needs_more"><span class="ev-stat__icon"><i class="fas fa-circle-info"></i></span><strong><?= number_format($stats['needs_more']) ?></strong><span>Needs more info</span></a>
  <a class="ev-stat" href="?status=outstanding"><span class="ev-stat__icon"><i class="fas fa-triangle-exclamation"></i></span><strong><?= number_format($stats['outstanding']) ?></strong><span>Outstanding</span></a>
</section>

<form method="get" class="ev-filters">
  <div class="ev-tabs">
    <a class="ev-tab <?= $filterStatus === '' ? 'is-active' : '' ?>" href="?type=<?= urlencode($filterType) ?>&q=<?= urlencode($filterQ) ?>">All</a>
    <?php foreach (ev_statuses() as $k => $def): ?>
      <a class="ev-tab <?= $filterStatus === $k ? 'is-active' : '' ?>"
         href="?status=<?= urlencode($k) ?>&type=<?= urlencode($filterType) ?>&q=<?= urlencode($filterQ) ?>"><?= e($def['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="ev-field">
    <select name="type" onchange="this.form.submit()">
      <option value="">All types</option>
      <?php foreach (ev_types() as $k => $def): ?>
        <option value="<?= e($k) ?>" <?= $filterType === $k ? 'selected' : '' ?>><?= e($def['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($filterQ) ?>" placeholder="Search title, candidate…">
    <button type="submit"><i class="fas fa-filter"></i> Filter</button>
  </div>
</form>

<section class="as-card">
  <div class="as-card__header">
    <div><h3>Evidence records</h3><p><?= number_format(count($rows)) ?> record<?= count($rows) === 1 ? '' : 's' ?> matching your current filters.</p></div>
  </div>
  <?php if (!$rows): ?>
    <div class="ev-empty">
      <i class="fas fa-file-shield"></i>
      <strong>No evidence matches these filters</strong>
      <span>Try clearing filters or capturing evidence for an assigned attempt.</span>
    </div>
  <?php else: ?>
    <div class="ev-list">
      <?php foreach ($rows as $r):
          $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Participant';
          $typeIcon = ev_file_icon($r['file_mime'] ?? null, $r['evidence_type']);
      ?>
        <a class="ev-row" href="<?= url('assessor/evidence_review.php?id=' . (int)$r['id']) ?>">
          <span class="ev-row__icon"><i class="<?= e($typeIcon) ?>"></i></span>
          <div class="ev-row__body">
            <div class="ev-row__top">
              <strong><?= e($r['title']) ?></strong>
              <span class="ev-pill ev-pill--<?= e(ev_status_class($r['status'])) ?>"><?= e(ev_status_label($r['status'])) ?></span>
              <?php if (!empty($r['sufficiency'])): ?>
                <span class="ev-pill ev-pill--muted"><?= e(ucfirst($r['sufficiency'])) ?></span>
              <?php endif; ?>
            </div>
            <div class="ev-row__meta">
              <span><i class="fas fa-user"></i> <?= e($name) ?></span>
              <span><i class="fas fa-award"></i> <?= e($r['outcome_code'] ?: '—') ?> · <?= e($r['outcome_title'] ?: '—') ?></span>
              <span><i class="fas fa-rotate"></i> Attempt <?= (int)$r['attempt_number'] ?></span>
              <span><i class="fas fa-tag"></i> <?= e(ev_type_label($r['evidence_type'])) ?></span>
              <?php if (!empty($r['captured_at'])): ?>
                <span><i class="fas fa-clock"></i> Captured <?= e(as_datetime($r['captured_at'])) ?></span>
              <?php endif; ?>
              <?php if (!empty($r['verifier_name']) && trim($r['verifier_name']) !== ''): ?>
                <span><i class="fas fa-user-shield"></i> Verified by <?= e(trim($r['verifier_name'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <i class="fas fa-chevron-right" style="opacity:.4"></i>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Upload modal -->
<div class="ev-modal" id="evUploadModal" aria-hidden="true">
  <div class="ev-modal__backdrop" data-ev-close></div>
  <form class="ev-modal__dialog" method="post" action="<?= url('assessor/evidence_upload.php') ?>" enctype="multipart/form-data" role="dialog" aria-modal="true">
    <header class="ev-modal__header">
      <h3><i class="fas fa-cloud-arrow-up"></i> Capture evidence</h3>
      <button type="button" class="ev-modal__close" data-ev-close aria-label="Close"><i class="fas fa-xmark"></i></button>
    </header>
    <div class="ev-modal__body">
      <input type="hidden" name="_csrf" value="<?= e(ev_csrf_token()) ?>">
      <div class="ev-form-row">
        <label for="evAttempt">Assessment attempt</label>
        <select id="evAttempt" name="attempt_id" required>
          <option value="">Select an attempt…</option>
          <?php foreach ($uploadAttempts as $a): ?>
            <option value="<?= (int)$a['id'] ?>">
              #<?= (int)$a['id'] ?> · <?= e($a['candidate']) ?> · <?= e($a['code']) ?> <?= e($a['title']) ?> (Attempt <?= (int)$a['attempt_number'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ev-form-grid">
        <div class="ev-form-row">
          <label for="evType">Evidence type</label>
          <select id="evType" name="evidence_type" required>
            <?php foreach (ev_types() as $k => $def): ?>
              <option value="<?= e($k) ?>"><?= e($def['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ev-form-row">
          <label for="evCaptured">Captured date</label>
          <input id="evCaptured" type="datetime-local" name="captured_at" value="<?= e(date('Y-m-d\TH:i')) ?>">
        </div>
      </div>
      <div class="ev-form-row">
        <label for="evTitle">Title <span style="opacity:.6">(required)</span></label>
        <input id="evTitle" type="text" name="title" maxlength="255" required placeholder="e.g. Workplace observation – cable termination">
      </div>
      <div class="ev-form-row">
        <label for="evDesc">Description / context</label>
        <textarea id="evDesc" name="description" placeholder="What does this evidence demonstrate?"></textarea>
      </div>
      <div class="ev-form-row">
        <label for="evFile">Attach file <span style="opacity:.6">(optional, max 20 MB)</span></label>
        <input id="evFile" type="file" name="evidence_file">
      </div>
      <div class="ev-form-row">
        <label for="evUrl">External URL <span style="opacity:.6">(optional)</span></label>
        <input id="evUrl" type="url" name="external_url" placeholder="https://">
      </div>
    </div>
    <footer class="ev-modal__footer">
      <button type="button" class="ev-btn ev-btn--ghost" data-ev-close>Cancel</button>
      <button type="submit" class="ev-btn ev-btn--primary"><i class="fas fa-cloud-arrow-up"></i> Upload evidence</button>
    </footer>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal  = document.getElementById('evUploadModal');
  const opener = document.querySelector('[data-ev-open]');
  if (!modal || !opener) return;
  const open  = () => { modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; };
  const close = () => { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; };
  opener.addEventListener('click', open);
  modal.querySelectorAll('[data-ev-close]').forEach(el => el.addEventListener('click', close));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('is-open')) close(); });
});
</script>

<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>