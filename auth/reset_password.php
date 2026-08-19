<?php
/**
 * ================================================
 * INVESTHOOD IT - Password Reset Processor
 * ================================================
 * Displays the reset form for a valid token and
 * processes the new password submission. Used
 * tokens are deleted; all sessions & remember-me
 * tokens for the user are revoked after reset.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_guest();

$token = $_GET['token'] ?? ($_POST['token'] ?? '');

// Validate the token before showing the form
$record = PasswordReset::findValid(Token::hash($token));
$tokenInvalid = ($token === '' || !$record);

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $result = AuthController::resetPassword(
        $_POST['token'] ?? '',
        $_POST['password'] ?? '',
        $_POST['confirm_password'] ?? ''
    );

    if ($result['success']) {
        set_flash('success', 'Password Reset Successful', $result['message']);
        safe_redirect('login.php');
    }

    set_flash('error', 'Password Reset Failed', $result['message']);
    if (!empty($result['errors'])) {
        $_SESSION['_form_errors'] = $result['errors'];
    }
    safe_redirect('reset-password.php?token=' . urlencode($token));
}

// If token invalid, show error state
if ($tokenInvalid) {
    set_flash('error', 'Invalid Reset Link', 'This password reset link is invalid or has expired. Please request a new one.');
    safe_redirect('forgot-password.php');
}

$errors = $_SESSION['_form_errors'] ?? [];
unset($_SESSION['_form_errors']);
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
    <div class="auth-card">
      <div class="auth-card__inner">
        <div class="auth-card__header">
          <div class="auth-hero__icon" style="margin:0 auto 1rem;width:64px;height:64px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-lock"></i>
          </div>
          <h2 class="auth-card__title">Set a New Password</h2>
          <p class="auth-card__subtitle">Choose a strong, secure password for your account.</p>
        </div>

        <form class="form" method="POST" action="<?= url('auth/reset_password.php') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">

          <div class="form-group">
            <label for="password" class="form-label">New Password <span class="form-label__required">*</span></label>
            <div class="password-input-wrapper">
              <input type="password" id="password" name="password" class="form-input" placeholder="Create a strong password" required autocomplete="new-password" minlength="8">
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
            <label for="confirm_password" class="form-label">Confirm New Password <span class="form-label__required">*</span></label>
            <div class="password-input-wrapper">
              <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm your new password" required autocomplete="new-password">
              <button type="button" class="password-toggle" aria-label="Toggle password visibility" tabindex="-1"><i class="fas fa-eye"></i></button>
            </div>
            <?= field_error($errors, 'confirm_password') ?>
          </div>

          <div class="form-options">
            <p class="auth-card__subtitle" style="font-size:0.8rem;text-align:left;">
              <i class="fas fa-info-circle"></i> Must be at least 8 characters with uppercase, lowercase, a number, and a special character.
            </p>
          </div>

          <button type="submit" class="btn btn--primary btn--full">Reset Password</button>

          <div class="auth-footer">
            <div class="auth-nav">
              <a href="<?= url('login.php') ?>" class="auth-nav__link"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
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

