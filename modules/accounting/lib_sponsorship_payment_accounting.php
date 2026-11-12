<?php
require_once dirname(__DIR__) . '/sponsorships/lib_monthly_agreements.php';
// Sponsorship payment allocation snapshots. Requires the caller's existing DB transaction.
// This file performs no DDL; database/migrations/2026-11-08_sponsorship_payment_accounting.sql owns the schema.

if (!function_exists('ak_sp_parse_payment_period')) {
function ak_sp_parse_payment_period(?string $period): ?string {
    $period = trim((string)$period);
    if ($period === '') return null;
    $date = DateTimeImmutable::createFromFormat('!F/Y', $period);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('F/Y') !== $period) {
        throw new RuntimeException('فترة الكفالة غير صالحة. استخدم الصيغة Month/YYYY مثل September/2026.');
    }
    return $date->format('Y-m-01');
}
}

if (!function_exists('ak_sp_month_is_pause_affected')) {
function ak_sp_month_is_pause_affected(int $sponsorshipId, string $monthStart, string $monthEnd, array $sponsorship, ?array $events = null): bool {
    // Report pages may preload pause history in one query; existing posting callers
    // omit this argument and retain the original per-sponsorship lookup.
    if ($events === null) {
        $events = dbFetchAll(
            "SELECT event_type, effective_date FROM sponsorship_pause_events WHERE sponsorship_id=? ORDER BY effective_date ASC, id ASC",
            [$sponsorshipId]
        );
    }
    if (!$events && (string)$sponsorship['status'] === 'paused') {
        throw new RuntimeException('الكفالة موقوفة دون سجل إيقاف تاريخي موثوق؛ تعذر تخصيص الدفعة بأمان.');
    }

    $openPause = null;
    $affected = false;
    foreach ($events as $event) {
        $eventDate = (string)$event['effective_date'];
        if ($event['event_type'] === 'pause') {
            if ($openPause !== null) throw new RuntimeException('سجل إيقاف الكفالة يحتوي على إيقاف متداخل دون استئناف.');
            $openPause = $eventDate;
        } elseif ($event['event_type'] === 'resume') {
            if ($openPause === null || $eventDate < $openPause) throw new RuntimeException('سجل استئناف الكفالة غير متسق.');
            if ($openPause <= $monthEnd && $eventDate >= $monthStart) $affected = true;
            $openPause = null;
        } else {
            throw new RuntimeException('نوع حدث إيقاف الكفالة غير معروف.');
        }
    }
    if ($openPause !== null) {
        if ((string)$sponsorship['status'] === 'paused') {
            if ($openPause <= $monthEnd) $affected = true;
        } elseif (in_array((string)$sponsorship['status'], ['completed','cancelled'], true) && !empty($sponsorship['end_date'])) {
            $closedDate = (string)$sponsorship['end_date'];
            if ($openPause <= $monthEnd && $closedDate >= $monthStart) $affected = true;
        } else {
            throw new RuntimeException('سجل إيقاف الكفالة لا يتوافق مع حالتها الحالية.');
        }
    } elseif ((string)$sponsorship['status'] === 'paused') {
        throw new RuntimeException('حالة الكفالة موقوفة لكن سجل الإيقاف لا يحتوي على حدث إيقاف مفتوح.');
    } else {
        // Keep legacy pause dates as bounded evidence too. This covers a pause
        // that began before event logging was introduced, including its later resume.
        $legacyStart = $sponsorship['pause_start_date'] ?? null;
        $legacyEnd = $sponsorship['pause_end_date'] ?? null;
        if ($legacyStart !== null || $legacyEnd !== null) {
            if ($legacyStart === null || $legacyEnd === null || (string)$legacyEnd < (string)$legacyStart) {
                throw new RuntimeException('تواريخ الإيقاف القديمة غير مكتملة؛ تعذر تخصيص الدفعة بأمان.');
            }
            if ((string)$legacyStart <= $monthEnd && (string)$legacyEnd >= $monthStart) $affected = true;
        }
    }
    return $affected;
}
}

