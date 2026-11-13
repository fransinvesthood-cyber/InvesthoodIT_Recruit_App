<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user = current_user();
$flashes = render_flashes();
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$uid = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($uid < 1) { http_response_code(403); exit('Invalid Finance Officer account.'); }

$currentPage = 'supplier_disbursements';
$pageTitle = 'Supplier Payment Processing & Disbursement Management';

function sd_e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function sd_money($v): string { return 'R ' . number_format((float)$v, 2); }
function sd_redirect(): never { header('Location: ' . url('finance/supplier_disbursements.php')); exit; }
function sd_history(mysqli $db, int $batchId, ?int $itemId, int $uid, string $action, string $note = '', ?float $amount = null): void {
    $st = $db->prepare('INSERT INTO finance_supplier_disbursement_history(batch_id,item_id,acted_by,action,action_note,amount) VALUES(?,?,?,?,?,?)');
    if (!$st) throw new RuntimeException('Could not prepare disbursement history.');
    $st->bind_param('iiissd', $batchId, $itemId, $uid, $action, $note, $amount);
    if (!$st->execute()) throw new RuntimeException('Could not write disbursement history.');
    $st->close();
}
function sd_status_class(string $status): string {
    return match ($status) {
        'completed' => 'success',
        'authorised', 'processing', 'partially_completed' => 'info',
        'submitted', 'draft' => 'warning',
        'failed', 'rejected' => 'danger',
        default => 'info'
    };
}

