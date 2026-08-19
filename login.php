<?php
/**
 * ================================================
 * INVESTHOOD IT - Login Page
 * ================================================
 * Displays the login form and processes server-side
 * validation output. Handles remember-me auto-login.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Guest only
require_guest();

// Attempt remember-me auto-login
AuthController::loginViaRememberMe();

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    safe_redirect(role_dashboard(current_role()) ?? 'candidate/dashboard.php');
}

$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Login - Investhood IT Programme & Scarce Skills Platform">
  <title>Login | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body class="auth-page">

  <main class="auth-main auth-main--split">
    <div class="auth-split">
      <!-- Left Side - Hero / Illustration -->
      <div class="auth-hero">
        <div class="auth-hero__content">
          <div class="auth-hero__icon">
            <i class="fas fa-rocket"></i>
          </div>
          <h1 class="auth-hero__title">Welcome Back!</h1>
          <p class="auth-hero__text">
            Continue building your future with Investhood IT. Access programmes, opportunities, talent pools, and your personalised career dashboard.
          </p>
          <div class="auth-hero__features">
            <span class="auth-hero__feature"><i class="fas fa-check"></i> Graduate Programmes</span>
            <span class="auth-hero__feature"><i class="fas fa-check"></i> Learnerships</span>
            <span class="auth-hero__feature"><i class="fas fa-check"></i> Internships</span>
            <span class="auth-hero__feature"><i class="fas fa-check"></i> Talent Community</span>
            <span class="auth-hero__feature"><i class="fas fa-check"></i> Career Dashboard</span>
            <span class="auth-hero__feature"><i class="fas fa-check"></i> Skills Development</span>
          </div>
        </div>
      </div>

      <!-- Right Side - Login Card -->
      <div class="auth-card">
        <div class="auth-card__inner">
          <div class="auth-card__header">
            <h2 class="auth-card__title">Sign In</h2>
            <p class="auth-card__subtitle">Access your personalised career dashboard and opportunities.</p>
          </div>

          <form class="form" id="loginForm" method="POST" action="<?= url('auth/login.php') ?>" novalidate>
            <?= csrf_field() ?>

            <!-- Login identifier (email or username) -->
            <div class="form-group">
              <label for="login" class="form-label">
                Email or Username <span class="form-label__required">*</span>
              </label>
              <input
                type="text"
                id="login"
                name="login"
                class="form-input"
                placeholder="you@example.com or username"
                required
                autocomplete="username"
                value="<?= e($old['login'] ?? '') ?>"
              />
              <div class="form-error" id="loginError"></div>
            </div>

            <!-- Password -->
            <div class="form-group">
              <label for="loginPassword" class="form-label">
                Password <span class="form-label__required">*</span>
              </label>
              <div class="password-input-wrapper">
                <input
                  type="password"
                  id="loginPassword"
                  name="password"
                  class="form-input"
                  placeholder="Enter your password"
                  required
                  autocomplete="current-password"
                  minlength="6"
                />
                <button type="button" class="password-toggle" aria-label="Toggle password visibility" tabindex="-1">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
              <div class="form-error" id="loginPasswordError"></div>
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="form-options">
              <label class="checkbox-group">
                <input type="checkbox" name="remember_me" id="rememberMe" value="1" <?= !empty($old['remember_me']) ? 'checked' : '' ?> />
                <span class="checkbox-custom"></span>
                <span class="checkbox-label">Remember me</span>
              </label>
              <a href="<?= url('forgot-password.php') ?>" class="forgot-link">Forgot Password?</a>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn--primary btn--full">
              <span class="btn__text">Sign In</span>
            </button>

            <!-- Register Link -->
            <div class="auth-footer">
              <p class="auth-footer__text">
                Don't have an account?
                <a href="<?= url('register.php') ?>" class="auth-footer__link">Create one here</a>
              </p>
              <div class="auth-nav">
                <a href="<?= url('index.php') ?>" class="auth-nav__link"><i class="fas fa-arrow-left"></i> Back to Home</a>
                <a href="<?= url('register.php') ?>" class="auth-nav__link">Register Now <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <?= render_flashes() ?>
  <script src="<?= url('js/script.js') ?>"></script>
</body>
</html>

