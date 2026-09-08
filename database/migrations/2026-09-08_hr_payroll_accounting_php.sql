-- Ahl El Kheir Charity Management System
-- Payroll accounting PHP integration
--
-- The payroll accounting lifecycle is implemented in procedural PHP.
-- This migration only establishes the required data columns.
-- It intentionally does NOT create any MySQL triggers.

SET NAMES utf8mb4;

ALTER TABLE payroll
    ADD COLUMN IF NOT EXISTS accounting_status ENUM('none','ready','posted') NOT NULL DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS accounting_entry_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS payment_account_id INT UNSIGNED NULL;

CREATE INDEX IF NOT EXISTS idx_payroll_accounting_entry
    ON payroll (accounting_entry_id);

SELECT
    COUNT(*) AS payroll_rows,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_rows,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_rows
FROM payroll;
