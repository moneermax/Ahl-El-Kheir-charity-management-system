-- Ahl El Kheir - Orphan Groups Lifecycle Improvement
-- Safe to run once even when one or more lifecycle columns already exist.
-- Non-destructive: existing group/member/nanny/payment history is preserved.

ALTER TABLE orphan_groups
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER is_active,
    ADD COLUMN IF NOT EXISTS lifecycle_reason VARCHAR(500) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL AFTER lifecycle_reason,
    ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS closed_by INT NULL AFTER closed_at;

-- Preserve the meaning of existing records.
UPDATE orphan_groups
SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'closed' END,
    updated_at = COALESCE(updated_at, created_at)
WHERE status = 'draft';

-- Existing active groups with no current members become explicitly empty.
UPDATE orphan_groups og
SET og.status = 'empty'
WHERE og.status = 'active'
  AND NOT EXISTS (
      SELECT 1 FROM group_children gc
      WHERE gc.group_id = og.id AND gc.left_date IS NULL
  );

-- Keep the legacy flag synchronized for older application code.
UPDATE orphan_groups
SET is_active = CASE WHEN status IN ('active', 'empty') THEN 1 ELSE 0 END;

-- Helpful indexes. IF NOT EXISTS prevents duplicate-index failures on reruns.
ALTER TABLE orphan_groups
    ADD INDEX IF NOT EXISTS idx_orphan_groups_status (status),
    ADD INDEX IF NOT EXISTS idx_orphan_groups_updated_at (updated_at),
    ADD INDEX IF NOT EXISTS idx_orphan_groups_closed_by (closed_by);
