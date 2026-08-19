<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Dashboard
 * ================================================
 * Role: Candidate
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId = (int) current_user_id();
$user = current_user();
$profile = CandidateProfile::ensureForUser($userId);
$completion = (int) ($profile['completion_percent'] ?? CandidateProfile::calculateCompletion($userId));
$fullName = $user['fullname'] ?? 'Candidate';
$qualifications = Qualification::forUser($userId);
$certifications = Certification::forUser($userId);
$skills = Skill::forUser($userId);
$experiences = WorkExperience::forUser($userId);
$documents = Document::forUser($userId);
$breakdown = CandidateProfile::completionBreakdown($userId);
$hasCv = Document::hasCv($userId);
$consentFuture = Consent::hasGranted($userId, CONSENT_FUTURE_OPPORTUNITIES);

// Opportunities integration
$savedOpportunities = SavedOpportunity::forCandidate($userId);
$savedCount = SavedOpportunity::countForCandidate($userId);
$recentOpportunities = CandidateOpportunitiesController::searchOpportunities([], 1, 3)['opportunities'];

$flashes = render_flashes();
?>
<?php $avatar = url('candidate/avatar.php'); ?><?php $availLabel = $profile['availability_label'] ?? 'Availability not set'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Candidate Dashboard - Investhood IT Programme & Scarce Skills Platform">
  <title>Candidate Dashboard | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body class="dashboard-page">

  <!-- =============================================
       DASHBOARD LAYOUT
       ============================================= -->
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
          <li><a href="#dashboard-welcome" class="sidebar__link active" data-section="welcome"><i class="fas fa-th-large"></i> Dashboard</a></li>
          <li><a href="#dashboard-opportunities" class="sidebar__link" data-section="opportunities"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="#dashboard-applications" class="sidebar__link" data-section="applications"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="#dashboard-talent" class="sidebar__link" data-section="talent"><i class="fas fa-users"></i> Talent Profile</a></li>
          <li><a href="#dashboard-programmes" class="sidebar__link" data-section="programmes"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="#dashboard-placements" class="sidebar__link" data-section="placements"><i class="fas fa-briefcase"></i> Placements</a></li>
          <li><a href="#dashboard-learning" class="sidebar__link" data-section="learning"><i class="fas fa-book-open"></i> Learning & Skills</a></li>
          <li><a href="#dashboard-interviews" class="sidebar__link" data-section="interviews"><i class="fas fa-calendar-check"></i> Interviews</a></li>
<li><a href="#dashboard-notifications" class="sidebar__link" data-section="notifications"><i class="fas fa-bell"></i> Notifications</a></li>
<li><a href="#dashboard-analytics" class="sidebar__link" data-section="analytics"><i class="fas fa-chart-line"></i> Analytics</a></li>
        </ul>

        <div class="sidebar__section-label">Quick Actions</div>
        <ul class="sidebar__menu">
          <li><a href="#dashboard-quick-actions" class="sidebar__link" data-section="quick-actions"><i class="fas fa-bolt"></i> Quick Actions</a></li>
          <li><a href="#dashboard-completeness" class="sidebar__link" data-section="completeness"><i class="fas fa-check-circle"></i> Profile Completion</a></li>
        </ul>
      </nav>

      <div class="sidebar__footer">
<div class="sidebar__user">
        <div class="sidebar__user-avatar">
            <img src="<?= $avatar ?>" alt="Profile">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Candidate') ?></span>
            <span class="sidebar__user-role"><?= e($user['role_name'] ?? 'Candidate') ?></span>
          </div>
        </div>
<a href="<?= url('auth/logout.php') ?>" class="sidebar__logout" id="sidebarLogoutBtn" onclick="event.preventDefault(); var m=document.getElementById('logoutModal'); if(m){m.classList.add('active'); m.setAttribute('aria-hidden','false');}">
          <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
      </div>
    </aside>

    <!-- ===== SIDEBAR OVERLAY (mobile) ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard__main">

      <!-- ===== DASHBOARD HEADER ===== -->
      <header class="dash-header" id="dashHeader">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
          </button>
          <div class="dash-header__search" id="dashSearch">
            <i class="fas fa-search"></i>
            <input type="text" class="dash-header__search-input" placeholder="Search opportunities, programmes, skills..." aria-label="Search dashboard">
          </div>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode">
            <i class="fas fa-moon"></i>
          </button>
          <button class="dash-header__icon-btn dash-header__notif-btn" id="notifHeaderBtn" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <span class="dash-header__notif-badge">5</span>
          </button>
