<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Applications (My Applications)
 * ================================================
 * Displays the logged-in candidate's applications from the database.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId = (int) current_user_id();
$user = current_user();
$data = CandidateApplicationsController::index($candidateId);
$applications = $data['applications'];
$counts = $data['counts'];

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="My Applications - Investhood IT Programme & Scarce Skills Platform">
  <title>My Applications | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page applications-page">

  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Main</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('candidate/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Dashboard</a></li>
          <li><a href="<?= url('candidate/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('candidate/applications.php') ?>" class="sidebar__link active"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="<?= url('candidate/profile.php') ?>" class="sidebar__link"><i class="fas fa-user"></i> My Profile</a></li>
          <li><a href="<?= url('candidate/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
      </nav>

      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="<?= url('candidate/avatar.php') ?>" alt="Profile">
          </div>
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
            <h1 class="opp-hero__title">My Applications</h1>
            <p class="opp-hero__subtitle">Track your application journey from submission to outcome.</p>
          </div>
        </section>

        <?= $flashes ?>

        <!-- ===== STATS ===== -->
        <section class="app-stats">
          <div class="app-stats__grid">
            <div class="stat-card"><span class="stat-card__number"><?= (int) ($counts['total'] ?? 0) ?></span><span class="stat-card__label">Total Applications</span></div>
            <div class="stat-card"><span class="stat-card__number"><?= (int) ($counts['draft'] ?? 0) ?></span><span class="stat-card__label">Drafts</span></div>
            <div class="stat-card"><span class="stat-card__number"><?= (int) ($counts['submitted'] ?? 0) ?></span><span class="stat-card__label">Submitted</span></div>
            <div class="stat-card"><span class="stat-card__number"><?= (int) ($counts['under_review'] ?? 0) ?></span><span class="stat-card__label">Under Review</span></div>
            <div class="stat-card"><span class="stat-card__number"><?= (int) ($counts['selected'] ?? 0) ?></span><span class="stat-card__label">Selected</span></div>
            <div class="stat-card"><span class="stat-card__number"><?= (int) ($counts['rejected'] ?? 0) ?></span><span class="stat-card__label">Rejected</span></div>
          </div>
        </section>

        <!-- ===== APPLICATIONS ===== -->
        <section class="app-section">
          <?php if (empty($applications)): ?>
            <div class="empty-state">
              <div class="empty-state__icon"><i class="fas fa-file-alt"></i></div>
              <h3 class="empty-state__title">No applications yet</h3>
              <p class="empty-state__text">You haven't submitted any applications yet.</p>
              <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--primary">Explore Opportunities</a>
            </div>
          <?php else: ?>
            <div class="app-grid">
              <?php foreach ($applications as $app): ?>
                <?php
                $badgeTone   = Application::badgeTone($app['status']);
                $statusLabel = Application::label($app['status']);
                ?>
                <article class="app-card">
                  <div class="app-card__header">
                    <div class="app-card__meta">
                      <span class="badge badge--<?= e($badgeTone) ?>"><?= e($statusLabel) ?></span>
                      <span class="app-card__date"><i class="far fa-calendar-alt"></i> Created <?= e(format_date($app['created_at'], 'd M Y')) ?></span>
                    </div>
                    <h3 class="app-card__title"><?= e($app['opportunity_title']) ?></h3>
                    <div class="app-card__programme">
                      <i class="fas fa-graduation-cap"></i>
                      <span><?= e($app['programme_name']) ?><?= !empty($app['cohort_name']) ? ' &bull; ' . e($app['cohort_name']) : '' ?></span>
                    </div>
                    <?php if (!empty($app['organisation'])): ?>
                      <div class="app-card__org"><i class="fas fa-building"></i> <?= e($app['organisation']) ?></div>
                    <?php endif; ?>
                    <div class="app-card__ref"><i class="fas fa-hashtag"></i> <?= e($app['application_reference']) ?></div>
                    <div class="app-card__date app-card__date--updated">
                      <i class="far fa-clock"></i> Last updated <?= e(format_date($app['updated_at'], 'd M Y')) ?>
                    </div>

                    <div class="app-card__footer">
                      <?php if ($app['status'] === 'draft'): ?>
                        <a href="<?= url('candidate/application_start.php?id=' . (int) $app['id']) ?>" class="btn btn--primary btn--sm">Continue Application</a>
                      <?php else: ?>
                        <a href="<?= url('candidate/application_detail.php?id=' . (int) $app['id']) ?>" class="btn btn--primary btn--sm">View Application</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      </div>
    </main>

  </div>

  <script src="<?= url('js/applications.js') ?>"></script>

</body>
</html>