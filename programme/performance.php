<?php
/**
 * INVESTHOOD IT - Programme Manager
 * Programme Performance Overview
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');

$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'performance';
$pageTitle = 'Programme Performance';

$managerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
if ($managerId <= 0) {
    exit('Invalid Programme Manager account.');
}

/* Portfolio KPI data */
$kpi = [
    'programmes' => 0,
    'active_programmes' => 0,
    'completed_programmes' => 0,
    'cohorts' => 0,
    'candidates' => 0,
    'completed_candidates' => 0,
    'applications' => 0,
    'selected_applications' => 0,
];

$sql = "
    SELECT
        COUNT(DISTINCT p.id) AS programmes,
        COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) AS active_programmes,
        COUNT(DISTINCT CASE WHEN p.status = 'completed' THEN p.id END) AS completed_programmes,
        COUNT(DISTINCT c.id) AS cohorts,
        COUNT(DISTINCT CASE WHEN cp.status <> 'withdrawn' THEN cp.user_id END) AS candidates,
        COUNT(DISTINCT CASE WHEN cp.status = 'completed' THEN cp.user_id END) AS completed_candidates,
        COUNT(DISTINCT a.id) AS applications,
        COUNT(DISTINCT CASE WHEN a.status IN ('selected','offer_sent','offer_accepted') THEN a.id END) AS selected_applications
    FROM programmes p
    LEFT JOIN cohorts c ON c.programme_id = p.id
    LEFT JOIN cohort_participants cp ON cp.cohort_id = c.id
    LEFT JOIN opportunities o ON o.programme_id = p.id AND (o.cohort_id = c.id OR o.cohort_id IS NULL)
    LEFT JOIN applications a ON a.opportunity_id = o.id
    WHERE p.programme_manager_id = ?
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    foreach ($kpi as $key => $value) {
        $kpi[$key] = (int) ($row[$key] ?? 0);
    }
    $stmt->close();
}

$overallProgress = $kpi['candidates'] > 0
    ? min(100, round(($kpi['completed_candidates'] / $kpi['candidates']) * 100))
    : 0;

