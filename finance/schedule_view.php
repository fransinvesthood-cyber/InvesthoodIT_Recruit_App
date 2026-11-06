
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
require_once __DIR__ . '/_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * Resolve the database connection provided by bootstrap.php.
 * Adjust this section only if your project uses a different connection variable.
 */
$db = $conn ?? $mysqli ?? $con ?? null;

if (!$db instanceof mysqli) {
    http_response_code(500);
    exit('Database connection is unavailable. Check includes/bootstrap.php.');
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

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">Stipend Schedule Details</h2>
            <p class="text-muted mb-0">
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
            <div class="row g-4">
                <div class="col-md-6">
                    <small class="text-muted">Schedule Reference</small>
                    <div class="fw-semibold">
                        <?= fo_view_escape(
                            $schedule['schedule_reference'] ?? 'Not assigned'
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Status</small>
                    <div>
                        <span class="badge text-bg-<?= fo_view_escape(
                            fo_status_class($status)
                        ) ?>">
                            <?= fo_view_escape(fo_label($status)) ?>
                        </span>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Programme</small>
                    <div class="fw-semibold">
                        <?= fo_view_escape(
                            $schedule['programme_name'] ?? 'Not specified'
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Created By</small>
                    <div class="fw-semibold">
                        <?= fo_view_escape($creator ?: 'Unknown') ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Period Start</small>
                    <div>
                        <?= fo_view_escape(
                            fo_date($schedule['period_start'] ?? null)
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Period End</small>
                    <div>
                        <?= fo_view_escape(
                            fo_date($schedule['period_end'] ?? null)
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Participants</small>
                    <div class="fw-semibold">
                        <?= number_format(
                            (int)($schedule['participant_count'] ?? 0)
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Total Amount</small>
                    <div class="fw-semibold fs-5">
                        <?= fo_view_escape(
                            fo_money($schedule['total_amount'] ?? 0)
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Exceptions</small>
                    <div>
                        <?= number_format(
                            (int)($schedule['exception_count'] ?? 0)
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Submitted At</small>
                    <div>
                        <?= fo_view_escape(
                            fo_datetime($schedule['submitted_at'] ?? null)
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Approved At</small>
                    <div>
                        <?= fo_view_escape(
                            fo_datetime($schedule['approved_at'] ?? null)
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Approved By</small>
                    <div>
                        <?= !empty($schedule['approved_by'])
                            ? 'User #' . (int)$schedule['approved_by']
                            : 'Not approved' ?>
                    </div>
                </div>
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
