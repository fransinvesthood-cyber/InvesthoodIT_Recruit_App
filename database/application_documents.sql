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
--    document requirement (e.g. cv, qualification, transcript,
--    id, cover_letter, supporting).
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
  `uploaded_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_docs_application_type` (`application_id`, `document_type`),
  KEY `idx_app_docs_application` (`application_id`),
  KEY `idx_app_docs_type` (`document_type`),
  CONSTRAINT `fk_app_docs_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;