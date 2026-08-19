<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Explore Opportunities
 * ================================================
 * Allows candidates to discover opportunities that match
 * their skills, qualifications, and career goals.
 * Only displays published, open opportunities.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId = (int) current_user_id();
$user = current_user();

// Get filter and search parameters
$search     = trim($_GET['q'] ?? '');
$page       = (int) ($_GET['page'] ?? 1);
$sort       = $_GET['sort'] ?? 'recent';
$type       = $_GET['type'] ?? '';
$programme  = $_GET['programme'] ?? '';
$cohort     = $_GET['cohort'] ?? '';
$province   = $_GET['province'] ?? '';
$city       = $_GET['city'] ?? '';
$arrangement = $_GET['work_arrangement'] ?? '';
$qualification = $_GET['qualification'] ?? '';

// Build filter array for controller
$filters = [
    'search'           => $search,
    'type'             => $type,
    'programme'        => $programme,
    'cohort'           => $cohort,
    'province'         => $province,
    'city'             => $city,
    'work_arrangement' => $arrangement,
    'qualification'    => $qualification,
    'sort'             => $sort,
];

// Search and get paginated results
$results = CandidateOpportunitiesController::searchOpportunities($filters, $page);
$opportunities = $results['opportunities'];
$total = $results['total'];
$totalPages = $results['pages'];
$currentPage = $results['current_page'];

// Get filter options for the UI
$filterOptions = CandidateOpportunitiesController::getFilterOptions();

