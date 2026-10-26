<?php
declare(strict_types=1);

/**
 * Payroll accounting integration.
 *
 * This file intentionally contains NO MySQL trigger creation. Payroll status
 * transitions and accounting posting are explicit procedural PHP operations.
 */

function hrPayrollAssertMutable(array $payroll): void
{
    if (($payroll['status'] ?? '') === 'paid') {
        throw new RuntimeException('لا يمكن تعديل مسير راتب بعد صرفه. استخدم إجراء تصحيح/عكس محاسبي مستقل.');
    }
}

function hrPayrollGetForUpdate(PDO $pdo, int $payrollId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT p.*, e.full_name AS employee_name, e.employee_code
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         WHERE p.id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$payrollId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hrPayrollResolvePaymentAccount(PDO $pdo, ?int $requestedAccountId): int
{
    if ($requestedAccountId !== null && $requestedAccountId > 0) {
        $stmt = $pdo->prepare(
            "SELECT id FROM accounts WHERE id = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$requestedAccountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('حساب الدفع البنكي غير صالح.');
        }
        return (int)$row['id'];
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM accounts WHERE code = '1200' AND is_active = 1 LIMIT 1"
    );
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('حساب الدفع البنكي غير صالح.');
    }
    return (int)$row['id'];
}

function hrPayrollPostAccounting(PDO $pdo, array $payroll, ?int $actorUserId = null): int
{
    require_once dirname(__DIR__) . '/accounting/lib.php';

    // IMPORTANT: ak_ensure_tables() contains DDL. It must not run while the
    // payroll transaction is active.
    $netSalary = round((float)($payroll['net_salary'] ?? 0), 2);
    $salaryAdvanceDeduction = round((float)($payroll['salary_advance_deduction'] ?? 0), 2);
    $incentiveAmount = round((float)($payroll['incentive_amount'] ?? 0), 2);
    if ($netSalary < 0 || $salaryAdvanceDeduction < 0 || $incentiveAmount < 0) {
        throw new RuntimeException('مبالغ مسير الراتب غير صالحة للمحاسبة.');
    }

    $expenseStmt = $pdo->prepare("SELECT id FROM accounts WHERE code = '5200' LIMIT 1");
    $expenseStmt->execute();
    $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$expense) {
        throw new RuntimeException('حساب الرواتب 5200 غير موجود في دليل الحسابات.');
    }
    $expenseAccountId = (int)$expense['id'];

    $receivableAccountId = null;
    if ($salaryAdvanceDeduction > 0) {
        $receivableStmt = $pdo->prepare(
            "SELECT id FROM accounts WHERE code = '1410' AND is_active = 1 LIMIT 1"
        );
        $receivableStmt->execute();
        $receivable = $receivableStmt->fetch(PDO::FETCH_ASSOC);
        if (!$receivable) {
            throw new RuntimeException('حساب سلف الرواتب 1410 غير موجود أو غير نشط في دليل الحسابات.');
        }
        $receivableAccountId = (int)$receivable['id'];
    }

    $requestedPaymentAccount = null;
    if (array_key_exists('payment_account_id', $payroll) && $payroll['payment_account_id'] !== null) {
        $requestedPaymentAccount = (int)$payroll['payment_account_id'];
    }
    $paymentAccountId = hrPayrollResolvePaymentAccount($pdo, $requestedPaymentAccount);

    require_once __DIR__ . '/lib_employee_incentives.php';
    $expenseAmount = round($netSalary + $salaryAdvanceDeduction, 2);
    $totalDebitExpected = round($expenseAmount + $incentiveAmount, 2);
    $totalBankPayment = round($netSalary + $incentiveAmount, 2);
    $incentivePayableAccountId = $incentiveAmount > 0 ? hrIncentiveAccountId($pdo, '2400') : null;

    $existingStmt = $pdo->prepare(
        "SELECT id
         FROM journal_entries
         WHERE reference_type = 'payroll'
           AND reference_id = ?
           AND status = 'posted'
         ORDER BY id ASC
         LIMIT 1"
    );
    $existingStmt->execute([(int)$payroll['id']]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $entryId = (int)$existing['id'];

        $journalCheckStmt = $pdo->prepare(
            "SELECT COUNT(*) AS line_count,
                    ROUND(SUM(debit), 2) AS debit_total,
                    ROUND(SUM(credit), 2) AS credit_total
             FROM journal_lines
             WHERE entry_id = ?"
        );
        $journalCheckStmt->execute([$entryId]);
        $journalCheck = $journalCheckStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $lineCount = (int)($journalCheck['line_count'] ?? 0);
        $debitTotal = round((float)($journalCheck['debit_total'] ?? 0), 2);
        $creditTotal = round((float)($journalCheck['credit_total'] ?? 0), 2);
        if ($lineCount < 2 ||
            $debitTotal <= 0 ||
            $debitTotal !== $creditTotal ||
            $debitTotal !== $totalDebitExpected) {
            throw new RuntimeException('يوجد قيد رواتب مرحّل سابق لكنه غير متوازن أو لا يطابق إجمالي تكلفة الراتب؛ تم إيقاف إعادة استخدامه لحماية سلامة المحاسبة.');
        }

        if ($incentiveAmount > 0) {
            $incentiveCheckStmt = $pdo->prepare(
                "SELECT ROUND(COALESCE(SUM(debit), 0), 2) AS payable_debit\n                 FROM journal_lines WHERE entry_id = ? AND account_id = ?"
            );
            $incentiveCheckStmt->execute([$entryId, $incentivePayableAccountId]);
            if (round((float)$incentiveCheckStmt->fetchColumn(), 2) !== $incentiveAmount) {
                throw new RuntimeException('القيد المرحّل السابق لا يحتوي على تسوية الحوافز الصحيحة على حساب 2400.');
            }
        }

        if ($salaryAdvanceDeduction > 0) {
            $repaymentCheckStmt = $pdo->prepare(
                "SELECT ROUND(COALESCE(SUM(credit), 0), 2) AS repayment_credit
                 FROM journal_lines
                 WHERE entry_id = ?
                   AND account_id = ?"
            );
            $repaymentCheckStmt->execute([$entryId, $receivableAccountId]);
            $repaymentCredit = round((float)($repaymentCheckStmt->fetchColumn() ?? 0), 2);
            if ($repaymentCredit !== $salaryAdvanceDeduction || $lineCount < 3) {
                throw new RuntimeException('القيد المرحّل السابق لا يحتوي على تخصيص سداد السلفة الصحيح على حساب 1410.');
            }
        }
    } else {
        $entryCode = 'PAY-' . (int)$payroll['id'];
        $entryDate = !empty($payroll['payment_date']) ? (string)$payroll['payment_date'] : date('Y-m-d');
        $employeeName = trim((string)($payroll['employee_name'] ?? ''));
        if ($employeeName === '') {
            $employeeName = 'ID ' . (int)$payroll['employee_id'];
        }

        $createdBy = $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null;
        if ($createdBy === null && class_exists('Session')) {
            $sessionUserId = Session::getUserId();
            if ($sessionUserId !== null && (int)$sessionUserId > 0) {
                $createdBy = (int)$sessionUserId;
            }
        }

        $entryStmt = $pdo->prepare(
            "INSERT INTO journal_entries
                (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?, ?, ?, 'payroll', ?, 'posted', ?)"
        );
        $entryStmt->execute([
            $entryCode,
            $entryDate,
            'صرف راتب الموظف: ' . $employeeName . ' - ' . (int)$payroll['year'] . '-' . str_pad((string)(int)$payroll['month'], 2, '0', STR_PAD_LEFT),
            (int)$payroll['id'],
            $createdBy
        ]);
        $entryId = (int)$pdo->lastInsertId();

        $lineStmt = $pdo->prepare(
            "INSERT INTO journal_lines
                (entry_id, account_id, debit, credit, description)
             VALUES (?, ?, ?, ?, ?)"
        );
        $period = (int)$payroll['year'] . '-' . str_pad((string)(int)$payroll['month'], 2, '0', STR_PAD_LEFT);

        // Payroll expense remains the salary before the salary-advance
        // receivable recovery. Cash is reduced only by the amount actually
        // paid to the employee; the repayment portion credits receivable 1410.
        $lineStmt->execute([$entryId, $expenseAccountId, $expenseAmount, 0, 'رواتب وأجور - ' . $period]);

        if ($totalBankPayment > 0) {
            $lineStmt->execute([$entryId, $paymentAccountId, 0, $totalBankPayment, 'صرف صافي الرواتب والحوافز المجدولة - ' . $period]);
        }
        if ($incentiveAmount > 0) {
            $lineStmt->execute([$entryId, $incentivePayableAccountId, $incentiveAmount, 0, 'تسوية مستحقات الحوافز من مسير الرواتب - ' . $period]);
        }
        if ($salaryAdvanceDeduction > 0) {
            $lineStmt->execute([$entryId, $receivableAccountId, 0, $salaryAdvanceDeduction, 'سداد سلفة راتب من مسير - ' . $period]);
        }
    }

    $update = $pdo->prepare(
        "UPDATE payroll
         SET accounting_entry_id = ?,
             accounting_status = 'posted'
         WHERE id = ?"
    );
    $update->execute([$entryId, (int)$payroll['id']]);
    if ($incentiveAmount > 0) {
        hrIncentiveMarkPayrollAwardsPaid($pdo, (int)$payroll['id'], $entryId);
    }

    return $entryId;
}

