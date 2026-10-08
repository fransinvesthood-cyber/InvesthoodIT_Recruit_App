<?php

/*
|--------------------------------------------------------------------------
| Programme Officer Navbar
|--------------------------------------------------------------------------
| This file is intended to be included from the Programme Officer layout.
|
| It safely initialises:
| - $user
| - $conn
| - $currentPage
| - notifications
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/bootstrap.php';


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
|
| The dashboard normally creates $conn before loading the layout.
| However, this navbar can also initialise it safely if it does not
| already exist.
|
*/

if (
    !isset($conn)
    || !($conn instanceof mysqli)
) {
    $conn = Database::getConnection();
}


/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
*/

$currentPage = $currentPage ?? '';


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

if (
    !isset($user)
    || !is_array($user)
) {
    $user = current_user();
}


/*
|--------------------------------------------------------------------------
| User Names
|--------------------------------------------------------------------------
*/

$firstName = trim(
    (string) (
        $user['first_name']
        ?? ''
    )
);

$lastName = trim(
    (string) (
        $user['last_name']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| Full Name
|--------------------------------------------------------------------------
*/

$name = trim(
    (string) (
        $user['full_name']
        ?? $user['fullname']
        ?? ($firstName . ' ' . $lastName)
    )
);

if ($name === '') {
    $name = 'Programme Officer';
}


/*
|--------------------------------------------------------------------------
| Initials
|--------------------------------------------------------------------------
*/

if (function_exists('po_initials')) {

    $initials = po_initials(
        $firstName,
        $lastName
    );

} else {

    $initials = '';

    if ($firstName !== '') {
        $initials .= strtoupper(
            substr($firstName, 0, 1)
        );
    }

    if ($lastName !== '') {
        $initials .= strtoupper(
            substr($lastName, 0, 1)
        );
    }

    if ($initials === '') {
        $initials = 'PO';
    }
}


/*
|--------------------------------------------------------------------------
| Page Titles
|--------------------------------------------------------------------------
*/

$titles = [

    'dashboard'
        => 'Dashboard',

    'programmes'
        => 'My Programmes',

    'cohorts'
        => 'Cohorts',

    'candidates'
        => 'Candidates',

    'reports'
        => 'Reports',

    'activity_log'
        => 'Activity Log',

    'cohort_view'
        => 'Cohorts',

    'candidate_view'
        => 'Candidates',

    'update_candidate_status'
        => 'Candidates',

    'notifications'
        => 'Notifications',

];


/*
|--------------------------------------------------------------------------
| Current Page Title
|--------------------------------------------------------------------------
*/

$title = $titles[$currentPage]
    ?? 'Programme Officer Portal';


/*
|--------------------------------------------------------------------------
| User ID
|--------------------------------------------------------------------------
*/

$uid = (int) (
    $user['id']
    ?? $user['user_id']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifications = [];

$unread = 0;


if (
    $conn instanceof mysqli
    && $uid > 0
    && function_exists('po_notifications')
) {

    try {

        $notificationData = po_notifications(
            $conn,
            $uid,
            6
        );


        if (
            is_array($notificationData)
            && isset($notificationData['items'])
            && is_array($notificationData['items'])
        ) {

            $notifications =
                $notificationData['items'];

        }


        if (
            is_array($notificationData)
            && isset($notificationData['unread'])
        ) {

            $unread =
                (int) $notificationData['unread'];

        }

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Do not break the navbar if notifications fail
        |--------------------------------------------------------------------------
        */

        $notifications = [];

        $unread = 0;
    }
}

?>

<!-- =========================================================
     PROGRAMME OFFICER NAVBAR
========================================================= -->

<header
    class="ux-navbar"
    id="programmeOfficerNavbar"
>

    <!-- =====================================================
         LEFT SIDE
    ====================================================== -->

    <div class="ux-navbar__left">

        <!-- Mobile Sidebar Button -->

        <button
            type="button"
            class="ux-navbar__menu"
            id="uxMenuButton"
            aria-label="Open navigation menu"
            aria-expanded="false"
            aria-controls="uxSidebar"
        >

            <i
                class="fas fa-bars"
                aria-hidden="true"
            ></i>

        </button>


        <!-- Page Heading -->

        <div class="ux-navbar__heading">

            <span>
                Programme Officer Workspace
            </span>

            <h1>
                <?= e($title) ?>
            </h1>

        </div>

    </div>


    <!-- =====================================================
         RIGHT SIDE
    ====================================================== -->

    <div class="ux-navbar__right">


        <!-- =================================================
             DARK MODE
        ================================================== -->

        <button
            type="button"
            class="ux-theme-toggle"
            data-ux-theme-toggle
            title="Toggle dark mode"
            aria-label="Toggle dark mode"
            aria-pressed="false"
        >

            <i
                class="fas fa-moon"
                data-ux-theme-icon
                aria-hidden="true"
            ></i>

        </button>


        <!-- =================================================
             NOTIFICATIONS
        ================================================== -->

        <div
            class="ux-navbar__notification"
            id="uxNotificationWrapper"
        >

            <!-- Notification Button -->

            <button
                type="button"
                class="ux-navbar__icon-button ux-notification-button"
                id="uxNotificationButton"
                aria-label="Open notifications"
                aria-expanded="false"
                aria-controls="uxNotificationDropdown"
            >

                <i
                    class="fas fa-bell"
                    aria-hidden="true"
                ></i>


                <?php if ($unread > 0): ?>

                    <span
                        class="ux-notification-badge"
                        aria-label="<?= $unread ?> unread notifications"
                    >

                        <?= $unread > 99
                            ? '99+'
                            : $unread ?>

                    </span>

                <?php endif; ?>

            </button>


            <!-- Notification Dropdown -->

            <div
                class="ux-dropdown"
                id="uxNotificationDropdown"
                role="dialog"
                aria-label="Notifications"
                aria-hidden="true"
            >

                <!-- Dropdown Header -->

                <div class="ux-dropdown__head">

                    <strong>
                        Notifications
                    </strong>

                    <span>
                        <?= number_format($unread) ?>
                        unread
                    </span>

                </div>


                <!-- Notification Body -->

                <div class="ux-dropdown__body">


                    <?php if (!$notifications): ?>

                        <!-- Empty State -->

                        <div
                            class="ux-empty ux-empty--compact"
                        >

                            <div
                                class="ux-empty__icon"
                            >

                                <i
                                    class="fas fa-bell"
                                    aria-hidden="true"
                                ></i>

                            </div>


                            <strong>
                                You're all caught up
                            </strong>


                            <span>
                                No notifications to display.
                            </span>

                        </div>


                    <?php else: ?>


                        <!-- Notifications -->

                        <?php foreach (
                            $notifications
                            as $notification
                        ): ?>

                            <?php

                            $notificationTitle =
                                trim(
                                    (string) (
                                        $notification['title']
                                        ?? 'Notification'
                                    )
                                );


                            $notificationMessage =
                                trim(
                                    (string) (
                                        $notification['message']
                                        ?? ''
                                    )
                                );


                            $isRead =
                                (int) (
                                    $notification['is_read']
                                    ?? 1
                                ) === 1;

                            ?>


                            <div
                                class="
                                    ux-notification-item
                                    <?= !$isRead
                                        ? 'ux-notification-item--unread'
                                        : ''
                                    ?>
                                "
                            >

                                <!-- Notification Icon -->

                                <span
                                    class="ux-notification-item__icon"
                                    aria-hidden="true"
                                >

                                    <i
                                        class="fas fa-bell"
                                    ></i>

                                </span>


                                <!-- Notification Content -->

                                <div
                                    class="ux-notification-item__content"
                                >

                                    <strong>
                                        <?= e(
                                            $notificationTitle
                                        ) ?>
                                    </strong>


                                    <?php if (
                                        $notificationMessage !== ''
                                    ): ?>

                                        <span>
                                            <?= e(
                                                $notificationMessage
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>

                </div>


                <!-- Notification Footer -->

                <div
                    class="ux-dropdown__footer"
                >

                    <a
                        href="<?= url(
                            'programme_officer/notifications.php'
                        ) ?>"
                    >

                        View all notifications

                        <i
                            class="fas fa-arrow-right"
                            aria-hidden="true"
                        ></i>

                    </a>

                </div>

            </div>

        </div>


        <!-- =================================================
             USER PROFILE
        ================================================== -->

        <div
            class="ux-navbar__user"
            id="uxNavbarUser"
        >

            <!-- Avatar -->

            <div
                class="ux-navbar__avatar"
                aria-hidden="true"
            >

                <?= e($initials) ?>

            </div>


            <!-- User Information -->

            <div
                class="ux-navbar__user-copy"
            >

                <strong>
                    <?= e($name) ?>
                </strong>

                <span>
                    Programme Officer
                </span>

            </div>

        </div>

    </div>

</header>


<!-- =========================================================
     PROGRAMME OFFICER NAVBAR STYLES
========================================================= -->

<style>

/*
|--------------------------------------------------------------------------
| Base
|--------------------------------------------------------------------------
*/

.ux-navbar {
    position: relative;
    z-index: 1000;
}

.ux-navbar__left,
.ux-navbar__right {
    min-width: 0;
}

.ux-navbar__heading {
    min-width: 0;
}

.ux-navbar__heading span,
.ux-navbar__heading h1 {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ux-navbar__user {
    min-width: 0;
}

.ux-navbar__user-copy {
    min-width: 0;
}

.ux-navbar__user-copy strong,
.ux-navbar__user-copy span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| Notification
|--------------------------------------------------------------------------
*/

.ux-navbar__notification {
    position: relative;
}

.ux-notification-button {
    position: relative;
}

.ux-notification-badge {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    position: absolute;
    top: -4px;
    right: -4px;

    border-radius: 999px;

    font-size: 10px;
    line-height: 1;
    font-weight: 800;

    background: #ef4444;
    color: #ffffff;

    border: 2px solid
        var(--ux-navbar-bg, #ffffff);
}


/*
|--------------------------------------------------------------------------
| Notification Content
|--------------------------------------------------------------------------
*/

.ux-notification-item {
    position: relative;
}

.ux-notification-item--unread {
    background: rgba(
        37,
        99,
        235,
        0.06
    );
}

.ux-notification-item__content {
    min-width: 0;
    flex: 1;
}

.ux-notification-item__content strong,
.ux-notification-item__content span {
    display: block;
}

.ux-notification-item__content strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ux-notification-item__content span {
    overflow: hidden;

    display: -webkit-box;

    -webkit-box-orient: vertical;

    -webkit-line-clamp: 2;
}


/*
|--------------------------------------------------------------------------
| Dropdown
|--------------------------------------------------------------------------
*/

.ux-dropdown {
    z-index: 1100;
}


/*
|--------------------------------------------------------------------------
| Tablet / Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 768px) {

    .ux-navbar {
        min-height: 64px;
    }


    .ux-navbar__heading span {
        font-size: 0.65rem;
    }


    .ux-navbar__heading h1 {
        font-size: 1rem;
    }


    .ux-navbar__user-copy {
        display: none;
    }


    .ux-navbar__right {
        gap: 6px;
    }


    .ux-navbar__notification {
        position: static;
    }


    .ux-dropdown {
        position: fixed !important;

        top: 70px !important;

        right: 10px !important;

        left: auto !important;

        width:
            min(
                360px,
                calc(100vw - 20px)
            ) !important;

        max-width:
            calc(100vw - 20px);
    }

}


/*
|--------------------------------------------------------------------------
| Small Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 480px) {

    .ux-navbar__heading span {
        display: none;
    }


    .ux-navbar__heading h1 {
        max-width: 145px;
        font-size: 0.9rem;
    }


    .ux-navbar__menu,
    .ux-theme-toggle,
    .ux-navbar__icon-button {
        width: 38px;
        height: 38px;
    }


    .ux-navbar__avatar {
        width: 36px;
        height: 36px;
    }


    .ux-dropdown {
        top: 64px !important;

        right: 8px !important;

        width:
            calc(100vw - 16px) !important;

        max-width:
            calc(100vw - 16px);
    }

}


/*
|--------------------------------------------------------------------------
| Extra Small Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 360px) {

    .ux-navbar__heading h1 {
        max-width: 110px;
    }


    .ux-navbar__right {
        gap: 3px;
    }


    .ux-navbar__menu,
    .ux-theme-toggle,
    .ux-navbar__icon-button {
        width: 34px;
        height: 34px;
    }


    .ux-navbar__avatar {
        width: 32px;
        height: 32px;

        font-size: 0.7rem;
    }

}

</style>


<!-- =========================================================
     NOTIFICATION DROPDOWN JAVASCRIPT
========================================================= -->

<script>

(function () {

    'use strict';


    const notificationButton =
        document.getElementById(
            'uxNotificationButton'
        );


    const notificationDropdown =
        document.getElementById(
            'uxNotificationDropdown'
        );


    const notificationWrapper =
        document.getElementById(
            'uxNotificationWrapper'
        );


    /*
    |--------------------------------------------------------------------------
    | Required Elements
    |--------------------------------------------------------------------------
    */

    if (
        !notificationButton ||
        !notificationDropdown ||
        !notificationWrapper
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Open
    |--------------------------------------------------------------------------
    */

    function openNotifications() {

        notificationDropdown.classList.add(
            'is-open'
        );


        notificationDropdown.setAttribute(
            'aria-hidden',
            'false'
        );


        notificationButton.setAttribute(
            'aria-expanded',
            'true'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Close
    |--------------------------------------------------------------------------
    */

    function closeNotifications() {

        notificationDropdown.classList.remove(
            'is-open'
        );


        notificationDropdown.setAttribute(
            'aria-hidden',
            'true'
        );


        notificationButton.setAttribute(
            'aria-expanded',
            'false'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Toggle
    |--------------------------------------------------------------------------
    */

    function toggleNotifications() {

        const isOpen =
            notificationDropdown.classList.contains(
                'is-open'
            );


        if (isOpen) {

            closeNotifications();

        } else {

            openNotifications();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Button
    |--------------------------------------------------------------------------
    */

    notificationButton.addEventListener(
        'click',
        function (event) {

            event.preventDefault();

            event.stopPropagation();

            toggleNotifications();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Dropdown Click
    |--------------------------------------------------------------------------
    */

    notificationDropdown.addEventListener(
        'click',
        function (event) {

            event.stopPropagation();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Outside Click
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        function (event) {

            if (
                !notificationWrapper.contains(
                    event.target
                )
            ) {

                closeNotifications();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Escape
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {

                closeNotifications();

            }

        }
    );


})();

</script>