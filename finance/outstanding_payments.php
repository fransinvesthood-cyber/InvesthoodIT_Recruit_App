
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('finance_officer');

$user = current_user();
$flashes = render_flashes();

$currentPage = 'outstanding_payments';
$pageTitle = 'Outstanding Payments Monitoring';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$uid = (int) ($user['id'] ?? $user['user_id'] ?? 0);

if ($uid <= 0) {
    http_response_code(403);
    exit('Invalid Finance Officer account.');
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$filter = (string) ($_GET['filter'] ?? 'all');

if (!in_array($filter, ['all', 'overdue'], true)) {
    $filter = 'all';
}

$rows = [];

$totals = [
    'count'   => 0,
    'amount'  => 0.0,
    'overdue' => 0,
];

$errorMessage = null;

/*
|--------------------------------------------------------------------------
| Load outstanding stipend schedules
|--------------------------------------------------------------------------
|
| Outstanding means the schedule is approved but has not yet been
| marked as processed in the application's workflow.
|
*/

if (fo_has_table($conn, 'stipend_schedules')) {
    $summarySql = "
        SELECT
            COUNT(*) AS item_count,
            COALESCE(SUM(total_amount), 0) AS amount,
            COALESCE(
                SUM(CASE
                    WHEN period_end < CURDATE() THEN 1
                    ELSE 0
                END),
                0
            ) AS overdue_count
        FROM stipend_schedules
        WHERE created_by = ?
          AND status = 'approved'
    ";

    $summaryStmt = $conn->prepare($summarySql);

    if ($summaryStmt) {
        $summaryStmt->bind_param('i', $uid);

        if ($summaryStmt->execute()) {
            $summary = $summaryStmt
                ->get_result()
                ->fetch_assoc() ?: [];

            $totals = [
                'count' => (int) ($summary['item_count'] ?? 0),
                'amount' => (float) ($summary['amount'] ?? 0),
                'overdue' => (int) ($summary['overdue_count'] ?? 0),
            ];
        } else {
            $errorMessage = 'Unable to load the outstanding payment summary.';
        }

        $summaryStmt->close();
    } else {
        $errorMessage = 'Unable to prepare the payment summary query.';
    }

    /*
    |--------------------------------------------------------------------------
    | Schedule list
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            schedule_reference,
            period_start,
            period_end,
            programme_name,
            participant_count,
            total_amount,
            exception_count,
            approved_at,
            created_at,
            DATEDIFF(CURDATE(), period_end) AS days_since_period_end
        FROM stipend_schedules
        WHERE created_by = ?
          AND status = 'approved'
    ";

    if ($filter === 'overdue') {
        $sql .= ' AND period_end < CURDATE()';
    }

    $sql .= '
        ORDER BY period_end ASC, created_at ASC
    ';

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param('i', $uid);

        if ($stmt->execute()) {
            $result = $stmt->get_result();

            while ($record = $result->fetch_assoc()) {
                $rows[] = $record;
            }
        } else {
            $errorMessage = 'Unable to load outstanding stipend schedules.';
        }

        $stmt->close();
    } else {
        $errorMessage = 'Unable to prepare the outstanding schedules query.';
    }
} else {
    $errorMessage = 'The stipend schedules table is unavailable.';
}

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>

<div class="fo-page-header">
    <div>
        <span>Payment Operations</span>
        <h2>Outstanding Payments Monitoring</h2>
        <p>
            Track approved stipend schedules that have not yet
            been marked as processed.
        </p>
    </div>

    <a
        class="fo-btn fo-btn--secondary"
        href="<?= e(url('finance/dashboard.php')) ?>"
    >
        <i class="fas fa-arrow-left"></i>
        Back to Dashboard
    </a>
</div>

<div class="fo-note">
    <i class="fas fa-circle-info"></i>

    <span>
        <strong>Scope:</strong>
        This page tracks schedule workflow status, not confirmed
        bank settlement or individual participant payments.
        Schedules appear while their status is
        <em>approved</em> and leave this list when marked
        <em>processed</em>.
    </span>
</div>

<?php if ($errorMessage !== null): ?>

    <div class="fo-note fo-note--warning" role="alert">
        <i class="fas fa-triangle-exclamation"></i>
        <span><?= e($errorMessage) ?></span>
    </div>

<?php endif; ?>

<section class="fo-stats fo-stats--4">

    <article>
        <i class="fas fa-file-invoice-dollar"></i>

        <strong>
            <?= number_format($totals['count']) ?>
        </strong>

        <span>Outstanding approved schedules</span>
    </article>

    <article>
        <i class="fas fa-wallet"></i>

        <strong>
            <?= e(fo_money($totals['amount'])) ?>
        </strong>

        <span>Total approved value awaiting processing</span>
    </article>

    <article class="<?= $totals['overdue'] > 0
        ? 'fo-stat-alert--warning'
        : '' ?>">

        <i class="fas fa-calendar-xmark"></i>

        <strong>
            <?= number_format($totals['overdue']) ?>
        </strong>

        <span>Past period end</span>
    </article>

    <article>
        <i class="fas fa-shield-halved"></i>

        <strong>Approved</strong>

        <span>Only approved schedules are listed</span>
    </article>

</section>

<div class="fo-card">

    <div class="fo-card__header fo-card__header--split">

        <div>
            <h3>Schedules Awaiting Processing</h3>

            <p>
                <?= number_format(count($rows)) ?>
                schedule(s)
                <?= $filter === 'overdue'
                    ? 'past period end'
                    : 'in this view' ?>.
            </p>
        </div>

        <div class="fo-filter-links">

            <a
                class="fo-btn <?= $filter === 'all'
                    ? 'fo-btn--primary'
                    : 'fo-btn--secondary' ?>"
                href="<?= e(url('finance/outstanding_payments.php')) ?>"
            >
                All outstanding
            </a>

            <a
                class="fo-btn <?= $filter === 'overdue'
                    ? 'fo-btn--primary'
                    : 'fo-btn--secondary' ?>"
                href="<?= e(url('finance/outstanding_payments.php?filter=overdue')) ?>"
            >
                Past period end
            </a>

        </div>

    </div>

    <?php if (empty($rows)): ?>

        <div class="fo-empty">

            <i class="fas fa-circle-check"></i>

            <strong>No matching outstanding schedules</strong>

            <span>
                <?= $filter === 'overdue'
                    ? 'There are no approved schedules past their period end.'
                    : 'There are no approved schedules awaiting processing.' ?>
            </span>

        </div>

    <?php else: ?>

        <div class="fo-table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Schedule reference</th>
                        <th>Programme</th>
                        <th>Period</th>
                        <th>Participants</th>
                        <th>Amount</th>
                        <th>Exceptions</th>
                        <th>Age</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($rows as $record): ?>

                    <?php
                    $scheduleId = (int) ($record['id'] ?? 0);

                    $daysSinceEnd = (int) (
                        $record['days_since_period_end'] ?? 0
                    );

                    $isOverdue = $daysSinceEnd > 0;

                    /*
                     * The target must exist at:
                     * finance/schedule_view.php
                     */
                    $reviewUrl = url(
                        'finance/schedule_view.php?id=' . $scheduleId
                    );
                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= e($record['schedule_reference'] ?? '') ?>
                            </strong>

                            <span>
                                Approved
                                <?= e(fo_datetime(
                                    $record['approved_at'] ?? null
                                )) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(
                                $record['programme_name']
                                ?: 'All programmes'
                            ) ?>
                        </td>

                        <td>
                            <?= e(fo_date($record['period_start'] ?? null)) ?>
                            –
                            <?= e(fo_date($record['period_end'] ?? null)) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (int) ($record['participant_count'] ?? 0)
                            ) ?>
                        </td>

                        <td>
                            <strong>
                                <?= e(fo_money(
                                    $record['total_amount'] ?? 0
                                )) ?>
                            </strong>
                        </td>

                        <td>
                            <?= number_format(
                                (int) ($record['exception_count'] ?? 0)
                            ) ?>
                        </td>

                        <td>
                            <span class="fo-status fo-status--<?= $isOverdue
                                ? 'danger'
                                : 'warning' ?>">

                                <?= $isOverdue
                                    ? number_format($daysSinceEnd)
                                        . ' days past end'
                                    : 'Current period' ?>

                            </span>
                        </td>

                        <td>
                            <a
                                class="fo-btn fo-btn--secondary"
                                href="<?= e($reviewUrl) ?>"
                            >
                                Review
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/_layout_end.php'; ?>