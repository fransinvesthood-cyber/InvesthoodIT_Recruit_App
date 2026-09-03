<?php
/**
 * ================================================================
 * INVESTHOOD IT
 * SUPERVISOR SIDEBAR
 * ================================================================
 */
$currentPage =
    $currentPage
    ?? '';
if (
    !isset($user)
    ||
    !is_array($user)
) {
    $user = current_user();
}
/*
|--------------------------------------------------------------------------
| Supervisor Details
|--------------------------------------------------------------------------
*/
$sidebarFirstName = trim(
    (string)(
        $user['first_name']
        ?? ''
    )
);
$sidebarLastName = trim(
    (string)(
        $user['last_name']
        ?? ''
    )
);
$sidebarFullName = trim(
    (string)(
        $user['fullname']
        ?? $user['full_name']
        ?? (
            $sidebarFirstName .
            ' ' .
            $sidebarLastName
        )
    )
);
if ($sidebarFullName === '') {
    $sidebarFullName = 'Supervisor';
}
$sidebarEmail = trim(
    (string)(
        $user['email']
        ?? ''
    )
);
/*
|--------------------------------------------------------------------------
| Initials
|--------------------------------------------------------------------------
*/
$sidebarInitials = '';
if ($sidebarFirstName !== '') {
    $sidebarInitials .= substr(
        $sidebarFirstName,
        0,
        1
    );
}
if ($sidebarLastName !== '') {
    $sidebarInitials .= substr(
        $sidebarLastName,
        0,
        1
    );
}
if ($sidebarInitials === '') {
    $sidebarInitials = substr(
        $sidebarFullName,
        0,
        1
    );
}
$sidebarInitials =
    strtoupper(
        $sidebarInitials
    );
/*
|--------------------------------------------------------------------------
| Active Page Helper
|--------------------------------------------------------------------------
*/
if (
    !function_exists(
        'supervisorNavActive'
    )
) {
    function supervisorNavActive(
        string $currentPage,
        string|array $pages
    ): string {
        $pages =
            (array)$pages;
        return in_array(
            $currentPage,
            $pages,
            true
        )
            ? 'supervisor-nav__item--active'
            : '';
    }
}
/*
|--------------------------------------------------------------------------
| Active Groups
|--------------------------------------------------------------------------
*/
$cohortPages = [
    'cohorts',
    'cohort_view'
];
$candidatePages = [
    'candidates',
    'candidate_view',
    'update_candidate_status'
];
$reportPages = [
    'reports'
];
$activityPages = [
    'activity_log'
];
?>
<!-- ================================================================
     SUPERVISOR SIDEBAR
================================================================ -->
<aside
    class="supervisor-sidebar"
    id="supervisorSidebar"
    aria-label="Supervisor navigation"
