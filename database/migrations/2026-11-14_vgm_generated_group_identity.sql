-- Add immutable, database-ID-derived codes to orphan groups.
-- Existing group names are intentionally preserved; only new groups receive
-- the generated display-name convention from the VGM creation workflow.

ALTER TABLE orphan_groups
    ADD COLUMN group_code VARCHAR(32) NULL AFTER group_name;

UPDATE orphan_groups
SET group_code = CONCAT('GRP-', LPAD(id, 6, '0'))
WHERE group_code IS NULL OR TRIM(group_code) = '';

ALTER TABLE orphan_groups
    MODIFY group_code VARCHAR(32) NOT NULL,
    ADD UNIQUE KEY uq_orphan_groups_group_code (group_code);
