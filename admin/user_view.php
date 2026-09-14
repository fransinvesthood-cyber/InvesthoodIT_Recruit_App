<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin View User Page
 * ================================================
 * Displays detailed information about a user account.
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

// ---- Fetch additional data for candidates ----
$relatedData = [];
if ($targetUser['role_slug'] === ROLE_CANDIDATE) {
    // Check for applications count
    $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM applications WHERE candidate_id = ?", 'i', [$userId]);
    $relatedData['applications'] = (int) ($row['cnt'] ?? 0);

    // Check for documents count
    $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM documents WHERE user_id = ?", 'i', [$userId]);
    $relatedData['documents'] = (int) ($row['cnt'] ?? 0);

    // Check for profile completion
    $row = Database::fetchOne("SELECT completion_percent FROM candidate_profiles WHERE user_id = ? LIMIT 1", 'i', [$userId]);
    $relatedData['profile_completion'] = $row ? (int) $row['completion_percent'] : 0;
}

// ---- Status labels ----
$statusClass = match ($targetUser['status']) {
    STATUS_ACTIVE => 'tag--green',
    STATUS_PENDING => 'tag--amber',
    STATUS_SUSPENDED => 'tag--red',
    STATUS_DISABLED => 'tag--gray',
    default => 'tag--gray',
};
$statusLabel = match ($targetUser['status']) {
    STATUS_ACTIVE => 'Active',
    STATUS_PENDING => 'Pending',
    STATUS_SUSPENDED => 'Suspended',
    STATUS_DISABLED => 'Disabled',
    default => ucfirst($targetUser['status']),
};

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="View User - Investhood IT Administrator">
  <title>View User | Investhood IT Admin</title>
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
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== FLASH NOTIFICATIONS ===== -->
        <?= $flashes ?>

        <!-- ===== USER DETAIL CARD ===== -->
        <section class="pm-form-section">
          <div class="pm-form-section__header">
            <span class="pm-form-section__icon"><i class="fas fa-user"></i></span>
            <div>
              <h3>User Details</h3>
              <p>Account information and activity summary.</p>
            </div>
          </div>

          <div class="pm-form-section__body">
            <div class="pm-detail-header">
              <div class="pm-detail-header__avatar">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($targetUser['first_name'] . '+' . $targetUser['last_name']) ?>&background=1a56db&color=fff&size=80" alt="">
              </div>
              <div class="pm-detail-header__info">
                <h2 class="pm-detail-header__title"><?= e($targetUser['first_name'] . ' ' . $targetUser['last_name']) ?></h2>
                <p class="pm-detail-header__subtitle">@<?= e($targetUser['username']) ?></p>
                <div class="pm-detail-header__tags">
                  <span class="tag tag--cyan"><?= e($targetUser['role_name']) ?></span>
                  <span class="tag <?= $statusClass ?>"><?= $statusLabel ?></span>
                </div>
              </div>
            </div>

            <div class="pm-detail-grid">
              <div class="pm-detail-item">
                <span class="pm-detail-item__label">Email Address</span>
                <span class="pm-detail-item__value"><?= e($targetUser['email']) ?></span>
              </div>
              <div class="pm-detail-item">
                <span class="pm-detail-item__label">Username</span>
                <span class="pm-detail-item__value"><?= e($targetUser['username']) ?></span>
              </div>
              <div class="pm-detail-item">
                <span class="pm-detail-item__label">Role</span>
                <span class="pm-detail-item__value"><?= e($targetUser['role_name']) ?></span>
              </div>
              <div class="pm-detail-item">
                <span class="pm-detail-item__label">Account Status</span>
                <span class="pm-detail-item__value"><span class="tag <?= $statusClass ?>"><?= $statusLabel ?></span></span>
              </div>
              <div class="pm-detail-item">
                <span class="pm-detail-item__label">Registered</span>
                <span class="pm-detail-item__value"><?= e(format_date($targetUser['created_at'], 'd M Y H:i')) ?></span>
              </div>
              <div class="pm-detail-item">
                <span class="pm-detail-item__label">Last Updated</span>
                <span class="pm-detail-item__value"><?= e(format_date($targetUser['updated_at'], 'd M Y H:i')) ?></span>
              </div>
              <div class="pm-detail-item">
                <span class="pm-detail-item__label">Last Login</span>
                <span class="pm-detail-item__value"><?= e($targetUser['last_login'] ? time_ago($targetUser['last_login']) : 'Never') ?></span>
              </div>
              <div class="pm-detail-item">
                <span class="pm-detail-item__label">Email Verified</span>
                <span class="pm-detail-item__value"><?= !empty($targetUser['email_verified_at']) ? e(format_date($targetUser['email_verified_at'], 'd M Y')) : '<span class="tag tag--amber">Not Verified</span>' ?></span>
              </div>
            </div>

            <?php if ($targetUser['role_slug'] === ROLE_CANDIDATE && !empty($relatedData)): ?>
              <div class="pm-detail-section">
                <h4 class="pm-detail-section__title">Candidate Information</h4>
                <div class="pm-detail-grid">
                  <div class="pm-detail-item">
                    <span class="pm-detail-item__label">Applications</span>
                    <span class="pm-detail-item__value"><?= number_format($relatedData['applications'] ?? 0) ?></span>
                  </div>
                  <div class="pm-detail-item">
                    <span class="pm-detail-item__label">Documents</span>
                    <span class="pm-detail-item__value"><?= number_format($relatedData['documents'] ?? 0) ?></span>
                  </div>
                  <div class="pm-detail-item">
                    <span class="pm-detail-item__label">Profile Completion</span>
                    <span class="pm-detail-item__value"><?= (int) ($relatedData['profile_completion'] ?? 0) ?>%</span>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="pm-form-actions">
            <a href="<?= url('admin/users.php') ?>" class="btn btn--ghost"><i class="fas fa-arrow-left"></i> Back to Users</a>
            <a href="<?= url('admin/user_edit.php?id=' . (int) $targetUser['id']) ?>" class="btn btn--primary"><i class="fas fa-edit"></i> Edit User</a>
          </div>
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