>
    <!-- ============================================================
         BRAND
    ============================================================= -->
    <div class="supervisor-sidebar__brand">
        <a
            href="<?= url(
                'supervisor/dashboard.php'
            ) ?>"
            class="supervisor-sidebar__brand-link"
        >
            <div class="supervisor-sidebar__logo">
                <span class="supervisor-sidebar__logo-ring">
                    <i class="fas fa-chart-simple"></i>
                </span>
            </div>
            <div class="supervisor-sidebar__brand-copy">
                <strong>
                    Investhood IT
                </strong>
                <span>
                    Programme Platform
                </span>
            </div>
        </a>
        <button
            type="button"
            class="supervisor-sidebar__close"
            id="supervisorSidebarClose"
            aria-label="Close navigation"
        >
            <i class="fas fa-xmark"></i>
        </button>
    </div>
    <!-- ============================================================
         PROFILE
    ============================================================= -->
    <div class="supervisor-sidebar__profile">
        <div class="supervisor-sidebar__avatar">
            <?= e(
                $sidebarInitials
            ) ?>
            <span
                class="supervisor-sidebar__online"
                title="Online"
            ></span>
        </div>
        <div class="supervisor-sidebar__profile-details">
            <strong
                title="<?= e(
                    $sidebarFullName
                ) ?>"
            >
                <?= e(
                    $sidebarFullName
                ) ?>
            </strong>
            <div class="supervisor-sidebar__role">
                <span>
                    Supervisor
                </span>
                <i class="fas fa-circle-check"></i>
            </div>
            <?php if (
                $sidebarEmail !== ''
            ): ?>
                <small
                    title="<?= e(
                        $sidebarEmail
                    ) ?>"
                >
                    <?= e(
                        $sidebarEmail
                    ) ?>
                </small>
            <?php endif; ?>
        </div>
    </div>
    <!-- ============================================================
         NAVIGATION
    ============================================================= -->
    <nav class="supervisor-nav">
        <!-- ========================================================
             MAIN
        ========================================================= -->
        <section class="supervisor-nav__group">
            <span class="supervisor-nav__heading">
                <span>
                    Main
                </span>
            </span>
            <a
                href="<?= url(
                    'supervisor/dashboard.php'
                ) ?>"
                class="
                    supervisor-nav__item
                    <?= supervisorNavActive(
                        $currentPage,
                        'dashboard'
                    ) ?>
                "
            >
                <span class="supervisor-nav__icon">
                    <i class="fas fa-grid-2"></i>
                </span>
                <span class="supervisor-nav__label">
                    Dashboard
                </span>
                <?php if (
                    $currentPage === 'dashboard'
                ): ?>
                    <span class="supervisor-nav__indicator"></span>
                <?php endif; ?>
            </a>
        </section>
        <!-- ========================================================
             MANAGEMENT
        ========================================================= -->
        <section class="supervisor-nav__group">
            <span class="supervisor-nav__heading">
                <span>
                    Management
                </span>
            </span>
            <!-- COHORTS -->
            <a
                href="<?= url(
                    'supervisor/cohorts.php'
                ) ?>"
                class="
                    supervisor-nav__item
                    <?= supervisorNavActive(
                        $currentPage,
                        $cohortPages
                    ) ?>
                "
            >
                <span class="supervisor-nav__icon">
                    <i class="fas fa-layer-group"></i>
                </span>
                <span class="supervisor-nav__label">
                    My Cohorts
                </span>
                <span class="supervisor-nav__chevron">
                    <i class="fas fa-chevron-right"></i>
                </span>
            </a>
            <!-- CANDIDATES -->
            <a
                href="<?= url(
                    'supervisor/candidates.php'
                ) ?>"
                class="
                    supervisor-nav__item
                    <?= supervisorNavActive(
                        $currentPage,
                        $candidatePages
                    ) ?>
                "
            >
                <span class="supervisor-nav__icon">
                    <i class="fas fa-users"></i>
                </span>
                <span class="supervisor-nav__label">
                    Candidates
                </span>
                <span class="supervisor-nav__chevron">
                    <i class="fas fa-chevron-right"></i>
                </span>
            </a>
        </section>
        <!-- ========================================================
             REPORTING
        ========================================================= -->
        <section class="supervisor-nav__group">
            <span class="supervisor-nav__heading">
                <span>
                    Reporting
                </span>
            </span>
            <!-- COHORT PROGRESS -->
            <a
                href="<?= url(
                    'supervisor/reports.php'
                ) ?>"
                class="
                    supervisor-nav__item
                    <?= supervisorNavActive(
                        $currentPage,
                        $reportPages
                    ) ?>
                "
            >
                <span class="supervisor-nav__icon">
                    <i class="fas fa-chart-column"></i>
                </span>
                <span class="supervisor-nav__label">
                    Cohort Progress
                </span>
                <span class="supervisor-nav__chevron">
                    <i class="fas fa-chevron-right"></i>
                </span>
            </a>
            <!-- ACTIVITY -->
            <a
                href="<?= url(
                    'supervisor/activity_log.php'
                ) ?>"
                class="
                    supervisor-nav__item
                    <?= supervisorNavActive(
                        $currentPage,
                        $activityPages
                    ) ?>
                "
            >
                <span class="supervisor-nav__icon">
                    <i class="fas fa-clock-rotate-left"></i>
                </span>
                <span class="supervisor-nav__label">
                    Activity Log
                </span>
                <span class="supervisor-nav__chevron">
                    <i class="fas fa-chevron-right"></i>
                </span>
            </a>
        </section>
        <!-- ========================================================
             APPEARANCE
        ========================================================= -->
        <section class="supervisor-nav__group">
            <span class="supervisor-nav__heading">
                <span>
                    Appearance
                </span>
            </span>
            <button
                type="button"
                class="
                    supervisor-nav__item
                    supervisor-theme-toggle
                "
                id="supervisorDarkToggle"
                aria-label="Toggle dark mode"
                aria-pressed="false"
            >
                <span class="supervisor-nav__icon">
                    <i
                        class="fas fa-moon"
                        id="supervisorDarkIcon"
                    ></i>
                </span>
                <span class="supervisor-nav__label">
                    <span class="supervisor-theme-toggle__title">
                        Dark Mode
                    </span>
                    <small>
                        Change appearance
                    </small>
                </span>
                <span class="supervisor-theme-switch">
                    <span
                        class="supervisor-theme-switch__thumb"
                    ></span>
                </span>
            </button>
        </section>
    </nav>
    <!-- ============================================================
         LOWER AREA
    ============================================================= -->
    <div class="supervisor-sidebar__bottom">
        <!-- SECURITY -->
        <div class="supervisor-security">
            <div class="supervisor-security__icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div class="supervisor-security__copy">
                <strong>
                    Secure Access
                </strong>
                <p>
                    Data is restricted to your assigned cohorts.
                </p>
            </div>
            <span
                class="supervisor-security__status"
                title="Secure session"
            ></span>
        </div>
        <!-- LOGOUT -->
        <a
            href="<?= url(
                'auth/logout.php'
            ) ?>"
            class="supervisor-logout"
        >
            <span class="supervisor-logout__icon">
                <i class="fas fa-arrow-right-from-bracket"></i>
            </span>
            <span class="supervisor-logout__copy">
                <strong>
                    Sign Out
                </strong>
                <small>
                    End your session
                </small>
            </span>
            <i class="fas fa-chevron-right supervisor-logout__arrow"></i>
        </a>
    </div>
