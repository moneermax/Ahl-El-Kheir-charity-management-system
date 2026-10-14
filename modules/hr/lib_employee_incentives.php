<?php
declare(strict_types=1);

/**
 * Employee incentive award/accounting helpers.
 *
 * Callers own the surrounding business transaction. This file intentionally
 * performs no DDL and never changes a non-draft payroll's incentive component.
 */

function hrIncentiveAccountId(PDO $pdo, string $code): int
{
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$code]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id <= 0) {
        throw new RuntimeException('حساب الحوافز المطلوب غير موجود أو غير نشط: ' . $code);
    }
    return $id;
}

function hrIncentiveNextJournalCode(PDO $pdo): string
{
    $stmt = $pdo->query(
        "SELECT COALESCE(MAX(CASE WHEN entry_code REGEXP '^JE-[0-9]+$'
             THEN CAST(SUBSTRING(entry_code, 4) AS UNSIGNED) ELSE 0 END), 0) + 1
         FROM journal_entries"
    );
    $next = (int)$stmt->fetchColumn();
    if ($next <= 0) {
        throw new RuntimeException('تعذر تخصيص رقم القيد المحاسبي للحافز.');
    }
    return 'JE-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

/**
 * Recognize one verified award: Dr 5500 Employee Incentives Expense /
 * Cr 2400 Employee Incentives Payable. This does not schedule or pay it.
 */
