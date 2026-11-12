-- Personal Information Security Incident & Data Breach Management
-- Back up the database before applying. Existing io_privacy_incidents is retained.
CREATE TABLE IF NOT EXISTS io_security_incidents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, incident_reference VARCHAR(60) NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL,
 incident_type ENUM('unauthorised_access','loss_or_theft','wrong_recipient','unauthorised_disclosure','malware_or_ransomware','system_compromise','improper_disposal','availability','other') NOT NULL DEFAULT 'other',
 severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium', summary TEXT NOT NULL,
 occurred_at DATETIME NULL, discovered_at DATETIME NULL, information_categories TEXT NOT NULL,
 data_subject_categories TEXT NOT NULL, systems_affected TEXT NULL, estimated_subjects INT UNSIGNED NOT NULL DEFAULT 0,
 risk_likelihood TINYINT UNSIGNED NOT NULL DEFAULT 3, risk_impact TINYINT UNSIGNED NOT NULL DEFAULT 3,
 risk_score TINYINT UNSIGNED NOT NULL DEFAULT 9, risk_assessment TEXT NULL, investigation_findings LONGTEXT NULL,
 containment_summary TEXT NULL, contained_at DATETIME NULL,
 notification_required ENUM('undetermined','yes','no') NOT NULL DEFAULT 'undetermined',
 notification_reason TEXT NULL,
 status ENUM('reported','triage','investigating','containment','notification_assessment','remediation','monitoring','resolved','closed') NOT NULL DEFAULT 'reported',
 reported_by BIGINT UNSIGNED NULL, last_updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_sec_status_severity(status,severity), INDEX idx_io_sec_discovered(discovered_at),
 INDEX idx_io_sec_notification(notification_required,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS io_security_incident_actions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, incident_id BIGINT UNSIGNED NOT NULL,
 action_description TEXT NOT NULL, owner_name VARCHAR(180) NULL,
 priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium', due_date DATE NULL,
 status ENUM('open','in_progress','completed','overdue','cancelled') NOT NULL DEFAULT 'open',
 completed_at DATETIME NULL, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_io_sec_actions_incident(incident_id), INDEX idx_io_sec_actions_due(status,due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS io_security_breach_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, incident_id BIGINT UNSIGNED NOT NULL,
 recipient_type ENUM('regulator','affected_subjects','other') NOT NULL,
 notification_status ENUM('not_assessed','not_required','pending','approved','sent','failed') NOT NULL DEFAULT 'not_assessed',
 sent_at DATETIME NULL, notification_reference VARCHAR(255) NULL, notes TEXT NULL,
 recorded_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_io_breach_notice_incident(incident_id), INDEX idx_io_breach_notice_status(notification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS io_security_incident_notes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, incident_id BIGINT UNSIGNED NOT NULL,
 note_type ENUM('investigation','evidence_reference','decision','communications') NOT NULL DEFAULT 'investigation',
 note_text TEXT NOT NULL, created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_io_sec_notes_incident(incident_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Store secure evidence repository references, not raw sensitive evidence files, in this module.
