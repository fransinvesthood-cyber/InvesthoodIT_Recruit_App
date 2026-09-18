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

/**
 * Render a compact interview card for the candidate view.
 *
 * @param array $iv      Interview row (from Interview::forCandidate etc.)
 * @param bool  $isUpcoming Whether the card belongs to the upcoming list
 * @return string
 */
function candidate_interview_card(array $iv, bool $isUpcoming): string
{
    $tone        = Interview::badgeTone($iv['status'] ?? 'scheduled');
    $statusLabel = Interview::label($iv['status'] ?? 'scheduled');
    $format      = Interview::typeLabel($iv['interview_type'] ?? 'online');
    $joinUrl     = Interview::joinUrl($iv);
    $id          = (int) $iv['id'];

    $locationHtml = '';
    if (!empty($iv['location'])) {
        if ($joinUrl !== null) {
            $locationHtml = '<a href="' . e($joinUrl) . '" target="_blank" rel="noopener">' . e($iv['location']) . '</a>';
        } else {
            $locationHtml = e($iv['location']);
        }
    }

    $interviewerHtml = '';
    if (!empty($iv['interviewer_first_name'])) {
        $interviewerHtml = '<div class="interview-card__detail"><i class="fas fa-user-tie"></i> Interviewer: '
            . e($iv['interviewer_first_name'] . ' ' . $iv['interviewer_last_name']) . '</div>';
    }

    $countdown = '';
    if ($isUpcoming) {
        $countdown = '<div class="interview-card__countdown"><i class="fas fa-clock"></i>'
            . '<span class="interview-card__countdown-time">'
            . e(Interview::countdownLabel($iv['interview_date'], $iv['start_time']))
            . '</span></div>';
    }

    $joinBtn = '';
    if ($isUpcoming && $joinUrl !== null) {
        $joinBtn = '<a href="' . e($joinUrl) . '" target="_blank" rel="noopener" class="btn btn--primary btn--sm"><i class="fas fa-video"></i> Join Interview</a> ';
    }

    $locationRow = '';
    if ($locationHtml !== '') {
        $locIcon = $joinUrl !== null ? 'fa-link' : Interview::typeIcon($iv['interview_type']);
        $locationRow = '<div class="interview-card__detail"><i class="fas ' . $locIcon . '"></i> ' . $locationHtml . '</div>';
    }

    return '
    <article class="interview-card" id="interview-' . $id . '">
      <div class="interview-card__header">
        <div>
          <h3 class="interview-card__title">' . e($format) . ' Interview</h3>
          <div class="interview-card__programme">
            <i class="fas fa-briefcase"></i>' . e($iv['opportunity_title'] ?? '')
            . (!empty($iv['cohort_name']) ? ' • ' . e($iv['cohort_name']) : '')
            . (!empty($iv['programme_name']) ? ' • ' . e($iv['programme_name']) : '')
            . '</div>
        </div>
        <span class="tag tag--' . e($tone) . '">' . e($statusLabel) . '</span>
      </div>'
      . $countdown . '
      <div class="interview-card__details">
        <div class="interview-card__detail"><i class="fas fa-calendar-alt"></i> ' . e(format_date($iv['interview_date'], 'l, d F Y')) . '</div>
        <div class="interview-card__detail"><i class="fas fa-clock"></i> ' . e(Interview::timeRange($iv['start_time'], $iv['end_time'])) . '</div>
        <div class="interview-card__detail"><i class="fas ' . e(Interview::typeIcon($iv['interview_type'])) . '"></i> Format: ' . e($format) . '</div>'
        . $interviewerHtml . $locationRow . '
      </div>
      <div class="interview-card__actions">
        ' . $joinBtn . '
                <a href="#interview-modal-' . $id . '" class="btn btn--outline btn--sm"><i class="fas fa-eye"></i> View Details</a>
      </div>
    </article>';
}

/**
 * Render the full interview details modal (candidate-facing only).
 * Internal recruitment data (feedback, ratings, cancellation reason,
 * evaluator comments) is deliberately omitted.
 *
 * @param array $iv
 * @return string
 */