function hrIncentiveRecognizeAward(PDO $pdo, int $awardId, int $actorUserId = 0): int
{
    if ($awardId <= 0) {
        throw new InvalidArgumentException('سجل الحافز غير صالح.');
    }

    $startedHere = !$pdo->inTransaction();
    if ($startedHere) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT a.*, e.status AS event_status, e.occurred_at
             FROM hr_incentive_awards a
             JOIN hr_incentive_events e ON e.id = a.event_id
             WHERE a.id = ? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$awardId]);
        $award = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$award) {
            throw new RuntimeException('سجل الحافز غير موجود.');
        }
        if ($award['event_status'] !== 'verified') {
            throw new RuntimeException('لا يمكن إثبات مصروف حافز قبل التحقق من الإنجاز.');
        }

        $existing = $pdo->prepare(
            "SELECT id FROM journal_entries
             WHERE reference_type = 'hr_incentive_award_recognition'
               AND reference_id = ? AND status = 'posted'
             ORDER BY id ASC LIMIT 1"
        );
        $existing->execute([$awardId]);
        $existingId = (int)($existing->fetchColumn() ?: 0);
        if ($existingId > 0) {
            $pdo->prepare(
                "UPDATE hr_incentive_awards SET recognition_journal_entry_id = ?
                 WHERE id = ? AND recognition_journal_entry_id IS NULL"
            )->execute([$existingId, $awardId]);
            if ($startedHere) {
                $pdo->commit();
            }
            return $existingId;
        }

        $amount = round((float)$award['reward_amount'], 2);
        $currency = strtoupper(trim((string)$award['reward_currency_code']));
        if ($amount <= 0 || $currency !== strtoupper((string)APP_CURRENCY_CODE)) {
            $pdo->prepare(
                "UPDATE hr_incentive_awards
                 SET status = 'held_currency',
                     hold_reason = ?
                 WHERE id = ? AND recognition_journal_entry_id IS NULL"
            )->execute([
                'عملة الحافز لا تطابق عملة الدفاتر؛ يلزم حل معتمد للعملة دون تحويل تلقائي.',
                $awardId
            ]);
            if ($startedHere) {
                $pdo->commit();
            }
            throw new RuntimeException('تم تعليق الحافز لأن عملته لا تطابق عملة الدفاتر.');
        }

        $expenseId = hrIncentiveAccountId($pdo, '5500');
        $payableId = hrIncentiveAccountId($pdo, '2400');
        $entryCode = hrIncentiveNextJournalCode($pdo);
        $createdBy = $actorUserId > 0 ? $actorUserId : null;
        $entry = $pdo->prepare(
            "INSERT INTO journal_entries
             (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?, DATE(?), ?, 'hr_incentive_award_recognition', ?, 'posted', ?)"
        );
        $entry->execute([
            $entryCode,
            (string)$award['occurred_at'],
            'إثبات استحقاق حافز موظف #' . $awardId . ' - ' . $currency,
            $awardId,
            $createdBy
        ]);
        $entryId = (int)$pdo->lastInsertId();
        if ($entryId <= 0) {
            throw new RuntimeException('تعذر إنشاء قيد إثبات الحافز.');
        }

        $line = $pdo->prepare(
            "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
             VALUES (?, ?, ?, ?, ?)"
        );
        $line->execute([$entryId, $expenseId, $amount, 0, 'مصروف حوافز الموظفين - الحافز #' . $awardId]);
        $line->execute([$entryId, $payableId, 0, $amount, 'مستحق حافز موظف - الحافز #' . $awardId]);

        $update = $pdo->prepare(
            "UPDATE hr_incentive_awards
             SET recognition_journal_entry_id = ?, status = 'earned', hold_reason = NULL
             WHERE id = ? AND recognition_journal_entry_id IS NULL"
        );
        $update->execute([$entryId, $awardId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('تغير سجل الحافز أثناء إثباته؛ تم إيقاف العملية.');
        }

        if ($startedHere) {
            $pdo->commit();
        }
        return $entryId;
    } catch (Throwable $e) {
        if ($startedHere && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Refresh the incentive component of a draft payroll and attach verified,
 * already-recognized awards that have no payroll assignment. Award rows are
 * locked before assignment, making repeated refreshes idempotent.
 */
function hrIncentiveRefreshPayrollDraft(PDO $pdo, int $payrollId): void
{
    if ($payrollId <= 0) {
        throw new InvalidArgumentException('مسير الرواتب غير صالح.');
    }

    $startedHere = !$pdo->inTransaction();
    if ($startedHere) {
        $pdo->beginTransaction();
    }

    try {
        $payrollStmt = $pdo->prepare(
            "SELECT p.id, p.employee_id, p.month, p.year, p.status,
                    s.salary_currency
             FROM payroll p
             LEFT JOIN hr_employee_salary_history s ON s.id = (
                 SELECT s2.id FROM hr_employee_salary_history s2
                 WHERE s2.employee_id = p.employee_id
                   AND s2.effective_from <= LAST_DAY(STR_TO_DATE(CONCAT(p.year,'-',LPAD(p.month,2,'0'),'-01'),'%Y-%m-%d'))
                   AND (s2.effective_to IS NULL OR s2.effective_to >= LAST_DAY(STR_TO_DATE(CONCAT(p.year,'-',LPAD(p.month,2,'0'),'-01'),'%Y-%m-%d')))
                 ORDER BY s2.effective_from DESC, s2.id DESC LIMIT 1
             )
             WHERE p.id = ? LIMIT 1 FOR UPDATE"
        );
        $payrollStmt->execute([$payrollId]);
        $payroll = $payrollStmt->fetch(PDO::FETCH_ASSOC);
        if (!$payroll) {
            throw new RuntimeException('مسير الرواتب غير موجود.');
        }
        if ($payroll['status'] !== 'draft') {
            if ($startedHere) {
                $pdo->commit();
            }
            return;
        }

        $salaryCurrency = strtoupper(trim((string)($payroll['salary_currency'] ?? '')));
        $systemCurrency = strtoupper((string)APP_CURRENCY_CODE);
        $pendingStmt = $pdo->prepare(
            "SELECT a.id, a.reward_currency_code, a.recognition_journal_entry_id
             FROM hr_incentive_awards a
             JOIN hr_incentive_events e ON e.id = a.event_id
             WHERE a.employee_id = ?
               AND a.payroll_id IS NULL
               AND a.status IN ('earned','held_currency')
               AND e.status = 'verified'
             ORDER BY a.earned_at ASC, a.id ASC
             FOR UPDATE"
        );
        $pendingStmt->execute([(int)$payroll['employee_id']]);
        $assign = $pdo->prepare(
            "UPDATE hr_incentive_awards
             SET payroll_id = ?, status = 'scheduled', scheduled_at = NOW(), hold_reason = NULL
             WHERE id = ? AND payroll_id IS NULL AND status IN ('earned','held_currency')"
        );
        $hold = $pdo->prepare(
            "UPDATE hr_incentive_awards
             SET status = 'held_currency', hold_reason = ?
             WHERE id = ? AND payroll_id IS NULL AND status IN ('earned','held_currency')"
        );

        foreach ($pendingStmt->fetchAll(PDO::FETCH_ASSOC) as $award) {
            $awardCurrency = strtoupper(trim((string)$award['reward_currency_code']));
            if (empty($award['recognition_journal_entry_id'])) {
                continue;
            }
            if ($salaryCurrency === '' || $awardCurrency !== $salaryCurrency || $awardCurrency !== $systemCurrency) {
                $hold->execute([
                    'عملة الحافز لا تطابق عملة راتب الموظف وعملة الدفاتر؛ لا يتم إجراء تحويل تلقائي.',
                    (int)$award['id']
                ]);
                continue;
            }
            $assign->execute([$payrollId, (int)$award['id']]);
        }

        $sumStmt = $pdo->prepare(
            "SELECT ROUND(COALESCE(SUM(reward_amount), 0), 2)
             FROM hr_incentive_awards
             WHERE payroll_id = ? AND status = 'scheduled'"
        );
        $sumStmt->execute([$payrollId]);
        $amount = round((float)$sumStmt->fetchColumn(), 2);
        if ($amount < 0 || $amount > 9999999999.99) {
            throw new RuntimeException('إجمالي الحوافز المجدولة خارج النطاق المسموح.');
        }
        $update = $pdo->prepare(
            "UPDATE payroll SET incentive_amount = ?
             WHERE id = ? AND status = 'draft'"
        );
        $update->execute([$amount, $payrollId]);

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

/**
 * Mark the payroll's scheduled awards paid after their balanced settlement
 * lines have been included in the payroll journal.
 */
function hrIncentiveMarkPayrollAwardsPaid(PDO $pdo, int $payrollId, int $journalEntryId): void
{
    if ($payrollId <= 0 || $journalEntryId <= 0) {
        throw new InvalidArgumentException('مراجع تسوية الحوافز غير صالحة.');
    }
    $stmt = $pdo->prepare(
        "SELECT id, reward_amount, status, settlement_journal_entry_id
         FROM hr_incentive_awards
         WHERE payroll_id = ? AND status IN ('scheduled','paid')
         ORDER BY id ASC FOR UPDATE"
    );
    $stmt->execute([$payrollId]);
    $awards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sum = 0.0;
    foreach ($awards as $award) {
        $sum += round((float)$award['reward_amount'], 2);
        if ($award['status'] === 'paid' && (int)($award['settlement_journal_entry_id'] ?? 0) !== $journalEntryId) {
            throw new RuntimeException('الحافز مسجل كمدفوع بقيد تسوية مختلف عن قيد مسير الرواتب.');
        }
    }

    $payrollStmt = $pdo->prepare("SELECT incentive_amount FROM payroll WHERE id = ? FOR UPDATE");
    $payrollStmt->execute([$payrollId]);
    $payrollAmount = round((float)($payrollStmt->fetchColumn() ?: 0), 2);
    if (round($sum, 2) !== $payrollAmount) {
        throw new RuntimeException('إجمالي الحوافز المجدولة لا يطابق مكون الحوافز في مسير الرواتب.');
    }
    $update = $pdo->prepare(
        "UPDATE hr_incentive_awards
         SET status = 'paid', paid_at = NOW(), settlement_journal_entry_id = ?
         WHERE id = ? AND payroll_id = ? AND status = 'scheduled'"
    );
    foreach ($awards as $award) {
        if ($award['status'] === 'paid') {
            continue;
        }
        $update->execute([$journalEntryId, (int)$award['id'], $payrollId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('تغيرت حالة أحد الحوافز أثناء التسوية.');
        }
    }
}
