-- System-generated project-completion allocations do not have a human approver.
-- Keep the columns for legacy/manual historical rows, but allow NULL for truthful
-- automated allocations. No runtime DDL; apply once to the local ahl_el_kheir DB.
ALTER TABLE hr_incentive_project_allocations
    MODIFY COLUMN approved_by_user_id INT UNSIGNED NULL,
    MODIFY COLUMN approved_at DATETIME NULL;
