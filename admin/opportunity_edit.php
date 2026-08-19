<?php
/**
 * ================================================
 * INVESTHOOD IT - Edit Opportunity Page
 * ================================================
 * Admin-only opportunity editing form. Pre-populated
 * with existing values, skills, eligibility,
 * documents and responsibilities.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$opportunity = Opportunity::find($id);
if (!$opportunity) {
    set_flash('error', 'Not Found', 'The requested opportunity does not exist.');
    safe_redirect('admin/opportunities.php');
}

$user   = current_user();
$old    = $_SESSION['opportunity_form_old'] ?? [];
$errors = $_SESSION['opportunity_form_errors'] ?? [];
unset($_SESSION['opportunity_form_old'], $_SESSION['opportunity_form_errors']);

$programmes = Programme::all();
$cohorts    = $opportunity['programme_id'] ? Cohort::forProgramme((int) $opportunity['programme_id']) : [];

// Load existing config for pre-population
$elig = OpportunityEligibility::forOpportunity($id) ?? [];
$skills = OpportunitySkill::forOpportunity($id);
$docs = OpportunityDocument::forOpportunity($id);
$responsibilities = OpportunityResponsibility::forOpportunity($id);

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Edit Opportunity - Investhood IT Administrator">
  <title>Edit Opportunity | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_opportunities.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard pm-module opp-module">

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
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link active"><i class="fas fa-briefcase"></i> Opportunities</a></li>
        </ul>
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
          <a href="<?= url('admin/opportunity_detail.php?id=' . $id) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="dash-header__right">
          <a href="<?= url('admin/opportunities.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-th-large"></i> Manage Opportunities</a>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">
        <section class="pm-hero">
          <div class="pm-hero__inner">
            <div class="pm-hero__text">
              <span class="section__badge">Opportunity Management</span>
              <h1 class="pm-hero__title">Edit Opportunity</h1>
              <p class="pm-hero__subtitle">Update the details of "<?= e($opportunity['title']) ?>".</p>
            </div>
            <?= status_badge($opportunity['status'], OPPORTUNITY_STATUS_LABELS[$opportunity['status']] ?? ucfirst(str_replace('_', ' ', $opportunity['status']))) ?>
          </div>
        </section>

        <?php if (!empty($errors['general'])): ?>
          <div class="pm-alert pm-alert--error"><i class="fas fa-exclamation-circle"></i> <?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('admin/opportunity_actions.php') ?>" class="pm-form" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int) $opportunity['id'] ?>">

          <!-- ===== BASIC INFORMATION ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-info-circle"></i></span><div><h3>Basic Information</h3><p>Core details about this opportunity.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="title">Opportunity Title <span class="pm-req">*</span></label>
                  <input type="text" id="title" name="title" class="form-input" value="<?= e($old['title'] ?? $opportunity['title']) ?>" required>
                  <?= field_error($errors, 'title') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="type">Opportunity Type <span class="pm-req">*</span></label>
                  <select id="type" name="type" class="form-input form-input--select">
                    <?php foreach (OPPORTUNITY_TYPE_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['type'] ?? $opportunity['type']) === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="organisation">Organisation / Host Employer</label>
                  <input type="text" id="organisation" name="organisation" class="form-input" value="<?= e($old['organisation'] ?? $opportunity['organisation']) ?>">
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="programme_id">Programme <span class="pm-req">*</span></label>
                  <select id="programme_id" name="programme_id" class="form-input form-input--select" required data-current-cohort="<?= (int) ($opportunity['cohort_id'] ?: 0) ?>">
                    <option value="">Select Programme</option>
                    <?php foreach ($programmes as $p): ?>
                      <option value="<?= (int) $p['id'] ?>" <?= ($old['programme_id'] ?? $opportunity['programme_id']) == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'programme_id') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="cohort_id">Cohort</label>
                  <select id="cohort_id" name="cohort_id" class="form-input form-input--select">
                    <option value="">No cohort (programme-wide)</option>
                    <?php foreach ($cohorts as $c): ?>
                      <option value="<?= (int) $c['id'] ?>" <?= ($old['cohort_id'] ?? $opportunity['cohort_id']) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'cohort_id') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="short_description">Short Description <span class="pm-req">*</span></label>
                  <textarea id="short_description" name="short_description" class="form-input" rows="3" maxlength="500"><?= e($old['short_description'] ?? $opportunity['short_description']) ?></textarea>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="full_description">Full Description</label>
                  <textarea id="full_description" name="full_description" class="form-input" rows="6"><?= e($old['full_description'] ?? $opportunity['full_description']) ?></textarea>
                </div>
              </div>
            </div>
          </section>

          <!-- ===== DATES ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-calendar-alt"></i></span><div><h3>Dates</h3><p>Application and programme dates.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="application_open_date">Application Opening Date <span class="pm-req">*</span></label>
                  <input type="date" id="application_open_date" name="application_open_date" class="form-input" value="<?= e($old['application_open_date'] ?? $opportunity['application_open_date']) ?>">
                  <?= field_error($errors, 'application_open_date') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="application_close_date">Application Closing Date <span class="pm-req">*</span></label>
                  <input type="date" id="application_close_date" name="application_close_date" class="form-input" value="<?= e($old['application_close_date'] ?? $opportunity['application_close_date']) ?>">
                  <?= field_error($errors, 'application_close_date') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="start_date">Programme Start Date</label>
                  <input type="date" id="start_date" name="start_date" class="form-input" value="<?= e($old['start_date'] ?? $opportunity['start_date']) ?>">
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="end_date">Programme End Date</label>
                  <input type="date" id="end_date" name="end_date" class="form-input" value="<?= e($old['end_date'] ?? $opportunity['end_date']) ?>">
                </div>
              </div>
              <div class="pm-alert pm-alert--info" id="cohortDateHint" style="display:none;"></div>
            </div>
          </section>

          <!-- ===== CAPACITY ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-users"></i></span><div><h3>Capacity</h3><p>Number of available positions and age range.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="available_positions">Number of Available Positions <span class="pm-req">*</span></label>
                  <input type="number" id="available_positions" name="available_positions" class="form-input" min="1" value="<?= e($old['available_positions'] ?? $opportunity['available_positions']) ?>">
                  <?= field_error($errors, 'available_positions') ?>
                  <?php $selected = Opportunity::selectedCount($id); if ($selected > 0): ?>
                    <div class="form-error"><?= (int) $selected ?> candidate(s) already selected — capacity cannot be reduced below this.</div>
                  <?php endif; ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="min_age">Minimum Age</label>
                  <input type="number" id="min_age" name="min_age" class="form-input" min="0" value="<?= e($old['min_age'] ?? $opportunity['min_age']) ?>">
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="max_age">Maximum Age</label>
                  <input type="number" id="max_age" name="max_age" class="form-input" min="0" value="<?= e($old['max_age'] ?? $opportunity['max_age']) ?>">
                </div>
              </div>
            </div>
          </section>

          <!-- ===== LOCATION ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-map-marker-alt"></i></span><div><h3>Location</h3><p>Where this opportunity is based.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="province">Province</label>
                  <select id="province" name="province" class="form-input form-input--select">
                    <option value="">Select Province</option>
                    <?php foreach (ALLOWED_PROVINCES as $pr): ?>
                      <option value="<?= e($pr) ?>" <?= ($old['province'] ?? $opportunity['province']) === $pr ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $pr))) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="city">City</label>
                  <input type="text" id="city" name="city" class="form-input" value="<?= e($old['city'] ?? $opportunity['city']) ?>">
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="physical_location">Physical Location</label>
                  <input type="text" id="physical_location" name="physical_location" class="form-input" value="<?= e($old['physical_location'] ?? $opportunity['physical_location']) ?>">
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="work_arrangement">Work Arrangement <span class="pm-req">*</span></label>
                  <select id="work_arrangement" name="work_arrangement" class="form-input form-input--select">
                    <?php foreach (OPPORTUNITY_WORK_ARRANGEMENT_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['work_arrangement'] ?? $opportunity['work_arrangement']) === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </section>

          <!-- ===== ELIGIBILITY ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-clipboard-list"></i></span><div><h3>Eligibility</h3><p>Opportunity-specific eligibility requirements.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Qualification Requirements</label><textarea name="qualification_requirements" class="form-input" rows="2"><?= e($old['qualification_requirements'] ?? $elig['qualification_requirements'] ?? '') ?></textarea></div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Required Skills</label><textarea name="required_skills" class="form-input" rows="2"><?= e($old['required_skills'] ?? $elig['required_skills'] ?? '') ?></textarea></div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Preferred Skills</label><textarea name="preferred_skills" class="form-input" rows="2"><?= e($old['preferred_skills'] ?? $elig['preferred_skills'] ?? '') ?></textarea></div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field"><label class="pm-form-label">Minimum Experience</label><input type="text" name="min_experience" class="form-input" value="<?= e($old['min_experience'] ?? $elig['min_experience'] ?? '') ?>" placeholder="e.g. 0-2 years"></div>
                <div class="pm-form-field"><label class="pm-form-label">Availability Requirements</label><input type="text" name="availability_requirements" class="form-input" value="<?= e($old['availability_requirements'] ?? $elig['availability_requirements'] ?? '') ?>" placeholder="e.g. Available immediately"></div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full"><label class="pm-form-label">Other Requirements</label><textarea name="other_requirements" class="form-input" rows="2"><?= e($old['other_requirements'] ?? $elig['other_requirements'] ?? '') ?></textarea></div>
              </div>
            </div>
          </section>

          <!-- ===== RESPONSIBILITIES ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-tasks"></i></span><div><h3>Responsibilities</h3><p>Key responsibilities, duties, activities and learning outcomes.</p></div></div>
            <div class="pm-form-section__body">
              <?php
              $respFields = [
                'key_responsibilities' => 'Key Responsibilities',
                'duties'               => 'Duties',
                'programme_activities' => 'Programme Activities',
                'learning_outcomes'    => 'Learning Outcomes',
              ];
              foreach ($respFields as $rk => $rl):
                $lines = [];
                foreach (($responsibilities[$rk] ?? []) as $item) { $lines[] = $item['content']; }
                $val = $old[$rk] ?? implode("\n", $lines);
              ?>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full"><label class="pm-form-label"><?= e($rl) ?></label><textarea name="<?= e($rk) ?>" class="form-input" rows="3"><?= e($val) ?></textarea></div>
              </div>
              <?php endforeach; ?>
            </div>
          </section>

          <!-- ===== APPLICATION INFORMATION ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-file-alt"></i></span><div><h3>Application Information</h3><p>Required and optional documents for applicants.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-docs-list" id="oppDocRows">
                <?php if (empty($docs)): ?>
                  <div class="pm-doc-item opp-doc-row">
                    <span class="pm-doc-item__icon"><i class="fas fa-file-alt"></i></span>
                    <div class="pm-doc-item__info">
                      <input type="text" name="document_name[]" class="form-input" value="CV" placeholder="Document name">
                      <div class="pm-doc-item__tags">
                        <label class="pm-toggle"><input type="radio" name="document_required[0]" value="1" checked><span class="pm-toggle__box"></span> Required</label>
                        <label class="pm-toggle"><input type="radio" name="document_required[0]" value="0"><span class="pm-toggle__box"></span> Optional</label>
                      </div>
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm opp-doc-remove" aria-label="Remove"><i class="fas fa-times"></i></button>
                  </div>
                <?php else: foreach ($docs as $idx => $d): ?>
                  <div class="pm-doc-item opp-doc-row">
                    <span class="pm-doc-item__icon"><i class="fas fa-file-alt"></i></span>
                    <div class="pm-doc-item__info">
                      <input type="text" name="document_name[]" class="form-input" value="<?= e($d['document_name']) ?>" placeholder="Document name">
                      <div class="pm-doc-item__tags">
                        <label class="pm-toggle"><input type="radio" name="document_required[<?= (int) $idx ?>]" value="1" <?= (int) $d['is_required'] === 1 ? 'checked' : '' ?>><span class="pm-toggle__box"></span> Required</label>
                        <label class="pm-toggle"><input type="radio" name="document_required[<?= (int) $idx ?>]" value="0" <?= (int) $d['is_required'] === 0 ? 'checked' : '' ?>><span class="pm-toggle__box"></span> Optional</label>
                      </div>
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm opp-doc-remove" aria-label="Remove"><i class="fas fa-times"></i></button>
                  </div>
                <?php endforeach; endif; ?>
              </div>
              <button type="button" class="btn btn--ghost btn--sm" id="oppAddDocRow"><i class="fas fa-plus"></i> Add Document</button>
            </div>
          </section>

          <!-- ===== STATUS ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-flag"></i></span><div><h3>Status</h3><p>Set the opportunity lifecycle status.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="status">Opportunity Status <span class="pm-req">*</span></label>
                  <select id="status" name="status" class="form-input form-input--select">
                    <?php foreach (OPPORTUNITY_STATUS_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['status'] ?? $opportunity['status']) === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'status') ?>
                </div>
              </div>
            </div>
          </section>

          <!-- ===== SKILL CATEGORIES ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-code"></i></span><div><h3>Skills</h3><p>Required and preferred skills for this opportunity.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-skills-categories">
                <?php
                $cats = [
                  'required_technical' => 'Required Technical Skills',
                  'preferred_technical' => 'Preferred Technical Skills',
                  'required_soft'       => 'Required Soft Skills',
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
          </section>

          <div class="pm-form-actions">
            <a href="<?= url('admin/opportunity_detail.php?id=' . $id) ?>" class="btn btn--ghost"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Save Changes</button>
          </div>
        </form>
      </div>

      <footer class="dash-footer admin-footer">
        <div class="container"><div class="dash-footer__inner"><p>&copy; 2025 Investhood IT. All rights reserved.</p></div></div>
      </footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <script src="<?= url('js/admin_opportunities.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
