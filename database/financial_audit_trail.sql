-- Investhood IT: Financial Audit Trail & Transaction History
-- Import into the investhood_platform database.
CREATE TABLE IF NOT EXISTS finance_audit_trail (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NULL,
  action VARCHAR(120) NOT NULL,
  description VARCHAR(1000) NOT NULL,
  entity_type VARCHAR(80) DEFAULT NULL,
  entity_id BIGINT DEFAULT NULL,
  amount DECIMAL(15,2) DEFAULT NULL,
  reference_no VARCHAR(150) DEFAULT NULL,
  before_data LONGTEXT DEFAULT NULL,
  after_data LONGTEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fin_audit_created (created_at),
  KEY idx_fin_audit_user (user_id),
  KEY idx_fin_audit_entity (entity_type, entity_id),
  KEY idx_fin_audit_action (action),
  KEY idx_fin_audit_reference (reference_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
