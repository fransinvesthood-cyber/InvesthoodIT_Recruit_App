<?php
/**
 * My Learning Programmes
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$candidateId = (int) current_user_id();
$filters = CandidateLearningController::normaliseFilters($_GET);
$result = CandidateLearningController::index($candidateId, $filters);
$assignments = $result['assignments'];
$stats = $result['stats'];
$overallProgress = $result['overall_progress'];
$filtersUsed = $result['filters'];
$dashboardStats = $stats;
$learningNotifications = CandidateLearningController::notifications($candidateId);
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Learning Programmes | Investhood IT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/applications.css') ?>">
<style>
.learning-hero {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark, #1e3a8a) 100%);
  color: #fff;
  padding: 2rem 0;
  margin-bottom: 1.5rem;
  border-radius: 0 0 16px 16px;
  box-shadow: 0 10px 30px rgba(30, 58, 138, 0.18);
}
.learning-hero .container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  flex-wrap: wrap;
}
.learning-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  opacity: 0.9;
  margin-bottom: 0.5rem;
}
.learning-hero__title {
  font-size: 2rem;
  font-weight: 800;
  margin: 0 0 0.5rem;
  line-height: 1.2;
}
.learning-hero__subtitle {
  font-size: 1rem;
  opacity: 0.9;
  margin: 0;
  max-width: 640px;
}
.learning-hero__progress {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}
.learning-hero__progress-ring {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.15);
  border: 3px solid rgba(255, 255, 255, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  font-weight: 800;
  box-shadow: inset 0 0 12px rgba(255, 255, 255, 0.1);
}
.learning-hero__progress-label {
  font-size: 0.75rem;
  opacity: 0.9;
}
@media (max-width: 640px) {
  .learning-hero__title { font-size: 1.5rem; }
  .learning-hero .container { flex-direction: column; text-align: center; }
}
.learning-overview .progress-card {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
.learning-overview .progress-card__fill {
  background: linear-gradient(90deg, var(--primary), var(--secondary, #06b6d4));
}
.programmes-grid .learning-card {
  border-radius: 14px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.programmes-grid .learning-card:hover {
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.1);
}
.programmes-grid .learning-card__header h4 {
  font-size: 1rem;
  line-height: 1.3;
}
</style>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page learning-page">
<div class="dashboard">
<aside class="sidebar" id="sidebar">
<div class="sidebar__header">
<a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a>
<button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
</div>
<nav class="sidebar__nav">
<div class="sidebar__section-label">Main</div>
<ul class="sidebar__menu">
<li><a href="<?= url('candidate/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Dashboard</a></li>
<li><a href="<?= url('candidate/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
<li><a href="<?= url('candidate/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
<li><a href="<?= url('candidate/learning.php') ?>" class="sidebar__link active"><i class="fas fa-graduation-cap"></i> My Learning</a></li>
<li><a href="<?= url('candidate/skills.php') ?>" class="sidebar__link"><i class="fas fa-star"></i> Skills</a></li>
<li><a href="<?= url('candidate/certifications.php') ?>" class="sidebar__link"><i class="fas fa-certificate"></i> Certifications</a></li>
<li><a href="<?= url('candidate/notifications.php') ?>" class="sidebar__link"><i class="fas fa-bell"></i> Notifications</a></li>
<li><a href="<?= url('candidate/profile.php') ?>" class="sidebar__link"><i class="fas fa-user"></i> My Profile</a></li>
<li><a href="<?= url('candidate/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> Settings</a></li>
</ul>
</nav>
<div class="sidebar__footer">
<div class="sidebar__user">
<div class="sidebar__user-avatar"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile"></div>
<div class="sidebar__user-info">
<span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Candidate') ?></span>
<span class="sidebar__user-role">Candidate</span>
</div>
</div>
<a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
</div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="dashboard__main">
<header class="learning-hero">
  <div class="container">
    <div class="learning-hero__content">
      <span class="learning-hero__eyebrow"><i class="fas fa-graduation-cap"></i> Learning Hub</span>
      <h1 class="learning-hero__title">My Learning Programmes</h1>
      <p class="learning-hero__subtitle">Track courses assigned to you, complete lessons, and watch your skills grow.</p>
    </div>
    <div class="learning-hero__progress">
      <div class="learning-hero__progress-ring">
        <span><?= (int) $overallProgress ?>%</span>
      </div>
      <span class="learning-hero__progress-label">Overall progress</span>
    </div>
  </div>
</header>
<div class="container">
<section class="learning-overview">
<div class="progress-card">
<div class="progress-card__header"><h3>Overall Learning Progress</h3><span class="progress-card__value"><?= $overallProgress ?>%</span></div>
<div class="progress-card__bar"><div class="progress-card__fill" style="width: <?= $overallProgress ?>%"></div></div>
<div class="progress-card__stats">
<div class="progress-stat"><span class="progress-stat__value"><?= $stats['total'] ?></span><span class="progress-stat__label">Programmes assigned</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= $stats['completed'] ?></span><span class="progress-stat__label">Completed</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= $stats['in_progress'] ?></span><span class="progress-stat__label">In progress</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= $stats['overdue'] ?></span><span class="progress-stat__label">Overdue</span></div>
</div>
</div>

<section class="filter-bar">
<form method="get" action="<?= url('candidate/learning.php') ?>" class="filter-form">
<div class="filter-form__group"><label for="search">Search courses</label><input type="text" id="search" name="search" placeholder="Search by title, category or provider..." value="<?= e($filtersUsed['search']) ?>"></div>
<div class="filter-form__group"><label for="category">Category</label>
<select id="category" name="category">
<option value="general" <?= $filtersUsed['category'] === 'general' ? 'selected' : '' ?>>All Categories</option>
<option value="digital_skills" <?= $filtersUsed['category'] === 'digital_skills' ? 'selected' : '' ?>>Digital Skills</option>
<option value="professional_development" <?= $filtersUsed['category'] === 'professional_development' ? 'selected' : '' ?>>Professional Development</option>
<option value="cybersecurity" <?= $filtersUsed['category'] === 'cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
<option value="cloud_computing" <?= $filtersUsed['category'] === 'cloud_computing' ? 'selected' : '' ?>>Cloud Computing</option>
<option value="project_management" <?= $filtersUsed['category'] === 'project_management' ? 'selected' : '' ?>>Project Management</option>
<option value="communication" <?= $filtersUsed['category'] === 'communication' ? 'selected' : '' ?>>Communication</option>
<option value="data_analysis" <?= $filtersUsed['category'] === 'data_analysis' ? 'selected' : '' ?>>Data Analysis</option>
</select></div>
<div class="filter-form__group"><label for="status">Status</label>
<select id="status" name="status">
<option value="assigned" <?= $filtersUsed['status'] === 'assigned' ? 'selected' : '' ?>>Assigned</option>
<option value="in_progress" <?= $filtersUsed['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
<option value="completed" <?= $filtersUsed['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
<option value="overdue" <?= $filtersUsed['status'] === 'overdue' ? 'selected' : '' ?>>Overdue</option>
</select></div>
<div class="filter-form__group"><label for="sort">Sort by</label>
<select id="sort" name="sort">
<option value="assigned_at" <?= $filtersUsed['sort'] === 'assigned_at' ? 'selected' : '' ?>>Assignment date</option>
<option value="due_date" <?= $filtersUsed['sort'] === 'due_date' ? 'selected' : '' ?>>Due date</option>
<option value="title" <?= $filtersUsed['sort'] === 'title' ? 'selected' : '' ?>>Title</option>
</select></div>
<button type="submit" class="btn btn--primary"><i class="fas fa-filter"></i> Apply Filters</button>
<?php if ($filtersUsed['search'] !== '' || $filtersUsed['category'] !== 'general' || $filtersUsed['status'] !== 'assigned' || $filtersUsed['sort'] !== 'assigned_at'): ?>
<a href="<?= url('candidate/learning.php') ?>" class="btn btn--ghost">Clear</a>
<?php endif; ?>
</form>
</section>

<section class="programmes-section" style="margin-top:1.5rem;">
  <div class="programmes-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem;">
    <?php if (empty($assignments)): ?>
      <div class="empty-state">
        <div class="empty-state__icon"><i class="fas fa-graduation-cap"></i></div>
        <h3 class="empty-state__title">No programmes assigned yet</h3>
        <p class="empty-state__text">You don't have any learning programmes assigned to you right now. Check back soon or explore opportunities to get started.</p>
        <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-briefcase"></i> Explore Opportunities</a>
      </div>
    <?php else: ?>
      <?php foreach ($assignments as $a): ?>
        <?php
          $status   = $a['computed_status'] ?? 'assigned';
          $tone     = LearningProgramme::statusTone($status);
          $label    = LearningProgramme::statusLabel($status);
          $progress = (int) ($a['progress'] ?? 0);
          $due      = !empty($a['due_date']) ? format_date($a['due_date'], 'M d, Y') : 'No due date';
          $badgeClass = $tone === 'success' ? 'learning-card__badge--green' : ($tone === 'secondary' ? 'learning-card__badge--cyan' : '');
        ?>
        <article class="learning-card <?= $status === 'completed' ? 'learning-card--complete' : '' ?>">
          <div class="learning-card__header">
            <h4><?= e($a['title']) ?></h4>
            <span class="learning-card__badge <?= e($badgeClass) ?>"><?= e($label) ?></span>
          </div>
          <p class="text-muted" style="font-size:.85rem;margin:.5rem 0;"><?= e($a['category'] ?? 'General') ?> &middot; <?= (int) ($a['duration_hours'] ?? 0) ?>h</p>
          <div class="learning-card__progress">
            <div class="progress-bar"><div class="progress-bar__fill" style="width:<?= $progress ?>%"></div></div>
            <span class="learning-card__percent"><?= $progress ?>% Complete</span>
          </div>
          <div class="learning-card__stats">
            <span><i class="fas fa-check-circle"></i> <?= (int) ($a['completed_lessons'] ?? 0) ?>/<?= (int) ($a['required_lessons'] ?? 0) ?> Lessons</span>
            <span><i class="fas fa-calendar-alt"></i> Due: <?= e($due) ?></span>
          </div>
          <div class="learning-card__actions" style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap;">
            <?php if ($status === 'completed'): ?>
              <a href="<?= url('candidate/course.php?id=' . (int) $a['assignment_id']) ?>" class="btn btn--ghost btn--sm">Review <i class="fas fa-arrow-right"></i></a>
            <?php elseif ($status === 'assigned'): ?>
              <form method="post" action="<?= url('candidate/learning_api.php') ?>" data-action="start_course" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="assignment_id" value="<?= (int) $a['assignment_id'] ?>">
                <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-play"></i> Start</button>
              </form>
            <?php else: ?>
              <a href="<?= url('candidate/course.php?id=' . (int) $a['assignment_id']) ?>" class="btn btn--ghost btn--sm">Continue Learning <i class="fas fa-arrow-right"></i></a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

</div>
</main>
</div>
<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/learning.js') ?>"></script>
</body>
</html>
