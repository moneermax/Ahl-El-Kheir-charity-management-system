<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/accounting/lib_sponsor_payment_approval.php';
require_once dirname(__DIR__, 2) . '/accounting/lib_transaction_review.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$allowedRoles = ['financial_manager', 'fm', 'finance', 'admin', 'sudo', 'general_manager'];
if (!in_array((string)$user['role_code'], $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Financial review actions are not allowed for this account.',
    ]]);
}

$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$paymentId = filter_var($input['payment_id'] ?? null, FILTER_VALIDATE_INT);
if (!$paymentId || $paymentId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_payment_id',
        'message' => 'A valid payment ID is required.',
    ]]);
}
if (!in_array($action, ['approve', 'return'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_action',
        'message' => 'Action must be approve or return.',
    ]]);
}
$returnNote = trim((string)($input['return_note'] ?? ''));
if ($action === 'return' && $returnNote === '') {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'return_note_required',
        'message' => 'A return reason is required.',
    ]]);
}

$pdo = db();
try {
    if ($action === 'approve') {
        $pdo->beginTransaction();
        try {
            $approval = ak_approve_sponsor_payment($pdo, (int)$paymentId, $userId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        try {
            $gmUsers = dbFetchAll(
                "SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id
                 WHERE r.code = 'general_manager' AND u.is_active = 1"
            );
            foreach ($gmUsers as $gm) {
                ak_transaction_review_notify_event(
                    (int)$gm['id'],
                    'اعتماد تحصيل مشرف #' . (int)$paymentId,
                    'تم اعتماد تحصيل (' . (string)$approval['purpose_label'] . ') بمبلغ '
                        . number_format((float)$approval['amount'], 2) . ' ج.س بواسطة المراجع المالي.',
                    APP_URL . 'modules/accounting/fm_review_queue.php?payment_id=' . (int)$paymentId,
                    (int)$paymentId,
                    'sponsor_payment_approved'
                );
            }
        } catch (Throwable $notificationError) {
            error_log('Mobile sponsor payment approval notification failed: ' . $notificationError->getMessage());
        }

        ak_api_json(200, ['ok' => true, 'data' => [
            'payment_id' => (int)$paymentId,
            'status' => 'approved',
            'transaction_id' => (int)$approval['transaction_id'],
            'journal_id' => (int)$approval['journal_id'],
            'message' => 'Payment approved and posted atomically.',
        ]]);
    }

    $pdo->beginTransaction();
    try {
        $payment = dbFetchOne(
            "SELECT id, supervisor_id, sponsor_id, status
             FROM sponsor_payments WHERE id = ? FOR UPDATE",
            [(int)$paymentId]
        );
        if (!$payment || (string)$payment['status'] !== 'pending') {
            $pdo->rollBack();
            ak_api_json(409, ['ok' => false, 'error' => [
                'code' => 'payment_not_pending',
                'message' => 'The payment is missing or is no longer pending review.',
            ]]);
        }

        $updated = dbExecute(
            "UPDATE sponsor_payments
             SET status = 'returned', reviewed_by_user_id = ?, reviewed_at = NOW(), return_note = ?
             WHERE id = ? AND status = 'pending'",
            [$userId, $returnNote, (int)$paymentId]
        );
        if ($updated !== 1) {
            throw new RuntimeException('The payment changed before the return could be saved.');
        }

        try {
            dbExecute(
                "INSERT INTO audit_log
                 (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'RETURN', 'sponsor_payments', ?, ?, ?, ?, ?)",
                [
                    $userId,
                    (int)$paymentId,
                    json_encode(['status' => 'pending'], JSON_UNESCAPED_UNICODE),
                    json_encode(['status' => 'returned', 'note' => $returnNote], JSON_UNESCAPED_UNICODE),
                    (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ]
            );
        } catch (Throwable $auditError) {
            // Match the existing web workflow: audit failure must not block the return.
            error_log('Mobile sponsor payment return audit failed: ' . $auditError->getMessage());
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    try {
        ak_transaction_review_notify_event(
            (int)$payment['supervisor_id'],
            'تم إرجاع تحصيل مشرف #' . (int)$paymentId,
            'تم إرجاع التحصيل للمراجعة والتعديل. سبب الإرجاع: ' . $returnNote,
            APP_URL . 'modules/transactions/create.php?sponsor_id=' . (int)($payment['sponsor_id'] ?? 0)
                . '&returned_payment=' . (int)$paymentId,
            (int)$paymentId,
            'sponsor_payment_returned'
        );
    } catch (Throwable $notificationError) {
        error_log('Mobile sponsor payment return notification failed: ' . $notificationError->getMessage());
    }

    ak_api_json(200, ['ok' => true, 'data' => [
        'payment_id' => (int)$paymentId,
        'status' => 'returned',
        'message' => 'Payment returned for correction.',
    ]]);
} catch (Throwable $e) {
    error_log('Mobile financial review action failed: ' . $e->getMessage());
    if ($e instanceof RuntimeException && $e->getMessage() === 'الدفعة غير موجودة أو لم تعد معلقة.') {
        ak_api_json(409, ['ok' => false, 'error' => [
            'code' => 'payment_not_pending',
            'message' => 'The payment is missing or is no longer pending review.',
        ]]);
    }
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'review_action_failed',
        'message' => 'The financial review action could not be completed.',
    ]]);
}
