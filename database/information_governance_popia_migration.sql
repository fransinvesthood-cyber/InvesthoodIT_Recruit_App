-- Information Officer: Information Governance & POPIA Compliance
-- Target: Investhood IT recruitment platform database.
-- Apply to a backed-up database. This creates governance registers; it does not
-- certify legal compliance or automatically notify the Information Regulator.

CREATE TABLE IF NOT EXISTS io_data_subject_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_type ENUM('access','correction','objection','deletion_assessment','restriction','consent_withdrawal','other') NOT NULL DEFAULT 'other',
 subject_reference VARCHAR(100) NULL,
 description TEXT NOT NULL,
 priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
 status ENUM('received','triage','in_progress','awaiting_subject','awaiting_approval','completed','rejected','closed') NOT NULL DEFAULT 'received',
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 due_date DATE NULL,
 resolution_notes TEXT NULL,
 created_by INT UNSIGNED NULL,
 assigned_to INT UNSIGNED NULL,
 completed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_requests_status_due(status,due_date),
 INDEX idx_io_requests_received(received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_privacy_incidents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 incident_type ENUM('unauthorised_access','data_loss','wrong_recipient','disclosure','device_loss','malware','availability','other') NOT NULL DEFAULT 'other',
 severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
 summary VARCHAR(255) NOT NULL,
 details TEXT NOT NULL,
 status ENUM('reported','triage','investigating','containment','notification_assessment','resolved','closed') NOT NULL DEFAULT 'reported',
 reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 discovered_at DATETIME NULL,
 contained_at DATETIME NULL,
 notification_assessment TEXT NULL,
 regulator_notified_at DATETIME NULL,
 subjects_notified_at DATETIME NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_incidents_status_severity(status,severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_access_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 review_name VARCHAR(180) NOT NULL,
 scope_description TEXT NOT NULL,
 status ENUM('pending','in_progress','remediation_required','approved','completed') NOT NULL DEFAULT 'pending',
 due_date DATE NULL,
 reviewed_by INT UNSIGNED NULL,
 completed_at DATETIME NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_access_status_due(status,due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_retention_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 record_category VARCHAR(150) NOT NULL,
 retention_basis VARCHAR(255) NOT NULL,
 retention_period VARCHAR(100) NOT NULL,
 due_date DATE NULL,
 legal_hold TINYINT(1) NOT NULL DEFAULT 0,
 proposed_action ENUM('retain','review','archive','secure_disposal','anonymise') NOT NULL DEFAULT 'review',
 status ENUM('pending','review','deferred','approved','completed') NOT NULL DEFAULT 'pending',
 decision_notes TEXT NULL,
 created_by INT UNSIGNED NULL,
 approved_by INT UNSIGNED NULL,
 completed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_retention_status_due(status,due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id INT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 entity_type VARCHAR(80) NOT NULL,
 entity_id VARCHAR(80) NULL,
 purpose VARCHAR(255) NULL,
 outcome ENUM('success','failure','review_required') NOT NULL DEFAULT 'success',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_io_audit_created(created_at),
 INDEX idx_io_audit_entity(entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_processing_activities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 activity_name VARCHAR(180) NOT NULL,
 business_owner VARCHAR(180) NOT NULL,
 purpose TEXT NOT NULL,
 personal_information_categories TEXT NOT NULL,
 data_subject_categories TEXT NOT NULL,
 lawful_basis VARCHAR(80) NOT NULL,
 recipients TEXT NULL,
 security_safeguards TEXT NOT NULL,
 cross_border_transfer ENUM('yes','no') NOT NULL DEFAULT 'no',
 status ENUM('active','under_review','suspended','retired') NOT NULL DEFAULT 'active',
 review_due_date DATE NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_processing_review(status,review_due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_operator_register (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 operator_name VARCHAR(180) NOT NULL,
 service_description TEXT NOT NULL,
 information_categories TEXT NULL,
 agreement_reference VARCHAR(180) NULL,
 security_measures TEXT NOT NULL,
 incident_contact VARCHAR(255) NULL,
 review_due_date DATE NULL,
 status ENUM('active','under_review','suspended','terminated') NOT NULL DEFAULT 'active',
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_operator_review(status,review_due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional supporting controls: policy attestations and documented assessments.
CREATE TABLE IF NOT EXISTS io_governance_assessments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 assessment_type ENUM('pia','lawful_processing','cross_border','security_safeguards','other') NOT NULL DEFAULT 'other',
 title VARCHAR(180) NOT NULL,
 scope TEXT NOT NULL,
 findings TEXT NULL,
 actions_required TEXT NULL,
 owner_user_id INT UNSIGNED NULL,
 due_date DATE NULL,
 status ENUM('draft','in_progress','awaiting_approval','approved','closed') NOT NULL DEFAULT 'draft',
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_assessment_status_due(status,due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS io_policy_acknowledgements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 policy_name VARCHAR(180) NOT NULL,
 policy_version VARCHAR(40) NOT NULL,
 acknowledged_by INT UNSIGNED NOT NULL,
 acknowledged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 evidence_reference VARCHAR(255) NULL,
 UNIQUE KEY uq_io_policy_ack(policy_name,policy_version,acknowledged_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
