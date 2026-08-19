<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Dashboard (root entry)
 * ================================================
 * Role: Administrator
 * Accessible only by users with role 'admin'.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user = current_user();
$flashes = render_flashes();

// ------------------------------------------------------------
// Real dynamic programme statistics (from MySQL, not hard-coded)
// ------------------------------------------------------------
$progCountsByStatus = Programme::countsByStatus();
$totalProgrammes     = array_sum($progCountsByStatus);
$activeProgrammes    = (int) ($progCountsByStatus['active'] ?? 0);
$draftProgrammes     = (int) ($progCountsByStatus['draft'] ?? 0);
$completedProgrammes = (int) ($progCountsByStatus['completed'] ?? 0);
$archivedProgrammes  = (int) ($progCountsByStatus['archived'] ?? 0);

// Active cohorts across all programmes
$activeCohortsRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM cohorts WHERE status = 'active'");
$activeCohorts = (int) ($activeCohortsRow['cnt'] ?? 0);

// Total capacity across all cohorts
$capacityRow = Database::fetchOne("SELECT COALESCE(SUM(max_capacity),0) AS total FROM cohorts");
$totalCapacity = (int) ($capacityRow['total'] ?? 0);

// Total current participants (selected/onboarded/active)
$participantsRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM cohort_participants WHERE status IN ('selected','onboarded','active')"
);
$totalParticipants = (int) ($participantsRow['cnt'] ?? 0);

// Total applications across cohorts
$appsRow = Database::fetchOne("SELECT COALESCE(SUM(applications_count),0) AS total FROM cohorts");
$totalApplications = (int) ($appsRow['total'] ?? 0);

// Total candidates (active users with the candidate role)
$candidatesRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'"
);
$totalCandidates = (int) ($candidatesRow['cnt'] ?? 0);

// New candidates registered within the last 30 days
$newCandidatesRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
       AND u.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
);
$newCandidatesMonth = (int) ($newCandidatesRow['cnt'] ?? 0);

// Profile completeness (average completion % + count of complete profiles >= 80%)
$profileStatsRow = Database::fetchOne(
    "SELECT ROUND(AVG(completion_percent)) AS avg_completion,
            COUNT(*) AS total_profiles,
            COALESCE(SUM(completion_percent >= 80), 0) AS complete_profiles
     FROM candidate_profiles"
);
$avgProfileCompleteness = (int) ($profileStatsRow['avg_completion'] ?? 0);
$totalCandidateProfiles  = (int) ($profileStatsRow['total_profiles'] ?? 0);
$completeProfiles        = (int) ($profileStatsRow['complete_profiles'] ?? 0);

// ------------------------------------------------------------
// Real dynamic opportunity statistics (from MySQL, not hard-coded)
// ------------------------------------------------------------
$oppCountsByStatus = Opportunity::countsByStatus();
$totalOpportunities  = array_sum($oppCountsByStatus);
$publishedOpps       = (int) ($oppCountsByStatus['published'] ?? 0);
$draftOpps           = (int) ($oppCountsByStatus['draft'] ?? 0);
$closingSoonOpps     = (int) ($oppCountsByStatus['closing_soon'] ?? 0);
$closedOpps          = (int) ($oppCountsByStatus['closed'] ?? 0);
$archivedOpps        = (int) ($oppCountsByStatus['archived'] ?? 0);

// Opportunities created within the last 7 days (for the "new this week" metric)
$newOppsWeekRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM opportunities WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$newOppsWeek = (int) ($newOppsWeekRow['cnt'] ?? 0);

