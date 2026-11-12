
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('finance_officer');

$user = current_user();
$flashes = render_flashes();

$currentPage = 'approvals';
$pageTitle = 'Approval Queue';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$uid = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$cfg = fo_config($conn);

if ($uid <= 0) {
    http_response_code(403);
    exit('Invalid Finance Officer account.');
}

/*
|--------------------------------------------------------------------------
| Handle approval submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fo_verify_csrf();

    $id = (int) ($_POST['schedule_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($id <= 0 || $action !== 'approve') {
        fo_flash('danger', 'Invalid approval request.');
        header('Location: ' . url('finance/approvals.php'));
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT id, created_by, status
         FROM stipend_schedules
         WHERE id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        fo_flash('danger', 'Unable to prepare the approval request.');
        header('Location: ' . url('finance/approvals.php'));
        exit;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $schedule = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (
        !$schedule ||
        $schedule['status'] !== 'awaiting_approval'
    ) {
        fo_flash('danger', 'Schedule unavailable or already processed.');
        header('Location: ' . url('finance/approvals.php'));
        exit;
    }

    if (
        !fo_can_approve(
            $cfg,
            (int) $schedule['created_by'],
            $uid
        )
    ) {
        fo_flash(
            'danger',
            'Maker/checker segregation prevents self-approval.'
        );

        header('Location: ' . url('finance/approvals.php'));
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Approve schedule and record approval history
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {
        $update = $conn->prepare(
            "UPDATE stipend_schedules
             SET status = 'approved',
                 approved_by = ?,
                 approved_at = NOW()
             WHERE id = ?
               AND status = 'awaiting_approval'"
        );

        if (!$update) {
            throw new RuntimeException(
                'Unable to prepare schedule approval.'
            );
        }

        $update->bind_param('ii', $uid, $id);
        $update->execute();

        if ($update->affected_rows !== 1) {
            $update->close();

            throw new RuntimeException(
                'Schedule status changed or approval was not applied.'
            );
        }

        $update->close();

        $history = $conn->prepare(
            "INSERT INTO stipend_schedule_approval_history
                (schedule_id, action, acted_by, action_note)
             VALUES (?, 'approved', ?, 'Final approval granted')"
        );

        if (!$history) {
            throw new RuntimeException(
                'Unable to prepare approval history.'
            );
        }

        $history->bind_param('ii', $id, $uid);
        $history->execute();
        $history->close();

        $conn->commit();

        fo_activity(
            $conn,
            $uid,
            'stipend_schedule_approved',
            'Final-approved stipend schedule.',
            'stipend_schedule',
            $id
        );

        fo_flash('success', 'Schedule approved successfully.');

    } catch (Throwable $exception) {
        $conn->rollback();

        error_log(
            'Finance schedule approval failed: ' .
            $exception->getMessage()
        );

        fo_flash(
            'danger',
            'Approval failed. Please check the schedule and try again.'
        );
    }

    header('Location: ' . url('finance/approvals.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Load schedules awaiting approval
|--------------------------------------------------------------------------
*/

$rows = [];

$sql = "
    SELECT
        ss.id,
        ss.schedule_reference,
        ss.period_start,
        ss.period_end,
        ss.participant_count,
        ss.total_amount,
        ss.exception_count,
        ss.created_by,
        ss.submitted_at,
        u.first_name,
        u.last_name
    FROM stipend_schedules ss
    JOIN users u ON u.id = ss.created_by
    WHERE ss.status = 'awaiting_approval'
    ORDER BY ss.submitted_at ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($record = $result->fetch_assoc()) {
        $rows[] = $record;
    }
}

require __DIR__ . '/_layout_start.php';

?>

<div class="fo-page-header">
    <div>
        <span>Maker / Checker</span>
        <h2>Approval Queue</h2>
        <p>
            Review stipend schedules before granting final approval.
        </p>
    </div>
</div>

<div class="fo-alert fo-alert--info">
    <i class="fas fa-user-shield"></i>

    <div>
        <strong>
            Segregation
            <?= (int) ($cfg['maker_checker_enabled'] ?? 0) === 1
                ? 'Enabled'
                : 'Disabled' ?>
        </strong>

        <span>
            When enabled, makers cannot final-approve their own schedules.
        </span>
    </div>
</div>

<?php if (!empty($rows)): ?>

    <div class="fo-approval-grid">

        <?php foreach ($rows as $record): ?>

            <?php
            $scheduleId = (int) $record['id'];

            $canApprove = fo_can_approve(
                $cfg,
                (int) $record['created_by'],
                $uid
            );

            $reviewUrl = url(
                'finance/schedule_view.php?id=' . $scheduleId
            );

            $preparedBy = trim(
                ($record['first_name'] ?? '') . ' ' .
                ($record['last_name'] ?? '')
            );
            ?>

            <article>

                <div>
                    <strong>
                        <?= e($record['schedule_reference']) ?>
                    </strong>

                    <span class="fo-status <?= $canApprove
                        ? 'fo-status--success'
                        : 'fo-status--warning' ?>">

                        <?= $canApprove
                            ? 'Eligible to Approve'
                            : 'Maker Locked' ?>

                    </span>
                </div>

                <p>
                    <?= e(fo_date($record['period_start'])) ?>
                    -
                    <?= e(fo_date($record['period_end'])) ?>
                </p>

                <div class="fo-metrics">

                    <span>
                        <?= number_format(
                            (int) $record['participant_count']
                        ) ?>
                        participants
                    </span>

                    <span>
                        <?= e(fo_money($record['total_amount'])) ?>
                    </span>

                    <span>
                        <?= number_format(
                            (int) $record['exception_count']
                        ) ?>
                        exceptions
                    </span>

                </div>

                <small>
                    Prepared by
                    <?= e($preparedBy !== '' ? $preparedBy : 'Unknown user') ?>
                </small>

                <div class="fo-approval-actions">

                    <a
                        class="fo-btn fo-btn--secondary"
                        href="<?= e($reviewUrl) ?>"
                    >
                        <i class="fas fa-eye"></i>
                        Review
                    </a>

                    <?php if ($canApprove): ?>

                        <form method="post" style="display:inline">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(fo_csrf_token()) ?>"
                            >

                            <input
                                type="hidden"
                                name="schedule_id"
                                value="<?= $scheduleId ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="approve"
                            >

                            <button
                                type="submit"
                                class="fo-btn fo-btn--primary"
                            >
                                <i class="fas fa-check"></i>
                                Approve
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

<?php else: ?>

    <div class="fo-card">

        <div class="fo-empty">
            <i class="fas fa-user-check"></i>

            <strong>No schedules awaiting approval</strong>

            <span>
                There are currently no stipend schedules requiring
                final approval.
            </span>
        </div>

    </div>

<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
