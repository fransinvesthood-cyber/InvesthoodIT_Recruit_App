<?php
/**
 * Certifications & Achievements
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId = (int) current_user_id();

$certificationsView = CandidateLearningController::certificationsView($userId);
$certifications = $certificationsView['certificates'];

$completedAssignments = [];
$stats = CandidateLearningController::index($userId);
foreach ($stats['assignments'] as $a) {
    if ($a['computed_status'] === 'completed') {
        $completedAssignments[] = $a;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certifications &amp; Achievements | Investhood IT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/applications.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page certifications-page">
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
<li><a href="<?= url('candidate/certifications.php') ?>" class="sidebar__link active"><i class="fas fa-certificate"></i> Certifications</a></li>
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
<main class="dash-content">
<header class="page-header">
<div class="container">
<h1 class="page-header__title">Certifications &amp; Achievements</h1>
<p class="page-header__subtitle">Certificates earned through completed training, with expiry information.</p>
</div>
</header>
<div class="container">
<section class="learning-overview">
<div class="progress-card">
<div class="progress-card__header"><h3>Certifications earned</h3><span class="progress-card__value"><?= count($certifications) ?></span></div>
<div class="progress-card__stats">
<div class="progress-stat"><span class="progress-stat__value"><?= count($completedAssignments) ?></span><span class="progress-stat__label">Completed courses</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= count(array_filter($certifications, fn($c) => !$c['expired'])) ?></span><span class="progress-stat__label">Currently valid</span></div>
<div class="progress-stat"><span class="progress-stat__value"><?= count(array_filter($certifications, fn($c) => $c['expired'])) ?></span><span class="progress-stat__label">Expired</span></div>
</div>
</div>

<!-- ===== CERTIFICATIONS ===== -->
<?php if (empty($certifications)): ?>
<section class="skills-section">
<div class="skills-section__card">
<div class="skills-section__header"><h3>No certificates yet</h3></div>
<p class="empty-state__text">Complete a learning programme to earn certificates for your achievements.</p>
</div>
</section>
<?php else: ?>
<section class="certifications-grid">
<?php foreach ($certifications as $c): ?>
<article class="cert-card <?= $c['expired'] ? 'cert-card--expired' : 'cert-card--valid' ?>">
<div class="cert-card__header">
<span class="badge badge--<?= $c['expired'] ? 'danger' : 'success' ?>"><?= $c['expired'] ? 'Expired' : 'Valid' ?></span>
<h3 class="cert-card__title"><?= e($c['name'] ?? 'Certificate') ?></h3>
</div>
<p class="cert-card__organisation"><i class="fas fa-building"></i> <?= e($c['issuing_organisation'] ?? '—') ?></p>
<div class="cert-card__meta">
<span class="cert-card__date"><i class="fas fa-calendar-check"></i> Issued <?= e(format_date($c['year_obtained'] ?? null, 'M Y')) ?></span>
<?php if (!empty($c['expiry_date'])): ?>
<span class="cert-card__date"><i class="fas fa-calendar-times"></i> Expires <?= e(format_date($c['expiry_date'], 'M Y')) ?></span>
<?php endif; ?>
</div>
<div class="cert-card__actions">
<?php if (!empty($c['credential_id'])): ?>
<a href="<?= e($c['credential_id']) ?>" class="btn btn--primary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> View digital credential</a>
<?php endif; ?>
</div>
</article>
<?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ===== ACHIEVEMENTS ===== -->
<section class="certifications-section">
<div class="certifications-section__card">
<div class="certifications-section__header"><h3>Completion history</h3></div>
<?php if (empty($completedAssignments)): ?>
<p class="empty-state__text">No completed courses yet.</p>
<?php else: ?>
<div class="certifications-timeline">
<?php foreach ($completedAssignments as $a): ?>
<div class="certifications-timeline__item">
<div class="certifications-timeline__marker"><i class="fas fa-check-circle"></i></div>
<div class="certifications-timeline__content">
<span class="certifications-timeline__title"><?= e($a['title']) ?></span>
<span class="certifications-timeline__meta">Completed <?= e(format_date($a['completed_at'] ?? $a['assigned_at'], 'M Y')) ?></span>
<span class="certifications-timeline__meta"><?= $a['progress'] ?>% complete</span>
</div>
</div>
<?php endforeach; ?>
</div>
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

</section>
