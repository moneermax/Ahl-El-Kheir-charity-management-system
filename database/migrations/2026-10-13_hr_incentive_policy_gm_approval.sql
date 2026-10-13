-- GM approval workflow for employee incentive policy versions.
-- Legacy agreement columns and their historical values are intentionally preserved.
-- Safe to rerun after a partial application; all DDL remains migration-only.

SET @ak_policy_status_type = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_incentive_policy_versions'
      AND COLUMN_NAME = 'status'
    LIMIT 1
);
SET @ak_sql = IF(
    @ak_policy_status_type IS NOT NULL
    AND (
        LOCATE("'pending_gm'", @ak_policy_status_type) = 0
        OR LOCATE("'approved'", @ak_policy_status_type) = 0
    ),
    "ALTER TABLE hr_incentive_policy_versions MODIFY status ENUM('draft','pending_gm','approved','active','retired') NOT NULL DEFAULT 'draft'",
    'SELECT 1'
);
PREPARE ak_stmt FROM @ak_sql;
EXECUTE ak_stmt;
DEALLOCATE PREPARE ak_stmt;

CREATE TABLE IF NOT EXISTS hr_incentive_policy_approval_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_version_id INT UNSIGNED NOT NULL,
    action ENUM('submitted','approved','rejected') NOT NULL,
    actor_user_id INT UNSIGNED NOT NULL,
    reason VARCHAR(2000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hr_incentive_approval_policy_time (policy_version_id, created_at, id),
    KEY idx_hr_incentive_approval_actor (actor_user_id),
    CONSTRAINT fk_hr_incentive_approval_policy
        FOREIGN KEY (policy_version_id) REFERENCES hr_incentive_policy_versions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_hr_incentive_approval_actor
        FOREIGN KEY (actor_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
