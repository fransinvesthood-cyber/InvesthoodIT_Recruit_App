<?php
/**
 * ================================================================
 * INVESTHOOD IT - SUPERVISOR NAVBAR
 * ================================================================
 *
 * Shared Supervisor top navigation.
 *
 * Expected variables:
 *  - $user
 *  - $currentPage
 *
 * Can safely derive missing user information.
 * ================================================================
 */
require_once __DIR__ . '/notification_helpers.php';

$currentPage = $currentPage ?? '';
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
$navbarFirstName = trim(
    (string)(
        $user['first_name']
        ?? ''
    )
);
$navbarLastName = trim(
    (string)(
        $user['last_name']
        ?? ''
    )
);
$navbarFullName = trim(
    (string)(
        $user['fullname']
        ?? $user['full_name']
        ?? (
            $navbarFirstName .
            ' ' .
            $navbarLastName
        )
    )
);
if ($navbarFullName === '') {
    $navbarFullName = 'Supervisor';
}
/*
|--------------------------------------------------------------------------
| Initials
|--------------------------------------------------------------------------
*/
$navbarInitials = '';
if ($navbarFirstName !== '') {
    $navbarInitials .= substr(
        $navbarFirstName,
        0,
        1
    );
}
if ($navbarLastName !== '') {
    $navbarInitials .= substr(
        $navbarLastName,
        0,
        1
    );
}
if ($navbarInitials === '') {
    $navbarInitials = 'S';
}
$navbarInitials = strtoupper(
    $navbarInitials
);

/*
|--------------------------------------------------------------------------
| Notification Session / CSRF
|--------------------------------------------------------------------------
*/

if (
    session_status()
    !== PHP_SESSION_ACTIVE
) {
    session_start();
}

if (
    empty(
        $_SESSION[
            'supervisor_notification_csrf'
        ]
    )
) {

    $_SESSION[
        'supervisor_notification_csrf'
    ] = bin2hex(
        random_bytes(32)
    );
}

$notificationCsrfToken =
    $_SESSION[
        'supervisor_notification_csrf'
    ];

/*
|--------------------------------------------------------------------------
| Supervisor ID
|--------------------------------------------------------------------------
*/

$navbarSupervisorId = (int)(
    $user['id']
    ?? $user['user_id']
    ?? 0
);

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$navbarUnreadNotifications = 0;
$navbarNotifications = [];

if ($navbarSupervisorId > 0) {

    $navbarUnreadNotifications =
        getSupervisorUnreadNotificationCount(
            $navbarSupervisorId
        );

    $navbarNotifications =
        getSupervisorNotifications(
            $navbarSupervisorId,
            8
        );
}
/*
|--------------------------------------------------------------------------
| Page Titles
|--------------------------------------------------------------------------
*/
$pageTitles = [
    'dashboard' => [
        'title' => 'Dashboard',
        'eyebrow' => 'Supervisor Portal'
    ],
    'cohorts' => [
        'title' => 'My Cohorts',
        'eyebrow' => 'Cohort Management'
    ],
    'cohort_view' => [
        'title' => 'Cohort Details',
        'eyebrow' => 'Cohort Management'
    ],
    'candidates' => [
        'title' => 'Candidates',
        'eyebrow' => 'Candidate Management'
    ],
    'candidate_view' => [
        'title' => 'Candidate Profile',
        'eyebrow' => 'Candidate Management'
    ],
    'update_candidate_status' => [
        'title' => 'Update Candidate Status',
        'eyebrow' => 'Candidate Management'
    ],
    'activity_log' => [
    'title' => 'Activity Log',
    'eyebrow' => 'Audit & Accountability'
    ],
];
$pageConfig =
    $pageTitles[$currentPage]
    ?? [
        'title' => 'Supervisor',
        'eyebrow' => 'Supervisor Portal'
    ];
$navbarTitle =
    $pageConfig['title'];
$navbarEyebrow =
    $pageConfig['eyebrow'];
