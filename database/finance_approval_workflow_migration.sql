-- Investhood IT: Structured Finance Approval Workflow
-- Run once against the same database used by the Finance Officer portal.
-- Back up the database before applying.

SET NAMES utf8mb4;

-- Add a workflow stage without relying on MySQL's non-portable
-- "ADD COLUMN IF NOT EXISTS" syntax.
SET @has_stage := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'stipend_schedules'
    AND COLUMN_NAME = 'approval_stage'
);
SET @stage_sql := IF(
  @has_stage = 0,
  'ALTER TABLE stipend_schedules ADD COLUMN approval_stage VARCHAR(40) NULL AFTER status',
  'SELECT ''approval_stage already exists'' AS migration_note'
);
PREPARE stage_stmt FROM @stage_sql;
EXECUTE stage_stmt;
DEALLOCATE PREPARE stage_stmt;

-- Existing awaiting_approval records start at the Finance Officer review stage.
UPDATE stipend_schedules
SET approval_stage = 'finance_review'
WHERE status = 'awaiting_approval'
  AND (approval_stage IS NULL OR approval_stage = '');

UPDATE stipend_schedules
SET approval_stage = 'manager_review'
WHERE status = 'awaiting_manager_approval'
  AND (approval_stage IS NULL OR approval_stage = '');

UPDATE stipend_schedules
SET approval_stage = 'completed'
WHERE status = 'approved'
  AND (approval_stage IS NULL OR approval_stage = '');

-- Expand the existing ENUM so the application can persist intermediate stages.
ALTER TABLE stipend_schedules
  MODIFY status ENUM(
    'draft',
    'awaiting_approval',
    'awaiting_manager_approval',
    'returned_for_correction',
    'approved',
    'rejected',
    'processed',
    'superseded'
  ) NOT NULL DEFAULT 'draft';

-- Explicit user-level delegation for managers (and optional named reviewers).
-- Insert the target user's id and stage='manager_review' to grant manager access.
CREATE TABLE IF NOT EXISTS finance_approval_authorities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  stage VARCHAR(40) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  assigned_by INT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_finance_approval_authority (user_id, stage),
  KEY idx_finance_approval_authority_stage (stage, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_approval_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  schedule_id BIGINT UNSIGNED NOT NULL,
  stage VARCHAR(40) NOT NULL,
  decision VARCHAR(40) NOT NULL,
  acted_by INT NOT NULL,
  comment TEXT NULL,
  previous_status VARCHAR(50) NULL,
  new_status VARCHAR(50) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_finance_approval_schedule (schedule_id, created_at),
  KEY idx_finance_approval_actor (acted_by, created_at),
  KEY idx_finance_approval_decision (decision, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- New statuses used by the workflow:
-- awaiting_approval, awaiting_manager_approval, returned_for_correction,
-- rejected, approved. Existing status column is intentionally not narrowed
-- or converted, preserving compatibility with existing portal statuses.

SELECT 'Finance approval workflow migration completed.' AS migration_status;
