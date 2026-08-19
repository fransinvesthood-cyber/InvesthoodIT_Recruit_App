<?php
/**
 * ================================================
 * INVESTHOOD IT - Settings Controller
 * ================================================
 * Business logic for the candidate Settings page:
 * account info, password change, sessions, trusted
 * devices, notification preferences, privacy/
 * display preferences, data export and account
 * lifecycle actions (deactivate / deletion request).
 *
 * Every method operates strictly on the user resolved
 * from the authenticated session (never client input)
 * and uses prepared statements throughout.
 */

class SettingsController
{
    // ------------------------------------------------------------
    // NOTIFICATION CATEGORIES (canonical list)
    // ------------------------------------------------------------
    const NOTIFICATION_CATEGORIES = [
        'applications'      => 'Application Updates',
        'programmes'        => 'Programme Updates',
        'interviews'        => 'Interview Notifications',
        'opportunities'     => 'Opportunity Notifications',
        'talent_pool'       => 'Talent Pool Notifications',
        'learning'          => 'Learning & Development',
        'system'            => 'System Notifications',
    ];

    // Categories that are critical and cannot be disabled.
    const REQUIRED_NOTIFICATION_CATEGORIES = ['system'];

    // ------------------------------------------------------------
    // SETTINGS KEYS (extensible)
    // ------------------------------------------------------------
    const SETTING_THEME       = 'theme';          // system|light|dark  (dark = coming soon)
    const SETTING_LANG        = 'email_language'; // en
    const SETTING_TIMEZONE    = 'timezone';       // auto|Africa/Johannesburg
    const SETTING_DATE_FORMAT = 'date_format';    // DD/MM/YYYY etc.

