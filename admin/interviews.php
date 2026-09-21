<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Interviews Dashboard
 * ================================================
 * Lists all interviews with search, filters, summary cards,
 * upcoming/recent sections, and actions.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$user = current_user();

$search       = trim($_GET['q'] ?? '');
$fStatus      = $_GET['status'] ?? '';
$fType        = $_GET['type'] ?? '';
$fProgramme   = $_GET['programme'] ?? '';
$fCohort      = $_GET['cohort'] ?? '';
$fOpportunity = $_GET['opportunity'] ?? '';
$fInterviewer = $_GET['interviewer'] ?? '';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 20;

$filters = [
    'search'        => $search,
    'status'        => $fStatus,
    'interview_type'=> $fType,
    'programme_id'  => $fProgramme,
    'cohort_id'     => $fCohort,
    'opportunity_id'=> $fOpportunity,
    'interviewer_id'=> $fInterviewer,
    'date_from'     => $dateFrom,
    'date_to'       => $dateTo,
];

// "Upcoming" is a virtual filter (active statuses that are still to happen,
// based on the current date and time) — it is not a value stored in the DB.
$upcomingOnly = ($fStatus === 'upcoming');
if ($upcomingOnly) {
    $filters['status'] = '';
}
$filters['upcoming_only'] = $upcomingOnly;

$result        = Interview::adminList($filters, $page, $perPage);
$interviews    = $result['records'];
$totalCount    = $result['total'];
$totalPages    = $result['pages'];

$statusCounts    = Interview::countByStatus();
$totalInterviews = array_sum($statusCounts);
$upcomingCount   = Interview::countUpcoming();

$upcomingInterviews = Interview::upcoming(5);
$recentInterviews   = Interview::recent(5);