function hrPayrollChangeStatus(PDO $pdo, int $payrollId, string $newStatus, ?int $paymentAccountId = null, ?int $actorUserId = null, bool $initializeAccounting = true): void
{
    if (!in_array($newStatus, ['approved', 'paid'], true)) {
        throw new RuntimeException('حالة الرواتب غير صالحة.');
    }
    if ($payrollId <= 0) {
        throw new RuntimeException('سجل الرواتب غير صالح.');
    }

    // Accounting initialization may execute DDL, which can implicitly
    // commit in MySQL/MariaDB. Only initialize it when this function owns the
    // transaction boundary; callers with an existing transaction must prepare
    // the accounting schema before entering it.
    $startedHere = !$pdo->inTransaction();
    if ($startedHere) {
        if ($initializeAccounting) {
            require_once dirname(__DIR__) . '/accounting/lib.php';
            ak_ensure_tables();
            ak_seed_accounts();
        }
        $pdo->beginTransaction();
    }

    try {
        $row = hrPayrollGetForUpdate($pdo, $payrollId);
        if (!$row) {
            throw new RuntimeException('سجل الرواتب غير موجود.');
        }

        hrPayrollAssertMutable($row);

        if ((float)$row['basic_salary'] <= 0) {
            throw new RuntimeException('لا يمكن اعتماد أو صرف مسير بدون راتب أساسي صالح.');
        }
        if ((float)$row['net_salary'] < 0) {
            throw new RuntimeException('صافي الراتب لا يمكن أن يكون سالباً.');
        }

        if ($newStatus === 'approved') {
            if ($row['status'] !== 'draft') {
                throw new RuntimeException('لا يمكن اعتماد هذا السجل من حالته الحالية.');
            }

            $stmt = $pdo->prepare(
                "UPDATE payroll
                 SET status = 'approved', accounting_status = 'ready'
                 WHERE id = ? AND status = 'draft'"
            );
            $stmt->execute([$payrollId]);
        } else {
            if ($row['status'] !== 'approved') {
                throw new RuntimeException('يجب اعتماد مسير الراتب أولاً.');
            }

            require_once __DIR__ . '/lib_payroll_transfer.php';
            if (!hrPayrollTransferSchemaAvailable($pdo)) {
                throw new RuntimeException('يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.');
            }
            $proofStmt = $pdo->prepare(
                "SELECT id, proof_state, storage_key FROM hr_payroll_payment_proofs
                 WHERE payroll_id = ? LIMIT 1 FOR UPDATE"
            );
            $proofStmt->execute([$payrollId]);
            $proof = $proofStmt->fetch(PDO::FETCH_ASSOC);
            if (!$proof || $proof['proof_state'] !== 'prepared') {
                throw new RuntimeException('يجب رفع إثبات تحويل صالح قبل تأكيد الدفع. الدليل التاريخي لا يؤكد تحويلاً جديداً.');
            }
            $proofPath = hrPayrollProofPath((string)$proof['storage_key']);
            if (!is_file($proofPath) || !is_readable($proofPath)) {
                throw new RuntimeException('ملف إثبات التحويل غير موجود في التخزين الخاص؛ لم يتم تأكيد الدفع.');
            }

            $storedPaymentAccountId = (int)($row['payment_account_id'] ?? 0);
            if ($storedPaymentAccountId <= 0) {
                throw new RuntimeException('عيّن حساب التحويل للفترة أو لهذا الموظف قبل تأكيد الدفع.');
            }
            $validatedPaymentAccountId = hrPayrollResolvePaymentAccount($pdo, $storedPaymentAccountId);
            if ($paymentAccountId !== null && $paymentAccountId !== $validatedPaymentAccountId) {
                throw new RuntimeException('حساب التحويل المرسل لا يطابق الحساب المحفوظ لسجل الراتب.');
            }
            $paymentAccountId = $validatedPaymentAccountId;

            $paymentDate = date('Y-m-d');
            $stmt = $pdo->prepare(
                "UPDATE payroll
                 SET status = 'paid', payment_date = ?
                 WHERE id = ? AND status = 'approved'"
            );
            $stmt->execute([$paymentDate, $payrollId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('تعذر تسجيل صرف مسير الراتب.');
            }

            $row['status'] = 'paid';
            $row['payment_date'] = $paymentDate;
            $row['payment_account_id'] = $paymentAccountId;
            $entryId = hrPayrollPostAccounting($pdo, $row, $actorUserId);
            require_once __DIR__ . '/lib_salary_advance_payroll.php';
            hrSalaryAdvancePayrollApply($pdo, $row, $entryId);

            $confirmProof = $pdo->prepare(
                "UPDATE hr_payroll_payment_proofs
                 SET proof_state = 'confirmed', confirmed_by_user_id = ?, confirmed_at = NOW()
                 WHERE id = ? AND proof_state = 'prepared'"
            );
            $proofActorUserId = $actorUserId;
            if (($proofActorUserId === null || $proofActorUserId <= 0) && class_exists('Session')) {
                $sessionUserId = Session::getUserId();
                $proofActorUserId = $sessionUserId !== null && (int)$sessionUserId > 0 ? (int)$sessionUserId : null;
            }
            $confirmProof->execute([$proofActorUserId, (int)$proof['id']]);
            if ($confirmProof->rowCount() !== 1) {
                throw new RuntimeException('تعذر تأكيد إثبات التحويل؛ تم إلغاء عملية الدفع والمحاسبة.');
            }
        }

        if ($startedHere) {
            $pdo->commit();
            if ($newStatus === 'paid') {
                require_once __DIR__ . '/lib_salary_advance_payroll.php';
                hrSalaryAdvancePayrollNotifyApplied($pdo, $payrollId);
            }
            if ($newStatus === 'paid') {
                hrPayrollNotifyStatementAvailable($pdo, $payrollId);
                require_once __DIR__ . '/lib_gm_payroll_period.php';
                hrGmPayrollNotifyPeriodComplete($pdo, (int)$row['month'], (int)$row['year']);
            }
        }
    } catch (Throwable $e) {
        if ($startedHere && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function hrPayrollApproveAll(PDO $pdo, int $month, int $year): int
{
    if ($month < 1 || $month > 12) {
        throw new RuntimeException('الشهر غير صالح.');
    }

    // Ensure accounting infrastructure before starting the transaction.
    require_once dirname(__DIR__) . '/accounting/lib.php';
    ak_ensure_tables();
    ak_seed_accounts();

    $pdo->beginTransaction();
    try {
        $invalid = dbFetchOne(
            "SELECT COUNT(*) AS c
             FROM payroll
             WHERE month = ? AND year = ? AND status = 'draft' AND basic_salary <= 0",
            [$month, $year]
        );
        if ((int)($invalid['c'] ?? 0) > 0) {
            throw new RuntimeException('يوجد مسودات بدون راتب أساسي صالح. حدّث الراتب التاريخي أولاً.');
        }

        $stmt = $pdo->prepare(
            "UPDATE payroll
             SET status = 'approved', accounting_status = 'ready'
             WHERE month = ? AND year = ? AND status = 'draft'"
        );
        $stmt->execute([$month, $year]);
        $count = $stmt->rowCount();
        $pdo->commit();
        return $count;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}


/**
 * Approve only the explicitly selected draft payroll rows for one period.
 * This is approval only; payment and accounting posting remain separate actions.
 */
function hrPayrollApproveSelected(PDO $pdo, array $payrollIds, int $month, int $year): int
{
    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
        throw new RuntimeException(t('hr.payroll_error_invalid_period'));
    }

    $uniqueIds = [];
    foreach ($payrollIds as $rawId) {
        if (!is_scalar($rawId) || !preg_match('/^\\d+$/', (string)$rawId)) {
            throw new RuntimeException(t('hr.payroll_error_invalid_selection'));
        }
        $id = (int)$rawId;
        if ($id > 0) $uniqueIds[$id] = $id;
    }
    $ids = array_values($uniqueIds);
    if (!$ids) {
        throw new RuntimeException(t('hr.payroll_error_no_selection'));
    }
    if (count($ids) > 500) {
        throw new RuntimeException(t('hr.payroll_error_selection_limit'));
    }

    // This is an approval-only status transition. It does not need to
    // initialize accounting tables or seed accounts at request time.
    $pdo->beginTransaction();
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $select = $pdo->prepare(
            "SELECT id, basic_salary
             FROM payroll
             WHERE id IN ($placeholders)
               AND month = ? AND year = ? AND status = 'draft'
             FOR UPDATE"
        );
        $select->execute(array_merge($ids, [$month, $year]));
        $rows = $select->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) !== count($ids)) {
            throw new RuntimeException(t('hr.payroll_error_invalid_selection'));
        }
        foreach ($rows as $row) {
            if ((float)$row['basic_salary'] <= 0) {
                throw new RuntimeException(t('hr.payroll_error_selected_salary'));
            }
        }

        $update = $pdo->prepare(
            "UPDATE payroll
             SET status = 'approved', accounting_status = 'ready'
             WHERE id IN ($placeholders)
               AND month = ? AND year = ? AND status = 'draft'"
        );
        $update->execute(array_merge($ids, [$month, $year]));
        if ($update->rowCount() !== count($ids)) {
            throw new RuntimeException(t('hr.payroll_error_transaction'));
        }

        $pdo->commit();
        return count($ids);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}


/**
 * Notify the linked employee after payroll payment and accounting have committed.
 *
 * The notification is informational; the paid payroll and posted journal remain
 * authoritative. Failures must not make a completed financial action look failed.
 */
function hrPayrollNotifyStatementAvailable(PDO $pdo, int $payrollId): bool
{
    if ($payrollId <= 0) {
        return false;
    }

    try {
        $row = dbFetchOne(
            "SELECT p.id, p.month, p.year, p.net_salary, p.incentive_amount,
                    e.user_id AS employee_user_id
             FROM payroll p
             JOIN employees e ON e.id = p.employee_id
             JOIN users u ON u.id = e.user_id AND u.is_active = 1
             WHERE p.id = ?
               AND p.status = 'paid'
               AND p.accounting_status = 'posted'
             LIMIT 1",
            [$payrollId]
        );
        if (!$row) {
            return false;
        }

        $recipientId = (int)($row['employee_user_id'] ?? 0);
        if ($recipientId <= 0) {
            return false;
        }

        $link = APP_URL . 'modules/hr/payroll_statement.php?id=' . $payrollId;
        $title = 'كشف تفاصيل الراتب جاهز';
        $period = sprintf('%04d-%02d', (int)$row['year'], (int)$row['month']);
        $totalPaid = round((float)$row['net_salary'] + (float)($row['incentive_amount'] ?? 0), 2);
        $body = 'أصبح كشف تفاصيل راتبك للفترة ' . $period . ' متاحاً للعرض والطباعة. إجمالي المبلغ المصروف: '
            . number_format($totalPaid, 2) . ' ' . (defined('APP_CURRENCY_NAME_AR') ? APP_CURRENCY_NAME_AR : 'ج.س.') . '.';

        $existing = dbFetchOne(
            "SELECT id FROM notifications
             WHERE recipient_user_id = ? AND link = ? AND title = ?
             LIMIT 1",
            [$recipientId, $link, $title]
        );
        if ($existing) {
            return true;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO notifications
                (recipient_user_id, type, title, body, link, is_read, created_at)
             VALUES (?, 'info', ?, ?, ?, 0, NOW())"
        );
        $stmt->execute([$recipientId, $title, $body, $link]);
        return $stmt->rowCount() === 1;
    } catch (Throwable $notificationError) {
        // Payroll and accounting have already committed. Never report them as failed
        // because an informational notification could not be delivered.
        return false;
    }
}
