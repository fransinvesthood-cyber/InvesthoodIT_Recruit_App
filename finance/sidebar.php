
<?php

$currentPage = $currentPage ?? '';

if (!isset($user) || !is_array($user)) {
    $user = current_user();
}

$firstName = trim((string)($user['first_name'] ?? ''));
$lastName  = trim((string)($user['last_name'] ?? ''));

$name = trim((string)(
    $user['full_name']
    ?? $user['fullname']
    ?? ($firstName . ' ' . $lastName)
));

if ($name === '') {
    $name = 'Finance Officer';
}

$initials = fo_initials($firstName, $lastName);

/**
 * Determine the active navigation item.
 */
if (!function_exists('fo_nav')) {
    function fo_nav(string $current, $pages): string
    {
        $pages = is_array($pages) ? $pages : [$pages];

        return in_array($current, $pages, true)
            ? 'fo-nav__item--active'
            : '';
    }
}

?>

<aside class="fo-sidebar" id="foSidebar">

    <!-- BRAND -->
    <div class="fo-sidebar__brand">

        <div class="fo-sidebar__logo">
            <i class="fas fa-coins"></i>
        </div>

        <div>
            <strong>Investhood IT</strong>
            <span>Finance Operations</span>
        </div>

        <button
            type="button"
            id="foSidebarClose"
            aria-label="Close sidebar"
        >
            <i class="fas fa-xmark"></i>
        </button>

    </div>


    <!-- PROFILE -->
    <div class="fo-profile">

        <div class="fo-avatar">
            <?= e($initials) ?>
        </div>

        <div>
            <strong><?= e($name) ?></strong>
            <span>Finance Officer</span>
        </div>

    </div>


    <!-- NAVIGATION -->
    <nav class="fo-nav">

        <!-- MAIN -->
        <div class="fo-nav__label">Main</div>

        <a
            class="fo-nav__item <?= fo_nav($currentPage, 'dashboard') ?>"
            href="<?= url('finance/dashboard.php') ?>"
        >
            <i class="fas fa-grid-2"></i>
            <span>Dashboard</span>
        </a>


        <!-- STIPENDS -->
        <div class="fo-nav__label">Stipends</div>

        <a
            class="fo-nav__item <?= fo_nav($currentPage, 'attendance') ?>"
            href="<?= url('finance/attendance.php') ?>"
        >
            <i class="fas fa-calendar-check"></i>
            <span>Approved Attendance</span>
        </a>

        <a
            class="fo-nav__item <?= fo_nav(
                $currentPage,
                ['schedules', 'prepare_schedule', 'schedule_view']
            ) ?>"
            href="<?= url('finance/schedules.php') ?>"
        >
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Stipend Schedules</span>
        </a>

        <a
            class="fo-nav__item <?= fo_nav($currentPage, 'approvals') ?>"
            href="<?= url('finance/approvals.php') ?>"
        >
            <i class="fas fa-user-check"></i>
            <span>Approval Queue</span>
        </a>


        <!-- BUDGETING -->
        <div class="fo-nav__label">Budgeting</div>

        <a
            class="fo-nav__item <?= fo_nav($currentPage, 'budget') ?>"
            href="<?= url('finance/budget.php') ?>"
        >
            <i class="fas fa-wallet"></i>
            <span>Budget Allocation</span>
        </a>


        <!-- GOVERNANCE -->
        <div class="fo-nav__label">Governance</div>

        <a
            class="fo-nav__item <?= fo_nav($currentPage, 'exceptions') ?>"
            href="<?= url('finance/exceptions.php') ?>"
        >
            <i class="fas fa-triangle-exclamation"></i>
            <span>Exceptions</span>
        </a>

        <a
            class="fo-nav__item <?= fo_nav($currentPage, 'history') ?>"
            href="<?= url('finance/history.php') ?>"
        >
            <i class="fas fa-lock"></i>
            <span>Approved History</span>
        </a>

        <a
            class="fo-nav__item <?= fo_nav($currentPage, 'activity_log') ?>"
            href="<?= url('finance/activity_log.php') ?>"
        >
            <i class="fas fa-fingerprint"></i>
            <span>Activity Log</span>
        </a>


        <!-- APPEARANCE -->
        <div class="fo-nav__label">Appearance</div>

        <button
            type="button"
            class="fo-theme"
            data-fo-theme-toggle
        >
            <i class="fas fa-moon" data-fo-theme-icon></i>
            <span>Dark Mode</span>
        </button>

    </nav>


    <!-- FOOTER -->
    <div class="fo-sidebar__footer">

        <div class="fo-secure">

            <i class="fas fa-user-shield"></i>

            <div>
                <strong>Maker / Checker</strong>

                <span>
                    Approval segregation protects payment integrity.
                </span>
            </div>

        </div>

        <a
            class="fo-logout"
            href="<?= url('auth/logout.php') ?>"
        >
            <i class="fas fa-arrow-right-from-bracket"></i>
            Sign Out
        </a>

    </div>

</aside>


<!-- MOBILE OVERLAY -->
<div class="fo-overlay" id="foOverlay"></div>
