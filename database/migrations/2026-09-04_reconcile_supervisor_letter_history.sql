-- Reconcile supervisor_letter_assignment_history after the lifecycle schema was deployed.
-- Safe for existing data: no table drop, no FK_CHECKS bypass, no history deletion.
--
-- This repair targets the two duplicate FK names found in the database dump:
--   fk_hist_sl_supervisor_letter_2026
--   fk_slah_supervisor_letter
--
-- Final relationship model:
--   supervisor_id           -> users.id              RESTRICT
--   letter_id               -> letters.id            RESTRICT
--   assigned_by             -> users.id              SET NULL
--   ended_by                -> users.id              SET NULL
--   supervisor_letter_id    -> supervisor_letters.id SET NULL
--
-- The last relationship is intentionally nullable because supervisor_letters is the
-- current assignment table and its rows are removed when a supervisor permanently departs.
--
-- IMPORTANT: This version deliberately does not query information_schema. Some local
-- phpMyAdmin/MariaDB root configurations reject direct access to the information_schema
-- database even though normal ALTER/SELECT operations on the application database work.

-- 1) Ensure the history columns have the final compatible types.
ALTER TABLE supervisor_letter_assignment_history
    MODIFY COLUMN supervisor_letter_id INT UNSIGNED NULL,
    MODIFY COLUMN supervisor_id INT UNSIGNED NOT NULL,
    MODIFY COLUMN letter_id TINYINT UNSIGNED NOT NULL,
    MODIFY COLUMN assigned_by INT UNSIGNED NULL,
    MODIFY COLUMN ended_by INT UNSIGNED NULL;

-- 2) Existing history can legitimately point to a supervisor_letter row that has
-- already been released/deleted. Preserve the historical record and null only that
-- obsolete current-assignment reference before adding the FK.
UPDATE supervisor_letter_assignment_history h
LEFT JOIN supervisor_letters sl ON sl.id = h.supervisor_letter_id
SET h.supervisor_letter_id = NULL
WHERE h.supervisor_letter_id IS NOT NULL
  AND sl.id IS NULL;

-- 3) Remove the duplicate FK constraints known to exist in the supplied database dump.
-- Do not use FOREIGN_KEY_CHECKS=0 and do not drop/recreate the history table.
ALTER TABLE supervisor_letter_assignment_history
    DROP FOREIGN KEY fk_hist_sl_supervisor_letter_2026,
    DROP FOREIGN KEY fk_slah_supervisor_letter;

-- 4) Recreate exactly one canonical FK for the current supervisor-letter reference.
-- InnoDB will create/use the required supporting index for this FK if needed.
ALTER TABLE supervisor_letter_assignment_history
    ADD CONSTRAINT fk_slah_supervisor_letter
        FOREIGN KEY (supervisor_letter_id)
        REFERENCES supervisor_letters(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- 5) Final verification query. It is intentionally read-only and runs against the
-- currently selected application database.
SHOW CREATE TABLE supervisor_letter_assignment_history;
