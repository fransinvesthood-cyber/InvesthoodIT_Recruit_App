<?php
/**
 * ================================================
 * INVESTHOOD IT - Create User Page
 * ================================================
 * Admin-only user creation form.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user  = current_user();
$old   = $_SESSION['user_form_old'] ?? [];
$errors = $_SESSION['user_form_errors'] ?? [];
unset($_SESSION['user_form_old'], $_SESSION['user_form_errors']);

// Fetch roles for the dropdown
$roles = Database::fetchAll("SELECT id, name, slug FROM roles ORDER BY name ASC");

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create User | Investhood IT Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body class="dashboard-page admin-dashboard pm-module">
  <div class="dashboard">
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li>
        </ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php#admin-users') ?>" class="sidebar__link active"><i class="fas fa-users"></i> User Management</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin') ?></span><span class="sidebar__user-role">Administrator</span></div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <a href="<?= url('admin/dashboard.php#admin-users') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Users</a>
        </div>
      </header>
      <div class="dash-content">
        <div class="pm-form-container">
          <div class="pm-form-header">
            <div class="pm-form-header__icon"><i class="fas fa-user-plus"></i></div>
            <div class="pm-form-header__text">
              <h1>Create New User</h1>
              <p>Add a new user account to the platform with a specific role and permissions.</p>
            </div>
          </div>
          <form method="POST" action="<?= url('admin/user_store.php') ?>" class="pm-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if (!empty($errors['general'])): ?>
              <div class="alert alert--danger"><i class="fas fa-exclamation-circle"></i> <?= e($errors['general']) ?></div>
            <?php endif; ?>
            <section class="pm-form-section">
              <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-user"></i></span><div><h3>Personal Information</h3><p>Basic details for the new user account.</p></div></div>
              <div class="pm-form-section__body">
                <div class="pm-form-row">
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="first_name">First Name <span class="pm-req">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-input" value="<?= e($old['first_name'] ?? '') ?>" required placeholder="Enter first name">
                    <?= field_error($errors, 'first_name') ?>
                  </div>
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="last_name">Last Name <span class="pm-req">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-input" value="<?= e($old['last_name'] ?? '') ?>" required placeholder="Enter last name">
                    <?= field_error($errors, 'last_name') ?>
                  </div>
                </div>
                <div class="pm-form-row">
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="username">Username <span class="pm-req">*</span></label>
                    <input type="text" id="username" name="username" class="form-input" value="<?= e($old['username'] ?? '') ?>" required placeholder="Enter username" pattern="[a-zA-Z0-9_]{3,30}" title="3-30 characters, letters, numbers, and underscores only">
                    <?= field_error($errors, 'username') ?>
                  </div>
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="email">Email Address <span class="pm-req">*</span></label>
                    <input type="email" id="email" name="email" class="form-input" value="<?= e($old['email'] ?? '') ?>" required placeholder="Enter email address">
                    <?= field_error($errors, 'email') ?>
                  </div>
                </div>
              </div>
            </section>
            <section class="pm-form-section">
              <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-lock"></i></span><div><h3>Security</h3><p>Set the password for the new account.</p></div></div>
              <div class="pm-form-section__body">
                <div class="pm-form-row">
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="password">Password <span class="pm-req">*</span></label>
                    <input type="password" id="password" name="password" class="form-input" required placeholder="Enter password" minlength="8">
                    <?= field_error($errors, 'password') ?>
                    <small class="form-hint">Minimum 8 characters. Include letters and numbers for security.</small>
                  </div>
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="password_confirm">Confirm Password <span class="pm-req">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-input" required placeholder="Confirm password">
                    <?= field_error($errors, 'password_confirm') ?>
                  </div>
                </div>
              </div>
            </section>
            <section class="pm-form-section">
              <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-user-tag"></i></span><div><h3>Role & Status</h3><p>Assign a role and set the account status.</p></div></div>
              <div class="pm-form-section__body">
                <div class="pm-form-row">
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="role_id">Role <span class="pm-req">*</span></label>
                    <select id="role_id" name="role_id" class="form-input form-input--select" required>
                      <option value="">Select a role...</option>
                      <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= ($old['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'role_id') ?>
                  </div>
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="status">Account Status <span class="pm-req">*</span></label>
                    <select id="status" name="status" class="form-input form-input--select" required>
                      <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                      <option value="pending" <?= ($old['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                      <option value="suspended" <?= ($old['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                      <option value="disabled" <?= ($old['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                    <?= field_error($errors, 'status') ?>
                  </div>
                </div>
              </div>
            </section>
            <section class="pm-form-section">
              <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-info-circle"></i></span><div><h3>Optional Details</h3><p>Additional information for the user profile.</p></div></div>
              <div class="pm-form-section__body">
                <div class="pm-form-row">
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-input" value="<?= e($old['phone'] ?? '') ?>" placeholder="Enter phone number">
                    <?= field_error($errors, 'phone') ?>
                  </div>
                  <div class="pm-form-field">
                    <label class="pm-form-label" for="province">Province</label>
                    <select id="province" name="province" class="form-input form-input--select">
                      <option value="">Select province...</option>
                      <?php foreach (ALLOWED_PROVINCES as $prov): ?>
                        <option value="<?= e($prov) ?>" <?= ($old['province'] ?? '') === $prov ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $prov))) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'province') ?>
                  </div>
                </div>
              </div>
            </section>
            <div class="pm-form-actions">
              <a href="<?= url('admin/dashboard.php#admin-users') ?>" class="btn btn--ghost"><i class="fas fa-times"></i> Cancel</a>
              <button type="submit" class="btn btn--primary"><i class="fas fa-user-plus"></i> Create User</button>
            </div>
          </form>
        </div>
      </div>
      <footer class="dash-footer admin-footer">
        <div class="container"><div class="dash-footer__inner"><p>&copy; 2025 Investhood IT. All rights reserved.</p></div></div>
      </footer>
    </main>
  </div>
  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>