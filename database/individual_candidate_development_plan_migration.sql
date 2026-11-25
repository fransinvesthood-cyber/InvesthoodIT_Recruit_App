CREATE TABLE IF NOT EXISTS supervisor_candidate_development_plans (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, cohort_id INT UNSIGNED NOT NULL,
 candidate_id INT UNSIGNED NOT NULL, supervisor_id INT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL, baseline_assessment TEXT NULL, target_outcome TEXT NULL,
 start_date DATE NOT NULL, target_date DATE NOT NULL,
 status ENUM('draft','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_idp_supervisor(supervisor_id,status), KEY idx_idp_candidate(candidate_id,cohort_id),
 CONSTRAINT fk_idp_cohort FOREIGN KEY(cohort_id) REFERENCES cohorts(id) ON DELETE RESTRICT,
 CONSTRAINT fk_idp_candidate FOREIGN KEY(candidate_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_idp_supervisor FOREIGN KEY(supervisor_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_idp_membership FOREIGN KEY(cohort_id,candidate_id) REFERENCES cohort_participants(cohort_id,user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS supervisor_development_goals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, plan_id BIGINT UNSIGNED NOT NULL,
 skill_area VARCHAR(150) NOT NULL, gap_description TEXT NULL, objective TEXT NOT NULL,
 priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
 target_date DATE NOT NULL, status ENUM('not_started','in_progress','achieved','deferred') NOT NULL DEFAULT 'not_started',
 progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_idp_goal_plan(plan_id,status,target_date),
 CONSTRAINT fk_idp_goal_plan FOREIGN KEY(plan_id) REFERENCES supervisor_candidate_development_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS supervisor_development_activities (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, plan_id BIGINT UNSIGNED NOT NULL, goal_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL, details TEXT NULL, assigned_to INT UNSIGNED NULL, due_date DATE NOT NULL,
 status ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending', completion_notes TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_idp_activity_plan(plan_id,status,due_date), KEY idx_idp_assignee(assigned_to),
 CONSTRAINT fk_idp_activity_plan FOREIGN KEY(plan_id) REFERENCES supervisor_candidate_development_plans(id) ON DELETE CASCADE,
 CONSTRAINT fk_idp_activity_goal FOREIGN KEY(goal_id) REFERENCES supervisor_development_goals(id) ON DELETE SET NULL,
 CONSTRAINT fk_idp_activity_assignee FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS supervisor_development_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, plan_id BIGINT UNSIGNED NOT NULL, reviewed_by INT UNSIGNED NOT NULL,
 review_date DATE NOT NULL, progress_rating ENUM('exceeding','on_track','needs_attention','at_risk') NOT NULL DEFAULT 'on_track',
 review_notes TEXT NULL, next_steps TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_idp_review_plan(plan_id,review_date),
 CONSTRAINT fk_idp_review_plan FOREIGN KEY(plan_id) REFERENCES supervisor_candidate_development_plans(id) ON DELETE CASCADE,
 CONSTRAINT fk_idp_review_user FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
