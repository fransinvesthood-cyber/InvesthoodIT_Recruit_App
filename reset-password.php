<?php
/**
 * ================================================
 * INVESTHOOD IT - Reset Password Page
 * ================================================
 * Form to set a new password using a valid reset token.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_guest();

$token = $_GET['token'] ?? '';
if (empty($token)) {
    set_flash('error', 'Invalid Link', 'The password reset link is invalid or missing.');
    safe_redirect('forgot-password.php');
}

$errors = $_SESSION['_form_errors'] ?? [];
$old    = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Reset Password - Investhood IT">
  <title>Reset Password | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body class="auth-page">

  <main class="auth-main">
    <div class="auth-card" style="max-width:480px;">
      <div class="auth-card__inner">
        <div class="auth-card__header">
          <div class="auth-hero__icon" style="margin:0 auto 1.25rem;width:64px;height:64px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(16,185,129,0.12);color:#10b981;">
            <i class="fas fa-lock" style="font-size:1.5rem;"></i>
          </div>
          <h2 class="auth-card__title">Set New Password</h2>
          <p class="auth-card__subtitle">Enter your new password below. Must be at least 8 characters with uppercase, lowercase, number, and special character.</p>
        </div>

        <form class="form" method="POST" action="<?= url('auth/reset_password.php') ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">

          <div class="form-group">
            <label for="newPassword" class="form-label">
              New Password <span class="form-label__required">*</span>
            </label>
            <div class="password-input-wrapper">
              <input type="password" id="newPassword" name="password" class="form-input" placeholder="Enter new password" required autocomplete="new-password" minlength="8" />
              <button type="button" class="password-toggle" aria-label="Toggle password visibility" tabindex="-1"><i class="fas fa-eye"></i></button>
            </div>
            <div class="password-strength">
              <div class="password-strength__bar">
                <span class="password-strength__segment"></span>
                <span class="password-strength__segment"></span>
                <span class="password-strength__segment"></span>
                <span class="password-strength__segment"></span>
                <span class="password-strength__segment"></span>
              </div>
              <span class="password-strength__text"></span>
            </div>
            <?= field_error($errors, 'password') ?>
          </div>

          <div class="form-group">
            <label for="confirmNewPassword" class="form-label">
              Confirm New Password <span class="form-label__required">*</span>
            </label>
            <div class="password-input-wrapper">
              <input type="password" id="confirmNewPassword" name="confirm_password" class="form-input" placeholder="Confirm new password" required autocomplete="new-password" />
              <button type="button" class="password-toggle" aria-label="Toggle password visibility" tabindex="-1"><i class="fas fa-eye"></i></button>
            </div>
            <?= field_error($errors, 'confirm_password') ?>
          </div>

          <button type="submit" class="btn btn--primary btn--full">
            <span class="btn__text">Reset Password</span>
          </button>

          <div class="auth-footer">
            <div class="auth-nav">
              <a href="<?= url('login.php') ?>" class="auth-nav__link"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
              <a href="<?= url('index.php') ?>" class="auth-nav__link">Home <i class="fas fa-arrow-right"></i></a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </main>

  <?= render_flashes() ?>
  <script src="<?= url('js/script.js') ?>"></script>
</body>
</html>
