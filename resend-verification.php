<?php
/**
 * ================================================
 * INVESTHOOD IT - Resend Verification Email Page
 * ================================================
 * Allows users to request a new verification email.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_guest();

$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Resend Verification - Investhood IT">
  <title>Resend Verification | Investhood IT</title>
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
          <div class="auth-hero__icon" style="margin:0 auto 1.25rem;width:64px;height:64px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(26,86,219,0.12);color:#1a56db;">
            <i class="fas fa-envelope" style="font-size:1.5rem;"></i>
          </div>
          <h2 class="auth-card__title">Resend Verification Email</h2>
          <p class="auth-card__subtitle">Enter your registered email address and we'll send a new verification link.</p>
        </div>

        <form class="form" method="POST" action="<?= url('auth/resend_verification.php') ?>" novalidate>
          <?= csrf_field() ?>

          <div class="form-group">
            <label for="resendEmail" class="form-label">
              Email Address <span class="form-label__required">*</span>
            </label>
            <input
              type="email"
              id="resendEmail"
              name="email"
              class="form-input"
              placeholder="you@example.com"
              required
              autocomplete="email"
              inputmode="email"
              value="<?= e($old['email'] ?? '') ?>"
            />
            <div class="form-error" id="resendEmailError"></div>
          </div>

          <button type="submit" class="btn btn--primary btn--full">
            <span class="btn__text">Send Verification Email</span>
          </button>

          <div class="auth-footer">
            <p class="auth-footer__text">
              Already verified?
              <a href="<?= url('login.php') ?>" class="auth-footer__link">Sign In</a>
            </p>
            <div class="auth-nav">
              <a href="<?= url('index.php') ?>" class="auth-nav__link"><i class="fas fa-arrow-left"></i> Back to Home</a>
              <a href="<?= url('register.php') ?>" class="auth-nav__link">Create Account <i class="fas fa-arrow-right"></i></a>
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
