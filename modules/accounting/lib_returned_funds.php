<?php
/**
 * Returned disbursement funds lifecycle.
 *
 * Keeps the original return event immutable and tracks any later re-disbursement
 * as a separate financial event.
 */
if (!defined('APP_URL')) {
    exit('Direct access not permitted');
}

function returned_funds_ensure_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    dbExecute("CREATE TABLE IF NOT EXISTS returned_disbursement_reissues (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        disbursement_item_id INT UNSIGNED NOT NULL,
        disbursement_id INT UNSIGNED NOT NULL,
        family_id INT UNSIGNED NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        original_month VARCHAR(7) NOT NULL,
        original_return_journal_id INT UNSIGNED NULL,
        original_return_reason VARCHAR(500) NULL,
        original_returned_at DATETIME NULL,
        original_return_receipt_path VARCHAR(255) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'awaiting_redelivery',
        authorized_by INT UNSIGNED NULL,
        authorized_at DATETIME NULL,
        authorization_reason VARCHAR(500) NULL,
        redelivered_by INT UNSIGNED NULL,
        redelivered_at DATETIME NULL,
        redelivery_receipt_path VARCHAR(255) NULL,
        redelivery_transaction_id INT UNSIGNED NULL,
        redelivery_journal_id INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_returned_reissue_item (disbursement_item_id),
        KEY idx_returned_reissue_status (status),
        KEY idx_returned_reissue_family (family_id),
        KEY idx_returned_reissue_disbursement (disbursement_id),
        CONSTRAINT fk_returned_reissue_item FOREIGN KEY (disbursement_item_id) REFERENCES disbursement_items(id) ON DELETE RESTRICT,
        CONSTRAINT fk_returned_reissue_batch FOREIGN KEY (disbursement_id) REFERENCES monthly_disbursements(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function returned_funds_get_item(int $itemId): ?array
{
    returned_funds_ensure_table();
    return dbFetchOne("SELECT
        i.id AS item_id, i.disbursement_id, i.family_id, i.amount, i.status AS item_status,
        i.return_reason, i.returned_at, i.returned_by_user_id, i.reversal_journal_id,
        i.receipt_file_path AS item_receipt_path,
        d.month, d.nanny_id, d.status AS disbursement_status, d.group_id,
        d.return_receipt_file_path AS batch_return_receipt_path,
        f.family_code, f.mother_name
        FROM disbursement_items i
        INNER JOIN monthly_disbursements d ON d.id = i.disbursement_id
        INNER JOIN families f ON f.id = i.family_id
        WHERE i.id = ? LIMIT 1", [$itemId]);
}

function returned_funds_accountant_can_manage(int $userId, string $role, int $nannyId): bool
{
    if (in_array($role, ['admin', 'financial_manager'], true)) {
        return true;
    }
    if ($role !== 'accountant_staff') {
        return false;
    }
    return (bool) dbFetchOne(
        "SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ? LIMIT 1",
        [$userId, $nannyId]
    );
}

function returned_funds_authorize(int $itemId, int $userId, string $role, string $reason): array
{
    returned_funds_ensure_table();
    $reason = trim($reason);
    if ($reason === '') {
        return ['success' => false, 'message' => 'يرجى كتابة سبب إعادة الفتح لإعادة الصرف.'];
    }

    try {
        dbExecute('START TRANSACTION');
        $item = returned_funds_get_item($itemId);
        if (!$item || $item['item_status'] !== 'returned') {
            throw new RuntimeException('السجل غير موجود أو لم يعد في حالة مُعاد للصندوق.');
        }
        if (!returned_funds_accountant_can_manage($userId, $role, (int)$item['nanny_id'])) {
            throw new RuntimeException('هذا السجل ليس ضمن صلاحياتك.');
        }

        $existing = dbFetchOne("SELECT * FROM returned_disbursement_reissues WHERE disbursement_item_id = ? FOR UPDATE", [$itemId]);
        if ($existing && $existing['status'] !== 'cancelled') {
            throw new RuntimeException('يوجد بالفعل سجل إعادة صرف لهذا البند.');
        }

        if ($existing) {
            dbExecute("UPDATE returned_disbursement_reissues SET
                amount = ?, original_month = ?, original_return_journal_id = ?,
                original_return_reason = ?, original_returned_at = ?, original_return_receipt_path = ?,
                status = 'authorized', authorized_by = ?, authorized_at = NOW(), authorization_reason = ?,
                redelivered_by = NULL, redelivered_at = NULL, redelivery_receipt_path = NULL,
                redelivery_transaction_id = NULL, redelivery_journal_id = NULL
                WHERE id = ?",
                [(float)$item['amount'], $item['month'], $item['reversal_journal_id'], $item['return_reason'],
                 $item['returned_at'], $item['batch_return_receipt_path'], $userId, $reason, (int)$existing['id']]
            );
            $reissueId = (int)$existing['id'];
        } else {
            dbExecute("INSERT INTO returned_disbursement_reissues
                (disbursement_item_id, disbursement_id, family_id, amount, original_month,
                 original_return_journal_id, original_return_reason, original_returned_at,
                 original_return_receipt_path, status, authorized_by, authorized_at, authorization_reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'authorized', ?, NOW(), ?)",
                [(int)$item['item_id'], (int)$item['disbursement_id'], (int)$item['family_id'],
                 (float)$item['amount'], $item['month'], $item['reversal_journal_id'], $item['return_reason'],
                 $item['returned_at'], $item['batch_return_receipt_path'], $userId, $reason]
            );
            $reissueId = (int)dbLastInsertId();
        }

        dbExecute('COMMIT');
        return ['success' => true, 'id' => $reissueId];
    } catch (Throwable $e) {
        try { dbExecute('ROLLBACK'); } catch (Throwable $rollbackError) {}
        error_log('returned_funds_authorize error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function returned_funds_upload_receipt($file, int $itemId, int $familyId): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'ملف إيصال إعادة الصرف غير صالح.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        return ['success' => false, 'message' => 'نوع الملف غير مسموح. استخدم PDF أو JPG أو PNG أو GIF فقط.'];
    }
    if ((int)$file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم الإيصال يجب ألا يتجاوز 5 ميجابايت.'];
    }
    $dir = dirname(__DIR__, 2) . '/storage/receipts/returned_redelivery';
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
        return ['success' => false, 'message' => 'تعذر إنشاء مجلد إيصالات إعادة الصرف.'];
    }
    $fname = 'returned_redelivery_' . $itemId . '_' . $familyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'message' => 'فشل حفظ إيصال إعادة الصرف على الخادم.'];
    }
    return ['success' => true, 'path' => 'storage/receipts/returned_redelivery/' . $fname, 'absolute_path' => $dest];
}

function returned_funds_redeliver(int $reissueId, int $nannyId, int $userId, string $receiptPath): array
{
    returned_funds_ensure_table();
    try {
        dbExecute('START TRANSACTION');
        $reissue = dbFetchOne("SELECT r.*, i.status AS item_status, i.amount AS item_amount, d.nanny_id, d.month, d.expense_account_code
            FROM returned_disbursement_reissues r
            INNER JOIN disbursement_items i ON i.id = r.disbursement_item_id
            INNER JOIN monthly_disbursements d ON d.id = r.disbursement_id
            WHERE r.id = ? FOR UPDATE", [$reissueId]);
        if (!$reissue) {
            throw new RuntimeException('سجل إعادة الصرف غير موجود.');
        }
        if ((int)$reissue['nanny_id'] !== $nannyId || (int)$reissue['nanny_id'] !== $userId) {
            throw new RuntimeException('هذا السجل ليس ضمن صلاحياتك.');
        }
        if ($reissue['status'] !== 'authorized' || $reissue['item_status'] !== 'returned') {
            throw new RuntimeException('السجل غير جاهز لإعادة الصرف.');
        }
        if (round((float)$reissue['item_amount'], 2) !== round((float)$reissue['amount'], 2)) {
            throw new RuntimeException('مبلغ إعادة الصرف لا يطابق مبلغ السجل الأصلي.');
        }

        $expense = dbFetchOne("SELECT jl.account_id, a.code
            FROM journal_entries je
            INNER JOIN journal_lines jl ON jl.entry_id = je.id
            INNER JOIN accounts a ON a.id = jl.account_id
            WHERE je.reference_type = 'disbursement'
              AND je.reference_id = ?
              AND je.status = 'posted'
              AND jl.debit > 0
            ORDER BY jl.id ASC LIMIT 1", [(int)$reissue['disbursement_id']]);
        if (!$expense) {
            $fallback = trim((string)($reissue['expense_account_code'] ?? '5110'));
            $expense = dbFetchOne("SELECT id AS account_id, code FROM accounts WHERE code = ? LIMIT 1", [$fallback]);
        }
        if (!$expense) {
            throw new RuntimeException('تعذر تحديد حساب المصروف الأصلي لإعادة الصرف.');
        }
        $cash = dbFetchOne("SELECT id, code FROM accounts WHERE code = '1100' LIMIT 1");
        if (!$cash) {
            throw new RuntimeException('حساب الصندوق 1100 غير موجود.');
        }

        $amount = round((float)$reissue['amount'], 2);
        $txRef = 'DISB-REDISB-ITEM-' . (int)$reissue['disbursement_item_id'];
        $existingTx = dbFetchOne("SELECT id FROM transactions WHERE reference_number = ? AND status = 'posted' LIMIT 1", [$txRef]);
        if ($existingTx) {
            throw new RuntimeException('تم تسجيل معاملة إعادة الصرف لهذا السجل مسبقاً.');
        }
        dbExecute("INSERT INTO transactions
            (transaction_type, reference_number, description, amount, currency_code, payment_method,
             transaction_date, status, created_by, created_at, payment_period, purpose_note,
             admin_fee_percent, admin_fee_amount, net_amount)
            VALUES ('disbursement', ?, ?, ?, 'SDG', 'cash', CURDATE(), 'posted', ?, NOW(), ?, ?, 0, 0, ?)",
            [$txRef, 'إعادة صرف مبلغ مرتجع للأسرة ' . $reissue['family_id'] . ' - شهر ' . $reissue['month'],
             $amount, $userId, $reissue['month'], 'إعادة صرف مبلغ مرتجع بعد إعادة فتحه', $amount]
        );
        $transactionId = (int)dbLastInsertId();

        $entryCode = 'JE-REDISB-ITEM-' . (int)$reissue['disbursement_item_id'] . '-' . uniqid();
        dbExecute("INSERT INTO journal_entries
            (entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)
            VALUES (?, CURDATE(), ?, 'disbursement_redelivery', ?, 'posted', ?, NOW())",
            [$entryCode, 'إعادة صرف مبلغ مرتجع للأسرة ' . $reissue['family_id'] . ' - شهر ' . $reissue['month'], $reissueId, $userId]
        );
        $journalId = (int)dbLastInsertId();
        dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, 0, ?)",
            [$journalId, (int)$expense['account_id'], $amount, 'إعادة إثبات مصروف إعادة الصرف']);
        dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, 0, ?, ?)",
            [$journalId, (int)$cash['id'], $amount, 'خصم المبلغ من الصندوق لإعادة الصرف']);

        dbExecute("UPDATE disbursement_items SET status = 'paid', confirmed_at = NOW(), receipt_file_path = ? WHERE id = ? AND status = 'returned'",
            [$receiptPath, (int)$reissue['disbursement_item_id']]);
        dbExecute("UPDATE returned_disbursement_reissues SET status = 'redelivered', redelivered_by = ?, redelivered_at = NOW(), redelivery_receipt_path = ?, redelivery_transaction_id = ?, redelivery_journal_id = ? WHERE id = ? AND status = 'authorized'",
            [$userId, $receiptPath, $transactionId, $journalId, $reissueId]);

        dbExecute('COMMIT');
        return ['success' => true, 'transaction_id' => $transactionId, 'journal_id' => $journalId];
    } catch (Throwable $e) {
        try { dbExecute('ROLLBACK'); } catch (Throwable $rollbackError) {}
        error_log('returned_funds_redeliver error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
