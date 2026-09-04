-- Ahl El Kheir - Orphan Groups Lifecycle Improvement
-- Run once in phpMyAdmin before testing modules/deputy_gm/groups.php.
-- This migration is intentionally non-destructive: existing group/member/nanny/payment history is preserved.

ALTER TABLE orphan_groups
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER is_active,
    ADD COLUMN lifecycle_reason VARCHAR(500) NULL AFTER status,
    ADD COLUMN updated_at DATETIME NULL AFTER lifecycle_reason,
    ADD COLUMN closed_at DATETIME NULL AFTER updated_at,
    ADD COLUMN closed_by INT NULL AFTER closed_at;

-- Preserve the meaning of existing records:
-- active groups remain operational; previously deactivated groups become closed.
UPDATE orphan_groups
SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'closed' END,
    updated_at = COALESCE(updated_at, created_at)
WHERE status = 'draft';

-- Existing active groups that currently have no members become explicitly empty.
UPDATE orphan_groups og
SET og.status = 'empty'
WHERE og.status = 'active'
  AND NOT EXISTS (
      SELECT 1 FROM group_children gc
      WHERE gc.group_id = og.id AND gc.left_date IS NULL
  );

-- Keep the legacy flag synchronized for older parts of the application.
UPDATE orphan_groups
SET is_active = CASE WHEN status IN ('active', 'empty') THEN 1 ELSE 0 END;

-- Helpful indexes for lifecycle filtering and ordering.
ALTER TABLE orphan_groups
    ADD INDEX idx_orphan_groups_status (status),
    ADD INDEX idx_orphan_groups_updated_at (updated_at),
    ADD INDEX idx_orphan_groups_closed_by (closed_by);
