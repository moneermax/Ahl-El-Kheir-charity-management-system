-- AHL EL KHEIR
-- Preserve the specific legal/entity classification for Tree sponsors without
-- expanding sponsors.sponsor_type beyond its existing company/organization values.
-- Apply through the migration process only. Never execute DDL at runtime.

ALTER TABLE sponsors
    ADD COLUMN IF NOT EXISTS legal_entity_category VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS legal_entity_category_other VARCHAR(150) DEFAULT NULL;
