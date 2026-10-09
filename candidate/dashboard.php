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

// Get candidate profile data for personalized recommendations
$candidateSkills = array_column(Skill::forUser($userId), 'skill_name');
$candidateProvince = $user['province'] ?? ($profile['province'] ?? null);
$candidateCity = $profile['city'] ?? null;
$candidateInterests = $profile['career_interests'] ?? null;

// Build personalized filters based on candidate profile
$personalizedFilters = [];
if (!empty($candidateProvince)) {
    $personalizedFilters['province'] = $candidateProvince;
}

// Fetch personalized opportunities (limit to 6 for dashboard)
$recentOpportunities = CandidateOpportunitiesController::searchOpportunities($personalizedFilters, 1, 6)['opportunities'];

// Get saved opportunity IDs for quick lookup
$savedOpportunityIds = [];
foreach ($savedOpportunities as $saved) {
    $savedOpportunityIds[$saved['opportunity_id']] = true;
}

// Applications integration (Stage 2)
$appStats = Application::countByStatus($userId);
$draftApplications = (int) ($appStats['draft'] ?? 0);
$activeApplications = (int) ($appStats['total'] ?? 0) - (int) ($appStats['rejected'] ?? 0);
$interviewInvitations = (int) ($appStats['under_review'] ?? 0);

// Offers integration (Stage 12) — live data from Admin Selection & Offers.
$offerSummary = ['stats' => ['total' => 0, 'pending' => 0, 'accepted' => 0, 'declined' => 0, 'expired' => 0, 'withdrawn' => 0], 'recent' => null, 'recentList' => [], 'deadlines' => [], 'notifications' => [], 'unread' => 0, 'expired' => 0];
$offerLoadError = null;
try {
    $offerSummary = CandidateOffersController::dashboard($userId);
} catch (Throwable $e) {
    error_log('[Candidate Dashboard] Offers unavailable: ' . $e->getMessage());
    $offerLoadError = 'Offers are temporarily unavailable. Please try again later.';
}
$offerStats = $offerSummary['stats'] ?? [];
$offerRecentList = $offerSummary['recentList'] ?? [];
$offerDeadlines = $offerSummary['deadlines'] ?? [];

// Placements integration — live data from the Admin Placement Management
// module (placements table) scoped to the logged-in candidate.
// Dashboard-safe: CandidatePlacement degrades to null when the Stage 12
// tables have not been migrated yet.
$placementStatus = 'Not Yet Placed';
$placement = null;
$placementStage = ['stage'=>1,'key'=>'application','label'=>'Application','offer_status'=>null,'selected'=>false,'has_application'=>false];
$placementProgress = 25;
$placementStatusMessage = ['tone'=>'info','message'=>'No placement has been assigned yet. Once the programme team places you, your organisation, role and dates will appear here.'];
$placementLoadError = null;
$placementOrg = null;
$placementRole = null;

try {
    $placement = CandidatePlacement::currentForCandidate($userId);
    $placementStage = CandidatePlacement::stageForCandidate($userId, $placement);
    $placementStatus = $placement !== null
        ? CandidatePlacement::statusLabel($placement['status'] ?? null)
        : 'Not Yet Placed';
    $placementProgress = CandidatePlacement::progressForStage(
        $placementStage['key'] ?? 'application',
        $placement !== null ? (string)($placement['status'] ?? '') : null
    );
    $placementStatusMessage = CandidatePlacement::statusMessage($placement);
    if ($placement !== null) {
        $placementOrg = CandidatePlacement::organisationFor($placement);
        $placementRole = CandidatePlacement::roleFor($placement);
    }
} catch (Throwable $e) {
    $placementLoadError = 'Placement information is temporarily unavailable.';
    error_log('[Candidate Dashboard] Placement load failed for user ' . $userId . ': ' . $e->getMessage());
}

// Interviews integration — dynamic data sourced from the interviews table
// via the candidate's applications (Candidate → Application → Interview).
// Dashboard-safe loading: an interview-module database problem must not prevent
// an authenticated candidate from opening the rest of the dashboard.
$candidateInterviews = [];
$interviewStats = array_fill_keys(Interview::STATUSES, 0);
$interviewStats['total'] = 0;
$interviewStats['upcoming'] = 0;
$upcomingInterviews = [];
$nextInterview = null;
$interviewLoadError = null;

try {
    $candidateInterviews = Interview::forCandidate($userId);
    $interviewStats = Interview::candidateStatusCounts($userId);
    $upcomingInterviews = Interview::candidateUpcoming($userId, 5);
    $nextInterview = $upcomingInterviews[0] ?? null;
} catch (Throwable $e) {
    $interviewLoadError = 'Interview information is temporarily unavailable.';
    error_log('[Candidate Dashboard] Interview load failed for user ' . $userId . ': ' . $e->getMessage());
}

// Recent interviews preview for the dashboard (most recent 3),
// excluding the featured "next upcoming" interview so it isn't duplicated.
$recentInterviews = [];
if (!empty($candidateInterviews)) {
    foreach ($candidateInterviews as $iv) {
        if ($nextInterview !== null && (int) $iv['id'] === (int) $nextInterview['id']) {
            continue;
        }
        $recentInterviews[] = $iv;
        if (count($recentInterviews) >= 3) {
            break;
        }
    }
}

