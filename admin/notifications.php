<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$adminId = (int) current_user_id();
$user = current_user();
try { AdminNotification::syncPendingEvents(); } catch (Throwable $e) { error_log('[Admin Notifications] sync: ' . $e->getMessage()); }
$status = (string) ($_GET['status'] ?? 'all');
$category = (string) ($_GET['category'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
if (!in_array($status, ['all','unread','read'], true)) { $status = 'all'; }
if ($category !== '' && !isset(AdminNotification::CATEGORIES[$category])) { $category = ''; }
$result = AdminNotification::forAdmin($adminId, ['status' => $status, 'category' => $category, 'q' => $q], $page, 15);
$records = $result['records'];
$unreadCount = (int) $result['unread'];
$totalCount = (int) $result['total'];
$pages = (int) $result['pages'];
$currentPage = (int) $result['page'];
$statusCounts = $result['statusCounts'];
$recent = AdminNotification::recentForAdmin($adminId, 5);
$pending = AdminNotification::pendingTasks();
$nq = static function (array $o) use ($status, $category, $q, $currentPage): string {
    $p = array_merge(['status' => $status, 'category' => $category, 'q' => $q, 'page' => $currentPage], $o);
    if (($p['status'] ?? '') === 'all') unset($p['status']);
    if (($p['category'] ?? '') === '') unset($p['category']);
    if (($p['q'] ?? '') === '') unset($p['q']);
    if ((int) ($p['page'] ?? 1) <= 1) unset($p['page']);
    return http_build_query($p);
};
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications | Investhood IT Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/candidate_notifications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_notifications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard app-module">
<div class="dashboard">
<aside class="sidebar admin-sidebar" id="adminSidebar">
<div class="sidebar__header"><a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a><button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button></div>
<nav class="sidebar__nav">
<div class="sidebar__section-label">Command Centre</div>
<ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li><li><a href="<?= url('admin/notifications.php') ?>" class="sidebar__link active"><i class="fas fa-bell"></i> Notifications</a></li></ul>
<div class="sidebar__section-label">Management</div>
<ul class="sidebar__menu"><li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li><li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li><li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li><li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li><li><a href="<?= url('admin/selection.php') ?>" class="sidebar__link"><i class="fas fa-user-check"></i> Selection &amp; Offers</a></li><li><a href="<?= url('admin/placements.php') ?>" class="sidebar__link"><i class="fas fa-handshake"></i> Placements</a></li></ul>
</nav>
<div class="sidebar__footer"><a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a></div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="dashboard__main">
<header class="dash-header admin-dash-header">
<div class="dash-header__left"><button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button><a href="<?= url('admin/dashboard.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Dashboard</a></div>
<div class="dash-header__right">
<div class="notif-bell-wrap"><button class="dash-header__icon-btn" id="adminNotifBtn" aria-label="Notifications" aria-expanded="false"><i class="fas fa-bell"></i><span class="dash-header__notif-badge<?= $unreadCount === 0 ? ' is-hidden' : '' ?>" id="adminNotifBadge"><?= $unreadCount > 99 ? '99+' : (int) $unreadCount ?></span></button>
<div class="notif-dropdown" id="adminNotifDropdown" aria-hidden="true">
<div class="notif-dropdown__header"><span class="notif-dropdown__title">Notifications</span><span class="notif-dropdown__count" id="adminNotifDropdownCount"><?= (int) $unreadCount ?> unread</span></div>
<div class="notif-dropdown__list" id="adminNotifDropdownList"></div>
<div class="notif-dropdown__footer"><button type="button" class="btn btn--ghost btn--sm" data-notif-mark-all><i class="fas fa-check-double"></i> Mark All Read</button><a href="<?= url('admin/notifications.php') ?>" class="btn btn--primary btn--sm">View All</a></div>
</div></div>
<button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
</div>
</header>
<div class="dash-content">
<?= $flashes ?>
<section class="app-hero"><div class="app-hero__inner"><div><span class="section__badge">Admin Notifications</span><h1 class="app-hero__title">Notifications</h1><p class="app-hero__subtitle">System activity requiring administrative attention.</p></div><div class="app-hero__actions"><?php if ($unreadCount > 0): ?><button type="button" class="btn btn--primary btn--sm" data-notif-mark-all><i class="fas fa-check-double"></i> Mark All Read</button><?php endif; ?></div></div></section>
<section class="app-stats"><div class="app-stats__grid">
<div class="stat-card"><span class="stat-card__number"><?= (int) $statusCounts['all'] ?></span><span class="stat-card__label">Total</span></div>
<div class="stat-card"><span class="stat-card__number" id="adminNotifStatUnread"><?= (int) $statusCounts['unread'] ?></span><span class="stat-card__label">Unread</span></div>
<div class="stat-card"><span class="stat-card__number"><?= (int) $statusCounts['read'] ?></span><span class="stat-card__label">Read</span></div>
</div></section>
<section class="notif-centre" id="notificationsPanel">
<div class="notif-centre__toolbar">
<div class="notif-centre__filters" role="tablist" aria-label="Status filter">
<a class="notif-centre__filter<?= $status === 'all' ? ' active' : '' ?>" href="?<?= e($nq(['status' => 'all', 'page' => 1])) ?>">All (<?= (int) $statusCounts['all'] ?>)</a>
<a class="notif-centre__filter<?= $status === 'unread' ? ' active' : '' ?>" href="?<?= e($nq(['status' => 'unread', 'page' => 1])) ?>">Unread (<?= (int) $statusCounts['unread'] ?>)</a>
<a class="notif-centre__filter<?= $status === 'read' ? ' active' : '' ?>" href="?<?= e($nq(['status' => 'read', 'page' => 1])) ?>">Read (<?= (int) $statusCounts['read'] ?>)</a>
</div>
<div class="notif-centre__tools">
<form method="get" action="<?= url('admin/notifications.php') ?>" class="notif-search">
<?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
<label class="notif-search__field"><i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search..." aria-label="Search"></label>
<label class="notif-search__select"><i class="fas fa-filter"></i><select name="category" onchange="this.form.submit()" aria-label="Category"><option value="">All categories</option><?php foreach (AdminNotification::CATEGORIES as $k => $c): ?><option value="<?= e($k) ?>"<?= $category === $k ? ' selected' : '' ?>><?= e($c['label']) ?></option><?php endforeach; ?></select></label>
<button type="submit" class="btn btn--outline btn--sm">Search</button>
</form>
</div>
</div>
<?php if (empty($records)): ?>
<div class="empty-state"><div class="empty-state__icon"><i class="fas fa-bell-slash"></i></div><h3 class="empty-state__title">No notifications</h3><p class="empty-state__text">Admin alerts will appear here.</p><?php if ($q !== '' || $category !== '' || $status !== 'all'): ?><a href="<?= url('admin/notifications.php') ?>" class="btn btn--outline btn--sm">Clear Filters</a><?php endif; ?></div>
<?php else: ?>
<div class="notif-list" id="adminNotifList">
<?php foreach ($records as $n): ?>
<?php $nc = AdminNotification::normaliseCategory((string) $n['category']); $nu = (int) $n['is_read'] === 0; $np = AdminNotification::normalisePriority((string) ($n['priority'] ?? 'info')); ?>
<?php $nu2 = url(AdminNotification::destinationUrl($n['module'] ?? null, isset($n['record_id']) ? (int) $n['record_id'] : null)); ?>
<div class="notif-item<?= $nu ? ' notif-item--unread' : '' ?>" data-notif-id="<?= (int) $n['id'] ?>" data-notif-url="<?= e($nu2) ?>">
<div class="notif-item__icon notif-item__icon--<?= e($nc) ?>"><i class="fas <?= e(AdminNotification::categoryIcon($nc)) ?>"></i></div>
<div class="notif-item__content">
<div class="notif-item__head"><a class="notif-item__title" href="<?= e($nu2) ?>" data-notif-open><?= e($n['title']) ?></a><span class="notif-item__tag notif-item__tag--<?= e($nc) ?>"><?= e(AdminNotification::categoryLabel($nc)) ?></span><span class="notif-priority notif-priority--<?= e($np) ?>"><?= e($np) ?></span><?php if ($nu): ?><span class="notif-item__unread-dot"></span><?php endif; ?></div>
<div class="notif-item__message"><?= e($n['message']) ?></div>
<div class="notif-item__time"><?= e(time_ago((string) $n['created_at'])) ?></div>
</div>
<div class="notif-item__actions"><?php if ($nu): ?><button type="button" class="notif-item__mark-read" data-notif-mark-read title="Mark as read"><i class="fas fa-check"></i></button><?php endif; ?><button type="button" class="notif-item__delete" data-notif-delete title="Delete"><i class="fas fa-trash-alt"></i></button></div>
</div>
<?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
<nav class="notif-pagination" aria-label="Pages">
<?php if ($currentPage > 1): ?><a class="notif-pagination__link" href="?<?= e($nq(['page' => $currentPage - 1])) ?>">Prev</a><?php endif; ?>
<?php for ($p = 1; $p <= $pages; $p++): ?><?php if ($p === $currentPage): ?><span class="notif-pagination__link is-active"><?= $p ?></span><?php elseif ($p === 1 || $p === $pages || abs($p - $currentPage) <= 2): ?><a class="notif-pagination__link" href="?<?= e($nq(['page' => $p])) ?>"><?= $p ?></a><?php endif; ?><?php endfor; ?>
<?php if ($currentPage < $pages): ?><a class="notif-pagination__link" href="?<?= e($nq(['page' => $currentPage + 1])) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
</section>
</div>
</main>
</div>
<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/admin_notifications.js') ?>"></script>
</body>
</html>



