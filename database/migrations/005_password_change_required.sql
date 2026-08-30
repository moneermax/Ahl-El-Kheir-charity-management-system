-- Ahl El Kheir Charity Management System
-- Migration: Forced password change flag
-- Purpose:
--   Store whether a user must replace a temporary/recovery password.
--
-- Required by:
--   modules/users/recovery.php
--   modules/users/change_password.php
--
-- Safe for the current schema: the column is added only if it does not exist.

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'password_change_required'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE users ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