</aside>
<!-- ================================================================
     MOBILE OVERLAY
================================================================ -->
<div
    class="supervisor-sidebar-overlay"
    id="supervisorSidebarOverlay"
></div>
<style>
/* ================================================================
   SUPERVISOR DESIGN TOKENS
================================================================ */
:root {
    --sv-page-bg:
        #f6f8fc;
    --sv-card-bg:
        #ffffff;
    --sv-card-soft:
        #f8fafc;
    --sv-text:
        #101828;
    --sv-text-soft:
        #667085;
    --sv-border:
        #e4e7ec;
    --sv-primary:
        #2563eb;
    --sv-primary-soft:
        #eff6ff;
    --sv-shadow:
        0 12px 32px
        rgba(16,24,40,.07);
    --sv-sidebar-width:
        272px;
    --sv-sidebar-bg:
        #0c1425;
    --sv-sidebar-bg-2:
        #111c31;
    --sv-sidebar-text:
        #d0d5dd;
    --sv-sidebar-soft:
        #98a2b3;
    --sv-sidebar-border:
        rgba(255,255,255,.065);
    --sv-sidebar-hover:
        rgba(255,255,255,.055);
    --sv-sidebar-active:
        rgba(59,130,246,.14);
}
/* ================================================================
   DARK MODE
================================================================ */
html[data-theme="dark"] {
    --sv-page-bg:
        #08111f;
    --sv-card-bg:
        #101828;
    --sv-card-soft:
        #182230;
    --sv-text:
        #f9fafb;
    --sv-text-soft:
        #98a2b3;
    --sv-border:
        rgba(255,255,255,.08);
    --sv-primary:
        #60a5fa;
    --sv-primary-soft:
        rgba(59,130,246,.12);
    --sv-shadow:
        0 15px 40px
        rgba(0,0,0,.24);
    --sv-sidebar-bg:
        #060c17;
    --sv-sidebar-bg-2:
        #0c1424;
}
/* ================================================================
   GLOBAL LAYOUT
================================================================ */
html,
body {
    margin: 0;
    padding: 0;
}
body.dashboard-page {
    background:
        var(--sv-page-bg);
    color:
        var(--sv-text);
    transition:
        background .22s ease,
        color .22s ease;
}
.dashboard {
    width: 100%;
    min-height: 100vh;
    margin: 0 !important;
    padding: 0 !important;
    display: flex !important;
    gap: 0 !important;
}
.dashboard__main {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    width: auto !important;
    margin: 0 !important;
    background:
        var(--sv-page-bg);
}
/* ================================================================
   SIDEBAR
================================================================ */
.supervisor-sidebar {
    width:
        var(--sv-sidebar-width);
    min-width:
        var(--sv-sidebar-width);
    flex:
        0 0
        var(--sv-sidebar-width);
    height: 100vh;
    position: sticky;
    top: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    color:
        var(--sv-sidebar-text);
    background:
        radial-gradient(
            circle at 0 0,
            rgba(59,130,246,.15),
            transparent 30%
        ),
        radial-gradient(
            circle at 100% 25%,
            rgba(124,58,237,.08),
            transparent 30%
        ),
        linear-gradient(
            180deg,
            var(--sv-sidebar-bg),
            var(--sv-sidebar-bg-2)
        );
    border-right:
        1px solid
        var(--sv-sidebar-border);
    box-shadow:
        6px 0 28px
        rgba(2,6,23,.06);
    z-index: 1000;
}
/* subtle top accent */
.supervisor-sidebar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 22px;
    right: 22px;
    height: 2px;
    border-radius:
        0 0 999px 999px;
    background:
        linear-gradient(
            90deg,
            transparent,
            #3b82f6,
            #8b5cf6,
            transparent
        );
}
/* ================================================================
   BRAND
================================================================ */
.supervisor-sidebar__brand {
    min-height: 79px;
    padding:
        1rem 1rem
        .95rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .7rem;
    border-bottom:
        1px solid
        var(--sv-sidebar-border);
}
.supervisor-sidebar__brand-link {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: .72rem;
    text-decoration: none;
}
.supervisor-sidebar__logo {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    padding: 2px;
    border-radius: 13px;
    background:
        linear-gradient(
            135deg,
            #60a5fa,
            #4f46e5,
            #8b5cf6
        );
    box-shadow:
        0 8px 22px
        rgba(37,99,235,.27);
}
.supervisor-sidebar__logo-ring {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background:
        #111b30;
    color: #ffffff;
    font-size: .9rem;
}
.supervisor-sidebar__brand-copy {
    min-width: 0;
}
.supervisor-sidebar__brand-copy strong {
    display: block;
    overflow: hidden;
    color: #ffffff;
    font-size: .87rem;
    font-weight: 800;
    letter-spacing: -.01em;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.supervisor-sidebar__brand-copy span {
    display: block;
    margin-top: .16rem;
    overflow: hidden;
    color:
        #7f8da3;
    font-size: .56rem;
    font-weight: 500;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.supervisor-sidebar__close {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    display: none;
    align-items: center;
    justify-content: center;
    border:
        1px solid
        rgba(255,255,255,.06);
    border-radius: 10px;
    background:
        rgba(255,255,255,.04);
    color: #cbd5e1;
    cursor: pointer;
}
/* ================================================================
   PROFILE
================================================================ */
.supervisor-sidebar__profile {
    position: relative;
    margin:
        .9rem .85rem .35rem;
    padding:
        .78rem;
    display: flex;
    align-items: center;
    gap: .7rem;
    border:
        1px solid
        rgba(255,255,255,.07);
    border-radius: 15px;
    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,.065),
            rgba(255,255,255,.025)
        );
}
.supervisor-sidebar__avatar {
    width: 42px;
    height: 42px;
    position: relative;
    flex: 0 0 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border:
        1px solid
        rgba(147,197,253,.20);
    border-radius: 12px;
    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.45),
            rgba(124,58,237,.34)
        );
    color: #eff6ff;
    font-size: .72rem;
    font-weight: 800;
    box-shadow:
        inset 0 1px 0
        rgba(255,255,255,.08);
}
.supervisor-sidebar__online {
    width: 9px;
    height: 9px;
    position: absolute;
    right: -2px;
    bottom: -1px;
    border:
        2px solid
        #111b30;
    border-radius: 999px;
    background: #22c55e;
}
.supervisor-sidebar__profile-details {
    min-width: 0;
    flex: 1;
}
.supervisor-sidebar__profile-details strong {
    display: block;
    overflow: hidden;
    color: #f8fafc;
    font-size: .69rem;
    font-weight: 700;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.supervisor-sidebar__role {
    margin-top: .15rem;
    display: flex;
    align-items: center;
    gap: .28rem;
    color: #60a5fa;
    font-size: .54rem;
    font-weight: 700;
}
.supervisor-sidebar__role i {
    font-size: .48rem;
}
.supervisor-sidebar__profile-details small {
    display: block;
    max-width: 155px;
    margin-top: .15rem;
    overflow: hidden;
    color: #718096;
    font-size: .51rem;
    white-space: nowrap;
    text-overflow: ellipsis;
}
/* ================================================================
   NAVIGATION
================================================================ */
.supervisor-nav {
    flex: 1;
    min-height: 0;
    padding:
        .25rem .7rem 1rem;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color:
        rgba(148,163,184,.14)
        transparent;
}
.supervisor-nav::-webkit-scrollbar {
    width: 4px;
}
.supervisor-nav::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background:
        rgba(148,163,184,.16);
}
.supervisor-nav__group {
    margin-top: .95rem;
}
.supervisor-nav__heading {
    min-height: 22px;
    padding:
        0 .65rem .35rem;
    display: flex;
    align-items: center;
    color: #536176;
    font-size: .49rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}
