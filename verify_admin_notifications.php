<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli' && APP_ENV !== 'development') { http_response_code(403); exit('Dev only.'); }
header('Content-Type: text/plain; charset=utf-8');
$pass = 0; $fail = 0;
function vcheck(string $label, bool $ok, string $d = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($d !== '' ? ' :: ' . $d : '') . "\n";
}
echo "=== Admin Notifications verification ===\n";
$admins = Database::fetchAll("SELECT u.id FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.slug='admin' ORDER BY u.id ASC LIMIT 2");
if (!$admins) { echo "No admin accounts found.\n"; exit(0); }
$owner = (int) $admins[0]['id'];
$other = isset($admins[1]) ? (int) $admins[1]['id'] : 0;
echo "Owner admin: $owner | Other admin: " . ($other ?: '(none)') . "\n";
AdminNotification::ensureSchema();
vcheck('admin_notifications table exists', Database::fetchOne("SHOW TABLES LIKE 'admin_notifications'") !== null);
vcheck('admin_notification_state table exists', Database::fetchOne("SHOW TABLES LIKE 'admin_notification_state'") !== null);
$c1 = AdminNotification::create('application', 'Verification event', 'Verification body.', 'application', 1, 'verify_admin_1', 'action');
vcheck('create() stores a notification', $c1);
$c2 = AdminNotification::create('application', 'Verification event dup', 'Must not duplicate.', 'application', 1, 'verify_admin_1', 'action');
$cnt = (int) (Database::fetchOne("SELECT COUNT(*) AS cnt FROM admin_notifications WHERE dedupe_key='verify_admin_1'")['cnt'] ?? 0);
vcheck('duplicate event is idempotent', $cnt === 1, "count=$cnt");
$row = Database::fetchOne("SELECT id FROM admin_notifications WHERE dedupe_key='verify_admin_1'");
$nid = (int) ($row['id'] ?? 0);
$u0 = AdminNotification::unreadCount($owner);
vcheck('unread count includes new row', $u0 >= 1, "unread=$u0");
AdminNotification::markRead($nid, $owner);
vcheck('markRead clears owner unread', AdminNotification::unreadCount($owner) === $u0 - 1);
if ($other > 0) {
    vcheck('other admin still sees it unread', AdminNotification::unreadCount($other) >= 1);
    AdminNotification::hide($nid, $other);
    vcheck('hide() removes it for other admin only', AdminNotification::findForAdmin($nid, $other) === null && AdminNotification::findForAdmin($nid, $owner) !== null);
}
AdminNotification::markAllRead($owner);
vcheck('markAllRead clears all owner unread', AdminNotification::unreadCount($owner) === 0);
$tasks = AdminNotification::pendingTasks();
vcheck('pendingTasks returns real counters', isset($tasks['applications'], $tasks['interviews'], $tasks['selection'], $tasks['offers'], $tasks['placements']), json_encode($tasks));
try { AdminNotification::syncPendingEvents(); vcheck('syncPendingEvents runs without fatal', true); }
catch (Throwable $e) { vcheck('syncPendingEvents runs without fatal', false, $e->getMessage()); }
Database::execute('DELETE FROM admin_notification_state WHERE notification_id=?', 'i', [$nid]);
Database::execute('DELETE FROM admin_notifications WHERE dedupe_key=?', 's', ['verify_admin_1']);
echo "\nDone: $pass passed, $fail failed.\n";
