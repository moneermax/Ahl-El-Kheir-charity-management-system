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

function hrPayrollPostAccounting(PDO $pdo, array $payroll): int
{
    require_once dirname(__DIR__) . '/accounting/lib.php';

    // IMPORTANT: ak_ensure_tables() contains DDL (CREATE TABLE IF NOT EXISTS).
    // DDL may implicitly commit a MySQL/MariaDB transaction, so it MUST NOT
    // be called while the payroll transaction is active. Initialization is
    // performed by hrPayrollChangeStatus() before beginTransaction().

    $amount = (float)($payroll['net_salary'] ?? 0);
    if ($amount <= 0) {
        throw new RuntimeException('لا يمكن ترحيل مسير راتب بصافي راتب غير صالح إلى المحاسبة.');
    }

    $expenseStmt = $pdo->prepare(
        "SELECT id FROM accounts WHERE code = '5200' LIMIT 1"
    );
    $expenseStmt->execute();
    $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$expense) {
        throw new RuntimeException('حساب الرواتب 5200 غير موجود في دليل الحسابات.');
    }
    $expenseAccountId = (int)$expense['id'];

    $requestedPaymentAccount = null;
    if (array_key_exists('payment_account_id', $payroll) && $payroll['payment_account_id'] !== null) {
        $requestedPaymentAccount = (int)$payroll['payment_account_id'];
    }
    $paymentAccountId = hrPayrollResolvePaymentAccount($pdo, $requestedPaymentAccount);

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
    } else {
        $entryCode = 'PAY-' . (int)$payroll['id'];
        $entryDate = !empty($payroll['payment_date']) ? (string)$payroll['payment_date'] : date('Y-m-d');
        $employeeName = trim((string)($payroll['employee_name'] ?? ''));
        if ($employeeName === '') {
            $employeeName = 'ID ' . (int)$payroll['employee_id'];
        }

        $createdBy = null;
        if (class_exists('Session')) {
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
        $lineStmt->execute([$entryId, $expenseAccountId, $amount, 0, 'رواتب وأجور - ' . $period]);
        $lineStmt->execute([$entryId, $paymentAccountId, 0, $amount, 'صرف رواتب - ' . $period]);
    }

    $update = $pdo->prepare(
        "UPDATE payroll
         SET accounting_entry_id = ?,
             accounting_status = 'posted'
         WHERE id = ?"
    );
    $update->execute([$entryId, (int)$payroll['id']]);

    return $entryId;
}

function hrPayrollChangeStatus(PDO $pdo, int $payrollId, string $newStatus, ?int $paymentAccountId = null): void
{
    if (!in_array($newStatus, ['approved', 'paid'], true)) {
        throw new RuntimeException('حالة الرواتب غير صالحة.');
    }
    if ($payrollId <= 0) {
        throw new RuntimeException('سجل الرواتب غير صالح.');
    }

    // Accounting table initialization contains DDL. Do it before opening
    // the transaction so it cannot implicitly commit the payroll transaction.
    require_once dirname(__DIR__) . '/accounting/lib.php';
    ak_ensure_tables();
    ak_seed_accounts();

    $startedHere = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedHere = true;
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
            hrPayrollPostAccounting($pdo, $row);
        }

        if ($startedHere) {
            $pdo->commit();
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
