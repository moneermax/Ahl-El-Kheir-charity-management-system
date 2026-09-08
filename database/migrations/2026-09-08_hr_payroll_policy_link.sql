-- Ahl El Kheir Charity Management System
-- Stage 2: link payroll records to the effective payroll policy version.
-- Existing payroll rows remain valid and are intentionally left NULL.
-- New payroll generation must resolve and record the policy effective at period end.

SET NAMES utf8mb4;

ALTER TABLE payroll
    ADD COLUMN IF NOT EXISTS payroll_policy_version_id BIGINT UNSIGNED NULL AFTER status;

ALTER TABLE payroll
    ADD INDEX IF NOT EXISTS idx_payroll_policy_version (payroll_policy_version_id);

-- Do not backfill historical payroll automatically.
-- In particular, the verified September 2026 payroll remains unchanged.
