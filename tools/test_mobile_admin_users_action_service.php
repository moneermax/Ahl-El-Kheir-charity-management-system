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
    'SELECT u.id, u.is_active, u.password_hash, u.password_change_required, u.full_name, u.email, u.phone, u.gender, u.department_id, u.manager_id, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id <> ? ORDER BY u.id LIMIT 1',
    [$actorId]
);
$assert($target !== null, 'A second user account is required.');
$targetId = (int)$target['id'];
$originalActive = (int)$target['is_active'];
$originalHash = (string)$target['password_hash'];
$originalChangeRequired = (int)$target['password_change_required'];
$originalName = (string)$target['full_name'];
$originalEmail = $target['email'];
$originalPhone = $target['phone'];
$originalGender = $target['gender'];
$originalDepartmentId = $target['department_id'];
$originalManagerId = $target['manager_id'];
$originalRoleCode = (string)$target['role_code'];

$pdo->beginTransaction();
try {
    $short = $expectError('password_too_short', static fn() => ak_api_admin_user_action([
        'action' => 'reset_password',
        'user_id' => $targetId,
        'new_password' => '12345',
    ], $actorId));
    $assert($short->httpStatus === 422, 'Short password must return HTTP 422.');

    $updatedName = 'Mobile updated ' . bin2hex(random_bytes(4));
    $updatedIdentity = ak_api_admin_user_action([
        'action' => 'update',
        'user_id' => $targetId,
        'full_name' => $updatedName,
        'email' => 'mobile-test@example.test',
        'phone' => '555-0199',
        'role_code' => $originalRoleCode,
        'department_id' => $originalDepartmentId,
        'manager_id' => $originalManagerId,
        'gender' => $originalGender,
        'is_active' => $originalActive === 1,
    ], $actorId);
    $afterIdentity = dbFetchOne('SELECT full_name, email, phone FROM users WHERE id = ?', [$targetId]);
    $assert($afterIdentity !== null && $afterIdentity['full_name'] === $updatedName, 'User identity update did not persist.');
    $assert(($afterIdentity['email'] ?? null) === 'mobile-test@example.test' && ($afterIdentity['phone'] ?? null) === '555-0199', 'User contact update did not persist.');
    $assert(stripos((string)($updatedIdentity['message'] ?? ''), 'updated') !== false, 'Identity update response was missing.');

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

    echo 'PASS | mobile admin user actions | identity update, toggle, password reset hash, forced change, short-password guard, self-action guard, rollback=PASS' . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$restored = dbFetchOne('SELECT is_active, password_hash, password_change_required, full_name, email, phone, gender, department_id, manager_id FROM users WHERE id = ?', [$targetId]);
$assert($restored !== null && (int)$restored['is_active'] === $originalActive, 'Rollback did not restore account activation.');
$assert((string)$restored['password_hash'] === $originalHash, 'Rollback did not restore password hash.');
$assert((int)$restored['password_change_required'] === $originalChangeRequired, 'Rollback did not restore password-change flag.');
$assert((string)$restored['full_name'] === $originalName, 'Rollback did not restore full name.');
$assert(($restored['email'] ?? null) === $originalEmail, 'Rollback did not restore email.');
$assert(($restored['phone'] ?? null) === $originalPhone, 'Rollback did not restore phone.');
$assert(($restored['gender'] ?? null) === $originalGender, 'Rollback did not restore gender.');
$assert(($restored['department_id'] === null ? null : (int)$restored['department_id']) === ($originalDepartmentId === null ? null : (int)$originalDepartmentId), 'Rollback did not restore department.');
$assert(($restored['manager_id'] === null ? null : (int)$restored['manager_id']) === ($originalManagerId === null ? null : (int)$originalManagerId), 'Rollback did not restore manager.');

