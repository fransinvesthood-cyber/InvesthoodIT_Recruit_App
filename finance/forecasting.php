<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user = current_user();
$conn = Database::getConnection();
require_once __DIR__ . '/_helpers.php';
$currentPage = 'forecasting';
$pageTitle = 'Financial Forecasting & Budget Variance Analysis';

function ff_e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function ff_money($v): string { return 'R ' . number_format((float)$v, 2); }
function ff_months_between(DateTimeImmutable $from, DateTimeImmutable $to): int {
    if ($to < $from) return 0;
    return (((int)$to->format('Y') - (int)$from->format('Y')) * 12)
        + ((int)$to->format('n') - (int)$from->format('n')) + 1;
}
function ff_fy_dates(string $fy): array {
    if (!preg_match('/^(\d{4})\/(\d{4})$/', $fy, $m)) {
        $year = (int)date('Y');
        return [new DateTimeImmutable($year . '-04-01'), new DateTimeImmutable(($year + 1) . '-03-31')];
    }
    $startYear = (int)$m[1];
    return [new DateTimeImmutable($startYear . '-04-01'), new DateTimeImmutable(((int)$m[2]) . '-03-31')];
}

$today = new DateTimeImmutable('today');
$currentFyStartYear = (int)$today->format('Y') - ((int)$today->format('n') < 4 ? 1 : 0);
$currentFy = $currentFyStartYear . '/' . ($currentFyStartYear + 1);
$selectedFy = trim((string)($_GET['financial_year'] ?? $currentFy));
if (!preg_match('/^\d{4}\/\d{4}$/', $selectedFy)) $selectedFy = $currentFy;
$programmeFilter = max(0, (int)($_GET['programme_id'] ?? 0));
$cohortFilter = max(0, (int)($_GET['cohort_id'] ?? 0));
[$fyStart, $fyEnd] = ff_fy_dates($selectedFy);
$fyStartSql = $fyStart->format('Y-m-d');
$fyEndSql = $fyEnd->format('Y-m-d');
$effectiveToday = $today < $fyStart ? $fyStart->modify('-1 day') : ($today > $fyEnd ? $fyEnd : $today);
$remainingMonths = 0;
if ($today <= $fyEnd) {
    $forecastStart = (new DateTimeImmutable($effectiveToday->format('Y-m-01')))->modify('first day of next month');
    $forecastEnd = new DateTimeImmutable($fyEnd->format('Y-m-01'));
    $remainingMonths = ff_months_between($forecastStart, $forecastEnd);
}
$forecastWindowEnd = $today > $fyEnd ? $fyEnd : $today;
$historyStart = $forecastWindowEnd->modify('first day of this month')->modify('-2 months');
$historyEnd = $forecastWindowEnd;
$historyMonths = max(1, ff_months_between($historyStart, $historyEnd));

$programmes = [];
$pr = $conn->query('SELECT id, name FROM programmes ORDER BY name');
if ($pr) $programmes = $pr->fetch_all(MYSQLI_ASSOC);
$cohorts = [];
$cr = $conn->query('SELECT id, programme_id, name FROM cohorts ORDER BY name');
if ($cr) $cohorts = $cr->fetch_all(MYSQLI_ASSOC);

$years = [];
$yr = $conn->query("SELECT DISTINCT financial_year FROM finance_budgets WHERE financial_year REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY financial_year DESC");
if ($yr) $years = array_column($yr->fetch_all(MYSQLI_ASSOC), 'financial_year');
if (!in_array($currentFy, $years, true)) $years[] = $currentFy;
if (!in_array($selectedFy, $years, true)) $years[] = $selectedFy;
rsort($years, SORT_STRING);

