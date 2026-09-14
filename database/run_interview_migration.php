<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$errors = [];
$success = [];

try {
    Database::execute("
        CREATE TABLE IF NOT EXISTS `interviews` (
          `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `application_id`      INT UNSIGNED NOT NULL,
          `interviewer_id`      INT UNSIGNED NULL,
          `interview_date`      DATE         NOT NULL,
          `start_time`          TIME         NOT NULL,
          `end_time`            TIME         NOT NULL,
          `interview_type`      ENUM('online','in_person','phone') NOT NULL DEFAULT 'online',
          `location`            VARCHAR(300) NULL,
          `status`              ENUM('scheduled','confirmed','rescheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
          `notes`               TEXT         NULL,
          `cancellation_reason` TEXT         NULL,
          `cancelled_by`        INT UNSIGNED NULL,
          `cancelled_at`        DATETIME     NULL,
          `previous_date`       DATE         NULL,
          `previous_start_time` TIME         NULL,
          `previous_end_time`   TIME         NULL,
          `reschedule_count`    INT UNSIGNED NOT NULL DEFAULT 0,
          `created_by`          INT UNSIGNED NULL,
          `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_interviews_application_date` (`application_id`, `interview_date`, `start_time`),
          KEY `idx_interviews_application` (`application_id`),
          KEY `idx_interviews_interviewer` (`interviewer_id`),
          KEY `idx_interviews_date` (`interview_date`),
          KEY `idx_interviews_status` (`status`),
          KEY `idx_interviews_type` (`interview_type`),
          KEY `idx_interviews_created_by` (`created_by`),
          CONSTRAINT `fk_interviews_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `fk_interviews_interviewer` FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
          CONSTRAINT `fk_interviews_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
          CONSTRAINT `fk_interviews_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = 'Created table: interviews';
} catch (Exception $e) {
    $errors[] = 'Error creating interviews table: ' . $e->getMessage();
}

try {
    Database::execute("
        CREATE TABLE IF NOT EXISTS `interview_feedback` (
          `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `interview_id`          INT UNSIGNED NOT NULL,
          `application_id`        INT UNSIGNED NOT NULL,
          `interviewer_id`        INT UNSIGNED NULL,
          `overall_rating`        TINYINT UNSIGNED NULL,
          `technical_rating`      TINYINT UNSIGNED NULL,
          `communication_rating`  TINYINT UNSIGNED NULL,
          `problem_solving_rating` TINYINT UNSIGNED NULL,
          `programme_suitability` ENUM('excellent','good','fair','poor') NULL,
          `strengths`             TEXT         NULL,
          `areas_for_improvement` TEXT         NULL,
          `general_comments`      TEXT         NULL,
          `recommendation`        ENUM('strongly_recommended','recommended','consider','not_recommended') NULL,
          `submitted_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_interview_feedback_interview` (`interview_id`),
          KEY `idx_interview_feedback_application` (`application_id`),
          KEY `idx_interview_feedback_interviewer` (`interviewer_id`),
          KEY `idx_interview_feedback_recommendation` (`recommendation`),
          CONSTRAINT `fk_interview_feedback_interview` FOREIGN KEY (`interview_id`) REFERENCES `interviews` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `fk_interview_feedback_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `fk_interview_feedback_interviewer` FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = 'Created table: interview_feedback';
} catch (Exception $e) {
    $errors[] = 'Error creating interview_feedback table: ' . $e->getMessage();
}

try {
    Database::execute("
        CREATE TABLE IF NOT EXISTS `interview_status_history` (
          `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `interview_id`    INT UNSIGNED NOT NULL,
          `previous_status` VARCHAR(30)  NULL,
          `new_status`      VARCHAR(30)  NOT NULL,
          `changed_by`      INT UNSIGNED NULL,
          `change_reason`   TEXT         NULL,
          `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_interview_status_history_interview` (`interview_id`),
          KEY `idx_interview_status_history_changed_by` (`changed_by`),
          CONSTRAINT `fk_interview_status_history_interview` FOREIGN KEY (`interview_id`) REFERENCES `interviews` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `fk_interview_status_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = 'Created table: interview_status_history';
} catch (Exception $e) {
    $errors[] = 'Error creating interview_status_history table: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Migration | Investhood IT Admin</title>
    <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
</head>
<body style="padding:2rem;background:#f8fafc;">
    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
        <h1 style="margin:0 0 1.5rem;color:#1e293b;"><i class="fas fa-database"></i> Interview Management Migration</h1>
        <?php if (!empty($success)): ?>
            <div style="background:#d1fae5;border:1px solid #10b981;border-radius:8px;padding:1rem;margin-bottom:1rem;">
                <h3 style="margin:0 0 0.5rem;color:#047857;"><i class="fas fa-check-circle"></i> Success</h3>
                <ul style="margin:0;padding-left:1.25rem;color:#065f46;">
                    <?php foreach ($success as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div style="background:#fee2e2;border:1px solid #ef4444;border-radius:8px;padding:1rem;margin-bottom:1rem;">
                <h3 style="margin:0 0 0.5rem;color:#b91c1c;"><i class="fas fa-exclamation-circle"></i> Errors</h3>
                <ul style="margin:0;padding-left:1.25rem;color:#991b1b;">
                    <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (empty($errors)): ?>
            <p style="color:#047857;font-weight:600;"><i class="fas fa-check"></i> All tables created successfully!</p>
            <a href="<?= url('admin/interviews.php') ?>" class="btn btn--primary" style="display:inline-block;margin-top:1rem;text-decoration:none;"><i class="fas fa-calendar-check"></i> Go to Interview Management</a>
        <?php endif; ?>
    </div>
</body>
</html>
