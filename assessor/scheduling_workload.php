<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/scheduling_workload.php';

$user        = current_user();
$currentPage = 'scheduling_team';      // ← distinguishes from personal assessor/scheduling.php
$pageTitle   = 'Scheduling & Workload';
$conn        = Database::getConnection();
$userId      = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($userId <= 0) { http_response_code(403); exit('Invalid account.'); }

$isManager = sw_is_manager($user);
$scope     = sw_scope($user);

sw_bootstrap($conn);

/* ---------- POST handlers ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'schedule':
                sw_assert_can_manage_attempt($conn, $user, (int)($_POST['attempt_id'] ?? 0));
                if (!$isManager) $_POST['assessor_id'] = $userId;
                sw_schedule_assessment($conn, $_POST, $userId);
                $_SESSION['sw_notice'] = ['type' => 'success', 'text' => 'Assessment scheduled.'];
                break;

            case 'update_schedule':
                sw_assert_can_manage_schedule($conn, $user, (int)($_POST['schedule_id'] ?? 0));
                if (!$isManager) $_POST['assessor_id'] = $userId;
                sw_update_schedule($conn, (int)($_POST['schedule_id'] ?? 0), $_POST);
                $_SESSION['sw_notice'] = ['type' => 'success', 'text' => 'Schedule updated.'];
                break;

            case 'allocate':
                $scheduleId = (int)($_POST['schedule_id'] ?? 0);
                $assessorId = (int)($_POST['assessor_id'] ?? 0);
                sw_assert_can_manage_schedule($conn, $user, $scheduleId);
                if (!$isManager && $assessorId !== $userId) {
                    throw new RuntimeException('You can only allocate schedules to yourself.');
                }
                sw_allocate_assessor($conn, $scheduleId, $assessorId);
                $_SESSION['sw_notice'] = ['type' => 'success', 'text' => 'Assessor allocated.'];
                break;

            case 'complete':
                sw_assert_can_manage_schedule($conn, $user, (int)($_POST['schedule_id'] ?? 0));
                sw_change_schedule_status($conn, (int)$_POST['schedule_id'], 'completed');
                $_SESSION['sw_notice'] = ['type' => 'success', 'text' => 'Marked completed.'];
                break;

            case 'cancel':
                sw_assert_can_manage_schedule($conn, $user, (int)($_POST['schedule_id'] ?? 0));
                sw_change_schedule_status($conn, (int)$_POST['schedule_id'], 'cancelled');
                $_SESSION['sw_notice'] = ['type' => 'warning', 'text' => 'Schedule cancelled.'];
                break;

            case 'no_show':
                sw_assert_can_manage_schedule($conn, $user, (int)($_POST['schedule_id'] ?? 0));
                sw_change_schedule_status($conn, (int)$_POST['schedule_id'], 'no_show');
                $_SESSION['sw_notice'] = ['type' => 'warning', 'text' => 'Marked no-show.'];
                break;

            case 'create_slot':
                sw_create_slot($conn, $_POST, $userId);
                $_SESSION['sw_notice'] = ['type' => 'success', 'text' => 'Availability slot created.'];
                break;

            case 'close_slot':
                sw_assert_can_manage_slot($conn, $user, (int)($_POST['slot_id'] ?? 0));
                sw_close_slot($conn, (int)$_POST['slot_id'], 'closed');
                $_SESSION['sw_notice'] = ['type' => 'success', 'text' => 'Slot closed.'];
                break;

            case 'cancel_slot':
                sw_assert_can_manage_slot($conn, $user, (int)($_POST['slot_id'] ?? 0));
                sw_close_slot($conn, (int)$_POST['slot_id'], 'cancelled');
                $_SESSION['sw_notice'] = ['type' => 'warning', 'text' => 'Slot cancelled.'];
                break;
        }
    } catch (Throwable $ex) {
        $_SESSION['sw_notice'] = ['type' => 'error', 'text' => $ex->getMessage()];
    }
    sw_redirect(url('assessor/scheduling_workload.php'));
}

$notice  = $_SESSION['sw_notice'] ?? null;
unset($_SESSION['sw_notice']);
$flashes = render_flashes();

/* ---------- Filters ---------- */
$f = [
    'q'            => trim((string)($_GET['q'] ?? '')),
    'programme_id' => (int)($_GET['programme_id'] ?? 0),
    'cohort_id'    => (int)($_GET['cohort_id'] ?? 0),
    'assessor_id'  => $isManager ? (int)($_GET['assessor_id'] ?? 0) : $userId,
];

$stats         = sw_stats($conn);
$programmes    = sw_programmes($conn);
$cohorts       = sw_cohorts($conn);
$assessors     = $isManager ? sw_assessor_workload($conn) : [];
$pending       = sw_fetch_pending_attempts($conn, $f + ['assessor_scope' => $scope]);
$schedules     = sw_fetch_schedules($conn,       $f + ['assessor_scope' => $scope]);
$slots         = sw_fetch_slots($conn, ['from_date' => date('Y-m-d')]);

