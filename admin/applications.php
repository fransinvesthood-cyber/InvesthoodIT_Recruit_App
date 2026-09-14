<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Applications Page (Stage 9)
 * ================================================
 * Lists all applications with search, filters, pagination
 * and actions (view/manage). Accessible only by administrators.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user = current_user();

// ---- Search & filter state ----
$search       = trim($_GET['q'] ?? '');
$fStatus      = $_GET['status'] ?? '';
$fProgramme   = $_GET['programme'] ?? '';
$fCohort      = $_GET['cohort'] ?? '';
$fOpportunity = $_GET['opportunity'] ?? '';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 20;

// Build filters array
$filters = [
    'search'       => $search,
    'status'       => $fStatus,
    'programme_id' => $fProgramme,
    'cohort_id'    => $fCohort,
    'opportunity_id' => $fOpportunity,
    'date_from'    => $dateFrom,
    'date_to'      => $dateTo,
];

// Fetch applications with pagination
$result        = Application::adminList($filters, $page, $perPage);
$applications  = $result['records'];
$totalCount    = $result['total'];
$totalPages    = $result['pages'];

// Fetch status counts for stats
$statusCounts = Application::adminCountByStatus();

// Fetch filter options
$programmes    = Programme::all();
$cohorts       = Cohort::activeAll();
$opportunities = Opportunity::all();

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Applications - Investhood IT Administrator">
  <title>Applications | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard app-module">

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
          <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link active"><i class="fas fa-file-alt"></i> Applications</a></li>
        </ul>
        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-history"></i> Audit Log</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=40" alt="Admin">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span>
            <span class="sidebar__user-role">Administrator</span>
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
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
          </button>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <!-- ===== MAIN CONTENT ===== -->
      <div class="dash-content">

      <!-- ===== PAGE HERO ===== -->
      <section class="app-hero">
        <div class="app-hero__inner">
          <div>
            <span class="section__badge">Application Management</span>
            <h1 class="app-hero__title">Applications</h1>
            <p class="app-hero__subtitle">View and manage all candidate applications across programmes and opportunities.</p>
          </div>
        </div>
      </section>

      <?= $flashes ?>

      <!-- ===== STATS ===== -->
      <section class="app-stats">
        <div class="app-stat app-stat--total">
          <div class="app-stat__icon"><i class="fas fa-file-alt"></i></div>
          <div>
            <span class="app-stat__value"><?= (int) $statusCounts['total'] ?></span>
            <span class="app-stat__label">Total Applications</span>
          </div>
        </div>
        <div class="app-stat app-stat--submitted">
          <div class="app-stat__icon"><i class="fas fa-paper-plane"></i></div>
          <div>
            <span class="app-stat__value"><?= (int) $statusCounts['submitted'] ?></span>
            <span class="app-stat__label">Submitted</span>
          </div>
        </div>
        <div class="app-stat app-stat--review">
          <div class="app-stat__icon"><i class="fas fa-search"></i></div>
          <div>
            <span class="app-stat__value"><?= (int) ($statusCounts['under_review'] + $statusCounts['shortlisted'] + $statusCounts['assessment']) ?></span>
            <span class="app-stat__label">Under Review</span>
          </div>
        </div>
        <div class="app-stat app-stat--interview">
          <div class="app-stat__icon"><i class="fas fa-calendar-check"></i></div>
          <div>
            <span class="app-stat__value"><?= (int) ($statusCounts['interview_scheduled'] + $statusCounts['interview_completed']) ?></span>
            <span class="app-stat__label">Interview</span>
          </div>
        </div>
        <div class="app-stat app-stat--selected">
          <div class="app-stat__icon"><i class="fas fa-check-circle"></i></div>
          <div>
            <span class="app-stat__value"><?= (int) ($statusCounts['selected'] + $statusCounts['offer_sent'] + $statusCounts['offer_accepted']) ?></span>
            <span class="app-stat__label">Selected</span>
          </div>
        </div>
        <div class="app-stat app-stat--rejected">
          <div class="app-stat__icon"><i class="fas fa-times-circle"></i></div>
          <div>
            <span class="app-stat__value"><?= (int) ($statusCounts['rejected'] + $statusCounts['offer_declined']) ?></span>
            <span class="app-stat__label">Rejected</span>
          </div>
        </div>
      </section>

      <!-- ===== FILTERS ===== -->
      <section class="app-filters">
        <div class="app-filters__search">
          <i class="fas fa-search"></i>
          <input type="text" id="appSearchInput" class="app-filters__input" placeholder="Search by reference, candidate name, email, or opportunity..." value="<?= e($search) ?>">
        </div>
        <select id="appFilterStatus" class="app-filters__select">
          <option value="">All Statuses</option>
          <?php foreach (Application::STATUS_LABELS as $slug => $label): ?>
            <option value="<?= e($slug) ?>" <?= $fStatus === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="appFilterProgramme" class="app-filters__select">
          <option value="">All Programmes</option>
          <?php foreach ($programmes as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= $fProgramme === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="appFilterCohort" class="app-filters__select">
          <option value="">All Cohorts</option>
          <?php foreach ($cohorts as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $fCohort === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="appFilterOpportunity" class="app-filters__select">
          <option value="">All Opportunities</option>
          <?php foreach ($opportunities as $o): ?>
            <option value="<?= (int) $o['id'] ?>" <?= $fOpportunity === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" id="appFilterDateFrom" class="app-filters__date" value="<?= e($dateFrom) ?>" title="From date">
        <input type="date" id="appFilterDateTo" class="app-filters__date" value="<?= e($dateTo) ?>" title="To date">
        <button type="button" class="btn btn--ghost btn--sm" id="appClearFilters"><i class="fas fa-times"></i> Clear</button>
      </section>

      <!-- ===== RESULTS COUNT ===== -->
      <div class="app-results-count">
        Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $totalCount) ?> of <?= $totalCount ?> application<?= $totalCount !== 1 ? 's' : '' ?>
      </div>

      <!-- ===== APPLICATIONS TABLE ===== -->
      <?php if (empty($applications)): ?>
        <div class="app-empty">
          <div class="app-empty__icon"><i class="fas fa-file-alt"></i></div>
          <h3 class="app-empty__title">No applications found</h3>
          <p class="app-empty__text">No applications match your current filters. Try adjusting your search criteria.</p>
        </div>
      <?php else: ?>
        <div class="app-table-container">
          <table class="app-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Candidate</th>
                <th>Opportunity</th>
                <th>Programme / Cohort</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Last Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($applications as $app): ?>
                <?php
                $badgeTone   = Application::badgeTone($app['status']);
                $statusLabel = Application::label($app['status']);
                ?>
                <tr>
                  <td>
                    <a href="<?= url('admin/application.php?id=' . (int) $app['id']) ?>" class="app-ref-link">
                      <?= e($app['application_reference']) ?>
                    </a>
                  </td>
                  <td class="app-candidate">
                    <div class="app-candidate__name"><?= e($app['candidate_name']) ?></div>
                    <div class="app-candidate__email"><?= e($app['candidate_email']) ?></div>
                  </td>
                  <td class="app-opportunity">
                    <div class="app-opportunity__title"><?= e($app['opportunity_title']) ?></div>
                  </td>
                  <td class="app-opportunity">
                    <div class="app-opportunity__title"><?= e($app['programme_name']) ?></div>
                    <div class="app-opportunity__programme"><?= e($app['cohort_name'] ?? '—') ?></div>
                  </td>
                  <td>
                    <span class="app-status-badge app-status-badge--<?= e($badgeTone) ?>"><?= e($statusLabel) ?></span>
                  </td>
                  <td class="app-date">
                    <?= !empty($app['submitted_at']) ? e(format_date($app['submitted_at'], 'd M Y')) : '<span class="app-status-badge app-status-badge--muted">Draft</span>' ?>
                  </td>
                  <td class="app-date"><?= e(format_date($app['updated_at'], 'd M Y H:i')) ?></td>
                  <td class="app-actions">
                    <a href="<?= url('admin/application.php?id=' . (int) $app['id']) ?>" class="btn btn--ghost btn--sm" title="View Details">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <a href="<?= url('admin/application.php?id=' . (int) $app['id']) ?>&action=manage" class="btn btn--primary btn--sm" title="Manage Application">
                      <i class="fas fa-cog"></i> Manage
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- ===== PAGINATION ===== -->
        <?php if ($totalPages > 1): ?>
          <nav class="app-pagination">
            <?php
            $queryParams = $_GET;
            unset($queryParams['page']);
            $baseQuery = http_build_query($queryParams);
            $baseUrl = url('admin/applications.php') . ($baseQuery ? '?' . $baseQuery . '&' : '?');
            ?>

            <?php if ($page > 1): ?>
              <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="app-pagination__link"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);

            if ($startPage > 1): ?>
              <a href="<?= $baseUrl ?>page=1" class="app-pagination__link">1</a>
              <?php if ($startPage > 2): ?>
                <span class="app-pagination__ellipsis">…</span>
              <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
              <?php if ($i === $page): ?>
                <span class="app-pagination__current"><?= $i ?></span>
              <?php else: ?>
                <a href="<?= $baseUrl ?>page=<?= $i ?>" class="app-pagination__link"><?= $i ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
              <?php if ($endPage < $totalPages - 1): ?>
                <span class="app-pagination__ellipsis">…</span>
              <?php endif; ?>
              <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="app-pagination__link"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
              <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="app-pagination__link"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>

      </div>
    </main>
  </div>

  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_applications.js') ?>"></script>
</body>
</html>