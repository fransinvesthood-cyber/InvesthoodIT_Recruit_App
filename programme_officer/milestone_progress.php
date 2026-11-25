<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$flashes = render_flashes();
$currentPage = 'deliverables';
$pageTitle = 'Milestone Progress';
$conn = Database::getConnection();

$officerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
if ($officerId <= 0) { http_response_code(403); exit('Invalid Programme Officer account.'); }

$scope = po_scope($conn, 'p', 'c');
if (po_scope_mode($scope) === 'none') { http_response_code(403); exit('Access scope unavailable.'); }
$scopeCondition = po_scope_condition($scope);
$today = date('Y-m-d');

/* Milestones in scope */
$milestones = [];
$stmt = $conn->prepare("
    SELECT m.id, m.title, m.due_date, m.status, m.priority, m.progress,
           p.name AS programme_name, c.name AS cohort_name
    FROM programme_milestones m
    INNER JOIN programmes p ON p.id = m.programme_id
    LEFT JOIN cohorts c ON c.id = m.cohort_id
    WHERE {$scopeCondition}
    ORDER BY m.due_date ASC, m.id ASC");
$stmt->bind_param('i', $officerId);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $m) {
    $m['deliverables'] = [];
    $milestones[(int) $m['id']] = $m;
}
$stmt->close();

/* Deliverables linked to a milestone, with verification counts */
$stmt = $conn->prepare("
    SELECT d.id, d.milestone_id, d.title, d.due_date, d.status,
        (SELECT COUNT(DISTINCT cp2.user_id) FROM cohort_participants cp2
           INNER JOIN cohorts c2 ON c2.id = cp2.cohort_id
          WHERE c2.programme_id = d.programme_id
            AND (d.cohort_id IS NULL OR c2.id = d.cohort_id)
            AND cp2.status <> 'withdrawn') AS expected_count,
        (SELECT COUNT(DISTINCT s.candidate_id) FROM deliverable_submissions s
          WHERE s.deliverable_id = d.id AND s.status = 'verified') AS verified_count,
        (SELECT COUNT(*) FROM deliverable_submissions s
          WHERE s.deliverable_id = d.id AND s.status = 'submitted') AS awaiting_count
    FROM programme_deliverables d
    INNER JOIN programmes p ON p.id = d.programme_id
    LEFT JOIN cohorts c ON c.id = d.cohort_id
    WHERE {$scopeCondition} AND d.milestone_id IS NOT NULL
    ORDER BY d.due_date ASC");
$stmt->bind_param('i', $officerId);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $d) {
    $mid = (int) $d['milestone_id'];
    if (isset($milestones[$mid])) { $milestones[$mid]['deliverables'][] = $d; }
}
$stmt->close();

$stats = ['milestones' => 0, 'completed' => 0, 'overdue' => 0, 'at_risk' => 0];
foreach ($milestones as &$m) {
    $total = count($m['deliverables']);
    $done = 0; $lateD = 0;
    foreach ($m['deliverables'] as &$d) {
        $d['complete'] = (int) $d['expected_count'] > 0 && (int) $d['verified_count'] >= (int) $d['expected_count'];
        $d['late'] = !$d['complete'] && $d['status'] === 'open' && $d['due_date'] < $today;
        if ($d['complete']) $done++;
        if ($d['late']) $lateD++;
    }
    unset($d);
    $m['total'] = $total; $m['done'] = $done; $m['late'] = $lateD;
    $m['rate'] = $total > 0 ? (int) round($done / $total * 100) : 0;
    $m['overdue'] = $m['status'] !== 'completed' && $m['due_date'] < $today;
    $stats['milestones']++;
    if ($m['status'] === 'completed') $stats['completed']++;
    if ($m['overdue']) $stats['overdue']++;
    if ($lateD > 0 && !$m['overdue']) $stats['at_risk']++;
}
unset($m);

require __DIR__ . '/_layout_start.php';
?>
<div class="po-breadcrumb"><a href="<?= url('programme_officer/deliverables.php') ?>">Deliverables</a>
    <i class="fas fa-chevron-right"></i><span>Milestone progress</span></div>

<div class="po-page-header"><div><span class="po-page-header__eyebrow">Programme Delivery</span>
    <h2>Milestone Progress</h2>
    <p>See each milestone's deliverables and how many are fully verified.</p></div></div>

<section class="po-stats po-stats--4">
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--blue"><i class="fas fa-flag"></i></span><strong><?= number_format($stats['milestones']) ?></strong><span>Milestones</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--green"><i class="fas fa-circle-check"></i></span><strong><?= number_format($stats['completed']) ?></strong><span>Completed</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--purple"><i class="fas fa-clock"></i></span><strong><?= number_format($stats['overdue']) ?></strong><span>Overdue</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--orange"><i class="fas fa-triangle-exclamation"></i></span><strong><?= number_format($stats['at_risk']) ?></strong><span>At risk</span></article>
</section>

<?php if (!$milestones): ?>
<div class="po-card"><div class="po-empty"><div class="po-empty__icon"><i class="fas fa-flag"></i></div>
    <strong>No milestones found</strong><span>No milestones exist in your programme scope.</span></div></div>
<?php endif; ?>

<?php foreach ($milestones as $m): ?>
<section class="po-card">
    <div class="po-card__header">
        <div>
            <h3><?= e($m['title']) ?></h3>
            <p><?= e($m['programme_name']) ?> · <?= e($m['cohort_name'] ?? 'All cohorts') ?> · Due <?= e(po_date($m['due_date'])) ?></p>
        </div>
        <span>
            <?php if ($m['overdue']): ?><span class="po-status po-status--inactive">Overdue</span><?php endif; ?>
            <span class="po-status po-status--<?= e(po_status_class($m['status'])) ?>"><?= e(po_status_label($m['status'])) ?></span>
        </span>
    </div>
    <div class="po-card__body">
        <div class="po-progress">
            <div class="po-progress__track"><span style="width:<?= (int) $m['rate'] ?>%"></span></div>
            <small><?= (int) $m['done'] ?> of <?= (int) $m['total'] ?> deliverable(s) fully verified (<?= (int) $m['rate'] ?>%)</small>
        </div>
        <?php if (!$m['deliverables']): ?>
            <p class="po-table-sub">No deliverables are linked to this milestone yet.</p>
        <?php else: foreach ($m['deliverables'] as $d): ?>
            <a class="po-action-item" href="<?= url('programme_officer/deliverable_view.php?id=' . (int) $d['id']) ?>">
                <span class="po-action-item__body">
                    <strong><?= e($d['title']) ?></strong>
                    <span><?= (int) $d['verified_count'] ?> / <?= (int) $d['expected_count'] ?> verified
                        · <?= (int) $d['awaiting_count'] ?> awaiting review · Due <?= e(po_date($d['due_date'])) ?></span>
                </span>
                <?php if ($d['complete']): ?><span class="po-action-item__priority po-action-item__priority--low">Complete</span>
                <?php elseif ($d['late']): ?><span class="po-action-item__priority po-action-item__priority--high">Overdue</span>
                <?php else: ?><span class="po-action-item__priority po-action-item__priority--medium">In progress</span><?php endif; ?>
                <i class="fas fa-chevron-right po-action-item__arrow"></i>
            </a>
        <?php endforeach; endif; ?>
    </div>
</section>
<?php endforeach; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>