$assessorIndex = [];
foreach ($assessors as $a) $assessorIndex[(int)$a['id']] = $a;

require __DIR__ . '/_layout_start.php';
?>
<style>
.as-hero{position:relative;overflow:hidden;border-radius:26px;padding:34px;background:linear-gradient(135deg,#1e1b4b 0%,#312e81 55%,#4f46e5 100%);color:#fff;margin-bottom:22px}
.as-hero__content{max-width:680px;position:relative;z-index:2}
.as-hero__eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.16);font-size:12px;font-weight:600;letter-spacing:.03em;text-transform:uppercase}
.as-hero h2{margin:14px 0 10px;font-size:29px;line-height:1.25}
.as-hero p{margin:0 0 22px;opacity:.88;font-size:14.5px;line-height:1.6;max-width:600px}
.as-hero__actions{display:flex;gap:10px;flex-wrap:wrap}

.as-stat-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:22px}
.as-stat{display:flex;flex-direction:column;gap:5px;padding:16px;border-radius:16px;background:rgba(127,127,127,.06);border:1px solid rgba(127,127,127,.1);transition:transform .18s ease,box-shadow .18s ease}
.as-stat:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,.07)}
.as-stat__icon{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;font-size:14px;margin-bottom:2px}
.as-stat__icon--blue{background:rgba(59,130,246,.14);color:#2563eb}
.as-stat__icon--orange{background:rgba(249,115,22,.14);color:#ea580c}
.as-stat__icon--green{background:rgba(16,185,129,.14);color:#059669}
.as-stat__icon--red{background:rgba(239,68,68,.14);color:#dc2626}
.as-stat__icon--purple{background:rgba(139,92,246,.14);color:#7c3aed}
.as-stat__icon--sky{background:rgba(14,165,233,.14);color:#0284c7}
.as-stat strong{font-size:22px;line-height:1.1}
.as-stat span{font-size:12px;opacity:.7}

.as-card{background:rgba(127,127,127,.04);border:1px solid rgba(127,127,127,.1);border-radius:20px;padding:20px;margin-bottom:18px}
.as-card__header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:14px;flex-wrap:wrap}
.as-card__header h3{margin:0;font-size:16.5px}
.as-card__header p{margin:3px 0 0;font-size:12.5px;opacity:.65}

.as-filter{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.as-filter input[type="search"],.as-filter select{padding:9px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);background:rgba(127,127,127,.04);color:inherit;font:inherit;font-size:13px}
.as-filter input[type="search"]{flex:1;min-width:220px}

.as-row{display:flex;align-items:center;gap:12px;padding:12px;border-radius:14px;border:1px solid rgba(127,127,127,.12);background:rgba(127,127,127,.02);margin-bottom:8px}
.as-row:last-child{margin-bottom:0}
.as-row__avatar{width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;border-radius:50%;background:rgba(79,70,229,.12);color:#4338ca;font-weight:800;font-size:12.5px}
.as-row__body{flex:1;min-width:0}
.as-row__top{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.as-row__top strong{font-size:14px}
.as-row__meta{display:flex;flex-wrap:wrap;gap:10px;font-size:11.5px;opacity:.65;margin-top:4px}
.as-row__actions{display:flex;gap:6px;flex-wrap:wrap}

.as-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(127,127,127,.12);color:inherit}
.as-pill--success{background:rgba(16,185,129,.14);color:#059669}
.as-pill--warn{background:rgba(245,158,11,.16);color:#b45309}
.as-pill--danger{background:rgba(239,68,68,.14);color:#dc2626}
.as-pill--info{background:rgba(59,130,246,.14);color:#2563eb}
.as-pill--muted{background:rgba(127,127,127,.12);color:#64748b}
.as-pill--purple{background:rgba(139,92,246,.14);color:#7c3aed}

.as-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 13px;border-radius:10px;border:0;font-weight:600;font-size:12.5px;text-decoration:none;cursor:pointer;line-height:1.1;transition:transform .15s ease}
.as-btn:hover{transform:translateY(-1px)}
.as-btn--primary{background:#4f46e5;color:#fff}
.as-btn--light{background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.28)}
.as-btn--glass{background:rgba(127,127,127,.1);color:inherit}
.as-btn--success{background:rgba(16,185,129,.14);color:#059669}
.as-btn--danger{background:rgba(239,68,68,.12);color:#dc2626}
.as-btn--sm{padding:6px 10px;font-size:11.5px}
.as-btn:disabled{opacity:.55;cursor:not-allowed}

.as-empty{padding:26px 18px;text-align:center;opacity:.7;font-size:13px}
.as-empty i{display:block;font-size:22px;margin-bottom:6px;opacity:.4}

.as-notice{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:10px;border:1px solid transparent}
.as-notice--success{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#059669}
.as-notice--error{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#dc2626}
.as-notice--warning{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:#b45309}

.as-tabs{display:flex;gap:4px;margin-bottom:14px;border-bottom:1px solid rgba(127,127,127,.14);padding-bottom:0}
.as-tab{padding:9px 14px;border:0;background:transparent;color:inherit;font-weight:600;font-size:13px;cursor:pointer;border-bottom:2px solid transparent;opacity:.65}
.as-tab.is-active{opacity:1;border-bottom-color:#4f46e5;color:#4f46e5}

.as-slot{display:flex;align-items:center;gap:12px;padding:12px;border-radius:14px;border:1px solid rgba(127,127,127,.12);background:rgba(127,127,127,.02);margin-bottom:8px}
.as-slot__when{display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:60px;padding:7px 10px;border-radius:10px;background:rgba(79,70,229,.1);color:#4338ca;font-weight:700;line-height:1.1}
.as-slot__when span.day{font-size:18px}
.as-slot__when span.mon{font-size:10px;text-transform:uppercase;letter-spacing:.05em;opacity:.7}
.as-slot__body{flex:1;min-width:0}
.as-slot__top{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.as-slot__meta{display:flex;flex-wrap:wrap;gap:10px;font-size:11.5px;opacity:.65;margin-top:4px}
.as-capacity{display:flex;align-items:center;gap:8px;font-size:11.5px;opacity:.8}

.as-modal{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;padding:20px}
.as-modal.is-open{display:flex}
.as-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(4px)}
.as-modal__dialog{position:relative;z-index:1;width:min(680px,100%);max-height:90vh;display:flex;flex-direction:column;background:#fff;border-radius:22px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,.28)}
body.dark-mode .as-modal__dialog,html[data-theme="dark"] .as-modal__dialog{background:#111827;color:#f8fafc}
.as-modal__header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 22px 14px;border-bottom:1px solid rgba(127,127,127,.14)}
.as-modal__header h3{margin:0;font-size:17px}
.as-modal__header p{margin:4px 0 0;font-size:12.5px;opacity:.65}
.as-modal__close{width:36px;height:36px;display:grid;place-items:center;border:0;border-radius:50%;background:rgba(127,127,127,.1);color:inherit;cursor:pointer}
.as-modal__body{padding:18px 22px;overflow-y:auto;flex:1}
.as-modal__footer{display:flex;justify-content:flex-end;gap:10px;padding:14px 22px;border-top:1px solid rgba(127,127,127,.14)}
body.as-modal-open{overflow:hidden}

.as-field{display:flex;flex-direction:column;gap:5px;margin-bottom:12px}
.as-field label{font-size:12.5px;font-weight:600;opacity:.8}
.as-field input,.as-field select,.as-field textarea{width:100%;padding:9px 11px;border-radius:9px;border:1px solid rgba(127,127,127,.25);background:rgba(127,127,127,.04);color:inherit;font:inherit;font-size:13px}
.as-field textarea{min-height:64px;resize:vertical}
.as-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:10px}

@media(max-width:1000px){.as-stat-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){
  .as-hero{padding:24px}
  .as-stat-grid{grid-template-columns:1fr 1fr}
  .as-row{flex-direction:column;align-items:flex-start}
  .as-grid-2{grid-template-columns:1fr}
  .as-modal{align-items:flex-end;padding:0}
  .as-modal__dialog{width:100%;max-height:94vh;border-radius:22px 22px 0 0}
}
</style>

<?php if ($notice): ?>
  <div class="as-notice as-notice--<?= e($notice['type'] === 'warning' ? 'warning' : ($notice['type'] === 'error' ? 'error' : 'success')) ?>">
    <i class="fas <?= $notice['type'] === 'error' ? 'fa-circle-exclamation' : ($notice['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check') ?>"></i>
    <span><?= e($notice['text']) ?></span>
  </div>
<?php endif; ?>

<section class="as-hero">
  <div class="as-hero__content">
    <span class="as-hero__eyebrow"><i class="fas fa-calendar-plus"></i> <?= $isManager ? 'Scheduling & Workload' : 'My Scheduling' ?></span>
    <h2><?= $isManager ? 'Schedule assessments, allocate assessors, manage availability' : 'Manage your own assessment sessions' ?></h2>
    <p>
      <?= $isManager
          ? 'Coordinate candidate assessments across the team. Create availability slots, allocate qualified assessors with conflict detection, and progress each schedule to completion.'
          : 'Schedule your own assessments, manage your availability, and progress your sessions. Elevated assessors have visibility across the team.' ?>
    </p>
    <div class="as-hero__actions">
      <button type="button" class="as-btn as-btn--light" id="btnNewSchedule"><i class="fas fa-calendar-plus"></i> <?= $isManager ? 'Schedule assessment' : 'Schedule one of my assessments' ?></button>
      <button type="button" class="as-btn as-btn--glass" id="btnNewSlot"><i class="fas fa-clock"></i> Create availability slot</button>
      <?php if ($isManager): ?>
        <a class="as-btn as-btn--glass" href="<?= url('assessor/workload.php') ?>"><i class="fas fa-chart-simple"></i> Workload view</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="as-stat-grid">
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--blue"><i class="fas fa-calendar-check"></i></span><strong><?= number_format($stats['active']) ?></strong><span>Active schedules</span></div>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--sky"><i class="fas fa-calendar-day"></i></span><strong><?= number_format($stats['upcoming_7d']) ?></strong><span>Next 7 days</span></div>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--orange"><i class="fas fa-user-clock"></i></span><strong><?= number_format($stats['pending_sched']) ?></strong><span>Awaiting scheduling</span></div>
  <?php if ($isManager): ?>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--purple"><i class="fas fa-user-slash"></i></span><strong><?= number_format($stats['unallocated']) ?></strong><span>Unallocated</span></div>
  <?php endif; ?>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--red"><i class="fas fa-triangle-exclamation"></i></span><strong><?= number_format($stats['overdue']) ?></strong><span>Overdue</span></div>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--green"><i class="fas fa-circle-check"></i></span><strong><?= number_format($stats['completed_30d']) ?></strong><span>Completed (30d)</span></div>
</section>

<form method="get" class="as-filter">
  <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search candidate, outcome, email…">
  <?php if ($programmes): ?>
    <select name="programme_id">
      <option value="">All programmes</option>
      <?php foreach ($programmes as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $f['programme_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['code'] . ' · ' . $p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <?php if ($cohorts): ?>
    <select name="cohort_id">
      <option value="">All cohorts</option>
      <?php foreach ($cohorts as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $f['cohort_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <?php if ($isManager): ?>
    <select name="assessor_id">
      <option value="">All assessors</option>
      <?php foreach ($assessors as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= $f['assessor_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?> (<?= (int)$a['active'] ?>/<?= SW_DEFAULT_CAPACITY ?>)</option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <button class="as-btn as-btn--primary as-btn--sm"><i class="fas fa-filter"></i> Apply</button>
  <a class="as-btn as-btn--glass as-btn--sm" href="<?= url('assessor/scheduling_workload.php') ?>"><i class="fas fa-xmark"></i> Reset</a>
</form>

<div class="as-tabs">
  <button class="as-tab is-active" data-tab="awaiting">Awaiting scheduling <span class="as-pill as-pill--warn" style="margin-left:6px"><?= count($pending) ?></span></button>
  <button class="as-tab" data-tab="scheduled">Scheduled <span class="as-pill as-pill--info" style="margin-left:6px"><?= count($schedules) ?></span></button>
  <button class="as-tab" data-tab="slots">Availability slots <span class="as-pill as-pill--purple" style="margin-left:6px"><?= count($slots) ?></span></button>
</div>

<!-- Awaiting scheduling -->
<section class="as-card" data-panel="awaiting">
  <div class="as-card__header">
    <div>
      <h3><?= $isManager ? 'Awaiting scheduling' : 'My assessments awaiting scheduling' ?></h3>
      <p><?= $isManager ? 'Submitted assessment attempts that have no active schedule.' : 'Your assigned attempts that are not yet on the calendar.' ?></p>
    </div>
  </div>
  <?php if (!$pending): ?>
    <div class="as-empty"><i class="fas fa-thumbs-up"></i><?= $isManager ? 'Every submitted attempt already has an active schedule.' : 'You have no attempts waiting to be scheduled.' ?></div>
  <?php else: ?>
    <?php foreach ($pending as $p):
      $first = trim((string)$p['first_name']); $last = trim((string)$p['last_name']);
      $name  = trim($first . ' ' . $last) ?: 'Participant';
      $initials = strtoupper(mb_substr($first,0,1) . mb_substr($last,0,1));
      $qualified = $isManager ? sw_fetch_qualified_assessors($conn, (int)$p['outcome_id']) : [[
          'id' => $userId, 'first_name' => $user['first_name'] ?? '', 'last_name' => $user['last_name'] ?? ''
      ]];
    ?>
      <div class="as-row">
        <div class="as-row__avatar"><?= e($initials) ?></div>
        <div class="as-row__body">
          <div class="as-row__top">
            <strong><?= e($name) ?></strong>
            <span class="as-pill as-pill--info"><?= e(as_label($p['attempt_status']) ?: ucfirst($p['attempt_status'])) ?></span>
            <?php if (!empty($p['programme_code'])): ?><span class="as-pill as-pill--muted"><?= e($p['programme_code']) ?></span><?php endif; ?>
            <?php if (!empty($p['cohort_name'])): ?><span class="as-pill as-pill--muted"><?= e($p['cohort_name']) ?></span><?php endif; ?>
          </div>
          <div class="as-row__meta">
            <span><i class="fas fa-book-open"></i> <?= e($p['outcome_code']) ?> · <?= e($p['outcome_title']) ?></span>
            <span><i class="fas fa-rotate"></i> Attempt <?= (int)$p['attempt_number'] ?></span>
            <?php if (!empty($p['submitted_at'])): ?><span><i class="fas fa-clock"></i> Submitted <?= e(as_datetime($p['submitted_at'])) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="as-row__actions">
          <button type="button" class="as-btn as-btn--primary as-btn--sm"
            data-schedule-open
            data-attempt-id="<?= (int)$p['attempt_id'] ?>"
            data-label="<?= e($name . ' — ' . $p['outcome_code']) ?>"
            data-qualified="<?= e(implode(',', array_map(fn($a) => (int)$a['id'], $qualified))) ?>">
            <i class="fas fa-calendar-plus"></i> Schedule
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<!-- Scheduled -->
<section class="as-card" data-panel="scheduled" style="display:none">
  <div class="as-card__header">
    <div><h3><?= $isManager ? 'Active schedules' : 'My active schedules' ?></h3><p>Manage allocations, progress and cancellations.</p></div>
  </div>
  <?php if (!$schedules): ?>
    <div class="as-empty"><i class="fas fa-calendar"></i>No active schedules match these filters.</div>
  <?php else: ?>
    <?php foreach ($schedules as $s):
      $dt = strtotime($s['scheduled_at']);
      $first = trim((string)$s['first_name']); $last = trim((string)$s['last_name']);
      $name  = trim($first . ' ' . $last) ?: 'Participant';
      $initials = strtoupper(mb_substr($first,0,1) . mb_substr($last,0,1));
      $isOverdue = $dt < time();
      $assessorName = trim((string)($s['assessor_first'] ?? '') . ' ' . (string)($s['assessor_last'] ?? '')) ?: null;
      $pill = $isOverdue ? 'danger' : ($s['status'] === 'completed' ? 'success' : 'info');
      $qualified = $isManager ? sw_fetch_qualified_assessors($conn, (int)$s['outcome_id']) : [[
          'id' => $userId, 'first_name' => $user['first_name'] ?? '', 'last_name' => $user['last_name'] ?? ''
      ]];
    ?>
      <div class="as-row">
        <div class="as-row__avatar"><?= e($initials) ?></div>
        <div class="as-row__body">
          <div class="as-row__top">
            <strong><?= e($name) ?></strong>
            <span class="as-pill as-pill--<?= $pill ?>"><?= e(as_label($s['status']) ?: ucfirst($s['status'])) ?></span>
            <span class="as-pill as-pill--muted"><?= e(ucfirst($s['mode'])) ?></span>
            <span class="as-pill as-pill--muted"><?= (int)$s['duration_minutes'] ?> min</span>
            <?php if ($isOverdue && in_array($s['status'], ['scheduled','confirmed'], true)): ?>
              <span class="as-pill as-pill--danger">Overdue</span>
            <?php endif; ?>
          </div>
          <div class="as-row__meta">
            <span><i class="fas fa-calendar"></i> <?= e(date('D d M Y, H:i', $dt)) ?></span>
            <span><i class="fas fa-book-open"></i> <?= e($s['outcome_code']) ?> · <?= e($s['outcome_title']) ?></span>
            <?php if (!empty($s['programme_code'])): ?><span><i class="fas fa-layer-group"></i> <?= e($s['programme_code']) ?></span><?php endif; ?>
            <span><i class="fas fa-user-tie"></i> <?= $assessorName ? e($assessorName) : '<em>Unallocated</em>' ?></span>
          </div>
        </div>
        <div class="as-row__actions">
          <?php if ($s['status'] !== 'completed'): ?>
            <?php if ($isManager): ?>
              <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="hidden" name="action" value="allocate">
                <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                <select name="assessor_id" style="padding:6px 9px;border-radius:8px;font-size:12px;border:1px solid rgba(127,127,127,.25);background:rgba(127,127,127,.04);color:inherit">
                  <option value="">Allocate…</option>
                  <?php foreach ($qualified as $qa):
                    $qaId = (int)$qa['id']; $qaRow = $assessorIndex[$qaId] ?? null;
                    $label = trim($qa['first_name'] . ' ' . $qa['last_name']);
                    if ($qaRow) $label .= ' (' . $qaRow['active'] . '/' . SW_DEFAULT_CAPACITY . ')';
                  ?>
                    <option value="<?= $qaId ?>" <?= (int)$s['assessor_id'] === $qaId ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="as-btn as-btn--glass as-btn--sm" type="submit"><i class="fas fa-user-plus"></i></button>
              </form>
            <?php endif; ?>
            <button type="button" class="as-btn as-btn--glass as-btn--sm"
              data-schedule-edit
              data-id="<?= (int)$s['id'] ?>"
              data-label="<?= e($name . ' — ' . $s['outcome_code']) ?>"
              data-scheduled-at="<?= e(date('Y-m-d\TH:i', $dt)) ?>"
              data-duration="<?= (int)$s['duration_minutes'] ?>"
              data-mode="<?= e($s['mode']) ?>"
              data-location="<?= e((string)$s['location']) ?>"
              data-meeting-link="<?= e((string)$s['meeting_link']) ?>"
              data-notes="<?= e((string)$s['notes']) ?>">
              <i class="fas fa-pen"></i>
            </button>
            <form method="post" onsubmit="return confirm('Mark this assessment as completed?');" style="display:inline">
              <input type="hidden" name="action" value="complete">
              <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
              <button class="as-btn as-btn--success as-btn--sm" type="submit"><i class="fas fa-check"></i></button>
            </form>
            <form method="post" onsubmit="return confirm('Mark as no-show?');" style="display:inline">
              <input type="hidden" name="action" value="no_show">
              <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
              <button class="as-btn as-btn--glass as-btn--sm" type="submit"><i class="fas fa-user-slash"></i></button>
            </form>
            <form method="post" onsubmit="return confirm('Cancel this assessment?');" style="display:inline">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
              <button class="as-btn as-btn--danger as-btn--sm" type="submit"><i class="fas fa-xmark"></i></button>
            </form>
          <?php else: ?>
            <span class="as-pill as-pill--success"><i class="fas fa-circle-check"></i> Completed</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<!-- Availability slots -->
<section class="as-card" data-panel="slots" style="display:none">
  <div class="as-card__header">
    <div><h3>Availability slots</h3><p>Open time windows for scheduling.</p></div>
    <button type="button" class="as-btn as-btn--primary as-btn--sm" id="btnNewSlotInline"><i class="fas fa-plus"></i> New slot</button>
  </div>
  <?php if (!$slots): ?>
    <div class="as-empty"><i class="fas fa-clock"></i>No availability slots yet.</div>
  <?php else: ?>
    <?php foreach ($slots as $sl):
      $d = strtotime($sl['slot_date']);
      $used = (int)$sl['used_count']; $cap = (int)$sl['capacity'];
      $pct  = $cap > 0 ? min(100, (int)round($used / $cap * 100)) : 0;
      $pillClass = $sl['status'] === 'open' ? ($used >= $cap ? 'danger' : 'success') : 'muted';
    ?>
      <div class="as-slot">
        <div class="as-slot__when">
          <span class="day"><?= e(date('d', $d)) ?></span>
          <span class="mon"><?= e(date('M', $d)) ?></span>
        </div>
        <div class="as-slot__body">
          <div class="as-slot__top">
            <strong><?= e(substr($sl['start_time'],0,5) . ' – ' . substr($sl['end_time'],0,5)) ?></strong>
            <span class="as-pill as-pill--<?= $pillClass ?>"><?= e(ucfirst($sl['status'])) ?></span>
            <span class="as-pill as-pill--muted"><?= e(ucfirst($sl['mode'])) ?></span>
            <?php if (!empty($sl['programme_code'])): ?><span class="as-pill as-pill--purple"><?= e($sl['programme_code']) ?></span><?php endif; ?>
          </div>
          <div class="as-slot__meta">
            <span><i class="fas fa-users"></i> Capacity <?= $used ?> / <?= $cap ?></span>
            <?php if (!empty($sl['location'])): ?><span><i class="fas fa-location-dot"></i> <?= e($sl['location']) ?></span><?php endif; ?>
          </div>
          <div class="as-capacity" style="margin-top:6px">
            <div style="flex:1;height:6px;border-radius:999px;background:rgba(127,127,127,.15);overflow:hidden">
              <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#dc2626' : ($pct >= 75 ? '#d97706' : '#059669') ?>"></div>
            </div>
            <span><?= $pct ?>%</span>
          </div>
        </div>
        <div class="as-row__actions">
          <?php if ($sl['status'] === 'open'): ?>
            <form method="post" onsubmit="return confirm('Close this slot to new bookings?');" style="display:inline">
              <input type="hidden" name="action" value="close_slot">
              <input type="hidden" name="slot_id" value="<?= (int)$sl['id'] ?>">
              <button class="as-btn as-btn--glass as-btn--sm" type="submit"><i class="fas fa-lock"></i></button>
            </form>
            <form method="post" onsubmit="return confirm('Cancel this slot?');" style="display:inline">
              <input type="hidden" name="action" value="cancel_slot">
              <input type="hidden" name="slot_id" value="<?= (int)$sl['id'] ?>">
              <button class="as-btn as-btn--danger as-btn--sm" type="submit"><i class="fas fa-xmark"></i></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<!-- ---------- Schedule modal ---------- -->
<div class="as-modal" id="scheduleModal" aria-hidden="true">
  <div class="as-modal__backdrop" data-close></div>
  <section class="as-modal__dialog" role="dialog" aria-modal="true">
    <header class="as-modal__header">
      <div><h3 id="scheduleTitle">Schedule assessment</h3><p id="scheduleSubtitle">Pick a time.</p></div>
      <button type="button" class="as-modal__close" data-close><i class="fas fa-xmark"></i></button>
    </header>
    <form method="post" id="scheduleForm">
      <input type="hidden" name="action" id="scheduleAction" value="schedule">
      <input type="hidden" name="schedule_id" id="scheduleId" value="">
      <input type="hidden" name="attempt_id" id="scheduleAttemptId" value="">
      <div class="as-modal__body">
        <div class="as-field"><label>Assessment</label><input type="text" id="scheduleLabel" readonly></div>
        <div class="as-grid-2">
          <div class="as-field"><label>Date &amp; time</label><input type="datetime-local" name="scheduled_at" id="scheduleAt" required></div>
          <div class="as-field"><label>Duration (min)</label><input type="number" name="duration_minutes" id="scheduleDuration" min="15" max="480" step="15" value="60" required></div>
        </div>
        <div class="as-grid-2">
          <div class="as-field"><label>Mode</label>
            <select name="mode" id="scheduleMode"><option value="in_person">In person</option><option value="online">Online</option><option value="phone">Phone</option></select>
          </div>
          <div class="as-field"><label>Assessor</label>
            <select name="assessor_id" id="scheduleAssessor">
              <?php if (!$isManager): ?>
                <option value="<?= $userId ?>" selected><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?> (you)</option>
              <?php else: ?>
                <option value="">— Unallocated —</option>
              <?php endif; ?>
            </select>
          </div>
        </div>
        <div class="as-field"><label>Location</label><input type="text" name="location" id="scheduleLocation"></div>
        <div class="as-field"><label>Meeting link</label><input type="url" name="meeting_link" id="scheduleLink"></div>
        <div class="as-field"><label>Notes</label><textarea name="notes" id="scheduleNotes"></textarea></div>
      </div>
      <footer class="as-modal__footer">
        <button type="button" class="as-btn as-btn--glass" data-close>Cancel</button>
        <button type="submit" class="as-btn as-btn--primary" id="scheduleSubmit"><i class="fas fa-calendar-check"></i> Schedule</button>
      </footer>
    </form>
  </section>
</div>

<!-- ---------- Slot modal ---------- -->
<div class="as-modal" id="slotModal" aria-hidden="true">
  <div class="as-modal__backdrop" data-close></div>
  <section class="as-modal__dialog" role="dialog" aria-modal="true">
    <header class="as-modal__header">
      <div><h3>Create availability slot</h3><p>Open a window of capacity for scheduling.</p></div>
      <button type="button" class="as-modal__close" data-close><i class="fas fa-xmark"></i></button>
    </header>
    <form method="post">
      <input type="hidden" name="action" value="create_slot">
      <div class="as-modal__body">
        <div class="as-grid-2">
          <div class="as-field"><label>Date</label><input type="date" name="slot_date" required value="<?= e(date('Y-m-d')) ?>"></div>
          <div class="as-field"><label>Capacity</label><input type="number" name="capacity" min="1" max="50" value="5" required></div>
        </div>
        <div class="as-grid-2">
          <div class="as-field"><label>Start</label><input type="time" name="start_time" required value="09:00"></div>
          <div class="as-field"><label>End</label><input type="time" name="end_time" required value="17:00"></div>
        </div>
        <div class="as-grid-2">
          <div class="as-field"><label>Mode</label>
            <select name="mode"><option value="in_person">In person</option><option value="online">Online</option><option value="phone">Phone</option></select>
          </div>
          <?php if ($programmes): ?>
            <div class="as-field"><label>Programme (optional)</label>
              <select name="programme_id"><option value="">Any programme</option>
                <?php foreach ($programmes as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['code'] . ' · ' . $p['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>
        <div class="as-field"><label>Location</label><input type="text" name="location"></div>
        <div class="as-field"><label>Meeting link</label><input type="url" name="meeting_link"></div>
        <div class="as-field"><label>Notes</label><textarea name="notes"></textarea></div>
      </div>
      <footer class="as-modal__footer">
        <button type="button" class="as-btn as-btn--glass" data-close>Cancel</button>
        <button type="submit" class="as-btn as-btn--primary"><i class="fas fa-plus"></i> Create slot</button>
      </footer>
    </form>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  /* ---------- Scope injected from PHP ---------- */
  const IS_MANAGER = <?= $isManager ? 'true' : 'false' ?>;
  const ME_ID      = <?= (int)$userId ?>;
  const ME_NAME    = <?= json_encode(trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? '')), JSON_UNESCAPED_UNICODE) ?>;
  const CAPACITY   = <?= (int)SW_DEFAULT_CAPACITY ?>;

  /* ---------- Tabs ---------- */
  const tabs   = document.querySelectorAll('.as-tab');
  const panels = document.querySelectorAll('[data-panel]');
  tabs.forEach(t => t.addEventListener('click', function () {
    tabs.forEach(x => x.classList.remove('is-active'));
    t.classList.add('is-active');
    panels.forEach(p => p.style.display = p.dataset.panel === t.dataset.tab ? '' : 'none');
  }));

  /* ---------- Schedule modal ---------- */
  const schedModal  = document.getElementById('scheduleModal');
  const schedForm   = document.getElementById('scheduleForm');
  const schedAction = document.getElementById('scheduleAction');
  const schedIdF    = document.getElementById('scheduleId');
  const schedAttF   = document.getElementById('scheduleAttemptId');
  const schedLabel  = document.getElementById('scheduleLabel');
  const schedAt     = document.getElementById('scheduleAt');
  const schedDur    = document.getElementById('scheduleDuration');
  const schedMode   = document.getElementById('scheduleMode');
  const schedAssF   = document.getElementById('scheduleAssessor');
  const schedLoc    = document.getElementById('scheduleLocation');
  const schedLink   = document.getElementById('scheduleLink');
  const schedNotes  = document.getElementById('scheduleNotes');
  const schedTitle  = document.getElementById('scheduleTitle');
  const schedSub    = document.getElementById('scheduleSubtitle');
  const schedSubmit = document.getElementById('scheduleSubmit');

  /* Assessor data — only meaningful for managers */
  const assessorsData = <?= json_encode(array_map(fn($a) => [
      'id'     => (int)$a['id'],
      'name'   => trim($a['first_name'] . ' ' . $a['last_name']),
      'active' => (int)$a['active'],
      'pct'    => (int)$a['capacity_pct'],
  ], $assessors), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  function fillAssessors(qualifiedIds) {
    if (!IS_MANAGER) {
      /* Non-manager: always self, never editable */
      schedAssF.innerHTML = '';
      const opt = document.createElement('option');
      opt.value = ME_ID;
      opt.textContent = (ME_NAME || 'You') + ' (you)';
      opt.selected = true;
      schedAssF.appendChild(opt);
      return;
    }
    schedAssF.innerHTML = '<option value="">— Unallocated —</option>';
    const list = (qualifiedIds && qualifiedIds.length)
      ? assessorsData.filter(a => qualifiedIds.includes(a.id))
      : assessorsData;
    list.forEach(a => {
      const opt = document.createElement('option');
      opt.value = a.id;
      opt.textContent = a.name + ' (' + a.active + '/' + CAPACITY + ')';
      schedAssF.appendChild(opt);
    });
  }

  function openSchedModal() {
    schedModal.classList.add('is-open');
    schedModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('as-modal-open');
    setTimeout(() => schedAt.focus(), 60);
  }
  function closeSchedModal() {
    schedModal.classList.remove('is-open');
    schedModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('as-modal-open');
  }

  /* ---- Top "Schedule" button: shortcut to first pending attempt ---- */
  document.getElementById('btnNewSchedule').addEventListener('click', function () {
    const first = document.querySelector('[data-schedule-open]');
    if (first) { first.click(); return; }
    alert(IS_MANAGER
      ? 'No assessments are currently waiting to be scheduled.'
      : 'You have no assessments waiting to be scheduled.');
  });

  /* ---- Per-row "Schedule" button ---- */
  document.querySelectorAll('[data-schedule-open]').forEach(btn => {
    btn.addEventListener('click', function () {
      schedAction.value = 'schedule';
      schedIdF.value    = '';
      schedAttF.value   = btn.dataset.attemptId || '';
      schedLabel.value  = btn.dataset.label || '';
      schedAt.value     = '';
      schedDur.value    = 60;
      schedMode.value   = 'in_person';
      schedLoc.value    = '';
      schedLink.value   = '';
      schedNotes.value  = '';
      schedTitle.textContent = 'Schedule assessment';
      schedSub.textContent   = btn.dataset.label || '';
      schedSubmit.innerHTML  = '<i class="fas fa-calendar-check"></i> Schedule';

      const qualified = (btn.dataset.qualified || '').split(',').filter(Boolean).map(Number);
      fillAssessors(qualified.length ? qualified : null);
      openSchedModal();
    });
  });

  /* ---- Per-row "Edit" / reschedule button ---- */
  document.querySelectorAll('[data-schedule-edit]').forEach(btn => {
    btn.addEventListener('click', function () {
      schedAction.value = 'update_schedule';
      schedIdF.value    = btn.dataset.id || '';
      schedAttF.value   = '';
      schedLabel.value  = btn.dataset.label || '';
      schedAt.value     = btn.dataset.scheduledAt || '';
      schedDur.value    = btn.dataset.duration || 60;
      schedMode.value   = btn.dataset.mode || 'in_person';
      schedLoc.value    = btn.dataset.location || '';
      schedLink.value   = btn.dataset.meetingLink || '';
      schedNotes.value  = btn.dataset.notes || '';
      schedTitle.textContent = 'Reschedule assessment';
      schedSub.textContent   = btn.dataset.label || '';
      schedSubmit.innerHTML  = '<i class="fas fa-check"></i> Save changes';
      fillAssessors(null);
      openSchedModal();
    });
  });

  schedModal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', closeSchedModal));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && schedModal.classList.contains('is-open')) closeSchedModal();
  });

  /* ---------- Slot modal ---------- */
  const slotModal = document.getElementById('slotModal');
  function openSlotModal()  { slotModal.classList.add('is-open');    document.body.classList.add('as-modal-open'); }
  function closeSlotModal() { slotModal.classList.remove('is-open'); document.body.classList.remove('as-modal-open'); }

  ['btnNewSlot', 'btnNewSlotInline'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', openSlotModal);
  });
  slotModal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', closeSlotModal));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && slotModal.classList.contains('is-open')) closeSlotModal();
  });
});
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>