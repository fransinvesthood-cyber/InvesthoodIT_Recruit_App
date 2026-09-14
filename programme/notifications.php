<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');

$user      = current_user();
$managerId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
$currentPage = 'notifications';

if (!function_exists('pm_notifications')) {
    function pm_notifications(int $managerId): array {
        return $_SESSION['pm_notifications_' . $managerId] ?? [];
    }
}
if (!function_exists('pm_time_ago')) {
    function pm_time_ago(string $datetime): string {
        $ts = strtotime($datetime); $diff = time() - $ts;
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('d M Y', $ts);
    }
}

$notifications = pm_notifications($managerId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Investhood IT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body class="dashboard-page">
<div class="dashboard">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="dashboard__main">
        <header class="dash-header">
            <div class="dash-header__left">
                <h1 class="dash-header__title">All Notifications</h1>
            </div>
        </header>
        <div class="dash-content">
            <div class="welcome-card">
                <div class="welcome-card__content">
                    <?php if (empty($notifications)): ?>
                        <p>No notifications yet.</p>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="pm-upcoming-item">
                                <div>
                                    <strong><?= e($n['title']) ?></strong>
                                    <div style="color:#6b7280;font-size:.85rem;margin-top:.25rem;">
                                        <?= e($n['message']) ?>
                                    </div>
                                </div>
                                <div class="pm-date">
                                    <?= e(pm_time_ago($n['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>