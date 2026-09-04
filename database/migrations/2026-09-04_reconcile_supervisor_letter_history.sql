-- Reconcile supervisor_letter_assignment_history after the lifecycle schema was deployed.
-- Safe for existing data: no table drop, no FK_CHECKS bypass, no history deletion.
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

-- 3) Remove every existing FK on supervisor_letter_id -> supervisor_letters.id.
-- This eliminates both the known duplicate dump constraints and any equivalent
-- constraint left by a previous repair attempt. No data is removed.
SET @drop_slah_supervisor_letter_fks = (
    SELECT COALESCE(
        GROUP_CONCAT(
            CONCAT('DROP FOREIGN KEY `', CONSTRAINT_NAME, '`')
            ORDER BY CONSTRAINT_NAME
            SEPARATOR ', '
        ),
        'DROP FOREIGN KEY `__no_slah_supervisor_letter_fk__`'
    )
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'supervisor_letter_assignment_history'
      AND COLUMN_NAME = 'supervisor_letter_id'
      AND REFERENCED_TABLE_NAME = 'supervisor_letters'
      AND REFERENCED_COLUMN_NAME = 'id'
);

-- If no FK exists, use a harmless statement instead of executing a fake DROP.
SET @drop_slah_supervisor_letter_fks = IF(
    @drop_slah_supervisor_letter_fks = 'DROP FOREIGN KEY `__no_slah_supervisor_letter_fk__`',
    'SELECT 1',
    CONCAT('ALTER TABLE `supervisor_letter_assignment_history` ', @drop_slah_supervisor_letter_fks)
);

PREPARE stmt_drop_slah_supervisor_letter_fks FROM @drop_slah_supervisor_letter_fks;
EXECUTE stmt_drop_slah_supervisor_letter_fks;
DEALLOCATE PREPARE stmt_drop_slah_supervisor_letter_fks;

-- 4) Recreate exactly one canonical FK for the current supervisor-letter reference.
ALTER TABLE supervisor_letter_assignment_history
    ADD CONSTRAINT fk_slah_supervisor_letter
        FOREIGN KEY (supervisor_letter_id)
        REFERENCES supervisor_letters(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- 5) Ensure the supporting index exists. The FK can use this index; the conditional
-- creation avoids an unnecessary duplicate index on servers where it already exists.
SET @slah_supervisor_letter_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'supervisor_letter_assignment_history'
      AND INDEX_NAME = 'idx_slah_supervisor_letter'
);

SET @add_slah_supervisor_letter_index = IF(
    @slah_supervisor_letter_index_exists = 0,
    'ALTER TABLE `supervisor_letter_assignment_history` ADD INDEX `idx_slah_supervisor_letter` (`supervisor_letter_id`)',
    'SELECT 1'
);

PREPARE stmt_add_slah_supervisor_letter_index FROM @add_slah_supervisor_letter_index;
EXECUTE stmt_add_slah_supervisor_letter_index;
DEALLOCATE PREPARE stmt_add_slah_supervisor_letter_index;

-- 6) Final verification query. It is intentionally read-only.
SELECT
    k.CONSTRAINT_NAME,
    k.COLUMN_NAME,
    k.REFERENCED_TABLE_NAME,
    k.REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE k
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.TABLE_NAME = 'supervisor_letter_assignment_history'
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY k.CONSTRAINT_NAME;