<div class="dash-header__user">
            <img src="<?= $avatar ?>" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <!-- ===== DASHBOARD CONTENT ===== -->
      <div class="dash-content" id="dashContent">

        <!-- =============================================
             S1: WELCOME SECTION
             ============================================= -->
        <section class="dash-section" id="dashboard-welcome">
          <div class="welcome-card">
            <div class="welcome-card__bg"></div>
            <div class="welcome-card__content">
              <div class="welcome-card__profile">
                <div class="welcome-card__avatar">
                  <img src="<?= $avatar ?>" alt="Profile">
                  <span class="welcome-card__status welcome-card__status--active"></span>
                </div>
                <div class="welcome-card__info">
                  <h1 class="welcome-card__greeting">Welcome back, <span class="text-gradient"><?= e($user['fullname'] ?? 'Candidate') ?></span></h1>
                  <p class="welcome-card__title"><?= e($profile['professional_title'] ?? ($user['role_name'] ?? 'Candidate')) ?></p>
                  <div class="welcome-card__meta">
                    <span class="welcome-card__badge welcome-card__badge--success"><i class="fas fa-circle"></i> Available</span>
                    <span class="welcome-card__badge welcome-card__badge--primary"><i class="fas fa-users"></i> Talent Pool: Active</span>
                    <span class="welcome-card__badge welcome-card__badge--amber"><i class="fas fa-graduation-cap"></i> Programme: Enrolled</span>
                  </div>
                </div>
              </div>
<div class="welcome-card__completeness">
                <div class="completeness-ring">
                  <svg viewBox="0 0 36 36" class="completeness-ring__svg">
                    <path class="completeness-ring__bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="completeness-ring__fill" stroke-dasharray="<?= (int)$completion ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  </svg>
                  <span class="completeness-ring__text"><?= (int)$completion ?>%</span>
                </div>
                <span class="welcome-card__completeness-label">Profile Complete</span>
              </div>
            </div>
            <p class="welcome-card__message">
              Continue building your professional journey with Investhood IT by exploring opportunities, developing your skills, and managing your career progression.
            </p>
<div class="welcome-card__actions">
              <a href="<?= url('candidate/profile.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-user-edit"></i> Complete Profile</a>
              <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-search"></i> Explore Opportunities</a>
              <a href="#dashboard-talent" class="btn btn--outline btn--sm"><i class="fas fa-users"></i> Join Talent Pools</a>
              <a href="<?= url('candidate/profile.php#profile-professional') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-clock"></i> Update Availability</a>
<a href="<?= url('candidate/profile.php#profile-documents') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-upload"></i> Upload Documents</a>
              <a href="<?= url('candidate/settings.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-cog"></i> Settings</a>
            </div>
          </div>
        </section>

        <!-- =============================================
             S2: CAREER OVERVIEW SECTION
             ============================================= -->
        <section class="dash-section" id="dashboard-overview">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Career <span class="text-gradient">Overview</span></h2>
          </div>
          <div class="overview-grid">
            <div class="overview-card">
              <div class="overview-card__icon overview-card__icon--primary"><i class="fas fa-file-alt"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="4">0</span>
                <span class="overview-card__label">Active Applications</span>
              </div>
              <a href="#" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="overview-card">
              <div class="overview-card__icon overview-card__icon--cyan"><i class="fas fa-graduation-cap"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="2">0</span>
                <span class="overview-card__label">Programme Enrolments</span>
              </div>
              <a href="#" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="overview-card">
              <div class="overview-card__icon overview-card__icon--amber"><i class="fas fa-briefcase"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="12">0</span>
                <span class="overview-card__label">Available Opportunities</span>
              </div>
              <a href="<?= url('candidate/opportunities.php') ?>" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="overview-card">
              <div class="overview-card__icon overview-card__icon--green"><i class="fas fa-calendar-check"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="3">0</span>
                <span class="overview-card__label">Interview Invitations</span>
              </div>
              <a href="#" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="overview-card">
              <div class="overview-card__icon overview-card__icon--purple"><i class="fas fa-user-check"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="1">0</span>
                <span class="overview-card__label">Placement Status</span>
              </div>
              <a href="#" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="overview-card">
              <div class="overview-card__icon overview-card__icon--red"><i class="fas fa-bell"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="5">0</span>
                <span class="overview-card__label">Notifications</span>
              </div>
              <a href="#" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="overview-card">
              <div class="overview-card__icon overview-card__icon--indigo"><i class="fas fa-check-double"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="8">0</span>
                <span class="overview-card__label">Skills Verified</span>
              </div>
              <a href="#" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
            </div>
