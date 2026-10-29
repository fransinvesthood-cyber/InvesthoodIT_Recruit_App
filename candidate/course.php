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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/applications.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
<style>
.course-page { background: #f5f7fb; color: #334155; }
.course-page .dashboard { min-height: 100vh; }
.course-page .dash-content {
  width: 100%; max-width: none; margin-left: 0;
  padding: 0 2.5rem 3.5rem; box-sizing: border-box;
}
.course-page .page-header {
  position: relative; overflow: hidden;
  margin: 0 -2.5rem 2rem; padding: 2rem 2.5rem 2.25rem;
  color: #fff; background: linear-gradient(135deg, #123f8f, #1d66d2);
  box-shadow: 0 12px 30px rgba(18, 63, 143, .18);
}
.course-page .page-header::after {
  content: ""; position: absolute; width: 280px; height: 280px; right: 8%;
  top: -190px; border: 1px solid rgba(255,255,255,.13); border-radius: 50%;
  box-shadow: 0 0 0 28px rgba(255,255,255,.035), 0 0 0 56px rgba(255,255,255,.025);
  pointer-events: none;
}
.course-page .page-header .container { position: relative; z-index: 1; }
.course-page .page-header__content { display: flex; align-items: flex-end; justify-content: space-between; gap: 2.5rem; }
.course-page .page-header__title { margin: 0 0 .45rem; color: #fff; font-size: clamp(1.65rem, 3vw, 2.25rem); font-weight: 800; }
.course-page .page-header__subtitle { max-width: 680px; margin: 0; color: rgba(255,255,255,.82); font-size: .95rem; line-height: 1.6; }
.course-page .page-header__aside { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .65rem; max-width: 300px; }
.course-page .page-header__meta { display: inline-flex; align-items: center; gap: .45rem; padding: .65rem .8rem; color: #fff; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.2); border-radius: 10px; font-size: .78rem; backdrop-filter: blur(8px); }
.course-page .page-header__meta:first-child { color: #d1fae5; background: rgba(16,185,129,.2); border-color: rgba(167,243,208,.35); }
.course-page .page-header__breadcrumb { margin: 0 0 .8rem; color: rgba(255,255,255,.7); font-size: .75rem; }
.course-page .page-header__breadcrumb a { color: #fff; text-decoration: underline; text-underline-offset: 3px; }
.course-page .badge--success { color: #047857; background: #d1fae5; }
.course-page .badge--success i { margin-right: .25rem; font-size: .45rem; vertical-align: middle; }
.course-page .dash-content > .container { display: grid; grid-template-columns: minmax(260px, .34fr) minmax(0, .66fr); gap: 1.5rem; align-items: start; }
.course-page .course-main { display: contents; }
.course-page .course-summary, .course-page .course-progress, .course-page .lesson-nav,
.course-page .lesson-content, .course-page .lesson-complete-form, .course-page .lesson-complete-banner {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .055);
}
.course-page .course-summary { display: block; grid-column: 1 / -1; overflow: hidden; }
.course-page .course-summary__card { padding: 1.5rem 1.75rem; border: 0; background: transparent; }
.course-page .course-summary__header { align-items: flex-start; margin-bottom: .85rem; gap: 1rem; }
.course-page .course-summary__header h2 { color: #0f274d; font-size: 1.35rem; font-weight: 800; }
.course-page .course-summary__card > p { max-width: 820px; margin: 0; color: #64748b; line-height: 1.7; }
.course-page .course-summary__meta { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; margin-top: 1.35rem; padding-top: 1.35rem; border-top: 1px solid #edf2f7; }
.course-page .course-summary__item { min-height: 54px; padding: .8rem .9rem; background: #f8fafc; border: 1px solid #edf2f7; border-radius: 10px; }
.course-page .course-summary__item i { margin-bottom: .25rem; color: #1d66d2; font-size: .85rem; }
.course-page .course-summary__item span { color: #334155; font-size: .82rem; }
.course-page .course-summary__item .label { color: #64748b; }
.course-page .course-progress { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin: 0; padding: 0; background: transparent; border: 0; box-shadow: none; }
.course-page .course-progress__header { display: none; }
.course-page .progress-card { min-height: 138px; padding: 1.2rem 1.25rem; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; box-shadow: 0 5px 18px rgba(15, 23, 42, .05); }
.course-page .progress-card__header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.course-page .progress-card__header::before { display: grid; place-items: center; width: 34px; height: 34px; color: #1d66d2; background: #eaf2ff; border-radius: 9px; font: 900 .9rem/1 "Font Awesome 6 Free"; }
.course-page .progress-card:nth-child(1) .progress-card__header::before { content: "\f201"; }
.course-page .progress-card:nth-child(2) .progress-card__header::before { content: "\f02d"; }
.course-page .progress-card:nth-child(3) .progress-card__header::before { content: "\f058"; }
.course-page .progress-card__value { color: #0f274d; font-size: 1.8rem; font-weight: 800; }
.course-page .progress-card__label { display: block; margin-top: .2rem; color: #64748b; font-size: .78rem; font-weight: 700; }
.course-page .course-progress__hint { display: none; }
.course-page .progress-card__bar { height: 7px; margin: .85rem 0 0; overflow: hidden; background: #e7eef8; border-radius: 99px; }
.course-page .progress-card__fill { background: linear-gradient(90deg, #1d66d2, #06b6d4); border-radius: inherit; }
.course-page .progress-card__stats { display: none; }
.course-page .progress-stat { padding: .8rem; background: #f8fafc; border: 1px solid #edf2f7; border-radius: 10px; }
.course-page .progress-stat__value { color: #0f274d; font-weight: 800; }
.course-page .lesson-nav { grid-column: 1; grid-row: 3 / span 2; margin: 0; padding: 1.5rem; position: sticky; top: 1.5rem; }
.course-page .lesson-nav__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
.course-page .lesson-nav h3 { margin: 0; color: #0f274d; font-size: 1.05rem; font-weight: 800; }
.course-page .lesson-nav__count { color: #64748b; font-size: .75rem; white-space: nowrap; }
.course-page .lesson-nav__list { gap: .65rem; }
.course-page .lesson-nav__item { padding: .9rem; background: #fff; border-color: #e2e8f0; border-radius: 10px; }
.course-page .lesson-nav__item:hover { transform: translateY(-1px); border-color: #9dbbe9; background: #f7fbff; }
.course-page .lesson-nav__item.is-selected { border-color: #1d66d2; background: #eff6ff; box-shadow: 0 0 0 3px rgba(29,102,210,.1); }
.course-page .lesson-nav__item--complete { border-left: 3px solid #10b981; background: #f0fdf8; }
.course-page .lesson-nav__item-label { gap: .25rem; }
.course-page .lesson-nav__item-title { color: #1e293b; font-weight: 700; }
.course-page .lesson-nav__badge { width: 30px; height: 30px; color: #1d66d2; background: #eaf2ff; }
.course-page .lesson-nav__direct-link {
  display: inline-flex; align-items: center; gap: .35rem; margin: .7rem 0 0 2.15rem;
  color: #1d66d2; font-size: .73rem; font-weight: 700; text-decoration: none;
}
.course-page .lesson-nav__direct-link:hover { color: #123f8f; text-decoration: underline; text-underline-offset: 3px; }
.course-page .lesson-nav__direct-link i { font-size: .65rem; }
.course-page .course-empty-state { display: grid; justify-items: center; gap: .45rem; padding: 2rem 1rem; color: #64748b; text-align: center; }
.course-page .course-empty-state > i { margin-bottom: .35rem; color: #1d66d2; font-size: 1.5rem; }
.course-page .course-empty-state strong { color: #0f274d; font-size: .95rem; }
.course-page .course-empty-state span { max-width: 260px; font-size: .8rem; line-height: 1.5; }
.course-page .lesson-content { grid-column: 2; margin: 0; padding: 1.5rem; }
.course-page .lesson-content__header { margin-bottom: 1rem; }
.course-page .lesson-content__header h3 { color: #0f274d; font-size: 1.05rem; font-weight: 800; }
.course-page .lesson-content__body { min-height: 165px; padding: 2rem; border: 1px dashed #cbd5e1; background: #f8fafc; line-height: 1.7; }
.course-page .lesson-content__body p { max-width: 420px; margin: 1rem auto 0; }
.course-page .lesson-content__body.lesson-content__body--active { border-style: solid; background: #fff; text-align: left; }
.course-page .lesson-content__body--active::before { display: none; }
.course-page .lesson-resource { margin-top: 1.25rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; }
.course-page .lesson-resource__type { display: block; margin-bottom: .35rem; color: #64748b; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
.course-page .lesson-resource__text { margin: 0; color: #475569; font-size: .88rem; line-height: 1.6; white-space: pre-line; }
.course-page .lesson-resource__link { display: inline-flex; align-items: center; gap: .45rem; margin-top: .9rem; }
.course-page .lesson-content__header .badge { white-space: nowrap; }
.course-page .lesson-content__body::before {
  content: "\f19d"; display: grid; place-items: center; width: 52px; height: 52px;
  margin: 0 auto; color: #1d66d2; background: #eaf2ff; border-radius: 50%;
  font: 900 1.25rem/1 "Font Awesome 6 Free";
}
.course-page .lesson-complete-form, .course-page .lesson-complete-banner { grid-column: 2; margin-top: 1rem; padding: 1rem 1.25rem; }
.course-page .lesson-complete-form { border-color: #dbeafe; background: #f8fbff; }
.course-page .lesson-complete-form { display: flex; justify-content: flex-end; align-items: center; }
.course-page .lesson-complete-form .btn:disabled { cursor: not-allowed; opacity: .7; }
.course-page .btn:focus-visible, .course-page .lesson-nav__item:focus-within { outline: 3px solid rgba(29,102,210,.25); outline-offset: 2px; }
@media (max-width: 900px) {
  .course-page .dash-content > .container { grid-template-columns: 1fr; }
  .course-page .course-summary, .course-page .course-progress, .course-page .lesson-nav,
  .course-page .lesson-content, .course-page .lesson-complete-form { grid-column: 1; grid-row: auto; }
  .course-page .lesson-nav { position: static; }
  .course-page .course-summary__meta { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 1023px) {
  .course-page .dash-content { width: 100%; margin-left: 0; }
}
@media (max-width: 640px) {
  .course-page .dash-content { padding: 0 1rem 2rem; }
  .course-page .page-header { margin: 0 -1rem 1.25rem; padding: 1.5rem 1rem 1.75rem; }
  .course-page .course-summary__card, .course-page .course-progress, .course-page .lesson-nav, .course-page .lesson-content { padding: 1.1rem; }
  .course-page .course-summary__header, .course-page .course-progress__header { flex-direction: column; align-items: flex-start; }
  .course-page .page-header__content { align-items: flex-start; flex-direction: column; gap: 1.25rem; }
  .course-page .page-header__aside { justify-content: flex-start; }
  .course-page .course-progress { grid-template-columns: 1fr; }
  .course-page .course-summary__meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .course-page .lesson-content__body { padding: 1.5rem 1rem; }
}
</style>
</head>
<body class="dashboard-page course-page">

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
<li><a href="<?= url('candidate/learning.php') ?>" class="sidebar__link active" aria-current="page"><i class="fas fa-graduation-cap"></i> My Learning</a></li>
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
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="dashboard__main">
<header class="dash-header" id="dashHeader">
<div class="dash-header__left">
<button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
<div class="dash-header__search" id="dashSearch"><i class="fas fa-search"></i><input type="text" class="dash-header__search-input" placeholder="Search course content..." aria-label="Search course content"></div>
</div>
<div class="dash-header__right">
<button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
<a href="<?= url('candidate/dashboard.php') ?>" class="dash-header__icon-btn" aria-label="Back to dashboard" title="Back to dashboard"><i class="fas fa-th-large"></i></a>
<div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
</div>
</header>
<div class="dash-content">
<header class="page-header">
<div class="container">
<p class="page-header__breadcrumb"><a href="<?= url('candidate/learning.php') ?>">My Learning</a> <span aria-hidden="true">/</span> Course Details</p>
<div class="page-header__content">
<div>
<h1 class="page-header__title"><?= e($assignment['title']) ?></h1>
<p class="page-header__subtitle"><?= e(($assignment['description'] ?? '') ?: 'Build your foundation in modern web development.') ?></p>
</div>
<div class="page-header__aside">
<span class="page-header__meta"><i class="fas fa-circle" aria-hidden="true"></i> <?= $assignment['programme_status'] ? e(LearningProgramme::statusLabel($assignment['programme_status'])) : 'Active' ?></span>
<span class="page-header__meta"><i class="fas fa-calendar-alt" aria-hidden="true"></i> <?= !empty($assignment['due_date']) ? e(format_date($assignment['due_date'], 'd M Y')) : 'No due date' ?></span>
</div>
</div>
</div>
</header>
<div class="container">
<!-- ===== COURSE SUMMARY ===== -->
<section class="course-summary">
<div class="course-summary__card">
<div class="course-summary__header">
<h2 class="course-summary__title">Course overview</h2>
<span class="badge badge--success"><i class="fas fa-circle" aria-hidden="true"></i> <?= $assignment['programme_status'] ? e(LearningProgramme::statusLabel($assignment['programme_status'])) : 'Active' ?></span>
</div>
<p><?= nl2br(e($assignment['description'] ?? 'No description provided.')) ?></p>
<div class="course-summary__meta">
<div class="course-summary__item"><i class="fas fa-tag"></i> <span><?= e($assignment['category'] ?? 'General') ?></span></div>
<div class="course-summary__item"><i class="fas fa-building"></i> <span><?= e($assignment['provider'] ?? 'Provider not specified') ?></span></div>
<div class="course-summary__item"><i class="fas fa-calendar-alt"></i> <span><?= e($assignment['start_date'] ?? '—') ?></span></div>
<div class="course-summary__item"><i class="fas fa-calendar-times"></i> <?= $assignment['due_date'] ? "<span class=\"label\">Due</span><span>" . e(format_date($assignment['due_date'], 'd M Y')) . "</span>" : '<span class="label">No due date</span>' ?></div>
<div class="course-summary__item"><i class="fas fa-clock"></i> <span><?= $assignment['duration_hours'] > 0 ? $assignment['duration_hours'] . ' hours' : '—' ?></span></div>
</div>
</div>
</section>

<section class="course-progress">
<div class="progress-card progress-card--overall">
<div class="progress-card__header"><span class="progress-card__value"><?= $assignment['progress'] ?>%</span></div>
<span class="progress-card__label">Overall Progress</span>
<p class="course-progress__hint"><i class="fas fa-lightbulb" aria-hidden="true"></i> Start your first lesson to begin making progress.</p>
<div class="progress-card__bar"><div class="progress-card__fill" style="width: <?= $assignment['progress'] ?>%"></div></div>
</div>
<div class="progress-card">
<div class="progress-card__header"><span class="progress-card__value"><?= (int) ($assignment['total_lessons'] ?? $assignment['required_lessons'] ?? 0) ?></span></div>
<span class="progress-card__label">Total Lessons</span>
</div>
<div class="progress-card">
<div class="progress-card__header"><span class="progress-card__value"><?= (int) ($assignment['completed_lessons'] ?? 0) ?></span></div>
<span class="progress-card__label">Completed Lessons</span>
</div>
</section>

<section class="lesson-nav">
<div class="lesson-nav__heading">
<div><h3>Course Curriculum</h3><span class="lesson-nav__count">Lessons &amp; learning resources</span></div>
<span class="lesson-nav__count"><?= count($lessons) ?> <?= count($lessons) === 1 ? 'lesson' : 'lessons' ?></span>
</div>
<?php if (empty($lessons)): ?>
<div class="course-empty-state">
<i class="fas fa-book-open" aria-hidden="true"></i>
<strong>No lessons available yet</strong>
<span>Your course curriculum will appear here when lessons are added.</span>
</div>
<?php else: ?>
<div class="lesson-nav__list">
<?php foreach ($lessons as $lesson): ?>
<?php $lessonUrl = trim((string) ($lesson['content_url'] ?? '')); ?>
<div class="lesson-nav__item <?= isset($completed[(int) $lesson['id']]) ? 'lesson-nav__item--complete' : '' ?>" tabindex="0" role="button"
 data-lesson-id="<?= (int) $lesson['id'] ?>"
 data-lesson-title="<?= e($lesson['title']) ?>"
 data-lesson-type="<?= e(LearningProgramme::lessonTypeLabel($lesson['type'])) ?>"
 data-lesson-url="<?= e($lessonUrl) ?>"
 data-lesson-text="<?= e($lesson['content_text'] ?? '') ?>"
 data-lesson-duration="<?= (int) ($lesson['duration_min'] ?? 0) ?>">
<input type="checkbox" id="lesson-<?= (int) $lesson['id'] ?>" class="lesson-checkbox" data-lesson-id="<?= (int) $lesson['id'] ?>" <?= isset($completed[(int) $lesson['id']]) ? 'checked' : '' ?>>
<label for="lesson-<?= (int) $lesson['id'] ?>" class="lesson-nav__item-label">
<span class="lesson-nav__badge"><?= LearningProgramme::lessonTypeIcon($lesson['type']) ?></span>
<span class="lesson-nav__item-title"><?= e($lesson['title']) ?></span>
<span class="lesson-nav__item-type"><?= LearningProgramme::lessonTypeLabel($lesson['type']) ?></span>
</label>
<?php if (preg_match('/^https?:\/\//i', $lessonUrl)): ?>
<a class="lesson-nav__direct-link" href="<?= e($lessonUrl) ?>" target="_blank" rel="noopener" aria-label="Open <?= e($lesson['title']) ?> resource">
<i class="fas fa-external-link-alt" aria-hidden="true"></i> Open link
</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<div class="course-main">
<!-- ===== ACTIVE LESSON CONTENT ===== -->
<section class="lesson-content">
<div class="lesson-content__header">
<h3 id="active-lesson-title">Open a lesson to begin</h3>
<span class="badge badge--muted" id="active-lesson-type">Select a lesson above</span>
</div>
<div class="lesson-content__body">
<p>Ready to start learning?</p>
<span>Select a lesson from the course curriculum to access its learning resources.</span>
<div class="lesson-resource" id="active-lesson-resource" hidden></div>
</div>
</section>

<!-- ===== LESSON COMPLETE ACTION ===== -->
<form method="post" action="<?= url('candidate/learning_api.php') ?>" class="lesson-complete-form" data-course-id="<?= $assignment['assignment_id'] ?>" data-lesson-id="0">
<?= csrf_field() ?>
<input type="hidden" name="action" value="mark_lesson_complete">
<input type="hidden" name="assignment_id" value="<?= $assignment['assignment_id'] ?>">
<input type="hidden" name="lesson_id" value="0">
<button type="submit" class="btn btn--primary" id="markLessonBtn" disabled><i class="fas fa-check"></i> Mark lesson complete</button>
</form>
</div>
</div>
 </div>
</main>
</div>

<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/learning.js') ?>"></script>
</body>
</html>
