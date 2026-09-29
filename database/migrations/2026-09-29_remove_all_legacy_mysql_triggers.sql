-- Ahl El Kheir Charity Management System
-- Remove all legacy MySQL triggers from the database.
--
-- The project architecture is procedural PHP and does not use database
-- triggers. Business validation, workflow, accounting, and lifecycle logic
-- must be enforced explicitly by the application.
--
-- This migration removes triggers that may still exist in databases created
-- from older schema/database exports.

SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_attendance_employment_state_bi;
DROP TRIGGER IF EXISTS trg_attendance_employment_state_bu;

DROP TRIGGER IF EXISTS trg_employees_employment_state_ai;
DROP TRIGGER IF EXISTS trg_employees_employment_state_bi;
DROP TRIGGER IF EXISTS trg_employees_employment_state_bu;
DROP TRIGGER IF EXISTS trg_employees_salary_history_sync_ai;
DROP TRIGGER IF EXISTS trg_employees_salary_history_sync_au;

DROP TRIGGER IF EXISTS trg_hr_payroll_policy_no_delete_effective;
DROP TRIGGER IF EXISTS trg_hr_payroll_policy_no_update_effective;

DROP TRIGGER IF EXISTS trg_leaves_validate_insert;
DROP TRIGGER IF EXISTS trg_leaves_validate_update;

DROP TRIGGER IF EXISTS trg_payroll_accounting_before_update;
DROP TRIGGER IF EXISTS trg_payroll_immutable_before_update;
