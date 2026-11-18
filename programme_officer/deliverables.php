<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$flashes = render_flashes();
$currentPage = 'deliverables';
$pageTitle = 'Deliverables & Milestones';
$conn = Database::getConnection();

$officerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
if ($officerId <= 0) {
    http_response_code(403);
    exit('Invalid Programme Officer account.');
}

$scope = po_scope($conn, 'p', 'c');
$scopeMode = po_scope_mode($scope);
$scopeCondition = po_scope_condition($scope);
$tableResult = $conn->query("SHOW TABLES LIKE 'programme_deliverables'");
if (!$tableResult) {
    throw new RuntimeException('Unable to check deliverables schema: ' . $conn->error);
}
$moduleReady = $tableResult->num_rows > 0;
$submissionResult = $conn->query("SHOW TABLES LIKE 'deliverable_submissions'");
if (!$submissionResult) {
    throw new RuntimeException('Unable to check deliverable submissions schema: ' . $conn->error);
}
$moduleReady = $moduleReady && $submissionResult->num_rows > 0;
$milestoneResult = $conn->query("SHOW TABLES LIKE 'programme_milestones'");
if (!$milestoneResult) {
    throw new RuntimeException('Unable to check milestone schema: ' . $conn->error);
}
$milestonesReady = $milestoneResult->num_rows > 0;

$errors = [];
$searchValue = $_GET['search'] ?? '';
$filterValue = $_GET['status'] ?? '';
$programmeFilterValue = filter_var($_GET['programme_id'] ?? 0, FILTER_VALIDATE_INT);
$search = is_string($searchValue) ? trim($searchValue) : '';
$filter = is_string($filterValue) ? $filterValue : '';
$programmeFilter = $programmeFilterValue === false ? 0 : (int) $programmeFilterValue;
if (!in_array($filter, ['', 'awaiting', 'overdue', 'verified'], true)) {
    $filter = '';
}

$form = [
    'title' => '',
    'description' => '',
    'deliverable_type' => 'report',
    'programme_id' => 0,
    'cohort_id' => 0,
    'milestone_id' => 0,
    'due_date' => '',
];
$types = [
    'report' => 'Report',
    'assignment' => 'Assignment',
    'project' => 'Project',
    'assessment' => 'Assessment',
    'other' => 'Other',
];

$programmes = [];
$programmeSql = "
    SELECT DISTINCT p.id, p.name
    FROM programmes p
    LEFT JOIN cohorts c ON c.programme_id = p.id
    WHERE {$scopeCondition}
    ORDER BY p.name
";
$stmt = $conn->prepare($programmeSql);
if (!$stmt) {
    throw new RuntimeException('Unable to load programmes: ' . $conn->error);
}
$stmt->bind_param('i', $officerId);
$stmt->execute();
$programmes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$cohorts = [];
$cohortSql = "
    SELECT c.id, c.name, c.programme_id
    FROM cohorts c
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE {$scopeCondition}
    ORDER BY p.name, c.name
