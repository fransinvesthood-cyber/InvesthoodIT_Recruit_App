-- ============================================================
-- INVESTHOOD IT - Candidate Applications (Stage 1: My Applications)
-- ============================================================
-- Adds the `applications` table for the candidate's My Applications
-- module. This migration is additive and does NOT modify existing
-- authentication, candidate profile, programme or opportunity schema.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. APPLICATIONS
--    Links one candidate to one opportunity.
--    A candidate may apply once per opportunity.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_reference` VARCHAR(20)  NOT NULL,
  `candidate_id`         INT UNSIGNED NOT NULL,
  `opportunity_id`         INT UNSIGNED NOT NULL,
  `status`              ENUM('draft','submitted','eligibility_review','screened','assessment','interview','waitlisted','selected','rejected','withdrawn','expired')
                                  NOT NULL DEFAULT 'draft',
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at`        DATETIME      NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_applications_candidate_opportunity` (`candidate_id`,`opportunity_id`),
  KEY `idx_applications_candidate` (`candidate_id`, `status`),
  KEY `idx_applications_opportunity` (`opportunity_id`),
  KEY `idx_applications_status` (`status`),
  KEY `idx_applications_created` (`created_at`),
  CONSTRAINT `fk_applications_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_applications_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;