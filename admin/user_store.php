<?php
/**
 * ================================================
 * INVESTHOOD IT - User Store Handler
 * ================================================
 * Processes the create user form submission.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_redirect('admin/user_create.php');
}

// Validate CSRF token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid security token. Please try again.');
    safe_redirect('admin/user_create.php');
}

// Collect and sanitize form data
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$username   = trim($_POST['username'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$role_id    = (int) ($_POST['role_id'] ?? 0);
$status     = $_POST['status'] ?? 'active';
$phone      = trim($_POST['phone'] ?? '');
$province   = trim($_POST['province'] ?? '');

// Validation
$errors = [];

if (empty($first_name)) {
    $errors['first_name'] = 'First name is required.';
} elseif (strlen($first_name) > 50) {
    $errors['first_name'] = 'First name must be 50 characters or less.';
}

if (empty($last_name)) {
    $errors['last_name'] = 'Last name is required.';
} elseif (strlen($last_name) > 50) {
    $errors['last_name'] = 'Last name must be 50 characters or less.';
}

if (empty($username)) {
    $errors['username'] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
    $errors['username'] = 'Username must be 3-30 characters, letters, numbers, and underscores only.';
} else {
    // Check if username already exists
    $existing = Database::fetchOne("SELECT id FROM users WHERE username = ?", 's', [$username]);
    if ($existing) {
        $errors['username'] = 'This username is already taken.';
    }
}

if (empty($email)) {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} elseif (strlen($email) > 100) {
    $errors['email'] = 'Email must be 100 characters or less.';
} else {
    // Check if email already exists
    $existing = Database::fetchOne("SELECT id FROM users WHERE email = ?", 's', [$email]);
    if ($existing) {
        $errors['email'] = 'This email address is already registered.';
    }
}

if (empty($password)) {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if ($password !== $password_confirm) {
    $errors['password_confirm'] = 'Passwords do not match.';
}

if ($role_id <= 0) {
    $errors['role_id'] = 'Please select a role.';
} else {
    // Verify role exists
    $role = Database::fetchOne("SELECT id FROM roles WHERE id = ?", 'i', [$role_id]);
    if (!$role) {
        $errors['role_id'] = 'Selected role does not exist.';
    }
}

$allowed_statuses = ['active', 'pending', 'suspended', 'disabled'];
if (!in_array($status, $allowed_statuses)) {
    $errors['status'] = 'Invalid status selected.';
}

if (!empty($province) && !in_array($province, ALLOWED_PROVINCES)) {
    $errors['province'] = 'Invalid province selected.';
}

// If there are validation errors, redirect back with errors
if (!empty($errors)) {
    $_SESSION['user_form_old'] = $_POST;
    $_SESSION['user_form_errors'] = $errors;
    safe_redirect('admin/user_create.php');
}

// Hash the password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert the new user
try {
    $sql = "INSERT INTO users (role_id, first_name, last_name, username, email, password_hash, phone, province, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    Database::execute($sql, 'issssssss', [
        $role_id,
        $first_name,
        $last_name,
        $username,
        $email,
        $password_hash,
        $phone ?: null,
        $province ?: null,
        $status
    ]);

    $new_user_id = Database::lastInsertId();

    // Log the action (optional - if there's an audit log)
    // audit_log('user_created', $new_user_id, "Created user: $username");

    set_flash('success', "User '$username' has been created successfully.");
    safe_redirect('admin/dashboard.php#admin-users');

} catch (Exception $ex) {
    error_log('[USER CREATE] Failed to create user: ' . $ex->getMessage());
    $_SESSION['user_form_old'] = $_POST;
    $_SESSION['user_form_errors'] = ['general' => 'An error occurred while creating the user. Please try again.'];
    safe_redirect('admin/user_create.php');
}
$clean = [
    'first_name'      => Sanitizer::name($_POST['first_name'] ?? ''),
    'last_name'       => Sanitizer::name($_POST['last_name'] ?? ''),
    'email'           => Sanitizer::email($_POST['email'] ?? ''),
    'username'        => Sanitizer::username($_POST['username'] ?? ''),
    'role_id'         => (int) ($_POST['role_id'] ?? 0),
    'status'          => $_POST['status'] ?? STATUS_ACTIVE,
    'password'        => (string) ($_POST['password'] ?? ''),
    'confirm_password' => (string) ($_POST['confirm_password'] ?? ''),
];

// ---- Validate ----
$validator = new Validator();
$validator->validate($clean, [
    'first_name'      => ['required', 'min:2', 'max:50'],
    'last_name'       => ['required', 'min:2', 'max:50'],
    'username'        => ['required', 'min:3', 'max:30', 'alphanumeric', 'unique:users,username'],
    'email'           => ['required', 'email', 'max:100', 'unique:users,email'],
    'password'        => ['required', 'min:8', 'strength'],
    'confirm_password' => ['required', 'matches:password'],
]);

// Validate role
if ($clean['role_id'] <= 0) {
    $errors = $validator->errors();
    $errors['role_id'] = 'Please select a valid role.';
    $validator = new Validator();
    $validator->validate($clean, []); // Reset
    // Manually set errors
    $_SESSION['user_form_errors'] = $errors;
    $_SESSION['user_form_old'] = $_POST;
    set_flash('error', 'Validation Error', 'Please correct the errors below.');
    safe_redirect('user_create.php');
}

// Verify role exists
$role = Role::find($clean['role_id']);
if (!$role) {
    $_SESSION['user_form_errors'] = ['role_id' => 'The selected role does not exist.'];
    $_SESSION['user_form_old'] = $_POST;
    set_flash('error', 'Invalid Role', 'The selected role does not exist.');
    safe_redirect('user_create.php');
}

// Validate status
$allowedStatuses = [STATUS_ACTIVE, STATUS_PENDING, STATUS_SUSPENDED, STATUS_DISABLED];
if (!in_array($clean['status'], $allowedStatuses, true)) {
    $clean['status'] = STATUS_ACTIVE;
}

// Check if validation failed
if (!$validator->passes()) {
    $_SESSION['user_form_errors'] = $validator->errors();
    $_SESSION['user_form_old'] = $_POST;
    set_flash('error', 'Validation Error', 'Please correct the errors below.');
    safe_redirect('user_create.php');
}

// ---- Create the user ----
try {
    $passwordHash = password_hash($clean['password'], PASSWORD_DEFAULT);

    $userData = [
        'role_id'      => $clean['role_id'],
        'first_name'   => $clean['first_name'],
        'last_name'    => $clean['last_name'],
        'username'     => $clean['username'],
        'email'        => $clean['email'],
        'phone'        => null,
        'date_of_birth' => null,
        'gender'       => null,
        'province'     => null,
        'employment_status' => null,
        'qualification_level' => null,
        'professional_title' => null,
        'password_hash' => $passwordHash,
        'status'       => $clean['status'],
    ];

    $newUserId = User::create($userData);

    if ($newUserId > 0) {
        set_flash(
            'success',
            'User Created',
            e($clean['first_name'] . ' ' . $clean['last_name']) . ' has been successfully created with the role: ' . e($role['name']) . '.'
        );
        safe_redirect('users.php');
    } else {
        throw new RuntimeException('Failed to create user record.');
    }
} catch (Exception $e) {
    error_log('[Admin User Create] Error: ' . $e->getMessage());
    $_SESSION['user_form_errors'] = ['general' => 'An error occurred while creating the user. Please try again.'];
    $_SESSION['user_form_old'] = $_POST;
    set_flash('error', 'Creation Failed', 'An unexpected error occurred. Please try again.');
    safe_redirect('user_create.php');
}