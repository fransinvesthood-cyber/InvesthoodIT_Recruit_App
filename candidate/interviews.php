<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Interview Management
 * ================================================
 * Lists every interview belonging to the logged-in candidate
 * (reached via applications.candidate_id). Provides upcoming and
 * previous views, a dynamic "Join Interview" button driven by the
 * real meeting link, and full interview details via secure modals.
 *
 * No candidate can see another candidate's interviews: every record
 * is filtered through the authenticated user's applications.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId = (int) current_user_id();
$user        = current_user();

// Real interview data from the database (Candidate -> Application -> Interview)
$interviewStats = Interview::candidateStatusCounts($candidateId);

// Upcoming = active statuses whose scheduled end time hasn't passed
$upcoming = Interview::candidateUpcoming($candidateId, 100);

// Previous = everything else (completed, cancelled, no-show, rescheduled,
// or active interviews whose slot has already passed)
$all        = Interview::forCandidate($candidateId);
$upcomingIds = array_column($upcoming, 'id', 'id');
$previous   = [];
foreach ($all as $iv) {
    if (isset($upcomingIds[(int) $iv['id']])) {
        continue;
    }
    $previous[] = $iv;
}

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="My Interviews - Investhood IT">
  <title>My Interviews | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page applications-page interviews-page">
  <div class="dashboard">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar__header">
        <a href="<?= url('candidate/dashboard.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Main</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('candidate/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Dashboard</a></li>
          <li><a href="<?= url('candidate/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('candidate/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="<?= url('candidate/interviews.php') ?>" class="sidebar__link active"><i class="fas fa-calendar-check"></i> Interviews</a></li>
          <li><a href="<?= url('candidate/profile.php') ?>" class="sidebar__link"><i class="fas fa-user"></i> My Profile</a></li>
          <li><a href="<?= url('candidate/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile"></div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Candidate') ?></span>
            <span class="sidebar__user-role">Candidate</span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- ===== MAIN ===== -->
    <main class="dashboard__main">
      <header class="dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>
      <div class="dash-content">
        <!-- ===== PAGE HERO ===== -->
        <section class="opp-hero">
          <div class="opp-hero__inner">
            <h1 class="opp-hero__title">Interview Management</h1>
            <p class="opp-hero__subtitle">View your upcoming and previous interviews, join online interviews, and review interview details.</p>
          </div>
        </section>
        <?= $flashes ?>
        <!-- ===== STATS ===== -->
        <section class="app-stats">
          <div class="app-stats__grid">
            <div class="stat-card"><span class="stat-card__number" data-count="<?= (int) ($interviewStats['total'] ?? 0) ?>"><?= (int) ($interviewStats['total'] ?? 0) ?></span><span class="stat-card__label">Total Interviews</span></div>
            <div class="stat-card"><span class="stat-card__number" data-count="<?= (int) ($interviewStats['upcoming'] ?? 0) ?>"><?= (int) ($interviewStats['upcoming'] ?? 0) ?></span><span class="stat-card__label">Upcoming Interviews</span></div>
            <div class="stat-card"><span class="stat-card__number" data-count="<?= (int) ($interviewStats['completed'] ?? 0) ?>"><?= (int) ($interviewStats['completed'] ?? 0) ?></span><span class="stat-card__label">Completed Interviews</span></div>
            <div class="stat-card"><span class="stat-card__number" data-count="<?= (int) ($interviewStats['cancelled'] ?? 0) ?>"><?= (int) ($interviewStats['cancelled'] ?? 0) ?></span><span class="stat-card__label">Cancelled Interviews</span></div>
          </div>
        </section>
        <!-- ===== UPCOMING INTERVIEWS ===== -->
        <section class="app-section" id="interviews-upcoming">
          <h2 class="section__title" style="font-size:1.25rem;">Upcoming Interviews</h2>
          <?php if (empty($upcoming)): ?>
            <div class="empty-state">
              <div class="empty-state__icon"><i class="fas fa-calendar-times"></i></div>
              <h3>No upcoming interviews</h3>
              <p>You don't have any upcoming interviews at the moment.</p>
            </div>
          <?php else: ?>
            <div class="interview-grid">
              <?php foreach ($upcoming as $iv): ?>
                <?= candidate_interview_card($iv, true) ?>
                <?= candidate_interview_modal($iv) ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
        <!-- ===== PREVIOUS INTERVIEWS ===== -->
        <section class="app-section" id="interviews-previous">
          <h2 class="section__title" style="font-size:1.25rem;">Previous Interviews</h2>
          <?php if (empty($previous)): ?>
            <div class="empty-state">
              <div class="empty-state__icon"><i class="fas fa-history"></i></div>
              <h3>No previous interviews</h3>
              <p>Your interview history will appear here once interviews are completed, cancelled, or no-shows.</p>
            </div>
          <?php else: ?>
            <div class="interview-grid">
              <?php foreach ($previous as $iv): ?>
                <?= candidate_interview_card($iv, false) ?>
                <?= candidate_interview_modal($iv) ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </main>
  </div>
  <script src="<?= url('js/candidate_interviews.js') ?>"></script>
</body>
</html>