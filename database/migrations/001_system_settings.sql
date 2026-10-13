-- ============================================================
-- INVESTHOOD IT - System Settings table
-- ============================================================
-- Stores all dynamic platform configuration as key/value rows.
-- One row per setting_key (PRIMARY KEY prevents duplicates).
-- Run once: mysql -u root investhood_platform < 001_system_settings.sql
-- The application also auto-creates this table on first use
-- (see helpers/SystemSettings.php::ensureTable()).

CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_type`  ENUM('string','text','int','bool','email','url') NOT NULL DEFAULT 'string',
  `category`      VARCHAR(40) NOT NULL DEFAULT 'general',
  `description`   VARCHAR(255) NULL,
  `updated_by`    INT(10) UNSIGNED NULL,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  KEY `idx_system_settings_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed defaults (INSERT IGNORE keeps existing values on re-run).
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`) VALUES
-- General
('site_name', 'Investhood IT', 'string', 'general', 'Platform name shown in titles and emails'),
('site_description', 'Graduate programme and talent management platform connecting candidates with opportunities.', 'text', 'general', 'Short platform description'),
('organisation_name', 'Investhood IT', 'string', 'general', 'Organisation name'),
('support_email', 'support@investhoodit.co.za', 'email', 'general', 'Public support email address'),
('contact_phone', '+27 11 234 5678', 'string', 'general', 'Public contact phone number'),
('timezone', 'Africa/Johannesburg', 'string', 'general', 'Default system timezone'),
('date_format', 'd M Y', 'string', 'general', 'Default date display format'),
('time_format', 'H:i', 'string', 'general', 'Default time display format'),
('default_language', 'en', 'string', 'general', 'Default platform language'),
('logo_path', '', 'string', 'general', 'Relative path to platform logo under uploads/'),
-- Users & accounts
('registration_enabled', '1', 'bool', 'accounts', 'Allow new candidate self-registration'),
('email_verification_required', '1', 'bool', 'accounts', 'Require email verification before login'),
('password_min_length', '8', 'int', 'accounts', 'Minimum password length (6-32)'),
('password_require_uppercase', '1', 'bool', 'accounts', 'Password must contain an uppercase letter'),
('password_require_lowercase', '1', 'bool', 'accounts', 'Password must contain a lowercase letter'),
('password_require_number', '1', 'bool', 'accounts', 'Password must contain a number'),
('password_require_special', '1', 'bool', 'accounts', 'Password must contain a special character'),
('session_timeout_minutes', '30', 'int', 'accounts', 'Idle session timeout in minutes (5-480)'),
('login_max_attempts', '5', 'int', 'accounts', 'Failed logins before lockout (3-20)'),
('login_lockout_minutes', '15', 'int', 'accounts', 'Lockout duration in minutes (1-120)'),
-- Notifications
('mail_notifications_enabled', '1', 'bool', 'notifications', 'Master switch for platform email notifications'),
('notif_registration', '1', 'bool', 'notifications', 'Send welcome email on candidate registration'),
('notif_verification', '1', 'bool', 'notifications', 'Send email verification link'),
('notif_application_status', '1', 'bool', 'notifications', 'Notify candidates of application status changes'),
('notif_interview', '1', 'bool', 'notifications', 'Notify about interview scheduling and changes'),
('notif_offer', '1', 'bool', 'notifications', 'Notify about selection and offers'),
('notif_placement', '1', 'bool', 'notifications', 'Notify about placements'),
('notif_admin_alerts', '1', 'bool', 'notifications', 'Send system alerts to administrators'),
-- Programmes & opportunities
('default_application_status', 'draft', 'string', 'programmes', 'Status assigned to newly started applications'),
('applications_enabled', '1', 'bool', 'programmes', 'Allow candidates to start and submit applications'),
('close_past_deadline', '1', 'bool', 'programmes', 'Block applications after the closing date'),
('opportunities_require_published', '1', 'bool', 'programmes', 'Only published opportunities accept applications'),
('talent_pool_enabled', '1', 'bool', 'programmes', 'Enable talent pool visibility and matching'),
('auto_publish_programmes', '0', 'bool', 'programmes', 'New programmes default to active (otherwise draft)'),
-- Maintenance & platform
('maintenance_mode', '0', 'bool', 'maintenance', 'Take the public site offline for maintenance'),
('maintenance_message', 'We are performing scheduled maintenance. Please check back shortly.', 'text', 'maintenance', 'Message shown to users during maintenance'),
('feature_reporting_enabled', '1', 'bool', 'maintenance', 'Enable reporting and analytics features'),
('feature_chatbot_enabled', '1', 'bool', 'maintenance', 'Enable the help chatbot widget'),
('app_version', '1.0.0', 'string', 'maintenance', 'Current application version (informational)');