$where = ['b.financial_year = ?'];
$params = [$selectedFy];
$types = 's';
if ($programmeFilter > 0) { $where[] = 'b.programme_id = ?'; $params[] = $programmeFilter; $types .= 'i'; }
if ($cohortFilter > 0) { $where[] = 'b.cohort_id = ?'; $params[] = $cohortFilter; $types .= 'i'; }
$sql = "SELECT b.id, b.programme_id, b.cohort_id, b.financial_year, b.budget_name, b.allocated_amount, b.status,
               p.name AS programme_name, c.name AS cohort_name,
               COALESCE(SUM(CASE WHEN x.expense_date BETWEEN ? AND ? AND x.status = 'paid' THEN x.amount ELSE 0 END),0) AS fy_paid,
               COALESCE(SUM(CASE WHEN x.expense_date BETWEEN ? AND ? AND x.status = 'committed' THEN x.amount ELSE 0 END),0) AS fy_committed,
               COALESCE(SUM(CASE WHEN x.expense_date BETWEEN ? AND ? AND x.status IN ('paid','committed') THEN x.amount ELSE 0 END),0) AS fy_total,
               COALESCE(SUM(CASE WHEN x.expense_date BETWEEN ? AND ? AND x.status IN ('paid','committed') THEN x.amount ELSE 0 END),0) AS recent_total
        FROM finance_budgets b
        JOIN programmes p ON p.id=b.programme_id
        LEFT JOIN cohorts c ON c.id=b.cohort_id
        LEFT JOIN finance_expenditures x ON x.budget_id=b.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY b.id ORDER BY p.name, c.name, b.budget_name";
$stmt = $conn->prepare($sql);
if (!$stmt) throw new RuntimeException('Unable to prepare forecasting query: ' . $conn->error);
$bindTypes = 'ssssssss' . $types;
$bindValues = array_merge([
    $fyStartSql, $fyEndSql,
    $fyStartSql, $fyEndSql,
    $fyStartSql, $fyEndSql,
    $historyStart->format('Y-m-d'), $historyEnd->format('Y-m-d')
], $params);
$stmt->bind_param($bindTypes, ...$bindValues);
$stmt->execute();
$budgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$rows = [];
$totals = ['allocated'=>0.0,'paid'=>0.0,'committed'=>0.0,'spent'=>0.0,'projected'=>0.0,'shortfall'=>0.0,'surplus'=>0.0];
$monthlyTotals = [];
for ($i=0; $i<12; $i++) {
    $month = $fyStart->modify('+' . $i . ' months');
    if ($month > $fyEnd) break;
    $monthlyTotals[$month->format('Y-m')] = ['label'=>$month->format('M y'),'amount'=>0.0];
}

// Load monthly expenditure once for trend analysis and forecast calculations.
$trendStart = $fyStartSql;
$trendEnd = $fyEndSql;
$trendSql = "SELECT b.id AS budget_id, DATE_FORMAT(x.expense_date,'%Y-%m') AS month_key,
                    SUM(CASE WHEN x.status IN ('paid','committed') THEN x.amount ELSE 0 END) AS amount
             FROM finance_budgets b
             JOIN finance_expenditures x ON x.budget_id=b.id
             WHERE b.financial_year=? AND x.expense_date BETWEEN ? AND ?";
$trendTypes = 'sss'; $trendParams = [$selectedFy, $trendStart, $trendEnd];
if ($programmeFilter > 0) { $trendSql .= ' AND b.programme_id=?'; $trendTypes .= 'i'; $trendParams[] = $programmeFilter; }
if ($cohortFilter > 0) { $trendSql .= ' AND b.cohort_id=?'; $trendTypes .= 'i'; $trendParams[] = $cohortFilter; }
$trendSql .= " GROUP BY b.id, DATE_FORMAT(x.expense_date,'%Y-%m')";
$trendStmt = $conn->prepare($trendSql);
if ($trendStmt) {
    $trendStmt->bind_param($trendTypes, ...$trendParams);
    $trendStmt->execute();
    $trendResult = $trendStmt->get_result();
    while ($t = $trendResult->fetch_assoc()) {
        if (isset($monthlyTotals[$t['month_key']])) $monthlyTotals[$t['month_key']]['amount'] += (float)$t['amount'];
    }
    $trendStmt->close();
}

