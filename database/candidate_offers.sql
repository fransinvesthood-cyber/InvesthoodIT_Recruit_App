-- ============================================================
-- INVESTHOOD IT - Candidate Offers Module (Stage 12)
-- ============================================================
-- Adds ONLY the structures the candidate-side Offers module
-- needs on top of the completed Administrator Selection & Offer
-- module (database/selection_offers.sql). It does NOT create a
-- second offer table and does NOT define a second set of offer
-- statuses:
--
--   offers                  (existing) - one row per issued offer.
--                             The candidate response RE-USES the
--                             existing columns:
--                               status       -> accepted / declined
--                               responded_at -> response date/time
--                             `decline_reason` is the only addition
--                             (requirement: store the decline reason).
--   offer_status_history    (existing) - every candidate response is
--                             appended to the same audit trail used by
--                             the Admin module.
--   offer_notifications     (new) - candidate-facing notifications for
--                             offer events, mirroring the existing
--                             placement_notifications layout
--                             (database/placements.sql) so the offers
--                             module follows the established
--                             notification architecture instead of
--                             inventing a new one.
--
-- The migration is ADDITIVE and IDEMPOTENT. Run it with
--   database/run_candidate_offers_migration.php
-- or simply open the candidate Offers pages: CandidateOffer::ensureSchema()
-- applies exactly the same changes lazily (the same pattern already used by
-- Interview::ensureFeedbackColumns()).
-- ============================================================

SET NAMES utf8mb4;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. OFFER NOTIFICATIONS
--    One row per offer event per candidate. The UNIQUE key makes
--    notification generation idempotent: a page refresh can never
--    duplicate a notification.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `offer_notifications` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offer_id`          INT UNSIGNED NOT NULL,
  `candidate_id`      INT UNSIGNED NOT NULL COMMENT 'Recipient candidate',
  `sender_id`         INT UNSIGNED NULL COMMENT 'Admin who triggered the notification (NULL for system events)',
  `notification_type` ENUM('offer_issued','offer_updated','offer_withdrawn',
                           'offer_deadline_approaching','offer_expired',
                           'offer_response_recorded')
                      NOT NULL,
  `title`             VARCHAR(200) NOT NULL,
  `message`           VARCHAR(500) NOT NULL,
  `is_read`           TINYINT(1)   NOT NULL DEFAULT 0,
  `read_at`           DATETIME     NULL DEFAULT NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offer_notification_event` (`offer_id`, `candidate_id`, `notification_type`),
  KEY `idx_offer_notifications_candidate` (`candidate_id`, `is_read`, `created_at`),
  KEY `idx_offer_notifications_offer` (`offer_id`),
  KEY `idx_offer_notifications_sender` (`sender_id`),
  CONSTRAINT `fk_offer_notifications_offer`
    FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_offer_notifications_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_offer_notifications_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. OFFERS: CANDIDATE DECLINE REASON
--    MySQL cannot add a column conditionally, so this statement is
--    executed only when the column is still missing. Both the migration
--    runner and CandidateOffer::ensureSchema() perform that check; the
--    runner skips this statement (instead of failing) on a re-run.
-- ------------------------------------------------------------
ALTER TABLE `offers`
  ADD COLUMN `decline_reason` VARCHAR(500) NULL DEFAULT NULL
  COMMENT 'Reason provided by the candidate when declining the offer'
  AFTER `responded_at`;
