
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
require_once __DIR__ . '/_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * bootstrap.php loads the application's Database class. Use its shared
 * connection manager, matching the other Finance portal pages.
 */
try {
    $db = Database::getConnection();
} catch (Throwable $databaseError) {
    error_log('[Finance schedule_view] Database connection failed: ' . $databaseError->getMessage());
    http_response_code(500);
    exit('Unable to connect to the database. Check the database settings in config/config.php.');
}

if (!$db instanceof mysqli) {
    error_log('[Finance schedule_view] Database::getConnection() did not return a mysqli connection.');
    http_response_code(500);
    exit('The database connection could not be initialized.');
}

$scheduleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$scheduleId || $scheduleId < 1) {
    http_response_code(400);
    exit('Invalid stipend schedule ID.');
}

$stmt = $db->prepare(
    "SELECT ss.*, u.first_name, u.last_name
     FROM stipend_schedules ss
     LEFT JOIN users u ON u.id = ss.created_by
     WHERE ss.id = ?
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    exit('Unable to prepare the schedule query.');
}

$stmt->bind_param('i', $scheduleId);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_assoc();
$stmt->close();

if (!$schedule) {
    http_response_code(404);
    exit('Stipend schedule not found.');
}

$history = [];
$historyAvailable = fo_has_table(
    $db,
    'stipend_schedule_approval_history'
);

if ($historyAvailable) {
    $historyStmt = $db->prepare(
        "SELECT h.*, u.first_name, u.last_name
         FROM stipend_schedule_approval_history h
         LEFT JOIN users u ON u.id = h.acted_by
         WHERE h.schedule_id = ?
         ORDER BY h.id DESC"
    );

    if ($historyStmt) {
        $historyStmt->bind_param('i', $scheduleId);
        $historyStmt->execute();
        $history = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $historyStmt->close();
    }
}

