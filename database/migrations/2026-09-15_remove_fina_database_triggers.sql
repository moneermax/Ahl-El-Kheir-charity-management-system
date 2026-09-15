-- AHL EL KHEIR
-- Remove the previously introduced Fina database triggers.
--
-- Project rule: database triggers and database views are not permitted.
-- Fina protection and workflow validation are enforced in procedural PHP.
-- This one-time cleanup is required for databases where the earlier Fina
-- migration was already imported before the rule was formalized.

DROP TRIGGER IF EXISTS trg_fina_protect_disbursement_transfer;
DROP TRIGGER IF EXISTS trg_fina_validate_disbursement_transaction;
