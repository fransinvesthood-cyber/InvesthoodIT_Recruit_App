<?php
/**
 * Investhood IT - Admin Placements Management (Stage 12)
 */

require_once __DIR__ . '/../php/config/database.php';

session_start();
$user = $_SESSION['user'] ?? null;

if (!$user) { header('Location: /login.php'); exit; }

$roleSlug = $user['role_slug'] ?? '';
$allowedRoles = ['admin', 'programme_manager', 'programme_officer'];
if (!in_array($roleSlug, $allowedRoles)) { header('Location: /unauthorized.php'); exit; }

// Fetch stats
$stats = dbFetchOne("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending_placement' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status IN ('placement_in_progress', 'active') THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status IN ('withdrawn', 'cancelled') THEN 1 ELSE 0 END) as withdrawn
    FROM placements") ?: ['total' => 0, 'pending' => 0, 'active' => 0, 'completed' => 0, 'withdrawn' => 0];

// Fetch recent placements
$recentPlacements = dbQuery("
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placements Management - Investhood IT Admin</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/admin_placements.css">
    <link rel="stylesheet" href="/css/admin_applications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
    <!-- Sidebar -->
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar__header">
            <div class="sidebar__brand">
                <div class="sidebar__logo"><i class="fas fa-graduation-cap"></i></div>
            <!-- Hero Section -->
            <div class="pl-hero">
                <div class="pl-hero__inner">
                    <span class="section__badge"><i class="fas fa-building"></i> Stage 12</span>
                    <h1 class="pl-hero__title">Placements Management</h1>
                    <p class="pl-hero__subtitle">Manage candidate placements after offer acceptance. Assign programmes, cohorts, supervisors, and track placement status throughout the lifecycle.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="pl-stats">
                <div class="pl-stat pl-stat--total">
                    <div class="pl-stat__icon"><i class="fas fa-clipboard-list"></i></div>
                    <div>
                        <span class="pl-stat__value"><?php echo $stats['total']; ?></span>
                        <span class="pl-stat__label">Total Placements</span>
                    </div>
                </div>
                <div class="pl-stat pl-stat--pending">
                    <div class="pl-stat__icon"><i class="fas fa-clock"></i></div>
                    <div>
                        <span class="pl-stat__value"><?php echo $stats['pending']; ?></span>
                        <span class="pl-stat__label">Pending Placement</span>
                    </div>
                </div>
                <div class="pl-stat pl-stat--active">
                    <div class="pl-stat__icon"><i class="fas fa-play"></i></div>
                    <div>
                        <span class="pl-stat__value"><?php echo $stats['active']; ?></span>
                        <span class="pl-stat__label">Active Placements</span>
                    </div>
                </div>
                <div class="pl-stat pl-stat--completed">
                    <div class="pl-stat__icon"><i class="fas fa-check"></i></div>
                    <div>
                        <span class="pl-stat__value"><?php echo $stats['completed']; ?></span>
                        <span class="pl-stat__label">Completed</span>
                    </div>
                </div>
                <div class="pl-stat pl-stat--cancelled">
                    <div class="pl-stat__icon"><i class="fas fa-times"></i></div>
                    <div>
                        <span class="pl-stat__value"><?php echo $stats['withdrawn']; ?></span>
                        <span class="pl-stat__label">Cancelled/Withdrawn</span>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">Placement Records</h2>
                    <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;"><?php echo $stats['total']; ?> placement<?php echo $stats['total'] !== 1 ? 's' : ''; ?> total</p>
                </div>
                <a href="create-placement.php" class="pl-btn pl-btn--primary" style="padding: 0.6rem 1.2rem;"><i class="fas fa-plus"></i> Create Placement</a>
            </div>

                <div>
                    <strong>Investhood IT</strong>
                    <span>Admin Portal</span>
                </div>
            </div>
            <button class="sidebar__close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>
        <nav class="sidebar__nav">
            <div class="sidebar__section-label">Main</div>
            <div class="sidebar__menu">
                <a href="/admin/dashboard.php" class="sidebar__link"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
                <a href="/admin/candidates.php" class="sidebar__link"><i class="fas fa-user-friends"></i> <span>Candidates</span></a>
                <a href="/admin/applications.php" class="sidebar__link"><i class="fas fa-file-alt"></i> <span>Applications</span></a>
                <a href="/admin/interviews.php" class="sidebar__link"><i class="fas fa-comments"></i> <span>Interviews</span></a>
                <a href="/admin/selection.php" class="sidebar__link"><i class="fas fa-check-circle"></i> <span>Selection & Offers</span></a>
                <a href="/admin/placements.php" class="sidebar__link active"><i class="fas fa-building"></i> <span>Placements</span></a>
            </div>
            <div class="sidebar__section-label">Programmes</div>
            <div class="sidebar__menu">
                <a href="/admin/programmes.php" class="sidebar__link"><i class="fas fa-folder-open"></i> <span>Programmes</span></a>
                <a href="/admin/cohorts.php" class="sidebar__link"><i class="fas fa-layer-group"></i> <span>Cohorts</span></a>
                <a href="/admin/opportunities.php" class="sidebar__link"><i class="fas fa-briefcase"></i> <span>Opportunities</span></a>
            </div>
            <div class="sidebar__section-label">System</div>
            <div class="sidebar__menu">
                <a href="/admin/users.php" class="sidebar__link"><i class="fas fa-users"></i> <span>Users</span></a>
                <a href="/admin/audit-logs.php" class="sidebar__link"><i class="fas fa-history"></i> <span>Audit Logs</span></a>
            </div>
        </nav>
        <div class="sidebar__footer">
            <div class="sidebar__user">
                <div class="sidebar__user-avatar">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="/uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Avatar">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="sidebar__user-info">
                    <span class="sidebar__user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                    <span class="sidebar__user-role"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $roleSlug))); ?></span>
                </div>
            </div>
            <a href="/logout.php" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content pl-module">
        <div class="dash-content" id="adminDashContent">

<body>
