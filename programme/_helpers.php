<?php

if (!function_exists('pm_initials')) {
    function pm_initials(string $first, string $last, string $fullName = ''): string
    {
        $first = trim($first);
        $last = trim($last);

        if ($first === '' && $last === '' && trim($fullName) !== '') {
            $parts = preg_split('/\s+/', trim($fullName));
            $first = (string)($parts[0] ?? '');
            $last = count($parts) > 1 ? (string)end($parts) : '';
        }

        $initials = '';
        if ($first !== '') $initials .= strtoupper(substr($first, 0, 1));
        if ($last !== '') $initials .= strtoupper(substr($last, 0, 1));

        return $initials !== '' ? $initials : 'PM';
    }
}

if (!function_exists('pm_table_exists')) {
    function pm_table_exists(mysqli $conn, string $table): bool
    {
        $safe = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('pm_notification_columns')) {
    function pm_notification_columns(mysqli $conn): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $cache = [];

        if (!pm_table_exists($conn, 'notifications')) {
            return $cache;
        }

        $result = $conn->query("SHOW COLUMNS FROM notifications");

        while ($result && $row = $result->fetch_assoc()) {
            $cache[$row['Field']] = true;
        }

        return $cache;
    }
}

if (!function_exists('pm_notifications')) {
    function pm_notifications(mysqli $conn, int $userId, int $limit = 6): array
    {
        if ($userId <= 0 || !pm_table_exists($conn, 'notifications')) {
            return ['items' => [], 'unread' => 0, 'available' => false];
        }

        $columns = pm_notification_columns($conn);

        $userColumn = isset($columns['user_id'])
            ? 'user_id'
            : (isset($columns['recipient_id']) ? 'recipient_id' : null);

        $messageColumn = isset($columns['message'])
            ? 'message'
            : (isset($columns['body'])
                ? 'body'
                : (isset($columns['description']) ? 'description' : null));

        $titleColumn = isset($columns['title']) ? 'title' : null;
        $dateColumn = isset($columns['created_at']) ? 'created_at' : null;

        if (!$userColumn || !$messageColumn) {
            return ['items' => [], 'unread' => 0, 'available' => false];
        }

        $titleExpression = $titleColumn ? $titleColumn : "'Notification'";
        $dateExpression = $dateColumn ? $dateColumn : 'NULL';

        if (isset($columns['is_read'])) {
            $readExpression = 'is_read';
            $unreadWhere = 'is_read = 0';
        } elseif (isset($columns['read_at'])) {
            $readExpression = 'CASE WHEN read_at IS NULL THEN 0 ELSE 1 END';
            $unreadWhere = 'read_at IS NULL';
        } else {
            $readExpression = '1';
            $unreadWhere = '1 = 0';
        }

        $orderColumn = $dateColumn ?: (isset($columns['id']) ? 'id' : $userColumn);

        $sql = "
            SELECT
                ".(isset($columns['id']) ? 'id' : '0')." AS id,
                {$titleExpression} AS title,
                {$messageColumn} AS message,
                {$dateExpression} AS created_at,
                {$readExpression} AS is_read
            FROM notifications
            WHERE {$userColumn} = ?
            ORDER BY {$orderColumn} DESC
            LIMIT ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return ['items' => [], 'unread' => 0, 'available' => false];
        }

        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $unread = 0;

        $countStmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM notifications
            WHERE {$userColumn} = ?
              AND {$unreadWhere}
        ");

        if ($countStmt) {
            $countStmt->bind_param('i', $userId);
            $countStmt->execute();
            $row = $countStmt->get_result()->fetch_assoc();
            $unread = (int)($row['total'] ?? 0);
            $countStmt->close();
        }

        return [
            'items' => $items,
            'unread' => $unread,
            'available' => true
        ];
    }
}

if (!function_exists('pm_mark_notification_read')) {
    function pm_mark_notification_read(mysqli $conn, int $userId, int $notificationId): void
    {
        if ($userId <= 0 || $notificationId <= 0 || !pm_table_exists($conn, 'notifications')) return;

        $columns = pm_notification_columns($conn);
        if (!isset($columns['id'])) return;

        $userColumn = isset($columns['user_id'])
            ? 'user_id'
            : (isset($columns['recipient_id']) ? 'recipient_id' : null);

        if (!$userColumn) return;

        if (isset($columns['is_read'])) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND {$userColumn} = ?");
        } elseif (isset($columns['read_at'])) {
            $stmt = $conn->prepare("UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = ? AND {$userColumn} = ?");
        } else {
            return;
        }

        if ($stmt) {
            $stmt->bind_param('ii', $notificationId, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('pm_mark_all_notifications_read')) {
    function pm_mark_all_notifications_read(mysqli $conn, int $userId): void
    {
        if ($userId <= 0 || !pm_table_exists($conn, 'notifications')) return;

        $columns = pm_notification_columns($conn);

        $userColumn = isset($columns['user_id'])
            ? 'user_id'
            : (isset($columns['recipient_id']) ? 'recipient_id' : null);

        if (!$userColumn) return;

        if (isset($columns['is_read'])) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE {$userColumn} = ?");
        } elseif (isset($columns['read_at'])) {
            $stmt = $conn->prepare("UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE {$userColumn} = ?");
        } else {
            return;
        }

        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}
