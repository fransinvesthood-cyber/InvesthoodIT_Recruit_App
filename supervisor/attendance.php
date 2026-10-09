<?php
// ============================================================================
// Attendance Monitoring - detail page.
// URL: supervisor/attendance.php?cohort_id=..&days=30|60|90&show=concerns|all
// Scoped to cohorts assigned to the logged-in Supervisor (same rule as every
// other supervisor page). The dashboard section links here via "View details".
// ============================================================================
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_attendance.php';

$user = current_user();
$flashes = render_flashes();
$currentPage = 'attendance';
$pageTitle = 'Attendance Monitoring';
$supervisorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($supervisorId <= 0) {
    http_response_code(403);
    exit('Invalid Supervisor account.');
}

// ---- Filters ----------------------------------------------------------------
$cohortId = (int)($_GET['cohort_id'] ?? 0);
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [30, 60, 90], true)) $days = 30;
$show = (($_GET['show'] ?? 'concerns') === 'all') ? 'all' : 'concerns';

// Cohorts this Supervisor may filter by (also validates cohort_id).
$myCohorts = [];
$stmt = Database::prepare(
    "SELECT c.id, c.name AS cohort_name, p.name AS programme_name FROM cohorts c JOIN programmes p ON p.id=c.programme_id WHERE c.supervisor_id=? ORDER BY c.start_date DESC, c.id DESC",
    'i', [$supervisorId]
);
$res = $stmt->get_result();
while ($res && $row = $res->fetch_assoc()) $myCohorts[(int)$row['id']] = $row;
$stmt->close();
if ($cohortId > 0 && !isset($myCohorts[$cohortId])) $cohortId = 0;

$schema = sv_att_schema();
$a = null;
if ($schema) {
    $a = sv_att_analyse(sv_att_fetch($supervisorId, max(90, 2 * $days), $cohortId), $days);
}

require __DIR__ . '/_layout_start.php';
sv_att_styles();
?>
<div class="sv-page-header">
    <div>
        <span class="sv-eyebrow">Programme Delivery</span>
        <h2>Attendance Monitoring</h2>
        <p>Attendance at programme and training sessions, and the candidates with attendance concerns.</p>
    </div>
    <div class="sv-actions">
        <a class="sv-btn sv-btn--secondary" href="<?= e(url('supervisor/dashboard.php')) ?>"><i class="fas fa-arrow-left"></i> Dashboard</a>
    </div>
</div>

<section class="sv-card" style="margin-bottom:18px">
    <div class="sv-card__body">
        <form method="get" class="sv-att__filters">
            <div class="sv-field">
                <label for="att-cohort">Cohort</label>
                <select class="sv-select" id="att-cohort" name="cohort_id">
                    <option value="0">All my cohorts</option>
                    <?php foreach ($myCohorts as $id => $c): ?>
                        <option value="<?= (int)$id ?>" <?= $cohortId === $id ? 'selected' : '' ?>><?= e($c['cohort_name'] . ' · ' . $c['programme_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sv-field">
                <label for="att-days">Period</label>
                <select class="sv-select" id="att-days" name="days">
                    <?php foreach ([30, 60, 90] as $d): ?>
                        <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>>Last <?= $d ?> days</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sv-field">
                <label for="att-show">Show</label>
                <select class="sv-select" id="att-show" name="show">
                    <option value="concerns" <?= $show === 'concerns' ? 'selected' : '' ?>>Candidates with concerns</option>
                    <option value="all" <?= $show === 'all' ? 'selected' : '' ?>>All candidates</option>
                </select>
            </div>
            <button class="sv-btn sv-btn--primary" type="submit"><i class="fas fa-filter"></i> Apply</button>
        </form>
    </div>
</section>

<section class="sv-card">
    <div class="sv-card__header">
        <div>
            <h3>Overview</h3>
            <p>Last <?= (int)$days ?> days<?= $cohortId > 0 ? ' · ' . e($myCohorts[$cohortId]['cohort_name']) : ' · all assigned cohorts' ?>.</p>
        </div>
    </div>
    <div class="sv-card__body">
        <?php if (!$schema || !$a): ?>
            <div class="sv-att__empty"><i class="fas fa-calendar-xmark"></i><strong>Attendance data is not available</strong><span>The attendance records could not be loaded right now.</span></div>
        <?php elseif (!$a['has_data']): ?>
            <div class="sv-att__empty"><i class="fas fa-calendar-check"></i><strong>No attendance recorded yet</strong><span>Nothing has been recorded for the selected cohort and period.</span></div>
        <?php else: ?>
            <?php sv_att_render_overview($a); ?>
            <?php $listing = $show === 'all' ? $a['candidates'] : $a['concerns']; ?>
            <h4 class="sv-att__sub"><?= $show === 'all' ? 'All candidates' : 'Candidates needing attention' ?> <small style="font-weight:400;color:var(--sv-muted,#667085)">(<?= count($listing) ?>)</small></h4>
            <?php if (!$listing): ?>
                <div class="sv-att__empty" style="padding:22px 16px"><i class="fas fa-circle-check" style="color:#16a34a"></i><strong>No attendance concerns</strong><span>Every candidate with recorded sessions is meeting the <?= (int)$a['cfg']['warn'] ?>% attendance target.</span></div>
            <?php else: ?>
                <?php sv_att_render_candidates($listing); ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<div class="sv-security"><i class="fas fa-shield-halved"></i><div><strong>Supervisor-scoped access</strong><p>Attendance is only shown for candidates in cohorts assigned to your Supervisor account. Withdrawn candidates are excluded; excused absences are not counted against the attendance rate.</p></div></div>
<?php require __DIR__ . '/_layout_end.php'; ?>
