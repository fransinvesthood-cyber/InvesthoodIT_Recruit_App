<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Application Review & Declaration
 * ================================================
 * Stage 5: Final review step.
 * Displays a read-only summary of the application,
 * a completeness checklist, declaration & consent
 * confirmation, and a [Continue to Submission] button
 * that prepares the candidate for Stage 6.
 *
 * This page NEVER submits the application.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId   = (int) current_user_id();
$user          = current_user();
$applicationId = (int) ($_GET['id'] ?? 0);

// Load review data with ownership + status + closing-date checks
$reviewData = ApplicationFormController::loadReviewData($candidateId, $applicationId);

if ($reviewData === null) {
    set_flash('error', 'Application not found.', 'The requested application does not exist.');
    redirect('candidate/applications.php');
}

$application = $reviewData['application'];
$opportunity = $reviewData['opportunity'] ?? null;
$readonly    = $reviewData['readonly'] ?? false;
$errorMsg    = $reviewData['error'] ?? null;

// If readonly (submitted/closed), show read-only message
if ($readonly) {
    $flashes = render_flashes();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Application Review | Investhood IT</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
      <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
      <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
      <link rel="stylesheet" href="<?= url('css/application_form.css') ?>">
      <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
      <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
    </head>
    <body class="dashboard-page application-form-page">
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
              <a href="<?= url('candidate/application_detail.php?id=' . (int) $application['id']) ?>" class="dash-header__back"><i class="fas fa-chevron-left"></i> Back</a>
            </div>
            <div class="dash-header__right">
              <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
              <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
            </div>
          </header>

          <div class="dash-content">
            <?= $flashes ?>
            <section class="app-form__readonly">
              <div class="app-form__readonly-icon"><i class="fas fa-lock"></i></div>
              <h2>Application Not Editable</h2>
              <p><?= e($errorMsg ?? 'This application can no longer be edited.') ?></p>
              <a href="<?= url('candidate/application_detail.php?id=' . (int) $application['id']) ?>" class="btn btn--primary">
                <i class="fas fa-arrow-left"></i> Back to Application
              </a>
            </section>
          </div>
        </main>
      </div>
      <script src="<?= url('js/applications.js') ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

// Normal review data
$candidateUser   = $reviewData['user'] ?? [];
$candidateProfile = $reviewData['profile'] ?? [];
$qualifications  = $reviewData['qualifications'] ?? [];
$skills          = $reviewData['skills'] ?? [];
$experiences     = $reviewData['experiences'] ?? [];
$questions       = $reviewData['questions'] ?? ['eligibility' => [], 'application' => []];
$responses       = $reviewData['responses'] ?? [];
$documentData    = $reviewData['documents'] ?? ['requirements' => [], 'application_docs' => [], 'profile_docs' => [], 'items' => []];
$review          = $reviewData['review'] ?? [];

$completeness   = $review['completeness'] ?? ['items' => [], 'all_complete' => false, 'missing_fields' => []];
$docValidation  = $review['documents'] ?? ['valid' => false, 'missing' => []];
$consentState   = $review['consent_state'] ?? [];
$confirmation   = $review['confirmation'] ?? ['declaration_confirmed' => false, 'consent_purposes' => []];
$closingStatus  = $review['closing_status'] ?? true;
$closingReason  = $review['closing_reason'] ?? null;

$eligibilityCheck = ApplicationFormController::checkEligibility(
    [
        'user'           => $candidateUser,
        'profile'        => $candidateProfile,
        'qualifications' => $qualifications,
        'skills'         => $skills,
        'experiences'    => $experiences,
    ],
    $reviewData['eligibility'] ?? []
);

$fullName = trim(($candidateUser['first_name'] ?? '') . ' ' . ($candidateUser['last_name'] ?? ''));
$flashes = render_flashes();

if (!$closingStatus) {
    $closingStatus = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Review Your Application - <?= e($opportunity['title'] ?? '') ?> - Investhood IT">
  <title>Review Application | Investhood IT</title>
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
<body class="dashboard-page application-review-page">

  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
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

        <?= $flashes ?>

        <?php if (!$closingStatus): ?>
        <!-- ===== CLOSING DATE ALERT ===== -->
        <section class="app-review__alert app-review__alert--error">
          <i class="fas fa-exclamation-circle"></i>
          <div>
            <strong>Applications for this opportunity are now closed.</strong>
            <p>You can still review your application, but you cannot submit it.</p>
          </div>
          <a href="<?= url('candidate/application_detail.php?id=' . (int) $application['id']) ?>" class="btn btn--outline btn--sm">
            <i class="fas fa-arrow-left"></i> Back to Application
          </a>
        </section>
        <?php endif; ?>

        <!-- ===== FORM HEADER ===== -->
        <section class="app-form__header">
          <div class="app-form__header-badges">
            <span class="badge badge--muted"><?= e(Application::label($application['status'])) ?></span>
            <?php if (!empty($application['application_reference'])): ?>
              <span class="app-form__ref"><i class="fas fa-hashtag"></i> <?= e($application['application_reference']) ?></span>
            <?php endif; ?>
          </div>
          <h1 class="app-form__title">Review Your Application</h1>
          <p class="app-form__subtitle">
            <i class="fas fa-graduation-cap"></i>
            <?= e($opportunity['title'] ?? 'Application') ?>
            <?php if (!empty($opportunity['programme_name'])): ?>
              <span class="app-form__separator">•</span>
              <span><?= e($opportunity['programme_name']) ?></span>
            <?php endif; ?>
          </p>
          <p class="app-review__intro">Please review your application carefully before proceeding.</p>
        </section>

        <!-- ===== PROGRESS INDICATOR (5 steps) ===== -->
        <section class="app-form__progress" aria-label="Application progress">
          <div class="app-form__progress-steps">
            <div class="app-form__step app-form__step--done" data-step-indicator="1">
              <span class="app-form__step-num">1</span>
              <span class="app-form__step-label">Personal Information</span>
            </div>
            <div class="app-form__step-line"></div>
            <div class="app-form__step app-form__step--done" data-step-indicator="2">
              <span class="app-form__step-num">2</span>
              <span class="app-form__step-label">Eligibility</span>
            </div>
            <div class="app-form__step-line"></div>
            <div class="app-form__step app-form__step--done" data-step-indicator="3">
              <span class="app-form__step-num">3</span>
              <span class="app-form__step-label">Questions</span>
            </div>
            <div class="app-form__step-line"></div>
            <div class="app-form__step app-form__step--done" data-step-indicator="4">
              <span class="app-form__step-num">4</span>
              <span class="app-form__step-label">Documents</span>
            </div>
            <div class="app-form__step-line"></div>
            <div class="app-form__step app-form__step--active" data-step-indicator="5">
              <span class="app-form__step-num">5</span>
              <span class="app-form__step-label">Review & Declaration</span>
            </div>
          </div>
          <div class="app-form__progress-text">
            <span>Step 5 of 5</span>
            <span>Review & Declaration</span>
          </div>
        </section>

        <!-- ===== APPLICATION REVIEW ===== -->
        <form id="reviewForm" class="app-review" method="post" action="<?= url('candidate/application_actions.php') ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_review_confirmation">
          <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">

          <?php if (!$completeness['all_complete']): ?>
          <!-- ===== INCOMPLETE NOTICE ===== -->
          <section class="app-review__alert app-review__alert--warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <strong>Your application is incomplete.</strong>
              <p>Complete the missing items below before you can continue to submission.</p>
              <?php if (!empty($docValidation['missing'])): ?>
                <p class="app-review__missing-list-title">Missing required <?= count($docValidation['missing']) === 1 ? 'document' : 'documents' ?>:</p>
                <ul class="app-review__missing-list">
                  <?php foreach ($docValidation['missing'] as $missingDoc): ?>
                    <li><i class="fas fa-times-circle"></i> <?= e($missingDoc) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <?php if (!empty($completeness['missing_fields']) && empty($docValidation['missing'])): ?>
                <p class="app-review__missing-list">Missing required information:</p>
                <ul class="app-review__missing-list">
                  <?php foreach ($completeness['missing_fields'] as $missingField): ?>
                    <li><i class="fas fa-times-circle"></i> <?= e($missingField) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </section>
          <?php endif; ?>

          <!-- ===== COMPLETENESS CHECKLIST ===== -->
          <section class="app-review__section">
            <div class="app-review__section-header">
              <div class="app-review__section-icon"><i class="fas fa-clipboard-list"></i></div>
              <div>
                <h2>Application Progress</h2>
                <p>Check that everything is complete before submitting.</p>
              </div>
            </div>
            <div class="app-review__checklist">
              <?php foreach ($completeness['items'] as $key => $item): ?>
                <div class="app-review__checklist-item <?= $item['complete'] ? 'is-complete' : 'is-missing' ?>">
                  <span class="app-review__checklist-icon">
                    <?php if ($item['complete']): ?>
                      <i class="fas fa-check-circle"></i>
                    <?php else: ?>
                      <i class="fas fa-exclamation-circle"></i>
                    <?php endif; ?>
                  </span>
                  <span class="app-review__checklist-label"><?= e($item['label']) ?></span>
                  <?php if (!$item['complete'] && !empty($item['missing'])): ?>
                    <span class="app-review__checklist-missing">Missing: <?= e(implode(', ', array_slice($item['missing'], 0, 3))) ?></span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <!-- ===== PERSONAL INFORMATION ===== -->
          <section class="app-review__section" id="review-personal">
            <div class="app-review__section-header">
              <div class="app-review__section-icon"><i class="fas fa-user"></i></div>
              <div>
                <h2>Personal Information</h2>
                <p>Information from your candidate profile.</p>
              </div>
              <a href="<?= url('candidate/application_form.php?id=' . (int) $application['id'] . '&step=1') ?>" class="btn btn--outline btn--sm">
                <i class="fas fa-edit"></i> Edit
              </a>
            </div>

            <?php if (!$completeness['items']['personal_information']['complete'] ?? false): ?>
              <div class="app-review__missing-box">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Some required personal information is missing. Please update your profile before continuing.</span>
                <a href="<?= url('candidate/profile.php?return=application&id=' . (int) $application['id']) ?>" class="btn btn--outline btn--sm">
                  <i class="fas fa-user-edit"></i> Edit Personal Information
                </a>
              </div>
            <?php endif; ?>

            <div class="app-form__info-grid">
              <div class="app-form__info-item">
                <span class="app-form__info-label">Full Name</span>
                <span class="app-form__info-value"><?= e($fullName ?: 'Not provided') ?></span>
              </div>
              <div class="app-form__info-item">
                <span class="app-form__info-label">Email</span>
                <span class="app-form__info-value"><?= e($candidateUser['email'] ?? 'Not provided') ?></span>
              </div>
              <div class="app-form__info-item">
                <span class="app-form__info-label">Phone</span>
                <span class="app-form__info-value"><?= e($candidateUser['phone'] ?? 'Not provided') ?></span>
              </div>
              <div class="app-form__info-item">
                <span class="app-form__info-label">Location</span>
                <span class="app-form__info-value">
                  <?php
                  $city = $candidateProfile['city'] ?? '';
                  $province = $candidateUser['province'] ?? '';
                  $location = trim($city . ($city && $province ? ', ' : '') . ($province ? ucwords(str_replace('-', ' ', $province)) : ''));
                  echo $location !== '' ? e($location) : 'Not provided';
                  ?>
                </span>
              </div>
              <div class="app-form__info-item">
                <span class="app-form__info-label">Qualification</span>
                <span class="app-form__info-value">
                  <?php if (!empty($candidateUser['qualification_level'])): ?>
                    <?= e(qualification_label($candidateUser['qualification_level'])) ?>
                  <?php else: ?>
                    Not provided
                  <?php endif; ?>
                </span>
              </div>
              <div class="app-form__info-item">
                <span class="app-form__info-label">Field of Study</span>
                <span class="app-form__info-value">
                  <?php
                  $fieldOfStudy = '';
                  if (!empty($qualifications)) {
                      $fieldOfStudy = $qualifications[0]['name'] ?? '';
                  }
                  echo $fieldOfStudy !== '' ? e($fieldOfStudy) : 'Not provided';
                  ?>
                </span>
              </div>
              <div class="app-form__info-item app-form__info-item--full">
                <span class="app-form__info-label">Skills</span>
                <span class="app-form__info-value">
                  <?php if (!empty($skills)): ?>
                    <div class="app-form__skill-tags">
                      <?php foreach ($skills as $skill): ?>
                        <span class="skill-tag"><?= e($skill['name']) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    Not provided
                  <?php endif; ?>
                </span>
              </div>
              <div class="app-form__info-item app-form__info-item--full">
                <span class="app-form__info-label">Work Experience</span>
                <span class="app-form__info-value">
                  <?php if (!empty($experiences)): ?>
                    <div class="app-form__list">
                      <?php foreach ($experiences as $exp): ?>
                        <div class="app-form__list-item">
                          <span class="app-form__list-name"><?= e($exp['job_title']) ?></span>
                          <span class="app-form__list-meta">
                            <?= e($exp['company'] ?? '') ?>
                            <?php if (!empty($exp['start_date'])): ?> • <?= e(format_date($exp['start_date'], 'M Y')) ?><?php endif; ?>
                            <?php if (!empty($exp['end_date'])): ?> – <?= e(format_date($exp['end_date'], 'M Y')) ?><?php endif; ?>
                            <?php if (!empty($exp['is_current'])): ?> – Present<?php endif; ?>
                          </span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    Not provided
                  <?php endif; ?>
                </span>
              </div>
            </div>
          </section>

          <!-- ===== ELIGIBILITY REVIEW ===== -->
          <section class="app-review__section" id="review-eligibility">
            <div class="app-review__section-header">
              <div class="app-review__section-icon"><i class="fas fa-clipboard-check"></i></div>
              <div>
                <h2>Eligibility</h2>
                <p>Your eligibility information for this opportunity.</p>
              </div>
              <a href="<?= url('candidate/application_form.php?id=' . (int) $application['id'] . '&step=2') ?>" class="btn btn--outline btn--sm">
                <i class="fas fa-edit"></i> Edit
              </a>
            </div>

            <?php if (!empty($eligibilityCheck['requirements'])): ?>
              <div class="app-form__eligibility">
                <?php foreach ($eligibilityCheck['requirements'] as $req): ?>
                  <div class="app-form__eligibility-item <?= $req['met'] ? 'app-form__eligibility-item--met' : 'app-form__eligibility-item--review' ?>">
                    <div class="app-form__eligibility-icon">
                      <?php if ($req['met']): ?>
                        <i class="fas fa-check-circle"></i>
                      <?php else: ?>
                        <i class="fas fa-exclamation-triangle"></i>
                      <?php endif; ?>
                    </div>
                    <div class="app-form__eligibility-content">
                      <span class="app-form__eligibility-label"><?= e($req['label']) ?></span>
                      <span class="app-form__eligibility-value"><?= e($req['candidate_value']) ?></span>
                      <?php if (!empty($req['detail']) && !$req['met']): ?>
                        <span class="app-form__eligibility-detail"><?= e($req['detail']) ?></span>
                      <?php endif; ?>
                    </div>
                    <span class="app-form__eligibility-status">
                      <?= $req['met'] ? '✓ Meets requirement' : 'Requires review' ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="app-form__empty">
                <i class="fas fa-check-circle"></i>
                <p>No specific eligibility requirements configured for this opportunity.</p>
              </div>
            <?php endif; ?>

            <?php if (!empty($questions['eligibility'])): ?>
              <div class="app-review__subsection">
                <h3 class="app-form__subsection-title"><i class="fas fa-question-circle"></i> Eligibility Questions</h3>
                <div class="app-review__responses">
                  <?php foreach ($questions['eligibility'] as $q): ?>
                    <div class="app-review__response">
                      <div class="app-review__response-q"><?= e($q['question_text']) ?></div>
                      <div class="app-review__response-a">
                        <?php
                        $value = trim((string) ($responses[$q['id']] ?? ''));
                        echo $value !== '' ? nl2br(e($value)) : '<em>Not provided</em>';
                        ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </section>

          <!-- ===== APPLICATION QUESTIONS REVIEW ===== -->
          <section class="app-review__section" id="review-questions">
            <div class="app-review__section-header">
              <div class="app-review__section-icon"><i class="fas fa-pen"></i></div>
              <div>
                <h2>Application Questions</h2>
                <p>Your responses to the opportunity-specific questions.</p>
              </div>
              <a href="<?= url('candidate/application_form.php?id=' . (int) $application['id'] . '&step=3') ?>" class="btn btn--outline btn--sm">
                <i class="fas fa-edit"></i> Edit
              </a>
            </div>

            <?php if (!empty($questions['application'])): ?>
              <div class="app-review__responses">
                <?php foreach ($questions['application'] as $q): ?>
                  <div class="app-review__response">
                    <div class="app-review__response-q">
                      <?= e($q['question_text']) ?>
                      <?php if (!empty($q['is_required'])): ?><span class="form-label__required">*</span><?php endif; ?>
                    </div>
                    <div class="app-review__response-a">
                      <?php
                      $value = trim((string) ($responses[$q['id']] ?? ''));
                      echo $value !== '' ? nl2br(e($value)) : '<span class="app-review__not-provided">Not provided</span>';
                      ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="app-form__empty">
                <i class="fas fa-info-circle"></i>
                <p>No additional questions for this opportunity.</p>
              </div>
            <?php endif; ?>
          </section>

          <!-- ===== DOCUMENT REVIEW ===== -->
          <section class="app-review__section" id="review-documents">
            <div class="app-review__section-header">
              <div class="app-review__section-icon"><i class="fas fa-folder-open"></i></div>
              <div>
                <h2>Documents</h2>
                <p>All documents attached to your application.</p>
              </div>
              <a href="<?= url('candidate/application_form.php?id=' . (int) $application['id'] . '&step=4') ?>" class="btn btn--outline btn--sm">
                <i class="fas fa-edit"></i> Edit Documents
              </a>
            </div>

            <?php if (!$docValidation['valid']): ?>
              <div class="app-review__missing-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                  <strong>Your application is incomplete.</strong>
                  <p>Missing required <?= count($docValidation['missing']) === 1 ? 'document' : 'documents' ?>:</p>
                  <ul class="app-review__missing-list">
                    <?php foreach ($docValidation['missing'] as $missingDoc): ?>
                      <li>– <?= e($missingDoc) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <a href="<?= url('candidate/application_form.php?id=' . (int) $application['id'] . '&step=4') ?>" class="btn btn--primary btn--sm">
                  <i class="fas fa-file-upload"></i> Complete Documents
                </a>
              </div>
            <?php endif; ?>

            <div class="app-review__documents">
              <?php foreach ($documentData['items'] as $item): ?>
                <?php
                  $docType = e($item['document_type']);
                  $docName = e($item['document_name']);
                  $isRequired = $item['is_required'];
                  $appDoc = $item['application_doc'];
                  $appDocId = $appDoc ? (int) $appDoc['id'] : 0;
                  $origName = $appDoc ? e($appDoc['original_filename']) : '';
                  $fileSize = $appDoc ? (int) $appDoc['file_size'] : 0;
                  $mime = $appDoc ? strtoupper(pathinfo($appDoc['original_filename'] ?? '', PATHINFO_EXTENSION)) : '';
                  $sizeLabel = $fileSize > 0 ? number_format($fileSize / (1024 * 1024), 1) . ' MB' : '';
                ?>
                <div class="app-review__doc-card <?= $appDoc ? '' : 'app-review__doc-card--missing' ?>">
                  <div class="app-review__doc-icon">
                    <i class="fas <?= $appDoc ? 'fa-file-alt' : 'fa-file-upload' ?>"></i>
                  </div>
                  <div class="app-review__doc-details">
                    <div class="app-review__doc-title">
                      <?= $docName ?>
                      <span class="badge <?= $isRequired ? 'badge--primary' : 'badge--muted' ?>">
                        <?= $isRequired ? 'Required' : 'Optional' ?>
                      </span>
                    </div>
                    <?php if ($appDoc): ?>
                      <div class="app-review__doc-file">
                        <i class="fas fa-check-circle app-review__doc-ok"></i>
                        <span class="app-review__doc-filename"><?= $origName ?></span>
                        <span class="app-review__doc-meta">
                          <?= $mime ?> • <?= $sizeLabel ?>
                        </span>
                      </div>
                    <?php else: ?>
                      <div class="app-review__doc-empty">
                        <i class="fas fa-exclamation-circle"></i> Not uploaded yet
                      </div>
                    <?php endif; ?>
                  </div>
                  <?php if ($appDoc): ?>
                    <a href="<?= url('candidate/application_document.php?id=' . $appDocId . '&mode=preview') ?>" target="_blank" class="btn btn--ghost btn--sm">
                      <i class="fas fa-eye"></i> View
                    </a>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <!-- ===== DECLARATION ===== -->
          <section class="app-review__section" id="review-declaration">
            <div class="app-review__section-header">
              <div class="app-review__section-icon"><i class="fas fa-shield-alt"></i></div>
              <div>
                <h2>Declaration</h2>
                <p>Please read the declaration carefully before proceeding.</p>
              </div>
            </div>

            <div class="app-review__declaration">
              <p class="app-review__declaration-text">
                "I declare that the information provided in this application is true, accurate and complete to the best of my knowledge."
              </p>

              <label class="app-review__checkbox">
                <input type="checkbox" name="declaration" value="1" id="declarationCheckbox" <?= $confirmation['declaration_confirmed'] ? 'checked' : '' ?>>
                <span class="app-review__checkbox-text">I confirm that the information provided is accurate and complete.</span>
              </label>
              <div class="app-review__field-error" id="declarationError"></div>
            </div>
          </section>

          <!-- ===== PRIVACY / CONSENT ===== -->
          <?php
          $consentDefs = [
              CONSENT_PROGRAMME => [
                  'label' => 'Programme Administration',
                  'desc'  => 'Allows Investhood IT to process your information to administer and manage your programme participation, including evaluating your application.',
                  'why'   => 'Required to manage your enrolment, progress and programme records.',
                  'required' => true,
              ],
              CONSENT_CLIENT_SUBMISSION => [
                  'label' => 'Client / Recruitment Opportunities',
                  'desc'  => 'Allows your profile to be submitted to client organisations for recruitment opportunities, placement, and recruitment submissions.',
                  'why'   => 'Required for placement and recruitment submissions.',
                  'required' => false,
              ],
              CONSENT_FUTURE_OPPORTUNITIES => [
                  'label' => 'Future Opportunities',
                  'desc'  => 'Allows your profile to be used to match you with future opportunities, internships and programmes.',
                  'why'   => 'Helps us surface opportunities that fit your skills and goals.',
                  'required' => false,
              ],
          ];
          ?>
          <section class="app-review__section" id="review-consent">
            <div class="app-review__section-header">
              <div class="app-review__section-icon"><i class="fas fa-user-shield"></i></div>
              <div>
                <h2>Privacy & Consent</h2>
                <p>Your personal information is processed for the purposes below. Optional consent is not required to submit.</p>
              </div>
            </div>

            <div class="app-review__consent-list">
              <?php foreach ($consentDefs as $purpose => $def): ?>
                <?php
                  $st = $consentState[$purpose] ?? ['status' => 'never', 'granted_at' => null, 'withdrawn_at' => null];
                  $isGranted = $st['status'] === 'granted';
                ?>
                <div class="app-review__consent-item">
                  <div class="app-review__consent-info">
                    <strong><?= e($def['label']) ?></strong>
                    <span><?= e($def['desc']) ?></span>
                    <?php if ($def['required']): ?>
                      <span class="tag tag--primary">Required</span>
                    <?php else: ?>
                      <span class="tag tag--cyan">Optional</span>
                    <?php endif; ?>
                    <span class="app-review__consent-status <?= $isGranted ? 'app-review__consent-status--granted' : '' ?>">
                      <i class="fas <?= $isGranted ? 'fa-check-circle' : 'fa-minus-circle' ?>"></i>
                      <?= $isGranted ? 'Granted' : 'Not granted' ?>
                    </span>
                  </div>
                  <label class="app-review__checkbox">
                    <input type="checkbox" name="consent_purposes[]" value="<?= e($purpose) ?>" class="consent-checkbox"
                      <?= in_array($purpose, $confirmation['consent_purposes'], true) ? 'checked' : '' ?>
                      <?= $def['required'] ? 'required' : '' ?>>
                    <span class="app-review__checkbox-text">I confirm my <?= strtolower($def['label']) ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="app-review__field-error" id="consentError"></div>
          </section>

          <!-- ===== SUBMISSION READINESS ===== -->
          <section class="app-review__section app-review__section--ready" id="review-ready">
            <div class="app-review__section-header">
              <div class="app-review__section-icon"><i class="fas fa-paper-plane"></i></div>
              <div>
                <h2>Application Ready</h2>
                <p>Your application is ready to be submitted.</p>
              </div>
            </div>
            <div class="app-review__readiness">
              <div class="app-review__readiness-item <?= ($completeness['items']['personal_information']['complete'] ?? false) ? '' : 'app-review__readiness-item--missing' ?>">
                <i class="fas <?= ($completeness['items']['personal_information']['complete'] ?? false) ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                Personal information completed
              </div>
              <div class="app-review__readiness-item <?= ($completeness['items']['eligibility']['complete'] ?? false) ? '' : 'app-review__readiness-item--missing' ?>">
                <i class="fas <?= ($completeness['items']['eligibility']['complete'] ?? false) ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                Eligibility completed
              </div>
              <div class="app-review__readiness-item <?= ($completeness['items']['application_questions']['complete'] ?? false) ? '' : 'app-review__readiness-item--missing' ?>">
                <i class="fas <?= ($completeness['items']['application_questions']['complete'] ?? false) ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                Application questions completed
              </div>
              <div class="app-review__readiness-item <?= ($completeness['items']['documents']['complete'] ?? false) ? '' : 'app-review__readiness-item--missing' ?>">
                <i class="fas <?= ($completeness['items']['documents']['complete'] ?? false) ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                Required documents uploaded
              </div>
              <div class="app-review__readiness-item <?= $confirmation['declaration_confirmed'] ? '' : 'app-review__readiness-item--missing' ?>">
                <i class="fas <?= $confirmation['declaration_confirmed'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                Declaration completed
              </div>
            </div>
            <p class="app-review__ready-text">Your application is ready to be submitted.</p>
          </section>

          <!-- ===== ACTIONS ===== -->
          <div class="app-review__actions">
            <a href="<?= url('candidate/application_detail.php?id=' . (int) $application['id']) ?>" class="btn btn--ghost">
              <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($closingStatus): ?>
              <button type="button" class="btn btn--primary" id="continueSubmissionBtn" <?= $completeness['all_complete'] ? '' : 'disabled' ?>>
                Continue to Submission <i class="fas fa-arrow-right"></i>
              </button>
            <?php else: ?>
              <span class="app-review__disabled-hint">Applications for this opportunity are now closed.</span>
            <?php endif; ?>
          </div>

          <!-- ===== SAVE STATUS ===== -->
          <div class="app-review__save-status" id="reviewStatus" role="status" aria-live="polite"></div>
        </form>

      </div>
    </main>

  </div>

  <!-- ===== SCRIPTS ===== -->
  <script src="<?= url('js/applications.js') ?>"></script>
  <script src="<?= url('js/application_review.js') ?>"></script>

</body>
</html>