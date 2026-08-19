-- ============================================================
-- INVESTHOOD IT Programme & Scarce Skills Platform
-- Candidate Profile Management - Migration
-- ============================================================
-- Adds tables for the Candidate Profile module:
--   availability_statuses, candidate_profiles, qualifications,
--   skills, candidate_skills, work_experience, documents,
--   consents, audit_logs
--
-- This migration is additive and does NOT modify the existing
-- authentication schema (roles, users, sessions, tokens).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. AVAILABILITY STATUSES
--    Canonical availability states stored in DB (not hard-coded)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `availability_statuses`;
CREATE TABLE `availability_statuses` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(50)  NOT NULL,
  `label`       VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_availability_statuses_slug` (`slug`),
  KEY `idx_availability_statuses_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. CANDIDATE PROFILES
--    One active master profile per candidate
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `candidate_profiles`;
CREATE TABLE `candidate_profiles` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`               INT UNSIGNED NOT NULL,
  `professional_title`    VARCHAR(100) NULL,
  `professional_summary`  TEXT         NULL,
  `career_interests`      TEXT         NULL,
  `employment_status`     VARCHAR(30)  NULL,
  `availability_status_id` INT UNSIGNED NULL,
  `availability_date`     DATE         NULL,
  `address`               VARCHAR(255) NULL,
  `city`                  VARCHAR(100) NULL,
  `profile_picture`       VARCHAR(255) NULL,
  `completion_percent`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_profiles_user` (`user_id`),
  KEY `idx_candidate_profiles_availability` (`availability_status_id`),
  KEY `idx_candidate_profiles_active` (`is_active`),
  CONSTRAINT `fk_candidate_profiles_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_candidate_profiles_availability`
    FOREIGN KEY (`availability_status_id`) REFERENCES `availability_statuses` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. QUALIFICATIONS
--    Multiple qualifications per candidate
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `qualifications`;
CREATE TABLE `qualifications` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `name`           VARCHAR(150) NOT NULL,
  `institution`    VARCHAR(150) NULL,
  `year_completed` YEAR         NULL,
  `level`          VARCHAR(50)  NULL,
  `verification_status` ENUM('unverified','pending','verified','failed') NOT NULL DEFAULT 'unverified',
  `verified_at`    DATETIME     NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qualifications_user` (`user_id`),
  KEY `idx_qualifications_verification` (`verification_status`),
  CONSTRAINT `fk_qualifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. SKILLS (canonical)
--    Searchable, categorisable, versionable skill catalogue
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `skills`;
CREATE TABLE `skills` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `category`    ENUM('technical','soft') NOT NULL DEFAULT 'technical',
  `description` VARCHAR(255) NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_skills_name` (`name`),
  KEY `idx_skills_category` (`category`),
  KEY `idx_skills_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. CANDIDATE SKILLS (pivot)
--    Prevents duplicate skills per candidate
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `candidate_skills`;
CREATE TABLE `candidate_skills` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `skill_id`       INT UNSIGNED NOT NULL,
  `proficiency`    ENUM('beginner','intermediate','advanced','expert') NOT NULL DEFAULT 'intermediate',
  `verification_status` ENUM('unverified','pending','verified','failed') NOT NULL DEFAULT 'unverified',
  `verified_at`    DATETIME     NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_skills_user_skill` (`user_id`, `skill_id`),
  KEY `idx_candidate_skills_skill` (`skill_id`),
  KEY `idx_candidate_skills_verification` (`verification_status`),
  CONSTRAINT `fk_candidate_skills_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_candidate_skills_skill`
    FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. WORK EXPERIENCE
--    Multiple employment records per candidate
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `work_experience`;
CREATE TABLE `work_experience` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `job_title`     VARCHAR(150) NOT NULL,
  `company`       VARCHAR(150) NOT NULL,
  `start_date`    DATE         NOT NULL,
  `end_date`      DATE         NULL,
  `is_current`    TINYINT(1)   NOT NULL DEFAULT 0,
  `description`   TEXT         NULL,
  `sort_order`    INT          NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_work_experience_user` (`user_id`),
  CONSTRAINT `fk_work_experience_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. DOCUMENTS
--    Metadata only; physical files stored securely & served
--    through an authenticated download endpoint.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NOT NULL,
  `document_type`     ENUM('cv','qualification','supporting') NOT NULL DEFAULT 'supporting',
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename`   VARCHAR(255) NOT NULL,
  `mime_type`         VARCHAR(100) NOT NULL,
  `file_size`         INT UNSIGNED NOT NULL DEFAULT 0,
  `file_checksum`     VARCHAR(64)  NOT NULL,
  `uploaded_by`       INT UNSIGNED NOT NULL,
  `verification_status` ENUM('unverified','pending','verified','failed') NOT NULL DEFAULT 'unverified',
  `expiry_date`       DATE         NULL,
  `uploaded_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_documents_user` (`user_id`),
  KEY `idx_documents_type` (`document_type`),
  KEY `idx_documents_verification` (`verification_status`),
  CONSTRAINT `fk_documents_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_documents_uploader`
    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. CONSENTS
