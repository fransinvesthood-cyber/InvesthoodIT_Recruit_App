
<?php
/**
 * Finance Officer Navbar
 * Features:
 * - Dynamic page title
 * - Notification dropdown
 * - Dark mode toggle
 * - Responsive profile dropdown
 * - Safe handling of an unavailable database connection
 */

$currentPage = $currentPage ?? '';

if (!isset($user) || !is_array($user)) {
    $user = current_user();
}

if (!function_exists('fo_initials')) {
    require_once __DIR__ . '/_helpers.php';
}

$f = trim((string)($user['first_name'] ?? ''));
$l = trim((string)($user['last_name'] ?? ''));

$name = trim((string)(
    $user['full_name']
    ?? $user['fullname']
    ?? ($f . ' ' . $l)
));

if ($name === '') {
    $name = 'Finance Officer';
}

$initials = fo_initials($f, $l);

$titles = [
    'dashboard'          => 'Dashboard',
    'attendance'          => 'Approved Attendance',
    'schedules'           => 'Stipend Schedules',
    'approvals'           => 'Approval Queue',
    'exceptions'          => 'Exceptions',
    'history'             => 'Approved History',
    'activity_log'        => 'Activity Log',
    'audit_trail'         => 'Financial Audit Trail & Transaction History',
    'prepare_schedule'    => 'Stipend Schedules',
    'schedule_view'       => 'Stipend Schedule Details',
    'outstanding_payments'=> 'Outstanding Payments',
    'reconciliation'      => 'Financial Reconciliation',
    'payment_verification'=> 'Payment Verification',
    'notifications'       => 'Notifications',
    'profile'             => 'My Profile',
    'settings'            => 'Settings',
    'budget'              => 'Budget Allocation & Expenditure',
    'forecasting'         => 'Financial Forecasting & Budget Variance Analysis',
];

$title = $titles[$currentPage] ?? 'Finance Officer Portal';
$uid = (int)($user['id'] ?? $user['user_id'] ?? 0);

/*
 * Reuse the existing database connection.
 * Do not open another connection just for notifications.
 */
$financeDb = null;

if (isset($conn) && $conn instanceof mysqli) {
    $financeDb = $conn;
} elseif (isset($db) && $db instanceof mysqli) {
    $financeDb = $db;
} elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $financeDb = $mysqli;
}

$notifications = [];
$unread = 0;

if ($financeDb instanceof mysqli && function_exists('fo_notifications')) {
    try {
        $nd = fo_notifications($financeDb, $uid, 6);
        $notifications = $nd['items'] ?? [];
        $unread = (int)($nd['unread'] ?? 0);
    } catch (Throwable $ex) {
        error_log('Finance navbar notifications: ' . $ex->getMessage());
    }
}
?>

