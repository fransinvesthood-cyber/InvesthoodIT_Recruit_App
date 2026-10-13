<?php
/**
 * INVESTHOOD IT - Admin System Settings
 * Admin-only page for platform configuration.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$user = current_user();
$grouped = SystemSettings::all();
$flat = [];
foreach ($grouped as $cat => $rows) { foreach ($rows as $k => $v) { $flat[$k] = $v; } }
$errors = $_SESSION['settings_errors'] ?? [];
$old = $_SESSION['settings_old'] ?? [];
unset($_SESSION['settings_errors'], $_SESSION['settings_old']);
function sVal($key, $flat, $old) {
    if (array_key_exists($key, $old)) return $old[$key];
    return $flat[$key] ?? '';
}
function sChecked($key, $flat, $old) {
    $v = array_key_exists($key, $old) ? $old[$key] : ($flat[$key] ?? '0');
    return in_array((string)$v, ['1','on','true'], true) ? 'checked' : '';
}
$flashes = render_flashes();
$tzList = timezone_identifiers_list();
$logoUrl = ($flat['logo_path'] ?? '') !== '' ? url($flat['logo_path']) : '';
// Health summary
$health = [];
try { Database::fetchOne("SELECT setting_key FROM system_settings LIMIT 1"); $health['database'] = 'Connected'; }
catch (Throwable $e) { $health['database'] = 'Error: ' . $e->getMessage(); }
$health['environment'] = defined('APP_ENV') ? APP_ENV : 'unknown';
$health['php'] = PHP_VERSION;
$health['version'] = $flat['app_version'] ?? '1.0.0';
$health['maintenance'] = (($flat['maintenance_mode'] ?? '0') === '1') ? 'ON' : 'OFF';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings | Investhood IT Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
<link rel="stylesheet" href="<?= url('css/admin_settings.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard settings-module">
<div class="dashboard">
<aside class="sidebar admin-sidebar" id="adminSidebar">
<div class="sidebar__header">
<a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a>
<button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
</div>
<nav class="sidebar__nav">
<div class="sidebar__section-label">Command Centre</div>
<ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li></ul>
<div class="sidebar__section-label">Management</div>
<ul class="sidebar__menu">
<li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
<li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
<li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
<li><a href="<?= url('admin/placements.php') ?>" class="sidebar__link"><i class="fas fa-handshake"></i> Placements</a></li>
<li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li>
<li><a href="<?= url('admin/selection.php') ?>" class="sidebar__link"><i class="fas fa-user-check"></i> Selection &amp; Offers</a></li>
</ul>
<div class="sidebar__section-label">Operations</div>
<ul class="sidebar__menu">
<li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-history"></i> Audit Log</a></li>
<li><a href="<?= url('admin/settings.php') ?>" class="sidebar__link active"><i class="fas fa-cog"></i> System Settings</a></li>
</ul>
</nav>
<div class="sidebar__footer">
<div class="sidebar__user"><div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin') ?></span><span class="sidebar__user-role">Administrator</span></div></div>
<a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
</div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="dashboard__main">
<header class="dash-header admin-dash-header">
<div class="dash-header__left"><button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button><h2 style="margin:0;font-size:1.1rem;">System Settings</h2></div>
<div class="dash-header__right"><a href="<?= url('admin/dashboard.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Dashboard</a></div>
</header>
<div class="dash-content">
<?= $flashes ?>
<div class="app-hero"><div class="app-hero__inner">
<span class="section__badge"><i class="fas fa-cog"></i> Administration</span>
<h1 class="app-hero__title">System Settings</h1>
<p class="app-hero__subtitle">Manage platform configuration. Changes take effect immediately and are stored in the database.</p>
</div></div>
<?php require __DIR__ . '/settings_p1.php'; ?>
<?php require __DIR__ . '/settings_p2.php'; ?>
<?php require __DIR__ . '/settings_p3.php'; ?>
</div>
</main>
</div>
</div>
<script src="<?= url('js/admin_settings.js') ?>"></script>
</body>
</html>
