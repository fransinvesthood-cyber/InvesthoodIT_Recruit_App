-- Programme and cohort staff assignment fields.
-- Existing programmes.programme_manager_id is retained.
ALTER TABLE programmes
    ADD COLUMN IF NOT EXISTS programme_officer_id INT(10) UNSIGNED NULL AFTER programme_manager_id;

ALTER TABLE cohorts
    ADD COLUMN IF NOT EXISTS programme_manager_id INT(10) UNSIGNED NULL AFTER supervisor_id,
    ADD COLUMN IF NOT EXISTS programme_officer_id INT(10) UNSIGNED NULL AFTER programme_manager_id;
