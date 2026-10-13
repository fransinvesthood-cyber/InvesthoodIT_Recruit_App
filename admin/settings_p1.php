<?php // General tab ?>
<form method="POST" action="<?= url('admin/settings_save.php') ?>" enctype="multipart/form-data" id="settingsForm" novalidate>
<?= csrf_field() ?>
<input type="hidden" name="existing_logo_path" value="<?= e($flat['logo_path'] ?? '') ?>">
<div class="set-tabs" role="tablist">
<button type="button" class="set-tab active" data-tab="general" role="tab"><i class="fas fa-sliders-h"></i> General</button>
<button type="button" class="set-tab" data-tab="accounts" role="tab"><i class="fas fa-user-shield"></i> Users</button>
<button type="button" class="set-tab" data-tab="notifications" role="tab"><i class="fas fa-bell"></i> Notifications</button>
<button type="button" class="set-tab" data-tab="programmes" role="tab"><i class="fas fa-graduation-cap"></i> Programmes</button>
<button type="button" class="set-tab" data-tab="maintenance" role="tab"><i class="fas fa-tools"></i> Maintenance</button>
</div>
<section class="set-panel active" data-panel="general">
<h3 class="set-panel__title"><i class="fas fa-sliders-h"></i> General Configuration</h3>
<div class="set-grid">
<div class="set-field"><label>Platform name *</label><input class="form-input" name="site_name" maxlength="100" value="<?= e(sVal('site_name',$flat,$old)) ?>"><?= field_error($errors,'site_name') ?></div>
<div class="set-field"><label>Organisation name *</label><input class="form-input" name="organisation_name" maxlength="100" value="<?= e(sVal('organisation_name',$flat,$old)) ?>"><?= field_error($errors,'organisation_name') ?></div>
<div class="set-field set-field--full"><label>Platform description</label><textarea class="form-input" name="site_description" rows="3" maxlength="500"><?= e(sVal('site_description',$flat,$old)) ?></textarea><?= field_error($errors,'site_description') ?></div>
<div class="set-field"><label>Support email *</label><input type="email" class="form-input" name="support_email" value="<?= e(sVal('support_email',$flat,$old)) ?>"><?= field_error($errors,'support_email') ?></div>
<div class="set-field"><label>Contact phone</label><input class="form-input" name="contact_phone" value="<?= e(sVal('contact_phone',$flat,$old)) ?>"><?= field_error($errors,'contact_phone') ?></div>
<div class="set-field"><label>Timezone</label><select class="form-input" name="timezone"><?php foreach ($tzList as $tz): ?><option value="<?= e($tz) ?>" <?= sVal('timezone',$flat,$old)===$tz?'selected':'' ?>><?= e($tz) ?></option><?php endforeach; ?></select><?= field_error($errors,'timezone') ?></div>
<div class="set-field"><label>Date format</label><select class="form-input" name="date_format"><?php foreach (['d M Y'=>'12 Jan 2026','Y-m-d'=>'2026-01-12','d/m/Y'=>'12/01/2026','m/d/Y'=>'01/12/2026','F j, Y'=>'January 12, 2026'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= sVal('date_format',$flat,$old)===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors,'date_format') ?></div>
<div class="set-field"><label>Time format</label><select class="form-input" name="time_format"><?php foreach (['H:i'=>'14:30 (24h)','h:i A'=>'02:30 PM','H:i:s'=>'14:30:05'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= sVal('time_format',$flat,$old)===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors,'time_format') ?></div>
<div class="set-field"><label>Default language</label><select class="form-input" name="default_language"><?php foreach (['en'=>'English','af'=>'Afrikaans','zu'=>'isiZulu','xh'=>'isiXhosa','st'=>'Sesotho'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= sVal('default_language',$flat,$old)===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error($errors,'default_language') ?></div>
<div class="set-field set-field--full"><label>Platform logo (PNG/JPG/WebP, max 2MB)</label><?php if ($logoUrl !== ''): ?><div class="set-logo-preview"><img src="<?= e($logoUrl) ?>" alt="Logo"></div><?php endif; ?><input type="file" class="form-input" name="logo" accept=".png,.jpg,.jpeg,.webp"><?= field_error($errors,'logo') ?></div>
</div>
</section>
