<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Application Start
 * ================================================
 * Displays a simple application introduction page
 * after a Draft application has been created.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId   = (int) current_user_id();
$user          = current_user();
$applicationId = (int) ($_GET['id'] ?? 0);

// Fetch the application with ownership verification
$application = CandidateApplicationsController::show($candidateId, $applicationId);

if (!$application) {
    set_flash('error', 'Application not found.', 'The requested application does not exist.');
    redirect('candidate/applications.php');
}

// Only Draft status should be shown here for now
if ($application['status'] !== 'draft') {
    redirect('candidate/application_detail.php?id=' . (int) $application['id']);
}

$opportunityId = (int) $application['opportunity_id'];

// Get opportunity context (title, programme, cohort) via relationship
$opportunity = CandidateOpportunitiesController::getOpportunityDetail($opportunityId);
if (!$opportunity) {
    set_flash('error', 'Opportunity not found', 'The related opportunity could not be found.');
    redirect('candidate/applications.php');
}

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Start Your Application - Investhood IT">
  <title>Start Your Application | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page application-start-page">

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
          <a href="<?= url('candidate/application_detail.php?id=' . (int) $application['id']) ?>" class="dash-header__back"><i class="fas fa-chevron-left"></i> Back</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== FLASH MESSAGES ===== -->
        <?= $flashes ?>

        <!-- ===== APPLICATION START HERO ===== -->
        <section class="opp-hero">
          <div class="opp-hero__inner">
            <h1 class="opp-hero__title">Start Your Application</h1>
            <p class="opp-hero__subtitle">Begin your application for this opportunity.</p>
          </div>
        </section>

        <!-- ===== APPLICATION START CARD ===== -->
        <section class="app-start">
          <div class="app-start__card">

            <div class="app-start__header">
              <div class="app-start__status">
                <span class="badge badge--muted"><?= e(Application::label($application['status'])) ?></span>
              </div>
              <h2 class="app-start__title"><?= e($opportunity['title']) ?></h2>
            </div>

            <div class="app-start__info-grid">
              <div class="app-start__info">
                <span class="app-start__label">Opportunity</span>
                <span class="app-start__value"><?= e($opportunity['title']) ?></span>
              </div>
              <div class="app-start__info">
                <span class="app-start__label">Programme</span>
                <span class="app-start__value"><?= e($opportunity['programme_name']) ?></span>
              </div>
              <div class="app-start__info">
                <span class="app-start__label">Cohort</span>
                <span class="app-start__value"><?= e($opportunity['cohort_name'] ?? '—') ?></span>
              </div>
              <div class="app-start__info">
                <span class="app-start__label">Application Status</span>
                <span class="app-start__value">Draft</span>
              </div>
              <div class="app-start__info">
                <span class="app-start__label">Application Closing Date</span>
                <span class="app-start__value">
                  <?= !empty($opportunity['application_close_date'])
                      ? e(format_date($opportunity['application_close_date'], 'd M Y'))
                      : 'No fixed date' ?>
                </span>
              </div>
              <?php if (!empty($application['application_reference'])): ?>
              <div class="app-start__info">
                <span class="app-start__label">Application Reference</span>
                <span class="app-start__value"><?= e($application['application_reference']) ?></span>
              </div>
              <?php endif; ?>
            </div>

            <p class="app-start__message">
              <i class="fas fa-info-circle"></i>
              You can save your application and return to complete it later.
            </p>

            <div class="app-start__actions">
              <a href="<?= url('candidate/application_form.php?id=' . (int) $application['id']) ?>" class="btn btn--primary">
                <i class="fas fa-arrow-right"></i> Continue Application
              </a>
              <a href="<?= url('candidate/opportunity_detail.php?id=' . (int) $opportunityId) ?>" class="btn btn--outline">
                <i class="fas fa-briefcase"></i> Back to Opportunity
              </a>
            </div>

          </div>
        </section>

      </div>
    </main>

  </div>

  <!-- ===== SCRIPTS ===== -->
  <script src="<?= url('js/applications.js') ?>"></script>

</body>
</html>