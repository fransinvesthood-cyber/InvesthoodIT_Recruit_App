<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
function admin_notif_json(array $p, int $s = 200): void
{
    http_response_code($s);
    header_remove('Location');
    header('Content-Type: application/json');
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode($p);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { admin_notif_json(['success' => false, 'message' => 'Method not allowed.'], 405); }
if (!validate_csrf()) { admin_notif_json(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403); }
$adminId = (int) current_user_id();
$action = (string) ($_POST['action'] ?? '');
try {
    switch ($action) {
        case 'unread_count':
            admin_notif_json(['success' => true, 'unread' => AdminNotification::unreadCount($adminId)]);
        case 'recent':
            $limit = min(20, max(1, (int) ($_POST['limit'] ?? 5)));
            $rows = AdminNotification::recentForAdmin($adminId, $limit);
            admin_notif_json(['success' => true, 'unread' => AdminNotification::unreadCount($adminId), 'notifications' => array_map([AdminNotification::class, 'payload'], $rows)]);
        case 'mark_read':
            $nid = (int) ($_POST['notification_id'] ?? 0);
            if ($nid <= 0) { admin_notif_json(['success' => false, 'message' => 'Invalid notification.'], 400); }
            if (!AdminNotification::findForAdmin($nid, $adminId)) { admin_notif_json(['success' => false, 'message' => 'Notification not found.'], 404); }
            AdminNotification::markRead($nid, $adminId);
            admin_notif_json(['success' => true, 'unread' => AdminNotification::unreadCount($adminId)]);
        case 'mark_all_read':
            AdminNotification::markAllRead($adminId);
            admin_notif_json(['success' => true, 'unread' => AdminNotification::unreadCount($adminId)]);
        case 'delete':
            $nid = (int) ($_POST['notification_id'] ?? 0);
            if ($nid <= 0) { admin_notif_json(['success' => false, 'message' => 'Invalid notification.'], 400); }
            if (!AdminNotification::findForAdmin($nid, $adminId)) { admin_notif_json(['success' => false, 'message' => 'Notification not found.'], 404); }
            AdminNotification::hide($nid, $adminId);
            admin_notif_json(['success' => true, 'unread' => AdminNotification::unreadCount($adminId)]);
        default:
            admin_notif_json(['success' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    error_log('[Admin Notifications API] Error (' . $action . '): ' . $e->getMessage());
    admin_notif_json(['success' => false, 'message' => 'A system error occurred. Please try again later.'], 500);
}
