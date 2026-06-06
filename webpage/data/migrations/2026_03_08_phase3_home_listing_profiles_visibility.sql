-- Phase 3 listing profile visibility controls
-- Date: 2026-03-08

USE moro_db;

ALTER TABLE home_listing_profiles
    ADD COLUMN IF NOT EXISTS visibility_fields JSON NULL AFTER summary;
