<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Candidates
 * ================================================
 * Role: Programme Manager
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'cohorts';
$managerId = (int)($user['user_id'] ?? 0);
$cohortId  = (int)($_GET['cohort_id'] ?? 0);
if ($cohortId <= 0) {
    header('Location: ' . url('programme/cohorts.php'));
    exit;
}
/*
|--------------------------------------------------------------------------
| Verify ownership
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        c.id,
        c.name,
        c.status,
        c.start_date,
        c.end_date,
        p.id AS programme_id,
        p.name AS programme_name
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE c.id = ?
      AND p.programme_manager_id = ?
");
$stmt->bind_param("ii", $cohortId, $managerId);
$stmt->execute();
$cohort = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cohort) {
    header('Location: ' . url('programme/cohorts.php'));
    exit;
}
/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(*) total,
        SUM(cp.status='active') active_count,
        SUM(cp.status='completed') completed_count,
        SUM(cp.status='withdrawn') withdrawn_count
    FROM cohort_participants cp
    WHERE cp.cohort_id = ?
");
$stmt->bind_param("i", $cohortId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
$totalCandidates = (int)($stats['total'] ?? 0);
$activeCandidates = (int)($stats['active_count'] ?? 0);
$completedCandidates = (int)($stats['completed_count'] ?? 0);
$withdrawnCandidates = (int)($stats['withdrawn_count'] ?? 0);
/*
|--------------------------------------------------------------------------
| Candidate List
|--------------------------------------------------------------------------
*/
$sql = "
SELECT
    cp.id AS participant_id,
    cp.user_id,
    cp.status AS participation_status,
    cp.selected_at,
    cp.onboarded_at,
    cp.completed_at,
    u.first_name,
    u.last_name,
    u.email,
    u.phone
FROM cohort_participants cp
INNER JOIN users u
    ON u.id = cp.user_id
