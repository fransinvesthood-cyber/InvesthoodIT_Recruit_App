<?php // Programmes + maintenance tabs + actions ?>
<section class="set-panel" data-panel="programmes">
<h3 class="set-panel__title"><i class="fas fa-graduation-cap"></i> Programmes</h3>
<div class="set-switch-row"><div><strong>Applications enabled</strong><span>When off, candidates cannot start or submit applications.</span></div><label class="set-switch"><input type="checkbox" name="applications_enabled" value="1" <?= sChecked('applications_enabled',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Enforce closing dates</strong></div><label class="set-switch"><input type="checkbox" name="close_past_deadline" value="1" <?= sChecked('close_past_deadline',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Only published opportunities</strong></div><label class="set-switch"><input type="checkbox" name="opportunities_require_published" value="1" <?= sChecked('opportunities_require_published',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Talent pool enabled</strong></div><label class="set-switch"><input type="checkbox" name="talent_pool_enabled" value="1" <?= sChecked('talent_pool_enabled',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>New programmes active by default</strong></div><label class="set-switch"><input type="checkbox" name="auto_publish_programmes" value="1" <?= sChecked('auto_publish_programmes',$flat,$old) ?>><span></span></label></div>
<div class="set-grid"><div class="set-field"><label>Default application status</label><select class="form-input" name="default_application_status"><option value="draft" <?= sVal('default_application_status',$flat,$old)==='draft'?'selected':'' ?>>Draft</option><option value="submitted" <?= sVal('default_application_status',$flat,$old)==='submitted'?'selected':'' ?>>Submitted</option></select><?= field_error($errors,'default_application_status') ?></div></div>
</section>
<section class="set-panel" data-panel="maintenance">
<h3 class="set-panel__title"><i class="fas fa-tools"></i> Maintenance</h3>
<div class="set-alert"><i class="fas fa-exclamation-triangle"></i><div><strong>Maintenance never locks out admins.</strong><span>Admins always bypass it.</span></div></div>
<div class="set-switch-row set-switch-row--danger"><div><strong>Maintenance mode</strong><span>Show the message below to non-admin users.</span></div><label class="set-switch"><input type="checkbox" name="maintenance_mode" value="1" id="maintToggle" <?= sChecked('maintenance_mode',$flat,$old) ?>><span></span></label></div>
<div class="set-field set-field--full"><label>Maintenance message *</label><textarea class="form-input" name="maintenance_message" rows="3" maxlength="500"><?= e(sVal('maintenance_message',$flat,$old)) ?></textarea><?= field_error($errors,'maintenance_message') ?></div>
<div class="set-switch-row"><div><strong>Reporting features</strong></div><label class="set-switch"><input type="checkbox" name="feature_reporting_enabled" value="1" <?= sChecked('feature_reporting_enabled',$flat,$old) ?>><span></span></label></div>
<div class="set-switch-row"><div><strong>Chatbot widget</strong></div><label class="set-switch"><input type="checkbox" name="feature_chatbot_enabled" value="1" <?= sChecked('feature_chatbot_enabled',$flat,$old) ?>><span></span></label></div>
<div class="set-grid"><div class="set-field"><label>App version</label><input class="form-input" name="app_version" maxlength="20" value="<?= e(sVal('app_version',$flat,$old)) ?>"><?= field_error($errors,'app_version') ?></div></div>
<h4 class="set-sub">System health</h4>
<div class="set-health">
<div><span>Database</span><strong><?= e($health['database']) ?></strong></div>
<div><span>Environment</span><strong><?= e($health['environment']) ?></strong></div>
<div><span>PHP</span><strong><?= e($health['php']) ?></strong></div>
<div><span>Maintenance</span><strong><?= e($health['maintenance']) ?></strong></div>
</div>
</section>
<div class="set-actions"><button type="button" class="btn btn--ghost" id="settingsReset"><i class="fas fa-undo"></i> Reset</button><button type="submit" class="btn btn--primary" id="settingsSaveBtn"><span class="btn__spinner" hidden><i class="fas fa-spinner fa-spin"></i></span> <span class="btn__text"><i class="fas fa-save"></i> Save Changes</span></button></div>
</form>
