-- Programme Deliverables & Milestone Verification (additive, idempotent)
CREATE TABLE IF NOT EXISTS programme_deliverables (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  programme_id INT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED NULL,
  milestone_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  deliverable_type VARCHAR(50) NOT NULL DEFAULT 'report',
  due_date DATE NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pd_programme_due (programme_id, due_date),
  KEY idx_pd_cohort (cohort_id),
  KEY idx_pd_milestone (milestone_id),
  KEY idx_pd_due_status (due_date, status),
  CONSTRAINT fk_pd_programme FOREIGN KEY (programme_id)
    REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pd_cohort FOREIGN KEY (cohort_id)
    REFERENCES cohorts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pd_creator FOREIGN KEY (created_by)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deliverable_submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  deliverable_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  submitted_by INT UNSIGNED NULL,         -- candidate, or officer acting on their behalf
  notes TEXT NULL,
  reference_url VARCHAR(500) NULL,
  file_path VARCHAR(255) NULL,            -- random private-storage filename, not a filesystem path
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  file_size INT UNSIGNED NULL,
  status ENUM('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_by INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  verification_comment TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_ds_deliverable (deliverable_id, status),
  KEY idx_ds_candidate_deliverable (candidate_id, deliverable_id, submitted_at),
  KEY idx_ds_submitter (submitted_by),
  KEY idx_ds_reviewer (verified_by),
  CONSTRAINT fk_ds_deliverable FOREIGN KEY (deliverable_id)
    REFERENCES programme_deliverables(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ds_candidate FOREIGN KEY (candidate_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ds_submitter FOREIGN KEY (submitted_by)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ds_reviewer FOREIGN KEY (verified_by)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_officer_notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  programme_officer_id INT UNSIGNED NOT NULL,
  deliverable_id INT UNSIGNED NOT NULL,
  submission_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pon_submission (programme_officer_id, submission_id),
  KEY idx_pon_feed (programme_officer_id, is_read, created_at),
  CONSTRAINT fk_pon_officer FOREIGN KEY (programme_officer_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pon_deliverable FOREIGN KEY (deliverable_id)
    REFERENCES programme_deliverables(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pon_submission FOREIGN KEY (submission_id)
    REFERENCES deliverable_submissions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
