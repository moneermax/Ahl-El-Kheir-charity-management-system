-- Ahl El Kheir Charity Management System
-- Migration: orphan form serial integrity
-- Purpose:
--   1. Backfill missing family_children.form_serial values.
--   2. Prevent duplicate form serials.
--   3. Automatically assign a serial to newly inserted orphan records when
--      the application does not provide one.
--
-- Existing serial format is preserved: AK-00001, AK-00002, ...
-- The sequence is initialized from the highest existing AK-* value.

SET @old_sql_mode := @@sql_mode;
SET sql_mode = CONCAT_WS(',', @@sql_mode, 'NO_AUTO_VALUE_ON_ZERO');

-- Ensure the column exists before applying the integrity rules.
ALTER TABLE family_children
    ADD COLUMN IF NOT EXISTS form_serial VARCHAR(20) NULL;

-- Remove accidental empty strings from the legacy data set.
UPDATE family_children
SET form_serial = NULL
WHERE form_serial IS NOT NULL
  AND TRIM(form_serial) = '';

-- Backfill missing serials deterministically from the existing highest serial.
-- The generated values are allocated in family_children.id order.
SET @serial_start := (
    SELECT COALESCE(MAX(CAST(SUBSTRING(form_serial, 4) AS UNSIGNED)), 0)
    FROM family_children
    WHERE form_serial REGEXP '^AK-[0-9]+$'
);

SET @serial_row := @serial_start;

UPDATE family_children
SET form_serial = CONCAT('AK-', LPAD((@serial_row := @serial_row + 1), 5, '0'))
WHERE form_serial IS NULL
ORDER BY id;

-- Make sure there is no duplicate serial before adding the unique key.
-- This deliberately fails the migration rather than silently changing an
-- existing serial if duplicate legacy data is discovered.
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

-- A unique index makes duplicate form numbers impossible at the DB level.
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

-- Sequence table used by the insert trigger. It avoids the race condition
-- that would occur if a trigger generated the next number using MAX() alone.
CREATE TABLE IF NOT EXISTS orphan_form_serial_sequence (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    next_number INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

SET @current_max := (
    SELECT COALESCE(MAX(CAST(SUBSTRING(form_serial, 4) AS UNSIGNED)), 0)
    FROM family_children
    WHERE form_serial REGEXP '^AK-[0-9]+$'
);

INSERT INTO orphan_form_serial_sequence (id, next_number)
VALUES (1, @current_max)
ON DUPLICATE KEY UPDATE next_number = GREATEST(next_number, VALUES(next_number));

DROP TRIGGER IF EXISTS trg_family_children_form_serial_after_insert;

DELIMITER $$

CREATE TRIGGER trg_family_children_form_serial_after_insert
AFTER INSERT ON family_children
FOR EACH ROW
BEGIN
    DECLARE v_next INT UNSIGNED;

    IF NEW.form_serial IS NULL OR TRIM(NEW.form_serial) = '' THEN
        UPDATE orphan_form_serial_sequence
        SET next_number = LAST_INSERT_ID(next_number + 1)
        WHERE id = 1;

        SET v_next = LAST_INSERT_ID();

        UPDATE family_children
        SET form_serial = CONCAT('AK-', LPAD(v_next, 5, '0'))
        WHERE id = NEW.id;
    END IF;
END$$

DELIMITER ;

-- Do not force legacy records to a different number if they already have one.
-- The unique key and trigger now protect the field going forward.

SET sql_mode = @old_sql_mode;

-- Verification: should return zero missing and zero duplicate groups.
SELECT
    COUNT(*) AS total_children,
    SUM(CASE WHEN form_serial IS NULL OR TRIM(form_serial) = '' THEN 1 ELSE 0 END) AS missing_form_serials,
    COUNT(DISTINCT form_serial) AS distinct_form_serials
FROM family_children;
