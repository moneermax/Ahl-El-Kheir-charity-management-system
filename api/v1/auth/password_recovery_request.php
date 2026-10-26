<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$input = ak_api_read_json();
$identity = trim((string)($input['identity'] ?? ''));
if ($identity === '' || mb_strlen($identity) > 190) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_recovery_identity',
        'message' => 'Enter a username or registered email address.',
    ]]);
}

$pdo = db();
$handlers = [];
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT id, full_name FROM users
         WHERE is_active = 1 AND (username = ? OR email = ?)
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $pendingStmt = $pdo->prepare(
            "SELECT id FROM password_recovery_requests
             WHERE user_id = ? AND status = 'pending'
             ORDER BY id DESC LIMIT 1"
        );
        $pendingStmt->execute([(int)$user['id']]);
        $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pending) {
            $pdo->prepare(
                "INSERT INTO password_recovery_requests (user_id, status, requested_at)
                 VALUES (?, 'pending', NOW())"
            )->execute([(int)$user['id']]);

            $handlers = $pdo->query(
                "SELECT u.id FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.is_active = 1 AND r.code IN ('admin', 'hr_manager')"
            )->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Mobile password recovery request failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'recovery_request_failed',
        'message' => 'The request could not be submitted right now. Please try again later.',
    ]]);
}

if ($user && !$pending) {
    foreach ($handlers as $handlerId) {
        try {
            $notify = $pdo->prepare(
                "INSERT INTO notifications (recipient_user_id, type, title, body, link, is_read)
                 VALUES (?, 'recovery', ?, ?, ?, 0)"
            );
            $notify->execute([
                (int)$handlerId,
                'طلب استعادة كلمة مرور جديد',
                'يوجد طلب جديد لاستعادة كلمة مرور للمستخدم: ' . (string)$user['full_name'],
                APP_URL . 'modules/users/recovery.php',
            ]);
        } catch (Throwable $notificationError) {
            // Notification delivery is best-effort; the recovery request remains saved.
        }
    }
}

ak_api_json(200, ['ok' => true, 'data' => [
    'request_received' => true,
    'message' => 'The request was received. If the account exists and is active, HR or a system administrator will review it and contact you.',
]]);
