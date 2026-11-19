<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/scheduling_workload.php';

$user        = current_user();
$currentPage = 'workload';
$pageTitle   = 'Workload Monitor';
$conn        = Database::getConnection();
$userId      = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($userId <= 0) { http_response_code(403); exit('Invalid account.'); }

$isManager = sw_is_manager($user);
$scope     = sw_scope($user);

sw_bootstrap($conn);

/* ---------- Quick allocation ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_allocate') {
    try {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $assessorId = (int)($_POST['assessor_id']  ?? 0);

        sw_assert_can_manage_schedule($conn, $user, $scheduleId);
        if (!$isManager && $assessorId !== $userId) {
            throw new RuntimeException('You can only allocate yourself.');
        }
        sw_allocate_assessor($conn, $scheduleId, $assessorId);
        $_SESSION['sw_notice'] = ['type' => 'success', 'text' => 'Assessor allocated.'];
    } catch (Throwable $ex) {
        $_SESSION['sw_notice'] = ['type' => 'error', 'text' => $ex->getMessage()];
    }
    sw_redirect(url('assessor/workload.php'));
}

$notice = $_SESSION['sw_notice'] ?? null;
unset($_SESSION['sw_notice']);

$stats = sw_stats($conn);

/* ★ Scoped: managers see all; regular assessors see only themselves. */
$assessors = $isManager
    ? sw_assessor_workload($conn)
    : array_values(array_filter(sw_assessor_workload($conn), fn($a) => (int)$a['id'] === $userId));

$byProg    = sw_workload_by_programme($conn, $scope);
$byCohort  = sw_workload_by_cohort($conn, $scope);

/* Unallocated queue is manager-only — regular assessors see their own unscheduled attempts
   via the main scheduling page. */
$unallocated = $isManager
    ? sw_fetch_schedules($conn, ['unallocated' => true], 50)
    : [];

/* Overdue — scoped */
$overdueAll = sw_fetch_schedules($conn, ['assessor_scope' => $scope], 200);
$overdue    = array_values(array_filter($overdueAll,
    fn($r) => strtotime($r['scheduled_at']) < time()
           && in_array($r['status'], ['scheduled','confirmed'], true)));

$assessorIndex = [];
foreach ($assessors as $a) $assessorIndex[(int)$a['id']] = $a;

require __DIR__ . '/_layout_start.php';
?>
<style>
/* Reuse the workload page styling from the previous message — unchanged.
   Copy the <style> block from admin/workload.php verbatim. */
</style>

<?php if ($notice): ?>
  <div class="as-notice as-notice--<?= $notice['type'] === 'error' ? 'error' : 'success' ?>">
    <i class="fas <?= $notice['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
    <span><?= e($notice['text']) ?></span>
  </div>
<?php endif; ?>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-chart-simple"></i> <?= $isManager ? 'Workload Monitoring' : 'My Workload' ?></span>
    <h2><?= $isManager ? 'Balance assessor capacity across programmes and cohorts' : 'Your assessment workload at a glance' ?></h2>
    <p>
      <?= $isManager
          ? 'See how assessment workloads are distributed, spot overloaded assessors, track programme and cohort pipelines, and clear the unallocated queue from one place.'
          : 'Your active load, upcoming commitments, overdue items and the coverage of your assigned programmes and cohorts.' ?>
    </p>
    <div class="as-hero__actions">
      <a class="as-btn as-btn--light" href="<?= url('assessor/scheduling_workload.php') ?>"><i class="fas fa-calendar-plus"></i> Scheduling workspace</a>
    </div>
  </div>
</section>

<section class="as-stat-grid">
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--blue"><i class="fas fa-list-check"></i></span><strong><?= number_format($stats['active']) ?></strong><span>Active schedules</span></div>
  <?php if ($isManager): ?>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--orange"><i class="fas fa-user-slash"></i></span><strong><?= number_format($stats['unallocated']) ?></strong><span>Unallocated</span></div>
  <?php endif; ?>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--red"><i class="fas fa-triangle-exclamation"></i></span><strong><?= number_format($stats['overdue']) ?></strong><span>Overdue</span></div>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--green"><i class="fas fa-circle-check"></i></span><strong><?= number_format($stats['completed_30d']) ?></strong><span>Completed (30 days)</span></div>
</section>

