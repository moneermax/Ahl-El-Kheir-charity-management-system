-- Dedicated employee incentives payable account.
-- This migration only establishes the chart-of-accounts entry.
-- Award recognition, payroll settlement, and award-level journal linkage are separate steps.
-- 2400 follows the existing liability account series (2100, 2200, 2300) and is
-- reserved here for earned employee incentives payable; it must not be reused
-- for general payables or Fina Al-Khair liabilities.
INSERT INTO accounts (code, name_ar, name_en, account_type, parent_id, is_active, description)
SELECT '2400', 'مستحقات حوافز الموظفين', 'Employee Incentives Payable', 'liability', NULL, 1,
       'Dedicated liability for earned employee incentives awaiting payroll settlement'
WHERE NOT EXISTS (
    SELECT 1 FROM accounts WHERE code = '2400'
);