/* ================================================================
   NAV ITEM
================================================================ */
.supervisor-nav__item {
    width: 100%;
    min-height: 45px;
    position: relative;
    margin-bottom: .25rem;
    padding:
        .48rem .58rem;
    display: flex;
    align-items: center;
    gap: .62rem;
    border:
        1px solid
        transparent;
    border-radius: 11px;
    outline: none;
    background:
        transparent;
    color:
        #aeb9ca;
    font-family: inherit;
    font-size: .69rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition:
        background .16s ease,
        border-color .16s ease,
        color .16s ease,
        transform .16s ease;
}
.supervisor-nav__item:hover {
    background:
        var(--sv-sidebar-hover);
    color: #ffffff;
    transform:
        translateX(2px);
}
.supervisor-nav__icon {
    width: 31px;
    height: 31px;
    flex: 0 0 31px;
    display: flex;
    align-items: center;
    justify-content: center;
    border:
        1px solid
        rgba(255,255,255,.035);
    border-radius: 9px;
    background:
        rgba(255,255,255,.035);
    color: #8492a8;
    font-size: .66rem;
    transition:
        all .16s ease;
}
.supervisor-nav__item:hover
.supervisor-nav__icon {
    border-color:
        rgba(96,165,250,.10);
    background:
        rgba(59,130,246,.11);
    color: #93c5fd;
}
.supervisor-nav__label {
    min-width: 0;
    flex: 1;
    text-align: left;
}
.supervisor-nav__label small {
    display: block;
    margin-top: .07rem;
    color: #5f6d82;
    font-size: .48rem;
    font-weight: 500;
}
.supervisor-nav__chevron {
    color: #4f5f74;
    font-size: .5rem;
    transition:
        transform .16s ease,
        color .16s ease;
}
.supervisor-nav__item:hover
.supervisor-nav__chevron {
    color: #94a3b8;
    transform:
        translateX(2px);
}
/* ================================================================
   ACTIVE NAV ITEM
================================================================ */
.supervisor-nav__item--active {
    border-color:
        rgba(96,165,250,.12);
    background:
        linear-gradient(
            90deg,
            rgba(37,99,235,.20),
            rgba(99,102,241,.075)
        );
    color: #ffffff;
    box-shadow:
        inset 3px 0 0
        #3b82f6;
}
.supervisor-nav__item--active
.supervisor-nav__icon {
    border-color:
        rgba(96,165,250,.15);
    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.29),
            rgba(79,70,229,.18)
        );
    color: #bfdbfe;
}
.supervisor-nav__item--active
.supervisor-nav__chevron {
    color: #60a5fa;
}
.supervisor-nav__indicator {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #60a5fa;
    box-shadow:
        0 0 0 4px
        rgba(96,165,250,.09);
}
/* ================================================================
   DARK MODE CONTROL
================================================================ */
.supervisor-theme-toggle {
    text-align: left;
}
.supervisor-theme-switch {
    width: 35px;
    height: 20px;
    position: relative;
    flex: 0 0 35px;
    border:
        1px solid
        rgba(255,255,255,.07);
    border-radius: 999px;
    background: #344054;
    transition:
        background .18s ease;
}
.supervisor-theme-switch__thumb {
    width: 14px;
    height: 14px;
    position: absolute;
    left: 2px;
    top: 2px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow:
        0 2px 5px
        rgba(0,0,0,.3);
    transition:
        transform .2s ease;
}
html[data-theme="dark"]
.supervisor-theme-switch {
    border-color:
        rgba(96,165,250,.25);
    background: #2563eb;
}
html[data-theme="dark"]
.supervisor-theme-switch__thumb {
    transform:
        translateX(15px);
}
/* ================================================================
   LOWER AREA
================================================================ */
.supervisor-sidebar__bottom {
    padding:
        .7rem;
    border-top:
        1px solid
        var(--sv-sidebar-border);
    background:
        rgba(0,0,0,.055);
}
/* ================================================================
   SECURITY CARD
================================================================ */
.supervisor-security {
    position: relative;
    margin-bottom: .55rem;
    padding: .7rem;
    display: flex;
    align-items: flex-start;
    gap: .6rem;
    border:
        1px solid
        rgba(96,165,250,.09);
    border-radius: 12px;
    background:
        rgba(37,99,235,.055);
}
.supervisor-security__icon {
    width: 29px;
    height: 29px;
    flex: 0 0 29px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background:
        rgba(59,130,246,.11);
    color: #60a5fa;
    font-size: .59rem;
}
.supervisor-security__copy {
    min-width: 0;
    flex: 1;
}
.supervisor-security strong {
    display: block;
    color: #dbeafe;
    font-size: .56rem;
    font-weight: 700;
}
.supervisor-security p {
    margin:
        .15rem 0 0;
    color: #718096;
    font-size: .49rem;
    line-height: 1.45;
}
.supervisor-security__status {
    width: 6px;
    height: 6px;
    flex: 0 0 6px;
    margin-top: 3px;
    border-radius: 999px;
    background: #22c55e;
    box-shadow:
        0 0 0 3px
        rgba(34,197,94,.08);
}
/* ================================================================
   LOGOUT
================================================================ */
.supervisor-logout {
    min-height: 47px;
    padding:
        .48rem .58rem;
    display: flex;
    align-items: center;
    gap: .62rem;
    border:
        1px solid
        transparent;
    border-radius: 11px;
    color: #cbd5e1;
    text-decoration: none;
    transition:
        background .16s ease,
        border-color .16s ease,
        color .16s ease;
}
.supervisor-logout:hover {
    border-color:
        rgba(239,68,68,.08);
    background:
        rgba(239,68,68,.07);
    color: #fecaca;
}
.supervisor-logout__icon {
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background:
        rgba(239,68,68,.07);
    color: #f87171;
    font-size: .62rem;
}
.supervisor-logout__copy {
    min-width: 0;
    flex: 1;
}
.supervisor-logout__copy strong {
    display: block;
    color: inherit;
    font-size: .62rem;
}
.supervisor-logout__copy small {
    display: block;
    margin-top: .08rem;
    color: #59677a;
    font-size: .47rem;
}
.supervisor-logout__arrow {
    color: #536176;
    font-size: .48rem;
}
/* ================================================================
   OVERLAY
================================================================ */
.supervisor-sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 990;
    background:
        rgba(2,6,23,.66);
    backdrop-filter:
        blur(4px);
    -webkit-backdrop-filter:
        blur(4px);
}
/* ================================================================
   GENERAL DARK-MODE SUPPORT
================================================================ */
html[data-theme="dark"]
.dashboard__main {
    background:
        var(--sv-page-bg);
}
html[data-theme="dark"]
input,
html[data-theme="dark"]
select,
html[data-theme="dark"]
textarea {
    background:
        #0f172a !important;
    color:
        #f8fafc !important;
    border-color:
        rgba(148,163,184,.18) !important;
    color-scheme: dark;
}
/* ================================================================
   MOBILE
================================================================ */
@media(max-width:900px) {
    .dashboard {
        display: block !important;
    }
    .supervisor-sidebar {
        width: 275px;
        min-width: 275px;
        height: 100dvh;
        position: fixed;
        left: 0;
        top: 0;
        transform:
            translateX(-105%);
        transition:
            transform .24s
            cubic-bezier(
                .2,
                .7,
                .2,
                1
            );
        box-shadow:
            20px 0 55px
            rgba(0,0,0,.28);
    }
    .supervisor-sidebar--open {
        transform:
            translateX(0);
    }
    .supervisor-sidebar__close {
        display: inline-flex;
    }
    .supervisor-sidebar-overlay--open {
        display: block;
    }
    .dashboard__main {
        width: 100% !important;
        margin-left: 0 !important;
    }
}
@media(max-width:420px) {
    .supervisor-sidebar {
        width: 88vw;
        min-width: 88vw;
    }
}
</style>
<script>
/* ================================================================
   SUPERVISOR SIDEBAR CONTROLLER
================================================================ */
document.addEventListener(
    'DOMContentLoaded',
    function () {
        const sidebar =
            document.getElementById(
                'supervisorSidebar'
            );
        const overlay =
            document.getElementById(
                'supervisorSidebarOverlay'
            );
        const closeButton =
            document.getElementById(
                'supervisorSidebarClose'
            );
        const themeButton =
            document.getElementById(
                'supervisorDarkToggle'
            );
        const menuButtons =
            document.querySelectorAll(
                '#sidebarToggle,' +
                '#menuToggle,' +
                '#menuButton,' +
                '.sidebar-toggle,' +
                '.menu-toggle,' +
                '[data-sidebar-toggle]'
            );
        /*
        |--------------------------------------------------------------------------
        | Open
        |--------------------------------------------------------------------------
        */
        function openSidebar() {
            if (!sidebar) {
                return;
            }
            sidebar.classList.add(
                'supervisor-sidebar--open'
            );
            overlay?.classList.add(
                'supervisor-sidebar-overlay--open'
            );
            document.body.style.overflow =
                'hidden';
        }
        /*
        |--------------------------------------------------------------------------
        | Close
        |--------------------------------------------------------------------------
        */
        function closeSidebar() {
            sidebar?.classList.remove(
                'supervisor-sidebar--open'
            );
            overlay?.classList.remove(
                'supervisor-sidebar-overlay--open'
            );
            document.body.style.overflow =
                '';
        }
        /*
        |--------------------------------------------------------------------------
        | Mobile Navbar Button
        |--------------------------------------------------------------------------
        */
        menuButtons.forEach(
            function (button) {
                button.addEventListener(
                    'click',
                    function () {
                        if (!sidebar) {
                            return;
                        }
                        if (
                            sidebar.classList.contains(
                                'supervisor-sidebar--open'
                            )
                        ) {
                            closeSidebar();
                        } else {
                            openSidebar();
                        }
                    }
                );
            }
        );
        /*
        |--------------------------------------------------------------------------
        | Close Controls
        |--------------------------------------------------------------------------
        */
        closeButton?.addEventListener(
            'click',
            closeSidebar
        );
        overlay?.addEventListener(
            'click',
            closeSidebar
        );
        /*
        |--------------------------------------------------------------------------
        | ESC
        |--------------------------------------------------------------------------
        */
        document.addEventListener(
            'keydown',
            function (event) {
                if (
                    event.key
                    === 'Escape'
                ) {
                    closeSidebar();
                }
            }
        );
        /*
        |--------------------------------------------------------------------------
        | Auto Close After Navigation On Mobile
        |--------------------------------------------------------------------------
        */
        const navLinks =
            sidebar?.querySelectorAll(
                'a.supervisor-nav__item'
            )
            ?? [];
        navLinks.forEach(
            function (link) {
                link.addEventListener(
                    'click',
                    function () {
                        if (
                            window.innerWidth
                            <= 900
                        ) {
                            closeSidebar();
                        }
                    }
                );
            }
        );
        /*
        |--------------------------------------------------------------------------
        | Dark Mode
        |--------------------------------------------------------------------------
        |
        | Navbar owns the shared theme controller.
        | Sidebar calls it rather than maintaining a second theme system.
        |
        */
        if (
            themeButton
            &&
            !themeButton
                .dataset
                .sidebarThemeBound
        ) {
            themeButton
                .dataset
                .sidebarThemeBound =
                '1';
            themeButton.addEventListener(
                'click',
                function (event) {
                    event.preventDefault();
                    /*
                     * Preferred shared navbar controller
                     */
                    if (
                        typeof window
                            .toggleSupervisorTheme
                        === 'function'
                    ) {
                        window
                            .toggleSupervisorTheme();
                        return;
                    }
                    /*
                     * Safe fallback in case navbar
                     * has not loaded.
                     */
                    const current =
                        document.documentElement
                            .getAttribute(
                                'data-theme'
                            );
                    const next =
                        current === 'dark'
                            ? 'light'
                            : 'dark';
                    document.documentElement
                        .setAttribute(
                            'data-theme',
                            next
                        );
                    try {
                        localStorage.setItem(
                            'investhood-supervisor-theme',
                            next
                        );
                    } catch (error) {
                        console.warn(
                            'Unable to store theme.'
                        );
                    }
                }
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Resize Cleanup
        |--------------------------------------------------------------------------
        */
        window.addEventListener(
            'resize',
            function () {
                if (
                    window.innerWidth
                    > 900
                ) {
                    closeSidebar();
                }
            }
        );
    }
);
</script>