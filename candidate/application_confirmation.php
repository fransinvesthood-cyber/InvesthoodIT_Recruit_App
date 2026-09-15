<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Submission Confirmation
 * ================================================
 * Shows a secure confirmation page for the authenticated candidate.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId = (int) current_user_id();
$user = current_user();
$applicationId = (int) ($_GET['id'] ?? 0);

$application = Application::findForCandidate($candidateId, $applicationId);
if (!$application) {
    set_flash('error', 'Application not found.', 'The requested application does not exist.');
    redirect('candidate/applications.php');
}

if (($application['status'] ?? '') !== 'submitted') {
    set_flash('error', 'This application has not been submitted.', 'This confirmation page is only available for submitted applications.');
    redirect('candidate/application_detail.php?id=' . (int) $applicationId);
}

$opportunity = Opportunity::find((int) ($application['opportunity_id'] ?? 0));
if (!$opportunity) {
    set_flash('error', 'Opportunity not found.', 'The opportunity for this application could not be found.');
    redirect('candidate/applications.php');
}

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Application Submitted - Investhood IT">
  <title>Application Submitted | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body class="dashboard-page application-confirmation-page">
  <div class="dashboard">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
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
          <li><a href="<?= url('candidate/applications.php') ?>" class="sidebar__link active"><i class="fas fa-file-alt"></i> Applications</a></li>
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
        <?= $flashes ?>

        <section class="app-review__section app-review__section--ready" style="max-width: 840px; margin: 40px auto;">
          <div class="app-review__section-header">
            <div class="app-review__section-icon" style="background: rgba(16,185,129,0.12); color: #10b981;"><i class="fas fa-check-circle"></i></div>
            <div>
              <h2>✓ Application Submitted Successfully</h2>
              <p>Your application has been successfully submitted.</p>
            </div>
          </div>

          <div class="app-detail__grid" style="margin-top: 24px;">
            <div class="app-info-card">
              <span class="app-info-card__label">Opportunity</span>
              <span class="app-info-card__value"><?= e($opportunity['title'] ?? '—') ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Programme</span>
              <span class="app-info-card__value"><?= e($opportunity['programme_name'] ?? '—') ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Cohort</span>
              <span class="app-info-card__value"><?= e($opportunity['cohort_name'] ?? '—') ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Application Reference</span>
              <span class="app-info-card__value"><?= e($application['application_reference'] ?? '—') ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Submitted</span>
              <span class="app-info-card__value"><?= e(format_date($application['submitted_at'] ?? null, 'd M Y H:i')) ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Status</span>
              <span class="app-info-card__value"><?= e(Application::label($application['status'] ?? '')) ?></span>
            </div>
          </div>

          <div class="app-review__actions" style="margin-top: 24px;">
            <a href="<?= url('candidate/application_detail.php?id=' . (int) $applicationId) ?>" class="btn btn--primary">
              <i class="fas fa-eye"></i> View Application
            </a>
            <a href="<?= url('candidate/applications.php') ?>" class="btn btn--outline">
              <i class="fas fa-list"></i> Go to My Applications
            </a>
            <a href="<?= url('candidate/dashboard.php') ?>" class="btn btn--ghost">
              <i class="fas fa-home"></i> Back to Dashboard
            </a>
          </div>
        </section>
      </div>
    </main>
  </div>

  <script src="<?= url('js/applications.js') ?>"></script>
</body>
</html>
