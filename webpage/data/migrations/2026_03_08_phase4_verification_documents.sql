-- Phase 4: verification document uploads for submission slice
-- Date: 2026-03-08

USE moro_db;

CREATE TABLE IF NOT EXISTS verification_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    verification_case_id INT NOT NULL,
    subject_type ENUM('home_owner_claim','contractor_profile') NOT NULL,
    subject_id INT NOT NULL,
    doc_type VARCHAR(60) NOT NULL,
    doc_key VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    byte_size INT NOT NULL,
    sha256 CHAR(64) NOT NULL,
    uploaded_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_verification_documents_case
        FOREIGN KEY (verification_case_id) REFERENCES verification_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_verification_documents_uploaded_by
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX idx_verification_documents_case ON verification_documents (verification_case_id, created_at);
CREATE INDEX idx_verification_documents_subject ON verification_documents (subject_type, subject_id, created_at);