if (!function_exists('ak_sp_create_payment_allocation_prepared')) {
function ak_sp_create_payment_allocation_prepared(array $transaction, float $netReceived, ?int $createdByUserId = null, ?int $collectorUserId = null): array {
    if (!db()->inTransaction()) {
        throw new LogicException('تخصيص دفعة الكفالة يتطلب معاملة قاعدة بيانات مفتوحة.');
    }
    $transactionId = (int)$transaction['id'];
    if ($transactionId <= 0) throw new InvalidArgumentException('رقم معاملة الكفالة غير صالح.');
    $existing = dbFetchOne("SELECT id FROM sponsorship_payment_allocations WHERE transaction_id=? LIMIT 1 FOR UPDATE", [$transactionId]);
    if ($existing) throw new RuntimeException('يوجد تخصيص سابق للمعاملة؛ تم إيقاف الترحيل لمنع تكرار التخصيص.');

    $period = trim((string)($transaction['payment_period'] ?? ''));
    $coveredMonth = ak_sp_parse_payment_period($period);
    $sponsorshipId = (int)($transaction['sponsorship_id'] ?? 0);
    $commitment = 0.00;
    $allocated = 0.00;

    // Supervisor approval passes sponsor_payments.supervisor_id explicitly because
    // the transaction link/status is written only after the journal is posted.
    // Direct entry falls back to transactions.created_by.
    $collectorId = $collectorUserId !== null && $collectorUserId > 0
        ? $collectorUserId
        : (int)($transaction['created_by'] ?? 0);
    if ($collectorId <= 0) $collectorId = null;

    if ($sponsorshipId > 0 && $coveredMonth !== null && $netReceived > 0) {
        $sponsorship = dbFetchOne(
            "SELECT id, monthly_amount, start_date, end_date, status, sponsorship_code, pause_start_date, pause_end_date FROM sponsorships WHERE id=? FOR UPDATE",
            [$sponsorshipId]
        );
        if (!$sponsorship) throw new RuntimeException('الكفالة المرتبطة بالدفعة غير موجودة.');

        $monthStart = $coveredMonth;
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
        $monitoringStart = str_starts_with((string)$sponsorship['sponsorship_code'], 'IMP-SH-')
            ? '2026-08-01'
            : (string)$sponsorship['start_date'];
        if (in_array((string)$sponsorship['status'], ['completed','cancelled'], true) && empty($sponsorship['end_date'])) {
            throw new RuntimeException('الكفالة المكتملة أو الملغاة لا تحتوي على تاريخ انتهاء موثوق؛ تعذر تخصيص الدفعة.');
        }
        $dateEligible = $monitoringStart <= $monthStart
            && (string)$sponsorship['start_date'] <= $monthStart
            && (empty($sponsorship['end_date']) || (string)$sponsorship['end_date'] >= $monthEnd);
        $pauseAffected = ak_sp_month_is_pause_affected($sponsorshipId, $monthStart, $monthEnd, $sponsorship);

        if ($dateEligible && !$pauseAffected) {
            $snapshots = dbFetchAll(
                "SELECT DISTINCT commitment_amount FROM sponsorship_payment_allocations WHERE sponsorship_id=? AND covered_month=? ORDER BY commitment_amount",
                [$sponsorshipId, $coveredMonth]
            );
            $commitment = ak_spma_resolve_allocation_commitment($transaction, $snapshots, $sponsorship);
            $alreadyAllocated = dbFetchOne(
                "SELECT COALESCE(SUM(a.allocated_amount),0) AS allocated
                 FROM sponsorship_payment_allocations a
                 JOIN transactions t ON t.id=a.transaction_id
                 WHERE a.sponsorship_id=? AND a.covered_month=? AND t.status='posted' AND t.voided_at IS NULL",
                [$sponsorshipId, $coveredMonth]
            );
            $remaining = max(0.00, round($commitment - (float)($alreadyAllocated['allocated'] ?? 0), 2));
            $allocated = min(round($netReceived, 2), $remaining);
        }
    }

    $unallocated = round(max(0.00, $netReceived - $allocated), 2);
    $allocated = round($allocated, 2);
    $status = $allocated > 0 && $unallocated > 0 ? 'partial' : ($allocated > 0 ? 'allocated' : 'unallocated');
    dbExecute(
        "INSERT INTO sponsorship_payment_allocations
         (transaction_id, sponsorship_id, collector_user_id, payment_period, covered_month, commitment_amount, net_received, allocated_amount, unallocated_amount, allocation_status, source_type, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,'live',?)",
        [
            $transactionId,
            $sponsorshipId > 0 ? $sponsorshipId : null,
            $collectorId,
            $period !== '' ? $period : null,
            $coveredMonth,
            $commitment,
            round($netReceived, 2),
            $allocated,
            $unallocated,
            $status,
            $createdByUserId ?? (int)Session::getUserId()
        ]
    );
    return ['allocated_amount'=>$allocated, 'unallocated_amount'=>$unallocated, 'covered_month'=>$coveredMonth];
}
}


