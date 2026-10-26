<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$input = ak_api_read_json();
$username = trim((string)($input['username'] ?? ''));
$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

if ($username === '' || $currentPassword === '' || $newPassword === '' || $confirmPassword === ''
    || strlen($username) > 190 || strlen($currentPassword) > 4096
    || strlen($newPassword) > 4096 || strlen($confirmPassword) > 4096) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_password_change_input',
        'message' => 'Enter the username, current password, new password, and confirmation.',
    ]]);
}
if (mb_strlen($newPassword) < 6) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'password_too_short',
        'message' => 'The new password must contain at least 6 characters.',
    ]]);
}
if ($newPassword !== $confirmPassword) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'password_confirmation_mismatch',
        'message' => 'The new password and confirmation do not match.',
    ]]);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT id, password_hash, is_active, password_change_required
         FROM users WHERE username = ? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$username]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record || (int)$record['is_active'] !== 1
        || !password_verify($currentPassword, (string)$record['password_hash'])) {
        $pdo->rollBack();
        ak_api_json(401, ['ok' => false, 'error' => [
            'code' => 'invalid_credentials',
            'message' => 'The username or current password is incorrect, or the account is unavailable.',
        ]]);
    }
    if ((int)$record['password_change_required'] !== 1) {
        $pdo->rollBack();
        ak_api_json(409, ['ok' => false, 'error' => [
            'code' => 'password_change_not_required',
            'message' => 'This account does not require a forced password change.',
        ]]);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($newHash) || $newHash === '') {
        throw new RuntimeException('Unable to hash the new password.');
    }
    $pdo->prepare('UPDATE users SET password_hash = ?, password_change_required = 0 WHERE id = ?')
        ->execute([$newHash, (int)$record['id']]);

    try {
        $pdo->prepare("UPDATE password_recovery_requests SET status = 'completed' WHERE user_id = ? AND status = 'approved'")
            ->execute([(int)$record['id']]);
    } catch (Throwable $recoveryError) {
    }
    try {
        $pdo->prepare(
            "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'CHANGE_PASSWORD', 'users', ?, NULL, ?, ?, ?)"
        )->execute([
            (int)$record['id'],
            (int)$record['id'],
            json_encode(['password_changed' => true, 'forced' => true, 'source' => 'mobile'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $auditError) {
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

ak_api_json(200, ['ok' => true, 'data' => [
    'password_changed' => true,
    'message' => 'Password changed successfully. Sign in with the new password.',
]]);