foreach ($budgets as $b) {
    $allocated = (float)$b['allocated_amount'];
    $paid = (float)$b['fy_paid'];
    $committed = (float)$b['fy_committed'];
    $actualAndCommitted = $paid + $committed;
    $recent = (float)$b['recent_total'];
    $monthlyRunRate = $recent / $historyMonths;
    $projectedFuture = $monthlyRunRate * $remainingMonths;
    $projectedTotal = $actualAndCommitted + $projectedFuture;
    $variance = $allocated - $actualAndCommitted;
    $projectedVariance = $allocated - $projectedTotal;
    $utilisation = $allocated > 0 ? ($actualAndCommitted / $allocated) * 100 : 0;
    $forecastUtilisation = $allocated > 0 ? ($projectedTotal / $allocated) * 100 : 0;
    $risk = $projectedVariance < 0 ? 'shortfall' : ($forecastUtilisation >= 85 ? 'watch' : 'healthy');
    $row = $b + [
        'allocated'=>$allocated, 'paid'=>$paid, 'committed'=>$committed,
        'spent'=>$actualAndCommitted, 'monthly_run_rate'=>$monthlyRunRate,
        'projected_future'=>$projectedFuture, 'projected_total'=>$projectedTotal,
        'variance'=>$variance, 'projected_variance'=>$projectedVariance,
        'utilisation'=>$utilisation, 'forecast_utilisation'=>$forecastUtilisation,
        'risk'=>$risk
    ];
    $rows[] = $row;
    $totals['allocated'] += $allocated;
    $totals['paid'] += $paid;
    $totals['committed'] += $committed;
    $totals['spent'] += $actualAndCommitted;
    $totals['projected'] += $projectedTotal;
    if ($projectedVariance < 0) $totals['shortfall'] += abs($projectedVariance);
    else $totals['surplus'] += $projectedVariance;
}
$overallVariance = $totals['allocated'] - $totals['spent'];
$overallProjectedVariance = $totals['allocated'] - $totals['projected'];
$overallUtil = $totals['allocated'] > 0 ? ($totals['spent'] / $totals['allocated']) * 100 : 0;
$overallForecastUtil = $totals['allocated'] > 0 ? ($totals['projected'] / $totals['allocated']) * 100 : 0;
$shortfallRows = array_values(array_filter($rows, fn($r) => $r['risk'] === 'shortfall'));
$watchRows = array_values(array_filter($rows, fn($r) => $r['risk'] === 'watch'));

// CSV export uses the same filters and calculations as the dashboard.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="finance-forecast-' . preg_replace('/[^0-9\/]/','', $selectedFy) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Programme','Cohort','Budget','Financial year','Allocated (ZAR)','Paid (ZAR)','Committed (ZAR)','Current variance (ZAR)','Monthly run rate (ZAR)','Forecast future requirement (ZAR)','Projected total (ZAR)','Projected variance (ZAR)','Forecast status']);
    foreach ($rows as $r) fputcsv($out, [$r['programme_name'],$r['cohort_name'] ?: 'Programme-wide',$r['budget_name'],$r['financial_year'],$r['allocated'],$r['paid'],$r['committed'],$r['variance'],$r['monthly_run_rate'],$r['projected_future'],$r['projected_total'],$r['projected_variance'],$r['risk']]);
    fclose($out); exit;
}

