-- ============================================================
-- Investhood IT — Password Reset Hardening (001)
-- ============================================================
-- * Ensures the `password_resets` table exists with the
--   expected shape (hashed tokens, 30-minute expiry handled
--   in application logic via PASSWORD_RESET_EXPIRY_MINUTES).
-- * Adds `password_reset_requests` for per-email / per-IP
--   rate limiting of the forgot-password endpoint.
--
-- Run: mysql -u root investhood_platform < 001_password_reset_hardening.sql
-- Or import via phpMyAdmin. Safe to run multiple times.
-- ============================================================

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `token_hash`  VARCHAR(64)  NOT NULL COMMENT 'SHA-256 of the reset token — never store raw tokens',
  `expires_at`  DATETIME     NOT NULL,
  `used_at`     DATETIME     NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_resets_token` (`token_hash`),
  KEY `idx_password_resets_user` (`user_id`),
  KEY `idx_password_resets_expiry` (`expires_at`),
  CONSTRAINT `fk_password_resets_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`       VARCHAR(100) NOT NULL,
  `ip_address`  VARCHAR(45)  NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pwreset_req_email` (`email`, `created_at`),
  KEY `idx_pwreset_req_ip` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
