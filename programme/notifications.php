<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$conn = Database::getConnection();
$currentPage = 'notifications';
$pageTitle = 'Notifications';

$userId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if (isset($_GET['action']) && $_GET['action'] === 'mark_all') {
    pm_mark_all_notifications_read($conn, $userId);
    header('Location: ' . url('programme/notifications.php'));
    exit;
}

$readId = (int)($_GET['read'] ?? 0);

if ($readId > 0) {
    pm_mark_notification_read($conn, $userId, $readId);
    header('Location: ' . url('programme/notifications.php'));
    exit;
}

$data = pm_notifications($conn, $userId, 100);
$notifications = $data['items'];
$unread = (int)$data['unread'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Investhood IT</title>

    <script>
    (function () {
        try {
            const saved = localStorage.getItem('investhood-programme-manager-theme');
            const preferred = window.matchMedia &&
                window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark'
                : 'light';

            document.documentElement.setAttribute(
                'data-theme',
                saved === 'dark' || saved === 'light' ? saved : preferred
            );
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
    <link rel="stylesheet" href="<?= url('css/programme_manager_enhancements.css') ?>">
  <link rel="stylesheet" href="<?= url('css/styles_original_pm.css') ?>">
  <link rel="stylesheet" href="<?= url('css/original_pm_sidebar_compat.css') ?>">
  <link rel="stylesheet" href="<?= url('css/dashboard_card_modals.css') ?>">
</head>

<body class="dashboard-page pm-enhanced-page">
<div class="dashboard">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <main class="dashboard__main pm-main">
        <?php require __DIR__ . '/navbar.php'; ?>

        <div class="pm-page-content">
            <div class="pm-page-heading">
                <div>
                    <span>Notification Centre</span>
                    <h2>All Notifications</h2>
                    <p>Review alerts and updates for your Programme Manager account.</p>
                </div>

                <?php if ($unread > 0): ?>
                    <a class="pm-action-button" href="<?= url('programme/notifications.php?action=mark_all') ?>">
                        <i class="fas fa-check-double"></i>
                        Mark all as read
                    </a>
                <?php endif; ?>
            </div>

            <section class="pm-notifications-card">
                <?php if (!$notifications): ?>
                    <div class="pm-notification-page-empty">
                        <i class="fas fa-bell-slash"></i>
                        <strong>No notifications</strong>
                        <span>New Programme Manager notifications will appear here.</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <a
                            href="<?= url('programme/notifications.php?read=' . (int)($notification['id'] ?? 0)) ?>"
                            class="pm-notification-page-item <?= (int)($notification['is_read'] ?? 1) === 0 ? 'is-unread' : '' ?>"
                        >
                            <span class="pm-notification-page-icon">
                                <i class="fas fa-bell"></i>
                            </span>

                            <span class="pm-notification-page-copy">
                                <strong><?= e((string)($notification['title'] ?? 'Notification')) ?></strong>
                                <span><?= e((string)($notification['message'] ?? '')) ?></span>

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
            </section>
        </div>
    </main>
</div>

<script src="<?= url('js/programme_manager_enhancements.js') ?>"></script>
<script src="<?= url('js/original_pm_sidebar.js') ?>"></script>
<script src="<?= url('js/dashboard_card_modals.js') ?>"></script>
</body>
</html>
