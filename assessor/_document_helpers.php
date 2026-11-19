<?php
/**
 * Document verification helpers shared by assessor document screens.
 * Kept separate so the same helpers can be reused by candidate-facing pages.
 */

if (!function_exists('doc_status_label')) {
    function doc_status_label(string $status): string {
        static $map = [
            'pending'     => 'Pending Review',
            'verified'    => 'Verified',
            'rejected'    => 'Rejected',
            'outstanding' => 'Outstanding',
            'expired'     => 'Expired',
        ];
        return $map[$status] ?? ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('doc_status_class')) {
    function doc_status_class(string $status): string {
        static $map = [
            'pending'     => 'orange',
            'verified'    => 'green',
            'rejected'    => 'red',
            'outstanding' => 'blue',
            'expired'     => 'purple',
        ];
        return $map[$status] ?? 'blue';
    }
}

if (!function_exists('doc_status_icon')) {
    function doc_status_icon(string $status): string {
        static $map = [
            'pending'     => 'fas fa-hourglass-half',
            'verified'    => 'fas fa-circle-check',
            'rejected'    => 'fas fa-circle-xmark',
            'outstanding' => 'fas fa-triangle-exclamation',
            'expired'     => 'fas fa-calendar-xmark',
        ];
        return $map[$status] ?? 'fas fa-file';
    }
}

if (!function_exists('doc_size')) {
    function doc_size(int $bytes): string {
        if ($bytes <= 0) return '—';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}

if (!function_exists('doc_mime_icon')) {
    function doc_mime_icon(string $mime): string {
        $m = strtolower($mime);
        if (str_contains($m, 'pdf'))                             return 'fas fa-file-pdf';
        if (str_contains($m, 'image'))                           return 'fas fa-file-image';
        if (str_contains($m, 'word') || str_contains($m, 'document')) return 'fas fa-file-word';
        if (str_contains($m, 'sheet') || str_contains($m, 'excel'))   return 'fas fa-file-excel';
        return 'fas fa-file';
    }
}

if (!function_exists('doc_is_expiring')) {
    function doc_is_expiring(?string $expiresOn, int $withinDays = 30): bool {
        if (empty($expiresOn)) return false;
        $ts = strtotime($expiresOn);
        return $ts !== false && $ts <= strtotime("+{$withinDays} days");
    }
}

if (!function_exists('doc_csrf_ok')) {
    /**
     * Defensive CSRF check that adapts to whichever helper the app ships with.
     * If none exists the request is allowed — but you should wire this to your
     * actual CSRF helper so it's always enforced.
     */
    function doc_csrf_ok(?string $token): bool {
        if ($token === null || $token === '') return false;
        if (function_exists('verify_csrf'))        return (bool) verify_csrf($token);
        if (function_exists('csrf_verify'))        return (bool) csrf_verify($token);
        if (function_exists('validate_csrf_token')) return (bool) validate_csrf_token($token);
        return true; // Fallback — replace with a real check.
    }
}