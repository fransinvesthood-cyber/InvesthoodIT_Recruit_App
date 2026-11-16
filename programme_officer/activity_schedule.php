<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$conn = Database::getConnection();
$officerId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($officerId <= 0) {
    http_response_code(403);
    exit('Invalid Programme Officer account.');
}

$currentPage = 'activity_schedule';
$pageTitle = 'Activity Schedule';
$flashes = render_flashes();
$errors = [];
$statuses = [
    'planned' => 'Planned',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'on_hold' => 'On Hold',
    'cancelled' => 'Cancelled',
];
$tableReady = po_has_column($conn, 'programme_activities', 'scheduled_date');
$scope = po_scope($conn, 'p', 'c');
$scopeMode = po_scope_mode($scope);
$scopeCondition = po_scope_condition($scope);

$programmes = [];
$staff = [];
$cohorts = [];
$activities = [];
$editing = [
    'id' => 0,
    'title' => '',
    'description' => '',
    'location' => '',
    'programme_id' => 0,
    'cohort_id' => 0,
    'scheduled_date' => '',
    'responsible_user_id' => 0,
    'status' => 'planned',
    'progress' => 0,
    'actual_completed_on' => null,
];
$stats = ['total' => 0, 'completed' => 0, 'overdue' => 0, 'on_time' => 0];

