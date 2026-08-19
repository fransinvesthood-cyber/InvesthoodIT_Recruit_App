<?php
/**
 * ================================================
 * INVESTHOOD IT - Registration Page
 * ================================================
 * Displays the registration form and re-displays
 * server-side validation errors returned from the
 * registration processor.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_guest();

// Pull any validation errors & old input from the failed POST
$errors = $_SESSION['_form_errors'] ?? [];
$old    = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Register - Investhood IT Programme & Scarce Skills Platform">
  <title>Register | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body class="auth-page">

  <!-- ===== REGISTER HERO ===== -->
  <section class="hero" style="min-height:auto;padding:6rem 0 3rem;">
    <div class="hero__bg"></div>
    <div class="container hero__container">
      <div class="hero__content">
        <span class="hero__badge">Start Your Journey</span>
        <h1 class="hero__title">Begin Your Career Journey <span class="text-gradient">Today</span></h1>
        <p class="hero__text">
          Join the Investhood IT Talent Community and gain access to graduate programmes, internships, learnerships, career opportunities, and future placements.
        </p>
      </div>
    </div>
  </section>

  <!-- ===== REGISTER MAIN ===== -->
  <main class="auth-main">
    <div class="auth-card" style="box-shadow:var(--shadow-lg);border-radius:var(--radius-lg);max-width:800px;width:100%;border:1px solid var(--border);">
      <div class="auth-card__inner">
        <div class="auth-card__header">
          <h2 class="auth-card__title">Create Your Account</h2>
          <p class="auth-card__subtitle">Join thousands of professionals building their careers through Investhood IT.</p>
        </div>

        <div class="auth-notice">
          <i class="fas fa-user-graduate"></i>
          <div>
            <strong>Candidate registration</strong>
            <span>Public registration is for Candidates only. Staff accounts (admin, recruiter, supervisor, etc.) are created by an administrator.</span>
          </div>
        </div>

        <form class="form" id="registerForm" method="POST" action="<?= url('auth/register.php') ?>" novalidate>
          <?= csrf_field() ?>

          <!-- ===== Personal Information ===== -->
          <div class="auth-divider">
            <span class="auth-divider__text">Personal Information</span>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="firstName" class="form-label">
                First Name <span class="form-label__required">*</span>
              </label>
              <input type="text" id="firstName" name="first_name" class="form-input" placeholder="John" required autocomplete="given-name" value="<?= e($old['first_name'] ?? '') ?>" />
              <?= field_error($errors, 'first_name') ?>
            </div>
            <div class="form-group">
              <label for="lastName" class="form-label">
                Last Name <span class="form-label__required">*</span>
              </label>
              <input type="text" id="lastName" name="last_name" class="form-input" placeholder="Doe" required autocomplete="family-name" value="<?= e($old['last_name'] ?? '') ?>" />
              <?= field_error($errors, 'last_name') ?>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="regEmail" class="form-label">
                Email Address <span class="form-label__required">*</span>
              </label>
              <input type="email" id="regEmail" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email" inputmode="email" value="<?= e($old['email'] ?? '') ?>" />
              <?= field_error($errors, 'email') ?>
            </div>
            <div class="form-group">
              <label for="phoneNumber" class="form-label">
                Phone Number <span class="form-label__required">*</span>
              </label>
              <input type="tel" id="phoneNumber" name="phone" class="form-input" placeholder="+27 12 345 6789" required autocomplete="tel" value="<?= e($old['phone'] ?? '') ?>" />
              <?= field_error($errors, 'phone') ?>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="dateOfBirth" class="form-label">
                Date of Birth <span class="form-label__required">*</span>
              </label>
              <input type="date" id="dateOfBirth" name="date_of_birth" class="form-input" required value="<?= e($old['date_of_birth'] ?? '') ?>" />
              <?= field_error($errors, 'date_of_birth') ?>
            </div>
            <div class="form-group">
              <label for="gender" class="form-label">Gender</label>
              <select id="gender" name="gender" class="form-input form-input--select">
                <option value="">Select Gender</option>
                <option value="male" <?= ($old['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= ($old['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                <option value="non-binary" <?= ($old['gender'] ?? '') === 'non-binary' ? 'selected' : '' ?>>Non-binary</option>
                <option value="prefer-not-to-say" <?= ($old['gender'] ?? '') === 'prefer-not-to-say' ? 'selected' : '' ?>>Prefer not to say</option>
              </select>
            </div>
          </div>

          <!-- ===== Account Information ===== -->
          <div class="auth-divider">
            <span class="auth-divider__text">Account Information</span>
          </div>

          <div class="form-group">
            <label for="username" class="form-label">
              Username <span class="form-label__required">*</span>
            </label>
            <input type="text" id="username" name="username" class="form-input" placeholder="johndoe123" required autocomplete="username" minlength="3" value="<?= e($old['username'] ?? '') ?>" />
            <?= field_error($errors, 'username') ?>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="regPassword" class="form-label">
                Password <span class="form-label__required">*</span>
              </label>
              <div class="password-input-wrapper">
                <input type="password" id="regPassword" name="password" class="form-input" placeholder="Create a strong password" required autocomplete="new-password" minlength="8" />
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
              <label for="regConfirmPassword" class="form-label">
                Confirm Password <span class="form-label__required">*</span>
              </label>
              <div class="password-input-wrapper">
                <input type="password" id="regConfirmPassword" name="confirm_password" class="form-input" placeholder="Confirm your password" required autocomplete="new-password" />
                <button type="button" class="password-toggle" aria-label="Toggle password visibility" tabindex="-1"><i class="fas fa-eye"></i></button>
              </div>
              <?= field_error($errors, 'confirm_password') ?>
            </div>
          </div>

          <!-- ===== Candidate Preferences ===== -->
          <div class="auth-divider">
            <span class="auth-divider__text">Candidate Preferences</span>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="province" class="form-label">
                Province <span class="form-label__required">*</span>
              </label>
              <select id="province" name="province" class="form-input form-input--select" required>
                <option value="">Select Province</option>
                <option value="eastern-cape" <?= ($old['province'] ?? '') === 'eastern-cape' ? 'selected' : '' ?>>Eastern Cape</option>
                <option value="free-state" <?= ($old['province'] ?? '') === 'free-state' ? 'selected' : '' ?>>Free State</option>
                <option value="gauteng" <?= ($old['province'] ?? '') === 'gauteng' ? 'selected' : '' ?>>Gauteng</option>
                <option value="kwazulu-natal" <?= ($old['province'] ?? '') === 'kwazulu-natal' ? 'selected' : '' ?>>KwaZulu-Natal</option>
                <option value="limpopo" <?= ($old['province'] ?? '') === 'limpopo' ? 'selected' : '' ?>>Limpopo</option>
                <option value="mpumalanga" <?= ($old['province'] ?? '') === 'mpumalanga' ? 'selected' : '' ?>>Mpumalanga</option>
                <option value="northern-cape" <?= ($old['province'] ?? '') === 'northern-cape' ? 'selected' : '' ?>>Northern Cape</option>
                <option value="north-west" <?= ($old['province'] ?? '') === 'north-west' ? 'selected' : '' ?>>North West</option>
                <option value="western-cape" <?= ($old['province'] ?? '') === 'western-cape' ? 'selected' : '' ?>>Western Cape</option>
              </select>
              <?= field_error($errors, 'province') ?>
            </div>
            <div class="form-group">
              <label for="employmentStatus" class="form-label">
                Employment Status <span class="form-label__required">*</span>
              </label>
              <select id="employmentStatus" name="employment_status" class="form-input form-input--select" required>
                <option value="">Select Status</option>
                <option value="employed" <?= ($old['employment_status'] ?? '') === 'employed' ? 'selected' : '' ?>>Employed</option>
                <option value="unemployed" <?= ($old['employment_status'] ?? '') === 'unemployed' ? 'selected' : '' ?>>Unemployed</option>
                <option value="student" <?= ($old['employment_status'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="recent-graduate" <?= ($old['employment_status'] ?? '') === 'recent-graduate' ? 'selected' : '' ?>>Recent Graduate</option>
                <option value="freelancer" <?= ($old['employment_status'] ?? '') === 'freelancer' ? 'selected' : '' ?>>Freelancer</option>
                <option value="other" <?= ($old['employment_status'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
              </select>
              <?= field_error($errors, 'employment_status') ?>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="qualificationLevel" class="form-label">
                Qualification Level <span class="form-label__required">*</span>
              </label>
              <select id="qualificationLevel" name="qualification_level" class="form-input form-input--select" required>
                <option value="">Select Level</option>
                <option value="grade-12" <?= ($old['qualification_level'] ?? '') === 'grade-12' ? 'selected' : '' ?>>Grade 12 / Matric</option>
                <option value="certificate" <?= ($old['qualification_level'] ?? '') === 'certificate' ? 'selected' : '' ?>>Certificate</option>
                <option value="diploma" <?= ($old['qualification_level'] ?? '') === 'diploma' ? 'selected' : '' ?>>Diploma</option>
                <option value="degree" <?= ($old['qualification_level'] ?? '') === 'degree' ? 'selected' : '' ?>>Bachelor's Degree</option>
                <option value="honours" <?= ($old['qualification_level'] ?? '') === 'honours' ? 'selected' : '' ?>>Honours Degree</option>
                <option value="masters" <?= ($old['qualification_level'] ?? '') === 'masters' ? 'selected' : '' ?>>Master's Degree</option>
                <option value="phd" <?= ($old['qualification_level'] ?? '') === 'phd' ? 'selected' : '' ?>>PhD / Doctorate</option>
              </select>
              <?= field_error($errors, 'qualification_level') ?>
            </div>
            <div class="form-group">
              <label for="professionalTitle" class="form-label">Professional Title (Optional)</label>
              <input type="text" id="professionalTitle" name="professional_title" class="form-input" placeholder="e.g. Software Developer" value="<?= e($old['professional_title'] ?? '') ?>" />
            </div>
          </div>

          <!-- ===== Consent Requirements ===== -->
          <div class="auth-divider">
            <span class="auth-divider__text">Consent & Agreements</span>
          </div>

          <div class="form-group" style="gap:0.75rem;">
            <label class="checkbox-group">
              <input type="checkbox" id="termsCheckbox" name="terms" value="on" <?= !empty($old['terms']) ? 'checked' : '' ?> />
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">
                I accept the <a href="#">Terms and Conditions</a> <span style="color:#ef4444;">*</span>
              </span>
            </label>
            <?= field_error($errors, 'terms') ?>

            <label class="checkbox-group">
              <input type="checkbox" id="privacyCheckbox" name="privacy" value="on" <?= !empty($old['privacy']) ? 'checked' : '' ?> />
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">
                I acknowledge the <a href="#">Privacy Policy</a> <span style="color:#ef4444;">*</span>
              </span>
            </label>
            <?= field_error($errors, 'privacy') ?>

            <label class="checkbox-group">
              <input type="checkbox" id="adminConsent" name="admin_consent" value="on" <?= !empty($old['admin_consent']) ? 'checked' : '' ?> />
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">
                I consent to Programme Administration <span style="color:#ef4444;">*</span>
              </span>
            </label>
            <?= field_error($errors, 'admin_consent') ?>

            <label class="checkbox-group">
              <input type="checkbox" id="futureOpportunities" name="future_opportunities" value="on" <?= !empty($old['future_opportunities']) ? 'checked' : '' ?> />
              <span class="checkbox-custom"></span>
              <span class="checkbox-label">
                I would like to receive information about future opportunities and participate in the Talent Pool
              </span>
            </label>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn--primary btn--full" style="margin-top:0.5rem;">
            <span class="btn__text">Create Account <i class="fas fa-arrow-right"></i></span>
          </button>

          <!-- Login Link -->
          <div class="auth-footer">
            <p class="auth-footer__text">
              Already have an account?
              <a href="<?= url('login.php') ?>" class="auth-footer__link">Login here</a>
            </p>
            <div class="auth-nav">
              <a href="<?= url('index.php') ?>" class="auth-nav__link"><i class="fas fa-arrow-left"></i> Back to Home</a>
              <a href="<?= url('login.php') ?>" class="auth-nav__link">Sign In <i class="fas fa-arrow-right"></i></a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </main>

  <!-- ===== CANDIDATE JOURNEY SECTION ===== -->
  <section class="benefits-section">
    <div class="container">
      <div class="section__header">
        <span class="section__badge">What You Get</span>
        <h2 class="section__title">Your <span class="text-gradient">Career Journey</span> Starts Here</h2>
        <p class="section__text">Unlock access to exclusive benefits designed to accelerate your professional growth.</p>
      </div>
      <div class="benefits-grid">
        <div class="benefit-card fade-in">
          <div class="benefit-card__icon"><i class="fas fa-briefcase"></i></div>
          <h4>Apply for Opportunities</h4>
          <p>Browse and apply to graduate programmes, internships, and learnerships.</p>
        </div>
        <div class="benefit-card fade-in">
          <div class="benefit-card__icon"><i class="fas fa-graduation-cap"></i></div>
          <h4>Join Graduate Programmes</h4>
          <p>Enrol in structured development programmes designed for career success.</p>
        </div>
        <div class="benefit-card fade-in">
          <div class="benefit-card__icon"><i class="fas fa-users"></i></div>
          <h4>Become Part of the Talent Community</h4>
          <p>Connect with employers and recruiters looking for top talent.</p>
        </div>
        <div class="benefit-card fade-in">
          <div class="benefit-card__icon"><i class="fas fa-clipboard-check"></i></div>
          <h4>Track Applications & Placements</h4>
          <p>Monitor your application status and placement progress in real-time.</p>
        </div>
        <div class="benefit-card fade-in">
          <div class="benefit-card__icon"><i class="fas fa-id-card"></i></div>
          <h4>Manage Professional Profiles</h4>
          <p>Create and maintain a standout professional profile for employers.</p>
        </div>
        <div class="benefit-card fade-in">
          <div class="benefit-card__icon"><i class="fas fa-star"></i></div>
          <h4>Receive Personalised Recommendations</h4>
          <p>Get tailored opportunity recommendations based on your skills and preferences.</p>
        </div>
        <div class="benefit-card fade-in">
          <div class="benefit-card__icon"><i class="fas fa-rocket"></i></div>
          <h4>Access Future Career Opportunities</h4>
          <p>Stay informed about new placements and career advancement options.</p>
        </div>
        <div class="benefit-card fade-in">
          <div class="benefit-card__icon"><i class="fas fa-chart-line"></i></div>
          <h4>Skills Development & Upskilling</h4>
          <p>Access resources and programmes for continuous professional growth.</p>
        </div>
      </div>
    </div>
  </section>

  <?= render_flashes() ?>
  <script src="<?= url('js/script.js') ?>"></script>
</body>
</html>

