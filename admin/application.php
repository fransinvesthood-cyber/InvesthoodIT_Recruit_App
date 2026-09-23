<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Application Detail Page
 * ================================================
 * Displays complete application information including
 * overview, candidate info, opportunity details,
 * eligibility, responses, documents, and status history.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user = current_user();

// Get application ID & action (view | manage)
$applicationId = (int) ($_GET['id'] ?? 0);
$action         = ($_GET['action'] ?? '') === 'manage' ? 'manage' : 'view';

if ($applicationId <= 0) {
    set_flash('error', 'Invalid Application', 'No application ID was provided.');
    safe_redirect('admin/applications.php');
}

// Fetch application details
$app = Application::adminFind($applicationId);

if (!$app) {
    set_flash('error', 'Application Not Found', 'The requested application does not exist.');
    safe_redirect('admin/applications.php');
}

// Fetch related data
$statusHistory = Application::statusHistory($applicationId);
$documents = Application::documents($applicationId);
$responses = Application::responses($applicationId);
$opportunityEligibility = !empty($app['opportunity_id']) ? Application::opportunityEligibility((int) $app['opportunity_id']) : null;

// Fetch candidate profile data
$candidateId = (int) ($app['candidate_id'] ?? 0);
$qualifications = Application::candidateQualifications($candidateId);
$skills = Application::candidateSkills($candidateId);
$workExperience = Application::candidateWorkExperience($candidateId);
$candidateDocuments = Application::candidateDocuments($candidateId);

// Build the back URL with preserved query parameters
$backParams = $_GET;
unset($backParams['id']);
$backUrl = url('admin/applications.php') . (!empty($backParams) ? '?' . http_build_query($backParams) : '');

$flashes = render_flashes();

// Helper function to display field value or placeholder
function field_value($value, $placeholder = '—') {
    return !empty($value) ? e($value) : '<span class="text-muted">' . $placeholder . '</span>';
}

