-- Commercial Proposal & Quotation Management
-- Additive schema migration; back up database before applying.

CREATE TABLE IF NOT EXISTS commercial_proposals (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 commercial_manager_id INT NOT NULL,
 proposal_ref VARCHAR(48) NOT NULL,
 client_id INT UNSIGNED NOT NULL DEFAULT 0,
 client_name VARCHAR(180) NOT NULL,
 title VARCHAR(180) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'ZAR',
 status ENUM('draft','internal_review','submitted','negotiation','accepted','rejected','withdrawn','expired') NOT NULL DEFAULT 'draft',
 client_decision ENUM('pending','accepted','rejected','changes_requested','no_response') NOT NULL DEFAULT 'pending',
 valid_until DATE DEFAULT NULL,
 notes TEXT DEFAULT NULL,
 decision_notes TEXT DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_commercial_proposal_ref(proposal_ref),
 KEY idx_cp_owner_status(commercial_manager_id,status),
 KEY idx_cp_client(client_id),
 KEY idx_cp_valid(valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_proposal_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id INT UNSIGNED NOT NULL,
 item_description VARCHAR(255) NOT NULL,
 quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
 unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_cpi_proposal(proposal_id),
 CONSTRAINT fk_cpi_proposal FOREIGN KEY(proposal_id) REFERENCES commercial_proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_proposal_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id INT UNSIGNED NOT NULL,
 commercial_manager_id INT NOT NULL,
 event_type VARCHAR(60) NOT NULL,
 details TEXT DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_cph_proposal_date(proposal_id,created_at),
 CONSTRAINT fk_cph_proposal FOREIGN KEY(proposal_id) REFERENCES commercial_proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
