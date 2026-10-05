<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/accounting/lib.php';
require_once dirname(__DIR__) . '/accounting/lib_vouchers.php';
require_once dirname(__DIR__) . '/accounting/lib_transaction_review.php';

/**
 * GM salary-advance waiver/exemption feature.
 *
 * This library is intentionally isolated from the existing Stage 1–6
 * salary-advance lifecycle. It records GM decisions and FM financial effects
 * in dedicated tables and never deletes/re-writes historical repayment data.
 *
 * Existing payroll/accounting integrations should call these helpers only
 * after this feature has been migrated and enabled.
 */

function hrSalaryAdvanceWaiverCanGM(string $role): bool
{
    return in_array($role, ['general_manager', 'gm', 'admin'], true);
}

function hrSalaryAdvanceWaiverCanFM(string $role): bool
{
    return in_array($role, ['financial_manager', 'fm', 'finance', 'admin'], true);
}

function hrSalaryAdvanceWaiverNextDecisionNo(PDO $pdo): string
{
    $row = dbFetchOne(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(decision_no, 8) AS UNSIGNED)), 0) AS max_no
         FROM hr_salary_advance_waiver_decisions
         WHERE decision_no REGEXP '^SAW-[0-9]+$'"
    );
    return 'SAW-' . str_pad((string)((int)($row['max_no'] ?? 0) + 1), 6, '0', STR_PAD_LEFT);
}

function hrSalaryAdvanceWaiverEligibleRows(PDO $pdo, string $effectiveMonth, ?int $employeeId = null): array
{
    $sql = "SELECT r.id AS request_id, r.request_no, r.employee_id,
                   r.status, r.outstanding_balance,
                   e.full_name AS employee_name, e.employee_code,
                   COALESCE((
                       SELECT SUM(pr.actual_amount)
                       FROM hr_salary_advance_payroll_repayments pr
                       WHERE pr.salary_advance_request_id = r.id
                         AND pr.payroll_id IN (
                             SELECT p.id FROM payroll p
                             WHERE p.employee_id = r.employee_id
                               AND p.year = YEAR(?)
                               AND p.month = MONTH(?)
                         )
                   ), 0) AS current_period_repayment
            FROM hr_salary_advance_requests r
            JOIN employees e ON e.id = r.employee_id
            WHERE r.status = 'disbursed'
              AND COALESCE(r.outstanding_balance, 0) > 0";
    $params = [$effectiveMonth, $effectiveMonth];

    if ($employeeId !== null) {
        $sql .= " AND r.employee_id = ?";
        $params[] = $employeeId;
    }

    $sql .= " AND NOT EXISTS (
                 SELECT 1
                 FROM hr_salary_advance_waiver_items wi
                 JOIN hr_salary_advance_waiver_decisions wd ON wd.id = wi.decision_id
                 WHERE wi.salary_advance_request_id = r.id
                   AND wd.status = 'executed'
             )
             ORDER BY r.employee_id ASC, r.id ASC";

    return dbFetchAll($sql, $params);
}

