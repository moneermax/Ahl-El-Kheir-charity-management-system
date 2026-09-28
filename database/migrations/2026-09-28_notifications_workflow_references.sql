-- Notification workflow references
-- Adds stable workflow references used by notification routing.
-- Schema-only migration. No runtime DDL, triggers, or views.

ALTER TABLE notifications
    ADD COLUMN reference_id INT UNSIGNED NULL AFTER link,
    ADD COLUMN reference_type VARCHAR(100) NULL AFTER reference_id,
    ADD KEY idx_notifications_reference (reference_type, reference_id);
