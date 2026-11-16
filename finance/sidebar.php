<?php
$currentPage = $currentPage ?? '';
if (!isset($user) || !is_array($user)) $user = current_user();
$firstName = trim((string)($user['first_name'] ?? ''));
$lastName = trim((string)($user['last_name'] ?? ''));
$name = trim((string)($user['full_name'] ?? $user['fullname'] ?? ($firstName . ' ' . $lastName)));
if ($name === '') $name = 'Finance Officer';
$initials = fo_initials($firstName, $lastName);
if (!function_exists('fo_nav')) {
    function fo_nav(string $current, $pages): string {
        $pages = is_array($pages) ? $pages : [$pages];
        return in_array($current, $pages, true) ? 'fo-nav__item--active' : '';
    }
}
?>
<style>
/* Robust sidebar layout: fixed viewport shell + independently scrollable navigation. */
#foSidebar.fo-sidebar{
  position:fixed!important;top:0!important;bottom:0!important;left:0!important;
  height:100vh!important;height:100dvh!important;max-height:100vh;max-height:100dvh;
  display:grid!important;grid-template-rows:auto auto minmax(0,1fr) auto!important;
  overflow:hidden!important;min-height:0!important;box-sizing:border-box!important;
  overscroll-behavior:contain;
}
#foSidebar .fo-sidebar__brand,#foSidebar .fo-profile,#foSidebar .fo-sidebar__footer{
  min-height:0!important;min-width:0;overflow:visible;flex:none!important;
}
#foSidebar .fo-nav{
  display:block!important;grid-row:3!important;min-height:0!important;min-width:0!important;
  height:100%!important;max-height:none!important;width:100%;box-sizing:border-box!important;
  overflow-y:scroll!important;overflow-x:hidden!important;
  overscroll-behavior-y:contain;-webkit-overflow-scrolling:touch;touch-action:pan-y;
  scrollbar-width:thin;scrollbar-color:rgba(148,163,184,.8) transparent;
  scrollbar-gutter:stable;padding:8px 6px 16px 0!important;
}
#foSidebar .fo-nav::-webkit-scrollbar{width:9px}
#foSidebar .fo-nav::-webkit-scrollbar-track{background:transparent;margin:3px 0}
#foSidebar .fo-nav::-webkit-scrollbar-thumb{background:rgba(148,163,184,.75);border-radius:20px;border:2px solid transparent;background-clip:padding-box;min-height:32px}
#foSidebar .fo-nav:hover::-webkit-scrollbar-thumb{background:rgba(100,116,139,.98);background-clip:padding-box}
#foSidebar .fo-nav::-webkit-scrollbar-thumb:active{background:rgba(71,85,105,1);background-clip:padding-box}
@media(max-width:920px){#foSidebar.fo-sidebar{width:min(86vw,320px)!important;max-width:100vw}}
@media(prefers-reduced-motion:no-preference){#foSidebar .fo-nav{scroll-behavior:smooth}}
</style>
<aside class="fo-sidebar" id="foSidebar">
    <div class="fo-sidebar__brand fo-sidebar__brand--image">
        <a class="fo-sidebar__brand-link" href="<?= e(url('finance/dashboard.php')) ?>" aria-label="Investhood IT Finance dashboard">
            <img src="<?= e(url('finance/assets/investhood-it-logo.jpg')) ?>" alt="Investhood IT — Growing Digi-Edu Tech">
        </a>
        <button type="button" id="foSidebarClose" aria-label="Close sidebar"><i class="fas fa-xmark"></i></button>
    </div>
    <style>
    #foSidebar .fo-sidebar__brand--image{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 14px;min-height:88px}
    #foSidebar .fo-sidebar__brand-link{display:flex;align-items:center;justify-content:center;min-width:0;flex:1;text-decoration:none}
    #foSidebar .fo-sidebar__brand-link img{display:block;width:min(176px,100%);height:72px;object-fit:contain;object-position:center;background:#fff;border-radius:8px;padding:3px}
    #foSidebar .fo-sidebar__brand--image #foSidebarClose{flex:0 0 auto}
    @media(max-width:480px){#foSidebar .fo-sidebar__brand--image{min-height:76px}#foSidebar .fo-sidebar__brand-link img{height:62px;width:min(154px,100%)}}
    </style>
    <div class="fo-profile">
        <div class="fo-avatar"><?= e($initials) ?></div>
        <div><strong><?= e($name) ?></strong><span>Finance Officer</span></div>
    </div>
    <nav class="fo-nav">
        <div class="fo-nav__label">Main</div>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'dashboard') ?>" href="<?= url('finance/dashboard.php') ?>"><i class="fas fa-grid-2"></i><span>Dashboard</span></a>
        <div class="fo-nav__label">Stipends</div>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'attendance') ?>" href="<?= url('finance/attendance.php') ?>"><i class="fas fa-calendar-check"></i><span>Approved Attendance</span></a>
        <a class="fo-nav__item <?= fo_nav($currentPage, ['schedules','prepare_schedule','schedule_view']) ?>" href="<?= url('finance/schedules.php') ?>"><i class="fas fa-file-invoice-dollar"></i><span>Stipend Schedules</span></a>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'approvals') ?>" href="<?= url('finance/approvals.php') ?>"><i class="fas fa-user-check"></i><span>Approval Queue</span></a>
        <div class="fo-nav__label">Budgeting &amp; Forecasting</div>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'budget') ?>" href="<?= url('finance/budget.php') ?>"><i class="fas fa-wallet"></i><span>Budget Allocation</span></a>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'forecasting') ?>" href="<?= url('finance/forecasting.php') ?>"><i class="fas fa-chart-line"></i><span>Financial Forecasting</span></a>
        <div class="fo-nav__label">Accounts Payable</div>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'supplier_invoices') ?>" href="<?= url('finance/supplier_invoices.php') ?>"><i class="fas fa-file-invoice"></i><span>Supplier Invoices &amp; Payables</span></a>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'supplier_disbursements') ?>" href="<?= url('finance/supplier_disbursements.php') ?>"><i class="fas fa-money-bill-transfer"></i><span>Supplier Payment Processing</span></a>
        <div class="fo-nav__label">Procurement &amp; Commitments</div>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'commitments') ?>" href="<?= url('finance/commitments.php') ?>"><i class="fas fa-file-contract"></i><span>Financial Commitments &amp; POs</span></a>
        <div class="fo-nav__label">Management Reporting</div>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'reports') ?>" href="<?= url('finance/reports.php') ?>"><i class="fas fa-file-invoice-dollar"></i><span>Financial Statements &amp; Reports</span></a>
        <div class="fo-nav__label">Governance</div>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'exceptions') ?>" href="<?= url('finance/exceptions.php') ?>"><i class="fas fa-triangle-exclamation"></i><span>Exceptions</span></a>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'history') ?>" href="<?= url('finance/history.php') ?>"><i class="fas fa-lock"></i><span>Approved History</span></a>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'activity_log') ?>" href="<?= url('finance/activity_log.php') ?>"><i class="fas fa-fingerprint"></i><span>Activity Log</span></a>
        <a class="fo-nav__item <?= fo_nav($currentPage, 'audit_trail') ?>" href="<?= url('finance/audit_trail.php') ?>"><i class="fas fa-file-shield"></i><span>Financial Audit Trail</span></a>
        <div class="fo-nav__label">Appearance</div>
        <button type="button" class="fo-theme" data-fo-theme-toggle><i class="fas fa-moon" data-fo-theme-icon></i><span>Dark Mode</span></button>
    </nav>
    <div class="fo-sidebar__footer">
        <div class="fo-secure"><i class="fas fa-user-shield"></i><div><strong>Maker / Checker</strong><span>Approval segregation protects payment integrity.</span></div></div>
        <a class="fo-logout" href="<?= url('auth/logout.php') ?>"><i class="fas fa-arrow-right-from-bracket"></i> Sign Out</a>
    </div>
</aside>
<div class="fo-overlay" id="foOverlay"></div>
