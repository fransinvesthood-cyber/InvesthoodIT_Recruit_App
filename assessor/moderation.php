<?php
// ============================================================================
// assessor/moderation.php — Assessment Moderation & Results Review
// Full overwrite. Fixed dark-mode stat cards.
// ============================================================================
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$currentPage = 'moderation';
$pageTitle = 'Moderation Queue';
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$assessorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($assessorId <= 0) { http_response_code(403); exit('Invalid Assessor account.'); }

/* ---------- Criteria (must match review_moderation.php) ---------- */
$criteriaList = [
    ['id' => 1, 'title' => 'Knowledge & Understanding', 'max_score' => 20],
    ['id' => 2, 'title' => 'Practical Application',     'max_score' => 30],
    ['id' => 3, 'title' => 'Communication',              'max_score' => 10],
    ['id' => 4, 'title' => 'Professional Conduct',       'max_score' => 10],
];

/* ---------- Verification engine ---------- */
function verify_attempt_scores(int $attemptId, array $criteriaList): array
{
    $savedScores = $_SESSION['mock_scores'][$attemptId] ?? [];
    $maxTotal    = (float)array_sum(array_column($criteriaList, 'max_score'));

    if (empty($savedScores)) {
        return ['has_scores'=>false,'flags'=>['No assessor scores have been recorded for this attempt yet.'],
                'warnings'=>[],'total'=>0.0,'max'=>$maxTotal,'pct'=>0.0,'breakdown'=>[]];
    }

    $flags = []; $warnings = []; $breakdown = []; $total = 0.0;

    foreach ($criteriaList as $c) {
        $cid=$c['id']; $maxScore=(float)$c['max_score']; $row=$savedScores[$cid]??null;
        if ($row === null) {
            $flags[]='Missing score for "'.$c['title'].'".';
            $breakdown[]=['title'=>$c['title'],'score'=>null,'max'=>$maxScore,'feedback'=>'','issues'=>['Missing']];
            continue;
        }
        $score=(float)($row['score']??0); $feedback=trim((string)($row['feedback']??''));
        $total += $score; $issues=[];
        if ($score<0)                                 { $issues[]='Negative';       $flags[]=$c['title'].' has a negative score ('.$score.').'; }
        if ($score>$maxScore)                         { $issues[]='Exceeds max';    $flags[]=$c['title'].' score ('.$score.') exceeds maximum ('.$maxScore.').'; }
        if ($score>0 && $feedback==='')               { $issues[]='No feedback';    $warnings[]=$c['title'].' has a score but no feedback.'; }
        if ($score>=$maxScore*0.9 && $feedback!=='' && strlen($feedback)<15) { $issues[]='Thin feedback'; $warnings[]=$c['title'].' near-max score with minimal feedback.'; }
        if ($score==0 && $feedback==='')              { $warnings[]=$c['title'].' scored 0 with no explanatory feedback.'; }
        $breakdown[]=['title'=>$c['title'],'score'=>$score,'max'=>$maxScore,'feedback'=>$feedback,'issues'=>$issues];
    }

    $pct = $maxTotal > 0 ? round(($total/$maxTotal)*100, 1) : 0.0;
    return ['has_scores'=>true,'flags'=>$flags,'warnings'=>$warnings,'total'=>$total,'max'=>$maxTotal,'pct'=>$pct,'breakdown'=>$breakdown];
}

/* ---------- Column detection ---------- */
$hasAttemptsTable      = as_has_table($conn, 'assessment_attempts');
$hasModStatusCol       = false;
$hasModeratorResultCol = false;
if ($hasAttemptsTable) {
    $r = $conn->query("SHOW COLUMNS FROM assessment_attempts LIKE 'moderation_status'");
    $hasModStatusCol = $r && $r->num_rows > 0; if ($r) $r->free();
    $r = $conn->query("SHOW COLUMNS FROM assessment_attempts LIKE 'moderator_result'");
    $hasModeratorResultCol = $r && $r->num_rows > 0; if ($r) $r->free();
}

