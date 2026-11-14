-- VGM group names are globally unique (case/accent-insensitive under the
-- table's utf8mb4_unicode_ci collation).
--
-- Preflight: this query must return zero rows before applying the ALTER.
-- Resolve any reported duplicates deliberately; do not silently rename data.
SELECT group_name, COUNT(*) AS duplicate_count
FROM orphan_groups
GROUP BY group_name
HAVING COUNT(*) > 1;

ALTER TABLE orphan_groups
    ADD UNIQUE KEY uq_orphan_groups_group_name (group_name);
