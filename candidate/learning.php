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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/applications.css') ?>">
<style>
.learning-page .dashboard__main { background: #f5f7fb; }
.learning-page .learning-hero {
  color: #fff;
  padding: 2.25rem 0;
  background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
  box-shadow: 0 8px 24px rgba(15, 59, 138, .16);
}
.learning-page .learning-hero .container {
  display: flex; align-items: center; justify-content: space-between;
  gap: 2rem; flex-wrap: wrap;
}
.learning-page .learning-hero__eyebrow {
  display: inline-flex; align-items: center; gap: .5rem;
  margin-bottom: .65rem; font-size: .75rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; opacity: .8;
}
.learning-page .learning-hero__title {
  margin: 0 0 .45rem; font-size: clamp(1.65rem, 3vw, 2.2rem);
  font-weight: 800; line-height: 1.2;
}
.learning-page .learning-hero__subtitle { margin: 0; max-width: 620px; font-size: .95rem; opacity: .86; }
.learning-page .learning-hero__progress { display: flex; align-items: center; gap: .85rem; }
.learning-page .learning-hero__progress-ring {
  width: 82px; height: 82px; display: grid; place-items: center;
  border-radius: 50%; font-size: 1.15rem; font-weight: 800;
  background: radial-gradient(circle at center, var(--primary-dark) 58%, transparent 59%), conic-gradient(#fff <?= (int) $overallProgress ?>%, rgba(255,255,255,.2) 0);
}
.learning-page .learning-hero__progress-label { max-width: 75px; font-size: .75rem; line-height: 1.35; opacity: .8; }
.learning-page .sidebar__section-label { display: flex; align-items: center; gap: .45rem; }
.learning-page .sidebar__section-label i { color: var(--primary); font-size: .72rem; }
.learning-page .sidebar__link i { transition: color .2s, transform .2s; }
.learning-page .sidebar__link:hover i,
.learning-page .sidebar__link.active i { color: var(--primary); transform: translateX(1px); }
.learning-page .dash-content > .container { width: 100%; padding-top: 1.75rem; padding-bottom: 2.5rem; }
.learning-page .learning-overview { margin-bottom: 1.5rem; }
.learning-page .progress-card,
.learning-page .filter-bar {
  padding: 1.5rem; background: #fff; border: 1px solid var(--border);
  border-radius: var(--radius-md); box-shadow: 0 4px 16px rgba(15, 23, 42, .04);
}
.learning-page .progress-card__header { margin-bottom: 1rem; }
.learning-page .progress-card__header h3 { display: flex; align-items: center; gap: .55rem; font-size: 1.05rem; font-weight: 750; color: var(--dark); }
.learning-page .progress-card__header h3 i { color: var(--primary); font-size: .9rem; }
.learning-page .progress-card__value { font-size: 1.5rem; }
.learning-page .progress-card__bar { height: 11px; background: #eaf0f8; }
.learning-page .progress-card__fill { background: linear-gradient(90deg, var(--primary), var(--secondary)); }
.learning-page .progress-card__stats { gap: .75rem; margin-top: 1.5rem; }
.learning-page .progress-stat {
  display: flex; flex-direction: column; align-items: flex-start; gap: .15rem;
  padding: .85rem 1rem; text-align: left; background: #f8fafc;
  border: 1px solid var(--border-light); border-right: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
}
.learning-page .progress-stat__value { font-size: 1.35rem; }
.learning-page .progress-stat__label { font-size: .72rem; text-transform: none; letter-spacing: 0; }
.learning-page .progress-stat__label i { margin-right: .25rem; color: var(--primary); }
.learning-page .filter-bar { margin-bottom: 1.75rem; }
.learning-page .filter-bar__header { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; }
.learning-page .filter-bar__icon {
  width: 34px; height: 34px; display: grid; place-items: center;
  color: var(--primary); background: var(--primary-bg); border-radius: var(--radius-sm);
}
.learning-page .filter-bar__title { margin: 0; font-size: 1rem; color: var(--dark); }
.learning-page .filter-bar__hint { margin: .15rem 0 0; color: var(--text-light); font-size: .78rem; }
.learning-page .filter-form {
  display: grid; grid-template-columns: minmax(0, 2fr) repeat(3, minmax(0, 1fr)) auto auto;
  gap: 1rem; align-items: end;
}
.learning-page .filter-form__group { min-width: 0; display: flex; flex-direction: column; gap: .45rem; }
.learning-page .filter-form__group label {
  display: flex; align-items: center; gap: .4rem;
  color: var(--text); font-size: .78rem; font-weight: 700; letter-spacing: .01em;
}
.learning-page .filter-form__group label i { width: 13px; color: var(--primary); font-size: .72rem; text-align: center; }
.learning-page .filter-form__group input,
.learning-page .filter-form__group select {
  width: 100%; height: 44px; padding: 0 .8rem; color: var(--dark);
  background: #fff; border: 1px solid #cbd5e1; border-radius: var(--radius-sm);
  font: inherit; font-size: .84rem; transition: border-color .2s, box-shadow .2s;
}
.learning-page .filter-form__group input::placeholder { color: var(--text-lighter); }
.learning-page .filter-form__search { position: relative; }
.learning-page .filter-form__search > i {
  position: absolute; left: .85rem; top: 50%; z-index: 1;
  color: var(--text-lighter); font-size: .8rem; transform: translateY(-50%);
}
.learning-page .filter-form__search input { padding-left: 2.25rem; }
.learning-page .filter-form__group select {
  appearance: none; padding-right: 2.25rem;
  background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364758b' d='m2.2 4.2 3.8 3.8 3.8-3.8.9.9-4.7 4.7-4.7-4.7z'/%3E%3C/svg%3E") no-repeat right .8rem center;
}
.learning-page .filter-form__group input:focus,
.learning-page .filter-form__group select:focus {
  outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,86,219,.14);
}
.learning-page .filter-form > .btn { height: 44px; }
.learning-page .programmes-section { margin-top: 0 !important; }
.learning-page .programmes-section__header {
  display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; margin-bottom: 1rem;
}
.learning-page .programmes-section__title {
  display: flex; align-items: center; gap: .55rem;
  margin: 0; color: var(--dark); font-size: 1.1rem;
}
.learning-page .programmes-section__title i { color: var(--primary); font-size: .95rem; }
.learning-page .programmes-section__count { color: var(--text-light); font-size: .78rem; }
.learning-page .programmes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1.25rem; }
.learning-page .learning-card {
  display: flex; flex-direction: column; min-width: 0; padding: 1.35rem;
  background: #fff; border: 1px solid var(--border); border-radius: var(--radius-md);
  box-shadow: 0 3px 12px rgba(15, 23, 42, .04); transition: transform .2s, box-shadow .2s, border-color .2s;
}
.learning-page .learning-card:hover { transform: translateY(-3px); border-color: #b7c9e8; box-shadow: 0 12px 25px rgba(15, 23, 42, .09); }
.learning-page .learning-card__header { align-items: flex-start; margin-bottom: .55rem; }
.learning-page .learning-card__header h4 { flex: 1; margin: 0; font-size: 1rem; line-height: 1.4; }
.learning-page .learning-card__header h4 i { margin-right: .4rem; color: var(--primary); font-size: .85rem; }
.learning-page .learning-card__badge { flex-shrink: 0; padding: .3rem .6rem; font-size: .68rem; }
.learning-page .learning-card__badge i { margin-right: .25rem; }
.learning-page .learning-card__meta { display: flex; align-items: center; gap: .4rem; margin: 0 0 1.25rem; color: var(--text-light); font-size: .8rem; }
.learning-page .learning-card__meta i { color: var(--primary); font-size: .75rem; }
.learning-page .learning-card__progress { margin-top: auto; padding-top: .25rem; }
.learning-page .learning-card__progress .progress-bar { height: 8px; background: #edf2f7; }
.learning-page .learning-card__percent { font-size: .75rem; }
.learning-page .learning-card__stats { margin: .85rem 0 0; padding-top: .85rem; border-top: 1px solid var(--border-light); }
.learning-page .learning-card__actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1.1rem !important; }
.learning-page .learning-card__start-form { display: inline; }
.learning-page .empty-state {
  grid-column: 1 / -1; padding: 3.5rem 1.5rem; text-align: center;
  background: #fff; border: 1px solid var(--border); border-radius: var(--radius-md);
}
.learning-page .empty-state__icon {
  width: 64px; height: 64px; display: grid; place-items: center; margin: 0 auto 1rem;
  color: var(--primary); background: var(--primary-bg); border-radius: 50%; font-size: 1.4rem;
}
.learning-page .empty-state__title { margin-bottom: .45rem; color: var(--dark); font-size: 1.15rem; }
.learning-page .empty-state__text { max-width: 480px; margin: 0 auto 1.25rem; color: var(--text-light); font-size: .88rem; }
@media (max-width: 1100px) {
  .learning-page .filter-form { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .learning-page .filter-form__group:first-child { grid-column: 1 / -1; }
}
@media (max-width: 640px) {
  .learning-page .learning-hero { padding: 1.75rem 0; }
  .learning-page .learning-hero .container { align-items: flex-start; }
  .learning-page .learning-hero__progress { width: 100%; justify-content: flex-start; }
  .learning-page .dash-content > .container { padding-top: 1rem; }
  .learning-page .progress-card, .learning-page .filter-bar { padding: 1rem; }
  .learning-page .progress-card__stats { grid-template-columns: repeat(2, 1fr); }
  .learning-page .filter-form { grid-template-columns: 1fr; gap: .8rem; }
  .learning-page .filter-form__group:first-child { grid-column: auto; }
  .learning-page .filter-form > .btn { width: 100%; }
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
<div class="sidebar__section-label"><i class="fas fa-th-large" aria-hidden="true"></i> Main</div>
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
<header class="dash-header">
  <div class="dash-header__left">
    <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
  </div>
  <div class="dash-header__right">
    <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
    <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
  </div>
</header>
<div class="dash-content">
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
<div class="progress-card__header"><h3><i class="fas fa-chart-line" aria-hidden="true"></i> Overall Learning Progress</h3><span class="progress-card__value"><?= $overallProgress ?>%</span></div>
<div class="progress-card__bar"><div class="progress-card__fill" style="width: <?= $overallProgress ?>%"></div></div>
<div class="progress-card__stats">
<div class="progress-stat"><span class="progress-stat__value"><?= $stats['total'] ?></span><span class="progress-stat__label"><i class="fas fa-layer-group"></i> Programmes assigned</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= $stats['completed'] ?></span><span class="progress-stat__label"><i class="fas fa-check-circle"></i> Completed</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= $stats['in_progress'] ?></span><span class="progress-stat__label"><i class="fas fa-spinner"></i> In progress</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= $stats['overdue'] ?></span><span class="progress-stat__label"><i class="fas fa-clock"></i> Overdue</span></div>
</div>
</div>

<section class="filter-bar">
<div class="filter-bar__header">
<span class="filter-bar__icon"><i class="fas fa-sliders-h"></i></span>
<div><h2 class="filter-bar__title">Find a programme</h2><p class="filter-bar__hint">Use the filters below to narrow your learning plan.</p></div>
</div>
<form method="get" action="<?= url('candidate/learning.php') ?>" class="filter-form">
<div class="filter-form__group"><label for="search"><i class="fas fa-search" aria-hidden="true"></i> Search programmes</label><div class="filter-form__search"><i class="fas fa-search" aria-hidden="true"></i><input type="text" id="search" name="search" placeholder="Search by programme title or category" value="<?= e($filtersUsed['search']) ?>"></div></div>
<div class="filter-form__group"><label for="category"><i class="fas fa-tags" aria-hidden="true"></i> Category</label>
<select id="category" name="category">
<option value="" <?= $filtersUsed['category'] === '' ? 'selected' : '' ?>>All Categories</option>
<option value="digital_skills" <?= $filtersUsed['category'] === 'digital_skills' ? 'selected' : '' ?>>Digital Skills</option>
<option value="professional_development" <?= $filtersUsed['category'] === 'professional_development' ? 'selected' : '' ?>>Professional Development</option>
<option value="cybersecurity" <?= $filtersUsed['category'] === 'cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
<option value="cloud_computing" <?= $filtersUsed['category'] === 'cloud_computing' ? 'selected' : '' ?>>Cloud Computing</option>
<option value="project_management" <?= $filtersUsed['category'] === 'project_management' ? 'selected' : '' ?>>Project Management</option>
<option value="communication" <?= $filtersUsed['category'] === 'communication' ? 'selected' : '' ?>>Communication</option>
<option value="data_analysis" <?= $filtersUsed['category'] === 'data_analysis' ? 'selected' : '' ?>>Data Analysis</option>
</select></div>
<div class="filter-form__group"><label for="status"><i class="fas fa-tasks" aria-hidden="true"></i> Status</label>
<select id="status" name="status">
<option value="all" <?= $filtersUsed['status'] === 'all' ? 'selected' : '' ?>>All Statuses</option>
<option value="assigned" <?= $filtersUsed['status'] === 'assigned' ? 'selected' : '' ?>>Assigned</option>
<option value="in_progress" <?= $filtersUsed['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
<option value="completed" <?= $filtersUsed['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
<option value="overdue" <?= $filtersUsed['status'] === 'overdue' ? 'selected' : '' ?>>Overdue</option>
</select></div>
<div class="filter-form__group"><label for="sort"><i class="fas fa-sort-amount-down" aria-hidden="true"></i> Sort by</label>
<select id="sort" name="sort">
<option value="assigned_at" <?= $filtersUsed['sort'] === 'assigned_at' ? 'selected' : '' ?>>Assignment date</option>
<option value="due_date" <?= $filtersUsed['sort'] === 'due_date' ? 'selected' : '' ?>>Due date</option>
<option value="title" <?= $filtersUsed['sort'] === 'title' ? 'selected' : '' ?>>Title</option>
</select></div>
<button type="submit" class="btn btn--primary"><i class="fas fa-filter"></i> Apply Filters</button>
<?php if ($filtersUsed['search'] !== '' || $filtersUsed['category'] !== '' || $filtersUsed['status'] !== 'all' || $filtersUsed['sort'] !== 'assigned_at'): ?>
<a href="<?= url('candidate/learning.php') ?>" class="btn btn--ghost"><i class="fas fa-undo"></i> Clear</a>
<?php endif; ?>
</form>
</section>

<section class="programmes-section">
  <div class="programmes-section__header">
    <h2 class="programmes-section__title"><i class="fas fa-book-reader" aria-hidden="true"></i> Your programmes</h2>
    <span class="programmes-section__count"><?= count($assignments) ?> programme<?= count($assignments) === 1 ? '' : 's' ?></span>
  </div>
  <div class="programmes-grid">
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
          $assigned = !empty($a['assigned_at']) ? format_date($a['assigned_at'], 'M d, Y') : 'Not specified';
          $due      = !empty($a['due_date']) ? format_date($a['due_date'], 'M d, Y') : 'No due date';
          $badgeClass = $tone === 'success' ? 'learning-card__badge--green' : ($tone === 'secondary' ? 'learning-card__badge--cyan' : '');
          $statusIcon = $status === 'completed' ? 'fa-check-circle' : ($status === 'in_progress' ? 'fa-spinner' : ($status === 'overdue' ? 'fa-exclamation-circle' : 'fa-bookmark'));
        ?>
        <article class="learning-card <?= $status === 'completed' ? 'learning-card--complete' : '' ?>">
          <div class="learning-card__header">
            <h4><i class="fas fa-book-open" aria-hidden="true"></i><?= e($a['title']) ?></h4>
            <span class="learning-card__badge <?= e($badgeClass) ?>"><i class="fas <?= e($statusIcon) ?>" aria-hidden="true"></i><?= e($label) ?></span>
          </div>
          <p class="learning-card__meta"><i class="fas fa-tag"></i> <?= e($a['category'] ?? 'General') ?> <span aria-hidden="true">&middot;</span> <?= (int) ($a['duration_hours'] ?? 0) ?>h</p>
          <div class="learning-card__progress">
            <div class="progress-bar"><div class="progress-bar__fill" style="width:<?= $progress ?>%"></div></div>
            <span class="learning-card__percent"><?= $progress ?>% Complete</span>
          </div>
          <div class="learning-card__stats">
            <span><i class="fas fa-calendar-plus"></i> Assigned: <?= e($assigned) ?></span>
            <span><i class="fas fa-check-circle"></i> <?= (int) ($a['completed_lessons'] ?? 0) ?>/<?= (int) ($a['required_lessons'] ?? 0) ?> Lessons</span>
            <span><i class="fas fa-calendar-alt"></i> Due: <?= e($due) ?></span>
          </div>
          <div class="learning-card__actions">
            <?php if ($status === 'completed'): ?>
              <a href="<?= url('candidate/course.php?id=' . (int) $a['assignment_id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i> Review <i class="fas fa-arrow-right"></i></a>
            <?php elseif ($status === 'assigned'): ?>
              <form method="post" action="<?= url('candidate/learning_api.php') ?>" class="learning-card__start-form" data-action="start_course">
                <?= csrf_field() ?>
                <input type="hidden" name="assignment_id" value="<?= (int) $a['assignment_id'] ?>">
                <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-play"></i> Start</button>
              </form>
            <?php else: ?>
              <a href="<?= url('candidate/course.php?id=' . (int) $a['assignment_id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-play-circle"></i> Continue Learning <i class="fas fa-arrow-right"></i></a>
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
