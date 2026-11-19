-- Sponsor Document & Reporting Repository
-- Files must be placed in the private storage/sponsor_documents directory.
CREATE TABLE IF NOT EXISTS sponsor_documents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(220) NOT NULL,
 description TEXT NULL,
 category ENUM('programme_document','financial_report','performance_report','impact_report','sponsorship_agreement','compliance_document','other') NOT NULL DEFAULT 'other',
 document_type VARCHAR(30) NOT NULL DEFAULT 'pdf',
 status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 available_from DATETIME NULL,
 available_until DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_sponsor_doc_status(status), KEY idx_sponsor_doc_category(category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_document_programmes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 document_id BIGINT UNSIGNED NOT NULL,
 programme_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_sponsor_doc_programme(document_id,programme_id), KEY idx_sponsor_doc_programme(programme_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_document_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 document_id BIGINT UNSIGNED NOT NULL,
 version_number INT UNSIGNED NOT NULL,
 storage_path VARCHAR(500) NOT NULL,
 original_filename VARCHAR(255) NOT NULL,
 mime_type VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
 file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 checksum_sha256 CHAR(64) NULL,
 change_notes VARCHAR(500) NULL,
 status ENUM('draft','published','superseded','withdrawn') NOT NULL DEFAULT 'draft',
 uploaded_by BIGINT UNSIGNED NULL,
 published_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_sponsor_doc_version(document_id,version_number), KEY idx_sponsor_doc_version_status(document_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Access is inherited only through active sponsor_programme_access rows.
-- Create a document, link it to one or more programme IDs, then add its first version.
-- Publish the document and version only after authorised review.