<div class="overview-card">
              <div class="overview-card__icon overview-card__icon--primary"><i class="fas fa-user-circle"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="<?= (int)$completion ?>"><?= (int)$completion ?></span>
                <span class="overview-card__label">Profile Completion %</span>
              </div>
              <a href="<?= url('candidate/profile.php') ?>" class="overview-card__link">Complete <i class="fas fa-arrow-right"></i></a>
            </div>
          </div>
        </section>

        <!-- =============================================
             S3: OPPORTUNITIES SECTION
             ============================================= -->
        <section class="dash-section" id="dashboard-opportunities">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Opportunities <span class="text-gradient">For You</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Personalised opportunities based on your qualifications, skills, and preferences.</p>
          </div>

          <div class="opp-search">
            <div class="opp-search__bar">
              <i class="fas fa-search"></i>
              <input type="text" class="opp-search__input" id="oppSearchInput" placeholder="Search opportunities by title, skill, or location...">
            </div>
            <div class="opp-search__filters" id="oppFiltersContainer">
              <select class="opp-search__select" id="oppFilterType">
                <option value="all">All Types</option>
                <option value="graduate">Graduate Programme</option>
                <option value="learnership">Learnership</option>
                <option value="internship">Internship</option>
                <option value="wil">Work Integrated Learning</option>
              </select>
              <select class="opp-search__select" id="oppFilterLocation">
                <option value="all">All Locations</option>
                <option value="gauteng">Gauteng</option>
                <option value="western-cape">Western Cape</option>
                <option value="kwazulu-natal">KwaZulu-Natal</option>
                <option value="eastern-cape">Eastern Cape</option>
              </select>
              <select class="opp-search__select" id="oppFilterEmployment">
                <option value="all">All Types</option>
                <option value="full-time">Full-time</option>
                <option value="fixed-term">Fixed-term</option>
                <option value="internship">Internship</option>
                <option value="contract">Contract</option>
              </select>
            </div>
          </div>

          <div class="opp-grid" id="oppGrid">
            <!-- Populated by JS -->
          </div>

          <div class="opp-pagination" id="oppPagination">
            <button class="btn btn--ghost btn--sm" disabled><i class="fas fa-chevron-left"></i> Previous</button>
            <span class="opp-pagination__info">Page 1 of 1</span>
            <button class="btn btn--ghost btn--sm" disabled>Next <i class="fas fa-chevron-right"></i></button>
          </div>

          <!-- Recommended Section -->
          <div class="opp-recommended">
            <div class="opp-recommended__header">
              <h3><i class="fas fa-star"></i> Recommended For You</h3>
            </div>
            <div class="opp-recommended__grid" id="oppRecommendedGrid">
              <!-- Populated by JS -->
            </div>
          </div>
        </section>

        <!-- =============================================
             S4: APPLICATION TRACKER
             ============================================= -->
        <section class="dash-section" id="dashboard-applications">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Application <span class="text-gradient">Tracker</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Track your application journey from submission to outcome.</p>
          </div>
          <div class="app-tracker" id="appTracker">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             S5: TALENT COMMUNITY SECTION
             ============================================= -->
        <section class="dash-section" id="dashboard-talent">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">My Talent <span class="text-gradient">Profile</span></h2>
          </div>
          <div class="talent-profile">
            <div class="talent-profile__card">
              <div class="talent-profile__header">
                <div class="talent-profile__avatar">
                  <img src="<?= $avatar ?>" alt="Profile">
                </div>
                <div class="talent-profile__info">
                  <h3><?= e($user['fullname'] ?? 'Candidate') ?></h3>
                  <span><?= e($profile['professional_title'] ?? ($user['role_name'] ?? 'Candidate')) ?></span>
                  <span class="talent-profile__status talent-profile__status--active"><i class="fas fa-circle"></i> <?= e($availLabel) ?></span>
                </div>
                <a href="<?= url('candidate/profile.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-edit"></i> Update Profile</a>
              </div>
              <div class="talent-profile__body">
              <div class="talent-profile__section">
                  <h4><i class="fas fa-code"></i> Skills</h4>
                  <div class="talent-profile__skills">
                    <?php if (empty($skills)): ?>
                      <span class="tag tag--muted">No skills added yet.</span>
                    <?php else: ?>
                      <?php foreach ($skills as $sk): ?>
                      <span class="tag tag--primary"><?= e($sk['name']) ?></span>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="<?= url('candidate/profile.php#profile-skills') ?>" class="tag tag--add"><i class="fas fa-plus"></i> Add Skills</a>
                  </div>
                </div>
                <div class="talent-profile__section">
                  <h4><i class="fas fa-graduation-cap"></i> Qualifications</h4>
                  <ul class="talent-profile__list">
                    <?php if (empty($qualifications)): ?>
                      <li>No qualifications added yet.</li>
                    <?php else: ?>
                      <?php foreach ($qualifications as $q): ?>
                      <li><strong><?= e($q['name']) ?></strong><?= !empty($q['institution']) ? ' - ' . e($q['institution']) : '' ?><?= !empty($q['year_completed']) ? ' (' . e($q['year_completed']) . ')' : '' ?></li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                  <a href="<?= url('candidate/profile.php#profile-qualifications') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-plus"></i> Add Qualification</a>
                </div>
                <div class="talent-profile__section">
                  <h4><i class="fas fa-certificate"></i> Certifications</h4>
                  <ul class="talent-profile__list">
                    <?php if (empty($certifications)): ?>
                      <li>No certifications added yet.</li>
                    <?php else: ?>
                      <?php foreach ($certifications as $cert): ?>
                      <li><strong><?= e($cert['name']) ?></strong><?= !empty($cert['issuing_organisation']) ? ' - ' . e($cert['issuing_organisation']) : '' ?><?= !empty($cert['year_obtained']) ? ' (' . e($cert['year_obtained']) . ')' : '' ?></li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                  <a href="<?= url('candidate/profile.php#profile-qualifications') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-plus"></i> Add Certification</a>
                </div>
                <div class="talent-profile__section">
                  <h4><i class="fas fa-briefcase"></i> Experience</h4>
                  <ul class="talent-profile__list">
                    <?php if (empty($experiences)): ?>
                      <li>No work experience added yet.</li>
                    <?php else: ?>
                      <?php foreach ($experiences as $exp): ?>
                      <li><strong><?= e($exp['job_title']) ?></strong> - <?= e($exp['company']) ?> (<?= e(!empty($exp['start_date']) ? date('M Y', strtotime($exp['start_date'])) : '') ?> - <?= !empty($exp['is_current']) ? 'Present' : (!empty($exp['end_date']) ? e(date('M Y', strtotime($exp['end_date']))) : '') ?>)</li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                  <a href="<?= url('candidate/profile.php#profile-experience') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-plus"></i> Add Experience</a>
                </div>
                <div class="talent-profile__section">
                  <!--<h4><i class="fas fa-link"></i> Portfolio Links</h4>
                  <div class="talent-profile__links">
                    <a href="#" class="talent-profile__link"><i class="fab fa-github"></i> github.com/johndoe</a>
                    <a href="#" class="talent-profile__link"><i class="fab fa-linkedin"></i> linkedin.com/in/johndoe</a>
                    <a href="#" class="talent-profile__link"><i class="fas fa-globe"></i> johndoe.dev</a>
                  </div>-->
                </div>
                <div class="talent-profile__section">
                  <h4><i class="fas fa-file-pdf"></i> CV / Resume</h4>
                  <div class="talent-profile__cv">
                    <?php if ($hasCv): ?>
                      <span class="tag tag--green"><i class="fas fa-check"></i> CV Uploaded</span>
                    <?php else: ?>
                      <span class="tag tag--amber"><i class="fas fa-exclamation-circle"></i> No CV Uploaded</span>
                    <?php endif; ?>
                    <a href="<?= url('candidate/profile.php#profile-documents') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-upload"></i> Update CV</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- =============================================
             S6: PROGRAMME MANAGEMENT
             ============================================= -->
        <section class="dash-section" id="dashboard-programmes">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">My <span class="text-gradient">Programmes</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Track your active programme participation and progress.</p>
          </div>
          <div class="prog-grid" id="progGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             S7: PLACEMENTS SECTION
             ============================================= -->
        <section class="dash-section" id="dashboard-placements">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Placement <span class="text-gradient">Management</span></h2>
          </div>
          <div class="placement-card">
            <div class="placement-card__header">
              <div class="placement-card__status placement-card__status--active">
                <i class="fas fa-circle"></i> Active Placement
              </div>
              <div class="placement-card__progress">
                <span class="placement-card__progress-label">Progress: 65%</span>
                <div class="progress-bar">
                  <div class="progress-bar__fill" style="width:65%"></div>
                </div>
              </div>
            </div>
            <div class="placement-card__body">
              <div class="placement-card__info">
                <div class="placement-card__item">
                  <span class="placement-card__label">Host Organisation</span>
                  <span class="placement-card__value">TechCorp South Africa</span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Department</span>
                  <span class="placement-card__value">Software Engineering</span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Supervisor</span>
                  <span class="placement-card__value">Sarah Mokoena <span class="placement-card__sub">Senior Developer</span></span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Duration</span>
                  <span class="placement-card__value">01 Jan 2025 - 30 Jun 2025 (6 Months)</span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Workplace Location</span>
                  <span class="placement-card__value">Johannesburg, Gauteng (Hybrid)</span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Performance Status</span>
                  <span class="placement-card__value placement-card__value--success">Exceeding Expectations</span>
                </div>
              </div>
            </div>
            <div class="placement-card__footer">
              <a href="#" class="btn btn--primary btn--sm"><i class="fas fa-clock"></i> Log Activities</a>
              <a href="#" class="btn btn--outline btn--sm"><i class="fas fa-comment"></i> View Feedback</a>
              <a href="#" class="btn btn--ghost btn--sm"><i class="fas fa-history"></i> Placement History</a>
            </div>
          </div>

          <!-- Supervisor Feedback -->
          <div class="placement-feedback">
            <h3>Supervisor Feedback</h3>
            <div class="placement-feedback__card">
              <div class="placement-feedback__header">
                <div class="placement-feedback__avatar">SM</div>
                <div>
                  <strong>Sarah Mokoena</strong>
                  <span>Senior Developer • 2 days ago</span>
                </div>
              </div>
              <p class="placement-feedback__text">"John has been demonstrating excellent problem-solving skills and has shown great initiative in the current sprint. His contributions to the API integration project have been very valuable."</p>
            </div>
          </div>
        </section>

        <!-- =============================================
             S8: LEARNING & SKILLS DEVELOPMENT
             ============================================= -->
        <section class="dash-section" id="dashboard-learning">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Learning & Skills <span class="text-gradient">Development</span></h2>
          </div>
          <div class="learning-grid">
            <div class="learning-card">
              <div class="learning-card__header">
                <h4>Full-Stack Web Development</h4>
                <span class="learning-card__badge">In Progress</span>
              </div>
              <div class="learning-card__progress">
                <div class="progress-bar">
                  <div class="progress-bar__fill" style="width:60%"></div>
                </div>
                <span class="learning-card__percent">60% Complete</span>
              </div>
              <div class="learning-card__stats">
                <span><i class="fas fa-check-circle"></i> 6/10 Modules</span>
                <span><i class="fas fa-clock"></i> 2 Weeks Left</span>
              </div>
              <a href="#" class="btn btn--ghost btn--sm">Continue Learning <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="learning-card">
              <div class="learning-card__header">
                <h4>Cloud Computing Essentials</h4>
                <span class="learning-card__badge learning-card__badge--cyan">In Progress</span>
              </div>
              <div class="learning-card__progress">
                <div class="progress-bar">
                  <div class="progress-bar__fill" style="width:35%"></div>
                </div>
                <span class="learning-card__percent">35% Complete</span>
              </div>
              <div class="learning-card__stats">
                <span><i class="fas fa-check-circle"></i> 2/6 Modules</span>
                <span><i class="fas fa-clock"></i> 4 Weeks Left</span>
              </div>
              <a href="#" class="btn btn--ghost btn--sm">Continue Learning <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="learning-card learning-card--complete">
              <div class="learning-card__header">
                <h4>Agile & Scrum Fundamentals</h4>
                <span class="learning-card__badge learning-card__badge--green">Completed</span>
              </div>
              <div class="learning-card__progress">
                <div class="progress-bar">
                  <div class="progress-bar__fill" style="width:100%"></div>
                </div>
                <span class="learning-card__percent">100% Complete</span>
              </div>
              <div class="learning-card__stats">
                <span><i class="fas fa-check-circle" style="color:var(--success);"></i> 4/4 Modules</span>
                <span><i class="fas fa-certificate" style="color:var(--accent);"></i> Certificate Available</span>
              </div>
              <a href="#" class="btn btn--ghost btn--sm">View Certificate <i class="fas fa-download"></i></a>
            </div>
          </div>

          <!-- Skills Achievements -->
          <div class="skills-achievements">
            <h3><i class="fas fa-trophy"></i> Skills Achievements</h3>
            <div class="skills-achievements__grid">
              <div class="achievement-badge">
                <div class="achievement-badge__icon"><i class="fas fa-code"></i></div>
                <span class="achievement-badge__label">JavaScript</span>
                <span class="achievement-badge__level">Advanced</span>
              </div>
              <div class="achievement-badge">
                <div class="achievement-badge__icon"><i class="fab fa-react"></i></div>
                <span class="achievement-badge__label">React</span>
                <span class="achievement-badge__level">Intermediate</span>
              </div>
              <div class="achievement-badge">
                <div class="achievement-badge__icon"><i class="fab fa-python"></i></div>
                <span class="achievement-badge__label">Python</span>
                <span class="achievement-badge__level">Intermediate</span>
              </div>
              <div class="achievement-badge achievement-badge--locked">
                <div class="achievement-badge__icon"><i class="fas fa-cloud"></i></div>
                <span class="achievement-badge__label">AWS</span>
                <span class="achievement-badge__level">In Progress</span>
              </div>
              <div class="achievement-badge achievement-badge--locked">
                <div class="achievement-badge__icon"><i class="fab fa-docker"></i></div>
                <span class="achievement-badge__label">Docker</span>
                <span class="achievement-badge__level">Not Started</span>
              </div>
            </div>
          </div>

          <!-- Mentoring Sessions -->
          <div class="mentoring-sessions">
            <h3><i class="fas fa-users"></i> Mentoring Sessions</h3>
            <div class="mentoring-sessions__list">
              <div class="mentoring-session">
                <div class="mentoring-session__date">
                  <span class="mentoring-session__day">15</span>
                  <span class="mentoring-session__month">Jul</span>
                </div>
                <div class="mentoring-session__info">
                  <strong>Career Development Planning</strong>
                  <span>With: Thabo Moloi - 14:00 - 15:00</span>
                </div>
                <a href="#" class="btn btn--primary btn--sm">Join</a>
              </div>
              <div class="mentoring-session">
                <div class="mentoring-session__date">
                  <span class="mentoring-session__day">22</span>
                  <span class="mentoring-session__month">Jul</span>
                </div>
                <div class="mentoring-session__info">
                  <strong>Technical Interview Preparation</strong>
                  <span>With: Sarah Mokoena - 11:00 - 12:00</span>
                </div>
                <a href="#" class="btn btn--outline btn--sm">Reschedule</a>
              </div>
            </div>
          </div>
        </section>

        <!-- =============================================
             S9: INTERVIEW MANAGEMENT
             ============================================= -->
        <section class="dash-section" id="dashboard-interviews">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Interview <span class="text-gradient">Management</span></h2>
          </div>
          <div class="interview-grid" id="interviewGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             S10: NOTIFICATION CENTRE
             ============================================= -->
        <section class="dash-section" id="dashboard-notifications">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Notification <span class="text-gradient">Centre</span></h2>
          </div>
          <div class="notif-centre">
            <div class="notif-centre__toolbar">
              <div class="notif-centre__filters">
                <button class="notif-centre__filter active" data-filter="all">All</button>
                <button class="notif-centre__filter" data-filter="opportunities">Opportunities</button>
                <button class="notif-centre__filter" data-filter="programmes">Programmes</button>
                <button class="notif-centre__filter" data-filter="applications">Applications</button>
                <button class="notif-centre__filter" data-filter="interviews">Interviews</button>
                <button class="notif-centre__filter" data-filter="learning">Learning</button>
              </div>
              <button class="btn btn--ghost btn--sm" id="markAllRead"><i class="fas fa-check-double"></i> Mark All Read</button>
            </div>
            <div class="notif-centre__list" id="notifList">
              <!-- Populated by JS -->
            </div>
          </div>
        </section>

        <!-- =============================================
             S11: PROFILE COMPLETENESS
             ============================================= -->
        <section class="dash-section" id="dashboard-completeness">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Profile <span class="text-gradient">Completeness</span></h2>
          </div>
          <div class="completeness-detail">
            <div class="completeness-detail__card">
              <div class="completeness-detail__header">
