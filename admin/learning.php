<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'programme_manager', 'programme_officer']);
$adminRole = in_array(current_role(), ['admin', 'programme_manager', 'programme_officer'], true);
$userId = (int) current_user_id();

$search = trim((string) ($_GET['search'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$status = strtolower(trim((string) $_GET['status'] ?? 'all'));
if (!in_array($status, LearningProgramme::ALL_STATUSES, true)) {
    $status = 'assigned';
}
$sort = in_array((string) ($_GET['sort'] ?? 'status'), ['title', 'due_date', 'status', 'assigned_at'], true)
    ? (string) ($_GET['sort'] ?? 'status')
    : 'status';

$programmes = LearningProgramme::allActive();
$assignments = LearningProgramme::forCandidate($userId, ['status' => $status, 'category' => $category, 'search' => $search]);

$candidates = [];
if ($adminRole) {
    try {
        $candidates = Database::fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.email
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'candidate'
             ORDER BY u.first_name ASC, u.last_name ASC"
        );
    } catch (Throwable $e) {
        error_log('[Admin Learning] candidate list: ' . $e->getMessage());
    }
}

$programmeOptions = [];
foreach ($programmes as $p) {
    $programmeOptions[(int) $p['id']] = $p['title'];
}
rsort($programmeOptions);

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learning &amp; Skills | Admin</title>
<link rel="stylesheet" href="<?= url('css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="admin-page">

<header class="admin-navbar">
<div class="container">
<div class="admin-navbar__left">
<a href="<?= url('admin/dashboard.php') ?>" class="admin-navbar__logo">
<span class="admin-navbar__logo-icon"><i class="fas fa-graduation-cap"></i></span>
<span class="admin-navbar__logo-text">Investhood <span class="admin-navbar__logo-accent">IT</span></span>
<span class="admin-navbar__badge">Admin</span>
</a>
</div>
<div class="admin-navbar__right">
<a href="<?= url('candidate/dashboard.php') ?>" class="admin-navbar__nav-link">Candidate Portal</a>
<span class="admin-navbar__nav-link"><i class="fas fa-user"></i> <?= e($user['fullname'] ?? 'Admin') ?></span>
<a href="<?= url('auth/logout.php') ?>" class="admin-navbar__nav-link">Sign out</a>
</div>
</div>
</header>

<div class="container admin-main">
<header class="admin-page-header">
<div class="admin-page-header__title">Learning &amp; Skills</div>
<div class="admin-page-header__subtitle">Manage learning programmes assigned to candidates and track progress.</div>
</header>

<!-- ===== ASSIGN A COURSE ===== -->
<section class="admin-card">
<div class="admin-card__header">
<h2 class="admin-card__title"><i class="fas fa-book-open"></i> Assign a course</h2>
</div>
<form method="post" action="<?= url('admin/learning_actions.php') ?>" class="admin-form">
<?php echo csrf_field(); ?>
<div class="admin-form__row">
<div class="admin-form__field">
<label>Programme</label>
<select name="programme_id" id="programmeSelect" class="form-select" required>
<option value="">-- Select a programme --</option>
<?php foreach ($programmeOptions as $id => $title): ?>
<option value="<?= $id ?>"><?= e($title) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="admin-form__field">
<label>Candidate</label>
<select name="candidate_id" id="candidateSelect" class="form-select" required>
<option value="">-- Select a candidate --</option>
<?php if ($candidates): ?>
<?php foreach ($candidates as $c): ?>
<option value="<?= (int) $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?> (<?= e($c['email']) ?>)</option>
<?php endforeach; ?>
<?php else: ?>
<option>No candidates found.</option>
<?php endif; ?>
</select>
</div>
<div class="admin-form__field">
<label>Due date (optional)</label>
<input type="date" name="due_date" id="dueDate" class="form-input">
</div>
</div>
<div class="admin-form__actions">
<button type="submit" class="btn btn--primary"><i class="fas fa-user-graduate"></i> Assign Course</button>
</div>
</form>
<p class="admin-card__hint">Assignments are idempotent on the (programme_id, candidate_id) key.</p>
</section>

<!-- ===== CANDIDATE PROGRESS ===== -->
<section class="admin-card">
<div class="admin-card__header">
<h2 class="admin-card__title"><i class="fas fa-chart-bar"></i> Current progress</h2>
</div>
<?php if (empty($assignments)): ?>
<p class="admin-card__hint">No programmes assigned to you yet.</p>
<?php else: ?>
<div class="admin-table-container">
<table class="admin-table">
<thead>
<tr>
<th>Programme</th>
<th>Category</th>
<th>Status</th>
<th>Progress</th>
<th>Due</th>
<th>Lessons</th>
</tr>
</thead>
<tbody>
<?php
$now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
foreach ($assignments as $a): 
    $due = $a['due_date'] ? new DateTime($a['due_date'], new DateTimeZone(date_default_timezone_get())) : null;
    $isOverdue = $due !== null && $due < $now && $a['computed_status'] !== 'completed';
    $dueLabel = $due ? ($isOverdue ? '<span class="text-danger">Overdue</span>' : '<span class="text-muted">' . e(format_date($a['due_date'], 'M d, Y')) . '</span>') : '—';
?>
<tr>
<td><?= e($a['title']) ?></td>
<td><?= e($a['category'] ?? '—') ?></td>
<td><span class="badge badge--<?= LearningProgramme::statusTone($a['computed_status']) ?>"><?= LearningProgramme::statusLabel($a['computed_status']) ?></span></td>
<td>
<div class="progress-bar"><div class="progress-bar__fill" style="width: <?= $a['progress'] ?>%"></div></div>
<span class="text-muted"><?= $a['progress'] ?>%</span>
</td>
<td><?= $dueLabel ?></td>
<td><?= $a['required_lessons'] ?? 0 ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>

<!-- ===== PROGRAMME LIST ===== -->
<section class="admin-card">
<div class="admin-card__header">
<h2 class="admin-card__title"><i class="fas fa-list"></i> Available programmes</h2>
</div>
<div class="admin-table-container">
<table class="admin-table">
<thead>
<tr>
<th>Title</th>
<th>Category</th>
<th>Provider</th>
<th>Duration</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach ($programmes as $p): ?>
<tr>
<td><?= e($p['title']) ?></td>
<td><?= e($p['category'] ?? '—') ?></td>
<td><?= e($p['provider'] ?? '—') ?></td>
<td><?= $p['duration_hours'] . 'h' ?></td>
<td><span class="badge badge--success">Active</span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</div>
</body>
</html>