/* ---------- POST — inline decision ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderation_action'])) {
    $attemptId=(int)($_POST['attempt_id']??0);
    $action=(string)($_POST['moderation_action']??'');
    $notes=trim((string)($_POST['moderation_notes']??''));

    if ($attemptId<=0 || !in_array($action,['approve','reject'],true)) { flash('error','Invalid moderation request.'); redirect(url('assessor/moderation.php')); }
    if ($action==='reject' && $notes==='') { flash('error','Moderation notes are required when rejecting an assessment.'); redirect(url('assessor/moderation.php')); }

    $guard=$conn->prepare("SELECT assessor_id FROM assessment_attempts WHERE id = ? LIMIT 1");
    $guard->bind_param('i',$attemptId); $guard->execute();
    $row=$guard->get_result()->fetch_assoc(); $guard->close();

    if (!$row)                                     { flash('error','Assessment not found.');                    redirect(url('assessor/moderation.php')); }
    if ((int)$row['assessor_id']===$assessorId)    { flash('error','You cannot moderate your own assessment.'); redirect(url('assessor/moderation.php')); }

    if ($action==='approve') {
        $v=verify_attempt_scores($attemptId,$criteriaList);
        if (!$v['has_scores'])   { flash('error','Cannot approve: no assessor scores recorded.');             redirect(url('assessor/moderation.php')); }
        if (!empty($v['flags'])) { flash('error','Cannot approve: resolve the detected inconsistencies first.'); redirect(url('assessor/moderation.php')); }
    }

    $now=date('Y-m-d H:i:s');
    if ($action==='approve'){ $newStatus='approved'; $newModStatus='approved'; $message='Assessment approved and finalized.'; }
    else                    { $newStatus='in_review';$newModStatus='rejected'; $message='Assessment rejected and returned to the assessor.'; }

    if ($hasModeratorResultCol) {
        $stmt=$conn->prepare("UPDATE assessment_attempts SET status=?, moderation_status=?, moderator_id=?, moderated_at=?, moderator_result=? WHERE id=?");
        if ($stmt) { $stmt->bind_param('ssissi',$newStatus,$newModStatus,$assessorId,$now,$notes,$attemptId);
                     $stmt->execute()?flash('success',$message):flash('error','Unable to save moderation decision.'); $stmt->close(); }
        else { flash('error','Prepare failed: '.$conn->error); }
    } else {
        $stmt=$conn->prepare("UPDATE assessment_attempts SET status=?, moderation_status=?, moderator_id=?, moderated_at=? WHERE id=?");
        if ($stmt) { $stmt->bind_param('ssisi',$newStatus,$newModStatus,$assessorId,$now,$attemptId);
                     $stmt->execute()?flash('success',$message):flash('error','Unable to save moderation decision.'); $stmt->close(); }
        else { flash('error','Prepare failed: '.$conn->error); }
    }
    redirect(url('assessor/moderation.php'));
}

/* ---------- Load queue ---------- */
$attempts=[]; $queryError='';
if ($hasAttemptsTable) {
    $modFilter = $hasModStatusCol ? "AND (aa.moderation_status IS NULL OR aa.moderation_status = 'pending')" : "";
    $sql = "SELECT aa.id AS attempt_id, aa.attempt_number, aa.status, aa.assessor_result, aa.assessed_at,
                   u.first_name, u.last_name, u.email,
                   ao.code AS outcome_code, ao.title AS outcome_title,
                   assessor.first_name AS assessor_first, assessor.last_name AS assessor_last
            FROM assessment_attempts aa
            JOIN users u ON u.id = aa.participant_id
            JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
            LEFT JOIN users assessor ON assessor.id = aa.assessor_id
            WHERE aa.status IN ('awaiting_moderation','in_review','submitted')
              {$modFilter}
              AND (aa.assessor_id IS NULL OR aa.assessor_id != ?)
            ORDER BY aa.assessed_at ASC";
    $stmt=$conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i',$assessorId); $stmt->execute();
        $res=$stmt->get_result(); while ($row=$res->fetch_assoc()) $attempts[]=$row; $stmt->close();
    } else { $queryError=$conn->error; }
}

foreach ($attempts as &$a) $a['verification']=verify_attempt_scores((int)$a['attempt_id'],$criteriaList); unset($a);

