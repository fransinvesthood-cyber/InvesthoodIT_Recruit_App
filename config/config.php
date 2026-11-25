<?php
/**
 * ================================================
 * INVESTHOOD IT - Configuration File
 * ================================================
 * Central application configuration. Values defined
 * here are used across the entire authentication
 * system. In production, ensure APP_ENV is set to
 * 'production' and DB credentials are hardened.
 */

// --- Load .env first (secrets stay out of version control) ---
require_once __DIR__ . '/env.php';

// -------------------------------------------------
// Application Settings
// -------------------------------------------------
define('APP_NAME', 'Investhood IT');
define('APP_ENV', env('APP_ENV', 'production'));                  // Set APP_ENV=development explicitly for local development
define('APP_URL', rtrim((string) env('APP_URL', 'https://investhoodit.com/recruitment-project'), '/')); // Base URL (no trailing slash)
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Africa/Johannesburg'));

// -------------------------------------------------
// Database Settings (MySQLi)
// -------------------------------------------------
define('DB_HOST', (string) env('DB_HOST', 'localhost'));
define('DB_NAME', (string) env('DB_NAME', 'investhood_platform'));
define('DB_USER', (string) env('DB_USER', ''));
define('DB_PASS', (string) env('DB_PASS', ''));
define('DB_CHARSET', (string) env('DB_CHARSET', 'utf8mb4'));

// Production must never fall back to credentials embedded in source code.
// Configure DB_HOST, DB_NAME, DB_USER and DB_PASS in the server environment or .env.
if (APP_ENV === 'production' && (DB_NAME === '' || DB_USER === '' || DB_PASS === '')) {
    error_log('[CONFIG] Required production database environment variables are missing.');
    http_response_code(500);
    exit('Application configuration error. Please contact the system administrator.');
}


// -------------------------------------------------
// Security Settings
// -------------------------------------------------
define('PASSWORD_MIN_LENGTH', 8);                 // Minimum password length
define('LOGIN_MAX_ATTEMPTS', 5);                  // Failed attempts before lockout
define('LOGIN_LOCKOUT_MINUTES', 15);              // Lockout duration in minutes
define('SESSION_TIMEOUT_MINUTES', 30);            // Idle session timeout
define('REMEMBER_ME_DAYS', 30);                   // Remember-me cookie lifetime (days)
define('EMAIL_VERIFY_EXPIRY_HOURS', 24);          // Verification token lifetime
define('PASSWORD_RESET_EXPIRY_MINUTES', (int) env('PASSWORD_RESET_EXPIRY_MINUTES', 30)); // Password reset token lifetime (minutes) — spec: 30 min
define('PASSWORD_RESET_EXPIRY_HOURS', 1);         // @deprecated — kept for backward compatibility only
define('PASSWORD_RESET_MAX_REQUESTS_PER_HOUR', 5); // Rate limit: reset requests per email/hour
define('PASSWORD_RESET_MAX_REQUESTS_PER_IP_HOUR', 10); // Rate limit: reset requests per IP/hour
define('COOKIE_SECURE', filter_var(env('COOKIE_SECURE', APP_ENV === 'production'), FILTER_VALIDATE_BOOLEAN)); // Secure by default in production

// -------------------------------------------------
// PHPMailer / SMTP Settings (via environment — never commit secrets)
// -------------------------------------------------
define('MAIL_ENABLED', filter_var(env('MAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN)); // Set true once SMTP is configured
define('MAIL_HOST', (string) env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) env('MAIL_PORT', 587));
define('MAIL_USERNAME', (string) env('MAIL_USERNAME', '')); // Your SMTP username
define('MAIL_PASSWORD', (string) env('MAIL_PASSWORD', '')); // Your SMTP password / app password
define('MAIL_FROM_EMAIL', (string) env('MAIL_FROM_EMAIL', 'no-reply@investhoodit.co.za'));
define('MAIL_FROM_NAME', (string) env('MAIL_FROM_NAME', 'Investhood IT'));
define('MAIL_ENCRYPTION', (string) env('MAIL_ENCRYPTION', 'tls'));                 // 'tls' | 'ssl'

