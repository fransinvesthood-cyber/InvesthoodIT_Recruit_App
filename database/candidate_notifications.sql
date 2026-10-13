-- ============================================================
-- INVESTHOOD IT - Candidate Notifications (Unified Feed)
-- ============================================================
-- One candidate-facing notification feed for every module:
--   applications, programmes/opportunities, interviews,
--   selection & offers, placements and account/system events.
--
-- Design rules
--   * Re-uses the existing `users` table for the recipient
--     (users.id) — no duplicate candidate table.
--   * `dedupe_key` + the unique key on (user_id, dedupe_key)
--     make every INSERT IGNORE idempotent: retrying a request
--     or refreshing a page can never duplicate a notification.
--   * `module` + `record_id` drive the "view details" link back
--     to the originating record (application, interview, offer,
--     placement, opportunity or settings).
--
-- HOW TO APPLY (XAMPP / phpMyAdmin):
--   1. Open phpMyAdmin
--   2. Select the `investhood_platform` database
--   3. Import this file
--
-- NOTE: the model also applies this schema lazily through
-- CandidateNotification::ensureSchema(), so importing is optional
-- on a running development install (same pattern as
-- database/candidate_offers.sql for the offers module).
-- ============================================================

CREATE TABLE IF NOT EXISTS `candidate_notifications` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `category`    VARCHAR(30)  NOT NULL,
  `title`       VARCHAR(180) NOT NULL,
  `message`     TEXT         NOT NULL,
  `module`      VARCHAR(40)  DEFAULT NULL,
  `record_id`   INT UNSIGNED DEFAULT NULL,
  `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
  `read_at`     DATETIME     DEFAULT NULL,
  `dedupe_key`  VARCHAR(160) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_notification_event` (`user_id`, `dedupe_key`),
  KEY `idx_candidate_notifications_feed` (`user_id`, `is_read`, `created_at`),
  KEY `idx_candidate_notifications_category` (`user_id`, `category`, `created_at`),
  CONSTRAINT `fk_candidate_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
