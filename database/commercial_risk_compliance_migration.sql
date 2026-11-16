CREATE TABLE IF NOT EXISTS commercial_risks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, commercial_manager_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL, category ENUM('financial','legal','operational','reputational','delivery','relationship','regulatory','other') NOT NULL DEFAULT 'operational',
 related_type ENUM('general','client','proposal','contract') NOT NULL DEFAULT 'general', related_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium', likelihood TINYINT UNSIGNED NOT NULL DEFAULT 3,
 impact TINYINT UNSIGNED NOT NULL DEFAULT 3, risk_score TINYINT UNSIGNED NOT NULL DEFAULT 9, risk_owner VARCHAR(150) NULL,
 description TEXT NULL, mitigation_plan TEXT NULL, review_date DATE NULL,
 status ENUM('open','mitigating','monitoring','closed','accepted') NOT NULL DEFAULT 'open',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_cm_risk_owner(commercial_manager_id), KEY idx_cm_risk_status(status), KEY idx_cm_risk_review(review_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS commercial_compliance_obligations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, commercial_manager_id BIGINT UNSIGNED NOT NULL,
 obligation_title VARCHAR(180) NOT NULL, contract_reference VARCHAR(100) NOT NULL, clause_reference VARCHAR(100) NULL,
 responsible_owner VARCHAR(150) NULL, due_date DATE NOT NULL,
 frequency ENUM('once','monthly','quarterly','annually') NOT NULL DEFAULT 'once',
 compliance_status ENUM('pending','in_progress','compliant','at_risk','breached','waived') NOT NULL DEFAULT 'pending',
 evidence_notes TEXT NULL, completed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_cm_obligation_owner(commercial_manager_id), KEY idx_cm_obligation_due(due_date), KEY idx_cm_obligation_status(compliance_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
