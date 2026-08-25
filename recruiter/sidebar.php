<?php
/**
 * ================================================
 * INVESTHOOD IT - Recruiter Sidebar
 * ================================================
 */
if (!isset($user)) {
    $user = current_user();
}
?>
<aside class="sidebar">
    <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
            <span class="logo__icon">
                <i class="fas fa-code"></i>
            </span>
            <span class="logo__text">
                Investhood
                <span class="logo__accent">IT</span>
            </span>
        </a>
    </div>
    <nav class="sidebar__nav">
        <div class="sidebar__section-label">
            Recruiter
        </div>
        <ul class="sidebar__menu">
            <li>
                <a
                    href="<?= url('recruiter/dashboard.php') ?>"
                    class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-th-large"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a
                    href="<?= url('recruiter/opportunities.php') ?>"
                    class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === 'opportunities.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-briefcase"></i>
                    Opportunities
                </a>
            </li>
            <li>
                <a
                    href="<?= url('recruiter/candidates.php') ?>"
                    class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === 'candidates.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-users"></i>
                    Candidates
                </a>
            </li>
            <li>
                <a
                    href="<?= url('recruiter/reports.php') ?>"
                    class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-chart-line"></i>
                    Reports
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar__footer">
        <div class="sidebar__user">
            <div class="sidebar__user-avatar">
                <img
                    src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Recruiter') ?>&background=1a56db&color=fff&size=80"
                    alt=""
                >
            </div>
            <div class="sidebar__user-info">
                <span class="sidebar__user-name">
                    <?= e($user['fullname'] ?? 'Recruiter') ?>
                </span>
                <span class="sidebar__user-role">
                    Recruiter
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