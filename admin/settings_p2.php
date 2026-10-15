<?php // Accounts + notifications tabs ?>
<section class="set-panel" data-panel="accounts">
<h3 class="set-panel__title"><i class="fas fa-user-shield"></i> Users &amp; Accounts</h3>
<div class="set-switch-row"><div><strong>Candidate registrations</strong><span>When off, register.php shows registrations closed.</span></div><label class="set-switch"><input type="checkbox" name="registration_enabled" value="1" <?= sChecked('registration_enabled',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Email verification required</strong><span>When off, new accounts are active immediately.</span></div><label class="set-switch"><input type="checkbox" name="email_verification_required" value="1" <?= sChecked('email_verification_required',$flat,$old) ?>><span></span></label></div>
<div class="set-grid">
<div class="set-field"><label>Min password length (6-32)</label><input type="number" class="form-input" name="password_min_length" min="6" max="32" value="<?= e(sVal('password_min_length',$flat,$old)) ?>"><?= field_error($errors,'password_min_length') ?></div>
<div class="set-field"><label>Session timeout (minutes)</label><input type="number" class="form-input" name="session_timeout_minutes" min="5" max="480" value="<?= e(sVal('session_timeout_minutes',$flat,$old)) ?>"><?= field_error($errors,'session_timeout_minutes') ?></div>
<div class="set-field"><label>Max login attempts</label><input type="number" class="form-input" name="login_max_attempts" min="3" max="20" value="<?= e(sVal('login_max_attempts',$flat,$old)) ?>"><?= field_error($errors,'login_max_attempts') ?></div>
<div class="set-field"><label>Lockout minutes</label><input type="number" class="form-input" name="login_lockout_minutes" min="1" max="120" value="<?= e(sVal('login_lockout_minutes',$flat,$old)) ?>"><?= field_error($errors,'login_lockout_minutes') ?></div>
</div>
<h4 class="set-sub">Password complexity</h4>
<div class="set-checks">
<label><input type="checkbox" name="password_require_uppercase" value="1" <?= sChecked('password_require_uppercase',$flat,$old) ?>> Uppercase</label>
<label><input type="checkbox" name="password_require_lowercase" value="1" <?= sChecked('password_require_lowercase',$flat,$old) ?>> Lowercase</label>
<label><input type="checkbox" name="password_require_number" value="1" <?= sChecked('password_require_number',$flat,$old) ?>> Number</label>
<label><input type="checkbox" name="password_require_special" value="1" <?= sChecked('password_require_special',$flat,$old) ?>> Special char</label>
</div>
</section>
<section class="set-panel" data-panel="notifications">
<h3 class="set-panel__title"><i class="fas fa-bell"></i> Email &amp; Notifications</h3>
<p class="set-hint">SMTP credentials stay in server environment variables and are never shown here.</p>
<div class="set-switch-row"><div><strong>Master email switch</strong><span>When off, no platform emails are sent.</span></div><label class="set-switch"><input type="checkbox" name="mail_notifications_enabled" value="1" <?= sChecked('mail_notifications_enabled',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Registration welcome email</strong></div><label class="set-switch"><input type="checkbox" name="notif_registration" value="1" <?= sChecked('notif_registration',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Verification email</strong></div><label class="set-switch"><input type="checkbox" name="notif_verification" value="1" <?= sChecked('notif_verification',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Application status updates</strong></div><label class="set-switch"><input type="checkbox" name="notif_application_status" value="1" <?= sChecked('notif_application_status',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Interview notifications</strong></div><label class="set-switch"><input type="checkbox" name="notif_interview" value="1" <?= sChecked('notif_interview',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Selection &amp; offer notifications</strong></div><label class="set-switch"><input type="checkbox" name="notif_offer" value="1" <?= sChecked('notif_offer',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Placement notifications</strong></div><label class="set-switch"><input type="checkbox" name="notif_placement" value="1" <?= sChecked('notif_placement',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Admin system alerts</strong></div><label class="set-switch"><input type="checkbox" name="notif_admin_alerts" value="1" <?= sChecked('notif_admin_alerts',$flat,$old) ?>><span></span></label></div>
</section>
