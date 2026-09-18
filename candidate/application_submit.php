<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Application Submission
 * ================================================
 * Final Stage 6 submission confirmation and server-side submission.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId   = (int) current_user_id();
$user          = current_user();
$applicationId = (int) ($_REQUEST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $result = CandidateApplicationsController::submitApplication($candidateId, $applicationId);
    if (!empty($result['success'])) {
        set_flash('success', 'Application submitted successfully.', 'Your application has been submitted and is now being processed.');
        redirect((string) $result['redirect']);
    }

    if (!empty($result['already_submitted'])) {
        set_flash('error', 'This application has already been submitted.', 'The application is already in progress and cannot be submitted again.');
        redirect((string) $result['redirect']);
    }

    set_flash('error', 'Submission failed.', $result['message'] ?? 'We couldn\'t submit your application. Your application is still saved as a draft. Please try again.');
    redirect((string) $result['redirect']);
}

// GET: show final confirmation page for valid draft applications
$application = Application::findForCandidate($candidateId, $applicationId);
if (!$application) {
    set_flash('error', 'Application not found.', 'The requested application does not exist.');
    redirect('candidate/applications.php');
}

if (($application['status'] ?? '') !== 'draft') {
    set_flash('error', 'This application has already been submitted.', 'This application can no longer be edited or submitted again.');
    redirect('candidate/application_detail.php?id=' . (int) $applicationId);
}

$opportunity = Opportunity::find((int) ($application['opportunity_id'] ?? 0));
if (!$opportunity) {
    set_flash('error', 'Opportunity not found.', 'The opportunity for this application could not be found.');
    redirect('candidate/applications.php');
}

$readiness = ApplicationFormController::validateSubmissionReadiness($candidateId, $applicationId);
if (!$readiness['valid']) {
    set_flash('error', 'Application is not ready to submit.', $readiness['errors'][0] ?? 'Please complete your application before submitting.');
    redirect('candidate/application_review.php?id=' . (int) $applicationId);
}

$confirmation = ApplicationFormController::getReviewConfirmation($candidateId, $applicationId);
if (empty($confirmation['declaration_confirmed'])) {
    set_flash('error', 'Please confirm the declaration before submitting your application.', 'Your declaration must be completed before your application can be submitted.');
    redirect('candidate/application_review.php?id=' . (int) $applicationId);
}

$consentPurposes = array_values(array_unique(array_map('strval', $confirmation['consent_purposes'] ?? [])));
if (!in_array(CONSENT_PROGRAMME, $consentPurposes, true)) {
    set_flash('error', 'Please provide the required consent before submitting your application.', 'The required programme administration consent must be confirmed before you can submit.');
    redirect('candidate/application_review.php?id=' . (int) $applicationId);
}

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Submit Application - <?= e($opportunity['title'] ?? '') ?> - Investhood IT">
  <title>Submit Application | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/application_form.css') ?>">
  <link rel="stylesheet" href="<?= url('css/application_review.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page application-submit-page">

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
          <a href="<?= url('candidate/application_review.php?id=' . (int) $applicationId) ?>" class="dash-header__back"><i class="fas fa-chevron-left"></i> Back</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">
        <?= $flashes ?>

        <section class="app-form__header">
          <div class="app-form__header-badges">
            <span class="badge badge--muted"><?= e(Application::label($application['status'])) ?></span>
            <?php if (!empty($application['application_reference'])): ?>
              <span class="app-form__ref"><i class="fas fa-hashtag"></i> <?= e($application['application_reference']) ?></span>
            <?php endif; ?>
          </div>
          <h1 class="app-form__title">Submit Your Application</h1>
          <p class="app-form__subtitle">
            <i class="fas fa-graduation-cap"></i>
            <?= e($opportunity['title'] ?? 'Application') ?>
            <?php if (!empty($opportunity['programme_name'])): ?>
              <span class="app-form__separator">•</span>
              <span><?= e($opportunity['programme_name']) ?></span>
            <?php endif; ?>
          </p>
        </section>

        <section class="app-review__section app-review__section--ready">
          <div class="app-review__section-header">
            <div class="app-review__section-icon"><i class="fas fa-paper-plane"></i></div>
            <div>
              <h2>Final Submission</h2>
              <p>Please confirm the details below before submitting.</p>
            </div>
          </div>

          <div class="app-detail__grid">
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
              <span class="app-info-card__value"><?= e($application['application_reference'] ?: 'Pending') ?></span>
            </div>
            <div class="app-info-card">
              <span class="app-info-card__label">Current Status</span>
              <span class="app-info-card__value"><?= e(Application::label($application['status'])) ?></span>
            </div>
          </div>

          <div class="app-review__alert app-review__alert--warning" style="margin-top: 20px; margin-bottom: 0;">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <strong>Warning:</strong>
              <p>Once submitted, you will not be able to edit this application.</p>
            </div>
          </div>

          <form method="post" action="<?= url('candidate/application_submit.php?id=' . (int) $applicationId) ?>" id="finalSubmitForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $applicationId ?>">
            <div class="app-review__actions" style="margin-top: 22px;">
              <a href="<?= url('candidate/application_review.php?id=' . (int) $applicationId) ?>" class="btn btn--ghost">
                <i class="fas fa-arrow-left"></i> Back to Review
              </a>
              <button type="submit" class="btn btn--primary" id="submitApplicationBtn">
                <i class="fas fa-paper-plane"></i> Submit Application
              </button>
            </div>
          </form>
        </section>
      </div>
    </main>
  </div>

  <script src="<?= url('js/applications.js') ?>"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var form = document.getElementById('finalSubmitForm');
      var submitBtn = document.getElementById('submitApplicationBtn');
      if (!form || !submitBtn) return;

      form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
      });
    });
  </script>
</body>
</html>