<?php
$currentPage = $currentPage ?? '';
if (!isset($user) || !is_array($user)) $user = current_user();
$sf = trim((string)($user['first_name'] ?? ''));
$sl = trim((string)($user['last_name'] ?? ''));
$sn = trim((string)($user['full_name'] ?? $user['fullname'] ?? ($sf . ' ' . $sl)));
if ($sn === '') $sn = 'Supervisor';
$si = sv_initials($sf, $sl);
if (!function_exists('sv_nav_active')) {
    function sv_nav_active(string $current, $pages): string {
        return in_array($current, (array)$pages, true) ? 'sv-nav__item--active' : '';
    }
}
?>
<aside class="supervisor-sidebar" id="supervisorSidebar">
  <div class="sv-brand">
    <div class="sv-brand__logo"><i class="fas fa-chart-simple"></i></div>
    <div class="sv-brand__copy"><strong>Investhood IT</strong><span>Programme Platform</span></div>
    <button class="sv-sidebar-close" id="supervisorSidebarClose" type="button" aria-label="Close navigation"><i class="fas fa-xmark"></i></button>
  </div>

  <div class="sv-profile">
    <div class="sv-profile__avatar"><?= e($si) ?><span class="sv-profile__dot"></span></div>
    <div class="sv-profile__copy"><strong><?= e($sn) ?></strong><span>Supervisor</span></div>
  </div>

  <nav class="sv-nav" aria-label="Supervisor navigation">
    <div class="sv-nav__group">
      <div class="sv-nav__label">Main</div>
      <a class="sv-nav__item <?= sv_nav_active($currentPage, 'dashboard') ?>" href="<?= url('supervisor/dashboard.php') ?>">
        <span class="sv-nav__icon"><i class="fas fa-grid-2"></i></span><span>Dashboard</span>
      </a>
    </div>

    <div class="sv-nav__group">
      <div class="sv-nav__label">Management</div>
      <a class="sv-nav__item <?= sv_nav_active($currentPage, ['cohorts','cohort_view']) ?>" href="<?= url('supervisor/cohorts.php') ?>">
        <span class="sv-nav__icon"><i class="fas fa-layer-group"></i></span><span>My Cohorts</span>
      </a>
      <a class="sv-nav__item <?= sv_nav_active($currentPage, ['candidates','candidate_view','update_candidate_status']) ?>" href="<?= url('supervisor/candidates.php') ?>">
        <span class="sv-nav__icon"><i class="fas fa-users"></i></span><span>Candidates</span>
      </a>
    </div>

    <div class="sv-nav__group">
      <div class="sv-nav__label">Reporting</div>
      <a class="sv-nav__item <?= sv_nav_active($currentPage, 'reports') ?>" href="<?= url('supervisor/reports.php') ?>">
        <span class="sv-nav__icon"><i class="fas fa-chart-column"></i></span><span>Cohort Progress</span>
      </a>
      <a class="sv-nav__item <?= sv_nav_active($currentPage, 'activity_log') ?>" href="<?= url('supervisor/activity_log.php') ?>">
        <span class="sv-nav__icon"><i class="fas fa-clock-rotate-left"></i></span><span>Activity Log</span>
      </a>
    </div>

    <div class="sv-nav__group">
      <div class="sv-nav__label">Appearance</div>
      <button class="sv-theme-btn" type="button" data-supervisor-theme-toggle>
        <span class="sv-nav__icon"><i class="fas fa-moon" data-supervisor-theme-icon></i></span>
        <span>Dark Mode</span><span class="sv-theme-switch"></span>
      </button>
    </div>
  </nav>

  <div class="sv-sidebar-footer">
    <div class="sv-security-mini"><i class="fas fa-shield-halved"></i><div><strong>Secure Access</strong><span>Restricted to your assigned cohorts.</span></div></div>
    <a class="sv-logout" href="<?= url('auth/logout.php') ?>"><i class="fas fa-arrow-right-from-bracket"></i><span>Sign Out</span></a>
  </div>
</aside>
<div class="supervisor-overlay" id="supervisorOverlay"></div>
