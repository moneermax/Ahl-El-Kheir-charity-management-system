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
                hrIncentiveDispatchPendingNotifications($pdo);
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

        // Queue the earned notice atomically with recognition. The unique
        // award key prevents duplicate notices on retries; delivery is post-commit.
        $recipientUserId = (int)$award['recipient_user_id'];
        if ($recipientUserId <= 0) {
            throw new RuntimeException('لا يوجد مستخدم مستلم صالح لإشعار استحقاق الحافز.');
        }
        $outbox = $pdo->prepare(
            "INSERT INTO hr_incentive_notification_outbox
                (award_id, recipient_user_id, status)
             VALUES (?, ?, 'pending')
             ON DUPLICATE KEY UPDATE award_id = VALUES(award_id)"
        );
        $outbox->execute([$awardId, $recipientUserId]);

        if ($startedHere) {
            $pdo->commit();
            hrIncentiveDispatchPendingNotifications($pdo);
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
 * Deliver pending earned-incentive notices after the award-recognition
 * transaction commits. The outbox row and notification insert are completed
 * atomically; a delivery failure leaves the row pending for a later retry.
 */
function hrIncentiveDispatchPendingNotifications(PDO $pdo, ?int $onlyOutboxId = null): void
{
    if ($pdo->inTransaction()) {
        // Never emit a user-visible notification before the caller commits.
        return;
    }

    try {
        if ($onlyOutboxId !== null && $onlyOutboxId <= 0) return;
        $sql = "SELECT id FROM hr_incentive_notification_outbox WHERE status = 'pending'";
        $params = [];
        if ($onlyOutboxId !== null) {
            $sql .= " AND id = ?";
            $params[] = $onlyOutboxId;
        }
        $sql .= " ORDER BY id ASC LIMIT 100";
        $pendingStmt = $pdo->prepare($sql);
        $pendingStmt->execute($params);
        $pending = $pendingStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        error_log('HR incentive notification outbox could not be read: ' . $e->getMessage());
        return;
    }

    foreach ($pending as $outboxIdValue) {
        $outboxId = (int)$outboxIdValue;
        if ($outboxId <= 0) {
            continue;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT o.id, o.award_id, o.recipient_user_id, o.status,
                        a.reward_amount, a.reward_currency_code
                 FROM hr_incentive_notification_outbox o
                 JOIN hr_incentive_awards a ON a.id = o.award_id
                 WHERE o.id = ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([$outboxId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || $row['status'] !== 'pending') {
                $pdo->commit();
                continue;
            }

            $recipientUserId = (int)$row['recipient_user_id'];
            if ($recipientUserId <= 0) {
                throw new RuntimeException('مستلم إشعار الحافز غير صالح.');
            }

            $amount = number_format((float)$row['reward_amount'], 2, '.', ',');
            $currency = strtoupper(trim((string)$row['reward_currency_code']));
            $title = 'تم إثبات استحقاقك لحافز';
            $body = 'تم إثبات استحقاقك لحافز بقيمة ' . $amount . ' ' . $currency
                . ' نظير الإنجاز الذي حققته. سيُدرج الحافز في مسير الرواتب المؤهل ويُصرف في موعد الصرف المعتاد بعد استكمال إجراءات اعتماد المسير والصرف. هذا الإشعار يؤكد الاستحقاق ولا يعني أن المبلغ صُرف بالفعل.';
            $link = APP_URL . 'modules/notifications/index.php';

            // Controlled test seam: production does not define this function.
            if (function_exists('hr_incentive_notification_test_hook')) {
                hr_incentive_notification_test_hook(
                    $recipientUserId,
                    $title,
                    $body,
                    $link,
                    (int)$row['award_id']
                );
            }

            $insert = $pdo->prepare(
                "INSERT INTO notifications
                    (recipient_user_id, type, title, body, link, is_read, created_at)
                 VALUES (?, 'incentive', ?, ?, ?, 0, NOW())"
            );
            $insert->execute([$recipientUserId, $title, $body, $link]);
            $notificationId = (int)$pdo->lastInsertId();
            if ($notificationId <= 0) {
                throw new RuntimeException('تعذر حفظ إشعار استحقاق الحافز.');
            }

            $markSent = $pdo->prepare(
                "UPDATE hr_incentive_notification_outbox
                 SET status = 'sent', notification_id = ?, sent_at = NOW(),
                     attempt_count = attempt_count + 1,
                     last_attempt_at = NOW(), last_error = NULL
                 WHERE id = ? AND status = 'pending'"
            );
            $markSent->execute([$notificationId, $outboxId]);
            if ($markSent->rowCount() !== 1) {
                throw new RuntimeException('تغيرت حالة إشعار الحافز أثناء الإرسال.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Record the failed attempt separately; never undo the committed
            // award-recognition transaction because delivery failed.
            try {
                $failed = $pdo->prepare(
                    "UPDATE hr_incentive_notification_outbox
                     SET attempt_count = attempt_count + 1,
                         last_attempt_at = NOW(), last_error = ?
                     WHERE id = ? AND status = 'pending'"
                );
                $failed->execute([mb_substr($e->getMessage(), 0, 1000), $outboxId]);
            } catch (Throwable $recordError) {
                error_log('HR incentive notification failure could not be recorded: ' . $recordError->getMessage());
            }
            error_log('HR incentive earned-notice delivery failed for outbox ' . $outboxId . ': ' . $e->getMessage());
        }
    }
}

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
                hrIncentiveDispatchPendingNotifications($pdo);
            }
            return;
        }

        $salaryCurrency = strtoupper(trim((string)($payroll['salary_currency'] ?? '')));
        $systemCurrency = strtoupper((string)APP_CURRENCY_CODE);
        $pendingStmt = $pdo->prepare(
            "SELECT a.id, a.recipient_user_id, a.reward_currency_code, a.recognition_journal_entry_id
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
            // The earned-notice outbox row is created during recognition,
            // independently of whether a payroll draft exists yet.
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
            hrIncentiveDispatchPendingNotifications($pdo);
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
 * Record a new-sponsor event only for the sponsor's first financially confirmed
 * monthly-sponsorship payment. Caller must own the payment-confirmation transaction.
 */
function hrIncentiveRecordNewSponsorPaymentEvent(PDO $pdo, int $paymentId, int $createdByUserId = 0): int
{
    if ($paymentId <= 0) {
        throw new InvalidArgumentException('مرجع دفعة الكفيل الجديد غير صالح.');
    }
    if (!$pdo->inTransaction()) {
        throw new RuntimeException('يجب تسجيل حافز استقطاب الكفيل ضمن معاملة اعتماد الدفعة.');
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
        || (int)($payment['resolved_sponsor_id'] ?? 0) <= 0
        || empty($payment['created_at'])) {
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

    $sponsorStmt = $pdo->prepare(
        "SELECT id, brought_by_user_id, created_at
         FROM sponsors WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $sponsorStmt->execute([(int)$payment['resolved_sponsor_id']]);
    $sponsor = $sponsorStmt->fetch(PDO::FETCH_ASSOC);
    $recipientUserId = (int)($sponsor['brought_by_user_id'] ?? 0);
    if (!$sponsor || $recipientUserId <= 0
        || empty($sponsor['created_at'])
        || (string)$payment['created_at'] < (string)$sponsor['created_at']) {
        return 0;
    }

    // Do not classify a sponsor already explicitly reactivated through win-back
    // as a new acquisition, even if legacy payment history is incomplete.
    $reactivationStmt = $pdo->prepare(
        "SELECT id FROM winback_campaigns
         WHERE sponsor_id = ?
           AND status = 'persuaded'
           AND reactivated_by_user_id IS NOT NULL
           AND reactivation_confirmed_by_user_id IS NOT NULL
           AND reactivated_at IS NOT NULL
           AND reactivated_at <= ?
         LIMIT 1 FOR UPDATE"
    );
    $reactivationStmt->execute([(int)$sponsor['id'], (string)$payment['reviewed_at']]);
    if ($reactivationStmt->fetchColumn()) {
        return 0;
    }

    // The first financially confirmed payment wins. A tied confirmation time
    // is treated as ambiguous and held rather than selecting an arbitrary row.
    $earlierPaymentStmt = $pdo->prepare(
        "SELECT other.id
         FROM sponsor_payments other
         LEFT JOIN sponsorships other_s ON other_s.id = other.sponsorship_id
         JOIN transactions other_t
           ON other_t.id = other.transaction_id AND other_t.status = 'posted'
         WHERE other.id <> ?
           AND other.payment_type = 'monthly_sponsorship'
           AND other.status = 'approved'
           AND other.reviewed_at IS NOT NULL
           AND other.reviewed_at <= ?
           AND COALESCE(other.sponsor_id, other_s.sponsor_id) = ?
           AND EXISTS (
               SELECT 1 FROM journal_entries other_j
               WHERE other_j.reference_type = 'transaction'
                 AND other_j.reference_id = other_t.id
                 AND other_j.status = 'posted'
           )
         LIMIT 1 FOR UPDATE"
    );
    $earlierPaymentStmt->execute([
        $paymentId,
        (string)$payment['reviewed_at'],
        (int)$sponsor['id'],
    ]);
    if ($earlierPaymentStmt->fetchColumn()) {
        return 0;
    }

    $recipientEmployeeId = hrIncentiveEmployeeIdForUser($pdo, $recipientUserId);
    if ($recipientEmployeeId <= 0) {
        error_log('Incentive new-sponsor event skipped: brought_by_user_id has no unique employee mapping. Payment ID=' . $paymentId);
        return 0;
    }

    return hrIncentiveRecordSourceEvent(
        $pdo,
        'sponsor_payments',
        $paymentId,
        'new_sponsor',
        (string)$payment['reviewed_at'],
        (float)$payment['amount'],
        (string)($payment['currency_code'] ?: 'SDG'),
        $createdByUserId,
        $recipientUserId,
        $recipientEmployeeId
    );
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
           AND reactivation_confirmed_by_user_id IS NOT NULL
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
        "SELECT MAX(prior_sp.reviewed_at)
         FROM sponsor_payments prior_sp
         LEFT JOIN sponsorships prior_s ON prior_s.id = prior_sp.sponsorship_id
         JOIN transactions prior_t ON prior_t.id = prior_sp.transaction_id AND prior_t.status = 'posted'
         WHERE prior_sp.payment_type = 'monthly_sponsorship'
           AND prior_sp.status = 'approved'
           AND prior_sp.reviewed_at IS NOT NULL
           AND prior_sp.reviewed_at < ?
           AND COALESCE(prior_sp.sponsor_id, prior_s.sponsor_id) = ?
           AND EXISTS (
               SELECT 1 FROM journal_entries prior_j
               WHERE prior_j.reference_type = 'transaction'
                 AND prior_j.reference_id = prior_t.id
                 AND prior_j.status = 'posted'
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
    // after reactivation. Equal-second confirmations are treated as already claimed
    // once one transaction has recorded the event.
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
         LIMIT 1 FOR UPDATE"
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
        error_log('Incentive reactivation event skipped: actor has no unique employee mapping. Payment ID=' . $paymentId);
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

/**
 * Persist an immediate employee-facing notice in the same transaction as the
 * qualifying activity. This is an activity notice, not a claim that the award
 * is already earned or paid; later verification/financial gates remain intact.
 */
function hrIncentiveNotifyActivityRecorded(
    PDO $pdo,
    int $recipientUserId,
    string $incentiveType,
    float $amount,
    string $currency,
    bool $awaitingFinancialClosure = false,
    bool $throwOnFailure = false
): void {
    if ($recipientUserId <= 0 || !in_array($incentiveType, ['new_sponsor', 'reactivation', 'project_completion'], true)) {
        return;
    }
    if (!is_finite($amount) || $amount <= 0 || !preg_match('/^[A-Z]{3}$/', strtoupper(trim($currency)))) {
        throw new RuntimeException('بيانات إشعار الإنجاز المؤهل للحافز غير صالحة.');
    }

    $labels = [
        'new_sponsor' => 'تسجيل كفيل جديد',
        'reactivation' => 'إعادة تفعيل كفيل',
        'project_completion' => 'إكمال مشروع',
    ];
    $title = 'تم تسجيل نشاط مرتبط بحافز';
    $amountText = number_format($amount, 2, '.', ',') . ' ' . strtoupper(trim($currency));
    if ($awaitingFinancialClosure || $incentiveType === 'project_completion') {
        $body = 'تم تسجيل مشاركتك المؤهلة في ' . $labels[$incentiveType]
            . ' بقيمة حافز متوقعة ' . $amountText
            . '. ينتظر الاستحقاق الإغلاق المالي النهائي والتحقق المطلوب. أرسلنا لك هذا الإشعار فور تسجيل الإنجاز؛ ولا يعني أن الحافز اعتُمد نهائياً أو صُرف.';
    } else {
        $body = 'تم تسجيل نشاط ' . $labels[$incentiveType]
            . ' مرتبط بسياسة الحوافز، بقيمة حافز متوقعة ' . $amountText
            . '. ينتظر الاستحقاق التحقق المالي من العملية واستكمال التحقق المطلوب قبل تأكيده. أرسلنا لك هذا الإشعار فور تسجيل النشاط؛ ولا يعني أن الحافز اعتُمد نهائياً أو صُرف.';
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO notifications
                (recipient_user_id, type, title, body, link, is_read, created_at)
             VALUES (?, 'incentive', ?, ?, ?, 0, NOW())"
        );
        $stmt->execute([$recipientUserId, $title, $body, APP_URL . 'modules/notifications/index.php']);
    } catch (Throwable $notificationError) {
        error_log('Immediate incentive activity notification insert failed for user ' . $recipientUserId . ': ' . $notificationError->getMessage());
        if ($throwOnFailure) {
            throw $notificationError;
        }
    }
}

/**
 * Notify the employee as soon as the qualifying activity is recorded, without
 * waiting for the later financial/management gate. This notice is provisional;
 * the existing qualification and award-recognition controls remain authoritative.
 */
function hrIncentiveNotifyPendingActivity(
    PDO $pdo,
    int $recipientUserId,
    string $incentiveType,
    string $occurredAt
): void {
    if ($recipientUserId <= 0
        || !in_array($incentiveType, ['new_sponsor', 'reactivation'], true)
        || hrIncentiveEmployeeIdForUser($pdo, $recipientUserId) <= 0) {
        return;
    }

    $policyStmt = $pdo->prepare(
        "SELECT r.reward_amount, r.currency_code
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
         LIMIT 1"
    );
    $policyStmt->execute([$occurredAt, $occurredAt, $incentiveType]);
    $rule = $policyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$rule || (float)$rule['reward_amount'] <= 0) {
        return;
    }

    hrIncentiveNotifyActivityRecorded(
        $pdo,
        $recipientUserId,
        $incentiveType,
        (float)$rule['reward_amount'],
        strtoupper(trim((string)$rule['currency_code'])),
        false
    );
}

/**
 * Record one qualifying source event for later manager verification.
 *
 * Call only from the transaction that durably confirms the source business
 * event. A missing active/effective policy or disabled rule means no event is
 * created. Duplicate source notifications return the existing event ID.
 */
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

    $hasRecipientUser = $recipientUserId !== null && $recipientUserId > 0;
    $hasRecipientEmployee = $recipientEmployeeId !== null && $recipientEmployeeId > 0;
    if ($hasRecipientUser !== $hasRecipientEmployee) {
        throw new InvalidArgumentException('يجب حفظ المستخدم والموظف المستفيدين معاً.');
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



/**
 * Snapshot eligible active project-team members plus the separately assigned
 * primary Project Supervisor when PM administrative closure is durable. Each eligible
 * employee receives the full policy amount; allocations
 * are not a shared pool and carry no fabricated human-approval metadata.
 */
function hrIncentivePrepareProjectCompletion(PDO $pdo, int $projectId, int $actorUserId = 0): int
{
    if ($projectId <= 0) {
        throw new InvalidArgumentException('معرّف المشروع غير صالح.');
    }

    $startedHere = !$pdo->inTransaction();
    if ($startedHere) {
        $pdo->beginTransaction();
    }

    try {
        $projectStmt = $pdo->prepare(
            "SELECT project_id, lifecycle_status, closed_at
             FROM project_lifecycle
             WHERE project_id = ? LIMIT 1 FOR UPDATE"
        );
        $projectStmt->execute([$projectId]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        if (!$project || $project['lifecycle_status'] !== 'closed' || empty($project['closed_at'])) {
            if ($startedHere) {
                $pdo->commit();
            }
            return 0;
        }

        $eventId = hrIncentiveRecordSourceEvent(
            $pdo,
            'project_lifecycle',
            $projectId,
            'project_completion',
            (string)$project['closed_at'],
            null,
            null,
            $actorUserId
        );
        if ($eventId <= 0) {
            throw new RuntimeException(
                'لا يمكن إغلاق المشروع قبل تسجيل حدث الحافز؛ لا توجد سياسة فعالة ومفعلة لحافز إكمال المشروع في تاريخ الإغلاق.'
            );
        }

        $eventStmt = $pdo->prepare(
            "SELECT id, policy_version_id, status, verification_note
             FROM hr_incentive_events WHERE id = ? LIMIT 1 FOR UPDATE"
        );
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            throw new RuntimeException('تعذر تحميل حدث حافز إكمال المشروع.');
        }

        // This marker means the closure-time recipient snapshot finished, even
        // when no eligible team member existed. It is not an approval marker.
        $snapshotMarker = 'system:project-recipient-snapshot-v1';
        if (strpos((string)($event['verification_note'] ?? ''), $snapshotMarker) === false) {
            if ($event['status'] !== 'pending_verification') {
                throw new RuntimeException('تعذر استكمال لقطة مستفيدي المشروع بعد انتقال حالة الحدث.');
            }

            $ruleStmt = $pdo->prepare(
                "SELECT reward_amount, currency_code, is_enabled
                 FROM hr_incentive_policy_rules
                 WHERE policy_version_id = ? AND incentive_type = 'project_completion'
                 LIMIT 1 FOR UPDATE"
            );
            $ruleStmt->execute([(int)$event['policy_version_id']]);
            $rule = $ruleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$rule || (int)$rule['is_enabled'] !== 1 || (float)$rule['reward_amount'] <= 0) {
                throw new RuntimeException('قاعدة حافز إكمال المشروع غير مفعلة أو مبلغها غير صالح.');
            }

            // The primary Project Supervisor is stored separately from project_team.
            // Use the latest active supervisor assignment, matching the Projects UI,
            // and UNION it with active team members so a person assigned through both
            // paths receives one allocation only.
            $teamStmt = $pdo->prepare(
                "SELECT recipients.user_id
                 FROM (
                     SELECT pt.user_id
                     FROM project_team pt
                     WHERE pt.project_id = ? AND pt.unassigned_at IS NULL
                     UNION
                     SELECT psa.supervisor_user_id AS user_id
                     FROM project_supervisor_assignments psa
                     WHERE psa.id = (
                         SELECT MAX(psa2.id)
                         FROM project_supervisor_assignments psa2
                         WHERE psa2.project_id = ? AND psa2.ended_at IS NULL
                     )
                 ) recipients
                 ORDER BY recipients.user_id"
            );
            $teamStmt->execute([$projectId, $projectId]);
            $insertAllocation = $pdo->prepare(
                "INSERT INTO hr_incentive_project_allocations
                    (event_id, employee_id, recipient_user_id, allocation_percent,
                     reward_amount, currency_code, approved_by_user_id, approved_at, allocation_note)
                 VALUES (?, ?, ?, 100.00, ?, ?, NULL, NULL, ?)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
            );
            $eligibilityStmt = $pdo->prepare(
                "SELECT e.id
                 FROM employees e
                 JOIN hr_employment_states es ON es.id = e.employment_state_id
                 JOIN users u ON u.id = e.user_id
                 WHERE e.id = ? AND e.user_id = ?
                   AND es.category = 'working' AND es.is_active = 1 AND u.is_active = 1
                 LIMIT 1"
            );

            foreach ($teamStmt->fetchAll(PDO::FETCH_COLUMN) as $userIdValue) {
                $userId = (int)$userIdValue;
                if ($userId <= 0) {
                    continue;
                }
                $employeeId = hrIncentiveEmployeeIdForUser($pdo, $userId);
                if ($employeeId <= 0) {
                    continue;
                }
                $eligibilityStmt->execute([$employeeId, $userId]);
                if (!$eligibilityStmt->fetchColumn()) {
                    continue;
                }
                $insertAllocation->execute([
                    $eventId,
                    $employeeId,
                    $userId,
                    round((float)$rule['reward_amount'], 2),
                    strtoupper(trim((string)$rule['currency_code'])),
                    'System snapshot at PM administrative closure; full policy amount per eligible employee.'
                ]);
                // Employee notification is deliberately deferred until FM final
                // financial closure has passed and award recognition commits.
                // PM closure only freezes the recipient and reward snapshot.
            }

            $markSnapshot = $pdo->prepare(
                "UPDATE hr_incentive_events
                 SET verification_note = ?
                 WHERE id = ? AND status = 'pending_verification'"
            );
            $markSnapshot->execute([$snapshotMarker, $eventId]);
            if ($markSnapshot->rowCount() !== 1) {
                throw new RuntimeException('تعذر تثبيت لقطة مستفيدي المشروع.');
            }
        }

        if ($startedHere) {
            $pdo->commit();
        }
        return $eventId;
    } catch (Throwable $e) {
        if ($startedHere && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Issue project-completion awards only after both durable closure gates pass:
 * lifecycle is administratively closed, FM_FINANCIAL_CLOSURE exists, and the
 * caller-provided controlled balance is within the existing 0.009 tolerance.
 * All awards and recognition journals commit together and are retry-safe.
 */
function hrIncentiveProcessProjectCompletion(PDO $pdo, int $projectId, float $controlledBalance, int $actorUserId = 0): int
{
    if ($projectId <= 0 || !is_finite($controlledBalance)) {
        throw new InvalidArgumentException('بيانات الإغلاق المالي للمشروع غير صالحة.');
    }
    if ($controlledBalance > 0.009) {
        return 0;
    }

    // Do not create a late recipient snapshot here: team eligibility must be
    // frozen atomically with PM closure. If that source event/snapshot is absent,
    // stop safely instead of guessing from the later team membership.
    $eventLookup = $pdo->prepare(
        "SELECT id FROM hr_incentive_events
         WHERE source_table = 'project_lifecycle' AND source_record_id = ?
           AND incentive_type = 'project_completion'
         LIMIT 1"
    );
    $eventLookup->execute([$projectId]);
    $eventId = (int)($eventLookup->fetchColumn() ?: 0);
    if ($eventId <= 0) {
        return 0;
    }

    $startedHere = !$pdo->inTransaction();
    if ($startedHere) {
        $pdo->beginTransaction();
    }
    try {
        // Re-read the project balance inside the processing transaction when
        // the project domain helper is available; the caller's value is only
        // an initial guard, not the final financial-closure evidence.
        $currentControlledBalance = function_exists('akp_project_controlled_balance')
            ? (float)akp_project_controlled_balance($projectId)
            : $controlledBalance;
        if (!is_finite($currentControlledBalance) || $currentControlledBalance > 0.009) {
            if ($startedHere) { $pdo->rollBack(); }
            return 0;
        }

        // A zero posted controlled balance is not sufficient while any draft
        // funding allocation remains. Recheck inside this processing transaction
        // so callers cannot bypass the FM-page closure guard.
        $draftFundingStmt = $pdo->prepare(
            "SELECT id FROM project_funding_allocations
             WHERE project_id = ? AND status = 'draft'
             LIMIT 1 FOR UPDATE"
        );
        $draftFundingStmt->execute([$projectId]);
        if ($draftFundingStmt->fetchColumn() !== false) {
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return 0;
        }

        $gate = $pdo->prepare(
            "SELECT pl.lifecycle_status, pl.closed_at,
                    (SELECT COUNT(*) FROM audit_log al
                     WHERE al.action = 'FM_FINANCIAL_CLOSURE'
                       AND al.entity_type = 'project_lifecycle'
                       AND al.entity_id = pl.project_id) AS financial_closure_count
             FROM project_lifecycle pl
             WHERE pl.project_id = ? LIMIT 1 FOR UPDATE"
        );
        $gate->execute([$projectId]);
        $closure = $gate->fetch(PDO::FETCH_ASSOC);
        if (!$closure || $closure['lifecycle_status'] !== 'closed'
            || empty($closure['closed_at']) || (int)$closure['financial_closure_count'] < 1) {
            if ($startedHere) { $pdo->rollBack(); }
            return 0;
        }

        $eventStmt = $pdo->prepare(
            "SELECT id, status, verification_note
             FROM hr_incentive_events
             WHERE id = ? AND source_table = 'project_lifecycle'
               AND source_record_id = ? AND incentive_type = 'project_completion'
             LIMIT 1 FOR UPDATE"
        );
        $eventStmt->execute([$eventId, $projectId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
        if (!$event || strpos((string)($event['verification_note'] ?? ''), 'system:project-recipient-snapshot-v1') === false) {
            throw new RuntimeException('لقطة مستفيدي حافز المشروع غير مكتملة؛ لم تصدر أي حوافز.');
        }
        if (!in_array($event['status'], ['pending_verification', 'verified'], true)) {
            throw new RuntimeException('حالة حدث حافز المشروع لا تسمح بالمعالجة الآلية.');
        }

        $allocStmt = $pdo->prepare(
            "SELECT pa.id AS allocation_id, pa.employee_id, pa.recipient_user_id,
                    pa.reward_amount, pa.currency_code,
                    es.category, es.is_active AS employment_state_active,
                    u.is_active AS user_active, e.user_id AS employee_user_id
             FROM hr_incentive_project_allocations pa
             JOIN employees e ON e.id = pa.employee_id
             JOIN hr_employment_states es ON es.id = e.employment_state_id
             JOIN users u ON u.id = pa.recipient_user_id
             WHERE pa.event_id = ?
             ORDER BY pa.employee_id
             FOR UPDATE"
        );
        $allocStmt->execute([$eventId]);
        $allocations = $allocStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$allocations) {
            if ($startedHere) { $pdo->rollBack(); }
            return 0;
        }

        foreach ($allocations as $allocation) {
            if ((int)$allocation['employee_user_id'] !== (int)$allocation['recipient_user_id']
                || $allocation['category'] !== 'working'
                || (int)$allocation['employment_state_active'] !== 1
                || (int)$allocation['user_active'] !== 1
                || (float)$allocation['reward_amount'] <= 0
                || !preg_match('/^[A-Z]{3}$/', strtoupper(trim((string)$allocation['currency_code'])))) {
                throw new RuntimeException('تغيرت أهلية أحد مستفيدي المشروع أو بيانات الحافز؛ أوقفت المعالجة دون حفظ أي حافز.');
            }
        }

        if ($event['status'] === 'pending_verification') {
            $verify = $pdo->prepare(
                "UPDATE hr_incentive_events
                 SET status = 'verified', verified_by_user_id = NULL, verified_at = NOW(),
                     verification_note = CONCAT(COALESCE(verification_note, ''), '\nSystem verified after PM closure and durable FM financial closure.')
                 WHERE id = ? AND status = 'pending_verification'"
            );
            $verify->execute([$eventId]);
            if ($verify->rowCount() !== 1) {
                throw new RuntimeException('تغيرت حالة حدث حافز المشروع أثناء المعالجة.');
            }
        }

        $existingAward = $pdo->prepare(
            "SELECT id FROM hr_incentive_awards WHERE event_id = ? AND employee_id = ? LIMIT 1 FOR UPDATE"
        );
        $insertAward = $pdo->prepare(
            "INSERT INTO hr_incentive_awards
                (event_id, employee_id, recipient_user_id, project_allocation_id,
                 reward_amount, reward_currency_code, status, earned_at)
             VALUES (?, ?, ?, ?, ?, ?, 'earned', ?)"
        );
        $processedAwardIds = [];
        foreach ($allocations as $allocation) {
            $existingAward->execute([$eventId, (int)$allocation['employee_id']]);
            $awardId = (int)($existingAward->fetchColumn() ?: 0);
            if ($awardId <= 0) {
                $insertAward->execute([
                    $eventId,
                    (int)$allocation['employee_id'],
                    (int)$allocation['recipient_user_id'],
                    (int)$allocation['allocation_id'],
                    round((float)$allocation['reward_amount'], 2),
                    strtoupper(trim((string)$allocation['currency_code'])),
                    (string)$closure['closed_at']
                ]);
                $awardId = (int)$pdo->lastInsertId();
            }
            if ($awardId <= 0) {
                throw new RuntimeException('تعذر إنشاء استحقاق حافز المشروع.');
            }
            hrIncentiveRecognizeAward($pdo, $awardId, $actorUserId);
            $processedAwardIds[] = $awardId;
        }

        if ($startedHere) {
            $pdo->commit();
            hrIncentiveDispatchPendingNotifications($pdo);
        }

        // Refresh only the earliest current/future draft payroll per employee.
        // If none exists, normal payroll generation/refresh will schedule the award.
        $draftPayroll = $pdo->prepare(
            "SELECT id FROM payroll
             WHERE employee_id = ? AND status = 'draft'
               AND (year > YEAR(CURDATE()) OR (year = YEAR(CURDATE()) AND month >= MONTH(CURDATE())))
             ORDER BY year, month, id LIMIT 1"
        );
        foreach ($allocations as $allocation) {
            $draftPayroll->execute([(int)$allocation['employee_id']]);
            $payrollId = (int)($draftPayroll->fetchColumn() ?: 0);
            if ($payrollId > 0) {
                try {
                    hrIncentiveRefreshPayrollDraft($pdo, $payrollId);
                } catch (Throwable $refreshError) {
                    error_log('Project incentive payroll refresh deferred for award event ' . $eventId . ': ' . $refreshError->getMessage());
                }
            }
        }

        return count($processedAwardIds);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            if ($startedHere) { $pdo->rollBack(); }
        }
        throw $e;
    }
}
