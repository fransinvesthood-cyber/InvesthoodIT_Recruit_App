<?php

/*
|--------------------------------------------------------------------------
| Supervisor Navbar
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/_helpers.php';

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

if (!isset($user) || !is_array($user)) {
    $user = current_user();
}

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
|
| Some Supervisor pages include navbar.php without creating $conn first.
| The notification helper requires a valid mysqli connection, so make
| sure one always exists before sv_notifications() is called.
|
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = Database::getConnection();
}

/*
|--------------------------------------------------------------------------
| Supervisor Information
|--------------------------------------------------------------------------
*/

$firstName = trim(
    (string) ($user['first_name'] ?? '')
);

$lastName = trim(
    (string) ($user['last_name'] ?? '')
);

$name = trim(
    (string) (
        $user['full_name']
        ?? $user['fullname']
        ?? ($firstName . ' ' . $lastName)
    )
);

if ($name === '') {
    $name = 'Supervisor';
}

/*
|--------------------------------------------------------------------------
| Supervisor Initials
|--------------------------------------------------------------------------
*/

$initials = sv_initials(
    $firstName,
    $lastName
);

/*
|--------------------------------------------------------------------------
| Page Titles
|--------------------------------------------------------------------------
*/

$titles = [
    'dashboard' => 'Dashboard',
    'cohorts' => 'My Cohorts',
    'candidates' => 'Candidates',
    'reports' => 'Cohort Progress',
    'activity_log' => 'Activity Log',
    'cohort_view' => 'My Cohorts',
    'candidate_view' => 'Candidates',
    'update_candidate_status' => 'Candidates',
    'notifications' => 'Notifications',
];

$title = $titles[$currentPage] ?? 'Supervisor Portal';

/*
|--------------------------------------------------------------------------
| Current Supervisor ID
|--------------------------------------------------------------------------
*/

$userId = (int) (
    $user['id']
    ?? $user['user_id']
    ?? 0
);

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notificationData = [
    'items' => [],
    'unread' => 0,
];

if ($userId > 0) {
    $notificationData = sv_notifications(
        $conn,
        $userId,
        6
    );
}

$notifications = $notificationData['items'] ?? [];

$unread = (int) (
    $notificationData['unread'] ?? 0
);

?>

<header class="ux-navbar">

    <!-- =========================================================
         LEFT SIDE
    ========================================================== -->

    <div class="ux-navbar__left">

        <!-- Mobile / Sidebar Menu Button -->
        <button
            type="button"
            class="ux-navbar__menu"
            id="uxMenuButton"
            aria-label="Toggle sidebar"
            title="Show/Hide Menu"
        >
            <i class="fas fa-bars"></i>
        </button>

        <!-- Page Heading -->
        <div class="ux-navbar__heading">

            <span>
                Supervisor Workspace
            </span>

            <h1>
                <?= e($title) ?>
            </h1>

        </div>

    </div>

    <!-- =========================================================
         RIGHT SIDE
    ========================================================== -->

    <div class="ux-navbar__right">

        <!-- =====================================================
             DARK MODE
        ====================================================== -->

        <button
            type="button"
            class="ux-theme-toggle"
            data-ux-theme-toggle
            title="Toggle dark mode"
            aria-label="Toggle dark mode"
        >
            <i
                class="fas fa-moon"
                data-ux-theme-icon
            ></i>
        </button>

        <!-- =====================================================
             NOTIFICATIONS
        ====================================================== -->

        <div class="ux-navbar__notification">

            <button
                type="button"
                class="ux-navbar__icon-button ux-notification-button"
                id="uxNotificationButton"
                aria-label="Notifications"
                aria-expanded="false"
                aria-controls="uxNotificationDropdown"
            >

                <i class="fas fa-bell"></i>

                <?php if ($unread > 0): ?>

                    <span class="ux-notification-badge">

                        <?= $unread > 99
                            ? '99+'
                            : $unread
                        ?>

                    </span>

                <?php endif; ?>

            </button>

            <!-- Notification Dropdown -->

            <div
                class="ux-dropdown"
                id="uxNotificationDropdown"
            >

                <!-- Header -->

                <div class="ux-dropdown__head">

                    <strong>
                        Notifications
                    </strong>

                    <span>
                        <?= number_format($unread) ?>
                        unread
                    </span>

                </div>

                <!-- Notification List -->

                <div class="ux-dropdown__body">

                    <?php if (!$notifications): ?>

                        <div class="ux-empty ux-empty--compact">

                            <div class="ux-empty__icon">

                                <i class="fas fa-bell"></i>

                            </div>

                            <strong>
                                You're all caught up
                            </strong>

                            <span>
                                No notifications to display.
                            </span>

                        </div>

                    <?php else: ?>

                        <?php foreach ($notifications as $notification): ?>

                            <?php

                            $notificationTitle = trim(
                                (string) (
                                    $notification['title']
                                    ?? 'Notification'
                                )
                            );

                            $notificationMessage = trim(
                                (string) (
                                    $notification['message']
                                    ?? ''
                                )
                            );

                            $isUnread =
                                (int) (
                                    $notification['is_read']
                                    ?? 1
                                ) === 0;

                            ?>

                            <div
                                class="
                                    ux-notification-item
                                    <?= $isUnread
                                        ? 'ux-notification-item--unread'
                                        : ''
                                    ?>
                                "
                            >

                                <span
                                    class="ux-notification-item__icon"
                                >

                                    <i class="fas fa-bell"></i>

                                </span>

                                <div>

                                    <strong>
                                        <?= e($notificationTitle) ?>
                                    </strong>

                                    <?php if ($notificationMessage !== ''): ?>

                                        <span>
                                            <?= e($notificationMessage) ?>
                                        </span>

                                    <?php endif; ?>

                                    <?php if (!empty($notification['created_at'])): ?>

                                        <small>

                                            <?php

                                            $timestamp = strtotime(
                                                (string)
                                                $notification['created_at']
                                            );

                                            ?>

                                            <?= $timestamp
                                                ? e(
                                                    date(
                                                        'd M Y, H:i',
                                                        $timestamp
                                                    )
                                                )
                                                : ''
                                            ?>

                                        </small>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

                <!-- Footer -->

                <div class="ux-dropdown__footer">

                    <a
                        href="<?= url(
                            'supervisor/notifications.php'
                        ) ?>"
                    >

                        View all notifications

                        <i class="fas fa-arrow-right"></i>

                    </a>

                </div>

            </div>

        </div>

        <!-- =====================================================
             USER PROFILE
        ====================================================== -->

        <div class="ux-navbar__user">

            <div class="ux-navbar__avatar">

                <?= e($initials) ?>

            </div>

            <div class="ux-navbar__user-copy">

                <strong>
                    <?= e($name) ?>
                </strong>

                <span>
                    Supervisor
                </span>

            </div>

        </div>

    </div>

</header>