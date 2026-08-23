<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Opportunity Details
 * ================================================
 * Display full opportunity information to a candidate.
 * Shows requirements, eligibility, documents, and
 * application status/action.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId = (int) current_user_id();
$user = current_user();

// Get opportunity ID
$oppId = (int) ($_GET['id'] ?? 0);
if (!$oppId) {
    set_flash('error', 'Opportunity not found.', 'The requested opportunity could not be found.');
    redirect('candidate/opportunities.php');
}

// Fetch full opportunity details
$opportunity = CandidateOpportunitiesController::getOpportunityDetail($oppId);
if (!$opportunity) {
    set_flash('error', 'Opportunity not found.', 'The requested opportunity could not be found.');
    redirect('candidate/opportunities.php');
}

// Check if visible to candidate (must be published and open)
$today = date('Y-m-d');
if ($opportunity['status'] !== 'published') {
    set_flash('error', 'This opportunity is not available.', 'This opportunity is no longer available.');
    redirect('candidate/opportunities.php');
}

// Check if saved
$isSaved = SavedOpportunity::isSaved($userId, $oppId);

// Check application dates
$isBeforeOpen = !empty($opportunity['application_open_date']) && $opportunity['application_open_date'] > $today;
$isAfterClosing = !empty($opportunity['application_close_date']) && $opportunity['application_close_date'] < $today;

// Determine application action based on candidate state
$appAction = CandidateApplicationsController::getApplicationAction($userId, $oppId);
$appActionType = $appAction['action'];
$existingApplication = $appAction['application'];