if (!function_exists('ak_sp_reverse_accounting_events_for_void_prepared')) {
function ak_sp_reverse_accounting_events_for_void_prepared(int $transactionId, string $entryDate): int {
    if (!db()->inTransaction()) throw new LogicException('عكس آثار محاسبة الكفالة يتطلب معاملة قاعدة بيانات مفتوحة.');
    if ($transactionId <= 0) throw new InvalidArgumentException('رقم معاملة الكفالة غير صالح.');

    $eventKey = 'transaction-void-reversal:' . $transactionId;
    if (dbFetchOne("SELECT id FROM sponsorship_accounting_events WHERE event_key=? LIMIT 1 FOR UPDATE", [$eventKey])) {
        throw new RuntimeException('يوجد عكس محاسبي سابق لأحداث الكفالة لهذه المعاملة.');
    }

    $eventTotals = dbFetchAll(
        "SELECT sae.journal_entry_id, COALESCE(SUM(jl.debit),0) AS total_debit,
                COALESCE(SUM(jl.credit),0) AS total_credit, COUNT(jl.id) AS line_count
         FROM sponsorship_accounting_events sae
         JOIN journal_entries je ON je.id=sae.journal_entry_id
         LEFT JOIN journal_lines jl ON jl.entry_id=je.id
         WHERE sae.transaction_id=?
           AND sae.event_type IN ('historical_reclassification','month_end_recognition')
           AND je.status='posted' AND je.voided_at IS NULL
         GROUP BY sae.id, sae.journal_entry_id
         ORDER BY sae.id",
        [$transactionId]
    );
    if (!$eventTotals) return 0;
    foreach ($eventTotals as $event) {
        if ((int)$event['line_count'] < 2
            || round((float)$event['total_debit'], 2) <= 0
            || round((float)$event['total_debit'], 2) !== round((float)$event['total_credit'], 2)) {
            throw new RuntimeException('أحد قيود أحداث محاسبة الكفالة غير متوازن؛ تم إيقاف إبطال المعاملة.');
        }
    }

    $movements = dbFetchAll(
        "SELECT jl.account_id, COALESCE(SUM(jl.debit),0) AS total_debit,
                COALESCE(SUM(jl.credit),0) AS total_credit
         FROM sponsorship_accounting_events sae
         JOIN journal_entries je ON je.id=sae.journal_entry_id
         JOIN journal_lines jl ON jl.entry_id=je.id
         WHERE sae.transaction_id=?
           AND sae.event_type IN ('historical_reclassification','month_end_recognition')
           AND je.status='posted' AND je.voided_at IS NULL
         GROUP BY jl.account_id
         ORDER BY jl.account_id",
        [$transactionId]
    );

    $lines = [];
    $totalDebit = 0.00;
    $totalCredit = 0.00;
    $netAcrossAccounts = 0.00;
    foreach ($movements as $movement) {
        $net = round((float)$movement['total_debit'] - (float)$movement['total_credit'], 2);
        $netAcrossAccounts += $net;
        if ($net > 0) {
            $lines[] = [(int)$movement['account_id'], 0.00, $net, 'عكس أثر حدث محاسبة الكفالة للمعاملة ' . $transactionId];
            $totalCredit += $net;
        } elseif ($net < 0) {
            $lines[] = [(int)$movement['account_id'], abs($net), 0.00, 'عكس أثر حدث محاسبة الكفالة للمعاملة ' . $transactionId];
            $totalDebit += abs($net);
        }
    }
    if (round($netAcrossAccounts, 2) !== 0.00
        || round($totalDebit, 2) !== round($totalCredit, 2)) {
        throw new RuntimeException('صافي أحداث محاسبة الكفالة غير متوازن؛ تم إيقاف إبطال المعاملة.');
    }
    if (!$lines) return 0;

    $code = 'JE-SP-VOID-TXN-' . $transactionId;
    if (dbFetchOne("SELECT id FROM journal_entries WHERE entry_code=? LIMIT 1 FOR UPDATE", [$code])) {
        throw new RuntimeException('رمز قيد عكس أحداث الكفالة موجود مسبقاً.');
    }
    dbExecute(
        "INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
         VALUES (?,?,?,'sponsorship_accounting_void',?,'posted',?)",
        [$code,$entryDate,'عكس أحداث محاسبة الكفالة بسبب إبطال المعاملة ' . $transactionId,$transactionId,Session::getUserId()]
    );
    $journalId = (int)dbLastInsertId();
    if ($journalId <= 0) throw new RuntimeException('تعذر إنشاء قيد عكس أحداث محاسبة الكفالة.');
    foreach ($lines as $line) {
        dbExecute(
            "INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)",
            [$journalId,$line[0],$line[1],$line[2],$line[3]]
        );
    }
    $check = dbFetchOne(
        "SELECT COALESCE(SUM(debit),0) AS total_debit, COALESCE(SUM(credit),0) AS total_credit, COUNT(*) AS line_count
         FROM journal_lines WHERE entry_id=?",
        [$journalId]
    );
    if ((int)($check['line_count'] ?? 0) !== count($lines)
        || round((float)$check['total_debit'], 2) !== round((float)$check['total_credit'], 2)
        || round((float)$check['total_debit'], 2) <= 0) {
        throw new RuntimeException('قيد عكس أحداث محاسبة الكفالة غير متوازن أو غير مكتمل.');
    }

    $allocation = dbFetchOne(
        "SELECT id FROM sponsorship_payment_allocations WHERE transaction_id=? LIMIT 1 FOR UPDATE",
        [$transactionId]
    );
    dbExecute(
        "INSERT INTO sponsorship_accounting_events
         (event_key,event_type,transaction_id,allocation_id,journal_entry_id,event_date,amount,created_by)
         VALUES (?,'transaction_void_reversal',?,?,?,?,?,?)",
        [$eventKey,$transactionId,$allocation ? (int)$allocation['id'] : null,$journalId,$entryDate,round($totalDebit,2),(int)Session::getUserId()]
    );
    return $journalId;
}
}


