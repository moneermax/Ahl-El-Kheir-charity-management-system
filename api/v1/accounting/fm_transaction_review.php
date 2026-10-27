<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_transaction_review.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
if (!ak_transaction_review_is_fm($role)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Financial transaction review is not allowed for this account.']]);
}

$purposeLabels = [
    'monthly_sponsorship' => 'كفالة شهرية',
    'school_fees' => 'رسوم دراسية',
    'medicine' => 'علاج وأدوية',
    'gift' => 'هدية/عيدية',
    'admin_fee' => 'رسوم إدارية',
    'general_donation' => 'تبرع عام',
    'project_donation' => 'تبرع مشروع',
    'other' => 'أخرى',
];

if ($method === 'GET') {
    try {
        $pendingRows = dbFetchAll(
            "SELECT t.*, s.full_name AS sponsor_name, u.full_name AS creator_name
             FROM transactions t
             LEFT JOIN sponsors s ON s.id = t.sponsor_id
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.status = 'pending_fm_review'
             ORDER BY t.submitted_at ASC, t.id ASC"
        );
        $historyRows = dbFetchAll(
            "SELECT t.*, s.full_name AS sponsor_name, u.full_name AS creator_name,
                    r.full_name AS reviewer_name
             FROM transactions t
             LEFT JOIN sponsors s ON s.id = t.sponsor_id
             LEFT JOIN users u ON u.id = t.created_by
             LEFT JOIN users r ON r.id = t.fm_reviewed_by
             WHERE t.status IN ('returned', 'posted', 'cancelled')
               AND t.fm_reviewed_at IS NOT NULL
             ORDER BY t.fm_reviewed_at DESC
             LIMIT 50"
        );
        $map = static function (array $row, bool $history = false) use ($purposeLabels): array {
            $purpose = (string)($row['purpose'] ?? '');
            $type = (string)($row['transaction_type'] ?? '');
            return [
                'id' => (int)$row['id'],
                'transaction_code' => (string)($row['transaction_code'] ?? ''),
                'submitted_at' => (string)($row['submitted_at'] ?? $row['transaction_date'] ?? ''),
                'transaction_date' => (string)($row['transaction_date'] ?? ''),
                'creator_name' => (string)($row['creator_name'] ?? ''),
                'sponsor_name' => (string)($row['sponsor_name'] ?? ''),
                'purpose' => $purpose,
                'purpose_label' => $purposeLabels[$purpose] ?? ($type !== '' ? $type : 'أخرى'),
                'payment_period' => (string)($row['payment_period'] ?? ''),
                'amount' => (float)($row['amount'] ?? 0),
                'currency_code' => (string)($row['currency_code'] ?? ''),
                'has_receipt' => trim((string)($row['receipt_path'] ?? '')) !== '',
                'has_unified_receipt' => trim((string)($row['unified_receipt_path'] ?? '')) !== '',
                'status' => (string)($row['status'] ?? ''),
                'reviewed_at' => (string)($row['fm_reviewed_at'] ?? ''),
                'reviewer_name' => (string)($row['reviewer_name'] ?? ''),
                'review_reason' => (string)($row['fm_review_reason'] ?? ''),
            ];
        };
        $pending = array_map(static fn(array $row): array => $map($row), $pendingRows);
        $history = array_map(static fn(array $row): array => $map($row, true), $historyRows);
        ak_api_json(200, ['ok' => true, 'data' => [
            'pending' => $pending,
            'pending_count' => count($pending),
            'history' => $history,
            'can_review' => true,
        ]]);
    } catch (Throwable $e) {
        error_log('Mobile ordinary transaction review list failed: ' . $e->getMessage());
        ak_api_json(500, ['ok' => false, 'error' => ['code' => 'review_queue_unavailable', 'message' => 'The transaction review queue could not be loaded.']]);
    }
}