<div class="completeness-detail__ring">
                  <svg viewBox="0 0 36 36" class="completeness-ring__svg">
                    <path class="completeness-ring__bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="completeness-ring__fill" stroke-dasharray="<?= (int)$completion ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  </svg>
                  <span class="completeness-ring__text"><?= (int)$completion ?>%</span>
                </div>
                <div class="completeness-detail__info">
                  <h3>Profile Completion</h3>
                  <p>Complete your profile to increase your visibility to employers and recruiters. A complete profile is 4x more likely to be viewed.</p>
                </div>
              </div>
<div class="completeness-detail__items">
                <?php foreach ($breakdown as $key => $item): ?>
                <div class="completeness-item <?= $item['done'] ? 'completeness-item--done' : 'completeness-item--pending' ?>" data-key="<?= e($key) ?>">
                  <div class="completeness-item__icon"><i class="fas <?= $item['done'] ? 'fa-check' : 'fa-times' ?>"></i></div>
                  <div class="completeness-item__info">
                    <span class="completeness-item__label"><?= e($item['label']) ?></span>
                    <span class="completeness-item__status"><?= e($item['detail']) ?></span>
                  </div>
                  <a href="<?= url('candidate/profile.php#profile-' . e($item['tab'])) ?>" class="btn <?= $item['done'] ? 'btn--ghost btn--sm' : 'btn--primary btn--sm' ?>"><?= $item['done'] ? 'View' : 'Complete' ?></a>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </section>

        <!-- =============================================
             S12: QUICK ACTIONS
             ============================================= -->
        <section class="dash-section" id="dashboard-quick-actions">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Quick <span class="text-gradient">Actions</span></h2>
          </div>
          <div class="quick-actions-grid">
            <a href="#" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--primary"><i class="fas fa-briefcase"></i></div>
              <span>Apply for Opportunities</span>
            </a>
