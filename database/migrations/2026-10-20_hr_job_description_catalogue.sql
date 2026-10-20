-- Role-specific job description catalogue for the HR employment-contract portal.
-- Run once through the project's normal migration process; never run DDL from PHP.
CREATE TABLE IF NOT EXISTS hr_job_description_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    position_title VARCHAR(180) NOT NULL,
    role_purpose TEXT NULL,
    responsibilities TEXT NULL,
    qualifications TEXT NULL,
    reporting_to VARCHAR(180) NULL,
    role_specific_terms TEXT NULL,
    description_status ENUM('needs_review','approved') NOT NULL DEFAULT 'needs_review',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_job_description_position (position_title),
    KEY idx_hr_job_description_active_status (is_active, description_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the current employee position inventory without inventing duties.
-- Every new row remains visibly marked as needing HR review until HR completes it.
INSERT INTO hr_job_description_templates
    (position_title, description_status, is_active, created_at, updated_at)
SELECT DISTINCT TRIM(e.position), 'needs_review', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM employees e
WHERE e.position IS NOT NULL
  AND TRIM(e.position) <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM hr_job_description_templates j
      WHERE j.position_title = TRIM(e.position)
  );
