-- Client Relationship & Account Growth Management
-- Additive migration; back up the database before importing.

CREATE TABLE IF NOT EXISTS commercial_clients (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 commercial_manager_id INT NOT NULL,
 client_ref VARCHAR(40) NOT NULL,
 client_name VARCHAR(180) NOT NULL,
 industry VARCHAR(120) DEFAULT NULL,
 primary_contact VARCHAR(160) DEFAULT NULL,
 email VARCHAR(190) DEFAULT NULL,
 phone VARCHAR(50) DEFAULT NULL,
 account_type ENUM('prospect','customer','strategic_partner') NOT NULL DEFAULT 'prospect',
 account_status ENUM('active','at_risk','inactive','onboarding') NOT NULL DEFAULT 'active',
 annual_account_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
 currency CHAR(3) NOT NULL DEFAULT 'ZAR',
 satisfaction_score TINYINT UNSIGNED DEFAULT NULL,
 satisfaction_feedback TEXT DEFAULT NULL,
 satisfaction_date DATE DEFAULT NULL,
 next_review_date DATE DEFAULT NULL,
 account_notes TEXT DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_commercial_client_ref(client_ref),
 KEY idx_ccm_owner_status(commercial_manager_id,account_status),
 KEY idx_ccm_review(next_review_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_client_activities (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 client_id INT UNSIGNED NOT NULL,
 commercial_manager_id INT NOT NULL,
 activity_type ENUM('account_created','meeting','call','email','site_visit','review','presentation','other','satisfaction_review') NOT NULL DEFAULT 'other',
 subject VARCHAR(180) NOT NULL,
 details TEXT DEFAULT NULL,
 activity_date DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_cca_client_date(client_id,activity_date),
 KEY idx_cca_owner_date(commercial_manager_id,activity_date),
 CONSTRAINT fk_cca_client FOREIGN KEY(client_id) REFERENCES commercial_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_client_opportunities (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 client_id INT UNSIGNED NOT NULL,
 commercial_manager_id INT NOT NULL,
 opportunity_type ENUM('renewal','service_expansion','cross_sell','upsell','new_business') NOT NULL DEFAULT 'new_business',
 title VARCHAR(180) NOT NULL,
 description TEXT DEFAULT NULL,
 estimated_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
 currency CHAR(3) NOT NULL DEFAULT 'ZAR',
 stage ENUM('identified','qualifying','proposal','negotiation','won','lost') NOT NULL DEFAULT 'identified',
 target_date DATE DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_cco_client_stage(client_id,stage),
 KEY idx_cco_owner_stage(commercial_manager_id,stage),
 CONSTRAINT fk_cco_client FOREIGN KEY(client_id) REFERENCES commercial_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