/* Programme-level performance */
$programmes = [];
$sql = "
    SELECT
        p.id,
        p.name,
        p.type,
        p.status,
        p.start_date,
        p.end_date,
        COUNT(DISTINCT c.id) AS cohort_count,
        COUNT(DISTINCT CASE WHEN cp.status <> 'withdrawn' THEN cp.user_id END) AS candidate_count,
        COUNT(DISTINCT CASE WHEN cp.status = 'completed' THEN cp.user_id END) AS completed_count,
        COUNT(DISTINCT a.id) AS application_count,
        COUNT(DISTINCT CASE WHEN a.status IN ('selected','offer_sent','offer_accepted') THEN a.id END) AS selected_count
    FROM programmes p
    LEFT JOIN cohorts c ON c.programme_id = p.id
    LEFT JOIN cohort_participants cp ON cp.cohort_id = c.id
    LEFT JOIN opportunities o ON o.programme_id = p.id AND (o.cohort_id = c.id OR o.cohort_id IS NULL)
    LEFT JOIN applications a ON a.opportunity_id = o.id
    WHERE p.programme_manager_id = ?
    GROUP BY p.id, p.name, p.type, p.status, p.start_date, p.end_date
    ORDER BY CASE WHEN p.status = 'active' THEN 0 WHEN p.status = 'paused' THEN 1 ELSE 2 END,
             p.start_date DESC, p.id DESC
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $candidateCount = (int) $row['candidate_count'];
        $row['progress'] = $candidateCount > 0
            ? min(100, round(((int) $row['completed_count'] / $candidateCount) * 100))
            : 0;
        $programmes[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | Investhood IT</title>
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/programme_manager_enhancements.css') ?>?v=20261006">
<style>
.pm-performance-page{padding:0 0 2rem}.pm-performance-hero{margin-bottom:1.5rem}
.pm-performance-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin:1.25rem 0}
.pm-performance-kpi{padding:1.1rem;border:1px solid var(--border,#e5e7eb);border-radius:14px;background:var(--bg-white,#fff);box-shadow:0 4px 16px rgba(15,23,42,.05)}
.pm-performance-kpi__label{display:block;font-size:.78rem;color:var(--text-lighter,#64748b);font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.pm-performance-kpi__value{display:block;margin-top:.35rem;font-size:1.7rem;font-weight:800;color:var(--text,#111827)}
.pm-performance-kpi__meta{display:block;margin-top:.2rem;font-size:.76rem;color:var(--text-light,#64748b)}
.pm-performance-card{background:var(--bg-white,#fff);border:1px solid var(--border,#e5e7eb);border-radius:16px;overflow:hidden}
.pm-performance-card__header{padding:1.25rem 1.35rem;border-bottom:1px solid var(--border,#e5e7eb)}
.pm-performance-card__header h2{margin:0;font-size:1.05rem}.pm-performance-card__header p{margin:.35rem 0 0;color:var(--text-light,#64748b);font-size:.84rem}
.pm-performance-table-wrap{overflow-x:auto}.pm-performance-table{width:100%;border-collapse:collapse;min-width:980px}
.pm-performance-table th,.pm-performance-table td{padding:.95rem 1rem;text-align:left;border-bottom:1px solid var(--border-light,#eef2f7);vertical-align:middle}
.pm-performance-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-light,#64748b);background:var(--bg,#f8fafc)}
.pm-performance-table td{font-size:.84rem}.pm-performance-table tbody tr:hover{background:rgba(37,99,235,.035)}
.pm-performance-name{font-weight:800;color:var(--text,#111827)}.pm-performance-sub{display:block;margin-top:.2rem;font-size:.72rem;color:var(--text-light,#64748b)}
.pm-performance-progress{min-width:150px}.pm-performance-progress__track{height:8px;border-radius:999px;background:#e5e7eb;overflow:hidden}.pm-performance-progress__track span{display:block;height:100%;background:#2563eb;border-radius:inherit}.pm-performance-progress small{display:block;margin-top:.35rem;color:var(--text-light,#64748b);font-weight:700}
.pm-performance-status{display:inline-flex;padding:.32rem .62rem;border-radius:999px;font-size:.7rem;font-weight:800;background:#eef2ff;color:#3730a3}.pm-performance-status.active{background:#dcfce7;color:#166534}.pm-performance-status.paused{background:#fef3c7;color:#92400e}.pm-performance-status.completed{background:#dbeafe;color:#1e40af}
.pm-performance-action{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem .75rem;border-radius:9px;background:#2563eb;color:#fff;text-decoration:none;font-size:.75rem;font-weight:800}
html[data-theme="dark"] .pm-performance-kpi,html[data-theme="dark"] .pm-performance-card{background:#1e293b;border-color:#334155}
html[data-theme="dark"] .pm-performance-kpi__value,html[data-theme="dark"] .pm-performance-name{color:#f1f5f9}
html[data-theme="dark"] .pm-performance-table th{background:#0f172a}html[data-theme="dark"] .pm-performance-progress__track{background:#334155}
@media(max-width:1100px){.pm-performance-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.pm-performance-kpis{grid-template-columns:1fr}.pm-performance-page{padding-bottom:1rem}}
</style>
</head>
<body class="dashboard-page">
<div class="dashboard">
<?php require __DIR__ . '/sidebar.php'; ?>
<main class="dashboard__main">
<?php require __DIR__ . '/navbar.php'; ?>
<div class="dash-content pm-performance-page">
<?= $flashes ?>
<div class="welcome-card pm-performance-hero">
<div class="welcome-card__content">
<h1 class="welcome-card__greeting">Programme <span class="text-gradient">Performance</span></h1>
<p>Management-level performance across programmes assigned to your Programme Manager account.</p>
</div>
</div>

<div class="pm-performance-kpis">
<div class="pm-performance-kpi"><span class="pm-performance-kpi__label">Programmes</span><strong class="pm-performance-kpi__value"><?= number_format($kpi['programmes']) ?></strong><span class="pm-performance-kpi__meta"><?= number_format($kpi['active_programmes']) ?> active</span></div>
<div class="pm-performance-kpi"><span class="pm-performance-kpi__label">Cohorts</span><strong class="pm-performance-kpi__value"><?= number_format($kpi['cohorts']) ?></strong><span class="pm-performance-kpi__meta">Across your programmes</span></div>
<div class="pm-performance-kpi"><span class="pm-performance-kpi__label">Candidates</span><strong class="pm-performance-kpi__value"><?= number_format($kpi['candidates']) ?></strong><span class="pm-performance-kpi__meta"><?= number_format($kpi['completed_candidates']) ?> completed</span></div>
<div class="pm-performance-kpi"><span class="pm-performance-kpi__label">Applications</span><strong class="pm-performance-kpi__value"><?= number_format($kpi['applications']) ?></strong><span class="pm-performance-kpi__meta"><?= number_format($kpi['selected_applications']) ?> selected / offer stage</span></div>
</div>

<div class="pm-performance-card">
<div class="pm-performance-card__header"><h2>Programme Performance Overview</h2><p>Compare cohort delivery, candidate participation, application activity and completion progress.</p></div>
<?php if (!$programmes): ?>
<div class="po-empty" style="padding:3rem 1.5rem;"><div class="po-empty__icon"><i class="fas fa-chart-line"></i></div><strong>No programmes found</strong><span>No programmes are currently assigned to your account.</span></div>
<?php else: ?>
<div class="pm-performance-table-wrap">
<table class="pm-performance-table">
<thead><tr><th>Programme</th><th>Status</th><th>Cohorts</th><th>Candidates</th><th>Completed</th><th>Applications</th><th>Selected</th><th>Overall Progress</th><th>Drill Down</th></tr></thead>
<tbody>
<?php foreach ($programmes as $programme): ?>
<tr>
<td><span class="pm-performance-name"><?= e($programme['name']) ?></span><span class="pm-performance-sub"><?= e((string) $programme['type']) ?><?= $programme['start_date'] ? ' · '.e($programme['start_date']) : '' ?></span></td>
<td><span class="pm-performance-status <?= e((string)$programme['status']) ?>"><?= e(ucwords(str_replace('_',' ',(string)$programme['status']))) ?></span></td>
<td><?= number_format((int)$programme['cohort_count']) ?></td>
<td><?= number_format((int)$programme['candidate_count']) ?></td>
<td><?= number_format((int)$programme['completed_count']) ?></td>
<td><?= number_format((int)$programme['application_count']) ?></td>
<td><?= number_format((int)$programme['selected_count']) ?></td>
<td><div class="pm-performance-progress"><div class="pm-performance-progress__track"><span style="width:<?= (int)$programme['progress'] ?>%"></span></div><small><?= (int)$programme['progress'] ?>% completed</small></div></td>
<td><a class="pm-performance-action" href="<?= url('programme/programme_view.php?id='.(int)$programme['id']) ?>">View <i class="fas fa-arrow-right"></i></a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</div>
</main>
</div>
<script src="<?= url('js/programme_manager_enhancements.js') ?>?v=20261006"></script>
</body>
</html>
