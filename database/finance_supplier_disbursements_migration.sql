-- Investhood IT: Supplier Payment Processing & Disbursement Management
-- Additive migration. Back up the database before importing.
-- Requires finance_supplier_invoices and finance_supplier_invoice_payments.

CREATE TABLE IF NOT EXISTS finance_supplier_disbursements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_ref VARCHAR(60) NOT NULL,
  batch_label VARCHAR(180) NOT NULL,
  planned_payment_date DATE NOT NULL,
  total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('draft','submitted','authorised','processing','completed','partially_completed','failed','cancelled') NOT NULL DEFAULT 'draft',
  created_by INT NOT NULL,
  submitted_by INT DEFAULT NULL,
  submitted_at DATETIME DEFAULT NULL,
  authorised_by INT DEFAULT NULL,
  authorised_at DATETIME DEFAULT NULL,
  cancelled_by INT DEFAULT NULL,
  cancelled_at DATETIME DEFAULT NULL,
  cancellation_reason TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fsd_batch_ref (batch_ref),
  KEY idx_fsd_status_date (status, planned_payment_date),
  KEY idx_fsd_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_supplier_disbursement_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  supplier_name VARCHAR(180) NOT NULL,
  supplier_invoice_no VARCHAR(120) NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  payment_reference VARCHAR(120) NOT NULL,
  bank_reference VARCHAR(150) DEFAULT NULL,
  paid_date DATE DEFAULT NULL,
  status ENUM('queued','processing','completed','failed','removed','cancelled') NOT NULL DEFAULT 'queued',
  created_by INT NOT NULL,
  recorded_by INT DEFAULT NULL,
  recorded_at DATETIME DEFAULT NULL,
  outcome_note VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fsdi_payment_ref (payment_reference),
  UNIQUE KEY uq_fsdi_batch_invoice (batch_id, invoice_id),
  KEY idx_fsdi_invoice_status (invoice_id, status),
  KEY idx_fsdi_batch_status (batch_id, status),
  CONSTRAINT fk_fsdi_batch FOREIGN KEY (batch_id)
    REFERENCES finance_supplier_disbursements(id) ON DELETE CASCADE,
  CONSTRAINT fk_fsdi_invoice FOREIGN KEY (invoice_id)
    REFERENCES finance_supplier_invoices(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_supplier_disbursement_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED DEFAULT NULL,
  acted_by INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  action_note TEXT DEFAULT NULL,
  amount DECIMAL(15,2) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fsdh_batch (batch_id),
  KEY idx_fsdh_item (item_id),
  CONSTRAINT fk_fsdh_batch FOREIGN KEY (batch_id)
    REFERENCES finance_supplier_disbursements(id) ON DELETE CASCADE,
  CONSTRAINT fk_fsdh_item FOREIGN KEY (item_id)
    REFERENCES finance_supplier_disbursement_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
