<?php
/**
 * INVESTHOOD IT - Maintenance Mode Gate
 * When system_settings.maintenance_mode = 1, non-admin
 * visitors receive a 503 maintenance page. Admins, the
 * admin portal, auth endpoints and CLI always bypass.
 */

function investhood_maintenance_gate(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!class_exists('SystemSettings', false)) {
        $f = __DIR__ . '/../helpers/SystemSettings.php';
        if (is_file($f)) {
            require_once $f;
        } else {
            return;
        }
    }
    try {
        $on = SystemSettings::getBool('maintenance_mode', false);
    } catch (Throwable $ex) {
        return;
    }
    if (!$on) {
        return;
    }
    // Admins never get locked out.
    try {
        if (function_exists('current_role') && current_role() === 'admin') {
            return;
        }
        if (function_exists('is_logged_in') && is_logged_in()
            && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return;
        }
    } catch (Throwable $ex) {
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    foreach (['/admin/', '/auth/', 'settings_save', 'settings.php', 'login.php', 'logout.php'] as $allow) {
        if ($allow !== '' && (str_contains($script, $allow) || str_contains($uri, $allow))) {
            return;
        }
    }
    $msg = 'We are performing scheduled maintenance. Please check back shortly.';
    try {
        $db = SystemSettings::get('maintenance_message', $msg);
        if (trim($db) !== '') {
            $msg = $db;
        }
    } catch (Throwable $ex) {
    }
    http_response_code(503);
    header('Retry-After: 3600');
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
        . '<title>Maintenance | Investhood IT</title>'
        . '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#f1f5f9;display:flex;'
        . 'align-items:center;justify-content:center;min-height:100vh;margin:0}'
        . '.card{background:#fff;border-radius:16px;padding:2.5rem;max-width:520px;text-align:center;'
        . 'box-shadow:0 10px 30px rgba(0,0,0,.08)}</style></head><body>'
        . '<div class="card"><h1>Scheduled Maintenance</h1>'
        . '<p>' . $safe . '</p>'
        . '<p><a href="login.php">Admin sign in</a></p></div></body></html>';
    exit;
}
