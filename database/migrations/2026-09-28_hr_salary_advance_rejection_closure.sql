-- HR Salary Advance Stage 4: close FM-rejected requests as terminal records.
-- Schema-only migration. No runtime DDL, triggers, or views.

ALTER TABLE hr_salary_advance_requests
    ADD COLUMN closed_at DATETIME NULL
        AFTER fm_reviewed_at,
    ADD KEY idx_hr_salary_advance_closed_at (closed_at);

-- Preserve existing rejected requests as closed historical records.
UPDATE hr_salary_advance_requests
SET closed_at = COALESCE(fm_reviewed_at, updated_at, NOW())
WHERE status = 'rejected'
  AND closed_at IS NULL;
