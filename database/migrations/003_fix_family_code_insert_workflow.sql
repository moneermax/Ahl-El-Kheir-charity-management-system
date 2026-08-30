-- Ahl El Kheir - Fix family-code insert workflow
-- Replaces the strict BEFORE INSERT trigger from migration 001.
-- The application may INSERT a family before its AUTO_INCREMENT id is known.

ALTER TABLE families
    MODIFY COLUMN family_code VARCHAR(50) NULL;

DROP TRIGGER IF EXISTS trg_families_family_code_bi;
DROP TRIGGER IF EXISTS trg_families_family_code_bu;
DROP TRIGGER IF EXISTS trg_families_family_code_ai;

DELIMITER $$

CREATE TRIGGER trg_families_family_code_ai
AFTER INSERT ON families
FOR EACH ROW
BEGIN
    IF NEW.family_code IS NULL OR TRIM(NEW.family_code) = '' THEN
        UPDATE families
        SET family_code = CONCAT('IMP-FAM-', LPAD(NEW.id, 6, '0'))
        WHERE id = NEW.id;
    END IF;
END$$

CREATE TRIGGER trg_families_family_code_bu
BEFORE UPDATE ON families
FOR EACH ROW
BEGIN
    SET NEW.family_code = NULLIF(TRIM(NEW.family_code), '');

    IF NEW.family_code IS NULL THEN
        SET NEW.family_code = CONCAT('IMP-FAM-', LPAD(OLD.id, 6, '0'));
    END IF;

    IF NEW.family_code REGEXP '^FAM-[0-9]{6}$' THEN
        SET NEW.family_code = CONCAT('IMP-FAM-', SUBSTRING(NEW.family_code, 5));
    END IF;

    IF NEW.family_code NOT REGEXP '^IMP-FAM-[0-9]{6}$' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid family_code. Expected IMP-FAM-######';
    END IF;
END$$

DELIMITER ;

UPDATE families
SET family_code = CONCAT('IMP-FAM-', LPAD(id, 6, '0'))
WHERE family_code IS NULL OR TRIM(family_code) = '';

SELECT
    COUNT(*) AS total_families,
    SUM(CASE WHEN family_code IS NULL OR TRIM(family_code) = '' THEN 1 ELSE 0 END) AS missing_codes,
    SUM(CASE WHEN family_code REGEXP '^IMP-FAM-[0-9]{6}$' THEN 1 ELSE 0 END) AS valid_codes
FROM families;