$required = ['finance_supplier_invoices','finance_supplier_invoice_payments','finance_supplier_disbursements','finance_supplier_disbursement_items','finance_supplier_disbursement_history'];
if (array_filter($required, fn($t) => !fo_has_table($conn, $t))) {
    require __DIR__ . '/_layout_start.php'; ?>
    <div class="fo-page-header"><div><span>Finance / Accounts Payable</span><h2>Supplier Payment Processing</h2><p>Prepare, authorise and monitor supplier disbursements.</p></div></div>
    <div class="fo-alert fo-alert--warning"><i class="fas fa-database"></i><div>Disbursement database tables are not installed. Import <strong>finance_supplier_disbursements_migration.sql</strong>, then reload this page.</div></div>
    <?php require __DIR__ . '/_layout_end.php'; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fo_verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create_batch') {
            $label = trim((string)($_POST['batch_label'] ?? ''));
            $paymentDate = (string)($_POST['planned_payment_date'] ?? '');
            if ($label === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
                throw new RuntimeException('Enter a batch description and valid planned payment date.');
            }
            $batchRef = 'DISB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $st = $conn->prepare("INSERT INTO finance_supplier_disbursements(batch_ref,batch_label,planned_payment_date,status,created_by) VALUES(?,?,?,'draft',?)");
            if (!$st) throw new RuntimeException('Unable to prepare payment batch.');
            $st->bind_param('sssi', $batchRef, $label, $paymentDate, $uid);
            if (!$st->execute()) throw new RuntimeException('Unable to create payment batch.');
            $batchId = (int)$st->insert_id; $st->close();
            sd_history($conn, $batchId, null, $uid, 'batch_created', 'Payment batch created.');
            fo_activity($conn, $uid, 'supplier_disbursement_batch_created', 'Created supplier payment batch ' . $batchRef, 'supplier_disbursement', $batchId);
            fo_flash('success', 'Payment batch created.');
            sd_redirect();
        }

        $batchId = max(0, (int)($_POST['batch_id'] ?? 0));
        $itemId = max(0, (int)($_POST['item_id'] ?? 0));
        $note = trim((string)($_POST['action_note'] ?? ''));
        if ($batchId < 1) throw new RuntimeException('Invalid payment batch.');

        $conn->begin_transaction();
        $st = $conn->prepare('SELECT * FROM finance_supplier_disbursements WHERE id=? FOR UPDATE');
        $st->bind_param('i', $batchId); $st->execute();
        $batch = $st->get_result()->fetch_assoc(); $st->close();
        if (!$batch) throw new RuntimeException('Payment batch not found.');

        if ($action === 'add_invoice') {
            if (!in_array($batch['status'], ['draft'], true)) throw new RuntimeException('Invoices can only be added to a draft batch.');
            $invoiceId = max(0, (int)($_POST['invoice_id'] ?? 0));
            $amount = round((float)($_POST['amount'] ?? 0), 2);
            if ($invoiceId < 1 || $amount <= 0) throw new RuntimeException('Select an invoice and enter a valid payment amount.');

            $st = $conn->prepare("SELECT * FROM finance_supplier_invoices WHERE id=? FOR UPDATE");
            $st->bind_param('i', $invoiceId); $st->execute();
            $invoice = $st->get_result()->fetch_assoc(); $st->close();
            if (!$invoice || !in_array($invoice['status'], ['approved','partially_paid'], true)) {
                throw new RuntimeException('Only approved or partially paid supplier invoices can be scheduled.');
            }

            $st = $conn->prepare("SELECT COALESCE(SUM(i.amount),0) reserved FROM finance_supplier_disbursement_items i JOIN finance_supplier_disbursements b ON b.id=i.batch_id WHERE i.invoice_id=? AND i.status IN ('queued','processing') AND b.status IN ('draft','submitted','authorised','processing')");
            $st->bind_param('i', $invoiceId); $st->execute();
            $reserved = (float)($st->get_result()->fetch_assoc()['reserved'] ?? 0); $st->close();

            $outstanding = round((float)$invoice['total_amount'] - (float)$invoice['paid_amount'] - $reserved, 2);
            if ($amount > $outstanding + 0.001) throw new RuntimeException('Payment exceeds the unreserved invoice balance of ' . sd_money(max(0, $outstanding)) . '.');
            $st = $conn->prepare('SELECT id FROM finance_supplier_disbursement_items WHERE batch_id=? AND invoice_id=? LIMIT 1');
            $st->bind_param('ii', $batchId, $invoiceId); $st->execute();
            $duplicate = $st->get_result()->fetch_assoc(); $st->close();
            if ($duplicate) throw new RuntimeException('This invoice is already included in this batch.');

            $paymentRef = 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $st = $conn->prepare("INSERT INTO finance_supplier_disbursement_items(batch_id,invoice_id,supplier_name,supplier_invoice_no,amount,payment_reference,status,created_by) VALUES(?,?,?,?,?,?,'queued',?)");
            $st->bind_param('iissdsi', $batchId, $invoiceId, $invoice['supplier_name'], $invoice['supplier_invoice_no'], $amount, $paymentRef, $uid);
            if (!$st->execute()) throw new RuntimeException('Unable to add invoice to payment batch.');
            $newItemId = (int)$st->insert_id; $st->close();
            sd_history($conn, $batchId, $newItemId, $uid, 'invoice_added', 'Invoice added to payment batch.', $amount);
            fo_activity($conn, $uid, 'supplier_disbursement_invoice_added', 'Added invoice ' . $invoice['supplier_invoice_no'] . ' to batch ' . $batch['batch_ref'], 'supplier_disbursement', $batchId);
            fo_flash('success', 'Invoice added to payment batch.');
        } elseif ($action === 'remove_item') {
            if ($batch['status'] !== 'draft') throw new RuntimeException('Only draft batches can be edited.');
            $st = $conn->prepare("UPDATE finance_supplier_disbursement_items SET status='removed' WHERE id=? AND batch_id=? AND status='queued'");
            $st->bind_param('ii', $itemId, $batchId); $st->execute(); $changed = $st->affected_rows; $st->close();
            if (!$changed) throw new RuntimeException('Payment line could not be removed.');
            sd_history($conn, $batchId, $itemId, $uid, 'line_removed', $note ?: 'Removed from draft batch.');
            fo_flash('success', 'Payment line removed.');
        } elseif ($action === 'submit_batch') {
            if ($batch['status'] !== 'draft') throw new RuntimeException('Only draft batches can be submitted.');
            $st = $conn->prepare("SELECT COUNT(*) c, COALESCE(SUM(amount),0) total FROM finance_supplier_disbursement_items WHERE batch_id=? AND status='queued'");
            $st->bind_param('i', $batchId); $st->execute(); $totals = $st->get_result()->fetch_assoc(); $st->close();
            if ((int)$totals['c'] < 1) throw new RuntimeException('Add at least one supplier invoice before submitting.');
            $st = $conn->prepare("UPDATE finance_supplier_disbursements SET status='submitted',submitted_by=?,submitted_at=NOW(),total_amount=? WHERE id=?");
            $total = (float)$totals['total']; $st->bind_param('idi', $uid, $total, $batchId); $st->execute(); $st->close();
            sd_history($conn, $batchId, null, $uid, 'batch_submitted', 'Batch submitted for authorisation.', $total);
            fo_flash('success', 'Payment batch submitted for authorisation.');
        } elseif ($action === 'authorise_batch') {
            if ($batch['status'] !== 'submitted') throw new RuntimeException('Only submitted batches can be authorised.');
            if ((int)$batch['created_by'] === $uid || (int)$batch['submitted_by'] === $uid) throw new RuntimeException('The preparer cannot authorise their own payment batch.');
            $st = $conn->prepare("UPDATE finance_supplier_disbursements SET status='authorised',authorised_by=?,authorised_at=NOW() WHERE id=?");
            $st->bind_param('ii', $uid, $batchId); $st->execute(); $st->close();
            sd_history($conn, $batchId, null, $uid, 'batch_authorised', $note ?: 'Payment batch authorised.', (float)$batch['total_amount']);
            fo_activity($conn, $uid, 'supplier_disbursement_authorised', 'Authorised payment batch ' . $batch['batch_ref'], 'supplier_disbursement', $batchId);
            fo_flash('success', 'Payment batch authorised. It is ready for disbursement recording.');
        } elseif ($action === 'record_payment') {
            if (!in_array($batch['status'], ['authorised','processing'], true)) throw new RuntimeException('Only authorised batches can have payment outcomes recorded.');
            $bankRef = trim((string)($_POST['bank_reference'] ?? ''));
            $paidDate = (string)($_POST['paid_date'] ?? '');
            $outcome = (string)($_POST['outcome'] ?? 'completed');
            if ($bankRef === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidDate) || !in_array($outcome, ['completed','failed'], true)) throw new RuntimeException('Enter a bank/payment reference, payment date and valid outcome.');
            $st = $conn->prepare('SELECT i.*, inv.total_amount invoice_total, inv.paid_amount invoice_paid, inv.status invoice_status FROM finance_supplier_disbursement_items i JOIN finance_supplier_invoices inv ON inv.id=i.invoice_id WHERE i.id=? AND i.batch_id=? FOR UPDATE');
            $st->bind_param('ii', $itemId, $batchId); $st->execute(); $line = $st->get_result()->fetch_assoc(); $st->close();
            if (!$line || $line['status'] !== 'queued') throw new RuntimeException('This payment line is no longer awaiting disbursement.');
            if ($outcome === 'completed') {
                $st = $conn->prepare('SELECT id FROM finance_supplier_invoice_payments WHERE payment_reference=? LIMIT 1');
                $st->bind_param('s', $bankRef); $st->execute(); $existing = $st->get_result()->fetch_assoc(); $st->close();
                if ($existing) throw new RuntimeException('This bank/payment reference has already been recorded. Duplicate payment blocked.');
                $remaining = round((float)$line['invoice_total'] - (float)$line['invoice_paid'], 2);
                if ((float)$line['amount'] > $remaining + 0.001) throw new RuntimeException('Invoice balance changed after authorisation. Review the invoice before recording this payment.');
                $st = $conn->prepare('INSERT INTO finance_supplier_invoice_payments(invoice_id,payment_reference,payment_date,amount,recorded_by,notes) VALUES(?,?,?,?,?,?)');
                $notes = 'Disbursement batch ' . $batch['batch_ref'] . ($note !== '' ? ' — ' . $note : '');
                $st->bind_param('issdis', $line['invoice_id'], $bankRef, $paidDate, $line['amount'], $uid, $notes);
                if (!$st->execute()) throw new RuntimeException('Payment record could not be saved; reference may already exist.');
                $st->close();
                $newPaid = round((float)$line['invoice_paid'] + (float)$line['amount'], 2);
                $newStatus = $newPaid + 0.001 >= (float)$line['invoice_total'] ? 'paid' : 'partially_paid';
                $st = $conn->prepare('UPDATE finance_supplier_invoices SET paid_amount=?,status=? WHERE id=?');
                $st->bind_param('dsi', $newPaid, $newStatus, $line['invoice_id']); $st->execute(); $st->close();
            }
            $st = $conn->prepare('UPDATE finance_supplier_disbursement_items SET status=?,bank_reference=?,paid_date=?,recorded_by=?,recorded_at=NOW(),outcome_note=? WHERE id=?');
            $st->bind_param('sssisi', $outcome, $bankRef, $paidDate, $uid, $note, $itemId); $st->execute(); $st->close();
            sd_history($conn, $batchId, $itemId, $uid, 'payment_' . $outcome, $note ?: 'Payment outcome recorded.', (float)$line['amount']);
            fo_activity($conn, $uid, 'supplier_disbursement_' . $outcome, 'Recorded ' . $outcome . ' supplier payment ' . $bankRef, 'supplier_disbursement', $batchId);
            $st = $conn->prepare("SELECT SUM(status='queued') queued, SUM(status='completed') completed, SUM(status='failed') failed FROM finance_supplier_disbursement_items WHERE batch_id=?");
            $st->bind_param('i', $batchId); $st->execute(); $counts = $st->get_result()->fetch_assoc(); $st->close();
            $newBatchStatus = ((int)$counts['queued'] > 0) ? 'processing' : (((int)$counts['completed'] > 0) ? (((int)$counts['failed'] > 0) ? 'partially_completed' : 'completed') : 'failed');
            $st = $conn->prepare('UPDATE finance_supplier_disbursements SET status=? WHERE id=?');
            $st->bind_param('si', $newBatchStatus, $batchId); $st->execute(); $st->close();
            fo_flash('success', 'Payment outcome recorded.');
        } elseif ($action === 'cancel_batch') {
            if (!in_array($batch['status'], ['draft','submitted'], true)) throw new RuntimeException('Only draft or submitted batches can be cancelled.');
            if ($note === '') throw new RuntimeException('A cancellation reason is required.');
            $st = $conn->prepare("UPDATE finance_supplier_disbursements SET status='cancelled',cancelled_by=?,cancelled_at=NOW(),cancellation_reason=? WHERE id=?");
            $st->bind_param('isi', $uid, $note, $batchId); $st->execute(); $st->close();
            $st = $conn->prepare("UPDATE finance_supplier_disbursement_items SET status='cancelled',outcome_note=? WHERE batch_id=? AND status='queued'");
            $st->bind_param('si', $note, $batchId); $st->execute(); $st->close();
            sd_history($conn, $batchId, null, $uid, 'batch_cancelled', $note);
            fo_flash('success', 'Payment batch cancelled. Reserved invoice balances have been released.');
        } else {
            throw new RuntimeException('Unsupported payment action.');
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        fo_flash('error', $e->getMessage());
    }
    sd_redirect();
}

