<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$input = ak_api_read_json();

$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'password_fields_required',
        'message' => 'Enter the current password, new password, and confirmation.',
    ]]);
}
if (strlen($currentPassword) > 4096 || strlen($newPassword) > 4096 || strlen($confirmPassword) > 4096) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'password_input_too_long',
        'message' => 'Password input exceeds the allowed length.',
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
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$user['id']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        $pdo->rollBack();
        ak_api_json(404, ['ok' => false, 'error' => [
            'code' => 'account_unavailable',
            'message' => 'The account is unavailable.',
        ]]);
    }
    if (!password_verify($currentPassword, (string)$record['password_hash'])) {
        $pdo->rollBack();
        ak_api_json(422, ['ok' => false, 'error' => [
            'code' => 'current_password_invalid',
            'message' => 'The current password is incorrect.',
        ]]);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($newHash) || $newHash === '') {
        throw new RuntimeException('Unable to hash the new password.');
    }
    $update = $pdo->prepare('UPDATE users SET password_hash = ?, password_change_required = 0 WHERE id = ?');
    $update->execute([$newHash, (int)$user['id']]);

    // Preserve the existing web password-change side effect; recovery history
    // is supplementary and must not prevent a valid password change.
    try {
        $pdo->prepare("UPDATE password_recovery_requests SET status = 'completed' WHERE user_id = ? AND status = 'approved'")
            ->execute([(int)$user['id']]);
    } catch (Throwable $recoveryError) {
    }

    // Preserve the existing audit contract without exposing password material.
    try {
        $pdo->prepare(
            "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'CHANGE_PASSWORD', 'users', ?, NULL, ?, ?, ?)"
        )->execute([
            (int)$user['id'],
            (int)$user['id'],
            json_encode(['password_changed' => true, 'source' => 'mobile'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
    'message' => 'Password changed successfully.',
]]);
