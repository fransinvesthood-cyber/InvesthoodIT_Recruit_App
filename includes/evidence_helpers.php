<?php
/**
 * Assessment Evidence & Competency Verification – shared helpers.
 */

if (!function_exists('ev_types')) {
    function ev_types(): array {
        return [
            'document'          => ['label' => 'Document',              'icon' => 'fas fa-file-lines'],
            'image'             => ['label' => 'Photo / Image',         'icon' => 'fas fa-image'],
            'video'             => ['label' => 'Video recording',       'icon' => 'fas fa-video'],
            'audio'             => ['label' => 'Audio recording',       'icon' => 'fas fa-microphone'],
            'link'              => ['label' => 'External link',         'icon' => 'fas fa-link'],
            'written_statement' => ['label' => 'Written statement',     'icon' => 'fas fa-pen-to-square'],
            'witness_testimony' => ['label' => 'Witness testimony',     'icon' => 'fas fa-user-group'],
            'observation'       => ['label' => 'Assessor observation',  'icon' => 'fas fa-eye'],
            'other'             => ['label' => 'Other',                 'icon' => 'fas fa-paperclip'],
        ];
    }
}

if (!function_exists('ev_statuses')) {
    function ev_statuses(): array {
        return [
            'pending'     => ['label' => 'Pending review',   'class' => 'warning', 'icon' => 'fas fa-hourglass-half'],
            'verified'    => ['label' => 'Verified',         'class' => 'success', 'icon' => 'fas fa-circle-check'],
            'rejected'    => ['label' => 'Rejected',         'class' => 'danger',  'icon' => 'fas fa-circle-xmark'],
            'needs_more'  => ['label' => 'Needs more info',  'class' => 'info',    'icon' => 'fas fa-circle-info'],
            'outstanding' => ['label' => 'Outstanding',      'class' => 'warning', 'icon' => 'fas fa-triangle-exclamation'],
            'archived'    => ['label' => 'Archived',         'class' => 'muted',   'icon' => 'fas fa-box-archive'],
        ];
    }
}

if (!function_exists('ev_sufficiencies')) {
    function ev_sufficiencies(): array {
        return ['sufficient' => 'Sufficient', 'partial' => 'Partial', 'insufficient' => 'Insufficient'];
    }
}
if (!function_exists('ev_relevance_levels')) {
    function ev_relevance_levels(): array {
        return ['direct' => 'Direct', 'supporting' => 'Supporting', 'indirect' => 'Indirect'];
    }
}
if (!function_exists('ev_authenticity_levels')) {
    function ev_authenticity_levels(): array {
        return ['authentic' => 'Authentic', 'questionable' => 'Questionable', 'unverified' => 'Unverified'];
    }
}

if (!function_exists('ev_type_icon')) {
    function ev_type_icon(string $type): string {
        $t = ev_types();
        return $t[$type]['icon'] ?? 'fas fa-paperclip';
    }
}
if (!function_exists('ev_type_label')) {
    function ev_type_label(string $type): string {
        $t = ev_types();
        return $t[$type]['label'] ?? ucfirst($type);
    }
}
if (!function_exists('ev_status_label')) {
    function ev_status_label(string $status): string {
        $s = ev_statuses();
        return $s[$status]['label'] ?? ucfirst($status);
    }
}
if (!function_exists('ev_status_class')) {
    function ev_status_class(string $status): string {
        $s = ev_statuses();
        return $s[$status]['class'] ?? 'muted';
    }
}

if (!function_exists('ev_file_icon')) {
    function ev_file_icon(?string $mime, ?string $type = null): string {
        $m = strtolower((string)$mime);
        if (str_starts_with($m, 'image/')) return 'fas fa-file-image';
        if (str_starts_with($m, 'video/')) return 'fas fa-file-video';
        if (str_starts_with($m, 'audio/')) return 'fas fa-file-audio';
        if ($m === 'application/pdf')      return 'fas fa-file-pdf';
        if (str_contains($m, 'word') || str_contains($m, 'opendocument.text')) return 'fas fa-file-word';
        if (str_contains($m, 'sheet')|| str_contains($m, 'excel'))             return 'fas fa-file-excel';
        if (str_contains($m, 'zip')  || str_contains($m, 'compressed'))        return 'fas fa-file-zipper';
        return $type ? ev_type_icon($type) : 'fas fa-file';
    }
}

if (!function_exists('ev_human_size')) {
    function ev_human_size(?int $bytes): string {
        if (!$bytes || $bytes <= 0) return '—';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0; $b = (float)$bytes;
        while ($b >= 1024 && $i < count($units) - 1) { $b /= 1024; $i++; }
        return number_format($b, $i ? 1 : 0) . ' ' . $units[$i];
    }
}

if (!function_exists('ev_is_image')) {
    function ev_is_image(?string $mime): bool { return $mime && str_starts_with(strtolower($mime), 'image/'); }
}
if (!function_exists('ev_is_pdf')) {
    function ev_is_pdf(?string $mime): bool   { return strtolower((string)$mime) === 'application/pdf'; }
}
if (!function_exists('ev_is_video')) {
    function ev_is_video(?string $mime): bool { return $mime && str_starts_with(strtolower($mime), 'video/'); }
}
if (!function_exists('ev_is_audio')) {
    function ev_is_audio(?string $mime): bool { return $mime && str_starts_with(strtolower($mime), 'audio/'); }
}

if (!function_exists('ev_csrf_token')) {
    function ev_csrf_token(): string {
        if (function_exists('csrf_token')) return (string)csrf_token();
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (empty($_SESSION['_ev_csrf'])) $_SESSION['_ev_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_ev_csrf'];
    }
}
if (!function_exists('ev_csrf_ok')) {
    function ev_csrf_ok(?string $t): bool {
        if (function_exists('csrf_verify')) { try { return (bool)csrf_verify($t); } catch (Throwable $e) {} }
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        return !empty($_SESSION['_ev_csrf']) && is_string($t) && hash_equals($_SESSION['_ev_csrf'], $t);
    }
}

if (!function_exists('ev_action_label')) {
    function ev_action_label(string $action): string {
        return [
            'uploaded'           => 'Evidence uploaded',
            'verified'           => 'Marked as verified',
            'rejected'           => 'Marked as rejected',
            'requested_more'     => 'Requested more information',
            'marked_outstanding' => 'Marked as outstanding',
            'reopened'           => 'Reopened for review',
            'archived'           => 'Archived',
            'commented'          => 'Comment added',
            'criteria_updated'   => 'Criterion links updated',
        ][$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
}

if (!function_exists('ev_store_dir')) {
    function ev_store_dir(): string {
        $dir = __DIR__ . '/../uploads/evidence';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

/**
 * Resolve and validate the physical file for an evidence row.
 */
if (!function_exists('ev_resolve_file')) {
    function ev_resolve_file(array $row): ?string {
        if (empty($row['file_path'])) return null;
        $base = realpath(__DIR__ . '/../');
        $full = realpath(__DIR__ . '/../' . ltrim($row['file_path'], '/'));
        if (!$full || !$base || !str_starts_with($full, $base)) return null;
        return $full;
    }
}