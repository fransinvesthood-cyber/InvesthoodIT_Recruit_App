<?php
/**
 * INVESTHOOD IT - System Settings Helper
 * Database-backed platform configuration with in-request
 * caching, typed coercion and safe defaults. Table is
 * auto-created and seeded on first use.
 */

class SystemSettings
{
    /** @var array|null In-request cache key => raw string */
    private static $cache = null;

    /** Canonical defaults: key => [value, type, category, desc] */
    private const DEFAULTS = [
        'site_name' => ['Investhood IT', 'string', 'general', 'Platform name shown in titles and emails'],
        'site_description' => ['Graduate programme and talent management platform.', 'text', 'general', 'Short platform description'],
        'organisation_name' => ['Investhood IT', 'string', 'general', 'Organisation name'],
        'support_email' => ['support@investhoodit.co.za', 'email', 'general', 'Public support email address'],
        'contact_phone' => ['+27 11 234 5678', 'string', 'general', 'Public contact phone number'],
        'timezone' => ['Africa/Johannesburg', 'string', 'general', 'Default system timezone'],
        'date_format' => ['d M Y', 'string', 'general', 'Default date display format'],
        'time_format' => ['H:i', 'string', 'general', 'Default time display format'],
        'default_language' => ['en', 'string', 'general', 'Default platform language'],
        'logo_path' => ['', 'string', 'general', 'Relative path to platform logo under uploads/'],
        'registration_enabled' => ['1', 'bool', 'accounts', 'Allow new candidate self-registration'],
        'email_verification_required' => ['1', 'bool', 'accounts', 'Require email verification before login'],
        'password_min_length' => ['8', 'int', 'accounts', 'Minimum password length (6-32)'],
        'password_require_uppercase' => ['1', 'bool', 'accounts', 'Require uppercase letter'],
        'password_require_lowercase' => ['1', 'bool', 'accounts', 'Require lowercase letter'],
        'password_require_number' => ['1', 'bool', 'accounts', 'Require number'],
        'password_require_special' => ['1', 'bool', 'accounts', 'Require special character'],
        'session_timeout_minutes' => ['30', 'int', 'accounts', 'Idle session timeout minutes (5-480)'],
        'login_max_attempts' => ['5', 'int', 'accounts', 'Failed logins before lockout (3-20)'],
        'login_lockout_minutes' => ['15', 'int', 'accounts', 'Lockout duration minutes (1-120)'],
        'mail_notifications_enabled' => ['1', 'bool', 'notifications', 'Master switch for emails'],
        'notif_registration' => ['1', 'bool', 'notifications', 'Welcome email on registration'],
        'notif_verification' => ['1', 'bool', 'notifications', 'Email verification link'],
        'notif_application_status' => ['1', 'bool', 'notifications', 'Application status changes'],
        'notif_interview' => ['1', 'bool', 'notifications', 'Interview scheduling and changes'],
        'notif_offer' => ['1', 'bool', 'notifications', 'Selection and offers'],
        'notif_placement' => ['1', 'bool', 'notifications', 'Placements'],
        'notif_admin_alerts' => ['1', 'bool', 'notifications', 'System alerts to administrators'],
        'default_application_status' => ['draft', 'string', 'programmes', 'Status for new applications'],
        'applications_enabled' => ['1', 'bool', 'programmes', 'Allow candidates to apply'],
        'close_past_deadline' => ['1', 'bool', 'programmes', 'Block applications after closing date'],
        'opportunities_require_published' => ['1', 'bool', 'programmes', 'Only published opportunities accept applications'],
        'talent_pool_enabled' => ['1', 'bool', 'programmes', 'Enable talent pool visibility'],
        'auto_publish_programmes' => ['0', 'bool', 'programmes', 'New programmes default to active'],
        'maintenance_mode' => ['0', 'bool', 'maintenance', 'Take public site offline'],
        'maintenance_message' => ['We are performing scheduled maintenance. Please check back shortly.', 'text', 'maintenance', 'Message during maintenance'],
        'feature_reporting_enabled' => ['1', 'bool', 'maintenance', 'Enable reporting features'],
        'feature_chatbot_enabled' => ['1', 'bool', 'maintenance', 'Enable help chatbot widget'],
        'app_version' => ['1.0.0', 'string', 'maintenance', 'Application version (informational)'],
    ];

