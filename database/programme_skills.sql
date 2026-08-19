-- ============================================================
-- INVESTHOOD IT Programme Skills - Migration
-- ============================================================
-- Adds programme-level skills storage analogous to cohort_skills.
-- This migration is additive and safe to run.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- PROGRAMME SKILLS
--    Required/preferred technical and soft skills per programme.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `programme_skills`;
CREATE TABLE `programme_skills` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `programme_id`   INT UNSIGNED NOT NULL,
  `skill_name`     VARCHAR(150) NOT NULL,
  `skill_category` ENUM('required_technical','preferred_technical','required_soft')
                   NOT NULL DEFAULT 'required_technical',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programme_skills_prog_name_cat` (`programme_id`, `skill_name`, `skill_category`),
  KEY `idx_programme_skills_programme` (`programme_id`),
  KEY `idx_programme_skills_category` (`skill_category`),
  CONSTRAINT `fk_programme_skills_programme`
    FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
