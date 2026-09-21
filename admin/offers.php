<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Offer Management (Stage 11)
 * ================================================
 * Lists all offers for selected candidates with their status,
 * dates and actions. Accessible only by administrators.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user = current_user();

// ---- Search & filter state (server-side filtering) ----
$search       = trim($_GET['q'] ?? '');
$fStatus      = $_GET['status'] ?? '';
$fProgramme   = $_GET['programme'] ?? '';
$fCohort      = $_GET['cohort'] ?? '';
$fOpportunity = $_GET['opportunity'] ?? '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 20;

// Fetch offers with pagination
$result     = Selection::offersList(
    [
        'search'         => $search,
        'status'         => $fStatus,
        'programme_id'   => $fProgramme,
        'cohort_id'      => $fCohort,
        'opportunity_id' => $fOpportunity,
    ],
    $page,
    $perPage
);
$offers     = $result['records'];
$totalCount = $result['total'];
$totalPages = $result['pages'];

$stats         = Selection::dashboardStats();
$offerCounts   = Selection::offerCounts();
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
  <meta name="description" content="Offer Management - Investhood IT Administrator">
  <title>Offer Management | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_selection.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard app-module sel-module">

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
          <li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li>
          <li><a href="<?= url('admin/selection.php') ?>" class="sidebar__link active"><i class="fas fa-user-check"></i> Selection &amp; Offers</a></li>
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

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard__main">

      <!-- ===== HEADER ===== -->
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <a href="<?= url('admin/selection.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-user-check"></i> Selection Management</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <!-- ===== CONTENT ===== -->
      <div class="dash-content">
        <?= $flashes ?>

        <!-- ===== HERO ===== -->
        <section class="app-hero">
          <div class="app-hero__inner">
            <div>
              <span class="section__badge">Selection &amp; Offers</span>
              <h1 class="app-hero__title">Offer Management</h1>
              <p class="app-hero__subtitle">
                Create, issue and track offers for selected candidates.
                Offer status is managed separately from the application status.
              </p>
            </div>
            <div class="app-hero__actions">
              <a href="<?= url('admin/selection.php?decision=selected') ?>" class="btn btn--ghost btn--sm" style="background:rgba(255,255,255,0.15);border-color:rgba(255,255,255,0.35);color:#fff;">
                <i class="fas fa-user-check"></i> Selected Candidates
              </a>
            </div>
          </div>
        </section>

        <!-- ===== STATS ROW ===== -->
        <div class="app-stats">
          <div class="app-stat app-stat--draft">
            <div class="app-stat__icon"><i class="fas fa-file"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($offerCounts['draft'] ?? 0)) ?></span>
              <span class="app-stat__label">Pending (Draft) Offers</span>
            </div>
          </div>
          <div class="app-stat app-stat--submitted">
            <div class="app-stat__icon"><i class="fas fa-paper-plane"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($offerCounts['issued'] ?? 0)) ?></span>
              <span class="app-stat__label">Offers Issued</span>
            </div>
          </div>
          <div class="app-stat app-stat--selected">
            <div class="app-stat__icon"><i class="fas fa-check-circle"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($offerCounts['accepted'] ?? 0)) ?></span>
              <span class="app-stat__label">Offers Accepted</span>
            </div>
          </div>
          <div class="app-stat app-stat--rejected">
            <div class="app-stat__icon"><i class="fas fa-times-circle"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($offerCounts['declined'] ?? 0)) ?></span>
              <span class="app-stat__label">Offers Declined</span>
            </div>
          </div>
          <div class="app-stat app-stat--review">
            <div class="app-stat__icon"><i class="fas fa-hourglass-end"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($offerCounts['expired'] ?? 0)) ?></span>
              <span class="app-stat__label">Offers Expired</span>
            </div>
          </div>
          <div class="app-stat app-stat--interview">
            <div class="app-stat__icon"><i class="fas fa-ban"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($offerCounts['withdrawn'] ?? 0)) ?></span>
              <span class="app-stat__label">Offers Withdrawn</span>
            </div>
          </div>
        </div>

        <!-- ===== FILTERS ===== -->
        <form class="app-filters" data-auto-filters data-base="<?= e(url('admin/offers.php')) ?>">
          <div class="app-filters__search">
            <i class="fas fa-search"></i>
            <input type="text" class="app-filters__input" data-filter="q" placeholder="Search by offer title, position, reference or candidate..." value="<?= e($search) ?>">
          </div>
          <select class="app-filters__select" data-filter="status">
            <option value="">All Offer Statuses</option>
            <?php foreach (Selection::OFFER_STATUS_LABELS as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $fStatus === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="app-filters__select" data-filter="programme">
            <option value="">All Programmes</option>
            <?php foreach ($programmes as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= $fProgramme === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="app-filters__select" data-filter="cohort">
            <option value="">All Cohorts</option>
            <?php foreach ($cohorts as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $fCohort === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="app-filters__select" data-filter="opportunity">
            <option value="">All Opportunities</option>
            <?php foreach ($opportunities as $o): ?>
              <option value="<?= (int) $o['id'] ?>" <?= $fOpportunity === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn--ghost btn--sm" data-clear-filters><i class="fas fa-times"></i> Clear</button>
        </form>

        <!-- ===== RESULTS COUNT ===== -->
        <div class="app-results-count">
          Showing <?= $totalCount > 0 ? ((($result['page'] - 1) * $perPage) + 1) : 0 ?>–<?= min($result['page'] * $perPage, $totalCount) ?> of <?= $totalCount ?> offer<?= $totalCount !== 1 ? 's' : '' ?>
        </div>

        <!-- ===== OFFERS TABLE ===== -->
        <?php if (empty($offers)): ?>
          <div class="app-empty">
            <div class="app-empty__icon"><i class="fas fa-file-signature"></i></div>
            <h3 class="app-empty__title">No offers found</h3>
            <p class="app-empty__text">
              Offers appear here once an administrator records a <strong>Selected</strong> decision
              for a candidate and creates an offer. Try adjusting your filters.
            </p>
          </div>
        <?php else: ?>
          <div class="app-table-container">
            <table class="app-table">
              <thead>
                <tr>
                  <th>Candidate</th>
                  <th>Reference</th>
                  <th>Opportunity</th>
                  <th>Programme / Cohort</th>
                  <th>Position</th>
                  <th>Offer Status</th>
                  <th>Expiry</th>
                  <th>Created</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($offers as $off): ?>
                  <?php $offerTone = Selection::offerStatusBadgeTone($off['status']); ?>
                  <tr>
                    <td class="app-candidate" data-label="Candidate">
                      <div class="app-candidate__name"><?= e($off['candidate_name'] ?? '—') ?></div>
                      <div class="app-candidate__email"><?= e($off['candidate_email'] ?? '') ?></div>
                    </td>
                    <td data-label="Reference">
                      <a href="<?= url('admin/application.php?id=' . (int) $off['application_id']) ?>" class="app-candidate__name">
                        <?= e($off['application_reference'] ?? '—') ?>
                      </a>
                    </td>
                    <td class="app-opportunity" data-label="Opportunity">
                      <div class="app-opportunity__title"><?= e($off['opportunity_title'] ?? '—') ?></div>
                    </td>
                    <td class="app-opportunity" data-label="Programme / Cohort">
                      <div class="app-opportunity__title"><?= e($off['programme_name'] ?? '—') ?></div>
                      <div class="app-opportunity__programme"><?= e($off['cohort_name'] ?? '—') ?></div>
                    </td>
                    <td class="app-opportunity" data-label="Position">
                      <div class="app-opportunity__title"><?= e($off['position'] ?? '—') ?></div>
                      <div class="app-opportunity__programme"><?= e($off['title'] ?? '') ?></div>
                    </td>
                    <td data-label="Offer Status">
                      <span class="sel-decision-badge sel-decision-badge--<?= e($offerTone) ?>">
                        <?= e(Selection::offerStatusLabel($off['status'])) ?>
                      </span>
                      <div class="app-opportunity__programme">
                        Application: <?= e(Application::label($off['application_status'] ?? null)) ?>
                      </div>
                    </td>
                    <td class="app-date" data-label="Expiry"><?= e(format_date($off['expiry_date'], 'd M Y')) ?></td>
                    <td class="app-date" data-label="Created"><?= e(format_date($off['created_at'], 'd M Y H:i')) ?></td>
                    <td class="app-actions" data-label="Actions">
                      <a href="<?= url('admin/offer.php?id=' . (int) $off['id']) ?>" class="btn btn--primary btn--sm" title="View Offer">
                        <i class="fas fa-file-contract"></i> Offer
                      </a>
                      <?php if (($off['status'] ?? '') === 'draft'): ?>
                        <a href="<?= url('admin/offer_form.php?id=' . (int) $off['id']) ?>" class="btn btn--ghost btn--sm" title="Edit Draft Offer">
                          <i class="fas fa-edit"></i> Edit
                        </a>
                      <?php endif; ?>
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
              $currentPage = (int) $result['page'];
              $queryParams = $_GET;
              unset($queryParams['page']);
              $baseQuery = http_build_query($queryParams);
              $baseUrl   = url('admin/offers.php') . ($baseQuery ? '?' . $baseQuery . '&' : '?');
              ?>
              <?php if ($currentPage > 1): ?>
                <a href="<?= $baseUrl ?>page=<?= $currentPage - 1 ?>" class="app-pagination__link"><i class="fas fa-chevron-left"></i></a>
              <?php endif; ?>
              <?php
              $startPage = max(1, $currentPage - 2);
              $endPage   = min($totalPages, $currentPage + 2);
              if ($startPage > 1): ?>
                <a href="<?= $baseUrl ?>page=1" class="app-pagination__link">1</a>
                <?php if ($startPage > 2): ?><span class="app-pagination__ellipsis">…</span><?php endif; ?>
              <?php endif; ?>
              <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i === $currentPage): ?>
                  <span class="app-pagination__current"><?= $i ?></span>
                <?php else: ?>
                  <a href="<?= $baseUrl ?>page=<?= $i ?>" class="app-pagination__link"><?= $i ?></a>
                <?php endif; ?>
              <?php endfor; ?>
              <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?><span class="app-pagination__ellipsis">…</span><?php endif; ?>
                <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="app-pagination__link"><?= $totalPages ?></a>
              <?php endif; ?>
              <?php if ($currentPage < $totalPages): ?>
                <a href="<?= $baseUrl ?>page=<?= $currentPage + 1 ?>" class="app-pagination__link"><i class="fas fa-chevron-right"></i></a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    </main>
  </div>

  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_selection.js') ?>"></script>
</body>
</html>