// Check which opportunities are saved by this candidate
$savedOpportunityIds = [];
$savedOpps = SavedOpportunity::forCandidate($userId);
foreach ($savedOpps as $saved) {
    $savedOpportunityIds[$saved['opportunity_id']] = true;
}

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Explore Opportunities - Investhood IT Programme & Scarce Skills Platform">
  <title>Explore Opportunities | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/opportunities.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page opportunities-page">

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
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== PAGE HERO ===== -->
        <section class="opp-hero">
          <div class="opp-hero__inner">
            <h1 class="opp-hero__title">Explore Opportunities</h1>
            <p class="opp-hero__subtitle">Discover opportunities that match your skills, qualifications, and career goals.</p>
          </div>
        </section>

        <!-- ===== FLASH MESSAGES ===== -->
        <?= $flashes ?>

        <!-- ===== OPPORTUNITIES SECTION ===== -->
        <section class="opp-section">
          <div class="opp-container">

            <!-- ===== SEARCH & CONTROLS ===== -->
            <div class="opp-controls">
              <form class="opp-search-form" method="GET" action="<?= url('candidate/opportunities.php') ?>">
                <div class="opp-search-wrapper">
                  <i class="fas fa-search"></i>
                  <input type="text" name="q" class="opp-search-input" placeholder="Search opportunities..." value="<?= e($search) ?>" aria-label="Search opportunities">
                  <button type="submit" class="opp-search-btn" aria-label="Search"><i class="fas fa-arrow-right"></i></button>
                </div>

                <!-- Hidden filter fields (populated by JavaScript) -->
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <input type="hidden" name="type" value="<?= e($type) ?>">
                <input type="hidden" name="programme" value="<?= e($programme) ?>">
                <input type="hidden" name="cohort" value="<?= e($cohort) ?>">
                <input type="hidden" name="province" value="<?= e($province) ?>">
                <input type="hidden" name="city" value="<?= e($city) ?>">
                <input type="hidden" name="work_arrangement" value="<?= e($arrangement) ?>">
                <input type="hidden" name="qualification" value="<?= e($qualification) ?>">
                <input type="hidden" name="page" value="1">
              </form>

              <!-- Sort & Filter Toggle -->
              <div class="opp-controls__right">
                <div class="opp-sort">
                  <label for="sortSelect" class="opp-sort__label">Sort by:</label>
                  <select id="sortSelect" class="opp-sort__select">
                    <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most Recent</option>
                    <option value="closing_soon" <?= $sort === 'closing_soon' ? 'selected' : '' ?>>Closing Soon</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Opportunity Name</option>
                    <option value="programme" <?= $sort === 'programme' ? 'selected' : '' ?>>Programme</option>
                    <option value="location" <?= $sort === 'location' ? 'selected' : '' ?>>Location</option>
                  </select>
                </div>
                <button class="btn btn--secondary btn--sm" id="toggleFilters" aria-label="Toggle filters"><i class="fas fa-sliders-h"></i> Filters</button>
              </div>
            </div>

            <div class="opp-content">

              <!-- ===== FILTERS PANEL ===== -->
              <aside class="opp-filters" id="filtersPanel">
                <div class="opp-filters__header">
                  <h3 class="opp-filters__title">Advanced Filters</h3>
                  <button class="opp-filters__close" id="closeFilters" aria-label="Close filters"><i class="fas fa-times"></i></button>
                </div>

                <form class="opp-filters__form" id="filtersForm">

                  <!-- Opportunity Type -->
                  <div class="opp-filter-group">
                    <h4 class="opp-filter-group__title">Opportunity Type</h4>
                    <div class="opp-filter-group__items">
                      <?php foreach ($filterOptions['opportunity_types'] as $typeKey => $typeLabel): ?>
                        <label class="opp-filter-checkbox">
                          <input type="checkbox" name="type" value="<?= e($typeKey) ?>" <?= (!empty($type) && (is_array($type) ? in_array($typeKey, $type) : $type === $typeKey)) ? 'checked' : '' ?>>
                          <span><?= e($typeLabel) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <!-- Programme -->
                  <?php if (!empty($filterOptions['programmes'])): ?>
                  <div class="opp-filter-group">
                    <h4 class="opp-filter-group__title">Programme</h4>
                    <div class="opp-filter-group__items">
                      <?php foreach ($filterOptions['programmes'] as $prog): ?>
                        <label class="opp-filter-checkbox">
                          <input type="checkbox" name="programme" value="<?= e($prog['id']) ?>" <?= (!empty($programme) && (is_array($programme) ? in_array($prog['id'], $programme) : $programme == $prog['id'])) ? 'checked' : '' ?>>
                          <span><?= e($prog['name']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                  <!-- Province -->
                  <?php if (!empty($filterOptions['provinces'])): ?>
                  <div class="opp-filter-group">
                    <h4 class="opp-filter-group__title">Province</h4>
                    <div class="opp-filter-group__items">
                      <?php foreach ($filterOptions['provinces'] as $prov): ?>
                        <label class="opp-filter-checkbox">
                          <input type="checkbox" name="province" value="<?= e($prov['province']) ?>" <?= (!empty($province) && (is_array($province) ? in_array($prov['province'], $province) : $province === $prov['province'])) ? 'checked' : '' ?>>
                          <span><?= e($prov['province']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                  <!-- City -->
                  <?php if (!empty($filterOptions['cities'])): ?>
                  <div class="opp-filter-group">
                    <h4 class="opp-filter-group__title">City</h4>
                    <div class="opp-filter-group__items">
                      <?php foreach ($filterOptions['cities'] as $c): ?>
                        <label class="opp-filter-checkbox">
                          <input type="checkbox" name="city" value="<?= e($c['city']) ?>" <?= (!empty($city) && (is_array($city) ? in_array($c['city'], $city) : $city === $c['city'])) ? 'checked' : '' ?>>
                          <span><?= e($c['city']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                  <!-- Work Arrangement -->
                  <div class="opp-filter-group">
                    <h4 class="opp-filter-group__title">Work Arrangement</h4>
                    <div class="opp-filter-group__items">
                      <?php foreach ($filterOptions['work_arrangements'] as $arrangKey => $arrangLabel): ?>
                        <label class="opp-filter-checkbox">
                          <input type="checkbox" name="work_arrangement" value="<?= e($arrangKey) ?>" <?= (!empty($arrangement) && (is_array($arrangement) ? in_array($arrangKey, $arrangement) : $arrangement === $arrangKey)) ? 'checked' : '' ?>>
                          <span><?= e($arrangLabel) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <!-- Buttons -->
                  <div class="opp-filter-group__actions">
                    <button type="submit" class="btn btn--primary btn--full">Apply Filters</button>
                    <button type="reset" class="btn btn--secondary btn--full">Clear Filters</button>
                  </div>

                </form>
              </aside>

              <!-- ===== OPPORTUNITIES GRID ===== -->
              <div class="opp-main">

                <!-- Results header -->
                <div class="opp-results-header">
                  <p class="opp-results-count">
                    <?php if ($total === 0): ?>
                      No opportunities found
                    <?php else: ?>
                      Showing <?= ((($currentPage - 1) * $results['per_page']) + 1) ?> – <?= min($currentPage * $results['per_page'], $total) ?> of <?= $total ?> opportunities
                    <?php endif; ?>
                  </p>
                </div>

                <!-- Empty state -->
                <?php if (empty($opportunities)): ?>
                  <div class="opp-empty-state">
                    <div class="opp-empty-state__icon">
                      <i class="fas fa-search"></i>
                    </div>
                    <h3 class="opp-empty-state__title">No opportunities found</h3>
                    <p class="opp-empty-state__text">
                      <?php if (!empty($search) || !empty($type) || !empty($province)): ?>
                        Try adjusting your search or filters to find opportunities that match your interests.
                      <?php else: ?>
                        Check back later for new opportunities to explore.
                      <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || !empty($type) || !empty($province)): ?>
                      <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--primary">View All Opportunities</a>
                    <?php endif; ?>
                  </div>
                <?php else: ?>

                  <!-- Opportunities Grid -->
                  <div class="opp-grid">
                    <?php foreach ($opportunities as $opp): ?>
                      <?php
                      $isSaved = isset($savedOpportunityIds[$opp['id']]);
                      $today = date('Y-m-d');
                      $isOpen = !empty($opp['application_open_date']) && $opp['application_open_date'] <= $today;
                      $isClosed = !empty($opp['application_close_date']) && $opp['application_close_date'] < $today;
                      $daysUntilClose = null;
                      if (!empty($opp['application_close_date']) && !$isClosed) {
                          $daysUntilClose = (int) ((strtotime($opp['application_close_date']) - time()) / 86400);
                      }
                      ?>
                      <article class="opp-card">
                        <div class="opp-card__header">
                          <div class="opp-card__meta">
                            <span class="opp-badge opp-badge--type"><?= e(CandidateOpportunitiesController::OPPORTUNITY_TYPES_DISPLAY[$opp['type']] ?? $opp['type']) ?></span>
                            <?php if ($isClosed): ?>
                              <span class="opp-badge opp-badge--closed">Closed</span>
                            <?php elseif ($daysUntilClose !== null && $daysUntilClose <= 7): ?>
                              <span class="opp-badge opp-badge--urgent">Closing Soon</span>
                            <?php endif; ?>
                          </div>
                          <button class="opp-card__save-btn" data-opp-id="<?= (int) $opp['id'] ?>" data-saved="<?= $isSaved ? '1' : '0' ?>" aria-label="<?= $isSaved ? 'Remove from saved' : 'Save opportunity' ?>">
                            <i class="fas fa-bookmark"></i>
                          </button>
                        </div>

                        <div class="opp-card__content">
                          <h3 class="opp-card__title">
                            <a href="<?= url('candidate/opportunity_detail.php?id=' . (int)$opp['id']) ?>"><?= e($opp['title']) ?></a>
                          </h3>

                          <div class="opp-card__programme">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?= e($opp['programme_name']) ?><?= !empty($opp['cohort_name']) ? ' • ' . e($opp['cohort_name']) : '' ?></span>
                          </div>

                          <?php if (!empty($opp['organisation'])): ?>
                            <div class="opp-card__org">
                              <i class="fas fa-building"></i>
                              <span><?= e($opp['organisation']) ?></span>
                            </div>
                          <?php endif; ?>

                          <p class="opp-card__description"><?= e(substr($opp['short_description'] ?? '', 0, 120) . (strlen($opp['short_description'] ?? '') > 120 ? '...' : '')) ?></p>

                          <div class="opp-card__details">
                            <?php if (!empty($opp['city']) || !empty($opp['province'])): ?>
                              <div class="opp-detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= e($opp['city'] ?? $opp['province'] ?? 'Location TBD') ?></span>
                              </div>
                            <?php endif; ?>

                            <?php if (!empty($opp['work_arrangement'])): ?>
                              <div class="opp-detail">
                                <i class="fas fa-briefcase"></i>
                                <span><?= e(CandidateOpportunitiesController::WORK_ARRANGEMENTS_DISPLAY[$opp['work_arrangement']] ?? $opp['work_arrangement']) ?></span>
                              </div>
                            <?php endif; ?>

                            <?php if (!empty($opp['available_positions'])): ?>
                              <div class="opp-detail">
                                <i class="fas fa-users"></i>
                                <span><?= (int)$opp['available_positions'] ?> position<?= (int)$opp['available_positions'] !== 1 ? 's' : '' ?></span>
                              </div>
                            <?php endif; ?>
                          </div>

                          <!-- Deadline -->
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
                          <?php if (!$isClosed): ?>
                            <a href="<?= url('candidate/opportunity_detail.php?id=' . (int)$opp['id']) ?>" class="btn btn--primary btn--sm btn--full">View Opportunity</a>
                          <?php else: ?>
                            <button class="btn btn--disabled btn--sm btn--full" disabled>Applications Closed</button>
                          <?php endif; ?>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  </div>

                  <!-- Pagination -->
                  <?php if ($totalPages > 1): ?>
                    <div class="opp-pagination">
                      <?php if ($currentPage > 1): ?>
                        <a href="<?= url('candidate/opportunities.php') . '?' . http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="opp-pagination__btn"><i class="fas fa-chevron-left"></i> First</a>
                        <a href="<?= url('candidate/opportunities.php') . '?' . http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>" class="opp-pagination__btn"><i class="fas fa-chevron-left"></i> Previous</a>
                      <?php endif; ?>

                      <div class="opp-pagination__pages">
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);

                        if ($startPage > 1) {
                            echo '<a href="' . url('candidate/opportunities.php') . '?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="opp-pagination__page">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="opp-pagination__dots">...</span>';
                            }
                        }

                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i === $currentPage) {
                                echo '<span class="opp-pagination__page opp-pagination__page--active">' . $i . '</span>';
                            } else {
                                echo '<a href="' . url('candidate/opportunities.php') . '?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="opp-pagination__page">' . $i . '</a>';
                            }
                        }

                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="opp-pagination__dots">...</span>';
                            }
                            echo '<a href="' . url('candidate/opportunities.php') . '?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '" class="opp-pagination__page">' . $totalPages . '</a>';
                        }
                        ?>
                      </div>

                      <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= url('candidate/opportunities.php') . '?' . http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>" class="opp-pagination__btn">Next <i class="fas fa-chevron-right"></i></a>
                        <a href="<?= url('candidate/opportunities.php') . '?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" class="opp-pagination__btn">Last <i class="fas fa-chevron-right"></i></a>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>

                <?php endif; ?>

              </div>

            </div>

          </div>
        </section>

      </div>
    </main>

  </div>

  <!-- Scripts -->
  <script src="<?= url('js/opportunities.js') ?>"></script>

</body>
</html>