<header class="ux-navbar">

    <div class="ux-navbar__left">

        <button
            class="ux-navbar__menu"
            id="uxMenuButton"
            type="button"
            aria-label="Show or hide navigation menu"
            aria-expanded="false"
            aria-controls="foSidebar"
            title="Show or hide menu"
        >
            <span class="ux-menu-lines" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>

        <div class="ux-navbar__heading">
            <span>Finance Officer Workspace</span>
            <h1><?= e($title) ?></h1>
        </div>

    </div>

    <div class="ux-navbar__right">

        <!-- Dark mode toggle -->
        <button
            class="ux-theme-toggle"
            type="button"
            data-ux-theme-toggle
            title="Toggle dark mode"
            aria-label="Toggle dark mode"
        >
            <i class="fas fa-moon" data-ux-theme-icon></i>
        </button>

        <!-- Notifications -->
        <div class="ux-navbar__notification">

            <button
                class="ux-navbar__icon-button ux-notification-button"
                id="uxNotificationButton"
                type="button"
                aria-label="Notifications"
                aria-expanded="false"
                aria-controls="uxNotificationDropdown"
            >
                <i class="fas fa-bell"></i>

                <?php if ($unread > 0): ?>
                    <span class="ux-notification-badge">
                        <?= $unread > 99 ? '99+' : $unread ?>
                    </span>
                <?php endif; ?>
            </button>

            <div
                class="ux-dropdown ux-notification-dropdown"
                id="uxNotificationDropdown"
                hidden
            >

                <div class="ux-dropdown__head">
                    <strong>Notifications</strong>
                    <span><?= $unread ?> unread</span>
                </div>

                <div class="ux-dropdown__body">

                    <?php if (empty($notifications)): ?>

                        <div class="ux-empty ux-empty--compact">
                            <div class="ux-empty__icon">
                                <i class="fas fa-bell"></i>
                            </div>

                            <strong>You're all caught up</strong>
                            <span>No notifications to display.</span>
                        </div>

                    <?php else: ?>

                        <?php foreach ($notifications as $n): ?>

                            <div class="ux-notification-item <?= (int)($n['is_read'] ?? 1) === 0 ? 'ux-notification-item--unread' : '' ?>">

                                <span class="ux-notification-item__icon">
                                    <i class="fas fa-bell"></i>
                                </span>

                                <div>
                                    <strong>
                                        <?= e((string)($n['title'] ?? 'Notification')) ?>
                                    </strong>

                                    <span>
                                        <?= e((string)($n['message'] ?? '')) ?>
                                    </span>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

                <div class="ux-dropdown__footer">
                    <a href="<?= e(url('finance/notifications.php')) ?>">
                        View all notifications
                    </a>
                </div>

            </div>

        </div>

        <!-- Profile dropdown -->
        <div class="ux-navbar__profile" id="uxProfile">

            <button
                class="ux-navbar__user"
                id="uxProfileButton"
                type="button"
                aria-label="Open profile menu"
                aria-expanded="false"
                aria-controls="uxProfileDropdown"
            >

                <div class="ux-navbar__avatar">
                    <?= e($initials) ?>
                </div>

                <div class="ux-navbar__user-copy">
                    <strong><?= e($name) ?></strong>
                    <span>Finance Officer</span>
                </div>

                <i class="fas fa-chevron-down ux-profile-chevron"></i>

            </button>

            <div
                class="ux-dropdown ux-profile-dropdown"
                id="uxProfileDropdown"
                hidden
            >

                <div class="ux-dropdown__head">
                    <strong><?= e($name) ?></strong>
                    <span>Finance Officer</span>
                </div>

                <a
                    class="ux-profile-link"
                    href="<?= e(url('finance/profile.php')) ?>"
                >
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>

                <a
                    class="ux-profile-link"
                    href="<?= e(url('finance/settings.php')) ?>"
                >
                    <i class="fas fa-gear"></i>
                    <span>Settings</span>
                </a>

                <div class="ux-dropdown__divider"></div>

                <a
                    class="ux-profile-link ux-profile-link--logout"
                    href="<?= e(url('auth/logout.php')) ?>"
                    onclick="return confirm('Are you sure you want to log out?');"
                >
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>

            </div>

        </div>

    </div>

</header>

<style>
/* Navbar layout */
.ux-navbar__profile,
.ux-navbar__notification {
    position: relative;
}

.ux-navbar__user {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 5px;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    text-align: left;
}

.ux-navbar__user:focus-visible,
.ux-navbar__icon-button:focus-visible,
.ux-theme-toggle:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 3px;
}

.ux-profile-chevron {
    font-size: 11px;
    opacity: .7;
    transition: transform .2s ease;
}

.ux-navbar__user[aria-expanded="true"] .ux-profile-chevron {
    transform: rotate(180deg);
}

