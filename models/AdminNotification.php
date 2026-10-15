<?php
class AdminNotification
{
    public const CATEGORIES = [
        'application' => ['label' => 'Applications', 'icon' => 'fa-file-alt', 'tone' => 'application'],
        'programme' => ['label' => 'Programmes & Opportunities', 'icon' => 'fa-briefcase', 'tone' => 'opportunity'],
        'interview' => ['label' => 'Interviews', 'icon' => 'fa-calendar-check', 'tone' => 'interview'],
        'offer' => ['label' => 'Selection & Offers', 'icon' => 'fa-envelope-open-text', 'tone' => 'offer'],
        'placement' => ['label' => 'Placements', 'icon' => 'fa-map-marker-alt', 'tone' => 'placement'],
        'system' => ['label' => 'System & Account', 'icon' => 'fa-user-shield', 'tone' => 'account'],
    ];
    public const MODULES = ['application','interview','offer','selection','opportunity','programme','cohort','placement','settings','notifications','dashboard'];
    public const PRIORITIES = ['info','action','critical'];
    private static bool $schemaChecked = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) { return; }
        self::$schemaChecked = true;
        try {
            $exists = Database::fetchOne("SHOW TABLES LIKE 'admin_notifications'");
            if (!$exists) {
                Database::query('CREATE TABLE IF NOT EXISTS `admin_notifications` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`category` VARCHAR(30) NOT NULL,`priority` VARCHAR(20) NOT NULL DEFAULT \'info\',`title` VARCHAR(180) NOT NULL,`message` TEXT NOT NULL,`module` VARCHAR(40) DEFAULT NULL,`record_id` INT UNSIGNED DEFAULT NULL,`dedupe_key` VARCHAR(160) DEFAULT NULL,`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_admin_notification_event` (`dedupe_key`),KEY `idx_admin_notifications_feed` (`created_at`),KEY `idx_admin_notifications_category` (`category`, `created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            Database::query('CREATE TABLE IF NOT EXISTS `admin_notification_state` (`notification_id` INT UNSIGNED NOT NULL,`admin_id` INT UNSIGNED NOT NULL,`is_read` TINYINT(1) NOT NULL DEFAULT 0,`read_at` DATETIME DEFAULT NULL,`is_hidden` TINYINT(1) NOT NULL DEFAULT 0,`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`notification_id`, `admin_id`),KEY `idx_admin_notif_state_admin` (`admin_id`, `is_read`, `is_hidden`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (Throwable $e) { error_log('[AdminNotification] ensureSchema: ' . $e->getMessage()); }
    }

    public static function normaliseCategory(?string $c): string
    {
        $c = strtolower(trim((string) $c));
        return isset(self::CATEGORIES[$c]) ? $c : 'system';
    }
    public static function categoryLabel(?string $c): string { return self::CATEGORIES[self::normaliseCategory($c)]['label']; }
    public static function categoryIcon(?string $c): string { return self::CATEGORIES[self::normaliseCategory($c)]['icon']; }
    public static function categoryTone(?string $c): string { return self::CATEGORIES[self::normaliseCategory($c)]['tone']; }
    public static function normalisePriority(?string $p): string
    {
        $p = strtolower(trim((string) $p));
        return in_array($p, self::PRIORITIES, true) ? $p : 'info';
    }

    public static function destinationUrl(?string $m, ?int $rid = null): string
    {
        $rid = $rid !== null && $rid > 0 ? $rid : 0;
        switch ((string) $m) {
            case 'application': return $rid > 0 ? 'admin/application.php?id=' . $rid : 'admin/applications.php';
            case 'interview': return $rid > 0 ? 'admin/interview.php?id=' . $rid : 'admin/interviews.php';
            case 'offer': return $rid > 0 ? 'admin/offer.php?id=' . $rid : 'admin/offers.php';
            case 'selection': return $rid > 0 ? 'admin/selection_decision.php?id=' . $rid : 'admin/selection.php';
            case 'opportunity': return $rid > 0 ? 'admin/opportunity_detail.php?id=' . $rid : 'admin/opportunities.php';
            case 'programme': return $rid > 0 ? 'admin/programme_detail.php?id=' . $rid : 'admin/programmes.php';
            case 'cohort': return $rid > 0 ? 'admin/cohort_detail.php?id=' . $rid : 'admin/dashboard.php#admin-programmes';
            case 'placement': return $rid > 0 ? 'admin/placements.php?manage=' . $rid : 'admin/placements.php';
            case 'settings': return 'admin/settings.php';
            case 'dashboard': return 'admin/dashboard.php';
            default: return 'admin/notifications.php';
        }
    }

    public static function payload(array $row): array
    {
        $cat = self::normaliseCategory((string) ($row['category'] ?? ''));
        $rid = isset($row['record_id']) && $row['record_id'] !== null ? (int) $row['record_id'] : null;
        $mod = (string) ($row['module'] ?? '');
        $mod = $mod !== '' ? $mod : null;
        return [
            'id' => (int) ($row['id'] ?? 0), 'category' => $cat,
            'categoryLabel' => self::CATEGORIES[$cat]['label'],
            'icon' => self::CATEGORIES[$cat]['icon'], 'tone' => self::CATEGORIES[$cat]['tone'],
            'priority' => self::normalisePriority((string) ($row['priority'] ?? 'info')),
            'title' => (string) ($row['title'] ?? ''), 'message' => (string) ($row['message'] ?? ''),
            'url' => url(self::destinationUrl($mod, $rid)),
            'is_read' => (int) ($row['is_read'] ?? 0) === 1,
            'time' => time_ago((string) ($row['created_at'] ?? null)),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    public static function create(string $cat, string $title, string $msg, ?string $mod = null, ?int $rid = null, ?string $dedupe = null, ?string $pri = 'info'): bool
    {
        if (trim($title) === '' || trim($msg) === '') { return false; }
        self::ensureSchema();
        $cat = self::normaliseCategory($cat);
        $pri = self::normalisePriority($pri);
        $title = mb_substr(trim($title), 0, 180);
        $msg = trim($msg);
        $mod = $mod !== null && in_array($mod, self::MODULES, true) ? $mod : null;
        $rid = $rid !== null && $rid > 0 ? $rid : null;
        $dedupe = $dedupe !== null && trim($dedupe) !== '' ? mb_substr(trim($dedupe), 0, 160) : null;
        try {
            Database::execute('INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) VALUES (?, ?, ?, ?, ?, ?, ?)', 'sssssis', [$cat, $pri, $title, $msg, $mod, $rid, $dedupe]);
            return true;
        } catch (Throwable $e) { error_log('[AdminNotification] create: ' . $e->getMessage()); return false; }
    }

    public static function forApplicationSubmitted(int $appId, string $ref = '', string $who = ''): bool
    {
        if ($appId <= 0) { return false; }
        $w = $who !== '' ? ' from ' . $who : '';
        $r = $ref !== '' ? ' (' . $ref . ')' : '';
        return self::create('application', 'New application submitted', 'Application #' . $appId . $r . $w . ' was submitted and awaits review.', 'application', $appId, 'app_submitted_' . $appId, 'action');
    }

    public static function forApplicationStatus(int $appId, string $old, string $new, string $who = ''): bool
    {
        if ($appId <= 0 || $old === $new) { return false; }
        $alertable = ['shortlisted','rejected','selected','waitlisted','on_hold','withdrawn','interview_scheduled','interview_completed','offer_sent','offer_accepted','offer_declined'];
        if (!in_array($new, $alertable, true)) { return false; }
        $nice = static fn(string $s): string => ucwords(str_replace('_', ' ', $s));
        $w = $who !== '' ? ' (' . $who . ')' : '';
        $pri = in_array($new, ['rejected','withdrawn'], true) ? 'info' : 'action';
        return self::create('application', 'Application ' . strtolower($nice($new)) . ' #' . $appId, 'Application #' . $appId . $w . ' moved from ' . $nice($old) . ' to ' . $nice($new) . '.', 'application', $appId, 'app_status_' . $appId . '_' . $new, $pri);
    }

    public static function forInterviewScheduled(int $id, string $detail = ''): bool
    {
        if ($id <= 0) { return false; }
        return self::create('interview', 'Interview scheduled #' . $id, 'A new interview was scheduled.' . ($detail !== '' ? ' ' . $detail : ''), 'interview', $id, 'interview_scheduled_' . $id, 'action');
    }

    public static function forInterviewStatus(int $id, string $st, string $detail = ''): bool
    {
        if ($id <= 0) { return false; }
        $nice = ucwords(str_replace('_', ' ', $st));
        $pri = $st === 'completed' ? 'action' : ($st === 'cancelled' ? 'critical' : 'info');
        return self::create('interview', 'Interview ' . strtolower($nice) . ' #' . $id, 'Interview #' . $id . ' is now ' . $nice . '.' . ($detail !== '' ? ' ' . $detail : ''), 'interview', $id, 'interview_' . $st . '_' . $id, $pri);
    }

    public static function forSelectionDecision(int $appId, string $dec): bool
    {
        if ($appId <= 0) { return false; }
        return self::create('offer', 'Selection decision recorded #' . $appId, 'Application #' . $appId . ' was recorded as "' . $dec . '". Review offers and next steps.', 'selection', $appId, 'selection_decision_' . $appId . '_' . strtolower($dec), 'action');
    }

    public static function forOfferEvent(int $id, string $ev, string $detail = ''): bool
    {
        if ($id <= 0) { return false; }
        $nice = ucwords(str_replace('_', ' ', $ev));
        $pri = $ev === 'declined' ? 'critical' : (in_array($ev, ['issued','expiring','expired'], true) ? 'action' : 'info');
        return self::create('offer', 'Offer ' . strtolower($nice) . ' #' . $id, 'Offer #' . $id . ' is now ' . $nice . '.' . ($detail !== '' ? ' ' . $detail : ''), 'offer', $id, 'offer_' . $ev . '_' . $id, $pri);
    }

    public static function forPlacementEvent(int $id, string $ev, string $detail = ''): bool
    {
        if ($id <= 0) { return false; }
        $nice = ucwords(str_replace('_', ' ', $ev));
        return self::create('placement', 'Placement ' . strtolower($nice) . ' #' . $id, 'Placement #' . $id . ' event: ' . $nice . '.' . ($detail !== '' ? ' ' . $detail : ''), 'placement', $id, 'placement_' . $ev . '_' . $id, $ev === 'pending' ? 'action' : 'info');
    }

    public static function forProgrammeEvent(string $kind, int $rid, string $name): bool
    {
        $mod = in_array($kind, self::MODULES, true) ? $kind : 'dashboard';
        $label = ucfirst($kind);
        return self::create('programme', 'New ' . strtolower($label) . ' created', $label . ' "' . mb_substr($name, 0, 120) . '" was created and may need review.', $mod, $rid > 0 ? $rid : null, $kind . '_created_' . $rid, 'info');
    }

    public static function forSystemEvent(string $title, string $msg, string $dedupe, string $pri = 'info'): bool
    {
        return self::create('system', $title, $msg, 'settings', null, $dedupe, $pri);
    }

    public static function syncPendingEvents(): void
    {
        self::ensureSchema();
        $jobs = [
            "INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) SELECT 'application','action',CONCAT('New application ',COALESCE(a.application_reference,CONCAT('#',a.id))),CONCAT('Application ',COALESCE(a.application_reference,CONCAT('#',a.id)),' awaits review.'),'application',a.id,CONCAT('app_submitted_',a.id) FROM applications a WHERE a.status IN ('submitted','under_review') AND COALESCE(a.submitted_at,a.created_at) >= DATE_SUB(NOW(), INTERVAL 14 DAY)",
            "INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) SELECT 'programme','action',CONCAT('Deadline approaching: ',o.title),CONCAT('Applications for \"',o.title,'\" close on ',DATE_FORMAT(o.application_close_date,'%d %b %Y'),'.'),'opportunity',o.id,CONCAT('opp_deadline_',o.id) FROM opportunities o WHERE o.application_close_date IS NOT NULL AND o.application_close_date >= CURDATE() AND o.application_close_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            "INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) SELECT 'interview','info',CONCAT('Interview scheduled #',i.id),CONCAT('Interview #',i.id,' scheduled for ',DATE_FORMAT(i.interview_date,'%d %b %Y'),'.'),'interview',i.id,CONCAT('interview_scheduled_',i.id) FROM interviews i WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            "INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) SELECT 'interview','action',CONCAT('Interview outcome pending #',i.id),CONCAT('Interview #',i.id,' is completed. Record the selection outcome.'),'interview',i.id,CONCAT('interview_completed_',i.id) FROM interviews i INNER JOIN applications a ON a.id=i.application_id WHERE i.status='completed' AND a.status IN ('interview_completed','interview_scheduled') AND i.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            "INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) SELECT 'offer','action',CONCAT('Offer awaiting response #',off.id),CONCAT('Offer #',off.id,' is issued and awaits a response.'),'offer',off.id,CONCAT('offer_pending_',off.id) FROM offers off WHERE off.status='issued'",
            "INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) SELECT 'offer','action',CONCAT('Offer expiring soon #',off.id),CONCAT('Offer #',off.id,' expires on ',DATE_FORMAT(off.expiry_date,'%d %b %Y'),'.'),'offer',off.id,CONCAT('offer_expiring_',off.id) FROM offers off WHERE off.status='issued' AND off.expiry_date IS NOT NULL AND off.expiry_date >= CURDATE() AND off.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)",
            "INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) SELECT 'placement','action',CONCAT('Placement awaiting confirmation ',COALESCE(p.placement_reference,CONCAT('#',p.id))),CONCAT('Placement ',COALESCE(p.placement_reference,CONCAT('#',p.id)),' is pending confirmation.'),'placement',p.id,CONCAT('placement_pending_',p.id) FROM placements p WHERE p.status='pending_placement'",
            "INSERT IGNORE INTO admin_notifications (category, priority, title, message, module, record_id, dedupe_key) SELECT 'placement','info',CONCAT('Placement starting soon ',COALESCE(p.placement_reference,CONCAT('#',p.id))),CONCAT('Placement ',COALESCE(p.placement_reference,CONCAT('#',p.id)),' starts on ',DATE_FORMAT(p.start_date,'%d %b %Y'),'.'),'placement',p.id,CONCAT('placement_upcoming_',p.id) FROM placements p WHERE p.status IN ('active','placed','placement_in_progress','pending_placement') AND p.start_date >= CURDATE() AND p.start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
        ];
        foreach ($jobs as $sql) {
            try { Database::query($sql); }
            catch (Throwable $e) { error_log('[AdminNotification] sync: ' . $e->getMessage()); }
        }
    }

    public static function unreadCount(int $adminId): int
    {
        if ($adminId <= 0) { return 0; }
        self::ensureSchema();
        try {
            return (int) (Database::fetchOne('SELECT COUNT(*) AS cnt FROM admin_notifications n LEFT JOIN admin_notification_state s ON s.notification_id=n.id AND s.admin_id=? WHERE COALESCE(s.is_hidden,0)=0 AND COALESCE(s.is_read,0)=0', 'i', [$adminId])['cnt'] ?? 0);
        } catch (Throwable $e) { error_log('[AdminNotification] unreadCount: ' . $e->getMessage()); return 0; }
    }

    public static function recentForAdmin(int $adminId, int $limit = 5): array
    {
        if ($adminId <= 0) { return []; }
        self::ensureSchema();
        try {
            return Database::fetchAll('SELECT n.*, COALESCE(s.is_read,0) AS is_read FROM admin_notifications n LEFT JOIN admin_notification_state s ON s.notification_id=n.id AND s.admin_id=? WHERE COALESCE(s.is_hidden,0)=0 ORDER BY COALESCE(s.is_read,0) ASC, n.created_at DESC, n.id DESC LIMIT ?', 'ii', [$adminId, max(1, $limit)]);
        } catch (Throwable $e) { error_log('[AdminNotification] recent: ' . $e->getMessage()); return []; }
    }

    public static function findForAdmin(int $nid, int $adminId): ?array
    {
        if ($nid <= 0 || $adminId <= 0) { return null; }
        self::ensureSchema();
        try {
            return Database::fetchOne('SELECT n.*, COALESCE(s.is_read,0) AS is_read FROM admin_notifications n LEFT JOIN admin_notification_state s ON s.notification_id=n.id AND s.admin_id=? WHERE n.id=? AND COALESCE(s.is_hidden,0)=0 LIMIT 1', 'ii', [$adminId, $nid]);
        } catch (Throwable $e) { error_log('[AdminNotification] find: ' . $e->getMessage()); return null; }
    }

    public static function markRead(int $nid, int $adminId): bool
    {
        if ($nid <= 0 || $adminId <= 0) { return false; }
        self::ensureSchema();
        try {
            Database::execute('INSERT INTO admin_notification_state (notification_id, admin_id, is_read, read_at, is_hidden) VALUES (?, ?, 1, NOW(), 0) ON DUPLICATE KEY UPDATE is_read=1, read_at=NOW()', 'ii', [$nid, $adminId]);
            return true;
        } catch (Throwable $e) { error_log('[AdminNotification] markRead: ' . $e->getMessage()); return false; }
    }

    public static function markAllRead(int $adminId): bool
    {
        if ($adminId <= 0) { return false; }
        self::ensureSchema();
        try {
            Database::execute('INSERT INTO admin_notification_state (notification_id, admin_id, is_read, read_at, is_hidden) SELECT n.id, ?, 1, NOW(), 0 FROM admin_notifications n LEFT JOIN admin_notification_state s ON s.notification_id=n.id AND s.admin_id=? WHERE COALESCE(s.is_hidden,0)=0 AND COALESCE(s.is_read,0)=0 ON DUPLICATE KEY UPDATE is_read=1, read_at=NOW()', 'ii', [$adminId, $adminId]);
            return true;
        } catch (Throwable $e) { error_log('[AdminNotification] markAllRead: ' . $e->getMessage()); return false; }
    }

    public static function hide(int $nid, int $adminId): bool
    {
        if ($nid <= 0 || $adminId <= 0) { return false; }
        self::ensureSchema();
        try {
            Database::execute('INSERT INTO admin_notification_state (notification_id, admin_id, is_read, read_at, is_hidden) VALUES (?, ?, 1, NOW(), 1) ON DUPLICATE KEY UPDATE is_hidden=1, is_read=1, read_at=NOW()', 'ii', [$nid, $adminId]);
            return true;
        } catch (Throwable $e) { error_log('[AdminNotification] hide: ' . $e->getMessage()); return false; }
    }

    public static function statusCounts(int $adminId): array
    {
        $c = ['all' => 0, 'unread' => 0, 'read' => 0];
        if ($adminId <= 0) { return $c; }
        self::ensureSchema();
        try {
            $row = Database::fetchOne('SELECT COUNT(*) AS total, COALESCE(SUM(COALESCE(s.is_read,0)=0),0) AS unread FROM admin_notifications n LEFT JOIN admin_notification_state s ON s.notification_id=n.id AND s.admin_id=? WHERE COALESCE(s.is_hidden,0)=0', 'i', [$adminId]);
            $c['all'] = (int) ($row['total'] ?? 0);
            $c['unread'] = (int) ($row['unread'] ?? 0);
            $c['read'] = $c['all'] - $c['unread'];
        } catch (Throwable $e) { error_log('[AdminNotification] statusCounts: ' . $e->getMessage()); }
        return $c;
    }

    public static function pendingTasks(): array
    {
        self::ensureSchema();
        $t = ['applications' => 0, 'interviews' => 0, 'selection' => 0, 'offers' => 0, 'placements' => 0];
        try { $t['applications'] = (int) (Database::fetchOne("SELECT COUNT(*) AS cnt FROM applications WHERE status IN ('submitted','under_review')")['cnt'] ?? 0); } catch (Throwable $e) { error_log('[AdminNotification] tasks apps: ' . $e->getMessage()); }
        try { $t['interviews'] = (int) (Database::fetchOne("SELECT COUNT(*) AS cnt FROM interviews i INNER JOIN applications a ON a.id=i.application_id WHERE i.status='completed' AND a.status IN ('interview_completed','interview_scheduled')")['cnt'] ?? 0); } catch (Throwable $e) { error_log('[AdminNotification] tasks iv: ' . $e->getMessage()); }
        try { $t['selection'] = (int) (Database::fetchOne("SELECT COUNT(*) AS cnt FROM applications a WHERE a.status IN ('interview_completed','selected') AND NOT EXISTS (SELECT 1 FROM selection_decisions sd WHERE sd.application_id=a.id)")['cnt'] ?? 0); } catch (Throwable $e) { error_log('[AdminNotification] tasks sel: ' . $e->getMessage()); }
        try { $t['offers'] = (int) (Database::fetchOne("SELECT COUNT(*) AS cnt FROM offers WHERE status='issued'")['cnt'] ?? 0); } catch (Throwable $e) { error_log('[AdminNotification] tasks off: ' . $e->getMessage()); }
        try { $t['placements'] = (int) (Database::fetchOne("SELECT COUNT(*) AS cnt FROM placements WHERE status='pending_placement'")['cnt'] ?? 0); } catch (Throwable $e) { error_log('[AdminNotification] tasks plc: ' . $e->getMessage()); }
        return $t;
    }

    public static function forAdmin(int $adminId, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $empty = ['records' => [], 'total' => 0, 'unread' => 0, 'pages' => 1, 'page' => 1, 'perPage' => $perPage, 'statusCounts' => ['all' => 0, 'unread' => 0, 'read' => 0], 'categoryCounts' => []];
        if ($adminId <= 0) { return $empty; }
        self::ensureSchema();
        $status = (string) ($filters['status'] ?? 'all');
        $hasCat = array_key_exists('category', $filters) && (string) $filters['category'] !== '';
        $cat = self::normaliseCategory((string) ($filters['category'] ?? ''));
        $q = trim((string) ($filters['q'] ?? ''));
        if (!in_array($status, ['all','unread','read'], true)) { $status = 'all'; }
        $where = ['COALESCE(s.is_hidden,0)=0'];
        $types = 'i'; $params = [$adminId];
        if ($status === 'unread') { $where[] = 'COALESCE(s.is_read,0)=0'; }
        elseif ($status === 'read') { $where[] = 'COALESCE(s.is_read,0)=1'; }
        if ($hasCat) { $where[] = 'n.category=?'; $types .= 's'; $params[] = $cat; }
        if ($q !== '') { $where[] = '(n.title LIKE ? OR n.message LIKE ?)'; $types .= 'ss'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
        $whereSql = implode(' AND ', $where);
        $join = 'admin_notifications n LEFT JOIN admin_notification_state s ON s.notification_id=n.id AND s.admin_id=?';
        try {
            $total = (int) (Database::fetchOne("SELECT COUNT(*) AS total FROM {$join} WHERE {$whereSql}", $types, $params)['total'] ?? 0);
            $pages = max(1, (int) ceil($total / max(1, $perPage)));
            $page = min(max(1, $page), $pages);
            $offset = ($page - 1) * $perPage;
            $records = $total > 0 ? Database::fetchAll("SELECT n.*, COALESCE(s.is_read,0) AS is_read FROM {$join} WHERE {$whereSql} ORDER BY n.created_at DESC, n.id DESC LIMIT ?, ?", $types . 'ii', array_merge($params, [$offset, $perPage])) : [];
            $counts = self::statusCounts($adminId);
            $catCounts = array_fill_keys(array_keys(self::CATEGORIES), 0);
            $rows = Database::fetchAll("SELECT n.category, COUNT(*) AS cnt FROM {$join} WHERE COALESCE(s.is_hidden,0)=0 GROUP BY n.category", 'i', [$adminId]);
            foreach ($rows as $r) { $catCounts[self::normaliseCategory((string) ($r['category'] ?? ''))] = (int) ($r['cnt'] ?? 0); }
            return ['records' => $records, 'total' => $total, 'unread' => self::unreadCount($adminId), 'pages' => $pages, 'page' => $page, 'perPage' => $perPage, 'statusCounts' => $counts, 'categoryCounts' => $catCounts];
        } catch (Throwable $e) { error_log('[AdminNotification] forAdmin: ' . $e->getMessage()); return $empty; }
    }
}
