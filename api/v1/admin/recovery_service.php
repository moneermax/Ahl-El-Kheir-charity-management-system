<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

final class AkAdminRecoveryException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message
    ) {
        parent::__construct($message);
    }
}

function ak_api_admin_recovery_list(): array
{
    $rows = dbFetchAll(
        "SELECT r.id, r.user_id, r.status, r.requested_at, r.reviewed_at, r.expires_at,
                u.username, u.full_name, rev.full_name AS reviewer_name
         FROM password_recovery_requests r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN users rev ON rev.id = r.reviewed_by
         ORDER BY (r.status = 'pending') DESC, r.id DESC
         LIMIT 50"
    );

    return ['items' => array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'username' => (string)($row['username'] ?? ''),
        'full_name' => (string)($row['full_name'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'requested_at' => (string)($row['requested_at'] ?? ''),
        'reviewed_at' => (string)($row['reviewed_at'] ?? ''),
        'expires_at' => (string)($row['expires_at'] ?? ''),
        'reviewer_name' => (string)($row['reviewer_name'] ?? ''),
    ], $rows)];
}

function ak_api_admin_recovery_action(array $input, int $reviewerId): array
{
    $requestId = (int)($input['request_id'] ?? 0);
    $action = trim((string)($input['action'] ?? ''));
    if ($requestId <= 0) {
        throw new AkAdminRecoveryException(422, 'invalid_request', 'A valid recovery request ID is required.');
    }
    if (!in_array($action, ['approve', 'reject'], true)) {
        throw new AkAdminRecoveryException(422, 'invalid_action', 'Choose approve or reject.');
    }

    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    $temporaryPassword = null;
    $fullName = '';
    $userId = 0;
    try {
        $request = dbFetchOne(
            'SELECT r.id, r.user_id, r.status, u.full_name
             FROM password_recovery_requests r
             JOIN users u ON u.id = r.user_id
             WHERE r.id = ?
             FOR UPDATE',
            [$requestId]
        );
        if (!$request) {
            throw new AkAdminRecoveryException(404, 'request_not_found', 'Recovery request was not found.');
        }
        if ((string)$request['status'] !== 'pending') {
            throw new AkAdminRecoveryException(409, 'request_already_processed', 'This recovery request has already been processed.');
        }

        $userId = (int)$request['user_id'];
        $fullName = (string)($request['full_name'] ?? '');
        if ($action === 'approve') {
            $temporaryPassword = bin2hex(random_bytes(6));
            $temporaryHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            dbExecute(
                'UPDATE users SET password_hash = ?, password_change_required = 1 WHERE id = ?',
                [$temporaryHash, $userId]
            );
            $updated = dbExecute(
                "UPDATE password_recovery_requests
                 SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(),
                     expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                 WHERE id = ? AND status = 'pending'",
                [$reviewerId, $requestId]
            );
            if ($updated !== 1) {
                throw new AkAdminRecoveryException(409, 'request_already_processed', 'This recovery request has already been processed.');
            }
        } else {
            $updated = dbExecute(
                "UPDATE password_recovery_requests
                 SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ? AND status = 'pending'",
                [$reviewerId, $requestId]
            );
            if ($updated !== 1) {
                throw new AkAdminRecoveryException(409, 'request_already_processed', 'This recovery request has already been processed.');
            }
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // Notifications and audit are best-effort after the account/request state is committed.
    if ($action === 'approve') {
        try {
            dbExecute(
                "INSERT INTO notifications (recipient_user_id, type, title, body, link, is_read)
                 VALUES (?, 'recovery', ?, ?, ?, 0)",
                [
                    $userId,
                    'تمت الموافقة على طلب استعادة كلمة المرور',
                    'تمت الموافقة على طلبك. تواصل مع مسؤول الموارد البشرية أو مسؤول النظام لاستلام كلمة المرور المؤقتة.',
                    APP_URL . 'modules/users/change_password.php?forced=1',
                ]
            );
        } catch (Throwable $e) {
            error_log('Mobile recovery approval notification failed: ' . $e->getMessage());
        }
        try {
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'RECOVERY_APPROVED', 'users', ?, NULL, ?, ?, ?)",
                [
                    $reviewerId,
                    $userId,
                    json_encode(['recovery_request_id' => $requestId, 'password_change_required' => true], JSON_UNESCAPED_UNICODE),
                    (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ]
            );
        } catch (Throwable $e) {
            error_log('Mobile recovery approval audit failed: ' . $e->getMessage());
        }
        return [
            'message' => 'Recovery approved. Give the temporary password to the user securely.',
            'temporary_password' => $temporaryPassword,
            'full_name' => $fullName,
        ];
    }

    try {
        dbExecute(
            "INSERT INTO notifications (recipient_user_id, type, title, body, link, is_read)
             VALUES (?, 'recovery', ?, ?, ?, 0)",
            [
                $userId,
                'تم رفض طلب استعادة كلمة المرور',
                'تم رفض طلبك لاستعادة كلمة المرور. يرجى التواصل مع الموارد البشرية أو مسؤول النظام إذا كنت بحاجة إلى مساعدة.',
                APP_URL . 'modules/users/password_recovery_request.php',
            ]
        );
    } catch (Throwable $e) {
        error_log('Mobile recovery rejection notification failed: ' . $e->getMessage());
    }

    return ['message' => 'Recovery request rejected.'];
}