// Count available opportunities (published and open for applications)
$today = date('Y-m-d');
$availableOpportunities = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM opportunities
     WHERE status = 'published'
     AND (application_open_date IS NULL OR application_open_date <= ?)
     AND (application_close_date IS NULL OR application_close_date >= ?)",
    'ss',
    [$today, $today]
);
$availableOpportunitiesCount = (int) ($availableOpportunities['cnt'] ?? 0);

// Fetch real applications for the Application Tracker (limit to 5 most recent for dashboard)
$allApplications = Application::forCandidate($userId);
$trackerApplications = array_slice($allApplications, 0, 5);
$totalApplicationCount = count($allApplications);

// Define the application progress stages for the timeline
$progressStages = [
    'submitted' => 1,
    'eligibility_review' => 2,
    'screened' => 3,
    'assessment' => 4,
    'interview' => 5,
    'waitlisted' => 6,
    'selected' => 7,
];

// Define all timeline steps for display
$timelineSteps = [
    ['key' => 'submitted', 'label' => 'Submitted'],
    ['key' => 'eligibility_review', 'label' => 'Review'],
    ['key' => 'screened', 'label' => 'Screened'],
    ['key' => 'assessment', 'label' => 'Assessment'],
    ['key' => 'interview', 'label' => 'Interview'],
    ['key' => 'selected', 'label' => 'Decision'],
];

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
  <link rel="stylesheet" href="<?= url('css/opportunities.css') ?>">
  <link rel="stylesheet" href="<?= url('css/candidate_offers.css') ?>">
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
          <li><a href="#dashboard-offers" class="sidebar__link" data-section="offers"><i class="fas fa-envelope-open-text"></i> Offers</a></li>
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
                <span class="overview-card__number" data-count="<?= (int)$activeApplications ?>"><?= (int)$activeApplications ?></span>
                <span class="overview-card__label">Active Applications</span>
              </div>
              <a href="<?= url('candidate/applications.php') ?>" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
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
                <span class="overview-card__number" data-count="<?= $availableOpportunitiesCount ?>"><?= $availableOpportunitiesCount ?></span>
                <span class="overview-card__label">Available Opportunities</span>
              </div>
              <a href="<?= url('candidate/opportunities.php') ?>" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="overview-card">
              <div class="overview-card__icon overview-card__icon--green"><i class="fas fa-calendar-check"></i></div>
              <div class="overview-card__info">
                <span class="overview-card__number" data-count="<?= $interviewInvitations ?>"><?= $interviewInvitations ?></span>
                <span class="overview-card__label">Interview Invitations</span>
              </div>
              <a href="<?= url('candidate/applications.php') ?>" class="overview-card__link">View <i class="fas fa-arrow-right"></i></a>
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
            <?php if (empty($recentOpportunities)): ?>
              <div class="opp-empty-state" style="grid-column:1/-1;">
                <div class="opp-empty-state__icon"><i class="fas fa-briefcase"></i></div>
                <h3 class="opp-empty-state__title">No Opportunities Available</h3>
                <p class="opp-empty-state__text">
                  <?php if (!empty($candidateProvince)): ?>
                    No opportunities found for your location (<?= e($candidateProvince) ?>).
                    <a href="<?= url('candidate/opportunities.php') ?>">View all opportunities</a>.
                  <?php else: ?>
                    Check back later for new opportunities.
                    <a href="<?= url('candidate/opportunities.php') ?>">Browse all opportunities</a>.
                  <?php endif; ?>
                </p>
                <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--primary">View All Opportunities</a>
              </div>
            <?php else: ?>
              <?php foreach ($recentOpportunities as $opp): ?>
                <?php
                $isSaved = isset($savedOpportunityIds[$opp['id']]);
                $today = date('Y-m-d');
                $isClosed = !empty($opp['application_close_date']) && $opp['application_close_date'] < $today;
                $daysUntilClose = null;
                if (!empty($opp['application_close_date']) && !$isClosed) {
                    $daysUntilClose = (int) ((strtotime($opp['application_close_date']) - time()) / 86400);
                }
                $typeLabel = CandidateOpportunitiesController::OPPORTUNITY_TYPES_DISPLAY[$opp['type']] ?? ucfirst($opp['type'] ?? 'Opportunity');
                $arrangementLabel = CandidateOpportunitiesController::WORK_ARRANGEMENTS_DISPLAY[$opp['work_arrangement']] ?? ($opp['work_arrangement'] ?? '');
                $locationParts = array_filter([$opp['city'] ?? '', $opp['province'] ?? '']);
                $locationDisplay = !empty($locationParts) ? implode(', ', $locationParts) : 'Location TBD';
                ?>
                <article class="opp-card" data-type="<?= e($opp['type'] ?? '') ?>" data-province="<?= e(strtolower(str_replace(' ', '-', $opp['province'] ?? ''))) ?>" data-arrangement="<?= e($opp['work_arrangement'] ?? '') ?>">
                  <div class="opp-card__header">
                    <div class="opp-card__meta">
                      <span class="opp-badge opp-badge--type"><?= e($typeLabel) ?></span>
                      <?php if ($isClosed): ?>
                        <span class="opp-badge opp-badge--closed">Closed</span>
                      <?php elseif ($daysUntilClose !== null && $daysUntilClose <= 7): ?>
                        <span class="opp-badge opp-badge--urgent">Closing Soon</span>
                      <?php endif; ?>
                    </div>
                    <button class="opp-card__save-btn <?= $isSaved ? 'is-saved' : '' ?>" data-opp-id="<?= (int) $opp['id'] ?>" data-saved="<?= $isSaved ? '1' : '0' ?>" aria-label="<?= $isSaved ? 'Remove from saved' : 'Save opportunity' ?>">
                      <i class="<?= $isSaved ? 'fas' : 'far' ?> fa-bookmark"></i>
                    </button>
                  </div>
                  <div class="opp-card__content">
                    <h3 class="opp-card__title">
                      <a href="<?= url('candidate/opportunity_detail.php?id=' . (int)$opp['id']) ?>"><?= e($opp['title']) ?></a>
                    </h3>
                    <?php if (!empty($opp['programme_name'])): ?>
                      <div class="opp-card__programme">
                        <i class="fas fa-graduation-cap"></i>
                        <span><?= e($opp['programme_name']) ?><?= !empty($opp['cohort_name']) ? ' • ' . e($opp['cohort_name']) : '' ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($opp['organisation'])): ?>
                      <div class="opp-card__org">
                        <i class="fas fa-building"></i>
                        <span><?= e($opp['organisation']) ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($opp['short_description'])): ?>
                      <p class="opp-card__description"><?= e(substr($opp['short_description'], 0, 120) . (strlen($opp['short_description']) > 120 ? '...' : '')) ?></p>
                    <?php endif; ?>
                    <div class="opp-card__details">
                      <div class="opp-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= e($locationDisplay) ?></span>
                      </div>
                      <?php if (!empty($arrangementLabel)): ?>
                        <div class="opp-detail">
                          <i class="fas fa-briefcase"></i>
                          <span><?= e($arrangementLabel) ?></span>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($opp['available_positions']) && $opp['available_positions'] > 0): ?>
                        <div class="opp-detail">
                          <i class="fas fa-users"></i>
                          <span><?= (int)$opp['available_positions'] ?> position<?= (int)$opp['available_positions'] !== 1 ? 's' : '' ?></span>
                        </div>
                      <?php endif; ?>
                    </div>
                    <?php if (!$isClosed && !empty($opp['application_close_date'])): ?>
                      <div class="opp-card__deadline">
                        <?php if ($daysUntilClose <= 3): ?>
                          <span class="opp-deadline-urgent"><i class="fas fa-exclamation-circle"></i> Closes in <?= (int)$daysUntilClose ?> day<?= (int)$daysUntilClose !== 1 ? 's' : '' ?></span>
                        <?php elseif ($daysUntilClose <= 7): ?>
                          <span class="opp-deadline-warning"><i class="fas fa-clock"></i> Closes in <?= (int)$daysUntilClose ?> days</span>
                        <?php else: ?>
                          <span class="opp-deadline-info"><i class="fas fa-calendar-alt"></i> Closes <?= format_date($opp['application_close_date'], 'd M Y') ?></span>
                        <?php endif; ?>
                      </div>
                    <?php elseif ($isClosed): ?>
                      <div class="opp-card__closed">
                        <span class="opp-applications-closed"><i class="fas fa-lock"></i> Applications Closed</span>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="opp-card__footer">
                    <a href="<?= url('candidate/opportunity_detail.php?id=' . (int)$opp['id']) ?>" class="btn btn--primary btn--sm btn--full">View Details</a>
                  </div>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="opp-section-footer" style="text-align:center;margin-top:2rem;">
            <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--outline"><i class="fas fa-th-list"></i> View All Opportunities</a>
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
            <?php if (empty($trackerApplications)): ?>
              <div class="empty-state" style="padding:3rem 2rem;text-align:center;background:var(--bg-white);border:1px solid var(--border);border-radius:var(--radius-md);">
                <div class="empty-state__icon" style="font-size:3rem;color:var(--text-lighter);margin-bottom:1rem;"><i class="fas fa-file-alt"></i></div>
                <h3 class="empty-state__title" style="font-size:1.25rem;margin-bottom:0.5rem;">No Applications Yet</h3>
                <p class="empty-state__text" style="color:var(--text-light);margin-bottom:1.5rem;">You haven't submitted any applications yet. Start exploring opportunities and apply to programmes that match your skills and interests.</p>
                <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--primary"><i class="fas fa-search"></i> Browse Opportunities</a>
              </div>
            <?php else: ?>
              <?php foreach ($trackerApplications as $app): ?>
                <?php
                $appStatus = $app['status'] ?? 'draft';
                $appStatusLabel = Application::label($appStatus);
                $appBadgeTone = Application::badgeTone($appStatus);
                $appTitle = $app['opportunity_title'] ?? 'Untitled Opportunity';
                $appProgramme = $app['programme_name'] ?? '';
                $appCohort = $app['cohort_name'] ?? '';
                $appRef = $app['application_reference'] ?? '';
                $appDate = !empty($app['submitted_at']) ? $app['submitted_at'] : ($app['created_at'] ?? '');
                $appUpdatedAt = $app['updated_at'] ?? '';
                $appCloseDate = $app['application_close_date'] ?? '';
                $appId = (int) ($app['id'] ?? 0);
                $appOppId = (int) ($app['opportunity_id'] ?? 0);
                $isTerminal = in_array($appStatus, ['selected', 'rejected', 'withdrawn', 'expired'], true);
                $isRejected = in_array($appStatus, ['rejected', 'withdrawn'], true);
                $currentStage = $progressStages[$appStatus] ?? 0;
                $totalStages = count($timelineSteps);
                $progressPercent = $isTerminal ? 100 : min(100, round(($currentStage / $totalStages) * 100));
                ?>
                <article class="app-card" data-status="<?= e($appStatus) ?>">
                  <div class="app-card__header">
                    <div class="app-card__header-left">
                      <h3 class="app-card__title"><?= e($appTitle) ?></h3>
                      <?php if (!empty($appProgramme)): ?>
                        <div class="app-card__programme"><i class="fas fa-graduation-cap"></i> <span><?= e($appProgramme) ?><?= !empty($appCohort) ? ' &bull; ' . e($appCohort) : '' ?></span></div>
                      <?php endif; ?>
                    </div>
                    <span class="badge badge--<?= e($appBadgeTone) ?>"><?= e($appStatusLabel) ?></span>
                  </div>
                  <?php if ($appStatus !== 'draft'): ?>
                  <div class="app-card__timeline">
                    <?php foreach ($timelineSteps as $stepIndex => $step):
                      $stepKey = $step['key'];
                      $stepOrder = $progressStages[$stepKey] ?? 0;
                      $isDone = $currentStage > $stepOrder;
                      $isCurrent = $currentStage === $stepOrder;
                      $stepClass = 'timeline-step--waiting';
                      $stepIcon = '';
                      if ($isRejected && $isCurrent) {
                        $stepClass = 'timeline-step--rejected';
                        $stepIcon = '<i class="fas fa-times"></i>';
                      } elseif ($isDone) {
                        $stepClass = 'timeline-step--done';
                        $stepIcon = '<i class="fas fa-check"></i>';
                      } elseif ($isCurrent) {
                        $stepClass = 'timeline-step--current';
                        $stepIcon = '<i class="fas fa-circle"></i>';
                      }
                    ?>
                      <div class="timeline-step <?= $stepClass ?>">
                        <div class="timeline-step__dot"><?= $stepIcon ?></div>
                        <?php if ($stepIndex < count($timelineSteps) - 1): ?><div class="timeline-step__line"></div><?php endif; ?>
                        <span class="timeline-step__label"><?= e($step['label']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="app-card__progress">
                    <div class="app-card__progress-bar"><div class="app-card__progress-fill" style="width:<?= (int) $progressPercent ?>%"></div></div>
                    <span class="app-card__progress-label"><?php if ($isRejected): ?><strong style="color:#ef4444;"><?= e($appStatusLabel) ?></strong><?php elseif ($appStatus === 'selected'): ?><strong style="color:var(--success);">Congratulations! Selected</strong><?php else: ?>Current: <strong><?= e($appStatusLabel) ?></strong> &middot; <?= (int) $progressPercent ?>% complete<?php endif; ?></span>
                  </div>
                  <?php else: ?>
                  <div class="app-card__progress">
                    <div class="app-card__progress-bar"><div class="app-card__progress-fill" style="width:10%"></div></div>
                    <span class="app-card__progress-label">Draft - Not yet submitted</span>
                  </div>
                  <?php endif; ?>
                  <div class="app-card__meta">
                    <?php if (!empty($appRef)): ?><span class="app-card__ref"><i class="fas fa-hashtag"></i> <?= e($appRef) ?></span><?php endif; ?>
                    <?php if (!empty($appDate)): ?><span class="app-card__date"><i class="far fa-calendar-alt"></i> <?= e(format_date($appDate, 'd M Y')) ?></span><?php endif; ?>
                    <?php if (!empty($appUpdatedAt) && $appUpdatedAt !== $appDate): ?><span class="app-card__date app-card__date--updated"><i class="far fa-clock"></i> Updated <?= e(format_date($appUpdatedAt, 'd M Y')) ?></span><?php endif; ?>
                    <?php if (!empty($appCloseDate) && !$isTerminal): ?><span class="app-card__deadline"><i class="fas fa-hourglass-half"></i> Closes <?= e(format_date($appCloseDate, 'd M Y')) ?></span><?php endif; ?>
                  </div>
                  <div class="app-card__footer">
                    <?php if ($appStatus === 'draft'): ?>
                      <a href="<?= url('candidate/application_start.php?id=' . $appId) ?>" class="btn btn--primary btn--sm">Continue Application</a>
                    <?php else: ?>
                      <a href="<?= url('candidate/application_detail.php?id=' . $appId) ?>" class="btn btn--primary btn--sm">View Application</a>
                    <?php endif; ?>
                    <?php if (!$isTerminal && $appStatus !== 'draft'): ?><a href="<?= url('candidate/opportunity_detail.php?id=' . $appOppId) ?>" class="btn btn--ghost btn--sm">View Opportunity</a><?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
              <?php if ($totalApplicationCount > count($trackerApplications)): ?>
                <div class="app-tracker__footer" style="text-align:center;margin-top:1.5rem;">
                  <a href="<?= url('candidate/applications.php') ?>" class="btn btn--outline"><i class="fas fa-th-list"></i> View All Applications (<?= (int) $totalApplicationCount ?>)</a>
                </div>
              <?php elseif ($totalApplicationCount > 0): ?>
                <div class="app-tracker__footer" style="text-align:center;margin-top:1.5rem;">
                  <a href="<?= url('candidate/applications.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-right"></i> Manage Applications</a>
                </div>
              <?php endif; ?>
            <?php endif; ?>
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
          <?php
          $plTone = $placement !== null ? CandidatePlacement::statusTone($placement['status'] ?? null) : 'pending';
          $plRef = $placement !== null ? (string)($placement['placement_reference'] ?? '') : '';
          $plProgramme = $placement !== null ? trim((string)($placement['programme_name'] ?? '')) : '';
          $plCohort = $placement !== null ? trim((string)($placement['cohort_name'] ?? '')) : '';
          $plDept = $placement !== null ? trim((string)($placement['department'] ?? '')) : '';
          $plLoc = $placement !== null ? trim((string)($placement['location'] ?? '')) : '';
          $plStart = $placement !== null ? (string)($placement['start_date'] ?? '') : '';
          $plEnd = $placement !== null ? (string)($placement['end_date'] ?? '') : '';
          $plSup = $placement !== null ? trim((string)($placement['supervisor_name'] ?? '')) : '';
          $plType = $placement !== null ? trim((string)($placement['programme_type'] ?? '')) : '';
          $plDaysUntil = CandidatePlacement::daysUntilStart($placement);
          $plDuration = CandidatePlacement::durationLabel($plStart !== '' ? $plStart : null, $plEnd !== '' ? $plEnd : null);
          $plDurationRange = ($plStart !== '' ? CandidatePlacement::formatDay($plStart) : '—') . ' - ' . ($plEnd !== '' ? CandidatePlacement::formatDay($plEnd) : '—') . ' (' . $plDuration . ')';
          $plStageKey = (string)($placementStage['key'] ?? 'application');
          $plStageNum = (int)($placementStage['stage'] ?? 1);
          $plSteps = ['application' => 'Application', 'selection' => 'Selection', 'offer' => 'Offer', 'placement' => 'Placement'];
          $plStepNum = 0;
          ?>
          <div class="placement-card">
            <div class="placement-card__header">
              <div class="placement-card__status placement-card__status--<?= e($plTone) ?>"<?php if ($plTone === 'confirmed'): ?> style="color:var(--primary);"<?php elseif ($plTone === 'completed'): ?> style="color:var(--text-light);"<?php elseif ($plTone === 'cancelled'): ?> style="color:var(--danger);"<?php elseif ($plTone === 'withdrawn'): ?> style="color:var(--text-light);"<?php elseif ($plTone === 'pending'): ?> style="color:var(--accent);"<?php endif; ?>>
                <i class="fas fa-circle"></i> <?= e($placement !== null ? $placementStatus : 'No Placement Yet') ?>
              </div>
              <div class="placement-card__progress">
                <span class="placement-card__progress-label">Progress: <?= (int)$placementProgress ?>%</span>
                <div class="progress-bar">
                  <div class="progress-bar__fill" style="width:<?= (int)$placementProgress ?>%"></div>
                </div>
              </div>
            </div>
            <div class="placement-card__body">
              <?php if ($placementLoadError !== null): ?>
              <div class="placement-card__info" style="grid-template-columns:1fr;">
                <div class="placement-card__item">
                  <span class="placement-card__label">Placement Status</span>
                  <span class="placement-card__value"><?= e($placementLoadError) ?></span>
                </div>
              </div>
              <?php elseif ($placement === null): ?>
              <div class="placement-card__info" style="grid-template-columns:1fr;">
                <div class="placement-card__item">
                  <span class="placement-card__label">Placement Status</span>
                  <span class="placement-card__value">No placement has been assigned yet.</span>
                  <span class="placement-card__sub"><?= e($placementStatusMessage['message']) ?></span>
                </div>
              </div>
              <?php else: ?>
              <div class="placement-card__info">
                <div class="placement-card__item">
                  <span class="placement-card__label">Host Organisation</span>
                  <span class="placement-card__value"><?= e($placementOrg !== null ? $placementOrg : '—') ?></span>
                  <?php if ($plRef !== ''): ?><span class="placement-card__sub"><?= e($plRef) ?></span><?php endif; ?>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Role / Position</span>
                  <span class="placement-card__value"><?= e($placementRole !== null ? $placementRole : '—') ?></span>
                  <?php if ($plDept !== ''): ?><span class="placement-card__sub"><?= e($plDept) ?></span><?php endif; ?>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Programme</span>
                  <span class="placement-card__value"><?= e($plProgramme !== '' ? $plProgramme : '—') ?></span>
                  <?php if ($plType !== ''): ?><span class="placement-card__sub"><?= e(ucwords(str_replace('_', ' ', $plType))) ?></span><?php endif; ?>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Cohort</span>
                  <span class="placement-card__value"><?= e($plCohort !== '' ? $plCohort : '—') ?></span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Supervisor</span>
                  <span class="placement-card__value"><?= e($plSup !== '' ? $plSup : '—') ?></span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Duration</span>
                  <span class="placement-card__value"><?= e($plDurationRange) ?></span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Workplace Location</span>
                  <span class="placement-card__value"><?= e($plLoc !== '' ? $plLoc : '—') ?></span>
                </div>
                <div class="placement-card__item">
                  <span class="placement-card__label">Placement Status</span>
                  <span class="placement-card__value"><?= e($placementStatus) ?></span>
                  <span class="placement-card__sub"><?= e($placementStatusMessage['message']) ?></span>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <div class="placement-card__footer">
              <?php if ($placement !== null): ?>
              <a href="<?= url('candidate/placement_detail.php?id=' . (int)$placement['id']) ?>" class="btn btn--primary btn--sm"><i class="fas fa-eye"></i> View Placement Details</a>
              <?php endif; ?>
              <a href="<?= url('candidate/applications.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-file-alt"></i> View Applications</a>
              <a href="<?= url('candidate/applications.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-envelope-open-text"></i> View Offers</a>
            </div>
          </div>

          <div class="placement-card" aria-label="Recruitment progress">
            <div class="placement-card__body">
              <div class="placement-card__info" style="grid-template-columns:1fr;">
                <div class="placement-card__item">
                  <span class="placement-card__label">Current Stage: <?= e($placementStage['label'] ?? 'Application') ?></span>
                  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;">
                    <?php foreach ($plSteps as $stepKey => $stepLabel): ?>
                      <?php $plStepNum++; ?>
                      <span class="placement-card__sub" style="<?= $plStepNum <= $plStageNum ? 'color:var(--success);font-weight:700;' : '' ?>">
                        <?= $plStepNum <= $plStageNum ? '●' : '○' ?> <?= e($stepLabel) ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                  <span class="placement-card__sub">Application → Selection → Offer → Placement</span>
                </div>
              </div>
            </div>
          </div>

          <?php if ($placement !== null): ?>
          <div class="placement-card" aria-label="Placement details">
            <div class="placement-card__body">
              <div class="placement-card__info">
                <div class="placement-card__item"><span class="placement-card__label">Organisation</span><span class="placement-card__value"><?= e($placementOrg !== null ? $placementOrg : '—') ?></span></div>
                <div class="placement-card__item"><span class="placement-card__label">Role</span><span class="placement-card__value"><?= e($placementRole !== null ? $placementRole : '—') ?></span></div>
                <div class="placement-card__item"><span class="placement-card__label">Programme</span><span class="placement-card__value"><?= e($plProgramme !== '' ? $plProgramme : '—') ?></span></div>
                <div class="placement-card__item"><span class="placement-card__label">Cohort</span><span class="placement-card__value"><?= e($plCohort !== '' ? $plCohort : '—') ?></span></div>
                <div class="placement-card__item"><span class="placement-card__label">Location</span><span class="placement-card__value"><?= e($plLoc !== '' ? $plLoc : '—') ?></span></div>
                <div class="placement-card__item"><span class="placement-card__label">Start Date</span><span class="placement-card__value"><?= e($plStart !== '' ? CandidatePlacement::formatDay($plStart) : '—') ?></span></div>
                <div class="placement-card__item"><span class="placement-card__label">End Date</span><span class="placement-card__value"><?= e($plEnd !== '' ? CandidatePlacement::formatDay($plEnd) : '—') ?></span></div>
                <div class="placement-card__item"><span class="placement-card__label">Status</span><span class="placement-card__value"><?= e($placementStatus) ?></span></div>
              </div>
            </div>
          </div>
          <?php endif; ?>
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
          <div class="section__header interview-section__header">
            <div class="interview-section__heading">
              <h2 class="section__title" style="font-size:1.5rem;">Interview <span class="text-gradient">Management</span></h2>
              <p class="section__text" style="font-size:0.9rem;">Schedule, status, and history of your interviews.</p>
            </div>
            <a href="<?= url('candidate/interviews.php') ?>" class="btn btn--primary btn--sm interview-section__manage">
              <i class="fas fa-calendar-check"></i> Manage Interviews
            </a>
          </div>

          <?php if ($interviewLoadError !== null): ?>
            <div class="alert alert--warning" role="alert" style="margin-bottom:1rem;">
              <i class="fas fa-triangle-exclamation"></i>
              <?= e($interviewLoadError) ?>
            </div>
          <?php endif; ?>

          <!-- Dynamic summary cards (real counts from the database) -->
          <div class="interview-summary">
            <div class="interview-summary__card interview-summary__card--upcoming">
              <div class="interview-summary__icon"><i class="fas fa-hourglass-half"></i></div>
              <div class="interview-summary__body">
                <span class="interview-summary__number"><?= (int) ($interviewStats['upcoming'] ?? 0) ?></span>
                <span class="interview-summary__label">Upcoming Interviews</span>
              </div>
            </div>
            <div class="interview-summary__card interview-summary__card--completed">
              <div class="interview-summary__icon"><i class="fas fa-check-circle"></i></div>
              <div class="interview-summary__body">
                <span class="interview-summary__number"><?= (int) ($interviewStats['completed'] ?? 0) ?></span>
                <span class="interview-summary__label">Completed Interviews</span>
              </div>
            </div>
            <div class="interview-summary__card interview-summary__card--total">
              <div class="interview-summary__icon"><i class="fas fa-calendar-alt"></i></div>
              <div class="interview-summary__body">
                <span class="interview-summary__number"><?= (int) ($interviewStats['total'] ?? 0) ?></span>
                <span class="interview-summary__label">Total Interviews</span>
              </div>
            </div>
          </div>

          <?php if (empty($candidateInterviews)): ?>
            <!-- Empty state: candidate has no interviews -->
            <div class="interview-empty">
              <div class="interview-empty__icon"><i class="fas fa-calendar-times"></i></div>
              <h3 class="interview-empty__title">No interviews scheduled</h3>
              <p class="interview-empty__text">You don't have any interviews scheduled at the moment. When the recruitment team schedules an interview, it will appear here.</p>
              <a href="<?= url('candidate/interviews.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-calendar-check"></i> Manage Interviews</a>
            </div>
                              <?php else: ?>
            <?php if ($nextInterview !== null): ?>
              <?php
                  $niStatusTone = Interview::badgeTone($nextInterview['status']);
                  $niJoinUrl    = Interview::joinUrl($nextInterview);
              ?>
              <!-- Nearest upcoming interview (real data) -->
              <article class="interview-card interview-card--featured">
                <div class="interview-card__header">
                  <div class="interview-card__heading">
                    <span class="interview-card__overline">Next Upcoming Interview</span>
                    <h3 class="interview-card__title"><?= e(Interview::typeLabel($nextInterview['interview_type'])) ?> Interview</h3>
                    <span class="interview-card__programme">
                      <i class="fas fa-briefcase"></i>
                      <?= e($nextInterview['opportunity_title'] ?? '') ?>
                      <?= !empty($nextInterview['cohort_name']) ? ' • ' . e($nextInterview['cohort_name']) : '' ?>
                    </span>
                  </div>
                  <span class="tag tag--<?= e($niStatusTone) ?>"><?= e(Interview::label($nextInterview['status'])) ?></span>
                </div>
                <div class="interview-card__countdown">
                  <i class="fas fa-clock"></i>
                  <span class="interview-card__countdown-time"><?= e(Interview::countdownLabel($nextInterview['interview_date'], $nextInterview['start_time'])) ?></span>
                </div>
                <div class="interview-card__details">
                  <div class="interview-card__detail"><i class="fas fa-calendar-alt"></i> <?= e(format_date($nextInterview['interview_date'], 'l, d F Y')) ?></div>
                  <div class="interview-card__detail"><i class="fas fa-clock"></i> <?= e(Interview::timeRange($nextInterview['start_time'], $nextInterview['end_time'])) ?></div>
                  <div class="interview-card__detail"><i class="fas <?= e(Interview::typeIcon($nextInterview['interview_type'])) ?>"></i> Format: <?= e(Interview::typeLabel($nextInterview['interview_type'])) ?></div>
                  <?php if (!empty($nextInterview['interviewer_first_name'])): ?>
                    <div class="interview-card__detail"><i class="fas fa-user-tie"></i> Interviewer: <?= e($nextInterview['interviewer_first_name'] . ' ' . $nextInterview['interviewer_last_name']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($nextInterview['location'])): ?>
                    <div class="interview-card__detail">
                      <i class="fas <?= $niJoinUrl ? 'fa-link' : Interview::typeIcon($nextInterview['interview_type']) ?>"></i>
                      <?= $niJoinUrl ? '<a href="' . e($niJoinUrl) . '" target="_blank" rel="noopener">Open meeting link</a>' : e($nextInterview['location']) ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="interview-card__actions">
                  <?php if ($niJoinUrl): ?>
                    <a href="<?= e($niJoinUrl) ?>" target="_blank" rel="noopener" class="btn btn--primary btn--sm"><i class="fas fa-video"></i> Join Interview</a>
                  <?php endif; ?>
                  <a href="<?= url('candidate/interviews.php#interview-' . (int) $nextInterview['id']) ?>" class="btn btn--outline btn--sm"><i class="fas fa-eye"></i> View Details</a>
                </div>
              </article>
            <?php else: ?>
              <!-- No upcoming but has past/terminal interviews -->
              <div class="interview-empty interview-empty--sm" style="border-style:dashed;">
                <h3 class="interview-empty__title">No upcoming interviews</h3>
                <p class="interview-empty__text">You don't have any upcoming interviews. Below is your recent interview history.</p>
              </div>
            <?php endif; ?>

            <!-- Recent interviews preview (real records) -->
            <?php if (!empty($recentInterviews)): ?>
              <div class="interview-list">
                <div class="interview-list__title"><i class="fas fa-history"></i> Recent Interviews</div>
                <?php foreach ($recentInterviews as $iv): ?>
                  <div class="interview-list__item" id="interview-<?= (int) $iv['id'] ?>">
                    <div class="interview-list__date">
                      <span class="day"><?= e(format_date($iv['interview_date'], 'd')) ?></span>
                      <span><?= e(format_date($iv['interview_date'], 'M')) ?></span>
                    </div>
                    <div class="interview-list__info">
                      <strong><?= e(Interview::typeLabel($iv['interview_type'])) ?> Interview</strong>
                      <span><?= e($iv['opportunity_title'] ?? '') ?><?= !empty($iv['programme_name']) ? ' • ' . e($iv['programme_name']) : '' ?> • <?= e(Interview::label($iv['status'])) ?></span>
                    </div>
                    <span class="tag tag--<?= e(Interview::badgeTone($iv['status'])) ?> tag--sm"><?= e(Interview::label($iv['status'])) ?></span>
                    <a href="<?= url('candidate/interviews.php#interview-' . (int) $iv['id']) ?>" class="interview-list__link" title="View details"><i class="fas fa-chevron-right"></i></a>
                  </div>
                <?php endforeach; ?>
                <a href="<?= url('candidate/interviews.php') ?>" class="interview-list__view-all">View all interviews <i class="fas fa-arrow-right"></i></a>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </section>

        <!-- =============================================
             S9b: OFFERS (Selection & Offers → Candidate)
             Live data only. Positioned directly after
             Interview Management per spec.
             ============================================= -->
        <section class="dash-section" id="dashboard-offers">
          <div class="section__header interview-section__header">
            <div class="interview-section__heading">
              <span class="section__badge">Selection &amp; Offers</span>
              <h2 class="section__title" style="font-size:1.5rem;">My <span class="text-gradient">Offers</span></h2>
              <p class="section__text" style="font-size:0.9rem;">Offers issued to you by the programme team. Open each offer for full details and respond before the deadline.</p>
            </div>
            <a href="<?= url('candidate/offers.php') ?>" class="btn btn--primary btn--sm interview-section__manage"><i class="fas fa-envelope-open-text"></i> Manage Offers<?= ((int)($offerStats['pending'] ?? 0) > 0) ? ' (' . (int)($offerStats['pending']) . ' pending)' : '' ?></a>
          </div>
          <?php if ($offerLoadError !== null): ?>
            <div class="offer-notice offer-notice--muted"><i class="fas fa-triangle-exclamation"></i><div><?= e($offerLoadError) ?></div></div>
          <?php elseif (empty($offerRecentList)): ?>
            <div class="empty-state">
              <div class="empty-state__icon"><i class="fas fa-envelope-open-text"></i></div>
              <h3>No offers available at this time.</h3>
              <p>Once the programme team issues an offer through Selection &amp; Offers, it will appear here.</p>
              <a href="<?= url('candidate/applications.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-file-alt"></i> View Applications</a>
            </div>
          <?php else: ?>
            <div class="interview-grid offer-grid">
              <?php foreach ($offerRecentList as $off): ?>
                <?php $oTone = CandidateOffersController::statusTone($off); ?>
                <?php $oTag = $oTone === 'success' ? 'green' : ($oTone === 'danger' ? 'red' : ($oTone === 'primary' ? 'primary' : ($oTone === 'amber' ? 'amber' : 'muted'))); ?>
                <?php $oLbl = CandidateOffersController::statusLabel($off); ?>
                <?php $oCan = CandidateOffersController::canRespond($off); ?>
                <article class="interview-card offer-card">
                  <div class="interview-card__header">
                    <div>
                      <h3 class="interview-card__title"><?= e($off['title'] ?? $off['position'] ?? 'Offer') ?></h3>
                      <div class="interview-card__programme"><i class="fas fa-briefcase"></i><?= e($off['opportunity_title'] ?? $off['position'] ?? '') ?></div>
                    </div>
                    <span class="tag tag--<?= e($oTag) ?>"><?= e($oLbl) ?></span>
                  </div>
                  <div class="interview-card__details">
                    <div class="interview-card__detail"><i class="fas fa-graduation-cap"></i> <?= e($off['programme_name'] ?? '—') ?></div>
                    <div class="interview-card__detail"><i class="fas fa-building"></i> <?= e($off['organisation'] ?? '—') ?></div>
                    <div class="interview-card__detail"><i class="fas fa-calendar-alt"></i> Offer: <?= !empty($off['issued_at']) ? e(format_date($off['issued_at'], 'd M Y')) : '—' ?></div>
                    <div class="interview-card__detail"><i class="fas fa-hourglass-half"></i> Deadline: <?= !empty($off['expiry_date']) ? e(format_date($off['expiry_date'], 'd M Y')) : '—' ?></div>
                  </div>
                  <div class="interview-card__actions">
                    <a href="<?= url('candidate/offer_detail.php?id=' . (int)$off['id']) ?>" class="btn btn--outline btn--sm"><i class="fas fa-eye"></i> View Offer</a>
                    <?php if ($oCan): ?>
                      <form method="post" action="<?= url('candidate/offer_action.php') ?>" data-offer-confirm="accept" style="flex:1;display:flex;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="offer_id" value="<?= (int)$off['id'] ?>">
                        <input type="hidden" name="response" value="accepted">
                        <input type="hidden" name="return_to" value="dashboard">
                        <button type="submit" class="btn btn--primary btn--sm" style="flex:1;"><i class="fas fa-check"></i> Accept</button>
                      </form>
                    <?php endif; ?>
                  </div>
                  <?php if ($oCan): ?>
                    <div class="interview-card__actions" style="margin-top:.5rem;">
                      <form method="post" action="<?= url('candidate/offer_action.php') ?>" data-offer-confirm="decline" style="flex:1;display:flex;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="offer_id" value="<?= (int)$off['id'] ?>">
                        <input type="hidden" name="response" value="declined">
                        <input type="hidden" name="decline_reason" value="">
                        <input type="hidden" name="return_to" value="dashboard">
                        <button type="submit" class="btn btn--ghost btn--sm" style="flex:1;"><i class="fas fa-times"></i> Decline</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:.75rem;">
              <a href="<?= url('candidate/offers.php') ?>" class="interview-list__view-all">View all <?= (int)($offerStats['total'] ?? 0) ?> offers <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php endif; ?>
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
            <a href="<?= url('candidate/applications.php') ?>" class="quick-action-card">
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
  <script src="<?= url('js/candidate_offers.js') ?>"></script>
  <script>
  (function () {
    function setHidden(modal, hidden) {
      if (!modal) return;
      if (hidden) { modal.setAttribute('hidden', ''); modal.setAttribute('aria-hidden', 'true'); }
      else { modal.removeAttribute('hidden'); modal.setAttribute('aria-hidden', 'false'); }
    }
    document.querySelectorAll('[data-placement-modal-open]').forEach(function (btn) {
      btn.addEventListener('click', function () { setHidden(document.querySelector('[data-placement-modal]'), false); });
    });
    document.querySelectorAll('[data-placement-modal-close]').forEach(function (el) {
      el.addEventListener('click', function () { setHidden(document.querySelector('[data-placement-modal]'), true); });
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') setHidden(document.querySelector('[data-placement-modal]'), true);
    });
  })();
  </script>
  <?= $flashes ?>
</body>
</html>
