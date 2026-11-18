<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$conn = Database::getConnection();
$currentPage = 'profile';
$pageTitle = 'My Profile';
$flashes = render_flashes();
$firstName = trim((string)($user['first_name'] ?? ''));
$lastName = trim((string)($user['last_name'] ?? ''));
$fullName = trim((string)($user['full_name'] ?? ($firstName . ' ' . $lastName)));
$initials = pm_initials($firstName, $lastName, $fullName);
$email = (string)($user['email'] ?? 'Not available');
$phone = (string)($user['phone'] ?? 'Not provided');
$username = (string)($user['username'] ?? 'Not available');
$status = (string)($user['status'] ?? 'active');
$isActive = strtolower($status) === 'active';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile | Programme Manager</title>
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/programme_manager_enhancements.css') ?>?v=20261118b">
<link rel="stylesheet" href="<?= url('css/unified_portal.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= url('css/pm_profile.css') ?>?v=20261118b">
</head>
<body class="dashboard-page">
<div class="dashboard">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="dashboard__main">
        <?php require __DIR__ . '/navbar.php'; ?>
        <div class="pm-profile-page">
            <?= $flashes ?>

            <section class="pm-profile-hero" aria-labelledby="pmProfileName">
                <div class="pm-profile-hero__topline">
                    <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Account overview</span>
                    <span class="pm-profile-status <?= $isActive ? 'is-active' : 'is-other' ?>">
                        <i class="fa-solid fa-circle" aria-hidden="true"></i><?= e(ucfirst($status)) ?>
                    </span>
                </div>
                <div class="pm-profile-identity">
                    <div class="pm-profile-avatar" aria-label="Profile initials <?= e($initials) ?>">
                        <?= e($initials) ?><span class="pm-profile-avatar__online" aria-hidden="true"></span>
                    </div>
                    <div class="pm-profile-identity__copy">
                        <p class="pm-profile-kicker">Programme Manager</p>
                        <h2 id="pmProfileName"><?= e($fullName ?: 'Programme Manager') ?></h2>
                        <p class="pm-profile-email"><i class="fa-regular fa-envelope" aria-hidden="true"></i><?= e($email) ?></p>
                    </div>
                </div>
                <div class="pm-profile-hero__foot">
                    <span><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Your account information</span>
                    <span>Investhood IT Workspace</span>
                </div>
            </section>

            <div class="pm-profile-section-heading">
                <div>
                    <span class="pm-profile-eyebrow">Personal workspace</span>
                    <h3>Account information</h3>
                    <p>Review the details associated with your Programme Manager account.</p>
                </div>
                <span class="pm-profile-section-icon" aria-hidden="true"><i class="fa-regular fa-id-card"></i></span>
            </div>

            <section class="pm-profile-info-grid" aria-label="Account information">
                <article class="pm-profile-info-card">
                    <span class="pm-profile-info-card__icon is-blue" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                    <div><small>Full name</small><strong><?= e($fullName ?: 'Not available') ?></strong><span>Account holder</span></div>
                </article>
                <article class="pm-profile-info-card">
                    <span class="pm-profile-info-card__icon is-cyan" aria-hidden="true"><i class="fa-solid fa-at"></i></span>
                    <div><small>Email address</small><strong><?= e($email) ?></strong><span>Primary email</span></div>
                </article>
                <article class="pm-profile-info-card">
                    <span class="pm-profile-info-card__icon is-violet" aria-hidden="true"><i class="fa-solid fa-fingerprint"></i></span>
                    <div><small>Username</small><strong><?= e($username) ?></strong><span>Sign-in identity</span></div>
                </article>
                <article class="pm-profile-info-card">
                    <span class="pm-profile-info-card__icon is-teal" aria-hidden="true"><i class="fa-solid fa-phone"></i></span>
                    <div><small>Phone number</small><strong><?= e($phone) ?></strong><span>Contact information</span></div>
                </article>
                <article class="pm-profile-info-card">
                    <span class="pm-profile-info-card__icon is-amber" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
                    <div><small>Account role</small><strong>Programme Manager</strong><span>Portal access level</span></div>
                </article>
                <article class="pm-profile-info-card">
                    <span class="pm-profile-info-card__icon is-green" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                    <div><small>Account status</small><strong><?= e(ucfirst($status)) ?></strong><span>Current account state</span></div>
                </article>
            </section>

            <section class="pm-profile-actions-card">
                <div class="pm-profile-actions-copy">
                    <span class="pm-profile-eyebrow">Quick actions</span>
                    <h3>Manage your session</h3>
                    <p>Return to your workspace or securely sign out of your account.</p>
                </div>
                <div class="pm-profile-actions">
                    <a class="pm-profile-action pm-profile-action--primary" href="<?= url('programme/dashboard.php') ?>">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard
                    </a>
                    <a class="pm-profile-action pm-profile-action--danger" href="<?= url('auth/logout.php') ?>">
                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Sign out
                    </a>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="<?= url('js/programme_manager_enhancements.js') ?>?v=20261118b"></script>
<script src="<?= url('js/original_pm_sidebar.js') ?>?v=20261118b"></script>
</body>
</html>
