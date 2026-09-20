<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Selection Management (Stage 11)
 * ================================================
 * Lists ONLY candidates whose CURRENT application status is "selected"
 * and whose CURRENT (latest) interview is MARKED COMPLETED — an interview
 * still "scheduled" keeps the candidate off this page. Rows carry their
 * assessment/interview state, recorded selection decision and actions.
 * Accessible only by administrators.
 *
 * The page scope is enforced at query level inside
 * Selection::adminList() through the 'scope' => 'selected_completed'
 * filter below, so the row list, result count and pagination all
 * agree on the same scoped set.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user = current_user();

// ---- Search & filter state (server-side filtering) ----
$search       = trim($_GET['q'] ?? '');
// Normalise the status filter (trim + lowercase + legacy alias) so
// `Selected`, ` selected ` and the legacy `waitlisted` value all
// resolve to the canonical pipeline value. This keeps the dropdown
// highlight and the Selection::adminList() query in sync.
$fStatusRaw   = strtolower(trim((string) ($_GET['status'] ?? '')));
$fStatus      = $fStatusRaw === 'waitlisted' ? 'on_hold' : $fStatusRaw;
$fDecision    = strtolower(trim((string) ($_GET['decision'] ?? '')));
$fProgramme   = $_GET['programme'] ?? '';
$fCohort      = $_GET['cohort'] ?? '';
$fOpportunity = $_GET['opportunity'] ?? '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 20;

$filters = [
    'search'         => $search,
    'status'         => $fStatus,
    'decision'       => $fDecision,
    'programme_id'   => $fProgramme,
    'cohort_id'      => $fCohort,
    'opportunity_id' => $fOpportunity,
    // ---- Fixed page scope (enforced inside Selection::adminList()) ----
    // This page lists ONLY candidates whose CURRENT application status
    // is 'selected' AND whose CURRENT (latest) interview is marked
    // 'completed' — an interview still 'scheduled' keeps the candidate
    // off this page. The scope is applied to both the COUNT and LIST
    // queries so the result count and pagination stay truthful.
    'scope'          => 'selected_completed',
];

// Fetch selection records with pagination
$result     = Selection::adminList($filters, $page, $perPage);
$records    = $result['records'];
$totalCount = $result['total'];
$totalPages = $result['pages'];

// ---- Page scope note ----
// The scope of this page (current application status = 'selected' AND the
// CURRENT (latest) interview MARKED 'completed') is enforced at QUERY
// level inside Selection::adminList() via the 'scope' =>
// 'selected_completed' filter above. The returned rows, $totalCount and
// $totalPages therefore already reflect the scoped set — no post-query
// filtering (which would silently break pagination) is performed here.

// Stats + filter options
$stats          = Selection::dashboardStats();
$decisionCounts = Selection::decisionCounts();
$programmes     = Programme::all();
$cohorts        = Cohort::activeAll();
$opportunities  = Opportunity::all();

$flashes = render_flashes();

/**
 * Render the assessment-stage chip for a record.
 */
function sel_assessment_chip(array $record): string
{
    $stage = Selection::assessmentStage($record['status'] ?? null);
    if ($stage === 'completed') {
        return '<span class="sel-decision-badge sel-decision-badge--success"><i class="fas fa-check"></i> Completed</span>';
    }
    if ($stage === 'pending') {
        return '<span class="sel-decision-badge sel-decision-badge--amber"><i class="fas fa-hourglass-half"></i> Pending</span>';
    }
    return '<span class="sel-decision-badge sel-decision-badge--muted">—</span>';
}

/**
 * Render the interview status chip for a record.
 */
