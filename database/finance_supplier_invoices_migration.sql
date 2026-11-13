-- Investhood IT: Supplier Invoice Management & Accounts Payable
-- Additive migration. Back up the database before importing.
CREATE TABLE IF NOT EXISTS finance_supplier_invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_ref VARCHAR(60) NOT NULL,
  supplier_name VARCHAR(180) NOT NULL,
  supplier_tax_number VARCHAR(80) DEFAULT NULL,
  supplier_invoice_no VARCHAR(120) NOT NULL,
  purchase_order_no VARCHAR(100) DEFAULT NULL,
  commitment_id BIGINT UNSIGNED DEFAULT NULL,
  programme_id INT DEFAULT NULL,
  cohort_id INT DEFAULT NULL,
  department VARCHAR(150) DEFAULT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(15,2) NOT NULL,
  paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  description TEXT NOT NULL,
  attachment_path VARCHAR(500) DEFAULT NULL,
  status ENUM('draft','submitted','verified','approved','returned','rejected','partially_paid','paid','cancelled') NOT NULL DEFAULT 'draft',
  created_by INT NOT NULL,
  submitted_at DATETIME DEFAULT NULL,
  verified_by INT DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  approved_by INT DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fsi_invoice_supplier (supplier_name, supplier_invoice_no),
  UNIQUE KEY uq_fsi_invoice_ref (invoice_ref),
  KEY idx_fsi_status (status),
  KEY idx_fsi_due_date (due_date),
  KEY idx_fsi_supplier (supplier_name),
  KEY idx_fsi_programme (programme_id),
  KEY idx_fsi_commitment (commitment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_supplier_invoice_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  acted_by INT NOT NULL,
  action_note TEXT DEFAULT NULL,
  amount DECIMAL(15,2) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fsih_invoice (invoice_id),
  KEY idx_fsih_actor (acted_by),
  CONSTRAINT fk_fsih_invoice FOREIGN KEY (invoice_id)
    REFERENCES finance_supplier_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_supplier_invoice_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  payment_reference VARCHAR(120) NOT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  recorded_by INT NOT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fsip_payment_reference (payment_reference),
  KEY idx_fsip_invoice (invoice_id),
  KEY idx_fsip_payment_date (payment_date),
  CONSTRAINT fk_fsip_invoice FOREIGN KEY (invoice_id)
    REFERENCES finance_supplier_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
