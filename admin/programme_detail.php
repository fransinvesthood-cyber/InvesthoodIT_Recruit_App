<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Details Page
 * ================================================
 * Management workspace for a single programme:
 * stats, overview, cohorts, eligibility, documents
 * and workflow configuration.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$programme = Programme::find($id);
if (!$programme) {
    set_flash('error', 'Not Found', 'The requested programme does not exist.');
    safe_redirect('admin/programmes.php');
}

$user         = current_user();
$cohorts      = Cohort::forProgramme($id);
$participants = Programme::participantCount($id);
$applications = Programme::applicationCount($id);
$totalCapacity = (int) $programme['total_capacity'];
$availablePlaces = max(0, $totalCapacity - $participants);

$activeCohorts = 0;
foreach ($cohorts as $c) {
    if ($c['status'] === 'active') { $activeCohorts++; }
}

// Load existing programme-level eligibility for pre-populating the form
$eligibility = ProgrammeEligibility::forProgramme($id) ?? [];
$skills      = ProgrammeSkill::forProgramme($id);

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Programme Details - Investhood IT Administrator">
  <title><?= e($programme['name']) ?> | Investhood IT Admin</title>
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
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li></ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link active"><i class="fas fa-graduation-cap"></i> Programmes</a></li></ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile"></div>
          <div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin') ?></span><span class="sidebar__user-role">Administrator</span></div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <a href="<?= url('admin/programmes.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Programmes</a>
        </div>
        <div class="dash-header__right">
          <a href="<?= url('admin/programme_edit.php?id=' . $id) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i> Edit</a>
          <a href="<?= url('admin/programme_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Programme</a>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== PROGRAMME HEADER ===== -->
        <section class="pm-detail-header">
          <div class="pm-detail-header__main">
            <div class="pm-detail-header__top">
              <span class="pm-card__type"><?= e(programme_type_label($programme['type'])) ?></span>
              <?= status_badge($programme['status'], PROGRAMME_STATUS_LABELS[$programme['status']] ?? ucfirst($programme['status'])) ?>
            </div>
            <h1 class="pm-detail-header__title"><?= e($programme['name']) ?></h1>
            <p class="pm-detail-header__dates">
              <i class="fas fa-calendar-alt"></i>
              <?= e(!empty($programme['start_date']) ? format_date($programme['start_date'], 'd M Y') : 'Start TBD') ?>
              — <?= e(!empty($programme['end_date']) ? format_date($programme['end_date'], 'd M Y') : 'End TBD') ?>
            </p>
          </div>
          <div class="pm-detail-header__actions">
            <a href="<?= url('admin/cohort_create.php?programme_id=' . $id) ?>" class="btn btn--primary"><i class="fas fa-layer-group"></i> Create Cohort</a>
          </div>
        </section>

        <!-- ===== SUMMARY CARDS ===== -->
        <section class="pm-summary">
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) count($cohorts) ?></span><span class="pm-summary-card__label">Total Cohorts</span></div>
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $activeCohorts ?></span><span class="pm-summary-card__label">Active Cohorts</span></div>
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $totalCapacity ?></span><span class="pm-summary-card__label">Total Capacity</span></div>
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $participants ?></span><span class="pm-summary-card__label">Current Participants</span></div>
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $applications ?></span><span class="pm-summary-card__label">Applications</span></div>
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $availablePlaces ?></span><span class="pm-summary-card__label">Available Places</span></div>
        </section>

        <!-- ===== TABS ===== -->
        <section class="pm-workspace">
          <nav class="pm-tabs" id="pmTabs">
            <button class="pm-tab active" data-tab="overview"><i class="fas fa-th-large"></i> Overview</button>
            <button class="pm-tab" data-tab="cohorts"><i class="fas fa-layer-group"></i> Cohorts <span class="pm-tab__count"><?= (int) count($cohorts) ?></span></button>
            <button class="pm-tab" data-tab="eligibility"><i class="fas fa-clipboard-list"></i> Eligibility</button>
            <button class="pm-tab" data-tab="documents"><i class="fas fa-file-alt"></i> Required Evidence</button>
            <button class="pm-tab" data-tab="workflow"><i class="fas fa-route"></i> Workflow</button>
            <button class="pm-tab" data-tab="activity"><i class="fas fa-history"></i> Activity</button>
          </nav>

          <div class="pm-tab-panels">

            <!-- OVERVIEW PANEL -->
            <div class="pm-tab-panel is-active" id="tab-overview">
              <div class="pm-overview-grid">
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-info-circle"></i> Programme Overview</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($programme['description'] ?? 'No description provided.')) ?></p>
                </div>
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-bullseye"></i> Programme Objectives</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($programme['objectives'] ?? 'No objectives provided.')) ?></p>
                </div>
                <div class="pm-card-block pm-card-block--details">
                  <h3 class="pm-card-block__title"><i class="fas fa-list"></i> Programme Details</h3>
                  <dl class="pm-details-list">
                    <div><dt>Type</dt><dd><?= e(programme_type_label($programme['type'])) ?></dd></div>
                    <div><dt>Duration</dt><dd><?= e($programme['duration'] ?: '—') ?></dd></div>
                    <div><dt>Status</dt><dd><?= e(PROGRAMME_STATUS_LABELS[$programme['status']] ?? ucfirst($programme['status'])) ?></dd></div>
                    <div><dt>Start Date</dt><dd><?= e(!empty($programme['start_date']) ? format_date($programme['start_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>End Date</dt><dd><?= e(!empty($programme['end_date']) ? format_date($programme['end_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>Created</dt><dd><?= e(format_date($programme['created_at'], 'd M Y')) ?></dd></div>
                  </dl>
                </div>
              </div>
            </div>

            <!-- COHORTS PANEL -->
            <div class="pm-tab-panel" id="tab-cohorts">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Cohorts</h3>
                <a href="<?= url('admin/cohort_create.php?programme_id=' . $id) ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Cohort</a>
              </div>
              <?php if (empty($cohorts)): ?>
                <div class="pm-empty pm-empty--sm">
                  <div class="pm-empty__icon"><i class="fas fa-layer-group"></i></div>
                  <h3>No cohorts have been created for this programme yet.</h3>
                  <p>Create a cohort to begin configuring applications and capacity.</p>
                  <a href="<?= url('admin/cohort_create.php?programme_id=' . $id) ?>" class="btn btn--primary"><i class="fas fa-plus"></i> Create Cohort</a>
                </div>
              <?php else: ?>
                <div class="pm-cohort-list">
                  <?php foreach ($cohorts as $c):
                    $counts = Cohort::participantCounts((int) $c['id']);
                    $committed = $counts['selected'] + $counts['onboarded'] + $counts['active'];
                    $max = (int) $c['max_capacity'];
                    $pct = $max > 0 ? round(($committed / $max) * 100) : 0;
                  ?>
                  <div class="pm-cohort-card">
                    <div class="pm-cohort-card__top">
                      <div>
                        <h4 class="pm-cohort-card__name"><?= e($c['name']) ?></h4>
                        <span class="pm-cohort-card__meta"><i class="fas fa-map-marker-alt"></i> <?= e($c['location'] ?: ucfirst(str_replace('-', ' ', $c['province'] ?? 'On-site'))) ?> · <?= e(delivery_mode_label($c['delivery_mode'])) ?></span>
                      </div>
                      <?= status_badge($c['status'], COHORT_STATUS_LABELS[$c['status']] ?? ucfirst($c['status'])) ?>
                    </div>
                    <div class="pm-cohort-card__dates">
                      <span><i class="fas fa-calendar-alt"></i> <?= e(!empty($c['start_date']) ? format_date($c['start_date'], 'd M Y') : 'TBC') ?> — <?= e(!empty($c['end_date']) ? format_date($c['end_date'], 'd M Y') : 'TBC') ?></span>
                      <span><i class="fas fa-users"></i> Capacity <?= (int) $max ?></span>
                      <span><i class="fas fa-file-alt"></i> <?= (int) $c['applications_count'] ?> applications</span>
                    </div>
                    <div class="pm-cohort-card__capacity">
                      <div class="pm-cohort-card__capacity-head"><span>Capacity</span><span><?= $committed ?> / <?= $max ?> participants</span></div>
                      <div class="pm-progress"><div class="pm-progress__bar" style="width:<?= $pct ?>%"></div></div>
                    </div>
                    <div class="pm-cohort-card__actions">
                      <a href="<?= url('admin/cohort_detail.php?id=' . (int) $c['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i> View</a>
                      <a href="<?= url('admin/cohort_edit.php?id=' . (int) $c['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i> Edit</a>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- ELIGIBILITY PANEL -->
            <div class="pm-tab-panel" id="tab-eligibility">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Eligibility</h3>
                <p class="pm-panel-sub">Configure eligibility at programme level. Cohorts inherit and may override these.</p>
              </div>
              <form method="post" action="<?= url('admin/programme_eligibility_actions.php') ?>" class="pm-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="eligibility">
                <input type="hidden" name="id" value="<?= (int) $programme['id'] ?>">
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-graduation-cap"></i></span><div><h3>Qualification</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row">
                      <div class="pm-form-field"><label class="pm-form-label">Qualification Level</label>
<select name="qualification_level" class="form-input form-input--select">
                          <option value="">Any</option>
                          <?php foreach (['certificate', 'diploma', 'degree', 'honours', 'other'] as $lv): ?>
                            <option value="<?= e($lv) ?>" <?= (($eligibility['qualification_level'] ?? '') === $lv) ? 'selected' : '' ?>><?= e(ucfirst($lv)) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="pm-form-field"><label class="pm-form-label">Qualification Name</label><input type="text" name="qualification_name" class="form-input" placeholder="e.g. BSc Computer Science" value="<?= e($eligibility['qualification_name'] ?? '') ?>"></div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field"><label class="pm-form-label">Field of Study</label><input type="text" name="field_of_study" class="form-input" placeholder="e.g. Information Technology" value="<?= e($eligibility['field_of_study'] ?? '') ?>"></div>
                      <div class="pm-form-field"><label class="pm-form-label">Institution Requirements</label><input type="text" name="institution_requirements" class="form-input" placeholder="e.g. Accredited SAQA institution" value="<?= e($eligibility['institution_requirements'] ?? '') ?>"></div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field"><label class="pm-form-label">Min Completion Year</label><input type="number" name="min_completion_year" class="form-input" placeholder="e.g. 2019" value="<?= e($eligibility['min_completion_year'] ?? '') ?>"></div>
                      <div class="pm-form-field"><label class="pm-form-label">Max Completion Year</label><input type="number" name="max_completion_year" class="form-input" placeholder="e.g. 2025" value="<?= e($eligibility['max_completion_year'] ?? '') ?>"></div>
                    </div>
                  </div>
                </div>
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-briefcase"></i></span><div><h3>Experience</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row">
<div class="pm-form-field"><label class="pm-form-label">Minimum Experience (years)</label><input type="number" name="min_experience" class="form-input" placeholder="e.g. 0" value="<?= e($eligibility['min_experience'] ?? '') ?>"></div>
                      <div class="pm-form-field"><label class="pm-form-label">Maximum Experience (years)</label><input type="number" name="max_experience" class="form-input" placeholder="e.g. 2" value="<?= e($eligibility['max_experience'] ?? '') ?>"></div>
                    </div>
                  </div>
                </div>
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-map-marker-alt"></i></span><div><h3>Location</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row">
<div class="pm-form-field"><label class="pm-form-label">Province</label><select name="province" class="form-input form-input--select"><option value="">Any</option><?php foreach (ALLOWED_PROVINCES as $pr): ?><option value="<?= e($pr) ?>" <?= (($eligibility['province'] ?? '') === $pr) ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $pr))) ?></option><?php endforeach; ?></select></div>
                      <div class="pm-form-field"><label class="pm-form-label">City</label><input type="text" name="city" class="form-input" placeholder="e.g. Johannesburg" value="<?= e($eligibility['city'] ?? '') ?>"></div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Location Restrictions</label><textarea name="location_restrictions" class="form-input" rows="2"><?= e($eligibility['location_restrictions'] ?? '') ?></textarea></div>
                    </div>
                  </div>
                </div>
                <div class="pm-form-section">
                  <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-flag"></i></span><div><h3>Other Requirements</h3></div></div>
                  <div class="pm-form-section__body">
                    <div class="pm-form-row">
