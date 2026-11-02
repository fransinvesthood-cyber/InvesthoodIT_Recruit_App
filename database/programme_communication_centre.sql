-- Programme Communication & Announcement Centre
-- Run this file once against the Investhood platform database.
CREATE TABLE IF NOT EXISTS `programme_announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author_id` int(10) unsigned NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `category` varchar(40) NOT NULL DEFAULT 'general',
  `priority` enum('normal','important','urgent') NOT NULL DEFAULT 'normal',
  `target_role` varchar(50) NOT NULL DEFAULT 'all',
  `programme_id` int(10) unsigned DEFAULT NULL,
  `cohort_id` int(10) unsigned DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_announcement_status_date` (`status`,`published_at`),
  KEY `idx_announcement_target` (`target_role`,`programme_id`,`cohort_id`),
  KEY `idx_announcement_author` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
