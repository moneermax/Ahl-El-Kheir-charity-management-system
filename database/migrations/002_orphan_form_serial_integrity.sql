-- Ahl El Kheir Charity Management System
-- Migration: orphan form serial integrity
-- Purpose:
--   1. Backfill missing family_children.form_serial values.
--   2. Prevent duplicate form serials at the database level.
--   3. Keep serial generation in the application creation workflow.
--
-- Existing serial format is preserved: AK-00001, AK-00002, ...
-- Missing values are allocated in family_children.id order, starting after
-- the highest existing valid AK-* value.
--
-- IMPORTANT:
-- Migration 003 contains the compatible family_code UPDATE trigger. Apply
-- migration 003 before this migration on databases that already contain the
-- family-code integrity trigger. This prevents unrelated child updates from
-- being rejected because a legacy family_code is stored on the family row.

SET @old_sql_mode := @@sql_mode;
SET sql_mode = CONCAT_WS(',', @@sql_mode, 'NO_AUTO_VALUE_ON_ZERO');

/* Ensure the column exists. */
ALTER TABLE family_children
    ADD COLUMN IF NOT EXISTS form_serial VARCHAR(20) NULL;

/* Normalize accidental empty strings to NULL. */
UPDATE family_children
SET form_serial = NULL
WHERE form_serial IS NOT NULL
  AND TRIM(form_serial) = '';

/*
   Backfill only missing serials.
   Existing AK-* values are never changed.
*/
SET @serial_row := (
    SELECT COALESCE(MAX(CAST(SUBSTRING(form_serial, 4) AS UNSIGNED)), 0)
    FROM family_children
    WHERE form_serial REGEXP '^AK-[0-9]+$'
);

UPDATE family_children
SET form_serial = CONCAT(
    'AK-',
    LPAD((@serial_row := @serial_row + 1), 5, '0')
)
WHERE form_serial IS NULL
ORDER BY id;

/* Verify duplicate groups before adding the unique key. */
SET @duplicate_count := (
    SELECT COUNT(*)
    FROM (
        SELECT form_serial
        FROM family_children
        WHERE form_serial IS NOT NULL
        GROUP BY form_serial
        HAVING COUNT(*) > 1
    ) AS duplicate_serials
);

SELECT @duplicate_count AS duplicate_form_serial_groups;

/* Add the database-level uniqueness guarantee if it is not already present. */
SET @unique_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'family_children'
      AND index_name = 'uq_family_children_form_serial'
);

SET @add_unique_sql := IF(
    @duplicate_count = 0 AND @unique_index_exists = 0,
    'ALTER TABLE family_children ADD UNIQUE KEY uq_family_children_form_serial (form_serial)',
    'SELECT 1'
);

PREPARE stmt_unique FROM @add_unique_sql;
EXECUTE stmt_unique;
DEALLOCATE PREPARE stmt_unique;

SET sql_mode = @old_sql_mode;

/* Final verification. */
SELECT
    COUNT(*) AS total_children,
    SUM(CASE WHEN form_serial IS NULL OR TRIM(form_serial) = '' THEN 1 ELSE 0 END) AS missing_form_serials,
    COUNT(DISTINCT form_serial) AS distinct_form_serials,
    MAX(CASE
        WHEN form_serial REGEXP '^AK-[0-9]+$'
        THEN CAST(SUBSTRING(form_serial, 4) AS UNSIGNED)
        ELSE NULL
    END) AS highest_serial_number
FROM family_children;
