<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/scheduling_workload.php';
// Optional legacy layer — provides as_bootstrap_scheduling() and the scoring
// helpers used by assessor/scoring_queue.php. Safe to skip if not present.
if (is_file(__DIR__ . '/../includes/assessor_scheduling.php')) {
    require_once __DIR__ . '/../includes/assessor_scheduling.php';
}

$user        = current_user();
$currentPage = 'scheduling';
$pageTitle   = 'My Scheduling';
$conn        = Database::getConnection();
$userId      = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($userId <= 0) { http_response_code(403); exit('Invalid account.'); }

/* Ensure both schemas exist, then migrate legacy sessions once per request. */
if (function_exists('as_bootstrap_scheduling')) as_bootstrap_scheduling($conn);
sw_bootstrap($conn);
sw_migrate_legacy_sessions($conn);

/* ---------- POST handlers — every write goes through sw_* writers,
              forced to self-assessor scope. ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'create_session':
                $attemptId = (int)($_POST['attempt_id'] ?? 0);
                sw_assert_can_manage_attempt($conn, $user, $attemptId);
                $_POST['action'] = 'schedule';
                $_POST['assessor_id'] = $userId;
                sw_schedule_assessment($conn, $_POST, $userId);
                $_SESSION['sched_notice'] = ['type' => 'success', 'text' => 'Session scheduled.'];
                break;

            case 'update_session':
                $scheduleId = (int)($_POST['schedule_id'] ?? 0);
                sw_assert_can_manage_schedule($conn, $user, $scheduleId);
                $_POST['assessor_id'] = $userId;
                sw_update_schedule($conn, $scheduleId, $_POST);
                $_SESSION['sched_notice'] = ['type' => 'success', 'text' => 'Session rescheduled.'];
                break;

            case 'complete_session':
                $scheduleId = (int)($_POST['schedule_id'] ?? 0);
                sw_assert_can_manage_schedule($conn, $user, $scheduleId);
                sw_change_schedule_status($conn, $scheduleId, 'completed');
                $_SESSION['sched_notice'] = ['type' => 'success', 'text' => 'Session completed.'];
                break;

            case 'cancel_session':
                $scheduleId = (int)($_POST['schedule_id'] ?? 0);
                sw_assert_can_manage_schedule($conn, $user, $scheduleId);
                sw_change_schedule_status($conn, $scheduleId, 'cancelled');
                $_SESSION['sched_notice'] = ['type' => 'warning', 'text' => 'Session cancelled.'];
                break;

            case 'no_show':
                $scheduleId = (int)($_POST['schedule_id'] ?? 0);
                sw_assert_can_manage_schedule($conn, $user, $scheduleId);
                sw_change_schedule_status($conn, $scheduleId, 'no_show');
                $_SESSION['sched_notice'] = ['type' => 'warning', 'text' => 'Marked as no-show.'];
                break;
        }
    } catch (Throwable $ex) {
        $_SESSION['sched_notice'] = ['type' => 'error', 'text' => $ex->getMessage()];
    }
    sw_redirect(url('assessor/scheduling.php'));
}

$notice  = $_SESSION['sched_notice'] ?? null;
unset($_SESSION['sched_notice']);
$flashes = render_flashes();

/* ---------- Personal-calendar data ---------- */
$stats       = sw_personal_stats($conn, $userId);
$upcoming    = sw_fetch_upcoming_schedules($conn, $userId);
$past        = sw_fetch_past_schedules($conn, $userId);
$unscheduled = sw_fetch_pending_attempts($conn, ['assessor_scope' => $userId]);

/* ---------- Layout ---------- */
require __DIR__ . '/_layout_start.php';
?>
<style>
.as-hero{position:relative;overflow:hidden;border-radius:26px;padding:34px;background:linear-gradient(135deg,#312e81 0%,#4338ca 55%,#4f46e5 100%);color:#fff;margin-bottom:22px}
.as-hero__content{max-width:640px;position:relative;z-index:2}
.as-hero__eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.16);font-size:12px;font-weight:600;letter-spacing:.03em;text-transform:uppercase}
.as-hero h2{margin:14px 0 10px;font-size:29px;line-height:1.25}
.as-hero p{margin:0 0 22px;opacity:.88;font-size:14.5px;line-height:1.6;max-width:560px}
.as-hero__actions{display:flex;gap:10px;flex-wrap:wrap}