function sel_interview_chip(array $record): string
{
    $status = $record['interview_status'] ?? null;
    if (empty($status)) {
        return '<span class="sel-decision-badge sel-decision-badge--muted">No interview</span>';
    }
    $toneMap = ['completed' => 'success', 'cancelled' => 'danger', 'no_show' => 'danger'];
    $tone = $toneMap[$status] ?? 'primary';
    return '<span class="sel-decision-badge sel-decision-badge--' . e($tone) . '">'
        . e(Interview::label((string) $status)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Selection & Offers - Investhood IT Administrator">
  <title>Selection &amp; Offers | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_selection.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard app-module sel-module">

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
          <li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li>
          <li><a href="<?= url('admin/selection.php') ?>" class="sidebar__link active"><i class="fas fa-user-check"></i> Selection &amp; Offers</a></li>
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

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard__main">

      <!-- ===== HEADER ===== -->
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <a href="<?= url('admin/offers.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-file-signature"></i> Offer Management</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <!-- ===== CONTENT ===== -->
      <div class="dash-content">
        <?= $flashes ?>

        <!-- ===== HERO ===== -->
        <section class="app-hero">
          <div class="app-hero__inner">
            <div>
              <span class="section__badge">Selection &amp; Offers</span>
              <h1 class="app-hero__title">Selection Management</h1>
              <p class="app-hero__subtitle">
                Review candidates whose application status is selected and who have completed the interview stage.
                Record and review selection decisions here, then create and track offers for selected candidates.
              </p>
            </div>
            <div class="app-hero__actions">
              <a href="<?= url('admin/offers.php') ?>" class="btn btn--ghost btn--sm" style="background:rgba(255,255,255,0.15);border-color:rgba(255,255,255,0.35);color:#fff;">
                <i class="fas fa-file-signature"></i> View Offers
              </a>
            </div>
          </div>
        </section>

        <!-- ===== STATS ROW ===== -->
        <div class="app-stats">
          <div class="app-stat app-stat--total">
            <div class="app-stat__icon"><i class="fas fa-hourglass-half"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($stats['awaiting_decision'] ?? 0)) ?></span>
              <span class="app-stat__label">Awaiting Decision</span>
            </div>
          </div>
          <div class="app-stat app-stat--selected">
            <div class="app-stat__icon"><i class="fas fa-user-check"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($stats['selected'] ?? 0)) ?></span>
              <span class="app-stat__label">Selected</span>
            </div>
          </div>
          <div class="app-stat app-stat--interview">
            <div class="app-stat__icon"><i class="fas fa-user-clock"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($stats['waitlisted'] ?? 0)) ?></span>
              <span class="app-stat__label">Waitlisted</span>
            </div>
          </div>
          <div class="app-stat app-stat--rejected">
            <div class="app-stat__icon"><i class="fas fa-user-slash"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($stats['not_selected'] ?? 0)) ?></span>
              <span class="app-stat__label">Not Selected</span>
            </div>
          </div>
          <div class="app-stat app-stat--submitted">
            <div class="app-stat__icon"><i class="fas fa-file-signature"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($stats['offers_issued'] ?? 0)) ?></span>
              <span class="app-stat__label">Offers Issued</span>
            </div>
          </div>
          <div class="app-stat app-stat--draft">
            <div class="app-stat__icon"><i class="fas fa-file"></i></div>
            <div>
              <span class="app-stat__value"><?= number_format((int) ($stats['pending_offers'] ?? 0)) ?></span>
              <span class="app-stat__label">Pending (Draft) Offers</span>
            </div>
          </div>
        </div>

        <!-- ===== FILTERS ===== -->
        <form class="app-filters" data-auto-filters data-base="<?= e(url('admin/selection.php')) ?>">
          <div class="app-filters__search">
            <i class="fas fa-search"></i>
            <input type="text" class="app-filters__input" data-filter="q" placeholder="Search by reference, candidate, email or opportunity..." value="<?= e($search) ?>">
          </div>
          <select class="app-filters__select" data-filter="status">
            <option value="">All Application Statuses</option>
            <?php foreach (Selection::selectableStatuses() as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $fStatus === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="app-filters__select" data-filter="decision">
            <option value="">All Decisions</option>
            <option value="none" <?= $fDecision === 'none' ? 'selected' : '' ?>>Awaiting Decision</option>
            <?php foreach (Selection::DECISION_LABELS as $slug => $label): ?>
              <option value="<?= e($slug) ?>" <?= $fDecision === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="app-filters__select" data-filter="programme">
            <option value="">All Programmes</option>
            <?php foreach ($programmes as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= $fProgramme === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="app-filters__select" data-filter="cohort">
            <option value="">All Cohorts</option>
            <?php foreach ($cohorts as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $fCohort === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="app-filters__select" data-filter="opportunity">
            <option value="">All Opportunities</option>
            <?php foreach ($opportunities as $o): ?>
              <option value="<?= (int) $o['id'] ?>" <?= $fOpportunity === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn--ghost btn--sm" data-clear-filters><i class="fas fa-times"></i> Clear</button>
        </form>

        <!-- ===== RESULTS COUNT ===== -->
        <div class="app-results-count">
          <?php $shownPage = (int) $result['page']; ?>
          Showing <?= $totalCount > 0 ? (($shownPage - 1) * $perPage + 1) : 0 ?>–<?= min($shownPage * $perPage, $totalCount) ?> of <?= number_format($totalCount) ?> candidate<?= $totalCount !== 1 ? 's' : '' ?> selected with the interview marked completed
        </div>

        <!-- ===== SELECTION TABLE ===== -->
        <?php if (empty($records)): ?>
          <div class="app-empty">
            <div class="app-empty__icon"><i class="fas fa-user-check"></i></div>
            <h3 class="app-empty__title">No selected candidates with completed interviews</h3>
            <p class="app-empty__text">
              <?php if ($fStatus !== '' || $fDecision !== '' || $search !== '' || $fProgramme !== '' || $fCohort !== '' || $fOpportunity !== ''): ?>
                No candidates match the current filters.
                <?php if ($fStatus !== ''): ?> Application status filter: “<?= e(Application::label($fStatus)) ?>”.<?php endif; ?>
                This page only lists candidates whose application status is selected and who have completed the interview stage.
              <?php else: ?>
                No candidates are currently selected with a completed interview.
                Candidates appear here once their application status is selected and an interview is marked completed.
              <?php endif; ?>
            </p>
          </div>
        <?php else: ?>
          <div class="app-table-container">
            <table class="app-table">
              <thead>
                <tr>
                  <th>Candidate</th>
                  <th>Reference</th>
                  <th>Opportunity</th>
                  <th>Programme / Cohort</th>
                  <th>Assessment</th>
                  <th>Interview</th>
                  <th>Application Status</th>
                  <th>Decision</th>
                  <th>Last Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($records as $rec): ?>
                  <?php
                  $statusLabel = Application::label($rec['status']);
                  $statusTone  = Application::badgeTone($rec['status']);
                  $decision    = $rec['decision'] ?? null;
                  $offerStatus = $rec['offer_status'] ?? null;
                  ?>
                  <tr>
                    <td class="app-candidate" data-label="Candidate">
                      <div class="app-candidate__name"><?= e($rec['candidate_name']) ?></div>
                      <div class="app-candidate__email"><?= e($rec['candidate_email']) ?></div>
                    </td>
                    <td data-label="Reference">
                      <a href="<?= url('admin/application.php?id=' . (int) $rec['id']) ?>" class="app-ref-link"><?= e($rec['application_reference']) ?></a>
                    </td>
                    <td class="app-opportunity" data-label="Opportunity">
                      <div class="app-opportunity__title"><?= e($rec['opportunity_title'] ?? '—') ?></div>
                    </td>
                    <td class="app-opportunity" data-label="Programme / Cohort">
                      <div class="app-opportunity__title"><?= e($rec['programme_name'] ?? '—') ?></div>
                      <div class="app-opportunity__programme"><?= e($rec['cohort_name'] ?? '—') ?></div>
                    </td>
                    <td data-label="Assessment"><?= sel_assessment_chip($rec) ?></td>
                    <td data-label="Interview"><?= sel_interview_chip($rec) ?></td>
                    <td data-label="Application Status">
                      <span class="app-status-badge app-status-badge--<?= e($statusTone) ?>"><?= e($statusLabel) ?></span>
                    </td>
                    <td data-label="Decision">
                      <?php if ($decision): ?>
                        <span class="sel-decision-badge sel-decision-badge--<?= e(Selection::decisionBadgeTone($decision)) ?>">
                          <?= e(Selection::decisionLabel($decision)) ?>
                        </span>
                        <?php if ($offerStatus): ?>
                          <div style="margin-top:0.3rem;">
                            <span class="sel-decision-badge sel-decision-badge--<?= e(Selection::offerStatusBadgeTone($offerStatus)) ?>">
                              Offer: <?= e(Selection::offerStatusLabel($offerStatus)) ?>
                            </span>
                          </div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="sel-decision-badge sel-decision-badge--muted">Awaiting</span>
                      <?php endif; ?>
                    </td>
                    <td class="app-date" data-label="Last Updated"><?= e(format_date($rec['updated_at'], 'd M Y H:i')) ?></td>
                    <td class="app-actions" data-label="Actions">
                      <a href="<?= url('admin/application.php?id=' . (int) $rec['id']) ?>" class="btn btn--ghost btn--sm" title="View Application">
                        <i class="fas fa-eye"></i> Application
                      </a>
                      <a href="<?= url('admin/selection_decision.php?id=' . (int) $rec['id']) ?>" class="btn btn--primary btn--sm" title="Make / Review Selection">
                        <i class="fas fa-<?= $decision ? 'clipboard-check' : 'gavel' ?>"></i> <?= $decision ? 'Review' : 'Make Selection' ?>
                      </a>
                      <?php if ($decision === 'selected' && empty($rec['offer_id'])): ?>
                        <a href="<?= url('admin/offer_form.php?application=' . (int) $rec['id']) ?>" class="btn btn--outline btn--sm" title="Create Offer">
                          <i class="fas fa-file-signature"></i> Offer
                        </a>
                      <?php elseif (!empty($rec['offer_id'])): ?>
                        <a href="<?= url('admin/offer.php?id=' . (int) $rec['offer_id']) ?>" class="btn btn--outline btn--sm" title="View Offer">
                          <i class="fas fa-file-contract"></i> Offer
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- ===== PAGINATION ===== -->
          <?php if ($totalPages > 1): ?>
            <nav class="app-pagination">
              <?php
              // The query is already scoped (selected + completed
              // interview), so $result['page'] and $result['pages'] are
              // authoritative — no extra clamping needed.
              $page        = (int) $result['page'];
              $queryParams = $_GET;
              unset($queryParams['page']);
              $baseQuery = http_build_query($queryParams);
              $baseUrl   = url('admin/selection.php') . ($baseQuery ? '?' . $baseQuery . '&' : '?');
              ?>
              <?php if ($page > 1): ?>
                <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="app-pagination__link"><i class="fas fa-chevron-left"></i></a>
              <?php endif; ?>
              <?php
              $startPage = max(1, $page - 2);
              $endPage   = min($totalPages, $page + 2);
              if ($startPage > 1): ?>
                <a href="<?= $baseUrl ?>page=1" class="app-pagination__link">1</a>
                <?php if ($startPage > 2): ?><span class="app-pagination__ellipsis">…</span><?php endif; ?>
              <?php endif; ?>
              <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i === $page): ?>
                  <span class="app-pagination__current"><?= $i ?></span>
                <?php else: ?>
                  <a href="<?= $baseUrl ?>page=<?= $i ?>" class="app-pagination__link"><?= $i ?></a>
                <?php endif; ?>
              <?php endfor; ?>
              <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?><span class="app-pagination__ellipsis">…</span><?php endif; ?>
                <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="app-pagination__link"><?= $totalPages ?></a>
              <?php endif; ?>
              <?php if ($page < $totalPages): ?>
                <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="app-pagination__link"><i class="fas fa-chevron-right"></i></a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    </main>
  </div>

  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_selection.js') ?>"></script>
</body>
</html>