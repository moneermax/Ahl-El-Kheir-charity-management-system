-- Ahl El Kheir Charity Management System
-- HR Payroll Policy Foundation - application-level hardening.
--
-- IMPORTANT:
-- Payroll-policy business rules are intentionally enforced by PHP, not MySQL
-- triggers, because the production hosting environment must remain portable
-- and database imports must not depend on trigger support.
--
-- The policy UI validates creation rules in modules/hr/payroll_policy.php and
-- lib_payroll_policy.php. Payroll generation resolves and stores the policy
-- version used for the payroll period in modules/hr/payroll.php.
--
-- This migration deliberately creates NO triggers and NO views.
-- It only removes trigger objects left by earlier local/test migrations.

SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_hr_payroll_policy_validate_insert;
DROP TRIGGER IF EXISTS trg_hr_payroll_policy_validate_update;
DROP TRIGGER IF EXISTS trg_hr_payroll_policy_no_delete_effective;
DROP TRIGGER IF EXISTS trg_hr_payroll_policy_no_update_effective;

-- No CREATE TRIGGER statements belong in the production schema.