--    Privacy & consent management. Future-opportunity consent
--    is tracked separately from general programme consent.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `consents`;
CREATE TABLE `consents` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `purpose`        VARCHAR(50)  NOT NULL,
  `status`         ENUM('granted','withdrawn') NOT NULL DEFAULT 'granted',
  `granted_at`     DATETIME     NULL,
  `withdrawn_at`   DATETIME     NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_consents_user_purpose` (`user_id`, `purpose`),
  KEY `idx_consents_status` (`status`),
  KEY `idx_consents_purpose` (`purpose`),
  CONSTRAINT `fk_consents_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. AUDIT LOGS
--    Material profile changes (no sensitive data)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `action`         VARCHAR(100) NOT NULL,
  `record_type`    VARCHAR(50)  NULL,
  `record_id`      INT UNSIGNED NULL,
  `reason`         VARCHAR(255) NULL,
  `ip_address`     VARCHAR(45)  NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_record` (`record_type`, `record_id`),
  KEY `idx_audit_logs_time` (`created_at`),
  CONSTRAINT `fk_audit_logs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Seed data
-- ============================================================

-- Availability statuses
INSERT INTO `availability_statuses` (`slug`, `label`, `description`, `sort_order`) VALUES
('available_now',      'Available Now',      'Ready to start immediately', 1),
('available_from_date','Available From Date','Available to start from a specific date', 2),
('employed_open',      'Employed / Open',    'Currently employed but open to opportunities', 3),
('unavailable',        'Unavailable',        'Not currently available for opportunities', 4),
('do_not_contact',     'Do Not Contact',     'Do not contact for opportunities', 5),
('unknown_stale',      'Unknown / Stale',    'Availability status is unknown or outdated', 6)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- Canonical skills (technical)
INSERT INTO `skills` (`name`, `category`, `description`) VALUES
('PHP', 'technical', 'Server-side scripting language'),
('JavaScript', 'technical', 'Client-side scripting language'),
('MySQL', 'technical', 'Relational database management system'),
('Python', 'technical', 'General-purpose programming language'),
('Java', 'technical', 'Object-oriented programming language'),
('C#', 'technical', 'Object-oriented programming language'),
('C++', 'technical', 'General-purpose programming language'),
('TypeScript', 'technical', 'Typed superset of JavaScript'),
('Node.js', 'technical', 'JavaScript runtime environment'),
('React', 'technical', 'JavaScript library for building UIs'),
('Angular', 'technical', 'TypeScript-based web application framework'),
('Vue.js', 'technical', 'Progressive JavaScript framework'),
('HTML', 'technical', 'Markup language for web pages'),
('CSS', 'technical', 'Styling language for web pages'),
('SQL', 'technical', 'Structured query language'),
('Cloud Computing', 'technical', 'Delivery of computing services over the internet'),
('AWS', 'technical', 'Amazon Web Services cloud platform'),
('Azure', 'technical', 'Microsoft cloud platform'),
('Docker', 'technical', 'Containerisation platform'),
('Kubernetes', 'technical', 'Container orchestration platform'),
('Git', 'technical', 'Version control system'),
('Linux', 'technical', 'Open-source operating system'),
('Networking', 'technical', 'Computer networking fundamentals'),
('Cybersecurity', 'technical', 'Protection of computer systems from threats'),
('Data Analysis', 'technical', 'Inspection and interpretation of data'),
('Machine Learning', 'technical', 'Algorithms that learn from data'),
('Mobile Development', 'technical', 'Building applications for mobile devices'),
('Android', 'technical', 'Android app development'),
('iOS', 'technical', 'iOS app development'),
('REST APIs', 'technical', 'Design and consumption of RESTful APIs'),
('Database Design', 'technical', 'Designing relational database schemas'),
('DevOps', 'technical', 'Practices combining development and operations'),
('Testing / QA', 'technical', 'Software testing and quality assurance'),
('Project Management', 'technical', 'Planning and managing projects'),
('Figma', 'technical', 'UI/UX design tool'),
('Power BI', 'technical', 'Business intelligence tool'),
('Excel', 'technical', 'Spreadsheet application')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Canonical skills (soft)
INSERT INTO `skills` (`name`, `category`, `description`) VALUES
('Communication', 'soft', 'Clear and effective verbal and written communication'),
('Teamwork', 'soft', 'Working effectively within a team'),
('Problem Solving', 'soft', 'Analysing and resolving problems'),
('Leadership', 'soft', 'Guiding and motivating others'),
('Time Management', 'soft', 'Managing time effectively'),
('Adaptability', 'soft', 'Adjusting to new conditions'),
('Critical Thinking', 'soft', 'Objectively analysing and evaluating issues'),
('Creativity', 'soft', 'Generating innovative ideas'),
('Collaboration', 'soft', 'Working jointly with others'),
('Attention to Detail', 'soft', 'Thoroughness and accuracy'),
('Emotional Intelligence', 'soft', 'Understanding and managing emotions'),
('Conflict Resolution', 'soft', 'Resolving disagreements constructively'),
('Public Speaking', 'soft', 'Speaking confidently to audiences'),
('Customer Service', 'soft', 'Serving and supporting customers'),
('Decision Making', 'soft', 'Making effective choices'),
('Negotiation', 'soft', 'Reaching mutual agreements')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
