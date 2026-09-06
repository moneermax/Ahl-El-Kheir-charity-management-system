-- Ahl El Kheir Charity Management System
-- System monetary currency: Sudanese Pound (SDG)
-- Safe for the HR historical contract/salary foundation.

SET NAMES utf8mb4;

ALTER TABLE hr_employee_contracts
    ALTER COLUMN salary_currency SET DEFAULT 'SDG';

ALTER TABLE hr_employee_salary_history
    ALTER COLUMN salary_currency SET DEFAULT 'SDG';

UPDATE hr_employee_contracts
SET salary_currency = 'SDG'
WHERE salary_currency IS NULL OR salary_currency <> 'SDG';

UPDATE hr_employee_salary_history
SET salary_currency = 'SDG'
WHERE salary_currency IS NULL OR salary_currency <> 'SDG';