$totalPending=count($attempts); $flagged=$warned=$clean=$unscored=0;
foreach ($attempts as $a) { $v=$a['verification'];
    if (!$v['has_scores']) $unscored++;
    elseif (!empty($v['flags'])) $flagged++;
    elseif (!empty($v['warnings'])) $warned++;
    else $clean++;
}

$statusCounts=[];
if ($hasAttemptsTable) {
    $r=$conn->query("SELECT status, COUNT(*) AS c FROM assessment_attempts GROUP BY status ORDER BY status");
    if ($r) { while ($row=$r->fetch_assoc()) $statusCounts[$row['status']]=(int)$row['c']; $r->free(); }
}

require __DIR__ . '/_layout_start.php';
?>

<style>
/* ---------- Theme tokens ---------- */
:root {
  --mod-accent:       #1a56db;
  --mod-accent-soft:  rgba(37,99,235,.12);
  --mod-green:        #15803d;
  --mod-green-soft:   rgba(22,163,74,.12);
  --mod-amber:        #b45309;
  --mod-amber-soft:   rgba(217,119,6,.12);
  --mod-red:          #b91c1c;
  --mod-red-soft:     rgba(220,38,38,.12);
  --mod-red-tint:     rgba(220,38,38,.08);
  --mod-gray:         #4b5563;
  --mod-muted:        rgba(0,0,0,.55);
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]):not(.light) {
    --mod-accent:      #60a5fa;
    --mod-accent-soft: rgba(96,165,250,.16);
    --mod-green:       #4ade80;
    --mod-green-soft:  rgba(74,222,128,.14);
    --mod-amber:       #fbbf24;
    --mod-amber-soft:  rgba(251,191,36,.14);
    --mod-red:         #f87171;
    --mod-red-soft:    rgba(248,113,113,.14);
    --mod-red-tint:    rgba(248,113,113,.10);
    --mod-gray:        rgba(255,255,255,.75);
    --mod-muted:       rgba(255,255,255,.6);
  }
}

[data-theme="dark"], .dark, .dark-mode {
  --mod-accent:      #60a5fa;
  --mod-accent-soft: rgba(96,165,250,.16);
  --mod-green:       #4ade80;
  --mod-green-soft:  rgba(74,222,128,.14);
  --mod-amber:       #fbbf24;
  --mod-amber-soft:  rgba(251,191,36,.14);
  --mod-red:         #f87171;
  --mod-red-soft:    rgba(248,113,113,.14);
  --mod-red-tint:    rgba(248,113,113,.10);
  --mod-gray:        rgba(255,255,255,.75);
  --mod-muted:       rgba(255,255,255,.6);
}

/* ---------- Layout ---------- */
.mod-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
@media(max-width:860px){.mod-stats{grid-template-columns:repeat(2,1fr);}}

/* FIXED: removed var(--as-card, #fff) which caused invisible text in dark mode */
.mod-stat{display:flex;align-items:center;gap:14px;background:rgba(127,127,127,.08);border:1px solid rgba(127,127,127,.15);border-radius:14px;padding:16px;color:inherit;}
.mod-stat__icon{width:42px;height:42px;border-radius:11px;display:grid;place-items:center;font-size:16px;flex-shrink:0;}
.mod-stat__value{font-size:22px;font-weight:800;line-height:1.1;color:inherit;}
.mod-stat__label{font-size:11px;opacity:.65;text-transform:uppercase;letter-spacing:.05em;font-weight:600;}

.mod-item{padding:16px;border-bottom:1px solid rgba(127,127,127,.12);}
.mod-item:last-child{border-bottom:none;}
.mod-item__top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;}
.mod-item__info h4{margin:0 0 4px;font-size:15px;color:inherit;}
.mod-item__meta{font-size:12px;opacity:.7;display:block;margin-bottom:4px;color:inherit;}
.mod-item__byline{font-size:11px;opacity:.6;display:block;color:inherit;}
.mod-item__actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}

.mod-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:4px 9px;border-radius:999px;white-space:nowrap;}
.mod-badge--green{background:var(--mod-green-soft);color:var(--mod-green);}
.mod-badge--amber{background:var(--mod-amber-soft);color:var(--mod-amber);}
.mod-badge--red{background:var(--mod-red-soft);color:var(--mod-red);}
.mod-badge--gray{background:rgba(127,127,127,.16);color:var(--mod-gray);}