<!-- Assessor workload -->
<section class="as-card">
  <div class="as-card__header">
    <div>
      <h3><?= $isManager ? 'Assessor workload' : 'My workload' ?></h3>
      <p><?= $isManager
          ? 'Sorted by active load. Capacity default is ' . SW_DEFAULT_CAPACITY . ' active assessments per assessor.'
          : 'Your active load against the default capacity of ' . SW_DEFAULT_CAPACITY . '.' ?></p>
    </div>
  </div>
  <?php if (!$assessors): ?>
    <div class="as-empty"><i class="fas fa-user-tie"></i>No workload data available.</div>
  <?php else: ?>
    <div class="as-wl-grid">
      <?php foreach ($assessors as $a):
        $first = trim((string)$a['first_name']); $last = trim((string)$a['last_name']);
        $name  = trim($first . ' ' . $last) ?: 'Assessor';
        $init  = strtoupper(mb_substr($first,0,1) . mb_substr($last,0,1));
        $stateClass = 'as-wl__state--' . $a['state'];
        $stateLabel = $a['state'] === 'full' ? 'At capacity' : ($a['state'] === 'busy' ? 'Busy' : 'Available');
        $fillClass  = $a['state'] === 'full' ? 'as-wl__bar-fill--full' : ($a['state'] === 'busy' ? 'as-wl__bar-fill--busy' : '');
      ?>
        <div class="as-wl">
          <div class="as-wl__head">
            <div class="as-wl__avatar"><?= e($init) ?></div>
            <div class="as-wl__id">
              <strong><?= e($name) ?></strong>
              <span><?= e($a['email'] ?? '') ?></span>
            </div>
            <span class="as-wl__state <?= $stateClass ?>"><?= e($stateLabel) ?></span>
          </div>
          <div>
            <div class="as-wl__bar"><div class="as-wl__bar-fill <?= $fillClass ?>" style="width:<?= (int)$a['capacity_pct'] ?>%"></div></div>
            <div style="display:flex;justify-content:space-between;font-size:11.5px;opacity:.7;margin-top:5px">
              <span><?= (int)$a['active'] ?> / <?= SW_DEFAULT_CAPACITY ?> active</span>
              <span><?= (int)$a['capacity_pct'] ?>%</span>
            </div>
          </div>
          <div class="as-wl__numbers">
            <div class="as-wl__num"><strong><?= (int)$a['active'] ?></strong><span>Active</span></div>
            <div class="as-wl__num as-wl__num--warn"><strong><?= (int)$a['upcoming_7d'] ?></strong><span>Next 7d</span></div>
            <div class="as-wl__num <?= (int)$a['overdue'] > 0 ? 'as-wl__num--danger' : '' ?>"><strong><?= (int)$a['overdue'] ?></strong><span>Overdue</span></div>
            <div class="as-wl__num as-wl__num--success"><strong><?= (int)$a['completed_30d'] ?></strong><span>Done 30d</span></div>
          </div>
          <div style="display:flex;gap:6px">
            <a class="as-btn as-btn--glass as-btn--sm" href="<?= url('assessor/scheduling_workload.php?assessor_id=' . (int)$a['id']) ?>"><i class="fas fa-calendar"></i> Their schedules</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($isManager && $unallocated): ?>
<!-- Unallocated queue — managers only -->
<section class="as-card">
  <div class="as-card__header">
    <div><h3>Unallocated queue</h3><p>Schedules awaiting an assessor. Quick-allocate below.</p></div>
    <span class="as-pill as-pill--warn"><?= count($unallocated) ?> pending</span>
  </div>
  <?php foreach ($unallocated as $u):
    $first = trim((string)$u['first_name']); $last = trim((string)$u['last_name']);
    $name  = trim($first . ' ' . $last) ?: 'Participant';
    $init  = strtoupper(mb_substr($first,0,1) . mb_substr($last,0,1));
    $dt    = strtotime($u['scheduled_at']);
    $qualified = sw_fetch_qualified_assessors($conn, (int)$u['outcome_id']);
  ?>
    <div class="as-row">
      <div class="as-row__avatar"><?= e($init) ?></div>
      <div class="as-row__body">
        <div class="as-row__top">
          <strong><?= e($name) ?></strong>
          <span class="as-pill as-pill--warn">Unallocated</span>
          <?php if (!empty($u['programme_code'])): ?><span class="as-pill as-pill--muted"><?= e($u['programme_code']) ?></span><?php endif; ?>
        </div>
        <div class="as-row__meta">
          <span><i class="fas fa-calendar"></i> <?= e(date('D d M Y, H:i', $dt)) ?></span>
          <span><i class="fas fa-book-open"></i> <?= e($u['outcome_code']) ?> · <?= e($u['outcome_title']) ?></span>
        </div>
      </div>
      <form method="post" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="action" value="quick_allocate">
        <input type="hidden" name="schedule_id" value="<?= (int)$u['id'] ?>">
        <select name="assessor_id" required style="padding:7px 9px;border-radius:8px;font-size:12px;border:1px solid rgba(127,127,127,.25);background:rgba(127,127,127,.04);color:inherit">
          <option value="">— Choose assessor —</option>
          <?php foreach ($qualified as $qa):
            $qaId = (int)$qa['id']; $qaRow = $assessorIndex[$qaId] ?? null;
            $lbl = trim($qa['first_name'] . ' ' . $qa['last_name']);
            if ($qaRow) $lbl .= ' (' . $qaRow['active'] . '/' . SW_DEFAULT_CAPACITY . ' · ' . $qaRow['state'] . ')';
          ?>
            <option value="<?= $qaId ?>"><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="as-btn as-btn--primary as-btn--sm" type="submit"><i class="fas fa-user-plus"></i> Assign</button>
      </form>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($overdue): ?>