<a href="<?= url('candidate/profile.php#profile-skills') ?>" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--cyan"><i class="fas fa-user-edit"></i></div>
              <span>Complete Profile</span>
            </a>
            <a href="<?= url('candidate/profile.php#profile-documents') ?>" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--green"><i class="fas fa-upload"></i></div>
              <span>Upload CV</span>
            </a>
            <a href="#dashboard-talent" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--amber"><i class="fas fa-users"></i></div>
              <span>Join Talent Pools</span>
            </a>
            <a href="<?= url('candidate/profile.php#profile-professional') ?>" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--purple"><i class="fas fa-clock"></i></div>
              <span>Update Availability</span>
            </a>
            <a href="#" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--red"><i class="fas fa-file-alt"></i></div>
              <span>View Applications</span>
            </a>
            <a href="#" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--indigo"><i class="fas fa-download"></i></div>
              <span>Download Documents</span>
            </a>
            <a href="#" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--cyan"><i class="fas fa-bell"></i></div>
              <span>View Notifications</span>
            </a>
            <a href="#" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--green"><i class="fas fa-code"></i></div>
              <span>Update Skills</span>
            </a>
            <a href="#" class="quick-action-card">
              <div class="quick-action-card__icon quick-action-card__icon--primary"><i class="fas fa-chart-line"></i></div>
              <span>View Programme Progress</span>
            </a>
          </div>
        </section>

        <!-- =============================================
             S13: DASHBOARD ANALYTICS
             ============================================= -->
        <section class="dash-section" id="dashboard-analytics">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <h2 class="section__title" style="font-size:1.5rem;">Dashboard <span class="text-gradient">Analytics</span></h2>
          </div>
          <div class="analytics-grid">
            <div class="analytics-card analytics-card--chart">
              <h3>Applications Submitted</h3>
              <div class="analytics-chart-container">
                <canvas id="applicationsChart"></canvas>
              </div>
            </div>
            <div class="analytics-card analytics-card--chart">
              <h3>Skills Growth</h3>
              <div class="analytics-chart-container">
                <canvas id="skillsChart"></canvas>
              </div>
            </div>
            <div class="analytics-card analytics-card--chart">
              <h3>Programme Progress</h3>
              <div class="analytics-chart-container">
                <canvas id="programmeChart"></canvas>
              </div>
            </div>
            <div class="analytics-card analytics-card--chart">
              <h3>Career Growth Metrics</h3>
              <div class="analytics-chart-container">
                <canvas id="careerChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Stats Summary -->
          <div class="analytics-stats">
            <div class="analytics-stat">
              <span class="analytics-stat__number">4</span>
              <span class="analytics-stat__label">Applications Submitted</span>
            </div>
            <div class="analytics-stat">
              <span class="analytics-stat__number">12</span>
              <span class="analytics-stat__label">Opportunities Matched</span>
            </div>
            <div class="analytics-stat">
              <span class="analytics-stat__number">8</span>
              <span class="analytics-stat__label">Skills Verified</span>
            </div>
            <div class="analytics-stat">
              <span class="analytics-stat__number">60%</span>
              <span class="analytics-stat__label">Programme Progress</span>
            </div>
            <div class="analytics-stat">
              <span class="analytics-stat__number">65%</span>
              <span class="analytics-stat__label">Placement Progress</span>
            </div>
            <div class="analytics-stat">
              <span class="analytics-stat__number">48%</span>
              <span class="analytics-stat__label">Learning Completion</span>
            </div>
<div class="analytics-stat">
              <span class="analytics-stat__number"><?= (int)$completion ?>%</span>
              <span class="analytics-stat__label">Profile Completion</span>
            </div>
            <div class="analytics-stat">
              <span class="analytics-stat__number">8</span>
              <span class="analytics-stat__label">Career Growth Points</span>
            </div>
          </div>
        </section>

      </div><!-- // dash-content -->

      <!-- ===== DASHBOARD FOOTER ===== -->
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

  <!-- =============================================
       SIGN OUT CONFIRMATION MODAL
       ============================================= -->
  <div class="modal-overlay" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle" aria-hidden="true">
    <div class="modal">
      <div class="modal__icon modal__icon--info">
        <i class="fas fa-sign-out-alt"></i>
      </div>
      <h3 id="logoutModalTitle">Sign Out?</h3>
      <p>Are you sure you want to sign out of your account? You will need to sign in again to access your dashboard.</p>
      <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;">
        <button type="button" class="btn btn--ghost" id="logoutCancel"><i class="fas fa-times"></i> Cancel</button>
        <a href="<?= url('auth/logout.php') ?>" class="btn btn--primary" id="logoutConfirm"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/dashboard.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
