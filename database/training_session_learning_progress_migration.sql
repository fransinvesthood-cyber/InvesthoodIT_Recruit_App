-- Training Session Monitoring & Learning Progress Evaluation
-- Compatible with the supplied Investhood schema (MariaDB 10.11 / MySQL 8+).
-- Uses cohorts.id, users.id and cohort_participants(cohort_id,user_id).

CREATE TABLE IF NOT EXISTS supervisor_training_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cohort_id INT UNSIGNED NOT NULL,
  supervisor_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  session_type ENUM('academic','practical','workshop','assessment','mentoring','other') NOT NULL DEFAULT 'academic',
  description TEXT NULL,
  session_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  venue VARCHAR(190) NULL,
  delivery_mode ENUM('on_site','remote','hybrid') NOT NULL DEFAULT 'on_site',
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  learning_objectives TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sts_cohort_date (cohort_id, session_date),
  KEY idx_sts_supervisor (supervisor_id),
  CONSTRAINT fk_sts_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE CASCADE,
  CONSTRAINT fk_sts_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supervisor_training_evaluations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  cohort_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  supervisor_id INT UNSIGNED NOT NULL,
  attendance_status ENUM('present','late','absent','excused') NOT NULL DEFAULT 'present',
  participation_level ENUM('excellent','good','satisfactory','limited','not_participated') NOT NULL DEFAULT 'satisfactory',
  academic_score DECIMAL(5,2) NULL,
  practical_score DECIMAL(5,2) NULL,
  overall_score DECIMAL(5,2) NULL,
  learning_progress ENUM('exceeding','on_track','developing','at_risk') NOT NULL DEFAULT 'on_track',
  strengths TEXT NULL,
  learning_gaps TEXT NULL,
  support_type ENUM('none','academic','practical','academic_and_practical','wellbeing_or_referral') NOT NULL DEFAULT 'none',
  support_notes TEXT NULL,
  follow_up_date DATE NULL,
  follow_up_status ENUM('not_required','pending','in_progress','resolved') NOT NULL DEFAULT 'not_required',
  evaluator_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_st_eval_session_candidate (session_id, candidate_id),
  KEY idx_st_eval_cohort_candidate (cohort_id, candidate_id),
  KEY idx_st_eval_support (support_type, follow_up_status),
  CONSTRAINT fk_ste_session FOREIGN KEY (session_id) REFERENCES supervisor_training_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_ste_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ste_candidate FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ste_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
