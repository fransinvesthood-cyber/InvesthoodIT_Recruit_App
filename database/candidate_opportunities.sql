-- ============================================================
-- INVESTHOOD IT Candidate Opportunities - Migration
-- ============================================================
-- Adds the Candidate Opportunities module:
--   candidate_saved_opportunities
--
-- This migration is additive and does NOT modify existing tables.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. CANDIDATE SAVED OPPORTUNITIES
--    Allows candidates to save/bookmark opportunities they are interested in.
--    Prevents duplicate saves via unique constraint.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `candidate_saved_opportunities`;
CREATE TABLE `candidate_saved_opportunities` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `candidate_id`   INT UNSIGNED NOT NULL,
  `opportunity_id` INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved_opp_candidate_opportunity` (`candidate_id`, `opportunity_id`),
  KEY `idx_saved_opp_candidate` (`candidate_id`),
  KEY `idx_saved_opp_opportunity` (`opportunity_id`),
  KEY `idx_saved_opp_created` (`created_at`),
  CONSTRAINT `fk_saved_opp_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_saved_opp_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
