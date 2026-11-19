<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'eligibility';
$pageTitle = 'Eligibility Checklist';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_document_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

$candidates = [];
if (as_has_table($conn, 'eligibility_criteria') && as_has_table($conn, 'candidate_eligibility')) {
    $sql = "SELECT 
                u.id AS participant_id, u.first_name, u.last_name, u.email,
                (SELECT COUNT(*) FROM eligibility_criteria WHERE is_active = 1) AS total_criteria,
                (SELECT COUNT(*) FROM eligibility_criteria WHERE is_active = 1 AND is_mandatory = 1) AS total_mandatory,
                (SELECT COUNT(*) FROM candidate_eligibility ce JOIN eligibility_criteria ec ON ec.id = ce.criterion_id 
                 WHERE ce.participant_id = u.id AND ec.is_active = 1 AND ce.status = 'met') AS met_criteria,
                (SELECT COUNT(*) FROM candidate_eligibility ce JOIN eligibility_criteria ec ON ec.id = ce.criterion_id 
                 WHERE ce.participant_id = u.id AND ec.is_active = 1 AND ce.status = 'na') AS na_criteria,
                (SELECT COUNT(*) FROM candidate_eligibility ce JOIN eligibility_criteria ec ON ec.id = ce.criterion_id 
                 WHERE ce.participant_id = u.id AND ec.is_active = 1 AND ce.status = 'unmet') AS unmet_criteria,
                (SELECT COUNT(*) FROM candidate_eligibility ce JOIN eligibility_criteria ec ON ec.id = ce.criterion_id 
                 WHERE ce.participant_id = u.id AND ec.is_active = 1 AND ec.is_mandatory = 1 AND ce.status = 'unmet') AS unmet_mandatory,
                (SELECT COUNT(*) FROM candidate_eligibility ce JOIN eligibility_criteria ec ON ec.id = ce.criterion_id 
                 WHERE ce.participant_id = u.id AND ec.is_active = 1 AND ce.status = 'pending') AS pending_evaluated,
                (SELECT COUNT(*) FROM candidate_documents cd 
                 WHERE cd.participant_id = u.id AND cd.status IN ('pending','outstanding','rejected')) AS docs_needing_action,
                (SELECT COUNT(*) FROM candidate_documents cd 
                 WHERE cd.participant_id = u.id AND cd.status = 'verified') AS docs_verified,
                (SELECT COUNT(*) FROM candidate_documents cd WHERE cd.participant_id = u.id) AS docs_total
            FROM users u
            WHERE u.id IN (
                SELECT DISTINCT participant_id FROM assessment_attempts WHERE assessor_id = ?
            )
            ORDER BY u.last_name ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $assessorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $total     = (int)$row['total_criteria'];
            $met       = (int)$row['met_criteria'];
            $na        = (int)$row['na_criteria'];
            $unmet     = (int)$row['unmet_criteria'];
            $unmetMand = (int)$row['unmet_mandatory'];
            $pendingEv = (int)$row['pending_evaluated'];
            $docs      = (int)$row['docs_needing_action'];
            $docsOk    = (int)$row['docs_verified'];
            $docsAll   = (int)$row['docs_total'];

            // Requirements with no row are also considered pending
            $evaluated      = $met + $na + $unmet + $pendingEv;
            $neverEvaluated = max(0, $total - $evaluated);
            $pending        = $pendingEv + $neverEvaluated;
            $resolved       = $met + $na;

            $row['met_criteria']        = $met;
            $row['total_criteria']      = $total;
            $row['total_mandatory']     = (int)$row['total_mandatory'];
            $row['resolved_criteria']   = $resolved;
            $row['unmet_criteria']      = $unmet;
            $row['unmet_mandatory']     = $unmetMand;
            $row['pending_criteria']    = $pending;
            $row['docs_needing_action'] = $docs;
            $row['docs_verified']       = $docsOk;
            $row['docs_total']          = $docsAll;

            // Readiness classification (priority order)
            if ($unmetMand > 0) {
                $row['readiness'] = 'blocked';
                $row['readiness_label'] = 'Blocked';
            } elseif ($docs > 0) {
                $row['readiness'] = 'documents';
                $row['readiness_label'] = 'Docs Pending';
            } elseif ($pending > 0) {
                $row['readiness'] = 'pending';
                $row['readiness_label'] = 'Awaiting Review';
            } else {
                $row['readiness'] = 'ready';
                $row['readiness_label'] = 'Ready';
            }

            $row['progress'] = $total > 0 ? (int)round(($resolved / $total) * 100) : 0;

            // Blocking / outstanding reasons
            $blockers = [];
            if ($unmetMand > 0) {
                $blockers[] = ['type' => 'danger', 'icon' => 'fa-circle-xmark',
                    'text' => $unmetMand . ' mandatory requirement' . ($unmetMand === 1 ? '' : 's') . ' unmet'];
            }
            if ($docs > 0) {
                $blockers[] = ['type' => 'warn', 'icon' => 'fa-folder-exclamation',
                    'text' => $docs . ' document' . ($docs === 1 ? '' : 's') . ' need action'];
            }
            if ($pending > 0) {
                $blockers[] = ['type' => 'info', 'icon' => 'fa-hourglass-half',
                    'text' => $pending . ' requirement' . ($pending === 1 ? '' : 's') . ' pending review'];
            }
            $row['blockers'] = $blockers;

            $candidates[] = $row;
        }
        $stmt->close();
    }
}

