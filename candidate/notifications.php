<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Notifications
 * ================================================
 * The candidate's unified notification feed: applications,
 * programmes & opportunities, interviews, selection & offers,
 * placements and account/system events.
 *
 * Every notification shown here belongs to the authenticated
 * candidate only (all queries are scoped by users.id). The page
 * supports status/category filters, search, pagination, mark as
 * read, mark all as read, delete and "view details" deep links back
 * to the originating record.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId = (int) current_user_id();
$user        = current_user();

// Lazy, idempotent sync of state-derived events (application deadline
// reminders, new relevant opportunities, offer events) — the same
// pattern the offers module uses. INSERT IGNORE + dedupe keys make
// repeated page loads duplicate-proof.
try {
    CandidateNotification::syncOpportunityEvents($candidateId);
    CandidateNotification::mirrorOfferNotifications($candidateId);
} catch (Throwable $e) {
    error_log('[Candidate Notifications] sync failed: ' . $e->getMessage());
}

// ---- Filters ----
$status   = (string) ($_GET['status'] ?? 'all');
$category = (string) ($_GET['category'] ?? '');
$q        = trim((string) ($_GET['q'] ?? ''));
$page     = max(1, (int) ($_GET['page'] ?? 1));
if (!in_array($status, ['all', 'unread', 'read'], true)) {
    $status = 'all';
}
if ($category !== '' && !isset(CandidateNotification::CATEGORIES[$category])) {
    $category = '';
}

$result      = CandidateNotification::forCandidate(
    $candidateId,
    ['status' => $status, 'category' => $category, 'q' => $q],
    $page,
    15
);
$records     = $result['records'];
$unreadCount = (int) $result['unread'];
$totalCount  = (int) $result['total'];
$pages       = (int) $result['pages'];
$currentPage = (int) $result['page'];
$statusCounts = $result['statusCounts'];

// Recent notifications for the header dropdown.
$recent = CandidateNotification::recentForCandidate($candidateId, 5);

/**
 * Build a filter/pagination query string, dropping defaults.
 */
