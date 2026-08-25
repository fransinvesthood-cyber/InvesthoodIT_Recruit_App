<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Officer Sidebar
 * ================================================
 * Reusable sidebar navigation for Programme Officer
 */
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
            Programme Officer
        </div>
        <ul class="sidebar__menu">
            <!-- Dashboard -->
            <li>
                <a
                    href="<?= url('programme_officer/dashboard.php') ?>"
                    class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-th-large"></i>
                    Dashboard
                </a>
            </li>
            <!-- Programmes -->
            <li>
                <a
                    href="<?= url('programme_officer/programmes.php') ?>"
                    class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === 'programmes.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-graduation-cap"></i>
                    Programmes
                </a>
            </li>
            <!-- Candidates -->
            <li>
                <a
                    href="<?= url('programme_officer/candidates.php') ?>"
                    class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === 'candidates.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-users"></i>
                    Candidates
                </a>
            </li>
            <!-- Reports -->
            <li>
                <a
                    href="<?= url('programme_officer/reports.php') ?>"
                    class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>"
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
                    src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Programme+Officer') ?>&background=1a56db&color=fff&size=80"
                    alt=""
                >
            </div>
            <div class="sidebar__user-info">
                <span class="sidebar__user-name">
                    <?= e($user['fullname'] ?? 'Programme Officer') ?>
                </span>
                <span class="sidebar__user-role">
                    Programme Officer
                </span>
            </div>
        </div>
        <!-- Sign Out -->
        <a
            href="<?= url('auth/logout.php') ?>"
            class="sidebar__logout"
        >
            <i class="fas fa-sign-out-alt"></i>
            Sign Out
        </a>
    </div>
</aside>