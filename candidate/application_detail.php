<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Application Detail
 * ================================================
 * Displays a single application if it belongs to the authenticated candidate.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId = (int) current_user_id();
$user = current_user();
$applicationId = (int) ($_GET['id'] ?? 0);

// Fetch the application (ownership verified server-side)
$application = CandidateApplicationsController::show($candidateId, $applicationId);

if (!$application) {
    set_flash('error', 'Application not found.');
    redirect('candidate/applications.php');
}

$opportunityId = (int) $application['opportunity_id'];

// Opportunity context for the app detail (title, programme, cohort, organisation)
$opportunity = CandidateOpportunitiesController::getOpportunityDetail($opportunityId);
if (!$opportunity) {
    set_flash('error', 'Opportunity not found.');
    redirect('candidate/applications.php');
}

$today = date('Y-m-d');
$submittedAt = $application['submitted_at'] ?? null;
$isSubmitted = !empty($submittedAt);
$statusLabel = Application::label($application['status'] ?? '');
$badgeClass = Application::badgeTone($application['status'] ?? '');
$appReference = $application['application_reference'] ?? '';

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($opportunity['title']) ?> - Investhood IT Application Detail">
  <title><?= e($opportunity['title']) ?> | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page application-detail-page">

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
          <a href="<?= url('candidate/applications.php') ?>" class="dash-header__back"><i class="fas fa-chevron-left"></i> Back</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== FLASH MESSAGES ===== -->
        <?= $flashes ?>

        <!-- ===== APPLICATION SUMMARY ===== -->
        <section class="app-detail__section">
          <div class="app-detail__header">
            <div>
              <div class="app-detail__header-badges">
                <span class="badge badge--<?= e($badgeClass) ?>"><?= e($statusLabel) ?></span>
                <span class="app-detail__ref"><i class="fas fa-hashtag"></i> <?= e($appReference) ?></span>
              </div>
              <h1 class="app-detail__title"><?= e($opportunity['title']) ?></h1>
              <p class="app-detail__programme">
                <i class="fas fa-graduation-cap"></i>
                <?= e($opportunity['programme_name']) ?>
                <?php if (!empty($opportunity['cohort_name'])): ?>
                  <span class="app-detail__separator">•</span>
                  <span><?= e($opportunity['cohort_name']) ?></span>
                <?php endif; ?>
              </p>
            </div>
          </div>

          <div class="app-detail__grid">
            <div class="app-info-card">
              <span class="app-info-card__label">Application Reference</span>
              <span class="app-info-card__value"><?= e($appReference) ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Status</span>
              <span class="app-info-card__value"><?= e($statusLabel) ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Submitted</span>
              <span class="app-info-card__value"><?= $isSubmitted ? e(format_date($submittedAt, 'd M Y')) : 'Not submitted' ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Last Updated</span>
              <span class="app-info-card__value"><?= e(format_date($application['updated_at'], 'd M Y H:i')) ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Organisation</span>
              <span class="app-info-card__value"><?= e($opportunity['organisation'] ?? '—') ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Programme</span>
              <span class="app-info-card__value"><?= e($opportunity['programme_name']) ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Cohort</span>
              <span class="app-info-card__value"><?= e($opportunity['cohort_name'] ?? '—') ?></span>
            </div>
          </div>
        </section>

        <!-- ===== OPPORTUNITY VENUE ===== -->
        <section class="app-detail__section">
          <div class="app-detail__header">
            <h3 class="app-detail__subtitle">Opportunity Details</h3>
          </div>
          <div class="app-detail__grid">
            <div class="app-info-card">
              <span class="app-info-card__label">Location</span>
              <span class="app-info-card__value"><?= e($opportunity['city'] ?? $opportunity['province'] ?? 'Location TBD') ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Work Arrangement</span>
              <span class="app-info-card__value">
                <?= e(CandidateOpportunitiesController::WORK_ARRANGEMENTS_DISPLAY[$opportunity['work_arrangement']] ?? $opportunity['work_arrangement']) ?>
              </span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Positions</span>
              <span class="app-info-card__value"><?= (int) ($opportunity['available_positions'] ?? 0) ?></span>
            </div>
            <?php if (!empty($opportunity['start_date']) || !empty($opportunity['end_date'])): ?>
            <div class="app-info-card">
              <span class="app-info-card__label">Duration</span>
              <span class="app-info-card__value">
                <?php if (!empty($opportunity['start_date']) && !empty($opportunity['end_date'])): ?>
                  <?= format_date($opportunity['start_date'], 'd M Y') ?> – <?= format_date($opportunity['end_date'], 'd M Y') ?>
                <?php elseif (!empty($opportunity['start_date'])): ?>
                  From <?= format_date($opportunity['start_date'], 'd M Y') ?>
                <?php elseif (!empty($opportunity['end_date'])): ?>
                  Until <?= format_date($opportunity['end_date'], 'd M Y') ?>
                <?php else: ?>
                  Not specified
                <?php endif; ?>
              </span>
            </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- ===== ACTION ===== -->
        <section class="app-detail__section">
          <div class="app-detail__card app-detail__card--action">
            <h3 class="app-detail__card-title">Application Status</h3>
            <p class="app-detail__card-text">
              Your application is currently <strong><?= e($statusLabel) ?></strong>.
              <?php if ($application['status'] === 'draft'): ?>
                You can continue completing your application at any time before the closing date.
              <?php else: ?>
                You will be notified of any updates by the recruitment team.
              <?php endif; ?>
            </p>
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
              <?php if ($application['status'] === 'draft'): ?>
                <a href="<?= url('candidate/application_form.php?id=' . (int) $application['id']) ?>" class="btn btn--primary btn--full">
                  <i class="fas fa-arrow-right"></i> Continue Application
                </a>
              <?php endif; ?>
              <a href="<?= url('candidate/opportunity_detail.php?id=' . (int) $opportunityId) ?>" class="btn btn--outline btn--full">
                <i class="fas fa-briefcase"></i> View Opportunity
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