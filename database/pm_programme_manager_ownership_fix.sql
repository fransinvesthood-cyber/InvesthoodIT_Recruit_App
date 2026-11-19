-- Programme Manager ownership field required by Programme Manager pages.
-- Safe to import more than once on supported MariaDB/MySQL versions.
ALTER TABLE programmes
    ADD COLUMN IF NOT EXISTS programme_manager_id INT UNSIGNED NULL AFTER created_by;

-- Backfill only where the programme creator is already a Programme Manager.
-- Do not assign programmes created by administrators to an arbitrary manager.
UPDATE programmes p
INNER JOIN users u ON u.id = p.created_by
INNER JOIN roles r ON r.id = u.role_id
SET p.programme_manager_id = p.created_by
WHERE p.programme_manager_id IS NULL
  AND r.slug = 'programme_manager';

-- If this installation has exactly ONE Programme Manager account, safely
-- assign remaining unassigned programmes to that sole manager. When multiple
-- Programme Managers exist, this statement updates nothing; assignments must
-- be made explicitly by an administrator.
UPDATE programmes p
INNER JOIN (
    SELECT MIN(u.id) AS manager_id
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE r.slug = 'programme_manager'
    HAVING COUNT(*) = 1
) sole_manager ON 1 = 1
SET p.programme_manager_id = sole_manager.manager_id
WHERE p.programme_manager_id IS NULL;

-- After import, administrators must assign any remaining unassigned programmes
-- to the correct Programme Manager. Example (replace IDs with verified values):
-- UPDATE programmes SET programme_manager_id = 2 WHERE id IN (1, 2, 3);
