<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Application Form
 * ================================================
 * Multi-step application form
 * (Personal Info → Eligibility → Questions → Documents).
 * Uses the existing Draft application; never creates a new one.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId   = (int) current_user_id();
$user          = current_user();
$applicationId = (int) ($_GET['id'] ?? 0);

// Load form data with ownership + status checks
$formData = ApplicationFormController::loadForm($candidateId, $applicationId);

if ($formData === null) {
    set_flash('error', 'Application not found.', 'The requested application does not exist.');
    redirect('candidate/applications.php');
}

$application = $formData['application'];
$opportunity = $formData['opportunity'] ?? null;
$readonly    = $formData['readonly'] ?? false;
$errorMsg    = $formData['error'] ?? null;

// If readonly (submitted/closed), show read-only message
if ($readonly) {
    $flashes = render_flashes();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Application | Investhood IT</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
      <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
      <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
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

// Normal form data
$candidateUser = $formData['user'] ?? [];
$candidateProfile = $formData['profile'] ?? [];
$qualifications = $formData['qualifications'] ?? [];
$skills = $formData['skills'] ?? [];
$experiences = $formData['experiences'] ?? [];
$questions = $formData['questions'] ?? ['eligibility' => [], 'application' => []];
$responses = $formData['responses'] ?? [];
$eligibility = $formData['eligibility'] ?? [];
$documentData = $formData['documents'] ?? ['requirements' => [], 'application_docs' => [], 'profile_docs' => [], 'items' => []];

// Check for missing profile info
$personalValidation = ApplicationFormController::validatePersonalInfo($candidateUser, $candidateProfile);
$hasMissingInfo = !$personalValidation['valid'];

// Check eligibility
$eligibilityCheck = ApplicationFormController::checkEligibility(
    [
        'user'           => $candidateUser,
        'profile'        => $candidateProfile,
        'qualifications' => $qualifications,
        'skills'         => $skills,
        'experiences'    => $experiences,
    ],
    $eligibility
);

$fullName = trim(($candidateUser['first_name'] ?? '') . ' ' . ($candidateUser['last_name'] ?? ''));
$flashes = render_flashes();

// Helper to render a question input
function render_question_input(array $question, string $value = ''): string
{
    $type = (string) ($question['question_type'] ?? 'text');
    $name = 'responses[' . (int) $question['id'] . ']';
    $required = !empty($question['is_required']) ? ' required' : '';
    $id = 'q_' . (int) $question['id'];
    $options = $question['options'] ?? null;

    $html = '';

    switch ($type) {
        case 'textarea':
            $html .= '<textarea id="' . $id . '" name="' . $name . '" class="form-input" rows="4" placeholder="Your answer"' . $required . '>' . e($value) . '</textarea>';
            break;

        case 'yes_no':
            $html .= '<div class="app-form__radio-group">';
            foreach (['yes' => 'Yes', 'no' => 'No'] as $val => $label) {
                $checked = ($value === $val) ? ' checked' : '';
                $html .= '<label class="app-form__radio-label">';
                $html .= '<input type="radio" name="' . $name . '" value="' . $val . '"' . $checked . $required . '>';
                $html .= '<span>' . $label . '</span>';
                $html .= '</label>';
            }
            $html .= '</div>';
            break;

        case 'radio':
            $html .= '<div class="app-form__radio-group">';
            if (is_array($options)) {
                foreach ($options as $opt) {
                    $checked = ($value === $opt) ? ' checked' : '';
                    $html .= '<label class="app-form__radio-label">';
                    $html .= '<input type="radio" name="' . $name . '" value="' . e($opt) . '"' . $checked . $required . '>';
                    $html .= '<span>' . e($opt) . '</span>';
                    $html .= '</label>';
                }
            }
            $html .= '</div>';
            break;

        case 'dropdown':
            $html .= '<select id="' . $id . '" name="' . $name . '" class="form-input form-input--select"' . $required . '>';
            $html .= '<option value="">Select an option</option>';
            if (is_array($options)) {
                foreach ($options as $opt) {
                    $selected = ($value === $opt) ? ' selected' : '';
                    $html .= '<option value="' . e($opt) . '"' . $selected . '>' . e($opt) . '</option>';
                }
            }
            $html .= '</select>';
            break;

        case 'checkbox':
            $html .= '<div class="app-form__checkbox-group">';
            if (is_array($options)) {
                $selectedValues = array_filter(array_map('trim', explode(',', $value)));
                foreach ($options as $opt) {
                    $checked = in_array($opt, $selectedValues, true) ? ' checked' : '';
                    $html .= '<label class="app-form__checkbox-label">';
                    $html .= '<input type="checkbox" name="' . $name . '[]" value="' . e($opt) . '"' . $checked . '>';
                    $html .= '<span>' . e($opt) . '</span>';
                    $html .= '</label>';
                }
            }
            $html .= '</div>';
            break;

        case 'number':
            $html .= '<input type="number" id="' . $id . '" name="' . $name . '" class="form-input" value="' . e($value) . '"' . $required . '>';
            break;

        case 'date':
            $html .= '<input type="date" id="' . $id . '" name="' . $name . '" class="form-input" value="' . e($value) . '"' . $required . '>';
            break;

        default: // text
            $html .= '<input type="text" id="' . $id . '" name="' . $name . '" class="form-input" value="' . e($value) . '" placeholder="Your answer"' . $required . '>';
            break;
    }

    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Application Form - <?= e($opportunity['title'] ?? '') ?> - Investhood IT">
  <title>Application Form | Investhood IT</title>
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

        <!-- ===== FORM HEADER ===== -->
        <section class="app-form__header">
          <div class="app-form__header-badges">
            <span class="badge badge--muted"><?= e(Application::label($application['status'])) ?></span>
            <?php if (!empty($application['application_reference'])): ?>
              <span class="app-form__ref"><i class="fas fa-hashtag"></i> <?= e($application['application_reference']) ?></span>
            <?php endif; ?>
          </div>
          <h1 class="app-form__title"><?= e($opportunity['title'] ?? 'Application') ?></h1>
          <p class="app-form__subtitle">
            <i class="fas fa-graduation-cap"></i>
            <?= e($opportunity['programme_name'] ?? '') ?>
            <?php if (!empty($opportunity['cohort_name'])): ?>
              <span class="app-form__separator">•</span>
              <span><?= e($opportunity['cohort_name']) ?></span>
            <?php endif; ?>
          </p>
        </section>

        <!-- ===== PROGRESS INDICATOR ===== -->
        <section class="app-form__progress" aria-label="Application progress">
          <div class="app-form__progress-steps">
            <div class="app-form__step app-form__step--active" data-step-indicator="1">
              <span class="app-form__step-num">1</span>
              <span class="app-form__step-label">Personal Information</span>
            </div>
            <div class="app-form__step-line"></div>
            <div class="app-form__step" data-step-indicator="2">
              <span class="app-form__step-num">2</span>
              <span class="app-form__step-label">Eligibility</span>
            </div>
            <div class="app-form__step-line"></div>
            <div class="app-form__step" data-step-indicator="3">
              <span class="app-form__step-num">3</span>
              <span class="app-form__step-label">Application Questions</span>
            </div>
            <div class="app-form__step-line"></div>
            <div class="app-form__step" data-step-indicator="4">
              <span class="app-form__step-num">4</span>
              <span class="app-form__step-label">Documents</span>
            </div>
          </div>
          <div class="app-form__progress-text">
            <span id="stepCount">Step 1 of 4</span>
            <span id="stepName">Personal Information</span>
          </div>
        </section>

        <!-- ===== FORM ===== -->
        <form id="applicationForm" class="app-form" method="post" action="<?= url('candidate/application_actions.php') ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_step">
          <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
          <input type="hidden" name="current_step" id="currentStep" value="1">

          <!-- ===== STEP 1: PERSONAL INFORMATION ===== -->
          <div class="app-form__step-panel" data-step-panel="1">
            <div class="app-form__card">
              <div class="app-form__card-header">
                <div class="app-form__card-icon"><i class="fas fa-user"></i></div>
                <div>
                  <h2>Personal Information</h2>
                  <p>Your information from your candidate profile. Update your profile if anything is missing.</p>
                </div>
              </div>

              <?php if ($hasMissingInfo): ?>
              <div class="app-form__alert app-form__alert--warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                  <strong>Some profile information is missing.</strong>
                  <p>Please update your profile to continue with your application.</p>
                  <a href="<?= url('candidate/profile.php?return=application&id=' . (int) $application['id']) ?>" class="btn btn--outline btn--sm">
                    <i class="fas fa-user-edit"></i> Update Profile
                  </a>
                </div>
              </div>
              <?php endif; ?>

              <div class="app-form__info-grid">
                <div class="app-form__info-item">
                  <span class="app-form__info-label">Full Name</span>
                  <span class="app-form__info-value"><?= e($fullName ?: 'Not specified') ?></span>
                </div>
                <div class="app-form__info-item">
                  <span class="app-form__info-label">Email Address</span>
                  <span class="app-form__info-value"><?= e($candidateUser['email'] ?? 'Not specified') ?></span>
                </div>
                <div class="app-form__info-item">
                  <span class="app-form__info-label">Phone Number</span>
                  <span class="app-form__info-value"><?= e($candidateUser['phone'] ?? 'Not specified') ?></span>
                </div>
                <div class="app-form__info-item">
                  <span class="app-form__info-label">Province</span>
                  <span class="app-form__info-value"><?= e(ucwords(str_replace('-', ' ', $candidateUser['province'] ?? 'Not specified'))) ?></span>
                </div>
                <div class="app-form__info-item">
                  <span class="app-form__info-label">City</span>
                  <span class="app-form__info-value"><?= e($candidateProfile['city'] ?? 'Not specified') ?></span>
                </div>
                <div class="app-form__info-item">
                  <span class="app-form__info-label">Qualification</span>
                  <span class="app-form__info-value">
                    <?php if (!empty($candidateUser['qualification_level'])): ?>
                      <?= e(qualification_label($candidateUser['qualification_level'])) ?>
                    <?php else: ?>
                      Not specified
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
                    echo $fieldOfStudy !== '' ? e($fieldOfStudy) : 'Not specified';
                    ?>
                  </span>
                </div>
                <div class="app-form__info-item">
                  <span class="app-form__info-label">Employment Status</span>
                  <span class="app-form__info-value">
                    <?php
                    $empStatus = $candidateProfile['employment_status'] ?? $candidateUser['employment_status'] ?? '';
                    echo $empStatus !== '' ? e(ucwords(str_replace('-', ' ', $empStatus))) : 'Not specified';
                    ?>
                  </span>
                </div>
              </div>

              <?php if (!empty($qualifications)): ?>
              <div class="app-form__subsection">
                <h3 class="app-form__subsection-title"><i class="fas fa-graduation-cap"></i> Qualifications</h3>
                <div class="app-form__list">
                  <?php foreach ($qualifications as $q): ?>
                  <div class="app-form__list-item">
                    <span class="app-form__list-name"><?= e($q['name']) ?></span>
                    <span class="app-form__list-meta">
                      <?= e($q['institution'] ?? '') ?>
                      <?php if (!empty($q['year_completed'])): ?> • <?= e($q['year_completed']) ?><?php endif; ?>
                    </span>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if (!empty($skills)): ?>
              <div class="app-form__subsection">
                <h3 class="app-form__subsection-title"><i class="fas fa-code"></i> Skills</h3>
                <div class="app-form__skill-tags">
                  <?php foreach ($skills as $skill): ?>
                  <span class="skill-tag"><?= e($skill['name']) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if (!empty($experiences)): ?>
              <div class="app-form__subsection">
                <h3 class="app-form__subsection-title"><i class="fas fa-briefcase"></i> Work Experience</h3>
                <div class="app-form__list">
                  <?php foreach ($experiences as $exp): ?>
                  <div class="app-form__list-item">
                    <span class="app-form__list-name"><?= e($exp['job_title']) ?></span>
                    <span class="app-form__list-meta">
                      <?= e($exp['company']) ?>
                      <?php if (!empty($exp['start_date'])): ?> • <?= e(format_date($exp['start_date'], 'M Y')) ?><?php endif; ?>
                      <?php if (!empty($exp['end_date'])): ?> – <?= e(format_date($exp['end_date'], 'M Y')) ?><?php endif; ?>
                      <?php if (!empty($exp['is_current'])): ?> – Present<?php endif; ?>
                    </span>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <div class="app-form__actions">
                <a href="<?= url('candidate/profile.php?return=application&id=' . (int) $application['id']) ?>" class="btn btn--outline">
                  <i class="fas fa-user-edit"></i> Update Profile
                </a>
                <button type="button" class="btn btn--primary" data-next-step="2">
                  Continue <i class="fas fa-arrow-right"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- ===== STEP 2: ELIGIBILITY ===== -->
          <div class="app-form__step-panel" data-step-panel="2" hidden>
            <div class="app-form__card">
              <div class="app-form__card-header">
                <div class="app-form__card-icon"><i class="fas fa-clipboard-check"></i></div>
                <div>
                  <h2>Eligibility</h2>
                  <p>Review the eligibility requirements for this opportunity.</p>
                </div>
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
                    <?php if (!empty($req['detail'])): ?>
                      <span class="app-form__eligibility-detail"><?= e($req['detail']) ?></span>
                    <?php endif; ?>
                  </div>
                  <span class="app-form__eligibility-status">
                    <?= $req['met'] ? '✓' : '⚠ This requirement may need review.' ?>
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
              <div class="app-form__subsection">
                <h3 class="app-form__subsection-title"><i class="fas fa-question-circle"></i> Eligibility Questions</h3>
                <p class="app-form__subsection-desc">Please answer the following questions to confirm your eligibility.</p>

                <?php foreach ($questions['eligibility'] as $q): ?>
                <div class="app-form__question" data-question-id="<?= (int) $q['id'] ?>" data-knockout="<?= !empty($q['is_knockout']) ? '1' : '0' ?>">
                  <label class="app-form__question-label">
                    <?= e($q['question_text']) ?>
                    <?php if (!empty($q['is_required'])): ?><span class="form-label__required">*</span><?php endif; ?>
                    <?php if (!empty($q['is_knockout'])): ?>
                      <span class="app-form__knockout-badge"><i class="fas fa-exclamation"></i> Eligibility</span>
                    <?php endif; ?>
                  </label>
                  <?= render_question_input($q, $responses[$q['id']] ?? '') ?>
                  <div class="form-error" data-error-for="responses[<?= (int) $q['id'] ?>]"></div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <div class="app-form__actions">
                <button type="button" class="btn btn--ghost" data-prev-step="1"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="btn btn--primary" data-next-step="3">
                  Continue <i class="fas fa-arrow-right"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- ===== STEP 3: APPLICATION QUESTIONS ===== -->
          <div class="app-form__step-panel" data-step-panel="3" hidden>
            <div class="app-form__card">
              <div class="app-form__card-header">
                <div class="app-form__card-icon"><i class="fas fa-pen"></i></div>
                <div>
                  <h2>Application Questions</h2>
                  <p>Tell us more about why you're a great fit for this opportunity.</p>
                </div>
              </div>

              <?php if (!empty($questions['application'])): ?>
                <?php foreach ($questions['application'] as $q): ?>
                <div class="app-form__question" data-question-id="<?= (int) $q['id'] ?>">
                  <label class="app-form__question-label">
                    <?= e($q['question_text']) ?>
                    <?php if (!empty($q['is_required'])): ?><span class="form-label__required">*</span><?php endif; ?>
                  </label>
                  <?= render_question_input($q, $responses[$q['id']] ?? '') ?>
                  <div class="form-error" data-error-for="responses[<?= (int) $q['id'] ?>]"></div>
                </div>
                <?php endforeach; ?>
              <?php else: ?>
              <div class="app-form__empty">
                <i class="fas fa-info-circle"></i>
                <p>No additional questions for this opportunity.</p>
              </div>
              <?php endif; ?>

              <div class="app-form__actions">
                <button type="button" class="btn btn--ghost" data-prev-step="2"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="btn btn--primary" data-next-step="4">
                  Continue <i class="fas fa-arrow-right"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- ===== STEP 4: DOCUMENTS ===== -->
          <div class="app-form__step-panel" data-step-panel="4" hidden>
            <div class="app-form__card">
              <div class="app-form__card-header">
                <div class="app-form__card-icon"><i class="fas fa-folder-open"></i></div>
                <div>
                  <h2>Documents</h2>
                  <p>Upload the required documents for this opportunity. You can also reuse documents from your profile.</p>
                </div>
              </div>

              <?php if (empty($documentData['items'])): ?>
              <div class="app-form__empty">
                <i class="fas fa-folder-open"></i>
                <p>No documents are required for this opportunity.</p>
              </div>
              <?php else: ?>
              <div class="app-form__documents" id="appDocuments">
                <?php foreach ($documentData['items'] as $item): ?>
                <?php
                  $docType = e($item['document_type']);
                  $docName = e($item['document_name']);
                  $isRequired = $item['is_required'];
                  $appDoc = $item['application_doc'];
                  $candidateDoc = $item['candidate_doc'];
                  $appDocId = $appDoc ? (int) $appDoc['id'] : 0;
                  $origName = $appDoc ? e($appDoc['original_filename']) : '';
                  $fileSize = $appDoc ? (int) $appDoc['file_size'] : 0;
                  $uploadedOn = $appDoc ? e(format_date($appDoc['created_at'], 'd M Y')) : '';
                  $mime = $appDoc ? e($appDoc['mime_type']) : '';
                ?>
                <div class="app-form__doc-item" data-doc-type="<?= $docType ?>" data-doc-id="<?= $appDocId ?>">
                  <div class="app-form__doc-info">
                    <div class="app-form__doc-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="app-form__doc-details">
                      <div class="app-form__doc-title">
                        <?= $docName ?>
                        <span class="badge <?= $isRequired ? 'badge--primary' : 'badge--muted' ?>">
                          <?= $isRequired ? 'Required' : 'Optional' ?>
                        </span>
                      </div>

                      <?php if ($appDoc): ?>
                      <!-- Uploaded document state -->
                      <div class="app-form__doc-file">
                        <i class="fas fa-check-circle app-form__doc-ok"></i>
                        <span class="app-form__doc-filename"><?= $origName ?></span>
                        <span class="app-form__doc-meta">
                          <?= $mime ?> • <?= $fileSize > 0 ? number_format($fileSize / (1024 * 1024), 1) . ' MB' : '' ?> • <?= $uploadedOn ?>
                        </span>
                      </div>
                      <?php else: ?>
                      <!-- No document yet -->
                      <div class="app-form__doc-empty-message">
                        <?= $isRequired ? 'Upload your ' . $item['document_name'] . ' to continue.' : 'Upload this document if you have it.' ?>
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php if ($appDoc): ?>
                  <!-- Actions when document exists -->
                  <div class="app-form__doc-actions">
                    <a href="<?= url('candidate/application_document.php?id=' . $appDocId . '&mode=preview') ?>" target="_blank" class="btn btn--ghost btn--sm doc-view">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <button type="button" class="btn btn--ghost btn--sm doc-replace" data-doc-id="<?= $appDocId ?>" data-doc-type="<?= $docType ?>">
                      <i class="fas fa-sync-alt"></i> Replace
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm btn--danger doc-remove" data-doc-id="<?= $appDocId ?>" data-doc-type="<?= $docType ?>">
                      <i class="fas fa-trash"></i> Remove
                    </button>
                  </div>
                  <?php else: ?>
                  <div class="app-form__doc-actions">
                    <?php if ($candidateDoc): ?>
                    <!-- Reuse an existing candidate document -->
                    <button type="button" class="btn btn--outline btn--sm doc-reuse" data-doc-type="<?= $docType ?>" data-profile-doc-id="<?= (int) $candidateDoc['id'] ?>" title="Use <?= e($candidateDoc['original_filename']) ?> from your profile">
                      <i class="fas fa-recycle"></i> Use Existing (<?= e($candidateDoc['original_filename']) ?>)
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn--outline btn--sm doc-upload" data-doc-type="<?= $docType ?>">
                      <i class="fas fa-upload"></i> Upload
                    </button>
                  </div>
                  <?php endif; ?>

                  <div class="app-form__doc-upload-area" data-upload-area="<?= $docType ?>" hidden>
                    <input type="file" class="app-form__doc-input" data-doc-input="<?= $docType ?>" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <span class="app-form__doc-file-hint">PDF, DOC or DOCX • Max 10 MB</span>
                    <div class="app-form__doc-upload-actions">
                      <button type="button" class="btn btn--primary btn--sm doc-upload-confirm" data-doc-type="<?= $docType ?>">
                        <i class="fas fa-upload"></i> Upload
                      </button>
                      <button type="button" class="btn btn--ghost btn--sm doc-upload-cancel" data-doc-type="<?= $docType ?>">
                        Cancel
                      </button>
                    </div>
                    <div class="app-form__doc-message" data-doc-message="<?= $docType ?>"></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <div class="app-form__actions">
                <button type="button" class="btn btn--ghost" data-prev-step="3"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="submit" class="btn btn--primary" id="saveApplicationBtn">
                  <i class="fas fa-save"></i> Save Application
                </button>
              </div>
            </div>
          </div>

          <!-- ===== SAVE STATUS ===== -->
          <div class="app-form__save-status" id="saveStatus" role="status" aria-live="polite"></div>

        </form>

      </div>
    </main>

  </div>

  <!-- ===== SCRIPTS ===== -->
  <script src="<?= url('js/applications.js') ?>"></script>
  <script src="<?= url('js/application_form.js') ?>"></script>

</body>
</html>