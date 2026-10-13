<?php
/**
 * ================================================
 * INVESTHOOD IT - Authentication Controller
 * ================================================
 * Central business-logic controller for all
 * authentication flows: registration, login, logout,
 * email verification, password reset and remember-me.
 */

class AuthController
{
    /**
     * Handle user registration.
     *
     * @param array $input  Raw POST data
     * @return array ['success' => bool, 'errors' => array, 'user' => array|null]
     */
    public static function register(array $input): array
    {
        // Admin kill-switch: candidate self-registration can be disabled.
        if (class_exists('SystemSettings', false)) {
            try {
                if (!SystemSettings::getBool('registration_enabled', true)) {
                    return ['success' => false, 'errors' => ['general' => 'Registrations are currently closed. Please try again later or contact support.'], 'user' => null];
                }
            } catch (Throwable $ex) {
            }
        }
        // ---- 1. Sanitise input ----
        $clean = [
            'first_name'           => Sanitizer::name($input['first_name'] ?? ''),
            'last_name'            => Sanitizer::name($input['last_name'] ?? ''),
            'username'             => Sanitizer::username($input['username'] ?? ''),
            'email'                => Sanitizer::email($input['email'] ?? ''),
            'phone'                => Sanitizer::phone($input['phone'] ?? ''),
            'date_of_birth'        => trim($input['date_of_birth'] ?? ''),
            'gender'               => trim($input['gender'] ?? ''),
            'province'             => trim($input['province'] ?? ''),
            'employment_status'    => trim($input['employment_status'] ?? ''),
            'qualification_level'  => trim($input['qualification_level'] ?? ''),
            'professional_title'   => Sanitizer::stripTags($input['professional_title'] ?? ''),
            'password'             => (string) ($input['password'] ?? ''),
            'confirm_password'     => (string) ($input['confirm_password'] ?? ''),
            'terms'                => $input['terms'] ?? '',
            'privacy'              => $input['privacy'] ?? '',
            'admin_consent'        => $input['admin_consent'] ?? '',
        ];

        // ---- 2. Validate ----
        $validator = new Validator();
        $validator->validate($clean, [
            'first_name'          => ['required', 'min:2', 'max:50'],
            'last_name'           => ['required', 'min:2', 'max:50'],
            'username'            => ['required', 'min:3', 'max:30', 'alphanumeric', 'unique:users,username'],
            'email'               => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone'               => ['required', 'phone'],
            'date_of_birth'       => ['required'],
            'province'            => ['required', 'in:' . implode(',', ALLOWED_PROVINCES)],
            'employment_status'   => ['required', 'in:' . implode(',', ALLOWED_EMPLOYMENT_STATUS)],
            'qualification_level' => ['required', 'in:' . implode(',', ALLOWED_QUALIFICATIONS)],
            'password'            => ['required', 'min:8', 'strength'],
            'confirm_password'    => ['required', 'matches:password'],
            'terms'               => ['checked'],
            'privacy'             => ['checked'],
            'admin_consent'       => ['checked'],
        ]);

        if (!$validator->passes()) {
            return ['success' => false, 'errors' => $validator->errors(), 'user' => null];
        }

        // Validate date of birth (must be in the past & plausible age)
        $dob = $clean['date_of_birth'];
        if (!self::isValidDate($dob) || $dob >= date('Y-m-d') || $dob < date('Y-m-d', strtotime('-100 years'))) {
            $errors = $validator->errors();
            $errors['date_of_birth'] = 'Please enter a valid date of birth.';
            return ['success' => false, 'errors' => $errors, 'user' => null];
        }

        // ---- 3. Check duplicate email/username again (defence in depth) ----
        if (User::emailExists($clean['email'])) {
            return ['success' => false, 'errors' => ['email' => 'This email address is already registered.'], 'user' => null];
        }
        if (User::usernameExists($clean['username'])) {
            return ['success' => false, 'errors' => ['username' => 'This username is already taken.'], 'user' => null];
        }

        // ---- 4. Assign the Candidate role (public registration policy) ----
        // Public self-registration is ONLY allowed for Candidates.
        // Any client-submitted "role" value is deliberately ignored here —
        // staff roles (admin, recruiter, supervisor, etc.) are created
        // exclusively by an administrator via the admin dashboard.
        $role = Role::findBySlug(ROLE_CANDIDATE);
        if (!$role) {
            return ['success' => false, 'errors' => ['general' => 'Registration is temporarily unavailable.'], 'user' => null];
        }

        // ---- 5. Hash password & create user ----
        $passwordHash = password_hash($clean['password'], PASSWORD_DEFAULT);

        try {
            Database::beginTransaction();

            $userId = User::create([
                'role_id'             => (int) $role['id'],
                'first_name'          => $clean['first_name'],
                'last_name'           => $clean['last_name'],
                'username'            => $clean['username'],
                'email'               => $clean['email'],
                'phone'               => $clean['phone'],
                'date_of_birth'       => $clean['date_of_birth'],
                'gender'              => $clean['gender'] !== '' ? $clean['gender'] : null,
                'province'            => $clean['province'],
                'employment_status'   => $clean['employment_status'],
                'qualification_level' => $clean['qualification_level'],
                'professional_title'  => $clean['professional_title'] !== '' ? $clean['professional_title'] : null,
                'password_hash'       => $passwordHash,
                'status'              => STATUS_PENDING,
            ]);

            // ---- 6. Generate verification token & send email ----
            // When verification is not required, activate immediately.
            $verifyRequired = true;
            if (class_exists('SystemSettings', false)) {
                try {
                    $verifyRequired = SystemSettings::getBool('email_verification_required', true);
                } catch (Throwable $ex) {
                }
            }
            if (!$verifyRequired) {
                User::update($userId, ['email_verified_at' => date('Y-m-d H:i:s'), 'status' => STATUS_ACTIVE]);
            } else {
                $token = Token::generate();
                EmailVerification::create($userId, Token::hash($token));
                $sendIt = true;
                if (class_exists('SettingsMail', false)) {
                    try {
                        $sendIt = SettingsMail::allowed('verification');
                    } catch (Throwable $ex) {
                    }
                }
                if ($sendIt) {
                    $emailSent = Mailer::sendVerificationEmail($clean['email'], $clean['first_name'], $token);
                    if (!$emailSent) {
                        error_log('[Auth] Verification email failed for user ' . $userId);
                    }
                }
            }

            Database::commit();

            // Welcome email (honours the registration toggle; the master
            // mail switch inside Mailer::send is the final backstop).
            try {
                $sendWelcome = true;
                if (class_exists('SettingsMail', false)) {
                    $sendWelcome = SettingsMail::allowed('registration');
                }
                if ($sendWelcome) {
                    $site = class_exists('SystemSettings', false) ? SystemSettings::get('site_name', APP_NAME) : APP_NAME;
                    Mailer::send(
                        $clean['email'],
                        $clean['first_name'],
                        'Welcome to ' . $site,
                        '<p>Hello ' . htmlspecialchars($clean['first_name']) . ',</p>'
                        . '<p>Welcome to the ' . htmlspecialchars($site) . ' talent community. Your account has been created successfully.</p>'
                        . '<p>You can now sign in and complete your profile to start applying for opportunities.</p>'
                    );
                }
            } catch (Throwable $welcomeEx) {
                error_log('[Auth] Welcome email failed: ' . $welcomeEx->getMessage());
            }

            $user = User::find($userId);
            return ['success' => true, 'errors' => [], 'user' => $user];

        } catch (Throwable $ex) {
            if (Database::getConnection()->errno) {
                Database::rollback();
            }
            error_log('[Auth] Registration error: ' . $ex->getMessage());
            return ['success' => false, 'errors' => ['general' => 'An error occurred during registration. Please try again.'], 'user' => null];
        }
    }

