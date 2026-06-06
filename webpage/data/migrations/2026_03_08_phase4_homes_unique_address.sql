-- Phase 4: enforce unique home address identity
-- Date: 2026-03-08
-- Goal:
-- - Prevent duplicate home rows for the same street/unit/city/state/zip.
-- - Treat NULL and blank address_line2 as equivalent so uniqueness is reliable.

USE moro_db;

-- Normalize unit line for uniqueness checks.
ALTER TABLE homes
    ADD COLUMN IF NOT EXISTS address_line2_norm VARCHAR(255)
        GENERATED ALWAYS AS (COALESCE(NULLIF(TRIM(address_line2), ''), '')) STORED;

-- Add uniqueness constraint once (idempotent pattern).
SET @idx_exists := (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'homes'
      AND index_name = 'uq_homes_full_address'
);

SET @add_idx_sql := IF(
    @idx_exists = 0,
    'ALTER TABLE homes ADD UNIQUE KEY uq_homes_full_address (address_line1, address_line2_norm, city, state, zip)',
    'SELECT "uq_homes_full_address already exists"'
);

PREPARE stmt FROM @add_idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional preflight query (run manually before applying index if needed):
-- SELECT address_line1,
--        COALESCE(NULLIF(TRIM(address_line2), ''), '') AS address_line2_norm,
--        city,
--        state,
--        zip,
--        COUNT(*) AS duplicate_count
-- FROM homes
-- GROUP BY address_line1, COALESCE(NULLIF(TRIM(address_line2), ''), ''), city, state, zip
-- HAVING COUNT(*) > 1;
