
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('finance_officer');

$user = current_user();
$flashes = render_flashes();

$currentPage = 'schedules';
$pageTitle = 'Stipend Schedules';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$uid = (int) ($user['id'] ?? $user['user_id'] ?? 0);

$rows = [];

if (fo_has_table($conn, 'stipend_schedules')) {
    $sql = "
        SELECT *
        FROM stipend_schedules
        WHERE created_by = ?
        ORDER BY created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
}

require __DIR__ . '/_layout_start.php';
?>

<div class="fo-page-header">
    <div>
        <span>Payment Preparation</span>
        <h2>Stipend Schedules</h2>
        <p>Review draft, approval-pending and approved schedules.</p>
    </div>

    <a
        class="fo-btn fo-btn--primary"
        href="<?= e(url('finance/prepare_schedule.php')) ?>"
    >
        <i class="fas fa-plus"></i>
        Prepare Schedule
    </a>
</div>

<div class="fo-schedule-grid">

    <?php foreach ($rows as $schedule): ?>

        <article class="fo-card">

            <div class="fo-schedule-card__top">
                <i class="fas fa-file-invoice-dollar"></i>

                <span class="fo-status fo-status--<?= e(fo_status_class($schedule['status'] ?? '')) ?>">
                    <?= e(fo_label($schedule['status'] ?? 'unknown')) ?>
                </span>
            </div>

            <h3>
                <?= e($schedule['schedule_reference'] ?? 'Unreferenced Schedule') ?>
            </h3>

            <p>
                <?= e(fo_date($schedule['period_start'] ?? null)) ?>
                –
                <?= e(fo_date($schedule['period_end'] ?? null)) ?>
            </p>

            <div class="fo-metrics">

                <span>
                    <?= number_format((int) ($schedule['participant_count'] ?? 0)) ?>
                    participants
                </span>

                <span>
                    <?= e(fo_money($schedule['total_amount'] ?? 0)) ?>
                </span>

                <span>
                    <?= number_format((int) ($schedule['exception_count'] ?? 0)) ?>
                    exceptions
                </span>

            </div>

            <a
                class="fo-btn fo-btn--secondary"
                href="<?= e(url('finance/schedule_view.php?id=' . (int) ($schedule['id'] ?? 0))) ?>"
            >
                Open
                <i class="fas fa-arrow-right"></i>
            </a>

        </article>

    <?php endforeach; ?>

</div>

<?php if (empty($rows)): ?>

    <div class="fo-card">
        <div class="fo-empty">
            <i class="fas fa-file-invoice-dollar"></i>
            <strong>No schedules yet</strong>
            <p>Prepare a stipend schedule to see it listed here.</p>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>