$stmt = $conn->prepare("
    SELECT DISTINCT p.id, p.name
    FROM programmes p
    LEFT JOIN cohorts c ON c.programme_id = p.id
    WHERE {$scopeCondition}
    ORDER BY p.name
");
if ($stmt) {
    $stmt->bind_param('i', $officerId);
    $stmt->execute();
    $programmes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $errors[] = 'Programmes could not be loaded.';
}

$stmt = $conn->prepare("
    SELECT c.id, c.name, c.programme_id
    FROM cohorts c
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE {$scopeCondition}
    ORDER BY c.name
");
if ($stmt) {
    $stmt->bind_param('i', $officerId);
    $stmt->execute();
    $cohorts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $errors[] = 'Cohorts could not be loaded.';
}

$staffResult = $conn->query("
    SELECT u.id, u.first_name, u.last_name, r.name AS role_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE u.status = 'active' AND r.slug <> 'candidate'
    ORDER BY u.first_name, u.last_name
");
if ($staffResult) {
    $staff = $staffResult->fetch_all(MYSQLI_ASSOC);
} else {
    $errors[] = 'Responsible personnel could not be loaded.';
}

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('validate_csrf') || !validate_csrf()) {
        $errors[] = 'Your session validation failed. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'save');
        $id = (int)($_POST['activity_id'] ?? 0);

        if ($action === 'delete') {
            $stmt = $conn->prepare("
                DELETE a
                FROM programme_activities a
                INNER JOIN programmes p ON p.id = a.programme_id
                LEFT JOIN cohorts c ON c.id = a.cohort_id
                WHERE a.id = ? AND {$scopeCondition}
            ");
            if ($stmt) {
                $stmt->bind_param('ii', $id, $officerId);
                $ok = $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();
                if ($ok && $deleted > 0) {
                    set_flash('success', 'Activity deleted', 'The scheduled activity was removed.');
                    safe_redirect('programme_officer/activity_schedule.php');
                }
            }
            $errors[] = 'The activity was not found in your programme scope or could not be deleted.';
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $programmeId = (int)($_POST['programme_id'] ?? 0);
            $cohortId = (int)($_POST['cohort_id'] ?? 0);
            $scheduledDate = trim((string)($_POST['scheduled_date'] ?? ''));
            $responsibleId = (int)($_POST['responsible_user_id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'planned');
            $progress = filter_var($_POST['progress'] ?? 0, FILTER_VALIDATE_INT);

            if ($title === '' || mb_strlen($title) > 180) {
                $errors[] = 'Enter an activity title (maximum 180 characters).';
            }
            if (mb_strlen($description) > 5000) {
                $errors[] = 'The description must be 5,000 characters or fewer.';
            }
            if (mb_strlen($location) > 180) {
                $errors[] = 'The location must be 180 characters or fewer.';
            }
            if ($programmeId <= 0) {
                $errors[] = 'Select a programme.';
            }
            if (!isset($statuses[$status])) {
                $errors[] = 'Choose a valid activity status.';
            }
            if ($progress === false || $progress < 0 || $progress > 100) {
                $errors[] = 'Progress must be a number from 0 to 100.';
            }
            $date = DateTime::createFromFormat('!Y-m-d', $scheduledDate);
            if (!$date || $date->format('Y-m-d') !== $scheduledDate) {
                $errors[] = 'Choose a valid scheduled date.';
            }
            if ($scopeMode === 'cohort' && $cohortId <= 0) {
                $errors[] = 'Select a cohort assigned to your account.';
            }

            $stmt = $conn->prepare("
                SELECT p.id
                FROM programmes p
                LEFT JOIN cohorts c ON c.programme_id = p.id
                WHERE p.id = ? AND {$scopeCondition}
                LIMIT 1
            ");
            $validProgramme = false;
            if ($stmt) {
                $stmt->bind_param('ii', $programmeId, $officerId);
                $stmt->execute();
                $validProgramme = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
            if (!$validProgramme) {
                $errors[] = 'You can only schedule activities for programmes in your permitted scope.';
            }

            if ($cohortId > 0) {
                $stmt = $conn->prepare("
                    SELECT c.id
                    FROM cohorts c
                    INNER JOIN programmes p ON p.id = c.programme_id
                    WHERE c.id = ? AND c.programme_id = ? AND {$scopeCondition}
                    LIMIT 1
                ");
                $validCohort = false;
                if ($stmt) {
                    $stmt->bind_param('iii', $cohortId, $programmeId, $officerId);
                    $stmt->execute();
                    $validCohort = (bool)$stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
                if (!$validCohort) {
                    $errors[] = 'Select a cohort belonging to the chosen programme and your permitted scope.';
                }
            }

            $stmt = $conn->prepare("
                SELECT u.id
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.id = ? AND u.status = 'active' AND r.slug <> 'candidate'
                LIMIT 1
            ");
            $validResponsible = false;
            if ($stmt) {
                $stmt->bind_param('i', $responsibleId);
                $stmt->execute();
                $validResponsible = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
            if (!$validResponsible) {
                $errors[] = 'Assign the activity to an active staff member.';
            }

            $existing = null;
            if ($id > 0 && $tableReady) {
                $stmt = $conn->prepare("
                    SELECT a.*
                    FROM programme_activities a
                    INNER JOIN programmes p ON p.id = a.programme_id
                    LEFT JOIN cohorts c ON c.id = a.cohort_id
                    WHERE a.id = ? AND {$scopeCondition}
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param('ii', $id, $officerId);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc() ?: null;
                    $stmt->close();
                }
                if (!$existing) {
                    $errors[] = 'You cannot edit this activity.';
                }
            }

            if ($progress !== false && $progress === 100) {
                $status = 'completed';
            }
            if ($status === 'completed') {
                $progress = 100;
            } elseif ($status === 'planned' && $progress !== false && $progress > 0) {
                $status = 'in_progress';
            }

            if (!$errors) {
                $cohortValue = $cohortId > 0 ? $cohortId : null;
                $descriptionValue = $description !== '' ? $description : null;
                $locationValue = $location !== '' ? $location : null;
                $completedOn = null;
                if ($status === 'completed') {
                    $completedOn = !empty($existing['actual_completed_on'])
                        ? (string)$existing['actual_completed_on']
                        : date('Y-m-d');
                }

                if ($id > 0) {
                    $stmt = $conn->prepare("
                        UPDATE programme_activities a
                        INNER JOIN programmes p ON p.id = a.programme_id
                        LEFT JOIN cohorts c ON c.id = a.cohort_id
                        SET a.programme_id = ?, a.cohort_id = ?, a.title = ?, a.description = ?, a.location = ?,
                            a.scheduled_date = ?, a.responsible_user_id = ?, a.status = ?, a.progress = ?,
                            a.actual_completed_on = ?
                        WHERE a.id = ? AND {$scopeCondition}
                    ");
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO programme_activities
                            (programme_id, cohort_id, title, description, location, scheduled_date,
                             responsible_user_id, status, progress, actual_completed_on, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                }

                if ($stmt) {
                    if ($id > 0) {
                        $stmt->bind_param(
                            'iissssisisii',
                            $programmeId,
                            $cohortValue,
                            $title,
                            $descriptionValue,
                            $locationValue,
                            $scheduledDate,
                            $responsibleId,
                            $status,
                            $progress,
                            $completedOn,
                            $id,
                            $officerId
                        );
                    } else {
                        $stmt->bind_param(
                            'iissssisisi',
                            $programmeId,
                            $cohortValue,
                            $title,
                            $descriptionValue,
                            $locationValue,
                            $scheduledDate,
                            $responsibleId,
                            $status,
                            $progress,
                            $completedOn,
                            $officerId
                        );
                    }
                    $saved = $stmt->execute();
                    $stmt->close();
                    if ($saved) {
                        set_flash('success', $id > 0 ? 'Activity updated' : 'Activity scheduled', 'Activity delivery details have been saved.');
                        safe_redirect('programme_officer/activity_schedule.php');
                    }
                }
                $errors[] = 'The activity could not be saved. Check the database migration and try again.';
            }
        }
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $errors
    && (string)($_POST['action'] ?? 'save') !== 'delete'
) {
    $editing = array_merge($editing, [
        'id' => (int)($_POST['activity_id'] ?? 0),
        'title' => (string)($_POST['title'] ?? ''),
        'description' => (string)($_POST['description'] ?? ''),
        'location' => (string)($_POST['location'] ?? ''),
        'programme_id' => (int)($_POST['programme_id'] ?? 0),
        'cohort_id' => (int)($_POST['cohort_id'] ?? 0),
        'scheduled_date' => (string)($_POST['scheduled_date'] ?? ''),
        'responsible_user_id' => (int)($_POST['responsible_user_id'] ?? 0),
        'status' => (string)($_POST['status'] ?? 'planned'),
        'progress' => (int)($_POST['progress'] ?? 0),
    ]);
}

if ($tableReady) {
    $editId = (int)($_GET['edit'] ?? 0);
    if (
        $editId > 0
        && !($_SERVER['REQUEST_METHOD'] === 'POST' && $errors)
    ) {
        $stmt = $conn->prepare("
            SELECT a.*
            FROM programme_activities a
            INNER JOIN programmes p ON p.id = a.programme_id
            LEFT JOIN cohorts c ON c.id = a.cohort_id
            WHERE a.id = ? AND {$scopeCondition}
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('ii', $editId, $officerId);
            $stmt->execute();
            $found = $stmt->get_result()->fetch_assoc();
            if ($found) {
                $editing = $found;
            }
            $stmt->close();
        }
    }

    $stmt = $conn->prepare("
        SELECT a.*, p.name AS programme_name, c.name AS cohort_name,
               u.first_name, u.last_name, r.name AS responsible_role
        FROM programme_activities a
        INNER JOIN programmes p ON p.id = a.programme_id
        LEFT JOIN cohorts c ON c.id = a.cohort_id
        INNER JOIN users u ON u.id = a.responsible_user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE {$scopeCondition}
        ORDER BY (a.status = 'completed') ASC, a.scheduled_date ASC, a.id DESC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $officerId);
        $stmt->execute();
        $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $errors[] = 'Scheduled activities could not be loaded.';
    }

    $today = date('Y-m-d');
    foreach ($activities as $activity) {
        $stats['total']++;
        if ($activity['status'] === 'completed') {
            $stats['completed']++;
            if (
                !empty($activity['actual_completed_on'])
                && $activity['actual_completed_on'] <= $activity['scheduled_date']
            ) {
                $stats['on_time']++;
            }
        } elseif (
            $activity['status'] !== 'cancelled'
            && $activity['scheduled_date'] < $today
        ) {
            $stats['overdue']++;
        }
    }
}

require __DIR__ . '/_layout_start.php';
?>
<div class="po-page-header po-activity-page-header">
    <div>
        <span class="po-page-header__eyebrow">Programme Delivery</span>
        <h2>Activity Schedule</h2>
        <p>Plan programme activities, assign responsible staff, and monitor delivery against scheduled dates.</p>
    </div>
    <a class="po-btn po-btn--primary" href="#activityForm"><i class="fas fa-plus"></i> Schedule activity</a>
</div>

<?php foreach ($errors as $error): ?>
    <div class="po-alert"><i class="fas fa-circle-exclamation"></i><div><?= e($error) ?></div></div>
<?php endforeach; ?>

<?php if (!$tableReady): ?>
    <div class="po-alert">
        <i class="fas fa-database"></i>
        <div><strong>Database setup required.</strong><span>Import <code>database/programme_activity_scheduling.sql</code> into the application database before using this module.</span></div>
    </div>
<?php endif; ?>

<section class="po-stats po-stats--4">
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--blue"><i class="fas fa-calendar-check"></i></span><strong><?= number_format($stats['total']) ?></strong><span>Scheduled activities</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--green"><i class="fas fa-check"></i></span><strong><?= number_format($stats['completed']) ?></strong><span>Completed</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--orange"><i class="fas fa-clock"></i></span><strong><?= number_format($stats['overdue']) ?></strong><span>Overdue</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--purple"><i class="fas fa-stopwatch"></i></span><strong><?= number_format($stats['on_time']) ?></strong><span>Completed on time</span></article>
</section>

<div class="po-activity-layout">
    <section class="po-card" id="activityForm">
        <div class="po-card__header"><div><h3><?= !empty($editing['id']) ? 'Edit scheduled activity' : 'Schedule an activity' ?></h3><p>Set a date, programme and delivery owner.</p></div></div>
        <form method="post" class="po-activity-form" action="<?= url('programme_officer/activity_schedule.php' . (!empty($editing['id']) ? '?edit=' . (int)$editing['id'] : '')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="activity_id" value="<?= (int)$editing['id'] ?>">
            <div class="po-activity-form__field po-activity-form__field--wide">
                <label for="activityTitle">Activity title</label>
                <input id="activityTitle" name="title" maxlength="180" required value="<?= e((string)$editing['title']) ?>" placeholder="e.g. Candidate onboarding workshop">
            </div>
            <div class="po-activity-form__field po-activity-form__field--wide">
                <label for="activityDescription">Description</label>
                <textarea id="activityDescription" name="description" rows="3" maxlength="5000" placeholder="Purpose, expected outcome or delivery notes"><?= e((string)($editing['description'] ?? '')) ?></textarea>
            </div>
            <div class="po-activity-form__field">
                <label for="activityProgramme">Programme</label>
                <select id="activityProgramme" name="programme_id" required>
                    <option value="">Select a programme</option>
                    <?php foreach ($programmes as $programme): ?>
                        <option value="<?= (int)$programme['id'] ?>" <?= (int)$editing['programme_id'] === (int)$programme['id'] ? 'selected' : '' ?>><?= e($programme['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="po-activity-form__field">
                <label for="activityCohort">Cohort <?= $scopeMode === 'cohort' ? '' : '<span>(optional)</span>' ?></label>
                <select id="activityCohort" name="cohort_id" <?= $scopeMode === 'cohort' ? 'required' : '' ?>>
                    <?php if ($scopeMode === 'cohort'): ?><option value="">Select a cohort</option><?php else: ?><option value="0">Programme-wide activity</option><?php endif; ?>
                    <?php foreach ($cohorts as $cohort): ?>
                        <option value="<?= (int)$cohort['id'] ?>" data-programme="<?= (int)$cohort['programme_id'] ?>" <?= (int)($editing['cohort_id'] ?? 0) === (int)$cohort['id'] ? 'selected' : '' ?>><?= e($cohort['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="po-activity-form__field">
                <label for="activityDate">Scheduled date</label>
                <input id="activityDate" type="date" name="scheduled_date" required value="<?= e((string)$editing['scheduled_date']) ?>">
            </div>
            <div class="po-activity-form__field">
                <label for="activityLocation">Location</label>
                <input id="activityLocation" name="location" maxlength="180" value="<?= e((string)($editing['location'] ?? '')) ?>" placeholder="Venue or online">
            </div>
            <div class="po-activity-form__field po-activity-form__field--wide">
                <label for="activityResponsible">Responsible personnel</label>
                <select id="activityResponsible" name="responsible_user_id" required>
                    <option value="">Assign an active staff member</option>
                    <?php foreach ($staff as $person): ?>
                        <option value="<?= (int)$person['id'] ?>" <?= (int)$editing['responsible_user_id'] === (int)$person['id'] ? 'selected' : '' ?>><?= e($person['first_name'] . ' ' . $person['last_name'] . ' — ' . $person['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="po-activity-form__field">
                <label for="activityStatus">Delivery status</label>
                <select id="activityStatus" name="status">
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $editing['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="po-activity-form__field">
                <label for="activityProgress">Progress (%)</label>
                <input id="activityProgress" type="number" name="progress" min="0" max="100" step="1" required value="<?= (int)$editing['progress'] ?>">
            </div>
            <div class="po-activity-form__actions po-activity-form__field--wide">
                <button class="po-btn po-btn--primary" type="submit" <?= $tableReady ? '' : 'disabled' ?>><i class="fas fa-floppy-disk"></i> <?= !empty($editing['id']) ? 'Save changes' : 'Schedule activity' ?></button>
                <?php if (!empty($editing['id'])): ?><a class="po-btn po-btn--secondary" href="<?= url('programme_officer/activity_schedule.php') ?>">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="po-card">
        <div class="po-card__header"><div><h3>Programme activities</h3><p><?= count($activities) ?> activity<?= count($activities) === 1 ? '' : 'ies' ?> in your permitted programme scope</p></div></div>
        <?php if (!$tableReady): ?>
            <div class="po-empty"><div class="po-empty__icon"><i class="fas fa-database"></i></div><strong>Waiting for database setup</strong><span>Import the activity scheduling migration to begin tracking delivery.</span></div>
        <?php elseif (!$activities): ?>
            <div class="po-empty"><div class="po-empty__icon"><i class="fas fa-calendar-plus"></i></div><strong>No activities scheduled</strong><span>Use the form to schedule the first programme activity.</span></div>
        <?php else: ?>
            <div class="po-table-wrap">
                <table class="po-table po-activity-table">
                    <thead><tr><th>Activity</th><th>Programme / Cohort</th><th>Scheduled</th><th>Responsible</th><th>Delivery</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($activities as $activity):
                        $isOverdue = $activity['status'] !== 'completed' && $activity['status'] !== 'cancelled' && $activity['scheduled_date'] < date('Y-m-d');
                        $onTime = $activity['status'] === 'completed' && !empty($activity['actual_completed_on']) && $activity['actual_completed_on'] <= $activity['scheduled_date'];
                        $statusClass = $activity['status'] === 'completed' ? 'active' : ($isOverdue ? 'danger' : (in_array($activity['status'], ['planned', 'on_hold'], true) ? 'warning' : 'info'));
                    ?>
                        <tr>
                            <td><strong><?= e($activity['title']) ?></strong><?php if (!empty($activity['description'])): ?><span class="po-table-sub"><?= e($activity['description']) ?></span><?php endif; ?><?php if (!empty($activity['location'])): ?><span class="po-table-sub"><i class="fas fa-location-dot"></i> <?= e($activity['location']) ?></span><?php endif; ?></td>
                            <td><?= e($activity['programme_name']) ?><span class="po-table-sub"><?= e($activity['cohort_name'] ?: 'Programme-wide') ?></span></td>
                            <td><?= e(po_date($activity['scheduled_date'])) ?><?php if ($isOverdue): ?><span class="po-activity-flag">Overdue</span><?php elseif ($activity['status'] === 'completed'): ?><span class="po-table-sub"><?= $onTime ? 'Completed on time' : 'Completed late' ?><?= !empty($activity['actual_completed_on']) ? ' · ' . e(po_date($activity['actual_completed_on'])) : '' ?></span><?php endif; ?></td>
                            <td><?= e($activity['first_name'] . ' ' . $activity['last_name']) ?><span class="po-table-sub"><?= e($activity['responsible_role']) ?></span></td>
                            <td><span class="po-status po-status--<?= e($statusClass) ?>"><?= e($statuses[$activity['status']] ?? po_status_label($activity['status'])) ?></span><div class="po-progress po-progress--table"><div class="po-progress__labels"><span>Progress</span><strong><?= (int)$activity['progress'] ?>%</strong></div><div class="po-progress__track"><span style="width:<?= max(0, min(100, (int)$activity['progress'])) ?>%"></span></div></div></td>
                            <td class="po-activity-actions"><a class="po-icon-btn" href="<?= url('programme_officer/activity_schedule.php?edit=' . (int)$activity['id']) ?>" aria-label="Edit activity"><i class="fas fa-pen"></i></a><form method="post" onsubmit="return confirm('Delete this scheduled activity? This action cannot be undone.');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>"><button type="submit" class="po-icon-btn po-icon-btn--danger" aria-label="Delete activity"><i class="fas fa-trash"></i></button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var programme = document.getElementById('activityProgramme');
    var cohort = document.getElementById('activityCohort');
    if (!programme || !cohort) return;
    function filterCohorts() {
        Array.prototype.forEach.call(cohort.options, function (option) {
            if (!option.value || option.value === '0') return;
            var visible = programme.value !== '' && option.getAttribute('data-programme') === programme.value;
            option.hidden = !visible;
            if (!visible && option.selected) cohort.value = <?= $scopeMode === 'cohort' ? "''" : "'0'" ?>;
        });
    }
    programme.addEventListener('change', filterCohorts);
    filterCohorts();
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
