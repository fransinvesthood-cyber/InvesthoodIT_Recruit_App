-- ============================================================
-- INVESTHOOD IT - Application Documents (Stage 4) Migration
-- ============================================================
-- Adds per-application document storage. Each uploaded document
-- is associated with a Draft application and references a secure
-- file stored outside the web root (uploads_private/documents/).
--
-- Relationship:
--   Candidate
--     ↓
--   Application
--     ↓
--   Application Document
--     ↓
--   Document/File (secure storage)
--
-- When a candidate reuses an existing profile document
-- (documents.id), the application_documents row references the
-- same stored file. `source_document_id` tracks this so the
-- physical file is never deleted while the profile still needs it.
--
-- This migration is additive and does NOT modify existing
-- authentication, profile, programme, cohort, opportunity,
-- applications or application form schema.
--
-- Tables added:
--   application_documents - documents attached to an application
--
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE `investhood_platform`;

-- ------------------------------------------------------------
-- 1. APPLICATION DOCUMENTS
--    Metadata only; physical files stored securely & served
--    through an authenticated download endpoint.
--    document_type maps to the opportunity's configured
--    document requirement (e.g. CV, QUALIFICATION, TRANSCRIPT,
--    ID, COVER_LETTER, SUPPORTING_DOCUMENT).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `application_documents`;
CREATE TABLE `application_documents` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id`    INT UNSIGNED NOT NULL,
  `document_type`     VARCHAR(50)  NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename`   VARCHAR(255) NOT NULL,
  `mime_type`         VARCHAR(100) NOT NULL,
  `file_size`         INT UNSIGNED NOT NULL DEFAULT 0,
  `file_checksum`     VARCHAR(64)  NOT NULL,
  `is_reused`         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 when referencing a candidate profile document',
  `source_document_id` INT UNSIGNED NULL COMMENT 'documents.id when is_reused = 1',
  `uploaded_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_docs_application_type` (`application_id`, `document_type`),
  KEY `idx_app_docs_application` (`application_id`),
  KEY `idx_app_docs_type` (`document_type`),
  KEY `idx_app_docs_source` (`source_document_id`),
  CONSTRAINT `fk_app_docs_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;