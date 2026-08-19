<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Settings Page
 * ================================================
 * Premium account management centre. Reuses the
 * existing dashboard layout, design system and
 * notification styling. All mutations are handled
 * securely via candidate/settings_actions.php
 * (auth + CSRF + prepared statements).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId   = (int) current_user_id();
$user     = User::find($userId);
$profile  = CandidateProfile::ensureForUser($userId);
$session  = current_user();

$fullName   = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$verified   = !empty($user['email_verified_at']);
$accountStatus = $user['status'] ?? 'pending';

// Gather data for the Settings workspace
$completion      = (int) ($profile['completion_percent'] ?? CandidateProfile::calculateCompletion($userId));
$notifications   = SettingsController::notificationStateForUser($userId);
$consents        = SettingsController::consentStateForUser($userId);
$preferences     = SettingsController::preferencesForUser($userId);
$sessions        = SettingsController::sessionsForUser($userId);
$trustedDevices  = SettingsController::trustedDevicesForUser($userId);
$accountStatus   = SettingsController::accountStatus($userId);

$documents = Document::forUser($userId);
$cvCount = 0;
$cvVerified = false;
$cvPending = false;
$recentDocs = [];
foreach ($documents as $doc) {
    if ($doc['document_type'] === 'cv') {
        $cvCount++;
        if ($doc['verification_status'] === 'verified') $cvVerified = true;
        if ($doc['verification_status'] === 'pending') $cvPending = true;
    }
    if (count($recentDocs) < 3) {
        $recentDocs[] = $doc;
    }
}
$docCount = count($documents);

$flashes = render_flashes();

