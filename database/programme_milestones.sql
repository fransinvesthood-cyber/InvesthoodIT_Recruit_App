-- Programme Milestone & Deadline Tracking
-- Import this migration into the active Investhood application database.
CREATE TABLE IF NOT EXISTS `programme_milestones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `programme_id` INT UNSIGNED NOT NULL,
  `cohort_id` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `due_date` DATE NOT NULL,
  `status` ENUM('planned','in_progress','completed','on_hold') NOT NULL DEFAULT 'planned',
  `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pm_milestones_programme_due` (`programme_id`, `due_date`),
  KEY `idx_pm_milestones_cohort` (`cohort_id`),
  KEY `idx_pm_milestones_status_due` (`status`, `due_date`),
  CONSTRAINT `fk_pm_milestones_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pm_milestones_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_pm_milestone_progress` CHECK (`progress` BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
