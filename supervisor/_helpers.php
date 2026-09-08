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