function hrSalaryAdvanceWaiverCreateDecision(
    PDO $pdo,
    string $decisionType,
    string $effectiveMonth,
    string $reason,
    int $createdBy,
    ?int $employeeId = null
): int {
    if (!in_array($decisionType, ['individual', 'blanket'], true)) {
        throw new InvalidArgumentException('نوع قرار الإعفاء غير صالح.');
    }
    if ($createdBy <= 0 || trim($reason) === '') {
        throw new InvalidArgumentException('بيانات قرار الإعفاء غير مكتملة.');
    }

    $date = DateTime::createFromFormat('!Y-m-d', $effectiveMonth);
    if (!$date || $date->format('Y-m-d') !== $effectiveMonth || $date->format('d') !== '01') {
        throw new InvalidArgumentException('شهر السريان يجب أن يكون تاريخاً صالحاً في اليوم الأول من الشهر.');
    }

    if ($decisionType === 'individual' && ($employeeId === null || $employeeId <= 0)) {
        throw new InvalidArgumentException('قرار الإعفاء الفردي يتطلب موظفاً محدداً.');
    }

    $lock = ak_voucher_lock();
    if (!$lock) {
        throw new RuntimeException('تعذر الحصول على قفل ترقيم قرار الإعفاء.');
    }

    try {
        $pdo->beginTransaction();

        $decisionNo = hrSalaryAdvanceWaiverNextDecisionNo($pdo);
        $pdo->prepare(
            "INSERT INTO hr_salary_advance_waiver_decisions
             (decision_no, decision_type, effective_month, reason, status, created_by)
             VALUES (?, ?, ?, ?, 'pending_fm', ?)"
        )->execute([$decisionNo, $decisionType, $effectiveMonth, trim($reason), $createdBy]);

        $decisionId = (int)$pdo->lastInsertId();
        if ($decisionId <= 0) {
            throw new RuntimeException('تعذر إنشاء قرار الإعفاء.');
        }

        $rows = hrSalaryAdvanceWaiverEligibleRows(
            $pdo,
            $effectiveMonth,
            $decisionType === 'individual' ? $employeeId : null
        );

        if (!$rows) {
            throw new RuntimeException('لا توجد سلف قائمة مشمولة بقرار الإعفاء.');
        }

        foreach ($rows as $row) {
            $pdo->prepare(
                "INSERT INTO hr_salary_advance_waiver_items
                 (decision_id, salary_advance_request_id, employee_id,
                  balance_before, current_period_repayment, refund_amount,
                  balance_before_waiver, waived_amount, balance_after,
                  previous_request_status, resulting_request_status)
                 VALUES (?, ?, ?, ?, ?, 0, ?, 0, 0, ?, 'disbursed')"
            )->execute([
                $decisionId,
                (int)$row['request_id'],
                (int)$row['employee_id'],
                round((float)$row['outstanding_balance'], 2),
                round((float)$row['current_period_repayment'], 2),
                round((float)$row['outstanding_balance'], 2),
                (string)$row['status'],
            ]);
        }

        $pdo->commit();

        // Notify FM only after the GM decision transaction has committed.
        ak_transaction_review_notify_fm_event(
            $decisionId,
            'salary_advance_waiver_decision',
            'قرار إعفاء سلف راتب بانتظار التنفيذ المالي',
            'يوجد قرار إعفاء سلف راتب «' . $decisionNo . '» صادر من المدير العام وبانتظار مراجعة وتنفيذ المدير المالي.',
            APP_URL . 'modules/hr/salary_advance_waiver_fm.php'
        );

        return $decisionId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        ak_voucher_unlock();
    }
}

