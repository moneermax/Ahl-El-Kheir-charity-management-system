-- Existing installations may have created the policy table before working_days was added to the application contract.
-- Keep this as a migration; never add the column at runtime.
ALTER TABLE hr_attendance_policy_versions
    ADD COLUMN IF NOT EXISTS working_days VARCHAR(13) NOT NULL DEFAULT '1,2,3,4,5' AFTER working_end_time;
