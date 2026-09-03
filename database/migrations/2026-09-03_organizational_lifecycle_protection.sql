-- Organizational lifecycle protection
-- Phase 1: keep supervisor lifecycle authoritative and prevent generic account toggles
-- from silently changing a supervisor's organizational state.
--
-- This migration is intentionally additive. It does not remove is_active.

-- No schema change is required for this protection.
-- The application layer must route supervisor state changes through
-- config/supervisor_lifecycle.php.

-- Verification query:
-- SELECT u.id, u.full_name, u.is_active, u.supervisor_status
-- FROM users u
-- JOIN roles r ON r.id = u.role_id
-- WHERE r.code = 'supervisor'
-- ORDER BY u.id;