";
$stmt = $conn->prepare($cohortSql);
if (!$stmt) {
    throw new RuntimeException('Unable to load cohorts: ' . $conn->error);
}
$stmt->bind_param('i', $officerId);
$stmt->execute();
$cohorts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$milestones = [];
if ($milestonesReady) {
    $milestoneSql = "
        SELECT m.id, m.title, m.programme_id, m.cohort_id
        FROM programme_milestones m
        INNER JOIN programmes p ON p.id = m.programme_id
        LEFT JOIN cohorts c ON c.id = m.cohort_id
        WHERE {$scopeCondition}
        ORDER BY m.due_date, m.title
    ";
    $stmt = $conn->prepare($milestoneSql);
    if (!$stmt) {
        throw new RuntimeException('Unable to load milestones: ' . $conn->error);
    }
    $stmt->bind_param('i', $officerId);
    $stmt->execute();
    $milestones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $moduleReady) {
    $rawTitle = $_POST['title'] ?? '';
    $rawDescription = $_POST['description'] ?? '';
    $rawType = $_POST['deliverable_type'] ?? 'report';
    $rawDueDate = $_POST['due_date'] ?? '';
    $programmeIdValue = filter_var($_POST['programme_id'] ?? null, FILTER_VALIDATE_INT);
    $cohortIdValue = filter_var($_POST['cohort_id'] ?? null, FILTER_VALIDATE_INT);
    $milestoneIdValue = filter_var($_POST['milestone_id'] ?? null, FILTER_VALIDATE_INT);
    $form['title'] = is_string($rawTitle) ? trim($rawTitle) : '';
    $form['description'] = is_string($rawDescription) ? trim($rawDescription) : '';
    $form['deliverable_type'] = is_string($rawType) ? $rawType : '';
    $form['programme_id'] = $programmeIdValue === false ? 0 : (int) $programmeIdValue;
    $form['cohort_id'] = $cohortIdValue === false ? 0 : (int) $cohortIdValue;
    $form['milestone_id'] = $milestoneIdValue === false ? 0 : (int) $milestoneIdValue;
    $form['due_date'] = is_string($rawDueDate) ? trim($rawDueDate) : '';

    if (!validate_csrf()) {
        $errors[] = 'Your session validation failed. Refresh the page and try again.';
    }
    if (!is_string($rawTitle) || !is_string($rawDescription) || !is_string($rawType) || !is_string($rawDueDate)) {
        $errors[] = 'Deliverable fields must be plain text.';
    }
    if ($form['title'] === '' || mb_strlen($form['title']) > 190) {
        $errors[] = 'Enter a deliverable title (maximum 190 characters).';
    }
    if (mb_strlen($form['description']) > 5000) {
        $errors[] = 'The description must be 5,000 characters or fewer.';
    }
    if (!isset($types[$form['deliverable_type']])) {
        $errors[] = 'Choose a valid deliverable type.';
    }
    $dueDate = DateTime::createFromFormat('!Y-m-d', $form['due_date']);
    if (!$dueDate || $dueDate->format('Y-m-d') !== $form['due_date']) {
        $errors[] = 'Choose a valid due date.';
    }
    if ($form['programme_id'] <= 0) {
        $errors[] = 'Select a programme.';
    }
    if ($scopeMode === 'cohort' && $form['cohort_id'] <= 0) {
        $errors[] = 'Select one of your assigned cohorts for this deliverable.';
    }

    $validProgramme = false;
    if ($form['programme_id'] > 0) {
        $stmt = $conn->prepare("
            SELECT p.id
            FROM programmes p
            LEFT JOIN cohorts c ON c.programme_id = p.id
            WHERE p.id = ? AND {$scopeCondition}
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to validate programme scope: ' . $conn->error);
        }
        $stmt->bind_param('ii', $form['programme_id'], $officerId);
        $stmt->execute();
        $validProgramme = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$validProgramme) {
            $errors[] = 'Select a programme in your permitted scope.';
        }
    }

    if ($form['cohort_id'] > 0) {
        $stmt = $conn->prepare("
            SELECT c.id
            FROM cohorts c
            INNER JOIN programmes p ON p.id = c.programme_id
            WHERE c.id = ? AND c.programme_id = ? AND {$scopeCondition}
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to validate cohort scope: ' . $conn->error);
        }
        $stmt->bind_param('iii', $form['cohort_id'], $form['programme_id'], $officerId);
        $stmt->execute();
        $validCohort = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$validCohort) {
            $errors[] = 'Select a cohort in the selected programme and your permitted scope.';
        }
    }

    if ($form['milestone_id'] > 0) {
        if (!$milestonesReady) {
            $errors[] = 'Milestone linking is unavailable until programme milestones are installed.';
        } else {
            $stmt = $conn->prepare("
                SELECT m.id
                FROM programme_milestones m
                INNER JOIN programmes p ON p.id = m.programme_id
                LEFT JOIN cohorts c ON c.id = m.cohort_id
                WHERE m.id = ?
                  AND m.programme_id = ?
                  AND (m.cohort_id IS NULL OR m.cohort_id = ?)
                  AND {$scopeCondition}
                LIMIT 1
            ");
            if (!$stmt) {
                throw new RuntimeException('Unable to validate milestone scope: ' . $conn->error);
            }
            $stmt->bind_param('iiii', $form['milestone_id'], $form['programme_id'], $form['cohort_id'], $officerId);
            $stmt->execute();
            $validMilestone = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$validMilestone) {
                $errors[] = 'Select a milestone matching the programme, cohort and your permitted scope.';
            }
        }
    }

    if (!$errors) {
        $cohortId = $form['cohort_id'] > 0 ? $form['cohort_id'] : null;
        $milestoneId = $form['milestone_id'] > 0 ? $form['milestone_id'] : null;
        $description = $form['description'] !== '' ? $form['description'] : null;
        $stmt = $conn->prepare("
            INSERT INTO programme_deliverables
                (programme_id, cohort_id, milestone_id, title, description, deliverable_type, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare deliverable: ' . $conn->error);
        }
        $stmt->bind_param(
            'iiissssi',
            $form['programme_id'],
            $cohortId,
            $milestoneId,
            $form['title'],
            $description,
            $form['deliverable_type'],
            $form['due_date'],
            $officerId
        );
        $saved = $stmt->execute();
        $stmt->close();
        if (!$saved) {
            $errors[] = 'The deliverable could not be saved.';
        } else {
            set_flash('success', 'Deliverable created', 'The deliverable is now available to eligible cohort candidates.');
            safe_redirect('programme_officer/deliverables.php');
        }
    }
}

