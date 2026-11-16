-- Sponsor Communication & Engagement Management
-- MySQL 5.7+ / MariaDB compatible. Run once using phpMyAdmin.
CREATE TABLE IF NOT EXISTS sponsor_communication_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sponsor_user_id INT NOT NULL,
  programme_id INT NOT NULL,
  subject VARCHAR(180) NOT NULL,
  enquiry_type ENUM('general','progress_update','funding','beneficiary_outcomes','documentation','meeting_request','other') NOT NULL DEFAULT 'general',
  status ENUM('open','awaiting_response','in_progress','awaiting_sponsor','closed') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  assigned_to INT NULL,
  follow_up_due_at DATETIME NULL,
  last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sponsor_status (sponsor_user_id,status),
  KEY idx_programme (programme_id),
  KEY idx_follow_up (follow_up_due_at),
  KEY idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_communication_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id BIGINT UNSIGNED NOT NULL,
  sender_user_id INT NOT NULL,
  sender_role ENUM('sponsor','management','system') NOT NULL DEFAULT 'sponsor',
  message_body TEXT NOT NULL,
  attachment_path VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_thread_created (thread_id,created_at),
  CONSTRAINT fk_sponsor_comm_message_thread FOREIGN KEY (thread_id)
    REFERENCES sponsor_communication_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional response and follow-up tracking fields are stored on the thread.
-- Management users should reply using sender_role='management' and their own user ID.