$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$transactionId = filter_var($input['transaction_id'] ?? null, FILTER_VALIDATE_INT);
$reason = trim((string)($input['return_reason'] ?? ''));
if (!in_array($action, ['approve', 'return'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Action must be approve or return.']]);
}
if ($transactionId === false || $transactionId === null || $transactionId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_transaction_id', 'message' => 'A valid transaction ID is required.']]);
}
if ($action === 'return' && $reason === '') {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'return_reason_required', 'message' => 'A return reason is required.']]);
}
if (mb_strlen($reason, 'UTF-8') > 255) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'return_reason_too_long', 'message' => 'The return reason cannot exceed 255 characters.']]);
}

$pdo = db();
$transaction = null;
$journalId = 0;
$oldValues = [];
try {
    $pdo->beginTransaction();
    $transaction = dbFetchOne('SELECT * FROM transactions WHERE id = ? LIMIT 1 FOR UPDATE', [(int)$transactionId]);
    if (!$transaction || (string)$transaction['status'] !== 'pending_fm_review') {
        throw new DomainException('The transaction is not pending financial review.');
    }
    if ((int)($transaction['created_by'] ?? 0) === $userId) {
        throw new DomainException('The creator cannot review their own transaction.');
    }
    $oldValues = [
        'status' => (string)$transaction['status'],
        'fm_reviewed_by' => $transaction['fm_reviewed_by'],
        'fm_reviewed_at' => $transaction['fm_reviewed_at'],
        'fm_review_reason' => $transaction['fm_review_reason'],
    ];

    if ($action === 'approve') {
        $affected = dbExecute(
            "UPDATE transactions
             SET status = 'posted', fm_reviewed_by = ?, fm_reviewed_at = NOW(), fm_review_reason = NULL
             WHERE id = ? AND status = 'pending_fm_review'",
            [$userId, (int)$transactionId]
        );
        if ($affected !== 1) throw new DomainException('The transaction is no longer pending financial review.');
        // This prepared variant follows the web posting logic without invoking runtime schema DDL.
        $journalId = ak_post_transaction_journal_prepared((int)$transactionId, $userId);
        if ($journalId <= 0) throw new RuntimeException('The accounting journal entry was not created.');
        ak_transaction_review_audit($userId, 'FM_APPROVE', (int)$transactionId, $oldValues, ['status' => 'posted', 'journal_id' => $journalId]);
    } else {
        $affected = dbExecute(
            "UPDATE transactions
             SET status = 'returned', fm_reviewed_by = ?, fm_reviewed_at = NOW(), fm_review_reason = ?
             WHERE id = ? AND status = 'pending_fm_review'",
            [$userId, $reason, (int)$transactionId]
        );
        if ($affected !== 1) throw new DomainException('The transaction is no longer pending financial review.');
        ak_transaction_review_audit($userId, 'FM_RETURN', (int)$transactionId, $oldValues, ['status' => 'returned', 'reason' => $reason]);
    }
    $pdo->commit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'transaction_not_reviewable', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mobile ordinary transaction review action failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'review_action_failed', 'message' => 'The transaction review action could not be completed. No partial posting was committed.']]);
}

$creatorId = (int)($transaction['created_by'] ?? 0);
$code = (string)($transaction['transaction_code'] ?? ('#' . (int)$transactionId));
if ($action === 'approve') {
    ak_transaction_review_notify_user(
        $creatorId,
        'تم اعتماد الدفعة',
        'تم اعتماد الدفعة ' . $code . ' وترحيلها للقيد المحاسبي.',
        'modules/transactions/index.php'
    );
    ak_api_json(200, ['ok' => true, 'data' => [
        'transaction_id' => (int)$transactionId,
        'status' => 'posted',
        'journal_id' => $journalId,
        'message' => 'Transaction approved and journal posted atomically.',
    ]]);
}
ak_transaction_review_notify_user(
    $creatorId,
    'تم إرجاع الدفعة للتعديل',
    'تم إرجاع الدفعة ' . $code . ' للتعديل. السبب: ' . $reason,
    'modules/transactions/edit_returned.php?id=' . (int)$transactionId
);
ak_api_json(200, ['ok' => true, 'data' => [
    'transaction_id' => (int)$transactionId,
    'status' => 'returned',
    'return_reason' => $reason,
    'message' => 'Transaction returned for correction.',
]]);
