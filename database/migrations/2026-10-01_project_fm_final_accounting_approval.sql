-- 2026-10-01_project_fm_final_accounting_approval.sql
-- Phase 5: separate FM financial approval from the final accounting release.
-- No runtime DDL. Apply this migration once before using the new FM final-approval action.

ALTER TABLE project_approval
    ADD COLUMN fm_accounting_approved_by INT NULL AFTER fm_reviewed_at,
    ADD COLUMN fm_accounting_approved_at DATETIME NULL AFTER fm_accounting_approved_by;

CREATE INDEX idx_project_approval_fm_accounting_approved
    ON project_approval (fm_accounting_approved_at);
