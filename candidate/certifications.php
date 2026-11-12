<?php
/**
 * Certifications & Achievements
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId = (int) current_user_id();
$user = current_user();

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
  <link rel="stylesheet" href="<?= url('candidate/theme.css') ?>?v=20261112-cert-layout1">
<style>
/* Certifications page layout: keep content clear of the fixed candidate sidebar. */
.certifications-page .dashboard { min-height:100vh; }
.certifications-page .dash-content {
  display:block; min-width:0; width:calc(100% - 260px); margin-left:260px;
  padding:0 2rem 3rem; box-sizing:border-box;
}
.certifications-page .dash-content > .container {
  width:100%; max-width:1180px; margin:0 auto; padding:0;
  box-sizing:border-box;
}
.certifications-page .page-header { margin:0 -2rem 1.75rem; padding:1.75rem 2rem; }
.certifications-page .learning-overview { display:block; margin:0 0 1.5rem; }
.certifications-page .progress-card,
.certifications-page .certifications-section__card,
.certifications-page .skills-section__card {
  width:100%; padding:1.4rem; box-sizing:border-box; border:1px solid #e2e8f0;
  border-radius:14px; background:#fff; box-shadow:0 5px 18px rgba(15,23,42,.05);
}
.certifications-page .progress-card__header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.1rem; }
.certifications-page .progress-card__stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.85rem; }
.certifications-page .progress-stat { display:flex; flex-direction:column; gap:.3rem; min-width:0; padding:1rem; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
.certifications-page .certifications-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr)); gap:1rem; margin:0 0 1.5rem; }
.certifications-page .cert-card { min-width:0; padding:1.25rem; border:1px solid #e2e8f0; border-radius:14px; background:#fff; box-shadow:0 5px 18px rgba(15,23,42,.05); overflow-wrap:anywhere; }
.certifications-page .cert-card__header { display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:.75rem; margin-bottom:1rem; }
.certifications-page .cert-card__title { flex:1 1 100%; margin:.15rem 0 0; font-size:1.1rem; line-height:1.35; }
.certifications-page .cert-card__organisation,
.certifications-page .cert-card__meta { display:flex; flex-wrap:wrap; gap:.65rem 1rem; color:#64748b; }
.certifications-page .cert-card__meta { margin-top:.8rem; font-size:.85rem; }
.certifications-page .cert-card__actions { display:flex; flex-wrap:wrap; margin-top:1rem; }
.certifications-page .certifications-section { margin-top:1.5rem; }
.certifications-page .certifications-section__header { margin-bottom:1rem; }
.certifications-page .certifications-timeline { display:grid; gap:.85rem; }
.certifications-page .certifications-timeline__item { display:flex; gap:.9rem; padding:.9rem; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
.certifications-page .certifications-timeline__content { display:flex; flex-direction:column; gap:.25rem; min-width:0; }
.certifications-page .certifications-timeline__title { font-weight:700; overflow-wrap:anywhere; }
.certifications-page .certifications-timeline__meta { color:#64748b; font-size:.85rem; }
body.dark-mode.certifications-page .progress-card,
body.dark-mode.certifications-page .cert-card,
body.dark-mode.certifications-page .certifications-section__card,
body.dark-mode.certifications-page .skills-section__card,
body[data-theme="dark"].certifications-page .progress-card,
body[data-theme="dark"].certifications-page .cert-card,
body[data-theme="dark"].certifications-page .certifications-section__card,
body[data-theme="dark"].certifications-page .skills-section__card { background:#111c2e; color:#e5edf8; border-color:#2b3b53; }
body.dark-mode.certifications-page .progress-stat,
body.dark-mode.certifications-page .certifications-timeline__item,
body[data-theme="dark"].certifications-page .progress-stat,
body[data-theme="dark"].certifications-page .certifications-timeline__item { background:#17263b; border-color:#344761; }
body.dark-mode.certifications-page .cert-card__organisation,
body.dark-mode.certifications-page .cert-card__meta,
body.dark-mode.certifications-page .certifications-timeline__meta,
body[data-theme="dark"].certifications-page .cert-card__organisation,
body[data-theme="dark"].certifications-page .cert-card__meta,
body[data-theme="dark"].certifications-page .certifications-timeline__meta { color:#aebdd1; }
@media(max-width:900px) {
  .certifications-page .dash-content { width:100%; margin-left:0; padding:0 1rem 2rem; }
  .certifications-page .page-header { margin:0 -1rem 1.25rem; padding:1.4rem 1rem; }
  .certifications-page .progress-card__stats { grid-template-columns:1fr; }
}
</style>
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
<button type="button" class="dash-header__toggle candidate-sidebar-trigger" id="sidebarToggle" aria-label="Show or hide menu" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
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
</section>

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
  <script src="<?= url('candidate/theme.js') ?>?v=20261112-sidebar1" defer></script>
</body>
</html>

</section>
