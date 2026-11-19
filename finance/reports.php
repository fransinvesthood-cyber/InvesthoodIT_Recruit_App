<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user = current_user();
$flashes = render_flashes();
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$uid = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($uid < 1) { http_response_code(403); exit('Invalid Finance Officer account.'); }

$currentPage = 'reports';
$pageTitle = 'Financial Statements & Management Reporting';

function fr_e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fr_rows(mysqli $db, string $sql, string $types = '', array $params = []): array {
    $stmt = $db->prepare($sql);
    if (!$stmt) { error_log('Finance report query prepare failed: '.$db->error); return []; }
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { error_log('Finance report query failed: '.$stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}
function fr_sum(array $rows, string $key): float {
    $total = 0.0; foreach ($rows as $row) $total += (float)($row[$key] ?? 0); return $total;
}
function fr_valid_date(string $value, string $fallback): string {
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : $fallback;
}
function fr_csv_section($out, string $title, array $headers, array $rows, array $keys): void {
    fputcsv($out, [$title]); fputcsv($out, $headers);
    foreach ($rows as $row) { $line=[]; foreach ($keys as $key) $line[]=$row[$key] ?? ''; fputcsv($out,$line); }
    fputcsv($out, []);
}

$today = new DateTimeImmutable('today');
$fyStartYear = ((int)$today->format('n') >= 4) ? (int)$today->format('Y') : (int)$today->format('Y') - 1;
$defaultFrom = sprintf('%04d-04-01', $fyStartYear);
$defaultTo = sprintf('%04d-03-31', $fyStartYear + 1);
$from = fr_valid_date((string)($_GET['from'] ?? ''), $defaultFrom);
$to = fr_valid_date((string)($_GET['to'] ?? ''), $defaultTo);
if ($from > $to) { [$from, $to] = [$to, $from]; }
$programmeId = max(0, (int)($_GET['programme_id'] ?? 0));
$cohortId = max(0, (int)($_GET['cohort_id'] ?? 0));
$financialYear = (string)($_GET['financial_year'] ?? (sprintf('%04d/%04d', $fyStartYear, $fyStartYear + 1)));
if (!preg_match('/^\d{4}\/\d{4}$/', $financialYear)) $financialYear = sprintf('%04d/%04d', $fyStartYear, $fyStartYear + 1);

$required = ['finance_budgets','finance_expenditures','programmes','cohorts','stipend_schedules','stipend_schedule_lines'];
$missing = array_values(array_filter($required, fn($t) => !fo_has_table($conn, $t)));
$programmes = fr_rows($conn, 'SELECT id,name FROM programmes ORDER BY name');
$cohorts = fr_rows($conn, 'SELECT id,programme_id,name FROM cohorts ORDER BY name');

$budgetRows = $expenseRows = $categoryRows = $stipendRows = $paymentRows = $monthlyRows = $reconRows = [];
if (!$missing) {
    $budgetSql = "SELECT b.id,b.budget_name,b.financial_year,b.allocated_amount,b.status,
                         p.name AS programme_name,COALESCE(c.name,'Programme-wide') AS cohort_name,
                         COALESCE(SUM(CASE WHEN x.status='paid' AND x.expense_date BETWEEN ? AND ? THEN x.amount ELSE 0 END),0) AS paid_amount,
                         COALESCE(SUM(CASE WHEN x.status='committed' AND x.expense_date BETWEEN ? AND ? THEN x.amount ELSE 0 END),0) AS committed_amount
                  FROM finance_budgets b
                  INNER JOIN programmes p ON p.id=b.programme_id
                  LEFT JOIN cohorts c ON c.id=b.cohort_id
                  LEFT JOIN finance_expenditures x ON x.budget_id=b.id
                  WHERE b.financial_year=?";
    $bt='sssss'; $bp=[$from,$to,$from,$to,$financialYear];
    if ($programmeId) { $budgetSql.=' AND b.programme_id=?'; $bt.='i'; $bp[]=$programmeId; }
    if ($cohortId) { $budgetSql.=' AND b.cohort_id=?'; $bt.='i'; $bp[]=$cohortId; }
    $budgetSql.=' GROUP BY b.id,b.budget_name,b.financial_year,b.allocated_amount,b.status,p.name,c.name ORDER BY p.name,c.name,b.budget_name';
    $budgetRows=fr_rows($conn,$budgetSql,$bt,$bp);

    $expenseSql = "SELECT x.expense_date,x.category,x.description,x.reference_no,x.amount,x.status,
                          p.name AS programme_name,COALESCE(c.name,'Programme-wide') AS cohort_name
                   FROM finance_expenditures x
                   INNER JOIN finance_budgets b ON b.id=x.budget_id
                   INNER JOIN programmes p ON p.id=b.programme_id
                   LEFT JOIN cohorts c ON c.id=b.cohort_id
                   WHERE x.expense_date BETWEEN ? AND ? AND b.financial_year=?";
    $et='sss'; $ep=[$from,$to,$financialYear];
    if ($programmeId) { $expenseSql.=' AND b.programme_id=?'; $et.='i'; $ep[]=$programmeId; }
    if ($cohortId) { $expenseSql.=' AND b.cohort_id=?'; $et.='i'; $ep[]=$cohortId; }
    $expenseSql.=' ORDER BY x.expense_date DESC,x.id DESC';
    $expenseRows=fr_rows($conn,$expenseSql,$et,$ep);

    $categorySql = "SELECT x.category,
                           SUM(CASE WHEN x.status='paid' THEN x.amount ELSE 0 END) AS paid_amount,
                           SUM(CASE WHEN x.status='committed' THEN x.amount ELSE 0 END) AS committed_amount,
                           COUNT(*) AS transaction_count
                    FROM finance_expenditures x
                    INNER JOIN finance_budgets b ON b.id=x.budget_id
                    WHERE x.expense_date BETWEEN ? AND ? AND b.financial_year=?";
    $ct='sss'; $cp=[$from,$to,$financialYear];
    if ($programmeId) { $categorySql.=' AND b.programme_id=?'; $ct.='i'; $cp[]=$programmeId; }
    if ($cohortId) { $categorySql.=' AND b.cohort_id=?'; $ct.='i'; $cp[]=$cohortId; }
    $categorySql.=' GROUP BY x.category ORDER BY (paid_amount+committed_amount) DESC';
    $categoryRows=fr_rows($conn,$categorySql,$ct,$cp);

    // Allocate verified schedule payments proportionally to cohort line values.
    // This avoids duplicating a schedule's payment total when one schedule covers multiple cohorts.
    if (fo_has_table($conn,'finance_payment_records')) {
        $stipendSql = "SELECT lc.programme_name,lc.cohort_name,
                              SUM(lc.cohort_amount) AS approved_amount,
                              SUM(CASE WHEN tl.line_total>0 THEN COALESCE(pay.verified_amount,0)*(lc.cohort_amount/tl.line_total) ELSE 0 END) AS verified_paid,
                              SUM(CASE WHEN tl.line_total>0 THEN lc.cohort_amount-(COALESCE(pay.verified_amount,0)*(lc.cohort_amount/tl.line_total)) ELSE lc.cohort_amount END) AS outstanding_amount,
                              COUNT(DISTINCT lc.schedule_id) AS schedule_count
                       FROM (
                           SELECT ss.id AS schedule_id,
                                  COALESCE(NULLIF(l.programme_name_snapshot,''),ss.programme_name,'Unassigned programme') AS programme_name,
                                  COALESCE(NULLIF(l.cohort_name_snapshot,''),'Programme-wide / unspecified') AS cohort_name,
                                  SUM(l.gross_amount) AS cohort_amount
                           FROM stipend_schedules ss
                           INNER JOIN stipend_schedule_lines l ON l.schedule_id=ss.id
                           WHERE ss.status IN ('approved','processed')
                             AND ss.period_start<=? AND ss.period_end>=?
                           GROUP BY ss.id,COALESCE(NULLIF(l.programme_name_snapshot,''),ss.programme_name,'Unassigned programme'),
                                    COALESCE(NULLIF(l.cohort_name_snapshot,''),'Programme-wide / unspecified')
                       ) lc
                       INNER JOIN (
                           SELECT schedule_id,SUM(gross_amount) AS line_total
                           FROM stipend_schedule_lines GROUP BY schedule_id
                       ) tl ON tl.schedule_id=lc.schedule_id
                       LEFT JOIN (
                           SELECT schedule_id,SUM(CASE WHEN verification_status='verified' AND payment_date BETWEEN ? AND ? THEN payment_amount ELSE 0 END) AS verified_amount
                           FROM finance_payment_records GROUP BY schedule_id
                       ) pay ON pay.schedule_id=lc.schedule_id
                       WHERE 1=1";
        $st='ssss'; $sp=[$to,$from,$from,$to];
        if ($programmeId) { $stipendSql.=' AND lc.programme_name=(SELECT name FROM programmes WHERE id=?)'; $st.='i'; $sp[]=$programmeId; }
        if ($cohortId) { $stipendSql.=' AND lc.cohort_name=(SELECT name FROM cohorts WHERE id=?)'; $st.='i'; $sp[]=$cohortId; }
        $stipendSql.=' GROUP BY lc.programme_name,lc.cohort_name ORDER BY lc.programme_name,lc.cohort_name';
        $stipendRows=fr_rows($conn,$stipendSql,$st,$sp);
    }

    $monthlySql = "SELECT DATE_FORMAT(x.expense_date,'%Y-%m') AS month_key,
                          DATE_FORMAT(x.expense_date,'%b %Y') AS month_label,
                          SUM(CASE WHEN x.status='paid' THEN x.amount ELSE 0 END) AS paid_amount,
                          SUM(CASE WHEN x.status='committed' THEN x.amount ELSE 0 END) AS committed_amount
                   FROM finance_expenditures x
                   INNER JOIN finance_budgets b ON b.id=x.budget_id
                   WHERE x.expense_date BETWEEN ? AND ? AND b.financial_year=?";
    $mt='sss'; $mp=[$from,$to,$financialYear];
    if ($programmeId) { $monthlySql.=' AND b.programme_id=?'; $mt.='i'; $mp[]=$programmeId; }
    if ($cohortId) { $monthlySql.=' AND b.cohort_id=?'; $mt.='i'; $mp[]=$cohortId; }
    $monthlySql.=" GROUP BY DATE_FORMAT(x.expense_date,'%Y-%m'),DATE_FORMAT(x.expense_date,'%b %Y') ORDER BY month_key";
    $monthlyRows=fr_rows($conn,$monthlySql,$mt,$mp);

    if (fo_has_table($conn,'finance_reconciliations')) {
        $reconRows=fr_rows($conn,"SELECT reconciliation_status,COUNT(*) AS record_count,SUM(variance_amount) AS variance_total
                                  FROM finance_reconciliations GROUP BY reconciliation_status");
    }
}

$allocated=fr_sum($budgetRows,'allocated_amount');
$paidExpenses=fr_sum($budgetRows,'paid_amount');
$committedExpenses=fr_sum($budgetRows,'committed_amount');
$totalSpend=$paidExpenses+$committedExpenses;
$verifiedStipends=fr_sum($stipendRows,'verified_paid');
$stipendApproved=fr_sum($stipendRows,'approved_amount');
$stipendOutstanding=fr_sum($stipendRows,'outstanding_amount');
$available=$allocated-$totalSpend;
$utilisation=$allocated>0?($totalSpend/$allocated)*100:0;
$maxMonthly=0.0; foreach($monthlyRows as $m) $maxMonthly=max($maxMonthly,(float)$m['paid_amount']+(float)$m['committed_amount']);
$maxCategory=0.0; foreach($categoryRows as $c) $maxCategory=max($maxCategory,(float)$c['paid_amount']+(float)$c['committed_amount']);

if (($_GET['export'] ?? '') === 'csv' && !$missing) {
    $filename='investhood-financial-management-report-'.str_replace('/','-',$financialYear).'.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out=fopen('php://output','w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out,['Investhood IT - Financial Statements & Management Report']);
    fputcsv($out,['Financial year',$financialYear,'Period',$from.' to '.$to]);
    fputcsv($out,['Programme filter',$programmeId?:'All','Cohort filter',$cohortId?:'All']);
    fputcsv($out,[]);
    fputcsv($out,['Management summary']);
    fputcsv($out,['Budget allocated','Paid expenditure','Committed expenditure','Total expenditure','Budget available','Utilisation %','Approved stipend value','Verified stipend payments','Outstanding stipend value']);
    fputcsv($out,[$allocated,$paidExpenses,$committedExpenses,$totalSpend,$available,round($utilisation,2),$stipendApproved,$verifiedStipends,$stipendOutstanding]);
    fputcsv($out,[]);
    fr_csv_section($out,'Budget vs actual',['Programme','Cohort','Budget','Financial year','Allocated','Paid','Committed'],$budgetRows,['programme_name','cohort_name','budget_name','financial_year','allocated_amount','paid_amount','committed_amount']);
    fr_csv_section($out,'Stipend position',['Programme','Cohort','Approved stipend value','Verified payments','Outstanding','Schedules'],$stipendRows,['programme_name','cohort_name','approved_amount','verified_paid','outstanding_amount','schedule_count']);
    fr_csv_section($out,'Expenditure transactions',['Date','Programme','Cohort','Category','Description','Reference','Amount','Status'],$expenseRows,['expense_date','programme_name','cohort_name','category','description','reference_no','amount','status']);
    fr_csv_section($out,'Expenditure by category',['Category','Paid','Committed','Transactions'],$categoryRows,['category','paid_amount','committed_amount','transaction_count']);
    fclose($out); exit;
}

require __DIR__ . '/_layout_start.php';
?>
<div class="finance-page-polished fr-page">
  <div class="fo-page-header">
    <div><span>Finance / Management Oversight</span><h2>Financial Statements &amp; Management Reporting</h2><p>Consolidated programme and cohort reporting for budget allocations, operational expenditure, stipend commitments, verified payments and financial control.</p></div>
    <div class="fr-actions">
      <a class="fo-btn fo-btn--secondary" href="<?=url('finance/reports.php?'.http_build_query(array_merge($_GET,['export'=>'csv'])))?>"><i class="fas fa-file-csv"></i> Export CSV</a>
      <button class="fo-btn fo-btn--primary" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
    </div>
  </div>

  <?php if($missing): ?>
    <div class="fo-alert fo-alert--warning"><i class="fas fa-database"></i><div><strong>Reporting database setup is incomplete.</strong><p>Missing required tables: <?=fr_e(implode(', ',$missing))?>. Apply the existing Finance Officer database setup/migration before generating reports. No sample figures are displayed.</p></div></div>
  <?php else: ?>
  <section class="fo-card fr-filter-card">
    <form method="get" class="fr-filters">
      <label>Financial year<select name="financial_year"><?php for($y=$fyStartYear-2;$y<=$fyStartYear+1;$y++):$fy=sprintf('%04d/%04d',$y,$y+1);?><option value="<?=fr_e($fy)?>" <?=$financialYear===$fy?'selected':''?>><?=fr_e($fy)?></option><?php endfor;?></select></label>
      <label>From<input type="date" name="from" value="<?=fr_e($from)?>" required></label>
      <label>To<input type="date" name="to" value="<?=fr_e($to)?>" required></label>
      <label>Programme<select name="programme_id"><option value="0">All programmes</option><?php foreach($programmes as $p):?><option value="<?=(int)$p['id']?>" <?=(int)$p['id']===$programmeId?'selected':''?>><?=fr_e($p['name'])?></option><?php endforeach;?></select></label>
      <label>Cohort<select name="cohort_id"><option value="0">All cohorts</option><?php foreach($cohorts as $c):?><option value="<?=(int)$c['id']?>" <?=(int)$c['id']===$cohortId?'selected':''?>><?=fr_e($c['name'])?></option><?php endforeach;?></select></label>
      <div class="fr-filter-buttons"><button class="fo-btn fo-btn--primary" type="submit"><i class="fas fa-filter"></i> Apply filters</button><a class="fo-btn fo-btn--secondary" href="<?=url('finance/reports.php')?>">Reset</a></div>
    </form>
    <div class="fr-period-note">Reporting period: <strong><?=fr_e(fo_date($from))?> – <?=fr_e(fo_date($to))?></strong> · Budget allocations: financial year <strong><?=fr_e($financialYear)?></strong> · Cohort filtering shows cohort-specific budgets; programme-wide allocations remain visible only in the all-cohorts / programme view.</div>
  </section>

  <div class="fo-dashboard-grid fr-kpis">
    <article class="fo-card"><span>Budget allocated</span><h2><?=e(fo_money($allocated))?></h2><small>Active and closed allocations in selected financial year</small></article>
    <article class="fo-card"><span>Paid expenditure</span><h2><?=e(fo_money($paidExpenses))?></h2><small>Recorded operational expenditure marked paid</small></article>
    <article class="fo-card"><span>Committed expenditure</span><h2><?=e(fo_money($committedExpenses))?></h2><small>Recorded obligations not yet paid</small></article>
    <article class="fo-card"><span>Budget available</span><h2 class="<?=$available<0?'fr-negative':''?>"><?=e(fo_money($available))?></h2><small>Allocation less paid and committed expenditure</small></article>
    <article class="fo-card"><span>Budget utilisation</span><h2><?=$utilisation<0?0:number_format($utilisation,1)?>%</h2><small>Paid plus committed against allocation</small><div class="fr-progress"><span style="width:<?=min(100,max(0,$utilisation))?>%"></span></div></article>
    <article class="fo-card"><span>Approved stipend value</span><h2><?=e(fo_money($stipendApproved))?></h2><small>Approved / processed stipend line value in period</small></article>
    <article class="fo-card"><span>Verified stipend payments</span><h2><?=e(fo_money($verifiedStipends))?></h2><small>Payment records verified against evidence</small></article>
    <article class="fo-card"><span>Outstanding stipend value</span><h2><?=e(fo_money($stipendOutstanding))?></h2><small>Approved stipend value less allocated verified payments</small></article>
  </div>

  <section class="fo-card">
    <div class="fr-section-head"><div><h3><i class="fas fa-scale-balanced"></i> Budget vs Actual Expenditure Statement</h3><p>Programme and cohort allocation position for the selected financial year and date range.</p></div><span class="fo-status fo-status--<?= $available<0?'danger':'success'?>"><?= $available<0?'Overall overspend':'Within allocation'?></span></div>
    <div class="table-responsive"><table class="table fr-table"><thead><tr><th>Programme / Cohort</th><th>Budget</th><th>Allocated</th><th>Paid</th><th>Committed</th><th>Total spend</th><th>Available</th><th>Utilisation</th></tr></thead><tbody>
    <?php if(!$budgetRows):?><tr><td colspan="8" class="fr-empty">No budget allocations match the selected filters.</td></tr><?php endif;?>
    <?php foreach($budgetRows as $b):$sp=(float)$b['paid_amount']+(float)$b['committed_amount'];$bal=(float)$b['allocated_amount']-$sp;$pct=(float)$b['allocated_amount']>0?$sp/(float)$b['allocated_amount']*100:0;?>
      <tr><td><strong><?=fr_e($b['programme_name'])?></strong><small><?=fr_e($b['cohort_name'])?></small></td><td><?=fr_e($b['budget_name'])?></td><td><?=e(fo_money($b['allocated_amount']))?></td><td><?=e(fo_money($b['paid_amount']))?></td><td><?=e(fo_money($b['committed_amount']))?></td><td><strong><?=e(fo_money($sp))?></strong></td><td class="<?=$bal<0?'fr-negative':'fr-positive'?>"><?=e(fo_money($bal))?></td><td><div class="fr-inline-progress"><span style="width:<?=min(100,max(0,$pct))?>%"></span></div><?=number_format($pct,1)?>%</td></tr>
    <?php endforeach;?>
    </tbody><tfoot><tr><th colspan="2">Total</th><th><?=e(fo_money($allocated))?></th><th><?=e(fo_money($paidExpenses))?></th><th><?=e(fo_money($committedExpenses))?></th><th><?=e(fo_money($totalSpend))?></th><th class="<?=$available<0?'fr-negative':'fr-positive'?>"><?=e(fo_money($available))?></th><th><?=number_format($utilisation,1)?>%</th></tr></tfoot></table></div>
  </section>

  <div class="fr-two-col">
    <section class="fo-card"><div class="fr-section-head"><div><h3><i class="fas fa-chart-pie"></i> Expenditure by Category</h3><p>Paid and committed operational expenditure.</p></div></div>
      <?php if(!$categoryRows):?><div class="fr-empty">No expenditure categories found for this period.</div><?php endif;?>
      <?php foreach($categoryRows as $cat):$v=(float)$cat['paid_amount']+(float)$cat['committed_amount'];$pct=$maxCategory>0?$v/$maxCategory*100:0;?>
      <div class="fr-bar-row"><div><strong><?=fr_e($cat['category'])?></strong><span><?=e(fo_money($v))?> · <?=number_format((int)$cat['transaction_count'])?> transaction(s)</span></div><div class="fr-bar"><span style="width:<?=number_format($pct,2,'.','')?>%"></span></div><small>Paid <?=e(fo_money($cat['paid_amount']))?> · Committed <?=e(fo_money($cat['committed_amount']))?></small></div>
      <?php endforeach;?>
    </section>
    <section class="fo-card"><div class="fr-section-head"><div><h3><i class="fas fa-chart-column"></i> Monthly Expenditure Trend</h3><p>Operational expenditure by transaction date.</p></div></div>
      <?php if(!$monthlyRows):?><div class="fr-empty">No monthly expenditure data for this period.</div><?php endif;?>
      <?php foreach($monthlyRows as $m):$v=(float)$m['paid_amount']+(float)$m['committed_amount'];$pct=$maxMonthly>0?$v/$maxMonthly*100:0;?>
      <div class="fr-month-row"><div><strong><?=fr_e($m['month_label'])?></strong><span><?=e(fo_money($v))?></span></div><div class="fr-bar"><span style="width:<?=number_format($pct,2,'.','')?>%"></span></div></div>
      <?php endforeach;?>
    </section>
  </div>

  <section class="fo-card">
    <div class="fr-section-head"><div><h3><i class="fas fa-users"></i> Stipend Commitments by Programme &amp; Cohort</h3><p>Approved and processed stipend schedule lines, with verified payments allocated proportionally across cohorts in a shared schedule.</p></div></div>
    <div class="table-responsive"><table class="table fr-table"><thead><tr><th>Programme</th><th>Cohort</th><th>Schedules</th><th>Approved value</th><th>Verified paid</th><th>Outstanding</th></tr></thead><tbody>
    <?php if(!$stipendRows):?><tr><td colspan="6" class="fr-empty">No approved stipend schedules match this reporting period.</td></tr><?php endif;?>
    <?php foreach($stipendRows as $s):?><tr><td><?=fr_e($s['programme_name'])?></td><td><?=fr_e($s['cohort_name'])?></td><td><?=number_format((int)$s['schedule_count'])?></td><td><?=e(fo_money($s['approved_amount']))?></td><td><?=e(fo_money($s['verified_paid']))?></td><td><strong><?=e(fo_money($s['outstanding_amount']))?></strong></td></tr><?php endforeach;?>
    </tbody></table></div>
  </section>

  <section class="fo-card">
    <div class="fr-section-head"><div><h3><i class="fas fa-receipt"></i> Expenditure Transaction Register</h3><p>Transaction-level detail supporting the management expenditure statement.</p></div><span class="fo-status fo-status--info"><?=number_format(count($expenseRows))?> records</span></div>
    <div class="table-responsive"><table class="table fr-table"><thead><tr><th>Date</th><th>Programme / Cohort</th><th>Category</th><th>Description</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody>
    <?php if(!$expenseRows):?><tr><td colspan="7" class="fr-empty">No expenditure transactions match the selected filters.</td></tr><?php endif;?>
    <?php foreach($expenseRows as $x):?><tr><td><?=fr_e(fo_date($x['expense_date']))?></td><td><?=fr_e($x['programme_name'])?><small><?=fr_e($x['cohort_name'])?></small></td><td><?=fr_e($x['category'])?></td><td><?=fr_e($x['description'])?></td><td><?=fr_e($x['reference_no']?:'—')?></td><td><?=e(fo_money($x['amount']))?></td><td><span class="fo-status fo-status--<?=($x['status']==='paid'?'success':'warning')?>"><?=fr_e(fo_label($x['status']))?></span></td></tr><?php endforeach;?>
    </tbody></table></div>
  </section>

  <?php if($reconRows):?><section class="fo-card"><div class="fr-section-head"><div><h3><i class="fas fa-shield-halved"></i> Reconciliation Control Summary</h3><p>Current reconciliation statuses across recorded schedules.</p></div><a class="fo-btn fo-btn--secondary" href="<?=url('finance/reconciliation.php')?>">Open reconciliation</a></div><div class="fr-recon-grid"><?php foreach($reconRows as $r):?><div><span><?=fr_e(fo_label($r['reconciliation_status']))?></span><strong><?=number_format((int)$r['record_count'])?></strong><small>Net variance <?=e(fo_money($r['variance_total']))?></small></div><?php endforeach;?></div></section><?php endif;?>
  <div class="fr-footnote"><strong>Reporting basis:</strong> Budget availability is calculated as allocation less recorded paid and committed operational expenditure. Stipend commitments and verified stipend payments are presented separately to avoid double counting. Only verified payment records are treated as paid. These management reports are derived from records entered in the portal and do not replace audited statutory financial statements or a general-ledger trial balance.</div>
  <?php endif;?>
</div>
<style>
.fr-page{font-family:Inter,system-ui,sans-serif;color:var(--text-color,inherit);font-size:13px;line-height:1.5}
.fr-page *{box-sizing:border-box}
.fr-page .fo-page-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;border-bottom:1px solid var(--border-color,#e5e7eb);padding-bottom:16px;margin-bottom:16px}
.fr-page .fo-page-header h2{font-size:clamp(1.35rem,2vw,1.75rem);margin:5px 0;font-weight:750;letter-spacing:-.03em}
.fr-page .fo-page-header p,.fr-page .fr-section-head p{color:var(--muted,#64748b);margin:3px 0 0;max-width:760px}
.fr-actions{display:flex;gap:8px;flex-wrap:wrap}
.fr-page .fo-card{background:var(--surface,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:13px;padding:17px;margin-bottom:15px;min-width:0;box-shadow:0 2px 10px rgba(15,23,42,.035)}
.fr-filters{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr)) auto;gap:10px;align-items:end}
.fr-filters label{display:grid;gap:5px;font-size:11px;font-weight:700;color:var(--muted,#64748b)}
.fr-filters input,.fr-filters select{width:100%;min-width:0;min-height:39px;padding:8px 10px;border:1px solid var(--border-color,#d1d5db);border-radius:8px;background:var(--surface,#fff);color:inherit;font:inherit}
.fr-filter-buttons{display:flex;gap:6px;align-items:center}
.fr-period-note{margin-top:11px;color:var(--muted,#64748b);font-size:11px}
.fr-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 15px}
.fr-kpis .fo-card{margin:0;padding:14px}
.fr-kpis .fo-card>span{display:block;color:var(--muted,#64748b);font-size:11px;font-weight:650}
.fr-kpis h2{font-size:clamp(1.15rem,1.6vw,1.5rem);line-height:1.25;margin:7px 0 4px;overflow-wrap:anywhere}
.fr-kpis small{display:block;color:var(--muted,#64748b);font-size:10px;min-height:28px}
.fr-progress,.fr-inline-progress,.fr-bar{height:8px;background:var(--border-color,#e5e7eb);border-radius:20px;overflow:hidden}
.fr-progress{margin-top:8px}.fr-progress span,.fr-inline-progress span,.fr-bar span{display:block;height:100%;background:#2876d2;border-radius:20px}
.fr-section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.fr-section-head h3{font-size:15px;font-weight:750;margin:0}
.fr-table{width:100%;border-collapse:collapse;min-width:850px;font-size:11px}
.fr-table th,.fr-table td{padding:10px 9px;border-bottom:1px solid var(--border-color,#e5e7eb);text-align:left;vertical-align:middle}
.fr-table th{font-size:10px;text-transform:uppercase;letter-spacing:.035em;color:var(--muted,#64748b);white-space:nowrap}
.fr-table td small{display:block;color:var(--muted,#64748b);font-size:10px}
.fr-table tfoot th{border-top:2px solid var(--border-color,#cbd5e1);font-size:11px;color:inherit}
.fr-negative{color:#dc2626!important;font-weight:750}.fr-positive{color:#15803d!important;font-weight:700}
.fr-inline-progress{display:inline-block;width:65px;vertical-align:middle;margin-right:5px;height:6px}
.fr-two-col{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.fr-bar-row{margin:12px 0}
.fr-bar-row>div:first-child,.fr-month-row>div:first-child{display:flex;justify-content:space-between;gap:10px;margin-bottom:5px}
.fr-bar-row>div:first-child span,.fr-bar-row>small,.fr-month-row span{font-size:10px;color:var(--muted,#64748b)}
.fr-bar{height:9px}
.fr-month-row{margin:11px 0}
.fr-empty{text-align:center;color:var(--muted,#64748b);padding:20px!important}
.fr-recon-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px}
.fr-recon-grid>div{padding:11px;border:1px solid var(--border-color,#e5e7eb);border-radius:9px;display:grid;gap:3px}
.fr-recon-grid span,.fr-recon-grid small{font-size:10px;color:var(--muted,#64748b)}
.fr-recon-grid strong{font-size:19px}
.fr-footnote{padding:12px 14px;margin:4px 0 18px;border-left:3px solid #64748b;background:var(--surface,#fff);color:var(--muted,#64748b);font-size:10px}
@media(max-width:1100px){.fr-filters{grid-template-columns:repeat(3,minmax(130px,1fr))}.fr-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:720px){.fr-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.fr-filter-buttons{grid-column:1/-1}.fr-two-col{grid-template-columns:1fr}.fr-page .fo-card{padding:13px}.fr-actions{width:100%}.fr-actions .fo-btn{flex:1;justify-content:center}}
@media print{
  @page{size:A4 landscape;margin:12mm}
  body.ux-body{overflow:visible!important;background:#fff!important}
  .fo-sidebar,.fo-overlay,.ux-navbar,.fr-filter-card,.fr-actions{display:none!important}
  .ux-app,.ux-main,.ux-content{height:auto!important;max-height:none!important;overflow:visible!important;margin:0!important;width:100%!important;padding:0!important}
  .fr-page{color:#111!important;font-size:9px}
  .fr-page .fo-card{box-shadow:none;border:1px solid #ccc;break-inside:avoid}
  .fr-page .fr-table{min-width:0;font-size:8px}
  .fr-page .fr-table th,.fr-page .fr-table td{padding:5px}
  .fr-page .fo-page-header{margin-bottom:8px}
  .fr-page .fr-kpis{grid-template-columns:repeat(4,1fr)}
  .fr-page .fr-two-col{grid-template-columns:repeat(2,1fr)}
}
</style>
<?php require __DIR__ . '/_layout_end.php'; ?>