$rows = [];
$tableMissing = !$moduleReady;
$today = date('Y-m-d');
$stats = ['total' => 0, 'awaiting' => 0, 'overdue' => 0, 'verified' => 0];
if ($moduleReady && $scopeMode !== 'none') {
    $milestoneSelect = $milestonesReady
        ? 'm.title AS milestone_title, m.status AS milestone_status, m.progress AS milestone_progress'
        : 'NULL AS milestone_title, NULL AS milestone_status, NULL AS milestone_progress';
    $milestoneJoin = $milestonesReady
        ? 'LEFT JOIN programme_milestones m ON m.id = d.milestone_id'
        : '';
    $where = [$scopeCondition];
    $params = [$officerId];
    $bindTypes = 'i';
    if ($search !== '') {
        $where[] = '(d.title LIKE ? OR p.name LIKE ? OR c.name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
        $bindTypes .= 'sss';
    }
    if ($programmeFilter > 0) {
        $where[] = 'p.id = ?';
        $params[] = $programmeFilter;
        $bindTypes .= 'i';
    }
    $sql = "
        SELECT d.id, d.title, d.deliverable_type, d.due_date, d.status,
               p.name AS programme_name, c.name AS cohort_name,
               {$milestoneSelect},
               (SELECT COUNT(DISTINCT cp2.user_id)
                  FROM cohort_participants cp2
                  INNER JOIN cohorts c2 ON c2.id = cp2.cohort_id
                 WHERE c2.programme_id = d.programme_id
                   AND (d.cohort_id IS NULL OR c2.id = d.cohort_id)
                   AND cp2.status <> 'withdrawn') AS expected_count,
               (SELECT COUNT(DISTINCT s.candidate_id)
                  FROM deliverable_submissions s
                 WHERE s.deliverable_id = d.id AND s.status = 'submitted') AS awaiting_count,
               (SELECT COUNT(DISTINCT s.candidate_id)
                  FROM deliverable_submissions s
                 WHERE s.deliverable_id = d.id AND s.status = 'verified') AS verified_count
        FROM programme_deliverables d
        INNER JOIN programmes p ON p.id = d.programme_id
        LEFT JOIN cohorts c ON c.id = d.cohort_id
        {$milestoneJoin}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY d.due_date ASC, d.id DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to load deliverables: ' . $conn->error);
    }
    $stmt->bind_param($bindTypes, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['is_overdue'] = $row['status'] === 'open'
            && $row['due_date'] < $today
            && (int) $row['verified_count'] < (int) $row['expected_count'];
        $row['is_complete'] = (int) $row['expected_count'] > 0
            && (int) $row['verified_count'] >= (int) $row['expected_count'];
        $stats['total']++;
        if ((int) $row['awaiting_count'] > 0) {
            $stats['awaiting']++;
        }
        if ($row['is_overdue']) {
            $stats['overdue']++;
        }
        if ($row['is_complete']) {
            $stats['verified']++;
        }
    }
    unset($row);
}

if ($filter !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($filter): bool {
        return ($filter === 'awaiting' && (int) $row['awaiting_count'] > 0)
            || ($filter === 'overdue' && $row['is_overdue'])
            || ($filter === 'verified' && $row['is_complete']);
    }));
}

require __DIR__ . '/_layout_start.php';
?>
<div class="po-page-header">
    <div>
        <span class="po-page-header__eyebrow">Programme Delivery</span>
        <h2>Deliverables &amp; Milestones</h2>
        <p>Monitor commitments, verify submitted outputs and spot delayed items across your portfolio.</p>
    </div>
    <div class="po-page-header__actions">
        <a class="po-btn po-btn--secondary" href="<?= url('programme_officer/milestone_progress.php') ?>">
            <i class="fas fa-flag"></i> Milestone progress
        </a>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="po-alert"><i class="fas fa-circle-exclamation"></i><div><?= e($error) ?></div></div>
<?php endforeach; ?>

