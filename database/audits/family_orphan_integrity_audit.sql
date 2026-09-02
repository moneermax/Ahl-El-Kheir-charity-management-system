-- Ahl El Kheir - Family / Orphan Relationship Audit
-- DIAGNOSTIC ONLY. This file intentionally contains NO INSERT, UPDATE, DELETE,
-- ALTER, DROP, TRUNCATE, or FOREIGN_KEY_CHECKS changes.
-- Run against the live application database in phpMyAdmin.

-- 1) High-level relationship totals.
SELECT
    (SELECT COUNT(*) FROM families) AS total_families,
    (SELECT COUNT(*) FROM family_children) AS total_family_children,
    (SELECT COUNT(DISTINCT family_id) FROM family_children) AS families_with_children,
    (SELECT COUNT(*)
     FROM families f
     LEFT JOIN family_children fc ON fc.family_id = f.id
     WHERE fc.id IS NULL) AS orphanless_families;

-- 2) Families that genuinely have no family_children relationship.
SELECT
    f.id,
    f.family_code,
    f.mother_name,
    f.status,
    f.children_count AS cached_children_count
FROM families f
LEFT JOIN family_children fc ON fc.family_id = f.id
WHERE fc.id IS NULL
ORDER BY f.id;

-- 3) Compare the cached families.children_count with the authoritative
--    family_children relationship. Any returned row is a cache mismatch.
SELECT
    f.id,
    f.family_code,
    f.mother_name,
    f.children_count AS cached_children_count,
    COUNT(fc.id) AS actual_children_count
FROM families f
LEFT JOIN family_children fc ON fc.family_id = f.id
GROUP BY f.id, f.family_code, f.mother_name, f.children_count
HAVING f.children_count <> COUNT(fc.id)
ORDER BY f.id;

-- 4) Families where the cached count is positive but the authoritative
--    relationship contains no child. These require investigation; do NOT
--    fabricate child records from the cache.
SELECT
    f.id,
    f.family_code,
    f.mother_name,
    f.children_count AS cached_children_count
FROM families f
WHERE COALESCE(f.children_count, 0) > 0
  AND NOT EXISTS (
      SELECT 1
      FROM family_children fc
      WHERE fc.family_id = f.id
  )
ORDER BY f.id;

-- 5) Orphan relationships pointing to a non-existent family (should be zero).
SELECT
    fc.id AS family_child_id,
    fc.family_id
FROM family_children fc
LEFT JOIN families f ON f.id = fc.family_id
WHERE f.id IS NULL
ORDER BY fc.id;

-- 6) Sponsorships whose child/orphan does not exist (should be zero).
SELECT
    sp.id AS sponsorship_id,
    sp.sponsorship_code,
    sp.child_id
FROM sponsorships sp
LEFT JOIN family_children fc ON fc.id = sp.child_id
WHERE sp.child_id IS NOT NULL
  AND fc.id IS NULL
ORDER BY sp.id;

-- 7) Sponsorships whose child exists but whose family relationship is broken
--    (normally impossible if family_children is valid).
SELECT
    sp.id AS sponsorship_id,
    sp.sponsorship_code,
    sp.child_id,
    fc.family_id
FROM sponsorships sp
JOIN family_children fc ON fc.id = sp.child_id
LEFT JOIN families f ON f.id = fc.family_id
WHERE f.id IS NULL
ORDER BY sp.id;
