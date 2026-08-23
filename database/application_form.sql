-- ============================================================
-- INVESTHOOD IT - Application Form (Stage 3) Migration
-- ============================================================
-- Adds configurable opportunity questions and per-application
-- response storage. This migration is additive and does NOT
-- modify existing authentication, profile, programme, cohort,
-- opportunity or applications schema.
--
-- Tables added:
--   opportunity_questions  - configurable eligibility & application questions
--   application_responses - candidate answers tied to an application
--
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. OPPORTUNITY QUESTIONS
--    Configurable questions configured per opportunity.
--    Section distinguishes eligibility screening questions
--    from opportunity-specific application questions.
--    Options are stored as a JSON array for choice-based types.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `opportunity_questions`;
CREATE TABLE `opportunity_questions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `opportunity_id` INT UNSIGNED NOT NULL,
  `section`        ENUM('eligibility','application') NOT NULL DEFAULT 'application',
  `question_text`  TEXT         NOT NULL,
  `question_type`  ENUM('text','textarea','yes_no','radio','dropdown','checkbox','number','date')
                           NOT NULL DEFAULT 'text',
  `options`        TEXT         NULL COMMENT 'JSON array of options for radio/dropdown/checkbox',
  `is_required`    TINYINT(1)   NOT NULL DEFAULT 0,
  `is_knockout`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Eligibility questions that may knock out',
  `sort_order`     INT          NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_opp_questions_opportunity` (`opportunity_id`),
  KEY `idx_opp_questions_section` (`section`),
  CONSTRAINT `fk_opp_questions_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. APPLICATION RESPONSES
--    Stores one response per question per application.
--    Responses belong to the candidate's application and are
--    never stored on the Candidate Profile.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `application_responses`;
CREATE TABLE `application_responses` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `question_id`    INT UNSIGNED NOT NULL,
  `response`       TEXT         NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_responses_application_question` (`application_id`, `question_id`),
  KEY `idx_app_responses_application` (`application_id`),
  KEY `idx_app_responses_question` (`question_id`),
  CONSTRAINT `fk_app_responses_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_app_responses_question`
    FOREIGN KEY (`question_id`) REFERENCES `opportunity_questions` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;