require __DIR__ . '/_layout_start.php';
?>
<div class="finance-page-polished finance-forecasting-page">
<div class="fo-page-header"><div><span>Finance Operations / Planning</span><h2>Financial Forecasting &amp; Budget Variance Analysis</h2><p>Review spending patterns, compare expenditure with allocations, project future requirements and identify programme or cohort funding risks.</p></div><div class="ff-header-actions"><a class="fo-btn fo-btn--secondary" href="<?= e(url('finance/budget.php')) ?>"><i class="fas fa-wallet"></i> Budget Allocation</a><a class="fo-btn fo-btn--primary" href="<?= e(url('finance/forecasting.php?' . http_build_query(['financial_year'=>$selectedFy,'programme_id'=>$programmeFilter,'cohort_id'=>$cohortFilter,'export'=>'csv']))) ?>"><i class="fas fa-file-csv"></i> Export CSV</a></div></div>
<div class="fo-card ff-method"><i class="fas fa-circle-info"></i><div><strong>Forecast methodology</strong><span>Projected total = financial-year paid + committed expenditure + (average monthly expenditure over the latest three months × remaining months). The forecast is a run-rate estimate, not a guaranteed spend or approved budget change. Cancelled expenses are excluded.</span></div></div>
<form class="fo-card ff-filters" method="get"><div><label for="ffYear">Financial year</label><select id="ffYear" name="financial_year"><?php foreach($years as $year): ?><option value="<?= ff_e($year) ?>" <?= $year===$selectedFy?'selected':'' ?>><?= ff_e($year) ?></option><?php endforeach; ?></select></div><div><label for="ffProgramme">Programme</label><select id="ffProgramme" name="programme_id"><option value="0">All programmes</option><?php foreach($programmes as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $programmeFilter===(int)$p['id']?'selected':'' ?>><?= ff_e($p['name']) ?></option><?php endforeach; ?></select></div><div><label for="ffCohort">Cohort</label><select id="ffCohort" name="cohort_id"><option value="0">All cohorts</option><?php foreach($cohorts as $c): ?><option value="<?= (int)$c['id'] ?>" data-programme="<?= (int)$c['programme_id'] ?>" <?= $cohortFilter===(int)$c['id']?'selected':'' ?>><?= ff_e($c['name']) ?></option><?php endforeach; ?></select></div><div class="ff-filter-submit"><button class="fo-btn fo-btn--primary" type="submit"><i class="fas fa-filter"></i> Apply filters</button><a class="fo-btn fo-btn--secondary" href="<?= e(url('finance/forecasting.php')) ?>">Reset</a></div></form>
<div class="fo-dashboard-grid ff-kpis">
 <article class="fo-card"><span class="fo-muted">Total allocated</span><h2><?= ff_money($totals['allocated']) ?></h2><small><?= ff_e($selectedFy) ?></small></article>
 <article class="fo-card"><span class="fo-muted">Paid expenditure</span><h2><?= ff_money($totals['paid']) ?></h2><small>Recorded as paid</small></article>
 <article class="fo-card"><span class="fo-muted">Committed expenditure</span><h2><?= ff_money($totals['committed']) ?></h2><small>Committed, not yet paid</small></article>
 <article class="fo-card"><span class="fo-muted">Current budget balance</span><h2 class="<?= $overallVariance<0?'ff-negative':'ff-positive' ?>"><?= ff_money($overallVariance) ?></h2><small>Allocation less paid and committed</small></article>
 <article class="fo-card"><span class="fo-muted">Projected year-end spend</span><h2><?= ff_money($totals['projected']) ?></h2><small><?= $remainingMonths ?> forecast month(s) remaining</small></article>
 <article class="fo-card"><span class="fo-muted">Potential shortfall</span><h2 class="<?= $overallProjectedVariance<0?'ff-negative':'ff-positive' ?>"><?= ff_money(max(0, -$overallProjectedVariance)) ?></h2><small><?= count($shortfallRows) ?> allocation(s) projected over budget</small></article>
</div>
<div class="fo-two-column ff-overview">
 <section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-chart-line"></i> Budget utilisation</h3><strong><?= number_format($overallUtil,1) ?>% actual</strong></div><div class="ff-bar-track"><span class="ff-bar-actual" style="width:<?= min(100,max(0,$overallUtil)) ?>%"></span><span class="ff-bar-projected" style="width:<?= min(100,max(0,$overallForecastUtil)) ?>%"></span></div><div class="ff-legend"><span><i class="ff-dot ff-dot-actual"></i> Current paid + committed</span><span><i class="ff-dot ff-dot-projected"></i> Forecast year-end</span></div><p class="fo-muted">Forecast utilisation: <strong><?= number_format($overallForecastUtil,1) ?>%</strong>. A forecast above 100% indicates a potential funding gap.</p></section>
 <section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-chart-column"></i> Monthly expenditure pattern</h3><span class="fo-muted">Paid + committed</span></div><div class="ff-month-chart"><?php $maxMonth=max(1.0,max(array_map(fn($m)=>(float)$m['amount'],$monthlyTotals?:[['amount'=>0]]))); foreach($monthlyTotals as $m): ?><div class="ff-month-col"><div class="ff-month-value"><?= $m['amount']>0?ff_e(number_format($m['amount']/1000,1).'k'):' ' ?></div><div class="ff-month-bar-wrap"><span style="height:<?= min(100,($m['amount']/$maxMonth)*100) ?>%"></span></div><small><?= ff_e($m['label']) ?></small></div><?php endforeach; ?></div></section>
</div>
<?php if($shortfallRows || $watchRows): ?><section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-triangle-exclamation"></i> Budget risk alerts</h3><span class="fo-status fo-status--warning"><?= count($shortfallRows)+count($watchRows) ?> flagged</span></div><?php foreach($shortfallRows as $r): ?><div class="ff-alert ff-alert-danger"><div><strong><?= ff_e($r['programme_name']) ?> · <?= ff_e($r['cohort_name'] ?: 'Programme-wide') ?></strong><small><?= ff_e($r['budget_name']) ?></small></div><span>Projected shortfall: <strong><?= ff_money(abs($r['projected_variance'])) ?></strong></span></div><?php endforeach; ?><?php foreach($watchRows as $r): ?><div class="ff-alert ff-alert-warning"><div><strong><?= ff_e($r['programme_name']) ?> · <?= ff_e($r['cohort_name'] ?: 'Programme-wide') ?></strong><small><?= ff_e($r['budget_name']) ?></small></div><span>Forecast utilisation: <strong><?= number_format($r['forecast_utilisation'],1) ?>%</strong></span></div><?php endforeach; ?></section><?php endif; ?>
<section class="fo-card"><div class="fo-card-header"><div><h3><i class="fas fa-scale-balanced"></i> Budget variance and forecast by allocation</h3><span class="fo-muted">Positive variance = remaining budget; negative variance = overspend / shortfall</span></div><span class="fo-muted"><?= count($rows) ?> allocation(s)</span></div><div class="table-responsive"><table class="table ff-table"><thead><tr><th>Programme / cohort</th><th>Budget</th><th>Allocated</th><th>Paid</th><th>Committed</th><th>Current variance</th><th>Monthly run rate</th><th>Projected total</th><th>Projected variance</th><th>Risk</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="10">No budget allocations found for the selected filters. Create an allocation in Budget Allocation first.</td></tr><?php endif; ?><?php foreach($rows as $r): ?><tr><td><strong><?= ff_e($r['programme_name']) ?></strong><small class="ff-subline"><?= ff_e($r['cohort_name'] ?: 'Programme-wide') ?></small></td><td><?= ff_e($r['budget_name']) ?><small class="ff-subline"><?= ff_e($r['financial_year']) ?></small></td><td><?= ff_money($r['allocated']) ?></td><td><?= ff_money($r['paid']) ?></td><td><?= ff_money($r['committed']) ?></td><td class="<?= $r['variance']<0?'ff-negative':'ff-positive' ?>"><?= ff_money($r['variance']) ?></td><td><?= ff_money($r['monthly_run_rate']) ?></td><td><strong><?= ff_money($r['projected_total']) ?></strong><small class="ff-subline"><?= ff_money($r['projected_future']) ?> future requirement</small></td><td class="<?= $r['projected_variance']<0?'ff-negative':'ff-positive' ?>"><?= ff_money($r['projected_variance']) ?></td><td><span class="ff-risk ff-risk-<?= ff_e($r['risk']) ?>"><?= $r['risk']==='shortfall'?'Shortfall':($r['risk']==='watch'?'Watch':'Healthy') ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="fo-card ff-notes"><h3><i class="fas fa-lightbulb"></i> How to use this analysis</h3><ul><li>Use the financial year, programme and cohort filters to isolate the funding position.</li><li>Compare current variance with projected variance to distinguish an existing overspend from a forecast shortfall.</li><li>Review allocations marked <strong>Watch</strong> when forecast utilisation reaches 85% or more.</li><li>Use the CSV export to support finance reviews and budget reallocation discussions.</li><li>Forecasts are based on recorded expenses. Ensure expenditure and commitments are kept up to date for reliable results.</li></ul></section>
<style>
.ff-header-actions{display:flex;gap:.55rem;flex-wrap:wrap}.ff-method{display:flex;gap:.8rem;align-items:flex-start;margin-bottom:1rem;background:var(--surface,#fff)}.ff-method>i{color:#2563eb;margin-top:.2rem}.ff-method div{display:grid;gap:.25rem}.ff-method span,.ff-notes li{color:var(--muted,#64748b);font-size:.9rem}.ff-filters{display:grid;grid-template-columns:repeat(3,minmax(150px,1fr)) auto;gap:.8rem;align-items:end;margin-bottom:1rem}.ff-filters label{display:block;font-weight:600;font-size:.85rem;margin-bottom:.35rem}.ff-filters select{width:100%;padding:.65rem;border:1px solid var(--border-color,#d1d5db);border-radius:8px;background:var(--surface,#fff);color:inherit}.ff-filter-submit{display:flex;gap:.45rem;flex-wrap:wrap}.ff-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:1rem;margin-bottom:1rem}.ff-kpis h2{font-size:1.35rem;margin:.45rem 0}.ff-kpis small,.ff-subline{display:block;color:var(--muted,#64748b);font-size:.78rem;margin-top:.2rem}.ff-positive{color:#15803d!important}.ff-negative{color:#dc2626!important}.ff-overview{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-bottom:1rem}.ff-bar-track{height:16px;background:#e5e7eb;border-radius:20px;position:relative;overflow:hidden;margin:1.2rem 0 .7rem}.ff-bar-track span{position:absolute;left:0;top:0;height:100%;border-radius:20px}.ff-bar-actual{background:#2563eb;z-index:2}.ff-bar-projected{background:rgba(245,158,11,.55);z-index:1}.ff-legend{display:flex;gap:1rem;flex-wrap:wrap;font-size:.82rem}.ff-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:.3rem}.ff-dot-actual{background:#2563eb}.ff-dot-projected{background:#f59e0b}.ff-month-chart{height:175px;display:flex;align-items:stretch;gap:.45rem;padding-top:.4rem}.ff-month-col{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:.25rem}.ff-month-value{font-size:.65rem;color:var(--muted,#64748b);height:14px;white-space:nowrap}.ff-month-bar-wrap{height:115px;width:100%;display:flex;align-items:flex-end;background:rgba(148,163,184,.10);border-radius:5px 5px 0 0;overflow:hidden}.ff-month-bar-wrap span{display:block;width:100%;background:#0d9488;border-radius:5px 5px 0 0;min-height:0}.ff-month-col small{font-size:.68rem;color:var(--muted,#64748b)}.ff-alert{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.8rem 1rem;border-radius:9px;margin:.55rem 0;flex-wrap:wrap}.ff-alert>div{display:grid;gap:.2rem}.ff-alert small{opacity:.8}.ff-alert-danger{background:rgba(220,38,38,.09);border:1px solid rgba(220,38,38,.25)}.ff-alert-warning{background:rgba(245,158,11,.10);border:1px solid rgba(245,158,11,.28)}.ff-risk{display:inline-block;padding:.28rem .55rem;border-radius:20px;font-size:.75rem;font-weight:700;white-space:nowrap}.ff-risk-shortfall{background:rgba(220,38,38,.12);color:#dc2626}.ff-risk-watch{background:rgba(245,158,11,.16);color:#b45309}.ff-risk-healthy{background:rgba(22,163,74,.12);color:#15803d}.ff-table{min-width:1100px}.ff-notes ul{margin:.7rem 0 0;padding-left:1.2rem}.ff-notes li{margin:.35rem 0}@media(max-width:900px){.ff-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-overview{grid-template-columns:1fr}}@media(max-width:560px){.ff-filters{grid-template-columns:1fr}.ff-header-actions{width:100%}.ff-header-actions a{flex:1;justify-content:center}.ff-month-chart{gap:.2rem}.ff-month-value{font-size:.55rem}}
</style>
<script>
(function(){const p=document.getElementById('ffProgramme'),c=document.getElementById('ffCohort');if(!p||!c)return;function filter(){const id=p.value;Array.from(c.options).forEach(o=>{if(!o.dataset.programme)return;o.hidden=!!id&&o.dataset.programme!==id;if(o.hidden&&o.selected)c.value='0';});}p.addEventListener('change',filter);filter();})();
</script>

<style>
.finance-page-polished{font-family:Inter,system-ui,sans-serif;font-size:14px;line-height:1.5;max-width:100%;color:var(--text-color,inherit)}
.finance-page-polished *{box-sizing:border-box}
.finance-page-polished .fo-page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;margin:0 0 20px;padding:0 0 16px;border-bottom:1px solid var(--border-color,#e5e7eb)}
.finance-page-polished .fo-page-header h2{font-size:clamp(1.35rem,2vw,1.75rem);line-height:1.25;font-weight:700;margin:5px 0 7px;letter-spacing:-.025em}
.finance-page-polished .fo-page-header p{font-size:13px;line-height:1.55;margin:0;color:var(--muted,#64748b);max-width:760px}
.finance-page-polished .fo-card{padding:18px;border-radius:12px;margin-bottom:16px;min-width:0}
.finance-page-polished .fo-card-header h3,.finance-page-polished .fo-card h3{font-size:15px;line-height:1.4;font-weight:700}
.finance-page-polished .fo-muted,.finance-page-polished small,.finance-page-polished .ff-subline{font-size:12px;line-height:1.45}
.finance-page-polished .ff-kpis{grid-template-columns:repeat(auto-fit,minmax(min(100%,175px),1fr));gap:14px}
.finance-page-polished .ff-kpis h2{font-size:clamp(1.15rem,1.7vw,1.4rem);line-height:1.25;font-weight:700;overflow-wrap:anywhere}
.finance-page-polished .ff-filters{grid-template-columns:repeat(3,minmax(150px,1fr)) auto;gap:12px;padding:16px}
.finance-page-polished .ff-filters label{font-size:12px;font-weight:600}
.finance-page-polished .ff-filters select{font:inherit;font-size:13px;min-height:40px;padding:9px 11px}
.finance-page-polished .fo-btn{font-size:13px;line-height:1.3;padding:9px 13px;border-radius:8px}
.finance-page-polished .ff-method span,.finance-page-polished .ff-notes li{font-size:13px;line-height:1.55}
.finance-page-polished table{font-size:12px;line-height:1.45;min-width:1050px}
.finance-page-polished th{font-size:11px;font-weight:700;white-space:nowrap}
.finance-page-polished td,.finance-page-polished th{padding:10px 11px;vertical-align:middle}
.finance-page-polished .table-responsive{width:100%;overflow-x:auto}
@media(max-width:900px){.finance-page-polished .ff-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.finance-page-polished .ff-filters{grid-template-columns:1fr}.finance-page-polished .fo-card{padding:14px}.finance-page-polished .ff-header-actions{width:100%}.finance-page-polished .ff-header-actions a{flex:1;justify-content:center}}
</style>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
