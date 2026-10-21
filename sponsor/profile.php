<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
$user = current_user();
$conn = Database::getConnection();
$userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$errors = [];
$success = '';
$firstName = trim((string)($user['first_name'] ?? ''));
$lastName = trim((string)($user['last_name'] ?? ''));
$phone = trim((string)($user['phone'] ?? ''));
$email = (string)($user['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('enforce_csrf')) {
        enforce_csrf();
    }
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    if ($firstName === '' || mb_strlen($firstName) > 50) $errors[] = 'Enter a first name (up to 50 characters).';
    if ($lastName === '' || mb_strlen($lastName) > 50) $errors[] = 'Enter a last name (up to 50 characters).';
    if (mb_strlen($phone) > 30) $errors[] = 'Phone number must be 30 characters or fewer.';

    if (!$errors) {
        $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?');
        if (!$stmt) {
            $errors[] = 'Unable to prepare the profile update. Please try again.';
        } else {
            $stmt->bind_param('sssi', $firstName, $lastName, $phone, $userId);
            if ($stmt->execute()) {
                $success = 'Your profile has been updated.';
                // Keep the current session identity in sync for the navbar.
                if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                    $_SESSION['user']['first_name'] = $firstName;
                    $_SESSION['user']['last_name'] = $lastName;
                    $_SESSION['user']['phone'] = $phone;
                }
                $user['first_name'] = $firstName;
                $user['last_name'] = $lastName;
                $user['phone'] = $phone;
            } else {
                $errors[] = 'The profile could not be saved. Please try again.';
            }
            $stmt->close();
        }
    }
}

sponsor_header('My Profile', 'profile', 'Manage the contact details associated with your sponsor account.');
?>
<style>
.sp-profile-card{max-width:820px;background:var(--surface);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);overflow:hidden}.sp-profile-head{padding:22px 24px;border-bottom:1px solid var(--line)}.sp-profile-head h2{margin:0 0 5px;font-size:17px}.sp-profile-head p{margin:0;color:var(--muted);font-size:12px}.sp-profile-form{padding:24px}.sp-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.sp-field{display:grid;gap:7px}.sp-field label{font-size:12px;font-weight:700}.sp-field input{width:100%;padding:12px 13px;border:1px solid var(--line);border-radius:10px;background:var(--surface2);color:var(--text);font:inherit}.sp-field input:focus{outline:2px solid color-mix(in srgb,var(--primary) 45%,transparent);border-color:var(--primary)}.sp-field input[readonly]{opacity:.75;cursor:not-allowed}.sp-field small{font-size:11px;color:var(--muted)}.sp-form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}.sp-alert{padding:13px 15px;border-radius:11px;margin-bottom:16px;font-size:13px}.sp-alert.success{background:color-mix(in srgb,var(--green) 12%,var(--surface));border:1px solid color-mix(in srgb,var(--green) 35%,var(--line));color:var(--green)}.sp-alert.error{background:color-mix(in srgb,var(--red) 10%,var(--surface));border:1px solid color-mix(in srgb,var(--red) 35%,var(--line));color:var(--red)}@media(max-width:600px){.sp-form-grid{grid-template-columns:1fr}.sp-profile-form{padding:17px}.sp-profile-head{padding:18px}.sp-form-actions{justify-content:stretch}.sp-form-actions .sp-btn{flex:1}}
</style>
<section class="sp-profile-card">
  <div class="sp-profile-head"><h2>Account details</h2><p>Update your name and contact number. Your account email is shown for reference and cannot be changed here.</p></div>
  <form class="sp-profile-form" method="post" action="<?= url('sponsor/profile.php') ?>">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>
    <?php if ($success !== ''): ?><div class="sp-alert success" role="status"><?= sponsor_e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="sp-alert error" role="alert"><strong>Please check the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= sponsor_e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="sp-form-grid">
      <div class="sp-field"><label for="first_name">First name</label><input id="first_name" name="first_name" maxlength="50" required value="<?= sponsor_e($firstName) ?>" autocomplete="given-name"></div>
      <div class="sp-field"><label for="last_name">Last name</label><input id="last_name" name="last_name" maxlength="50" required value="<?= sponsor_e($lastName) ?>" autocomplete="family-name"></div>
      <div class="sp-field"><label for="phone">Phone number</label><input id="phone" name="phone" maxlength="30" value="<?= sponsor_e($phone) ?>" autocomplete="tel" inputmode="tel"><small>Optional. Used for account contact details.</small></div>
      <div class="sp-field"><label for="email">Email address</label><input id="email" value="<?= sponsor_e($email) ?>" readonly aria-readonly="true"><small>Contact an administrator if this needs to change.</small></div>
    </div>
    <div class="sp-form-actions"><a class="sp-btn" href="<?= url('sponsor/dashboard.php') ?>">Cancel</a><button class="sp-btn primary" type="submit">Save profile</button></div>
  </form>
</section>
<?php sponsor_footer(); ?>
