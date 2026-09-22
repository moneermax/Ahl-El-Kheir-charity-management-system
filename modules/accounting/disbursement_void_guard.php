<?php
// Narrow accounting guard for the legacy void_batch POST route in disbursements.php.
// This runs only when config.php detects the exact route/action and prevents the
// legacy handler from directly voiding a posted disbursement journal.

declare(strict_types=1);

if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== 'disbursements.php') {
    return;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['void_batch'])) {
    return;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';

Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'financial_manager'], true)) {
    flash('error', 'ليس لديك صلاحية إبطال الدفعة.');
    header('Location: ' . APP_URL . 'modules/accounting/disbursements.php');
    exit();
}

if (function_exists('verify_csrf') && !verify_csrf()) {
    flash('error', 'انتهت صلاحية الطلب الأمني. يرجى المحاولة مرة أخرى.');
    header('Location: ' . APP_URL . 'modules/accounting/disbursements.php');
    exit();
}

$uid = Session::getUserId();
$id = (int)$_POST['void_batch'];
$reason = trim((string)($_POST['void_batch_reason'] ?? ''));
$redirect = APP_URL . 'modules/accounting/disbursements.php?view=' . $id;

if ($id <= 0 || $reason === '') {
    flash('error', 'يرجى تحديد الدفعة وكتابة سبب الإبطال.');
    header('Location: ' . $redirect);
    exit();
}

