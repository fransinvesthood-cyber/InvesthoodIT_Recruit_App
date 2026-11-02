-- Programme Manager financial, outcome and risk monitoring suite
-- Import into the active Investhood application database before using the new pages.
CREATE TABLE IF NOT EXISTS programme_budgets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED NULL,
  fiscal_period VARCHAR(40) NOT NULL DEFAULT 'Current period',
  allocated_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pm_budget_programme (programme_id), KEY idx_pm_budget_cohort (cohort_id),
  CONSTRAINT fk_pm_budget_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pm_budget_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_expenditures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED NULL,
  expense_date DATE NOT NULL,
  category VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  reference_number VARCHAR(100) NULL,
  recorded_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pm_exp_programme_date (programme_id, expense_date), KEY idx_pm_exp_cohort (cohort_id),
  CONSTRAINT fk_pm_exp_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pm_exp_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_outcomes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED NULL,
  candidate_id INT UNSIGNED NULL,
  outcome_type ENUM('achievement','completion','employment','other') NOT NULL DEFAULT 'achievement',
  outcome_title VARCHAR(180) NOT NULL,
  details TEXT NULL,
  achieved_on DATE NOT NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  verification_reference VARCHAR(180) NULL,
  recorded_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pm_outcome_programme (programme_id, outcome_type), KEY idx_pm_outcome_candidate (candidate_id), KEY idx_pm_outcome_cohort (cohort_id),
  CONSTRAINT fk_pm_outcome_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pm_outcome_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_risks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Operational',
  likelihood TINYINT UNSIGNED NOT NULL DEFAULT 3,
  impact TINYINT UNSIGNED NOT NULL DEFAULT 3,
  mitigation_plan TEXT NULL,
  owner_name VARCHAR(150) NULL,
  due_date DATE NULL,
  status ENUM('open','mitigating','monitoring','resolved','accepted') NOT NULL DEFAULT 'open',
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pm_risk_programme (programme_id, status), KEY idx_pm_risk_cohort (cohort_id),
  CONSTRAINT fk_pm_risk_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pm_risk_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_pm_risk_likelihood CHECK (likelihood BETWEEN 1 AND 5),
  CONSTRAINT chk_pm_risk_impact CHECK (impact BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
