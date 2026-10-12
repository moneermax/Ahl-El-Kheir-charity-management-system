-- Employee incentives expense account.
-- This migration only establishes the approved chart-of-accounts entry.
-- Incentive policy/award tables and payroll integration are separate implementation steps.
INSERT INTO accounts (code, name_ar, name_en, account_type, parent_id, is_active)
SELECT '5500', 'مصروف حوافز الموظفين', 'Employee Incentives Expense', 'expense', NULL, 1
WHERE NOT EXISTS (
    SELECT 1 FROM accounts WHERE code = '5500'
);
