<?php
/**
 * One-time patch for modules/accounting/disbursements.php.
 *
 * This script is intentionally a temporary repository-side bridge so the large
 * application file does not have to be replaced through the GitHub Contents API.
 * It edits only the exact current source anchors and creates a local backup.
 */

$root = dirname(__DIR__);
$file = $root . '/modules/accounting/disbursements.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: disbursements.php was not found.\n");
    exit(1);
}

$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "ERROR: unable to read disbursements.php.\n");
    exit(1);
}

$expectedSha = '0d8d6c094f838b3d2260513070cf59fb727113a9';
$currentSha = sha1('blob ' . strlen($source) . "\0" . $source);
if ($currentSha !== $expectedSha) {
    fwrite(STDERR, "ERROR: unexpected source version.\nExpected blob SHA: {$expectedSha}\nActual file SHA:    {$currentSha}\nNo changes were made.\n");
    exit(1);
}

$backup = $file . '.before_integrity_patch_20260910.bak';
if (!file_exists($backup) && file_put_contents($backup, $source) === false) {
    fwrite(STDERR, "ERROR: could not create backup.\n");
    exit(1);
}

$changes = [];

// -----------------------------------------------------------------------------
// Fix 1: accounts schema uses name_ar/name_en, not name.
// The names are not used by the reversal logic, so remove the invalid column.
// -----------------------------------------------------------------------------
$replacements = [
    [
        "SELECT jl.account_id, a.code, a.name",
        "SELECT jl.account_id, a.code",
        'Removed invalid accounts.name from original expense lookup.'
    ],
    [
        "SELECT id, code, name FROM accounts WHERE code = ? LIMIT 1",
        "SELECT id, code FROM accounts WHERE code = ? LIMIT 1",
        'Removed invalid accounts.name from fallback expense lookup.'
    ],
    [
        "SELECT id, code, name FROM accounts WHERE code = '1100' LIMIT 1",
        "SELECT id, code FROM accounts WHERE code = '1100' LIMIT 1",
        'Removed invalid accounts.name from cash-account lookup.'
    ],
];

foreach ($replacements as [$old, $new, $label]) {
    if (substr_count($source, $old) !== 1) {
        fwrite(STDERR, "ERROR: expected exactly one occurrence for: {$label}\nNo changes were made.\n");
        exit(1);
    }
    $source = str_replace($old, $new, $source);
    $changes[] = $label;
}

// -----------------------------------------------------------------------------
// Add one generic accounting helper for posted-journal void/reversal handling.
// It preserves the original journal as voided and creates a balanced reversal.
// -----------------------------------------------------------------------------
$helperMarker = '$viewId = isset($_GET[\'view\']) ? (int)$_GET[\'view\'] : null;';
if (substr_count($source, $helperMarker) !== 1) {
    fwrite(STDERR, "ERROR: helper insertion marker not found exactly once.\nNo changes were made.\n");
    exit(1);
}

$helper = <<<'PHP'
/**
 * Void a posted journal entry while preserving the audit trail with a balanced
 * reversal journal. The caller owns the surrounding DB transaction.
 */
