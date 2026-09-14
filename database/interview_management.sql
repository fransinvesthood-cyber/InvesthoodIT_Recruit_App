-- ============================================================
-- INVESTHOOD IT Interview Management Module
-- ============================================================
-- Adds interview scheduling, tracking, feedback, and calendar
-- functionality. Links interviews to applications, candidates,
-- programmes, cohorts, opportunities, and interviewer users.
--
-- This migration is additive and does NOT modify existing tables.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. INTERVIEWS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `interviews`;
CREATE TABLE `interviews` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id`      INT UNSIGNED NOT NULL,
  `interviewer_id`      INT UNSIGNED NULL,
  `interview_date`      DATE         NOT NULL,
  `start_time`          TIME         NOT NULL,
  `end_time`            TIME         NOT NULL,
  `interview_type`      ENUM('online','in_person','phone') NOT NULL DEFAULT 'online',
  `location`            VARCHAR(300) NULL,
  `status`              ENUM('scheduled','confirmed','rescheduled','completed','cancelled','no_show')
                        NOT NULL DEFAULT 'scheduled',
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
  CONSTRAINT `fk_interviews_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_interviews_interviewer`
    FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_interviews_cancelled_by`
    FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_interviews_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. INTERVIEW FEEDBACK
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `interview_feedback`;
CREATE TABLE `interview_feedback` (
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
  CONSTRAINT `fk_interview_feedback_interview`
    FOREIGN KEY (`interview_id`) REFERENCES `interviews` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_interview_feedback_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_interview_feedback_interviewer`
    FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. INTERVIEW STATUS HISTORY
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `interview_status_history`;
CREATE TABLE `interview_status_history` (
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
  CONSTRAINT `fk_interview_status_history_interview`
    FOREIGN KEY (`interview_id`) REFERENCES `interviews` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_interview_status_history_changed_by`
    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
