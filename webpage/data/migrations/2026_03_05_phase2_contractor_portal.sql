-- Phase 2 Contractor Portal MVP migration
-- Date: 2026-03-05
-- Notes:
-- - Assumes existing tables: users, homes, items, maintenance_tasks
-- - Uses additive changes only

USE moro_db;

-- 1) Contractor profile (1:1 with users)
CREATE TABLE IF NOT EXISTS contractor_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    service_categories JSON NULL,
    license_number VARCHAR(120) NULL,
    license_state VARCHAR(30) NULL,
    insured TINYINT(1) NOT NULL DEFAULT 0,
    bio TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_contractor_user UNIQUE (user_id),
    CONSTRAINT fk_contractor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_contractor_business_name ON contractor_profiles (business_name);

-- 2) Service jobs owned by homeowners and optionally linked to item/task
CREATE TABLE IF NOT EXISTS service_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    home_id INT NOT NULL,
    item_id INT NULL,
    task_id INT NULL,
    homeowner_user_id INT NOT NULL,
    contractor_user_id INT NOT NULL,

    title VARCHAR(255) NOT NULL,
    description TEXT NULL,

    state ENUM('open','assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',

    scheduled_for DATETIME NULL,
    due_at DATETIME NULL,
    completed_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_service_jobs_home FOREIGN KEY (home_id) REFERENCES homes(id) ON DELETE CASCADE,
    CONSTRAINT fk_service_jobs_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    CONSTRAINT fk_service_jobs_task FOREIGN KEY (task_id) REFERENCES maintenance_tasks(id) ON DELETE SET NULL,
    CONSTRAINT fk_service_jobs_homeowner FOREIGN KEY (homeowner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_service_jobs_contractor FOREIGN KEY (contractor_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX idx_service_jobs_home_state ON service_jobs (home_id, state);
CREATE INDEX idx_service_jobs_contractor_state ON service_jobs (contractor_user_id, state);
CREATE INDEX idx_service_jobs_due_at ON service_jobs (due_at);

-- 3) Contractor submissions for jobs
CREATE TABLE IF NOT EXISTS job_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_job_id INT NOT NULL,
    submitted_by_user_id INT NOT NULL,

    state ENUM('draft','submitted','needs_changes','approved','rejected') NOT NULL DEFAULT 'draft',

    amount DECIMAL(10,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    work_summary TEXT NULL,
    receipt_doc_key VARCHAR(500) NULL,

    submitted_at DATETIME NULL,
    decided_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_job_submissions_job FOREIGN KEY (service_job_id) REFERENCES service_jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_job_submissions_user FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX idx_job_submissions_job_state ON job_submissions (service_job_id, state);
CREATE INDEX idx_job_submissions_submitted_at ON job_submissions (submitted_at);

-- 4) Submission media (before/after/general)
CREATE TABLE IF NOT EXISTS submission_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_submission_id INT NOT NULL,
    media_type ENUM('before','after','general') NOT NULL DEFAULT 'general',
    media_key VARCHAR(500) NOT NULL,
    caption VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_submission_media_submission FOREIGN KEY (job_submission_id) REFERENCES job_submissions(id) ON DELETE CASCADE
);

CREATE INDEX idx_submission_media_submission ON submission_media (job_submission_id);

-- 5) Review decisions (immutable audit log)
CREATE TABLE IF NOT EXISTS submission_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_submission_id INT NOT NULL,
    reviewer_user_id INT NOT NULL,
    decision ENUM('approve','reject','needs_changes') NOT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_submission_reviews_submission FOREIGN KEY (job_submission_id) REFERENCES job_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_reviews_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX idx_submission_reviews_submission ON submission_reviews (job_submission_id);
CREATE INDEX idx_submission_reviews_reviewer ON submission_reviews (reviewer_user_id);

-- Optional forward-looking helper index for homeowner inbox patterns
CREATE INDEX idx_service_jobs_home_contractor ON service_jobs (home_id, contractor_user_id);
