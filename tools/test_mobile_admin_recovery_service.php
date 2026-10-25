<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/admin/recovery_service.php';

$pdo = db();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectError = static function (string $code, callable $callback) use ($assert): AkAdminRecoveryException {
    try {
        $callback();
    } catch (AkAdminRecoveryException $e) {
        $assert($e->errorCode === $code, 'Expected error ' . $code . ', got ' . $e->errorCode . '.');
        return $e;
    }
    throw new RuntimeException('Expected recovery service error: ' . $code);
};

$reviewer = dbFetchOne(
    "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.code IN ('admin', 'sudo', 'super_admin', 'hr_manager') ORDER BY u.id LIMIT 1"
);
$target = dbFetchOne(
    'SELECT id, password_hash, password_change_required FROM users ORDER BY id LIMIT 1'
);
$assert($reviewer !== null, 'A recovery reviewer account is required.');
$assert($target !== null, 'A target user account is required.');
$reviewerId = (int)$reviewer['id'];
$targetId = (int)$target['id'];
$originalHash = (string)$target['password_hash'];
$originalChangeRequired = (int)$target['password_change_required'];
$pdo->beginTransaction();
$requestIds = [];
try {
    dbExecute(
        "INSERT INTO password_recovery_requests (user_id, status, requested_at) VALUES (?, 'pending', NOW())",
        [$targetId]
    );
    $approveId = (int)$pdo->lastInsertId();
    $requestIds[] = $approveId;

    $queue = ak_api_admin_recovery_list();
    $queueIds = array_map(static fn(array $item): int => (int)$item['id'], $queue['items']);
    $assert(in_array($approveId, $queueIds, true), 'Recovery queue omitted a pending request.');

    $approved = ak_api_admin_recovery_action(['request_id' => $approveId, 'action' => 'approve'], $reviewerId);
    $temporaryPassword = (string)($approved['temporary_password'] ?? '');
    $assert(strlen($temporaryPassword) === 12, 'Approval did not return the expected one-time temporary password.');
    $afterUser = dbFetchOne('SELECT password_hash, password_change_required FROM users WHERE id = ?', [$targetId]);
    $assert($afterUser !== null && password_verify($temporaryPassword, (string)$afterUser['password_hash']), 'Temporary password does not match the saved password hash.');
    $assert((int)$afterUser['password_change_required'] === 1, 'Approval did not require a password change.');
    $afterRequest = dbFetchOne('SELECT status, reviewed_by, expires_at FROM password_recovery_requests WHERE id = ?', [$approveId]);
    $assert($afterRequest !== null && $afterRequest['status'] === 'approved', 'Recovery request was not approved.');
    $assert((int)$afterRequest['reviewed_by'] === $reviewerId && !empty($afterRequest['expires_at']), 'Approval reviewer/expiry fields are incorrect.');
    $expectError('request_already_processed', static fn() => ak_api_admin_recovery_action(['request_id' => $approveId, 'action' => 'approve'], $reviewerId));

    dbExecute(
        "INSERT INTO password_recovery_requests (user_id, status, requested_at) VALUES (?, 'pending', NOW())",
        [$targetId]
    );
    $rejectId = (int)$pdo->lastInsertId();
    $requestIds[] = $rejectId;
    $rejected = ak_api_admin_recovery_action(['request_id' => $rejectId, 'action' => 'reject'], $reviewerId);
    $assert(($rejected['message'] ?? '') !== '', 'Rejection did not return a result message.');
    $rejectRow = dbFetchOne('SELECT status, reviewed_by FROM password_recovery_requests WHERE id = ?', [$rejectId]);
    $assert($rejectRow !== null && $rejectRow['status'] === 'rejected' && (int)$rejectRow['reviewed_by'] === $reviewerId, 'Recovery request was not rejected.');

    echo 'PASS | mobile admin recovery service | queue, approve, temporary password hash, forced change, expiry, duplicate guard, reject, rollback=PASS' . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$restoredUser = dbFetchOne('SELECT password_hash, password_change_required FROM users WHERE id = ?', [$targetId]);
$assert($restoredUser !== null && (string)$restoredUser['password_hash'] === $originalHash, 'Rollback did not restore the target password hash.');
$assert((int)$restoredUser['password_change_required'] === $originalChangeRequired, 'Rollback did not restore the target password-change flag.');
foreach ($requestIds as $requestId) {
    $assert(dbFetchOne('SELECT id FROM password_recovery_requests WHERE id = ?', [$requestId]) === null, 'Rollback left a test recovery request behind.');
}
