<?php
/**
 * ================================================
 * INVESTHOOD IT - Create Opportunity Page
 * ================================================
 * Admin-only opportunity creation form with
 * detailed sections (basic info, dates, capacity,
 * location, eligibility, responsibilities, documents).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user   = current_user();
$old    = $_SESSION['opportunity_form_old'] ?? [];
$errors = $_SESSION['opportunity_form_errors'] ?? [];
unset($_SESSION['opportunity_form_old'], $_SESSION['opportunity_form_errors']);

// Preselected programme (from query string, e.g. linking from a programme)
$preselectProgramme = (int) ($_GET['programme_id'] ?? ($old['programme_id'] ?? 0));

$programmes = Programme::all();

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Create Opportunity - Investhood IT Administrator">
  <title>Create Opportunity | Investhood IT Admin</title>
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
          <a href="<?= url('admin/opportunities.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Opportunities</a>
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
              <h1 class="pm-hero__title">Create Opportunity</h1>
              <p class="pm-hero__subtitle">Set up a new opportunity linked to an existing programme and cohort. Save as a draft and publish later once full details are in place.</p>
            </div>
          </div>
        </section>

        <?php if (!empty($errors['general'])): ?>
          <div class="pm-alert pm-alert--error"><i class="fas fa-exclamation-circle"></i> <?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('admin/opportunity_actions.php') ?>" class="pm-form" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">

          <!-- ===== BASIC INFORMATION ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-info-circle"></i></span><div><h3>Basic Information</h3><p>Core details about this opportunity.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="title">Opportunity Title <span class="pm-req">*</span></label>
                  <input type="text" id="title" name="title" class="form-input" value="<?= e($old['title'] ?? '') ?>" placeholder="e.g. Graduate Software Developer" required>
                  <?= field_error($errors, 'title') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="type">Opportunity Type <span class="pm-req">*</span></label>
                  <select id="type" name="type" class="form-input form-input--select">
                    <?php foreach (OPPORTUNITY_TYPE_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['type'] ?? '') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'type') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="organisation">Organisation / Host Employer</label>
                  <input type="text" id="organisation" name="organisation" class="form-input" value="<?= e($old['organisation'] ?? '') ?>" placeholder="e.g. Investhood Technologies">
                  <?= field_error($errors, 'organisation') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="programme_id">Programme <span class="pm-req">*</span></label>
                  <select id="programme_id" name="programme_id" class="form-input form-input--select" required>
                    <option value="">Select Programme</option>
                    <?php foreach ($programmes as $p): ?>
                      <option value="<?= (int) $p['id'] ?>" <?= ($old['programme_id'] ?? $preselectProgramme) == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'programme_id') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="cohort_id">Cohort</label>
                  <select id="cohort_id" name="cohort_id" class="form-input form-input--select">
                    <option value="">No cohort (programme-wide)</option>
                  </select>
                  <?= field_error($errors, 'cohort_id') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="short_description">Short Description <span class="pm-req">*</span></label>
                  <textarea id="short_description" name="short_description" class="form-input" rows="3" maxlength="500" placeholder="A concise summary shown on the opportunity card."><?= e($old['short_description'] ?? '') ?></textarea>
                  <?= field_error($errors, 'short_description') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="full_description">Full Description</label>
                  <textarea id="full_description" name="full_description" class="form-input" rows="6" placeholder="Detailed description of the opportunity..."><?= e($old['full_description'] ?? '') ?></textarea>
                  <?= field_error($errors, 'full_description') ?>
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
                  <input type="date" id="application_open_date" name="application_open_date" class="form-input" value="<?= e($old['application_open_date'] ?? '') ?>">
                  <?= field_error($errors, 'application_open_date') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="application_close_date">Application Closing Date <span class="pm-req">*</span></label>
                  <input type="date" id="application_close_date" name="application_close_date" class="form-input" value="<?= e($old['application_close_date'] ?? '') ?>">
                  <?= field_error($errors, 'application_close_date') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="start_date">Programme Start Date</label>
                  <input type="date" id="start_date" name="start_date" class="form-input" value="<?= e($old['start_date'] ?? '') ?>">
                  <?= field_error($errors, 'start_date') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="end_date">Programme End Date</label>
                  <input type="date" id="end_date" name="end_date" class="form-input" value="<?= e($old['end_date'] ?? '') ?>">
                  <?= field_error($errors, 'end_date') ?>
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
                  <input type="number" id="available_positions" name="available_positions" class="form-input" min="1" value="<?= e($old['available_positions'] ?? '1') ?>">
                  <?= field_error($errors, 'available_positions') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="min_age">Minimum Age</label>
                  <input type="number" id="min_age" name="min_age" class="form-input" min="0" placeholder="e.g. 18" value="<?= e($old['min_age'] ?? '') ?>">
                  <?= field_error($errors, 'min_age') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="max_age">Maximum Age</label>
                  <input type="number" id="max_age" name="max_age" class="form-input" min="0" placeholder="e.g. 35" value="<?= e($old['max_age'] ?? '') ?>">
                  <?= field_error($errors, 'max_age') ?>
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
                      <option value="<?= e($pr) ?>" <?= ($old['province'] ?? '') === $pr ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $pr))) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'province') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="city">City</label>
                  <input type="text" id="city" name="city" class="form-input" value="<?= e($old['city'] ?? '') ?>" placeholder="e.g. Johannesburg">
                  <?= field_error($errors, 'city') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="physical_location">Physical Location</label>
                  <input type="text" id="physical_location" name="physical_location" class="form-input" value="<?= e($old['physical_location'] ?? '') ?>" placeholder="e.g. 12 Tech Park, Sandton">
                  <?= field_error($errors, 'physical_location') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="work_arrangement">Work Arrangement <span class="pm-req">*</span></label>
                  <select id="work_arrangement" name="work_arrangement" class="form-input form-input--select">
                    <?php foreach (OPPORTUNITY_WORK_ARRANGEMENT_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['work_arrangement'] ?? 'hybrid') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'work_arrangement') ?>
                </div>
              </div>
            </div>
          </section>

          <!-- ===== ELIGIBILITY ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-clipboard-list"></i></span><div><h3>Eligibility</h3><p>Opportunity-specific eligibility requirements.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="qualification_requirements">Qualification Requirements</label>
                  <textarea id="qualification_requirements" name="qualification_requirements" class="form-input" rows="2" placeholder="e.g. BSc in Computer Science or related field"><?= e($old['qualification_requirements'] ?? '') ?></textarea>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="required_skills">Required Skills</label>
                  <textarea id="required_skills" name="required_skills" class="form-input" rows="2" placeholder="Comma-separated list of required skills"><?= e($old['required_skills'] ?? '') ?></textarea>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="preferred_skills">Preferred Skills</label>
                  <textarea id="preferred_skills" name="preferred_skills" class="form-input" rows="2" placeholder="Comma-separated list of preferred skills"><?= e($old['preferred_skills'] ?? '') ?></textarea>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="min_experience">Minimum Experience</label>
                  <input type="text" id="min_experience" name="min_experience" class="form-input" value="<?= e($old['min_experience'] ?? '') ?>" placeholder="e.g. 0-2 years">
                  <?= field_error($errors, 'min_experience') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="availability_requirements">Availability Requirements</label>
                  <input type="text" id="availability_requirements" name="availability_requirements" class="form-input" value="<?= e($old['availability_requirements'] ?? '') ?>" placeholder="e.g. Available immediately">
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="other_requirements">Other Requirements</label>
                  <textarea id="other_requirements" name="other_requirements" class="form-input" rows="2" placeholder="Any other eligibility requirements"><?= e($old['other_requirements'] ?? '') ?></textarea>
                </div>
              </div>
            </div>
          </section>

          <!-- ===== RESPONSIBILITIES ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-tasks"></i></span><div><h3>Responsibilities</h3><p>Key responsibilities, duties, activities and learning outcomes.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="key_responsibilities">Key Responsibilities</label>
                  <textarea id="key_responsibilities" name="key_responsibilities" class="form-input" rows="4" placeholder="One responsibility per line..."><?= e($old['key_responsibilities'] ?? '') ?></textarea>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="duties">Duties</label>
                  <textarea id="duties" name="duties" class="form-input" rows="4" placeholder="One duty per line..."><?= e($old['duties'] ?? '') ?></textarea>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="programme_activities">Programme Activities</label>
                  <textarea id="programme_activities" name="programme_activities" class="form-input" rows="3" placeholder="Programme activities..."><?= e($old['programme_activities'] ?? '') ?></textarea>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="learning_outcomes">Learning Outcomes</label>
                  <textarea id="learning_outcomes" name="learning_outcomes" class="form-input" rows="3" placeholder="Expected learning outcomes..."><?= e($old['learning_outcomes'] ?? '') ?></textarea>
                </div>
              </div>
            </div>
          </section>

          <!-- ===== APPLICATION INFORMATION ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-file-alt"></i></span><div><h3>Application Information</h3><p>Required and optional documents for applicants.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-docs-list" id="oppDocRows">
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
                <div class="pm-doc-item opp-doc-row">
                  <span class="pm-doc-item__icon"><i class="fas fa-file-alt"></i></span>
                  <div class="pm-doc-item__info">
                    <input type="text" name="document_name[]" class="form-input" value="Qualification Certificate" placeholder="Document name">
                    <div class="pm-doc-item__tags">
                      <label class="pm-toggle"><input type="radio" name="document_required[1]" value="1" checked><span class="pm-toggle__box"></span> Required</label>
                      <label class="pm-toggle"><input type="radio" name="document_required[1]" value="0"><span class="pm-toggle__box"></span> Optional</label>
                    </div>
                  </div>
                  <button type="button" class="btn btn--ghost btn--sm opp-doc-remove" aria-label="Remove"><i class="fas fa-times"></i></button>
                </div>
                <div class="pm-doc-item opp-doc-row">
                  <span class="pm-doc-item__icon"><i class="fas fa-id-card"></i></span>
                  <div class="pm-doc-item__info">
                    <input type="text" name="document_name[]" class="form-input" value="ID" placeholder="Document name">
                    <div class="pm-doc-item__tags">
                      <label class="pm-toggle"><input type="radio" name="document_required[2]" value="1" checked><span class="pm-toggle__box"></span> Required</label>
                      <label class="pm-toggle"><input type="radio" name="document_required[2]" value="0"><span class="pm-toggle__box"></span> Optional</label>
                    </div>
                  </div>
                  <button type="button" class="btn btn--ghost btn--sm opp-doc-remove" aria-label="Remove"><i class="fas fa-times"></i></button>
                </div>
              </div>
              <button type="button" class="btn btn--ghost btn--sm" id="oppAddDocRow"><i class="fas fa-plus"></i> Add Document</button>
            </div>
          </section>

          <!-- ===== STATUS ===== -->
          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-flag"></i></span><div><h3>Status</h3><p>Start as a draft or publish immediately.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="status">Opportunity Status <span class="pm-req">*</span></label>
                  <select id="status" name="status" class="form-input form-input--select">
                    <option value="draft" <?= ($old['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= ($old['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                  </select>
                  <?= field_error($errors, 'status') ?>
                </div>
              </div>
            </div>
          </section>

          <!-- ===== SKILL CATEGORIES (relational) ===== -->
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
                ?>
                <div class="pm-skills-cat">
                  <label class="pm-form-label"><?= e($catLabel) ?></label>
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
            <a href="<?= url('admin/opportunities.php') ?>" class="btn btn--ghost"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Create Opportunity</button>
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
