<?php
/**
 * Course Details & Learning Resources
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$candidateId = (int) current_user_id();
$assignmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($assignmentId <= 0) {
    set_flash('error', 'Course not found', 'No course selected.');
    safe_redirect('learning.php');
}

$show = CandidateLearningController::show($candidateId, $assignmentId);
if (!empty($show['error'])) {
    set_flash('error', 'Access denied', $show['error']);
    safe_redirect('learning.php');
}
$assignment = $show['assignment'];
$lessons = $show['lessons'];
$completed = $show['completed_lessons'];

$stats = CandidateLearningController::skillsView($candidateId);
$certifications = CandidateLearningController::certificationsView($candidateId)['certificates'];
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($assignment['title']) ?> | Investhood IT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/applications.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page course-page">

<!-- ===== COURSE SUMMARY ===== -->
<section class="course-summary">
<div class="course-summary__card">
<div class="course-summary__header">
<h2 class="course-summary__title">Course overview</h2>
<span class="badge badge--primary"><?= $assignment['programme_status'] ? LearningProgramme::statusLabel($assignment['programme_status']) : 'Active' ?></span>
</div>
<p><?= nl2br(e($assignment['description'] ?? 'No description provided.')) ?></p>
<div class="course-summary__meta">
<div class="course-summary__item"><i class="fas fa-tag"></i> <span><?= e($assignment['category'] ?? 'General') ?></span></div>
<div class="course-summary__item"><i class="fas fa-calendar-alt"></i> <span><?= e($assignment['start_date'] ?? '—') ?></span></div>
<div class="course-summary__item"><i class="fas fa-calendar-times"></i> <?= $assignment['due_date'] ? "<span class=\"label\">Due</span><span>" . e(format_date($assignment['due_date'], 'd M Y')) . "</span>" : '<span class="label">No due date</span>' ?></div>
<div class="course-summary__item"><i class="fas fa-clock"></i> <span><?= $assignment['duration_hours'] > 0 ? $assignment['duration_hours'] . ' hours' : '—' ?></span></div>
</div>
</div>
</section>

<!-- ===== PROGRESS BAR ===== -->
<section class="course-progress">
<div class="course-progress__header">
<h3>Your progress</h3>
<a href="<?= url('candidate/learning.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to programmes</a>
</div>
<div class="progress-card">
<div class="progress-card__header"><span class="progress-card__value"><?= $assignment['progress'] ?>%</span></div>
<div class="progress-card__bar"><div class="progress-card__fill" style="width: <?= $assignment['progress'] ?>%"></div></div>
<div class="progress-card__stats">
<div class="progress-stat"><span class="progress-stat__value"><?= $assignment['required_lessons'] ?? 0 ?></span><span class="progress-stat__label">Lessons total</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= $assignment['completed_lessons'] ?? 0 ?></span><span class="progress-stat__label">Completed</span></div>
</div>
</div>
</section>

<!-- ===== LESSON NAVIGATION ===== -->
<section class="lesson-nav">
<h3>Lessons &amp; learning resources</h3>
<?php if (empty($lessons)): ?>
<p class="empty-state__text">No lessons are configured for this course yet.</p>
<?php else: ?>
<div class="lesson-nav__list">
<?php foreach ($lessons as $lesson): ?>
<div class="lesson-nav__item <?= isset($completed[(int) $lesson['id']] ?? false) ? 'lesson-nav__item--complete' : '' ?>">
<input type="checkbox" id="lesson-<?= (int) $lesson['id'] ?>" class="lesson-checkbox" data-lesson-id="<?= (int) $lesson['id'] ?>" <?= isset($completed[(int) $lesson['id']] ?? false) ? 'checked' : '' ?>>
<label for="lesson-<?= (int) $lesson['id'] ?>" class="lesson-nav__item-label">
<span class="lesson-nav__badge"><?= LearningProgramme::lessonTypeIcon($lesson['type']) ?></span>
<span class="lesson-nav__item-title"><?= e($lesson['title']) ?></span>
<span class="lesson-nav__item-type"><?= LearningProgramme::lessonTypeLabel($lesson['type']) ?></span>
</label>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

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
<li><a href="<?= url('candidate/learning.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> My Learning</a></li>
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

<!-- ===== ACTIVE LESSON CONTENT ===== -->
<section class="lesson-content">
<div class="lesson-content__header">
<h3 id="active-lesson-title">Open a lesson to begin</h3>
<span class="badge badge--muted">Select a lesson above</span>
</div>
<div class="lesson-content__body">
<p>Choose a lesson from the list on the left to view the content and mark it complete.</p>
</div>
</section>

<!-- ===== LESSON COMPLETE ACTION ===== -->
<?php if (!empty($completed[(int) $lesson['id']] ?? false)): ?>
<div class="lesson-complete-banner">
<i class="fas fa-check-circle"></i> This lesson is complete.
</div>
<?php else: ?>
<form method="post" action="<?= url('candidate/learning_api.php') ?>" class="lesson-complete-form" data-course-id="<?= $assignment['assignment_id'] ?>">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="mark_lesson_complete">
<input type="hidden" name="assignment_id" value="<?= $assignment['assignment_id'] ?>">
<input type="hidden" name="lesson_id" value="0">
<button type="submit" class="btn btn--primary" id="markLessonBtn" disabled>
<i class="fas fa-check"></i> Mark complete
</button>
</form>
<?php endif; ?>
</div>
</main>
</div>

<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/learning.js') ?>"></script>
</body>
</html>

</div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="dash-content">
<header class="page-header">
<div class="container">
<h1 class="page-header__title"><?= e($assignment['title']) ?></h1>
<p class="page-header__subtitle">Course details, lessons and learning resources</p>
</div>
</header>
<div class="container">
