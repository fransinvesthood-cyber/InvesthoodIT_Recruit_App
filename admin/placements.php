<?php
/**
 * Investhood IT - Admin Placements Management (Stage 12)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user = current_user();
$roleSlug = current_role() ?? 'admin';

// Allowed placement statuses (must match placements.status ENUM in database/placements.sql).
$PLACEMENT_STATUSES = ['pending_placement', 'placement_in_progress', 'placed', 'active', 'completed', 'withdrawn', 'cancelled'];

// ---------------------------------------------------------------------------
// QUICK STATUS UPDATE: POST placement_action=update_status
// Lets an admin change a placement's status from the list or detail view.
// Writes placement_status_history + a candidate notification (best-effort).
// Uses PRG (redirect after POST) so refreshes don't re-submit.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['placement_action'] ?? '') === 'update_status') {
    require_csrf();
    $statusPid = (int) ($_POST['placement_id'] ?? 0);
    $newStatus = trim((string) ($_POST['new_status'] ?? ''));
    $changeReason = trim((string) ($_POST['change_reason'] ?? ''));
    // Only allow returning to this page (avoid open redirects).
    $returnTo = trim((string) ($_POST['return_to'] ?? ''));
    if ($returnTo === '' || stripos($returnTo, 'admin/placements.php') !== 0) {
        $returnTo = $statusPid > 0 ? 'admin/placements.php?manage=' . $statusPid : 'admin/placements.php';
    }
    if ($statusPid <= 0) {
        set_flash('error', 'Status not updated', 'Missing placement. Please try again.');
        safe_redirect($returnTo);
    }
    if (!in_array($newStatus, $PLACEMENT_STATUSES, true)) {
        set_flash('error', 'Status not updated', 'Invalid status selected.');
        safe_redirect($returnTo);
    }
    try {
        $existing = Database::fetchOne("SELECT id, candidate_id, status FROM placements WHERE id = ?", 'i', [$statusPid]);
        if (!$existing) {
            set_flash('error', 'Status not updated', 'Placement #' . $statusPid . ' could not be found.');
            safe_redirect($returnTo);
        }
        $oldStatus = (string) ($existing['status'] ?? '');
        if ($oldStatus === $newStatus) {
            set_flash('info', 'No change needed', 'Placement is already "' . ucwords(str_replace('_', ' ', $newStatus)) . '".');
            safe_redirect($returnTo);
        }
        Database::execute("UPDATE placements SET status = ?, updated_at = NOW() WHERE id = ?", 'si', [$newStatus, $statusPid]);
        $actorId = function_exists('current_user_id') ? (int) (current_user_id() ?? 0) : 0;
        $bySql = $actorId > 0 ? '?' : 'NULL';
        $byParams = $actorId > 0 ? [$actorId] : [];
        $byTypes = $actorId > 0 ? 'i' : '';
        $reasonParam = $changeReason !== '' ? $changeReason : ('Status changed from ' . $oldStatus . ' to ' . $newStatus);
        // History trail.
        try {
            Database::execute(
                "INSERT INTO placement_status_history (placement_id, field_name, previous_value, new_value, changed_by, change_reason) VALUES (?, 'status', ?, ?, {$bySql}, ?)",
                'isss' . $byTypes,
                array_merge([$statusPid, $oldStatus, $newStatus], $byParams, [$reasonParam])
            );
        } catch (Throwable $histEx) { error_log('[PLACEMENTS] status history: ' . $histEx->getMessage()); }
        // Candidate notification (best-effort).
        try {
            $niceOld = ucwords(str_replace('_', ' ', $oldStatus));
            $niceNew = ucwords(str_replace('_', ' ', $newStatus));
            Database::execute(
                "INSERT INTO placement_notifications (placement_id, candidate_id, sender_id, notification_type, title, message) VALUES (?, ?, {$bySql}, 'status_changed', 'Placement Status Updated', ?)",
                'ii' . $byTypes . 's',
                array_merge([$statusPid, (int) ($existing['candidate_id'] ?? 0)], $byParams, ["Your placement status changed from {$niceOld} to {$niceNew}."])
            );
        } catch (Throwable $notifEx) { error_log('[PLACEMENTS] status notify: ' . $notifEx->getMessage()); }
        // Audit trail (best-effort).
        try {
            Database::execute(
                "INSERT INTO audit_logs (user_id, action, record_type, record_id, reason, ip_address) VALUES ({$bySql}, 'Placement Status Updated', 'placement', ?, ?, ?)",
                $byTypes . 'iss',
                array_merge($byParams, [$statusPid, $reasonParam, $_SERVER['REMOTE_ADDR'] ?? '::1'])
            );
        } catch (Throwable $auditEx) { error_log('[PLACEMENTS] status audit: ' . $auditEx->getMessage()); }
        set_flash('success', 'Placement status updated', 'Placement #' . $statusPid . ' is now "' . ucwords(str_replace('_', ' ', $newStatus)) . '".');
    } catch (Throwable $ex) {
        error_log('[PLACEMENTS] status update: ' . $ex->getMessage());
        set_flash('error', 'Status not updated', 'Failed to update placement status. Please try again.');
    }
    safe_redirect($returnTo);
}

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
                  <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                    <span class="pl-status pl-status--<?= e($dStatus) ?>"><?= e(ucwords(str_replace('_', ' ', $dStatus))) ?></span>
                    <form method="post" action="<?= url('admin/placements.php') ?>?manage=<?= (int) $placementDetail['id'] ?>" style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;" onsubmit="return confirm('Change status to \"' + this.new_status.options[this.new_status.selectedIndex].text + '\"?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="placement_action" value="update_status">
                      <input type="hidden" name="placement_id" value="<?= (int) $placementDetail['id'] ?>">
                      <input type="hidden" name="return_to" value="admin/placements.php?manage=<?= (int) $placementDetail['id'] ?>">
                      <select name="new_status" class="pl-filters__select" style="padding:.35rem 1.8rem .35rem .6rem;font-size:.78rem;" aria-label="Change placement status">
                        <?php foreach (($PLACEMENT_STATUSES ?? []) as $optStatus): ?>
                          <option value="<?= e($optStatus) ?>" <?= $optStatus === $dStatus ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $optStatus))) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="pl-btn pl-btn--sm pl-btn--primary"><i class="fas fa-sync-alt"></i> Update Status</button>
                    </form>
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
              <p style="margin:.35rem 0 0;color:var(--text-muted);font-size:.85rem;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                <span><?= (int) $stats['total'] ?> placement<?= (int) $stats['total'] !== 1 ? 's' : ''; ?> total</span>
                <?php if ((int) $stats['total'] > 0): ?>
                  <span class="pl-chip" style="font-size:.7rem;"><i class="fas fa-clock"></i><?= (int) $stats['pending'] ?> pending</span>
                  <span class="pl-chip pl-chip--green" style="font-size:.7rem;"><i class="fas fa-play"></i><?= (int) $stats['active'] ?> active</span>
                <?php endif; ?>
              </p>
            </div>
            <a href="<?= url('admin/create-placement.php') ?>" class="pl-btn pl-btn--primary" style="padding:.65rem 1.3rem;border-radius:10px;box-shadow:0 6px 16px rgba(26,86,219,.3);"><i class="fas fa-plus"></i> Create Placement</a>
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
                    <th><i class="fas fa-user" style="margin-right:.35rem;"></i>Candidate</th>
                    <th><i class="fas fa-graduation-cap" style="margin-right:.35rem;"></i>Programme / Cohort</th>
                    <th><i class="fas fa-building" style="margin-right:.35rem;"></i>Department</th>
                    <th><i class="fas fa-user-tie" style="margin-right:.35rem;"></i>Supervisor</th>
                    <th><i class="fas fa-calendar-days" style="margin-right:.35rem;"></i>Duration</th>
                    <th><i class="fas fa-flag" style="margin-right:.35rem;"></i>Status</th>
                    <th><i class="fas fa-bolt" style="margin-right:.35rem;"></i>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentPlacements as $pl): ?>
                    <?php
                      $plName   = trim(($pl['first_name'] ?? '') . ' ' . ($pl['last_name'] ?? ''));
                      $plSup    = trim(($pl['sup_first'] ?? '') . ' ' . ($pl['sup_last'] ?? ''));
                      $plStatus = $pl['status'] ?? 'pending_placement';
                      $plInitials = strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_filter(preg_split('/\s+/', $plName !== '' ? $plName : '??'), fn($p) => $p !== ''))));
                      $plInitials = $plInitials !== '' ? mb_substr($plInitials, 0, 2) : '—';
                      $plAvatar = trim((string) ($pl['profile_picture'] ?? ''));
                      $plHasDates = !empty($pl['start_date']) || !empty($pl['end_date']);
                      $plDept = trim((string) ($pl['department'] ?? ''));
                      $plLoc  = trim((string) ($pl['location'] ?? ''));
                    ?>
                    <tr>
                      <td data-label="Candidate">
                        <div class="pl-candidate">
                          <div class="pl-candidate__avatar" aria-hidden="true">
                            <?php if ($plAvatar !== ''): ?>
                              <img src="<?= url('uploads/' . $plAvatar) ?>" alt="">
                            <?php else: ?>
                              <?= e($plInitials) ?>
                            <?php endif; ?>
                          </div>
                          <div class="pl-candidate__info">
                            <div class="pl-candidate__name"><?= e($plName !== '' ? $plName : '—') ?></div>
                            <div class="pl-candidate__email"><i class="fas fa-envelope"></i> <?= e($pl['email'] ?? '—') ?></div>
                            <?php if (!empty($pl['placement_reference'])): ?>
                              <div class="pl-candidate__email"><span class="pl-ref"><?= e($pl['placement_reference']) ?></span></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td data-label="Programme / Cohort">
                        <div class="pl-opportunity">
                          <div class="pl-opportunity__title pl-cell__title"><i class="fas fa-graduation-cap" style="color:var(--primary);margin-right:.3rem;"></i><?= e($pl['programme_name'] ?? '—') ?></div>
                          <?php if (!empty($pl['cohort_name'])): ?>
                            <div class="pl-cell__sub"><span class="pl-chip"><i class="fas fa-users"></i><?= e($pl['cohort_name']) ?></span></div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td data-label="Department">
                        <div class="pl-cell__title"><?= e($plDept !== '' ? $plDept : '—') ?></div>
                        <?php if ($plLoc !== ''): ?>
                          <div class="pl-cell__sub"><i class="fas fa-location-dot"></i><?= e($plLoc) ?></div>
                        <?php endif; ?>
                      </td>
                      <td data-label="Supervisor">
                        <?php if ($plSup !== ''): ?>
                          <div class="pl-cell__title"><?= e($plSup) ?></div>
                          <div class="pl-cell__sub"><span class="pl-chip pl-chip--green"><i class="fas fa-user-check"></i>Assigned</span></div>
                        <?php else: ?>
                          <div class="pl-cell__sub"><span class="pl-chip pl-chip--muted"><i class="fas fa-user-plus"></i>Unassigned</span></div>
                        <?php endif; ?>
                      </td>
                      <td data-label="Duration">
                        <?php if ($plHasDates): ?>
                          <div class="pl-dates">
                            <i class="fas fa-calendar-days"></i>
                            <span><?= e(!empty($pl['start_date']) ? format_date($pl['start_date'], 'd M Y') : '—') ?></span>
                            <span class="pl-dates__sep">&rarr;</span>
                            <span><?= e(!empty($pl['end_date']) ? format_date($pl['end_date'], 'd M Y') : '—') ?></span>
                          </div>
                        <?php else: ?>
                          <span class="pl-chip pl-chip--muted"><i class="fas fa-calendar-xmark"></i>No dates</span>
                        <?php endif; ?>
                      </td>
                      <td data-label="Status">
                        <span class="pl-status pl-status--<?= e($plStatus) ?>"><?= e(ucwords(str_replace('_', ' ', $plStatus))) ?></span>
                        <form method="post" action="<?= url('admin/placements.php') ?>" class="pl-status-form" onsubmit="return confirm('Change status to &quot;' + this.new_status.options[this.new_status.selectedIndex].text + '&quot;?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="placement_action" value="update_status">
                          <input type="hidden" name="placement_id" value="<?= (int) $pl['id'] ?>">
                          <input type="hidden" name="return_to" value="admin/placements.php">
                          <select name="new_status" class="pl-filters__select" aria-label="Change status for placement #<?= (int) $pl['id'] ?>">
                            <?php foreach (($PLACEMENT_STATUSES ?? []) as $optStatus): ?>
                              <option value="<?= e($optStatus) ?>" <?= $optStatus === $plStatus ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $optStatus))) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="pl-btn pl-btn--sm" title="Update status"><i class="fas fa-check"></i></button>
                        </form>
                      </td>
                      <td data-label="Actions">
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
