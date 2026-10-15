-- Permit the GM to withdraw an unactivated incentive-policy approval back to HR draft.
-- Approval history is retained; no active policy can be returned to draft.
SET @ak_action_type = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_incentive_policy_approval_history'
      AND COLUMN_NAME = 'action'
    LIMIT 1
);
SET @ak_sql = IF(
    @ak_action_type IS NOT NULL
    AND LOCATE("'returned_to_draft'", @ak_action_type) = 0,
    "ALTER TABLE hr_incentive_policy_approval_history MODIFY action ENUM('submitted','approved','rejected','returned_to_draft') NOT NULL",
    'SELECT 1'
);
PREPARE ak_stmt FROM @ak_sql;
EXECUTE ak_stmt;
DEALLOCATE PREPARE ak_stmt;