    /**
     * Handle user login.
     *
     * @param array $input
     * @return array ['success' => bool, 'redirect' => string, 'errors' => array]
     */
    public static function login(array $input): array
    {
        $login    = trim($input['login'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $remember = !empty($input['remember_me']);
        $ip       = client_ip();

        // ---- 1. Basic validation ----
        if ($login === '' || $password === '') {
            return ['success' => false, 'redirect' => '', 'errors' => ['general' => 'Please enter your email/username and password.']];
        }

        // ---- 2. Brute-force check per IP (limits from System Settings) ----
        $lockMins = LOGIN_LOCKOUT_MINUTES;
        if (class_exists('SystemSettings', false)) {
            try {
                $lockMins = SystemSettings::getInt('login_lockout_minutes', LOGIN_LOCKOUT_MINUTES, 1, 120);
            } catch (Throwable $ex) {
            }
        }
        if (LoginAttempt::isIpLocked($ip)) {
            return ['success' => false, 'redirect' => '', 'errors' => ['general' => 'Too many failed attempts. Your IP is temporarily blocked for ' . $lockMins . ' minutes.']];
        }

        // ---- 3. Locate user by login ----
        $user = User::findByLogin($login);

        if (!$user) {
            LoginAttempt::record(null, $ip, $login, false);
            return ['success' => false, 'redirect' => '', 'errors' => ['general' => 'Invalid credentials. Please try again.']];
        }

        // ---- 4. Verify password ----
        if (!password_verify($password, $user['password_hash'])) {
            LoginAttempt::record((int) $user['id'], $ip, $login, false);

            // Check if account should be locked (threshold from System Settings)
            $maxAttempts = LOGIN_MAX_ATTEMPTS;
            if (class_exists('SystemSettings', false)) {
                try {
                    $maxAttempts = SystemSettings::getInt('login_max_attempts', LOGIN_MAX_ATTEMPTS, 3, 20);
                } catch (Throwable $ex) {
                }
            }
            if (LoginAttempt::countFailedForUser((int) $user['id']) >= $maxAttempts) {
                Security::lockAccount((int) $user['id']);
                return ['success' => false, 'redirect' => '', 'errors' => ['general' => 'Account temporarily locked due to too many failed attempts. Please reset your password or wait.']];
            }

            return ['success' => false, 'redirect' => '', 'errors' => ['general' => 'Invalid credentials. Please try again.']];
        }

        // ---- 5. Check email verification (skipped when admin disables it) ----
        $verifyRequired = true;
        if (class_exists('SystemSettings', false)) {
            try {
                $verifyRequired = SystemSettings::getBool('email_verification_required', true);
            } catch (Throwable $ex) {
            }
        }
        if ($verifyRequired && $user['status'] === STATUS_PENDING) {
            return ['success' => false, 'redirect' => '', 'errors' => ['unverified' => 'Please verify your email address before logging in. Check your inbox or <a href="auth/resend_verification.php">resend the verification link</a>.']];
        }

        // ---- 6. Check account status ----
        if ($user['status'] === STATUS_DISABLED) {
            return ['success' => false, 'redirect' => '', 'errors' => ['general' => 'This account has been disabled. Please contact support.']];
        }
        if ($user['status'] === STATUS_SUSPENDED) {
            return ['success' => false, 'redirect' => '', 'errors' => ['general' => 'This account has been suspended. Please contact support.']];
        }
        if ($user['status'] !== STATUS_ACTIVE) {
            return ['success' => false, 'redirect' => '', 'errors' => ['general' => 'This account is not active. Please contact support.']];
        }

        // ---- 7. Success: record login, clear attempts, create session ----
        User::recordLogin((int) $user['id']);
        LoginAttempt::clearForIp($ip);
        LoginAttempt::clearForUser((int) $user['id']);

        $role = Role::find((int) $user['role_id']);
        if (!$role) {
            $role = ['id' => (int) $user['role_id'], 'slug' => 'candidate', 'name' => 'Candidate'];
        }

        // Regenerate session (fixation prevention)
        regenerate_session();

        // Store user in session
        set_logged_in($user, $role);

        // Record session in DB
        UserSession::create(
            (int) $user['id'],
            hash('sha256', session_id()),
            $ip,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        );

        // ---- 8. Remember Me ----
        if ($remember) {
            self::createRememberMe((int) $user['id']);
        }

        // ---- 9. Determine redirect ----
        $dashboard = role_dashboard($role['slug']);
        if (!$dashboard) {
            $dashboard = 'candidate/dashboard.php';
        }

        return ['success' => true, 'redirect' => $dashboard, 'errors' => []];
    }

    /**
     * Create a remember-me cookie + DB token.
     *
     * @param int $userId
     */
    private static function createRememberMe(int $userId): void
    {
        [$selector, $validator, $hashedValidator] = Token::generateSplit();
        RememberMeToken::create($userId, $selector, $hashedValidator);

        $cookieValue = $selector . ':' . $validator;
        $expiry = time() + (REMEMBER_ME_DAYS * 86400);

        setcookie(
            'remember_me',
            $cookieValue,
            [
                'expires'  => $expiry,
                'path'     => '/',
                'secure'   => COOKIE_SECURE,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Attempt to log in a user via remember-me cookie.
     *
     * @return bool
     */
    public static function loginViaRememberMe(): bool
    {
        if (is_logged_in()) {
            return true;
        }

        $cookie = $_COOKIE['remember_me'] ?? '';
        if ($cookie === '' || !str_contains($cookie, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', $cookie, 2);

        $record = RememberMeToken::findBySelector($selector);
        if (!$record) {
            // Invalid or expired selector
            self::clearRememberMeCookie();
            return false;
        }

        if (!hash_equals($record['validator_hash'], hash('sha256', $validator))) {
            // Possible token theft — revoke all user tokens
            RememberMeToken::deleteAllForUser((int) $record['user_id']);
            self::clearRememberMeCookie();
            return false;
        }

        $user = User::find((int) $record['user_id']);
        if (!$user || $user['status'] !== STATUS_ACTIVE || empty($user['email_verified_at'])) {
            RememberMeToken::delete((int) $record['id']);
            self::clearRememberMeCookie();
            return false;
        }

        $role = Role::find((int) $user['role_id']);

        regenerate_session();
        set_logged_in($user, $role);

        UserSession::create(
            (int) $user['id'],
            hash('sha256', session_id()),
            client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        );

        return true;
    }

    /**
     * Clear the remember-me cookie.
     */
    public static function clearRememberMeCookie(): void
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

    /**
     * Handle logout: destroy session, remember-me tokens & cookies.
     */
    public static function logout(): void
    {
        // Delete remember-me token from DB
        $cookie = $_COOKIE['remember_me'] ?? '';
        if ($cookie !== '' && str_contains($cookie, ':')) {
            $selector = explode(':', $cookie, 2)[0];
            RememberMeToken::deleteBySelector($selector);
        }

        // Deactivate DB session record
        if (session_status() === PHP_SESSION_ACTIVE) {
            UserSession::deactivate(hash('sha256', session_id()));
        }

        // Clear remember-me cookie
        self::clearRememberMeCookie();

        // Destroy the session
        destroy_session();
    }

    /**
     * Verify an email address using a token.
     *
     * @param string $plainToken
     * @return array ['success' => bool, 'message' => string]
     */
    public static function verifyEmail(string $plainToken): array
    {
        if ($plainToken === '') {
            return ['success' => false, 'message' => 'Verification token is missing.'];
        }

        $hashed = Token::hash($plainToken);
        $record = EmailVerification::findValid($hashed);

        if (!$record) {
            return ['success' => false, 'message' => 'This verification link is invalid or has expired. Please request a new one.'];
        }

        $user = User::find((int) $record['user_id']);
        if (!$user) {
            return ['success' => false, 'message' => 'User account not found.'];
        }

        if (!empty($user['email_verified_at'])) {
            // Already verified — mark token used to prevent reuse
            EmailVerification::markUsed((int) $record['id']);
            return ['success' => true, 'message' => 'Your email has already been verified. You can log in now.'];
        }

        // Mark verified & activate account
        EmailVerification::markUsed((int) $record['id']);
        EmailVerification::invalidateForUser((int) $user['id']);

        User::update((int) $user['id'], [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'status'            => STATUS_ACTIVE,
        ]);

        return ['success' => true, 'message' => 'Your email has been verified successfully. You can now log in.'];
    }

    /**
     * Resend the verification email for a pending account.
     *
     * @param string $email
     * @return array
     */
    public static function resendVerification(string $email): array
    {
        $email = Sanitizer::email($email);
        if ($email === '') {
            return ['success' => false, 'message' => 'Please enter your email address.'];
        }

        $user = User::findByEmail($email);
        if (!$user) {
            // Don't reveal whether email exists
            return ['success' => true, 'message' => 'If that email is registered, a new verification link has been sent.'];
        }

        if (!empty($user['email_verified_at']) || $user['status'] !== STATUS_PENDING) {
            return ['success' => true, 'message' => 'If that email is registered, a new verification link has been sent.'];
        }

        // Invalidate old tokens & create a new one
        EmailVerification::invalidateForUser((int) $user['id']);

        $token = Token::generate();
        EmailVerification::create((int) $user['id'], Token::hash($token));
        Mailer::sendVerificationEmail($user['email'], $user['first_name'], $token);

        return ['success' => true, 'message' => 'A new verification link has been sent to your email address.'];
    }

    /**
     * Handle forgot-password request.
     * Works for every role (admin, staff, candidate) — lookup is by
     * email only. Always returns a generic message so callers can
     * never learn whether an address is registered. Applies
     * per-email + per-IP rate limiting to prevent abuse.
     *
     * @param string $email
     * @return array ['success' => bool, 'message' => string, 'rate_limited' => bool]
     */
    public static function forgotPassword(string $email): array
    {
        $generic = 'If an account exists for that email address, a password reset link has been sent. Please check your inbox (and spam folder). The link expires in 30 minutes.';
        $email = Sanitizer::email($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.', 'rate_limited' => false];
        }

        $ip = client_ip();

        // Rate limit BEFORE any user lookup (identical path either way).
        if (!PasswordResetRequest::isAllowed($email, $ip)) {
            PasswordResetRequest::record($email, $ip);
            return ['success' => true, 'message' => $generic, 'rate_limited' => true];
        }
        PasswordResetRequest::record($email, $ip);

        $user = User::findByEmail($email);

        // Unknown address: generic success, nothing else.
        if (!$user) {
            return ['success' => true, 'message' => $generic, 'rate_limited' => false];
        }

        try {
            // Invalidate previous reset tokens so only the newest link works.
            PasswordReset::invalidateForUser((int) $user['id']);

            // Secure token; store ONLY its SHA-256 hash.
            $token = Token::generate(32);
            PasswordReset::create((int) $user['id'], Token::hash($token));
            // Delivery of the reset email honours the master mail switch
            // inside Mailer::send; no per-type toggle applies here.
            $sent = Mailer::sendPasswordResetEmail((string) $user['email'], (string) ($user['first_name'] ?? ''), $token);
            if (!$sent) {
                error_log('[Auth] Password-reset email failed for user ' . (int) $user['id']);
            }
        } catch (Throwable $ex) {
            error_log('[Auth] forgotPassword error: ' . $ex->getMessage());
        }

        return ['success' => true, 'message' => $generic, 'rate_limited' => false];
    }

    /**
     * Handle password reset submission.
     *
     * @param string $plainToken
     * @param string $password
     * @param string $confirm
     * @return array
     */
    public static function resetPassword(string $plainToken, string $password, string $confirm): array
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || !preg_match('/^[a-f0-9]{32,128}$/i', $plainToken)) {
            return ['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.', 'errors' => []];
        }

        // Validate password FIRST so weak/mismatched attempts do NOT burn the token.
        $errors = [];
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors['password'] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one number.';
        } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one special character.';
        }
        if ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Please fix the validation errors.', 'errors' => $errors];
        }

        // Atomically claim the token (single UPDATE … WHERE unused AND unexpired).
        try {
            $record = PasswordReset::claimValid(Token::hash($plainToken));
        } catch (Throwable $ex) {
            error_log('[Auth] resetPassword claim error: ' . $ex->getMessage());
            return ['success' => false, 'message' => 'We could not update your password right now. Please try again or request a new link.', 'errors' => []];
        }
        if (!$record) {
            return ['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.', 'errors' => []];
        }

        $userId = (int) $record['user_id'];
        try {
            Database::beginTransaction();
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            User::update($userId, ['password_hash' => $newHash]);
            // Claim already marked this token used; clear any other outstanding ones.
            PasswordReset::invalidateForUser($userId);
            // Revoke persistent logins & sessions.
            RememberMeToken::deleteAllForUser($userId);
            UserSession::deactivateAllForUser($userId);
            Database::commit();
        } catch (Throwable $ex) {
            try {
                Database::rollback();
            } catch (Throwable $ignored) {
            }
            error_log('[Auth] resetPassword error for user ' . $userId . ': ' . $ex->getMessage());
            return ['success' => false, 'message' => 'We could not update your password right now. Please try again or request a new link.', 'errors' => []];
        }

        return ['success' => true, 'message' => 'Your password has been reset successfully. You can now log in with your new password.', 'errors' => []];
    }

    /**
     * Validate a Y-m-d date string.
     *
     * @param string $date
     * @return bool
     */
    private static function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

