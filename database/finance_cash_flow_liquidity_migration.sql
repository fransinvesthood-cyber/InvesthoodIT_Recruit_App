-- Cash Flow Monitoring & Liquidity Management
-- Additive migration. Back up the database before importing.
-- Opening balances are captured once per account; actual movements update the live cash position.
CREATE TABLE IF NOT EXISTS finance_cash_accounts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 account_name VARCHAR(150) NOT NULL,
 institution VARCHAR(150) NULL,
 account_reference VARCHAR(80) NULL COMMENT 'Store masked reference only; never store online banking credentials',
 opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
 opening_date DATE NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'ZAR',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_by INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_fca_active(is_active),
 KEY idx_fca_created_by(created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_cash_movements (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 movement_type ENUM('inflow','outflow') NOT NULL,
 movement_status ENUM('forecast','actual','cancelled') NOT NULL DEFAULT 'forecast',
 category VARCHAR(120) NOT NULL,
 description VARCHAR(500) NOT NULL,
 reference_no VARCHAR(120) NULL,
 amount DECIMAL(15,2) NOT NULL,
 movement_date DATE NOT NULL,
 recorded_by INT NOT NULL,
 updated_by INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_fcm_account FOREIGN KEY(account_id) REFERENCES finance_cash_accounts(id),
 KEY idx_fcm_date_status(movement_date,movement_status),
 KEY idx_fcm_account_date(account_id,movement_date),
 KEY idx_fcm_type_status(movement_type,movement_status),
 KEY idx_fcm_reference(reference_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