if (!function_exists('ak_sp_recognize_month_end_prepared')) {
function ak_sp_recognize_month_end_prepared(string $coveredMonth, ?int $createdByUserId = null): array {
    if (!db()->inTransaction()) throw new LogicException('إثبات إيراد الكفالة يتطلب معاملة قاعدة بيانات مفتوحة.');
    $monthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $coveredMonth);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$monthDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $monthDate->format('Y-m-d') !== $coveredMonth
        || $monthDate->format('d') !== '01') {
        throw new InvalidArgumentException('الشهر المطلوب غير صالح؛ استخدم أول يوم من الشهر بصيغة YYYY-MM-01.');
    }
    $monthEnd = $monthDate->modify('last day of this month')->format('Y-m-d');
    if ($monthEnd > date('Y-m-d')) throw new RuntimeException('لا يمكن إثبات إيراد شهر لم ينتهِ بعد.');

    $allocations = dbFetchAll(
        "SELECT a.id AS allocation_id, a.transaction_id, a.allocated_amount, a.covered_month
         FROM sponsorship_payment_allocations a
         JOIN transactions t ON t.id=a.transaction_id
         WHERE a.covered_month=? AND a.allocated_amount>0
           AND t.status='posted' AND t.voided_at IS NULL
         ORDER BY a.id
         FOR UPDATE",
        [$coveredMonth]
    );
    $recognizedCount = 0;
    $alreadyCount = 0;
    $recognizedAmount = 0.00;
    $alreadyAmount = 0.00;
    $accountDeferred = dbFetchOne("SELECT id FROM accounts WHERE code='2200' AND is_active=1 LIMIT 1");
    $accountRevenue = dbFetchOne("SELECT id FROM accounts WHERE code='4100' AND is_active=1 LIMIT 1");
    if ($allocations && (!$accountDeferred || !$accountRevenue)) {
        throw new RuntimeException('حساب الإيرادات المؤجلة 2200 أو إيرادات الكفالات 4100 غير موجود أو غير نشط.');
    }

    foreach ($allocations as $allocation) {
        $allocationId = (int)$allocation['allocation_id'];
        $transactionId = (int)$allocation['transaction_id'];
        $amount = round((float)$allocation['allocated_amount'], 2);
        if ($allocationId <= 0 || $transactionId <= 0 || $amount <= 0) {
            throw new RuntimeException('بيانات تخصيص الكفالة غير صالحة لإثبات الإيراد.');
        }
        $eventKey = 'month-end-recognition:allocation:' . $allocationId;
        $existing = dbFetchOne(
            "SELECT id,event_type,transaction_id,allocation_id,journal_entry_id,event_date,amount
             FROM sponsorship_accounting_events WHERE event_key=? LIMIT 1 FOR UPDATE",
            [$eventKey]
        );
        if ($existing) {
            if ($existing['event_type'] !== 'month_end_recognition'
                || (int)$existing['transaction_id'] !== $transactionId
                || (int)$existing['allocation_id'] !== $allocationId
                || (string)$existing['event_date'] !== $monthEnd
                || round((float)$existing['amount'], 2) !== $amount) {
                throw new RuntimeException('حدث إثبات سابق لا يطابق تخصيص الكفالة؛ تم إيقاف العملية.');
            }
            $journal = dbFetchOne(
                "SELECT id,status,voided_at,entry_date,reference_type,reference_id FROM journal_entries WHERE id=? LIMIT 1 FOR UPDATE",
                [(int)$existing['journal_entry_id']]
            );
            $lines = dbFetchAll(
                "SELECT a.code,jl.debit,jl.credit FROM journal_lines jl JOIN accounts a ON a.id=jl.account_id WHERE jl.entry_id=? ORDER BY jl.id",
                [(int)$existing['journal_entry_id']]
            );
            if (!$journal || $journal['status'] !== 'posted' || $journal['voided_at'] !== null
                || (string)$journal['entry_date'] !== $monthEnd
                || $journal['reference_type'] !== 'sponsorship_month_end_recognition'
                || (int)$journal['reference_id'] !== $allocationId
                || count($lines) !== 2
                || $lines[0]['code'] === $lines[1]['code']) {
                throw new RuntimeException('قيد إثبات الإيراد السابق غير صالح أو غير مكتمل.');
            }
            $validDeferred = false;
            $validRevenue = false;
            foreach ($lines as $line) {
                if ($line['code'] === '2200'
                    && round((float)$line['debit'],2) === $amount
                    && round((float)$line['credit'],2) === 0.00) $validDeferred = true;
                if ($line['code'] === '4100'
                    && round((float)$line['debit'],2) === 0.00
                    && round((float)$line['credit'],2) === $amount) $validRevenue = true;
            }
            if (!$validDeferred || !$validRevenue) throw new RuntimeException('أسطر قيد إثبات الإيراد السابق لا تطابق الحسابات أو المبلغ المتوقع.');
            $alreadyCount++;
            $alreadyAmount += $amount;
            continue;
        }

        $otherRecognition = dbFetchOne(
            "SELECT id FROM sponsorship_accounting_events WHERE allocation_id=? AND event_type='month_end_recognition' LIMIT 1 FOR UPDATE",
            [$allocationId]
        );
        if ($otherRecognition) throw new RuntimeException('يوجد حدث إثبات آخر مرتبط بتخصيص الكفالة.');
        $voidEvent = dbFetchOne(
            "SELECT id FROM sponsorship_accounting_events WHERE transaction_id=? AND event_type='transaction_void_reversal' LIMIT 1 FOR UPDATE",
            [$transactionId]
        );
        if ($voidEvent) throw new RuntimeException('المعاملة لها حدث إبطال محاسبي؛ لا يمكن إثبات إيرادها.');

        $entryCode = 'JE-SP-RECOG-' . $allocationId;
        if (dbFetchOne("SELECT id FROM journal_entries WHERE entry_code=? LIMIT 1 FOR UPDATE", [$entryCode])) {
            throw new RuntimeException('رمز قيد إثبات الإيراد مستخدم مسبقاً دون حدث مطابق.');
        }
        dbExecute(
            "INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
             VALUES (?,?,?,'sponsorship_month_end_recognition',?,'posted',?)",
            [$entryCode,$monthEnd,'إثبات إيراد الكفالة عن شهر ' . $monthDate->format('m/Y'),$allocationId,$createdByUserId]
        );
        $journalId = (int)dbLastInsertId();
        if ($journalId <= 0) throw new RuntimeException('تعذر إنشاء قيد إثبات إيراد الكفالة.');
        dbExecute(
            "INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,0,?)",
            [$journalId,(int)$accountDeferred['id'],$amount,'إيراد مؤجل مستحق عن شهر ' . $monthDate->format('m/Y')]
        );
        dbExecute(
            "INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,0,?,?)",
            [$journalId,(int)$accountRevenue['id'],$amount,'إيراد كفالة مستحق عن شهر ' . $monthDate->format('m/Y')]
        );
        $check = dbFetchOne(
            "SELECT COALESCE(SUM(debit),0) AS total_debit,COALESCE(SUM(credit),0) AS total_credit,COUNT(*) AS line_count
             FROM journal_lines WHERE entry_id=?",
            [$journalId]
        );
        if ((int)($check['line_count'] ?? 0) !== 2
            || round((float)$check['total_debit'],2) !== $amount
            || round((float)$check['total_credit'],2) !== $amount) {
            throw new RuntimeException('قيد إثبات إيراد الكفالة غير متوازن أو غير مكتمل.');
        }
        dbExecute(
            "INSERT INTO sponsorship_accounting_events
             (event_key,event_type,transaction_id,allocation_id,journal_entry_id,event_date,amount,created_by)
             VALUES (?,'month_end_recognition',?,?,?,?,?,?)",
            [$eventKey,$transactionId,$allocationId,$journalId,$monthEnd,$amount,$createdByUserId]
        );
        $recognizedCount++;
        $recognizedAmount += $amount;
    }
    return [
        'recognized_count'=>$recognizedCount,
        'already_recognized_count'=>$alreadyCount,
        'recognized_amount'=>round($recognizedAmount,2),
        'already_recognized_amount'=>round($alreadyAmount,2),
        'month_end'=>$monthEnd
    ];
}
}


