-- Programme Stakeholder Engagement and Benefits Realisation modules
-- Import into the active InvesthoodIT Recruitment App database.

CREATE TABLE IF NOT EXISTS programme_stakeholders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED NULL,
  stakeholder_name VARCHAR(160) NOT NULL,
  organisation VARCHAR(160) NULL,
  stakeholder_type VARCHAR(80) NOT NULL DEFAULT 'Partner',
  role_responsibility VARCHAR(180) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  influence_level ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  interest_level ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  relationship_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pm_stakeholder_programme (programme_id), KEY idx_pm_stakeholder_cohort (cohort_id),
  CONSTRAINT fk_pm_stakeholder_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pm_stakeholder_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_stakeholder_engagements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  stakeholder_id INT UNSIGNED NOT NULL,
  programme_id INT UNSIGNED NOT NULL,
  engagement_date DATETIME NOT NULL,
  channel ENUM('meeting','phone','email','site_visit','workshop','other') NOT NULL DEFAULT 'meeting',
  subject VARCHAR(180) NOT NULL,
  engagement_summary TEXT NULL,
  contribution TEXT NULL,
  action_required VARCHAR(255) NULL,
  action_owner VARCHAR(160) NULL,
  action_due DATE NULL,
  action_status ENUM('not_required','open','in_progress','completed','overdue') NOT NULL DEFAULT 'not_required',
  recorded_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pm_engagement_stakeholder (stakeholder_id), KEY idx_pm_engagement_programme_date (programme_id, engagement_date), KEY idx_pm_engagement_action (action_status, action_due),
  CONSTRAINT fk_pm_engagement_stakeholder FOREIGN KEY (stakeholder_id) REFERENCES programme_stakeholders(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pm_engagement_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_benefits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED NULL,
  benefit_name VARCHAR(180) NOT NULL,
  benefit_description TEXT NULL,
  indicator_name VARCHAR(160) NOT NULL,
  baseline_value DECIMAL(14,3) NOT NULL DEFAULT 0,
  target_value DECIMAL(14,3) NOT NULL,
  target_direction ENUM('increase','decrease') NOT NULL DEFAULT 'increase',
  actual_value DECIMAL(14,3) NULL,
  measurement_unit VARCHAR(60) NOT NULL DEFAULT 'value',
  target_date DATE NULL,
  benefit_status ENUM('planned','on_track','partially_achieved','not_achieved','achieved') NOT NULL DEFAULT 'planned',
  evidence_reference VARCHAR(190) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pm_benefit_programme (programme_id), KEY idx_pm_benefit_cohort (cohort_id), KEY idx_pm_benefit_status (benefit_status),
  CONSTRAINT fk_pm_benefit_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pm_benefit_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_benefit_evaluations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  benefit_id INT UNSIGNED NOT NULL,
  evaluation_date DATE NOT NULL,
  previous_actual DECIMAL(14,3) NULL,
  actual_value DECIMAL(14,3) NOT NULL,
  benefit_status ENUM('on_track','partially_achieved','not_achieved','achieved') NOT NULL,
  impact_rating ENUM('low','moderate','high','very_high') NOT NULL DEFAULT 'moderate',
  evaluation_notes TEXT NULL,
  evidence_reference VARCHAR(190) NULL,
  evaluated_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pm_benefit_eval_benefit_date (benefit_id, evaluation_date),
  CONSTRAINT fk_pm_benefit_eval_benefit FOREIGN KEY (benefit_id) REFERENCES programme_benefits(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
