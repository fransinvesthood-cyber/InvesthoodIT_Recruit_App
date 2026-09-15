<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

echo '<h1>Database Diagnostic</h1>';

// Check if tables exist
$tables = ['interviews', 'interview_feedback', 'interview_status_history'];
echo '<h2>Table Status</h2><ul>';
foreach ($tables as $table) {
    $result = Database::fetchAll("SHOW TABLES LIKE '{$table}'");
    if (!empty($result)) {
        echo "<li style='color:green'>✓ {$table} - EXISTS</li>";
    } else {
        echo "<li style='color:red'>✗ {$table} - MISSING</li>";
    }
}
echo '</ul>';

// Try to create tables
echo '<h2>Creating Tables...</h2><ul>';
try {
    Database::execute("CREATE TABLE IF NOT EXISTS `interviews` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `application_id` INT UNSIGNED NOT NULL,
        `interviewer_id` INT UNSIGNED NULL,
        `interview_date` DATE NOT NULL,
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        `interview_type` ENUM('online','in_person','phone') NOT NULL DEFAULT 'online',
        `location` VARCHAR(300) NULL,
        `status` ENUM('scheduled','confirmed','rescheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
        `notes` TEXT NULL,
        `cancellation_reason` TEXT NULL,
        `cancelled_by` INT UNSIGNED NULL,
        `cancelled_at` DATETIME NULL,
        `previous_date` DATE NULL,
        `previous_start_time` TIME NULL,
        `previous_end_time` TIME NULL,
        `reschedule_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_by` INT UNSIGNED NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<li style='color:green'>✓ interviews table created</li>";
} catch (Exception $e) {
    echo "<li style='color:red'>✗ interviews: " . $e->getMessage() . "</li>";
}

try {
    Database::execute("CREATE TABLE IF NOT EXISTS `interview_feedback` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `interview_id` INT UNSIGNED NOT NULL,
        `application_id` INT UNSIGNED NOT NULL,
        `interviewer_id` INT UNSIGNED NULL,
        `overall_rating` TINYINT UNSIGNED NULL,
        `technical_rating` TINYINT UNSIGNED NULL,
        `communication_rating` TINYINT UNSIGNED NULL,
        `problem_solving_rating` TINYINT UNSIGNED NULL,
        `programme_suitability` ENUM('excellent','good','fair','poor') NULL,
        `strengths` TEXT NULL,
        `areas_for_improvement` TEXT NULL,
        `general_comments` TEXT NULL,
        `recommendation` ENUM('strongly_recommended','recommended','consider','not_recommended') NULL,
        `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<li style='color:green'>✓ interview_feedback table created</li>";
} catch (Exception $e) {
    echo "<li style='color:red'>✗ interview_feedback: " . $e->getMessage() . "</li>";
}

try {
    Database::execute("CREATE TABLE IF NOT EXISTS `interview_status_history` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `interview_id` INT UNSIGNED NOT NULL,
        `previous_status` VARCHAR(30) NULL,
        `new_status` VARCHAR(30) NOT NULL,
        `changed_by` INT UNSIGNED NULL,
        `change_reason` TEXT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<li style='color:green'>✓ interview_status_history table created</li>";
} catch (Exception $e) {
    echo "<li style='color:red'>✗ interview_status_history: " . $e->getMessage() . "</li>";
}
echo '</ul>';

// Check tables again
echo '<h2>Verification</h2><ul>';
foreach ($tables as $table) {
    $result = Database::fetchAll("SHOW TABLES LIKE '{$table}'");
    if (!empty($result)) {
        echo "<li style='color:green'>✓ {$table} - EXISTS</li>";
    } else {
        echo "<li style='color:red'>✗ {$table} - MISSING</li>";
    }
}
echo '</ul>';

echo '<p><a href="' . url('admin/interviews.php') . '">Go to Interview Management</a></p>';