<?php if ($tableMissing): ?>
    <div class="po-alert"><i class="fas fa-circle-info"></i><div><strong>Module not installed.</strong><span>Run database/programme_deliverables.sql before using deliverables.</span></div></div>
<?php endif; ?>

<section class="po-stats po-stats--4">
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--blue"><i class="fas fa-list-check"></i></span><strong><?= number_format($stats['total']) ?></strong><span>Deliverables</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--orange"><i class="fas fa-inbox"></i></span><strong><?= number_format($stats['awaiting']) ?></strong><span>Awaiting verification</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--purple"><i class="fas fa-clock"></i></span><strong><?= number_format($stats['overdue']) ?></strong><span>Overdue</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--green"><i class="fas fa-circle-check"></i></span><strong><?= number_format($stats['verified']) ?></strong><span>Fully verified</span></article>
</section>

<?php if ($moduleReady): ?>
    <section class="po-card" style="margin-bottom:18px">
        <div class="po-card__header"><div><h3>Create a deliverable</h3><p>Assign a programme-wide or cohort-specific output and deadline.</p></div></div>
        <form method="post" class="po-activity-form" style="padding:18px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="po-activity-form__field po-activity-form__field--wide">
                <label for="deliverableTitle">Title</label>
                <input id="deliverableTitle" name="title" maxlength="190" required value="<?= e((string) $form['title']) ?>">
            </div>
            <div class="po-activity-form__field">
                <label for="deliverableProgramme">Programme</label>
                <select id="deliverableProgramme" name="programme_id" required>
                    <option value="">Select a programme</option>
                    <?php foreach ($programmes as $programme): ?>
                        <option value="<?= (int) $programme['id'] ?>" <?= (int) $form['programme_id'] === (int) $programme['id'] ? 'selected' : '' ?>><?= e($programme['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="po-activity-form__field">
                <label for="deliverableCohort">Cohort <?= $scopeMode === 'cohort' ? '' : '<span>(optional)</span>' ?></label>
                <select id="deliverableCohort" name="cohort_id" <?= $scopeMode === 'cohort' ? 'required' : '' ?>>
                    <?php if ($scopeMode !== 'cohort'): ?><option value="0">All cohorts</option><?php else: ?><option value="">Select a cohort</option><?php endif; ?>
                    <?php foreach ($cohorts as $cohort): ?>
                        <option value="<?= (int) $cohort['id'] ?>" data-programme="<?= (int) $cohort['programme_id'] ?>" <?= (int) $form['cohort_id'] === (int) $cohort['id'] ? 'selected' : '' ?>><?= e($cohort['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="po-activity-form__field">
                <label for="deliverableMilestone">Linked milestone <span>(optional)</span></label>
                <select id="deliverableMilestone" name="milestone_id">
                    <option value="0">No linked milestone</option>
                    <?php foreach ($milestones as $milestone): ?>
                        <option value="<?= (int) $milestone['id'] ?>" data-programme="<?= (int) $milestone['programme_id'] ?>" data-cohort="<?= (int) ($milestone['cohort_id'] ?? 0) ?>" <?= (int) $form['milestone_id'] === (int) $milestone['id'] ? 'selected' : '' ?>><?= e($milestone['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="po-activity-form__field">
                <label for="deliverableType">Type</label>
                <select id="deliverableType" name="deliverable_type">
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $form['deliverable_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="po-activity-form__field">
                <label for="deliverableDue">Due date</label>
                <input id="deliverableDue" type="date" name="due_date" required value="<?= e((string) $form['due_date']) ?>">
            </div>
            <div class="po-activity-form__field po-activity-form__field--wide">
                <label for="deliverableDescription">Instructions</label>
                <textarea id="deliverableDescription" name="description" rows="3" maxlength="5000"><?= e((string) $form['description']) ?></textarea>
            </div>
            <div class="po-activity-form__actions po-activity-form__field--wide">
                <button class="po-btn po-btn--primary" type="submit"><i class="fas fa-plus"></i> Create deliverable</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<form class="po-filter" method="get">
    <div class="po-field po-field--grow"><label for="deliverableSearch">Search</label>
        <div class="po-input-icon"><i class="fas fa-magnifying-glass"></i><input id="deliverableSearch" name="search" value="<?= e($search) ?>" placeholder="Deliverable, programme or cohort"></div>
    </div>
    <div class="po-field"><label for="deliverableProgrammeFilter">Programme</label>
        <select id="deliverableProgrammeFilter" name="programme_id"><option value="0">All programmes</option>
            <?php foreach ($programmes as $programme): ?>
                <option value="<?= (int) $programme['id'] ?>" <?= $programmeFilter === (int) $programme['id'] ? 'selected' : '' ?>><?= e($programme['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="po-field"><label for="deliverableStatusFilter">Show</label>
        <select id="deliverableStatusFilter" name="status"><option value="">All</option>
            <?php foreach (['awaiting' => 'Awaiting verification', 'overdue' => 'Overdue', 'verified' => 'Fully verified'] as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="po-filter__actions">
        <button class="po-btn po-btn--primary" type="submit"><i class="fas fa-filter"></i> Apply</button>
        <a class="po-btn po-btn--secondary" href="<?= url('programme_officer/deliverables.php') ?>">Reset</a>
    </div>
</form>

<div class="po-card">
    <div class="po-card__header"><div><h3>Deliverables</h3><p><?= number_format(count($rows)) ?> found.</p></div></div>
    <?php if (!$rows): ?>
        <div class="po-empty"><div class="po-empty__icon"><i class="fas fa-list-check"></i></div><strong>No deliverables found</strong><span>Nothing matched the filters or your scope.</span></div>
    <?php else: ?>
        <div class="po-table-wrap"><table class="po-table">
            <thead><tr><th>Deliverable</th><th>Due</th><th>Milestone</th><th>Verified</th><th>Awaiting</th><th>Progress</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $deliverable):
                $rate = po_completion_rate((int) $deliverable['expected_count'], (int) $deliverable['verified_count']); ?>
                <tr>
                    <td><div class="po-table-title"><span class="po-table-title__icon"><i class="fas fa-list-check"></i></span>
                        <div><strong><?= e($deliverable['title']) ?></strong>
                            <span><?= e($deliverable['programme_name']) ?> · <?= e($deliverable['cohort_name'] ?? 'All cohorts') ?><?= !empty($deliverable['milestone_title']) ? ' · ' . e($deliverable['milestone_title']) : '' ?></span>
                        </div>
                    </div></td>
                    <td><strong><?= e(po_date($deliverable['due_date'])) ?></strong>
                        <?php if ($deliverable['is_overdue']): ?><span class="po-table-sub" style="color:#b91c1c">Overdue</span><?php endif; ?>
                    </td>
                    <td><?php if (!empty($deliverable['milestone_title'])): ?>
                        <strong><?= e($deliverable['milestone_title']) ?></strong>
                        <span class="po-table-sub"><?= e(ucwords(str_replace('_', ' ', (string) $deliverable['milestone_status']))) ?> · <?= (int) $deliverable['milestone_progress'] ?>%</span>
                    <?php else: ?>—<?php endif; ?></td>
                    <td><?= (int) $deliverable['verified_count'] ?> / <?= (int) $deliverable['expected_count'] ?></td>
                    <td><?= (int) $deliverable['awaiting_count'] ?></td>
                    <td><div class="po-progress po-progress--table"><div class="po-progress__labels"><span>Verified</span><strong><?= $rate ?>%</strong></div><div class="po-progress__track"><span style="width:<?= $rate ?>%"></span></div></div></td>
                    <td class="po-table__action"><a class="po-icon-btn" href="<?= url('programme_officer/deliverable_view.php?id=' . (int) $deliverable['id']) ?>" aria-label="Review <?= e($deliverable['title']) ?>"><i class="fas fa-arrow-right"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<script>
(function () {
    var programme = document.getElementById('deliverableProgramme');
    var cohort = document.getElementById('deliverableCohort');
    var milestone = document.getElementById('deliverableMilestone');
    if (!programme) return;
    function filterOptions() {
        if (cohort) {
            Array.prototype.forEach.call(cohort.options, function (option) {
                if (!option.value || option.value === '0') return;
                var visible = programme.value !== '' && option.getAttribute('data-programme') === programme.value;
                option.hidden = !visible;
                if (!visible && option.selected) cohort.value = <?= $scopeMode === 'cohort' ? "''" : "'0'" ?>;
            });
        }
        if (milestone) {
            Array.prototype.forEach.call(milestone.options, function (option) {
                if (!option.value || option.value === '0') return;
                var programmeMatches = option.getAttribute('data-programme') === programme.value;
                var milestoneCohort = option.getAttribute('data-cohort') || '0';
                var cohortMatches = milestoneCohort === '0' || !cohort || cohort.value === milestoneCohort;
                option.hidden = !programmeMatches || !cohortMatches;
                if (option.hidden && option.selected) milestone.value = '0';
            });
        }
    }
    programme.addEventListener('change', filterOptions);
    if (cohort) cohort.addEventListener('change', filterOptions);
    filterOptions();
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
