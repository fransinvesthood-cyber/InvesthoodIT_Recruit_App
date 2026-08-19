<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Programmes Page
 * ================================================
 * Lists all programmes with search, filters, stats
 * and actions (view/edit/cohorts/duplicate/archive).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user = current_user();

// ---- Search & filter state ----
$search   = trim($_GET['q'] ?? '');
$fStatus  = $_GET['status'] ?? '';
$fType    = $_GET['type'] ?? '';
$fStart   = $_GET['start_date'] ?? '';
$fEnd     = $_GET['end_date'] ?? '';

$programmes = Programme::all();
$counts     = Programme::countsByStatus();

// ---- Client-side filtering will run on the loaded list ----
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Programmes - Investhood IT Administrator">
  <title>Programmes | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard pm-module">

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
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link active"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="#admin-opportunities" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
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
            <input type="text" class="dash-header__search-input" placeholder="Search programmes, cohorts..." aria-label="Search">
          </div>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <a href="<?= url('admin/programme_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Programme</a>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== PAGE HEADER ===== -->
        <section class="pm-hero">
          <div class="pm-hero__inner">
            <div class="pm-hero__text">
              <span class="section__badge">Programme Management</span>
              <h1 class="pm-hero__title">Programmes</h1>
              <p class="pm-hero__subtitle">Create and manage programmes, cohorts, eligibility requirements, capacity, and programme configuration.</p>
            </div>
            <a href="<?= url('admin/programme_create.php') ?>" class="btn btn--primary"><i class="fas fa-plus"></i> Create Programme</a>
          </div>
        </section>

        <!-- ===== STATS ===== -->
        <section class="pm-stats">
          <div class="pm-stat pm-stat--total"><div class="pm-stat__icon"><i class="fas fa-layer-group"></i></div><div><span class="pm-stat__value"><?= (int) count($programmes) ?></span><span class="pm-stat__label">Total Programmes</span></div></div>
          <div class="pm-stat pm-stat--active"><div class="pm-stat__icon"><i class="fas fa-play-circle"></i></div><div><span class="pm-stat__value"><?= (int) $counts['active'] ?></span><span class="pm-stat__label">Active</span></div></div>
          <div class="pm-stat pm-stat--draft"><div class="pm-stat__icon"><i class="fas fa-file-alt"></i></div><div><span class="pm-stat__value"><?= (int) $counts['draft'] ?></span><span class="pm-stat__label">Draft</span></div></div>
          <div class="pm-stat pm-stat--completed"><div class="pm-stat__icon"><i class="fas fa-check-circle"></i></div><div><span class="pm-stat__value"><?= (int) $counts['completed'] ?></span><span class="pm-stat__label">Completed</span></div></div>
          <div class="pm-stat pm-stat--archived"><div class="pm-stat__icon"><i class="fas fa-archive"></i></div><div><span class="pm-stat__value"><?= (int) $counts['archived'] ?></span><span class="pm-stat__label">Archived</span></div></div>
        </section>

        <!-- ===== FILTERS ===== -->
        <section class="pm-filters">
          <div class="pm-filters__search">
            <i class="fas fa-search"></i>
            <input type="text" id="pmSearchInput" class="pm-filters__input" placeholder="Search by programme name, type, or description..." value="<?= e($search) ?>">
          </div>
          <select id="pmFilterStatus" class="pm-filters__select">
            <option value="">All Statuses</option>
            <?php foreach (PROGRAMME_STATUS_LABELS as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $fStatus === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="pmFilterType" class="pm-filters__select">
            <option value="">All Types</option>
            <?php foreach (PROGRAMME_TYPE_LABELS as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $fType === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="date" id="pmFilterStart" class="pm-filters__select" value="<?= e($fStart) ?>" title="Start date">
          <input type="date" id="pmFilterEnd" class="pm-filters__select" value="<?= e($fEnd) ?>" title="End date">
          <button class="btn btn--primary btn--sm" id="pmApplyFilters"><i class="fas fa-filter"></i> Apply Filters</button>
          <button class="btn btn--ghost btn--sm" id="pmClearFilters"><i class="fas fa-times"></i> Clear</button>
        </section>

        <!-- ===== RESULTS COUNT ===== -->
        <div class="pm-results-count" id="pmResultsCount"></div>

        <!-- ===== PROGRAMME LIST ===== -->
        <section class="pm-list" id="pmList">
          <?php if (empty($programmes)): ?>
            <div class="pm-empty">
              <div class="pm-empty__icon"><i class="fas fa-graduation-cap"></i></div>
              <h3>No programmes have been created yet.</h3>
              <p>Create your first programme to begin configuring cohorts and opportunities.</p>
              <a href="<?= url('admin/programme_create.php') ?>" class="btn btn--primary"><i class="fas fa-plus"></i> Create Programme</a>
            </div>
          <?php else: ?>
            <?php foreach ($programmes as $p):
              $participants = Programme::participantCount((int) $p['id']);
              $applications = Programme::applicationCount((int) $p['id']);
              $activeCohort = '';
              foreach (Cohort::forProgramme((int) $p['id']) as $c) {
                  if ($c['status'] === 'active') { $activeCohort = $c['name']; break; }
              }
            ?>
            <div class="pm-card" data-id="<?= (int) $p['id'] ?>"
                 data-name="<?= e(strtolower($p['name'])) ?>"
                 data-type="<?= e($p['type']) ?>"
                 data-description="<?= e(strtolower($p['description'] ?? '')) ?>"
                 data-status="<?= e($p['status']) ?>"
                 data-start="<?= e($p['start_date'] ?? '') ?>"
                 data-end="<?= e($p['end_date'] ?? '') ?>">
              <div class="pm-card__main">
                <div class="pm-card__top">
                  <div class="pm-card__title-row">
                    <span class="pm-card__type"><?= e(programme_type_label($p['type'])) ?></span>
                    <?= status_badge($p['status'], PROGRAMME_STATUS_LABELS[$p['status']] ?? ucfirst($p['status'])) ?>
                  </div>
                  <h3 class="pm-card__name"><?= e($p['name']) ?></h3>
                  <p class="pm-card__desc"><?= e(mb_strimwidth($p['description'] ?? 'No description provided.', 0, 140, '…')) ?></p>
                </div>
                <div class="pm-card__meta">
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Cohorts</span><span class="pm-card__meta-value"><?= (int) $p['cohort_count'] ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Active Cohort</span><span class="pm-card__meta-value"><?= e($activeCohort ?: '—') ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Capacity</span><span class="pm-card__meta-value"><?= (int) $p['total_capacity'] ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Participants</span><span class="pm-card__meta-value"><?= (int) $participants ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Applications</span><span class="pm-card__meta-value"><?= (int) $applications ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Start</span><span class="pm-card__meta-value"><?= e(!empty($p['start_date']) ? format_date($p['start_date'], 'd M Y') : '—') ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">End</span><span class="pm-card__meta-value"><?= e(!empty($p['end_date']) ? format_date($p['end_date'], 'd M Y') : '—') ?></span></div>
                  <div class="pm-card__meta-item"><span class="pm-card__meta-label">Created</span><span class="pm-card__meta-value"><?= e(format_date($p['created_at'], 'd M Y')) ?></span></div>
                </div>
              </div>
              <div class="pm-card__actions">
                <a href="<?= url('admin/programme_detail.php?id=' . (int) $p['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i> View</a>
                <a href="<?= url('admin/programme_edit.php?id=' . (int) $p['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i> Edit</a>
                <a href="<?= url('admin/programme_detail.php?id=' . (int) $p['id'] . '#cohorts') ?>" class="btn btn--outline btn--sm"><i class="fas fa-layer-group"></i> Manage Cohorts</a>
                <div class="pm-card__more">
                  <button class="btn btn--ghost btn--sm pm-more-btn" aria-label="More actions"><i class="fas fa-ellipsis-v"></i></button>
                  <div class="pm-card__menu">
                    <form method="post" action="<?= url('admin/programme_actions.php') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="duplicate">
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <button type="submit" class="pm-card__menu-item"><i class="fas fa-copy"></i> Duplicate</button>
                    </form>
                    <form method="post" action="<?= url('admin/programme_actions.php') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="archive">
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <button type="submit" class="pm-card__menu-item pm-card__menu-item--danger"><i class="fas fa-archive"></i> Archive</button>
                    </form>
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
  <?= $flashes ?>
</body>
</html>
