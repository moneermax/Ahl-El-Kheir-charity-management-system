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
    $expectedType = ['5500' => 'expense', '2400' => 'liability'][$code] ?? null;
    if ($expectedType === null) {
        throw new InvalidArgumentException('رمز حساب الحوافز غير مدعوم: ' . $code);
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM accounts
         WHERE code = ? AND is_active = 1 AND account_type = ?
         LIMIT 1"
    );
    $stmt->execute([$code, $expectedType]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id <= 0) {
        throw new RuntimeException('حساب الحوافز المطلوب غير موجود أو غير نشط أو نوعه غير صحيح: ' . $code);
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
                $amount <= 0
                    ? 'مبلغ الحافز غير صالح؛ يلزم تصحيح معتمد قبل الإثبات.'
                    : 'عملة الحافز لا تطابق عملة الدفاتر؛ يلزم حل معتمد للعملة دون تحويل تلقائي.',
                $awardId
            ]);
            if ($startedHere) {
                $pdo->commit();
            }
            // Return 0 so an outer transaction can commit the hold instead of
            // rolling it back in response to an exception. Callers must treat
            // zero as "held; no recognition journal was posted".
            return 0;
        }

        $expenseId = hrIncentiveAccountId($pdo, '5500');
        $payableId = hrIncentiveAccountId($pdo, '2400');
        $createdBy = $actorUserId > 0 ? $actorUserId : null;
        $entry = $pdo->prepare(
            "INSERT INTO journal_entries
             (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?, DATE(?), ?, 'hr_incentive_award_recognition', ?, 'posted', ?)"
        );
        $entryId = 0;
        $lastCodeCollision = null;
        // JE codes follow the existing MAX+1 convention, which can race when
        // different awards are recognized concurrently. Retry only a duplicate
        // entry-code collision; never mask unrelated SQL errors.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $entryCode = hrIncentiveNextJournalCode($pdo);
            try {
                $entry->execute([
                    $entryCode,
                    (string)$award['occurred_at'],
                    'إثبات استحقاق حافز موظف #' . $awardId . ' - ' . $currency,
                    $awardId,
                    $createdBy
                ]);
                $entryId = (int)$pdo->lastInsertId();
                break;
            } catch (PDOException $e) {
                $errorInfo = $e->errorInfo ?? [];
                $isDuplicateKey = (int)($errorInfo[1] ?? 0) === 1062;
                $errorText = strtolower($e->getMessage());
                $isEntryCodeCollision = $isDuplicateKey &&
                    (str_contains($errorText, 'uq_je_code') ||
                     str_contains($errorText, 'entry_code'));
                if (!$isEntryCodeCollision) {
                    throw $e;
                }
                $lastCodeCollision = $e;
            }
        }
        if ($entryId <= 0) {
            if ($lastCodeCollision instanceof PDOException) {
                throw new RuntimeException(
                    'تعذر تخصيص رمز قيد فريد للحافز بعد خمس محاولات بسبب تصادم رموز القيود.',
                    0,
                    $lastCodeCollision
                );
            }
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

/**
 * Record one qualifying source event for later manager verification.
 *
 * Call only from the transaction that durably confirms the source business
 * event. A missing active/effective policy or disabled rule means no event is
 * created. Duplicate source notifications return the existing event ID.
 */
/**
 * Resolve a user to exactly one employee row. Ambiguous/missing mappings are
 * deliberately not guessed because an incentive must have a real employee recipient.
 */
function hrIncentiveEmployeeIdForUser(PDO $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT id FROM employees WHERE user_id = ? ORDER BY id LIMIT 2');
    $stmt->execute([$userId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return count($ids) === 1 && $ids[0] > 0 ? $ids[0] : 0;
}

/**
 * Record a reactivation event only for the first financially confirmed
 * monthly-sponsorship payment created after a durable win-back transition.
 * Caller must be inside the payment-confirmation transaction.
 */
function hrIncentiveRecordReactivationPaymentEvent(PDO $pdo, int $paymentId, int $createdByUserId = 0): int
{
    if ($paymentId <= 0) {
        throw new InvalidArgumentException('مرجع دفعة إعادة التفعيل غير صالح.');
    }
    if (!$pdo->inTransaction()) {
        throw new RuntimeException('يجب تسجيل حافز الاستعادة ضمن معاملة اعتماد الدفعة.');
    }

    $paymentStmt = $pdo->prepare(
        "SELECT sp.id, sp.payment_type, sp.sponsor_id, sp.sponsorship_id,
                sp.amount, sp.currency_code, sp.status, sp.reviewed_at,
                sp.created_at, sp.transaction_id,
                COALESCE(sp.sponsor_id, s.sponsor_id) AS resolved_sponsor_id,
                t.status AS transaction_status
         FROM sponsor_payments sp
         LEFT JOIN sponsorships s ON s.id = sp.sponsorship_id
         LEFT JOIN transactions t ON t.id = sp.transaction_id
         WHERE sp.id = ?
         LIMIT 1 FOR UPDATE"
    );
    $paymentStmt->execute([$paymentId]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment
        || $payment['payment_type'] !== 'monthly_sponsorship'
        || $payment['status'] !== 'approved'
        || (int)($payment['transaction_id'] ?? 0) <= 0
        || $payment['transaction_status'] !== 'posted'
        || empty($payment['reviewed_at'])
        || (int)($payment['resolved_sponsor_id'] ?? 0) <= 0) {
        return 0;
    }

    $journalStmt = $pdo->prepare(
        "SELECT id FROM journal_entries
         WHERE reference_type = 'transaction'
           AND reference_id = ?
           AND status = 'posted'
         LIMIT 1"
    );
    $journalStmt->execute([(int)$payment['transaction_id']]);
    if (!(int)$journalStmt->fetchColumn()) {
        return 0;
    }

    $sponsorId = (int)$payment['resolved_sponsor_id'];
    $campaignStmt = $pdo->prepare(
        "SELECT id, reactivated_by_user_id, reactivated_at
         FROM winback_campaigns
         WHERE sponsor_id = ?
           AND status = 'persuaded'
           AND reactivated_by_user_id IS NOT NULL
           AND reactivated_at IS NOT NULL
           AND reactivated_at < ?
           AND reactivated_at <= ?
         ORDER BY reactivated_at DESC, id DESC
         LIMIT 1 FOR UPDATE"
    );
    $campaignStmt->execute([$sponsorId, $payment['created_at'], $payment['reviewed_at']]);
    $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        return 0;
    }

    $reactivatedAt = new DateTimeImmutable((string)$campaign['reactivated_at']);
    $priorPaymentStmt = $pdo->prepare(
        "SELECT MAX(old.reviewed_at)
         FROM sponsor_payments old
         LEFT JOIN sponsorships old_s ON old_s.id = old.sponsorship_id
         JOIN transactions old_t ON old_t.id = old.transaction_id AND old_t.status = 'posted'
         WHERE old.payment_type = 'monthly_sponsorship'
           AND old.status = 'approved'
           AND old.reviewed_at IS NOT NULL
           AND old.reviewed_at < ?
           AND COALESCE(old.sponsor_id, old_s.sponsor_id) = ?
           AND EXISTS (
               SELECT 1 FROM journal_entries old_j
               WHERE old_j.reference_type = 'transaction'
                 AND old_j.reference_id = old_t.id
                 AND old_j.status = 'posted'
           )"
    );
    $priorPaymentStmt->execute([(string)$campaign['reactivated_at'], $sponsorId]);
    $lastPriorPaymentAt = $priorPaymentStmt->fetchColumn();
    if (!$lastPriorPaymentAt) {
        return 0;
    }
    $minimumLapseBoundary = $reactivatedAt->modify('-90 days');
    if (new DateTimeImmutable((string)$lastPriorPaymentAt) > $minimumLapseBoundary) {
        return 0;
    }

    // Serialize claims per campaign above, then allow only one confirmed payment
    // after the reactivation transition. Same-second ties are conservatively held.
    $earlierReturnPaymentStmt = $pdo->prepare(
        "SELECT other.id
         FROM sponsor_payments other
         LEFT JOIN sponsorships other_s ON other_s.id = other.sponsorship_id
         JOIN transactions other_t ON other_t.id = other.transaction_id AND other_t.status = 'posted'
         WHERE other.id <> ?
           AND other.payment_type = 'monthly_sponsorship'
           AND other.status = 'approved'
           AND other.reviewed_at IS NOT NULL
           AND other.reviewed_at <= ?
           AND other.created_at > ?
           AND COALESCE(other.sponsor_id, other_s.sponsor_id) = ?
           AND EXISTS (
               SELECT 1 FROM journal_entries other_j
               WHERE other_j.reference_type = 'transaction'
                 AND other_j.reference_id = other_t.id
                 AND other_j.status = 'posted'
           )
         LIMIT 1"
    );
    $earlierReturnPaymentStmt->execute([
        $paymentId,
        $payment['reviewed_at'],
        (string)$campaign['reactivated_at'],
        $sponsorId,
    ]);
    if ($earlierReturnPaymentStmt->fetchColumn()) {
        return 0;
    }

    $recipientUserId = (int)$campaign['reactivated_by_user_id'];
    $recipientEmployeeId = hrIncentiveEmployeeIdForUser($pdo, $recipientUserId);
    if ($recipientEmployeeId <= 0) {
        return 0;
    }

    return hrIncentiveRecordSourceEvent(
        $pdo,
        'sponsor_payments',
        $paymentId,
        'reactivation',
        (string)$payment['reviewed_at'],
        (float)$payment['amount'],
        (string)($payment['currency_code'] ?: 'SDG'),
        $createdByUserId,
        $recipientUserId,
        $recipientEmployeeId
    );
}

function hrIncentiveRecordSourceEvent(
    PDO $pdo,
    string $sourceTable,
    int $sourceRecordId,
    string $incentiveType,
    string $occurredAt,
    ?float $sourceAmount = null,
    ?string $sourceCurrencyCode = null,
    int $createdByUserId = 0,
    ?int $recipientUserId = null,
    ?int $recipientEmployeeId = null
): int {
    $allowedSources = [
        'collection' => 'sponsor_payments',
        'new_sponsor' => 'sponsor_payments',
        'reactivation' => 'sponsor_payments',
        'project_completion' => 'project_lifecycle',
    ];
    if ($sourceRecordId <= 0 || !isset($allowedSources[$incentiveType])
        || $allowedSources[$incentiveType] !== $sourceTable) {
        throw new InvalidArgumentException('مصدر حدث الحافز أو فئته غير صالح.');
    }

    $occurred = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $occurredAt);
    if (!$occurred || $occurred->format('Y-m-d H:i:s') !== $occurredAt) {
        throw new InvalidArgumentException('تاريخ حدث الحافز غير صالح.');
    }
    if ($sourceAmount !== null && (!is_finite($sourceAmount) || $sourceAmount < 0)) {
        throw new InvalidArgumentException('مبلغ مصدر الحافز غير صالح.');
    }

    $currency = $sourceCurrencyCode !== null ? strtoupper(trim($sourceCurrencyCode)) : null;
    if ($currency !== null && !preg_match('/^[A-Z]{3}$/', $currency)) {
        throw new InvalidArgumentException('عملة مصدر الحافز غير صالحة.');
    }

    $idempotencyKey = 'hr-incentive:' . $sourceTable . ':' . $sourceRecordId . ':' . $incentiveType;
    $existingStmt = $pdo->prepare('SELECT id FROM hr_incentive_events WHERE idempotency_key = ? LIMIT 1');
    $existingStmt->execute([$idempotencyKey]);
    $existingId = (int)($existingStmt->fetchColumn() ?: 0);
    if ($existingId > 0) {
        return $existingId;
    }

    $policyStmt = $pdo->prepare(
        "SELECT p.id
         FROM hr_incentive_policy_versions p
         JOIN hr_incentive_policy_rules r ON r.policy_version_id = p.id
         JOIN hr_incentive_types t ON t.type_code = r.incentive_type
         WHERE p.status = 'active'
           AND p.effective_from <= DATE(?)
           AND (p.effective_to IS NULL OR p.effective_to >= DATE(?))
           AND r.incentive_type = ?
           AND r.is_enabled = 1
           AND t.is_active = 1
           AND t.qualification_mode = 'source_event'
         ORDER BY p.effective_from DESC, p.version_no DESC
         LIMIT 1
         FOR UPDATE"
    );
    $policyStmt->execute([$occurredAt, $occurredAt, $incentiveType]);
    $policyVersionId = (int)($policyStmt->fetchColumn() ?: 0);
    if ($policyVersionId <= 0) {
        return 0;
    }

    try {
        $insert = $pdo->prepare(
            "INSERT INTO hr_incentive_events
             (idempotency_key, source_table, source_record_id, incentive_type,
              policy_version_id, occurred_at, source_amount, source_currency_code,
              recipient_user_id, recipient_employee_id, status, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_verification', ?)"
        );
        $insert->execute([
            $idempotencyKey,
            $sourceTable,
            $sourceRecordId,
            $incentiveType,
            $policyVersionId,
            $occurredAt,
            $sourceAmount,
            $currency,
            ($recipientUserId !== null && $recipientUserId > 0) ? $recipientUserId : null,
            ($recipientEmployeeId !== null && $recipientEmployeeId > 0) ? $recipientEmployeeId : null,
            $createdByUserId > 0 ? $createdByUserId : null,
        ]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        $duplicate = (string)$e->getCode() === '23000'
            && (stripos($e->getMessage(), 'uq_hr_incentive_event_idempotency') !== false
                || stripos($e->getMessage(), 'idempotency_key') !== false);
        if (!$duplicate) {
            throw $e;
        }
        $existingStmt->execute([$idempotencyKey]);
        $existingId = (int)($existingStmt->fetchColumn() ?: 0);
        if ($existingId <= 0) {
            throw $e;
        }
        return $existingId;
    }
}

