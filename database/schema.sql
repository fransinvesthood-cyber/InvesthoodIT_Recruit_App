-- ============================================================
-- INVESTHOOD IT Programme & Scarce Skills Platform
-- Database Schema (MySQL 8+)
-- ============================================================
-- Tables: roles, users, email_verifications, password_resets,
--         remember_me_tokens, login_attempts, user_sessions
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Create database (comment out if it already exists)
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `investhood_platform`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. ROLES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)  NOT NULL,
  `slug`        VARCHAR(50)  NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`),
  KEY `idx_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. USERS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`             INT UNSIGNED NOT NULL,
  `first_name`          VARCHAR(50)  NOT NULL,
  `last_name`           VARCHAR(50)  NOT NULL,
  `username`            VARCHAR(30)  NOT NULL,
  `email`               VARCHAR(100) NOT NULL,
  `phone`               VARCHAR(20)  NULL,
  `date_of_birth`       DATE         NULL,
  `gender`              VARCHAR(20)  NULL,
  `province`            VARCHAR(30)  NULL,
  `employment_status`   VARCHAR(30)  NULL,
  `qualification_level` VARCHAR(30)  NULL,
  `professional_title`  VARCHAR(100) NULL,
  `password_hash`       VARCHAR(255) NOT NULL,
  `profile_picture`     VARCHAR(255) NULL,
  `status`              ENUM('pending','active','suspended','disabled')
                        NOT NULL DEFAULT 'pending',
  `email_verified_at`   DATETIME     NULL,
  `last_login`          DATETIME     NULL,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_email_status` (`email`, `status`),
  CONSTRAINT `fk_users_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. EMAIL VERIFICATIONS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `email_verifications`;
CREATE TABLE `email_verifications` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `token_hash`  VARCHAR(64)  NOT NULL,
  `expires_at`  DATETIME     NOT NULL,
  `used_at`     DATETIME     NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_verifications_token` (`token_hash`),
  KEY `idx_email_verifications_user` (`user_id`),
  KEY `idx_email_verifications_expiry` (`expires_at`),
  CONSTRAINT `fk_email_verifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. PASSWORD RESETS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `token_hash`  VARCHAR(64)  NOT NULL,
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

-- ------------------------------------------------------------
-- 5. REMEMBER ME TOKENS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `remember_me_tokens`;
CREATE TABLE `remember_me_tokens` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `selector`       VARCHAR(64)  NOT NULL,
  `validator_hash` VARCHAR(64)  NOT NULL,
  `expires_at`     DATETIME     NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remember_me_selector` (`selector`),
  KEY `idx_remember_me_user` (`user_id`),
  KEY `idx_remember_me_expiry` (`expires_at`),
  CONSTRAINT `fk_remember_me_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. LOGIN ATTEMPTS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `username`     VARCHAR(100) NULL,
  `successful`   TINYINT(1)   NOT NULL DEFAULT 0,
  `attempted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip` (`ip_address`, `successful`, `attempted_at`),
  KEY `idx_login_attempts_user` (`user_id`),
  KEY `idx_login_attempts_time` (`attempted_at`),
  CONSTRAINT `fk_login_attempts_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. USER SESSIONS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `session_hash`  VARCHAR(64)  NOT NULL,
  `ip_address`    VARCHAR(45)  NOT NULL,
  `user_agent`    VARCHAR(255) NULL,
  `login_time`    DATETIME     NOT NULL,
  `last_activity` DATETIME     NOT NULL,
  `logout_time`   DATETIME     NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sessions_hash` (`session_hash`),
  KEY `idx_user_sessions_user` (`user_id`, `is_active`),
  KEY `idx_user_sessions_activity` (`last_activity`),
  CONSTRAINT `fk_user_sessions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Seed data for roles
-- ============================================================
INSERT INTO `roles` (`name`, `slug`, `description`) VALUES
('Administrator', 'admin', 'Full platform administration and configuration access.'),
('Programme Manager', 'programme_manager', 'Manages programme delivery, cohorts, and performance.'),
('Programme Officer', 'programme_officer', 'Coordinates programme operations and candidate support.'),
('Recruiter', 'recruiter', 'Manages opportunities, talent sourcing, and placements.'),
('Supervisor', 'supervisor', 'Supervises candidates during work-integrated learning and internships.'),
('Assessor', 'assessor', 'Conducts candidate assessments and skills verification.'),
('Finance Officer', 'finance_officer', 'Manages financial records, stipends, and invoicing.'),
('Information Officer', 'information_officer', 'Manages privacy, compliance, and access to information.'),
('Candidate', 'candidate', 'Platform users seeking programmes, internships, and opportunities.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

