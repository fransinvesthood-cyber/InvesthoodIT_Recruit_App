-- ============================================================
-- INVESTHOOD IT Opportunity Management - Migration
-- ============================================================
-- Adds the Opportunity Management module:
--   opportunities,
--   opportunity_skills,
--   opportunity_eligibility,
--   opportunity_documents,
--   opportunity_responsibilities
--
-- This migration is additive and does NOT modify the existing
-- authentication, candidate profile, or programme/cohort schema.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. OPPORTUNITIES
--    An opportunity belongs to a programme and optionally a cohort.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `opportunities`;
CREATE TABLE `opportunities` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `programme_id`            INT UNSIGNED NOT NULL,
  `cohort_id`               INT UNSIGNED NULL,
  `title`                   VARCHAR(200) NOT NULL,
  `type`                    ENUM('graduate_programme','internship','learnership','wil',
                                'skills_development','mentorship','other')
                            NOT NULL DEFAULT 'other',
  `organisation`            VARCHAR(200) NULL,
  `short_description`       VARCHAR(500) NULL,
  `full_description`        TEXT         NULL,
  `application_open_date`   DATE         NULL,
  `application_close_date`  DATE         NULL,
  `start_date`              DATE         NULL,
  `end_date`                DATE         NULL,
  `available_positions`     INT UNSIGNED NOT NULL DEFAULT 0,
  `applications_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `min_age`                 INT UNSIGNED NULL,
  `max_age`                 INT UNSIGNED NULL,
  `province`                VARCHAR(50)  NULL,
  `city`                    VARCHAR(100) NULL,
  `physical_location`       VARCHAR(200) NULL,
  `work_arrangement`        ENUM('on_site','remote','hybrid') NOT NULL DEFAULT 'hybrid',
  `status`                  ENUM('draft','published','closing_soon','closed','archived')
                            NOT NULL DEFAULT 'draft',
  `created_by`              INT UNSIGNED NULL,
  `created_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_opportunities_status` (`status`),
  KEY `idx_opportunities_type` (`type`),
  KEY `idx_opportunities_programme` (`programme_id`),
  KEY `idx_opportunities_cohort` (`cohort_id`),
  KEY `idx_opportunities_open` (`application_open_date`),
  KEY `idx_opportunities_close` (`application_close_date`),
  KEY `idx_opportunities_province` (`province`),
  KEY `idx_opportunities_created_by` (`created_by`),
  CONSTRAINT `fk_opportunities_programme`
    FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_opportunities_cohort`
    FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_opportunities_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. OPPORTUNITY SKILLS
--    Required/preferred technical and soft skills per opportunity.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `opportunity_skills`;
CREATE TABLE `opportunity_skills` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `opportunity_id` INT UNSIGNED NOT NULL,
  `skill_name`     VARCHAR(150) NOT NULL,
  `skill_category` ENUM('required_technical','preferred_technical','required_soft')
                   NOT NULL DEFAULT 'required_technical',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_opp_skills_opp_name_cat` (`opportunity_id`, `skill_name`, `skill_category`),
  KEY `idx_opp_skills_opportunity` (`opportunity_id`),
  KEY `idx_opp_skills_category` (`skill_category`),
  CONSTRAINT `fk_opp_skills_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. OPPORTUNITY ELIGIBILITY
--    Opportunity-specific eligibility requirements (one row per opportunity).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `opportunity_eligibility`;
CREATE TABLE `opportunity_eligibility` (
  `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `opportunity_id`           INT UNSIGNED NOT NULL,
  `qualification_requirements` TEXT        NULL,
  `required_skills`          TEXT         NULL,
  `preferred_skills`         TEXT         NULL,
  `min_experience`           VARCHAR(100) NULL,
  `availability_requirements` TEXT        NULL,
  `other_requirements`       TEXT         NULL,
  `created_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_opp_elig_opportunity` (`opportunity_id`),
  CONSTRAINT `fk_opp_elig_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. OPPORTUNITY DOCUMENTS
--    Required/optional document configuration per opportunity.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `opportunity_documents`;
CREATE TABLE `opportunity_documents` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `opportunity_id` INT UNSIGNED NOT NULL,
  `document_name`  VARCHAR(150) NOT NULL,
  `is_required`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_opp_docs_opp_name` (`opportunity_id`, `document_name`),
  KEY `idx_opp_docs_opportunity` (`opportunity_id`),
  CONSTRAINT `fk_opp_docs_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. OPPORTUNITY RESPONSIBILITIES
--    Responsibilities, duties, activities and learning outcomes.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `opportunity_responsibilities`;
CREATE TABLE `opportunity_responsibilities` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `opportunity_id` INT UNSIGNED NOT NULL,
  `type`           ENUM('key_responsibilities','duties','programme_activities','learning_outcomes')
                   NOT NULL DEFAULT 'key_responsibilities',
  `content`        TEXT NOT NULL,
  `sort_order`     INT          NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_opp_resp_opportunity` (`opportunity_id`),
  KEY `idx_opp_resp_type` (`type`),
  CONSTRAINT `fk_opp_resp_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