?>
<style>
/* ================================================================
   SHARED SUPERVISOR NAVBAR
================================================================ */
.supervisor-navbar {
    min-height: 78px;
    position: sticky;
    top: 0;
    z-index: 900;
    padding:
        .75rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background:
        rgba(255,255,255,.88);
    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);
    backdrop-filter:
        blur(18px);
    -webkit-backdrop-filter:
        blur(18px);
    transition:
        background .25s ease,
        border-color .25s ease;
}
html[data-theme="dark"]
.supervisor-navbar {
    background:
        rgba(15,23,42,.90);
}
/* ================================================================
   LEFT
================================================================ */
.supervisor-navbar__left {
    display: flex;
    align-items: center;
    gap: .8rem;
    min-width: 0;
}
.supervisor-navbar__menu {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: none;
    align-items: center;
    justify-content: center;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 12px;
    background:
        var(--sv-card-bg, #fff);
    color:
        var(--sv-text, #111827);
    cursor: pointer;
    transition:
        transform .18s ease,
        border-color .18s ease,
        background .18s ease;
}
.supervisor-navbar__menu:hover {
    transform:
        translateY(-1px);
    border-color:
        rgba(37,99,235,.35);
}
.supervisor-navbar__heading {
    min-width: 0;
}
.supervisor-navbar__eyebrow {
    display: block;
    margin-bottom: .12rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .58rem;
    font-weight: 800;
    letter-spacing: .09em;
    text-transform: uppercase;
}
.supervisor-navbar__title {
    margin: 0;
    overflow: hidden;
    color:
        var(--sv-text, #111827);
    font-size: 1.16rem;
    font-weight: 800;
    white-space: nowrap;
    text-overflow: ellipsis;
}
/* ================================================================
   RIGHT
================================================================ */
.supervisor-navbar__right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: .65rem;
}
/* ================================================================
   CANDIDATE SEARCH SHORTCUT
================================================================ */
.supervisor-navbar__search {
    min-height: 41px;
    min-width: 205px;
    padding:
        .55rem .8rem;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 12px;
    background:
        var(--sv-card-soft, #f8fafc);
    color:
        var(--sv-text-soft, #64748b);
    text-decoration: none;
    font-size: .69rem;
    transition:
        border-color .18s ease,
        transform .18s ease,
        color .18s ease;
}
.supervisor-navbar__search:hover {
    transform:
        translateY(-1px);
    border-color:
        rgba(37,99,235,.30);
    color:
        var(--sv-primary, #2563eb);
}
/* ================================================================
   ICON BUTTONS
================================================================ */
.supervisor-navbar__icon {
    width: 41px;
    height: 41px;
    position: relative;
    flex: 0 0 41px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border:
        1px solid
        var(--sv-border, #e5e7eb);
    border-radius: 12px;
    background:
        var(--sv-card-bg, #fff);
    color:
        var(--sv-text, #111827);
    cursor: pointer;
    transition:
        transform .18s ease,
        border-color .18s ease,
        background .18s ease;
}
.supervisor-navbar__icon:hover {
    transform:
        translateY(-2px);
    border-color:
        rgba(37,99,235,.32);
}
/* ================================================================
   NOTIFICATION
================================================================ */
.supervisor-navbar__notification-dot {
    width: 7px;
    height: 7px;
    position: absolute;
    top: 8px;
    right: 8px;
    border-radius: 50%;
    background: #ef4444;
    border:
        2px solid
        var(--sv-card-bg, #fff);
}
/* ================================================================
   PROFILE
================================================================ */
.supervisor-navbar__profile {
    min-height: 44px;
    margin-left: .1rem;
    padding:
        .25rem .35rem .25rem .25rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    border-radius: 13px;
    transition:
        background .18s ease;
}
.supervisor-navbar__profile:hover {
    background:
        var(--sv-card-soft, #f8fafc);
}
.supervisor-navbar__avatar {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );
    color: #fff;
    font-size: .68rem;
    font-weight: 800;
    box-shadow:
        0 6px 16px
        rgba(37,99,235,.17);
}
.supervisor-navbar__online {
    width: 9px;
    height: 9px;
    position: absolute;
    right: -1px;
    bottom: -1px;
    border-radius: 50%;
    background: #22c55e;
    border:
        2px solid
        var(--sv-card-bg, #fff);
}
.supervisor-navbar__profile-copy {
    min-width: 0;
}
.supervisor-navbar__profile-copy strong {
    display: block;
    max-width: 145px;
    overflow: hidden;
    color:
        var(--sv-text, #111827);
    font-size: .69rem;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.supervisor-navbar__profile-copy span {
    display: block;
    margin-top: .1rem;
    color:
        var(--sv-text-soft, #64748b);
    font-size: .57rem;
}
/* ================================================================
   DARK MODE
================================================================ */
html[data-theme="dark"]
.supervisor-navbar__search,
html[data-theme="dark"]
.supervisor-navbar__profile:hover {
    background:
        rgba(255,255,255,.035);
}
/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:1050px) {
    .supervisor-navbar__search {
        min-width: 170px;
    }
}
@media(max-width:900px) {
    .supervisor-navbar__menu {
        display: inline-flex;
    }
}
@media(max-width:700px) {
    .supervisor-navbar {
        min-height: 70px;
        padding:
            .65rem 1rem;
    }
    .supervisor-navbar__search {
        display: none;
    }
    .supervisor-navbar__profile-copy {
        display: none;
    }
    .supervisor-navbar__profile {
        padding: 0;
    }
}
@media(max-width:420px) {
    .supervisor-navbar__eyebrow {
        display: none;
    }
    .supervisor-navbar__title {
        font-size: .95rem;
    }
    .supervisor-navbar__right {
        gap: .4rem;
    }
    .supervisor-navbar__icon {
        width: 38px;
        height: 38px;
        flex-basis: 38px;
    }
    .supervisor-navbar__avatar {
        width: 38px;
        height: 38px;
        flex-basis: 38px;
    }
}
/* ================================================================
   SUPERVISOR NOTIFICATION CENTRE
================================================================ */

.supervisor-notification {
    position: relative;
}

/* BADGE */

.supervisor-notification__badge {
    min-width: 17px;
    height: 17px;

    position: absolute;

    top: -5px;
    right: -5px;

    padding: 0 4px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 999px;

    background: #ef4444;

    border:
        2px solid
        var(--sv-card-bg, #fff);

    color: #fff;

    font-size: .5rem;

    font-weight: 800;

    line-height: 1;
}

/* ================================================================
   DROPDOWN
================================================================ */

.supervisor-notification__dropdown {
    width: 390px;

    max-height: 570px;

    position: absolute;

    top: calc(100% + 13px);
    right: 0;

    z-index: 1100;

    overflow: hidden;

    border:
        1px solid
        var(--sv-border, #e5e7eb);

    border-radius: 18px;

    background:
        var(--sv-card-bg, #fff);

    box-shadow:
        0 22px 55px
        rgba(15,23,42,.18);

    opacity: 0;

    visibility: hidden;

    transform:
        translateY(-8px)
        scale(.98);

    transform-origin:
        top right;

    transition:
        opacity .16s ease,
        visibility .16s ease,
        transform .16s ease;
}

.supervisor-notification__dropdown.is-open {
    opacity: 1;

    visibility: visible;

    transform:
        translateY(0)
        scale(1);
}

/* ================================================================
   HEADER
================================================================ */

.supervisor-notification__header {
    min-height: 70px;

    padding:
        .9rem 1rem;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 1rem;

    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);
}

.supervisor-notification__header strong {
    display: block;

    color:
        var(--sv-text, #111827);

    font-size: .78rem;
}

.supervisor-notification__header span {
    display: block;

    margin-top: .15rem;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .6rem;
}

.supervisor-notification__mark-all {
    padding: .4rem .55rem;

    border: 0;

    border-radius: 8px;

    background:
        var(--sv-primary-soft, #eff6ff);

    color:
        var(--sv-primary, #2563eb);

    font-family: inherit;

    font-size: .58rem;

    font-weight: 700;

    cursor: pointer;
}

/* ================================================================
   LIST
================================================================ */

.supervisor-notification__list {
    max-height: 475px;

    overflow-y: auto;
}

.supervisor-notification__item-form {
    margin: 0;
}

.supervisor-notification__item {
    width: 100%;

    padding:
        .85rem 1rem;

    display: flex;

    align-items: flex-start;

    gap: .75rem;

    border: 0;

    border-bottom:
        1px solid
        var(--sv-border, #e5e7eb);

    background:
        var(--sv-card-bg, #fff);

    color: inherit;

    font-family: inherit;

    text-align: left;

    cursor: pointer;

    transition:
        background .15s ease;
}

.supervisor-notification__item:hover {
    background:
        var(--sv-card-soft, #f8fafc);
}

.supervisor-notification__item--unread {
    background:
        rgba(37,99,235,.045);
}

.supervisor-notification__item-form:last-child
.supervisor-notification__item {
    border-bottom: 0;
}

/* ================================================================
   TYPE ICON
================================================================ */

.supervisor-notification__type-icon {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 11px;

    font-size: .7rem;
}

.supervisor-notification__type-icon--info {
    background:
        rgba(59,130,246,.11);

    color: #2563eb;
}

.supervisor-notification__type-icon--candidate {
    background:
        rgba(124,58,237,.10);

    color: #7c3aed;
}

.supervisor-notification__type-icon--cohort {
    background:
        rgba(6,182,212,.10);

    color: #0891b2;
}

.supervisor-notification__type-icon--success {
    background:
        rgba(34,197,94,.10);

    color: #16a34a;
}

.supervisor-notification__type-icon--warning {
    background:
        rgba(245,158,11,.11);

    color: #d97706;
}

.supervisor-notification__type-icon--danger {
    background:
        rgba(239,68,68,.10);

    color: #dc2626;
}

/* ================================================================
   CONTENT
================================================================ */

.supervisor-notification__content {
    min-width: 0;

    flex: 1;
}

.supervisor-notification__title {
    display: flex;

    align-items: center;

    gap: .4rem;

    color:
        var(--sv-text, #111827);

    font-size: .67rem;

    font-weight: 700;
}

.supervisor-notification__unread-dot {
    width: 6px;
    height: 6px;

    flex: 0 0 6px;

    margin: 0;

    border-radius: 50%;

    background: #2563eb;
}

.supervisor-notification__message {
    display: -webkit-box;

    margin-top: .25rem;

    overflow: hidden;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .6rem;

    font-weight: 400;

    line-height: 1.5;

    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.supervisor-notification__time {
    margin-top: .4rem !important;

    display: flex !important;

    align-items: center;

    gap: .28rem;

    color:
        var(--sv-text-soft, #64748b) !important;

    font-size: .54rem !important;
}

/* ================================================================
   EMPTY
================================================================ */

.supervisor-notification__empty {
    padding:
        2.6rem 1.2rem;

    text-align: center;
}

.supervisor-notification__empty-icon {
    width: 57px;
    height: 57px;

    margin:
        0 auto .8rem;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 16px;

    background:
        var(--sv-primary-soft, #eff6ff);

    color:
        var(--sv-primary, #2563eb);

    font-size: 1.1rem;
}

.supervisor-notification__empty strong {
    display: block;

    color:
        var(--sv-text, #111827);

    font-size: .72rem;
}

.supervisor-notification__empty span {
    display: block;

    max-width: 260px;

    margin:
        .35rem auto 0;

    color:
        var(--sv-text-soft, #64748b);

    font-size: .59rem;

    line-height: 1.5;
}

/* ================================================================
   DARK MODE
================================================================ */

html[data-theme="dark"]
.supervisor-notification__dropdown {
    box-shadow:
        0 25px 60px
        rgba(0,0,0,.42);
}

html[data-theme="dark"]
.supervisor-notification__item--unread {
    background:
        rgba(59,130,246,.065);
}

/* ================================================================
   MOBILE
================================================================ */

@media(max-width:520px) {

    .supervisor-notification__dropdown {
        width:
            min(
                calc(100vw - 24px),
                390px
            );

        position: fixed;

        top: 76px;
        right: 12px;
    }

}
</style>
<!-- ================================================================
     NAVBAR
================================================================ -->
<header class="supervisor-navbar">
    <!-- LEFT -->
    <div class="supervisor-navbar__left">
        <!-- MOBILE SIDEBAR BUTTON -->
        <button
            type="button"
            class="supervisor-navbar__menu"
            id="sidebarToggle"
            aria-label="Open navigation"
            title="Open menu"
        >
            <i class="fas fa-bars"></i>
        </button>
        <!-- PAGE TITLE -->
        <div class="supervisor-navbar__heading">
            <span class="supervisor-navbar__eyebrow">
                <?= e(
                    $navbarEyebrow
                ) ?>
            </span>
            <h1 class="supervisor-navbar__title">
                <?= e(
                    $navbarTitle
                ) ?>
            </h1>
        </div>
    </div>
    <!-- RIGHT -->
    <div class="supervisor-navbar__right">
        <!-- ========================================================
             SEARCH
        ========================================================= -->
        <a
            href="<?= url(
                'supervisor/candidates.php'
            ) ?>"
            class="supervisor-navbar__search"
            title="Search candidates"
        >
            <i class="fas fa-magnifying-glass"></i>
            <span>
                Search candidates
            </span>
        </a>
        <!-- ========================================================
             DARK MODE
        ========================================================= -->
        <button
            type="button"
            class="supervisor-navbar__icon"
            id="navbarDarkToggle"
            aria-label="Toggle dark mode"
            title="Toggle dark mode"
        >
            <i
                class="fas fa-moon"
                id="navbarDarkIcon"
            ></i>
        </button>
        <!-- ========================================================
             NOTIFICATIONS
        ========================================================= -->
<div
    class="supervisor-notification"
    id="supervisorNotification"
>

    <button
        type="button"
        class="supervisor-navbar__icon"
        id="supervisorNotificationButton"
        aria-label="Notifications"
        aria-expanded="false"
        title="Notifications"
    >

        <i class="fas fa-bell"></i>

        <?php if (
            $navbarUnreadNotifications > 0
        ): ?>

            <span
                class="supervisor-notification__badge"
                id="supervisorNotificationBadge"
            >

                <?= $navbarUnreadNotifications > 99
                    ? '99+'
                    : (int)$navbarUnreadNotifications ?>

            </span>

        <?php endif; ?>

    </button>

    <!-- ============================================================
         DROPDOWN
    ============================================================= -->

    <div
        class="supervisor-notification__dropdown"
        id="supervisorNotificationDropdown"
        aria-hidden="true"
    >

        <!-- HEADER -->

        <div class="supervisor-notification__header">

            <div>

                <strong>
                    Notifications
                </strong>

                <span>

                    <?php if (
                        $navbarUnreadNotifications > 0
                    ): ?>

                        <?= number_format(
                            $navbarUnreadNotifications
                        ) ?>

                        unread

                    <?php else: ?>

                        You're all caught up

                    <?php endif; ?>

                </span>

            </div>

            <?php if (
                $navbarUnreadNotifications > 0
            ): ?>

                <form
                    method="POST"
                    action="<?= url(
                        'supervisor/notification_action.php'
                    ) ?>"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(
                            $notificationCsrfToken
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="mark_all_read"
                    >

                    <input
                        type="hidden"
                        name="return_page"
                        value="<?= e(
                            'supervisor/' .
                            (
                                $currentPage === 'dashboard'
                                    ? 'dashboard'
                                    : (
                                        in_array(
                                            $currentPage,
                                            [
                                                'cohorts',
                                                'cohort_view'
                                            ],
                                            true
                                        )
                                            ? 'cohorts'
                                            : 'candidates'
                                    )
                            ) .
                            '.php'
                        ) ?>"
                    >

                    <button
                        type="submit"
                        class="supervisor-notification__mark-all"
                    >

                        Mark all read

                    </button>

                </form>

            <?php endif; ?>

        </div>

        <!-- NOTIFICATION LIST -->

        <div class="supervisor-notification__list">

            <?php if (
                empty($navbarNotifications)
            ): ?>

                <div class="supervisor-notification__empty">

                    <div class="supervisor-notification__empty-icon">

                        <i class="far fa-bell"></i>

                    </div>

                    <strong>
                        No notifications
                    </strong>

                    <span>
                        New candidate and cohort updates will appear here.
                    </span>

                </div>

            <?php else: ?>

                <?php foreach (
                    $navbarNotifications
                    as $notification
                ): ?>

                    <?php

                    $notificationType =
                        supervisorNotificationTypeClass(
                            (string)(
                                $notification['type']
                                ?? 'info'
                            )
                        );

                    $isUnread =
                        (int)(
                            $notification['is_read']
                            ?? 0
                        ) === 0;

                    ?>

                    <form
                        method="POST"
                        action="<?= url(
                            'supervisor/notification_action.php'
                        ) ?>"
                        class="
                            supervisor-notification__item-form
                        "
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(
                                $notificationCsrfToken
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="mark_read"
                        >

                        <input
                            type="hidden"
                            name="notification_id"
                            value="<?= (int)(
                                $notification['id']
                                ?? 0
                            ) ?>"
                        >

                        <button
                            type="submit"
                            class="
                                supervisor-notification__item
                                <?= $isUnread
                                    ? 'supervisor-notification__item--unread'
                                    : '' ?>
                            "
                        >

                            <span
                                class="
                                    supervisor-notification__type-icon
                                    supervisor-notification__type-icon--<?= e(
                                        $notificationType
                                    ) ?>
                                "
                            >

                                <i
                                    class="fas <?= e(
                                        supervisorNotificationIcon(
                                            $notificationType
                                        )
                                    ) ?>"
                                ></i>

                            </span>

                            <span class="supervisor-notification__content">

                                <span class="supervisor-notification__title">

                                    <?= e(
                                        $notification['title']
                                        ?? ''
                                    ) ?>

                                    <?php if (
                                        $isUnread
                                    ): ?>

                                        <span
                                            class="supervisor-notification__unread-dot"
                                        ></span>

                                    <?php endif; ?>

                                </span>

                                <span class="supervisor-notification__message">

                                    <?= e(
                                        $notification['message']
                                        ?? ''
                                    ) ?>

                                </span>

                                <span class="supervisor-notification__time">

                                    <i class="far fa-clock"></i>

                                    <?= e(
                                        supervisorNotificationTimeAgo(
                                            $notification[
                                                'created_at'
                                            ]
                                            ?? null
                                        )
                                    ) ?>

                                </span>

                            </span>

                        </button>

                    </form>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>
        <!-- ========================================================
             PROFILE
        ========================================================= -->
        <div class="supervisor-navbar__profile">
            <div class="supervisor-navbar__avatar">
                <?= e(
                    $navbarInitials
                ) ?>
                <span
                    class="supervisor-navbar__online"
                    title="Online"
                ></span>
            </div>
            <div class="supervisor-navbar__profile-copy">
                <strong>
                    <?= e(
                        $navbarFullName
                    ) ?>
                </strong>
                <span>
                    Supervisor
                </span>
            </div>
        </div>
    </div>
</header>
<script>
/* ================================================================
   SHARED SUPERVISOR DARK MODE
================================================================ */

(function () {

    const STORAGE_KEY = 'investhood-supervisor-theme';

    /*
    |--------------------------------------------------------------------------
    | Read Theme
    |--------------------------------------------------------------------------
    */

    function getCurrentTheme() {

        return document.documentElement
            .getAttribute('data-theme') === 'dark'
                ? 'dark'
                : 'light';
    }

    /*
    |--------------------------------------------------------------------------
    | Update Icons
    |--------------------------------------------------------------------------
    */

    function updateSupervisorThemeIcons() {

        const isDark =
            getCurrentTheme() === 'dark';

        /*
        |--------------------------------------------------------------------------
        | Navbar icon
        |--------------------------------------------------------------------------
        */

        const navbarIcon =
            document.getElementById(
                'navbarDarkIcon'
            );

        if (navbarIcon) {

            navbarIcon.className =
                isDark
                    ? 'fas fa-sun'
                    : 'fas fa-moon';
        }

        /*
        |--------------------------------------------------------------------------
        | Sidebar icon
        |--------------------------------------------------------------------------
        */

        const sidebarIcon =
            document.getElementById(
                'supervisorDarkIcon'
            );

        if (sidebarIcon) {

            sidebarIcon.className =
                isDark
                    ? 'fas fa-sun'
                    : 'fas fa-moon';
        }

        /*
        |--------------------------------------------------------------------------
        | Sidebar toggle/switch state
        |--------------------------------------------------------------------------
        */

        const sidebarToggle =
            document.getElementById(
                'supervisorDarkToggle'
            );

        if (sidebarToggle) {

            sidebarToggle.setAttribute(
                'aria-pressed',
                isDark
                    ? 'true'
                    : 'false'
            );

            sidebarToggle.classList.toggle(
                'is-active',
                isDark
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Navbar accessibility
        |--------------------------------------------------------------------------
        */

        const navbarButton =
            document.getElementById(
                'navbarDarkToggle'
            );

        if (navbarButton) {

            const label =
                isDark
                    ? 'Switch to light mode'
                    : 'Switch to dark mode';

            navbarButton.title =
                label;

            navbarButton.setAttribute(
                'aria-label',
                label
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Set Theme
    |--------------------------------------------------------------------------
    */

    function setSupervisorTheme(theme) {

        const normalizedTheme =
            theme === 'dark'
                ? 'dark'
                : 'light';

        document.documentElement
            .setAttribute(
                'data-theme',
                normalizedTheme
            );

        try {

            localStorage.setItem(
                STORAGE_KEY,
                normalizedTheme
            );

        } catch (error) {

            console.warn(
                'Unable to save Supervisor theme.',
                error
            );
        }

        updateSupervisorThemeIcons();
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Theme
    |--------------------------------------------------------------------------
    */

    function toggleSupervisorTheme() {

        const nextTheme =
            getCurrentTheme() === 'dark'
                ? 'light'
                : 'dark';

        setSupervisorTheme(
            nextTheme
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Expose Shared Functions
    |--------------------------------------------------------------------------
    */

    window.setSupervisorTheme =
        setSupervisorTheme;

    window.toggleSupervisorTheme =
        toggleSupervisorTheme;

    /*
    |--------------------------------------------------------------------------
    | Restore Saved Theme Immediately
    |--------------------------------------------------------------------------
    */

    let savedTheme = 'light';

    try {

        savedTheme =
            localStorage.getItem(
                STORAGE_KEY
            ) || 'light';

    } catch (error) {

        savedTheme = 'light';
    }

    document.documentElement
        .setAttribute(
            'data-theme',
            savedTheme === 'dark'
                ? 'dark'
                : 'light'
        );

    /*
    |--------------------------------------------------------------------------
    | Bind Navbar Button
    |--------------------------------------------------------------------------
    */

    function initializeSupervisorTheme() {

        updateSupervisorThemeIcons();

        const navbarButton =
            document.getElementById(
                'navbarDarkToggle'
            );

        if (navbarButton) {

            /*
            |--------------------------------------------------------------------------
            | Remove our previous handler if navbar was loaded twice
            |--------------------------------------------------------------------------
            */

            if (
                window
                    .supervisorNavbarDarkHandler
            ) {

                navbarButton
                    .removeEventListener(
                        'click',
                        window
                            .supervisorNavbarDarkHandler,
                        true
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Capture mode prevents old page handlers from toggling it again
            |--------------------------------------------------------------------------
            */

            window.supervisorNavbarDarkHandler =
                function (event) {

                    event.preventDefault();

                    /*
                     * Prevent duplicated old dark-mode handlers
                     * from firing after this one.
                     */
                    event.stopImmediatePropagation();

                    toggleSupervisorTheme();
                };

            navbarButton
                .addEventListener(
                    'click',
                    window
                        .supervisorNavbarDarkHandler,
                    true
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Sidebar Dark Toggle
        |--------------------------------------------------------------------------
        */

        const sidebarButton =
            document.getElementById(
                'supervisorDarkToggle'
            );

        if (
            sidebarButton
            &&
            !sidebarButton
                .dataset
                .sharedThemeBound
        ) {

            sidebarButton
                .dataset
                .sharedThemeBound =
                'true';

            sidebarButton
                .addEventListener(
                    'click',
                    function (event) {

                        event.preventDefault();

                        toggleSupervisorTheme();
                    }
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Initialise
    |--------------------------------------------------------------------------
    */

    if (
        document.readyState
        === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initializeSupervisorTheme,
            {
                once: true
            }
        );

    } else {

        initializeSupervisorTheme();
    }

})();
</script>