-- ============================================================
-- INVESTHOOD IT Programme & Cohort Management - Migration
-- ============================================================
-- Adds the Programme & Cohort Management module:
--   programmes, cohorts, programme_eligibility, cohort_eligibility,
--   cohort_skills, cohort_documents, cohort_workflow, cohort_participants
--
-- This migration is additive and does NOT modify the existing
-- authentication or candidate profile schema.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. PROGRAMMES
--    A Programme represents the overall initiative.
--    A Programme can contain multiple cohorts.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `programmes`;
CREATE TABLE `programmes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(200) NOT NULL,
  `type`        ENUM('graduate_programme','internship','learnership','wil','skills_development','other')
                NOT NULL DEFAULT 'other',
  `description` TEXT         NULL,
  `objectives`  TEXT         NULL,
  `duration`    VARCHAR(100) NULL,
  `start_date`  DATE         NULL,
  `end_date`    DATE         NULL,
  `status`      ENUM('draft','active','paused','completed','archived')
                NOT NULL DEFAULT 'draft',
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_programmes_type` (`type`),
  KEY `idx_programmes_status` (`status`),
  KEY `idx_programmes_start` (`start_date`),
  KEY `idx_programmes_end` (`end_date`),
  KEY `idx_programmes_created_by` (`created_by`),
  CONSTRAINT `fk_programmes_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. COHORTS
--    A cohort belongs to exactly one programme.
--    A programme can contain many cohorts.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `cohorts`;
CREATE TABLE `cohorts` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `programme_id`          INT UNSIGNED NOT NULL,
  `name`                  VARCHAR(200) NOT NULL,
  `description`           TEXT         NULL,
  `start_date`            DATE         NULL,
  `end_date`              DATE         NULL,
  `application_open_date` DATE         NULL,
  `application_close_date` DATE        NULL,
  `max_capacity`          INT UNSIGNED NOT NULL DEFAULT 0,
  `applications_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `location`              VARCHAR(150) NULL,
  `province`              VARCHAR(50)  NULL,
  `delivery_mode`         ENUM('on_site','remote','hybrid') NOT NULL DEFAULT 'hybrid',
  `status`                ENUM('draft','open','closed','active','completed','archived')
                          NOT NULL DEFAULT 'draft',
  `created_by`            INT UNSIGNED NULL,
  `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cohorts_programme_name` (`programme_id`, `name`),
  KEY `idx_cohorts_status` (`status`),
  KEY `idx_cohorts_start` (`start_date`),
  KEY `idx_cohorts_close` (`application_close_date`),
  KEY `idx_cohorts_province` (`province`),
  KEY `idx_cohorts_delivery` (`delivery_mode`),
  CONSTRAINT `fk_cohorts_programme`
    FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_cohorts_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. PROGRAMME ELIGIBILITY
--    Eligibility requirements that apply at programme level.
--    Stored relationally (not hard-coded comma lists).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `programme_eligibility`;
CREATE TABLE `programme_eligibility` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `programme_id`            INT UNSIGNED NOT NULL,
  `qualification_level`     VARCHAR(50)  NULL,
  `qualification_name`      VARCHAR(150) NULL,
  `field_of_study`          VARCHAR(150) NULL,
  `institution_requirements` TEXT        NULL,
  `min_completion_year`     YEAR         NULL,
  `max_completion_year`     YEAR         NULL,
  `min_experience`          INT UNSIGNED NULL,
  `max_experience`          INT UNSIGNED NULL,
  `province`                VARCHAR(50)  NULL,
  `city`                    VARCHAR(100) NULL,
  `location_restrictions`   TEXT         NULL,
  `availability`            TEXT         NULL,
  `citizenship_residency`   TEXT         NULL,
  `programme_specific`      TEXT         NULL,
  `created_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prog_elig_programme` (`programme_id`),
  CONSTRAINT `fk_prog_elig_programme`
    FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. COHORT ELIGIBILITY
