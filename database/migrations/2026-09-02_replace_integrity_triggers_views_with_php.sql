-- Ahl El Kheir
-- 2026-09-02: Move family/child/supervisor integrity rules from MySQL
-- triggers/views to explicit procedural PHP application logic.
--
-- Safe to run more than once.
-- IMPORTANT: never disable FOREIGN_KEY_CHECKS.

START TRANSACTION;

-- Reconcile the cached child count once before removing trigger maintenance.
UPDATE families AS f
SET f.children_count = (
    SELECT COUNT(*)
    FROM family_children AS fc
    WHERE fc.family_id = f.id
);

-- Application-owned PHP replacements now live in config/data_integrity.php.
DROP TRIGGER IF EXISTS trg_families_family_code_ai;
DROP TRIGGER IF EXISTS trg_families_family_code_bu;
DROP TRIGGER IF EXISTS trg_family_children_ai;
DROP TRIGGER IF EXISTS trg_family_children_au;
DROP TRIGGER IF EXISTS trg_family_children_ad;
DROP TRIGGER IF EXISTS trg_supervisor_letters_ai;
DROP TRIGGER IF EXISTS trg_supervisor_letters_au;
DROP TRIGGER IF EXISTS trg_supervisor_letters_ad;

-- These legacy read models are not referenced by the current PHP code.
-- Current report/supervisor pages use explicit PHP queries.
DROP VIEW IF EXISTS vw_families_without_sponsorship;
DROP VIEW IF EXISTS vw_sponsor_current_supervisor;
DROP VIEW IF EXISTS vw_supervisor_sponsors;

COMMIT;
