-- Investhood IT | Sponsorship Performance & Impact Monitoring
-- Import into the same database used by the application.
-- Access is explicitly assigned per sponsor user and programme. No sponsor sees
-- programmes or financial/impact records until a link is inserted below.

CREATE TABLE IF NOT EXISTS sponsor_programme_access (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sponsor_user_id INT UNSIGNED NOT NULL,
  programme_id INT UNSIGNED NOT NULL,
  access_status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  assigned_by INT UNSIGNED DEFAULT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sponsor_programme (sponsor_user_id, programme_id),
  KEY idx_spa_programme (programme_id),
  CONSTRAINT fk_spa_sponsor FOREIGN KEY (sponsor_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_spa_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_spa_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_contributions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sponsor_user_id INT UNSIGNED NOT NULL,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED DEFAULT NULL,
  reference_no VARCHAR(100) NOT NULL,
  contribution_type ENUM('cash','in_kind') NOT NULL DEFAULT 'cash',
  contribution_status ENUM('pledged','received','allocated','cancelled') NOT NULL DEFAULT 'pledged',
  contribution_date DATE DEFAULT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  description VARCHAR(255) DEFAULT NULL,
  recorded_by INT UNSIGNED DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sponsor_contribution_reference (sponsor_user_id, reference_no),
  KEY idx_sc_sponsor_programme (sponsor_user_id, programme_id),
  KEY idx_sc_status_date (contribution_status, contribution_date),
  CONSTRAINT fk_sc_sponsor FOREIGN KEY (sponsor_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sc_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_sc_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_sc_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_impact_indicators (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sponsor_user_id INT UNSIGNED NOT NULL,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED DEFAULT NULL,
  indicator_category ENUM('reach','learning','completion','employment','equity','other') NOT NULL DEFAULT 'other',
  indicator_name VARCHAR(180) NOT NULL,
  baseline_value DECIMAL(14,2) DEFAULT NULL,
  target_value DECIMAL(14,2) DEFAULT NULL,
  actual_value DECIMAL(14,2) DEFAULT NULL,
  unit VARCHAR(40) NOT NULL DEFAULT 'people',
  reporting_period_start DATE DEFAULT NULL,
  reporting_period_end DATE DEFAULT NULL,
  evidence_reference VARCHAR(255) DEFAULT NULL,
  verification_status ENUM('draft','pending','verified','rejected') NOT NULL DEFAULT 'pending',
  notes TEXT DEFAULT NULL,
  recorded_by INT UNSIGNED DEFAULT NULL,
  verified_by INT UNSIGNED DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sii_scope (sponsor_user_id, programme_id, verification_status),
  KEY idx_sii_period (reporting_period_start, reporting_period_end),
  CONSTRAINT fk_sii_sponsor FOREIGN KEY (sponsor_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sii_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_sii_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_sii_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_sii_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- After creating/authorising a sponsor account, grant access to specific programmes.
-- Example (replace IDs after checking users and programmes):
-- INSERT INTO sponsor_programme_access (sponsor_user_id, programme_id, assigned_by)
-- VALUES (20, 1, 1), (20, 3, 1);
-- Record funding contributions in sponsor_contributions. Use 'received' only after
-- finance has confirmed receipt. Use 'pledged' for commitments not yet received.
-- Record impact measures in sponsor_impact_indicators; only 'verified' measures
-- are included in verified headline impact totals.