/* ------- Readiness overview counts ------- */
// FIXED: Added 'all' key so the tab loop doesn't throw an undefined array key warning
$counts = [
    'all'       => count($candidates), 
    'total'     => count($candidates), 
    'ready'     => 0, 
    'blocked'   => 0, 
    'documents' => 0, 
    'pending'   => 0
];

foreach ($candidates as $c) {
    if (isset($counts[$c['readiness']])) $counts[$c['readiness']]++;
}

/* ------- Filtering ------- */
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'ready', 'blocked', 'documents', 'pending'], true)) $filter = 'all';
$filtered = $filter === 'all'
    ? $candidates
    : array_values(array_filter($candidates, function ($c) use ($filter) { return $c['readiness'] === $filter; }));

/* ------- Display helpers ------- */
$pillClass = ['ready' => 'success', 'blocked' => 'danger', 'documents' => 'warning', 'pending' => 'info'];
$barClass  = ['ready' => 'success', 'blocked' => 'danger', 'documents' => 'warn',    'pending' => 'info'];
$tabDefs   = [
    'all'       => ['label' => 'All Candidates',   'icon' => 'fa-list'],
    'ready'     => ['label' => 'Ready',            'icon' => 'fa-circle-check'],
    'blocked'   => ['label' => 'Blocked',          'icon' => 'fa-circle-exclamation'],
    'documents' => ['label' => 'Docs Pending',     'icon' => 'fa-folder-exclamation'],
    'pending'   => ['label' => 'Awaiting Review',  'icon' => 'fa-hourglass-half'],
];

require __DIR__ . '/_layout_start.php';
?>

