<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate "My Profile" Page
 * ================================================
 * Premium professional profile experience.
 * Reuses the existing dashboard design language and
 * the secure candidate profile backend. No backend
 * functionality is changed - only the presentation.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId   = (int) current_user_id();
$user     = User::find($userId);
$profile  = CandidateProfile::ensureForUser($userId);
$breakdown = CandidateProfile::completionBreakdown($userId);
$completion = (int) ($profile['completion_percent'] ?? CandidateProfile::calculateCompletion($userId));

$availabilityStatuses = AvailabilityStatus::all();
$selectedSkills       = Skill::forUser($userId);
$skillCatalogue       = Skill::allByCategory();
$qualifications       = Qualification::forUser($userId);
$certifications       = Certification::forUser($userId);
$experiences          = WorkExperience::forUser($userId);
$documents            = Document::forUser($userId);
$consentState         = ConsentController::stateForUser($userId);

$fullName   = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$title      = $profile['professional_title'] ?? $user['professional_title'] ?? 'Candidate';
$province   = $user['province'] ?? null;
$city       = $profile['city'] ?? null;
$verified   = !empty($user['email_verified_at']);
$accountStatus = $user['status'] ?? 'pending';

$temp = current_user();
$flashes = render_flashes();

// Career interests as tags
$careerInterests = array_filter(array_map('trim', explode(',', (string)($profile['career_interests'] ?? ''))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="My Profile - Investhood IT Programme & Scarce Skills Platform">
  <title>My Profile | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/profile.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page profile-page">

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
          <li><a href="#profile-overview" class="sidebar__link"><i class="fas fa-user"></i> Overview</a></li>
          <li><a href="#profile-summary" class="sidebar__link"><i class="fas fa-align-left"></i> Professional Summary</a></li>
          <li><a href="#profile-skills" class="sidebar__link"><i class="fas fa-code"></i> Skills</a></li>
          <li><a href="#profile-qualifications" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Qualifications</a></li>
          <li><a href="#profile-experience" class="sidebar__link"><i class="fas fa-history"></i> Work Experience</a></li>
          <li><a href="#profile-documents" class="sidebar__link"><i class="fas fa-file-alt"></i> Documents</a></li>
          <li><a href="#profile-availability" class="sidebar__link"><i class="fas fa-clock"></i> Availability</a></li>
          <li><a href="#profile-consent" class="sidebar__link"><i class="fas fa-shield-alt"></i> Privacy & Consent</a></li>
        </ul>

        <div class="sidebar__section-label">Professional</div>
        <ul class="sidebar__menu">
          <li><a href="#profile-completion" class="sidebar__link"><i class="fas fa-check-circle"></i> Profile Completion</a></li>
          <li><a href="#profile-account" class="sidebar__link"><i class="fas fa-cog"></i> Account Information</a></li>
        </ul>
      </nav>

      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="<?= url('candidate/avatar.php') ?>" alt="Profile">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($fullName ?: ($user['username'] ?? 'Candidate')) ?></span>
            <span class="sidebar__user-role"><?= e($temp['role_name'] ?? 'Candidate') ?></span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout">
          <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
      </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard__main">

      <header class="dash-header" id="dashHeader">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
          </button>
          <div class="dash-header__search" id="dashSearch">
            <i class="fas fa-search"></i>
            <input type="text" class="dash-header__search-input" placeholder="Search my profile..." aria-label="Search profile">
          </div>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode">
            <i class="fas fa-moon"></i>
          </button>
          <a href="<?= url('candidate/dashboard.php') ?>" class="dash-header__icon-btn" aria-label="Back to dashboard" title="Back to dashboard">
            <i class="fas fa-th-large"></i>
          </a>
          <div class="dash-header__user">
            <img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <div class="dash-content profile-layout" id="dashContent">

        <!-- ============ PROFILE HERO ============ -->
        <section class="profile-hero" id="profile-top">
          <div class="profile-hero__bg"></div>
          <div class="profile-hero__inner">
            <div class="profile-hero__avatar">
              <img src="<?= url('candidate/avatar.php') ?>" alt="Profile picture" id="heroAvatarImg">
              <button class="profile-hero__avatar-btn" id="uploadPictureBtn" type="button" title="Change profile picture">
                <i class="fas fa-camera"></i>
              </button>
            </div>

            <div class="profile-hero__info">
              <h1 class="profile-hero__name" id="heroName"><?= e($fullName ?: ($user['username'] ?? '')) ?></h1>
              <p class="profile-hero__title" id="heroTitle"><?= e($title) ?></p>
              <div class="profile-hero__meta">
                <span class="profile-hero__meta-item"><i class="fas fa-envelope"></i> <?= e($user['email'] ?? '') ?></span>
                <?php if (!empty($user['phone'])): ?>
                <span class="profile-hero__meta-item"><i class="fas fa-phone"></i> <?= e($user['phone']) ?></span>
                <?php endif; ?>
                <?php if ($city || $province): ?>
                <span class="profile-hero__meta-item"><i class="fas fa-map-marker-alt"></i> <?= e(trim(($city ?: '') . ($city && $province ? ', ' : '') . ($province ?: ''))) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="profile-hero__aside">
              <div class="profile-hero__badges">
                <span class="profile-hero__badge profile-hero__badge--availability" id="heroAvailability">
                  <i class="fas fa-circle"></i> <?= e($profile['availability_label'] ?? 'Availability not set') ?>
                </span>
                <span class="profile-hero__badge profile-hero__badge--verify <?= $verified ? 'is-verified' : 'is-unverified' ?>" id="heroVerify">
                  <i class="fas <?= $verified ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                  <?= $verified ? 'Verified Account' : 'Email Pending Verification' ?>
                </span>
              </div>
              <div class="profile-hero__completion" id="heroCompletionWrap">
                <div class="completeness-ring">
                  <svg viewBox="0 0 36 36" class="completeness-ring__svg">
                    <path class="completeness-ring__bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="completeness-ring__fill profile-hero__ring-fill" stroke-dasharray="<?= (int)$completion ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  </svg>
                  <span class="completeness-ring__text" id="heroCompletion"><?= (int)$completion ?>%</span>
                </div>
                <span class="profile-hero__completion-label">Profile Complete</span>
              </div>
            </div>
          </div>

          <div class="profile-hero__actions">
            <button type="button" class="btn btn--outline btn--sm" id="uploadPictureBtn2"><i class="fas fa-camera"></i> Update Photo</button>
            <a href="#profile-documents" class="btn btn--ghost btn--sm"><i class="fas fa-upload"></i> Upload CV</a>
            <a href="#profile-skills" class="btn btn--ghost btn--sm"><i class="fas fa-code"></i> Update Skills</a>
          </div>
        </section>

        <!-- ============ QUICK ACTIONS ============ -->
        <section class="quick-actions" aria-label="Quick actions">
          <button type="button" class="quick-action" id="uploadPictureBtn3">
            <div class="quick-action__icon quick-action__icon--cyan"><i class="fas fa-camera"></i></div>
            <span>Update Photo</span>
          </button>
          <button type="button" class="quick-action" id="qualificationAddBtn">
            <div class="quick-action__icon quick-action__icon--amber"><i class="fas fa-graduation-cap"></i></div>
            <span>Add Qualification</span>
          </button>
          <button type="button" class="quick-action" id="addCertificationBtn3">
            <div class="quick-action__icon quick-action__icon--purple"><i class="fas fa-certificate"></i></div>
            <span>Add Certification</span>
          </button>
          <button type="button" class="quick-action" id="experienceAddBtn">
            <div class="quick-action__icon quick-action__icon--red"><i class="fas fa-briefcase"></i></div>
            <span>Add Experience</span>
          </button>
          <a href="#profile-skills" class="quick-action">
            <div class="quick-action__icon quick-action__icon--purple"><i class="fas fa-code"></i></div>
            <span>Add Skills</span>
          </a>
          <a href="#profile-documents" class="quick-action">
            <div class="quick-action__icon quick-action__icon--green"><i class="fas fa-file-upload"></i></div>
            <span>Upload CV</span>
          </a>
        </section>

        <!-- ============ PROFILE COMPLETION BANNER ============ -->
        <section class="completion-banner" aria-label="Profile completion progress">
          <div class="completion-banner__info">
            <div class="completion-banner__icon"><i class="fas fa-chart-line"></i></div>
            <div>
              <h2>Complete Your Professional Profile</h2>
              <p>Complete your profile to improve your visibility for relevant opportunities.</p>
            </div>
          </div>
          <div class="completion-banner__progress">
            <div class="completion-banner__bar" role="progressbar" aria-valuenow="<?= (int)$completion ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="completion-banner__bar-fill" style="width:<?= (int)$completion ?>%"></div>
            </div>
            <span class="completion-banner__percent" id="heroCompletionPercent"><?= (int)$completion ?>% Complete</span>
          </div>
          <a href="#profile-completion" class="completion-banner__link"><i class="fas fa-list-check"></i> View Checklist</a>
        </section>

        <!-- ============ MAIN + ASIDE GRID ============ -->
        <div class="profile-grid">

          <!-- ===== ASIDE COLUMN ===== -->
          <aside class="profile-aside">
            <div class="profile-aside__sticky">

              <!-- PROFILE COMPLETION -->
              <section class="profile-aside-card" id="profile-completion">
                <div class="profile-aside-card__header">
                  <i class="fas fa-check-circle"></i>
                  <h3>Complete Your Profile</h3>
                </div>
                <div class="completion-summary">
                  <div class="completion-summary__ring">
                    <div class="completeness-ring">
                      <svg viewBox="0 0 36 36" class="completeness-ring__svg">
                        <path class="completeness-ring__bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="completeness-ring__fill" stroke-dasharray="<?= (int)$completion ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                      </svg>
                      <span class="completeness-ring__text" id="asideCompletion"><?= (int)$completion ?>%</span>
                    </div>
                  </div>
                  <p class="completion-summary__text" id="completionMessage"><?= (int)$completion ?>% complete. Complete your profile to improve your visibility for relevant opportunities.</p>
                </div>
                <div class="completion-checklist" id="completionChecklist">
                  <?php foreach ($breakdown as $key => $item): ?>
                  <a href="#profile-<?= e($item['tab']) ?>" class="completion-check <?= $item['done'] ? 'is-done' : 'is-pending' ?>" data-key="<?= e($key) ?>">
                    <span class="completion-check__icon"><i class="fas <?= $item['done'] ? 'fa-check' : 'fa-times' ?>"></i></span>
                    <span class="completion-check__info">
                      <span class="completion-check__label"><?= e($item['label']) ?></span>
                      <span class="completion-check__status" data-status-for="<?= e($key) ?>"><?= e($item['detail']) ?></span>
                    </span>
                    <?php if (!$item['done']): ?>
                    <span class="completion-check__go">Complete <i class="fas fa-arrow-right"></i></span>
                    <?php endif; ?>
                  </a>
                  <?php endforeach; ?>
                </div>
              </section>

            </div>
          </aside>

          <!-- ===== MAIN COLUMN ===== -->
          <div class="profile-main">

            <!-- PROFILE OVERVIEW -->
            <section class="profile-card" id="profile-overview">
              <div class="profile-card__header">
                <div class="profile-card__header-icon"><i class="fas fa-user"></i></div>
                <div>
                  <h2>Profile Overview</h2>
                  <p>Your personal and professional identity within the Investhood IT talent ecosystem.</p>
                </div>
              </div>
              <div class="profile-card__body">

                <!-- PERSONAL INFORMATION overview -->
                <div class="profile-subsection">
                  <h3 class="profile-subsection__title"><i class="fas fa-id-card"></i> Personal Information</h3>
                  <div class="info-grid">
                    <div class="info-item"><span class="info-item__label">Full Name</span><span class="info-item__value"><?= e($fullName ?: '—') ?></span></div>
                    <div class="info-item"><span class="info-item__label">Email</span><span class="info-item__value"><?= e($user['email'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-item__label">Phone</span><span class="info-item__value"><?= e($user['phone'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-item__label">Location</span><span class="info-item__value"><?= e(trim(($city ?: '') . ($city && $province ? ', ' : '') . ($province ?: '')) ?: '—') ?></span></div>
                    <div class="info-item"><span class="info-item__label">Date of Birth</span><span class="info-item__value"><?= e(!empty($user['date_of_birth']) ? format_date($user['date_of_birth'], 'd M Y') : '—') ?></span></div>
                    <div class="info-item"><span class="info-item__label">Gender</span><span class="info-item__value"><?= e(!empty($user['gender']) ? ucwords(str_replace('-', ' ', $user['gender'])) : '—') ?></span></div>
                  </div>
                  <button type="button" class="profile-edit-toggle" data-toggle-target="personalEditSection"><i class="fas fa-edit"></i> Edit Personal Information</button>
                  <div class="profile-edit-section" id="personalEditSection">
                    <div class="profile-edit-section__inner">
                      <div class="profile-edit-section__title"><i class="fas fa-id-card"></i> Edit Personal Information</div>
                      <form id="personalForm" class="form" novalidate>
                        <?= csrf_field() ?>
                        <div class="form-row">
                          <div class="form-group">
                            <label class="form-label" for="first_name">First Name <span class="form-label__required">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="form-input" value="<?= e($user['first_name'] ?? '') ?>" required placeholder="Your first name">
                            <div class="form-error" data-error-for="first_name"></div>
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="last_name">Last Name <span class="form-label__required">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="form-input" value="<?= e($user['last_name'] ?? '') ?>" required placeholder="Your last name">
                            <div class="form-error" data-error-for="last_name"></div>
                          </div>
                        </div>
                        <div class="form-row">
                          <div class="form-group">
                            <label class="form-label" for="email">Email Address <span class="form-label__required">*</span></label>
                            <input type="email" id="email" name="email" class="form-input" value="<?= e($user['email'] ?? '') ?>" required placeholder="you@example.com">
                            <div class="form-error" data-error-for="email"></div>
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="phone">Phone Number <span class="form-label__required">*</span></label>
                            <input type="tel" id="phone" name="phone" class="form-input" value="<?= e($user['phone'] ?? '') ?>" required placeholder="+27 00 000 0000">
                            <div class="form-error" data-error-for="phone"></div>
                          </div>
                        </div>
                        <div class="form-row">
                          <div class="form-group">
                            <label class="form-label" for="date_of_birth">Date of Birth <span class="form-label__required">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-input" value="<?= e($user['date_of_birth'] ?? '') ?>" required>
                            <div class="form-error" data-error-for="date_of_birth"></div>
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-input form-input--select">
                              <option value="">Select gender</option>
                              <?php foreach (ALLOWED_GENDERS as $g): ?>
                              <option value="<?= e($g) ?>" <?= (($user['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $g))) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <div class="form-error" data-error-for="gender"></div>
                          </div>
                        </div>
                        <div class="form-group">
                          <label class="form-label" for="address">Address</label>
                          <input type="text" id="address" name="address" class="form-input" value="<?= e($profile['address'] ?? '') ?>" placeholder="Street address">
                          <div class="form-error" data-error-for="address"></div>
                        </div>
                        <div class="form-row">
                          <div class="form-group">
                            <label class="form-label" for="city">City / Town</label>
                            <input type="text" id="city" name="city" class="form-input" value="<?= e($profile['city'] ?? '') ?>" placeholder="e.g. Johannesburg">
                            <div class="form-error" data-error-for="city"></div>
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="province">Province <span class="form-label__required">*</span></label>
                            <select id="province" name="province" class="form-input form-input--select">
                              <option value="">Select province</option>
                              <?php foreach (ALLOWED_PROVINCES as $p): ?>
                              <option value="<?= e($p) ?>" <?= (($user['province'] ?? '') === $p) ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $p))) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <div class="form-error" data-error-for="province"></div>
                          </div>
                        </div>
                        <div class="form-group profile-form__actions">
                          <button type="submit" class="btn btn--primary" id="personalSaveBtn">
                            <span class="btn__text"><i class="fas fa-save"></i> Save Personal Information</span>
                            <span class="btn__spinner"></span>
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- PROFESSIONAL INFORMATION overview -->
                <div class="profile-subsection">
                  <h3 class="profile-subsection__title"><i class="fas fa-briefcase"></i> Professional Information</h3>
                  <div class="info-grid">
                    <div class="info-item"><span class="info-item__label">Professional Title</span><span class="info-item__value"><?= e($profile['professional_title'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-item__label">Employment Status</span><span class="info-item__value"><?= e(!empty($profile['employment_status']) ? ucwords(str_replace('-', ' ', $profile['employment_status'])) : '—') ?></span></div>
                    <div class="info-item"><span class="info-item__label">Availability</span><span class="info-item__value"><?= e($profile['availability_label'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-item__label">Career Interests</span><span class="info-item__value info-item__value--muted"><?= e($profile['career_interests'] ?: 'Not specified') ?></span></div>
                  </div>

                  <button type="button" class="profile-edit-toggle" data-toggle-target="profInfoEditSection"><i class="fas fa-edit"></i> Edit Professional Information</button>
                  <div class="profile-edit-section" id="profInfoEditSection">
                    <div class="profile-edit-section__inner">
                      <div class="profile-edit-section__title"><i class="fas fa-briefcase"></i> Edit Professional Information</div>
                      <form id="profInfoForm" class="form" novalidate>
                        <?= csrf_field() ?>
                        <div class="form-group">
                          <label class="form-label" for="professional_title">Professional Title <span class="form-label__required">*</span></label>
                          <input type="text" id="professional_title" name="professional_title" class="form-input" value="<?= e($profile['professional_title'] ?? '') ?>" placeholder="e.g. Software Developer">
                          <div class="form-error" data-error-for="professional_title"></div>
                        </div>
                        <div class="form-group">
                          <label class="form-label" for="professional_summary">Professional Summary</label>
                          <textarea id="professional_summary" name="professional_summary" class="form-input" rows="4" placeholder="A short summary of your professional background and strengths."><?= e($profile['professional_summary'] ?? '') ?></textarea>
                          <div class="form-error" data-error-for="professional_summary"></div>
                        </div>
                        <div class="form-group">
                          <label class="form-label" for="career_interests">Career Interests</label>
                          <textarea id="career_interests" name="career_interests" class="form-input" rows="3" placeholder="e.g. Back-end development, cloud computing, data analysis"><?= e($profile['career_interests'] ?? '') ?></textarea>
                          <div class="form-error" data-error-for="career_interests"></div>
                        </div>
                        <div class="form-row">
                          <div class="form-group">
                            <label class="form-label" for="employment_status">Employment Status</label>
                            <select id="employment_status" name="employment_status" class="form-input form-input--select">
                              <option value="">Select status</option>
                              <?php foreach (ALLOWED_EMPLOYMENT_STATUS as $es): ?>
                              <option value="<?= e($es) ?>" <?= (($profile['employment_status'] ?? '') === $es || (($profile['employment_status'] ?? '') === '' && ($user['employment_status'] ?? '') === $es)) ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $es))) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <div class="form-error" data-error-for="employment_status"></div>
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="availability_status">Availability Status</label>
                            <select id="availability_status" name="availability_status" class="form-input form-input--select">
                              <option value="">Select availability</option>
                              <?php foreach ($availabilityStatuses as $as): ?>
                              <option value="<?= e($as['slug']) ?>" <?= (($profile['availability_slug'] ?? '') === $as['slug']) ? 'selected' : '' ?>><?= e($as['label']) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <div class="form-error" data-error-for="availability_status"></div>
                          </div>
                        </div>
                        <div class="form-group" id="availabilityDateWrap" style="<?= (($profile['availability_slug'] ?? '') === AVAILABILITY_AVAILABLE_DATE) ? '' : 'display:none;' ?>">
                          <label class="form-label" for="availability_date">Available From Date</label>
                          <input type="date" id="availability_date" name="availability_date" class="form-input" value="<?= e($profile['availability_date'] ?? '') ?>">
                          <div class="form-error" data-error-for="availability_date"></div>
                        </div>
                        <div class="form-group profile-form__actions">
                          <button type="submit" class="btn btn--primary" id="profInfoSaveBtn">
                            <span class="btn__text"><i class="fas fa-save"></i> Save Professional Information</span>
                            <span class="btn__spinner"></span>
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

              </div>
            </section>

            <!-- PROFESSIONAL SUMMARY -->
            <section class="profile-card" id="profile-summary">
              <div class="profile-card__header">
                <div class="profile-card__header-icon"><i class="fas fa-align-left"></i></div>
                <div>
                  <h2>Professional Summary</h2>
                  <p>Tell employers and programme managers about yourself.</p>
                </div>
              </div>
              <div class="profile-card__body">
                <?php if (!empty($profile['professional_summary'])): ?>
                <p class="summary-text"><?= e($profile['professional_summary']) ?></p>
                <?php else: ?>
                <div class="empty-state">
                  <div class="empty-state__icon"><i class="fas fa-pen"></i></div>
                  <h3>Add Your Professional Summary</h3>
                  <p>Tell employers and programme managers about yourself.</p>
                  <button type="button" class="btn btn--primary btn--sm profile-edit-toggle" data-toggle-target="summaryEditSection"><i class="fas fa-plus"></i> Add Professional Summary</button>
                </div>
                <?php endif; ?>

                <?php if ($careerInterests): ?>
                <div class="profile-subsection" style="margin-top:1rem;">
                  <h3 class="profile-subsection__title"><i class="fas fa-star"></i> Career Interests</h3>
                  <div class="summary-tags">
                    <?php foreach ($careerInterests as $interest): ?>
                    <span class="skill-tag"><i class="fas fa-tag"></i> <?= e($interest) ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>

                <div class="profile-edit-section" id="summaryEditSection">
                  <div class="profile-edit-section__inner">
                    <div class="profile-edit-section__title"><i class="fas fa-align-left"></i> Professional Summary</div>
                    <form id="summaryForm" class="form" novalidate>
                      <?= csrf_field() ?>
                      <div class="form-group">
                        <label class="form-label" for="professional_summary">Professional Summary</label>
                        <textarea id="professional_summary" name="professional_summary" class="form-input" rows="4" placeholder="A short summary of your professional background and strengths."><?= e($profile['professional_summary'] ?? '') ?></textarea>
                        <div class="form-error" data-error-for="professional_summary"></div>
                      </div>
                      <div class="form-group">
                        <label class="form-label" for="career_interests">Career Interests</label>
                        <textarea id="career_interests" name="career_interests" class="form-input" rows="3" placeholder="Comma separated, e.g. Back-end development, Cloud computing"><?= e($profile['career_interests'] ?? '') ?></textarea>
                        <div class="form-error" data-error-for="career_interests"></div>
                      </div>
                      <div class="form-group">
                        <button type="submit" class="btn btn--primary" id="summarySaveBtn">
                          <span class="btn__text"><i class="fas fa-save"></i> Save Summary</span>
                          <span class="btn__spinner"></span>
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </section>

            <!-- SKILLS -->
            <section class="profile-card" id="profile-skills">
              <div class="profile-card__header">
                <div class="profile-card__header-icon"><i class="fas fa-code"></i></div>
                <div>
                  <h2>Skills</h2>
                  <p>Your professional skills portfolio from our skill catalogue.</p>
                </div>
              </div>
              <div class="profile-card__body">
                <!-- Technical Skills -->
                <div class="profile-subsection">
                  <h3 class="profile-subsection__title"><i class="fas fa-microchip"></i> Technical Skills</h3>
                  <div class="skill-search">
                    <div class="skill-search__bar">
                      <i class="fas fa-search"></i>
                      <input type="text" class="skill-search__input" data-category="technical" placeholder="Search technical skills...">
                    </div>
                    <div class="skill-search__results" data-results="technical" hidden></div>
                  </div>
                  <div class="skill-chips" id="technicalSkills">
                    <?php
                    $techSelected = array_filter($selectedSkills, fn($s) => $s['category'] === 'technical');
                    foreach ($techSelected as $sk): ?>
                    <span class="skill-chip" data-id="<?= (int)$sk['skill_id'] ?>" data-category="technical">
                      <span class="skill-chip__name"><?= e($sk['name']) ?></span>
                      <span class="skill-chip__level"><?= e(ucfirst($sk['proficiency'])) ?></span>
                      <button type="button" class="skill-chip__remove skill-remove" data-id="<?= (int)$sk['skill_id'] ?>" data-name="<?= e($sk['name']) ?>"><i class="fas fa-times"></i></button>
                    </span>
                    <?php endforeach; ?>
                    <?php if (!$techSelected): ?>
                    <span class="skill-empty">No technical skills added yet.</span>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Soft Skills -->
                <div class="profile-subsection">
                  <h3 class="profile-subsection__title"><i class="fas fa-users"></i> Soft Skills</h3>
                  <div class="skill-search">
                    <div class="skill-search__bar">
                      <i class="fas fa-search"></i>
                      <input type="text" class="skill-search__input" data-category="soft" placeholder="Search soft skills...">
                    </div>
                    <div class="skill-search__results" data-results="soft" hidden></div>
                  </div>
                  <div class="skill-chips" id="softSkills">
                    <?php
                    $softSelected = array_filter($selectedSkills, fn($s) => $s['category'] === 'soft');
                    foreach ($softSelected as $sk): ?>
                    <span class="skill-chip skill-chip--soft" data-id="<?= (int)$sk['skill_id'] ?>" data-category="soft">
                      <span class="skill-chip__name"><?= e($sk['name']) ?></span>
                      <span class="skill-chip__level"><?= e(ucfirst($sk['proficiency'])) ?></span>
                      <button type="button" class="skill-chip__remove skill-remove" data-id="<?= (int)$sk['skill_id'] ?>" data-name="<?= e($sk['name']) ?>"><i class="fas fa-times"></i></button>
                    </span>
                    <?php endforeach; ?>
                    <?php if (!$softSelected): ?>
                    <span class="skill-empty">No soft skills added yet.</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </section>

            <!-- QUALIFICATIONS -->
            <section class="profile-card" id="profile-qualifications">
              <div class="profile-card__header">
                <div class="profile-card__header-icon"><i class="fas fa-graduation-cap"></i></div>
                <div>
                  <h2>Education &amp; Qualifications</h2>
                  <p>Your academic and professional qualifications.</p>
                </div>
              </div>
              <div class="profile-card__body">
                <div id="qualificationsList" class="qual-timeline">
                  <?php if (empty($qualifications)): ?>
                  <div class="empty-state">
                    <div class="empty-state__icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3>No Qualifications Added</h3>
                    <p>Add your qualifications to strengthen your profile and improve your visibility for programme applications.</p>
                    <button type="button" class="btn btn--primary btn--sm" id="addQualificationBtn"><i class="fas fa-plus"></i> Add Qualification</button>
                  </div>
                  <?php else: ?>
                    <?php foreach ($qualifications as $q): ?>
                    <div class="qual-timeline__item timeline-item" data-id="<?= (int)$q['id'] ?>">
                      <span class="qual-timeline__dot"></span>
                      <span class="qual-timeline__year"><?= e($q['year_completed'] ?? '—') ?></span>
                      <div class="qual-timeline__name"><?= e($q['name']) ?></div>
                      <div class="qual-timeline__institution"><?= e($q['institution'] ?: 'Institution not specified') ?></div>
                      <div class="qual-timeline__meta">
                        <span class="tag tag--primary"><?= $q['level'] ? e(ucwords(str_replace('-', ' ', $q['level']))) : 'General' ?></span>
                        <?php if (!empty($q['verification_status']) && $q['verification_status'] === 'verified'): ?>
                        <span class="tag tag--green"><i class="fas fa-check-circle"></i> Verified</span>
                        <?php elseif (!empty($q['verification_status']) && $q['verification_status'] === 'pending'): ?>
                        <span class="tag tag--amber"><i class="fas fa-clock"></i> Verification pending</span>
                        <?php endif; ?>
                      </div>
                      <div class="qual-actions">
                        <button type="button" class="btn btn--ghost btn--sm qualification-edit" data-id="<?= (int)$q['id'] ?>" data-name="<?= e($q['name']) ?>" data-institution="<?= e($q['institution'] ?? '') ?>" data-year="<?= e($q['year_completed'] ?? '') ?>" data-level="<?= e($q['level'] ?? '') ?>"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="btn btn--ghost btn--sm qualification-delete" data-id="<?= (int)$q['id'] ?>" data-name="<?= e($q['name']) ?>"><i class="fas fa-trash"></i></button>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
<?php if (!empty($qualifications)): ?>
                <div class="profile-card__add">
                  <button type="button" class="btn btn--outline btn--sm" id="addQualificationBtn2"><i class="fas fa-plus"></i> Add Qualification</button>
                </div>
                <?php endif; ?>

                <!-- Certifications -->
                <div class="profile-subsection" style="margin-top:1.5rem;">
                  <h3 class="profile-subsection__title"><i class="fas fa-certificate"></i> Certifications</h3>
                  <div id="certificationsList" class="cert-timeline">
                    <?php if (empty($certifications)): ?>
                    <div class="empty-state empty-state--sm">
                      <div class="empty-state__icon"><i class="fas fa-certificate"></i></div>
                      <h3>No Certifications Added</h3>
                      <p>Add professional certifications, licences and credentials to strengthen your profile.</p>
                      <button type="button" class="btn btn--primary btn--sm" id="addCertificationBtn"><i class="fas fa-plus"></i> Add Certification</button>
                    </div>
                    <?php else: ?>
                      <?php foreach ($certifications as $c): ?>
                      <div class="cert-timeline__item timeline-item" data-id="<?= (int)$c['id'] ?>">
                        <span class="cert-timeline__dot"></span>
                        <span class="cert-timeline__year"><?= e($c['year_obtained'] ?? '—') ?></span>
                        <div class="cert-timeline__name"><?= e($c['name']) ?></div>
                        <div class="cert-timeline__org"><?= e($c['issuing_organisation'] ?: 'Issuing organisation not specified') ?></div>
                        <div class="cert-timeline__meta">
                          <?php if (!empty($c['expiry_date'])): ?>
                          <span class="tag tag--cyan"><i class="fas fa-calendar-alt"></i> Expires <?= e(format_date($c['expiry_date'], 'd M Y')) ?></span>
                          <?php endif; ?>
                          <?php if (!empty($c['credential_id'])): ?>
                          <span class="tag tag--purple"><i class="fas fa-id-badge"></i> <?= e($c['credential_id']) ?></span>
                          <?php endif; ?>
                          <?php if (!empty($c['verification_status']) && $c['verification_status'] === 'verified'): ?>
                          <span class="tag tag--green"><i class="fas fa-check-circle"></i> Verified</span>
                          <?php elseif (!empty($c['verification_status']) && $c['verification_status'] === 'pending'): ?>
                          <span class="tag tag--amber"><i class="fas fa-clock"></i> Verification pending</span>
                          <?php endif; ?>
                        </div>
                        <div class="cert-actions">
                          <button type="button" class="btn btn--ghost btn--sm certification-edit" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>" data-org="<?= e($c['issuing_organisation'] ?? '') ?>" data-year="<?= e($c['year_obtained'] ?? '') ?>" data-expiry="<?= e($c['expiry_date'] ?? '') ?>" data-credential="<?= e($c['credential_id'] ?? '') ?>"><i class="fas fa-edit"></i> Edit</button>
                          <button type="button" class="btn btn--ghost btn--sm certification-delete" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>"><i class="fas fa-trash"></i></button>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($certifications)): ?>
                  <div class="profile-card__add" style="padding:1rem 0 0;">
                    <button type="button" class="btn btn--outline btn--sm" id="addCertificationBtn2"><i class="fas fa-plus"></i> Add Certification</button>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </section>

            <!-- WORK EXPERIENCE -->
            <section class="profile-card" id="profile-experience">
              <div class="profile-card__header">
                <div class="profile-card__header-icon"><i class="fas fa-history"></i></div>
                <div>
                  <h2>Work Experience</h2>
                  <p>Your career journey, ordered by relevance.</p>
                </div>
              </div>
              <div class="profile-card__body">
                <div id="experiencesList" class="exp-timeline">
                  <?php if (empty($experiences)): ?>
                  <div class="empty-state">
                    <div class="empty-state__icon"><i class="fas fa-briefcase"></i></div>
                    <h3>No Work Experience Yet</h3>
                    <p>Add your professional experience to help us understand your career journey.</p>
                    <button type="button" class="btn btn--primary btn--sm" id="addExperienceBtn"><i class="fas fa-plus"></i> Add Experience</button>
                  </div>
                  <?php else: ?>
                    <?php foreach ($experiences as $exp): ?>
                    <div class="exp-timeline__item timeline-item" data-id="<?= (int)$exp['id'] ?>">
                      <span class="exp-timeline__dot"></span>
                      <span class="exp-timeline__period"><i class="fas fa-calendar-alt"></i> <?= e(!empty($exp['start_date']) ? date('M Y', strtotime($exp['start_date'])) : '') ?> — <?= !empty($exp['is_current']) ? 'Present' : (!empty($exp['end_date']) ? e(date('M Y', strtotime($exp['end_date']))) : '') ?></span>
                      <div class="exp-timeline__job"><?= e($exp['job_title']) ?></div>
                      <div class="exp-timeline__company"><i class="fas fa-building"></i> <?= e($exp['company']) ?></div>
                      <?php if (!empty($exp['description'])): ?>
                      <p class="exp-timeline__desc"><?= e($exp['description']) ?></p>
                      <?php endif; ?>
                      <div class="exp-actions">
                        <button type="button" class="btn btn--ghost btn--sm experience-edit" data-id="<?= (int)$exp['id'] ?>" data-job="<?= e($exp['job_title']) ?>" data-company="<?= e($exp['company']) ?>" data-start="<?= e($exp['start_date'] ?? '') ?>" data-end="<?= e($exp['end_date'] ?? '') ?>" data-current="<?= !empty($exp['is_current']) ? '1' : '0' ?>" data-desc="<?= e($exp['description'] ?? '') ?>"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="btn btn--ghost btn--sm experience-delete" data-id="<?= (int)$exp['id'] ?>" data-job="<?= e($exp['job_title']) ?>"><i class="fas fa-trash"></i></button>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <?php if (!empty($experiences)): ?>
                <div class="profile-card__add">
                  <button type="button" class="btn btn--outline btn--sm" id="addExperienceBtn2"><i class="fas fa-plus"></i> Add Work Experience</button>
                </div>
                <?php endif; ?>
              </div>
            </section>

            <!-- DOCUMENTS -->
            <section class="profile-card" id="profile-documents">
              <div class="profile-card__header">
                <div class="profile-card__header-icon"><i class="fas fa-file-alt"></i></div>
                <div>
                  <h2>My Documents</h2>
                  <p>Securely store your CV, certificates and supporting documents.</p>
                </div>
              </div>
              <div class="profile-card__body">
                <div class="profile-subsection">
                  <h3 class="profile-subsection__title"><i class="fas fa-file-pdf"></i> CV / Resume</h3>
                  <div class="doc-grid" id="cvList">
                    <?php
                    $cvDocs = array_filter($documents, fn($d) => $d['document_type'] === 'cv');
                    if ($cvDocs):
                      $cv = reset($cvDocs);
                    ?>
                    <div class="doc-card doc-item" data-id="<?= (int)$cv['id'] ?>">
                      <div class="doc-card__icon"><i class="fas fa-file-pdf"></i></div>
                      <div class="doc-card__body">
                        <div class="doc-card__name"><?= e($cv['original_filename']) ?></div>
                        <div class="doc-card__meta"><?= e(number_format((int)$cv['file_size'] / 1024, 1)) ?> KB · Updated <?= e(format_date($cv['created_at'], 'd M Y')) ?></div>
                        <div class="doc-card__status">
                          <span class="tag tag--green"><i class="fas fa-check-circle"></i> Uploaded</span>
                          <?php if (!empty($cv['verification_status'])): ?>
                            <?php if ($cv['verification_status'] === 'verified'): ?><span class="tag tag--green">Verified</span>
                            <?php elseif ($cv['verification_status'] === 'pending'): ?><span class="tag tag--amber">Verification pending</span>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                        <div class="doc-card__actions">
                          <a href="<?= url('candidate/download.php?id=' . (int)$cv['id'] . '&mode=preview') ?>" target="_blank" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i> Preview</a>
                          <a href="<?= url('candidate/download.php?id=' . (int)$cv['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-download"></i> Download</a>
                          <button type="button" class="btn btn--ghost btn--sm doc-replace" data-id="<?= (int)$cv['id'] ?>"><i class="fas fa-sync-alt"></i> Replace</button>
                          <button type="button" class="btn btn--ghost btn--sm doc-delete" data-id="<?= (int)$cv['id'] ?>"><i class="fas fa-trash"></i></button>
                        </div>
                      </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state empty-state--sm" style="grid-column:1/-1;">
                      <div class="empty-state__icon"><i class="fas fa-file-pdf"></i></div>
                      <h3>No CV Uploaded</h3>
                      <p>Upload your CV to significantly boost your profile completion and visibility.</p>
                      <button type="button" class="btn btn--primary btn--sm" id="uploadCvBtn"><i class="fas fa-upload"></i> Upload CV</button>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="profile-subsection">
                  <h3 class="profile-subsection__title"><i class="fas fa-certificate"></i> Certificates &amp; Supporting Documents</h3>
                  <div class="doc-grid" id="documentsList">
                    <?php
                    $otherDocs = array_filter($documents, fn($d) => $d['document_type'] !== 'cv');
                    if ($otherDocs):
                      foreach ($otherDocs as $doc):
                    ?>
                    <div class="doc-card doc-item" data-id="<?= (int)$doc['id'] ?>">
                      <div class="doc-card__icon"><i class="fas fa-file-alt"></i></div>
                      <div class="doc-card__body">
                        <div class="doc-card__name"><?= e($doc['original_filename']) ?></div>
                        <div class="doc-card__meta"><?= e(number_format((int)$doc['file_size'] / 1024, 1)) ?> KB · Uploaded <?= e(format_date($doc['created_at'], 'd M Y')) ?></div>
                        <div class="doc-card__status">
                          <span class="tag tag--primary"><?= e(ucwords($doc['document_type'])) ?></span>
                          <?php if (!empty($doc['verification_status'])): ?>
                            <?php if ($doc['verification_status'] === 'verified'): ?><span class="tag tag--green">Verified</span>
                            <?php elseif ($doc['verification_status'] === 'pending'): ?><span class="tag tag--amber">Verification pending</span>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                        <div class="doc-card__actions">
                          <a href="<?= url('candidate/download.php?id=' . (int)$doc['id'] . '&mode=preview') ?>" target="_blank" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i> Preview</a>
                          <a href="<?= url('candidate/download.php?id=' . (int)$doc['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-download"></i> Download</a>
                          <button type="button" class="btn btn--ghost btn--sm doc-replace" data-id="<?= (int)$doc['id'] ?>"><i class="fas fa-sync-alt"></i> Replace</button>
                          <button type="button" class="btn btn--ghost btn--sm doc-delete" data-id="<?= (int)$doc['id'] ?>"><i class="fas fa-trash"></i></button>
                        </div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state empty-state--sm" style="grid-column:1/-1;">
                      <div class="empty-state__icon"><i class="fas fa-folder-open"></i></div>
                      <h3>No Supporting Documents</h3>
                      <p>Upload certificates and supporting documents to complete your professional portfolio.</p>
                      <button type="button" class="btn btn--primary btn--sm" id="uploadOtherDocBtn"><i class="fas fa-upload"></i> Upload Document</button>
                    </div>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($otherDocs)): ?>
                  <div class="profile-card__add">
                    <button type="button" class="btn btn--outline btn--sm" id="uploadDocumentBtn"><i class="fas fa-upload"></i> Upload Document</button>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </section>

            <!-- AVAILABILITY -->
            <section class="profile-card" id="profile-availability">
              <div class="profile-card__header">
                <div class="profile-card__header-icon"><i class="fas fa-clock"></i></div>
                <div>
                  <h2>Availability</h2>
                  <p>Let employers and programme managers know when you are available.</p>
                </div>
              </div>
              <div class="profile-card__body">
                <div class="availability-status-card">
                  <div class="availability-status-card__icon availability-status-card__icon--<?= in_array($profile['availability_slug'] ?? '', [AVAILABILITY_AVAILABLE_NOW, AVAILABILITY_AVAILABLE_DATE, AVAILABILITY_EMPLOYED_OPEN], true) ? 'active' : 'inactive' ?>">
                    <i class="fas fa-circle"></i>
                  </div>
                  <div>
                    <span class="availability-status-card__label">Current Status</span>
                    <span class="availability-status-card__value" id="availabilityStatusValue"><?= e($profile['availability_label'] ?? 'Availability not set') ?></span>
                    <?php if (!empty($profile['availability_date']) && ($profile['availability_slug'] ?? '') === AVAILABILITY_AVAILABLE_DATE): ?>
                    <span class="availability-status-card__hint">Available from <?= e(format_date($profile['availability_date'], 'd M Y')) ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <button type="button" class="profile-edit-toggle" data-toggle-target="availabilityEditSection"><i class="fas fa-clock"></i> Update Availability</button>
                <div class="profile-edit-section" id="availabilityEditSection">
                  <div class="profile-edit-section__inner">
                    <div class="profile-edit-section__title"><i class="fas fa-clock"></i> Update Your Availability</div>
                    <form id="availabilityForm" class="form" novalidate>
                      <?= csrf_field() ?>
                      <div class="form-group">
                        <label class="form-label" for="availability_select">Availability Status</label>
                        <select id="availability_select" name="availability_status" class="form-input form-input--select">
                          <option value="">Select availability</option>
                          <?php foreach ($availabilityStatuses as $as): ?>
                          <option value="<?= e($as['slug']) ?>" <?= (($profile['availability_slug'] ?? '') === $as['slug']) ? 'selected' : '' ?>><?= e($as['label']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <div class="form-error" data-error-for="availability_status"></div>
                      </div>
                      <div class="form-group" id="availabilityDateWrap2" style="<?= (($profile['availability_slug'] ?? '') === AVAILABILITY_AVAILABLE_DATE) ? '' : 'display:none;' ?>">
                        <label class="form-label" for="availability_date2">Available From Date</label>
                        <input type="date" id="availability_date2" name="availability_date" class="form-input" value="<?= e($profile['availability_date'] ?? '') ?>">
                        <div class="form-error" data-error-for="availability_date"></div>
                      </div>
                      <div class="form-group">
                        <button type="submit" class="btn btn--primary" id="availabilitySaveBtn">
                          <span class="btn__text"><i class="fas fa-save"></i> Update Availability</span>
                          <span class="btn__spinner"></span>
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </section>

            <!-- PRIVACY & CONSENT -->
            <section class="profile-card" id="profile-consent">
              <div class="profile-card__header">
                <div class="profile-card__header-icon"><i class="fas fa-shield-alt"></i></div>
                <div>
                  <h2>Privacy &amp; Consent</h2>
                  <p>Control how your information is processed.</p>
                </div>
              </div>
              <div class="profile-card__body">
                <div class="consent-list">
                  <?php
                  $consentDefs = [
                    CONSENT_PROGRAMME => ['label' => 'Programme Administration', 'desc' => 'Process your information to administer and manage your programme participation.', 'locked' => false],
                    CONSENT_FUTURE_OPPORTUNITIES => ['label' => 'Future Opportunities', 'desc' => 'Use your profile to match you with future opportunities, internships and programmes.', 'locked' => false],
                    CONSENT_TALENT_POOL => ['label' => 'Talent Pool Participation', 'desc' => 'Include your profile in the talent pool for employers and recruiters to discover.', 'locked' => false],
                    CONSENT_CLIENT_SUBMISSION => ['label' => 'Client / Recruitment Submissions', 'desc' => 'Submit your profile to client organisations for recruitment opportunities.', 'locked' => false],
                  ];
                  foreach ($consentDefs as $purpose => $def):
                    $st = $consentState[$purpose] ?? ['status' => 'never', 'granted_at' => null, 'withdrawn_at' => null];
                  ?>
                  <div class="consent-item" data-purpose="<?= e($purpose) ?>">
                    <div class="consent-item__info">
                      <strong><?= e($def['label']) ?></strong>
                      <span><?= e($def['desc']) ?></span>
                      <?php if ($st['status'] === 'granted'): ?>
                        <span class="consent-item__status consent-item__status--granted"><i class="fas fa-check-circle"></i> Granted<?= !empty($st['granted_at']) ? ' on ' . e(format_date($st['granted_at'], 'd M Y')) : '' ?></span>
                      <?php elseif ($st['status'] === 'withdrawn'): ?>
                        <span class="consent-item__status consent-item__status--withdrawn"><i class="fas fa-times-circle"></i> Withdrawn<?= !empty($st['withdrawn_at']) ? ' on ' . e(format_date($st['withdrawn_at'], 'd M Y')) : '' ?></span>
                      <?php else: ?>
                        <span class="consent-item__status consent-item__status--never"><i class="fas fa-minus-circle"></i> Not set</span>
                      <?php endif; ?>
                    </div>
                    <label class="toggle">
                      <input type="checkbox" class="consent-toggle" data-purpose="<?= e($purpose) ?>" <?= $st['status'] === 'granted' ? 'checked' : '' ?> <?= ($def['locked'] ?? false) ? 'disabled' : '' ?>>
                      <span class="toggle__slider"></span>
                    </label>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>

            <!-- ACCOUNT INFORMATION (collapsible, secondary) -->
            <section class="profile-card" id="profile-account">
              <div class="account-collapse">
                <button type="button" class="account-collapse__trigger" data-toggle-account>
                  <span><i class="fas fa-cog"></i> Account Information</span>
                  <i class="fas fa-chevron-down"></i>
                </button>
                <div class="account-info" id="accountInfo">
                  <div class="account-info__inner">
                    <div class="info-grid">
                      <div class="info-item"><span class="info-item__label">User ID</span><span class="info-item__value">#<?= (int)$user['id'] ?></span></div>
                      <div class="info-item"><span class="info-item__label">Username</span><span class="info-item__value"><?= e($user['username'] ?? '') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Role</span><span class="info-item__value"><?= e($temp['role_name'] ?? 'Candidate') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Account Status</span><span class="info-item__value"><?= e(ucfirst($accountStatus)) ?></span></div>
                      <div class="info-item"><span class="info-item__label">Member Since</span><span class="info-item__value"><?= e(!empty($user['created_at']) ? format_date($user['created_at'], 'd M Y') : '—') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Last Login</span><span class="info-item__value"><?= e(!empty($user['last_login']) ? format_date($user['last_login'], 'd M Y') : '—') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Verification</span><span class="info-item__value"><?= $verified ? 'Verified' : 'Not verified' ?></span></div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

          </div>
        </div>
      </div>

      <footer class="dash-footer">
        <div class="container">
          <div class="dash-footer__inner">
            <p>&copy; 2025 Investhood IT. All rights reserved.</p>
            <div class="dash-footer__links">
              <a href="#">Privacy Policy</a>
              <a href="#">Terms & Conditions</a>
              <a href="<?= url('index.php') ?>">Back to Home</a>
            </div>
          </div>
        </div>
      </footer>

    </main>
  </div>

  <!-- ============ MODALS ============ -->

  <!-- Picture upload modal -->
  <div class="modal-overlay" id="pictureModal">
    <div class="modal">
      <div class="modal__icon modal__icon--info"><i class="fas fa-camera"></i></div>
      <h3>Upload Profile Picture</h3>
      <p>Choose a JPG, PNG or WebP image (max 2 MB).</p>
      <div class="picture-preview" id="picturePreviewWrap">
        <img id="picturePreview" alt="Preview" hidden>
      </div>
      <input type="file" id="pictureInput" accept="image/jpeg,image/png,image/webp" hidden>
      <div class="form-options" style="justify-content:center;flex-wrap:wrap;gap:0.5rem;">
        <button type="button" class="btn btn--ghost btn--sm" id="pictureChooseBtn"><i class="fas fa-folder-open"></i> Choose Image</button>
        <button type="button" class="btn btn--primary btn--sm" id="pictureSaveBtn" disabled><span class="btn__text"><i class="fas fa-upload"></i> Upload</span><span class="btn__spinner"></span></button>
        <button type="button" class="btn btn--ghost btn--sm" id="pictureRemoveBtn"><i class="fas fa-trash"></i> Remove</button>
        <button type="button" class="btn btn--ghost btn--sm" id="pictureCancelBtn">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Qualification modal -->
  <div class="modal-overlay" id="qualificationModal">
    <div class="modal modal--form">
      <h3 id="qualificationModalTitle">Add Qualification</h3>
      <form id="qualificationForm" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="qual_id">
        <div class="form-group">
          <label class="form-label" for="qual_name">Qualification Name <span class="form-label__required">*</span></label>
          <input type="text" id="qual_name" name="name" class="form-input" placeholder="e.g. BSc Computer Science">
          <div class="form-error" data-error-for="name"></div>
        </div>
        <div class="form-group">
          <label class="form-label" for="qual_institution">Institution</label>
          <input type="text" id="qual_institution" name="institution" class="form-input" placeholder="e.g. University of Johannesburg">
          <div class="form-error" data-error-for="institution"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="qual_year">Year Completed</label>
            <input type="number" id="qual_year" name="year_completed" class="form-input" min="1950" max="<?= date('Y') + 1 ?>" placeholder="2024">
            <div class="form-error" data-error-for="year_completed"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="qual_level">Qualification Level</label>
            <select id="qual_level" name="level" class="form-input form-input--select">
              <option value="">Select level</option>
              <?php foreach (ALLOWED_QUALIFICATIONS as $ql): ?>
              <option value="<?= e($ql) ?>"><?= e(ucwords(str_replace('-', ' ', $ql))) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-error" data-error-for="level"></div>
          </div>
        </div>
        <div class="form-options">
          <button type="button" class="btn btn--ghost qualification-modal-cancel">Cancel</button>
          <button type="submit" class="btn btn--primary" id="qualificationSaveBtn"><span class="btn__text"><i class="fas fa-save"></i> Save</span><span class="btn__spinner"></span></button>
        </div>
      </form>
    </div>
  </div>

<!-- Certification modal -->
  <div class="modal-overlay" id="certificationModal">
    <div class="modal modal--form">
      <h3 id="certificationModalTitle">Add Certification</h3>
      <form id="certificationForm" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="cert_id">
        <div class="form-group">
          <label class="form-label" for="cert_name">Certification Name <span class="form-label__required">*</span></label>
          <input type="text" id="cert_name" name="name" class="form-input" placeholder="e.g. AWS Certified Solutions Architect">
          <div class="form-error" data-error-for="name"></div>
        </div>
        <div class="form-group">
          <label class="form-label" for="cert_org">Issuing Organisation</label>
          <input type="text" id="cert_org" name="issuing_organisation" class="form-input" placeholder="e.g. Amazon Web Services">
          <div class="form-error" data-error-for="issuing_organisation"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="cert_year">Year Obtained</label>
            <input type="number" id="cert_year" name="year_obtained" class="form-input" min="1950" max="<?= date('Y') + 1 ?>" placeholder="2024">
            <div class="form-error" data-error-for="year_obtained"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="cert_expiry">Expiry Date</label>
            <input type="date" id="cert_expiry" name="expiry_date" class="form-input">
            <div class="form-error" data-error-for="expiry_date"></div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="cert_credential">Credential ID</label>
          <input type="text" id="cert_credential" name="credential_id" class="form-input" placeholder="e.g. AWS-12345678">
          <div class="form-error" data-error-for="credential_id"></div>
        </div>
        <div class="form-options">
          <button type="button" class="btn btn--ghost certification-modal-cancel">Cancel</button>
          <button type="submit" class="btn btn--primary" id="certificationSaveBtn"><span class="btn__text"><i class="fas fa-save"></i> Save</span><span class="btn__spinner"></span></button>
        </div>
      </form>
    </div>
  </div>

  <!-- Experience modal -->
  <div class="modal-overlay" id="experienceModal">
    <div class="modal modal--form">
      <h3 id="experienceModalTitle">Add Work Experience</h3>
      <form id="experienceForm" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="exp_id">
        <div class="form-group">
          <label class="form-label" for="exp_job">Job Title <span class="form-label__required">*</span></label>
          <input type="text" id="exp_job" name="job_title" class="form-input" placeholder="e.g. Junior Developer">
          <div class="form-error" data-error-for="job_title"></div>
        </div>
        <div class="form-group">
          <label class="form-label" for="exp_company">Company / Organisation <span class="form-label__required">*</span></label>
          <input type="text" id="exp_company" name="company" class="form-input" placeholder="e.g. TechCorp SA">
          <div class="form-error" data-error-for="company"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="exp_start">Start Date <span class="form-label__required">*</span></label>
            <input type="date" id="exp_start" name="start_date" class="form-input">
            <div class="form-error" data-error-for="start_date"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="exp_end">End Date</label>
            <input type="date" id="exp_end" name="end_date" class="form-input">
            <div class="form-error" data-error-for="end_date"></div>
          </div>
        </div>
        <div class="checkbox-group" style="margin-bottom:0.5rem;">
          <input type="checkbox" id="exp_current" name="is_current">
          <span class="checkbox-custom"></span>
          <label class="checkbox-label" for="exp_current">I currently work here</label>
        </div>
        <div class="form-group">
          <label class="form-label" for="exp_desc">Description / Duties</label>
          <textarea id="exp_desc" name="description" class="form-input" rows="3" placeholder="Describe your responsibilities and achievements"></textarea>
          <div class="form-error" data-error-for="description"></div>
        </div>
        <div class="form-options">
          <button type="button" class="btn btn--ghost experience-modal-cancel">Cancel</button>
          <button type="submit" class="btn btn--primary" id="experienceSaveBtn"><span class="btn__text"><i class="fas fa-save"></i> Save</span><span class="btn__spinner"></span></button>
        </div>
      </form>
    </div>
  </div>

  <!-- Document upload modal -->
  <div class="modal-overlay" id="documentModal">
    <div class="modal modal--form">
      <h3 id="documentModalTitle">Upload Document</h3>
      <form id="documentForm" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="doc_id">
        <div class="form-group">
          <label class="form-label" for="doc_type">Document Type</label>
          <select id="doc_type" name="document_type" class="form-input form-input--select">
            <option value="cv">CV / Resume</option>
            <option value="qualification">Qualification Certificate</option>
            <option value="supporting">Supporting Document</option>
          </select>
          <div class="form-error" data-error-for="document_type"></div>
        </div>
        <div class="form-group">
          <label class="form-label" for="doc_file">Choose File (PDF, DOC, DOCX, max 10 MB)</label>
          <input type="file" id="doc_file" name="document" class="form-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,image/jpeg,image/png,image/webp">
          <div class="form-error" data-error-for="document"></div>
          <div class="form-error" data-error-for="document_type"></div>
        </div>
        <div class="form-options">
          <button type="button" class="btn btn--ghost document-modal-cancel">Cancel</button>
          <button type="submit" class="btn btn--primary" id="documentSaveBtn"><span class="btn__text"><i class="fas fa-upload"></i> Upload</span><span class="btn__spinner"></span></button>
        </div>
      </form>
    </div>
  </div>

  <!-- Confirmation modal -->
  <div class="modal-overlay" id="confirmModal">
    <div class="modal">
      <div class="modal__icon modal__icon--info"><i class="fas fa-exclamation-triangle"></i></div>
      <h3 id="confirmTitle">Are you sure?</h3>
      <p id="confirmMessage">This action cannot be undone.</p>
      <div class="form-options" style="justify-content:center;gap:0.5rem;">
        <button type="button" class="btn btn--ghost" id="confirmCancelBtn">Cancel</button>
        <button type="button" class="btn btn--primary" id="confirmOkBtn"><span class="btn__text">Confirm</span><span class="btn__spinner"></span></button>
      </div>
    </div>
  </div>

<script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/dashboard.js') ?>"></script>
  <script src="<?= url('js/profile.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