function hrSalaryAdvanceWaiverReject(PDO $pdo, int $decisionId, int $fmUserId, string $reason): void
{
    if ($decisionId <= 0 || $fmUserId <= 0 || trim($reason) === '') {
        throw new InvalidArgumentException('قرار الرفض وسببه غير صالحين.');
    }

    $pdo->beginTransaction();
    try {
        $decision = dbFetchOne(
            "SELECT * FROM hr_salary_advance_waiver_decisions WHERE id = ? FOR UPDATE",
            [$decisionId]
        );
        if (!$decision || $decision['status'] !== 'pending_fm') {
            throw new RuntimeException('قرار الإعفاء غير متاح للرفض.');
        }

        $pdo->prepare(
            "UPDATE hr_salary_advance_waiver_decisions
             SET status='rejected', fm_reviewed_by=?, fm_reviewed_at=NOW(), fm_rejection_reason=?
             WHERE id=? AND status='pending_fm'"
        )->execute([$fmUserId, trim($reason), $decisionId]);

        if ($pdo->rowCount() !== 1) {
            throw new RuntimeException('تعذر تسجيل رفض قرار الإعفاء.');
        }

        dbExecute(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'HR_SALARY_ADVANCE_WAIVER_REJECTED', 'hr_salary_advance_waiver_decision', ?, ?, ?, ?, ?)",
            [
                $fmUserId,
                $decisionId,
                json_encode(['status'=>'pending_fm'], JSON_UNESCAPED_UNICODE),
                json_encode(['status'=>'rejected','reason'=>trim($reason)], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * FM executes an already-approved GM decision.
 *
 * Accounting account selection is deliberately supplied by FM; this feature
 * never invents an expense account. The refund account must be one of the
 * existing payment accounts (1100/1200/1300).
 */
function hrSalaryAdvanceWaiverExecute(
    PDO $pdo,
    int $decisionId,
    int $fmUserId,
    int $refundAccountId,
    int $waiverExpenseAccountId
): array {
    if ($decisionId <= 0 || $fmUserId <= 0 || $refundAccountId <= 0 || $waiverExpenseAccountId <= 0) {
        throw new InvalidArgumentException('بيانات تنفيذ الإعفاء غير صالحة.');
    }

    $lock = ak_voucher_lock();
    if (!$lock) throw new RuntimeException('تعذر الحصول على قفل الترقيم المحاسبي.');

    try {
        $pdo->beginTransaction();

        $decision = dbFetchOne(
            "SELECT * FROM hr_salary_advance_waiver_decisions WHERE id = ? FOR UPDATE",
            [$decisionId]
        );
        if (!$decision) throw new RuntimeException('قرار الإعفاء غير موجود.');
        if ($decision['status'] !== 'pending_fm') {
            throw new RuntimeException('قرار الإعفاء ليس بانتظار التنفيذ المالي.');
        }

        $refundAccount = dbFetchOne(
            "SELECT id, code, name_ar, account_type, is_active
             FROM accounts WHERE id = ? FOR UPDATE",
            [$refundAccountId]
        );
        if (!$refundAccount || (int)$refundAccount['is_active'] !== 1 ||
            !in_array((string)$refundAccount['code'], ['1100','1200','1300'], true)) {
            throw new RuntimeException('حساب رد الخصم غير صالح.');
        }

        $expenseAccount = dbFetchOne(
            "SELECT id, code, name_ar, account_type, is_active
             FROM accounts WHERE id = ? FOR UPDATE",
            [$waiverExpenseAccountId]
        );
        if (!$expenseAccount || (int)$expenseAccount['is_active'] !== 1 ||
            $expenseAccount['account_type'] !== 'expense') {
            throw new RuntimeException('حساب مصروف الإعفاء غير صالح.');
        }

        $items = dbFetchAll(
            "SELECT wi.*, r.outstanding_balance, r.status AS live_request_status
             FROM hr_salary_advance_waiver_items wi
             JOIN hr_salary_advance_requests r ON r.id = wi.salary_advance_request_id
             WHERE wi.decision_id = ?
             ORDER BY wi.id ASC
             FOR UPDATE",
            [$decisionId]
        );
        if (!$items) throw new RuntimeException('قرار الإعفاء لا يحتوي على بنود تنفيذ.');

        foreach ($items as $item) {
            if ((string)$item['live_request_status'] !== 'disbursed') {
                throw new RuntimeException('تغيرت حالة إحدى السلف منذ إنشاء القرار؛ التنفيذ متوقف للمراجعة.');
            }
            if (round((float)$item['outstanding_balance'], 2) !== round((float)$item['balance_before'], 2)) {
                throw new RuntimeException('تغير رصيد إحدى السلف منذ إنشاء القرار؛ التنفيذ متوقف للمراجعة.');
            }
        }

        $refundTotal = 0.00;
        $waiverTotal = 0.00;

        foreach ($items as $item) {
            $requestId = (int)$item['salary_advance_request_id'];
            $balance = round(max(0.00, (float)$item['outstanding_balance']), 2);
            $refund = round(max(0.00, (float)$item['current_period_repayment']), 2);
            $balanceBeforeWaiver = round(max(0.00, $balance + $refund), 2);

            // The current payroll deduction has already reduced the live 1410
            // balance. Refund it first, then waive the restored outstanding debt.
            if ($refund > 0.00) {
                $refundTotal = round($refundTotal + $refund, 2);
            }
            $waiverTotal = round($waiverTotal + $balanceBeforeWaiver, 2);
        }

        // A salary refund is a real cash/bank/wallet outflow. Refuse the entire
        // execution atomically if the selected source cannot fund it.
        if ($refundTotal > 0.00) {
            $availableRefund = ak_voucher_cash_balance($refundAccountId);
            if ($refundTotal > $availableRefund + 0.000001) {
                throw new RuntimeException('الرصيد غير كافٍ في حساب رد الخصم المحدد لتنفيذ الإعفاء بالكامل.');
            }
        }

        foreach ($items as $item) {
            $requestId = (int)$item['salary_advance_request_id'];
            $balance = round(max(0.00, (float)$item['outstanding_balance']), 2);
            $refund = round(max(0.00, (float)$item['current_period_repayment']), 2);
            $balanceBeforeWaiver = round(max(0.00, $balance + $refund), 2);

            $pdo->prepare(
                "UPDATE hr_salary_advance_waiver_items
                 SET refund_amount = ?, balance_before_waiver = ?, waived_amount = ?,
                     balance_after = 0, refund_account_id = ?, waiver_expense_account_id = ?
                 WHERE id = ?"
            )->execute([
                $refund,
                $balanceBeforeWaiver,
                $balanceBeforeWaiver,
                $refundAccountId,
                $waiverExpenseAccountId,
                (int)$item['id']
            ]);
        }

        // Accounting is posted as separate, auditable salary-advance events.
        // Original disbursement and payroll journals remain untouched.
        if ($refundTotal > 0.00) {
            $jeCode = ak_voucher_next_journal_code();
            $desc = 'رد خصومات سلف الراتب ضمن قرار الإعفاء';
            $pdo->prepare(
                "INSERT INTO journal_entries
                 (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
                 VALUES (?, ?, ?, 'salary_advance_waiver_refund', ?, 'posted', ?)"
            )->execute([$jeCode, date('Y-m-d'), $desc, $decisionId, $fmUserId]);
            $refundEntryId = (int)$pdo->lastInsertId();

            $advanceId = ak_account_id('1410');
            if ($advanceId <= 0) throw new RuntimeException('حساب 1410 غير موجود.');

            $pdo->prepare("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, 0, ?)")
                ->execute([$refundEntryId, $advanceId, $refundTotal, $desc]);
            $pdo->prepare("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, 0, ?, ?)")
                ->execute([$refundEntryId, $refundAccountId, $refundTotal, $desc]);

            foreach ($items as $item) {
                $refund = round(max(0.00, (float)$item['current_period_repayment']), 2);
                if ($refund <= 0.00) continue;
                $pdo->prepare(
                    "UPDATE hr_salary_advance_waiver_items
                     SET refund_journal_entry_id = ?
                     WHERE id = ?"
                )->execute([$refundEntryId, (int)$item['id']]);
            }
        } else {
            $refundEntryId = null;
        }

        if ($waiverTotal <= 0.00) throw new RuntimeException('لا يوجد رصيد يمكن إعفاؤه.');

        $jeCode = ak_voucher_next_journal_code();
        $desc = 'إعفاء سلف رواتب بقرار المدير العام';
        $pdo->prepare(
            "INSERT INTO journal_entries
             (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?, ?, ?, 'salary_advance_waiver', ?, 'posted', ?)"
        )->execute([$jeCode, date('Y-m-d'), $desc, $decisionId, $fmUserId]);
        $waiverEntryId = (int)$pdo->lastInsertId();

        $advanceId = ak_account_id('1410');
        if ($advanceId <= 0) throw new RuntimeException('حساب 1410 غير موجود.');

        $pdo->prepare("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, 0, ?)")
            ->execute([$waiverEntryId, $waiverExpenseAccountId, $waiverTotal, $desc]);
        $pdo->prepare("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, 0, ?, ?)")
            ->execute([$waiverEntryId, $advanceId, $waiverTotal, $desc]);

        foreach ($items as $item) {
            $pdo->prepare(
                "UPDATE hr_salary_advance_waiver_items
                 SET waiver_journal_entry_id = ?, executed_at = NOW(),
                     resulting_request_status = 'disbursed'
                 WHERE id = ?"
            )->execute([$waiverEntryId, (int)$item['id']]);

            $pdo->prepare(
                "UPDATE hr_salary_advance_requests
                 SET outstanding_balance = 0, updated_at = NOW()
                 WHERE id = ? AND status = 'disbursed'"
            )->execute([(int)$item['salary_advance_request_id']]);

            $pdo->prepare(
                "INSERT INTO audit_log
                 (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'HR_SALARY_ADVANCE_WAIVER_EXECUTED', 'hr_salary_advance_request', ?, ?, ?, ?, ?)"
            )->execute([
                $fmUserId,
                (int)$item['salary_advance_request_id'],
                json_encode([
                    'outstanding_balance' => round((float)$item['outstanding_balance'], 2)
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'decision_id' => $decisionId,
                    'refund_amount' => round((float)$item['refund_amount'], 2),
                    'waived_amount' => round((float)$item['waived_amount'], 2),
                    'outstanding_balance' => 0.00
                ], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }

        $pdo->prepare(
            "UPDATE hr_salary_advance_waiver_decisions
             SET status = 'executed', fm_reviewed_by = ?, fm_reviewed_at = NOW(), executed_at = NOW()
             WHERE id = ? AND status = 'pending_fm'"
        )->execute([$fmUserId, $decisionId]);

        if ($pdo->rowCount() !== 1) throw new RuntimeException('تعذر إكمال قرار الإعفاء.');

        $pdo->commit();

        return [
            'decision_id' => $decisionId,
            'refund_total' => $refundTotal,
            'waiver_total' => $waiverTotal,
            'refund_journal_entry_id' => $refundEntryId,
            'waiver_journal_entry_id' => $waiverEntryId,
            'item_count' => count($items),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        ak_voucher_unlock();
    }
}
