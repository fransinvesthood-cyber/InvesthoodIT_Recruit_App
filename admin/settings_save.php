<?php
/**
 * INVESTHOOD IT - Admin System Settings Save Handler
 * POST-only. Validates CSRF + admin role, validates all
 * fields server-side, persists via SystemSettings model,
 * writes an audit log entry, redirects with flash message.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');
require_csrf();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    safe_redirect('admin/settings.php');
}

$adminId = (int) (current_user_id() ?? 0);

try {
    [$clean, $errors] = SystemSettings::validate($_POST);
} catch (Throwable $ex) {
    error_log('[Settings] validate: ' . $ex->getMessage());
    set_flash('error', 'Could Not Save', 'An unexpected error occurred. Please try again.');
    safe_redirect('admin/settings.php');
}

// Logo upload is optional and independent of other validation.
try {
    if (!empty($_FILES['logo']['tmp_name'])) {
        $newLogo = SystemSettings::handleLogoUpload($_FILES['logo']);
        if ($newLogo !== null) {
            $clean['logo_path'] = $newLogo;
        }
    }
} catch (Throwable $ex) {
    $errors['logo'] = $ex->getMessage();
}

if (!empty($errors)) {
    $_SESSION['settings_errors'] = $errors;
    $_SESSION['settings_old'] = $_POST;
    set_flash('error', 'Validation Failed', 'Please fix the highlighted fields and try again.');
    safe_redirect('admin/settings.php');
}

try {
    Database::beginTransaction();
    $changed = [];
    foreach ($clean as $key => $value) {
        $before = SystemSettings::get($key);
        if ($before !== (string) $value) {
            SystemSettings::set($key, (string) $value, $adminId > 0 ? $adminId : null);
            $changed[] = $key;
        }
    }
    Database::commit();
    SystemSettings::clearCache();

    if (!empty($changed)) {
        try {
            Database::execute(
                "INSERT INTO audit_logs (user_id, action, record_type, record_id, reason, ip_address) VALUES (?, 'settings_updated', 'system_setting', NULL, ?, ?)",
                'iss',
                [$adminId > 0 ? $adminId : 1, 'Updated: ' . implode(', ', array_slice($changed, 0, 10)), client_ip()]
            );
        } catch (Throwable $auditEx) {
            error_log('[Settings] audit: ' . $auditEx->getMessage());
        }
        set_flash('success', 'Settings Saved', count($changed) . ' setting(s) updated successfully.');
    } else {
        set_flash('info', 'No Changes', 'Everything is already up to date.');
    }
} catch (Throwable $ex) {
    try {
        Database::rollback();
    } catch (Throwable $ignored) {
    }
    error_log('[Settings] save: ' . $ex->getMessage());
    $_SESSION['settings_old'] = $_POST;
    set_flash('error', 'Save Failed', 'The settings could not be saved. Please try again.');
}

safe_redirect('admin/settings.php');
