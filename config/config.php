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

// -------------------------------------------------
// Application Settings
// -------------------------------------------------
define('APP_NAME', 'Investhood IT');
define('APP_ENV', 'development');                 // 'development' | 'production'
define('APP_URL', 'http://localhost/Recruitment-Project'); // Base URL (no trailing slash)
define('APP_TIMEZONE', 'Africa/Johannesburg');

// -------------------------------------------------
// Database Settings (MySQLi)
// -------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'investhood_platform');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// -------------------------------------------------
// Security Settings
// -------------------------------------------------
define('PASSWORD_MIN_LENGTH', 8);                 // Minimum password length
define('LOGIN_MAX_ATTEMPTS', 5);                  // Failed attempts before lockout
define('LOGIN_LOCKOUT_MINUTES', 15);              // Lockout duration in minutes
define('SESSION_TIMEOUT_MINUTES', 30);            // Idle session timeout
define('REMEMBER_ME_DAYS', 30);                   // Remember-me cookie lifetime (days)
define('EMAIL_VERIFY_EXPIRY_HOURS', 24);          // Verification token lifetime
define('PASSWORD_RESET_EXPIRY_HOURS', 1);         // Password reset token lifetime
define('COOKIE_SECURE', false);                   // Set true when served over HTTPS

// -------------------------------------------------
// PHPMailer / SMTP Settings
// -------------------------------------------------
define('MAIL_ENABLED', false);                    // Set true once SMTP is configured
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');                      // Your SMTP username
define('MAIL_PASSWORD', '');                      // Your SMTP password / app password
define('MAIL_FROM_EMAIL', 'no-reply@investhoodit.co.za');
define('MAIL_FROM_NAME', 'Investhood IT');
define('MAIL_ENCRYPTION', 'tls');                 // 'tls' | 'ssl'

