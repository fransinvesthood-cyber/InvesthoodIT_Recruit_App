-- ============================================================
-- INVESTHOOD IT
-- Budget Allocation & Expenditure Monitoring
-- Target database: investhood_platform
-- ============================================================

USE `investhood_platform`;

-- ============================================================
-- 1. FINANCE BUDGETS
-- ============================================================

CREATE TABLE IF NOT EXISTS `finance_budgets` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `programme_id` INT(10) UNSIGNED NOT NULL,
    `cohort_id` INT(10) UNSIGNED DEFAULT NULL,
    `financial_year` VARCHAR(9) NOT NULL,
    `budget_name` VARCHAR(180) NOT NULL,
    `allocated_amount` DECIMAL(14,2) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('active','closed') NOT NULL DEFAULT 'active',
    `created_by` INT(10) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    KEY `idx_budget_programme` (`programme_id`),
    KEY `idx_budget_cohort` (`cohort_id`),
    KEY `idx_budget_year` (`financial_year`),
    KEY `idx_budget_created_by` (`created_by`),

    CONSTRAINT `fk_fin_budget_programme`
        FOREIGN KEY (`programme_id`)
        REFERENCES `programmes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk_fin_budget_cohort`
        FOREIGN KEY (`cohort_id`)
        REFERENCES `cohorts` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk_fin_budget_created_by`
        FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. FINANCE EXPENDITURES
-- Corrected: expense_date and reference_no
-- ============================================================

CREATE TABLE IF NOT EXISTS `finance_expenditures` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `budget_id` INT(10) UNSIGNED NOT NULL,
    `expense_date` DATE NOT NULL,
    `category` VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `reference_no` VARCHAR(120) DEFAULT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `status` ENUM('paid','committed') NOT NULL DEFAULT 'paid',
    `recorded_by` INT(10) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    KEY `idx_expenditure_budget` (`budget_id`),
    KEY `idx_expenditure_date` (`expense_date`),
    KEY `idx_expenditure_status` (`status`),
    KEY `idx_expenditure_recorded_by` (`recorded_by`),

    CONSTRAINT `fk_fin_expenditure_budget`
        FOREIGN KEY (`budget_id`)
        REFERENCES `finance_budgets` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk_fin_expenditure_recorded_by`
        FOREIGN KEY (`recorded_by`)
        REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. FINANCE BUDGET AUDIT
-- Includes original and additional audit fields
-- ============================================================

CREATE TABLE IF NOT EXISTS `finance_budget_audit` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `budget_id` INT(10) UNSIGNED DEFAULT NULL,
    `expenditure_id` INT(10) UNSIGNED DEFAULT NULL,
    `entity_type` VARCHAR(40) NOT NULL DEFAULT 'budget',
    `entity_id` INT(10) UNSIGNED DEFAULT NULL,
    `user_id` INT(10) UNSIGNED NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    KEY `idx_budget_audit_budget` (`budget_id`),
    KEY `idx_budget_audit_expenditure` (`expenditure_id`),
    KEY `idx_budget_audit_entity` (`entity_type`, `entity_id`),
    KEY `idx_budget_audit_user` (`user_id`),

    CONSTRAINT `fk_fin_audit_budget`
        FOREIGN KEY (`budget_id`)
        REFERENCES `finance_budgets` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT `fk_fin_audit_expenditure`
        FOREIGN KEY (`expenditure_id`)
        REFERENCES `finance_expenditures` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT `fk_fin_audit_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. VERIFY TABLES
-- ============================================================

SHOW TABLES LIKE 'finance_budgets';

SHOW TABLES LIKE 'finance_expenditures';

SHOW TABLES LIKE 'finance_budget_audit';


-- ============================================================
-- 5. VERIFY EXPENDITURE COLUMNS
-- ============================================================

SHOW COLUMNS FROM `finance_expenditures`;


-- ============================================================
-- 6. VERIFY AUDIT COLUMNS
-- ============================================================

SHOW COLUMNS FROM `finance_budget_audit`;