$programmes    = Programme::all();
$cohorts       = Cohort::activeAll();
$opportunities = Opportunity::all();
$interviewers  = Interview::availableInterviewers();

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Interviews - Investhood IT Administrator">
  <title>Interviews | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_interviews.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard app-module">
  <div class="dashboard">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li>
        </ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
                    <li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
                  </ul>
        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/selection.php') ?>" class="sidebar__link"><i class="fas fa-user-check"></i> Selection &amp; Offers</a></li>
          <li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link active"><i class="fas fa-calendar-check"></i> Interviews</a></li>
                              <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-history"></i> Audit Log</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=40" alt="Admin">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span>
            <span class="sidebar__user-role">Administrator</span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN ===== -->
    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
        </div>
        <div class="dash-header__right">
          <a href="<?= url('admin/interview_schedule.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Schedule</a>
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">
        <?= $flashes ?>

        <!-- ===== PAGE HERO ===== -->
        <section class="app-hero">
          <div class="app-hero__inner">
            <div>
              <span class="section__badge">Interview Management</span>
              <h1 class="app-hero__title">Interviews</h1>
              <p class="app-hero__subtitle">Schedule, manage, and track candidate interviews across all programmes.</p>
            </div>
            <div class="app-hero__actions">
              <a href="<?= url('admin/interview_schedule.php') ?>" class="btn btn--primary"><i class="fas fa-plus"></i> Schedule Interview</a>
            </div>
          </div>
        </section>

        <!-- ===== SUMMARY CARDS (clickable → filtered list) ===== -->
        <section class="int-stats">
          <a class="int-stat-card int-stat-card--total" href="<?= url('admin/interviews.php') ?>">
            <div class="int-stat-card__icon"><i class="fas fa-calendar-check"></i></div>
            <div class="int-stat-card__body">
              <span class="int-stat-card__number"><?= (int) $totalInterviews ?></span>
              <span class="int-stat-card__label">Total Interviews</span>
            </div>
          </a>
          <a class="int-stat-card int-stat-card--upcoming" href="<?= url('admin/interviews.php?status=upcoming') ?>">
            <div class="int-stat-card__icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="int-stat-card__body">
              <span class="int-stat-card__number"><?= (int) $upcomingCount ?></span>
              <span class="int-stat-card__label">Upcoming</span>
            </div>
          </a>
          <a class="int-stat-card int-stat-card--scheduled" href="<?= url('admin/interviews.php?status=scheduled') ?>">
            <div class="int-stat-card__icon"><i class="fas fa-clock"></i></div>
            <div class="int-stat-card__body">
              <span class="int-stat-card__number"><?= (int) ($statusCounts['scheduled'] ?? 0) ?></span>
              <span class="int-stat-card__label">Scheduled</span>
            </div>
          </a>
          <a class="int-stat-card int-stat-card--completed" href="<?= url('admin/interviews.php?status=confirmed') ?>">
            <div class="int-stat-card__icon"><i class="fas fa-thumbs-up"></i></div>
            <div class="int-stat-card__body">
              <span class="int-stat-card__number"><?= (int) ($statusCounts['confirmed'] ?? 0) ?></span>
              <span class="int-stat-card__label">Confirmed</span>
            </div>
          </a>
          <a class="int-stat-card int-stat-card--rescheduled" href="<?= url('admin/interviews.php?status=rescheduled') ?>">
            <div class="int-stat-card__icon"><i class="fas fa-redo"></i></div>
            <div class="int-stat-card__body">
              <span class="int-stat-card__number"><?= (int) ($statusCounts['rescheduled'] ?? 0) ?></span>
              <span class="int-stat-card__label">Rescheduled</span>
            </div>
          </a>
          <a class="int-stat-card int-stat-card--total" href="<?= url('admin/interviews.php?status=completed') ?>">
            <div class="int-stat-card__icon"><i class="fas fa-check-circle"></i></div>
            <div class="int-stat-card__body">
              <span class="int-stat-card__number"><?= (int) ($statusCounts['completed'] ?? 0) ?></span>
              <span class="int-stat-card__label">Completed</span>
            </div>
          </a>
          <a class="int-stat-card int-stat-card--cancelled" href="<?= url('admin/interviews.php?status=cancelled') ?>">
            <div class="int-stat-card__icon"><i class="fas fa-times-circle"></i></div>
            <div class="int-stat-card__body">
              <span class="int-stat-card__number"><?= (int) ($statusCounts['cancelled'] ?? 0) ?></span>
              <span class="int-stat-card__label">Cancelled</span>
            </div>
          </a>
          <a class="int-stat-card int-stat-card--noshow" href="<?= url('admin/interviews.php?status=no_show') ?>">
            <div class="int-stat-card__icon"><i class="fas fa-user-slash"></i></div>
            <div class="int-stat-card__body">
              <span class="int-stat-card__number"><?= (int) ($statusCounts['no_show'] ?? 0) ?></span>
              <span class="int-stat-card__label">No Show</span>
            </div>
          </a>
        </section>

        <!-- ===== FILTERS ===== -->
        <section class="int-filters">
          <form method="GET" action="<?= url('admin/interviews.php') ?>" class="int-filters__form">
            <div class="int-filters__row">
              <div class="int-filters__search">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search candidate, reference, opportunity...">
              </div>
              <select name="status" class="int-filters__select">
                <option value="">All Statuses</option>
                <option value="upcoming" <?= $fStatus === 'upcoming' ? 'selected' : '' ?>>Upcoming (still to happen)</option>
                <?php foreach (Interview::STATUSES as $s): ?>
                  <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= e(Interview::label($s)) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="type" class="int-filters__select">
                <option value="">All Types</option>
                <?php foreach (Interview::TYPES as $t): ?>
                  <option value="<?= $t ?>" <?= $fType === $t ? 'selected' : '' ?>><?= e(Interview::typeLabel($t)) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="programme" class="int-filters__select">
                <option value="">All Programmes</option>
                <?php foreach ($programmes as $p): ?>
                  <option value="<?= (int) $p['id'] ?>" <?= $fProgramme == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="interviewer" class="int-filters__select">
                <option value="">All Interviewers</option>
                <?php foreach ($interviewers as $i): ?>
                  <option value="<?= (int) $i['id'] ?>" <?= $fInterviewer == $i['id'] ? 'selected' : '' ?>><?= e($i['first_name'] . ' ' . $i['last_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="int-filters__row">
              <div class="int-filters__date">
                <label>From</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
              </div>
              <div class="int-filters__date">
                <label>To</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>">
              </div>
              <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-filter"></i> Filter</button>
              <a href="<?= url('admin/interviews.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-times"></i> Clear</a>
            </div>
          </form>
        </section>

        <!-- ===== INTERVIEWS TABLE ===== -->
        <section class="int-table-section">
          <div class="int-table-header">
            <h2 class="int-table-title"><i class="fas fa-list"></i> All Interviews <span class="int-table-count">(<?= $totalCount ?>)</span></h2>
          </div>
          <?php if (empty($interviews)): ?>
            <div class="int-empty">
              <div class="int-empty__icon"><i class="fas fa-calendar-times"></i></div>
              <h3 class="int-empty__title">No interviews found</h3>
              <p class="int-empty__text">No interviews match your current filters. Try adjusting your search or schedule a new interview.</p>
              <a href="<?= url('admin/interview_schedule.php') ?>" class="btn btn--primary"><i class="fas fa-plus"></i> Schedule Interview</a>
            </div>
          <?php else: ?>
            <div class="int-table-wrap">
              <table class="int-table">
                <thead>
                  <tr>
                    <th>Candidate</th>
                    <th>Programme</th>
                    <th>Opportunity</th>
                    <th>Date &amp; Time</th>
                    <th>Type</th>
                    <th>Application Status</th>
                    <th>Outcome</th>
                    <th>Feedback By / Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($interviews as $int): ?>
                    <?php
                    $statusBadge = Interview::badgeTone($int['status']);
                    $typeLabel = Interview::typeLabel($int['interview_type']);
                    $hasFb = !empty($int['has_feedback']);
                    $fbOutcomeRaw = $int['feedback_outcome'] ?? null;
                    $fbOutcomeLabel = $hasFb ? InterviewFeedback::label($fbOutcomeRaw) : '—';
                    $fbOutcomeTone = InterviewFeedback::tone($fbOutcomeRaw);
                    $fbAdmin = trim((string)($int['feedback_admin_name'] ?? ''));
                    $fbDate = $int['feedback_submitted_at'] ?? null;
                    $appTone = Application::badgeTone($int['application_status'] ?? null);
                    ?>
                    <tr>
                      <td>
                        <div class="int-candidate">
                          <div class="int-candidate__avatar">
                            <?= e(strtoupper(substr($int['candidate_first_name'] ?? 'A', 0, 1) . substr($int['candidate_last_name'] ?? 'U', 0, 1))) ?>
                          </div>
                          <div class="int-candidate__info">
                            <span class="int-candidate__name"><?= e(($int['candidate_first_name'] ?? '') . ' ' . ($int['candidate_last_name'] ?? '')) ?></span>
                            <span class="int-candidate__email"><?= e($int['candidate_email'] ?? '') ?></span>
                          </div>
                        </div>
                      </td>
                      <td><?= e($int['programme_name'] ?? '—') ?></td>
                      <td>
                        <div class="int-opp">
                          <span class="int-opp__title"><?= e($int['opportunity_title'] ?? '—') ?></span>
                          <span class="int-opp__ref"><?= e($int['application_reference'] ?? '') ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="int-datetime">
                          <span class="int-datetime__date"><i class="fas fa-calendar"></i> <?= e(format_date($int['interview_date'], 'd M Y')) ?></span>
                          <span class="int-datetime__time"><i class="fas fa-clock"></i> <?= e(substr($int['start_time'], 0, 5)) ?> – <?= e(substr($int['end_time'], 0, 5)) ?></span>
                        </div>
                      </td>
                      <td><span class="int-type int-type--<?= e($int['interview_type']) ?>"><?= e($typeLabel) ?></span></td>
                      <td><span class="int-status int-status--<?= e($appTone) ?>"><?= e(Application::label($int['application_status'] ?? null)) ?></span></td>
                      <td><?= $hasFb ? '<span class="int-status int-status--' . e($fbOutcomeTone) . '">' . e($fbOutcomeLabel) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                      <td>
                        <?php if ($hasFb): ?>
                          <div class="int-datetime"><span class="int-datetime__date"><?= e($fbAdmin !== '' ? $fbAdmin : '—') ?></span><span class="int-datetime__time"><?= e(format_date($fbDate, 'd M Y, H:i')) ?></span></div>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td><span class="int-status int-status--<?= e($statusBadge) ?>"><?= e(Interview::label($int['status'])) ?></span></td>
                      <td>
                        <div class="int-actions">
                          <a href="<?= url('admin/interview.php?id=' . (int) $int['id']) ?>" class="btn btn--ghost btn--sm" title="View"><i class="fas fa-eye"></i></a>
                          <?php if (!in_array($int['status'], ['completed', 'cancelled'])): ?>
                            <a href="<?= url('admin/interview_schedule.php?id=' . (int) $int['id']) ?>" class="btn btn--ghost btn--sm" title="Reschedule"><i class="fas fa-edit"></i></a>
                          <?php endif; ?>
                          <?php if ($int['status'] === 'completed'): ?>
                            <?php if ($hasFb): ?>
                              <a href="<?= url('admin/interview.php?id=' . (int) $int['id']) ?>#feedbackView" class="btn btn--ghost btn--sm" title="View Feedback"><i class="fas fa-comment-dots"></i></a>
                            <?php else: ?>
                              <a href="<?= url('admin/interview.php?id=' . (int) $int['id']) ?>" class="btn btn--primary btn--sm" title="Give Feedback"><i class="fas fa-plus"></i></a>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </main>
  </div>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_interviews.js') ?>"></script>
</body>
</html>
