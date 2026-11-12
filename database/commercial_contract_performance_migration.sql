-- Commercial Manager: Contract & Commercial Performance Monitoring
-- Additive migration. Back up the database before importing.

CREATE TABLE IF NOT EXISTS commercial_contracts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  commercial_manager_id INT NOT NULL,
  contract_ref VARCHAR(40) NOT NULL,
  client_name VARCHAR(180) NOT NULL,
  contract_title VARCHAR(180) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  renewal_date DATE DEFAULT NULL,
  contract_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  status ENUM('draft','active','under_review','completed','expired','terminated') NOT NULL DEFAULT 'draft',
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  description TEXT DEFAULT NULL,
  risk_notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_contract_ref (contract_ref),
  KEY idx_cc_manager_status (commercial_manager_id,status),
  KEY idx_cc_renewal (renewal_date),
  KEY idx_cc_end_date (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_contract_milestones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id INT UNSIGNED NOT NULL,
  milestone_title VARCHAR(180) NOT NULL,
  deliverable TEXT DEFAULT NULL,
  responsible_party VARCHAR(160) DEFAULT NULL,
  due_date DATE NOT NULL,
  completed_date DATE DEFAULT NULL,
  weight_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending','in_progress','completed','at_risk','cancelled') NOT NULL DEFAULT 'pending',
  completion_note TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ccm_contract_due (contract_id,due_date),
  KEY idx_ccm_status_due (status,due_date),
  CONSTRAINT fk_ccm_contract FOREIGN KEY (contract_id)
    REFERENCES commercial_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_contract_activity (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id INT UNSIGNED NOT NULL,
  commercial_manager_id INT NOT NULL,
  action VARCHAR(60) NOT NULL,
  details TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cca_contract_date (contract_id,created_at),
  KEY idx_cca_manager_date (commercial_manager_id,created_at),
  CONSTRAINT fk_cca_contract FOREIGN KEY (contract_id)
    REFERENCES commercial_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
