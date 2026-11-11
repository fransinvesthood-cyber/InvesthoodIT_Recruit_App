<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');

$user         = current_user();
$currentPage  = 'documents';
$pageTitle    = 'Document Verification';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_document_helpers.php';

$conn        = Database::getConnection();
$assessorId  = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

$hasTables = as_has_table($conn, 'candidate_documents')
          && as_has_table($conn, 'document_types');

/* -------------------------------------------------------------------------
 * POST — bulk verify
 * ---------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasTables) {
    $token = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? null;
    if (!doc_csrf_ok($token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect(url('assessor/documents.php'));
    }

    if (($_POST['action'] ?? '') === 'bulk_verify') {
        $ids = array_filter(array_map('intval', (array)($_POST['document_ids'] ?? [])), fn($v) => $v > 0);
        if (!$ids) {
            flash('error', 'No documents selected.');
            redirect(url('assessor/documents.php'));
        }
        $now    = date('Y-m-d H:i:s');
        $stmt   = $conn->prepare(
            "UPDATE candidate_documents
                SET status='verified', verified_by=?, verified_at=?, updated_at=?
              WHERE id=? AND status IN ('pending','outstanding','rejected')"
        );
        $count = 0;
        if ($stmt) {
            foreach ($ids as $id) {
                $stmt->bind_param('issi', $assessorId, $now, $now, $id);
                $stmt->execute();
                $count += $stmt->affected_rows;
            }
            $stmt->close();
        }
        flash($count > 0 ? 'success' : 'warning',
              $count > 0 ? "{$count} document(s) verified." : 'No documents were updated.');
        redirect(url('assessor/documents.php'));
    }
}

/* -------------------------------------------------------------------------
 * Stats
 * ---------------------------------------------------------------------- */
$stats = ['total'=>0,'pending'=>0,'verified'=>0,'rejected'=>0,'outstanding'=>0,'expiring'=>0];
if ($hasTables) {
    $res = $conn->query(
        "SELECT COUNT(*) total,
                COALESCE(SUM(status='pending'),0)     pending,
                COALESCE(SUM(status='verified'),0)    verified,
                COALESCE(SUM(status='rejected'),0)    rejected,
                COALESCE(SUM(status='outstanding'),0) outstanding,
                COALESCE(SUM(status='verified'
                          AND expires_on IS NOT NULL
                          AND expires_on <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)),0) expiring
           FROM candidate_documents"
    );
    if ($res) {
        $row = $res->fetch_assoc() ?: [];
        foreach ($stats as $k => $_) $stats[$k] = (int)($row[$k] ?? 0);
    }
}

/* -------------------------------------------------------------------------
 * Filters
 * ---------------------------------------------------------------------- */
$allowedStatus = ['pending','verified','rejected','outstanding','expired','expiring'];
$statusFilter  = in_array(($_GET['status'] ?? ''), $allowedStatus, true) ? $_GET['status'] : 'all';
$typeFilter    = (int)($_GET['type'] ?? 0);
$search        = trim((string)($_GET['q'] ?? ''));

$where = []; $params = []; $types = '';
if ($statusFilter === 'expiring') {
    $where[] = "cd.status='verified' AND cd.expires_on IS NOT NULL AND cd.expires_on <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($statusFilter !== 'all') {
    $where[] = 'cd.status = ?'; $params[] = $statusFilter; $types .= 's';
}
if ($typeFilter > 0) {
    $where[] = 'cd.document_type_id = ?'; $params[] = $typeFilter; $types .= 'i';
}
if ($search !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR cd.original_name LIKE ?)';
    $like = '%'.$search.'%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
$whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

$documents = [];
if ($hasTables) {
    $sql = "SELECT cd.*, u.first_name, u.last_name, u.email,
                   dt.code AS type_code, dt.title AS type_title, dt.is_required,
                   v.first_name AS v_first, v.last_name AS v_last
              FROM candidate_documents cd
              JOIN users          u ON u.id = cd.participant_id
              JOIN document_types dt ON dt.id = cd.document_type_id
              LEFT JOIN users     v ON v.id = cd.verified_by
              {$whereSql}
             ORDER BY CASE cd.status
                        WHEN 'pending' THEN 0 WHEN 'outstanding' THEN 1
                        WHEN 'rejected' THEN 2 WHEN 'expired' THEN 3
                        WHEN 'verified' THEN 4 ELSE 5 END,
                      cd.uploaded_at DESC
             LIMIT 200";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($params) {
            $refs = [];
            foreach ($params as $k => $v) $refs[$k] = &$params[$k];
            call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $refs));
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $documents[] = $r;
        $stmt->close();
    }
}

