<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin User Management Page
 * ================================================
 * Lists all platform users with search, filters,
 * pagination, and actions (view/edit/activate/deactivate/delete).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user = current_user();

// ---- Search, filter & pagination state ----
$search   = trim($_GET['q'] ?? '');
$fRole    = $_GET['role'] ?? '';
$fStatus  = $_GET['status'] ?? '';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 15;

// ---- Fetch users with filters ----
$result = User::adminList($search, $fRole, $fStatus, $page, $perPage);
$users      = $result['users'];
$totalUsers = $result['total'];
$totalPages = $result['pages'];
$currentPage = $result['page'];

// ---- Fetch stats ----
$statsTotal    = User::countAll();
$statsActive   = User::countByStatus(STATUS_ACTIVE);
$statsInactive = User::countByStatus(STATUS_SUSPENDED) + User::countByStatus(STATUS_DISABLED);
$statsPending  = User::countByStatus(STATUS_PENDING);

// ---- Fetch roles for filter dropdown ----
$roles = Role::all();

// ---- Build pagination URL helper ----
function paginationUrl(int $page, string $search, string $role, string $status): string
{
    $params = ['page' => $page];
    if ($search !== '') $params['q'] = $search;
    if ($role !== '') $params['role'] = $role;
    if ($status !== '') $params['status'] = $status;
    return url('admin/users.php') . '?' . http_build_query($params);
}

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="User Management - Investhood IT Administrator">
  <title>User Management | Investhood IT Admin</title>
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
          <li><a href="<?= url('admin/users.php') ?>" class="sidebar__link active"><i class="fas fa-user-shield"></i> User Management</a></li>
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
          <div class="dash-header__notifications">
            <button class="dash-header__icon-btn" aria-label="Notifications"><i class="fas fa-bell"></i></button>
          </div>
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
              <h2 class="pm-hero__title">Manage Platform Users</h2>
              <p class="pm-hero__subtitle">View, create, edit, and manage user accounts, roles, and access permissions across the platform.</p>
            </div>
            <a href="<?= url('admin/user_create.php') ?>" class="btn btn--primary"><i class="fas fa-user-plus"></i> Add User</a>
          </div>
        </section>

        <!-- ===== STATS ROW ===== -->
        <div class="pm-stats">
          <div class="pm-stat pm-stat--total">
            <div class="pm-stat__icon"><i class="fas fa-users"></i></div>
            <div>
              <span class="pm-stat__value"><?= number_format($statsTotal) ?></span>
              <span class="pm-stat__label">Total Users</span>
            </div>
          </div>
          <div class="pm-stat pm-stat--active">
            <div class="pm-stat__icon"><i class="fas fa-user-check"></i></div>
            <div>
              <span class="pm-stat__value"><?= number_format($statsActive) ?></span>
              <span class="pm-stat__label">Active</span>
            </div>
          </div>
          <div class="pm-stat pm-stat--draft">
            <div class="pm-stat__icon"><i class="fas fa-user-clock"></i></div>
            <div>
              <span class="pm-stat__value"><?= number_format($statsPending) ?></span>
              <span class="pm-stat__label">Pending</span>
            </div>
          </div>
          <div class="pm-stat pm-stat--archived">
            <div class="pm-stat__icon"><i class="fas fa-user-slash"></i></div>
            <div>
              <span class="pm-stat__value"><?= number_format($statsInactive) ?></span>
              <span class="pm-stat__label">Inactive</span>
            </div>
          </div>
        </div>

        <!-- ===== FILTERS ===== -->
        <div class="pm-filters">
          <form method="get" action="<?= url('admin/users.php') ?>" class="pm-filters__form">
            <div class="user-search" id="userSearch">
              <i class="fas fa-search user-search__icon" aria-hidden="true"></i>
              <input type="text" name="q" id="userSearchInput" class="user-search__input" placeholder="Search by name, email, or username..." value="<?= e($search) ?>" autocomplete="off" spellcheck="false" aria-label="Search users by name, email, or username">
              <span class="user-search__spinner" id="userSearchSpinner" hidden><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i></span>
              <button type="button" class="user-search__clear" id="userSearchClear" aria-label="Clear search" title="Clear search" hidden><i class="fas fa-times-circle" aria-hidden="true"></i></button>
              <kbd class="user-search__kbd" id="userSearchKbd" aria-hidden="true">Ctrl&nbsp;K</kbd>
            </div>
            <div class="pm-filters__select">
              <select name="role" class="form-input form-input--select">
                <option value="">All Roles</option>
                <?php foreach ($roles as $role): ?>
                  <option value="<?= e($role['slug']) ?>" <?= $fRole === $role['slug'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="pm-filters__select">
              <select name="status" class="form-input form-input--select">
                <option value="">All Statuses</option>
                <option value="<?= STATUS_ACTIVE ?>" <?= $fStatus === STATUS_ACTIVE ? 'selected' : '' ?>>Active</option>
                <option value="<?= STATUS_PENDING ?>" <?= $fStatus === STATUS_PENDING ? 'selected' : '' ?>>Pending</option>
                <option value="<?= STATUS_SUSPENDED ?>" <?= $fStatus === STATUS_SUSPENDED ? 'selected' : '' ?>>Suspended</option>
                <option value="<?= STATUS_DISABLED ?>" <?= $fStatus === STATUS_DISABLED ? 'selected' : '' ?>>Disabled</option>
              </select>
            </div>
            <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $fRole !== '' || $fStatus !== ''): ?>
              <a href="<?= url('admin/users.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
          </form>
        </div>

        <?php if ($search !== '' || $fRole !== '' || $fStatus !== ''): ?>
          <p class="pm-results-count user-search__results">
            <i class="fas fa-list-ul" aria-hidden="true"></i>
            Found <strong><?= number_format($totalUsers) ?></strong> user<?= $totalUsers === 1 ? '' : 's' ?>
            <?php if ($search !== ''): ?>matching &ldquo;<strong><?= e($search) ?></strong>&rdquo;<?php endif; ?>
          </p>
        <?php endif; ?>

        <!-- ===== USERS TABLE ===== -->
        <div class="pm-table-container">
          <?php if (empty($users)): ?>
            <div class="pm-empty">
              <div class="pm-empty__icon"><i class="fas fa-users"></i></div>
              <h3>No users found</h3>
              <p><?php if ($search !== '' || $fRole !== '' || $fStatus !== ''): ?>
                No users match your current filters. <a href="<?= url('admin/users.php') ?>">Clear filters</a> to see all users.
              <?php else: ?>
                There are no registered users on the platform yet.
              <?php endif; ?></p>
            </div>
          <?php else: ?>
            <div class="pm-table-wrapper">
              <table class="pm-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Last Login</th>
                    <th class="pm-table__actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <?php
                    $statusClass = match ($u['status']) {
                        STATUS_ACTIVE => 'tag--green',
                        STATUS_PENDING => 'tag--amber',
                        STATUS_SUSPENDED => 'tag--red',
                        STATUS_DISABLED => 'tag--gray',
                        default => 'tag--gray',
                    };
                    $statusLabel = match ($u['status']) {
                        STATUS_ACTIVE => 'Active',
                        STATUS_PENDING => 'Pending',
                        STATUS_SUSPENDED => 'Suspended',
                        STATUS_DISABLED => 'Disabled',
                        default => ucfirst($u['status']),
                    };
                    ?>
                    <tr>
                      <td>
                        <div class="pm-table__user">
                          <div class="pm-table__avatar">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($u['first_name'] . '+' . $u['last_name']) ?>&background=1a56db&color=fff&size=40" alt="">
                          </div>
                          <div class="pm-table__user-info">
                            <span class="pm-table__user-name"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></span>
                            <span class="pm-table__user-username">@<?= e($u['username']) ?></span>
                          </div>
                        </div>
                      </td>
                      <td><?= e($u['email']) ?></td>
                      <td><span class="tag tag--cyan"><?= e($u['role_name']) ?></span></td>
                      <td><span class="tag <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                      <td><?= e(format_date($u['created_at'], 'd M Y')) ?></td>
                      <td><?= e($u['last_login'] ? time_ago($u['last_login']) : '—') ?></td>
                      <td class="pm-table__actions">
                        <div class="pm-table__action-group">
                          <a href="<?= url('admin/user_view.php?id=' . (int) $u['id']) ?>" class="btn btn--ghost btn--sm" title="View"><i class="fas fa-eye"></i></a>
                          <a href="<?= url('admin/user_edit.php?id=' . (int) $u['id']) ?>" class="btn btn--ghost btn--sm" title="Edit"><i class="fas fa-edit"></i></a>
                          <?php if ($u['status'] === STATUS_ACTIVE): ?>
                            <form method="post" action="<?= url('admin/user_actions.php') ?>" style="display:inline;" onsubmit="return confirm('Deactivate this user? They will not be able to log in.');">
                              <?= csrf_field() ?>
                              <input type="hidden" name="action" value="deactivate">
                              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                              <button type="submit" class="btn btn--ghost btn--sm" title="Deactivate"><i class="fas fa-user-slash"></i></button>
                            </form>
                          <?php else: ?>
                            <form method="post" action="<?= url('admin/user_actions.php') ?>" style="display:inline;" onsubmit="return confirm('Activate this user? They will be able to log in.');">
                              <?= csrf_field() ?>
                              <input type="hidden" name="action" value="activate">
                              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                              <button type="submit" class="btn btn--ghost btn--sm" title="Activate"><i class="fas fa-user-check"></i></button>
                            </form>
                          <?php endif; ?>
                          <form method="post" action="<?= url('admin/user_actions.php') ?>" style="display:inline;" onsubmit="return confirm('Delete this user permanently? This action cannot be undone.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn--ghost btn--sm pm-table__delete" title="Delete"><i class="fas fa-trash"></i></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- ===== PAGINATION ===== -->
            <?php if ($totalPages > 1): ?>
              <div class="pm-pagination">
                <div class="pm-pagination__info">
                  Showing <?= (($currentPage - 1) * $perPage) + 1 ?>–<?= min($currentPage * $perPage, $totalUsers) ?> of <?= number_format($totalUsers) ?> users
                </div>
                <div class="pm-pagination__controls">
                  <?php if ($currentPage > 1): ?>
                    <a href="<?= paginationUrl($currentPage - 1, $search, $fRole, $fStatus) ?>" class="pm-pagination__btn"><i class="fas fa-chevron-left"></i></a>
                  <?php endif; ?>

                  <?php
                  $startPage = max(1, $currentPage - 2);
                  $endPage = min($totalPages, $currentPage + 2);
                  if ($startPage > 1): ?>
                    <a href="<?= paginationUrl(1, $search, $fRole, $fStatus) ?>" class="pm-pagination__btn">1</a>
                    <?php if ($startPage > 2): ?>
                      <span class="pm-pagination__ellipsis">...</span>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="<?= paginationUrl($i, $search, $fRole, $fStatus) ?>" class="pm-pagination__btn <?= $i === $currentPage ? 'pm-pagination__btn--active' : '' ?>"><?= $i ?></a>
                  <?php endfor; ?>

                  <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                      <span class="pm-pagination__ellipsis">...</span>
                    <?php endif; ?>
                    <a href="<?= paginationUrl($totalPages, $search, $fRole, $fStatus) ?>" class="pm-pagination__btn"><?= $totalPages ?></a>
                  <?php endif; ?>

                  <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= paginationUrl($currentPage + 1, $search, $fRole, $fStatus) ?>" class="pm-pagination__btn"><i class="fas fa-chevron-right"></i></a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

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