<?php
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
function ak_sp_month_is_pause_affected(int $sponsorshipId, string $monthStart, string $monthEnd, array $sponsorship): bool {
    $events = dbFetchAll(
        "SELECT event_type, effective_date FROM sponsorship_pause_events WHERE sponsorship_id=? ORDER BY effective_date ASC, id ASC",
        [$sponsorshipId]
    );
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
        $dateEligible = $monitoringStart <= $monthStart
            && (string)$sponsorship['start_date'] <= $monthStart
            && (empty($sponsorship['end_date']) || (string)$sponsorship['end_date'] >= $monthEnd);
        $pauseAffected = ak_sp_month_is_pause_affected($sponsorshipId, $monthStart, $monthEnd, $sponsorship);

        if ($dateEligible && !$pauseAffected) {
            $snapshots = dbFetchAll(
                "SELECT DISTINCT commitment_amount FROM sponsorship_payment_allocations WHERE sponsorship_id=? AND covered_month=? ORDER BY commitment_amount",
                [$sponsorshipId, $coveredMonth]
            );
            if (count($snapshots) > 1) throw new RuntimeException('توجد لالتزامات الشهر قيم متعارضة في تخصيصات سابقة.');
            $commitment = $snapshots ? round((float)$snapshots[0]['commitment_amount'], 2) : round((float)$sponsorship['monthly_amount'], 2);
            if ($commitment <= 0) throw new RuntimeException('قيمة الالتزام الشهري للكفالة غير صالحة.');
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