function disb_void_posted_journal_atomic(int $journalId, string $reversalReferenceType, int $reversalReferenceId, string $codePrefix, string $reason, int $userId): int {
    $journal = dbFetchOne(
        "SELECT id, entry_code, entry_date, description, reference_type, reference_id, status
        FROM journal_entries WHERE id = ? FOR UPDATE",
        [$journalId]
    );
    if (!$journal) {
        throw new RuntimeException('القيد المحاسبي غير موجود.');
    }
    if ($journal['status'] !== 'posted') {
        throw new RuntimeException('القيد المحاسبي ' . $journalId . ' ليس في حالة مرحّلة.');
    }

    $existing = dbFetchOne(
        "SELECT id FROM journal_entries WHERE reference_type = ? AND reference_id = ? LIMIT 1 FOR UPDATE",
        [$reversalReferenceType, $reversalReferenceId]
    );
    if ($existing) {
        throw new RuntimeException('يوجد قيد عكس سابق مرتبط بالقيد ' . $journalId . '.');
    }

    $lines = dbFetchAll(
        "SELECT account_id, debit, credit, description
        FROM journal_lines WHERE entry_id = ? ORDER BY id",
        [$journalId]
    );
    if (!$lines) {
        throw new RuntimeException('القيد المحاسبي ' . $journalId . ' لا يحتوي على أسطر.');
    }

    $totalDebit = 0.0;
    $totalCredit = 0.0;
    foreach ($lines as $line) {
        $debit = round((float)$line['debit'], 2);
        $credit = round((float)$line['credit'], 2);
        if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
            throw new RuntimeException('سطر القيد ' . $journalId . ' غير صالح.');
        }
        $totalDebit += $debit;
        $totalCredit += $credit;
    }
    if (round($totalDebit, 2) !== round($totalCredit, 2) || round($totalDebit, 2) <= 0) {
        throw new RuntimeException('القيد ' . $journalId . ' غير متوازن أو صفري.');
    }

    $affected = dbExecute(
        "UPDATE journal_entries
        SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ?
        WHERE id = ? AND status = 'posted'",
        [$userId, $reason, $journalId]
    );
    if ($affected !== 1) {
        throw new RuntimeException('تعذر إبطال القيد ' . $journalId . '.');
    }

    $entryCode = $codePrefix . '-' . $journalId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    dbExecute(
        "INSERT INTO journal_entries
        (entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, 'posted', ?, NOW())",
        [
            $entryCode,
            $journal['entry_date'],
            'عكس القيد بسبب إبطال الدفعة #' . $reversalReferenceId . ' — ' . ($journal['description'] ?? ''),
            $reversalReferenceType,
            $reversalReferenceId,
            $userId
        ]
    );
    $reversalId = (int)dbLastInsertId();
    if ($reversalId <= 0) {
        throw new RuntimeException('تعذر الحصول على رقم قيد العكس للقيد ' . $journalId . '.');
    }

    $lineCount = dbExecute(
        "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
        SELECT ?, account_id, credit, debit, CONCAT('عكس: ', COALESCE(description, ''))
        FROM journal_lines WHERE entry_id = ? ORDER BY id",
        [$reversalId, $journalId]
    );
    if ($lineCount !== count($lines)) {
        throw new RuntimeException('تعذر إنشاء جميع أسطر قيد العكس للقيد ' . $journalId . '.');
    }

    $totals = dbFetchOne(
        "SELECT COALESCE(SUM(debit),0) AS total_debit,
                COALESCE(SUM(credit),0) AS total_credit,
                COUNT(*) AS line_count
        FROM journal_lines WHERE entry_id = ?",
        [$reversalId]
    );
    if ((int)($totals['line_count'] ?? 0) !== count($lines)
        || round((float)$totals['total_debit'], 2) !== round((float)$totals['total_credit'], 2)
        || round((float)$totals['total_debit'], 2) <= 0) {
        throw new RuntimeException('قيد العكس ' . $reversalId . ' غير متوازن أو غير مكتمل.');
    }

    return $reversalId;
}

/**
 * Reopen a returned family item by posting a compensating journal for exactly
 * that returned amount. The original return journal remains posted for audit.
 */
