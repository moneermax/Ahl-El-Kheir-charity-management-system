-- Sponsor acquisition provenance field used by the sponsor create/edit routes.
-- Kept idempotent so it can be applied safely to databases that already contain the column.
ALTER TABLE sponsors
    ADD COLUMN IF NOT EXISTS brought_by_name VARCHAR(255) NULL;