/* Dropdown panels */
.ux-navbar .ux-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    z-index: 1200;
    width: min(320px, calc(100vw - 24px));
    overflow: hidden;
    padding: 8px;
    border: 1px solid var(--ux-border, #e5e7eb);
    border-radius: 12px;
    background: var(--ux-dropdown-bg, #ffffff);
    color: var(--ux-text, #1f2937);
    box-shadow: 0 12px 35px rgb(0 0 0 / 12%);
}

.ux-navbar .ux-dropdown[hidden] {
    display: none !important;
}

.ux-navbar .ux-dropdown.is-open {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}

.ux-navbar {
    position: relative;
    z-index: 5000;
}

.ux-navbar__right {
    position: relative;
    z-index: 5001;
}

.ux-dropdown__head {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px;
    border-bottom: 1px solid var(--ux-border, #e5e7eb);
}

.ux-dropdown__head strong {
    font-size: 14px;
    color: inherit;
}

.ux-dropdown__head span {
    font-size: 12px;
    color: var(--ux-muted, #6b7280);
}

.ux-dropdown__body {
    max-height: 320px;
    overflow-y: auto;
}

.ux-profile-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 8px;
    color: inherit;
    text-decoration: none;
    font-size: 14px;
}

.ux-profile-link i {
    width: 18px;
    text-align: center;
}

.ux-profile-link:hover {
    background: var(--ux-hover-bg, #f3f4f6);
}

.ux-profile-link--logout {
    color: #dc2626;
}

.ux-dropdown__divider {
    height: 1px;
    margin: 6px 0;
    background: var(--ux-border, #e5e7eb);
}

.ux-dropdown__footer {
    padding: 10px 12px;
    border-top: 1px solid var(--ux-border, #e5e7eb);
}

.ux-dropdown__footer a {
    color: var(--ux-link, #4f46e5);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.ux-notification-item {
    display: flex;
    gap: 10px;
    padding: 12px;
    border-radius: 8px;
}

.ux-notification-item--unread {
    background: var(--ux-hover-bg, #f3f4f6);
}

.ux-notification-item > div {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.ux-notification-item strong {
    font-size: 13px;
    color: inherit;
}

.ux-notification-item span {
    font-size: 12px;
    overflow-wrap: anywhere;
}

.ux-notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
}

/* Dark mode support */
html[data-theme="dark"] .ux-navbar,
html[data-ux-theme="dark"] .ux-navbar,
body.dark-mode .ux-navbar,
body.dark .ux-navbar {
    --ux-dropdown-bg: #1f2937;
    --ux-text: #f9fafb;
    --ux-muted: #9ca3af;
    --ux-border: #374151;
    --ux-hover-bg: #374151;
    --ux-link: #a5b4fc;
}

html[data-theme="dark"] .ux-navbar .ux-dropdown,
html[data-ux-theme="dark"] .ux-navbar .ux-dropdown,
body.dark-mode .ux-navbar .ux-dropdown,
body.dark .ux-navbar .ux-dropdown {
    background: var(--ux-dropdown-bg);
    color: var(--ux-text);
    border-color: var(--ux-border);
}

html[data-theme="dark"] .ux-navbar .ux-dropdown__head,
html[data-ux-theme="dark"] .ux-navbar .ux-dropdown__head,
body.dark-mode .ux-navbar .ux-dropdown__head,
body.dark .ux-navbar .ux-dropdown__head {
    background: transparent;
    color: var(--ux-text);
}

/* Mobile responsiveness */
@media (max-width: 640px) {
    .ux-navbar__user-copy,
    .ux-profile-chevron {
        display: none;
    }

    .ux-navbar .ux-dropdown {
        width: min(300px, calc(100vw - 20px));
        right: -4px;
    }

    .ux-navbar__heading h1 {
        font-size: 1rem;
    }
}

/* Always-visible responsive sidebar toggle (desktop, tablet and mobile). */
.ux-navbar__left { display:flex; align-items:center; gap:12px; min-width:0; }
.ux-navbar__menu {
    display:inline-flex !important;
    flex:0 0 42px;
    align-items:center;
    justify-content:center;
    width:42px;
    height:42px;
    min-width:42px;
    min-height:42px;
    padding:0;
    border:1px solid var(--ux-border, #d1d5db);
    border-radius:10px;
    background:var(--ux-surface, #fff);
    color:var(--ux-text, #1f2937);
    cursor:pointer;
    visibility:visible !important;
    opacity:1 !important;
    position:relative;
    z-index:5100;
}
.ux-navbar__menu:hover { background:var(--ux-hover-bg, #f3f4f6); }
.ux-navbar__menu:focus-visible { outline:2px solid #6366f1; outline-offset:3px; }
.ux-menu-lines { display:flex; width:18px; flex-direction:column; gap:4px; }
.ux-menu-lines span { display:block; width:18px; height:2px; border-radius:2px; background:currentColor; }
[data-theme="dark"] .ux-navbar__menu, body.dark .ux-navbar__menu, body.dark-mode .ux-navbar__menu {
    background:var(--ux-surface, #1f2937); color:var(--ux-text, #f9fafb); border-color:var(--ux-border, #374151);
}
@media(max-width:600px) {
    .ux-navbar__left { gap:8px; }
    .ux-navbar__menu { flex-basis:40px; width:40px; height:40px; min-width:40px; min-height:40px; }
    .ux-navbar__heading h1 { font-size:clamp(14px, 4vw, 18px); }
}

/* Reliable, visible theme control */
.ux-theme-toggle {
    display:inline-flex !important;
    align-items:center;
    justify-content:center;
    width:42px;
    height:42px;
    min-width:42px;
    min-height:42px;
    padding:0;
    border:1px solid var(--ux-border, #d1d5db);
    border-radius:10px;
    background:var(--ux-surface, #fff);
    color:var(--ux-text, #1f2937);
    cursor:pointer;
    position:relative;
    z-index:5200;
    opacity:1 !important;
    visibility:visible !important;
    pointer-events:auto !important;
    transition:background .2s ease, color .2s ease, transform .2s ease;
}
.ux-theme-toggle:hover { background:var(--ux-hover-bg, #f3f4f6); transform:translateY(-1px); }
.ux-theme-toggle i { font-size:17px; line-height:1; pointer-events:none; }
html[data-theme="dark"] .ux-theme-toggle,
html[data-ux-theme="dark"] .ux-theme-toggle,
body.dark .ux-theme-toggle,
body.dark-mode .ux-theme-toggle {
    background:#1f2937;
    color:#f9fafb;
    border-color:#4b5563;
}
@media(max-width:600px) {
    .ux-theme-toggle { width:38px; height:38px; min-width:38px; min-height:38px; }
}

</style>