/* Document types for filter dropdown */
$docTypes = [];
if ($hasTables) {
    $res = $conn->query("SELECT id, code, title FROM document_types WHERE is_active=1 ORDER BY sort_order, title");
    if ($res) while ($r = $res->fetch_assoc()) $docTypes[] = $r;
}

require __DIR__.'/_layout_start.php';
?>

<style>
/* ---------- Theme Tokens ---------- */
:root {
    --doc-bg-soft: rgba(127,127,127,.08);
    --doc-bg-hover: rgba(127,127,127,.04);
    --doc-indigo: #4f46e5;
    --doc-indigo-soft: rgba(79,70,229,.12);
    --doc-orange: #b45309; 
    --doc-orange-soft: rgba(245,158,11,.15);
    --doc-green: #15803d; 
    --doc-green-soft: rgba(34,197,94,.15);
    --doc-red: #b91c1c; 
    --doc-red-soft: rgba(239,68,68,.15);
    --doc-blue: #1d4ed8; 
    --doc-blue-soft: rgba(59,130,246,.15);
    --doc-purple: #7e22ce; 
    --doc-purple-soft: rgba(168,85,247,.15);
}

/* Automatic Dark Mode */
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]):not(.light) {
        --doc-bg-soft: rgba(255,255,255,.06);
        --doc-bg-hover: rgba(255,255,255,.03);
        --doc-indigo: #818cf8;
        --doc-indigo-soft: rgba(129,140,248,.15);
        --doc-orange: #fbbf24; 
        --doc-orange-soft: rgba(251,191,36,.15);
        --doc-green: #4ade80; 
        --doc-green-soft: rgba(74,222,128,.15);
        --doc-red: #f87171; 
        --doc-red-soft: rgba(248,113,113,.15);
        --doc-blue: #60a5fa; 
        --doc-blue-soft: rgba(96,165,250,.15);
        --doc-purple: #c084fc; 
        --doc-purple-soft: rgba(192,132,252,.15);
        color-scheme: dark;
    }
}

/* Manual Dark Mode Toggles */
[data-theme="dark"], .dark, .dark-mode {
    --doc-bg-soft: rgba(255,255,255,.06);
    --doc-bg-hover: rgba(255,255,255,.03);
    --doc-indigo: #818cf8;
    --doc-indigo-soft: rgba(129,140,248,.15);
    --doc-orange: #fbbf24; 
    --doc-orange-soft: rgba(251,191,36,.15);
    --doc-green: #4ade80; 
    --doc-green-soft: rgba(74,222,128,.15);
    --doc-red: #f87171; 
    --doc-red-soft: rgba(248,113,113,.15);
    --doc-blue: #60a5fa; 
    --doc-blue-soft: rgba(96,165,250,.15);
    --doc-purple: #c084fc; 
    --doc-purple-soft: rgba(192,132,252,.15);
    color-scheme: dark;
}

/* ---------- Layout ---------- */
.doc-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:18px}
@media(max-width:1000px){.doc-stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:620px){.doc-stats{grid-template-columns:repeat(2,1fr)}}

/* FIXED: Replaced var(--as-card, #fff) with --doc-bg-soft */
.doc-stat{display:flex;flex-direction:column;gap:6px;padding:16px 18px;border-radius:16px;background:var(--doc-bg-soft);border:1px solid rgba(127,127,127,.15);cursor:pointer;transition:transform .15s ease,box-shadow .15s ease;text-decoration:none;color:inherit}
.doc-stat:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(15,23,42,.08);background:var(--doc-bg-hover)}
.doc-stat strong{font-size:26px;line-height:1;color:inherit}
.doc-stat span{font-size:12px;opacity:.75}
.doc-stat small{font-size:10px;opacity:.55}

/* FIXED: Replaced var(--as-card, #fff) with --doc-bg-soft */
.doc-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:14px 16px;margin-bottom:16px;border-radius:16px;background:var(--doc-bg-soft);border:1px solid rgba(127,127,127,.15);color:inherit}
.doc-filters input,.doc-filters select{padding:9px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);background:rgba(127,127,127,.04);color:inherit;font-size:13px;min-width:160px}