if (!function_exists('fo_view_escape')) {
    function fo_view_escape($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

$status = (string)($schedule['status'] ?? 'unknown');
$creator = trim(
    (string)($schedule['first_name'] ?? '') . ' ' .
    (string)($schedule['last_name'] ?? '')
);

$pageTitle = 'Stipend Schedule Details';

/*
 * Use the existing portal layout if your project provides these files.
 */
$layoutStart = __DIR__ . '/_layout_start.php';
$layoutEnd = __DIR__ . '/_layout_end.php';

if (is_file($layoutStart)) {
    require $layoutStart;
} else {
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= fo_view_escape($pageTitle) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
              rel="stylesheet">
    </head>
    <body class="bg-light">
    <main class="container py-4">
    <?php
}
?>

<style>
/* Schedule details page: scoped styles avoid changing other Finance screens. */
.fo-schedule-page{--sv-text:#172033;--sv-muted:#64748b;--sv-card:#fff;--sv-border:#e2e8f0;--sv-soft:#f8fafc;color:var(--sv-text);width:100%;max-width:1500px;margin-inline:auto;padding:clamp(16px,2.2vw,32px)!important;font-family:Inter,system-ui,sans-serif;font-size:15px;line-height:1.55}
.fo-schedule-page .sv-heading{font-size:clamp(1.55rem,2.2vw,2rem);font-weight:800;letter-spacing:-.035em;line-height:1.2;color:var(--sv-text)}
.fo-schedule-page .sv-subtitle{font-size:1rem;color:var(--sv-muted)!important;margin-top:7px}
.fo-schedule-page .card{background:var(--sv-card)!important;border:1px solid var(--sv-border)!important;border-radius:16px!important;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.055)!important;margin-bottom:22px}
.fo-schedule-page .card-header{background:var(--sv-soft)!important;border-bottom:1px solid var(--sv-border)!important;padding:17px 22px!important}
.fo-schedule-page .card-header h5{font-size:1.08rem;font-weight:750;color:var(--sv-text);letter-spacing:-.01em}
.fo-schedule-page .card-body{padding:clamp(18px,2.2vw,28px)!important}
.fo-schedule-page .sv-field{height:100%;padding:14px 16px;background:var(--sv-soft);border:1px solid var(--sv-border);border-radius:12px;min-width:0}
.fo-schedule-page .sv-label{display:block;font-size:.78rem;font-weight:750;letter-spacing:.055em;text-transform:uppercase;color:var(--sv-muted);margin-bottom:7px}
.fo-schedule-page .sv-field > div{font-size:1rem;font-weight:650;color:var(--sv-text);overflow-wrap:anywhere}
.fo-schedule-page .sv-value{font-size:1rem;font-weight:650;color:var(--sv-text);overflow-wrap:anywhere}
.fo-schedule-page .sv-value--amount{font-size:1.25rem;font-weight:800}
.fo-schedule-page .table-responsive{border:1px solid var(--sv-border);border-radius:12px}
.fo-schedule-page .table{margin:0;color:var(--sv-text);font-size:.94rem;min-width:680px}
.fo-schedule-page .table thead th{background:var(--sv-soft);color:var(--sv-muted);font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.045em;padding:14px 16px;border-bottom:1px solid var(--sv-border);white-space:nowrap}
.fo-schedule-page .table tbody td{padding:15px 16px;vertical-align:middle;border-color:var(--sv-border);line-height:1.5}
.fo-schedule-page .table tbody tr:last-child td{border-bottom:0}
.fo-schedule-page .btn{font-weight:650;border-radius:10px;padding:10px 15px}
.fo-schedule-page .text-muted{color:var(--sv-muted)!important}
html[data-theme="dark"] .fo-schedule-page,body.dark-mode .fo-schedule-page,body.dark .fo-schedule-page{--sv-text:#e5edf8;--sv-muted:#a8b6c9;--sv-card:#172235;--sv-border:#334155;--sv-soft:#1e2c42;color:var(--sv-text)}
html[data-theme="dark"] .fo-schedule-page .card,body.dark-mode .fo-schedule-page .card,body.dark .fo-schedule-page .card{box-shadow:0 8px 24px rgba(0,0,0,.18)!important}
html[data-theme="dark"] .fo-schedule-page .btn-outline-secondary,body.dark-mode .fo-schedule-page .btn-outline-secondary,body.dark .fo-schedule-page .btn-outline-secondary{color:#dbeafe;border-color:#64748b}
@media(max-width:640px){.fo-schedule-page{padding:14px!important;font-size:14px}.fo-schedule-page .card-header{padding:15px 17px!important}.fo-schedule-page .card-body{padding:16px!important}.fo-schedule-page .sv-field{padding:12px}.fo-schedule-page .sv-heading{font-size:1.45rem}.fo-schedule-page .sv-subtitle{font-size:.9rem}.fo-schedule-page .table{font-size:.88rem}}
</style>
<div class="container-fluid fo-schedule-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1 sv-heading">Stipend Schedule Details</h2>
            <p class="text-muted mb-0 sv-subtitle">
                Review schedule information and approval history.
            </p>
        </div>

        <a href="<?= fo_view_escape(
            function_exists('url')
                ? url('finance/approvals.php')
                : 'approvals.php'
        ) ?>" class="btn btn-outline-secondary">
            &larr; Back to Approvals
        </a>
    </div>

    <?= fo_local_flash() ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Schedule Information</h5>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Schedule Reference</small>
                    <div class="sv-value">
                        <?= fo_view_escape(
                            $schedule['schedule_reference'] ?? 'Not assigned'
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Status</small>
                    <div>
                        <span class="badge text-bg-<?= fo_view_escape(
                            fo_status_class($status)
                        ) ?>">
                            <?= fo_view_escape(fo_label($status)) ?>
                        </span>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Programme</small>
                    <div class="sv-value">
                        <?= fo_view_escape(
                            $schedule['programme_name'] ?? 'Not specified'
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Created By</small>
                    <div class="sv-value">
                        <?= fo_view_escape($creator ?: 'Unknown') ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Period Start</small>
                    <div>
                        <?= fo_view_escape(
                            fo_date($schedule['period_start'] ?? null)
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Period End</small>
                    <div>
                        <?= fo_view_escape(
                            fo_date($schedule['period_end'] ?? null)
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Participants</small>
                    <div class="sv-value">
                        <?= number_format(
                            (int)($schedule['participant_count'] ?? 0)
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Total Amount</small>
                    <div class="sv-value sv-value--amount">
                        <?= fo_view_escape(
                            fo_money($schedule['total_amount'] ?? 0)
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Exceptions</small>
                    <div>
                        <?= number_format(
                            (int)($schedule['exception_count'] ?? 0)
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Submitted At</small>
                    <div>
                        <?= fo_view_escape(
                            fo_datetime($schedule['submitted_at'] ?? null)
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Approved At</small>
                    <div>
                        <?= fo_view_escape(
                            fo_datetime($schedule['approved_at'] ?? null)
                        ) ?>
                    </div>
                </div></div>

                <div class="col-md-6"><div class="sv-field">
                    <small class="sv-label">Approved By</small>
                    <div>
                        <?= !empty($schedule['approved_by'])
                            ? 'User #' . (int)$schedule['approved_by']
                            : 'Not approved' ?>
                    </div>
                </div></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Approval History</h5>
        </div>

        <div class="card-body">
            <?php if (!$historyAvailable): ?>
                <p class="text-muted mb-0">
                    Approval history is unavailable because the history
                    table does not exist in this database.
                </p>
            <?php elseif (!$history): ?>
                <p class="text-muted mb-0">
                    No approval history has been recorded for this schedule.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Action</th>
                            <th>Performed By</th>
                            <th>Note</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td>
                                    <?= fo_view_escape(
                                        fo_label($item['action'] ?? '')
                                    ) ?>
                                </td>
                                <td>
                                    <?= fo_view_escape(
                                        trim(
                                            (string)($item['first_name'] ?? '') .
                                            ' ' .
                                            (string)($item['last_name'] ?? '')
                                        ) ?: 'Unknown'
                                    ) ?>
                                </td>
                                <td>
                                    <?= fo_view_escape(
                                        $item['action_note'] ?? '—'
                                    ) ?>
                                </td>
                                <td>
                                    <?= fo_view_escape(
                                        fo_datetime(
                                            $item['created_at']
                                                ?? $item['acted_at']
                                                ?? null
                                        )
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if (is_file($layoutEnd)) {
    require $layoutEnd;
} else {
    ?>
    </main>
    </body>
    </html>
    <?php
}
?>
