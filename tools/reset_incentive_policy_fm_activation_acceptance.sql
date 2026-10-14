-- TEST-ONLY rollback of the exact R3 FM activation acceptance action.
-- Run only against the local ahl_el_kheir database.
-- Requires the exact activation result reported by the user.
-- Preserves notifications and approval history; no unrelated policy is modified.

START TRANSACTION;

SET @ak_r3_activation_guard = (
    SELECT COUNT(*)
    FROM hr_incentive_policy_versions
    WHERE id = 3
      AND policy_name = 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3'
      AND version_no = 1
      AND created_by_user_id = 32
      AND status = 'active'
      AND effective_from = '2026-10-14'
      AND effective_to IS NULL
      AND activated_by_user_id = 29
      AND activated_at = '2026-10-14 14:12:23'
);

UPDATE hr_incentive_policy_versions
SET status = 'approved',
    activated_by_user_id = NULL,
    activated_at = NULL
WHERE @ak_r3_activation_guard = 1
  AND id = 3
  AND policy_name = 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3'
  AND version_no = 1
  AND created_by_user_id = 32
  AND status = 'active'
  AND effective_from = '2026-10-14'
  AND effective_to IS NULL
  AND activated_by_user_id = 29
  AND activated_at = '2026-10-14 14:12:23';

SET @ak_r3_activation_reset_rows = ROW_COUNT();

SELECT
    @ak_r3_activation_guard AS guarded_activation_matches,
    @ak_r3_activation_reset_rows AS reset_rows,
    id,
    policy_name,
    status,
    effective_from,
    effective_to,
    activated_by_user_id,
    activated_at
FROM hr_incentive_policy_versions
WHERE id = 3
  AND policy_name = 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3';

COMMIT;
