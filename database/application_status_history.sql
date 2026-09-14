-- ============================================================
-- INVESTHOOD IT - Application Status History (Stage 9)
-- ============================================================
-- Adds the `application_status_history` table to track all
-- status changes for applications. This enables the admin
-- to view the complete timeline of an application's journey.
--
-- This migration is additive and does NOT modify existing
-- authentication, profile, programme, cohort, opportunity,
-- or applications schema.
--
-- Tables added:
--   application_status_history - status change audit trail
--
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. APPLICATION STATUS HISTORY
--    Records every status change for an application.
--    Provides a complete audit trail visible to administrators.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `application_status_history`;
CREATE TABLE `application_status_history` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id`  INT UNSIGNED NOT NULL,
  `previous_status` VARCHAR(50)  NULL COMMENT 'NULL for initial status',
  `new_status`      VARCHAR(50)  NOT NULL,
  `changed_by`      INT UNSIGNED NULL COMMENT 'User ID of admin who made the change (NULL for system)',
  `change_reason`   VARCHAR(500) NULL COMMENT 'Optional reason for the status change',
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_app_status_history_application` (`application_id`, `created_at`),
  KEY `idx_app_status_history_new_status` (`new_status`),
  KEY `idx_app_status_history_changed_by` (`changed_by`),
  KEY `idx_app_status_history_created` (`created_at`),
  CONSTRAINT `fk_app_status_history_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_app_status_history_changed_by`
    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;