<!-- Overdue — scoped -->
<section class="as-card">
  <div class="as-card__header">
    <div><h3>Overdue schedules</h3><p>Scheduled in the past, not yet completed.</p></div>
    <span class="as-pill as-pill--danger"><?= count($overdue) ?></span>
  </div>
  <?php foreach ($overdue as $o):
    $first = trim((string)$o['first_name']); $last = trim((string)$o['last_name']);
    $name  = trim($first . ' ' . $last) ?: 'Participant';
    $dt    = strtotime($o['scheduled_at']);
    $assessorName = trim((string)($o['assessor_first'] ?? '') . ' ' . (string)($o['assessor_last'] ?? '')) ?: 'Unallocated';
    $days = max(1, (int)floor((time() - $dt) / 86400));
  ?>
    <div class="as-row">
      <div class="as-row__avatar" style="background:rgba(239,68,68,.14);color:#dc2626"><i class="fas fa-exclamation"></i></div>
      <div class="as-row__body">
        <div class="as-row__top">
          <strong><?= e($name) ?></strong>
          <span class="as-pill as-pill--danger"><?= $days ?>d overdue</span>
          <span class="as-pill as-pill--muted"><?= e($o['outcome_code']) ?></span>
        </div>
        <div class="as-row__meta">
          <span><i class="fas fa-calendar"></i> <?= e(date('D d M Y, H:i', $dt)) ?></span>
          <span><i class="fas fa-user-tie"></i> <?= e($assessorName) ?></span>
        </div>
      </div>
      <a class="as-btn as-btn--glass as-btn--sm" href="<?= url('assessor/scheduling_workload.php') ?>"><i class="fas fa-arrow-right"></i> Open</a>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- Programme breakdown -->
<section class="as-card">
  <div class="as-card__header">
    <div><h3><?= $isManager ? 'Workload by programme' : 'My workload by programme' ?></h3>
      <p><?= sw_has_programme($conn) ? 'Attempts grouped by programme, with scheduling and allocation coverage.' : 'Programme metadata unavailable — grouped by assessment outcome.' ?></p>
    </div>
  </div>
  <?php if (!$byProg): ?>
    <div class="as-empty"><i class="fas fa-layer-group"></i>No programme-level assessment activity yet.</div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="as-table">
        <thead>
          <tr>
            <th><?= sw_has_programme($conn) ? 'Programme' : 'Outcome' ?></th>
            <th class="num">Attempts</th>
            <th class="num">Scheduled</th>
            <th class="num">Allocated</th>
            <?php if ($isManager): ?><th class="num">Unallocated</th><?php endif; ?>
            <th class="num">Completed</th>
            <th>Coverage</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byProg as $p):
            $total = (int)$p['total_attempts']; $alloc = (int)$p['allocated'];
            $coverage = $total > 0 ? (int)round($alloc / $total * 100) : 0;
            $covClass = $coverage >= 80 ? 'success' : ($coverage >= 50 ? 'warn' : 'danger');
          ?>
            <tr>
              <td><strong><?= e($p['code']) ?></strong><br><span style="font-size:11.5px;opacity:.65"><?= e($p['name']) ?></span></td>
              <td class="num"><?= $total ?></td>
              <td class="num"><?= (int)$p['scheduled'] ?></td>
              <td class="num"><?= $alloc ?></td>
              <?php if ($isManager): ?>
                <td class="num" style="<?= (int)$p['unallocated'] > 0 ? 'color:#dc2626;font-weight:700' : '' ?>"><?= (int)$p['unallocated'] ?></td>
              <?php endif; ?>
              <td class="num"><?= (int)$p['completed'] ?></td>
              <td>
                <div class="as-bar-cell">
                  <div class="as-wl__bar"><div class="as-wl__bar-fill <?= $covClass === 'danger' ? 'as-wl__bar-fill--full' : ($covClass === 'warn' ? 'as-wl__bar-fill--busy' : '') ?>" style="width:<?= $coverage ?>%"></div></div>
                  <span><?= $coverage ?>%</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($byCohort): ?>
<!-- Cohort breakdown -->
<section class="as-card">
  <div class="as-card__header">
    <div><h3><?= $isManager ? 'Workload by cohort' : 'My workload by cohort' ?></h3><p>Top 50 cohorts by assessment volume.</p></div>
  </div>
  <div style="overflow-x:auto">
    <table class="as-table">
      <thead>
        <tr>
          <th>Cohort</th>
          <th class="num">Attempts</th>
          <th class="num">Scheduled</th>
          <?php if ($isManager): ?><th class="num">Unallocated</th><?php endif; ?>
          <th class="num">Completed</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($byCohort as $c): ?>
          <tr>
            <td><strong><?= e($c['code']) ?></strong><br><span style="font-size:11.5px;opacity:.65"><?= e($c['name']) ?></span></td>
            <td class="num"><?= (int)$c['total_attempts'] ?></td>
            <td class="num"><?= (int)$c['scheduled'] ?></td>
            <?php if ($isManager): ?>
              <td class="num" style="<?= (int)$c['unallocated'] > 0 ? 'color:#dc2626;font-weight:700' : '' ?>"><?= (int)$c['unallocated'] ?></td>
            <?php endif; ?>
            <td class="num"><?= (int)$c['completed'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>