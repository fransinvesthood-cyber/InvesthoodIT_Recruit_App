<?php
/**
 * ================================================
 * INVESTHOOD IT - Bootstrap Loader
 * ================================================
 * Central entry point that loads configuration,
 * starts the secure session, registers the
 * class autoloader, and includes core helpers.
 * Include this at the top of every public script.
 */

declare(strict_types=1);

// --- Load core configuration & constants FIRST ---
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

// --- Error handling (production-safe) ---
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set(APP_TIMEZONE);

// --- Start hardened session ---
require_once __DIR__ . '/session.php';
start_session();

// --- Register PSR-4-ish autoloader for app classes ---
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\Models\\'     => __DIR__ . '/../models/',
        'App\\Helpers\\'    => __DIR__ . '/../helpers/',
        'App\\Controllers\\'=> __DIR__ . '/../controllers/',
        'App\\Middleware\\' => __DIR__ . '/../middleware/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $baseDir . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
            return;
        }
    }

// Legacy non-namespaced class names (helpers/models/controllers without prefix)
    $legacyDirs = [
        __DIR__ . '/../helpers/',
        __DIR__ . '/../models/',
        __DIR__ . '/../controllers/',
    ];
    foreach ($legacyDirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// --- Load PHPMailer if vendor exists (composer) ---
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    // Manual PHPMailer fallback
    $mailerSrc = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
    foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $mailerFile) {
        if (file_exists($mailerSrc . $mailerFile)) {
            require_once $mailerSrc . $mailerFile;
        }
    }
}

// --- Core includes ---
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/notifications.php';

// --- Middleware includes ---
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/guest.php';
require_once __DIR__ . '/../middleware/role.php';
require_once __DIR__ . '/../middleware/session_validation.php';
require_once __DIR__ . '/../middleware/csrf.php';

// --- Custom error handler (no sensitive info leaked) ---
set_exception_handler(function (Throwable $ex): void {
    error_log('[EXCEPTION] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    if (APP_ENV === 'development') {
        echo '<pre>' . htmlspecialchars($ex->getMessage()) . '</pre>';
    } else {
        echo 'A system error occurred. Please try again later.';
    }
    exit;
});

