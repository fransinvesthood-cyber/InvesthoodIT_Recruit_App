<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Officer Dashboard
 * ================================================
 * Role: Programme Officer
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('programme_officer');

$user = current_user();
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Programme Officer Dashboard | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body class="dashboard-page">
  <div class="dashboard">
    <aside class="sidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Programme Officer</div>
        <ul class="sidebar__menu">
          <li><a href="#" class="sidebar__link active"><i class="fas fa-th-large"></i> Dashboard</a></li>
          <li><a href="#" class="sidebar__link"><i class="fas fa-tasks"></i> Tasks</a></li>
          <li><a href="#" class="sidebar__link"><i class="fas fa-users"></i> Candidate Support</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'PO+User') ?>&background=1a56db&color=fff&size=80" alt=""></div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Programme Officer') ?></span>
            <span class="sidebar__user-role">Programme Officer</span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <main class="dashboard__main">
      <header class="dash-header">
        <div class="dash-header__left">
          <h1>Programme Officer Dashboard</h1>
        </div>
        <div class="dash-header__right">
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'PO+User') ?>&background=1a56db&color=fff&size=80" alt="" class="dash-header__avatar">
          </div>
        </div>
      </header>
      <div class="dash-content">
        <div class="welcome-card">
          <div class="welcome-card__bg"></div>
          <div class="welcome-card__content">
            <h1 class="welcome-card__greeting">Welcome, <span class="text-gradient"><?= e($user['fullname'] ?? 'Programme Officer') ?></span></h1>
            <p>Track programme activities, support candidates, and manage operational tasks.</p>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
