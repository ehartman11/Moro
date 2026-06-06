-- Phase 3 Seeker Portal foundation migration
-- Date: 2026-03-08
-- Notes:
-- - Adds owner-managed listing profile metadata for seeker-safe views
-- - Keeps core home identity in `homes`; no owner PII fields are added here

USE moro_db;

CREATE TABLE IF NOT EXISTS home_listing_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    home_id INT NOT NULL,

    beds DECIMAL(3,1) NULL,
    baths DECIMAL(3,1) NULL,
    interior_sqft INT NULL,
    style VARCHAR(80) NULL,
    floors TINYINT NULL,
    basement_type VARCHAR(40) NULL,

    garage_type VARCHAR(40) NULL,
    garage_capacity DECIMAL(4,1) NULL,
    acreage DECIMAL(8,3) NULL,

    year_built_override SMALLINT NULL,

    headline VARCHAR(140) NULL,
    summary TEXT NULL,

    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,

    updated_by_user_id INT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_home_listing_profile_home UNIQUE (home_id),
    CONSTRAINT fk_home_listing_profile_home FOREIGN KEY (home_id) REFERENCES homes(id) ON DELETE CASCADE,
    CONSTRAINT fk_home_listing_profile_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_home_listing_profile_published ON home_listing_profiles (is_published, home_id);
CREATE INDEX idx_home_listing_profile_geo ON home_listing_profiles (acreage, interior_sqft);