// Consent definitions for the Privacy & Consent section
$consentDefs = [
    CONSENT_PROGRAMME => [
        'label' => 'Programme Administration',
        'desc'  => 'Allows Investhood IT to process your information to administer and manage your programme participation.',
        'why'   => 'Required to manage your enrolment, progress and programme records.',
        'required' => true,
    ],
    CONSENT_TALENT_POOL => [
        'label' => 'Talent Pool Participation',
        'desc'  => 'Allows your profile to be included in the talent pool so employers and recruiters can discover you.',
        'why'   => 'Enables matching with relevant employers and opportunities.',
        'required' => false,
    ],
    CONSENT_FUTURE_OPPORTUNITIES => [
        'label' => 'Future Opportunities',
        'desc'  => 'Allows your profile to be used to match you with future opportunities, internships and programmes.',
        'why'   => 'Helps us surface opportunities that fit your skills and goals.',
        'required' => false,
    ],
    CONSENT_CLIENT_SUBMISSION => [
        'label' => 'Client / Recruitment Opportunities',
        'desc'  => 'Allows your profile to be submitted to client organisations for recruitment opportunities.',
        'why'   => 'Required for placement and recruitment submissions.',
        'required' => false,
    ],
];
?>
<?php $avatar = url('candidate/avatar.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Settings - Investhood IT Programme & Scarce Skills Platform">
  <title>Settings | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/settings.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page settings-page">

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
          <li><a href="<?= url('candidate/profile.php') ?>" class="sidebar__link"><i class="fas fa-user"></i> My Profile</a></li>
          <li><a href="<?= url('candidate/settings.php') ?>" class="sidebar__link active"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>

        <div class="sidebar__section-label">Account</div>
        <ul class="sidebar__menu">
          <li><a href="#settings-account" class="sidebar__link" data-settings="account"><i class="fas fa-user-cog"></i> Account</a></li>
          <li><a href="#settings-security" class="sidebar__link" data-settings="security"><i class="fas fa-shield-alt"></i> Security</a></li>
          <li><a href="#settings-notifications" class="sidebar__link" data-settings="notifications"><i class="fas fa-bell"></i> Notifications</a></li>
          <li><a href="#settings-privacy" class="sidebar__link" data-settings="privacy"><i class="fas fa-user-shield"></i> Privacy &amp; Consent</a></li>
          <li><a href="#settings-preferences" class="sidebar__link" data-settings="preferences"><i class="fas fa-sliders-h"></i> Preferences</a></li>
          <li><a href="#settings-data" class="sidebar__link" data-settings="data"><i class="fas fa-folder-open"></i> Documents &amp; Data</a></li>
          <li><a href="#settings-sessions" class="sidebar__link" data-settings="sessions"><i class="fas fa-signal"></i> Sessions</a></li>
        </ul>
      </nav>

      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="<?= $avatar ?>" alt="Profile">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($fullName ?: ($user['username'] ?? 'Candidate')) ?></span>
            <span class="sidebar__user-role"><?= e($session['role_name'] ?? 'Candidate') ?></span>
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
            <input type="text" class="dash-header__search-input" placeholder="Search settings... " aria-label="Search settings">
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
            <img src="<?= $avatar ?>" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <div class="dash-content settings-layout" id="dashContent">

        <!-- ===== PAGE HEADER ===== -->
        <section class="settings-hero">
          <div class="settings-hero__inner">
            <div class="settings-hero__text">
              <h1 class="settings-hero__title">Settings</h1>
              <p class="settings-hero__subtitle">Manage your account, security, notifications, privacy, and preferences.</p>
            </div>
            <div class="settings-hero__status">
              <span class="settings-hero__badge settings-hero__badge--active"><i class="fas fa-circle"></i> Account Active</span>
            </div>
          </div>
        </section>

        <!-- ===== SETTINGS WORKSPACE (two-column) ===== -->
        <div class="settings-workspace">

          <!-- ===== LEFT: SETTINGS NAVIGATION ===== -->
          <aside class="settings-nav" id="settingsNav">
            <nav class="settings-nav__inner" aria-label="Settings navigation">
              <a href="#settings-account" class="settings-nav__item active" data-settings="account">
                <span class="settings-nav__icon"><i class="fas fa-user-cog"></i></span>
                <span class="settings-nav__label">Account</span>
              </a>
              <a href="#settings-security" class="settings-nav__item" data-settings="security">
                <span class="settings-nav__icon"><i class="fas fa-shield-alt"></i></span>
                <span class="settings-nav__label">Security</span>
              </a>
              <a href="#settings-notifications" class="settings-nav__item" data-settings="notifications">
                <span class="settings-nav__icon"><i class="fas fa-bell"></i></span>
                <span class="settings-nav__label">Notifications</span>
              </a>
              <a href="#settings-privacy" class="settings-nav__item" data-settings="privacy">
                <span class="settings-nav__icon"><i class="fas fa-user-shield"></i></span>
                <span class="settings-nav__label">Privacy &amp; Consent</span>
              </a>
              <a href="#settings-preferences" class="settings-nav__item" data-settings="preferences">
                <span class="settings-nav__icon"><i class="fas fa-sliders-h"></i></span>
                <span class="settings-nav__label">Preferences</span>
              </a>
              <a href="#settings-data" class="settings-nav__item" data-settings="data">
                <span class="settings-nav__icon"><i class="fas fa-folder-open"></i></span>
                <span class="settings-nav__label">Documents &amp; Data</span>
              </a>
              <a href="#settings-sessions" class="settings-nav__item" data-settings="sessions">
                <span class="settings-nav__icon"><i class="fas fa-signal"></i></span>
                <span class="settings-nav__label">Sessions</span>
              </a>
            </nav>

            <!-- Account status card (secondary) -->
            <div class="settings-status-card">
              <div class="settings-status-card__header"><i class="fas fa-id-card"></i> Account Status</div>
              <ul class="settings-status-card__list">
                <li><span>Account</span><span class="settings-status-card__val settings-status-card__val--active"><i class="fas fa-check-circle"></i> <?= e(ucfirst($accountStatus['status'])) ?></span></li>
                <li><span>Email</span><span class="settings-status-card__val <?= $accountStatus['email_verified'] ? 'settings-status-card__val--active' : 'settings-status-card__val--warn' ?>"><i class="fas <?= $accountStatus['email_verified'] ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= $accountStatus['email_verified'] ? 'Verified' : 'Unverified' ?></span></li>
                <li><span>Profile</span><span class="settings-status-card__val"><?= (int)$accountStatus['completion'] ?>% Complete</span></li>
                <li><span>Talent Pool</span><span class="settings-status-card__val <?= $accountStatus['talent_pool'] ? 'settings-status-card__val--active' : 'settings-status-card__val--muted' ?>"><i class="fas fa-circle"></i> <?= $accountStatus['talent_pool'] ? 'Active' : 'Inactive' ?></span></li>
              </ul>
            </div>
          </aside>

          <!-- ===== RIGHT: SELECTED SETTINGS CONTENT ===== -->
          <div class="settings-panels">

            <!-- Mobile: settings selector -->
            <div class="settings-select">
              <label class="settings-select__label" for="settingsMobileSelect">Settings Category</label>
              <select id="settingsMobileSelect" class="form-input form-input--select">
                <option value="account">Account</option>
                <option value="security">Security</option>
                <option value="notifications">Notifications</option>
                <option value="privacy">Privacy &amp; Consent</option>
                <option value="preferences">Preferences</option>
                <option value="data">Documents &amp; Data</option>
                <option value="sessions">Sessions</option>
              </select>
            </div>

            <!-- ============ ACCOUNT ============ -->
            <section class="settings-panel is-active" id="settings-account" data-panel="account">
              <div class="settings-card">
                <div class="settings-card__header">
                  <div class="settings-card__header-icon"><i class="fas fa-user-cog"></i></div>
                  <div>
                    <h2>Account</h2>
                    <p>Manage your personal account information.</p>
                  </div>
                </div>

                <div class="settings-card__body">
                  <div class="settings-subsection">
                    <h3 class="settings-subsection__title"><i class="fas fa-id-card"></i> Personal Account Information</h3>
                    <div class="info-grid">
                      <div class="info-item"><span class="info-item__label">Full Name</span><span class="info-item__value"><?= e($fullName ?: '—') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Username</span><span class="info-item__value"><?= e($user['username'] ?? '—') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Email Address</span><span class="info-item__value"><?= e($user['email'] ?? '—') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Phone Number</span><span class="info-item__value"><?= e($user['phone'] ?? '—') ?></span></div>
                    </div>

                    <div class="profile-edit-section" id="accountEditSection">
                      <div class="profile-edit-section__inner">
                        <div class="profile-edit-section__title"><i class="fas fa-id-card"></i> Edit Account Information</div>
                        <form id="accountForm" class="form" novalidate>
                          <?= csrf_field() ?>
                          <div class="form-row">
                            <div class="form-group">
                              <label class="form-label" for="acct_first_name">First Name <span class="form-label__required">*</span></label>
                              <input type="text" id="acct_first_name" name="first_name" class="form-input" value="<?= e($user['first_name'] ?? '') ?>" required>
                              <div class="form-error" data-error-for="first_name"></div>
                            </div>
                            <div class="form-group">
                              <label class="form-label" for="acct_last_name">Last Name <span class="form-label__required">*</span></label>
                              <input type="text" id="acct_last_name" name="last_name" class="form-input" value="<?= e($user['last_name'] ?? '') ?>" required>
                              <div class="form-error" data-error-for="last_name"></div>
                            </div>
                          </div>
                          <div class="form-row">
                            <div class="form-group">
                              <label class="form-label" for="acct_username">Username <span class="form-label__required">*</span></label>
                              <input type="text" id="acct_username" name="username" class="form-input" value="<?= e($user['username'] ?? '') ?>" required>
                              <div class="form-error" data-error-for="username"></div>
                            </div>
                            <div class="form-group">
                              <label class="form-label" for="acct_email">Email Address <span class="form-label__required">*</span></label>
                              <input type="email" id="acct_email" name="email" class="form-input" value="<?= e($user['email'] ?? '') ?>" required>
                              <div class="form-error" data-error-for="email"></div>
                            </div>
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="acct_phone">Phone Number <span class="form-label__required">*</span></label>
                            <input type="tel" id="acct_phone" name="phone" class="form-input" value="<?= e($user['phone'] ?? '') ?>" required>
                            <div class="form-error" data-error-for="phone"></div>
                          </div>
                          <div class="settings-note">
                            <i class="fas fa-info-circle"></i>
                            <span>Your User ID, role, account creation date and account status are system-controlled and cannot be changed here. Changing your email address will require you to verify the new email.</span>
                          </div>
                          <div class="form-group profile-form__actions">
                            <button type="submit" class="btn btn--primary" id="accountSaveBtn">
                              <span class="btn__text"><i class="fas fa-save"></i> Save Account Information</span>
                              <span class="btn__spinner"></span>
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <div class="settings-subsection">
                    <h3 class="settings-subsection__title"><i class="fas fa-shield-alt"></i> Account &amp; Email Status</h3>
                    <div class="info-grid">
                      <div class="info-item"><span class="info-item__label">User ID</span><span class="info-item__value">#<?= (int)$user['id'] ?></span></div>
                      <div class="info-item"><span class="info-item__label">Role</span><span class="info-item__value"><?= e($session['role_name'] ?? 'Candidate') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Member Since</span><span class="info-item__value"><?= e(!empty($user['created_at']) ? format_date($user['created_at'], 'd M Y') : '—') ?></span></div>
                      <div class="info-item"><span class="info-item__label">Account Status</span><span class="info-item__value"><?= e(ucfirst($accountStatus['status'])) ?></span></div>
                    </div>

                    <div class="settings-verify">
                      <?php if ($verified): ?>
                      <div class="settings-verify__status settings-verify__status--good"><i class="fas fa-check-circle"></i> Email Verified</div>
                      <?php else: ?>
                      <div class="settings-verify__status settings-verify__status--warn"><i class="fas fa-exclamation-circle"></i> Email Not Verified</div>
                      <p class="settings-verify__text">Verify your email address to fully activate your account and receive important notifications.</p>
                      <button type="button" class="btn btn--outline btn--sm" id="resendVerifyBtn"><i class="fas fa-paper-plane"></i> Resend Verification Email</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- PROFILE SETTINGS quick links -->
              <div class="settings-card">
                <div class="settings-card__header">
                  <div class="settings-card__header-icon settings-card__header-icon--cyan"><i class="fas fa-user"></i></div>
                  <div>
                    <h2>Profile Settings</h2>
                    <p>Manage your professional profile and documents.</p>
                  </div>
                </div>
                <div class="settings-card__body">
                  <div class="settings-profile-completion">
                    <div class="completeness-ring">
                      <svg viewBox="0 0 36 36" class="completeness-ring__svg">
                        <path class="completeness-ring__bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="completeness-ring__fill" stroke-dasharray="<?= (int)$completion ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                      </svg>
                      <span class="completeness-ring__text"><?= (int)$completion ?>%</span>
                    </div>
                    <div class="settings-profile-completion__info">
                      <strong>Profile Completion</strong>
                      <p>Keep your professional profile up to date to improve your experience on the platform.</p>
                      <a href="<?= url('candidate/profile.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-user-edit"></i> Go to My Profile</a>
                    </div>
                  </div>
                  <div class="settings-quicklinks">
                    <a href="<?= url('candidate/profile.php') ?>" class="settings-quicklink"><i class="fas fa-camera"></i> Update Profile Picture</a>
                    <a href="<?= url('candidate/profile.php#profile-summary') ?>" class="settings-quicklink"><i class="fas fa-align-left"></i> Update Professional Summary</a>
                    <a href="<?= url('candidate/profile.php#profile-skills') ?>" class="settings-quicklink"><i class="fas fa-code"></i> Update Skills</a>
                    <a href="<?= url('candidate/profile.php#profile-qualifications') ?>" class="settings-quicklink"><i class="fas fa-graduation-cap"></i> Update Qualifications</a>
                    <a href="<?= url('candidate/profile.php#profile-experience') ?>" class="settings-quicklink"><i class="fas fa-briefcase"></i> Update Work Experience</a>
                  </div>
                </div>
              </div>
            </section>

            <!-- ============ SECURITY ============ -->
            <section class="settings-panel" id="settings-security" data-panel="security">
              <div class="settings-card">
                <div class="settings-card__header">
                  <div class="settings-card__header-icon"><i class="fas fa-shield-alt"></i></div>
                  <div>
                    <h2>Security</h2>
                    <p>Manage your password and account security.</p>
                  </div>
                </div>
                <div class="settings-card__body">
                  <div class="settings-subsection">
                    <h3 class="settings-subsection__title"><i class="fas fa-key"></i> Change Password</h3>
                    <form id="passwordForm" class="form" novalidate>
                      <?= csrf_field() ?>
                      <div class="form-group">
                        <label class="form-label" for="current_password">Current Password <span class="form-label__required">*</span></label>
                        <div class="password-input-wrapper">
                          <input type="password" id="current_password" name="current_password" class="form-input" autocomplete="current-password" required>
                          <button type="button" class="password-toggle" aria-label="Show/Hide password"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="form-error" data-error-for="current_password"></div>
                      </div>
                      <div class="form-row">
                        <div class="form-group">
                          <label class="form-label" for="new_password">New Password <span class="form-label__required">*</span></label>
                          <div class="password-input-wrapper">
                            <input type="password" id="new_password" name="new_password" class="form-input" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" aria-label="Show/Hide password"><i class="fas fa-eye"></i></button>
                          </div>
                          <div class="password-strength">
                            <span class="password-strength__segment"></span>
                            <span class="password-strength__segment"></span>
                            <span class="password-strength__segment"></span>
                            <span class="password-strength__segment"></span>
                            <span class="password-strength__segment"></span>
                            <span class="password-strength__text"></span>
                          </div>
                          <div class="form-error" data-error-for="new_password"></div>
                        </div>
                        <div class="form-group">
                          <label class="form-label" for="confirm_password">Confirm New Password <span class="form-label__required">*</span></label>
                          <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" aria-label="Show/Hide password"><i class="fas fa-eye"></i></button>
                          </div>
                          <div class="form-error" data-error-for="confirm_password"></div>
                        </div>
                      </div>
                      <div class="settings-note">
                        <i class="fas fa-info-circle"></i>
                        <span>Password must be at least <?= PASSWORD_MIN_LENGTH ?> characters with an uppercase letter, lowercase letter, number and special character. Changing your password logs you out of other sessions.</span>
                      </div>
                      <div class="form-group profile-form__actions">
                        <button type="submit" class="btn btn--primary" id="passwordSaveBtn">
                          <span class="btn__text"><i class="fas fa-key"></i> Update Password</span>
                          <span class="btn__spinner"></span>
                        </button>
                      </div>
                    </form>
                  </div>

                  <div class="settings-subsection">
                    <h3 class="settings-subsection__title"><i class="fas fa-lock"></i> Two-Factor Authentication</h3>
                    <div class="settings-feature-row">
                      <div class="settings-feature-row__info">
                        <strong>Two-Factor Authentication</strong>
                        <p>Add an additional layer of security to your account.</p>
                      </div>
                      <span class="tag tag--amber"><i class="fas fa-hourglass-half"></i> Coming Soon</span>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- ============ NOTIFICATIONS ============ -->
            <section class="settings-panel" id="settings-notifications" data-panel="notifications">
              <div class="settings-card">
                <div class="settings-card__header">
                  <div class="settings-card__header-icon"><i class="fas fa-bell"></i></div>
                  <div>
                    <h2>Notifications</h2>
                    <p>Control what you receive and how you receive it.</p>
                  </div>
                </div>
                <div class="settings-card__body">
                  <form id="notificationsForm" class="form" novalidate>
                    <?= csrf_field() ?>
                    <div class="settings-subsection">
                      <h3 class="settings-subsection__title"><i class="fas fa-bell"></i> Communication Preferences</h3>
                      <div class="settings-notif-list">
                        <?php foreach ($notifications as $key => $n): ?>
                        <div class="settings-notif-item">
                          <div class="settings-notif-item__info">
                            <strong><?= e($n['label']) ?></strong>
                            <span><?= $n['required'] ? 'Required system notification — cannot be disabled.' : 'Optional notification.' ?></span>
                          </div>
                          <label class="toggle <?= $n['required'] ? 'toggle--disabled' : '' ?>">
                            <input type="checkbox" class="notif-toggle" data-cat="<?= e($key) ?>" name="enabled_<?= e($key) ?>" <?= $n['enabled'] ? 'checked' : '' ?> <?= $n['required'] ? 'disabled' : '' ?>>
                            <span class="toggle__slider"></span>
                          </label>
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="settings-subsection">
                      <h3 class="settings-subsection__title"><i class="fas fa-envelope"></i> Email Notifications</h3>
                      <div class="settings-notif-list">
                        <?php foreach ($notifications as $key => $n): if ($n['required']) continue; ?>
                        <div class="settings-notif-item">
                          <div class="settings-notif-item__info">
                            <strong><?= e($n['label']) ?></strong>
                            <span>Receive email notifications for this category.</span>
                          </div>
                          <label class="toggle">
                            <input type="checkbox" class="email-toggle" data-cat="<?= e($key) ?>" name="email_<?= e($key) ?>" <?= $n['email_enabled'] ? 'checked' : '' ?>>
                            <span class="toggle__slider"></span>
                          </label>
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="form-group profile-form__actions">
                      <button type="submit" class="btn btn--primary" id="notificationsSaveBtn">
                        <span class="btn__text"><i class="fas fa-save"></i> Save Notification Preferences</span>
                        <span class="btn__spinner"></span>
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </section>

            <!-- ============ PRIVACY & CONSENT ============ -->
            <section class="settings-panel" id="settings-privacy" data-panel="privacy">
              <div class="settings-card">
                <div class="settings-card__header">
                  <div class="settings-card__header-icon"><i class="fas fa-user-shield"></i></div>
                  <div>
                    <h2>Privacy &amp; Consent</h2>
                    <p>Manage how your personal information may be used within the Investhood IT platform.</p>
                  </div>
                </div>
                <div class="settings-card__body">
                  <div class="settings-subsection">
                    <p class="settings-privacy-intro">Each consent purpose is managed separately. You can withdraw optional consent at any time; required consents are needed to provide core services.</p>
                    <div class="consent-list">
                      <?php foreach ($consentDefs as $purpose => $def): $st = $consents[$purpose] ?? ['status' => 'never', 'granted_at' => null, 'withdrawn_at' => null]; ?>
                      <div class="consent-item" data-purpose="<?= e($purpose) ?>">
                        <div class="consent-item__info">
                          <strong><?= e($def['label']) ?></strong>
                          <span><?= e($def['desc']) ?></span>
                          <span class="consent-item_why"><?= e($def['why']) ?> <?= $def['required'] ? '<span class="tag tag--primary">Required</span>' : '<span class="tag tag--cyan">Optional</span>' ?></span>
                          <?php if ($st['status'] === 'granted'): ?>
                            <span class="consent-item__status consent-item__status--granted"><i class="fas fa-check-circle"></i> Active<?= !empty($st['granted_at']) ? ' since ' . e(format_date($st['granted_at'], 'd M Y')) : '' ?></span>
                          <?php elseif ($st['status'] === 'withdrawn'): ?>
                            <span class="consent-item__status consent-item__status--withdrawn"><i class="fas fa-times-circle"></i> Withdrawn<?= !empty($st['withdrawn_at']) ? ' on ' . e(format_date($st['withdrawn_at'], 'd M Y')) : '' ?></span>
                          <?php else: ?>
                            <span class="consent-item__status consent-item__status--never"><i class="fas fa-minus-circle"></i> Not set</span>
                          <?php endif; ?>
                        </div>
                        <label class="toggle <?= $def['required'] ? 'toggle--disabled' : '' ?>">
                          <input type="checkbox" class="consent-toggle" data-purpose="<?= e($purpose) ?>" <?= $st['status'] === 'granted' ? 'checked' : '' ?> <?= $def['required'] ? 'disabled' : '' ?>>
                          <span class="toggle__slider"></span>
                        </label>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- ============ PREFERENCES ============ -->
            <section class="settings-panel" id="settings-preferences" data-panel="preferences">
              <div class="settings-card">
                <div class="settings-card__header">
                  <div class="settings-card__header-icon"><i class="fas fa-sliders-h"></i></div>
                  <div>
                    <h2>Preferences</h2>
                    <p>Customise your display and language settings.</p>
                  </div>
                </div>
                <div class="settings-card__body">
                  <form id="preferencesForm" class="form" novalidate>
                    <?= csrf_field() ?>
                    <div class="settings-subsection">
                      <h3 class="settings-subsection__title"><i class="fas fa-palette"></i> Theme</h3>
                      <div class="settings-radio-group">
                        <label class="settings-radio">
                          <input type="radio" name="theme" value="system" <?= $preferences['theme'] === 'system' ? 'checked' : '' ?>>
                          <span><i class="fas fa-desktop"></i> System Default</span>
                        </label>
                        <label class="settings-radio">
                          <input type="radio" name="theme" value="light" <?= $preferences['theme'] === 'light' ? 'checked' : '' ?>>
                          <span><i class="fas fa-sun"></i> Light</span>
                        </label>
                        <label class="settings-radio settings-radio--disabled">
                          <input type="radio" name="theme" value="dark" <?= $preferences['theme'] === 'dark' ? 'checked' : '' ?> disabled>
                          <span><i class="fas fa-moon"></i> Dark Mode <em>Coming Soon</em></span>
                        </label>
                      </div>
                    </div>

                    <div class="settings-subsection">
                      <h3 class="settings-subsection__title"><i class="fas fa-globe"></i> Language &amp; Region</h3>
                      <div class="form-row">
                        <div class="form-group">
                          <label class="form-label" for="email_language">Email Language</label>
                          <select id="email_language" name="email_language" class="form-input form-input--select">
                            <option value="en" <?= $preferences['email_language'] === 'en' ? 'selected' : '' ?>>English</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label class="form-label" for="timezone">Time Zone</label>
                          <select id="timezone" name="timezone" class="form-input form-input--select">
                            <option value="auto" <?= $preferences['timezone'] === 'auto' ? 'selected' : '' ?>>Automatically detected</option>
                            <option value="Africa/Johannesburg" <?= $preferences['timezone'] === 'Africa/Johannesburg' ? 'selected' : '' ?>>South Africa (GMT+2)</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label class="form-label" for="date_format">Date Format</label>
                          <select id="date_format" name="date_format" class="form-input form-input--select">
                            <option value="DD/MM/YYYY" <?= $preferences['date_format'] === 'DD/MM/YYYY' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                            <option value="MM/DD/YYYY" <?= $preferences['date_format'] === 'MM/DD/YYYY' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                            <option value="YYYY-MM-DD" <?= $preferences['date_format'] === 'YYYY-MM-DD' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                          </select>
                        </div>
                      </div>
                    </div>

                    <div class="form-group profile-form__actions">
                      <button type="submit" class="btn btn--primary" id="preferencesSaveBtn">
                        <span class="btn__text"><i class="fas fa-save"></i> Save Preferences</span>
                        <span class="btn__spinner"></span>
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </section>

            <!-- ============ DOCUMENTS & DATA ============ -->
            <section class="settings-panel" id="settings-data" data-panel="data">
              <div class="settings-card">
                <div class="settings-card__header">
                  <div class="settings-card__header-icon"><i class="fas fa-folder-open"></i></div>
                  <div>
                    <h2>My Documents</h2>
                    <p>Manage your uploaded documents and request your data.</p>
                  </div>
                </div>
                <div class="settings-card__body">
                  <div class="settings-subsection">
                    <div class="settings-doc-summary">
                      <div class="settings-doc-summary__item">
                        <span class="settings-doc-summary__num"><?= (int)$docCount ?></span>
                        <span class="settings-doc-summary__label">Documents Uploaded</span>
                      </div>
                      <div class="settings-doc-summary__item">
                        <span class="settings-doc-summary__num"><?= $cvCount > 0 ? 'Yes' : 'No' ?></span>
                        <span class="settings-doc-summary__label">CV Uploaded</span>
                      </div>
                      <div class="settings-doc-summary__item">
                        <?php if ($cvVerified): ?>
                        <span class="settings-doc-summary__num settings-doc-summary__num--good"><i class="fas fa-check-circle"></i></span>
                        <?php elseif ($cvPending): ?>
                        <span class="settings-doc-summary__num settings-doc-summary__num--warn"><i class="fas fa-clock"></i></span>
                        <?php else: ?>
                        <span class="settings-doc-summary__num">—</span>
                        <?php endif; ?>
                        <span class="settings-doc-summary__label">CV Status</span>
                      </div>
                    </div>
                    <a href="<?= url('candidate/profile.php#profile-documents') ?>" class="btn btn--primary btn--sm"><i class="fas fa-folder-open"></i> Manage Documents</a>
                  </div>

                  <div class="settings-subsection">
                    <h3 class="settings-subsection__title"><i class="fas fa-clock"></i> Recently Uploaded</h3>
                    <?php if (empty($recentDocs)): ?>
                    <p class="settings-muted">No documents uploaded yet.</p>
                    <?php else: ?>
                    <ul class="settings-recent-docs">
                      <?php foreach ($recentDocs as $doc): ?>
                      <li>
                        <i class="fas fa-file-alt"></i>
                        <span class="settings-recent-docs__name"><?= e($doc['original_filename']) ?></span>
                        <span class="settings-recent-docs__meta"><?= e(ucwords($doc['document_type'])) ?> · <?= e(format_date($doc['created_at'], 'd M Y')) ?></span>
                      </li>
                      <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                  </div>

                  <div class="settings-subsection">
                    <h3 class="settings-subsection__title"><i class="fas fa-download"></i> Download My Data</h3>
                    <p class="settings-muted">Request a copy of the personal information we hold about you.</p>
                    <button type="button" class="btn btn--outline btn--sm" id="exportDataBtn"><i class="fas fa-download"></i> Download My Data</button>
                  </div>
                </div>
              </div>
            </section>

            <!-- ============ SESSIONS ============ -->
            <section class="settings-panel" id="settings-sessions" data-panel="sessions">
              <div class="settings-card">
                <div class="settings-card__header">
                  <div class="settings-card__header-icon"><i class="fas fa-signal"></i></div>
                  <div>
                    <h2>Login &amp; Session Security</h2>
                    <p>Review your recent account activity and active sessions.</p>
                  </div>
                </div>
                <div class="settings-card__body">
                  <div class="settings-subsection">
                    <h3 class="settings-subsection__title"><i class="fas fa-history"></i> Recent Account Activity</h3>
                    <?php if (empty($sessions)): ?>
                    <p class="settings-muted">No session history found.</p>
                    <?php else: ?>
                    <ul class="settings-session-list">
                      <?php foreach ($sessions as $s): ?>
                      <li class="settings-session-item <?= !empty($s['is_active']) ? 'settings-session-item--active' : '' ?>">
                        <div class="settings-session-item__icon"><i class="fas <?= !empty($s['is_active']) ? 'fa-laptop' : 'fa-laptop-code' ?>"></i></div>
                        <div class="settings-session-item__info">
                          <strong><?= !empty($s['is_current']) ? 'Current Session' : (!empty($s['is_active']) ? 'Active Session' : 'Previous Session') ?></strong>
                          <span><i class="fas fa-desktop"></i> <?= e(self_detect_browser($s['user_agent'] ?? '')) ?></span>
                          <span><i class="fas fa-map-marker-alt"></i> <?= e($s['ip_address'] ?? 'Unknown') ?> · Last active <?= e(!empty($s['last_activity']) ? time_ago($s['last_activity']) : '—') ?></span>
                        </div>
                        <div class="settings-session-item__status">
                          <?php if (!empty($s['is_active'])): ?>
                          <span class="tag tag--green"><i class="fas fa-circle"></i> Active Now</span>
                          <?php else: ?>
                          <span class="tag tag--muted"><i class="fas fa-sign-out-alt"></i> Logged out</span>
                          <?php endif; ?>
                        </div>
                      </li>
                      <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                  </div>

                  <div class="settings-subsection">
                    <div class="settings-session-actions">
                      <button type="button" class="btn btn--outline btn--sm" id="logoutOtherBtn"><i class="fas fa-sign-out-alt"></i> Log Out Other Sessions</button>
                      <button type="button" class="btn btn--ghost btn--sm" id="logoutEverywhereBtn"><i class="fas fa-sign-out-alt"></i> Log Out Everywhere</button>
                    </div>
                  </div>

                  <div class="settings-subsection">
                    <h3 class="settings-subsection__title"><i class="fas fa-mobile-alt"></i> Remember Me / Trusted Devices</h3>
                    <p class="settings-muted">Stay signed in on trusted devices. You can revoke any remembered device below.</p>
                    <?php if (empty($trustedDevices)): ?>
                    <p class="settings-muted">No trusted devices currently saved.</p>
                    <?php else: ?>
                    <ul class="settings-device-list">
                      <?php foreach ($trustedDevices as $device): ?>
                      <li class="settings-session-item">
                        <div class="settings-session-item__icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="settings-session-item__info">
                          <strong>Trusted Device</strong>
                          <span><i class="fas fa-desktop"></i> <?= e(self_detect_browser($device['user_agent'] ?? '')) ?></span>
                          <span><i class="fas fa-calendar-alt"></i> Remembered since <?= e(format_date($device['created_at'] ?? '', 'd M Y')) ?> · Expires <?= e(format_date($device['expires_at'] ?? '', 'd M Y')) ?></span>
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm device-revoke" data-id="<?= (int)$device['id'] ?>"><i class="fas fa-trash"></i> Revoke</button>
                      </li>
                      <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn btn--ghost btn--sm" id="revokeAllDevicesBtn"><i class="fas fa-trash"></i> Revoke All Trusted Devices</button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </section>

            <!-- ============ DANGER ZONE ============ -->
            <section class="settings-panel" data-panel="danger">
              <div class="settings-danger">
                <div class="settings-danger__header"><i class="fas fa-exclamation-triangle"></i> Danger Zone</div>
                <div class="settings-danger__item">
                  <div class="settings-danger__info">
                    <strong>Deactivate Account</strong>
                    <p>Temporarily disable your account. You must contact support to reactivate it.</p>
                  </div>
                  <button type="button" class="btn btn--outline btn--sm" id="deactivateBtn"><i class="fas fa-pause-circle"></i> Deactivate Account</button>
                </div>
                <div class="settings-danger__item">
                  <div class="settings-danger__info">
                    <strong>Delete Account</strong>
                    <p>Permanently request deletion of your account and associated personal information, subject to applicable retention requirements.</p>
                  </div>
                  <button type="button" class="btn btn--ghost btn--sm settings-btn--danger" id="deleteBtn"><i class="fas fa-trash-alt"></i> Request Account Deletion</button>
                </div>
              </div>
            </section>

          </div><!-- // settings-panels -->
        </div><!-- // settings-workspace -->
      </div><!-- // dash-content -->

      <footer class="dash-footer">
        <div class="container">
          <div class="dash-footer__inner">
            <p>&copy; 2025 Investhood IT. All rights reserved.</p>
            <div class="dash-footer__links">
              <a href="#">Privacy Policy</a>
              <a href="#">Terms &amp; Conditions</a>
              <a href="<?= url('index.php') ?>">Back to Home</a>
            </div>
          </div>
        </div>
      </footer>

    </main>
  </div>

  <!-- ============ MODALS ============ -->

  <!-- Deactivate confirmation -->
  <div class="modal-overlay" id="deactivateModal">
    <div class="modal modal--form">
      <div class="modal__icon modal__icon--warn"><i class="fas fa-pause-circle"></i></div>
      <h3>Deactivate Account?</h3>
      <p>Temporarily disabling your account will log you out and remove your profile from active matching. You must contact support to reactivate it.</p>
      <form id="deactivateForm" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="deactivate_password">Confirm Password <span class="form-label__required">*</span></label>
          <div class="password-input-wrapper">
            <input type="password" id="deactivate_password" name="password" class="form-input" autocomplete="current-password" required>
            <button type="button" class="password-toggle" aria-label="Show/Hide password"><i class="fas fa-eye"></i></button>
          </div>
          <div class="form-error" data-error-for="password"></div>
        </div>
        <div class="form-options" style="justify-content:center;gap:0.5rem;">
          <button type="button" class="btn btn--ghost modal-cancel" data-modal="deactivateModal">Cancel</button>
          <button type="submit" class="btn btn--primary" id="deactivateConfirmBtn"><span class="btn__text"><i class="fas fa-pause-circle"></i> Deactivate Account</span><span class="btn__spinner"></span></button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete confirmation -->
  <div class="modal-overlay" id="deleteModal">
    <div class="modal modal--form">
      <div class="modal__icon modal__icon--danger"><i class="fas fa-trash-alt"></i></div>
      <h3>Request Account Deletion?</h3>
      <p>This will submit a deletion request to our responsible team. Your account will remain active until the request is processed, subject to applicable retention requirements.</p>
      <form id="deleteForm" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="delete_reason">Reason (optional)</label>
          <textarea id="delete_reason" name="reason" class="form-input" rows="3" placeholder="Tell us why you are leaving (optional)"></textarea>
          <div class="form-error" data-error-for="general"></div>
        </div>
        <div class="form-group">
          <label class="form-label" for="delete_password">Confirm Password <span class="form-label__required">*</span></label>
          <div class="password-input-wrapper">
            <input type="password" id="delete_password" name="password" class="form-input" autocomplete="current-password" required>
            <button type="button" class="password-toggle" aria-label="Show/Hide password"><i class="fas fa-eye"></i></button>
          </div>
          <div class="form-error" data-error-for="password"></div>
        </div>
        <div class="form-options" style="justify-content:center;gap:0.5rem;">
          <button type="button" class="btn btn--ghost modal-cancel" data-modal="deleteModal">Cancel</button>
          <button type="submit" class="btn btn--ghost settings-btn--danger" id="deleteConfirmBtn"><span class="btn__text"><i class="fas fa-trash-alt"></i> Request Deletion</span><span class="btn__spinner"></span></button>
        </div>
      </form>
    </div>
  </div>

<!-- Logout everywhere confirmation -->
  <div class="modal-overlay" id="logoutEverywhereModal">
    <div class="modal">
      <div class="modal__icon modal__icon--info"><i class="fas fa-sign-out-alt"></i></div>
      <h3>Log Out Everywhere?</h3>
      <p>This will end all active sessions and require you to sign in again on every device, including this one.</p>
      <div class="form-options" style="justify-content:center;gap:0.5rem;">
        <button type="button" class="btn btn--ghost modal-cancel" data-modal="logoutEverywhereModal">Cancel</button>
        <button type="button" class="btn btn--primary" id="logoutEverywhereConfirmBtn"><span class="btn__text"><i class="fas fa-sign-out-alt"></i> Log Out Everywhere</span><span class="btn__spinner"></span></button>
      </div>
    </div>
  </div>

<script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/dashboard.js') ?>"></script>
  <script src="<?= url('js/settings.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