function disb_reverse_return_for_item_atomic(int $itemId, int $disbursementId, float $amount, string $reason, int $userId): int {
    if ($amount <= 0) {
        throw new RuntimeException('مبلغ الأسرة المطلوب إعادة فتحه غير صالح.');
    }

    $existing = dbFetchOne(
        "SELECT id FROM journal_entries
        WHERE reference_type = 'disbursement_item_reopen' AND reference_id = ?
        LIMIT 1 FOR UPDATE",
        [$itemId]
    );
    if ($existing) {
        throw new RuntimeException('يوجد قيد تصحيح سابق لإعادة فتح الأسرة رقم ' . $itemId . '.');
    }

    $returnJournal = dbFetchOne(
        "SELECT id, entry_date, description
        FROM journal_entries
        WHERE reference_type = 'disbursement_return' AND reference_id = ? AND status = 'posted'
        ORDER BY id DESC LIMIT 1 FOR UPDATE",
        [$disbursementId]
    );
    if (!$returnJournal) {
        throw new RuntimeException('لا يوجد قيد إرجاع مرحّل مرتبط بهذه الدفعة.');
    }

    $debitLine = dbFetchOne(
        "SELECT account_id, debit FROM journal_lines
        WHERE entry_id = ? AND debit > 0 ORDER BY id ASC LIMIT 1",
        [$returnJournal['id']]
    );
    $creditLine = dbFetchOne(
        "SELECT account_id, credit FROM journal_lines
        WHERE entry_id = ? AND credit > 0 ORDER BY id ASC LIMIT 1",
        [$returnJournal['id']]
    );
    if (!$debitLine || !$creditLine) {
        throw new RuntimeException('قيد الإرجاع المرتبط بالدفعة غير مكتمل.');
    }

    $returnedTotal = round((float)$debitLine['debit'], 2);
    $amount = round($amount, 2);
    if ($amount > $returnedTotal) {
        throw new RuntimeException('مبلغ إعادة فتح الأسرة يتجاوز مبلغ الإرجاع الأصلي.');
    }

    $entryCode = 'JE-REOPEN-RET-' . $itemId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    dbExecute(
        "INSERT INTO journal_entries
        (entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)
        VALUES (?, ?, ?, 'disbursement_item_reopen', ?, 'posted', ?, NOW())",
        [
            $entryCode,
            $returnJournal['entry_date'],
            'تصحيح قيد الإرجاع لإعادة فتح سجل الأسرة #' . $itemId . ' ضمن الدفعة #' . $disbursementId . ' — ' . $reason,
            $itemId,
            $userId
        ]
    );
    $journalId = (int)dbLastInsertId();
    if ($journalId <= 0) {
        throw new RuntimeException('تعذر إنشاء قيد تصحيح إعادة فتح الأسرة.');
    }

    // Original return: Dr Cash / Cr Expense.
    // Reopening:         Dr Expense / Cr Cash.
    dbExecute(
        "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
        VALUES (?, ?, ?, 0, ?)",
        [$journalId, (int)$creditLine['account_id'], $amount, 'إعادة تحميل المصروف بعد إعادة فتح الأسرة #' . $itemId]
    );
    dbExecute(
        "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
        VALUES (?, ?, 0, ?, ?)",
        [$journalId, (int)$debitLine['account_id'], $amount, 'عكس أثر إرجاع الأسرة #' . $itemId]
    );

    $totals = dbFetchOne(
        "SELECT COALESCE(SUM(debit),0) AS total_debit,
                COALESCE(SUM(credit),0) AS total_credit,
                COUNT(*) AS line_count
        FROM journal_lines WHERE entry_id = ?",
        [$journalId]
    );
    if ((int)$totals['line_count'] !== 2
        || round((float)$totals['total_debit'], 2) !== round((float)$totals['total_credit'], 2)
        || round((float)$totals['total_debit'], 2) !== $amount) {
        throw new RuntimeException('قيد تصحيح إعادة فتح الأسرة غير متوازن.');
    }

    return $journalId;
}

PHP;

$source = str_replace($helperMarker, $helper . $helperMarker, $source);
$changes[] = 'Added atomic posted-journal reversal helper.';
$changes[] = 'Added item-level compensating journal helper for returned-family reopen.';