<style>
.elig-queue { display:flex; flex-direction:column; gap:12px; }
.elig-card { display:flex; align-items:center; justify-content:space-between; gap:20px; padding:18px 22px; background:var(--as-card,#fff); border:1px solid rgba(127,127,127,.15); border-radius:16px; }
.elig-card__main { flex:1; min-width:0; display:flex; flex-direction:column; gap:10px; }
.elig-card__info h4 { margin:0 0 4px; font-size:15px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.elig-email { font-size:12px; opacity:.65; }

.elig-pill { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; background:rgba(127,127,127,.08); }
.elig-pill--success { background:rgba(34,197,94,.15); color:#15803d; }
.elig-pill--warning { background:rgba(245,158,11,.15); color:#b45309; }
.elig-pill--danger  { background:rgba(239,68,68,.15); color:#b91c1c; }
.elig-pill--info    { background:rgba(59,130,246,.15); color:#1d4ed8; }

.elig-progress-wrap { display:flex; align-items:center; gap:10px; max-width:360px; }
.elig-progress { flex:1; height:6px; background:rgba(127,127,127,.12); border-radius:999px; overflow:hidden; }
.elig-progress__bar { height:100%; border-radius:999px; transition:width .3s ease; }
.elig-progress__bar--success { background:#22c55e; }
.elig-progress__bar--warn    { background:#f59e0b; }
.elig-progress__bar--danger  { background:#ef4444; }
.elig-progress__bar--info    { background:#3b82f6; }
.elig-progress__label { font-size:11px; opacity:.65; white-space:nowrap; font-weight:600; }

.elig-blockers { display:flex; flex-wrap:wrap; gap:6px; }
.elig-blocker-chip { display:inline-flex; align-items:center; gap:5px; font-size:10px; font-weight:700; padding:3px 8px; border-radius:8px; }
.elig-blocker-chip--danger { background:rgba(239,68,68,.1); color:#b91c1c; }
.elig-blocker-chip--warn   { background:rgba(245,158,11,.12); color:#b45309; }
.elig-blocker-chip--info   { background:rgba(59,130,246,.12); color:#1d4ed8; }

.elig-stats { display:flex; gap:16px; align-items:center; flex-shrink:0; }
.elig-stat { text-align:center; min-width:56px; }
.elig-stat strong { display:block; font-size:16px; line-height:1.1; }
.elig-stat span { font-size:10px; opacity:.6; text-transform:uppercase; letter-spacing:.04em; font-weight:700; }

/* Readiness KPI overview */
.readiness-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:20px; }
.rkpi { display:flex; align-items:center; gap:12px; padding:16px; background:rgba(127,127,127,.03); border:1px solid rgba(127,127,127,.12); border-radius:14px; }
.rkpi__icon { width:42px; height:42px; border-radius:11px; display:grid; place-items:center; font-size:16px; flex-shrink:0; }
.rkpi--total .rkpi__icon     { background:rgba(127,127,127,.1); }
.rkpi--ready .rkpi__icon     { background:rgba(34,197,94,.15); color:#15803d; }
.rkpi--blocked .rkpi__icon   { background:rgba(239,68,68,.15); color:#b91c1c; }
.rkpi--documents .rkpi__icon { background:rgba(245,158,11,.15); color:#b45309; }
.rkpi--pending .rkpi__icon   { background:rgba(59,130,246,.15); color:#1d4ed8; }
.rkpi__value { font-size:22px; font-weight:800; line-height:1; }
.rkpi__label { font-size:11px; opacity:.6; text-transform:uppercase; letter-spacing:.04em; font-weight:700; margin-top:3px; }

/* Filter tabs */
.readiness-tabs { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:4px; }
.readiness-tabs a { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:999px; background:rgba(127,127,127,.06); color:inherit; text-decoration:none; font-size:12px; font-weight:700; transition:background .15s; }
.readiness-tabs a:hover { background:rgba(127,127,127,.12); }
.readiness-tabs a.active { background:rgba(59,130,246,.15); color:#1d4ed8; }
.readiness-tabs a .count { background:rgba(0,0,0,.07); padding:1px 8px; border-radius:999px; font-size:10px; }
.readiness-tabs a.active .count { background:rgba(59,130,246,.2); }

@media (max-width:720px) {
  .elig-card { flex-direction:column; align-items:stretch; }
  .elig-stats { justify-content:flex-start; }
}
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-clipboard-user"></i> Candidate Screening</span>
    <h2>Eligibility & Requirements Checklist</h2>
    <p>Verify that candidates meet programme and assessment criteria, review supporting documents, and resolve outstanding information before progressing to evaluation.</p>
  </div>
</section>

<!-- ── Assessment Readiness Monitor (KPI + Filters) ─────────────────── -->
<div class="as-card">
  <div class="as-card__header">
    <div>
      <h3>Assessment Readiness Monitor</h3>
      <p>Real-time readiness across your assigned candidates: <strong>Ready</strong> candidates have all requirements resolved and documents verified; <strong>Blocked</strong> candidates have unmet mandatory criteria; <strong>Docs Pending</strong> have documents needing action.</p>
    </div>
  </div>

  <div class="readiness-kpis">
    <div class="rkpi rkpi--total">
      <div class="rkpi__icon"><i class="fas fa-users"></i></div>
      <div><div class="rkpi__value"><?=$counts['total']?></div><div class="rkpi__label">Total Assigned</div></div>
    </div>
    <div class="rkpi rkpi--ready">
      <div class="rkpi__icon"><i class="fas fa-circle-check"></i></div>
      <div><div class="rkpi__value"><?=$counts['ready']?></div><div class="rkpi__label">Ready</div></div>
    </div>
    <div class="rkpi rkpi--blocked">
      <div class="rkpi__icon"><i class="fas fa-circle-exclamation"></i></div>
      <div><div class="rkpi__value"><?=$counts['blocked']?></div><div class="rkpi__label">Blocked</div></div>
    </div>
    <div class="rkpi rkpi--documents">
      <div class="rkpi__icon"><i class="fas fa-folder-exclamation"></i></div>
      <div><div class="rkpi__value"><?=$counts['documents']?></div><div class="rkpi__label">Docs Pending</div></div>
    </div>
    <div class="rkpi rkpi--pending">
      <div class="rkpi__icon"><i class="fas fa-hourglass-half"></i></div>
      <div><div class="rkpi__value"><?=$counts['pending']?></div><div class="rkpi__label">Awaiting Review</div></div>
    </div>
  </div>

  <div class="readiness-tabs">
    <?php foreach ($tabDefs as $key => $tab): ?>
      <a href="?filter=<?=$key?>" class="<?=$filter === $key ? 'active' : ''?>">
        <i class="fas <?=$tab['icon']?>"></i> <?=e($tab['label'])?>
        <span class="count"><?=$counts[$key]?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Filtered Candidate Queue ─────────────────────────────────────── -->
<div class="as-card">
  <div class="as-card__header">
    <div>
      <h3><?= $filter === 'all' ? 'Assigned Candidates' : 'Filtered Candidates' ?></h3>
      <p>Requirement completion, document status, and outstanding actions per candidate.</p>
    </div>
  </div>

  <?php if (!$filtered): ?>
    <div class="as-empty">
      <i class="fas fa-clipboard-check"></i>
      <p><?= $filter === 'all'
            ? 'No candidates assigned to you yet.'
            : 'No candidates match the "' . e($tabDefs[$filter]['label']) . '" filter.' ?></p>
    </div>
  <?php else: ?>
    <div class="elig-queue">
      <?php foreach ($filtered as $c):
          $f = trim($c['first_name']); $l = trim($c['last_name']);
          $name   = trim("$f $l") ?: 'Candidate';
          $total  = (int)$c['total_criteria'];
          $met    = (int)$c['met_criteria'];
          $docs   = (int)$c['docs_needing_action'];
          $resolved = (int)$c['resolved_criteria'];
          $pending  = (int)$c['pending_criteria'];
          $pClass = $pillClass[$c['readiness']] ?? 'info';
          $bClass = $barClass[$c['readiness']] ?? 'info';
      ?>
      <div class="elig-card">
        <div class="elig-card__main">
          <div class="elig-card__info">
            <h4>
              <?=e($name)?>
              <span class="elig-pill elig-pill--<?=$pClass?>"><?=e($c['readiness_label'])?></span>
            </h4>
            <span class="elig-email"><?=e($c['email'])?></span>
          </div>

          <div class="elig-progress-wrap">
            <div class="elig-progress" title="<?=$resolved?> of <?=$total?> requirements resolved">
              <div class="elig-progress__bar elig-progress__bar--<?=$bClass?>" style="width:<?=(int)$c['progress']?>%"></div>
            </div>
            <span class="elig-progress__label">
              <?= $total > 0 ? $resolved . '/' . $total . ' resolved' : 'No criteria configured' ?>
            </span>
          </div>

          <?php if (!empty($c['blockers'])): ?>
          <div class="elig-blockers">
            <?php foreach ($c['blockers'] as $b): ?>
              <span class="elig-blocker-chip elig-blocker-chip--<?=e($b['type'])?>">
                <i class="fas <?=e($b['icon'])?>"></i> <?=e($b['text'])?>
              </span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="elig-stats">
          <div class="elig-stat">
            <strong><?=$total > 0 ? $met . '/' . $total : '—'?></strong>
            <span>Met</span>
          </div>
          <?php if ($docs > 0): ?>
          <div class="elig-stat">
            <strong style="color:#b45309"><?=$docs?></strong>
            <span>Doc Issues</span>
          </div>
          <?php endif; ?>
          <?php if ($pending > 0): ?>
          <div class="elig-stat">
            <strong style="color:#1d4ed8"><?=$pending?></strong>
            <span>Pending</span>
          </div>
          <?php endif; ?>
          <a class="as-btn as-btn--light" href="<?=url('assessor/review_eligibility.php?id='.$c['participant_id'])?>">Review</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>