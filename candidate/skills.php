<?php
/**
 * Candidate Skills Development
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId = (int) current_user_id();
$user = current_user();

$selfReported = Skill::forUser($userId);
$verifiedCount = (int) Skill::countVerifiedForUser($userId);
$catalogue = Skill::allByCategory();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_skill') {
    if (!validate_csrf()) {
        $error = 'Your session security token has expired. Please refresh and try again.';
    } else {
        $skillId = (int) ($_POST['skill_id'] ?? 0);
        $proficiency = trim((string) ($_POST['proficiency'] ?? ''));

        if ($skillId <= 0) {
            $error = 'Please select a skill.';
        } elseif (!in_array($proficiency, ['beginner', 'intermediate', 'advanced'], true)) {
            $error = 'Please select a valid proficiency level.';
        } else {
            if (Skill::addToUser($userId, $skillId, $proficiency)) {
                $success = 'Skill added successfully.';
            } else {
                $error = 'This skill may already be in your profile.';
            }
            $selfReported = Skill::forUser($userId);
            $verifiedCount = (int) Skill::countVerifiedForUser($userId);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Skills Development | Investhood IT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
<link rel="stylesheet" href="<?= url('candidate/theme.css') ?>?v=20261112-profilemenu1">
<style>
.skills-page .dash-content { min-width:0; width:calc(100% - 260px); margin-left:260px; box-sizing:border-box; }
.skills-page .dash-content > .container { width:100%; max-width:1200px; margin:0 auto; padding:1.5rem 2rem 3rem; box-sizing:border-box; }
.skills-page .skills-section { margin:0 0 1.5rem; }
.skills-page .skills-section__card { padding:1.35rem; border:1px solid #e2e8f0; border-radius:14px; background:#fff; box-shadow:0 5px 18px rgba(15,23,42,.05); }
.skills-page .skills-section__header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
.skills-page .skills-section__header h3 { margin:0 0 .35rem; font-size:1.05rem; font-weight:750; }
.skills-page .skills-section__header p { margin:0; color:#64748b; line-height:1.55; }
.skills-page .skills-form__row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; margin-bottom:1rem; }
.skills-page .skills-form__field { display:flex; flex-direction:column; gap:.4rem; min-width:0; }
.skills-page .skills-form__field label { font-weight:650; }
.skills-page .skills-form__field select { width:100%; min-height:44px; padding:.65rem .8rem; border:1px solid #cbd5e1; border-radius:9px; background:#fff; color:#1e293b; }
.skills-page .skills-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:.8rem; }
.skills-page .skill-card { display:flex; align-items:center; gap:.8rem; min-width:0; padding:.9rem; border:1px solid #e2e8f0; border-radius:11px; background:#f8fafc; }
.skills-page .skill-card__body { display:flex; flex:1; flex-direction:column; min-width:0; gap:.2rem; }
.skills-page .skill-card__name { font-weight:700; overflow-wrap:anywhere; }
.skills-page .skill-card__meta { color:#64748b; font-size:.82rem; }
.skills-page .skill-card__icon { color:#2563eb; font-size:1.1rem; }
.skills-page .skill-card__badge { margin-left:auto; }
.skills-page .empty-state__text { margin:0; color:#64748b; }
.skills-page .notification { margin:0 0 1rem; }
body.dark-mode.skills-page .skills-section__card,
body[data-theme="dark"].skills-page .skills-section__card { background:#111c2e; border-color:#2b3b53; color:#e5edf8; }
body.dark-mode.skills-page .skills-section__header p,
body.dark-mode.skills-page .skill-card__meta,
body.dark-mode.skills-page .empty-state__text,
body[data-theme="dark"].skills-page .skills-section__header p,
body[data-theme="dark"].skills-page .skill-card__meta,
body[data-theme="dark"].skills-page .empty-state__text { color:#aebdd1; }
body.dark-mode.skills-page .skill-card,
body[data-theme="dark"].skills-page .skill-card { background:#17263b; border-color:#344761; }
body.dark-mode.skills-page .skills-form__field select,
body[data-theme="dark"].skills-page .skills-form__field select { background:#0d1727; color:#e5edf8; border-color:#40536d; }
@media(max-width:900px) {
  .skills-page .dash-content { width:100%; margin-left:0; }
  .skills-page .dash-content > .container { padding:1rem 1rem 2rem; }
}
@media(max-width:620px) { .skills-page .skills-form__row { grid-template-columns:1fr; } }
</style>
</head>
<body class="dashboard-page skills-page">
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
      <li><a href="<?= url('candidate/skills.php') ?>" class="sidebar__link active"><i class="fas fa-star"></i> Skills</a></li>
      <li><a href="<?= url('candidate/certifications.php') ?>" class="sidebar__link"><i class="fas fa-certificate"></i> Certifications</a></li>
      <li><a href="<?= url('candidate/notifications.php') ?>" class="sidebar__link"><i class="fas fa-bell"></i> Notifications</a></li>
      <li><a href="<?= url('candidate/profile.php') ?>" class="sidebar__link"><i class="fas fa-user"></i> My Profile</a></li>
      <li><a href="<?= url('candidate/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>
  </nav>
  <div class="sidebar__footer">
    <div class="sidebar__user">
      <div class="sidebar__user-avatar"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile"></div>
      <div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Candidate') ?></span><span class="sidebar__user-role">Candidate</span></div>
    </div>
    <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<button type="button" class="dash-header__toggle candidate-sidebar-trigger" id="sidebarToggle" aria-label="Show or hide menu" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
<main class="dash-content">
  <header class="page-header"><div class="container"><h1 class="page-header__title">Skills Development</h1><p class="page-header__subtitle">View your skills, track development, and add self-reported skills where permitted.</p></div></header>
  <div class="container">
    <?php if ($error !== ''): ?><div class="notification notification--error"><div class="notification__icon"><i class="fas fa-exclamation-circle"></i></div><div class="notification__content"><div class="notification__title">Error</div><div class="notification__message"><?= e($error) ?></div></div></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="notification notification--success"><div class="notification__icon"><i class="fas fa-check-circle"></i></div><div class="notification__content"><div class="notification__title">Success</div><div class="notification__message"><?= e($success) ?></div></div></div><?php endif; ?>

    <section class="skills-section">
      <div class="skills-section__card">
        <div class="skills-section__header"><div><h3>Add a self-reported skill</h3><p>Add skills you have developed or want to learn. Self-reported skills are not automatically verified.</p></div></div>
        <form method="post" class="skills-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="add_skill">
          <div class="skills-form__row">
            <div class="skills-form__field"><label for="skill">Skill</label><select id="skill" name="skill_id" required><option value="">-- Choose a skill --</option>
              <?php foreach ($catalogue as $category => $items): ?><optgroup label="<?= e(ucfirst((string)$category)) ?>"><?php foreach ($items as $item): ?><option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
            </select></div>
            <div class="skills-form__field"><label for="proficiency">Proficiency level</label><select id="proficiency" name="proficiency" required><option value="">-- Select --</option><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select></div>
          </div>
          <button type="submit" class="btn btn--primary"><i class="fas fa-plus"></i> Add skill</button>
        </form>
      </div>
    </section>

    <section class="skills-section">
      <div class="skills-section__card">
        <div class="skills-section__header"><div><h3>My skills</h3><p>Your verified and self-reported skills.</p></div><span class="badge badge--success"><?= $verifiedCount ?> verified</span></div>
        <?php if (!$selfReported): ?><p class="empty-state__text">You haven't added any skills yet.</p><?php else: ?>
          <div class="skills-grid"><?php foreach ($selfReported as $row): $skill = Skill::find((int)$row['skill_id']); $isVerified = (string)($row['verification_status'] ?? '') === 'verified'; ?>
            <article class="skill-card"><div class="skill-card__icon"><i class="fas fa-<?= $isVerified ? 'check-circle' : 'star' ?>"></i></div><div class="skill-card__body"><span class="skill-card__name"><?= e($skill['name'] ?? 'Unknown skill') ?></span><span class="skill-card__meta"><?= e(ucfirst((string)($row['proficiency'] ?? 'intermediate'))) ?></span></div><div class="skill-card__badge"><span class="badge badge--<?= $isVerified ? 'success' : 'secondary' ?>"><?= $isVerified ? 'Verified' : 'Self-reported' ?></span></div></article>
          <?php endforeach; ?></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="skills-section">
      <div class="skills-section__card"><div class="skills-section__header"><div><h3>Skills targeted by programmes</h3><p>Skills gained through completed programmes are tracked alongside your learning achievements.</p></div></div><p class="empty-state__text">Skills gained from completed courses are listed in the Certifications section.</p></div>
    </section>

    <section class="skills-section">
      <div class="skills-section__card"><div class="skills-section__header"><div><h3>Skills catalogue</h3><p>Browse the skills available to add to your profile.</p></div></div>
        <div class="skills-grid skills-grid--catalog">
          <?php foreach (['technical' => 'code', 'soft' => 'lightbulb'] as $group => $icon): foreach (($catalogue[$group] ?? []) as $item): ?>
            <article class="skill-card"><div class="skill-card__icon"><i class="fas fa-<?= $icon ?>"></i></div><div class="skill-card__body"><span class="skill-card__name"><?= e($item['name']) ?></span></div></article>
          <?php endforeach; endforeach; ?>
        </div>
      </div>
    </section>
  </div>
</main>
</div>
<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/learning.js') ?>"></script>
<script src="<?= url('candidate/theme.js') ?>?v=20261112-profilemenu1" defer></script>
</body>
</html>