<div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Availability</label><textarea name="availability" class="form-input" rows="2" placeholder="e.g. Available immediately or within 1 month"><?= e($eligibility['availability'] ?? '') ?></textarea></div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Citizenship / Residency</label><textarea name="citizenship_residency" class="form-input" rows="2" placeholder="e.g. South African citizens or permanent residents"><?= e($eligibility['citizenship_residency'] ?? '') ?></textarea></div>
                    </div>
                    <div class="pm-form-row">
                      <div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Programme-Specific Requirements</label><textarea name="programme_specific" class="form-input" rows="2"><?= e($eligibility['programme_specific'] ?? '') ?></textarea></div>
                    </div>
                  </div>
                </div>
<div class="pm-form-actions">
                  <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Save Eligibility</button>
                </div>
              </form>

              <div class="pm-panel-header" style="margin-top:2rem;">
                <h3 class="pm-panel-title">Skills</h3>
                <p class="pm-panel-sub">Required and preferred skills for this programme. Cohorts inherit and may override these.</p>
              </div>
              <form method="post" action="<?= url('admin/programme_eligibility_actions.php') ?>" class="pm-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="skills">
                <input type="hidden" name="id" value="<?= (int) $programme['id'] ?>">
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

            <!-- DOCUMENTS PANEL (programme-level reference) -->
            <div class="pm-tab-panel" id="tab-documents">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Required Evidence</h3>
                <p class="pm-panel-sub">Evidence requirements are configured per cohort. Select a cohort to configure its required documents.</p>
              </div>
              <?php if (empty($cohorts)): ?>
                <div class="pm-empty pm-empty--sm">
                  <div class="pm-empty__icon"><i class="fas fa-file-alt"></i></div>
                  <h3>No cohorts available.</h3>
                  <p>Create a cohort first to configure its required evidence.</p>
                </div>
              <?php else: ?>
                <div class="pm-cohort-pick">
                  <p class="pm-panel-sub">Select a cohort to configure required evidence:</p>
                  <div class="pm-cohort-pick__list">
                    <?php foreach ($cohorts as $c): ?>
                      <a href="<?= url('admin/cohort_detail.php?id=' . (int) $c['id'] . '#documents') ?>" class="pm-cohort-pick__item"><i class="fas fa-layer-group"></i> <?= e($c['name']) ?></a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <!-- WORKFLOW PANEL -->
            <div class="pm-tab-panel" id="tab-workflow">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Activity / Workflow Configuration</h3>
                <p class="pm-panel-sub">Workflow stages are configured per cohort. Select a cohort to configure which workflow stages are active.</p>
              </div>
              <?php if (empty($cohorts)): ?>
                <div class="pm-empty pm-empty--sm">
                  <div class="pm-empty__icon"><i class="fas fa-route"></i></div>
                  <h3>No cohorts available.</h3>
                  <p>Create a cohort first to configure its workflow.</p>
                </div>
              <?php else: ?>
                <div class="pm-cohort-pick">
                  <p class="pm-panel-sub">Select a cohort to configure its workflow:</p>
                  <div class="pm-cohort-pick__list">
                    <?php foreach ($cohorts as $c): ?>
                      <a href="<?= url('admin/cohort_detail.php?id=' . (int) $c['id'] . '#workflow') ?>" class="pm-cohort-pick__item"><i class="fas fa-layer-group"></i> <?= e($c['name']) ?></a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <!-- ACTIVITY PANEL -->
            <div class="pm-tab-panel" id="tab-activity">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Activity</h3>
                <p class="pm-panel-sub">Recent actions related to this programme and its cohorts.</p>
              </div>
              <div class="pm-audit">
                <?php
                $audit = Database::fetchAll(
                    "SELECT * FROM audit_logs
                     WHERE (record_type = 'programme' AND record_id = ?)
                        OR (record_type = 'cohort' AND record_id IN (SELECT id FROM cohorts WHERE programme_id = ?))
                     ORDER BY created_at DESC LIMIT 25",
                    'ii',
                    [$id, $id]
                );
                ?>
                <?php if (empty($audit)): ?>
                  <p class="pm-muted">No activity recorded for this programme yet.</p>
                <?php else: foreach ($audit as $a): ?>
                  <div class="pm-audit__item">
                    <span class="pm-audit__dot"></span>
                    <div class="pm-audit__content">
                      <strong><?= e($a['action']) ?></strong>
                      <span><?= e($a['reason'] ?? '') ?> <span class="pm-audit__type">(<?= e($a['record_type'] ?? '') ?> #<?= (int) $a['record_id'] ?>)</span></span>
                    </div>
                    <span class="pm-audit__time"><?= e(time_ago($a['created_at'])) ?></span>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            </div>

          </div>
        </section>

      </div>

      <footer class="dash-footer admin-footer">
        <div class="container"><div class="dash-footer__inner"><p>&copy; 2025 Investhood IT. All rights reserved.</p></div></div>
      </footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
