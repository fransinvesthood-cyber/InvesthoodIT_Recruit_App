<?php
if (!function_exists('sv_status_label')) {
    function sv_status_label(?string $status): string {
        $status = trim((string)$status);
        return $status === '' ? 'Unknown' : ucwords(str_replace('_', ' ', $status));
    }
}
if (!function_exists('sv_status_class')) {
    function sv_status_class(?string $status): string {
        return match (strtolower(trim((string)$status))) {
            'active' => 'active',
            'completed' => 'completed',
            'withdrawn' => 'withdrawn',
            'pending', 'upcoming' => 'pending',
            default => 'neutral',
        };
    }
}
if (!function_exists('sv_date')) {
    function sv_date(?string $date): string {
        if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return 'Not set';
        $ts = strtotime($date);
        return $ts ? date('d M Y', $ts) : 'Not set';
    }
}
if (!function_exists('sv_datetime')) {
    function sv_datetime(?string $date): string {
        if (!$date) return '—';
        $ts = strtotime($date);
        return $ts ? date('d M Y, H:i', $ts) : '—';
    }
}
if (!function_exists('sv_initials')) {
    function sv_initials(string $first, string $last): string {
        $i = '';
        if ($first !== '') $i .= strtoupper(substr($first, 0, 1));
        if ($last !== '') $i .= strtoupper(substr($last, 0, 1));
        return $i !== '' ? $i : 'SV';
    }
}
if (!function_exists('sv_completion_rate')) {
    function sv_completion_rate(int $total, int $completed): int {
        if ($total <= 0) return 0;
        return min(100, max(0, (int)round(($completed / $total) * 100)));
    }
}
if (!function_exists('sv_query_url')) {
    function sv_query_url(array $changes = []): string {
        $params = $_GET;
        foreach ($changes as $key => $value) {
            if ($value === null || $value === '') unset($params[$key]); else $params[$key] = $value;
        }
        $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        return $path . ($params ? '?' . http_build_query($params) : '');
    }
}
if (!function_exists('sv_csrf_token')) {
    function sv_csrf_token(): string {
        if (function_exists('csrf_token')) return csrf_token();
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['sv_csrf_token'])) $_SESSION['sv_csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['sv_csrf_token'];
    }
}
if (!function_exists('sv_verify_csrf')) {
    function sv_verify_csrf(?string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (isset($_SESSION['sv_csrf_token'])) return hash_equals($_SESSION['sv_csrf_token'], (string)$token);
        return false;
    }
}

if (!function_exists('sv_notifications')) { function sv_notifications(mysqli $c,int $uid,int $limit=6): array { if($uid<=0)return ['items'=>[],'unread'=>0,'available'=>false];$r=$c->query("SHOW TABLES LIKE 'notifications'");if(!$r||!$r->num_rows)return ['items'=>[],'unread'=>0,'available'=>false];$cols=[];$cr=$c->query("SHOW COLUMNS FROM notifications");while($cr&&$x=$cr->fetch_assoc())$cols[$x['Field']]=true;$uc=isset($cols['user_id'])?'user_id':(isset($cols['recipient_id'])?'recipient_id':null);$mc=isset($cols['message'])?'message':(isset($cols['body'])?'body':(isset($cols['description'])?'description':null));$tc=isset($cols['title'])?'title':null;$rc=isset($cols['is_read'])?'is_read':(isset($cols['read_at'])?'read_at':null);$dc=isset($cols['created_at'])?'created_at':null;if(!$uc||!$mc)return ['items'=>[],'unread'=>0,'available'=>false];$te=$tc?$tc:"'Notification'";$de=$dc?$dc:'NULL';$re=$rc==='is_read'?'is_read':($rc==='read_at'?'CASE WHEN read_at IS NULL THEN 0 ELSE 1 END':'1');$sql="SELECT id,$te AS title,$mc AS message,$de AS created_at,$re AS is_read FROM notifications WHERE $uc=? ORDER BY ".($dc?$dc:'id')." DESC LIMIT ?";$st=$c->prepare($sql);if(!$st)return ['items'=>[],'unread'=>0,'available'=>false];$st->bind_param('ii',$uid,$limit);$st->execute();$items=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();$unread=0;foreach($items as $n)if((int)($n['is_read']??1)===0)$unread++;return ['items'=>$items,'unread'=>$unread,'available'=>true]; } }
