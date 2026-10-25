<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/admin/users_action_service.php';

$pdo = db();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectError = static function (string $code, callable $callback) use ($assert): AkAdminUserActionException {
    try {
        $callback();
    } catch (AkAdminUserActionException $e) {
        $assert($e->errorCode === $code, 'Expected error ' . $code . ', got ' . $e->errorCode . '.');
        return $e;
    }
    throw new RuntimeException('Expected user action error: ' . $code);
};

$actor = dbFetchOne(
    "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.code IN ('admin', 'sudo', 'super_admin', 'hr_manager') ORDER BY u.id LIMIT 1"
);
$assert($actor !== null, 'An administrator or HR Manager account is required.');
$actorId = (int)$actor['id'];
$target = dbFetchOne(
    'SELECT id, is_active, password_hash, password_change_required FROM users WHERE id <> ? ORDER BY id LIMIT 1',
    [$actorId]
);
$assert($target !== null, 'A second user account is required.');
$targetId = (int)$target['id'];
$originalActive = (int)$target['is_active'];
$originalHash = (string)$target['password_hash'];
$originalChangeRequired = (int)$target['password_change_required'];

$pdo->beginTransaction();
try {
    $short = $expectError('password_too_short', static fn() => ak_api_admin_user_action([
        'action' => 'reset_password',
        'user_id' => $targetId,
        'new_password' => '12345',
    ], $actorId));
    $assert($short->httpStatus === 422, 'Short password must return HTTP 422.');

    $toggle = ak_api_admin_user_action(['action' => 'toggle', 'user_id' => $targetId], $actorId);
    $afterToggle = dbFetchOne('SELECT is_active FROM users WHERE id = ?', [$targetId]);
    $assert($afterToggle !== null && (int)$afterToggle['is_active'] !== $originalActive, 'Toggle did not change account activation.');
    $assert(($toggle['is_active'] ?? null) === ((int)$afterToggle['is_active'] === 1), 'Toggle response did not match persisted state.');

    $password = 'NativeTestPassword2026';
    $reset = ak_api_admin_user_action([
        'action' => 'reset_password',
        'user_id' => $targetId,
        'new_password' => $password,
    ], $actorId);
    $afterReset = dbFetchOne('SELECT password_hash, password_change_required FROM users WHERE id = ?', [$targetId]);
    $assert($afterReset !== null && password_verify($password, (string)$afterReset['password_hash']), 'Password reset hash does not verify.');
    $assert((int)$afterReset['password_change_required'] === 1, 'Password reset did not require a password change.');
    $assert(stripos((string)($reset['message'] ?? ''), 'must change') !== false, 'Password reset response did not explain forced change.');

    $expectError('self_action_forbidden', static fn() => ak_api_admin_user_action([
        'action' => 'toggle',
        'user_id' => $actorId,
    ], $actorId));

    echo 'PASS | mobile admin user actions | toggle, password reset hash, forced change, short-password guard, self-action guard, rollback=PASS' . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$restored = dbFetchOne('SELECT is_active, password_hash, password_change_required FROM users WHERE id = ?', [$targetId]);
$assert($restored !== null && (int)$restored['is_active'] === $originalActive, 'Rollback did not restore account activation.');
$assert((string)$restored['password_hash'] === $originalHash, 'Rollback did not restore password hash.');
$assert((int)$restored['password_change_required'] === $originalChangeRequired, 'Rollback did not restore password-change flag.');
