<?php
/**
 * ================================================
 * INVESTHOOD IT - Forgot Password Processor
 * ================================================
 * Sends a password reset link to the user's email
 * if the account exists (neutral response to avoid
 * user enumeration).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_guest();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $email = $_POST['email'] ?? '';
    $result = AuthController::forgotPassword($email);

    if ($result['success']) {
        set_flash('success', 'Reset Link Sent', $result['message']);
    } else {
        set_flash('error', 'Request Failed', $result['message']);
    }

    safe_redirect('forgot-password.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Forgot Password - Investhood IT">
  <title>Forgot Password | Investhood IT</title>
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
            <i class="fas fa-key"></i>
          </div>
          <h2 class="auth-card__title">Forgot Your Password?</h2>
          <p class="auth-card__subtitle">No problem. Enter your email address and we'll send you a link to reset your password.</p>
        </div>

        <form class="form" method="POST" action="<?= url('auth/forgot_password.php') ?>">
          <?= csrf_field() ?>

          <div class="form-group">
            <label for="email" class="form-label">Email Address <span class="form-label__required">*</span></label>
            <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email" inputmode="email">
          </div>

          <button type="submit" class="btn btn--primary btn--full">Send Reset Link</button>

          <div class="auth-footer">
            <p class="auth-footer__text">
              Remembered your password?
              <a href="<?= url('login.php') ?>" class="auth-footer__link">Sign in here</a>
            </p>
            <div class="auth-nav">
              <a href="<?= url('index.php') ?>" class="auth-nav__link"><i class="fas fa-arrow-left"></i> Back to Home</a>
              <a href="<?= url('register.php') ?>" class="auth-nav__link">Register <i class="fas fa-arrow-right"></i></a>
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