// Calculate days until close
$daysUntilClose = null;
if (!empty($opportunity['application_close_date']) && !$isAfterClosing) {
    $daysUntilClose = (int) ((strtotime($opportunity['application_close_date']) - time()) / 86400);
}

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($opportunity['title']) ?> - Investhood IT">
  <title><?= e($opportunity['title']) ?> | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/opportunities.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page opportunity-detail-page">

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
          <li><a href="<?= url('candidate/opportunities.php') ?>" class="sidebar__link active"><i class="fas fa-briefcase"></i> Opportunities</a></li>
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
          <a href="<?= url('candidate/opportunities.php') ?>" class="dash-header__back"><i class="fas fa-chevron-left"></i> Back</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== FLASH MESSAGES ===== -->
        <?= $flashes ?>

        <!-- ===== OPPORTUNITY HEADER ===== -->
        <section class="opp-detail__header">
          <div class="opp-detail__container">
            <div class="opp-detail__title-section">
              <div class="opp-detail__badges">
                <span class="opp-badge opp-badge--type"><?= e(CandidateOpportunitiesController::OPPORTUNITY_TYPES_DISPLAY[$opportunity['type']] ?? $opportunity['type']) ?></span>
                <?php if ($isAfterClosing): ?>
                  <span class="opp-badge opp-badge--closed">Closed</span>
                <?php elseif ($daysUntilClose !== null && $daysUntilClose <= 7): ?>
                  <span class="opp-badge opp-badge--urgent">Closing Soon</span>
                <?php endif; ?>
              </div>
              <h1 class="opp-detail__title"><?= e($opportunity['title']) ?></h1>
              <div class="opp-detail__programme">
                <i class="fas fa-graduation-cap"></i>
                <span><?= e($opportunity['programme_name']) ?></span>
                <?php if (!empty($opportunity['cohort_name'])): ?>
                  <span class="opp-detail__separator">•</span>
                  <span><?= e($opportunity['cohort_name']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="opp-detail__actions">
              <button class="opp-detail__save-btn <?= $isSaved ? 'is-saved' : '' ?>" id="saveBtn" data-opp-id="<?= (int)$oppId ?>" data-saved="<?= $isSaved ? '1' : '0' ?>">
                <i class="fas fa-bookmark"></i> <?= $isSaved ? 'Saved' : 'Save' ?>
              </button>
            </div>
          </div>

          <!-- Quick Info Bar -->
          <div class="opp-detail__quick-info">
            <div class="opp-detail__container">
              <div class="opp-quick-info">
                <?php if (!empty($opportunity['city']) || !empty($opportunity['province'])): ?>
                  <div class="opp-quick-info__item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= e($opportunity['city'] ?? $opportunity['province'] ?? 'Location TBD') ?></span>
                  </div>
                <?php endif; ?>

                <?php if (!empty($opportunity['work_arrangement'])): ?>
                  <div class="opp-quick-info__item">
                    <i class="fas fa-briefcase"></i>
                    <span><?= e(CandidateOpportunitiesController::WORK_ARRANGEMENTS_DISPLAY[$opportunity['work_arrangement']] ?? $opportunity['work_arrangement']) ?></span>
                  </div>
                <?php endif; ?>

                <?php if (!empty($opportunity['available_positions'])): ?>
                  <div class="opp-quick-info__item">
                    <i class="fas fa-users"></i>
                    <span><?= (int)$opportunity['available_positions'] ?> position<?= (int)$opportunity['available_positions'] !== 1 ? 's' : '' ?></span>
                  </div>
                <?php endif; ?>

                <?php if (!empty($opportunity['organisation'])): ?>
                  <div class="opp-quick-info__item">
                    <i class="fas fa-building"></i>
                    <span><?= e($opportunity['organisation']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </section>

        <!-- ===== CONTENT LAYOUT ===== -->
        <section class="opp-detail__content">
          <div class="opp-detail__container">
            <div class="opp-detail__main">

              <!-- Overview -->
              <?php if (!empty($opportunity['full_description'])): ?>
              <section class="opp-detail__section">
                <h2 class="opp-detail__section-title">Overview</h2>
                <div class="opp-detail__description">
                  <?= nl2br(e($opportunity['full_description'])) ?>
                </div>
              </section>
              <?php endif; ?>

              <!-- Responsibilities -->
              <?php
              $duties = array_filter($opportunity['responsibilities'] ?? [], fn($r) => $r['type'] === 'key_responsibilities');
              if (!empty($duties)):
              ?>
              <section class="opp-detail__section">
                <h2 class="opp-detail__section-title">Key Responsibilities</h2>
                <ul class="opp-detail__list">
                  <?php foreach ($duties as $duty): ?>
                    <li><?= nl2br(e($duty['content'])) ?></li>
                  <?php endforeach; ?>
                </ul>
              </section>
              <?php endif; ?>

              <!-- Programme Activities -->
              <?php
              $activities = array_filter($opportunity['responsibilities'] ?? [], fn($r) => $r['type'] === 'programme_activities');
              if (!empty($activities)):
              ?>
              <section class="opp-detail__section">
                <h2 class="opp-detail__section-title">Programme Activities</h2>
                <ul class="opp-detail__list">
                  <?php foreach ($activities as $activity): ?>
                    <li><?= nl2br(e($activity['content'])) ?></li>
                  <?php endforeach; ?>
                </ul>
              </section>
              <?php endif; ?>

              <!-- Learning Outcomes -->
              <?php
              $outcomes = array_filter($opportunity['responsibilities'] ?? [], fn($r) => $r['type'] === 'learning_outcomes');
              if (!empty($outcomes)):
              ?>
              <section class="opp-detail__section">
                <h2 class="opp-detail__section-title">Learning Outcomes</h2>
                <ul class="opp-detail__list">
                  <?php foreach ($outcomes as $outcome): ?>
                    <li><?= nl2br(e($outcome['content'])) ?></li>
                  <?php endforeach; ?>
                </ul>
              </section>
              <?php endif; ?>

              <!-- Skills -->
              <?php if (!empty($opportunity['skills'])): ?>
              <section class="opp-detail__section">
                <h2 class="opp-detail__section-title">Required Skills</h2>
                <div class="opp-detail__skills">
                  <?php foreach ($opportunity['skills'] as $skill): ?>
                    <span class="opp-skill-badge opp-skill-badge--<?= e($skill['skill_category']) ?>">
                      <?= e($skill['skill_name']) ?>
                      <?php if ($skill['skill_category'] === 'preferred_technical'): ?>
                        <span class="opp-skill-badge__label">(Preferred)</span>
                      <?php endif; ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </section>
              <?php endif; ?>

              <!-- Eligibility Requirements -->
              <?php if (!empty($opportunity['eligibility'])): ?>
              <section class="opp-detail__section">
                <h2 class="opp-detail__section-title">Eligibility Requirements</h2>
                <div class="opp-detail__eligibility">
                  <?php if (!empty($opportunity['eligibility']['qualification_requirements'])): ?>
                    <div class="opp-detail__req">
                      <h4>Qualifications</h4>
                      <p><?= nl2br(e($opportunity['eligibility']['qualification_requirements'])) ?></p>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($opportunity['eligibility']['required_skills'])): ?>
                    <div class="opp-detail__req">
                      <h4>Required Skills</h4>
                      <p><?= nl2br(e($opportunity['eligibility']['required_skills'])) ?></p>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($opportunity['eligibility']['preferred_skills'])): ?>
                    <div class="opp-detail__req">
                      <h4>Preferred Skills</h4>
                      <p><?= nl2br(e($opportunity['eligibility']['preferred_skills'])) ?></p>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($opportunity['eligibility']['min_experience'])): ?>
                    <div class="opp-detail__req">
                      <h4>Experience</h4>
                      <p><?= nl2br(e($opportunity['eligibility']['min_experience'])) ?></p>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($opportunity['eligibility']['availability_requirements'])): ?>
                    <div class="opp-detail__req">
                      <h4>Availability</h4>
                      <p><?= nl2br(e($opportunity['eligibility']['availability_requirements'])) ?></p>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($opportunity['eligibility']['other_requirements'])): ?>
                    <div class="opp-detail__req">
                      <h4>Other Requirements</h4>
                      <p><?= nl2br(e($opportunity['eligibility']['other_requirements'])) ?></p>
                    </div>
                  <?php endif; ?>
                </div>
              </section>
              <?php endif; ?>

              <!-- Required Documents -->
              <?php if (!empty($opportunity['documents'])): ?>
              <section class="opp-detail__section">
                <h2 class="opp-detail__section-title">Required Documents</h2>
                <ul class="opp-detail__documents">
                  <?php foreach ($opportunity['documents'] as $doc): ?>
                    <li class="opp-detail__document">
                      <i class="fas fa-file-alt"></i>
                      <span><?= e($doc['document_name']) ?></span>
                      <?php if ($doc['is_required']): ?>
                        <span class="opp-detail__required">Required</span>
                      <?php else: ?>
                        <span class="opp-detail__optional">Optional</span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </section>
              <?php endif; ?>

            </div>

            <!-- ===== SIDEBAR ===== -->
            <aside class="opp-detail__sidebar">

              <!-- Application Information Card -->
              <div class="opp-detail__card opp-detail__card--info">
                <h3 class="opp-detail__card-title">Application Information</h3>

                <div class="opp-detail__card-item">
                  <span class="opp-detail__card-label">Opening Date</span>
                  <span class="opp-detail__card-value">
                    <?php if (!empty($opportunity['application_open_date'])): ?>
                      <?= format_date($opportunity['application_open_date'], 'd M Y') ?>
                      <?php if ($isBeforeOpen): ?>
                        <span class="opp-detail__status-badge opp-detail__status-badge--pending">Not Yet Open</span>
                      <?php endif; ?>
                    <?php else: ?>
                      Open Now
                    <?php endif; ?>
                  </span>
                </div>

                <div class="opp-detail__card-item">
                  <span class="opp-detail__card-label">Closing Date</span>
                  <span class="opp-detail__card-value">
                    <?php if (!empty($opportunity['application_close_date'])): ?>
                      <?= format_date($opportunity['application_close_date'], 'd M Y') ?>
                      <?php if ($isAfterClosing): ?>
                        <span class="opp-detail__status-badge opp-detail__status-badge--closed">Closed</span>
                      <?php elseif ($daysUntilClose !== null && $daysUntilClose <= 7): ?>
                        <span class="opp-detail__status-badge opp-detail__status-badge--urgent">
                          <?= (int)$daysUntilClose ?> day<?= (int)$daysUntilClose !== 1 ? 's' : '' ?> left
                        </span>
                      <?php endif; ?>
                    <?php else: ?>
                      No fixed date
                    <?php endif; ?>
                  </span>
                </div>

                <?php if (!empty($opportunity['start_date']) || !empty($opportunity['end_date'])): ?>
                <div class="opp-detail__card-item">
                  <span class="opp-detail__card-label">Duration</span>
                  <span class="opp-detail__card-value">
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

              <!-- Application Action Card -->
              <div class="opp-detail__card opp-detail__card--action">
                <h3 class="opp-detail__card-title">Ready to Apply?</h3>

                <?php if ($isBeforeOpen): ?>
                  <button class="btn btn--disabled btn--full" disabled>
                    <i class="fas fa-clock"></i> Applications Not Yet Open
                  </button>
                  <p class="opp-detail__card-info">Applications open <?= format_date($opportunity['application_open_date'], 'd M Y') ?></p>

                <?php elseif ($isAfterClosing): ?>
                  <button class="btn btn--disabled btn--full" disabled>
                    <i class="fas fa-lock"></i> Applications Closed
                  </button>
                  <p class="opp-detail__card-info">Applications closed on <?= format_date($opportunity['application_close_date'], 'd M Y') ?></p>

                <?php elseif ($appActionType === 'continue' && $existingApplication): ?>
                  <a href="<?= url('candidate/application_start.php?id=' . (int) $existingApplication['id']) ?>" class="btn btn--primary btn--full">
                    <i class="fas fa-arrow-right"></i> Continue Application
                  </a>
                  <p class="opp-detail__card-info">You have an application in progress.</p>

                <?php elseif ($appActionType === 'view' && $existingApplication): ?>
                  <a href="<?= url('candidate/application_detail.php?id=' . (int) $existingApplication['id']) ?>" class="btn btn--outline btn--full">
                    <i class="fas fa-eye"></i> View Application
                  </a>
                  <p class="opp-detail__card-info">You have already applied for this opportunity.</p>

                <?php elseif ($appActionType === 'apply_again' && $existingApplication): ?>
                  <form method="POST" action="<?= url('candidate/application_actions.php') ?>" onsubmit="this.querySelector('button').disabled = true;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="opportunity_id" value="<?= (int) $opportunity['id'] ?>">
                    <button type="submit" class="btn btn--primary btn--full">
                      <i class="fas fa-redo"></i> Apply Again
                    </button>
                  </form>
                  <p class="opp-detail__card-info">Your previous application was withdrawn.</p>

                <?php else: ?>
                  <form method="POST" action="<?= url('candidate/application_actions.php') ?>" id="applyNowForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="opportunity_id" value="<?= (int) $opportunity['id'] ?>">
                    <button type="submit" class="btn btn--primary btn--full" id="applyNowBtn">
                      <i class="fas fa-arrow-right"></i> Apply Now
                    </button>
                  </form>
                  <p class="opp-detail__card-info">Begin your application for this opportunity.</p>

                <?php endif; ?>
              </div>

            </aside>

          </div>
        </section>

      </div>
    </main>

  </div>

  <!-- Scripts -->
  <script src="<?= url('js/opportunities.js') ?>"></script>

</body>
</html>
