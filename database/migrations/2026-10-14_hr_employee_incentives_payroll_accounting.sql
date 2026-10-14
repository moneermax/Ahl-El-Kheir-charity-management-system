-- Keep employee incentives separate from salary expense and salary advances.
-- Payroll stores the incentive payout component separately; award records remain
-- the authoritative per-employee/per-event detail.
ALTER TABLE payroll
    ADD COLUMN incentive_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00
    AFTER salary_advance_deduction;

-- An award has two distinct accounting stages: recognize earned expense/payable,
-- then clear the payable when the award is paid through payroll.
ALTER TABLE hr_incentive_awards
    CHANGE COLUMN journal_entry_id recognition_journal_entry_id INT UNSIGNED NULL,
    ADD COLUMN settlement_journal_entry_id INT UNSIGNED NULL AFTER recognition_journal_entry_id,
    DROP INDEX idx_hr_incentive_award_journal,
    ADD KEY idx_hr_incentive_award_recognition_journal (recognition_journal_entry_id),
    ADD KEY idx_hr_incentive_award_settlement_journal (settlement_journal_entry_id);