.verify-panel{margin-top:14px;border:1px solid rgba(127,127,127,.18);border-radius:12px;background:rgba(127,127,127,.04);padding:14px;color:inherit;}
.verify-panel h5{margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;opacity:.7;color:inherit;}
.verify-panel h5.is-warn{color:var(--mod-amber);opacity:1;}
.verify-panel h5.is-danger{color:var(--mod-red);opacity:1;}
.verify-breakdown{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
.verify-row{display:grid;grid-template-columns:1.4fr .5fr;gap:12px;padding:8px 10px;border-radius:8px;background:rgba(127,127,127,.06);font-size:13px;color:inherit;}
.verify-row.has-issue{background:var(--mod-red-tint);}
.verify-row__title{font-weight:600;color:inherit;}
.verify-row__fb{font-size:12px;opacity:.75;font-style:italic;margin-top:2px;color:inherit;}
.verify-row__score{text-align:right;font-weight:700;color:inherit;}
.verify-row__issues{font-size:11px;color:var(--mod-red);margin-top:3px;font-weight:600;}
.verify-flags,.verify-warnings{margin:0 0 12px;padding-left:18px;font-size:12px;}
.verify-flags li{color:var(--mod-red);margin-bottom:4px;}
.verify-warnings li{color:var(--mod-amber);margin-bottom:4px;}
.verify-clean{font-size:13px;color:var(--mod-green);font-weight:600;margin-bottom:12px;}
.verify-clean.is-warn{color:var(--mod-amber);}

.decision-form{border-top:1px dashed rgba(127,127,127,.25);padding-top:12px;margin-top:4px;}
.decision-form label{color:inherit;}
.decision-form textarea{width:100%;padding:10px;border-radius:9px;border:1px solid rgba(127,127,127,.25);
    background:rgba(127,127,127,.06);color:inherit;font-size:13px;font-family:inherit;resize:vertical;margin-bottom:10px;}
.decision-form textarea::placeholder{color:var(--mod-muted);opacity:1;}
.decision-form__btns{display:flex;gap:8px;flex-wrap:wrap;}

.as-btn--success{background:#16a34a;color:#fff;border:0;}
.as-btn--danger{background:#dc2626;color:#fff;border:0;}
.as-btn--ghost{background:transparent;border:1px solid rgba(127,127,127,.35);color:inherit;}
.as-btn--ghost:hover{border-color:var(--mod-accent);color:var(--mod-accent);}
.as-btn--sm{padding:7px 12px;font-size:12px;}

.mod-accent{color:var(--mod-accent);}
.mod-warn  {color:var(--mod-amber);}
.mod-danger{color:var(--mod-red);}
.mod-muted {color:var(--mod-muted);font-weight:500;}

.diag{font-size:12px;padding:14px 16px;border-top:1px solid rgba(127,127,127,.12);color:var(--mod-muted);line-height:1.6;}
.diag code{background:rgba(127,127,127,.18);padding:1px 5px;border-radius:4px;font-size:11px;color:inherit;}
</style>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-scale-balanced"></i> Quality Assurance</span>
    <h2>Assessment Moderation &amp; Results Review</h2>
    <p>Review submitted results, verify scoring accuracy, identify inconsistencies, and document moderation decisions before results are finalized.</p>
    <div class="as-hero__actions">
      <button type="button" class="as-btn as-btn--primary" id="launchReviewBtn" <?= $attempts ? '' : 'disabled title="No pending assessments to review"' ?>>
        <i class="fas fa-play"></i> Launch Moderation Review<?= $attempts ? ' (' . count($attempts) . ')' : '' ?>
      </button>
      <a href="#queueSection" class="as-btn as-btn--light"><i class="fas fa-list-check"></i> View Queue</a>
    </div>
  </div>
</section>

<div class="mod-stats">
  <div class="mod-stat">
    <div class="mod-stat__icon" style="background:var(--mod-accent-soft);color:var(--mod-accent);"><i class="fas fa-inbox"></i></div>
    <div><div class="mod-stat__value"><?=$totalPending?></div><div class="mod-stat__label">Pending</div></div>
  </div>
  <div class="mod-stat">
    <div class="mod-stat__icon" style="background:var(--mod-red-soft);color:var(--mod-red);"><i class="fas fa-triangle-exclamation"></i></div>
    <div><div class="mod-stat__value"><?=$flagged?></div><div class="mod-stat__label">Flagged</div></div>
  </div>
  <div class="mod-stat">
    <div class="mod-stat__icon" style="background:var(--mod-amber-soft);color:var(--mod-amber);"><i class="fas fa-circle-exclamation"></i></div>
    <div><div class="mod-stat__value"><?=$warned?></div><div class="mod-stat__label">Warnings</div></div>
  </div>
  <div class="mod-stat">
    <div class="mod-stat__icon" style="background:var(--mod-green-soft);color:var(--mod-green);"><i class="fas fa-circle-check"></i></div>
    <div><div class="mod-stat__value"><?=$clean + $unscored?></div><div class="mod-stat__label">Clean / New</div></div>
  </div>
</div>

<div class="as-card" id="queueSection">
  <div class="as-card__header">
    <div><h3>Awaiting Moderation</h3><p>Assessments submitted by assessors for QA sign-off.</p></div>
  </div>

  <?php if (!$attempts): ?>
    <div class="as-empty"><i class="fas fa-shield-check"></i><p>No assessments are currently awaiting moderation.</p></div>
    <div class="diag">
      <strong><i class="fas fa-stethoscope"></i> Diagnostics</strong><br>
      <?php if (!$hasAttemptsTable): ?>
        Table <code>assessment_attempts</code> does not exist.
      <?php elseif ($queryError !== ''): ?>
        <span class="mod-danger">Query error: <?= e($queryError) ?></span>
      <?php elseif (empty($statusCounts)): ?>
        <code>assessment_attempts</code> is empty — insert a test row to exercise the queue.
      <?php else: ?>
        Row counts by status:
        <?php $parts=[]; foreach ($statusCounts as $s=>$c) $parts[]='<code>'.e((string)$s).'</code> = '.(int)$c; echo implode(', ', $parts); ?>
        <br>
        Queue requires <code>status IN ('awaiting_moderation','in_review','submitted')</code>
        <?php if ($hasModStatusCol): ?>and <code>moderation_status</code> IN (<code>NULL</code>, <code>'pending'</code>).<?php else: ?>
          <span class="mod-warn">(<code>moderation_status</code> column missing — filter relaxed.)</span>
        <?php endif; ?>
        Rows where <code>assessor_id = <?=$assessorId?></code> (your own work) are excluded.
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($attempts as $a):
        $f=trim($a['first_name']); $l=trim($a['last_name']); $name=trim("$f $l")?:'Candidate';
        $af=trim((string)$a['assessor_first']); $al=trim((string)$a['assessor_last']); $assessorName=trim("$af $al")?:'Assessor';
        $v=$a['verification']; $aid=(int)$a['attempt_id'];
        if (!$v['has_scores'])          { $bc='mod-badge--gray';  $bt='<i class="fas fa-hourglass-half"></i> Not Scored'; }
        elseif (!empty($v['flags']))    { $bc='mod-badge--red';   $bt='<i class="fas fa-triangle-exclamation"></i> '.count($v['flags']).' Issue'.(count($v['flags'])===1?'':'s'); }
        elseif (!empty($v['warnings'])) { $bc='mod-badge--amber'; $bt='<i class="fas fa-circle-exclamation"></i> '.count($v['warnings']).' Warning'.(count($v['warnings'])===1?'':'s'); }
        else                            { $bc='mod-badge--green'; $bt='<i class="fas fa-circle-check"></i> Verified'; }
    ?>
    <div class="mod-item">
      <div class="mod-item__top">
        <div class="mod-item__info">
          <h4><?=e($name)?> <span class="as-badge as-badge--blue">Attempt <?=(int)$a['attempt_number']?></span></h4>
          <span class="mod-item__meta"><?=e($a['outcome_code'])?> · <?=e($a['outcome_title'])?></span>
          <span class="mod-item__byline"><i class="fas fa-user-pen"></i> Scored by <?=e($assessorName)?> on <?=e(as_datetime($a['assessed_at']))?></span>
        </div>
        <div class="mod-item__actions">
          <span class="mod-badge <?=$bc?>"><?=$bt?></span>
          <button type="button" class="as-btn as-btn--ghost as-btn--sm verify-btn" data-target="verify-<?=$aid?>">
            <i class="fas fa-magnifying-glass-chart"></i> Verify
          </button>
          <a href="<?=url('assessor/review_moderation.php?id='.$aid)?>" class="as-btn as-btn--primary as-btn--sm">
            <i class="fas fa-up-right-from-square"></i> Review Scores
          </a>
        </div>
      </div>

      <div class="verify-panel" id="verify-<?=$aid?>" hidden>
        <?php if (!$v['has_scores']): ?>
          <div class="verify-clean is-warn"><i class="fas fa-hourglass-half"></i> No assessor scores have been recorded for this attempt yet.</div>
        <?php else: ?>
          <h5>Score Verification</h5>
          <div class="verify-breakdown">
            <?php foreach ($v['breakdown'] as $b): ?>
            <div class="verify-row<?=!empty($b['issues'])?' has-issue':''?>">
              <div>
                <div class="verify-row__title"><?=e($b['title'])?></div>
                <?php if ($b['feedback']!==''): ?><div class="verify-row__fb">"<?=e($b['feedback'])?>"</div><?php endif; ?>
                <?php if (!empty($b['issues'])): ?><div class="verify-row__issues"><i class="fas fa-flag"></i> <?=e(implode(', ',$b['issues']))?></div><?php endif; ?>
              </div>
              <div class="verify-row__score">
                <?= $b['score']===null?'—':number_format((float)$b['score'],1) ?>
                <span class="mod-muted" style="font-size:11px;">/ <?=number_format((float)$b['max'],1)?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($v['flags'])): ?>
            <h5 class="is-danger">Inconsistencies Detected</h5>
            <ul class="verify-flags"><?php foreach ($v['flags'] as $fl): ?><li><?=e($fl)?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if (!empty($v['warnings'])): ?>
            <h5 class="is-warn">Warnings</h5>
            <ul class="verify-warnings"><?php foreach ($v['warnings'] as $w): ?><li><?=e($w)?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if (empty($v['flags']) && empty($v['warnings'])): ?>
            <div class="verify-clean"><i class="fas fa-circle-check"></i> All scores verified — no inconsistencies detected.</div>
          <?php endif; ?>
          <div style="font-size:13px;font-weight:700;margin-bottom:12px;">
            Total: <?=number_format($v['total'],1)?> / <?=number_format($v['max'],1)?>
            <span class="mod-accent">(<?=number_format($v['pct'],1)?>%)</span>
          </div>
        <?php endif; ?>

        <form method="post" class="decision-form">
          <input type="hidden" name="attempt_id" value="<?=$aid?>">
          <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px;opacity:.75;">
            Moderation Notes (required for rejection)
          </label>
          <textarea name="moderation_notes" rows="2" placeholder="Document your verification findings or reasons for rejection..."></textarea>
          <div class="decision-form__btns">
            <button type="submit" name="moderation_action" value="approve" class="as-btn as-btn--success as-btn--sm"
                    onclick="return confirm('Approve this assessment and finalize the candidate result?');">
              <i class="fas fa-check"></i> Approve &amp; Finalize
            </button>
            <button type="submit" name="moderation_action" value="reject" class="as-btn as-btn--danger as-btn--sm"
                    onclick="return confirm('Reject this assessment and return it to the assessor?');">
              <i class="fas fa-xmark"></i> Reject &amp; Return
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
(function () {
  document.querySelectorAll('.verify-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = document.getElementById(btn.dataset.target);
      if (!panel) return;
      panel.hidden = !panel.hidden;
      btn.innerHTML = panel.hidden
        ? '<i class="fas fa-magnifying-glass-chart"></i> Verify'
        : '<i class="fas fa-eye-slash"></i> Hide';
    });
  });
  var launch = document.getElementById('launchReviewBtn');
  if (launch && !launch.disabled) {
    launch.addEventListener('click', function () {
      document.querySelectorAll('.verify-panel').forEach(function (p) { p.hidden = false; });
      document.querySelectorAll('.verify-btn').forEach(function (b) {
        b.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
      });
      var q = document.getElementById('queueSection');
      if (q) q.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
})();
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>