// Helper function to format date
function format_app_date($date, $format = 'd M Y, H:i') {
    return !empty($date) ? e(format_date($date, $format)) : '<span class="text-muted">—</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Application Details - Investhood IT Administrator">
  <title>Application <?= e($app['application_reference']) ?> | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard app-module">

  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li>
        </ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link active"><i class="fas fa-file-alt"></i> Applications</a></li>
        </ul>
        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-history"></i> Audit Log</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=40" alt="Admin">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span>
            <span class="sidebar__user-role">Administrator</span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN ===== -->
    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
          </button>
          <div class="dash-header__search">
            <i class="fas fa-search"></i>
            <input type="text" class="dash-header__search-input" placeholder="Search..." aria-label="Search">
          </div>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <a href="<?= url('admin/applications.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Applications</a>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <!-- ===== MAIN CONTENT ===== -->
      <div class="dash-content">

      <?= $flashes ?>

      <!-- ===== REFERENCE BANNER ===== -->
      <div class="app-ref-banner">
        <div>
          <div class="app-ref-banner__ref"><?= e($app['application_reference']) ?></div>
          <div class="app-ref-banner__meta">Application Reference</div>
        </div>
        <div>
          <?php
          $badgeTone = Application::badgeTone($app['status']);
          $statusLabel = Application::label($app['status']);
          ?>
          <span class="app-status-badge app-status-badge--<?= e($badgeTone) ?>" style="font-size: 0.9rem; padding: 0.4rem 1rem;">
            <?= e($statusLabel) ?>
          </span>
        </div>
      </div>

      <!-- ===== DETAIL LAYOUT ===== -->
      <div class="app-detail">

        <!-- ===== MAIN COLUMN ===== -->
        <div class="app-detail__main">

          <!-- ===== APPLICATION OVERVIEW ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-info-circle"></i> Application Overview</h2>
            </div>
            <div class="app-overview-grid">
              <div class="app-overview-item">
                <span class="app-overview-item__label">Reference</span>
                <span class="app-overview-item__value"><?= e($app['application_reference']) ?></span>
              </div>
              <div class="app-overview-item">
                <span class="app-overview-item__label">Current Status</span>
                <span class="app-overview-item__value"><?= e(Application::label($app['status'])) ?></span>
              </div>
              <div class="app-overview-item">
                <span class="app-overview-item__label">Date Created</span>
                <span class="app-overview-item__value"><?= e(format_date($app['created_at'], 'd M Y H:i')) ?></span>
              </div>
              <div class="app-overview-item">
                <span class="app-overview-item__label">Date Submitted</span>
                <span class="app-overview-item__value"><?= !empty($app['submitted_at']) ? e(format_date($app['submitted_at'], 'd M Y H:i')) : '—' ?></span>
              </div>
              <div class="app-overview-item">
                <span class="app-overview-item__label">Last Updated</span>
                <span class="app-overview-item__value"><?= e(format_date($app['updated_at'], 'd M Y H:i')) ?></span>
              </div>
            </div>
          </section>

          <!-- ===== OPPORTUNITY INFORMATION ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-briefcase"></i> Opportunity Information</h2>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Opportunity</span>
              <span class="app-info-row__value"><?= e($app['opportunity_title']) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Programme</span>
              <span class="app-info-row__value"><?= e($app['programme_name']) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Cohort</span>
              <span class="app-info-row__value"><?= e($app['cohort_name'] ?? '—') ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Organisation</span>
              <span class="app-info-row__value"><?= e($app['organisation'] ?? '—') ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Closing Date</span>
              <span class="app-info-row__value"><?= !empty($app['application_close_date']) ? e(format_date($app['application_close_date'], 'd M Y')) : '—' ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Location</span>
              <span class="app-info-row__value"><?= field_value($app['opportunity_city'] ?? $app['city'] ?? null) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Work Arrangement</span>
              <span class="app-info-row__value"><?= field_value($app['work_arrangement'] ?? null) ?></span>
            </div>
            <?php if (!empty($app['short_description'])): ?>
            <div class="app-info-row">
              <span class="app-info-row__label">Description</span>
              <span class="app-info-row__value"><?= e($app['short_description']) ?></span>
            </div>
            <?php endif; ?>
          </section>

          <!-- ===== PERSONAL INFORMATION ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-id-card"></i> Personal Information</h2>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">First Name</span>
              <span class="app-info-row__value"><?= field_value($app['candidate_name'] ? explode(' ', $app['candidate_name'])[0] : null) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Last Name</span>
              <span class="app-info-row__value">
                <?php
                $nameParts = explode(' ', $app['candidate_name'] ?? '');
                echo field_value(count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null);
                ?>
              </span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Email</span>
              <span class="app-info-row__value"><?= field_value($app['candidate_email']) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Phone</span>
              <span class="app-info-row__value"><?= field_value($app['candidate_phone'] ?? null) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Address</span>
              <span class="app-info-row__value"><?= field_value($app['candidate_address'] ?? null) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">City</span>
              <span class="app-info-row__value"><?= field_value($app['profile_city'] ?? $app['candidate_city'] ?? null) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Province</span>
              <span class="app-info-row__value"><?= field_value($app['candidate_province'] ?? null) ?></span>
            </div>
          </section>

          <!-- ===== ELIGIBILITY ===== -->
          <?php if ($opportunityEligibility): ?>
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-check-circle"></i> Eligibility Requirements</h2>
            </div>
            <?php if (!empty($opportunityEligibility['qualification_level'])): ?>
            <div class="app-info-row">
              <span class="app-info-row__label">Required Qualification Level</span>
              <span class="app-info-row__value"><?= e(ucfirst($opportunityEligibility['qualification_level'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($opportunityEligibility['qualification_name'])): ?>
            <div class="app-info-row">
              <span class="app-info-row__label">Required Qualification</span>
              <span class="app-info-row__value"><?= e($opportunityEligibility['qualification_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($opportunityEligibility['field_of_study'])): ?>
            <div class="app-info-row">
              <span class="app-info-row__label">Field of Study</span>
              <span class="app-info-row__value"><?= e($opportunityEligibility['field_of_study']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($opportunityEligibility['availability'])): ?>
            <div class="app-info-row">
              <span class="app-info-row__label">Availability Requirement</span>
              <span class="app-info-row__value"><?= e($opportunityEligibility['availability']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($opportunityEligibility['citizenship_residency'])): ?>
            <div class="app-info-row">
              <span class="app-info-row__label">Citizenship/Residency</span>
              <span class="app-info-row__value"><?= e($opportunityEligibility['citizenship_residency']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($opportunityEligibility['programme_specific'])): ?>
            <div class="app-info-row">
              <span class="app-info-row__label">Additional Requirements</span>
              <span class="app-info-row__value"><?= e($opportunityEligibility['programme_specific']) ?></span>
            </div>
            <?php endif; ?>
          </section>
          <?php endif; ?>

          <!-- ===== APPLICATION QUESTIONS & RESPONSES ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-question-circle"></i> Application Questions</h2>
            </div>
            <?php if (empty($responses)): ?>
            <div class="app-empty">
              <p class="app-empty__text">No question responses recorded for this application.</p>
            </div>
            <?php else: ?>
              <?php
              $currentSection = null;
              foreach ($responses as $response):
                $section = $response['section'] ?? 'General';
                if ($section !== $currentSection):
                  $currentSection = $section;
              ?>
              <div class="app-question-section">
                <h3 class="app-question-section__title"><?= e(ucfirst(str_replace('_', ' ', $section))) ?></h3>
              </div>
              <?php endif; ?>
              <div class="app-question-block">
                <div class="app-question"><?= e($response['question_text']) ?></div>
                <div class="app-answer">
                  <strong>Candidate Response:</strong>
                  <p><?= nl2br(e($response['response'])) ?></p>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <!-- ===== EDUCATION & QUALIFICATIONS ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-graduation-cap"></i> Education & Qualifications</h2>
            </div>
            <?php if (empty($qualifications)): ?>
            <div class="app-empty">
              <p class="app-empty__text">No qualifications provided.</p>
            </div>
            <?php else: ?>
              <?php foreach ($qualifications as $qual): ?>
              <div class="app-qualification-block">
                <div class="app-qualification__title"><?= e($qual['name']) ?></div>
                <div class="app-qualification__details">
                  <?php if (!empty($qual['institution'])): ?>
                  <span><strong>Institution:</strong> <?= e($qual['institution']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($qual['level'])): ?>
                  <span><strong>Level:</strong> <?= e(ucfirst($qual['level'])) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($qual['year_completed'])): ?>
                  <span><strong>Year:</strong> <?= e($qual['year_completed']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <!-- ===== SKILLS ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-cogs"></i> Skills</h2>
            </div>
            <?php if (empty($skills['technical']) && empty($skills['soft'])): ?>
            <div class="app-empty">
              <p class="app-empty__text">No skills provided.</p>
            </div>
            <?php else: ?>
              <?php if (!empty($skills['technical'])): ?>
              <div class="app-skills-group">
                <h3 class="app-skills-group__title">Technical Skills</h3>
                <div class="app-skills-list">
                  <?php foreach ($skills['technical'] as $skill): ?>
                  <span class="app-skill-tag app-skill-tag--technical">
                    <?= e($skill['name']) ?>
                    <small>(<?= e($skill['proficiency']) ?>)</small>
                  </span>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
              <?php if (!empty($skills['soft'])): ?>
              <div class="app-skills-group">
                <h3 class="app-skills-group__title">Soft Skills</h3>
                <div class="app-skills-list">
                  <?php foreach ($skills['soft'] as $skill): ?>
                  <span class="app-skill-tag app-skill-tag--soft">
                    <?= e($skill['name']) ?>
                    <small>(<?= e($skill['proficiency']) ?>)</small>
                  </span>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
            <?php endif; ?>
          </section>

          <!-- ===== WORK EXPERIENCE ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-briefcase"></i> Work Experience</h2>
            </div>
            <?php if (empty($workExperience)): ?>
            <div class="app-empty">
              <p class="app-empty__text">No work experience provided.</p>
            </div>
            <?php else: ?>
              <?php foreach ($workExperience as $work): ?>
              <div class="app-work-block">
                <div class="app-work__header">
                  <div class="app-work__title"><?= e($work['job_title']) ?></div>
                  <div class="app-work__company"><?= e($work['company']) ?></div>
                </div>
                <div class="app-work__dates">
                  <?= e(format_date($work['start_date'], 'M Y')) ?> –
                  <?= !empty($work['is_current']) ? 'Present' : e(format_date($work['end_date'], 'M Y')) ?>
                </div>
                <?php if (!empty($work['description'])): ?>
                <div class="app-work__description">
                  <strong>Responsibilities:</strong>
                  <p><?= nl2br(e($work['description'])) ?></p>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <!-- ===== DOCUMENTS ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-file-alt"></i> Documents</h2>
            </div>
            <?php
            // Merge application-specific documents with candidate documents.
            // Tag each row with its origin table so the View/Download
            // handlers can fetch from the correct table even when both
            // tables use overlapping auto-increment IDs.
            foreach ($documents as &$d) { $d['__source'] = 'application'; }
            unset($d);
            foreach ($candidateDocuments as &$d) { $d['__source'] = 'candidate'; }
            unset($d);
            $allDocuments = array_merge($documents, $candidateDocuments);
            // Remove duplicates based on stored_filename (reused profile docs
            // share the same physical file — keep the application copy first).
            $uniqueDocs = [];
            foreach ($allDocuments as $doc) {
                $key = $doc['stored_filename'] ?? $doc['id'];
                if (!isset($uniqueDocs[$key])) {
                    $uniqueDocs[$key] = $doc;
                }
            }
            ?>
            <?php if (empty($uniqueDocs)): ?>
            <div class="app-empty">
              <p class="app-empty__text">No documents uploaded.</p>
            </div>
            <?php else: ?>
              <?php foreach ($uniqueDocs as $doc):
                $docSource = $doc['__source'] ?? 'application';
                $docId = (int) ($doc['id'] ?? 0);
                $viewUrl = url('admin/document_view.php?id=' . $docId . '&source=' . $docSource . '&token=' . csrf_token());
                $downloadUrl = url('admin/document_download.php?id=' . $docId . '&source=' . $docSource . '&token=' . csrf_token());
                $isPdf = strpos($doc['mime_type'] ?? '', 'pdf') !== false;
              ?>
              <div class="app-document-row">
                <div class="app-document-info">
                  <div class="app-document__icon">
                    <i class="fas fa-file-<?= $isPdf ? 'pdf' : 'alt' ?>"></i>
                  </div>
                  <div class="app-document__details">
                    <div class="app-document__name"><?= e($doc['original_filename'] ?? 'Document') ?></div>
                    <div class="app-document__meta">
                      <?= e(ucfirst($doc['document_type'] ?? 'Document')) ?> •
                      <?= e(round(($doc['file_size'] ?? 0) / 1024, 1)) ?> KB
                    </div>
                  </div>
                </div>
                <div class="app-document__actions">
                  <button type="button" class="btn btn--ghost btn--sm app-doc-view-btn" title="View document"
                    data-doc-view="<?= e($viewUrl) ?>"
                    data-doc-name="<?= e($doc['original_filename'] ?? 'Document') ?>"
                    data-doc-download="<?= e($downloadUrl) ?>">
                    <i class="fas fa-eye"></i> View
                  </button>
                  <a href="<?= e($downloadUrl) ?>" class="btn btn--ghost btn--sm" title="Download">
                    <i class="fas fa-download"></i> Download
                  </a>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <!-- ===== DOCUMENT VIEWER MODAL ===== -->
          <div class="app-doc-viewer" id="docViewerModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="docViewerTitle">
            <div class="app-doc-viewer__overlay" data-doc-viewer-close></div>
            <div class="app-doc-viewer__panel">
              <div class="app-doc-viewer__header">
                <div class="app-doc-viewer__title" id="docViewerTitle">
                  <i class="fas fa-file-alt"></i> <span id="docViewerName">Document</span>
                </div>
                <div class="app-doc-viewer__header-actions">
                  <a href="#" id="docViewerDownload" class="btn btn--ghost btn--sm" title="Download this document">
                    <i class="fas fa-download"></i> Download
                  </a>
                  <a href="#" id="docViewerNewTab" class="btn btn--ghost btn--sm" title="Open in new tab" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-external-link-alt"></i> New Tab
                  </a>
                  <button type="button" class="btn btn--ghost btn--sm" data-doc-viewer-close aria-label="Close viewer">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
              <div class="app-doc-viewer__body">
                <iframe id="docViewerFrame" title="Document viewer" src="" frameborder="0"></iframe>
              </div>
            </div>
          </div>

          <!-- ===== APPLICATION TIMELINE ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-history"></i> Application Timeline</h2>
            </div>
            <?php if (empty($statusHistory)): ?>
            <div class="app-empty">
              <p class="app-empty__text">No application history available.</p>
            </div>
            <?php else: ?>
            <div class="app-timeline">
              <?php foreach ($statusHistory as $index => $history): ?>
              <div class="app-timeline__item <?= $index === count($statusHistory) - 1 ? 'app-timeline__item--current' : '' ?>">
                <div class="app-timeline__marker">
                  <i class="fas fa-<?= $index === count($statusHistory) - 1 ? 'circle' : 'check-circle' ?>"></i>
                </div>
                <div class="app-timeline__content">
                  <div class="app-timeline__title">
                    <?= e(Application::label($history['new_status'])) ?>
                  </div>
                  <div class="app-timeline__date">
                    <?= e(format_date($history['created_at'], 'd M Y, H:i')) ?>
                  </div>
                  <?php if (!empty($history['changed_by_name'])): ?>
                  <div class="app-timeline__by">
                    by <?= e($history['changed_by_name']) ?>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty($history['change_reason'])): ?>
                  <div class="app-timeline__reason">
                    <?= e($history['change_reason']) ?>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </section>

        </div>
        <!-- ===== END MAIN COLUMN ===== -->

        <!-- ===== SIDEBAR ===== -->
        <div class="app-detail__sidebar">

          <!-- ===== APPLICATION STATUS ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-tag"></i> Application Status</h2>
            </div>
            <div class="app-status-display">
              <span class="app-status-badge app-status-badge--<?= e($badgeTone) ?>" style="font-size: 1rem; padding: 0.5rem 1.5rem;">
                <?= e($statusLabel) ?>
              </span>
            </div>

            <?php
            // Status transitions allowed from the current status.
            // Server-side Application::updateStatus() enforces the same rules.
            $validTransitions = [];
            foreach (Application::STATUSES as $candidateStatus) {
                if ($candidateStatus !== $app['status']
                    && Application::isValidTransition($app['status'], $candidateStatus)) {
                    $validTransitions[$candidateStatus] = Application::label($candidateStatus);
                }
            }
            ?>

            <?php if ($action === 'manage'): ?>
            <div class="app-status-hint">
              <i class="fas fa-cog"></i> Manage mode — update the application status below.
            </div>
            <?php endif; ?>

            <?php if (!empty($validTransitions)): ?>
            <form id="statusUpdateForm" class="app-status-update" data-application-id="<?= (int) $app['id'] ?>">
              <div class="form-group">
                <label for="newStatusSelect" class="form-label">New Status</label>
                <select id="newStatusSelect" class="form-input form-input--select" required>
                  <option value="">— Select new status —</option>
                  <?php foreach ($validTransitions as $statusValue => $statusText): ?>
                  <option value="<?= e($statusValue) ?>"><?= e($statusText) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="statusReason" class="form-label">Reason (optional)</label>
                <textarea id="statusReason" class="form-input" rows="2" placeholder="Why is the status changing?"></textarea>
              </div>
              <div id="statusMessage" class="alert" style="display: none;"></div>
              <button type="submit" id="statusSubmitBtn" class="btn btn--primary btn--block">
                <i class="fas fa-check"></i> Update Status
              </button>
            </form>
            <?php else: ?>
            <p class="app-status-final">
              This application is in a final state (<?= e($statusLabel) ?>) — no further status transitions are available.
            </p>
            <?php endif; ?>
          </section>

          <!-- ===== APPLICATION METADATA ===== -->
          <section class="app-section">
            <div class="app-section__header">
              <h2 class="app-section__title"><i class="fas fa-info"></i> Application Metadata</h2>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Reference</span>
              <span class="app-info-row__value"><?= e($app['application_reference']) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Status</span>
              <span class="app-info-row__value"><?= e($statusLabel) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Date Created</span>
              <span class="app-info-row__value"><?= format_app_date($app['created_at']) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Date Submitted</span>
              <span class="app-info-row__value"><?= format_app_date($app['submitted_at']) ?></span>
            </div>
            <div class="app-info-row">
              <span class="app-info-row__label">Last Updated</span>
              <span class="app-info-row__value"><?= format_app_date($app['updated_at']) ?></span>
            </div>
          </section>

          <!-- ===== BACK BUTTON ===== -->
          <section class="app-section">
            <a href="<?= $backUrl ?>" class="btn btn--primary btn--block">
              <i class="fas fa-arrow-left"></i> Back to Applications
            </a>
          </section>

        </div>
        <!-- ===== END SIDEBAR ===== -->

      </div>
      <!-- ===== END DETAIL LAYOUT ===== -->

      </div>
      <!-- ===== END MAIN CONTENT ===== -->

    </main>
  </div>

  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_applications.js') ?>"></script>
</body>
</html>