// -----------------------------------------------------------------------------
// Replace reopen-batch handler: returned batches are not reopened as a whole.
// A returned batch already has a posted return journal; returned family records
// must be reopened individually so the exact accounting amount can be restored.
// -----------------------------------------------------------------------------
$oldReopenBatch = <<<'PHP'
    // 6. Accountant: Reopen an entire closed/returned batch and its group for further nanny edits
    if ($canManage && isset($_POST['reopen_batch'])) {
        $id = (int)$_POST['reopen_batch'];
        $reason = trim((string)($_POST['reopen_batch_reason'] ?? ''));
        $b = dbFetchOne("SELECT * FROM monthly_disbursements WHERE id = ?", [$id]);
        if (!$b || !in_array($b['status'], ['received', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذه الدفعة من حالتها الحالية.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب إعادة الفتح.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldStatus = $b['status'];
                dbExecute("UPDATE monthly_disbursements SET status = 'transferred' WHERE id = ?", [$id]);
                if (!empty($b['group_id'])) {
                    dbExecute("UPDATE orphan_groups SET verification_status = 'transferred', closed_at = NULL, closed_by = NULL WHERE id = ?", [$b['group_id']]);
                }
                log_group_action($b['group_id'] ?: null, null, $b['month'], 'group_reopened', $uid, $role, $oldStatus, 'transferred', $reason);
                disb_audit($uid, 'REOPEN_BATCH', $id, ['from' => $oldStatus, 'to' => 'transferred', 'reason' => $reason]);
                dbExecute('COMMIT');
                flash('success', 'تم إعادة فتح الدفعة والمجموعة بالكامل. يمكن للحاضنة الآن تعديل السجلات.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Reopen batch error: ' . $e->getMessage());
                flash('error', 'تعذر إعادة فتح الدفعة: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
PHP;
$newReopenBatch = <<<'PHP'
    // 6. Accountant: Reopen a fully received batch and its group for further nanny edits.
    // Returned batches are intentionally NOT reopened at batch level because their
    // posted return journal must remain authoritative. Returned family records are
    // reopened individually below with an exact compensating journal.
    if ($canManage && isset($_POST['reopen_batch'])) {
        $id = (int)$_POST['reopen_batch'];
        $reason = trim((string)($_POST['reopen_batch_reason'] ?? ''));
        $b = dbFetchOne("SELECT * FROM monthly_disbursements WHERE id = ?", [$id]);
        if (!$b || $b['status'] !== 'received') {
            flash('error', 'لا يمكن إعادة فتح هذه الدفعة من حالتها الحالية. الدفعات المُعادة تُفتح أسرةً بأسرة لضمان سلامة القيد المحاسبي.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب إعادة الفتح.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldStatus = $b['status'];
                dbExecute("UPDATE monthly_disbursements SET status = 'transferred' WHERE id = ?", [$id]);
                if (!empty($b['group_id'])) {
                    dbExecute("UPDATE orphan_groups SET verification_status = 'transferred', closed_at = NULL, closed_by = NULL WHERE id = ?", [$b['group_id']]);
                }
                log_group_action($b['group_id'] ?: null, null, $b['month'], 'group_reopened', $uid, $role, $oldStatus, 'transferred', $reason);
                disb_audit($uid, 'REOPEN_BATCH', $id, ['from' => $oldStatus, 'to' => 'transferred', 'reason' => $reason]);
                dbExecute('COMMIT');
                flash('success', 'تم إعادة فتح الدفعة والمجموعة بالكامل. يمكن للحاضنة الآن تعديل السجلات.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Reopen batch error: ' . $e->getMessage());
                flash('error', 'تعذر إعادة فتح الدفعة: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
PHP;
if (substr_count($source, $oldReopenBatch) !== 1) {
    fwrite(STDERR, "ERROR: reopen-batch handler was not found exactly once.\nNo changes were made.\n");
    exit(1);
}
$source = str_replace($oldReopenBatch, $newReopenBatch, $source);
$changes[] = 'Restricted whole-batch reopen to received batches; returned batches remain accounting-final.';

// -----------------------------------------------------------------------------
// Replace reopen-item handler with exact accounting treatment for returned items.
// Paid item: no accounting change.
// Returned item: create a compensating journal for that item's amount before
// moving it back to pending.
// -----------------------------------------------------------------------------
$oldReopenItem = <<<'PHP'
    // 7. Accountant: Reopen a single family record within a batch for re-confirmation
    // (e.g. she uploaded the wrong receipt, or regained contact with a family
    // previously marked as lost / returned to the fund).
    if ($canManage && isset($_POST['reopen_item'])) {
        $itemId = (int)$_POST['reopen_item'];
        $reason = trim((string)($_POST['reopen_item_reason'] ?? ''));
        $item = dbFetchOne(
            "SELECT i.*, d.id AS disbursement_id, d.status AS dstatus, d.group_id, d.month
            FROM disbursement_items i JOIN monthly_disbursements d ON d.id = i.disbursement_id
            WHERE i.id = ?", [$itemId]
        );
        $redirectId = $item['disbursement_id'] ?? null;
        if (!$item || !in_array($item['status'], ['paid', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذا السجل من حالته الحالية.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب إعادة الفتح.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldItemStatus = $item['status'];
                dbExecute(
                    "UPDATE disbursement_items SET status = 'pending', confirmed_at = NULL, receipt_file_path = NULL,
                    return_reason = NULL, return_requested_at = NULL, return_requested_by_user_id = NULL,
                    returned_at = NULL, returned_by_user_id = NULL, reversal_journal_id = NULL
                    WHERE id = ?", [$itemId]
                );
                // If the parent batch/group had already been fully closed, reopen it too
                // so the nanny actually has somewhere to act on this item.
                if (in_array($item['dstatus'], ['received', 'returned'], true)) {
                    dbExecute("UPDATE monthly_disbursements SET status = 'transferred' WHERE id = ?", [$item['disbursement_id']]);
                    if (!empty($item['group_id'])) {
                        dbExecute("UPDATE orphan_groups SET verification_status = 'transferred', closed_at = NULL, closed_by = NULL WHERE id = ?", [$item['group_id']]);
                    }
                }
                log_group_action($item['group_id'] ?: null, (int)$item['family_id'], $item['month'], 'family_reopened', $uid, $role, $oldItemStatus, 'pending', $reason);
                disb_audit($uid, 'REOPEN_ITEM', (int)$item['disbursement_id'], ['item_id' => $itemId, 'family_id' => $item['family_id'], 'from' => $oldItemStatus, 'reason' => $reason]);
                dbExecute('COMMIT');
                flash('success', 'تم إعادة فتح سجل الأسرة للتعديل بنجاح.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Reopen item error: ' . $e->getMessage());
                flash('error', 'تعذر إعادة فتح السجل: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php' . ($redirectId ? '?view=' . $redirectId : ''));
        exit();
    }
PHP;
$newReopenItem = <<<'PHP'
    // 7. Accountant: Reopen a single family record within a batch for re-confirmation.
    // A returned item has already affected accounting through the posted return journal,
    // so reopening it first posts an exact compensating journal for that item's amount.
    if ($canManage && isset($_POST['reopen_item'])) {
        $itemId = (int)$_POST['reopen_item'];
        $reason = trim((string)($_POST['reopen_item_reason'] ?? ''));
        $item = dbFetchOne(
            "SELECT i.*, d.id AS disbursement_id, d.status AS dstatus, d.group_id, d.month
            FROM disbursement_items i JOIN monthly_disbursements d ON d.id = i.disbursement_id
            WHERE i.id = ?", [$itemId]
        );
        $redirectId = $item['disbursement_id'] ?? null;
        if (!$item || !in_array($item['status'], ['paid', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذا السجل من حالته الحالية.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب إعادة الفتح.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldItemStatus = $item['status'];
                $reopenJournalId = null;
                if ($oldItemStatus === 'returned') {
                    $reopenJournalId = disb_reverse_return_for_item_atomic(
                        $itemId,
                        (int)$item['disbursement_id'],
                        (float)$item['amount'],
                        $reason,
                        $uid
                    );
                }
                dbExecute(
                    "UPDATE disbursement_items SET status = 'pending', confirmed_at = NULL, receipt_file_path = NULL,
                    return_reason = NULL, return_requested_at = NULL, return_requested_by_user_id = NULL,
                    returned_at = NULL, returned_by_user_id = NULL, reversal_journal_id = NULL
                    WHERE id = ?", [$itemId]
                );
                // If the parent batch/group had already been fully closed, reopen it too
                // so the nanny actually has somewhere to act on this item.
                if (in_array($item['dstatus'], ['received', 'returned'], true)) {
                    dbExecute("UPDATE monthly_disbursements SET status = 'transferred' WHERE id = ?", [$item['disbursement_id']]);
                    if (!empty($item['group_id'])) {
                        dbExecute("UPDATE orphan_groups SET verification_status = 'transferred', closed_at = NULL, closed_by = NULL WHERE id = ?", [$item['group_id']]);
                    }
                }
                log_group_action($item['group_id'] ?: null, (int)$item['family_id'], $item['month'], 'family_reopened', $uid, $role, $oldItemStatus, 'pending', $reason);
                disb_audit($uid, 'REOPEN_ITEM', (int)$item['disbursement_id'], [
                    'item_id' => $itemId,
                    'family_id' => $item['family_id'],
                    'from' => $oldItemStatus,
                    'reason' => $reason,
                    'compensating_journal_id' => $reopenJournalId
                ]);
                dbExecute('COMMIT');
                flash('success', 'تم إعادة فتح سجل الأسرة للتعديل بنجاح.' . ($reopenJournalId ? ' وتم إنشاء قيد تصحيح محاسبي متوازن.' : ''));
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Reopen item error: ' . $e->getMessage());
                flash('error', 'تعذر إعادة فتح السجل: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php' . ($redirectId ? '?view=' . $redirectId : ''));
        exit();
    }
PHP;
if (substr_count($source, $oldReopenItem) !== 1) {
    fwrite(STDERR, "ERROR: reopen-item handler was not found exactly once.\nNo changes were made.\n");
    exit(1);
}
$source = str_replace($oldReopenItem, $newReopenItem, $source);
$changes[] = 'Added balanced compensating journal when reopening a returned family item.';

// -----------------------------------------------------------------------------
// Replace void-batch handler so every posted batch-related journal is voided with
// a balanced reversal instead of simply being marked voided.
// -----------------------------------------------------------------------------
$oldVoid = <<<'PHP'
    // 8. Admin/Financial Manager: Void a mistakenly created/transferred batch.
    // Restricted to admin/financial_manager (not accountant/accountant_staff) as a basic
    // segregation-of-duties control — the person who posted an entry should not be the
    // sole party able to reverse it. Properly voids the linked journal entry AND
    // transaction (not just the batch row), matching real accounting practice: reversed,
    // never deleted, so the audit trail stays intact.
    if (in_array($role, ['admin', 'financial_manager'], true) && isset($_POST['void_batch'])) {
        $id = (int)$_POST['void_batch'];
        $reason = trim((string)($_POST['void_batch_reason'] ?? ''));
        $b = dbFetchOne("SELECT * FROM monthly_disbursements WHERE id = ?", [$id]);
        if (!$b || in_array($b['status'], ['voided', 'cancelled', 'draft'], true)) {
            flash('error', 'لا يمكن إبطال هذه الدفعة من حالتها الحالية.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب الإبطال.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldStatus = $b['status'];

                // Void the linked journal entry, if one was actually posted (a batch that
                // never reached the transfer step won't have one — that's fine, skip it).
                $je = dbFetchOne("SELECT id FROM journal_entries WHERE reference_type = 'disbursement' AND reference_id = ? AND status = 'posted'", [$id]);
                if ($je) {
                    dbExecute(
                        "UPDATE journal_entries SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ? WHERE id = ?",
                        [$uid, $reason, $je['id']]
                    );
                }
                // Void the linked transaction, found via the same DISB-OUT-{id} convention
                // used when it was created (there is no direct foreign key between
                // transactions and journal_entries in this schema — both are located via
                // their shared reference to the disbursement batch itself).
                $tx = dbFetchOne("SELECT id FROM transactions WHERE reference_number = ? AND status = 'posted'", ['DISB-OUT-' . $id]);
                if ($tx) {
                    dbExecute(
                        "UPDATE transactions SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ? WHERE id = ?",
                        [$uid, $reason, $tx['id']]
                    );
                }
                dbExecute(
                    "UPDATE monthly_disbursements SET status = 'voided', voided_by_user_id = ?, voided_at = NOW(), void_reason = ? WHERE id = ?",
                    [$uid, $reason, $id]
                );

                disb_audit($uid, 'VOID_BATCH', $id, [
                    'from' => $oldStatus, 'to' => 'voided', 'reason' => $reason,
                    'journal_entry_voided' => $je['id'] ?? null, 'transaction_voided' => $tx['id'] ?? null,
                ]);
                dbExecute('COMMIT');
                flash('success', 'تم إبطال الدفعة والقيد المحاسبي المرتبط بها بنجاح. يمكن الآن إنشاء دفعة جديدة صحيحة لهذه المجموعة.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Void batch error: ' . $e->getMessage());
                flash('error', 'تعذر إبطال الدفعة: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
PHP;
$newVoid = <<<'PHP'
    // 8. Admin/Financial Manager: Void a mistakenly created/transferred batch.
    // Every posted accounting journal related to the batch is preserved as voided and
    // receives its own balanced reversal journal. This includes the original
    // disbursement journal, any posted return journal, and any item-reopen compensating
    // journals created before the batch was voided.
    if (in_array($role, ['admin', 'financial_manager'], true) && isset($_POST['void_batch'])) {
        $id = (int)$_POST['void_batch'];
        $reason = trim((string)($_POST['void_batch_reason'] ?? ''));
        $b = dbFetchOne("SELECT * FROM monthly_disbursements WHERE id = ?", [$id]);
        if (!$b || in_array($b['status'], ['voided', 'cancelled', 'draft'], true)) {
            flash('error', 'لا يمكن إبطال هذه الدفعة من حالتها الحالية.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب الإبطال.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldStatus = $b['status'];
                $voidedJournals = [];
                $reversalJournals = [];

                $postedJournals = dbFetchAll(
                    "SELECT id, reference_type, reference_id
                    FROM journal_entries
                    WHERE status = 'posted'
                    AND (
                        (reference_type = 'disbursement' AND reference_id = ?)
                        OR (reference_type = 'disbursement_return' AND reference_id = ?)
                        OR (reference_type = 'disbursement_item_reopen' AND reference_id IN (
                            SELECT id FROM disbursement_items WHERE disbursement_id = ?
                        ))
                    )
                    ORDER BY id ASC FOR UPDATE",
                    [$id, $id, $id]
                );

                foreach ($postedJournals as $postedJournal) {
                    $reversalType = match ($postedJournal['reference_type']) {
                        'disbursement' => 'disbursement_void',
                        'disbursement_return' => 'disbursement_return_void',
                        'disbursement_item_reopen' => 'disbursement_item_reopen_void',
                        default => throw new RuntimeException('نوع قيد غير متوقع مرتبط بالدفعة.')
                    };
                    $reversalReferenceId = (int)$postedJournal['reference_id'];
                    $reversalId = disb_void_posted_journal_atomic(
                        (int)$postedJournal['id'],
                        $reversalType,
                        $reversalReferenceId,
                        'JE-DISB-VOID',
                        $reason,
                        $uid
                    );
                    $voidedJournals[] = (int)$postedJournal['id'];
                    $reversalJournals[] = $reversalId;
                }

                // Void the linked operational transaction as well.
                $tx = dbFetchOne("SELECT id FROM transactions WHERE reference_number = ? AND status = 'posted'", ['DISB-OUT-' . $id]);
                if ($tx) {
                    dbExecute(
                        "UPDATE transactions SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ? WHERE id = ?",
                        [$uid, $reason, $tx['id']]
                    );
                }

                dbExecute(
                    "UPDATE monthly_disbursements SET status = 'voided', voided_by_user_id = ?, voided_at = NOW(), void_reason = ? WHERE id = ?",
                    [$uid, $reason, $id]
                );

                disb_audit($uid, 'VOID_BATCH', $id, [
                    'from' => $oldStatus,
                    'to' => 'voided',
                    'reason' => $reason,
                    'journal_entries_voided' => $voidedJournals,
                    'reversal_journal_entries' => $reversalJournals,
                    'transaction_voided' => $tx['id'] ?? null,
                ]);
                dbExecute('COMMIT');
                flash('success', 'تم إبطال الدفعة وإنشاء قيود عكسية متوازنة لجميع القيود المحاسبية المرتبطة بها. يمكن الآن إنشاء دفعة صحيحة جديدة.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Void batch error: ' . $e->getMessage());
                flash('error', 'تعذر إبطال الدفعة: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
PHP;
if (substr_count($source, $oldVoid) !== 1) {
    fwrite(STDERR, "ERROR: void-batch handler was not found exactly once.\nNo changes were made.\n");
    exit(1);
}
$source = str_replace($oldVoid, $newVoid, $source);
$changes[] = 'Changed batch voiding to create balanced reversals for all posted batch-related journals.';

// -----------------------------------------------------------------------------
// UI: whole-batch reopen is only offered for received batches.
// -----------------------------------------------------------------------------
$uiOld = <<<'HTML'
<?php if ($canManage && in_array($viewBatch['status'], ['received', 'returned'])): ?>
HTML;
$uiNew = <<<'HTML'
<?php if ($canManage && $viewBatch['status'] === 'received'): ?>
HTML;
if (substr_count($source, $uiOld) !== 1) {
    fwrite(STDERR, "ERROR: batch-reopen UI condition was not found exactly once.\nNo changes were made.\n");
    exit(1);
}
$source = str_replace($uiOld, $uiNew, $source);
$changes[] = 'Updated batch-reopen UI to hide the whole-batch action for returned batches.';

// -----------------------------------------------------------------------------
// Final safety checks before writing.
// -----------------------------------------------------------------------------
$required = [
    'function disb_void_posted_journal_atomic',
    'function disb_reverse_return_for_item_atomic',
    "SELECT jl.account_id, a.code",
    "reference_type = 'disbursement_return'",
    "reference_type = 'disbursement_item_reopen'",
    "reference_type = 'disbursement_void'",
    "reference_type = 'disbursement_return_void'",
    "reference_type = 'disbursement_item_reopen_void'",
    "if (!$b || $b['status'] !== 'received')",
];
foreach ($required as $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "ERROR: final source check failed for: {$needle}\nNo changes were written.\n");
        exit(1);
    }
}

if (file_put_contents($file, $source, LOCK_EX) === false) {
    fwrite(STDERR, "ERROR: could not write patched disbursements.php.\n");
    exit(1);
}

$newSha = hash('sha1', $source);

fwrite(STDOUT, "PATCH APPLIED SUCCESSFULLY\n");
fwrite(STDOUT, "Original SHA: {$expectedSha}\n");
fwrite(STDOUT, "New local SHA: {$newSha}\n");
fwrite(STDOUT, "Backup: {$backup}\n\n");
foreach ($changes as $change) {
    fwrite(STDOUT, "- {$change}\n");
}

?>
