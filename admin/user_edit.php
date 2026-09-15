<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Edit User Page
 * ================================================
 * Form for administrators to edit an existing user account.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user = current_user();

// ---- Validate user ID ----
$userId = (int) ($_GET['id'] ?? 0);
if ($userId <= 0) {
    set_flash('error', 'Invalid User', 'No user was specified.');
    safe_redirect('users.php');
}

// ---- Fetch target user ----
$targetUser = User::findWithRole($userId);
if (!$targetUser) {
    set_flash('error', 'User Not Found', 'The specified user does not exist.');
    safe_redirect('users.php');
}

// ---- Form state ----
$old   = $_SESSION['user_form_old'] ?? [];
$errors = $_SESSION['user_form_errors'] ?? [];
unset($_SESSION['user_form_old'], $_SESSION['user_form_errors']);

// Merge old input with existing user data
$formData = array_merge($targetUser, $old);

// ---- Fetch roles for dropdown ----
$roles = Role::all();

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Edit User - Investhood IT Administrator">
  <title>Edit User | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard pm-module">

  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li>
        </ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/users.php') ?>" class="sidebar__link"><i class="fas fa-user-shield"></i> User Management</a></li>
        </ul>
        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-history"></i> Audit Log</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span>
            <span class="sidebar__user-role"><?= e($user['role_name'] ?? 'Administrator') ?></span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN ===== -->
    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <a href="<?= url('admin/users.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Users</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== FLASH NOTIFICATIONS ===== -->
        <?= $flashes ?>

        <!-- ===== PAGE HERO ===== -->
        <section class="pm-hero">
          <div class="pm-hero__inner">
            <div class="pm-hero__text">
              <span class="section__badge">User Management</span>
              <h2 class="pm-hero__title">Edit User</h2>
              <p class="pm-hero__subtitle">Update account details, role, and status for <?= e($targetUser['first_name'] . ' ' . $targetUser['last_name']) ?>.</p>
            </div>
          </div>
        </section>

        <!-- ===== EDIT USER FORM ===== -->
        <section class="pm-form-section">
          <form method="post" action="<?= url('admin/user_update.php') ?>" class="pm-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $targetUser['id'] ?>">

            <div class="pm-form-section__header">
              <span class="pm-form-section__icon"><i class="fas fa-user-edit"></i></span>
              <div>
                <h3>Account Details</h3>
                <p>Update the user's information and access settings.</p>
              </div>
            </div>

            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="first_name">First Name <span class="pm-req">*</span></label>
                  <input type="text" id="first_name" name="first_name" class="form-input" value="<?= e($formData['first_name'] ?? '') ?>" required maxlength="50" placeholder="Enter first name">
                  <?= field_error($errors, 'first_name') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="last_name">Last Name <span class="pm-req">*</span></label>
                  <input type="text" id="last_name" name="last_name" class="form-input" value="<?= e($formData['last_name'] ?? '') ?>" required maxlength="50" placeholder="Enter last name">
                  <?= field_error($errors, 'last_name') ?>
                </div>
              </div>

              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="email">Email Address <span class="pm-req">*</span></label>
                  <input type="email" id="email" name="email" class="form-input" value="<?= e($formData['email'] ?? '') ?>" required maxlength="100" placeholder="Enter email address">
                  <?= field_error($errors, 'email') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="username">Username <span class="pm-req">*</span></label>
                  <input type="text" id="username" name="username" class="form-input" value="<?= e($formData['username'] ?? '') ?>" required maxlength="30" placeholder="Enter username" pattern="[a-zA-Z0-9_]+">
                  <?= field_error($errors, 'username') ?>
                </div>
              </div>

              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="role_id">Role <span class="pm-req">*</span></label>
                  <select id="role_id" name="role_id" class="form-input form-input--select" required>
                    <option value="">Select a role...</option>
                    <?php foreach ($roles as $role): ?>
                      <option value="<?= (int) $role['id'] ?>" <?= ($formData['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'role_id') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="status">Account Status <span class="pm-req">*</span></label>
                  <select id="status" name="status" class="form-input form-input--select" required>
                    <option value="<?= STATUS_ACTIVE ?>" <?= ($formData['status'] ?? '') === STATUS_ACTIVE ? 'selected' : '' ?>>Active</option>
                    <option value="<?= STATUS_PENDING ?>" <?= ($formData['status'] ?? '') === STATUS_PENDING ? 'selected' : '' ?>>Pending</option>
                    <option value="<?= STATUS_SUSPENDED ?>" <?= ($formData['status'] ?? '') === STATUS_SUSPENDED ? 'selected' : '' ?>>Suspended</option>
                    <option value="<?= STATUS_DISABLED ?>" <?= ($formData['status'] ?? '') === STATUS_DISABLED ? 'selected' : '' ?>>Disabled</option>
                  </select>
                  <?= field_error($errors, 'status') ?>
                </div>
              </div>
            </div>

            <div class="pm-form-actions">
              <a href="<?= url('admin/users.php') ?>" class="btn btn--ghost"><i class="fas fa-times"></i> Cancel</a>
              <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
          </form>
        </section>

      </div><!-- // dash-content -->

      <!-- ===== FOOTER ===== -->
      <footer class="dash-footer admin-footer">
        <div class="container">
          <div class="dash-footer__inner">
            <p>&copy; 2025 Investhood IT. All rights reserved.</p>
            <div class="dash-footer__links">
              <a href="#">Privacy Policy</a>
              <a href="<?= url('index.php') ?>">Back to Home</a>
            </div>
          </div>
        </div>
      </footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_users.js') ?>"></script>
</body>
</html>