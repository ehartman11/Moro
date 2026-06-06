-- Phase 3 owner-seeker inquiry workflow
-- Date: 2026-03-08

USE moro_db;

CREATE TABLE IF NOT EXISTS home_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    home_id INT NOT NULL,
    seeker_user_id INT NOT NULL,
    message TEXT NOT NULL,
    owner_response TEXT NULL,
    state ENUM('open','responded','closed') NOT NULL DEFAULT 'open',
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_home_inquiries_home FOREIGN KEY (home_id) REFERENCES homes(id) ON DELETE CASCADE,
    CONSTRAINT fk_home_inquiries_seeker FOREIGN KEY (seeker_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_home_inquiries_home_state ON home_inquiries (home_id, state, opened_at);
CREATE INDEX idx_home_inquiries_seeker ON home_inquiries (seeker_user_id, opened_at);