.as-stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:22px}
.as-stat{display:flex;flex-direction:column;gap:6px;padding:18px;border-radius:18px;background:rgba(127,127,127,.06);border:1px solid rgba(127,127,127,.1);transition:transform .18s ease,box-shadow .18s ease}
.as-stat:hover{transform:translateY(-3px);box-shadow:0 14px 40px rgba(0,0,0,.08)}
.as-stat__icon{width:38px;height:38px;display:grid;place-items:center;border-radius:12px;font-size:15px;margin-bottom:4px}
.as-stat__icon--blue{background:rgba(59,130,246,.14);color:#2563eb}
.as-stat__icon--orange{background:rgba(249,115,22,.14);color:#ea580c}
.as-stat__icon--green{background:rgba(16,185,129,.14);color:#059669}
.as-stat__icon--red{background:rgba(239,68,68,.14);color:#dc2626}
.as-stat__icon--purple{background:rgba(139,92,246,.14);color:#7c3aed}
.as-stat strong{font-size:24px;line-height:1.1}
.as-stat span{font-size:12.5px;opacity:.7}
.as-stat small{font-size:10.5px;opacity:.5;margin-top:2px}

.as-card{background:rgba(127,127,127,.04);border:1px solid rgba(127,127,127,.1);border-radius:22px;padding:22px;margin-bottom:18px}
.as-card__header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:16px;flex-wrap:wrap}
.as-card__header h3{margin:0;font-size:17px}
.as-card__header p{margin:3px 0 0;font-size:12.5px;opacity:.65}

.as-session{display:flex;align-items:center;gap:14px;padding:14px;border-radius:16px;border:1px solid rgba(127,127,127,.12);background:rgba(127,127,127,.02);margin-bottom:10px}
.as-session:last-child{margin-bottom:0}
.as-session__when{display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:64px;padding:8px 10px;border-radius:12px;background:rgba(79,70,229,.1);color:#4338ca;font-weight:700;line-height:1.1}
.as-session__when span.day{font-size:20px}
.as-session__when span.mon{font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;opacity:.7;color:inherit}
.as-session__when span.time{font-size:11px;margin-top:4px;opacity:.75}
.as-session__body{flex:1;min-width:0}
.as-session__top{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.as-session__top strong{font-size:14.5px}
.as-session__meta{display:flex;flex-wrap:wrap;gap:12px;font-size:11.5px;opacity:.65;margin-top:5px}
.as-session__actions{display:flex;gap:6px;flex-wrap:wrap}
.as-session__actions form{display:inline}

.as-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(127,127,127,.12);color:inherit}
.as-pill--success{background:rgba(16,185,129,.14);color:#059669}
.as-pill--warn{background:rgba(245,158,11,.16);color:#b45309}
.as-pill--danger{background:rgba(239,68,68,.14);color:#dc2626}
.as-pill--info{background:rgba(59,130,246,.14);color:#2563eb}
.as-pill--muted{background:rgba(127,127,127,.12);color:#64748b}

.as-empty{padding:32px 20px;text-align:center;opacity:.7;font-size:13px}
.as-empty i{display:block;font-size:26px;margin-bottom:8px;opacity:.4}

.as-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 14px;border-radius:11px;border:0;font-weight:600;font-size:13px;text-decoration:none;cursor:pointer;line-height:1.1;transition:transform .15s ease}
.as-btn:hover{transform:translateY(-1px)}
.as-btn--primary{background:#4f46e5;color:#fff}
.as-btn--light{background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.28)}
.as-btn--glass{background:rgba(127,127,127,.1);color:inherit}
.as-btn--sm{padding:7px 11px;font-size:12px}
.as-btn--danger{background:rgba(239,68,68,.12);color:#dc2626}
.as-btn--success{background:rgba(16,185,129,.14);color:#059669}

.as-notice{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:10px;border:1px solid transparent}
.as-notice--success{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#059669}
.as-notice--error{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#dc2626}
.as-notice--warning{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:#b45309}

.as-modal{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;padding:20px}
.as-modal.is-open{display:flex}
.as-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(4px)}
.as-modal__dialog{position:relative;z-index:1;width:min(640px,100%);max-height:90vh;display:flex;flex-direction:column;background:#fff;border-radius:22px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,.28)}
body.dark-mode .as-modal__dialog,html[data-theme="dark"] .as-modal__dialog{background:#111827;color:#f8fafc}
.as-modal__header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 22px 14px;border-bottom:1px solid rgba(127,127,127,.14)}
.as-modal__header h3{margin:0;font-size:17px}
.as-modal__header p{margin:4px 0 0;font-size:12.5px;opacity:.65}
.as-modal__close{width:36px;height:36px;display:grid;place-items:center;border:0;border-radius:50%;background:rgba(127,127,127,.1);color:inherit;cursor:pointer}
.as-modal__body{padding:18px 22px;overflow-y:auto;flex:1}
.as-modal__footer{display:flex;justify-content:flex-end;gap:10px;padding:14px 22px;border-top:1px solid rgba(127,127,127,.14)}
body.as-modal-open{overflow:hidden}

.as-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.as-field label{font-size:12.5px;font-weight:600;opacity:.8}
.as-field input,.as-field select,.as-field textarea{width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.25);background:rgba(127,127,127,.04);color:inherit;font:inherit;font-size:13.5px}
.as-field textarea{min-height:74px;resize:vertical}
.as-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}

@media(max-width:900px){.as-stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:650px){
  .as-hero{padding:24px}
  .as-stat-grid{grid-template-columns:1fr 1fr}
  .as-session{flex-direction:column;align-items:flex-start}
  .as-session__when{flex-direction:row;gap:8px;padding:6px 10px;min-width:0;width:auto}
  .as-session__when span.time{margin-top:0}
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
    <span class="as-hero__eyebrow"><i class="fas fa-calendar-check"></i> My Calendar</span>
    <h2>Your assessment sessions, all in one place</h2>
    <p>Schedule your assigned assessments, manage your own bookings, and track completed sessions. Changes here are immediately visible in the team Scheduling &amp; Workload view.</p>
    <div class="as-hero__actions">
      <button type="button" class="as-btn as-btn--light" id="btnNewSession"><i class="fas fa-plus"></i> New session</button>
      <a class="as-btn as-btn--glass" href="<?= url('assessor/scheduling_workload.php') ?>"><i class="fas fa-calendar-plus"></i> Team scheduling view</a>
    </div>
  </div>
</section>

<section class="as-stat-grid">
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--blue"><i class="fas fa-calendar-day"></i></span><strong><?= number_format($stats['upcoming']) ?></strong><span>Upcoming sessions</span><small>Scheduled in the future</small></div>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--purple"><i class="fas fa-sun"></i></span><strong><?= number_format($stats['today']) ?></strong><span>Today</span><small>Scheduled for today</small></div>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--orange"><i class="fas fa-clock"></i></span><strong><?= number_format($stats['week']) ?></strong><span>Next 7 days</span><small>Your coming week</small></div>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--red"><i class="fas fa-triangle-exclamation"></i></span><strong><?= number_format($stats['unscheduled']) ?></strong><span>Unscheduled</span><small>Awaiting a slot</small></div>
  <div class="as-stat"><span class="as-stat__icon as-stat__icon--green"><i class="fas fa-circle-check"></i></span><strong><?= number_format($stats['completed']) ?></strong><span>Completed</span><small>Sessions you've finished</small></div>
</section>

<!-- Unscheduled attempts -->
<section class="as-card">
  <div class="as-card__header">
    <div><h3>Awaiting a session</h3><p>Your assigned attempts that don't have an upcoming session yet.</p></div>
    <a class="as-btn as-btn--glass as-btn--sm" href="<?= url('assessor/assessments.php') ?>"><i class="fas fa-list-check"></i> Open queue</a>
  </div>
  <?php if (!$unscheduled): ?>
    <div class="as-empty"><i class="fas fa-thumbs-up"></i>All your assigned attempts are scheduled.</div>
  <?php else: ?>
    <?php foreach ($unscheduled as $a):
      $first = trim((string)$a['first_name']); $last = trim((string)$a['last_name']);
      $name  = trim($first . ' ' . $last) ?: 'Participant';
    ?>
      <div class="as-session">
        <div class="as-session__when" style="background:rgba(127,127,127,.08);color:inherit">
          <span class="day"><i class="fas fa-hourglass-half"></i></span>
          <span class="mon">to schedule</span>
        </div>
        <div class="as-session__body">
          <div class="as-session__top">
            <strong><?= e($name) ?></strong>
            <span class="as-pill as-pill--info"><?= e(as_label($a['attempt_status'])) ?></span>
          </div>
          <div class="as-session__meta">
            <span><i class="fas fa-book-open"></i> <?= e($a['outcome_code']) ?> · <?= e($a['outcome_title']) ?></span>
            <span><i class="fas fa-rotate"></i> Attempt <?= (int)$a['attempt_number'] ?></span>
            <?php if (!empty($a['submitted_at'])): ?>
              <span><i class="fas fa-clock"></i> Submitted <?= e(as_datetime($a['submitted_at'])) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="as-session__actions">
          <button type="button" class="as-btn as-btn--primary as-btn--sm"
            data-session-create
            data-attempt-id="<?= (int)$a['attempt_id'] ?>"
            data-candidate="<?= e($name) ?> — <?= e($a['outcome_code']) ?>">
            <i class="fas fa-calendar-plus"></i> Schedule
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<!-- Upcoming -->
<section class="as-card">
  <div class="as-card__header"><div><h3>Upcoming sessions</h3><p>Your scheduled assessments, ordered chronologically.</p></div></div>
  <?php if (!$upcoming): ?>
    <div class="as-empty"><i class="fas fa-calendar"></i>No upcoming sessions. Schedule one from the list above.</div>
  <?php else: ?>
    <?php foreach ($upcoming as $sess):
      $dt       = strtotime($sess['scheduled_at']);
      $isToday  = date('Y-m-d', $dt) === date('Y-m-d');
      $first    = trim((string)$sess['first_name']); $last = trim((string)$sess['last_name']);
      $name     = trim($first . ' ' . $last) ?: 'Participant';
      $pill     = $isToday ? 'warn' : 'info';
      $pillTxt  = $isToday ? 'Today' : 'Scheduled';
      $dtInput  = date('Y-m-d\TH:i', $dt);
    ?>
      <div class="as-session">
        <div class="as-session__when">
          <span class="day"><?= e(date('d', $dt)) ?></span>
          <span class="mon"><?= e(date('M', $dt)) ?></span>
          <span class="time"><?= e(date('H:i', $dt)) ?></span>
        </div>
        <div class="as-session__body">
          <div class="as-session__top">
            <strong><?= e($name) ?></strong>
            <span class="as-pill as-pill--<?= $pill ?>"><?= $pillTxt ?></span>
            <span class="as-pill as-pill--muted"><?= e(ucfirst($sess['mode'])) ?></span>
            <span class="as-pill as-pill--muted"><?= (int)$sess['duration_minutes'] ?> min</span>
          </div>
          <div class="as-session__meta">
            <span><i class="fas fa-book-open"></i> <?= e($sess['outcome_code']) ?> · <?= e($sess['outcome_title']) ?></span>
            <span><i class="fas fa-rotate"></i> Attempt <?= (int)$sess['attempt_number'] ?></span>
            <?php if (!empty($sess['location'])): ?>
              <span><i class="fas fa-location-dot"></i> <?= e($sess['location']) ?></span>
            <?php endif; ?>
            <?php if (!empty($sess['meeting_link'])): ?>
              <span><i class="fas fa-video"></i> <a href="<?= e($sess['meeting_link']) ?>" target="_blank" rel="noopener">Join link</a></span>
            <?php endif; ?>
          </div>
          <?php if (!empty($sess['notes'])): ?>
            <div class="as-session__meta"><span><i class="fas fa-note-sticky"></i> <?= e($sess['notes']) ?></span></div>
          <?php endif; ?>
        </div>
        <div class="as-session__actions">
          <button type="button" class="as-btn as-btn--glass as-btn--sm"
            data-session-edit
            data-id="<?= (int)$sess['id'] ?>"
            data-attempt-id="<?= (int)$sess['attempt_id'] ?>"
            data-candidate="<?= e($name) ?> — <?= e($sess['outcome_code']) ?>"
            data-scheduled-at="<?= e($dtInput) ?>"
            data-duration="<?= (int)$sess['duration_minutes'] ?>"
            data-mode="<?= e($sess['mode']) ?>"
            data-location="<?= e((string)$sess['location']) ?>"
            data-meeting-link="<?= e((string)$sess['meeting_link']) ?>"
            data-notes="<?= e((string)$sess['notes']) ?>">
            <i class="fas fa-pen"></i> Reschedule
          </button>
          <form method="post" onsubmit="return confirm('Mark this session as completed?');">
            <input type="hidden" name="action" value="complete_session">
            <input type="hidden" name="schedule_id" value="<?= (int)$sess['id'] ?>">
            <button class="as-btn as-btn--success as-btn--sm" type="submit"><i class="fas fa-check"></i> Complete</button>
          </form>
          <form method="post" onsubmit="return confirm('Mark as no-show?');">
            <input type="hidden" name="action" value="no_show">
            <input type="hidden" name="schedule_id" value="<?= (int)$sess['id'] ?>">
            <button class="as-btn as-btn--glass as-btn--sm" type="submit"><i class="fas fa-user-slash"></i> No-show</button>
          </form>
          <form method="post" onsubmit="return confirm('Cancel this session?');">
            <input type="hidden" name="action" value="cancel_session">
            <input type="hidden" name="schedule_id" value="<?= (int)$sess['id'] ?>">
            <button class="as-btn as-btn--danger as-btn--sm" type="submit"><i class="fas fa-xmark"></i> Cancel</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<!-- Past -->
<section class="as-card">
  <div class="as-card__header"><div><h3>Past sessions</h3><p>Completed, cancelled and no-show sessions.</p></div></div>
  <?php if (!$past): ?>
    <div class="as-empty"><i class="fas fa-clock-rotate-left"></i>No previous sessions yet.</div>
  <?php else: ?>
    <?php foreach ($past as $sess):
      $dt    = strtotime($sess['scheduled_at']);
      $first = trim((string)$sess['first_name']); $last = trim((string)$sess['last_name']);
      $name  = trim($first . ' ' . $last) ?: 'Participant';
      $pill  = $sess['status'] === 'completed' ? 'success' : ($sess['status'] === 'cancelled' ? 'muted' : 'danger');
    ?>
      <div class="as-session">
        <div class="as-session__when" style="background:rgba(127,127,127,.08);color:inherit">
          <span class="day"><?= e(date('d', $dt)) ?></span>
          <span class="mon"><?= e(date('M', $dt)) ?></span>
          <span class="time"><?= e(date('H:i', $dt)) ?></span>
        </div>
        <div class="as-session__body">
          <div class="as-session__top">
            <strong><?= e($name) ?></strong>
            <span class="as-pill as-pill--<?= $pill ?>"><?= e(as_label($sess['status'])) ?></span>
            <span class="as-pill as-pill--muted"><?= e(ucfirst($sess['mode'])) ?></span>
          </div>
          <div class="as-session__meta">
            <span><i class="fas fa-book-open"></i> <?= e($sess['outcome_code']) ?> · <?= e($sess['outcome_title']) ?></span>
            <span><i class="fas fa-rotate"></i> Attempt <?= (int)$sess['attempt_number'] ?></span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<!-- ---------- Session modal ---------- -->
<div class="as-modal" id="sessionModal" aria-hidden="true">
  <div class="as-modal__backdrop" data-close></div>
  <section class="as-modal__dialog" role="dialog" aria-modal="true">
    <header class="as-modal__header">
      <div><h3 id="sessionModalTitle">Schedule session</h3><p id="sessionModalSubtitle">Pick a date and time.</p></div>
      <button type="button" class="as-modal__close" data-close><i class="fas fa-xmark"></i></button>
    </header>
    <form method="post" id="sessionForm">
      <input type="hidden" name="action" id="sessionFormAction" value="create_session">
      <input type="hidden" name="schedule_id" id="sessionFormId" value="">
      <input type="hidden" name="attempt_id" id="sessionFormAttempt" value="">
      <div class="as-modal__body">
        <div class="as-field"><label>Assessment</label><input type="text" id="sessionCandidate" readonly></div>
        <div class="as-field">
          <label for="sessionScheduledAt">Date and time</label>
          <input type="datetime-local" id="sessionScheduledAt" name="scheduled_at" required>
        </div>
        <div class="as-grid-2">
          <div class="as-field">
            <label for="sessionDuration">Duration (minutes)</label>
            <input type="number" id="sessionDuration" name="duration_minutes" min="15" max="480" step="15" value="60" required>
          </div>
          <div class="as-field">
            <label for="sessionMode">Mode</label>
            <select id="sessionMode" name="mode">
              <option value="online">Online</option>
              <option value="in_person">In person</option>
              <option value="phone">Phone</option>
            </select>
          </div>
        </div>
        <div class="as-field"><label for="sessionLocation">Location (optional)</label><input type="text" id="sessionLocation" name="location"></div>
        <div class="as-field"><label for="sessionMeetingLink">Meeting link (optional)</label><input type="url" id="sessionMeetingLink" name="meeting_link"></div>
        <div class="as-field"><label for="sessionNotes">Notes (optional)</label><textarea id="sessionNotes" name="notes"></textarea></div>
      </div>
      <footer class="as-modal__footer">
        <button type="button" class="as-btn as-btn--glass" data-close>Cancel</button>
        <button type="submit" class="as-btn as-btn--primary" id="sessionFormSubmit"><i class="fas fa-calendar-check"></i> Schedule session</button>
      </footer>
    </form>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal     = document.getElementById('sessionModal');
  const form      = document.getElementById('sessionForm');
  const actionF   = document.getElementById('sessionFormAction');
  const idF       = document.getElementById('sessionFormId');
  const attemptF  = document.getElementById('sessionFormAttempt');
  const candidate = document.getElementById('sessionCandidate');
  const dtF       = document.getElementById('sessionScheduledAt');
  const durF      = document.getElementById('sessionDuration');
  const modeF     = document.getElementById('sessionMode');
  const locF      = document.getElementById('sessionLocation');
  const linkF     = document.getElementById('sessionMeetingLink');
  const notesF    = document.getElementById('sessionNotes');
  const titleEl   = document.getElementById('sessionModalTitle');
  const subEl     = document.getElementById('sessionModalSubtitle');
  const submitLbl = document.getElementById('sessionFormSubmit');

  function resetFields() {
    dtF.value = ''; durF.value = 60; modeF.value = 'online';
    locF.value = ''; linkF.value = ''; notesF.value = '';
  }
  function openModal() {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('as-modal-open');
    setTimeout(() => dtF.focus(), 50);
  }
  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('as-modal-open');
  }

  document.getElementById('btnNewSession').addEventListener('click', function () {
    const first = document.querySelector('[data-session-create]');
    if (first) { first.click(); return; }
    alert('You have no assessments waiting to be scheduled.');
  });

  document.querySelectorAll('[data-session-create]').forEach(btn => {
    btn.addEventListener('click', function () {
      actionF.value = 'create_session';
      idF.value = '';
      attemptF.value = btn.dataset.attemptId || '';
      candidate.value = btn.dataset.candidate || '';
      resetFields();
      titleEl.textContent = 'Schedule session';
      subEl.textContent = btn.dataset.candidate || '';
      submitLbl.innerHTML = '<i class="fas fa-calendar-check"></i> Schedule session';
      openModal();
    });
  });

  document.querySelectorAll('[data-session-edit]').forEach(btn => {
    btn.addEventListener('click', function () {
      actionF.value = 'update_session';
      idF.value = btn.dataset.id || '';
      attemptF.value = btn.dataset.attemptId || '';
      candidate.value = btn.dataset.candidate || '';
      dtF.value    = btn.dataset.scheduledAt || '';
      durF.value   = btn.dataset.duration || 60;
      modeF.value  = btn.dataset.mode || 'online';
      locF.value   = btn.dataset.location || '';
      linkF.value  = btn.dataset.meetingLink || '';
      notesF.value = btn.dataset.notes || '';
      titleEl.textContent = 'Reschedule session';
      subEl.textContent = btn.dataset.candidate || '';
      submitLbl.innerHTML = '<i class="fas fa-check"></i> Save changes';
      openModal();
    });
  });

  modal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });
});
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>