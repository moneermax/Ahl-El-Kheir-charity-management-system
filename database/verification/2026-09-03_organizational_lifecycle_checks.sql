-- Read-only verification suite for the organizational lifecycle foundation.
-- Run in phpMyAdmin. These queries do NOT modify data.

-- 1. Supervisor lifecycle distribution
SELECT
    COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status,
    COUNT(*) AS total
FROM users u
JOIN roles r ON r.id = u.role_id
WHERE r.code = 'supervisor'
GROUP BY COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END)
ORDER BY supervisor_status;

-- 2. Supervisors whose account-access flag conflicts with lifecycle status
SELECT u.id, u.full_name, u.is_active, u.supervisor_status
FROM users u
JOIN roles r ON r.id = u.role_id
WHERE r.code = 'supervisor'
  AND ((u.supervisor_status = 'active' AND u.is_active <> 1)
    OR (u.supervisor_status IN ('on_leave','suspended','departed','archived') AND u.is_active <> 0));

-- Expected result: zero rows.

-- 3. Open leave records per supervisor (should be at most one)
SELECT supervisor_id, COUNT(*) AS open_leave_count
FROM supervisor_leaves
WHERE status IN ('planned','active')
GROUP BY supervisor_id
HAVING COUNT(*) > 1;

-- Expected result: zero rows.

-- 4. Active supervisor-letter history records with a currently matching assignment
SELECT h.id, h.supervisor_letter_id, h.supervisor_id, h.letter_id, h.gender, h.assigned_at
FROM supervisor_letter_assignment_history h
LEFT JOIN supervisor_letters sl ON sl.id = h.supervisor_letter_id
WHERE h.ended_at IS NULL
  AND h.supervisor_letter_id IS NOT NULL
  AND sl.id IS NULL;

-- Expected result: zero rows.

-- 5. Final-state supervisors who still have active sponsor assignments
SELECT u.id, u.full_name, u.supervisor_status, COUNT(s.id) AS active_sponsor_assignments
FROM users u
JOIN roles r ON r.id = u.role_id
JOIN sponsors s ON s.supervisor_id = u.id
WHERE r.code = 'supervisor'
  AND u.supervisor_status IN ('departed','archived')
GROUP BY u.id, u.full_name, u.supervisor_status
HAVING COUNT(s.id) > 0;

-- Expected result: zero rows.

-- 6. Foreign-key constraints expected on supervisor_letter_assignment_history
SELECT tc.CONSTRAINT_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME,
       rc.DELETE_RULE, rc.UPDATE_RULE
FROM information_schema.TABLE_CONSTRAINTS tc
JOIN information_schema.KEY_COLUMN_USAGE kcu
  ON kcu.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
 AND kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
 AND kcu.TABLE_NAME = tc.TABLE_NAME
JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
 AND rc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
  AND tc.TABLE_NAME = 'supervisor_letter_assignment_history'
  AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
ORDER BY tc.CONSTRAINT_NAME;

-- 7. Indexes supporting the supervisor-letter history table
SHOW INDEX FROM supervisor_letter_assignment_history;
