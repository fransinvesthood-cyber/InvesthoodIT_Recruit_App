<?php
/**
 * ================================================
 * INVESTHOOD IT - Email Verification Confirmation
 * ================================================
 * Displays the outcome of the email verification
 * attempt (success or failure) from flash messages.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_guest();

$flashes = get_flashes();
$success = false;
foreach ($flashes as $flash) {
    if ($flash['type'] === 'success') {
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Email Verification - Investhood IT">
  <title>Email Verification | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body class="auth-page">
  <main class="auth-main">
    <div class="auth-card">
      <div class="auth-card__inner">
        <div class="auth-card__header">
          <div class="auth-hero__icon" style="margin:0 auto 1.25rem;width:80px;height:80px;display:flex;align-items:center;justify-content:center;border-radius:50%;<?= $success ? 'background:rgba(16,185,129,0.12);color:#10b981;' : 'background:rgba(239,68,68,0.12);color:#ef4444;' ?>">
            <i class="fas <?= $success ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:2rem;"></i>
          </div>
          <h2 class="auth-card__title"><?= $success ? 'Email Verified!' : 'Verification Failed' ?></h2>
          <p class="auth-card__subtitle">
            <?= $success
                ? 'Your email address has been successfully verified. You can now sign in to your account.'
                : 'We could not verify your email address. The link may be invalid or expired.' ?>
          </p>
        </div>

        <div style="display:flex;flex-direction:column;gap:0.75rem;">
          <a href="<?= url('login.php') ?>" class="btn btn--primary btn--full">Proceed to Sign In</a>
          <?php if (!$success): ?>
            <a href="<?= url('auth/resend_verification.php') ?>" class="btn btn--outline btn--full">Resend Verification Email</a>
          <?php endif; ?>
        </div>

        <div class="auth-footer">
          <div class="auth-nav">
            <a href="<?= url('index.php') ?>" class="auth-nav__link"><i class="fas fa-arrow-left"></i> Back to Home</a>
            <a href="<?= url('register.php') ?>" class="auth-nav__link">Create Account <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php
  // Re-render consumed flashes
  foreach ($flashes as $flash) {
      $icons = ['success' => 'fa-check-circle', 'error' => 'fa-exclamation-circle', 'warning' => 'fa-exclamation-triangle', 'info' => 'fa-info-circle'];
      $icon = $icons[$flash['type']] ?? $icons['info'];
      echo '<div class="notification-container">';
      echo '<div class="notification notification--' . $flash['type'] . '">';
      echo '<div class="notification__icon"><i class="fas ' . $icon . '"></i></div>';
      echo '<div class="notification__content"><div class="notification__title">' . e($flash['title']) . '</div><div class="notification__message">' . e($flash['message']) . '</div></div>';
      echo '</div></div>';
  }
  ?>

  <script src="<?= url('js/script.js') ?>"></script>
</body>
</html>