// Recent opportunities (most recently created)
$recentOpportunities = Opportunity::all();
usort($recentOpportunities, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$recentOpportunities = array_slice($recentOpportunities, 0, 5);

// Recent programmes (most recently updated)
$recentProgrammes = Programme::all();
usort($recentProgrammes, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
$recentProgrammes = array_slice($recentProgrammes, 0, 5);

// Active cohorts across all programmes (real data for the executive overview)
$activeCohortRows = Cohort::activeAll();
$activeCohortData = [];
foreach ($activeCohortRows as $ac) {
    $max = (int) ($ac['max_capacity'] ?? 0);
    $committed = (int) ($ac['committed'] ?? 0);
    $activeCohortData[] = [
        'id'            => (int) $ac['id'],
        'name'          => $ac['name'],
        'programme_name'=> $ac['programme_name'],
        'delivery_mode' => $ac['delivery_mode'],
        'location'      => $ac['location'],
        'province'      => $ac['province'],
        'start_date'    => $ac['start_date'],
        'end_date'      => $ac['end_date'],
        'max_capacity'  => $max,
        'applications'  => (int) ($ac['applications_count'] ?? 0),
        'committed'     => $committed,
        'available'     => max(0, $max - $committed),
        'pct'           => $max > 0 ? (int) round(($committed / $max) * 100) : 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Administration Dashboard - Investhood IT Programme & Scarce Skills Platform">
  <title>Admin Dashboard | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
</head>
<body class="dashboard-page admin-dashboard">

  <!-- =============================================
       ADMIN DASHBOARD LAYOUT
       ============================================= -->
  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar admin-sidebar" id="adminSidebar">
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
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="#admin-executive" class="sidebar__link active" data-section="executive"><i class="fas fa-th-large"></i> Executive Overview</a></li>
          <li><a href="#admin-analytics" class="sidebar__link" data-section="analytics"><i class="fas fa-chart-pie"></i> Analytics</a></li>
          <li><a href="#admin-alerts" class="sidebar__link" data-section="alerts"><i class="fas fa-exclamation-triangle"></i> Alerts <span class="alert-badge-sidebar">6</span></a></li>
        </ul>

<div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
<li><a href="#admin-programmes" class="sidebar__link" data-section="programmes"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="#admin-opportunities" class="sidebar__link" data-section="opportunities"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="#admin-applications" class="sidebar__link" data-section="applications"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="#admin-placements" class="sidebar__link" data-section="placements"><i class="fas fa-handshake"></i> Placements</a></li>
          <li><a href="#admin-interviews" class="sidebar__link" data-section="interviews"><i class="fas fa-calendar-check"></i> Interviews</a></li>
        </ul>

        <div class="sidebar__section-label">Talent</div>
        <ul class="sidebar__menu">
          <li><a href="#admin-talent-hub" class="sidebar__link" data-section="talent-hub"><i class="fas fa-users"></i> Talent Intelligence Hub</a></li>
          <li><a href="#admin-talent-matching" class="sidebar__link" data-section="talent-matching"><i class="fas fa-handshake"></i> Talent Matching</a></li>
          <li><a href="#admin-talent-pool" class="sidebar__link" data-section="talent-pool"><i class="fas fa-database"></i> Talent Pools</a></li>
        </ul>

        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="#admin-attendance" class="sidebar__link" data-section="attendance"><i class="fas fa-clock"></i> Attendance & Timesheets</a></li>
          <li><a href="#admin-communication" class="sidebar__link" data-section="communication"><i class="fas fa-bullhorn"></i> Communication Centre</a></li>
          <li><a href="#admin-reporting" class="sidebar__link" data-section="reporting"><i class="fas fa-file-alt"></i> Reporting & Analytics</a></li>
          <li><a href="#admin-compliance" class="sidebar__link" data-section="compliance"><i class="fas fa-shield-alt"></i> Consent & Compliance</a></li>
          <li><a href="#admin-audit" class="sidebar__link" data-section="audit"><i class="fas fa-history"></i> Audit Log</a></li>
          <li><a href="#admin-users" class="sidebar__link" data-section="users"><i class="fas fa-user-shield"></i> User Management</a></li>
        </ul>

        <div class="sidebar__section-label">Quick Access</div>
        <ul class="sidebar__menu">
          <li><a href="#admin-quick-actions" class="sidebar__link" data-section="quick-actions"><i class="fas fa-bolt"></i> Quick Actions</a></li>
        </ul>
      </nav>

      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span>
            <span class="sidebar__user-role"><?= e($user['role_name'] ?? 'Platform Administrator') ?></span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout" id="sidebarLogoutBtn" onclick="event.preventDefault(); var m=document.getElementById('logoutModal'); if(m){m.classList.add('active'); m.setAttribute('aria-hidden','false');}">
          <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
      </div>
    </aside>

    <!-- ===== SIDEBAR OVERLAY ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard__main">

      <!-- ===== DASHBOARD HEADER ===== -->
      <header class="dash-header admin-dash-header" id="adminDashHeader">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
          </button>
          <div class="dash-header__search" id="adminDashSearch">
            <i class="fas fa-search"></i>
            <input type="text" class="dash-header__search-input" id="adminGlobalSearch" placeholder="Search candidates, programmes, reports..." aria-label="Search admin dashboard">
          </div>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode">
            <i class="fas fa-moon"></i>
          </button>
          <button class="dash-header__icon-btn dash-header__notif-btn" id="adminNotifBtn" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <span class="dash-header__notif-badge">8</span>
          </button>
          <button class="dash-header__icon-btn" id="adminAlertsBtn" aria-label="Alerts">
            <i class="fas fa-exclamation-circle"></i>
            <span class="dash-header__notif-badge" style="background:var(--accent);">6</span>
          </button>
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <!-- ===== DASHBOARD CONTENT ===== -->
      <div class="dash-content" id="adminDashContent">

        <!-- =============================================
             SECTION 1: EXECUTIVE OVERVIEW
             ============================================= -->
        <section class="dash-section admin-section" id="admin-executive" data-section="executive">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Command Centre</span>
            <h2 class="section__title" style="font-size:1.5rem;">Executive <span class="text-gradient">Overview</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Real-time platform performance metrics and intelligence.</p>
          </div>

          <!-- Executive Stats Grid -->
          <div class="admin-executive-grid" id="adminExecutiveGrid">
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--primary"><i class="fas fa-users"></i></div>
                <span class="admin-exec-card__change up">+12.5%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$totalCandidates ?>">0</span>
              <span class="admin-exec-card__label">Total Candidates</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> <?= (int)$newCandidatesMonth ?> new this month</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--cyan"><i class="fas fa-graduation-cap"></i></div>
                <span class="admin-exec-card__change up">+8.3%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$activeProgrammes ?>">0</span>
              <span class="admin-exec-card__label">Active Programmes</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> <?= (int)$totalProgrammes ?> total programmes</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--amber"><i class="fas fa-layer-group"></i></div>
                <span class="admin-exec-card__change up">+15.0%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$activeCohorts ?>">0</span>
              <span class="admin-exec-card__label">Active Cohorts</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-users"></i> <?= (int)$totalParticipants ?> participants</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--green"><i class="fas fa-briefcase"></i></div>
                <span class="admin-exec-card__change up">+22.1%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$totalOpportunities ?>">0</span>
              <span class="admin-exec-card__label">Available Opportunities</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> <?= (int)$newOppsWeek ?> new this week</span>
                <a href="<?= url('admin/opportunities.php') ?>" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--purple"><i class="fas fa-file-alt"></i></div>
                <span class="admin-exec-card__change up">+18.7%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$totalApplications ?>">0</span>
              <span class="admin-exec-card__label">Total Applications</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-file-alt"></i> across all cohorts</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--indigo"><i class="fas fa-handshake"></i></div>
                <span class="admin-exec-card__change up">+6.8%</span>
              </div>
              <span class="admin-exec-card__number" data-count="248">0</span>
              <span class="admin-exec-card__label">Active Placements</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 18 new this month</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--primary"><i class="fas fa-database"></i></div>
                <span class="admin-exec-card__change up">+11.2%</span>
              </div>
              <span class="admin-exec-card__number" data-count="1280">0</span>
              <span class="admin-exec-card__label">Talent Pool Members</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 89 new this month</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--green"><i class="fas fa-check-circle"></i></div>
                <span class="admin-exec-card__change up">+9.4%</span>
              </div>
              <span class="admin-exec-card__number" data-count="1056">0</span>
              <span class="admin-exec-card__label">Successful Placements</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 42 this quarter</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--cyan"><i class="fas fa-chart-line"></i></div>
                <span class="admin-exec-card__change up">+4.2%</span>
              </div>
              <span class="admin-exec-card__number">87<span class="admin-exec-card__suffix">%</span></span>
              <span class="admin-exec-card__label">Completion Rate</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> +2.1% vs last month</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--amber"><i class="fas fa-calendar-check"></i></div>
                <span class="admin-exec-card__change up">+7.6%</span>
              </div>
              <span class="admin-exec-card__number" data-count="189">0</span>
              <span class="admin-exec-card__label">Interviews This Month</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 68% attendance</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--purple"><i class="fas fa-clipboard-check"></i></div>
                <span class="admin-exec-card__change up">+5.8%</span>
              </div>
              <span class="admin-exec-card__number">92<span class="admin-exec-card__suffix">%</span></span>
              <span class="admin-exec-card__label">Skills Verification Rate</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-check"></i> 2,450 skills verified</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--indigo"><i class="fas fa-user-check"></i></div>
                <span class="admin-exec-card__change up">+3.4%</span>
              </div>
<span class="admin-exec-card__number"><?= (int)$avgProfileCompleteness ?><span class="admin-exec-card__suffix">%</span></span>
              <span class="admin-exec-card__label">Profile Completeness</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-user"></i> <?= (int)$completeProfiles ?> complete profiles</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
          </div>

<!-- Mini Charts Row -->
          <div class="admin-mini-charts">
            <div class="admin-mini-chart-card">
              <div class="admin-mini-chart-card__header">
                <h4>Candidate Growth</h4>
                <span class="admin-mini-chart-card__period">Last 6 months</span>
              </div>
              <div class="admin-mini-chart-container">
                <canvas id="execCandidateChart"></canvas>
              </div>
            </div>
            <div class="admin-mini-chart-card">
              <div class="admin-mini-chart-card__header">
                <h4>Applications Trend</h4>
                <span class="admin-mini-chart-card__period">Weekly comparison</span>
              </div>
              <div class="admin-mini-chart-container">
                <canvas id="execApplicationChart"></canvas>
              </div>
            </div>
            <div class="admin-mini-chart-card">
              <div class="admin-mini-chart-card__header">
                <h4>Placement Success</h4>
                <span class="admin-mini-chart-card__period">Monthly rate</span>
              </div>
              <div class="admin-mini-chart-container">
                <canvas id="execPlacementChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Active Cohorts Panel (real data) -->
          <div class="admin-exec-cohorts">
            <div class="admin-exec-cohorts__head">
              <div>
                <h3 class="admin-exec-cohorts__title"><i class="fas fa-layer-group"></i> Active Cohorts</h3>
                <p class="admin-exec-cohorts__sub">Live cohorts currently accepting participants across all programmes</p>
              </div>
              <a href="<?= url('admin/programmes.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-right"></i> View All</a>
            </div>

            <?php if (empty($activeCohortData)): ?>
              <div class="pm-empty pm-empty--sm">
                <div class="pm-empty__icon"><i class="fas fa-layer-group"></i></div>
                <h3>No active cohorts right now.</h3>
                <p>Activate a cohort to see its live performance here.</p>
                <a href="<?= url('admin/programmes.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Manage Programmes</a>
              </div>
            <?php else: ?>
              <div class="admin-exec-cohorts__grid">
                <?php foreach ($activeCohortData as $ac): ?>
                  <div class="pm-cohort-card admin-exec-cohort-item">
                    <div class="pm-cohort-card__top">
                      <div>
                        <h3 class="pm-cohort-card__name"><?= e($ac['name']) ?></h3>
                        <span class="pm-cohort-card__meta"><?= e($ac['programme_name']) ?></span>
                      </div>
                      <?= status_badge('active', 'Active') ?>
                    </div>
                    <div class="pm-cohort-card__meta" style="margin-top:0.35rem;">
                      <i class="fas fa-map-marker-alt"></i> <?= e($ac['location'] ?: ucwords(str_replace('-', ' ', $ac['province'] ?: 'TBC'))) ?>
                      · <i class="fas fa-broadcast-tower"></i> <?= e(delivery_mode_label($ac['delivery_mode'])) ?>
                    </div>
                    <div class="pm-cohort-card__dates">
                      <span><i class="fas fa-calendar-alt"></i> <?= e(!empty($ac['start_date']) ? format_date($ac['start_date'], 'd M Y') : 'TBC') ?> — <?= e(!empty($ac['end_date']) ? format_date($ac['end_date'], 'd M Y') : 'TBC') ?></span>
                    </div>
                    <div class="pm-cohort-card__capacity">
                      <div class="pm-cohort-card__capacity-head">
                        <span><?= (int) $ac['committed'] ?> / <?= (int) $ac['max_capacity'] ?> participants</span>
                        <span><?= (int) $ac['pct'] ?>%</span>
                      </div>
                      <div class="pm-progress"><div class="pm-progress__bar" style="width:<?= (int) $ac['pct'] ?>%"></div></div>
                    </div>
                    <div class="admin-exec-cohort-item__footer">
                      <span class="admin-exec-cohort-item__stat"><i class="fas fa-file-alt"></i> <?= (int) $ac['applications'] ?> applications</span>
                      <span class="admin-exec-cohort-item__stat"><i class="fas fa-user-plus"></i> <?= (int) $ac['available'] ?> available</span>
                      <a href="<?= url('admin/cohort_detail.php?id=' . (int) $ac['id']) ?>" class="btn btn--ghost btn--sm">Manage <i class="fas fa-arrow-right"></i></a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- =============================================
             SECTION 2: DASHBOARD ANALYTICS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-analytics" data-section="analytics">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Data Intelligence</span>
            <h2 class="section__title" style="font-size:1.5rem;">Dashboard <span class="text-gradient">Analytics</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Comprehensive analytics across all platform dimensions.</p>
          </div>

          <!-- Analytics Filter Bar -->
          <div class="admin-analytics-filters">
            <div class="admin-analytics-filters__left">
              <select class="admin-filter-select" id="analyticsPeriod">
                <option value="7d">Last 7 Days</option>
                <option value="30d" selected>Last 30 Days</option>
                <option value="90d">Last Quarter</option>
                <option value="12m">Last 12 Months</option>
                <option value="custom">Custom Range</option>
              </select>
              <select class="admin-filter-select" id="analyticsCategory">
                <option value="all">All Categories</option>
                <option value="programmes">Programmes</option>
                <option value="applications">Applications</option>
                <option value="placements">Placements</option>
                <option value="talent">Talent Pool</option>
                <option value="skills">Skills</option>
              </select>
            </div>
            <div class="admin-analytics-filters__right">
              <button class="btn btn--ghost btn--sm"><i class="fas fa-download"></i> Export</button>
              <button class="btn btn--ghost btn--sm"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
          </div>

          <!-- Analytics Charts Grid -->
          <div class="admin-analytics-grid">
            <div class="admin-analytics-card admin-analytics-card--full">
              <div class="admin-analytics-card__header">
                <h3>Programme Performance</h3>
                <span class="admin-analytics-card__badge">Active</span>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsProgrammeChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Application Statistics</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsApplicationChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Placement Statistics</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsPlacementChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Talent Pool Analytics</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsTalentChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Skills Supply & Demand</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsSkillsChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Attendance Statistics</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsAttendanceChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Candidate Growth</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsGrowthChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Learning & Assessment</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsLearningChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Employment Outcomes</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsOutcomeChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card admin-analytics-card--full">
              <div class="admin-analytics-card__header">
                <h3>Recruitment Funnel</h3>
                <span class="admin-analytics-card__badge">Real-time</span>
              </div>
              <div class="admin-analytics-chart-container" style="height:100px;">
                <canvas id="analyticsFunnelChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Analytics Stats Summary -->
          <div class="admin-analytics-stats">
<div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number" data-count="<?= (int)$activeProgrammes ?>">0</span>
              <span class="admin-analytics-stat__label">Active Programmes</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-check"></i> <?= (int)$totalProgrammes ?> total</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number" data-count="<?= (int)$totalApplications ?>">0</span>
              <span class="admin-analytics-stat__label">Total Applications</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-file-alt"></i> across cohorts</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number" data-count="248">0</span>
              <span class="admin-analytics-stat__label">Active Placements</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-arrow-up"></i> +6.8%</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number" data-count="1280">0</span>
              <span class="admin-analytics-stat__label">Talent Pool</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-arrow-up"></i> +11.2%</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number">87<span class="admin-analytics-stat__suffix">%</span></span>
              <span class="admin-analytics-stat__label">Completion Rate</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-arrow-up"></i> +4.2%</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number">78<span class="admin-analytics-stat__suffix">%</span></span>
              <span class="admin-analytics-stat__label">Profile Completeness</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-arrow-up"></i> +3.4%</span>
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 3: ADMINISTRATIVE ALERTS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-alerts" data-section="alerts">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Intelligent Monitoring</span>
            <h2 class="section__title" style="font-size:1.5rem;">Administrative <span class="text-gradient">Alerts</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Smart alerts and notifications requiring your attention.</p>
          </div>

          <div class="admin-alerts-grid">
            <div class="admin-alert-card admin-alert-card--urgent">
              <div class="admin-alert-card__icon"><i class="fas fa-file-excel"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Expiring Documents</span>
                <span class="admin-alert-card__message">12 candidates have expiring compliance documents within 7 days.</span>
              </div>
              <span class="admin-alert-card__time">2h ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card admin-alert-card--warning">
              <div class="admin-alert-card__icon"><i class="fas fa-calendar-times"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Closing Opportunities</span>
                <span class="admin-alert-card__message">5 opportunities are closing within the next 48 hours.</span>
              </div>
              <span class="admin-alert-card__time">5h ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card admin-alert-card--danger">
              <div class="admin-alert-card__icon"><i class="fas fa-clock"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Missing Attendance Records</span>
                <span class="admin-alert-card__message">24 candidates missing attendance logs for the current period.</span>
              </div>
              <span class="admin-alert-card__time">1d ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card">
              <div class="admin-alert-card__icon"><i class="fas fa-user-edit"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Incomplete Profiles</span>
                <span class="admin-alert-card__message">340 candidates have profiles below 50% completeness.</span>
              </div>
              <span class="admin-alert-card__time">1d ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card admin-alert-card--warning">
              <div class="admin-alert-card__icon"><i class="fas fa-shield-alt"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Consent Issues</span>
                <span class="admin-alert-card__message">18 candidates require consent renewal for data processing.</span>
              </div>
              <span class="admin-alert-card__time">2d ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card admin-alert-card--danger">
              <div class="admin-alert-card__icon"><i class="fas fa-exclamation-triangle"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Placement Conflicts</span>
                <span class="admin-alert-card__message">3 candidates have overlapping placement schedules.</span>
              </div>
              <span class="admin-alert-card__time">3d ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
          </div>
        </section>

<!-- =============================================
             SECTION 4: PROGRAMME MANAGEMENT (dynamic)
             ============================================= -->
        <section class="dash-section admin-section" id="admin-programmes" data-section="programmes">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Programme Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Programme <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Create, manage, and monitor programme performance across cohorts.</p>
          </div>

          <!-- Programme Toolbar -->
          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <a href="<?= url('admin/programme_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Programme</a>
              <a href="<?= url('admin/programmes.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-th-list"></i> Manage Programmes</a>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="progSearchInput" placeholder="Search programmes..." oninput="filterDashProgrammes(this.value)">
              </div>
            </div>
          </div>

          <!-- Programme Stats (dynamic) -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$totalProgrammes ?></span> Total</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$activeProgrammes ?></span> Active</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$draftProgrammes ?></span> Draft</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$completedProgrammes ?></span> Completed</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$archivedProgrammes ?></span> Archived</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$activeCohorts ?></span> Active Cohorts</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$totalCapacity ?></span> Capacity</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$totalParticipants ?></span> Participants</div>
          </div>

          <!-- Recent Programmes -->
          <div class="admin-programme-grid" id="adminProgGrid">
            <?php if (empty($recentProgrammes)): ?>
              <div class="admin-empty-state">
                <div class="admin-empty-state__icon"><i class="fas fa-graduation-cap"></i></div>
                <h3>No programmes have been created yet.</h3>
                <p>Create your first programme to begin configuring cohorts and opportunities.</p>
                <a href="<?= url('admin/programme_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Programme</a>
              </div>
            <?php else: ?>
              <?php foreach ($recentProgrammes as $prog): ?>
                <div class="admin-programme-card" data-search="<?= e(strtolower($prog['name'] . ' ' . $prog['type'] . ' ' . ($prog['description'] ?? ''))) ?>">
                  <div class="admin-programme-card__head">
                    <div class="admin-programme-card__icon"><i class="fas fa-graduation-cap"></i></div>
                    <span class="tag tag--<?= e($prog['status']) ?>"><?= e(ucfirst($prog['status'])) ?></span>
                  </div>
                  <h3 class="admin-programme-card__title"><?= e($prog['name']) ?></h3>
                  <p class="admin-programme-card__sub"><?= e(programme_type_label($prog['type'])) ?></p>
                  <p class="admin-programme-card__desc"><?= e(mb_strimwidth($prog['description'] ?? 'No description', 0, 90, '…')) ?></p>
                  <div class="admin-programme-card__meta">
                    <span><i class="fas fa-layer-group"></i> <?= (int)$prog['cohort_count'] ?> cohorts</span>
                    <span><i class="fas fa-users"></i> <?= (int)Programme::participantCount((int)$prog['id']) ?> participants</span>
                  </div>
                  <div class="admin-programme-card__actions">
                    <a href="<?= url('admin/programme_detail.php?id=' . (int)$prog['id']) ?>" class="btn btn--outline btn--sm">View</a>
                    <a href="<?= url('admin/programme_edit.php?id=' . (int)$prog['id']) ?>" class="btn btn--ghost btn--sm">Edit</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <!-- =============================================
             SECTION 5: OPPORTUNITY MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-opportunities" data-section="opportunities">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Opportunity Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Opportunity <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Create, publish, and manage opportunities across programmes.</p>
          </div>

<div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <a href="<?= url('admin/opportunity_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Opportunity</a>
              <a href="<?= url('admin/opportunities.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-th-list"></i> Manage Opportunities</a>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="adminOppSearch" placeholder="Search opportunities..." oninput="filterDashOpportunities(this.value)">
              </div>
            </div>
          </div>

          <!-- Opportunity Stats (dynamic) -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$totalOpportunities ?></span> Total</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$publishedOpps ?></span> Published</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$draftOpps ?></span> Draft</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$closingSoonOpps ?></span> Closing Soon</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$closedOpps ?></span> Closed</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$archivedOpps ?></span> Archived</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$totalApplications ?></span> Applications</div>
          </div>

          <!-- Recent Opportunities -->
          <div class="admin-opp-grid" id="adminOppGrid">
            <?php if (empty($recentOpportunities)): ?>
              <div class="admin-empty-state">
                <div class="admin-empty-state__icon"><i class="fas fa-briefcase"></i></div>
                <h3>No opportunities have been created yet.</h3>
                <p>Create your first opportunity and connect it to an existing programme and cohort.</p>
                <a href="<?= url('admin/opportunity_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Opportunity</a>
              </div>
            <?php else: foreach ($recentOpportunities as $op): ?>
              <div class="admin-opp-card" data-search="<?= e(strtolower($op['title'] . ' ' . ($op['programme_name'] ?? '') . ' ' . ($op['cohort_name'] ?? ''))) ?>">
                <div class="admin-opp-card__head">
                  <div class="admin-opp-card__icon"><i class="fas fa-briefcase"></i></div>
                  <span class="tag tag--<?= e($op['status']) ?>"><?= e(OPPORTUNITY_STATUS_LABELS[$op['status']] ?? ucfirst($op['status'])) ?></span>
                </div>
                <h3 class="admin-opp-card__title"><?= e($op['title']) ?></h3>
                <p class="admin-opp-card__sub"><?= e(OPPORTUNITY_TYPE_LABELS[$op['type']] ?? ucfirst($op['type'])) ?></p>
                <p class="admin-opp-card__desc"><?= e(mb_strimwidth($op['short_description'] ?? 'No description', 0, 90, '…')) ?></p>
                <div class="admin-opp-card__meta">
                  <span><i class="fas fa-graduation-cap"></i> <?= e($op['programme_name'] ?? '—') ?></span>
                  <span><i class="fas fa-layer-group"></i> <?= e($op['cohort_name'] ?? 'No cohort') ?></span>
                  <span><i class="fas fa-users"></i> <?= (int)$op['available_positions'] ?> positions</span>
                </div>
                <div class="admin-opp-card__actions">
                  <a href="<?= url('admin/opportunity_detail.php?id=' . (int)$op['id']) ?>" class="btn btn--outline btn--sm">View</a>
                  <a href="<?= url('admin/opportunity_edit.php?id=' . (int)$op['id']) ?>" class="btn btn--ghost btn--sm">Edit</a>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </section>

        <!-- =============================================
             SECTION 6: APPLICATION MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-applications" data-section="applications">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Application Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Application <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Review, shortlist, and manage candidate applications.</p>
          </div>

          <!-- Application Status Pipeline -->
          <div class="admin-pipeline" id="adminPipeline">
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count">156</span>
                <span class="admin-pipeline__label">Submitted</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="submitted">
                <!-- Populated by JS -->
              </div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count">89</span>
                <span class="admin-pipeline__label">Under Review</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="review"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count">42</span>
                <span class="admin-pipeline__label">Assessment</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="assessment"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count">28</span>
                <span class="admin-pipeline__label">Interview</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="interview"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count">12</span>
                <span class="admin-pipeline__label">Waitlisted</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="waitlisted"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count admin-pipeline__count--success">18</span>
                <span class="admin-pipeline__label">Selected</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="selected"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count admin-pipeline__count--danger">24</span>
                <span class="admin-pipeline__label">Rejected</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="rejected"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count admin-pipeline__count--muted">8</span>
                <span class="admin-pipeline__label">Withdrawn</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="withdrawn"></div>
            </div>
          </div>

          <!-- Application Filters -->
          <div class="admin-toolbar" style="margin-top:1.5rem;">
            <div class="admin-toolbar__left">
              <button class="btn btn--outline btn--sm"><i class="fas fa-filter"></i> Advanced Filters</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-check-double"></i> Bulk Actions</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-file-export"></i> Export</button>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="appSearchInput" placeholder="Search applications...">
              </div>
            </div>
          </div>

          <!-- Application Table -->
          <div class="admin-table-container" id="adminAppTable">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 7: PLACEMENT MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-placements" data-section="placements">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Placement Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Placement <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Manage candidate placements, host organisations, and workplace activities.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <button class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> New Placement</button>
              <select class="admin-filter-select" id="placementFilterStatus">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="upcoming">Upcoming</option>
                <option value="risk">At Risk</option>
              </select>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="placementSearch" placeholder="Search placements...">
              </div>
            </div>
          </div>

          <!-- Placement Stats -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">248</span> Active Placements</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">1,056</span> Completed</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">64</span> Host Orgs</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">92%</span> Retention Rate</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">4.6/5</span> Avg Feedback</div>
          </div>

          <div class="admin-placement-grid" id="adminPlacementGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 8: INTERVIEW MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-interviews" data-section="interviews">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Interview Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Interview <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Schedule, manage, and track interviews and assessments.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <button class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Schedule Interview</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-calendar-alt"></i> Calendar View</button>
              <select class="admin-filter-select" id="interviewFilterDate">
                <option value="today">Today</option>
                <option value="week" selected>This Week</option>
                <option value="month">This Month</option>
                <option value="all">All</option>
              </select>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="interviewSearch" placeholder="Search interviews...">
              </div>
            </div>
          </div>

          <div class="admin-interview-grid" id="adminInterviewGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 9: TALENT INTELLIGENCE HUB
             ============================================= -->
        <section class="dash-section admin-section" id="admin-talent-hub" data-section="talent-hub">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Talent Intelligence</span>
            <h2 class="section__title" style="font-size:1.5rem;">Talent Intelligence <span class="text-gradient">Hub</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Discover, analyse, and engage with platform talent.</p>
          </div>

          <!-- Talent Hub Stats -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">3,420</span> Total Talent Pool</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">2,175</span> Verified Candidates</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">1,890</span> Available Now</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">42</span> Scarce Skills Categories</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">78%</span> Avg Profile Score</div>
          </div>

          <!-- Advanced Filters -->
          <div class="admin-talent-filters">
            <div class="admin-talent-filters__row">
              <div class="admin-filter-group">
                <label class="admin-filter-label">Qualifications</label>
                <select class="admin-filter-select" multiple size="1">
                  <option value="">All Qualifications</option>
                  <option value="degree">Bachelor's Degree</option>
                  <option value="honours">Honours</option>
                  <option value="masters">Master's</option>
                  <option value="diploma">Diploma</option>
                  <option value="certificate">Certificate</option>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Skills</label>
                <select class="admin-filter-select">
                  <option value="">All Skills</option>
                  <option value="javascript">JavaScript</option>
                  <option value="python">Python</option>
                  <option value="cloud">Cloud Computing</option>
                  <option value="cyber">Cyber Security</option>
                  <option value="data">Data Analytics</option>
                  <option value="devops">DevOps</option>
                  <option value="ai">AI/ML</option>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Location</label>
                <select class="admin-filter-select">
                  <option value="">All Locations</option>
                  <option value="gauteng">Gauteng</option>
                  <option value="western-cape">Western Cape</option>
                  <option value="kwazulu-natal">KwaZulu-Natal</option>
                  <option value="eastern-cape">Eastern Cape</option>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Availability</label>
                <select class="admin-filter-select">
                  <option value="">All</option>
                  <option value="immediate">Immediate</option>
                  <option value="2-weeks">Within 2 Weeks</option>
                  <option value="1-month">Within 1 Month</option>
                  <option value="not-available">Not Available</option>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Experience</label>
                <select class="admin-filter-select">
                  <option value="">All Levels</option>
                  <option value="entry">Entry Level (0-2 yrs)</option>
                  <option value="mid">Mid Level (3-5 yrs)</option>
                  <option value="senior">Senior (6-10 yrs)</option>
                  <option value="lead">Lead (10+ yrs)</option>
                </select>
              </div>
            </div>
            <div class="admin-talent-filters__actions">
              <button class="btn btn--primary btn--sm"><i class="fas fa-search"></i> Search Talent</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-save"></i> Save Search</button>
              <button class="btn btn--ghost btn--sm"><i class="fas fa-file-export"></i> Export Results</button>
              <span class="admin-talent-filters__count">Showing <strong>145</strong> candidates</span>
            </div>
          </div>

          <!-- Talent Search Results -->
          <div class="admin-talent-results" id="adminTalentResults">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 10: TALENT MATCHING SYSTEM
             ============================================= -->
        <section class="dash-section admin-section" id="admin-talent-matching" data-section="talent-matching">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Intelligent Matching</span>
            <h2 class="section__title" style="font-size:1.5rem;">Talent <span class="text-gradient">Matching System</span></h2>
            <p class="section__text" style="font-size:0.9rem;">AI-powered matching to find the best candidates for opportunities.</p>
          </div>

          <div class="admin-matching-dashboard">
            <!-- Match Overview -->
            <div class="admin-matching-overview">
              <div class="admin-matching-stat">
                <span class="admin-matching-stat__value">92%</span>
                <span class="admin-matching-stat__label">Average Match Accuracy</span>
              </div>
              <div class="admin-matching-stat">
                <span class="admin-matching-stat__value">156</span>
                <span class="admin-matching-stat__label">Pending Matches</span>
              </div>
              <div class="admin-matching-stat">
                <span class="admin-matching-stat__value">48</span>
                <span class="admin-matching-stat__label">Recommended Placements</span>
              </div>
              <div class="admin-matching-stat">
                <span class="admin-matching-stat__value">89%</span>
                <span class="admin-matching-stat__label">Candidate Readiness</span>
              </div>
            </div>

            <!-- Matching Cards -->
            <div class="admin-matching-grid" id="adminMatchingGrid">
              <!-- Populated by JS -->
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 11: TALENT POOLS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-talent-pool" data-section="talent-pool">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Talent Pools</span>
            <h2 class="section__title" style="font-size:1.5rem;">Talent <span class="text-gradient">Pools</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Manage and monitor talent pools across skill categories.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <button class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Talent Pool</button>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="poolSearch" placeholder="Search pools...">
              </div>
            </div>
          </div>

          <div class="admin-pool-grid" id="adminPoolGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 12: ATTENDANCE & TIMESHEETS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-attendance" data-section="attendance">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Attendance Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Attendance & <span class="text-gradient">Timesheets</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Monitor attendance, approve timesheets, and track compliance.</p>
          </div>

          <!-- Attendance Stats -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">94%</span> Attendance Rate</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">24</span> Missing Timesheets</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">88%</span> Approval Rate</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">96%</span> Programme Compliance</div>
            <div class="admin-stat-chip admin-stat-chip--danger"><span class="admin-stat-chip__value">6</span> Attendance Risks</div>
          </div>

          <div class="admin-attendance-grid" id="adminAttendanceGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 13: COMMUNICATION CENTRE
             ============================================= -->
        <section class="dash-section admin-section" id="admin-communication" data-section="communication">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Communication Centre</span>
            <h2 class="section__title" style="font-size:1.5rem;">Communication <span class="text-gradient">Centre</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Send notifications, manage templates, and monitor delivery.</p>
          </div>

          <div class="admin-comm-toolbar">
            <button class="btn btn--primary btn--sm"><i class="fas fa-paper-plane"></i> Send Notification</button>
            <button class="btn btn--outline btn--sm"><i class="fas fa-users"></i> Bulk Notify</button>
            <button class="btn btn--outline btn--sm"><i class="fas fa-envelope"></i> Email Templates</button>
            <button class="btn btn--outline btn--sm"><i class="fas fa-clock"></i> Schedule Reminder</button>
            <button class="btn btn--ghost btn--sm"><i class="fas fa-history"></i> Notification History</button>
          </div>

          <!-- Delivery Stats -->
          <div class="admin-stats-row" style="margin-top:1rem;">
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">12,450</span> Total Sent</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">94%</span> Delivery Rate</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">68%</span> Open Rate</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">42%</span> Click Rate</div>
          </div>

          <!-- Communication Channels -->
          <div class="admin-comm-channels">
            <div class="admin-comm-channel">
              <div class="admin-comm-channel__icon admin-comm-channel__icon--email"><i class="fas fa-envelope"></i></div>
              <div class="admin-comm-channel__info">
                <span class="admin-comm-channel__name">Email</span>
                <span class="admin-comm-channel__status">Connected</span>
              </div>
              <span class="admin-comm-channel__count">8,450 sent</span>
            </div>
            <div class="admin-comm-channel">
              <div class="admin-comm-channel__icon admin-comm-channel__icon--sms"><i class="fas fa-sms"></i></div>
              <div class="admin-comm-channel__info">
                <span class="admin-comm-channel__name">SMS</span>
                <span class="admin-comm-channel__status">Connected</span>
              </div>
              <span class="admin-comm-channel__count">3,200 sent</span>
            </div>
            <div class="admin-comm-channel">
              <div class="admin-comm-channel__icon admin-comm-channel__icon--inapp"><i class="fas fa-bell"></i></div>
              <div class="admin-comm-channel__info">
                <span class="admin-comm-channel__name">In-App Notifications</span>
                <span class="admin-comm-channel__status">Active</span>
              </div>
              <span class="admin-comm-channel__count">12,450 pushed</span>
            </div>
            <div class="admin-comm-channel admin-comm-channel--disabled">
              <div class="admin-comm-channel__icon"><i class="fab fa-whatsapp"></i></div>
              <div class="admin-comm-channel__info">
                <span class="admin-comm-channel__name">WhatsApp</span>
                <span class="admin-comm-channel__status admin-comm-channel__status--disabled">Coming Soon</span>
              </div>
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 14: REPORTING & ANALYTICS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-reporting" data-section="reporting">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Reporting</span>
            <h2 class="section__title" style="font-size:1.5rem;">Reporting & <span class="text-gradient">Analytics</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Generate, export, and schedule comprehensive reports.</p>
          </div>

          <div class="admin-report-categories">
            <button class="admin-report-cat active" data-report="programmes"><i class="fas fa-graduation-cap"></i> Programmes</button>
            <button class="admin-report-cat" data-report="candidates"><i class="fas fa-users"></i> Candidates</button>
            <button class="admin-report-cat" data-report="placements"><i class="fas fa-handshake"></i> Placements</button>
            <button class="admin-report-cat" data-report="recruitment"><i class="fas fa-search"></i> Recruitment</button>
            <button class="admin-report-cat" data-report="skills"><i class="fas fa-code"></i> Skills</button>
            <button class="admin-report-cat" data-report="attendance"><i class="fas fa-clock"></i> Attendance</button>
            <button class="admin-report-cat" data-report="completion"><i class="fas fa-check-circle"></i> Completion</button>
            <button class="admin-report-cat" data-report="employment"><i class="fas fa-briefcase"></i> Employment</button>
            <button class="admin-report-cat" data-report="governance"><i class="fas fa-shield-alt"></i> Governance</button>
            <button class="admin-report-cat" data-report="talent"><i class="fas fa-database"></i> Talent Pool</button>
          </div>

          <div class="admin-reports-list" id="adminReportsList">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 15: CONSENT & COMPLIANCE
             ============================================= -->
        <section class="dash-section admin-section" id="admin-compliance" data-section="compliance">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Compliance</span>
            <h2 class="section__title" style="font-size:1.5rem;">Consent & <span class="text-gradient">Compliance</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Manage consent records, data quality, and governance.</p>
          </div>

          <div class="admin-compliance-grid">
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--green"><i class="fas fa-check-circle"></i></div>
                <span class="admin-compliance-card__value">92%</span>
              </div>
              <span class="admin-compliance-card__label">Consent Provided</span>
              <span class="admin-compliance-card__sub">3,146 candidates</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--primary"><i class="fas fa-id-card"></i></div>
                <span class="admin-compliance-card__value">78%</span>
              </div>
              <span class="admin-compliance-card__label">Profile Verification</span>
              <span class="admin-compliance-card__sub">2,668 verified</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--amber"><i class="fas fa-database"></i></div>
                <span class="admin-compliance-card__value">95%</span>
              </div>
              <span class="admin-compliance-card__label">Data Quality Score</span>
              <span class="admin-compliance-card__sub">A rating</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--purple"><i class="fas fa-file-export"></i></div>
                <span class="admin-compliance-card__value">142</span>
              </div>
              <span class="admin-compliance-card__label">Export Activities</span>
              <span class="admin-compliance-card__sub">This month</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--cyan"><i class="fas fa-history"></i></div>
                <span class="admin-compliance-card__value">2,450</span>
              </div>
              <span class="admin-compliance-card__label">Audit Events</span>
              <span class="admin-compliance-card__sub">This quarter</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--indigo"><i class="fas fa-calendar-alt"></i></div>
                <span class="admin-compliance-card__value">24</span>
              </div>
              <span class="admin-compliance-card__label">Retention Schedules</span>
              <span class="admin-compliance-card__sub">Active policies</span>
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 16: AUDIT LOG
             ============================================= -->
        <section class="dash-section admin-section" id="admin-audit" data-section="audit">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Audit Trail</span>
            <h2 class="section__title" style="font-size:1.5rem;">Audit <span class="text-gradient">Log</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Comprehensive audit trail of all platform activities.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <select class="admin-filter-select" id="auditFilterAction">
                <option value="all">All Actions</option>
                <option value="auth">Authentication</option>
                <option value="programme">Programme Changes</option>
                <option value="candidate">Candidate Updates</option>
                <option value="permissions">Permission Changes</option>
                <option value="exports">Data Exports</option>
                <option value="reports">Report Generation</option>
                <option value="admin">Admin Actions</option>
                <option value="notifications">Notifications</option>
              </select>
              <select class="admin-filter-select" id="auditFilterDate">
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d" selected>Last 30 Days</option>
                <option value="90d">Last Quarter</option>
              </select>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="auditSearch" placeholder="Search audit log...">
              </div>
            </div>
          </div>

          <div class="admin-table-container admin-audit-table" id="adminAuditTable">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 17: USER MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-users" data-section="users">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">User Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">User <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Manage platform users, roles, permissions, and security settings.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <button class="btn btn--primary btn--sm"><i class="fas fa-user-plus"></i> Create User</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-users"></i> Bulk Invite</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-user-tag"></i> Roles</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-shield-alt"></i> Permissions</button>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="userSearchInput" placeholder="Search users...">
              </div>
            </div>
          </div>

          <!-- User Stats -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">3,420</span> Total Users</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">12</span> Administrators</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">3,408</span> Candidates</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">68</span> Active Sessions</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">156</span> New This Month</div>
          </div>

          <div class="admin-table-container" id="adminUserTable">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 18: QUICK ACTIONS PANEL
             ============================================= -->
        <section class="dash-section admin-section" id="admin-quick-actions" data-section="quick-actions">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Productivity</span>
            <h2 class="section__title" style="font-size:1.5rem;">Quick <span class="text-gradient">Actions</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Frequently used administrative actions at your fingertips.</p>
          </div>

<div class="admin-quick-actions-grid">
            <a href="<?= url('admin/programme_create.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--primary"><i class="fas fa-graduation-cap"></i></div>
              <span>Create Programme</span>
            </a>
            <a href="<?= url('admin/cohort_create.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--cyan"><i class="fas fa-layer-group"></i></div>
              <span>Create Cohort</span>
            </a>
            <a href="<?= url('admin/programmes.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--green"><i class="fas fa-th-list"></i></div>
              <span>Manage Programmes</span>
            </a>
            <a href="#admin-talent-hub" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--amber"><i class="fas fa-database"></i></div>
              <span>Talent Intelligence</span>
            </a>
            <a href="#admin-applications" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--purple"><i class="fas fa-file-alt"></i></div>
              <span>Review Applications</span>
            </a>
            <a href="#admin-reporting" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--indigo"><i class="fas fa-chart-line"></i></div>
              <span>Generate Reports</span>
            </a>
            <a href="#admin-communication" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--red"><i class="fas fa-bullhorn"></i></div>
              <span>Send Notifications</span>
            </a>
            <a href="#admin-audit" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--primary"><i class="fas fa-history"></i></div>
              <span>View Audit Logs</span>
            </a>
            <a href="#admin-placements" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--cyan"><i class="fas fa-handshake"></i></div>
              <span>Manage Placements</span>
            </a>
            <a href="#admin-compliance" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--amber"><i class="fas fa-shield-alt"></i></div>
              <span>Review Compliance</span>
            </a>
            <a href="<?= url('candidate/settings.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--green"><i class="fas fa-cog"></i></div>
              <span>System Settings</span>
            </a>
            <a href="<?= url('auth/logout.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--purple"><i class="fas fa-sign-out-alt"></i></div>
              <span>Sign Out</span>
            </a>
          </div>
        </section>

      </div><!-- // dash-content -->

      <!-- ===== DASHBOARD FOOTER ===== -->
      <footer class="dash-footer admin-footer">
        <div class="container">
          <div class="dash-footer__inner">
            <p>&copy; 2025 Investhood IT. All rights reserved.</p>
            <div class="dash-footer__links">
              <a href="#">Privacy Policy</a>
              <a href="#">Terms & Conditions</a>
              <a href="<?= url('index.php') ?>">Back to Home</a>
              <a href="<?= url('candidate/dashboard.php') ?>">Candidate Dashboard</a>
            </div>
          </div>
        </div>
      </footer>

    </main>
  </div>

  <!-- ===== SKELETON LOADER TEMPLATES ===== -->
  <template id="skeletonExecCard">
    <div class="admin-exec-card skeleton">
      <div class="admin-exec-card__header">
        <div class="skeleton skeleton--icon"></div>
        <div class="skeleton skeleton--badge"></div>
      </div>
      <div class="skeleton skeleton--h2" style="width:60%;"></div>
      <div class="skeleton skeleton--text" style="width:40%;"></div>
      <div class="skeleton skeleton--text" style="width:70%;"></div>
    </div>
  </template>

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
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>