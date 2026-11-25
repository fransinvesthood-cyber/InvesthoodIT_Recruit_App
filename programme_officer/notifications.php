<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');

$user = current_user();
$flashes = render_flashes();
$currentPage = 'notifications';
$pageTitle = 'Notifications';
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$officerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$notificationData = po_notifications($conn, $officerId, 100);
$notifications = $notificationData['items'];

require __DIR__ . '/_layout_start.php';
?>
<div class="ux-page-header">
    <div>
        <span class="ux-page-header__eyebrow">Notification Centre</span>
        <h2>All Notifications</h2>
        <p>Review recent alerts and updates for your Programme Officer account.</p>
    </div>
</div>
<div class="ux-card">
    <div class="ux-card__body">
        <?php if (!$notifications): ?>
            <div class="ux-empty">
                <div class="ux-empty__icon"><i class="fas fa-bell"></i></div>
                <strong>No notifications</strong>
                <span>New notifications will appear here.</span>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <?php
                $notificationUrl = trim((string) ($notification['url'] ?? ''));
                $notificationClass = 'ux-notification-item'
                    . ((int) ($notification['is_read'] ?? 1) === 0 ? ' ux-notification-item--unread' : '');
                ?>
                <?php if ($notificationUrl !== ''): ?>
                    <a class="<?= e($notificationClass) ?>" href="<?= e(url($notificationUrl)) ?>">
                <?php else: ?>
                    <div class="<?= e($notificationClass) ?>">
                <?php endif; ?>
                    <span class="ux-notification-item__icon"><i class="fas fa-bell"></i></span>
                    <div class="ux-notification-item__content">
                        <strong><?= e((string) ($notification['title'] ?? 'Notification')) ?></strong>
                        <span><?= e((string) ($notification['message'] ?? '')) ?></span>
                        <?php if (!empty($notification['created_at'])): ?>
                            <span><?= e(date('d M Y, H:i', strtotime($notification['created_at']))) ?></span>
                        <?php endif; ?>
                    </div>
                <?php if ($notificationUrl !== ''): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
