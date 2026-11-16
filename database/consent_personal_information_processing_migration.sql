-- Consent & Personal Information Processing Management
-- Additive migration for the existing Information Officer governance module.
-- Back up the database before import. This does not alter candidate consent choices.

CREATE TABLE IF NOT EXISTS io_consent_purpose_registry (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 consent_type VARCHAR(80) NOT NULL,
 purpose_name VARCHAR(180) NOT NULL,
 purpose_description TEXT NOT NULL,
 notice_version VARCHAR(40) NOT NULL,
 recipients TEXT NULL,
 retention_rule VARCHAR(500) NULL,
 is_required TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_io_consent_purpose_type(consent_type),
 KEY idx_io_consent_purpose_active(is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_consent_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 candidate_consent_id BIGINT UNSIGNED NOT NULL,
 review_outcome ENUM('reviewed','exception','follow_up_required','escalated') NOT NULL DEFAULT 'reviewed',
 review_notes TEXT NOT NULL,
 reviewed_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_io_consent_review_record(candidate_consent_id),
 KEY idx_io_consent_review_outcome(review_outcome,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_processing_controls (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NOT NULL,
 collection_method TEXT NOT NULL,
 use_description TEXT NOT NULL,
 sharing_description TEXT NOT NULL,
 permission_scope TEXT NOT NULL,
 retention_rule TEXT NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_io_processing_control_activity(activity_id),
 KEY idx_io_processing_control_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- No candidate_consents rows are inserted, edited, revoked or deleted by this migration.
