-- Commercial Sales Pipeline & Opportunity Management
-- Additive migration. Back up the database before importing.
CREATE TABLE IF NOT EXISTS commercial_sales_opportunities (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 commercial_manager_id INT NOT NULL,
 opportunity_ref VARCHAR(40) NOT NULL,
 client_id INT UNSIGNED DEFAULT NULL,
 client_name VARCHAR(180) NOT NULL,
 source_type ENUM('prospective_client','existing_client') NOT NULL DEFAULT 'prospective_client',
 title VARCHAR(180) NOT NULL,
 opportunity_type ENUM('new_business','renewal','cross_sell','upsell','service_expansion') NOT NULL DEFAULT 'new_business',
 stage ENUM('identified','qualifying','solution_design','proposal_submitted','negotiation','verbal_commitment','won','lost','on_hold') NOT NULL DEFAULT 'identified',
 estimated_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
 currency CHAR(3) NOT NULL DEFAULT 'ZAR',
 probability TINYINT UNSIGNED NOT NULL DEFAULT 10,
 expected_close_date DATE DEFAULT NULL,
 contact_person VARCHAR(160) DEFAULT NULL,
 notes TEXT DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_cso_ref(opportunity_ref),
 KEY idx_cso_owner_stage(commercial_manager_id,stage),
 KEY idx_cso_close(expected_close_date), KEY idx_cso_client(client_id),
 CONSTRAINT fk_cso_client FOREIGN KEY(client_id) REFERENCES commercial_clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_sales_opportunity_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 opportunity_id INT UNSIGNED NOT NULL,
 commercial_manager_id INT NOT NULL,
 event_type ENUM('created','stage_changed','updated','note_added') NOT NULL DEFAULT 'updated',
 old_stage VARCHAR(40) DEFAULT NULL,
 new_stage VARCHAR(40) DEFAULT NULL,
 details TEXT DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_csoh_opportunity_date(opportunity_id,created_at),
 KEY idx_csoh_owner_date(commercial_manager_id,created_at),
 CONSTRAINT fk_csoh_opportunity FOREIGN KEY(opportunity_id) REFERENCES commercial_sales_opportunities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
