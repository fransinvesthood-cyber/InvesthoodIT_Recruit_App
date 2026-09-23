-- ============================================================
-- INVESTHOOD IT - Placements Management Module (Stage 12)
-- ============================================================
-- Adds the Placements Management module for tracking candidate
-- placements after offer acceptance.
--
-- Tables added:
--   placements             - Main placement records
--   placement_status_history - Audit trail for placement changes
--   placement_notifications  - Notifications related to placements
-- ============================================================

-- ------------------------------------------------------------
-- 1. PLACEMENTS
--    Main placement records for candidates who have accepted
--    offers and are ready for placement into programmes/cohorts.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `placements` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `placement_reference` VARCHAR(30) NOT NULL COMMENT 'Unique placement reference: PLAC-YYYY-XXXXXX',
  `candidate_id`    INT UNSIGNED NOT NULL COMMENT 'FK to users.id - the placed candidate',
  `application_id`  INT UNSIGNED NOT NULL COMMENT 'FK to applications.id - the successful application',
  `offer_id`        INT UNSIGNED NOT NULL COMMENT 'FK to offers.id - the accepted offer',
  `programme_id`    INT UNSIGNED NOT NULL COMMENT 'FK to programmes.id - assigned programme',
  `cohort_id`       INT UNSIGNED NOT NULL COMMENT 'FK to cohorts.id - assigned cohort',
  `department`      VARCHAR(150) DEFAULT NULL COMMENT 'Department/team assignment',
  `location`        VARCHAR(200) DEFAULT NULL COMMENT 'Placement location/office',
  `supervisor_id`   INT UNSIGNED DEFAULT NULL COMMENT 'FK to users.id - workplace supervisor',
  `start_date`      DATE NOT NULL COMMENT 'Placement start date',
  `end_date`        DATE NOT NULL COMMENT 'Placement end date',
  `status`          ENUM('pending_placement','placement_in_progress','placed','active','completed','withdrawn','cancelled')
                    NOT NULL DEFAULT 'pending_placement' COMMENT 'Current placement status',
  `notes`           TEXT DEFAULT NULL COMMENT 'Internal placement notes - ADMIN ONLY',
  `created_by`      INT UNSIGNED DEFAULT NULL COMMENT 'FK to users.id - admin who created placement',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_placements_candidate_active` (`candidate_id`) COMMENT 'Prevent multiple active placements',
  UNIQUE KEY `uq_placements_reference` (`placement_reference`),
  KEY `idx_placements_candidate` (`candidate_id`),
  KEY `idx_placements_status` (`status`),
  KEY `idx_placements_programme` (`programme_id`),
  KEY `idx_placements_cohort` (`cohort_id`),
  KEY `idx_placements_supervisor` (`supervisor_id`),
  KEY `idx_placements_dates` (`start_date`,`end_date`),
  CONSTRAINT `fk_placements_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_placements_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_placements_offer` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_placements_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_placements_cohort` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_placements_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_placements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET NAMES utf8mb4;
USE `investhood_platform`;

-- ------------------------------------------------------------
-- 2. PLACEMENT STATUS HISTORY
--    Audit trail of all placement status and field changes.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `placement_status_history` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `placement_id`    INT UNSIGNED NOT NULL,
  `field_name`      VARCHAR(50) DEFAULT NULL COMMENT 'Field changed (status, supervisor, location, etc.)',
  `previous_value`  TEXT DEFAULT NULL COMMENT 'Previous value (NULL for creation)',
  `new_value`       TEXT NOT NULL COMMENT 'New value after change',
  `changed_by`      INT UNSIGNED DEFAULT NULL COMMENT 'Admin who made the change',
  `change_reason`   VARCHAR(500) DEFAULT NULL COMMENT 'Optional reason',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_placement_history_placement` (`placement_id`,`created_at`),
  KEY `idx_placement_history_new_value` (`new_value`(100)),
  KEY `idx_placement_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_placement_history_placement` FOREIGN KEY (`placement_id`) REFERENCES `placements` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_placement_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. PLACEMENT NOTIFICATIONS
--    Notifications generated by placement actions.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `placement_notifications` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `placement_id`    INT UNSIGNED NOT NULL,
  `candidate_id`    INT UNSIGNED NOT NULL COMMENT 'Recipient candidate',
  `sender_id`       INT UNSIGNED DEFAULT NULL COMMENT 'Admin who triggered notification',
  `notification_type` ENUM('placement_created','placement_updated','placement_assigned','dates_changed','status_changed','placement_completed','placement_cancelled')
                    NOT NULL,
  `title`           VARCHAR(200) NOT NULL,
  `message`         TEXT NOT NULL,
  `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_placement_notifs_candidate` (`candidate_id`,`is_read`),
  KEY `idx_placement_notifs_placement` (`placement_id`),
  CONSTRAINT `fk_placement_notifs_placement` FOREIGN KEY (`placement_id`) REFERENCES `placements` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_placement_notifs_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_placement_notifs_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
