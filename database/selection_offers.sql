-- ============================================================
-- INVESTHOOD IT - Selection & Offers (Stage 11)
-- ============================================================
-- Adds the minimal structures required for the Administrator
-- Selection & Offer management module:
--
--   selection_decisions   - one documented selection decision
--                           record per application (Selected /
--                           Waitlisted / Not Selected) with the
--                           ADMIN-ONLY internal selection note.
--   offers                - offer records for selected candidates
--                           (draft/issued/accepted/declined/
--                           expired/withdrawn).
--   offer_status_history  - audit trail of offer status changes,
--                           mirroring the canonical
--                           application_status_history layout.
--
-- This migration is ADDITIVE and idempotent (CREATE TABLE IF NOT
-- EXISTS, never drops existing data). It does NOT create a second
-- application-status system: selection outcomes are written to the
-- existing `applications.status` ENUM pipeline
--   selected / on_hold (Waitlisted) / rejected
-- and every change is recorded through the existing
-- `application_status_history` audit table by the Selection model.
--
-- Import in phpMyAdmin BEFORE using the Selection & Offers module.
-- ============================================================

SET NAMES utf8mb4;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. SELECTION DECISIONS
--    One row per application (UNIQUE application_id).
--    The full decision audit trail lives in
--    application_status_history; this table holds the current
--    documented decision and the internal admin-only note.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `selection_decisions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `candidate_id`   INT UNSIGNED NOT NULL,
  `opportunity_id` INT UNSIGNED NOT NULL,
  `decision`       ENUM('selected','not_selected','waitlisted') NOT NULL,
  `decided_by`     INT UNSIGNED NULL COMMENT 'Admin user who recorded the decision',
  `decision_note`  TEXT         NULL COMMENT 'Internal selection note - ADMIN ONLY, never shown to candidates',
  `decided_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_selection_decision_application` (`application_id`),
  KEY `idx_selection_decision_candidate` (`candidate_id`),
  KEY `idx_selection_decision_decision` (`decision`),
  KEY `idx_selection_decision_decided_by` (`decided_by`),
  CONSTRAINT `fk_selection_decision_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_selection_decision_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_selection_decision_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_selection_decision_decided_by`
    FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ------------------------------------------------------------
-- 2. OFFERS
--    An offer is always linked to a selected application (and
--    therefore to its candidate, opportunity, programme and
--    cohort). Offer status is a SEPARATE concept from the
--    application status (applications.status keeps its own
--    selected/offer_sent/offer_accepted/offer_declined values).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `offers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `candidate_id`   INT UNSIGNED NOT NULL,
  `opportunity_id` INT UNSIGNED NOT NULL,
  `programme_id`   INT UNSIGNED NOT NULL,
  `cohort_id`      INT UNSIGNED NULL DEFAULT NULL,
  `title`          VARCHAR(150) NOT NULL COMMENT 'Offer title',
  `position`       VARCHAR(150) NOT NULL COMMENT 'Offered position',
  `start_date`     DATE         NULL DEFAULT NULL,
  `end_date`       DATE         NULL DEFAULT NULL,
  `location`       VARCHAR(255) NULL DEFAULT NULL,
  `compensation`   VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stipend/salary where applicable',
  `expiry_date`    DATE         NOT NULL COMMENT 'Offer expiry date',
  `terms`          TEXT         NULL DEFAULT NULL COMMENT 'Additional terms / information',
  `status`         ENUM('draft','issued','accepted','declined','expired','withdrawn')
                                NOT NULL DEFAULT 'draft',
  `issued_by`      INT UNSIGNED NULL COMMENT 'Admin who issued the offer',
  `issued_at`      DATETIME     NULL DEFAULT NULL,
  `responded_at`   DATETIME     NULL DEFAULT NULL COMMENT 'Accept/decline timestamp (future candidate module)',
  `created_by`     INT UNSIGNED NULL COMMENT 'Admin who created the offer',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_offers_application` (`application_id`, `status`),
  KEY `idx_offers_candidate` (`candidate_id`),
  KEY `idx_offers_status` (`status`),
  KEY `idx_offers_programme` (`programme_id`),
  KEY `idx_offers_cohort` (`cohort_id`),
  KEY `idx_offers_expiry` (`expiry_date`),
  CONSTRAINT `fk_offers_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_offers_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_offers_opportunity`
    FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_offers_programme`
    FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_offers_cohort`
    FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_offers_issued_by`
    FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_offers_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. OFFER STATUS HISTORY
--    Audit trail of offer status changes and issued-offer
--    modifications. Mirrors the canonical
--    application_status_history layout (Stage 9).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `offer_status_history` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offer_id`        INT UNSIGNED NOT NULL,
  `previous_status` VARCHAR(50)  NULL COMMENT 'NULL for the initial status record',
  `new_status`      VARCHAR(50)  NOT NULL,
  `changed_by`      INT UNSIGNED NULL COMMENT 'Admin user who made the change (NULL for system)',
  `change_reason`   VARCHAR(500) NULL COMMENT 'Optional reason for the change',
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_offer_status_history_offer` (`offer_id`, `created_at`),
  KEY `idx_offer_status_history_new_status` (`new_status`),
  KEY `idx_offer_status_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_offer_status_history_offer`
    FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_offer_status_history_changed_by`
    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;