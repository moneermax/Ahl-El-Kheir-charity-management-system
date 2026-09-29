-- Ahl El Kheir Charity Management System
-- Remove legacy payroll database triggers.
--
-- Payroll -> accounting and payroll immutability are now implemented
-- explicitly in procedural PHP. The project does not use MySQL triggers.
-- This migration removes legacy triggers that may still exist in databases
-- created from older schema/database exports.

SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_payroll_accounting_before_update;
DROP TRIGGER IF EXISTS trg_payroll_immutable_before_update;
