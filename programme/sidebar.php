<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Manager Sidebar
 * ================================================
 */
if (!isset($user) || !is_array($user)) {
    $user = current_user();
}
$currentPage = $currentPage ?? '';
?>
<aside class="sidebar">
    <!-- =====================================================
         SIDEBAR HEADER
    ====================================================== -->
    <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
            <span class="logo__icon">
                <i class="fas fa-code"></i>
            </span>
            <span class="logo__text">
                Investhood <span class="logo__accent">IT</span>
            </span>
        </a>
    </div>
    <!-- =====================================================
         SIDEBAR NAVIGATION
    ====================================================== -->
    <nav class="sidebar__nav">
        <div class="sidebar__section-label">
            Programme Manager
        </div>
        <ul class="sidebar__menu">
            <!-- Dashboard -->
            <li>
                <a
                    href="<?= url('programme/dashboard.php') ?>"
                    class="sidebar__link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"
                >
                    <i class="fas fa-th-large"></i>
                    Dashboard
                </a>
            </li>
            <!-- My Programmes -->
            <li>
                <a
                    href="<?= url('programme/programmes.php') ?>"
                    class="sidebar__link <?= $currentPage === 'programmes' ? 'active' : '' ?>"
                >
                    <i class="fas fa-graduation-cap"></i>
                    My Programmes
                </a>
            </li>
            <!-- Cohorts -->
            <li>
                <a
                    href="<?= url('programme/cohorts.php') ?>"
                    class="sidebar__link <?= $currentPage === 'cohorts' ? 'active' : '' ?>"
                >
                    <i class="fas fa-graduation-cap"></i>
                    Cohorts
                </a>
            </li>
            <!-- Candidates -->
            <li>
                <a
                    href="<?= url('programme/candidates.php') ?>"
                    class="sidebar__link <?= $currentPage === 'candidates' ? 'active' : '' ?>"
                >
                    <i class="fas fa-users"></i>
                    Candidates
                </a>
            </li>
            <!-- Reports -->
            <li>
                <a
                    href="<?= url('programme/reports.php') ?>"
                    class="sidebar__link <?= $currentPage === 'reports' ? 'active' : '' ?>"
                >
                    <i class="fas fa-chart-line"></i>
                    Reports
                </a>
            </li>
        </ul>
    </nav>
    <!-- =====================================================
         SIDEBAR FOOTER
    ====================================================== -->
    <div class="sidebar__footer">
        <div class="sidebar__user">
            <div class="sidebar__user-avatar">
                <img
                    src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'PM+User') ?>&background=1a56db&color=fff&size=80"
                    alt="User Avatar"
                >
            </div>
            <div class="sidebar__user-info">
                <span class="sidebar__user-name">
                    <?= e($user['fullname'] ?? 'Programme Manager') ?>
                </span>
                <span class="sidebar__user-role">
                    Programme Manager
                </span>
            </div>
        </div>
        <a
            href="<?= url('auth/logout.php') ?>"
            class="sidebar__logout"
        >
            <i class="fas fa-sign-out-alt"></i>
            Sign Out
        </a>
    </div>
</aside>