$selectedBatchId = max(0, (int)($_GET['batch'] ?? 0));
$selectedBatch = null; $items = [];
if ($selectedBatchId) {
    $st = $conn->prepare('SELECT b.*, CONCAT(COALESCE(c.first_name,"")," ",COALESCE(c.last_name,"")) creator_name, CONCAT(COALESCE(a.first_name,"")," ",COALESCE(a.last_name,"")) authoriser_name FROM finance_supplier_disbursements b LEFT JOIN users c ON c.id=b.created_by LEFT JOIN users a ON a.id=b.authorised_by WHERE b.id=?');
    $st->bind_param('i', $selectedBatchId); $st->execute(); $selectedBatch = $st->get_result()->fetch_assoc(); $st->close();
    if ($selectedBatch) {
        $st = $conn->prepare('SELECT i.*,inv.invoice_ref,inv.due_date,inv.total_amount,inv.paid_amount,inv.status invoice_status FROM finance_supplier_disbursement_items i JOIN finance_supplier_invoices inv ON inv.id=i.invoice_id WHERE i.batch_id=? ORDER BY i.id');
        $st->bind_param('i', $selectedBatchId); $st->execute(); $items = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
    }
}
$batchRows = $conn->query("SELECT b.*,CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) creator_name,(SELECT COUNT(*) FROM finance_supplier_disbursement_items i WHERE i.batch_id=b.id AND i.status<>'removed') line_count FROM finance_supplier_disbursements b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$invoiceRows = $conn->query("SELECT id,invoice_ref,supplier_name,supplier_invoice_no,due_date,total_amount,paid_amount,status FROM finance_supplier_invoices WHERE status IN ('approved','partially_paid') ORDER BY due_date,id")->fetch_all(MYSQLI_ASSOC);
$stats = $conn->query("SELECT COUNT(*) batches,COALESCE(SUM(CASE WHEN status IN ('submitted','authorised','processing') THEN total_amount ELSE 0 END),0) in_pipeline,COALESCE(SUM(CASE WHEN status='completed' THEN total_amount ELSE 0 END),0) completed_total, SUM(status='submitted') awaiting_authorisation FROM finance_supplier_disbursements")->fetch_assoc() ?: [];

require __DIR__ . '/_layout_start.php';
?>
<style>
.sd-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}
.sd-card,.sd-panel{background:var(--surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;padding:16px;min-width:0}
.sd-card span{display:block;color:var(--muted,#64748b);font-size:12px}.sd-card strong{display:block;font-size:22px;margin-top:7px}
.sd-panel{margin:16px 0}.sd-panel h3{margin:0 0 14px;font-size:17px}.sd-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}
.sd-form label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.sd-form input,.sd-form select,.sd-form textarea{width:100%;padding:10px;border:1px solid var(--border,#d1d5db);border-radius:9px;background:var(--surface,#fff);color:var(--text,#111827)}
.sd-btn{border:0;border-radius:9px;padding:10px 13px;font-weight:700;cursor:pointer;background:#155eef;color:white}.sd-btn.secondary{background:#64748b}.sd-btn.danger{background:#b42318}.sd-actions{display:flex;gap:7px;flex-wrap:wrap}.sd-table-wrap{overflow:auto}table.sd-table{width:100%;border-collapse:collapse;min-width:760px} .sd-table th,.sd-table td{padding:10px;border-bottom:1px solid var(--border,#e5e7eb);text-align:left;font-size:13px;vertical-align:middle}.sd-table th{font-size:11px;text-transform:uppercase;color:var(--muted,#64748b)}.sd-muted{color:var(--muted,#64748b);font-size:12px}.sd-badge{display:inline-block;border-radius:20px;padding:4px 8px;font-size:11px;font-weight:700;background:#eef2ff;color:#3730a3}.sd-badge.success{background:#dcfce7;color:#166534}.sd-badge.warning{background:#fef3c7;color:#92400e}.sd-badge.danger{background:#fee2e2;color:#991b1b}.sd-badge.info{background:#dbeafe;color:#1e40af}
@media(max-width:1000px){.sd-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sd-form{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.sd-grid,.sd-form{grid-template-columns:1fr}}
</style>
<div class="fo-page-header"><div><span>Finance / Accounts Payable</span><h2>Supplier Payment Processing &amp; Disbursements</h2><p>Prepare controlled payment batches, enforce independent authorisation and record confirmed supplier payments.</p></div></div>

<div class="sd-grid">
 <div class="sd-card"><span>Payment batches</span><strong><?= (int)($stats['batches']??0) ?></strong></div>
 <div class="sd-card"><span>Awaiting authorisation</span><strong><?= (int)($stats['awaiting_authorisation']??0) ?></strong></div>
 <div class="sd-card"><span>Authorised / in process</span><strong><?= sd_money($stats['in_pipeline']??0) ?></strong></div>
 <div class="sd-card"><span>Completed batches</span><strong><?= sd_money($stats['completed_total']??0) ?></strong></div>
</div>

<div class="sd-panel">
 <h3>Create a payment batch</h3>
 <form method="post" class="sd-form">
  <input type="hidden" name="csrf_token" value="<?= sd_e(fo_csrf_token()) ?>"><input type="hidden" name="action" value="create_batch">
  <div><label>Batch description</label><input name="batch_label" required maxlength="180" placeholder="e.g. November supplier payments"></div>
  <div><label>Planned payment date</label><input type="date" name="planned_payment_date" required value="<?= date('Y-m-d') ?>"></div>
  <div><button class="sd-btn" type="submit">Create draft batch</button></div>
 </form>
</div>

<?php if ($selectedBatch): ?>
<div class="sd-panel">
 <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
  <div><h3><?= sd_e($selectedBatch['batch_ref']) ?> — <?= sd_e($selectedBatch['batch_label']) ?></h3><div class="sd-muted">Prepared by <?= sd_e(trim($selectedBatch['creator_name'])) ?> · Planned date <?= fo_date($selectedBatch['planned_payment_date']) ?> · Total <?= sd_money($selectedBatch['total_amount']) ?></div></div>
  <span class="sd-badge <?= sd_status_class($selectedBatch['status']) ?>"><?= sd_e(fo_label($selectedBatch['status'])) ?></span>
 </div>
 <?php if ($selectedBatch['status']==='draft'): ?>
 <form method="post" class="sd-form" style="margin:18px 0">
  <input type="hidden" name="csrf_token" value="<?= sd_e(fo_csrf_token()) ?>"><input type="hidden" name="action" value="add_invoice"><input type="hidden" name="batch_id" value="<?= (int)$selectedBatchId ?>">
  <div><label>Approved supplier invoice</label><select name="invoice_id" required><option value="">Select invoice</option><?php foreach($invoiceRows as $inv): ?><option value="<?= (int)$inv['id'] ?>"><?= sd_e($inv['invoice_ref'].' · '.$inv['supplier_name'].' · '.$inv['supplier_invoice_no'].' · Balance '.sd_money((float)$inv['total_amount']-(float)$inv['paid_amount'])) ?></option><?php endforeach; ?></select></div>
  <div><label>Payment amount (ZAR)</label><input type="number" name="amount" min="0.01" step="0.01" required></div>
  <div><button class="sd-btn" type="submit">Add invoice</button></div>
 </form>
 <?php endif; ?>
 <div class="sd-table-wrap"><table class="sd-table"><thead><tr><th>Supplier / invoice</th><th>Due date</th><th>Payment amount</th><th>Payment reference</th><th>Status</th><th>Action</th></tr></thead><tbody>
 <?php foreach($items as $line): ?><tr><td><strong><?= sd_e($line['supplier_name']) ?></strong><div class="sd-muted"><?= sd_e($line['invoice_ref']) ?> · <?= sd_e($line['supplier_invoice_no']) ?></div></td><td><?= fo_date($line['due_date']) ?></td><td><?= sd_money($line['amount']) ?></td><td><?= sd_e($line['payment_reference']) ?><?php if($line['bank_reference']): ?><div class="sd-muted">Bank: <?= sd_e($line['bank_reference']) ?></div><?php endif; ?></td><td><span class="sd-badge <?= sd_status_class($line['status']) ?>"><?= sd_e(fo_label($line['status'])) ?></span></td><td>
 <?php if($selectedBatch['status']==='draft' && $line['status']==='queued'): ?><form method="post" onsubmit="return confirm('Remove this invoice from the draft batch?')"><input type="hidden" name="csrf_token" value="<?= sd_e(fo_csrf_token()) ?>"><input type="hidden" name="action" value="remove_item"><input type="hidden" name="batch_id" value="<?= (int)$selectedBatchId ?>"><input type="hidden" name="item_id" value="<?= (int)$line['id'] ?>"><button class="sd-btn danger" type="submit">Remove</button></form>
 <?php elseif(in_array($selectedBatch['status'],['authorised','processing'],true) && $line['status']==='queued'): ?>
 <form method="post" class="sd-actions"><input type="hidden" name="csrf_token" value="<?= sd_e(fo_csrf_token()) ?>"><input type="hidden" name="action" value="record_payment"><input type="hidden" name="batch_id" value="<?= (int)$selectedBatchId ?>"><input type="hidden" name="item_id" value="<?= (int)$line['id'] ?>">
 <input name="bank_reference" required placeholder="Bank reference" style="max-width:145px;padding:8px;border:1px solid var(--border);border-radius:8px"><input type="date" name="paid_date" required value="<?= date('Y-m-d') ?>" style="max-width:145px;padding:8px;border:1px solid var(--border);border-radius:8px"><select name="outcome" style="max-width:125px;padding:8px;border:1px solid var(--border);border-radius:8px"><option value="completed">Completed</option><option value="failed">Failed</option></select><input name="action_note" placeholder="Note (optional)" style="max-width:140px;padding:8px;border:1px solid var(--border);border-radius:8px"><button class="sd-btn" type="submit">Record</button></form>
 <?php else: ?><span class="sd-muted"><?= $line['status']==='completed'?'Recorded':'—' ?></span><?php endif; ?>
 </td></tr><?php endforeach; ?>
 <?php if(!$items): ?><tr><td colspan="6">No invoices have been added to this batch.</td></tr><?php endif; ?>
 </tbody></table></div>
 <div class="sd-actions" style="margin-top:16px">
 <?php if($selectedBatch['status']==='draft'): ?>
 <form method="post"><input type="hidden" name="csrf_token" value="<?= sd_e(fo_csrf_token()) ?>"><input type="hidden" name="action" value="submit_batch"><input type="hidden" name="batch_id" value="<?= (int)$selectedBatchId ?>"><button class="sd-btn" type="submit">Submit for authorisation</button></form>
 <?php elseif($selectedBatch['status']==='submitted' && (int)$selectedBatch['created_by']!==$uid && (int)$selectedBatch['submitted_by']!==$uid): ?>
 <form method="post" class="sd-actions"><input type="hidden" name="csrf_token" value="<?= sd_e(fo_csrf_token()) ?>"><input type="hidden" name="action" value="authorise_batch"><input type="hidden" name="batch_id" value="<?= (int)$selectedBatchId ?>"><input name="action_note" placeholder="Authorisation note" style="padding:9px;border:1px solid var(--border);border-radius:8px"><button class="sd-btn" type="submit">Authorise batch</button></form>
 <?php endif; ?>
 <?php if(in_array($selectedBatch['status'],['draft','submitted'],true)): ?>
 <form method="post" class="sd-actions" onsubmit="return confirm('Cancel this batch and release its reserved amounts?')"><input type="hidden" name="csrf_token" value="<?= sd_e(fo_csrf_token()) ?>"><input type="hidden" name="action" value="cancel_batch"><input type="hidden" name="batch_id" value="<?= (int)$selectedBatchId ?>"><input name="action_note" required placeholder="Cancellation reason" style="padding:9px;border:1px solid var(--border);border-radius:8px"><button class="sd-btn danger" type="submit">Cancel batch</button></form>
 <?php endif; ?>
 </div>
</div>
<?php endif; ?>

<div class="sd-panel">
 <h3>Payment batches</h3>
 <div class="sd-table-wrap"><table class="sd-table"><thead><tr><th>Batch</th><th>Created by</th><th>Planned date</th><th>Lines</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
 <?php foreach($batchRows as $b): ?><tr><td><strong><?= sd_e($b['batch_ref']) ?></strong><div class="sd-muted"><?= sd_e($b['batch_label']) ?></div></td><td><?= sd_e(trim($b['creator_name'])) ?></td><td><?= fo_date($b['planned_payment_date']) ?></td><td><?= (int)$b['line_count'] ?></td><td><?= sd_money($b['total_amount']) ?></td><td><span class="sd-badge <?= sd_status_class($b['status']) ?>"><?= sd_e(fo_label($b['status'])) ?></span></td><td><a href="<?= sd_e(url('finance/supplier_disbursements.php?batch='.(int)$b['id'])) ?>">Open batch</a></td></tr><?php endforeach; ?>
 <?php if(!$batchRows): ?><tr><td colspan="7">No supplier payment batches have been created.</td></tr><?php endif; ?>
 </tbody></table></div>
</div>
<div class="sd-muted">Payment recording updates the linked supplier invoice and its accounts-payable payment history. This module records disbursement outcomes; it does not connect to a bank or initiate an EFT.</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