WHERE cp.cohort_id = ?
";
$types = "i";
$params = [$cohortId];
if ($search !== '') {
    $sql .= "
        AND (
            CONCAT(u.first_name,' ',u.last_name) LIKE ?
            OR u.email LIKE ?
        )
    ";
    $searchLike = "%{$search}%";
    $types .= "ss";
    $params[] = $searchLike;
    $params[] = $searchLike;
}
if ($status !== '') {
    $sql .= " AND cp.status = ? ";
    $types .= "s";
    $params[] = $status;
}
$sql .= " ORDER BY u.first_name,u.last_name ";
$stmt = $conn->prepare($sql);
$bind = [$types];
foreach ($params as $k => $v) {
    $bind[] = &$params[$k];
}
call_user_func_array([$stmt,'bind_param'],$bind);
$stmt->execute();
$result = $stmt->get_result();
$candidates = [];
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Cohort Candidates | Investhood IT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body class="dashboard-page">
<div class="dashboard">
<?php require __DIR__.'/sidebar.php'; ?>
<main class="dashboard__main">
<header class="dash-header">
<div class="dash-header__left">
<h1 class="dash-header__title">Cohort Candidates</h1>
</div>
<div class="dash-header__right">
<img
src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname']) ?>&background=1a56db&color=fff&size=80"
class="dash-header__avatar">
</div>
</header>
<div class="dash-content">
<?= $flashes ?>
<div class="welcome-card">
<div class="welcome-card__content">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
<div>
<h1 class="welcome-card__greeting">
<?= e($cohort['name']) ?>
</h1>
<p><?= e($cohort['programme_name']) ?></p>
</div>
<div style="display:flex;gap:.75rem;flex-wrap:wrap;">
<a
href="<?= url('programme/cohort_view.php?id='.(int)$cohort['id']) ?>"
class="sidebar__link">
<i class="fas fa-arrow-left"></i>
Back
</a>
<a
href="<?= url('programme/assign_candidates.php?cohort_id='.(int)$cohort['id']) ?>"
class="sidebar__link">
<i class="fas fa-user-plus"></i>
Assign Candidates
</a>
</div>
</div>
</div>
</div>
<div class="overview-grid" style="margin-top:2rem;">
<div class="overview-card">
<div class="overview-card__icon overview-card__icon--primary">
<i class="fas fa-users"></i>
</div>
<div class="overview-card__info">
<span class="overview-card__number">
<?= number_format($totalCandidates) ?>
</span>
<span class="overview-card__label">
Total
</span>
</div>
</div>
<div class="overview-card">
<div class="overview-card__icon overview-card__icon--cyan">
<i class="fas fa-user-check"></i>
</div>
<div class="overview-card__info">
<span class="overview-card__number">
<?= number_format($activeCandidates) ?>
</span>
<span class="overview-card__label">
Active
</span>
</div>
</div>
<div class="overview-card">
<div class="overview-card__icon overview-card__icon--primary">
<i class="fas fa-graduation-cap"></i>
</div>
<div class="overview-card__info">
<span class="overview-card__number">
<?= number_format($completedCandidates) ?>
</span>
<span class="overview-card__label">
Completed
</span>
</div>
</div>
<div class="overview-card">
<div class="overview-card__icon overview-card__icon--amber">
<i class="fas fa-user-minus"></i>
</div>
<div class="overview-card__info">
<span class="overview-card__number">
<?= number_format($withdrawnCandidates) ?>
</span>
<span class="overview-card__label">
Withdrawn
</span>
</div>
</div>
</div>
<div class="welcome-card" style="margin-top:2rem;">
<div class="welcome-card__content">
<form method="GET">
<input type="hidden" name="cohort_id" value="<?= (int)$cohortId ?>">
<div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">
<div style="flex:1;min-width:250px;">
<label>Search</label>
<input
type="text"
name="search"
value="<?= e($search) ?>"
placeholder="Name or email"
style="width:100%;padding:.75rem;border:1px solid #d1d5db;border-radius:8px;">
</div>
<div style="min-width:180px;">
<label>Status</label>
<select
name="status"
style="width:100%;padding:.75rem;border:1px solid #d1d5db;border-radius:8px;">
<option value="">All</option>
<option value="selected" <?= $status==='selected'?'selected':'' ?>>Selected</option>
<option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
<option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
<option value="withdrawn" <?= $status==='withdrawn'?'selected':'' ?>>Withdrawn</option>
</select>
</div>
<button
type="submit"
class="sidebar__link"
style="border:0;cursor:pointer;">
<i class="fas fa-search"></i>
Search
</button>
</div>
</form>
</div>
</div>
<div style="margin-top:2rem;">
<div class="welcome-card">
<div class="welcome-card__content">
<h2>Assigned Candidates</h2>
<p>Candidates currently participating in this cohort.</p>
</div>
</div>
</div>
<?php if(empty($candidates)): ?>
<div class="welcome-card" style="margin-top:1rem;">
<div class="welcome-card__content" style="text-align:center;">
<i class="fas fa-users-slash" style="font-size:2rem;color:#94a3b8;"></i>
<h3>No Candidates Assigned</h3>
<p>This cohort doesn't have any assigned candidates yet.</p>
<a
href="<?= url('programme/assign_candidates.php?cohort_id='.(int)$cohort['id']) ?>"
class="sidebar__link"
style="display:inline-flex;align-items:center;gap:.5rem;margin-top:1rem;">
<i class="fas fa-user-plus"></i>
Assign Candidates
</a>
</div>
</div>
<?php else: ?>
<div class="overview-grid" style="margin-top:1rem;">
<?php foreach($candidates as $candidate): ?>
<div class="overview-card">
<div class="overview-card__info">
<div style="display:flex;gap:1rem;align-items:center;">
<img
src="https://ui-avatars.com/api/?name=<?= urlencode($candidate['first_name'].' '.$candidate['last_name']) ?>&background=1a56db&color=fff"
style="width:50px;height:34px;border-radius:50%;">
<div>
<div style="font-weight:700;">
<?= e($candidate['first_name'].' '.$candidate['last_name']) ?>
</div>
<div style="font-size:.9rem;color:#64748b;">
<?= e($candidate['email']) ?>
</div>
</div>
</div>
<div style="margin-top:1rem;font-size:.9rem;">
<div>
<strong>Status:</strong>
<?= e(ucfirst($candidate['participation_status'])) ?>
</div>
<div style="margin-top:.35rem;">
<strong>Onboarded:</strong>
<?= !empty($candidate['onboarded_at']) ? e(date('d M Y',strtotime($candidate['onboarded_at']))) : '—' ?>
</div>
<div style="margin-top:.35rem;">
<strong>Completed:</strong>
<?= !empty($candidate['completed_at']) ? e(date('d M Y',strtotime($candidate['completed_at']))) : '—' ?>
</div>
</div>
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;">
<a
href="<?= url('programme/candidate_view.php?id='.(int)$candidate['user_id']) ?>"
class="sidebar__link">
<i class="fas fa-eye"></i>
View
</a>
<a
href="<?= url('programme/update_candidate_status.php?participant_id='.(int)$candidate['participant_id']) ?>"
class="sidebar__link">
<i class="fas fa-edit"></i>
Update
</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</main>
</div>
</body>
</html>