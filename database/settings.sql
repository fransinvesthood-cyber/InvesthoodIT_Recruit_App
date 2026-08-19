-- ============================================================
-- INVESTHOOD IT - Candidate Settings Schema
-- Requires: users, consents, user_sessions, remember_me_tokens
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. USER SETTINGS (platform/display preferences)
--    Key/value per user so new settings can be added later
--    without schema changes.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE `user_settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `setting_key`   VARCHAR(60)  NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_settings_user_key` (`user_id`, `setting_key`),
  KEY `idx_user_settings_user` (`user_id`),
  CONSTRAINT `fk_user_settings_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. NOTIFICATION PREFERENCES
--    One row per user+category. A disabled row means the
--    candidate has opted out of that channel/category.
--    'enabled' => Generic channel (in-app). 'email_enabled'
--    separately governs email delivery.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `notification_preferences`;
CREATE TABLE `notification_preferences` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `category`      VARCHAR(50)  NOT NULL,
  `enabled`       TINYINT(1)   NOT NULL DEFAULT 1,
  `email_enabled` TINYINT(1)   NOT NULL DEFAULT 1,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_pref_user_cat` (`user_id`, `category`),
  KEY `idx_notif_pref_user` (`user_id`),
  CONSTRAINT `fk_notif_pref_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. ACCOUNT DELETION REQUESTS
--    Records a compliant deletion request. Actual erasure is
--    handled by the Information Officer subject to retention
--    requirements (backend responsible party). The candidate
--    receives a confirmation and the request is queued.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `deletion_requests`;
CREATE TABLE `deletion_requests` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `status`        ENUM('pending','processing','completed','cancelled')
                  NOT NULL DEFAULT 'pending',
  `reason`        VARCHAR(500) NULL,
  `requested_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at`  DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deletion_req_user` (`user_id`, `status`),
  CONSTRAINT `fk_deletion_req_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

