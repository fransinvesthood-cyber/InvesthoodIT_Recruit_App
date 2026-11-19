-- Sponsorship Agreement & Deliverables Tracking
-- MySQL 8 / MariaDB compatible. Run after the existing Sponsor Portal migrations.

CREATE TABLE IF NOT EXISTS sponsor_agreements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agreement_ref VARCHAR(80) NOT NULL,
  title VARCHAR(200) NOT NULL,
  summary TEXT NULL,
  agreement_value DECIMAL(14,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  status ENUM('draft','active','under_review','completed','terminated','expired') NOT NULL DEFAULT 'draft',
  signed_on DATE NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_sponsor_agreement_ref (agreement_ref),
  KEY idx_sponsor_agreement_status_dates (status,start_date,end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_agreement_programmes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agreement_id BIGINT UNSIGNED NOT NULL,
  programme_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_sponsor_agreement_programme (agreement_id,programme_id),
  KEY idx_sap_programme (programme_id), KEY idx_sap_agreement (agreement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_agreement_deliverables (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agreement_id BIGINT UNSIGNED NOT NULL,
  deliverable_ref VARCHAR(80) NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'other',
  due_date DATE NULL,
  status ENUM('not_started','in_progress','submitted','fulfilled','overdue','waived','cancelled') NOT NULL DEFAULT 'not_started',
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  verification_status ENUM('not_submitted','pending','verified','rejected','not_required') NOT NULL DEFAULT 'not_submitted',
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  evidence_reference VARCHAR(500) NULL,
  management_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_sad_agreement_due (agreement_id,due_date),
  KEY idx_sad_status_verification (status,verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_agreement_deliverable_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  deliverable_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  old_progress TINYINT UNSIGNED NULL,
  new_progress TINYINT UNSIGNED NULL,
  verification_status VARCHAR(40) NULL,
  change_note TEXT NULL,
  changed_by BIGINT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_sadh_deliverable_date (deliverable_id,changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Programme assignment and sponsor visibility are deliberately linked through
-- sponsor_agreement_programmes + the existing sponsor_programme_access table.
-- Do not expose drafts to sponsors. Only authorised management users should
-- create agreements, update deliverables, attach evidence, and verify completion.