.doc-list{display:flex;flex-direction:column}
.doc-row{display:flex;align-items:center;gap:14px;padding:14px 6px;border-bottom:1px solid rgba(127,127,127,.1);text-decoration:none;color:inherit}
.doc-row:hover{background:var(--doc-bg-hover)}
.doc-row__check input{width:18px;height:18px;cursor:pointer;accent-color:var(--doc-indigo)}

/* FIXED: Avatar background */
.doc-row__avatar{width:44px;height:44px;flex:0 0 44px;display:grid;place-items:center;border-radius:50%;background:var(--doc-indigo-soft);color:var(--doc-indigo);font-size:12px;font-weight:800}

.doc-row__body{flex:1;min-width:0}
.doc-row__top{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:14px}
.doc-row__body>span{display:block;margin-top:3px;font-size:12px;opacity:.75}
.doc-row__meta{display:flex;flex-wrap:wrap;gap:12px;margin-top:6px;font-size:11px;opacity:.6;color:inherit}
.doc-row__actions{display:flex;gap:8px;align-items:center;flex-shrink:0}

/* FIXED: Badges now use theme variables */
.doc-badge{padding:3px 9px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
.doc-badge--orange{background:var(--doc-orange-soft);color:var(--doc-orange)}
.doc-badge--green{background:var(--doc-green-soft);color:var(--doc-green)}
.doc-badge--red{background:var(--doc-red-soft);color:var(--doc-red)}
.doc-badge--blue{background:var(--doc-blue-soft);color:var(--doc-blue)}
.doc-badge--purple{background:var(--doc-purple-soft);color:var(--doc-purple)}

.doc-empty{padding:60px 20px;text-align:center;opacity:.7}

/* FIXED: Bulkbar now uses theme variables */
.doc-bulkbar{display:none;align-items:center;gap:12px;padding:12px 16px;margin-bottom:12px;border-radius:14px;background:var(--doc-indigo-soft);color:inherit}
.doc-bulkbar.is-active{display:flex}
.doc-bulkbar button{padding:8px 14px;border:0;border-radius:10px;background:var(--doc-indigo);color:#fff;font-weight:700;cursor:pointer;font-size:12px}

@media(max-width:650px){.doc-row__actions{display:none}}
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-file-shield"></i> Candidate Document Verification</span>
    <h2>Verify candidate documents.</h2>
    <p>Review supporting documents submitted by candidates, confirm required evidence is on file, and track anything outstanding or rejected — all with a full audit trail.</p>
    <div class="as-hero__actions">
      <a class="as-btn as-btn--light" href="<?=url('assessor/documents.php')?>"><i class="fas fa-sync"></i> Refresh Queue</a>
      <a class="as-btn as-btn--glass" href="<?=url('assessor/dashboard.php')?>"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
  </div>
  <div class="as-hero__visual">
    <div class="as-quality-orb">
      <div class="as-quality-orb__icon"><i class="fas fa-folder-open"></i></div>
      <strong><?=number_format($stats['pending'])?></strong>
      <span>Awaiting review</span>
    </div>
  </div>
</section>

<section class="doc-stats">
  <a class="doc-stat" href="?status=pending">
    <strong><?=number_format($stats['pending'])?></strong><span>Pending Review</span><small>Awaiting assessor action</small>
  </a>
  <a class="doc-stat" href="?status=verified">
    <strong><?=number_format($stats['verified'])?></strong><span>Verified</span><small>Approved on file</small>
  </a>
  <a class="doc-stat" href="?status=rejected">
    <strong><?=number_format($stats['rejected'])?></strong><span>Rejected</span><small>Needs re-upload</small>
  </a>
  <a class="doc-stat" href="?status=outstanding">
    <strong><?=number_format($stats['outstanding'])?></strong><span>Outstanding</span><small>Awaiting candidate</small>
  </a>
  <a class="doc-stat" href="?status=expiring">
    <strong><?=number_format($stats['expiring'])?></strong><span>Expiring Soon</span><small>Within 30 days</small>
  </a>
</section>

<form class="doc-filters" method="get">
  <input type="search" name="q" value="<?=e($search)?>" placeholder="Search candidate or file name…">
  <select name="status">
    <option value="all" <?=$statusFilter==='all'?'selected':''?>>All statuses</option>
    <?php foreach (['pending','verified','rejected','outstanding','expired','expiring'] as $s): ?>
      <option value="<?=e($s)?>" <?=$statusFilter===$s?'selected':''?>><?=e(doc_status_label($s==='expiring'?'expiring':$s))?></option>
    <?php endforeach; ?>
  </select>
  <select name="type">
    <option value="0">All document types</option>
    <?php foreach ($docTypes as $dt): ?>
      <option value="<?=(int)$dt['id']?>" <?=$typeFilter===(int)$dt['id']?'selected':''?>><?=e($dt['title'])?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="as-btn as-btn--light" style="padding:9px 16px"><i class="fas fa-filter"></i> Filter</button>
  <?php if ($statusFilter!=='all' || $typeFilter>0 || $search!==''): ?>
    <a class="as-link" href="<?=url('assessor/documents.php')?>">Clear</a>
  <?php endif; ?>
</form>

<form method="post" id="bulkForm">
  <input type="hidden" name="_csrf" value="<?=e(function_exists('csrf_token') ? csrf_token() : '')?>">
  <input type="hidden" name="action" value="bulk_verify">
  <div class="doc-bulkbar" id="bulkBar">
    <span><strong id="bulkCount">0</strong> selected</span>
    <button type="submit"><i class="fas fa-circle-check"></i> Verify selected</button>
  </div>

  <section class="as-card">
    <div class="as-card__header">
      <div><h3>Document queue</h3><p><?=number_format(count($documents))?> document(s) matching your filters.</p></div>
    </div>

    <?php if (!$hasTables): ?>
      <div class="doc-empty"><i class="fas fa-database"></i><p>Document tables are not installed yet. Run the migration SQL to enable this feature.</p></div>
    <?php elseif (!$documents): ?>
      <div class="doc-empty"><i class="fas fa-folder-open"></i><p>No documents match your filters.</p></div>
    <?php else: ?>
      <div class="doc-list">
        <?php foreach ($documents as $d):
            $f = trim((string)$d['first_name']); $l = trim((string)$d['last_name']);
            $name = trim($f.' '.$l) ?: 'Candidate';
        ?>
        <div class="doc-row">
          <?php if (in_array($d['status'], ['pending','outstanding','rejected'], true)): ?>
            <label class="doc-row__check"><input type="checkbox" name="document_ids[]" value="<?=(int)$d['id']?>" onchange="updateBulk()"></label>
          <?php else: ?><span style="width:18px"></span><?php endif; ?>

          <div class="doc-row__avatar"><?=e(as_initials($f,$l))?></div>
          <div class="doc-row__body">
            <div class="doc-row__top">
              <strong><?=e($name)?></strong>
              <span class="doc-badge doc-badge--<?=e(doc_status_class($d['status']))?>">
                <i class="<?=e(doc_status_icon($d['status']))?>"></i> <?=e(doc_status_label($d['status']))?>
              </span>
              <?php if ((int)$d['is_required']===1): ?>
                <span class="doc-badge doc-badge--blue">Required</span>
              <?php endif; ?>
            </div>
            <span><?=e($d['type_code'])?> · <?=e($d['type_title'])?></span>
            <div class="doc-row__meta">
              <span><i class="<?=e(doc_mime_icon($d['mime_type']))?>"></i> <?=e($d['original_name'])?></span>
              <span><i class="fas fa-weight-hanging"></i> <?=e(doc_size((int)$d['file_size']))?></span>
              <span><i class="fas fa-clock"></i> Uploaded <?=e(as_datetime($d['uploaded_at']))?></span>
              <?php if (!empty($d['email'])): ?><span><i class="fas fa-envelope"></i> <?=e($d['email'])?></span><?php endif; ?>
              <?php if (!empty($d['expires_on'])): ?>
                <span><i class="fas fa-calendar"></i> Expires <?=e(as_datetime($d['expires_on'], 'd M Y'))?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="doc-row__actions">
            <a class="as-btn as-btn--light" href="<?=url('assessor/review_document.php?id='.(int)$d['id'])?>">
              <i class="fas fa-magnifying-glass"></i> Review
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</form>

<script>
function updateBulk(){
  const boxes = document.querySelectorAll('#bulkForm input[name="document_ids[]"]:checked');
  document.getElementById('bulkCount').textContent = boxes.length;
  document.getElementById('bulkBar').classList.toggle('is-active', boxes.length > 0);
}
updateBulk();
</script>

<?php require __DIR__.'/_layout_end.php'; ?>