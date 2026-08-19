<?php
/**
 * ================================================
 * INVESTHOOD IT - Flash Notifications
 * ================================================
 * One-time session-based flash messages rendered as
 * professional toast/alert notifications.
 */

/**
 * Queue a flash notification to display on the next page load.
 *
 * @param string $type    success | error | warning | info
 * @param string $title
 * @param string $message
 */
function set_flash(string $type, string $title, string $message): void
{
    if (!isset($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }
    $_SESSION['_flash'][] = [
        'type'    => in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
        'title'   => $title,
        'message' => $message,
    ];
}

/**
 * Consume and return all queued flash notifications.
 *
 * @return array
 */
function get_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/**
 * Render flash notifications as HTML toast elements.
 *
 * @return string
 */
function render_flashes(): string
{
    $flashes = get_flashes();
    if (empty($flashes)) {
        return '';
    }

    $html = '<div class="notification-container">';
    $icons = [
        'success' => 'fa-check-circle',
        'error'   => 'fa-exclamation-circle',
        'warning' => 'fa-exclamation-triangle',
        'info'    => 'fa-info-circle',
    ];

    foreach ($flashes as $flash) {
        $icon = $icons[$flash['type']] ?? $icons['info'];
        $html .= '<div class="notification notification--' . $flash['type'] . '">';
        $html .= '<div class="notification__icon"><i class="fas ' . $icon . '"></i></div>';
        $html .= '<div class="notification__content">';
        $html .= '<div class="notification__title">' . htmlspecialchars($flash['title'], ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<div class="notification__message">' . htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '</div>';
        $html .= '<button class="notification__close" aria-label="Close" onclick="this.closest(\'.notification\').classList.add(\'removing\'); setTimeout(()=>this.closest(\'.notification\').remove(),300);"><i class="fas fa-times"></i></button>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Display inline field errors for a form.
 *
 * @param array $errors  Associative [field => message]
 * @param string $field
 * @return string
 */
function field_error(array $errors, string $field): string
{
    if (isset($errors[$field])) {
        return '<div class="form-error">' . htmlspecialchars($errors[$field], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    return '';
}

/**
 * Preserve an old input value from the previous request.
 *
 * @param string $key
 * @param string $default
 * @return string
 */
function old(string $key, string $default = ''): string
{
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

