-- Verification queries for the PHP trigger/view migration.
-- Run after 2026-09-02_replace_integrity_triggers_views_with_php.sql.

-- 1) Legacy triggers must be gone.
SELECT TRIGGER_NAME
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME IN (
      'trg_families_family_code_ai',
      'trg_families_family_code_bu',
      'trg_family_children_ai',
      'trg_family_children_au',
      'trg_family_children_ad',
      'trg_supervisor_letters_ai',
      'trg_supervisor_letters_au',
      'trg_supervisor_letters_ad'
  );
-- Expected: 0 rows.

-- 2) Legacy views must be gone.
SELECT TABLE_NAME
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'vw_families_without_sponsorship',
      'vw_sponsor_current_supervisor',
      'vw_supervisor_sponsors'
  );
-- Expected: 0 rows.

-- 3) Cached family child counts must equal the actual child rows.
SELECT COUNT(*) AS family_count_mismatch
FROM families f
WHERE f.children_count <> (
    SELECT COUNT(*)
    FROM family_children fc
    WHERE fc.family_id = f.id
);
-- Expected: 0.

-- 4) Accounting integrity check: journal lines must have a parent entry.
SELECT COUNT(*) AS remaining_journal_line_orphans
FROM journal_lines jl
LEFT JOIN journal_entries je ON je.id = jl.entry_id
WHERE je.id IS NULL;
-- Expected: 0.

-- 5) Inspect current supervisor-letter matrix and non-manual sponsor assignments.
SELECT sl.letter_id, l.code, sl.gender, sl.supervisor_id, u.full_name
FROM supervisor_letters sl
LEFT JOIN letters l ON l.id = sl.letter_id
LEFT JOIN users u ON u.id = sl.supervisor_id
ORDER BY l.sort_order, sl.gender, u.full_name;

SELECT COUNT(*) AS non_manual_sponsors_without_resolved_supervisor
FROM sponsors s
WHERE COALESCE(s.is_manual_override, 0) = 0
  AND s.first_letter_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM supervisor_letters sl
      WHERE sl.letter_id = s.first_letter_id
        AND sl.supervisor_id IS NOT NULL
        AND (
            sl.gender = s.gender
            OR sl.gender = 'both'
            OR sl.gender IS NULL
            OR sl.gender = ''
        )
  );
-- This last value is informational: sponsors whose letter/gender has no
-- supervisor assignment are legitimately expected to remain unassigned.
