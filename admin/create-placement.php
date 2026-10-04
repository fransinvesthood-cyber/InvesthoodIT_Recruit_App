<?php
/**
 * Investhood IT - Create Placement (Stage 12)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user = current_user();
$currentUserId = (int) (current_user_id() ?? 0);

// ---------------------------------------------------------------------------
// DATA REPAIR: re-sync applications.status with offers.status
//
// The offers table is the authoritative record of whether a candidate
// accepted. Older rows can hold offers.status = 'accepted' while the linked
// application is still parked on an earlier pipeline status, because the
// application-status sync in Selection::changeOfferStatus() only fires for a
// narrow set of "from" statuses. That desync makes a genuinely accepted
// candidate invisible here, since eligibility below gates on BOTH tables.
//
// Repair any such row before querying, so already-accepted candidates become
// selectable immediately instead of requiring a manual DB edit.
// Terminal-negative application statuses are never overwritten: an accepted
// offer must not resurrect a rejected/withdrawn/declined application.
// ---------------------------------------------------------------------------
try {
    Database::execute(
        "UPDATE applications
            SET status = 'offer_accepted', updated_at = NOW()
          WHERE status NOT IN ('offer_accepted', 'offer_declined', 'rejected', 'withdrawn')
            AND id IN (
                SELECT application_id
                  FROM offers
                 WHERE status = 'accepted'
                   AND application_id IS NOT NULL
            )"
    );
} catch (Throwable $ex) {
    error_log('[PLACEMENTS] offer/application resync: ' . $ex->getMessage());
}

// Fetch eligible candidates (offer_accepted)
$eligibleCandidates = [];
$programmes = [];
$supervisors = [];
$lookupError = null;
try {
// NOTE: do NOT add SELECT DISTINCT here. This query orders by u.created_at,
// which is not in the select list; MySQL rejects DISTINCT + ORDER BY on a
// non-selected column ("Expression #1 of ORDER BY clause is not in SELECT
// list ... incompatible with DISTINCT") and the failure surfaces as a generic
// "Database prepare error.". Rows are already unique per accepted offer
// (offer_id is selected), so DISTINCT was never needed.
$eligibleCandidates = Database::fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
           ap.application_reference, ap.id AS application_id,
           o.id as offer_id, o.programme_id as offer_programme_id, o.cohort_id as offer_cohort_id,
           pr.name as programme_name, c.name as cohort_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id AND r.slug = 'candidate'
    INNER JOIN applications ap ON ap.candidate_id = u.id
    INNER JOIN offers o ON o.application_id = ap.id
    LEFT JOIN programmes pr ON o.programme_id = pr.id
    LEFT JOIN cohorts c ON o.cohort_id = c.id
    WHERE o.status = 'accepted' AND u.status NOT IN ('suspended', 'disabled')
    AND u.id NOT IN (SELECT candidate_id FROM placements WHERE status NOT IN ('cancelled', 'withdrawn'))
    ORDER BY u.created_at DESC
");

$programmes = Database::fetchAll("SELECT id, name FROM programmes WHERE status = 'active' ORDER BY name");
$supervisors = Database::fetchAll("SELECT u.id, u.first_name, u.last_name, u.email FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status NOT IN ('suspended', 'disabled') AND r.slug != 'candidate' ORDER BY u.last_name, u.first_name");
} catch (Throwable $ex) {
    error_log('[PLACEMENTS] create lookups: ' . $ex->getMessage());
    $lookupError = $ex->getMessage();
}

$selectedCandidateId = (int)($_GET['candidate_id'] ?? 0);
$preSelectedCandidate = null;
if ($selectedCandidateId > 0) {
    foreach ($eligibleCandidates as $c) {
        if ((int)($c['id'] ?? 0) === $selectedCandidateId) {
            $preSelectedCandidate = $c;
            break;
        }
    }
}

$errors = [];
$success = false;
$createdPlacement = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidateId = (int)($_POST['candidate_id'] ?? 0);
    $programmeId = (int)($_POST['programme_id'] ?? 0);
    $cohortId = (int)($_POST['cohort_id'] ?? 0);
    $department = trim($_POST['department'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $supervisorId = (int)($_POST['supervisor_id'] ?? 0);
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $offerId = (int)($_POST['offer_id'] ?? 0);

    if (!$candidateId) $errors[] = 'Please select a candidate.';
    if (!$programmeId) $errors[] = 'Please select a programme.';
    if (!$cohortId) $errors[] = 'Please select a cohort.';
    if (!$startDate) $errors[] = 'Start date is required.';
    if (!$endDate) $errors[] = 'End date is required.';
    if ($startDate && $endDate && $endDate < $startDate) $errors[] = 'End date cannot be before start date.';
    if (!$offerId) $errors[] = 'Please select an offer.';

    if (empty($errors)) {
        require_csrf();
        // mysqli bind_param() with an 'i' type converts PHP null to 0, which would
        // violate placements.supervisor_id / created_by FKs. So when the optional
        // supervisor (or the acting admin id) is empty we run a variant of the
        // INSERT...SELECT that inlines NULL for that column instead of binding it.
        $useNullSupervisor = !($supervisorId > 0);
        $useNullCreatedBy = !($currentUserId > 0);
        try {
        $appCheck = Database::fetchOne("SELECT ap.id, ap.id AS application_id FROM applications ap JOIN offers o ON o.application_id = ap.id WHERE ap.candidate_id = ? AND o.id = ? AND o.status = 'accepted'", 'ii', [$candidateId, $offerId]);
        if (!$appCheck) {
            $errors[] = 'Candidate is no longer eligible.';
        } else {
            $existing = Database::fetchOne("SELECT id FROM placements WHERE candidate_id = ? AND status NOT IN ('cancelled', 'withdrawn')", 'i', [$candidateId]);
            if ($existing) {
                $errors[] = 'Candidate already has an active placement.';
            } else {
                $ref = '';
                do {
                    $ref = 'PLAC-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    $cntRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM placements WHERE placement_reference = ?", 's', [$ref]);
                    $cnt = (int)($cntRow['cnt'] ?? 0);
                } while ($cnt > 0);

                $supervisorParam = $supervisorId > 0 ? $supervisorId : 0;
                $deptParam = $department !== '' ? $department : null;
                $locParam = $location !== '' ? $location : null;
                $notesParam = $notes !== '' ? $notes : null;
                $createdByParam = $currentUserId > 0 ? $currentUserId : 0;
                $supervisorSql = $useNullSupervisor ? 'NULL' : '?';
                $createdBySql = $useNullCreatedBy ? 'NULL' : '?';
                $types = 'siiii' . 'ss' . ($useNullSupervisor ? '' : 'i') . 'sss' . ($useNullCreatedBy ? '' : 'i') . 'ii';
                $params = [$ref, $candidateId, $offerId, $programmeId, $cohortId, $deptParam, $locParam];
                if (!$useNullSupervisor) $params[] = $supervisorParam;
                $params[] = $startDate;
                $params[] = $endDate;
                $params[] = $notesParam;
                if (!$useNullCreatedBy) $params[] = $createdByParam;
                $params[] = $candidateId;
                $params[] = $offerId;
                $affected = Database::execute(
                    "INSERT INTO placements (placement_reference, candidate_id, application_id, offer_id, programme_id, cohort_id, department, location, supervisor_id, start_date, end_date, status, notes, created_by)
                     SELECT ?, ?, ap.id, ?, ?, ?, ?, ?, {$supervisorSql}, ?, ?, 'pending_placement', ?, {$createdBySql}
                     FROM applications ap JOIN offers o ON o.application_id = ap.id
                     WHERE ap.candidate_id = ? AND o.id = ? AND o.status = 'accepted'",
                    $types,
                    $params
                );

                if ($affected) {
                    $placementId = Database::lastInsertId();
                    $progRow = Database::fetchOne("SELECT name FROM programmes WHERE id = ?", 'i', [$programmeId]);
                    $progName = $progRow['name'] ?? '';
                    $histBy = $useNullCreatedBy ? 'NULL' : '?';
                    $histParams = [$placementId];
                    $histTypes = 'i';
                    if (!$useNullCreatedBy) { $histParams[] = $currentUserId; $histTypes .= 'i'; }

                    Database::execute("INSERT INTO placement_status_history (placement_id, field_name, previous_value, new_value, changed_by, change_reason) VALUES (?, 'status', NULL, 'pending_placement', {$histBy}, 'Placement created')", $histTypes, $histParams);
                    Database::execute("INSERT INTO placement_notifications (placement_id, candidate_id, sender_id, notification_type, title, message) VALUES (?, ?, {$histBy}, 'placement_created', 'Placement Created', ?)", $histTypes . 'is', array_merge([$placementId, $candidateId], (!$useNullCreatedBy ? [$currentUserId] : []), ["Your placement has been created. Programme: $progName. Start: $startDate. End: $endDate."]));
                    try { Database::execute("INSERT INTO audit_logs (user_id, action, record_type, record_id, reason, ip_address) VALUES ({$histBy}, 'Placement Created', 'placement', ?, 'Placement created', ?)", ($useNullCreatedBy ? '' : 'i') . 'is', array_merge((!$useNullCreatedBy ? [$currentUserId] : []), [$placementId, $_SERVER['REMOTE_ADDR'] ?? '::1'])); } catch (Throwable $auditEx) { error_log('[PLACEMENTS] audit: ' . $auditEx->getMessage()); }

                    $success = true;
                    $createdPlacement = ['id' => $placementId, 'reference' => $ref];
                    $_POST = [];
                } else {
                    $errors[] = 'Failed to create placement. Please try again.';
                }
            }
        }
    } catch (Throwable $ex) {
        error_log('[PLACEMENTS] create: ' . $ex->getMessage());
        $errors[] = 'Failed to create placement. Please try again.';
    }
    }
}

$cohorts = [];
try {
    $cohorts = Database::fetchAll("SELECT id, name FROM cohorts ORDER BY name LIMIT 200");
} catch (Throwable $ex) { $cohorts = []; }
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Placement | Investhood IT Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
<link rel="stylesheet" href="<?= url('css/admin_placements.css') ?>">
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
<div class="sidebar__section-label">Placements</div>
<ul class="sidebar__menu">
<li><a href="<?= url('admin/placements.php') ?>" class="sidebar__link"><i class="fas fa-building"></i> Placements</a></li>
<li><a href="<?= url('admin/create-placement.php') ?>" class="sidebar__link active"><i class="fas fa-plus"></i> New Placement</a></li>
</ul>
</nav>
</aside>
<main class="dashboard__main"><div class="dash-content">
<a href="<?= url('admin/placements.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Placements</a>
<h1 style="margin:1rem 0;">New Placement</h1>
<?php if (!empty($errors)): ?>
<div style="background:#fee2e2;border:1px solid #fecaca;padding:1rem;border-radius:10px;margin-bottom:1rem;">
<?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($success && $createdPlacement): ?>
<div style="background:#dcfce7;border:1px solid #bbf7d0;padding:1rem;border-radius:10px;margin-bottom:1rem;">
Placement <strong><?= e($createdPlacement['reference']) ?></strong> created.
<a href="<?= url('admin/placements.php?view=' . (int) $createdPlacement['id']) ?>">View it</a>
</div>
<?php endif; ?>
<?php if (empty($eligibleCandidates)): ?>
<div class="admin-empty-state"><div class="admin-empty-state__icon"><i class="fas fa-user-check"></i></div>
<h3>No eligible candidates</h3>
<?php if ($lookupError !== null): ?>
<p style="color:#b91c1c;">Could not load candidates because the database query failed. Please try again, or check the application error log.</p>
<p style="color:var(--text-light);font-size:.8rem;word-break:break-word;"><?= e($lookupError) ?></p>
<?php else: ?>
<p style="color:var(--text-light);">No candidates with an accepted offer are waiting for placement right now.</p>
<?php endif; ?></div>
<?php endif; ?>
<form method="post" action="<?= url('admin/create-placement.php') ?>" style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:1.5rem;">
<?= csrf_field() ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
<div><label class="pm-form-label" for="candidate_id">Candidate *</label>
<select id="candidate_id" name="candidate_id" class="form-input form-input--select" required>
<option value="">-- Select candidate --</option>
<?php foreach ($eligibleCandidates as $c): ?>
<option value="<?= (int) $c['id'] ?>" <?= ((int)($_POST['candidate_id'] ?? $selectedCandidateId) === (int)$c['id']) ? 'selected' : '' ?>><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?> - <?= e($c['email'] ?? '') ?></option>
<?php endforeach; ?>
</select></div>
<div><label class="pm-form-label" for="offer_id">Offer ID *</label>
<input id="offer_id" name="offer_id" class="form-input" value="<?= e($_POST['offer_id'] ?? '') ?>" placeholder="e.g. 12" required></div>
<div><label class="pm-form-label" for="programme_id">Programme *</label>
<select id="programme_id" name="programme_id" class="form-input form-input--select" required>
<option value="">-- Select programme --</option>
<?php foreach ($programmes as $pr): ?>
<option value="<?= (int) $pr['id'] ?>" <?= ((int)($_POST['programme_id'] ?? 0) === (int)$pr['id']) ? 'selected' : '' ?>><?= e($pr['name']) ?></option>
<?php endforeach; ?>
</select></div>
<div><label class="pm-form-label" for="cohort_id">Cohort *</label>
<select id="cohort_id" name="cohort_id" class="form-input form-input--select" required>
<option value="">-- Select cohort --</option>
<?php foreach ($cohorts as $co): ?>
<option value="<?= (int) $co['id'] ?>" <?= ((int)($_POST['cohort_id'] ?? 0) === (int)$co['id']) ? 'selected' : '' ?>><?= e($co['name']) ?></option>
<?php endforeach; ?>
</select></div>
<div><label class="pm-form-label" for="department">Department</label>
<input id="department" name="department" class="form-input" value="<?= e($_POST['department'] ?? '') ?>" placeholder="e.g. Software Development"></div>
<div><label class="pm-form-label" for="location">Location</label>
<input id="location" name="location" class="form-input" value="<?= e($_POST['location'] ?? '') ?>" placeholder="e.g. Sandton Campus"></div>
<div><label class="pm-form-label" for="supervisor_id">Supervisor</label>
<select id="supervisor_id" name="supervisor_id" class="form-input form-input--select">
<option value="">-- No supervisor --</option>
<?php foreach ($supervisors as $s): ?>
<option value="<?= (int) $s['id'] ?>" <?= ((int)($_POST['supervisor_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>><?= e(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))) ?> (<?= e($s['email'] ?? '') ?>)</option>
<?php endforeach; ?>
</select></div>
<div><label class="pm-form-label" for="start_date">Start Date *</label>
<input type="date" id="start_date" name="start_date" class="form-input" value="<?= e($_POST['start_date'] ?? '') ?>" required></div>
<div><label class="pm-form-label" for="end_date">End Date *</label>
<input type="date" id="end_date" name="end_date" class="form-input" value="<?= e($_POST['end_date'] ?? '') ?>" required></div>
<div style="grid-column:1/-1;"><label class="pm-form-label" for="notes">Notes (admin only)</label>
<textarea id="notes" name="notes" class="form-input" rows="3" placeholder="Internal placement notes"><?= e($_POST['notes'] ?? '') ?></textarea></div>
</div>
<div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.25rem;flex-wrap:wrap;">
<a href="<?= url('admin/placements.php') ?>" class="btn btn--ghost"><i class="fas fa-times"></i> Cancel</a>
<button type="submit" class="btn btn--primary"><i class="fas fa-plus"></i> Create Placement</button>
</div>
</form>
</div></main>
</div>
<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/admin_dashboard.js') ?>"></script>
<script>
// Auto-fill offer / programme / cohort when a candidate is picked.
(function () {
  var map = {};
  <?php foreach ($eligibleCandidates as $c): ?>
  map[<?= (int) $c['id'] ?>] = {
    offer_id: <?= (int) ($c['offer_id'] ?? 0) ?>,
    programme_id: <?= (int) ($c['offer_programme_id'] ?? 0) ?>,
    cohort_id: <?= (int) ($c['offer_cohort_id'] ?? 0) ?>
  };
  <?php endforeach; ?>
  var cand = document.getElementById('candidate_id');
  var offer = document.getElementById('offer_id');
  var prog = document.getElementById('programme_id');
  var coh = document.getElementById('cohort_id');
  if (!cand) return;
  cand.addEventListener('change', function () {
    var row = map[parseInt(cand.value, 10)];
    if (!row) return;
    if (offer && !offer.value) offer.value = row.offer_id || '';
    if (prog && (!prog.value || prog.value === '')) prog.value = row.programme_id || prog.value;
    if (coh && (!coh.value || coh.value === '')) coh.value = row.cohort_id || coh.value;
  });
  // Pre-select on load when ?candidate_id= is given.
  if (cand.value && map[parseInt(cand.value, 10)]) {
    var r = map[parseInt(cand.value, 10)];
    if (offer && !offer.value) offer.value = r.offer_id || '';
    if (prog && (!prog.value || prog.value === '')) prog.value = r.programme_id || prog.value;
    if (coh && (!coh.value || coh.value === '')) coh.value = r.cohort_id || coh.value;
  }
})();
</script>
<?= $flashes ?>
</body>
</html>


