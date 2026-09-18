-- Add is_featured column to programmes table
ALTER TABLE `programmes` 
ADD COLUMN `is_featured` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `status`;

-- Set some programmes as featured (active programmes will be visible)
UPDATE `programmes` SET `is_featured` = 1 WHERE `status` = 'active' LIMIT 4;