    public static function ensureTable(): void
    {
        try {
            Database::query(
                "CREATE TABLE IF NOT EXISTS `system_settings` ("
                . "`setting_key` VARCHAR(100) NOT NULL,"
                . "`setting_value` TEXT NULL,"
                . "`setting_type` ENUM('string','text','int','bool','email','url') NOT NULL DEFAULT 'string',"
                . "`category` VARCHAR(40) NOT NULL DEFAULT 'general',"
                . "`description` VARCHAR(255) NULL,"
                . "`updated_by` INT(10) UNSIGNED NULL,"
                . "`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
                . "PRIMARY KEY (`setting_key`),"
                . "KEY `idx_system_settings_category` (`category`)"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            foreach (self::DEFAULTS as $key => $meta) {
                Database::execute(
                    "INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES (?, ?, ?, ?, ?)",
                    'sssss',
                    [$key, (string) $meta[0], $meta[1], $meta[2], $meta[3]]
                );
            }
        } catch (Throwable $ex) {
            error_log('[SystemSettings] ensureTable: ' . $ex->getMessage());
        }
    }

    private static function loadAll(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            self::ensureTable();
            foreach (Database::fetchAll("SELECT setting_key, setting_value FROM system_settings") as $row) {
                self::$cache[(string) $row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $ex) {
            error_log('[SystemSettings] load: ' . $ex->getMessage());
        }
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    public static function get(string $key, $default = ''): string
    {
        self::loadAll();
        if (array_key_exists($key, self::$cache)) {
            return (string) self::$cache[$key];
        }
        if (isset(self::DEFAULTS[$key])) {
            return (string) self::DEFAULTS[$key][0];
        }
        return (string) $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        self::loadAll();
        if (!array_key_exists($key, self::$cache) && !isset(self::DEFAULTS[$key])) {
            return $default;
        }
        $raw = strtolower(trim(self::get($key, $default ? '1' : '0')));
        return in_array($raw, ['1', 'true', 'on', 'yes'], true);
    }

    public static function getInt(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $val = (int) self::get($key, (string) $default);
        if ($min !== null && $val < $min) {
            $val = $min;
        }
        if ($max !== null && $val > $max) {
            $val = $max;
        }
        return $val;
    }

    public static function all(): array
    {
        self::loadAll();
        $out = [];
        foreach (self::DEFAULTS as $key => $meta) {
            $out[$meta[2]][$key] = array_key_exists($key, self::$cache)
                ? (string) self::$cache[$key]
                : (string) $meta[0];
        }
        return $out;
    }

    public static function set(string $key, string $value, ?int $updatedBy = null): void
    {
        if (!isset(self::DEFAULTS[$key])) {
            throw new InvalidArgumentException('Unknown setting: ' . $key);
        }
        self::ensureTable();
        $meta = self::DEFAULTS[$key];
        Database::execute(
            "INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description, updated_by)"
            . " VALUES (?, ?, ?, ?, ?, ?)"
            . " ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)",
            'sssssi',
            [$key, $value, $meta[1], $meta[2], $meta[3], $updatedBy ?? 0]
        );
        self::clearCache();
    }

    public static function validate(array $input): array
    {
        $clean = [];
        $errors = [];
        $bools = ['registration_enabled','email_verification_required',
            'password_require_uppercase','password_require_lowercase',
            'password_require_number','password_require_special',
            'mail_notifications_enabled','notif_registration','notif_verification',
            'notif_application_status','notif_interview','notif_offer',
            'notif_placement','notif_admin_alerts','applications_enabled',
            'close_past_deadline','opportunities_require_published',
            'talent_pool_enabled','auto_publish_programmes','maintenance_mode',
            'feature_reporting_enabled','feature_chatbot_enabled'];
        foreach ($bools as $k) {
            $clean[$k] = (!empty($input[$k]) && in_array((string)$input[$k], ['1','on','true'], true)) ? '1' : '0';
        }
        $clean['site_name'] = trim((string)($input['site_name'] ?? ''));
        if ($clean['site_name'] === '' || mb_strlen($clean['site_name']) > 100) {
            $errors['site_name'] = 'Platform name is required (max 100 characters).';
        }
        $clean['site_description'] = trim((string)($input['site_description'] ?? ''));
        if (mb_strlen($clean['site_description']) > 500) {
            $errors['site_description'] = 'Description must not exceed 500 characters.';
        }
        $clean['organisation_name'] = trim((string)($input['organisation_name'] ?? ''));
        if ($clean['organisation_name'] === '' || mb_strlen($clean['organisation_name']) > 100) {
            $errors['organisation_name'] = 'Organisation name is required (max 100 characters).';
        }
        $clean['support_email'] = strtolower(trim((string)($input['support_email'] ?? '')));
        if ($clean['support_email'] === '' || !filter_var($clean['support_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['support_email'] = 'Please enter a valid support email address.';
        }
        $clean['contact_phone'] = trim((string)($input['contact_phone'] ?? ''));
        if ($clean['contact_phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $clean['contact_phone'])) {
            $errors['contact_phone'] = 'Please enter a valid phone number.';
        }
        $tz = trim((string)($input['timezone'] ?? ''));
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'Please select a valid timezone.';
        } else {
            $clean['timezone'] = $tz;
        }
        $df = (string)($input['date_format'] ?? '');
        if (!in_array($df, ['d M Y','Y-m-d','d/m/Y','m/d/Y','F j, Y'], true)) {
            $errors['date_format'] = 'Please select a valid date format.';
        } else {
            $clean['date_format'] = $df;
        }
        $tf = (string)($input['time_format'] ?? '');
        if (!in_array($tf, ['H:i','h:i A','H:i:s'], true)) {
            $errors['time_format'] = 'Please select a valid time format.';
        } else {
            $clean['time_format'] = $tf;
        }
        $lang = (string)($input['default_language'] ?? 'en');
        if (!in_array($lang, ['en','af','zu','xh','st'], true)) {
            $errors['default_language'] = 'Please select a valid language.';
        } else {
            $clean['default_language'] = $lang;
        }
        $clean['logo_path'] = trim((string)($input['existing_logo_path'] ?? self::get('logo_path')));
        $pwMin = (int)($input['password_min_length'] ?? 8);
        if ($pwMin < 6 || $pwMin > 32) {
            $errors['password_min_length'] = 'Must be between 6 and 32.';
        }
        $clean['password_min_length'] = (string)max(6, min(32, $pwMin));
        $st = (int)($input['session_timeout_minutes'] ?? 30);
        if ($st < 5 || $st > 480) {
            $errors['session_timeout_minutes'] = 'Must be between 5 and 480 minutes.';
        }
        $clean['session_timeout_minutes'] = (string)max(5, min(480, $st));
        $clean['login_max_attempts'] = (string)max(3, min(20, (int)($input['login_max_attempts'] ?? 5)));
        $clean['login_lockout_minutes'] = (string)max(1, min(120, (int)($input['login_lockout_minutes'] ?? 15)));
        $das = (string)($input['default_application_status'] ?? 'draft');
        if (!in_array($das, ['draft','submitted'], true)) {
            $errors['default_application_status'] = 'Please select a valid default status.';
        } else {
            $clean['default_application_status'] = $das;
        }
        $clean['maintenance_message'] = trim((string)($input['maintenance_message'] ?? ''));
        if (mb_strlen($clean['maintenance_message']) > 500) {
            $errors['maintenance_message'] = 'Must not exceed 500 characters.';
        }
        if ($clean['maintenance_mode'] === '1' && $clean['maintenance_message'] === '') {
            $errors['maintenance_message'] = 'A message is required when maintenance mode is on.';
        }
        $clean['app_version'] = trim((string)($input['app_version'] ?? self::get('app_version', '1.0.0')));
        if (!preg_match('/^[A-Za-z0-9._-]{1,20}$/', $clean['app_version'])) {
            $errors['app_version'] = 'Version contains invalid characters.';
        }
        return [$clean, $errors];
    }

    public static function handleLogoUpload(array $file): ?string
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? 4) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Logo upload failed. Please try again.');
        }
        if (($file['size'] ?? 0) > 2097152) {
            throw new RuntimeException('Logo must not exceed 2 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Logo must be a PNG, JPG or WebP image.');
        }
        $dir = __DIR__ . '/../uploads/logos';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new RuntimeException('Could not save the uploaded logo.');
        }
        $old = self::get('logo_path');
        if ($old !== '' && str_starts_with($old, 'uploads/logos/') && is_file(__DIR__ . '/../' . $old)) {
            @unlink(__DIR__ . '/../' . $old);
        }
        return 'uploads/logos/' . $name;
    }
}