try {
    dbExecute('START TRANSACTION');

    $batch = dbFetchOne(
        "SELECT id, month, nanny_id, group_id, total_amount, status, transaction_id, reversal_journal_id
         FROM monthly_disbursements
         WHERE id = ?
         FOR UPDATE",
        [$id]
    );

    if (!$batch) {
        throw new RuntimeException('الدفعة غير موجودة.');
    }

    // A returned/received batch may already have a separate disbursement_return
    // accounting event. Do not let the old generic void route create an incomplete
    // accounting history. Only the pre-return states are eligible here.
    if (!in_array($batch['status'], ['pending_approval', 'transferred'], true)) {
        throw new RuntimeException('لا يمكن إبطال الدفعة من حالتها الحالية.');
    }

    if (!empty($batch['reversal_journal_id'])) {
        throw new RuntimeException('هذه الدفعة لديها قيد عكسي بالفعل ولا يمكن إبطالها مرة أخرى.');
    }

    $original = dbFetchOne(
        "SELECT id, entry_code, entry_date, description, reference_type, reference_id
         FROM journal_entries
         WHERE reference_type = 'disbursement'
           AND reference_id = ?
           AND status = 'posted'
         ORDER BY id ASC
         LIMIT 1
         FOR UPDATE",
        [$id]
    );

    $journalId = null;

    if ($original) {
        $originalLines = dbFetchAll(
            "SELECT account_id, debit, credit, description
             FROM journal_lines
             WHERE entry_id = ?
             ORDER BY id ASC",
            [(int)$original['id']]
        );

        if (count($originalLines) < 2) {
            throw new RuntimeException('القيد الأصلي غير صالح للإبطال: يجب أن يحتوي على سطرين على الأقل.');
        }

        $debitTotal = 0.0;
        $creditTotal = 0.0;
        foreach ($originalLines as $line) {
            $debitTotal += (float)$line['debit'];
            $creditTotal += (float)$line['credit'];
            if ((float)$line['debit'] < 0 || (float)$line['credit'] < 0) {
                throw new RuntimeException('القيد الأصلي يحتوي على مبلغ سالب وغير صالح للإبطال.');
            }
        }

        if (abs($debitTotal - $creditTotal) > 0.005 || $debitTotal <= 0) {
            throw new RuntimeException('القيد الأصلي غير متوازن ولا يمكن إنشاء قيد عكسي آمن له.');
        }

        // Preserve the original posted entry and create a separate reversal entry.
        // This is the required accounting pattern for a posted disbursement void.
        $entryCode = 'JE-REV-DISB-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT) . '-' . date('YmdHis');
        dbExecute(
            "INSERT INTO journal_entries
             (entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)
             VALUES (?, CURDATE(), ?, 'disbursement_void', ?, 'posted', ?, NOW())",
            [$entryCode, 'عكس إبطال دفعة #' . $id . ' - ' . $reason, $id, $uid]
        );
        $journalId = (int)dbLastInsertId();

        foreach ($originalLines as $line) {
            dbExecute(
                "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $journalId,
                    (int)$line['account_id'],
                    (float)$line['credit'],
                    (float)$line['debit'],
                    'عكس: ' . (string)($line['description'] ?? '')
                ]
            );
        }

        // Validate the reversal before changing the original status.
        $reversalTotals = dbFetchOne(
            "SELECT COALESCE(SUM(debit),0) AS debit_total, COALESCE(SUM(credit),0) AS credit_total
             FROM journal_lines
             WHERE entry_id = ?",
            [$journalId]
        );
        if (!$reversalTotals || abs((float)$reversalTotals['debit_total'] - (float)$reversalTotals['credit_total']) > 0.005) {
            throw new RuntimeException('فشل التحقق من توازن القيد العكسي.');
        }

        // The original entry stays 'posted' (see lib_transaction_void.php for why); only the
        // audit metadata changes. $batch['reversal_journal_id'] above already guards against voiding twice.
        dbExecute(
            "UPDATE journal_entries
             SET voided_at = NOW(), voided_by = ?, void_reason = ?
             WHERE id = ? AND status = 'posted' AND voided_at IS NULL",
            [$uid, $reason, (int)$original['id']]
        );
    }

    if (!empty($batch['transaction_id'])) {
        dbExecute(
            "UPDATE transactions
             SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ?
             WHERE id = ? AND status = 'posted'",
            [$uid, $reason, (int)$batch['transaction_id']]
        );
    } else {
        // Keep compatibility with older rows that predate transaction_id linkage.
        dbExecute(
            "UPDATE transactions
             SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ?
             WHERE reference_number = ? AND status = 'posted'",
            [$uid, $reason, 'DISB-OUT-' . $id]
        );
    }

    dbExecute(
        "UPDATE monthly_disbursements
         SET status = 'voided', voided_by_user_id = ?, voided_at = NOW(), void_reason = ?, reversal_journal_id = ?
         WHERE id = ? AND status IN ('pending_approval','transferred')",
        [$uid, $reason, $journalId, $id]
    );

    $updated = dbFetchOne(
        "SELECT status, reversal_journal_id
         FROM monthly_disbursements
         WHERE id = ?",
        [$id]
    );
    if (!$updated || $updated['status'] !== 'voided' || ($journalId !== null && (int)$updated['reversal_journal_id'] !== $journalId)) {
        throw new RuntimeException('فشل التحقق من حالة الدفعة بعد الإبطال.');
    }

    // Match the actual audit_log schema: the action column stores the event name.
    dbExecute(
        "INSERT INTO audit_log
         (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
         VALUES (?, 'DISBURSEMENT_VOID', 'monthly_disbursements', ?, ?, ?, ?, ?, NOW())",
        [
            $uid,
            $id,
            json_encode([
                'status' => $batch['status'],
                'transaction_id' => $batch['transaction_id'] ?? null,
                'original_journal_id' => $original['id'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode([
                'status' => 'voided',
                'reason' => $reason,
                'original_journal_id' => $original['id'] ?? null,
                'reversal_journal_id' => $journalId,
                'transaction_id' => $batch['transaction_id'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $_SERVER['REMOTE_ADDR'] ?? 'cli',
            $_SERVER['HTTP_USER_AGENT'] ?? 'cli'
        ]
    );

    dbExecute('COMMIT');
    flash('success', 'تم إبطال الدفعة وإنشاء القيد العكسي مع الحفاظ على القيد الأصلي وسجل التدقيق.');
} catch (Throwable $e) {
    try { dbExecute('ROLLBACK'); } catch (Throwable $rollbackError) {}
    error_log('Protected disbursement void error: ' . $e->getMessage());
    flash('error', 'تعذر إبطال الدفعة: ' . $e->getMessage());
}

header('Location: ' . $redirect);
exit();