if (!function_exists('ak_sp_backfill_historical_payments_prepared')) {
function ak_sp_backfill_historical_payments_prepared(bool $apply = false, ?int $createdByUserId = null): array {
    if (!db()->inTransaction()) throw new LogicException('المعالجة التاريخية للكفالات تتطلب معاملة قاعدة بيانات مفتوحة.');
    $manifest = [
        ['id'=>1,'code'=>'SP-000001','sponsorship_id'=>1429,'period'=>'July/2027','gross'=>11000.00,'commitment'=>11000.00,'allocated'=>11000.00,'unallocated'=>0.00,'journal'=>'JE-000001'],
        ['id'=>2,'code'=>'SP-000002','sponsorship_id'=>1429,'period'=>'August/2026','gross'=>11000.00,'commitment'=>11000.00,'allocated'=>11000.00,'unallocated'=>0.00,'journal'=>'JE-000002'],
        ['id'=>17,'code'=>'SP-000004','sponsorship_id'=>2947,'period'=>'September/2026','gross'=>15000.00,'commitment'=>10000.00,'allocated'=>10000.00,'unallocated'=>5000.00,'journal'=>'JE-000015'],
        ['id'=>18,'code'=>'SP-000005','sponsorship_id'=>2935,'period'=>'September/2026','gross'=>15000.00,'commitment'=>10000.00,'allocated'=>10000.00,'unallocated'=>5000.00,'journal'=>'JE-000016'],
        ['id'=>27,'code'=>'SP-000006','sponsorship_id'=>328,'period'=>'September/2026','gross'=>15000.00,'commitment'=>15000.00,'allocated'=>15000.00,'unallocated'=>0.00,'journal'=>'JE-000028'],
        ['id'=>28,'code'=>'SP-000010','sponsorship_id'=>3538,'period'=>'September/2026','gross'=>10000.00,'commitment'=>10000.00,'allocated'=>10000.00,'unallocated'=>0.00,'journal'=>'JE-000029'],
        ['id'=>29,'code'=>'SP-000011','sponsorship_id'=>3534,'period'=>'September/2026','gross'=>10000.00,'commitment'=>10000.00,'allocated'=>10000.00,'unallocated'=>0.00,'journal'=>'JE-000030'],
    ];
    $cashCodes = ['cash'=>'1100','bank_transfer'=>'1200','credit_card'=>'1200','mobile'=>'1300','other'=>'1100'];
    $expectedGross = 87000.00;
    $expectedAllocated = 77000.00;
    $expectedExcess = 10000.00;
    $expectedEarned = 66000.00;
    $expectedDeferred = 21000.00;
    $existingAllocationCount = 0;
    $existingReclassCount = 0;
    $plans = [];

    foreach ($manifest as $item) {
        $transactionId = (int)$item['id'];
        $transaction = dbFetchOne("SELECT * FROM transactions WHERE id=? FOR UPDATE", [$transactionId]);
        if (!$transaction
            || (string)$transaction['transaction_code'] !== $item['code']
            || (string)$transaction['transaction_type'] !== 'sponsorship_payment'
            || (string)$transaction['purpose'] !== 'monthly_sponsorship'
            || (int)$transaction['sponsorship_id'] !== (int)$item['sponsorship_id']
            || (string)$transaction['payment_period'] !== $item['period']
            || $transaction['status'] !== 'posted'
            || $transaction['voided_at'] !== null
            || round((float)$transaction['amount'],2) !== (float)$item['gross']
            || round((float)$transaction['net_amount'],2) !== (float)$item['gross']
            || round((float)$transaction['admin_fee_amount'],2) !== 0.00
            || round((float)$transaction['admin_fee_percent'],2) !== 0.00
            || (string)$transaction['currency_code'] !== 'SDG') {
            throw new RuntimeException('المعاملة التاريخية ' . $transactionId . ' لا تطابق بيانات النسخة الاحتياطية المعتمدة؛ تم إيقاف المعالجة.');
        }

        $submissionRows = dbFetchAll(
            "SELECT id,payment_type,sponsorship_id,payment_period,amount,status,transaction_id,supervisor_id
             FROM sponsor_payments WHERE transaction_id=? ORDER BY id LIMIT 2 FOR UPDATE",
            [$transactionId]
        );
        if (count($submissionRows) !== 1) throw new RuntimeException('لا يوجد طلب تحصيل مشرف وحيد مرتبط بالمعاملة ' . $transactionId . '.');
        $submission = $submissionRows[0];
        if ($submission['status'] !== 'approved'
            || $submission['payment_type'] !== 'monthly_sponsorship'
            || (int)$submission['transaction_id'] !== $transactionId
            || (int)$submission['sponsorship_id'] !== (int)$item['sponsorship_id']
            || (string)$submission['payment_period'] !== $item['period']
            || round((float)$submission['amount'],2) !== (float)$item['gross']
            || (int)$submission['supervisor_id'] <= 0) {
            throw new RuntimeException('بيانات جامع التبرع أو طلب التحصيل لا تطابق المعاملة ' . $transactionId . '.');
        }

        $sponsorship = dbFetchOne(
            "SELECT id,monthly_amount,start_date,end_date,status,sponsorship_code,pause_start_date,pause_end_date
             FROM sponsorships WHERE id=? FOR UPDATE",
            [(int)$item['sponsorship_id']]
        );
        if (!$sponsorship || round((float)$sponsorship['monthly_amount'],2) !== (float)$item['commitment']
            || $sponsorship['status'] !== 'active') {
            throw new RuntimeException('بيانات الكفالة أو الالتزام الشهري لا تطابق سجل المعالجة التاريخية للمعاملة ' . $transactionId . '.');
        }
        $coveredMonth = ak_sp_parse_payment_period((string)$item['period']);
        if ($coveredMonth === null) throw new RuntimeException('فترة المعاملة التاريخية فارغة.');
        $monthEnd = (new DateTimeImmutable($coveredMonth))->modify('last day of this month')->format('Y-m-d');
        $monitoringStart = str_starts_with((string)$sponsorship['sponsorship_code'], 'IMP-SH-')
            ? '2026-08-01'
            : (string)$sponsorship['start_date'];
        if ($monitoringStart > $coveredMonth
            || (string)$sponsorship['start_date'] > $coveredMonth
            || (!empty($sponsorship['end_date']) && (string)$sponsorship['end_date'] < $monthEnd)
            || ak_sp_month_is_pause_affected((int)$item['sponsorship_id'],$coveredMonth,$monthEnd,$sponsorship)) {
            throw new RuntimeException('الشهر التاريخي غير مؤهل للكفالة ' . $item['sponsorship_id'] . '.');
        }

        $original = dbFetchOne(
            "SELECT id,entry_code,entry_date,status,voided_at
             FROM journal_entries
             WHERE reference_type='transaction' AND reference_id=?
             ORDER BY id ASC LIMIT 1 FOR UPDATE",
            [$transactionId]
        );
        if (!$original || $original['entry_code'] !== $item['journal']
            || $original['status'] !== 'posted' || $original['voided_at'] !== null
            || (string)$original['entry_date'] !== (string)$transaction['transaction_date']) {
            throw new RuntimeException('القيد الأصلي للمعاملة ' . $transactionId . ' غير مطابق أو غير مرحّل.');
        }
        $cashCode = $cashCodes[(string)$transaction['payment_method']] ?? null;
        if ($cashCode === null) throw new RuntimeException('طريقة التحصيل غير معروفة للمعاملة ' . $transactionId . '.');
        $originalLines = dbFetchAll(
            "SELECT a.code,jl.debit,jl.credit FROM journal_lines jl JOIN accounts a ON a.id=jl.account_id WHERE jl.entry_id=? ORDER BY jl.id",
            [(int)$original['id']]
        );
        $cashLineOk = false;
        $revenueLineOk = false;
        if (count($originalLines) !== 2) throw new RuntimeException('عدد أسطر القيد الأصلي غير مطابق للمعاملة ' . $transactionId . '.');
        foreach ($originalLines as $line) {
            if ($line['code'] === $cashCode && round((float)$line['debit'],2) === (float)$item['gross'] && round((float)$line['credit'],2) === 0.00) $cashLineOk = true;
            if ($line['code'] === '4100' && round((float)$line['debit'],2) === 0.00 && round((float)$line['credit'],2) === (float)$item['gross']) $revenueLineOk = true;
        }
        if (!$cashLineOk || !$revenueLineOk) throw new RuntimeException('أسطر القيد الأصلي لا تطابق إيصال الكفالة ' . $transactionId . '.');

        $allocation = dbFetchOne("SELECT * FROM sponsorship_payment_allocations WHERE transaction_id=? LIMIT 1 FOR UPDATE",[$transactionId]);
        $eventKey = 'historical-reclassification:transaction:' . $transactionId;
        $event = dbFetchOne("SELECT * FROM sponsorship_accounting_events WHERE event_key=? LIMIT 1 FOR UPDATE",[$eventKey]);
        $reclassCode = 'JE-SP-HIST-RECLASS-' . $transactionId;
        $codeCollision = dbFetchOne("SELECT id FROM journal_entries WHERE entry_code=? LIMIT 1 FOR UPDATE",[$reclassCode]);
        if ($allocation) {
            $expectedStatus = $item['allocated'] > 0 && $item['unallocated'] > 0 ? 'partial' : ($item['allocated'] > 0 ? 'allocated' : 'unallocated');
            if ((int)$allocation['sponsorship_id'] !== (int)$item['sponsorship_id']
                || (int)$allocation['collector_user_id'] !== (int)$submission['supervisor_id']
                || (string)$allocation['payment_period'] !== $item['period']
                || (string)$allocation['covered_month'] !== $coveredMonth
                || round((float)$allocation['commitment_amount'],2) !== (float)$item['commitment']
                || round((float)$allocation['net_received'],2) !== (float)$item['gross']
                || round((float)$allocation['allocated_amount'],2) !== (float)$item['allocated']
                || round((float)$allocation['unallocated_amount'],2) !== (float)$item['unallocated']
                || $allocation['allocation_status'] !== $expectedStatus
                || $allocation['source_type'] !== 'historical_backfill') {
                throw new RuntimeException('لقطة التخصيص الموجودة لا تطابق المعاملة التاريخية ' . $transactionId . '.');
            }
        }
        if ($event) {
            if (!$allocation || $event['event_type'] !== 'historical_reclassification'
                || (int)$event['transaction_id'] !== $transactionId
                || (int)$event['allocation_id'] !== (int)$allocation['id']
                || (string)$event['event_date'] !== (string)$original['entry_date']
                || round((float)$event['amount'],2) !== (float)$item['gross']) {
                throw new RuntimeException('حدث إعادة التصنيف الموجود لا يطابق المعاملة التاريخية ' . $transactionId . '.');
            }
            $eventJournal = dbFetchOne("SELECT id,entry_code,entry_date,status,voided_at,reference_type,reference_id FROM journal_entries WHERE id=? LIMIT 1 FOR UPDATE",[(int)$event['journal_entry_id']]);
            $eventLines = dbFetchAll("SELECT a.code,jl.debit,jl.credit FROM journal_lines jl JOIN accounts a ON a.id=jl.account_id WHERE jl.entry_id=? ORDER BY jl.id",[(int)$event['journal_entry_id']]);
            $debitOk = false;
            $creditOk = false;
            foreach ($eventLines as $line) {
                if ($line['code']==='4100' && round((float)$line['debit'],2)===(float)$item['gross'] && round((float)$line['credit'],2)===0.00) $debitOk=true;
                if ($line['code']==='2200' && round((float)$line['debit'],2)===0.00 && round((float)$line['credit'],2)===(float)$item['gross']) $creditOk=true;
            }
            if (!$eventJournal || $eventJournal['entry_code'] !== $reclassCode
                || (string)$eventJournal['entry_date'] !== (string)$original['entry_date']
                || $eventJournal['status'] !== 'posted' || $eventJournal['voided_at'] !== null
                || $eventJournal['reference_type'] !== 'sponsorship_historical_reclassification'
                || (int)$eventJournal['reference_id'] !== $transactionId
                || count($eventLines) !== 2 || !$debitOk || !$creditOk) {
                throw new RuntimeException('قيد إعادة التصنيف الموجود غير مطابق للمعاملة التاريخية ' . $transactionId . '.');
            }
        } elseif ($allocation || $codeCollision) {
            throw new RuntimeException('حالة جزئية أو تعارض سابق في إعادة معالجة المعاملة ' . $transactionId . '.');
        }
        if ($allocation) $existingAllocationCount++;
        if ($event) $existingReclassCount++;

        $plans[] = [
            'item'=>$item,
            'transaction'=>$transaction,
            'submission'=>$submission,
            'sponsorship'=>$sponsorship,
            'original'=>$original,
            'covered_month'=>$coveredMonth,
            'allocation'=>$allocation,
            'event'=>$event
        ];
    }

    $alreadyApplied = $existingAllocationCount === count($manifest) && $existingReclassCount === count($manifest);
    if (($existingAllocationCount !== 0 || $existingReclassCount !== 0) && !$alreadyApplied) {
        throw new RuntimeException('تم العثور على معالجة تاريخية جزئية؛ لن يتم استكمالها تلقائياً.');
    }
    if (!$alreadyApplied && ($existingAllocationCount !== 0 || $existingReclassCount !== 0)) {
        throw new RuntimeException('حالة المعالجة التاريخية غير متسقة.');
    }

    if ($apply && !$alreadyApplied) {
        $accountRevenue = dbFetchOne("SELECT id FROM accounts WHERE code='4100' AND is_active=1 LIMIT 1");
        $accountDeferred = dbFetchOne("SELECT id FROM accounts WHERE code='2200' AND is_active=1 LIMIT 1");
        if (!$accountRevenue || !$accountDeferred) throw new RuntimeException('حسابا 4100 و2200 مطلوبان للمعالجة التاريخية.');

        foreach ($plans as $plan) {
            $item = $plan['item'];
            $transactionId = (int)$item['id'];
            $submission = $plan['submission'];
            $original = $plan['original'];
            $coveredMonth = $plan['covered_month'];
            $allocationStatus = $item['allocated'] > 0 && $item['unallocated'] > 0 ? 'partial' : ($item['allocated'] > 0 ? 'allocated' : 'unallocated');
            dbExecute(
                "INSERT INTO sponsorship_payment_allocations
                 (transaction_id,sponsorship_id,collector_user_id,payment_period,covered_month,commitment_amount,net_received,allocated_amount,unallocated_amount,allocation_status,source_type,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,'historical_backfill',?)",
                [
                    $transactionId,(int)$item['sponsorship_id'],(int)$submission['supervisor_id'],
                    $item['period'],$coveredMonth,(float)$item['commitment'],(float)$item['gross'],
                    (float)$item['allocated'],(float)$item['unallocated'],$allocationStatus,$createdByUserId
                ]
            );
            $allocationId = (int)dbLastInsertId();
            if ($allocationId <= 0) throw new RuntimeException('تعذر إنشاء لقطة التخصيص التاريخية للمعاملة ' . $transactionId . '.');

            $entryCode = 'JE-SP-HIST-RECLASS-' . $transactionId;
            dbExecute(
                "INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
                 VALUES (?,?,?,'sponsorship_historical_reclassification',?,'posted',?)",
                [$entryCode,$original['entry_date'],'إعادة تصنيف إيراد كفالة تاريخي إلى إيراد مؤجل — ' . $item['code'],$transactionId,$createdByUserId]
            );
            $journalId = (int)dbLastInsertId();
            if ($journalId <= 0) throw new RuntimeException('تعذر إنشاء قيد إعادة التصنيف التاريخي للمعاملة ' . $transactionId . '.');
            dbExecute(
                "INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,0,?)",
                [$journalId,(int)$accountRevenue['id'],(float)$item['gross'],'إعادة تصنيف إيراد كفالة تاريخي ' . $item['code']]
            );
            dbExecute(
                "INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,0,?,?)",
                [$journalId,(int)$accountDeferred['id'],(float)$item['gross'],'إيراد كفالة مؤجل ' . $item['code']]
            );
            $check = dbFetchOne("SELECT COALESCE(SUM(debit),0) debit,COALESCE(SUM(credit),0) credit,COUNT(*) line_count FROM journal_lines WHERE entry_id=?",[$journalId]);
            if ((int)$check['line_count'] !== 2 || round((float)$check['debit'],2) !== (float)$item['gross'] || round((float)$check['credit'],2) !== (float)$item['gross']) {
                throw new RuntimeException('قيد إعادة التصنيف التاريخي غير متوازن للمعاملة ' . $transactionId . '.');
            }
            dbExecute(
                "INSERT INTO sponsorship_accounting_events
                 (event_key,event_type,transaction_id,allocation_id,journal_entry_id,event_date,amount,created_by)
                 VALUES (?,'historical_reclassification',?,?,?,?,?,?)",
                ['historical-reclassification:transaction:' . $transactionId,$transactionId,$allocationId,$journalId,$original['entry_date'],(float)$item['gross'],$createdByUserId]
            );
        }
    }

    $recognitionAugust = ['recognized_count'=>0,'already_recognized_count'=>0,'recognized_amount'=>0.00,'already_recognized_amount'=>0.00];
    $recognitionSeptember = $recognitionAugust;
    if ($apply) {
        if ((new DateTimeImmutable('2026-08-31'))->format('Y-m-d') <= date('Y-m-d')) {
            $recognitionAugust = ak_sp_recognize_month_end_prepared('2026-08-01',$createdByUserId);
        }
        if ((new DateTimeImmutable('2026-09-30'))->format('Y-m-d') <= date('Y-m-d')) {
            $recognitionSeptember = ak_sp_recognize_month_end_prepared('2026-09-01',$createdByUserId);
        }
        $ids = array_map(static fn($item) => (int)$item['id'], $manifest);
        $marks = implode(',',array_fill(0,count($ids),'?'));
        $eventTotals = dbFetchAll(
            "SELECT event_type,COUNT(*) AS event_count,COALESCE(SUM(amount),0) AS event_amount
             FROM sponsorship_accounting_events WHERE transaction_id IN ($marks)
             GROUP BY event_type",
            $ids
        );
        $totalsByType = [];
        foreach ($eventTotals as $row) $totalsByType[$row['event_type']] = ['count'=>(int)$row['event_count'],'amount'=>round((float)$row['event_amount'],2)];
        if (($totalsByType['historical_reclassification']['count'] ?? 0) !== 7
            || ($totalsByType['historical_reclassification']['amount'] ?? 0.00) !== $expectedGross
            || ($totalsByType['month_end_recognition']['count'] ?? 0) !== 6
            || ($totalsByType['month_end_recognition']['amount'] ?? 0.00) !== $expectedEarned) {
            throw new RuntimeException('إجمالي أحداث المعالجة التاريخية أو الاعتراف الشهري لا يطابق التسوية المعتمدة.');
        }

        $journalIds = [];
        foreach ($ids as $id) {
            $original = dbFetchOne("SELECT id FROM journal_entries WHERE reference_type='transaction' AND reference_id=? ORDER BY id LIMIT 1",[$id]);
            if (!$original) throw new RuntimeException('القيد الأصلي مفقود أثناء التسوية النهائية.');
            $journalIds[] = (int)$original['id'];
            $eventJournals = dbFetchAll("SELECT journal_entry_id FROM sponsorship_accounting_events WHERE transaction_id=?",[$id]);
            foreach ($eventJournals as $eventJournal) $journalIds[] = (int)$eventJournal['journal_entry_id'];
        }
        $journalIds = array_values(array_unique($journalIds));
        $journalMarks = implode(',',array_fill(0,count($journalIds),'?'));
        $ledger = dbFetchAll(
            "SELECT a.code,ROUND(SUM(jl.debit),2) AS debit,ROUND(SUM(jl.credit),2) AS credit
             FROM journal_lines jl JOIN accounts a ON a.id=jl.account_id
             WHERE jl.entry_id IN ($journalMarks) GROUP BY a.code",
            $journalIds
        );
        $ledgerByCode = [];
        foreach ($ledger as $line) $ledgerByCode[$line['code']] = ['debit'=>(float)$line['debit'],'credit'=>(float)$line['credit']];
        if (!isset($ledgerByCode['4100']) || round($ledgerByCode['4100']['credit']-$ledgerByCode['4100']['debit'],2) !== $expectedEarned
            || !isset($ledgerByCode['2200']) || round($ledgerByCode['2200']['credit']-$ledgerByCode['2200']['debit'],2) !== $expectedDeferred) {
            throw new RuntimeException('الرصيد الصافي للحسابين 4100 و2200 لا يطابق 66,000 و21,000 SDG.');
        }
    }

    return [
        'state'=>$apply ? ($alreadyApplied ? 'already_applied' : 'applied') : ($alreadyApplied ? 'already_applied' : 'ready'),
        'transaction_count'=>count($manifest),
        'gross_total'=>$expectedGross,
        'allocated_total'=>$expectedAllocated,
        'unallocated_total'=>$expectedExcess,
        'earned_revenue_target'=>$expectedEarned,
        'deferred_revenue_target'=>$expectedDeferred,
        'recognition_august'=>$recognitionAugust,
        'recognition_september'=>$recognitionSeptember,
    ];
}
}
