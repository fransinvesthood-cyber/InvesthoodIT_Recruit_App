-- Additive migration: Financial Commitments & Purchase Order Tracking
CREATE TABLE IF NOT EXISTS finance_commitments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 commitment_no VARCHAR(60) NOT NULL UNIQUE,
 purchase_order_no VARCHAR(100) NOT NULL UNIQUE,
 supplier_name VARCHAR(180) NOT NULL,
 supplier_reference VARCHAR(120) NULL,
 programme_id INT NULL, cohort_id INT NULL, budget_id INT NULL,
 department VARCHAR(150) NULL, description TEXT NOT NULL,
 order_date DATE NOT NULL, expected_delivery_date DATE NULL,
 currency CHAR(3) NOT NULL DEFAULT 'ZAR',
 expected_cost DECIMAL(15,2) NOT NULL,
 paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
 status ENUM('draft','submitted','approved','returned','rejected','closed','cancelled') NOT NULL DEFAULT 'draft',
 created_by INT NOT NULL, submitted_at DATETIME NULL,
 approved_by INT NULL, approved_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_fc_status(status), KEY idx_fc_programme(programme_id),
 KEY idx_fc_cohort(cohort_id), KEY idx_fc_budget(budget_id),
 KEY idx_fc_date(order_date), KEY idx_fc_supplier(supplier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_commitment_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 commitment_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(30) NOT NULL, acted_by INT NOT NULL,
 action_note TEXT NULL, amount DECIMAL(15,2) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_fch_commitment(commitment_id), KEY idx_fch_actor(acted_by),
 CONSTRAINT fk_fch_commitment FOREIGN KEY(commitment_id)
 REFERENCES finance_commitments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_commitment_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 commitment_id BIGINT UNSIGNED NOT NULL,
 payment_reference VARCHAR(120) NOT NULL UNIQUE,
 payment_date DATE NOT NULL, amount DECIMAL(15,2) NOT NULL,
 recorded_by INT NOT NULL, notes VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_fcp_commitment(commitment_id),
 CONSTRAINT fk_fcp_commitment FOREIGN KEY(commitment_id)
 REFERENCES finance_commitments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
