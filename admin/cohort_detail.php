<?php
/**
 * ================================================
 * INVESTHOOD IT - Cohort Details Page
 * ================================================
 * Full cohort management workspace: capacity tracking,
 * eligibility, skills, required evidence, workflow config.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$cohort = Cohort::find($id);
if (!$cohort) {
    set_flash('error', 'Not Found', 'The requested cohort does not exist.');
    safe_redirect('admin/programmes.php');
}

$programme = Programme::find((int) $cohort['programme_id']);
$user      = current_user();

$counts       = Cohort::participantCounts($id);
$selected     = (int) $counts['selected'];
$onboarded    = (int) $counts['onboarded'];
$active       = (int) $counts['active'];
$committed    = $selected + $onboarded + $active;
$max          = (int) $cohort['max_capacity'];
$available    = max(0, $max - $committed);
$pct          = $max > 0 ? round(($committed / $max) * 100) : 0;

$eligibility = CohortEligibility::forCohort($id);
$skills      = CohortSkill::forCohort($id);
$documents   = CohortDocument::forCohort($id);
$workflow    = CohortWorkflow::forCohort($id);

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Cohort Details - Investhood IT Administrator">
  <title><?= e($cohort['name']) ?> | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard pm-module">

  <div class="dashboard">
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header"><a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a><button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button></div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li></ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link active"><i class="fas fa-graduation-cap"></i> Programmes</a></li></ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user"><div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile"></div><div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin') ?></span><span class="sidebar__user-role">Administrator</span></div></div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left"><button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button><a href="<?= url('admin/programme_detail.php?id=' . (int)$cohort['programme_id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Programme</a></div>
        <div class="dash-header__right">
          <a href="<?= url('admin/cohort_edit.php?id=' . $id) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i> Edit</a>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== COHORT HEADER ===== -->
        <section class="pm-detail-header">
          <div class="pm-detail-header__main">
            <div class="pm-detail-header__top">
              <span class="pm-card__type"><?= e(delivery_mode_label($cohort['delivery_mode'])) ?> Cohort</span>
              <?= status_badge($cohort['status'], COHORT_STATUS_LABELS[$cohort['status']] ?? ucfirst($cohort['status'])) ?>
            </div>
            <h1 class="pm-detail-header__title"><?= e($cohort['name']) ?></h1>
            <p class="pm-detail-header__dates">
              <i class="fas fa-graduation-cap"></i> <?= e($programme['name'] ?? 'Programme') ?> ·
              <i class="fas fa-map-marker-alt"></i> <?= e($cohort['location'] ?: ucwords(str_replace('-', ' ', $cohort['province'] ?? 'On-site'))) ?> ·
              <i class="fas fa-calendar-alt"></i> <?= e(!empty($cohort['start_date']) ? format_date($cohort['start_date'], 'd M Y') : 'TBC') ?> — <?= e(!empty($cohort['end_date']) ? format_date($cohort['end_date'], 'd M Y') : 'TBC') ?>
            </p>
          </div>
          <div class="pm-detail-header__actions">
            <form method="post" action="<?= url('admin/cohort_actions.php') ?>" style="display:flex;gap:0.5rem;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="id" value="<?= (int) $cohort['id'] ?>">
              <select name="status" class="form-input form-input--select" style="width:auto">
                <?php foreach (COHORT_STATUS_LABELS as $slug => $label): ?>
                  <option value="<?= e($slug) ?>" <?= $cohort['status'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-exchange-alt"></i> Update Status</button>
            </form>
          </div>
        </section>

        <!-- ===== CAPACITY SUMMARY ===== -->
        <section class="pm-capacity">
          <div class="pm-capacity__stats">
            <div class="pm-capacity-stat"><span class="pm-capacity-stat__value"><?= (int) $max ?></span><span class="pm-capacity-stat__label">Maximum Capacity</span></div>
            <div class="pm-capacity-stat"><span class="pm-capacity-stat__value"><?= (int) $cohort['applications_count'] ?></span><span class="pm-capacity-stat__label">Applications</span></div>
            <div class="pm-capacity-stat"><span class="pm-capacity-stat__value"><?= (int) $selected ?></span><span class="pm-capacity-stat__label">Selected</span></div>
            <div class="pm-capacity-stat"><span class="pm-capacity-stat__value"><?= (int) $onboarded ?></span><span class="pm-capacity-stat__label">Onboarded</span></div>
            <div class="pm-capacity-stat"><span class="pm-capacity-stat__value"><?= (int) $available ?></span><span class="pm-capacity-stat__label">Available Places</span></div>
          </div>
          <div class="pm-capacity__bar-block">
            <div class="pm-capacity__bar-head"><span class="pm-capacity__bar-title"><i class="fas fa-users"></i> Capacity</span><span class="pm-capacity__bar-count"><?= $committed ?> / <?= $max ?> participants</span></div>
            <div class="pm-progress pm-progress--lg"><div class="pm-progress__bar" style="width:<?= $pct ?>%"></div></div>
          </div>
        </section>

        <!-- ===== TABS ===== -->
        <section class="pm-workspace">
          <nav class="pm-tabs" id="pmTabs">
            <button class="pm-tab active" data-tab="overview"><i class="fas fa-th-large"></i> Overview</button>
            <button class="pm-tab" data-tab="eligibility"><i class="fas fa-clipboard-list"></i> Eligibility &amp; Skills</button>
            <button class="pm-tab" data-tab="documents"><i class="fas fa-file-alt"></i> Required Evidence</button>
            <button class="pm-tab" data-tab="workflow"><i class="fas fa-route"></i> Workflow</button>
            <button class="pm-tab" data-tab="activity"><i class="fas fa-history"></i> Activity</button>
          </nav>

          <div class="pm-tab-panels">

            <!-- OVERVIEW -->
            <div class="pm-tab-panel is-active" id="tab-overview">
              <div class="pm-overview-grid">
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-info-circle"></i> Cohort Description</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($cohort['description'] ?? 'No description provided.')) ?></p>
                </div>
                <div class="pm-card-block pm-card-block--details">
                  <h3 class="pm-card-block__title"><i class="fas fa-list"></i> Cohort Details</h3>
                  <dl class="pm-details-list">
                    <div><dt>Parent Programme</dt><dd><?= e($programme['name'] ?? '—') ?></dd></div>
                    <div><dt>Delivery Mode</dt><dd><?= e(delivery_mode_label($cohort['delivery_mode'])) ?></dd></div>
                    <div><dt>Location</dt><dd><?= e($cohort['location'] ?: '—') ?></dd></div>
                    <div><dt>Province</dt><dd><?= e(ucwords(str_replace('-', ' ', $cohort['province'] ?? '—'))) ?></dd></div>
                    <div><dt>Start Date</dt><dd><?= e(!empty($cohort['start_date']) ? format_date($cohort['start_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>End Date</dt><dd><?= e(!empty($cohort['end_date']) ? format_date($cohort['end_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>Application Open</dt><dd><?= e(!empty($cohort['application_open_date']) ? format_date($cohort['application_open_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>Application Close</dt><dd><?= e(!empty($cohort['application_close_date']) ? format_date($cohort['application_close_date'], 'd M Y') : '—') ?></dd></div>
                  </dl>
                </div>
              </div>
            </div>

            <!-- ELIGIBILITY & SKILLS -->
            <div class="pm-tab-panel" id="tab-eligibility">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Eligibility Configuration</h3>
                <p class="pm-panel-sub">Configure cohort-specific eligibility and skills. These extend the programme-level requirements.</p>
              </div>
              <form method="post" action="<?= url('admin/cohort_config_actions.php') ?>" class="pm-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="eligibility">
                <input type="hidden" name="id" value="<?= (int) $cohort['id'] ?>">
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-graduation-cap"></i></span><div><h3>Qualification</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row">
                      <div class="pm-form-field"><label class="pm-form-label">Qualification Level</label>
                        <select name="qualification_level" class="form-input form-input--select">
                          <option value="">Any</option>
                          <?php foreach (['certificate', 'diploma', 'degree', 'honours', 'other'] as $lv): ?>
                            <option value="<?= e($lv) ?>" <?= ($eligibility['qualification_level'] ?? '') === $lv ? 'selected' : '' ?>><?= e(ucfirst($lv)) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="pm-form-field"><label class="pm-form-label">Qualification Name</label><input type="text" name="qualification_name" class="form-input" value="<?= e($eligibility['qualification_name'] ?? '') ?>"></div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field"><label class="pm-form-label">Field of Study</label><input type="text" name="field_of_study" class="form-input" value="<?= e($eligibility['field_of_study'] ?? '') ?>"></div>
                      <div class="pm-form-field"><label class="pm-form-label">Institution Requirements</label><input type="text" name="institution_requirements" class="form-input" value="<?= e($eligibility['institution_requirements'] ?? '') ?>"></div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field"><label class="pm-form-label">Min Completion Year</label><input type="number" name="min_completion_year" class="form-input" value="<?= e($eligibility['min_completion_year'] ?? '') ?>"></div>
                      <div class="pm-form-field"><label class="pm-form-label">Max Completion Year</label><input type="number" name="max_completion_year" class="form-input" value="<?= e($eligibility['max_completion_year'] ?? '') ?>"></div>
                    </div>
                  </div>
                </div>
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-briefcase"></i></span><div><h3>Experience</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row">
                      <div class="pm-form-field"><label class="pm-form-label">Min Experience (years)</label><input type="number" name="min_experience" class="form-input" value="<?= e($eligibility['min_experience'] ?? '') ?>"></div>
                      <div class="pm-form-field"><label class="pm-form-label">Max Experience (years)</label><input type="number" name="max_experience" class="form-input" value="<?= e($eligibility['max_experience'] ?? '') ?>"></div>
                    </div>
                  </div>
                </div>
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-map-marker-alt"></i></span><div><h3>Location</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row">
                      <div class="pm-form-field"><label class="pm-form-label">Province</label><select name="province" class="form-input form-input--select"><option value="">Any</option><?php foreach (ALLOWED_PROVINCES as $pr): ?><option value="<?= e($pr) ?>" <?= ($eligibility['province'] ?? '') === $pr ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $pr))) ?></option><?php endforeach; ?></select></div>
                      <div class="pm-form-field"><label class="pm-form-label">City</label><input type="text" name="city" class="form-input" value="<?= e($eligibility['city'] ?? '') ?>"></div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Location Restrictions</label><textarea name="location_restrictions" class="form-input" rows="2"><?= e($eligibility['location_restrictions'] ?? '') ?></textarea></div>
                    </div>
                  </div>
                </div>
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-flag"></i></span><div><h3>Other Requirements</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row"><div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Availability</label><textarea name="availability" class="form-input" rows="2"><?= e($eligibility['availability'] ?? '') ?></textarea></div></div>
                    <div class="pm-form-row"><div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Citizenship / Residency</label><textarea name="citizenship_residency" class="form-input" rows="2"><?= e($eligibility['citizenship_residency'] ?? '') ?></textarea></div></div>
                    <div class="pm-form-row"><div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Programme-Specific Requirements</label><textarea name="programme_specific" class="form-input" rows="2"><?= e($eligibility['programme_specific'] ?? '') ?></textarea></div></div>
                  </div>
                </div>
                <div class="pm-form-actions"><button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Save Eligibility</button></div>
              </form>

              <div class="pm-panel-header" style="margin-top:2rem;">
                <h3 class="pm-panel-title">Skills</h3>
                <p class="pm-panel-sub">Required and preferred skills for this cohort.</p>
              </div>
              <form method="post" action="<?= url('admin/cohort_config_actions.php') ?>" class="pm-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="skills">
                <input type="hidden" name="id" value="<?= (int) $cohort['id'] ?>">
                <div class="pm-form-section">
                  <div class="pm-form-section__body">
                    <div class="pm-skills-categories">
                      <?php
                      $cats = [
                        'required_technical' => 'Required Technical Skills',
                        'preferred_technical' => 'Preferred Technical Skills',
                        'required_soft' => 'Required Soft Skills',
                      ];
                      foreach ($cats as $catKey => $catLabel):
                        $catSkills = array_values(array_filter($skills, fn($s) => $s['skill_category'] === $catKey));
                      ?>
                      <div class="pm-skills-cat">
                        <label class="pm-form-label"><?= e($catLabel) ?></label>
                        <?php foreach ($catSkills as $idx => $sk): ?>
                          <div class="pm-skill-row">
                            <input type="text" name="skills[<?= e($catKey) ?>][]" class="form-input" value="<?= e($sk['skill_name']) ?>" placeholder="Skill name">
                            <button type="button" class="btn btn--ghost btn--sm pm-skill-remove" aria-label="Remove"><i class="fas fa-times"></i></button>
                          </div>
                        <?php endforeach; ?>
                        <div class="pm-skill-row pm-skill-row--template" data-cat="<?= e($catKey) ?>" style="display:none;">
                          <input type="text" name="skills[<?= e($catKey) ?>][]" class="form-input" placeholder="Skill name">
                          <button type="button" class="btn btn--ghost btn--sm pm-skill-remove" aria-label="Remove"><i class="fas fa-times"></i></button>
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm pm-skill-add" data-cat="<?= e($catKey) ?>"><i class="fas fa-plus"></i> Add skill</button>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <div class="pm-form-actions"><button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Save Skills</button></div>
              </form>
            </div>

            <!-- DOCUMENTS -->
            <div class="pm-tab-panel" id="tab-documents">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Required Evidence / Documents</h3>
                <p class="pm-panel-sub">Configure which documents candidates must provide for this cohort.</p>
              </div>

              <div class="pm-docs-list">
                <?php if (empty($documents)): ?>
                  <div class="pm-empty pm-empty--sm">
                    <div class="pm-empty__icon"><i class="fas fa-file-alt"></i></div>
                    <h3>No eligibility requirements configured.</h3>
                    <p>Add required evidence documents below.</p>
                  </div>
                <?php else: foreach ($documents as $doc): ?>
                  <div class="pm-doc-item">
                    <div class="pm-doc-item__icon"><i class="fas fa-file-alt"></i></div>
                    <div class="pm-doc-item__info">
                      <strong><?= e($doc['document_name']) ?></strong>
                      <span class="pm-doc-item__tags">
                        <span class="tag <?= $doc['is_required'] ? 'tag--primary' : 'tag--muted' ?>"><?= $doc['is_required'] ? 'Required' : 'Optional' ?></span>
                        <span class="tag <?= $doc['verification_required'] ? 'tag--green' : 'tag--muted' ?>"><?= $doc['verification_required'] ? 'Verification Required' : 'No Verification' ?></span>
                        <?php if ($doc['expiry_required']): ?><span class="tag tag--amber">Expiry Required</span><?php endif; ?>
                      </span>
                    </div>
<form method="post" action="<?= url('admin/cohort_config_actions.php') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="document_delete">
                      <input type="hidden" name="id" value="<?= (int) $cohort['id'] ?>">
                      <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                      <button type="submit" class="btn btn--ghost btn--sm"><i class="fas fa-trash"></i> Remove</button>
                    </form>
                  </div>
                <?php endforeach; endif; ?>
              </div>

              <form method="post" action="<?= url('admin/cohort_config_actions.php') ?>" class="pm-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="document_add">
                <input type="hidden" name="id" value="<?= (int) $cohort['id'] ?>">
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-plus"></i></span><div><h3>Add Required Document</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row">
                      <div class="pm-form-field pm-form-field--full">
                        <label class="pm-form-label" for="document_name">Document Type <span class="pm-req">*</span></label>
                        <select id="document_name" name="document_name" class="form-input form-input--select">
                          <option value="CV">CV</option>
                          <option value="Qualification Certificate">Qualification Certificate</option>
                          <option value="ID Document">ID Document</option>
                          <option value="Proof of Residence">Proof of Residence</option>
                          <option value="Academic Transcript">Academic Transcript</option>
                          <option value="Other supporting document">Other supporting document</option>
                        </select>
                      </div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field">
                        <label class="pm-toggle"><input type="checkbox" name="is_required" value="1" checked><span class="pm-toggle__box"></span> Required</label>
                      </div>
                      <div class="pm-form-field">
                        <label class="pm-toggle"><input type="checkbox" name="verification_required" value="1"><span class="pm-toggle__box"></span> Verification Required</label>
                      </div>
                      <div class="pm-form-field">
                        <label class="pm-toggle"><input type="checkbox" name="expiry_required" value="1"><span class="pm-toggle__box"></span> Expiry Required</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="pm-form-actions"><button type="submit" class="btn btn--primary"><i class="fas fa-plus"></i> Add Document</button></div>
              </form>
            </div>

            <!-- WORKFLOW -->
            <div class="pm-tab-panel" id="tab-workflow">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Workflow Configuration</h3>
                <p class="pm-panel-sub">Configure which workflow stages are active for this cohort. This foundation powers future application, screening, assessment, interview, selection and onboarding modules.</p>
              </div>
              <form method="post" action="<?= url('admin/cohort_config_actions.php') ?>" class="pm-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="workflow">
                <input type="hidden" name="id" value="<?= (int) $cohort['id'] ?>">
                <div class="pm-form-section">
                  <div class="pm-form-section__body">
                    <div class="pm-workflow-list">
                      <?php foreach (COHORT_WORKFLOW_LABELS as $stage => $label):
                        $active = null;
                        foreach ($workflow as $wf) { if ($wf['stage'] === $stage) { $active = (int) $wf['is_active']; break; } }
                        $isOn = $active === null ? false : $active === 1;
                      ?>
                      <div class="pm-workflow-item">
                        <div class="pm-workflow-item__icon"><i class="fas fa-route"></i></div>
                        <div class="pm-workflow-item__info">
                          <strong><?= e($label) ?></strong>
                          <span><?= e(ucwords(str_replace('_', ' ', $stage))) ?></span>
                        </div>
                        <label class="pm-toggle">
                          <input type="checkbox" name="stages[]" value="<?= e($stage) ?>" <?= $isOn ? 'checked' : '' ?>>
                          <span class="pm-toggle__box"></span>
                        </label>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <div class="pm-form-actions"><button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Save Workflow</button></div>
              </form>
            </div>

            <!-- ACTIVITY -->
            <div class="pm-tab-panel" id="tab-activity">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Activity</h3>
                <p class="pm-panel-sub">Recent actions related to this cohort.</p>
              </div>
              <div class="pm-audit">
                <?php
                $audit = Database::fetchAll(
                    "SELECT * FROM audit_logs WHERE record_type = 'cohort' AND record_id = ? ORDER BY created_at DESC LIMIT 25",
                    'i',
                    [$id]
                );
                ?>
                <?php if (empty($audit)): ?>
                  <p class="pm-muted">No activity recorded for this cohort yet.</p>
                <?php else: foreach ($audit as $a): ?>
                  <div class="pm-audit__item">
                    <span class="pm-audit__dot"></span>
                    <div class="pm-audit__content">
                      <strong><?= e($a['action']) ?></strong>
                      <span><?= e($a['reason'] ?? '') ?></span>
                    </div>
                    <span class="pm-audit__time"><?= e(time_ago($a['created_at'])) ?></span>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            </div>

          </div>
        </section>

      </div>

      <footer class="dash-footer admin-footer"><div class="container"><div class="dash-footer__inner"><p>&copy; 2025 Investhood IT. All rights reserved.</p></div></div></footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
