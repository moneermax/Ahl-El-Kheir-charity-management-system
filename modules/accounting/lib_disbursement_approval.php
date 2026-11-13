<?php
// Monthly Nanny disbursement pre-approval controls.
// Approval is a workflow-only transition; financial posting remains the transfer action.
if (!function_exists('ak_disbursement_is_fm_approver')) {
    function ak_disbursement_is_fm_approver(string $role): bool {
        return in_array($role, ['financial_manager', 'fm', 'finance'], true);
    }
}
if (!function_exists('ak_disbursement_assert_assigned')) {
    function ak_disbursement_assert_assigned(int $accountantId, int $nannyId): void {
        $assigned = dbFetchOne(
            "SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?",
            [$accountantId, $nannyId]
        );
        if (!$assigned) throw new RuntimeException('هذه الدفعة ليست ضمن الدفعات المسندة إليك.');
    }
}
if (!function_exists('ak_disbursement_audit_required')) {
    function ak_disbursement_audit_required(int $userId, string $action, int $batchId, array $oldValues, array $newValues): void {
        dbExecute(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
             VALUES (?, ?, 'monthly_disbursements', ?, ?, ?, ?, ?, NOW())",
            [
                $userId, $action, $batchId,
                json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? 'cli',
            ]
        );
    }
}
if (!function_exists('ak_disbursement_preapproval_snapshot')) {
    /**
     * Validate each existing family row against the currently active, fully verified
     * sponsorship amount for the batch month. Only amounts can be corrected here;
     * family membership and the batch Nanny/group/month remain fixed.
     */
    function ak_disbursement_preapproval_snapshot(array $batch, ?array $requestedAmounts = null, bool $lockItems = false): array {
        $batchId = (int)($batch['id'] ?? 0);
        $groupId = (int)($batch['group_id'] ?? 0);
        $month = (string)($batch['month'] ?? '');
        if ($batchId <= 0 || $groupId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new RuntimeException('بيانات الدفعة أو المجموعة أو الشهر غير صالحة للمراجعة.');
        }
        $itemsSql = "SELECT id, family_id, amount, status
                     FROM disbursement_items
                     WHERE disbursement_id = ?
                     ORDER BY id ASC" . ($lockItems ? " FOR UPDATE" : "");
        $items = dbFetchAll($itemsSql, [$batchId]);
        if (!$items) throw new RuntimeException('لا يمكن إرسال دفعة دون بنود أسر.');

        if ($requestedAmounts !== null) {
            if (!is_array($requestedAmounts)) throw new RuntimeException('قائمة مبالغ الأسر غير صالحة.');
            $submittedIds = array_map('intval', array_keys($requestedAmounts));
            $actualIds = array_map(static fn($item) => (int)$item['id'], $items);
            sort($submittedIds);
            sort($actualIds);
            if ($submittedIds !== $actualIds) throw new RuntimeException('قائمة الأسر المرسلة لا تطابق بنود الدفعة الحالية.');
        }

        $oldItems = [];
        $newItems = [];
        $total = 0.0;
        foreach ($items as $item) {
            if ((string)$item['status'] !== 'pending') {
                throw new RuntimeException('لا يمكن تعديل أو اعتماد دفعة تحتوي على بند أسرة سبق تأكيده أو إرجاعه.');
            }
            $eligible = dbFetchOne(
                "SELECT COALESCE(SUM(sp.monthly_amount), 0) AS eligible_amount
                 FROM group_children gc
                 INNER JOIN family_children fc ON fc.id = gc.child_id
                 INNER JOIN nanny_family_verifications v
                    ON v.family_id = fc.family_id AND v.month = ?
                   AND v.is_orphan_verified = 1
                   AND v.is_mother_contact_verified = 1
                   AND v.is_bank_verified = 1
                 LEFT JOIN sponsorships sp ON sp.child_id = gc.child_id AND sp.status = 'active'
                 WHERE gc.group_id = ? AND gc.left_date IS NULL AND fc.family_id = ?",
                [$month, $groupId, (int)$item['family_id']]
            );
            $eligibleAmount = round((float)($eligible['eligible_amount'] ?? 0), 2);
            if ($eligibleAmount <= 0) {
                throw new RuntimeException('إحدى الأسر لم تعد تملك كفالات نشطة موثقة بالكامل لهذا الشهر.');
            }
            $itemId = (int)$item['id'];
            $rawAmount = $requestedAmounts === null
                ? $item['amount']
                : ($requestedAmounts[(string)$itemId] ?? $requestedAmounts[$itemId] ?? null);
            if (!is_numeric($rawAmount) || !is_finite((float)$rawAmount)) {
                throw new RuntimeException('يوجد مبلغ غير صالح في أحد بنود الأسر.');
            }
            $amount = round((float)$rawAmount, 2);
            if ($amount <= 0 || $amount > $eligibleAmount + 0.00001) {
                throw new RuntimeException('مبلغ إحدى الأسر يجب أن يكون أكبر من صفر وألا يتجاوز إجمالي كفالاتها النشطة والموثقة.');
            }
            $oldItems[] = ['item_id' => $itemId, 'family_id' => (int)$item['family_id'], 'amount' => round((float)$item['amount'], 2)];
            $newItems[] = ['item_id' => $itemId, 'family_id' => (int)$item['family_id'], 'amount' => $amount, 'eligible_maximum' => $eligibleAmount];
            $total += $amount;
        }
        return ['old_items' => $oldItems, 'new_items' => $newItems, 'total_amount' => round($total, 2)];
    }
}
