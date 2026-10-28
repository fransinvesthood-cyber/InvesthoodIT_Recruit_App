<?php
/**
 * Skills Development
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

$userId = (int) current_user_id();

// ---- Self-reported skills management ----
$selfReported = Skill::forUser($userId);
$verifiedCount = Skill::countVerifiedForUser($userId);

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $skillId = isset($_POST['skill_id']) ? (int) $_POST['skill_id'] : 0;
        $proficiency = trim($_POST['proficiency'] ?? '');
        if ($skillId <= 0) {
            $error = 'Select a skill.';
        } elseif (!in_array($proficiency, ['beginner', 'intermediate', 'advanced'], true)) {
            $error = 'Invalid proficiency level.';
        } else {
            if (Skill::addToUser($userId, $skillId, $proficiency)) {
                $success = 'Skill added.';

<!-- ===== FLASH MESSAGES ===== -->
<?php if ($error !== ''): ?>
<div class="notification notification--error">
<div class="notification__icon"><i class="fas fa-exclamation-circle"></i></div>
<div class="notification__content"><div class="notification__title">Error</div><div class="notification__message"><?= e($error) ?></div></div>
</div>
<?php endif; ?>
<?php if ($success !== ''): ?>
<div class="notification notification--success">
<div class="notification__icon"><i class="fas fa-check-circle"></i></div>
<div class="notification__content"><div class="notification__title">Success</div><div class="notification__message"><?= e($success) ?></div></div>
</div>
<?php endif; ?>

<!-- ===== SELF-REPORTED SKILLS FORM ===== -->
<section class="skills-section">
<div class="skills-section__card">
<div class="skills-section__header">
<h3>Add a self-reported skill</h3>
<p>Add skills you have developed or want to learn. Note: adding a self-reported skill does not automatically verify or award it.</p>
</div>
<form method="post" class="skills-form">
<?php echo csrf_field(); ?>
<div class="skills-form__row">
<div class="skills-form__field">
<label for="skill">Skill</label>
<select id="skill" name="skill_id">
<option value="">-- Choose a skill --</option>
<?php foreach (Skill::allByCategory() as $cat => $list): ?>
<optgroup label="<?= e(ucfirst($cat)) ?>">
<?php foreach ($list as $s): ?>
<option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
<?php endforeach; ?>
</optgroup>
<?php endforeach; ?>
</select>
</div>
<div class="skills-form__field">
<label for="proficiency">Proficiency level</label>
<select id="proficiency" name="proficiency">
<option value="">-- Select --</option>
<option value="beginner">Beginner</option>
<option value="intermediate">Intermediate</option>
<option value="advanced">Advanced</option>
</select>
</div>
</div>
<button type="submit" class="btn btn--primary"><i class="fas fa-plus"></i> Add skill</button>
</form>
</section>

<!-- ===== MY SKILLS ===== -->
<section class="skills-section">
<div class="skills-section__card">
<div class="skills-section__header">
<h3>My skills</h3>
<span class="badge badge--success"><?= $verifiedCount ?> verified</span>
</div>
<?php if (empty($selfReported)): ?>
<p class="empty-state__text">You haven't added any skills yet.</p>
<?php else: ?>
<div class="skills-grid">
<?php foreach ($selfReported as $row): ?>
<?php
$skill = Skill::find((int) $row['skill_id']);
$isVerified = (string) ($row['verification_status'] ?? '') === 'verified';
?>
<div class="skill-card">
<div class="skill-card__icon"><i class="fas fa-<?= $isVerified ? 'check-circle' : 'star' ?>"></i></div>
<div class="skill-card__body">
<span class="skill-card__name"><?= e($skill['name'] ?? 'Unknown') ?></span>
<span class="skill-card__meta"><?= e(ucfirst($row['proficiency'] ?? 'intermediate')) ?></span>
</div>
<div class="skill-card__badge"><?= $isVerified ? '<span class="badge badge--success">Verified</span>' : '<span class="badge badge--secondary">Self-reported</span>' ?></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<!-- ===== SKILLS TARGETED BY PROGRAMMES ===== -->
<section class="skills-section">
<div class="skills-section__card">
<div class="skills-section__header">
<h3>Skills targeted by your training</h3>
<p>Skills gained through completed programmes are tracked here automatically.</p>
</div>
<?php
$progSkills = [];
foreach ($assignments as $a) {
    // Skills for each assigned programme are not pre-loaded; show programme-level summary instead.
}
?>
<p class="empty-state__text">Skills gained from completed courses are listed in the Certifications section.</p>
</section>

<!-- ===== SKILLS CATALOG ===== -->
<section class="skills-section">
<div class="skills-section__card">
<div class="skills-section__header">
<h3>Skills catalogue</h3>
<p>Browse skills to see what is available to add.</p>
</div>
<div class="skills-grid skills-grid--catalog">
<?php
$all = Skill::allByCategory();
foreach ($all['technical'] as $s) {
    echo '<div class="skill-card"><div class="skill-card__icon"><i class="fas fa-code"></i></div><div class="skill-card__body"><span class="skill-card__name">' . e($s['name']) . '</span></div></div>';
}
foreach ($all['soft'] as $s) {
    echo '<div class="skill-card"><div class="skill-card__icon"><i class="fas fa-lightbulb"></i></div><div class="skill-card__body"><span class="skill-card__name">' . e($s['name']) . '</span></div></div>';
}
?>
</div>
</section>
</div>
</main>
</div>

<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/learning.js') ?>"></script>
</body>
</html>

                $selfReported = Skill::forUser($userId);
            } else {
                $error = 'Skill already added.';
            }
        }
    }
}

// ---- Toggle verified status for self-reported skill (admin-only) ----
$skillToggle = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_skill']) && current_role() === 'admin') {
    require_csrf();
    $skillId = isset($_POST['skill_id']) ? (int) $_POST['skill_id'] : 0;
    if ($skillId > 0) {
        $row = Skill::find($skillId);
        if ($row) {
            $ss = Skill::findForUser($userId);
            $exists = false;
            foreach ($ss as $r) {
                if ((int) $r['skill_id'] === $skillId) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                Skill::removeFromUser($userId, $skillId);
                $success = 'Self-reported skill removed.';
            } else {
                Skill::addToUser($userId, $skillId, 'intermediate');
                $success = 'Skill added.';
            }
            $selfReported = Skill::forUser($userId);
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
<h1 class="page-header__title">Skills Development</h1>
<p class="page-header__subtitle">View your skills, track development, and add self-reported skills where permitted.</p>
</div>
</header>
<div class="container">
