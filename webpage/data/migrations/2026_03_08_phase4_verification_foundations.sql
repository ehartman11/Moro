-- Phase 4: Verification foundations (Phase 1 MVP slice)
-- Date: 2026-03-08

USE moro_db;

-- Add fast lookup verification statuses on homes and contractor profiles.
ALTER TABLE homes
    ADD COLUMN IF NOT EXISTS owner_verification_status
        ENUM('unverified','pending_review','verified','rejected','revoked')
        NOT NULL DEFAULT 'unverified' AFTER year_built,
    ADD COLUMN IF NOT EXISTS owner_verification_requested_at DATETIME NULL AFTER owner_verification_status,
    ADD COLUMN IF NOT EXISTS owner_verification_reviewed_at DATETIME NULL AFTER owner_verification_requested_at;

ALTER TABLE contractor_profiles
    ADD COLUMN IF NOT EXISTS verification_status
        ENUM('unverified','pending_review','verified','rejected','revoked')
        NOT NULL DEFAULT 'unverified' AFTER insured,
    ADD COLUMN IF NOT EXISTS verification_requested_at DATETIME NULL AFTER verification_status,
    ADD COLUMN IF NOT EXISTS verification_reviewed_at DATETIME NULL AFTER verification_requested_at;

-- Canonical verification case records.
CREATE TABLE IF NOT EXISTS verification_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_type ENUM('home_owner_claim','contractor_profile') NOT NULL,
    subject_id INT NOT NULL,
    submitted_by_user_id INT NOT NULL,
    status ENUM('unverified','pending_review','verified','rejected','revoked') NOT NULL DEFAULT 'pending_review',
    review_notes TEXT NULL,
    reviewed_by_user_id INT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_verification_cases_submitted_by
        FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_verification_cases_reviewed_by
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_verification_cases_subject ON verification_cases (subject_type, subject_id, status);
CREATE INDEX idx_verification_cases_submitted_at ON verification_cases (submitted_at);

-- Immutable status transition log.
CREATE TABLE IF NOT EXISTS verification_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    verification_case_id INT NOT NULL,
    from_status ENUM('unverified','pending_review','verified','rejected','revoked') NULL,
    to_status ENUM('unverified','pending_review','verified','rejected','revoked') NOT NULL,
    actor_user_id INT NOT NULL,
    reason_code VARCHAR(80) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_verification_events_case
        FOREIGN KEY (verification_case_id) REFERENCES verification_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_verification_events_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX idx_verification_events_case_created ON verification_events (verification_case_id, created_at);