$notifQuery = static function (array $overrides) use ($status, $category, $q, $currentPage): string {
    $params = array_merge([
        'status'   => $status,
        'category' => $category,
        'q'        => $q,
        'page'     => $currentPage,
    ], $overrides);
    if (($params['status'] ?? '') === 'all') {
        unset($params['status']);
    }
    if (($params['category'] ?? '') === '') {
        unset($params['category']);
    }
    if (($params['q'] ?? '') === '') {
        unset($params['q']);
    }
    if ((int) ($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }
    return http_build_query($params);
};

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="My Notifications - Investhood IT Programme & Scarce Skills Platform">
  <title>Notifications | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/candidate_notifications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page applications-page notifications-page">

  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Main</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('candidate/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Dashboard</a></li>
          <li><a href="<?= url('candidate/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('candidate/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="<?= url('candidate/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li>
          <li><a href="<?= url('candidate/offers.php') ?>" class="sidebar__link"><i class="fas fa-envelope-open-text"></i> Offers</a></li>
          <li><a href="<?= url('candidate/notifications.php') ?>" class="sidebar__link active"><i class="fas fa-bell"></i> Notifications</a></li>
          <li><a href="<?= url('candidate/profile.php') ?>" class="sidebar__link"><i class="fas fa-user"></i> My Profile</a></li>
          <li><a href="<?= url('candidate/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile"></div>
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
          <div class="notif-bell-wrap">
            <button class="dash-header__icon-btn dash-header__notif-btn" id="notifHeaderBtn" aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
              <i class="fas fa-bell"></i>
              <span class="dash-header__notif-badge<?= $unreadCount > 0 ? '' : ' is-hidden' ?>" id="notifHeaderBadge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
            </button>
            <!-- ===== NOTIFICATION DROPDOWN ===== -->
            <div class="notif-dropdown" id="notifDropdown" aria-hidden="true">
              <div class="notif-dropdown__header">
                <span class="notif-dropdown__title">Notifications</span>
                <span class="notif-dropdown__count" id="notifDropdownCount"><?= $unreadCount ?> unread</span>
              </div>
              <div class="notif-dropdown__list" id="notifDropdownList">
                <?php if (empty($recent)): ?>
                  <div class="notif-dropdown__empty"><i class="fas fa-bell-slash"></i> You are all caught up!</div>
                <?php else: foreach ($recent as $n): ?>
                  <?php
                    $nCat    = CandidateNotification::normaliseCategory((string) $n['category']);
                    $nUnread = (int) $n['is_read'] === 0;
                    $nUrl    = url(CandidateNotification::destinationUrl(
                        $n['module'] !== null && $n['module'] !== '' ? (string) $n['module'] : null,
                        isset($n['record_id']) && $n['record_id'] !== null ? (int) $n['record_id'] : null
                    ));
                  ?>
                  <a class="notif-item notif-dropdown__item<?= $nUnread ? ' notif-item--unread' : '' ?>"
                     href="<?= e($nUrl) ?>" data-notif-id="<?= (int) $n['id'] ?>" data-notif-url="<?= e($nUrl) ?>">
                    <div class="notif-item__icon notif-item__icon--<?= e($nCat) ?>"><i class="fas <?= e(CandidateNotification::categoryIcon($nCat)) ?>"></i></div>
                    <div class="notif-item__content">
                      <div class="notif-item__title"><?= e($n['title']) ?></div>
                      <div class="notif-item__message"><?= e($n['message']) ?></div>
                      <div class="notif-item__time"><?= e(time_ago((string) $n['created_at'])) ?></div>
                    </div>
                  </a>
                <?php endforeach; endif; ?>
              </div>
              <div class="notif-dropdown__footer">
                <button type="button" class="btn btn--ghost btn--sm" data-notif-mark-all><i class="fas fa-check-double"></i> Mark All Read</button>
                <a href="<?= url('candidate/notifications.php') ?>" class="btn btn--primary btn--sm">View All Notifications</a>
              </div>
            </div>
          </div>
          <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <?= $flashes ?>

        <!-- ===== PAGE HERO ===== -->
        <section class="opp-hero">
          <div class="opp-hero__inner">
            <h1 class="opp-hero__title">Notifications</h1>
            <p class="opp-hero__subtitle">Stay on top of your applications, interviews, offers, placements and account updates.</p>
          </div>
        </section>

        <!-- ===== STATS ===== -->
        <section class="app-stats">
          <div class="app-stats__grid">
            <div class="stat-card"><span class="stat-card__number"><?= (int) $statusCounts['all'] ?></span><span class="stat-card__label">Total Notifications</span></div>
            <div class="stat-card"><span class="stat-card__number" id="notifStatUnread"><?= (int) $statusCounts['unread'] ?></span><span class="stat-card__label">Unread</span></div>
            <div class="stat-card"><span class="stat-card__number"><?= (int) $statusCounts['read'] ?></span><span class="stat-card__label">Read</span></div>
          </div>
        </section>

        <!-- ===== NOTIFICATIONS ===== -->
        <section class="notif-centre" id="notificationsPanel">
          <div class="notif-centre__toolbar">
            <div class="notif-centre__filters" role="tablist" aria-label="Notification status filter">
              <a class="notif-centre__filter<?= $status === 'all' ? ' active' : '' ?>" href="?<?= e($notifQuery(['status' => 'all', 'page' => 1])) ?>">All <span class="notif-filter-count"><?= (int) $statusCounts['all'] ?></span></a>
              <a class="notif-centre__filter<?= $status === 'unread' ? ' active' : '' ?>" href="?<?= e($notifQuery(['status' => 'unread', 'page' => 1])) ?>">Unread <span class="notif-filter-count"><?= (int) $statusCounts['unread'] ?></span></a>
              <a class="notif-centre__filter<?= $status === 'read' ? ' active' : '' ?>" href="?<?= e($notifQuery(['status' => 'read', 'page' => 1])) ?>">Read <span class="notif-filter-count"><?= (int) $statusCounts['read'] ?></span></a>
            </div>
            <div class="notif-centre__tools">
              <form method="get" action="<?= url('candidate/notifications.php') ?>" class="notif-search">
                <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                <label class="notif-search__field">
                  <i class="fas fa-search"></i>
                  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search notifications..." aria-label="Search notifications">
                </label>
                <label class="notif-search__select">
                  <i class="fas fa-filter"></i>
                  <select name="category" aria-label="Filter by category">
                    <option value="">All categories</option>
                    <?php foreach (CandidateNotification::CATEGORIES as $catKey => $catMeta): ?>
                      <option value="<?= e($catKey) ?>"<?= $category === $catKey ? ' selected' : '' ?>><?= e($catMeta['label']) ?> (<?= (int) ($result['categoryCounts'][$catKey] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <button type="submit" class="btn btn--outline btn--sm"><i class="fas fa-search"></i> Search</button>
              </form>
              <button type="button" class="btn btn--ghost btn--sm" data-notif-mark-all id="markAllReadPage"><i class="fas fa-check-double"></i> Mark All Read</button>
            </div>
          </div>

          <?php if (empty($records)): ?>
            <div class="empty-state">
              <div class="empty-state__icon"><i class="fas fa-bell-slash"></i></div>
              <h3 class="empty-state__title"><?= ($q !== '' || $category !== '' || $status !== 'all') ? 'No notifications match your filters' : 'No notifications yet' ?></h3>
              <p class="empty-state__text"><?= ($q !== '' || $category !== '' || $status !== 'all') ? 'Try adjusting your search or clearing the filters.' : 'Notifications about your applications, interviews, offers and placements will appear here.' ?></p>
              <?php if ($q !== '' || $category !== '' || $status !== 'all'): ?>
                <a href="<?= url('candidate/notifications.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-times"></i> Clear Filters</a>
              <?php else: ?>
                <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-briefcase"></i> Explore Opportunities</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="notif-centre__list" id="notifList">
              <?php foreach ($records as $n): ?>
                <?php
                  $nCat    = CandidateNotification::normaliseCategory((string) $n['category']);
                  $nUnread = (int) $n['is_read'] === 0;
                  $nUrl    = url(CandidateNotification::destinationUrl(
                      $n['module'] !== null && $n['module'] !== '' ? (string) $n['module'] : null,
                      isset($n['record_id']) && $n['record_id'] !== null ? (int) $n['record_id'] : null
                  ));
                ?>
                <div class="notif-item notif-feed__item<?= $nUnread ? ' notif-item--unread' : '' ?>"
                     data-notif-id="<?= (int) $n['id'] ?>"
                     data-category="<?= e($nCat) ?>"
                     data-notif-url="<?= e($nUrl) ?>">
                  <div class="notif-item__icon notif-item__icon--<?= e($nCat) ?>"><i class="fas <?= e(CandidateNotification::categoryIcon($nCat)) ?>"></i></div>
                  <div class="notif-item__content">
                    <div class="notif-item__head">
                      <a class="notif-item__title" href="<?= e($nUrl) ?>" data-notif-open><?= e($n['title']) ?></a>
                      <span class="notif-item__tag notif-item__tag--<?= e($nCat) ?>"><?= e(CandidateNotification::categoryLabel($nCat)) ?></span>
                      <?php if ($nUnread): ?><span class="notif-item__unread-dot" title="Unread"></span><?php endif; ?>
                    </div>
                    <div class="notif-item__message"><?= e($n['message']) ?></div>
                    <div class="notif-item__time"><i class="far fa-clock"></i> <?= e(format_date((string) $n['created_at'], 'd M Y, H:i')) ?> &middot; <?= e(time_ago((string) $n['created_at'])) ?></div>
                  </div>
                  <div class="notif-item__actions">
                    <?php if ($nUnread): ?>
                      <button type="button" class="notif-item__mark-read" data-notif-mark-read title="Mark as read" aria-label="Mark as read"><i class="fas fa-check"></i></button>
                    <?php endif; ?>
                    <button type="button" class="notif-item__delete" data-notif-delete title="Delete notification" aria-label="Delete notification"><i class="fas fa-trash-alt"></i></button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- ===== PAGINATION ===== -->
            <?php if ($pages > 1): ?>
              <nav class="notif-pagination" aria-label="Notification pages">
                <?php if ($currentPage > 1): ?>
                  <a class="notif-pagination__link" href="?<?= e($notifQuery(['page' => $currentPage - 1])) ?>"><i class="fas fa-chevron-left"></i> Prev</a>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                  <?php if ($p === $currentPage): ?>
                    <span class="notif-pagination__link is-active"><?= $p ?></span>
                  <?php elseif ($p === 1 || $p === $pages || abs($p - $currentPage) <= 2): ?>
                    <a class="notif-pagination__link" href="?<?= e($notifQuery(['page' => $p])) ?>"><?= $p ?></a>
                  <?php elseif (abs($p - $currentPage) === 3): ?>
                    <span class="notif-pagination__ellipsis">&hellip;</span>
                  <?php endif; ?>
                <?php endfor; ?>
                <?php if ($currentPage < $pages): ?>
                  <a class="notif-pagination__link" href="?<?= e($notifQuery(['page' => $currentPage + 1])) ?>">Next <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        </section>

      </div><!-- // dash-content -->

      <!-- ===== FOOTER ===== -->
      <footer class="dash-footer">
        <div class="container">
          <div class="dash-footer__inner">
            <p>&copy; 2025 Investhood IT. All rights reserved.</p>
            <div class="dash-footer__links">
              <a href="<?= url('index.php') ?>">Back to Home</a>
            </div>
          </div>
        </div>
      </footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/notifications.js') ?>"></script>
</body>
</html>