function candidate_interview_modal(array $iv): string
{
    $tone        = Interview::badgeTone($iv['status'] ?? 'scheduled');
    $statusLabel = Interview::label($iv['status'] ?? 'scheduled');
    $format      = Interview::typeLabel($iv['interview_type'] ?? 'online');
    $joinUrl     = Interview::joinUrl($iv);
    $id          = (int) $iv['id'];

    $locationValue = '—';
    if (!empty($iv['location'])) {
        $locationValue = $joinUrl !== null
            ? '<a href="' . e($joinUrl) . '" target="_blank" rel="noopener">' . e($iv['location']) . '</a>'
            : e($iv['location']);
    }

    $interviewerValue = !empty($iv['interviewer_first_name'])
        ? e($iv['interviewer_first_name'] . ' ' . $iv['interviewer_last_name'])
        : '—';

    $completionValue = '—';
    $st = $iv['status'] ?? '';
    if ($st === 'completed') {
        $completionValue = ((int) ($iv['has_feedback'] ?? 0)) > 0 ? 'Feedback received' : 'Awaiting feedback';
    } elseif ($st === 'cancelled') {
        $completionValue = 'Interview cancelled';
    } elseif ($st === 'no_show') {
        $completionValue = 'Candidate marked as no-show';
    } elseif ($st === 'rescheduled') {
        $completionValue = 'Interview rescheduled';
    } else {
        $completionValue = 'Pending';
    }

    $rescheduleNote = '';
    if (!empty($iv['reschedule_count']) && !empty($iv['previous_date'])) {
        $rescheduleNote = '<div class="interview-detail__row"><span class="label">Originally scheduled</span><span class="value">'
            . e(format_date($iv['previous_date'], 'd M Y') . ' ' . Interview::timeRange($iv['previous_start_time'], $iv['previous_end_time']))
            . '</span></div>';
    }

    $notesHtml = '';
    if (!empty($iv['notes'])) {
        $notesHtml = '<div class="interview-detail__instructions"><p><strong>Instructions</strong></p><p>'
            . nl2br(e($iv['notes'])) . '</p></div>';
    }

    $joinAction = '';
    if ($joinUrl !== null) {
        $joinAction = '<div class="interview-detail__row" style="margin-top:1rem;"><span class="label"></span><span class="value">'
                        . '<a href="' . e($joinUrl) . '" target="_blank" rel="noopener" class="btn btn--primary btn--sm"><i class="fas fa-video"></i> Join Interview</a>'
            . '</span></div>';
    }

    return '
  <div class="interview-modal" id="interview-modal-' . $id . '">
    <div class="interview-modal__overlay" data-close></div>
    <div class="interview-modal__panel">
      <div class="interview-modal__header">
        <div>
          <div class="interview-modal__title">' . e($format) . ' Interview</div>
          <div class="interview-modal__subtitle">' . e($iv['opportunity_title'] ?? '')
            . (!empty($iv['cohort_name']) ? ' • ' . e($iv['cohort_name']) : '') . '</div>
        </div>
        <button type="button" class="interview-modal__close" data-close aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="interview-modal__body">
        <div class="interview-detail__grid">
          <div class="interview-detail__card">
            <div class="interview-detail__row"><span class="label">Programme</span><span class="value">' . e($iv['programme_name'] ?? '—') . '</span></div>
            <div class="interview-detail__row"><span class="label">Cohort</span><span class="value">' . e($iv['cohort_name'] ?? '—') . '</span></div>
            <div class="interview-detail__row"><span class="label">Opportunity</span><span class="value">' . e($iv['opportunity_title'] ?? '—') . '</span></div>
            <div class="interview-detail__row"><span class="label">Application</span><span class="value">' . e($iv['application_reference'] ?? '—') . '</span></div>
            ' . $rescheduleNote . '
          </div>
          <div class="interview-detail__card">
            <div class="interview-detail__row"><span class="label">Interview date</span><span class="value">' . e(format_date($iv['interview_date'], 'l, d F Y')) . '</span></div>
            <div class="interview-detail__row"><span class="label">Start time</span><span class="value">' . e(date('g:i A', (int) strtotime((string) $iv['start_time']))) . '</span></div>
            <div class="interview-detail__row"><span class="label">End time</span><span class="value">' . e(date('g:i A', (int) strtotime((string) $iv['end_time']))) . '</span></div>
            <div class="interview-detail__row"><span class="label">Interview type</span><span class="value">' . e($format) . '</span></div>
            <div class="interview-detail__row"><span class="label">Interviewer</span><span class="value">' . $interviewerValue . '</span></div>
            <div class="interview-detail__row"><span class="label">Location / Meeting link</span><span class="value">' . $locationValue . '</span></div>
            <div class="interview-detail__row"><span class="label">Status</span><span class="value"><span class="tag tag--' . e($tone) . '">' . e($statusLabel) . '</span></span></div>
            <div class="interview-detail__row"><span class="label">Completion status</span><span class="value">' . e($completionValue) . '</span></div>
          </div>
          ' . $notesHtml . '
        </div>
        ' . $joinAction . '
        <div class="interview-detail__row"><span class="label">Last updated</span><span class="value">' . e(format_date($iv['updated_at'], 'd M Y, H:i')) . '</span></div>
      </div>
      <div class="interview-modal__header" style="border-top:1px solid var(--border);padding:0.75rem 1.5rem;">
        <button type="button" class="btn btn--ghost btn--sm" data-close><i class="fas fa-times"></i> Close</button>
      </div>
    </div>
  </div>';
}


