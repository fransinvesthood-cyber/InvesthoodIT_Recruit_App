-- ============================================================
-- INVESTHOOD IT - Application Status Pipeline Update
-- ============================================================
-- Replaces the legacy application statuses with the new
-- recruitment pipeline statuses:
--
--   Under Review, Shortlisted, Assessment, Interview Scheduled,
--   Interview Completed, Selected, Offer Sent, Offer Accepted,
--   Offer Declined, Rejected, Withdrawn, On Hold
--
-- 'draft' and 'submitted' are kept as candidate-side states.
--
-- IMPORTANT ORDERING:
--   A value can only be assigned to an ENUM column once it exists in
--   the column definition, so the ENUM is first widened to include
--   BOTH the legacy and the new values, the legacy values are then
--   remapped, and finally the ENUM is narrowed to the new set.
--
--   eligibility_review -> under_review
--   screened           -> shortlisted
--   interview          -> interview_scheduled
--   waitlisted         -> on_hold
--   expired            -> rejected
--   (assessment, selected, rejected, withdrawn are unchanged)
--
-- The script is idempotent and safe to re-run.
-- ============================================================

SET NAMES utf8mb4;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. Widen the `applications.status` ENUM to include the
--    legacy AND the new pipeline values
-- ------------------------------------------------------------
ALTER TABLE `applications`
  MODIFY COLUMN `status` ENUM(
    'draft',
    'submitted',
    'eligibility_review',
    'screened',
    'assessment',
    'interview',
    'waitlisted',
    'selected',
    'rejected',
    'withdrawn',
    'expired',
    'under_review',
    'shortlisted',
    'interview_scheduled',
    'interview_completed',
    'offer_sent',
    'offer_accepted',
    'offer_declined',
    'on_hold'
  ) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft';

-- ------------------------------------------------------------
-- 2. Remap legacy statuses to the new pipeline values
-- ------------------------------------------------------------
UPDATE `applications` SET `status` = 'under_review'        WHERE `status` = 'eligibility_review';
UPDATE `applications` SET `status` = 'shortlisted'         WHERE `status` = 'screened';
UPDATE `applications` SET `status` = 'interview_scheduled' WHERE `status` = 'interview';
UPDATE `applications` SET `status` = 'on_hold'             WHERE `status` = 'waitlisted';
UPDATE `applications` SET `status` = 'rejected'            WHERE `status` = 'expired';

-- ------------------------------------------------------------
-- 3. Narrow the `applications.status` ENUM to the final
--    pipeline set (no rows hold legacy values anymore)
-- ------------------------------------------------------------
ALTER TABLE `applications`
  MODIFY COLUMN `status` ENUM(
    'draft',
    'submitted',
    'under_review',
    'shortlisted',
    'assessment',
    'interview_scheduled',
    'interview_completed',
    'selected',
    'offer_sent',
    'offer_accepted',
    'offer_declined',
    'rejected',
    'withdrawn',
    'on_hold'
  ) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft';

-- ------------------------------------------------------------
-- 4. Ensure the status-history audit table exists
--    (canonical Stage 9 layout, matches
--     database/application_status_history.sql).
--    IF NOT EXISTS keeps any existing history data intact —
--    this script never drops the table.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `application_status_history` (
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