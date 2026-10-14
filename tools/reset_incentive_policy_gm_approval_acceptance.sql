-- TEST-ONLY reset for the exact R3 GM approval acceptance test.
-- Run only against the local ahl_el_kheir database after syncing the source fix.
-- It never activates a policy and preserves the original 'submitted' history row.
-- A zero-row guard performs no deletes; the policy must be approved and inactive.

START TRANSACTION;

UPDATE hr_incentive_policy_versions
SET status = 'pending_gm'
WHERE id = 3
  AND policy_name = 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3'
  AND version_no = 1
  AND created_by_user_id = 32
  AND status = 'approved'
  AND activated_by_user_id IS NULL
  AND activated_at IS NULL;

SET @ak_r3_reset_rows = ROW_COUNT();

DELETE FROM hr_incentive_policy_approval_history
WHERE @ak_r3_reset_rows = 1
  AND policy_version_id = 3
  AND action = 'approved'
  AND actor_user_id = 2;

DELETE FROM notifications
WHERE @ak_r3_reset_rows = 1
  AND recipient_user_id IN (2, 29, 32)
  AND title IN (
      'اعتمد المدير العام سياسة الحوافز',
      'سياسة حوافز معتمدة بانتظار التفعيل'
  )
  AND body LIKE '%ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3%'
  AND link LIKE '%/modules/hr/incentive_policy.php%';

SELECT
    @ak_r3_reset_rows AS reset_rows,
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
