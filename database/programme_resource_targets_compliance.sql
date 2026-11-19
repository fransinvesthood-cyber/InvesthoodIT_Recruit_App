-- Programme resource allocation, targets and compliance tracking
CREATE TABLE IF NOT EXISTS programme_resource_allocations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 programme_id INT UNSIGNED NOT NULL,
 cohort_id INT UNSIGNED NULL,
 resource_name VARCHAR(160) NOT NULL,
 resource_category VARCHAR(80) NOT NULL DEFAULT 'Equipment',
 unit_name VARCHAR(40) NOT NULL DEFAULT 'units',
 available_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
 allocated_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
 allocation_date DATE NULL,
 notes TEXT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_pm_resource_programme(programme_id), KEY idx_pm_resource_cohort(cohort_id),
 CONSTRAINT fk_pm_resource_programme FOREIGN KEY(programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_pm_resource_cohort FOREIGN KEY(cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_targets (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 programme_id INT UNSIGNED NOT NULL,
 cohort_id INT UNSIGNED NULL,
 target_name VARCHAR(160) NOT NULL,
 description TEXT NULL,
 target_value DECIMAL(14,2) NOT NULL DEFAULT 0,
 actual_value DECIMAL(14,2) NOT NULL DEFAULT 0,
 unit_name VARCHAR(40) NOT NULL DEFAULT 'units',
 due_date DATE NULL,
 status ENUM('active','achieved','at_risk','closed') NOT NULL DEFAULT 'active',
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_pm_target_programme(programme_id), KEY idx_pm_target_cohort(cohort_id),
 CONSTRAINT fk_pm_target_programme FOREIGN KEY(programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_pm_target_cohort FOREIGN KEY(cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme_compliance_documents (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 programme_id INT UNSIGNED NOT NULL,
 cohort_id INT UNSIGNED NULL,
 document_name VARCHAR(180) NOT NULL,
 requirement_category VARCHAR(100) NOT NULL DEFAULT 'Programme documentation',
 status ENUM('outstanding','submitted','verified','rejected','expired') NOT NULL DEFAULT 'outstanding',
 due_date DATE NULL,
 expiry_date DATE NULL,
 evidence_reference VARCHAR(255) NULL,
 notes TEXT NULL,
 recorded_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_pm_compliance_programme(programme_id), KEY idx_pm_compliance_cohort(cohort_id), KEY idx_pm_compliance_status(status),
 CONSTRAINT fk_pm_compliance_programme FOREIGN KEY(programme_id) REFERENCES programmes(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_pm_compliance_cohort FOREIGN KEY(cohort_id) REFERENCES cohorts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
