-- Payroll/attendance integration
-- Attendance determines attendance facts; payroll policy determines monetary treatment.
-- No runtime DDL, triggers, views, procedures, or functions.

ALTER TABLE payroll
    ADD COLUMN attendance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER deductions;

ALTER TABLE hr_attendance_policy_versions
    ADD COLUMN working_days VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5' AFTER working_end_time;
