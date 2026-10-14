-- Admin Notifications (shared feed + per-admin state).
CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(30) NOT NULL,
  `priority` VARCHAR(20) NOT NULL DEFAULT 'info',
  `title` VARCHAR(180) NOT NULL,
  `message` TEXT NOT NULL,
  `module` VARCHAR(40) DEFAULT NULL,
  `record_id` INT UNSIGNED DEFAULT NULL,
  `dedupe_key` VARCHAR(160) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_notification_event` (`dedupe_key`),
  KEY `idx_admin_notifications_feed` (`created_at`),
  KEY `idx_admin_notifications_category` (`category`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `admin_notification_state` (
  `notification_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`, `admin_id`),
  KEY `idx_admin_notif_state_admin` (`admin_id`, `is_read`, `is_hidden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
