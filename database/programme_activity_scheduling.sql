-- Programme Activity Scheduling & Delivery Tracking

CREATE TABLE IF NOT EXISTS `programme_activities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `programme_id` INT UNSIGNED NOT NULL,
  `cohort_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `location` VARCHAR(180) DEFAULT NULL,
  `scheduled_date` DATE NOT NULL,
  `responsible_user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('planned','in_progress','completed','on_hold','cancelled') NOT NULL DEFAULT 'planned',
  `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `actual_completed_on` DATE DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_programme_activities_programme_date` (`programme_id`,`scheduled_date`),
  KEY `idx_programme_activities_cohort` (`cohort_id`),
  KEY `idx_programme_activities_responsible_date` (`responsible_user_id`,`scheduled_date`),
  KEY `idx_programme_activities_status_date` (`status`,`scheduled_date`),
  CONSTRAINT `fk_programme_activities_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_programme_activities_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_programme_activities_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_programme_activities_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_programme_activities_progress` CHECK (`progress` BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
