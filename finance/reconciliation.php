<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user = current_user();
$flashes = render_flashes();
$currentPage = 'reconciliation';
$pageTitle = 'Financial Reconciliation';
require_once __DIR__ . '/_helpers.php';
$conn = Database::getConnection();
$uid = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(403); exit('Invalid Finance Officer account.'); }
if (!fo_has_table($conn, 'finance_payment_records') || !fo_has_table($conn, 'finance_reconciliations')) {
    require __DIR__ . '/_layout_start.php'; ?>
    <div class="fo-page-header"><div><span>Finance Controls</span><h2>Financial Reconciliation</h2><p>Compare approved stipend schedules with recorded payments.</p></div></div>
    <div class="fo-alert fo-alert--warning"><i class="fas fa-database"></i><div><strong>Database migration required</strong><p>Import <code>database/financial_reconciliation.sql</code> into the existing Investhood database, then reload this page.</p></div></div>
    <?php require __DIR__ . '/_layout_end.php'; exit;
}
$filter = (string)($_GET['status'] ?? 'all');
$allowed = ['all','unreconciled','matched','discrepancy'];
if (!in_array($filter, $allowed, true)) $filter = 'all';
$stats = ['schedules'=>0,'matched'=>0,'discrepancies'=>0,'unreconciled'=>0,'expected'=>0.0,'actual'=>0.0];
$sql = "SELECT ss.id, ss.schedule_reference, ss.programme_name, ss.period_start, ss.period_end, ss.participant_count, ss.total_amount, ss.status AS schedule_status,
        COALESCE(p.actual_amount,0) AS actual_amount, COALESCE(p.payment_count,0) AS payment_count,
        COALESCE(p.pending_count,0) AS pending_count, r.reconciliation_status, r.variance_amount, r.reconciled_at
        FROM stipend_schedules ss
        LEFT JOIN (SELECT schedule_id, SUM(payment_amount) actual_amount, COUNT(*) payment_count,
          SUM(verification_status <> 'verified') pending_count FROM finance_payment_records GROUP BY schedule_id) p ON p.schedule_id=ss.id
        LEFT JOIN (SELECT fr.* FROM finance_reconciliations fr INNER JOIN (SELECT schedule_id, MAX(id) max_id FROM finance_reconciliations GROUP BY schedule_id) latest ON latest.max_id=fr.id) r ON r.schedule_id=ss.id
        WHERE ss.status IN ('approved','processed') AND ss.status <> 'superseded'";
$params=[]; $types='';
if ($filter==='unreconciled') $sql .= " AND (r.id IS NULL OR r.reconciliation_status IN ('unverified','reopened'))";
elseif ($filter==='matched') $sql .= " AND r.reconciliation_status='matched'";
elseif ($filter==='discrepancy') $sql .= " AND r.reconciliation_status IN ('short_payment','overpayment')";
$sql .= ' ORDER BY ss.approved_at DESC, ss.id DESC';
$res=$conn->query($sql); $rows=[]; if($res){while($row=$res->fetch_assoc()){$rows[]=$row;$stats['schedules']++;$stats['expected']+=(float)$row['total_amount'];$stats['actual']+=(float)$row['actual_amount'];$rs=$row['reconciliation_status']??'';if($rs==='matched')$stats['matched']++;elseif(in_array($rs,['short_payment','overpayment'],true))$stats['discrepancies']++;else $stats['unreconciled']++;}}
require __DIR__ . '/_layout_start.php'; ?>
<div class="fo-page-header"><div><span>Finance Controls</span><h2>Financial Reconciliation</h2><p>Match verified payment records against approved stipend schedule totals. Reconciliation does not itself confirm bank settlement.</p></div><a class="fo-btn fo-btn--secondary" href="<?=url('finance/dashboard.php')?>"><i class="fas fa-arrow-left"></i> Dashboard</a></div>
<div class="fo-stats fo-stats--4"><article><i class="fas fa-file-invoice-dollar"></i><strong><?=number_format($stats['schedules'])?></strong><span>Schedules in view</span></article><article><i class="fas fa-circle-check"></i><strong><?=number_format($stats['matched'])?></strong><span>Matched reconciliations</span></article><article class="fo-stat-alert fo-stat-alert--warning"><i class="fas fa-triangle-exclamation"></i><strong><?=number_format($stats['discrepancies'])?></strong><span>Payment discrepancies</span></article><article><i class="fas fa-hourglass-half"></i><strong><?=number_format($stats['unreconciled'])?></strong><span>Unreconciled schedules</span></article></div>
<div class="fo-card"><div class="fo-card__header fo-card__header--split"><div><h3>Approved and Processed Schedules</h3><p>Expected total: <?=e(fo_money($stats['expected']))?> · Recorded payments: <?=e(fo_money($stats['actual']))?></p></div><div class="fo-filter-links"><?php foreach(['all'=>'All','unreconciled'=>'Unreconciled','matched'=>'Matched','discrepancy'=>'Discrepancies'] as $k=>$label):?><a class="fo-btn <?=$filter===$k?'fo-btn--primary':'fo-btn--secondary'?>" href="<?=url('finance/reconciliation.php?status='.$k)?>"><?=e($label)?></a><?php endforeach;?></div></div>
<?php if(!$rows):?><div class="fo-empty"><i class="fas fa-scale-balanced"></i><strong>No schedules found</strong><span>Approved or processed stipend schedules will appear here.</span></div><?php else:?><div class="fo-table-wrap"><table><thead><tr><th>Schedule</th><th>Programme</th><th>Expected</th><th>Payments recorded</th><th>Verification</th><th>Reconciliation</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $r):$rs=(string)($r['reconciliation_status']??'unreconciled');$badge=$rs==='matched'?'success':(in_array($rs,['short_payment','overpayment'],true)?'danger':'warning');?><tr><td><strong><?=e($r['schedule_reference'])?></strong><span><?=e(fo_date($r['period_start']))?> – <?=e(fo_date($r['period_end']))?></span></td><td><?=e($r['programme_name']?:'All programmes')?></td><td><strong><?=e(fo_money($r['total_amount']))?></strong></td><td><?=e(fo_money($r['actual_amount']))?><span><?=number_format((int)$r['payment_count'])?> record(s) · <?=number_format((int)$r['pending_count'])?> unverified</span></td><td><span class="fo-status fo-status--<?=((int)$r['pending_count']===0 && (int)$r['payment_count']>0)?'success':'warning'?>"><?=((int)$r['pending_count']===0 && (int)$r['payment_count']>0)?'All verified':'Verification pending'?></span></td><td><span class="fo-status fo-status--<?=$badge?>"><?=e(fo_label($rs))?></span><?php if($r['variance_amount']!==null):?><span>Variance <?=e(fo_money($r['variance_amount']))?></span><?php endif;?></td><td><a class="fo-btn fo-btn--secondary" href="<?=url('finance/payment_verification.php?schedule_id='.(int)$r['id'])?>"><i class="fas fa-receipt"></i> Verify / Reconcile</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
<?php require __DIR__ . '/_layout_end.php'; ?>
