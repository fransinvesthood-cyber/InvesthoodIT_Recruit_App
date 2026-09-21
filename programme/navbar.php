<?php
require_once __DIR__ . '/_helpers.php';

if (!isset($user) || !is_array($user)) {
    $user = current_user();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = Database::getConnection();
}

$currentPage = $currentPage ?? '';

$firstName = trim((string)($user['first_name'] ?? ''));
$lastName = trim((string)($user['last_name'] ?? ''));
$fullName = trim((string)(
    $user['full_name']
    ?? $user['fullname']
    ?? ($firstName . ' ' . $lastName)
));

if ($fullName === '') {
    $fullName = 'Programme Manager';
}

$initials = pm_initials($firstName, $lastName, $fullName);

$titles = [
    'dashboard' => 'Dashboard',
    'programmes' => 'Programmes',
    'cohorts' => 'Cohorts',
    'cohort_view' => 'Cohort Details',
    'assign_candidates' => 'Assign Candidates',
    'candidates' => 'Candidates',
    'reports' => 'Reports',
    'activity_log' => 'Activity Log',
    'notifications' => 'Notifications',
];

$navbarTitle = $titles[$currentPage] ?? ($pageTitle ?? 'Programme Manager Portal');

$userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$notificationData = pm_notifications($conn, $userId, 50);
$allNotifications = $notificationData['items'];
$notifications = array_slice($allNotifications, 0, 6);
$unread = (int)$notificationData['unread'];
?>

<header class="pm-navbar">
    <div class="pm-navbar__left">
        <button type="button" class="pm-navbar__menu" id="uxSidebarToggle" aria-label="Open menu">
            <i class="fas fa-bars"></i>
        </button>

        <div class="pm-navbar__heading">
            <span>Programme Manager Workspace</span>
            <h1><?= e($navbarTitle) ?></h1>
        </div>
    </div>

    <div class="pm-navbar__right">

        <!-- Dark mode lives only on the navbar -->
        <button
            type="button"
            class="pm-navbar__button"
            id="pmThemeToggle"
            title="Toggle dark mode"
            aria-label="Toggle dark mode"
        >
            <i class="fas fa-moon" id="pmThemeIcon"></i>
        </button>

        <!-- Notification bell lives only on the navbar -->
        <div class="pm-notification">
            <button
                type="button"
                class="pm-navbar__button pm-notification__button"
                id="pmNotificationButton"
                aria-label="Notifications"
                aria-expanded="false"
            >
                <i class="fas fa-bell"></i>

                <?php if ($unread > 0): ?>
                    <span class="pm-notification__badge">
                        <?= $unread > 99 ? '99+' : $unread ?>
                    </span>
                <?php endif; ?>
            </button>

            <div class="pm-notification__dropdown" id="pmNotificationDropdown">
                <div class="pm-notification__head">
                    <div>
                        <strong>Notifications</strong>
                        <span><?= number_format($unread) ?> unread</span>
                    </div>

                    <?php if ($unread > 0): ?>
                        <a href="<?= url('programme/notifications.php?action=mark_all') ?>">
                            Mark all read
                        </a>
                    <?php endif; ?>
                </div>

                <div class="pm-notification__list">
                    <?php if (!$notifications): ?>
                        <div class="pm-notification__empty">
                            <i class="fas fa-bell-slash"></i>
                            <strong>No notifications</strong>
                            <span>You're all caught up.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <a
                                class="pm-notification__item <?= (int)($notification['is_read'] ?? 1) === 0 ? 'is-unread' : '' ?>"
                                href="<?= url(
                                    'programme/notifications.php?read='
                                    . (int)($notification['id'] ?? 0)
                                ) ?>"
                            >
                                <span class="pm-notification__icon">
                                    <i class="fas fa-bell"></i>
                                </span>

                                <span class="pm-notification__copy">
                                    <strong>
                                        <?= e((string)($notification['title'] ?? 'Notification')) ?>
                                    </strong>

                                    <span>
                                        <?= e((string)($notification['message'] ?? '')) ?>
                                    </span>

                                    <?php if (!empty($notification['created_at'])): ?>
                                        <small>
                                            <?= e(date(
                                                'd M Y, H:i',
                                                strtotime((string)$notification['created_at'])
                                            )) ?>
                                        </small>
                                    <?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="pm-notification__footer">
                    <button type="button" class="pm-notification__view-all" id="pmViewAllNotifications">
                        View all notifications <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="pm-navbar__user">
            <div class="pm-navbar__avatar">
                <?= e($initials) ?>
            </div>

            <div class="pm-navbar__user-copy">
                <strong><?= e($fullName) ?></strong>
                <span>Programme Manager</span>
            </div>
        </div>
    </div>
</header>

<div class="pm-all-notifications" id="pmAllNotificationsModal" aria-hidden="true">
<div class="pm-all-notifications__dialog" role="dialog" aria-modal="true" aria-labelledby="pmAllNotificationsTitle">
<div class="pm-all-notifications__head"><div><span>Notification Centre</span><h2 id="pmAllNotificationsTitle">All Notifications</h2></div><button type="button" id="pmAllNotificationsClose" aria-label="Close"><i class="fas fa-xmark"></i></button></div>
<div class="pm-all-notifications__body">
<?php if(!$allNotifications): ?><div class="pm-notification__empty"><i class="fas fa-bell-slash"></i><strong>No notifications</strong><span>You're all caught up.</span></div>
<?php else: foreach($allNotifications as $notification): ?>
<div class="pm-all-notifications__item <?= (int)($notification['is_read']??1)===0?'is-unread':'' ?>"><span class="pm-notification__icon"><i class="fas fa-bell"></i></span><span class="pm-notification__copy"><strong><?=e((string)($notification['title']??'Notification'))?></strong><span><?=e((string)($notification['message']??''))?></span><?php if(!empty($notification['created_at'])):?><small><?=e(date('d M Y, H:i',strtotime((string)$notification['created_at'])))?></small><?php endif;?></span></div>
<?php endforeach; endif; ?>
</div>
<div class="pm-all-notifications__footer"><span><?=number_format(count($allNotifications))?> notification<?=count($allNotifications)===1?'':'s'?></span><button type="button" id="pmAllNotificationsDone">Close</button></div>
</div></div>
