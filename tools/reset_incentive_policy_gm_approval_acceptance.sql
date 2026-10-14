-- TEST-ONLY reset for the exact R3 GM approval acceptance test.
-- Run only against the local ahl_el_kheir database.
-- ASCII-only predicates avoid client/source-file encoding mismatches.
-- Never activates R3; preserves the original submission history and GM submission notification.

START TRANSACTION;

SET @ak_r3_guard = (
    SELECT COUNT(*)
    FROM hr_incentive_policy_versions
    WHERE id = 3
      AND policy_name = 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3'
      AND version_no = 1
      AND created_by_user_id = 32
      AND status IN ('approved', 'pending_gm')
      AND activated_by_user_id IS NULL
      AND activated_at IS NULL
);

UPDATE hr_incentive_policy_versions
SET status = 'pending_gm'
WHERE @ak_r3_guard = 1
  AND id = 3
  AND policy_name = 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3'
  AND version_no = 1
  AND created_by_user_id = 32
  AND status = 'approved'
  AND activated_by_user_id IS NULL
  AND activated_at IS NULL;

SET @ak_r3_status_reset_rows = ROW_COUNT();

DELETE FROM hr_incentive_policy_approval_history
WHERE @ak_r3_status_reset_rows = 1
  AND policy_version_id = 3
  AND action = 'approved'
  AND actor_user_id = 2;

-- HR and FM approval-handoff notifications only.
-- Recipient 2's submission notification is deliberately preserved.
-- Match the exact test policy in body and the policy page link, not Arabic title text.
DELETE FROM notifications
WHERE @ak_r3_guard = 1
  AND recipient_user_id IN (29, 32)
  AND body LIKE '%ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3%'
  AND link LIKE '%/modules/hr/incentive_policy.php%';

SELECT
    @ak_r3_guard AS guarded_policy_matches,
    @ak_r3_status_reset_rows AS status_reset_rows,
    id,
    policy_name,
    status,
    activated_by_user_id,
    activated_at
FROM hr_incentive_policy_versions
WHERE id = 3
  AND policy_name = 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3';

SELECT id, policy_version_id, action, actor_user_id, created_at
FROM hr_incentive_policy_approval_history
WHERE policy_version_id = 3
ORDER BY id;

COMMIT;
