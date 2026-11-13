-- Candidate Support & Intervention Management
-- MySQL 5.7+/MariaDB compatible

CREATE TABLE IF NOT EXISTS supervisor_candidate_interventions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidate_id INT NOT NULL,
  cohort_id INT NOT NULL,
  supervisor_id INT NOT NULL,
  concern_category VARCHAR(60) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  concern_summary VARCHAR(500) NOT NULL,
  support_provided TEXT NOT NULL,
  baseline_progress VARCHAR(255) DEFAULT NULL,
  intervention_date DATE NOT NULL,
  status ENUM('open','monitoring','resolved','escalated') NOT NULL DEFAULT 'open',
  effectiveness ENUM('pending','improved','no_change','declined') NOT NULL DEFAULT 'pending',
  outcome_notes TEXT DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sci_candidate_cohort (candidate_id,cohort_id),
  KEY idx_sci_supervisor_status (supervisor_id,status),
  KEY idx_sci_due_date (intervention_date),
  CONSTRAINT fk_sci_candidate FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sci_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE CASCADE,
  CONSTRAINT fk_sci_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supervisor_intervention_followups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  intervention_id BIGINT UNSIGNED NOT NULL,
  assigned_to VARCHAR(150) NOT NULL,
  action_description VARCHAR(500) NOT NULL,
  due_date DATE NOT NULL,
  status ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  completion_notes TEXT DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sif_intervention (intervention_id),
  KEY idx_sif_status_due (status,due_date),
  CONSTRAINT fk_sif_intervention FOREIGN KEY (intervention_id) REFERENCES supervisor_candidate_interventions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sif_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
