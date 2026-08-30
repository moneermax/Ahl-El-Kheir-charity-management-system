-- Ahl El Kheir Charity Management System
-- Migration: HR password recovery workflow
-- Purpose:
--   1. Ensure the password recovery request table exists.
--   2. Keep recovery requests separate from the users table.
--   3. Support HR approval, temporary password issuance, and completion.
--
-- The application stores only a password hash in users. The temporary
-- password itself is shown to HR once and is never stored in plain text.

CREATE TABLE IF NOT EXISTS password_recovery_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('pending','approved','completed','rejected','expired') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_prr_user_status (user_id, status),
    KEY idx_prr_status (status),
    KEY idx_prr_reviewed_by (reviewed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*
 * These statements are intentionally independent of the existing application
 * triggers. No trigger is created by this migration.
 *
 * If the table already exists, CREATE TABLE IF NOT EXISTS leaves it untouched.
 * The application requires the columns above; verify the existing table before
 * applying this migration to an older database that already has a custom
 * password_recovery_requests definition.
 */
