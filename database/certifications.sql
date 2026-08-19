-- ============================================================
-- INVESTHOOD IT Programme & Scarce Skills Platform
-- Candidate Certifications - Migration
-- ============================================================
-- Adds a dedicated certifications table for candidates to record
-- professional certifications, licences and credentials obtained.
-- This is additive and does NOT modify the existing schema.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- CERTIFICATIONS
--    Multiple certifications per candidate
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `certifications`;
CREATE TABLE `certifications` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`              INT UNSIGNED NOT NULL,
  `name`                 VARCHAR(150) NOT NULL,
  `issuing_organisation` VARCHAR(150) NULL,
  `year_obtained`        YEAR         NULL,
  `expiry_date`          DATE         NULL,
  `credential_id`        VARCHAR(100) NULL,
  `verification_status`  ENUM('unverified','pending','verified','failed') NOT NULL DEFAULT 'unverified',
  `verified_at`          DATETIME     NULL,
  `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_certifications_user` (`user_id`),
  KEY `idx_certifications_verification` (`verification_status`),
  CONSTRAINT `fk_certifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