--    Eligibility requirements specific to a cohort.
--    Overrides/extends the programme-level requirements.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `cohort_eligibility`;
CREATE TABLE `cohort_eligibility` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cohort_id`               INT UNSIGNED NOT NULL,
  `qualification_level`     VARCHAR(50)  NULL,
  `qualification_name`      VARCHAR(150) NULL,
  `field_of_study`          VARCHAR(150) NULL,
  `institution_requirements` TEXT        NULL,
  `min_completion_year`     YEAR         NULL,
  `max_completion_year`     YEAR         NULL,
  `min_experience`          INT UNSIGNED NULL,
  `max_experience`          INT UNSIGNED NULL,
  `province`                VARCHAR(50)  NULL,
  `city`                    VARCHAR(100) NULL,
  `location_restrictions`   TEXT         NULL,
  `availability`            TEXT         NULL,
  `citizenship_residency`   TEXT         NULL,
  `programme_specific`      TEXT         NULL,
  `created_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cohort_elig_cohort` (`cohort_id`),
  CONSTRAINT `fk_cohort_elig_cohort`
    FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. COHORT SKILLS
--    Required/preferred technical and soft skills per cohort.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `cohort_skills`;
CREATE TABLE `cohort_skills` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cohort_id`      INT UNSIGNED NOT NULL,
  `skill_name`     VARCHAR(150) NOT NULL,
  `skill_category` ENUM('required_technical','preferred_technical','required_soft')
                   NOT NULL DEFAULT 'required_technical',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cohort_skills_cohort_name_cat` (`cohort_id`, `skill_name`, `skill_category`),
  KEY `idx_cohort_skills_cohort` (`cohort_id`),
  KEY `idx_cohort_skills_category` (`skill_category`),
  CONSTRAINT `fk_cohort_skills_cohort`
    FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. COHORT DOCUMENTS / REQUIRED EVIDENCE
--    Required evidence configuration per cohort.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `cohort_documents`;
CREATE TABLE `cohort_documents` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cohort_id`             INT UNSIGNED NOT NULL,
  `document_name`         VARCHAR(150) NOT NULL,
  `is_required`           TINYINT(1)   NOT NULL DEFAULT 1,
  `verification_required` TINYINT(1)   NOT NULL DEFAULT 0,
  `expiry_required`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cohort_docs_cohort_name` (`cohort_id`, `document_name`),
  KEY `idx_cohort_docs_cohort` (`cohort_id`),
  CONSTRAINT `fk_cohort_docs_cohort`
    FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. COHORT WORKFLOW
--    Configuration foundation for future modules. Defines which
--    workflow stages are active for a cohort.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `cohort_workflow`;
CREATE TABLE `cohort_workflow` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cohort_id`   INT UNSIGNED NOT NULL,
  `stage`       ENUM('application','eligibility_review','screening','assessment',
                     'interview','selection','onboarding','active_participant')
               NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cohort_workflow_cohort_stage` (`cohort_id`, `stage`),
  KEY `idx_cohort_workflow_cohort` (`cohort_id`),
  CONSTRAINT `fk_cohort_workflow_cohort`
    FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. COHORT PARTICIPANTS
--    Foundation for tracking selected/onboarded/active/participants
--    to support capacity tracking and future modules.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `cohort_participants`;
CREATE TABLE `cohort_participants` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cohort_id`     INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `status`        ENUM('selected','onboarded','active','completed','withdrawn')
                  NOT NULL DEFAULT 'selected',
  `selected_at`   DATETIME     NULL,
  `onboarded_at`  DATETIME     NULL,
  `completed_at`  DATETIME     NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cohort_participants_cohort_user` (`cohort_id`, `user_id`),
  KEY `idx_cohort_participants_cohort` (`cohort_id`, `status`),
  KEY `idx_cohort_participants_user` (`user_id`),
  CONSTRAINT `fk_cohort_participants_cohort`
    FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_cohort_participants_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
