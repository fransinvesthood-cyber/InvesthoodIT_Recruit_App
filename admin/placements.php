<?php
/**
 * Investhood IT - Admin Placements Management (Stage 12)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user = current_user();
$roleSlug = current_role() ?? 'admin';

// Fetch stats
$stats = ['total' => 0, 'pending' => 0, 'active' => 0, 'completed' => 0, 'withdrawn' => 0];
try {
$statsRow = Database::fetchOne("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending_placement' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status IN ('placement_in_progress', 'active') THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status IN ('withdrawn', 'cancelled') THEN 1 ELSE 0 END) as withdrawn
    FROM placements");
if ($statsRow) {
    // COUNT()/SUM() come back from MySQL as strings (or NULL) — normalise to int
    foreach (array_keys($stats) as $k) {
        $stats[$k] = (int) ($statsRow[$k] ?? 0);
    }
}
} catch (Throwable $ex) { error_log('[PLACEMENTS] stats: ' . $ex->getMessage()); }

// Fetch recent placements
$recentPlacements = [];
try {
$recentPlacements = Database::fetchAll("
    SELECT p.*, u.first_name, u.last_name, u.email, u.profile_picture,
           pr.name as programme_name, c.name as cohort_name,
           IFNULL(sup.first_name, '') as sup_first, IFNULL(sup.last_name, '') as sup_last
    FROM placements p
    JOIN users u ON p.candidate_id = u.id
    LEFT JOIN programmes pr ON p.programme_id = pr.id
    LEFT JOIN cohorts c ON p.cohort_id = c.id
    LEFT JOIN users sup ON p.supervisor_id = sup.id
    ORDER BY p.created_at DESC
    LIMIT 10
");
} catch (Throwable $ex) { error_log('[PLACEMENTS] list: ' . $ex->getMessage()); }

// Single placement detail (used by the ?view= / ?manage= row actions)
$placementDetail = null;
$placementId = (int) ($_GET['view'] ?? $_GET['manage'] ?? 0);
if ($placementId > 0) {
    try {
        $placementDetail = Database::fetchOne("
            SELECT p.*, u.first_name, u.last_name, u.email, u.phone,
                   pr.name as programme_name, c.name as cohort_name,
                   IFNULL(sup.first_name, '') as sup_first, IFNULL(sup.last_name, '') as sup_last
            FROM placements p
            JOIN users u ON p.candidate_id = u.id
            LEFT JOIN programmes pr ON p.programme_id = pr.id
            LEFT JOIN cohorts c ON p.cohort_id = c.id
            LEFT JOIN users sup ON p.supervisor_id = sup.id
            WHERE p.id = ?
        ", 'i', [$placementId]);
    } catch (Throwable $ex) { error_log('[PLACEMENTS] detail: ' . $ex->getMessage()); }
}
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placements Management - Investhood IT Admin</title>
    <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
    <link rel="stylesheet" href="<?= url('css/admin_placements.css') ?>">
    <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard pl-module">

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
        <div class="sidebar__section-label">Main</div>
        <div class="sidebar__menu">
          <a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
          <a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> <span>Applications</span></a>
          <a href="<?= url('admin/interviews.php') ?>" class="sidebar__link"><i class="fas fa-comments"></i> <span>Interviews</span></a>
          <a href="<?= url('admin/selection.php') ?>" class="sidebar__link"><i class="fas fa-check-circle"></i> <span>Selection &amp; Offers</span></a>
          <a href="<?= url('admin/placements.php') ?>" class="sidebar__link active"><i class="fas fa-building"></i> <span>Placements</span></a>
        </div>
        <div class="sidebar__section-label">Programmes</div>
        <div class="sidebar__menu">
          <a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-folder-open"></i> <span>Programmes</span></a>
          <a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> <span>Opportunities</span></a>
        </div>
        <div class="sidebar__section-label">System</div>
        <div class="sidebar__menu">
          <a href="<?= url('admin/users.php') ?>" class="sidebar__link"><i class="fas fa-users"></i> <span>Users</span></a>
        </div>
        </nav>
        <div class="sidebar__footer">
            <div class="sidebar__user">
                <div class="sidebar__user-avatar">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?= url('uploads/' . $user['profile_picture']) ?>" alt="Avatar">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="sidebar__user-info">
                    <span class="sidebar__user-name"><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) !== '' ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : ($user['fullname'] ?? 'Admin')) ?></span>
                    <span class="sidebar__user-role"><?= e(ucwords(str_replace('_', ' ', $roleSlug))) ?></span>
                </div>
            </div>
            <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- .dashboard__main supplies margin-left:260px (clears the fixed sidebar),
         max-width:calc(100vw - 260px) and overflow-x:auto — see styles.css ~L3085.
         .main-content is defined only in programme_manager_enhancements.css, which
         this page does not load; using it left the content unoffset and clipped.
         Both classes are set here so the layout holds even before admin_dashboard.js
         adds .dashboard__main at runtime. -->
    <main class="main dashboard__main">
        <div class="dash-content" id="adminDashContent">
        <?= $flashes ?>

          <?php if ($placementId > 0): ?>
            <!-- ===== PLACEMENT DETAIL (view / manage) ===== -->
            <?php if ($placementDetail):
              $dStatus = $placementDetail['status'] ?? 'pending_placement';
              $dName   = trim(($placementDetail['first_name'] ?? '') . ' ' . ($placementDetail['last_name'] ?? ''));
              $dSup    = trim(($placementDetail['sup_first'] ?? '') . ' ' . ($placementDetail['sup_last'] ?? ''));
            ?>
              <div class="pl-table-container" style="margin-bottom:1.5rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1rem 1.15rem;border-bottom:1px solid var(--border);flex-wrap:wrap;">
                  <div>
                    <strong style="font-size:1.05rem;"><?= e($placementDetail['placement_reference'] ?? ('Placement #' . (int) $placementDetail['id'])) ?></strong>
                    <div style="font-size:.8rem;color:var(--text-muted);margin-top:.15rem;">
                      <?= e($dName !== '' ? $dName : '—') ?>
                    </div>
                  </div>
                  <div style="display:flex;align-items:center;gap:.6rem;">
                    <span class="pl-status pl-status--<?= e($dStatus) ?>"><?= e(ucwords(str_replace('_', ' ', $dStatus))) ?></span>
                    <a href="<?= url('admin/placements.php') ?>" class="pl-btn pl-btn--sm"><i class="fas fa-arrow-left"></i> Back to List</a>
                  </div>
                </div>
                <div style="padding:1.15rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                  <div>
                    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);">Programme</div>
                    <div style="font-weight:600;"><?= e($placementDetail['programme_name'] ?? '—') ?></div>
                  </div>
                  <div>
                    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);">Cohort</div>
                    <div style="font-weight:600;"><?= e($placementDetail['cohort_name'] ?? '—') ?></div>
                  </div>
                  <div>
                    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);">Department</div>
                    <div style="font-weight:600;"><?= e($placementDetail['department'] ?? '—') ?></div>
                  </div>
                  <div>
                    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);">Location</div>
                    <div style="font-weight:600;"><?= e($placementDetail['location'] ?? '—') ?></div>
                  </div>
                  <div>
                    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);">Supervisor</div>
                    <div style="font-weight:600;"><?= e($dSup !== '' ? $dSup : 'Unassigned') ?></div>
                  </div>
                  <div>
                    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);">Duration</div>
                    <div style="font-weight:600;">
                      <?= e(!empty($placementDetail['start_date']) ? format_date($placementDetail['start_date'], 'd M Y') : '—') ?>
                      &ndash;
                      <?= e(!empty($placementDetail['end_date']) ? format_date($placementDetail['end_date'], 'd M Y') : '—') ?>
                    </div>
                  </div>
                  <div>
                    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);">Contact</div>
                    <div style="font-weight:600;"><?= e($placementDetail['email'] ?? '—') ?></div>
                  </div>
                  <div>
                    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);">Created</div>
                    <div style="font-weight:600;"><?= e(!empty($placementDetail['created_at']) ? format_date($placementDetail['created_at'], 'd M Y') : '—') ?></div>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="pl-empty-state">
                <div class="pl-empty-state__icon"><i class="fas fa-magnifying-glass"></i></div>
                <h3>Placement not found</h3>
                <p>Placement #<?= (int) $placementId ?> could not be loaded. It may have been removed.</p>
                <div style="margin-top:1.5rem;">
                  <a href="<?= url('admin/placements.php') ?>" class="pl-btn pl-btn--primary"><i class="fas fa-arrow-left"></i> Back to List</a>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <!-- ===== PAGE HERO ===== -->
          <div class="pl-hero">
            <div class="pl-hero__inner">
              <h1 class="pl-hero__title">Placements Management</h1>
              <p class="pl-hero__subtitle">Manage candidate placements after offer acceptance. Assign programmes, cohorts, supervisors, and track placement status throughout the lifecycle.</p>
            </div>
          </div>

          <!-- ===== STATS CARDS ===== -->
          <div class="pl-stats">
            <div class="pl-stat pl-stat--total">
              <div class="pl-stat__icon"><i class="fas fa-clipboard-list"></i></div>
              <div>
                <span class="pl-stat__value"><?= (int) $stats['total'] ?></span>
                <span class="pl-stat__label">Total Placements</span>
              </div>
            </div>
            <div class="pl-stat pl-stat--pending">
              <div class="pl-stat__icon"><i class="fas fa-clock"></i></div>
              <div>
                <span class="pl-stat__value"><?= (int) $stats['pending'] ?></span>
                <span class="pl-stat__label">Pending Placement</span>
              </div>
            </div>
            <div class="pl-stat pl-stat--active">
              <div class="pl-stat__icon"><i class="fas fa-play"></i></div>
              <div>
                <span class="pl-stat__value"><?= (int) $stats['active'] ?></span>
                <span class="pl-stat__label">Active Placements</span>
              </div>
            </div>
            <div class="pl-stat pl-stat--completed">
              <div class="pl-stat__icon"><i class="fas fa-check"></i></div>
              <div>
                <span class="pl-stat__value"><?= (int) $stats['completed'] ?></span>
                <span class="pl-stat__label">Completed</span>
              </div>
            </div>
            <div class="pl-stat pl-stat--cancelled">
              <div class="pl-stat__icon"><i class="fas fa-times"></i></div>
              <div>
                <span class="pl-stat__value"><?= (int) $stats['withdrawn'] ?></span>
                <span class="pl-stat__label">Cancelled/Withdrawn</span>
              </div>
            </div>
          </div>

          <!-- ===== ACTIONS BAR ===== -->
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
            <div>
              <h2 style="font-size:1.25rem;font-weight:700;margin:0;color:var(--text);">Placement Records</h2>
              <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.85rem;">
                <?= (int) $stats['total'] ?> placement<?= (int) $stats['total'] !== 1 ? 's' : ''; ?> total
              </p>
            </div>
            <a href="<?= url('admin/create-placement.php') ?>" class="pl-btn pl-btn--primary" style="padding:.6rem 1.2rem;"><i class="fas fa-plus"></i> Create Placement</a>
          </div>

          <!-- ===== PLACEMENTS TABLE ===== -->
          <?php if (empty($recentPlacements)): ?>
            <div class="pl-empty-state">
              <div class="pl-empty-state__icon"><i class="fas fa-handshake"></i></div>
              <h3>No placements yet</h3>
              <p>Placements appear here once candidates with accepted offers are placed. Run <code>database/placements.sql</code> if the placements tables are missing.</p>
              <div style="margin-top:1.5rem;">
                <a href="<?= url('admin/create-placement.php') ?>" class="pl-btn pl-btn--primary"><i class="fas fa-plus"></i> New Placement</a>
              </div>
            </div>
          <?php else: ?>
            <div class="pl-table-container">
              <table class="pl-table">
                <thead>
                  <tr>
                    <th>Candidate</th>
                    <th>Programme / Cohort</th>
                    <th>Department</th>
                    <th>Supervisor</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentPlacements as $pl): ?>
                    <?php
                      $plName   = trim(($pl['first_name'] ?? '') . ' ' . ($pl['last_name'] ?? ''));
                      $plSup    = trim(($pl['sup_first'] ?? '') . ' ' . ($pl['sup_last'] ?? ''));
                      $plStatus = $pl['status'] ?? 'pending_placement';
                    ?>
                    <tr>
                      <td>
                        <div class="pl-candidate">
                          <div class="pl-candidate__info">
                            <div class="pl-candidate__name"><?= e($plName !== '' ? $plName : '—') ?></div>
                            <div class="pl-candidate__email"><?= e($pl['email'] ?? '') ?> · <?= e($pl['placement_reference'] ?? '') ?></div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="pl-opportunity">
                          <div class="pl-opportunity__title"><?= e($pl['programme_name'] ?? '—') ?></div>
                          <div class="pl-opportunity__ref"><?= e($pl['cohort_name'] ?? '') ?></div>
                        </div>
                      </td>
                      <td>
                        <?= e($pl['department'] ?? '—') ?>
                        <?php if (!empty($pl['location'])): ?>
                          <div class="pl-candidate__email"><?= e($pl['location']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td><?= e($plSup !== '' ? $plSup : 'Unassigned') ?></td>
                      <td>
                        <?php if (!empty($pl['start_date']) || !empty($pl['end_date'])): ?>
                          <?= e(!empty($pl['start_date']) ? format_date($pl['start_date'], 'd M Y') : '—') ?>
                          &ndash;
                          <?= e(!empty($pl['end_date']) ? format_date($pl['end_date'], 'd M Y') : '—') ?>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                      <td><span class="pl-status pl-status--<?= e($plStatus) ?>"><?= e(ucwords(str_replace('_', ' ', $plStatus))) ?></span></td>
                      <td>
                        <div class="pl-actions">
                          <a href="<?= url('admin/placements.php') ?>?view=<?= (int) $pl['id'] ?>" class="pl-btn pl-btn--sm" title="View placement"><i class="fas fa-eye"></i></a>
                          <a href="<?= url('admin/placements.php') ?>?manage=<?= (int) $pl['id'] ?>" class="pl-btn pl-btn--sm pl-btn--primary" title="Manage"><i class="fas fa-cog"></i></a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div><!-- //dash-content -->

      <footer class="dash-footer admin-footer">
        <div class="container">
          <div class="dash-footer__inner">
            <p>&copy; <?= date('Y') ?> Investhood IT. All rights reserved.</p>
            <div class="dash-footer__links">
              <a href="<?= url('index.php') ?>">Back to Home</a>
            </div>
          </div>
        </div>
      </footer>
    </main>
  </div>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_placements.js') ?>"></script>
</body>
</html>
