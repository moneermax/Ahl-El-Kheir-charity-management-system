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

    // Mark the original entry voided, then create a separate balanced reversal.
    // The caller owns the surrounding DB transaction, so any failure rolls both back.
    $affected = dbExecute(
        "UPDATE journal_entries
         SET status='voided', voided_at=NOW(), voided_by=?, void_reason=?
         WHERE id=? AND status='posted'",
        [Session::getUserId(), $reason, (int)$original['id']]
    );
    if ($affected !== 1) {
        throw new RuntimeException('تعذر إبطال القيد الأصلي للمعاملة ' . $txnId);
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

    // Use the same LAST_INSERT_ID() retrieval pattern already used by the
    // accounting posting engine. This is reliable across the project's MariaDB setup.
    $reversalIdRow = dbFetchOne("SELECT LAST_INSERT_ID() AS id");
    $reversalId = (int)($reversalIdRow['id'] ?? 0);
    if ($reversalId <= 0) {
        throw new RuntimeException('تعذر إنشاء قيد الإلغاء للمعاملة ' . $txnId);
    }

    $reversalDebit = 0.0;
    $reversalCredit = 0.0;
    $insertedLines = 0;
    foreach ($lines as $line) {
        $debit = round((float)$line['credit'], 2);
        $credit = round((float)$line['debit'], 2);
        $reversalDebit += $debit;
        $reversalCredit += $credit;
        $lineAffected = dbExecute(
            "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
             VALUES (?,?,?,?,?)",
            [
                $reversalId,
                (int)$line['account_id'],
                $debit,
                $credit,
                'عكس: ' . ($line['description'] ?? '')
            ]
        );
        if ($lineAffected !== 1) {
            throw new RuntimeException('تعذر إنشاء أحد أسطر قيد الإلغاء للمعاملة ' . $txnId);
        }
        $insertedLines++;
    }

    if ($insertedLines !== count($lines)) {
        throw new RuntimeException('عدد أسطر قيد الإلغاء غير مكتمل للمعاملة ' . $txnId);
    }
    if (round($reversalDebit, 2) !== round($reversalCredit, 2) || round($reversalDebit, 2) <= 0) {
        throw new RuntimeException('قيد الإلغاء للمعاملة ' . $txnId . ' غير متوازن.');
    }
}
}
