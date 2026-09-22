<?php
// modules/accounting/lib_transaction_void.php - Atomic transaction void/reversal helper

if (!function_exists('ak_void_transaction_journal_atomic')) {
function ak_void_transaction_journal_atomic(int $txnId, string $reason): void {
    // This helper intentionally does not call ak_ensure_tables(). MySQL DDL can
    // implicitly commit an active transaction, which would break atomic voiding.
    $original = dbFetchOne(
        "SELECT id, entry_code, entry_date, description, status
         FROM journal_entries
         WHERE reference_type='transaction' AND reference_id=?
         ORDER BY id ASC LIMIT 1 FOR UPDATE",
        [$txnId]
    );

    if (!$original) {
        throw new RuntimeException('لا يوجد قيد محاسبي مرحّل للمعاملة ' . $txnId);
    }
    if ($original['status'] !== 'posted') {
        throw new RuntimeException('القيد المحاسبي للمعاملة ' . $txnId . ' ليس في حالة مرحّلة.');
    }

    $existingReversal = dbFetchOne(
        "SELECT id FROM journal_entries
         WHERE reference_type='transaction_void' AND reference_id=?
         LIMIT 1 FOR UPDATE",
        [$txnId]
    );
    if ($existingReversal) {
        throw new RuntimeException('يوجد قيد إلغاء محاسبي سابق للمعاملة ' . $txnId . '.');
    }

    $lines = dbFetchAll(
        "SELECT account_id, debit, credit, description
         FROM journal_lines WHERE entry_id=? ORDER BY id",
        [(int)$original['id']]
    );
    if (!$lines) {
        throw new RuntimeException('القيد المحاسبي للمعاملة ' . $txnId . ' لا يحتوي على أسطر.');
    }

    $totalDebit = 0.0;
    $totalCredit = 0.0;
    foreach ($lines as $line) {
        $debit = round((float)$line['debit'], 2);
        $credit = round((float)$line['credit'], 2);
        if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
            throw new RuntimeException('سطر القيد الأصلي غير صالح للمعاملة ' . $txnId);
        }
        $totalDebit += $debit;
        $totalCredit += $credit;
    }

    if (round($totalDebit, 2) !== round($totalCredit, 2) || round($totalDebit, 2) <= 0) {
        throw new RuntimeException('القيد الأصلي للمعاملة ' . $txnId . ' غير متوازن أو صفري.');
    }

    // The original entry stays 'posted' forever: every balance/report query filters on
    // status='posted', so flipping it to 'voided' here would silently drop it from every
    // balance while the reversal below still counts, over-correcting by double the amount.
    // Only the audit metadata (voided_at/voided_by/void_reason) changes; $existingReversal
    // above is the real guard against voiding the same entry twice.
    $affected = dbExecute(
        "UPDATE journal_entries
         SET voided_at=NOW(), voided_by=?, void_reason=?
         WHERE id=? AND status='posted' AND voided_at IS NULL",
        [Session::getUserId(), $reason, (int)$original['id']]
    );
    if ($affected !== 1) {
        throw new RuntimeException('تعذر تسجيل بيانات إبطال القيد الأصلي للمعاملة ' . $txnId);
    }

    $code = 'JE-VOID-TXN-' . $txnId;
    $codeExists = dbFetchOne("SELECT id FROM journal_entries WHERE entry_code=? LIMIT 1 FOR UPDATE", [$code]);
    if ($codeExists) {
        throw new RuntimeException('رمز قيد الإلغاء موجود مسبقاً للمعاملة ' . $txnId . '.');
    }

    dbExecute(
        "INSERT INTO journal_entries
         (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
         VALUES (?,?,?,?,?,'posted',?)",
        [
            $code,
            $original['entry_date'],
            'عكس القيد بسبب إبطال المعاملة ' . $txnId,
            'transaction_void',
            $txnId,
            Session::getUserId()
        ]
    );

    // Capture the generated journal ID directly from the same PDO connection.
    // Do this immediately after the INSERT so no intervening SELECT can affect
    // the connection's last-insert-id state.
    $reversalId = (int)dbLastInsertId();
    if ($reversalId <= 0) {
        throw new RuntimeException('تعذر الحصول على رقم قيد الإلغاء للمعاملة ' . $txnId);
    }

    // Insert the complete reversal in one INSERT ... SELECT. This deliberately
    // reuses the original journal_lines rows, so every account_id is already
    // proven valid by the original FK relationship. Debit and credit are swapped.
    $lineCount = dbExecute(
        "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
         SELECT ?, account_id, credit, debit, CONCAT('عكس: ', COALESCE(description, ''))
         FROM journal_lines
         WHERE entry_id=?
         ORDER BY id",
        [$reversalId, (int)$original['id']]
    );

    if ($lineCount !== count($lines)) {
        throw new RuntimeException('تعذر إنشاء جميع أسطر قيد الإلغاء للمعاملة ' . $txnId);
    }

    // Final balance check against the rows actually inserted.
    $reversalTotals = dbFetchOne(
        "SELECT COALESCE(SUM(debit),0) AS total_debit,
                COALESCE(SUM(credit),0) AS total_credit,
                COUNT(*) AS line_count
         FROM journal_lines
         WHERE entry_id=?",
        [$reversalId]
    );

    if ((int)($reversalTotals['line_count'] ?? 0) !== count($lines)
        || round((float)$reversalTotals['total_debit'], 2) !== round((float)$reversalTotals['total_credit'], 2)
        || round((float)$reversalTotals['total_debit'], 2) <= 0) {
        throw new RuntimeException('قيد الإلغاء للمعاملة ' . $txnId . ' غير متوازن أو غير مكتمل.');
    }
}
}
