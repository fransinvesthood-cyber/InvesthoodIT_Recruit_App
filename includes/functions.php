<?php
/**
 * ================================================
 * INVESTHOOD IT - Reusable Helper Functions
 * ================================================
 * Common utilities used across the application.
 */

/**
 * Redirect to a given path (relative to APP_URL).
 *
 * @param string $path
 */
function redirect(string $path): void
{
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

/**
 * Safe redirect using a relative or absolute URL.
 *
 * Relative paths are resolved against APP_URL so redirects work
 * correctly even when fired from subdirectory pages (e.g. /auth/).
 * Absolute URLs (http/https) and root-relative paths (/...) are
 * passed through unchanged.
 *
 * @param string $url
 */
function safe_redirect(string $url): void
{
    if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '/')) {
        $url = APP_URL . '/' . ltrim($url, '/');
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Build a full application URL.
 *
 * @param string $path
 * @return string
 */
function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Resolve the dashboard path for a given role slug.
 *
 * @param string $roleSlug
 * @return string|null
 */
function role_dashboard(string $roleSlug): ?string
{
    return ROLE_DASHBOARD_MAP[$roleSlug] ?? null;
}

/**
 * Escape output for safe HTML display (XSS protection).
 *
 * @param mixed $value
 * @return string
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a date string into a friendly format.
 *
 * @param string|null $datetime
 * @param string      $format
 * @return string
 */
function format_date(?string $datetime, string $format = 'd M Y H:i'): string
{
    if (empty($datetime)) {
        return '—';
    }
    try {
        $dt = new DateTime($datetime, new DateTimeZone(APP_TIMEZONE));
        return $dt->format($format);
    } catch (Exception $ex) {
        return '—';
    }
}

/**
 * Time-ago string for human-friendly timestamps.
 *
 * @param string|null $datetime
 * @return string
 */
function time_ago(?string $datetime): string
{
    if (empty($datetime)) {
        return '—';
    }
    $time = strtotime($datetime);
    if ($time === false) {
        return '—';
    }
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('d M Y', $time);
}

/**
 * Get client IP address safely.
 *
 * @return string
 */
function client_ip(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $ip);
                $ip = trim($parts[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Generate a cryptographically secure random token.
 *
 * @param int $bytes
 * @return string hex token
 */
function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Hash a token for database storage (SHA-256).
 * Storing only the hash protects tokens at rest.
 *
 * @param string $token
 * @return string
 */
function hash_token(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Human-readable label for a programme type slug.
 *
 * @param string $type
 * @return string
 */
function programme_type_label(string $type): string
{
    return PROGRAMME_TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

/**
 * Human-readable label for a cohort delivery mode slug.
 *
 * @param string $mode
 * @return string
 */
function cohort_delivery_label(string $mode): string
{
    return COHORT_DELIVERY_LABELS[$mode] ?? ucfirst($mode);
}

/**
 * Alias for cohort_delivery_label (legacy / shorter name).
 *
 * @param string $mode
 * @return string
 */
function delivery_mode_label(string $mode): string
{
    return COHORT_DELIVERY_LABELS[$mode] ?? ucfirst($mode);
}

/**
 * Human-readable label for a qualification level slug.
 *
 * @param string $level
 * @return string
 */
function qualification_label(string $level): string
{
    return QUALIFICATION_LEVEL_LABELS[$level] ?? ucfirst(str_replace('_', ' ', $level));
}

/**
 * Render a coloured status badge for programme/cohort statuses.
 *
 * @param string $status
 * @param string $label
 * @return string
 */
function status_badge(string $status, string $label): string
{
    $map = [
        'draft'      => 'muted',
        'active'     => 'green',
        'open'       => 'green',
        'paused'     => 'amber',
        'closed'     => 'amber',
        'completed'  => 'primary',
        'archived'   => 'gray',
    ];
    $tone = $map[$status] ?? 'primary';
    return '<span class="tag tag--' . $tone . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Best-effort friendly browser/OS label from a User-Agent string.
 * Used by the Settings "Sessions" section to describe a device.
 * Never used for authentication decisions.
 *
 * @param string|null $userAgent
 * @return string
 */
function self_detect_browser(?string $userAgent): string
{
    $ua = (string) $userAgent;
    if ($ua === '') {
        return 'Unknown device';
    }

    $label = '';

    // Operating system
    if (preg_match('/Windows NT 10\.0/i', $ua))            $label .= 'Windows';
    elseif (preg_match('/Windows NT 6\.3/i', $ua))         $label .= 'Windows';
    elseif (preg_match('/Windows/i', $ua))                 $label .= 'Windows';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua))        $label .= 'iOS';
    elseif (preg_match('/Android/i', $ua))                 $label .= 'Android';
    elseif (preg_match('/Mac OS X/i', $ua))                $label .= 'macOS';
    elseif (preg_match('/Linux/i', $ua))                   $label .= 'Linux';
    else                                                   $label .= 'OS';

    // Browser
    if (preg_match('/Edg\//i', $ua))                        $label .= ' • Edge';
    elseif (preg_match('/OPR\//i', $ua))                    $label .= ' • Opera';
    elseif (preg_match('/Firefox\//i', $ua))                $label .= ' • Firefox';
    elseif (preg_match('/Chrome\//i', $ua))                 $label .= ' • Chrome';
    elseif (preg_match('/Safari\//i', $ua))                 $label .= ' • Safari';
    elseif (preg_match('/MSIE|Trident/i', $ua))             $label .= ' • Internet Explorer';
    else                                                    $label .= ' • Browser';

    return $label;
}