    // ------------------------------------------------------------
    // ACCOUNT INFORMATION
    // ------------------------------------------------------------
    /**
     * Update editable account fields (name, username, email, phone).
     * System-controlled fields (id, role, created_at, status) are
     * never accepted here.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors (by reference)
     * @return bool
     */
    public static function updateAccount(int $userId, array $input, array &$errors): bool
    {
        $clean = [
            'first_name' => Sanitizer::name($input['first_name'] ?? ''),
            'last_name'  => Sanitizer::name($input['last_name'] ?? ''),
            'username'   => Sanitizer::username($input['username'] ?? ''),
            'email'      => Sanitizer::email($input['email'] ?? ''),
            'phone'      => Sanitizer::phone($input['phone'] ?? ''),
        ];

        $validator = new Validator();
        $validator->validate($clean, [
            'first_name' => ['required', 'min:2', 'max:50'],
            'last_name'  => ['required', 'min:2', 'max:50'],
            'username'   => ['required', 'min:3', 'max:30', 'alphanumeric', 'unique:users,username,' . $userId],
            'email'      => ['required', 'email', 'max:100', 'unique:users,email,' . $userId],
            'phone'      => ['required', 'phone'],
        ]);

        if (!$validator->passes()) {
            $errors = $validator->errors();
            return false;
        }

        // Defence in depth
        if (User::usernameExists($clean['username'], $userId)) {
            $errors['username'] = 'This username is already taken.';
            return false;
        }
        if (User::emailExists($clean['email'], $userId)) {
            $errors['email'] = 'This email address is already registered.';
            return false;
        }

        $user = User::find($userId);
        $emailChanged = $user && strtolower(trim($user['email'])) !== strtolower($clean['email']);

        User::update($userId, [
            'first_name' => $clean['first_name'],
            'last_name'  => $clean['last_name'],
            'username'   => $clean['username'],
            'email'      => $clean['email'],
            'phone'      => $clean['phone'],
        ]);

        // Keep the session display name/email in sync.
        $_SESSION['fullname'] = trim($clean['first_name'] . ' ' . $clean['last_name']);
        $_SESSION['username'] = $clean['username'];
        $_SESSION['email']    = $clean['email'];

        AuditLog::log($userId, 'account_updated', 'user', $userId, 'Account information updated via settings.');

        // If the email changed, mark it unverified so the user can re-verify.
        if ($emailChanged) {
            User::update($userId, ['email_verified_at' => null, 'status' => STATUS_PENDING]);
            AuditLog::log($userId, 'email_changed', 'user', $userId, 'Email address changed; verification reset.');
        }

        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    // ------------------------------------------------------------
    // EMAIL VERIFICATION
    // ------------------------------------------------------------
    /**
     * Resend a verification email for the current user.
     *
     * @param int   $userId
     * @param array $errors
     * @return bool
     */
    public static function resendVerification(int $userId, array &$errors): bool
    {
        $user = User::find($userId);
        if (!$user) {
            $errors['general'] = 'User not found.';
            return false;
        }

        if (!empty($user['email_verified_at'])) {
            $errors['general'] = 'Your email is already verified.';
            return false;
        }

        EmailVerification::invalidateForUser($userId);
        $token = Token::generate();
        EmailVerification::create($userId, Token::hash($token));
        $sent = Mailer::sendVerificationEmail($user['email'], $user['first_name'], $token);

        if (!$sent) {
            $errors['general'] = 'We could not send the verification email right now. Please try again later.';
            return false;
        }

        AuditLog::log($userId, 'verification_email_sent', 'user', $userId, 'Verification email resent from settings.');
        return true;
    }

    // ------------------------------------------------------------
    // PASSWORD CHANGE
    // ------------------------------------------------------------
    /**
     * Change the user's password after verifying the current one.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors
     * @return bool
     */
    public static function changePassword(int $userId, array $input, array &$errors): bool
    {
        $current       = (string) ($input['current_password'] ?? '');
        $newPassword   = (string) ($input['new_password'] ?? '');
        $confirm       = (string) ($input['confirm_password'] ?? '');

        $user = User::find($userId);
        if (!$user) {
            $errors['current_password'] = 'User not found.';
            return false;
        }

        // Verify current password
        if (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = 'Your current password is incorrect.';
            return false;
        }

        // Validate new password strength
        $strength = Security::checkPasswordStrength($newPassword);
        if (!$strength['pass']) {
            $errors['new_password'] = $strength['message'];
            return false;
        }

        if ($newPassword !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
            return false;
        }

        // Make sure the new password differs from the current one
        if (password_verify($newPassword, $user['password_hash'])) {
            $errors['new_password'] = 'Your new password must be different from your current password.';
            return false;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        User::update($userId, ['password_hash' => $newHash]);

        // Revoke other sessions & all remember-me tokens for security
        $currentHash = hash('sha256', session_id());
        UserSession::deactivateAllExcept($userId, $currentHash);
        RememberMeToken::deleteAllForUser($userId);
        self::clearRememberMeCookie();

        AuditLog::log($userId, 'password_changed', 'user', $userId, 'Password changed from settings.');

        return true;
    }

    // ------------------------------------------------------------
    // SESSIONS
    // ------------------------------------------------------------
/**
     * List session records for the Settings page.
     * Identifies the current session and strips the raw session hash
     * so it is never exposed to the client.
     *
     * @param int $userId
     * @return array
     */
    public static function sessionsForUser(int $userId): array
    {
        $records = UserSession::forUser($userId);
        $currentHash = hash('sha256', session_id());

        foreach ($records as &$r) {
            $r['is_current'] = ($r['session_hash'] ?? '') === $currentHash;
            unset($r['session_hash']);
        }
        unset($r);

        return $records;
    }

    /**
     * Log out all sessions except the current one.
     *
     * @param int $userId
     * @return bool
     */
    public static function logoutOtherSessions(int $userId): bool
    {
        $currentHash = hash('sha256', session_id());
        UserSession::deactivateAllExcept($userId, $currentHash);
        AuditLog::log($userId, 'sessions_revoked', 'user', $userId, 'Logged out other sessions.');
        return true;
    }

    /**
     * Log out the user everywhere. Requires re-authentication.
     *
     * @param int $userId
     * @return bool
     */
    public static function logoutEverywhere(int $userId): bool
    {
        UserSession::deactivateAllForUser($userId);
        RememberMeToken::deleteAllForUser($userId);
        self::clearRememberMeCookie();
        AuditLog::log($userId, 'sessions_revoked_all', 'user', $userId, 'Logged out everywhere.');
        return true;
    }

    // ------------------------------------------------------------
    // TRUSTED DEVICES (Remember Me)
    // ------------------------------------------------------------
    /**
     * List trusted devices (active remember-me tokens metadata).
     *
     * @param int $userId
     * @return array
     */
    public static function trustedDevicesForUser(int $userId): array
    {
        return RememberMeToken::forUser($userId);
    }

    /**
     * Revoke a single trusted device.
     *
     * @param int $userId
     * @param int $tokenId
     * @return bool
     */
    public static function revokeTrustedDevice(int $userId, int $tokenId): bool
    {
        return RememberMeToken::deleteById($tokenId, $userId);
    }

    /**
     * Revoke all trusted devices.
     *
     * @param int $userId
     * @return bool
     */
    public static function revokeAllTrustedDevices(int $userId): bool
    {
        RememberMeToken::deleteAllForUser($userId);
        self::clearRememberMeCookie();
        return true;
    }

    // ------------------------------------------------------------
    // NOTIFICATIONS
    // ------------------------------------------------------------
    /**
     * Current notification preferences with defaults and flags.
     *
     * @param int $userId
     * @return array
     */
    public static function notificationStateForUser(int $userId): array
    {
        $prefs = NotificationPreference::allForUser($userId);
        $state = [];

        foreach (self::NOTIFICATION_CATEGORIES as $key => $label) {
            $state[$key] = [
                'label'         => $label,
                'enabled'       => $prefs[$key]['enabled'] ?? true,
                'email_enabled' => $prefs[$key]['email_enabled'] ?? true,
                'required'      => in_array($key, self::REQUIRED_NOTIFICATION_CATEGORIES, true),
            ];
        }

        return $state;
    }

    /**
     * Save notification preferences.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors
     * @return bool
     */
    public static function saveNotificationPreferences(int $userId, array $input, array &$errors): bool
    {
        $prefs = [];
        foreach (self::NOTIFICATION_CATEGORIES as $key => $label) {
            $isRequired = in_array($key, self::REQUIRED_NOTIFICATION_CATEGORIES, true);

            // Required/system categories are always enabled.
            $enabled = $isRequired
                ? true
                : !empty($input['enabled_' . $key]);

            $emailEnabled = $isRequired
                ? true
                : !empty($input['email_' . $key]);

            $prefs[$key] = [
                'enabled'       => $enabled,
                'email_enabled' => $emailEnabled,
            ];
        }

        NotificationPreference::bulkUpsert($userId, $prefs);
        AuditLog::log($userId, 'notification_preferences_updated', 'user', $userId, 'Notification preferences saved.');
        return true;
    }

    // ------------------------------------------------------------
    // CONSENT (reuses existing ConsentController)
    // ------------------------------------------------------------
    /**
     * Consent state for all purposes.
     *
     * @param int $userId
     * @return array
     */
    public static function consentStateForUser(int $userId): array
    {
        return ConsentController::stateForUser($userId);
    }

    /**
     * Update a single consent purpose.
     *
     * @param int    $userId
     * @param string $purpose
     * @param bool   $granted
     * @param array  $errors
     * @return bool
     */
    public static function updateConsent(int $userId, string $purpose, bool $granted, array &$errors): bool
    {
        return ConsentController::update($userId, $purpose, $granted, $errors);
    }

    // ------------------------------------------------------------
    // DISPLAY PREFERENCES
    // ------------------------------------------------------------
    /**
     * Current display preferences.
     *
     * @param int $userId
     * @return array
     */
    public static function preferencesForUser(int $userId): array
    {
        $all = UserSetting::allForUser($userId);
        return [
            'theme'       => $all[self::SETTING_THEME] ?? 'system',
            'email_language' => $all[self::SETTING_LANG] ?? 'en',
            'timezone'    => $all[self::SETTING_TIMEZONE] ?? 'auto',
            'date_format' => $all[self::SETTING_DATE_FORMAT] ?? 'DD/MM/YYYY',
        ];
    }

    /**
     * Save display preferences.
     *
     * @param int   $userId
     * @param array $input
     * @param array $errors
     * @return bool
     */
    public static function savePreferences(int $userId, array $input, array &$errors): bool
    {
        $theme = trim($input['theme'] ?? 'system');
        if (!in_array($theme, ['system', 'light', 'dark'], true)) {
            $errors['theme'] = 'Please select a valid theme.';
            return false;
        }

        $emailLanguage = trim($input['email_language'] ?? 'en');
        $timezone = trim($input['timezone'] ?? 'auto');
        $dateFormat = trim($input['date_format'] ?? 'DD/MM/YYYY');

        UserSetting::bulkSet($userId, [
            self::SETTING_THEME       => $theme,
            self::SETTING_LANG        => $emailLanguage,
            self::SETTING_TIMEZONE    => $timezone,
            self::SETTING_DATE_FORMAT => $dateFormat,
        ]);

        AuditLog::log($userId, 'preferences_updated', 'user', $userId, 'Display preferences updated.');
        return true;
    }

    // ------------------------------------------------------------
    // ACCOUNT STATUS
    // ------------------------------------------------------------
    /**
     * Summary data for the account status card.
     *
     * @param int $userId
     * @return array
     */
    public static function accountStatus(int $userId): array
    {
        $user = User::find($userId);
        $profile = CandidateProfile::findByUser($userId);
        $completion = (int) ($profile['completion_percent'] ?? CandidateProfile::calculateCompletion($userId));
        $consentTalentPool = Consent::hasGranted($userId, CONSENT_TALENT_POOL);

        return [
            'status'        => $user['status'] ?? 'pending',
            'email_verified'=> !empty($user['email_verified_at']),
            'completion'    => $completion,
            'talent_pool'   => $consentTalentPool,
        ];
    }

    // ------------------------------------------------------------
    // ACCOUNT LIFECYCLE
    // ------------------------------------------------------------
    /**
     * Temporarily deactivate the account. The user is logged out
     * and must contact support to reactivate.
     *
     * @param int   $userId
     * @param string $password
     * @param array  $errors
     * @return bool
     */
    public static function deactivateAccount(int $userId, string $password, array &$errors): bool
    {
        $user = User::find($userId);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors['password'] = 'Your password is incorrect. Please try again.';
            return false;
        }

        User::update($userId, ['status' => STATUS_SUSPENDED]);
        AuditLog::log($userId, 'account_deactivated', 'user', $userId, 'Account temporarily deactivated from settings.');

        // Terminate all sessions & trusted devices
        UserSession::deactivateAllForUser($userId);
        RememberMeToken::deleteAllForUser($userId);
        self::clearRememberMeCookie();
        destroy_session();

        return true;
    }

    /**
     * Request account deletion. Stored for the responsible party
     * (Information Officer) to process subject to retention rules.
     *
     * @param int    $userId
     * @param string $password
     * @param string $reason
     * @param array  $errors
     * @return bool
     */
    public static function requestDeletion(int $userId, string $password, string $reason, array &$errors): bool
    {
        $user = User::find($userId);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors['password'] = 'Your password is incorrect. Please try again.';
            return false;
        }

        // Prevent duplicate pending requests
        $existing = Database::fetchOne(
            "SELECT id FROM deletion_requests
             WHERE user_id = ? AND status = 'pending' LIMIT 1",
            'i',
            [$userId]
        );
        if ($existing) {
            $errors['general'] = 'You already have a pending deletion request. Our team will contact you.';
            return false;
        }

        Database::execute(
            "INSERT INTO deletion_requests (user_id, status, reason)
             VALUES (?, 'pending', ?)",
            'is',
            [$userId, mb_substr($reason, 0, 500)]
        );

        AuditLog::log($userId, 'deletion_requested', 'user', $userId, 'Account deletion requested from settings.');

        return true;
    }

