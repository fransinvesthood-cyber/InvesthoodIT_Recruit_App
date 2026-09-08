<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
require_once __DIR__ . '/_helpers.php';
$user = current_user();
$flashes = render_flashes();
$currentPage = 'dashboard';
$pageTitle = 'Dashboard';
$supervisorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($supervisorId <= 0) { http_response_code(403); exit('Invalid Supervisor account.'); }
$firstName = trim((string)($user['first_name'] ?? 'Supervisor')) ?: 'Supervisor';

$stmt = Database::prepare("SELECT COUNT(DISTINCT c.id) assigned_cohorts,
 COUNT(DISTINCT CASE WHEN c.status='active' THEN c.id END) active_cohorts,
 COUNT(DISTINCT CASE WHEN cp.status<>'withdrawn' THEN cp.user_id END) current_candidates,
 COUNT(DISTINCT CASE WHEN cp.status='completed' THEN cp.user_id END) completed_candidates,
 COUNT(DISTINCT CASE WHEN cp.status='withdrawn' THEN cp.user_id END) withdrawn_candidates
 FROM cohorts c LEFT JOIN cohort_participants cp ON cp.cohort_id=c.id WHERE c.supervisor_id=?", 'i', [$supervisorId]);
$stats = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$assigned=(int)($stats['assigned_cohorts']??0);$active=(int)($stats['active_cohorts']??0);$current=(int)($stats['current_candidates']??0);$completed=(int)($stats['completed_candidates']??0);$withdrawn=(int)($stats['withdrawn_candidates']??0);$rate=sv_completion_rate($current,$completed);

$stmt = Database::prepare("SELECT c.id,c.name cohort_name,c.status cohort_status,c.start_date,c.end_date,p.name programme_name,
 COUNT(DISTINCT CASE WHEN cp.status<>'withdrawn' THEN cp.user_id END) current_candidates,
 COUNT(DISTINCT CASE WHEN cp.status='completed' THEN cp.user_id END) completed_candidates
 FROM cohorts c JOIN programmes p ON p.id=c.programme_id LEFT JOIN cohort_participants cp ON cp.cohort_id=c.id
 WHERE c.supervisor_id=? GROUP BY c.id,c.name,c.status,c.start_date,c.end_date,p.name
 ORDER BY CASE WHEN c.status='active' THEN 0 ELSE 1 END,c.start_date DESC LIMIT 5", 'i', [$supervisorId]);
$cohorts=[];$r=$stmt->get_result();while($row=$r->fetch_assoc()){$row['rate']=sv_completion_rate((int)$row['current_candidates'],(int)$row['completed_candidates']);$cohorts[]=$row;}$stmt->close();

$stmt = Database::prepare("SELECT u.id candidate_id,u.first_name,u.last_name,u.email,cp.status participant_status,c.id cohort_id,c.name cohort_name,p.name programme_name
 FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id JOIN programmes p ON p.id=c.programme_id JOIN users u ON u.id=cp.user_id
 WHERE c.supervisor_id=? ORDER BY COALESCE(cp.completed_at,cp.onboarded_at,cp.selected_at) DESC,cp.id DESC LIMIT 6", 'i', [$supervisorId]);
$candidates=[];$r=$stmt->get_result();while($row=$r->fetch_assoc())$candidates[]=$row;$stmt->close();
require __DIR__ . '/_layout_start.php';
?>
<section class="sv-hero">
  <div class="sv-hero__content"><span class="sv-hero__eyebrow"><i class="fas fa-sparkles"></i> Supervisor Workspace</span><h2>Welcome back, <?= e($firstName) ?>.</h2><p>Track your assigned cohorts, monitor candidate progress and keep programme delivery moving with a clear operational view.</p></div>
  <div class="sv-hero__metric"><strong><?= $rate ?>%</strong><span>Overall completion</span></div>
</section>
<section class="sv-stats sv-stats--5">
  <article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--blue"><i class="fas fa-layer-group"></i></div><strong><?= number_format($assigned) ?></strong><span>Assigned Cohorts</span></article>
  <article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--purple"><i class="fas fa-bolt"></i></div><strong><?= number_format($active) ?></strong><span>Active Cohorts</span></article>
  <article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--orange"><i class="fas fa-users"></i></div><strong><?= number_format($current) ?></strong><span>Current Candidates</span></article>
  <article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--green"><i class="fas fa-circle-check"></i></div><strong><?= number_format($completed) ?></strong><span>Completed</span></article>
  <article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--red"><i class="fas fa-user-minus"></i></div><strong><?= number_format($withdrawn) ?></strong><span>Withdrawn</span></article>
</section>
<div class="sv-grid sv-grid--main">
  <section class="sv-card">
    <div class="sv-card__header"><div><h3>My Cohorts</h3><p>Your most relevant assigned cohorts.</p></div><a class="sv-card__link" href="<?= url('supervisor/cohorts.php') ?>">View all <i class="fas fa-arrow-right"></i></a></div>
    <?php if(!$cohorts): ?><div class="sv-empty"><i class="fas fa-layer-group"></i><strong>No cohorts assigned</strong><span>Your assigned cohorts will appear here.</span></div><?php else: ?>
    <div class="sv-table-wrap"><table class="sv-table"><thead><tr><th>Cohort</th><th>Status</th><th>Candidates</th><th>Progress</th><th></th></tr></thead><tbody>
    <?php foreach($cohorts as $c): ?><tr><td><div class="sv-person"><div class="sv-avatar"><i class="fas fa-layer-group"></i></div><div class="sv-person__copy"><strong><?= e($c['cohort_name']) ?></strong><span><?= e($c['programme_name']) ?></span></div></div></td><td><span class="sv-status sv-status--<?= e(sv_status_class($c['cohort_status'])) ?>"><?= e(sv_status_label($c['cohort_status'])) ?></span></td><td><?= number_format((int)$c['current_candidates']) ?></td><td style="min-width:140px"><div class="sv-progress"><div class="sv-progress__meta"><span>Completed</span><strong><?= (int)$c['rate'] ?>%</strong></div><div class="sv-progress__track"><div class="sv-progress__bar" style="width:<?= (int)$c['rate'] ?>%"></div></div></div></td><td><a class="sv-icon-btn" href="<?= url('supervisor/cohort_view.php?id='.(int)$c['id']) ?>"><i class="fas fa-arrow-right"></i></a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <section class="sv-card">
    <div class="sv-card__header"><div><h3>Quick Actions</h3><p>Common Supervisor tasks.</p></div></div>
    <div class="sv-card__body" style="display:grid;gap:10px">
      <a class="sv-btn sv-btn--secondary" href="<?= url('supervisor/cohorts.php') ?>"><i class="fas fa-layer-group"></i> Review My Cohorts</a>
      <a class="sv-btn sv-btn--secondary" href="<?= url('supervisor/candidates.php') ?>"><i class="fas fa-users"></i> Review Candidates</a>
      <a class="sv-btn sv-btn--secondary" href="<?= url('supervisor/reports.php') ?>"><i class="fas fa-chart-column"></i> View Progress Report</a>
      <a class="sv-btn sv-btn--secondary" href="<?= url('supervisor/activity_log.php') ?>"><i class="fas fa-clock-rotate-left"></i> Activity Log</a>
    </div>
  </section>
</div>
<section class="sv-card" style="margin-top:18px">
  <div class="sv-card__header"><div><h3>Recent Candidates</h3><p>Recent participants across your assigned cohorts.</p></div><a class="sv-card__link" href="<?= url('supervisor/candidates.php') ?>">View candidates <i class="fas fa-arrow-right"></i></a></div>
  <?php if(!$candidates): ?><div class="sv-empty"><i class="fas fa-users"></i><strong>No candidates found</strong><span>Candidate activity will appear here.</span></div><?php else: ?>
  <div class="sv-table-wrap"><table class="sv-table"><thead><tr><th>Candidate</th><th>Programme</th><th>Cohort</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($candidates as $c): $fn=trim((string)$c['first_name']);$ln=trim((string)$c['last_name']); ?><tr><td><div class="sv-person"><div class="sv-avatar"><?= e(sv_initials($fn,$ln)) ?></div><div class="sv-person__copy"><strong><?= e(trim($fn.' '.$ln) ?: 'Candidate') ?></strong><span><?= e((string)$c['email']) ?></span></div></div></td><td><?= e($c['programme_name']) ?></td><td><?= e($c['cohort_name']) ?></td><td><span class="sv-status sv-status--<?= e(sv_status_class($c['participant_status'])) ?>"><?= e(sv_status_label($c['participant_status'])) ?></span></td><td><a class="sv-icon-btn" href="<?= url('supervisor/candidate_view.php?id='.(int)$c['candidate_id'].'&cohort_id='.(int)$c['cohort_id']) ?>"><i class="fas fa-eye"></i></a></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<div class="sv-security"><i class="fas fa-shield-halved"></i><div><strong>Supervisor-scoped access</strong><p>All dashboard data is restricted to cohorts assigned to your Supervisor account and their participants.</p></div></div>
<?php require __DIR__ . '/_layout_end.php'; ?>
