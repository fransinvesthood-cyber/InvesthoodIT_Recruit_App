<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Opportunities Page
 * ================================================
 * Lists all opportunities with live stats, search,
 * filters and lifecycle actions (view/edit/publish/
 * close/archive/duplicate).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user = current_user();

// ---- Live statistics from MySQL ----
$counts   = Opportunity::countsByStatus();
$total    = array_sum($counts);
$agg      = Opportunity::aggregateStats();
$published    = (int) ($counts['published'] ?? 0);
$drafts       = (int) ($counts['draft'] ?? 0);
$closingSoon  = (int) ($counts['closing_soon'] ?? 0);
$closedCount  = (int) ($counts['closed'] ?? 0);
$archived     = (int) ($counts['archived'] ?? 0);

// ---- Search & filter state ----
$search  = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fType   = $_GET['type'] ?? '';
$fProg   = $_GET['programme'] ?? '';
$fCohort = $_GET['cohort'] ?? '';
$fProv   = $_GET['province'] ?? '';
$fOpen   = $_GET['open_date'] ?? '';
$fClose  = $_GET['close_date'] ?? '';

$opportunities = Opportunity::all();
$programmes    = Programme::all();
$cohorts       = Cohort::activeAll();

// ---- Automatic closing: treat past-close published opportunities as closed ----
$today = date('Y-m-d');
foreach ($opportunities as &$opp) {
    $opp['effective_status'] = $opp['status'];
    if (in_array($opp['status'], ['published', 'closing_soon'], true)
        && !empty($opp['application_close_date'])
        && $opp['application_close_date'] < $today) {
        $opp['effective_status'] = 'closed';
    }
}
unset($opp);

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Opportunities - Investhood IT Administrator">
  <title>Opportunities | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_opportunities.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard pm-module opp-module">

  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li>
        </ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link active"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="#admin-applications" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
        </ul>
        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-history"></i> Audit Log</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span>
            <span class="sidebar__user-role"><?= e($user['role_name'] ?? 'Administrator') ?></span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN ===== -->
    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <div class="dash-header__search">
            <i class="fas fa-search"></i>
            <input type="text" class="dash-header__search-input" placeholder="Search opportunities..." aria-label="Search">
          </div>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <a href="<?= url('admin/opportunity_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Opportunity</a>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== PAGE HEADER ===== -->
        <section class="pm-hero">
          <div class="pm-hero__inner">
            <div class="pm-hero__text">
              <span class="section__badge">Opportunity Management</span>
              <h1 class="pm-hero__title">Opportunities</h1>
              <p class="pm-hero__subtitle">Create, publish, close and archive opportunities linked to your programmes and cohorts.</p>
            </div>
            <a href="<?= url('admin/opportunity_create.php') ?>" class="btn btn--primary"><i class="fas fa-plus"></i> Create Opportunity</a>
          </div>
        </section>

        <!-- ===== STATS ===== -->
        <section class="pm-stats">
          <div class="pm-stat pm-stat--total"><div class="pm-stat__icon"><i class="fas fa-briefcase"></i></div><div><span class="pm-stat__value"><?= (int) $total ?></span><span class="pm-stat__label">Total Opportunities</span></div></div>
          <div class="pm-stat pm-stat--active"><div class="pm-stat__icon"><i class="fas fa-play-circle"></i></div><div><span class="pm-stat__value"><?= (int) $published ?></span><span class="pm-stat__label">Published</span></div></div>
          <div class="pm-stat pm-stat--draft"><div class="pm-stat__icon"><i class="fas fa-file-alt"></i></div><div><span class="pm-stat__value"><?= (int) $drafts ?></span><span class="pm-stat__label">Drafts</span></div></div>
          <div class="pm-stat pm-stat--closing"><div class="pm-stat__icon"><i class="fas fa-hourglass-half"></i></div><div><span class="pm-stat__value"><?= (int) $closingSoon ?></span><span class="pm-stat__label">Closing Soon</span></div></div>
          <div class="pm-stat pm-stat--closed"><div class="pm-stat__icon"><i class="fas fa-lock"></i></div><div><span class="pm-stat__value"><?= (int) $closedCount ?></span><span class="pm-stat__label">Closed</span></div></div>
          <div class="pm-stat pm-stat--archived"><div class="pm-stat__icon"><i class="fas fa-archive"></i></div><div><span class="pm-stat__value"><?= (int) $archived ?></span><span class="pm-stat__label">Archived</span></div></div>
        </section>

        <!-- ===== CAPACITY / APPLICATION STATS ===== -->
        <section class="opp-agg-stats">
          <div class="opp-agg-stat"><span class="opp-agg-stat__value"><?= (int) $agg['total_positions'] ?></span><span class="opp-agg-stat__label">Total Available Positions</span></div>
          <div class="opp-agg-stat"><span class="opp-agg-stat__value"><?= (int) $agg['total_applications'] ?></span><span class="opp-agg-stat__label">Total Applications</span></div>
          <div class="opp-agg-stat"><span class="opp-agg-stat__value"><?= (float) $agg['avg_applications'] ?></span><span class="opp-agg-stat__label">Avg Applications / Opportunity</span></div>
        </section>

        <!-- ===== FILTERS ===== -->
        <section class="pm-filters opp-filters">
          <div class="pm-filters__search">
            <i class="fas fa-search"></i>
            <input type="text" id="oppSearchInput" class="pm-filters__input" placeholder="Search by title, programme, cohort, skills, location..." value="<?= e($search) ?>">
          </div>
          <select id="oppFilterType" class="pm-filters__select">
            <option value="">All Types</option>
            <?php foreach (OPPORTUNITY_TYPE_LABELS as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $fType === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="oppFilterProgramme" class="pm-filters__select">
            <option value="">All Programmes</option>
            <?php foreach ($programmes as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= $fProg === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="oppFilterCohort" class="pm-filters__select">
            <option value="">All Cohorts</option>
            <?php foreach ($cohorts as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $fCohort === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="oppFilterProvince" class="pm-filters__select">
            <option value="">All Provinces</option>
            <?php foreach (ALLOWED_PROVINCES as $pr): ?>
              <option value="<?= e($pr) ?>" <?= $fProv === $pr ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $pr))) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="oppFilterStatus" class="pm-filters__select">
            <option value="">All Statuses</option>
            <?php foreach (OPPORTUNITY_STATUS_LABELS as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $fStatus === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="date" id="oppFilterOpen" class="pm-filters__select" value="<?= e($fOpen) ?>" title="Opening date">
          <input type="date" id="oppFilterClose" class="pm-filters__select" value="<?= e($fClose) ?>" title="Closing date">
          <button class="btn btn--primary btn--sm" id="oppApplyFilters"><i class="fas fa-filter"></i> Apply Filters</button>
          <button class="btn btn--ghost btn--sm" id="oppClearFilters"><i class="fas fa-times"></i> Clear</button>
        </section>

        <!-- ===== RESULTS COUNT ===== -->
        <div class="pm-results-count" id="oppResultsCount"></div>

        <!-- ===== OPPORTUNITY LIST ===== -->
        <section class="pm-list" id="oppList">
          <?php if (empty($opportunities)): ?>
            <div class="pm-empty">
              <div class="pm-empty__icon"><i class="fas fa-briefcase"></i></div>
              <h3>No opportunities have been created yet.</h3>
              <p>Create your first opportunity and connect it to an existing programme and cohort.</p>
              <a href="<?= url('admin/opportunity_create.php') ?>" class="btn btn--primary"><i class="fas fa-plus"></i> Create Opportunity</a>
            </div>
          <?php else: ?>
            <?php foreach ($opportunities as $o):
              $eff = $o['effective_status'];
              $skills = OpportunitySkill::forOpportunity((int) $o['id']);
              $skillNames = array_map(fn($s) => $s['skill_name'], $skills);
            ?>
            <div class="pm-card opp-card"
                 data-id="<?= (int) $o['id'] ?>"
                 data-title="<?= e(strtolower($o['title'])) ?>"
                 data-programme="<?= e(strtolower($o['programme_name'] ?? '')) ?>"
                 data-programme-id="<?= (int) $o['programme_id'] ?>"
                 data-cohort="<?= e(strtolower($o['cohort_name'] ?? '')) ?>"
                 data-cohort-id="<?= (int) ($o['cohort_id'] ?: 0) ?>"
                 data-skills="<?= e(strtolower(implode(' ', $skillNames))) ?>"
                 data-location="<?= e(strtolower($o['city'] . ' ' . ($o['province'] ?? ''))) ?>"
                 data-type="<?= e($o['type']) ?>"
                 data-province="<?= e($o['province'] ?? '') ?>"
                 data-status="<?= e($eff) ?>"
                 data-open="<?= e($o['application_open_date'] ?? '') ?>"
                 data-close="<?= e($o['application_close_date'] ?? '') ?>">
              <div class="pm-card__main">
                <div class="pm-card__top">
                  <div class="pm-card__title-row">
                    <span class="pm-card__type"><?= e(OPPORTUNITY_TYPE_LABELS[$o['type']] ?? ucfirst(str_replace('_', ' ', $o['type']))) ?></span>
                    <?= status_badge($eff, OPPORTUNITY_STATUS_LABELS[$eff] ?? ucfirst(str_replace('_', ' ', $eff))) ?>
                  </div>
                  <h3 class="pm-card__name"><?= e($o['title']) ?></h3>
                  <p class="pm-card__desc"><?= e(mb_strimwidth($o['short_description'] ?? 'No description provided.', 0, 140, '…')) ?></p>
                </div>
                <div class="pm-card__meta">
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Programme</span><span class="pm-card__meta-value"><?= e($o['programme_name'] ?? '—') ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Cohort</span><span class="pm-card__meta-value"><?= e($o['cohort_name'] ?? '—') ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Location</span><span class="pm-card__meta-value"><?= e($o['city'] ?: ucfirst(str_replace('-', ' ', $o['province'] ?? '—'))) ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Positions</span><span class="pm-card__meta-value"><?= (int) $o['available_positions'] ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Applications</span><span class="pm-card__meta-value"><?= (int) $o['applications_count'] ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Opening</span><span class="pm-card__meta-value"><?= e(!empty($o['application_open_date']) ? format_date($o['application_open_date'], 'd M Y') : '—') ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Closing</span><span class="pm-card__meta-value"><?= e(!empty($o['application_close_date']) ? format_date($o['application_close_date'], 'd M Y') : '—') ?></span></div>
                </div>
              </div>
              <div class="pm-card__actions opp-card__actions">
                <a href="<?= url('admin/opportunity_detail.php?id=' . (int) $o['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i> View</a>
                <a href="<?= url('admin/opportunity_edit.php?id=' . (int) $o['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i> Edit</a>
                <?php if ($eff === 'draft'): ?>
                  <form method="post" action="<?= url('admin/opportunity_actions.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="publish">
                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                    <button type="submit" class="btn btn--outline btn--sm"><i class="fas fa-rocket"></i> Publish</button>
                  </form>
                <?php elseif (in_array($eff, ['published', 'closing_soon'], true)): ?>
                  <form method="post" action="<?= url('admin/opportunity_actions.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                    <button type="submit" class="btn btn--outline btn--sm"><i class="fas fa-times-circle"></i> Close</button>
                  </form>
                <?php endif; ?>
                <div class="pm-card__more">
                  <button class="btn btn--ghost btn--sm pm-more-btn" aria-label="More actions"><i class="fas fa-ellipsis-v"></i></button>
                  <div class="pm-card__menu">
                    <form method="post" action="<?= url('admin/opportunity_actions.php') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="duplicate">
                      <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                      <button type="submit" class="pm-card__menu-item"><i class="fas fa-copy"></i> Duplicate</button>
                    </form>
                    <?php if (in_array($eff, ['draft', 'published', 'closing_soon'], true)): ?>
                    <form method="post" action="<?= url('admin/opportunity_actions.php') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="archive">
                      <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                      <button type="submit" class="pm-card__menu-item pm-card__menu-item--danger"><i class="fas fa-archive"></i> Archive</button>
                    </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>

      </div>

      <footer class="dash-footer admin-footer">
        <div class="container">
          <div class="dash-footer__inner">
            <p>&copy; 2025 Investhood IT. All rights reserved.</p>
            <div class="dash-footer__links">
              <a href="#">Privacy Policy</a>
              <a href="<?= url('index.php') ?>">Back to Home</a>
            </div>
          </div>
        </div>
      </footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <script src="<?= url('js/admin_opportunities.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