    // ------------------------------------------------------------
    // DATA EXPORT
    // ------------------------------------------------------------
    /**
     * Compile a personal data export for the user (their own data).
     * Returns an array of sections => rows.
     *
     * @param int $userId
     * @return array
     */
    public static function exportData(int $userId): array
    {
        $user = User::find($userId);
        $profile = CandidateProfile::findByUser($userId);

        $data = [
            'account' => [
                'User ID'    => (int) $userId,
                'Full Name'  => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'Username'   => $user['username'] ?? '',
                'Email'      => $user['email'] ?? '',
                'Phone'      => $user['phone'] ?? '',
                'Role'       => $_SESSION['role_name'] ?? 'Candidate',
                'Member Since' => $user['created_at'] ?? '',
                'Account Status' => $user['status'] ?? '',
            ],
            'profile' => [
                'Professional Title' => $profile['professional_title'] ?? '',
                'Professional Summary' => $profile['professional_summary'] ?? '',
                'Career Interests'    => $profile['career_interests'] ?? '',
            ],
            'consent' => [],
            'documents' => [],
        ];

        // Consent
        foreach (self::consentStateForUser($userId) as $purpose => $st) {
            $data['consent'][ucwords(str_replace('_', ' ', $purpose))] =
                $st['status'] . (($st['status'] === 'granted' && $st['granted_at']) ? ' (since ' . $st['granted_at'] . ')' : '');
        }

        // Documents metadata
        $docs = Document::forUser($userId);
        foreach ($docs as $doc) {
            $data['documents'][] = [
                'type'     => $doc['document_type'],
                'filename' => $doc['original_filename'],
                'uploaded' => $doc['created_at'],
            ];
        }

        return $data;
    }

    // ------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------
    private static function clearRememberMeCookie(): void
    {
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => COOKIE_SECURE,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE['remember_me']);
        }
    }
}
