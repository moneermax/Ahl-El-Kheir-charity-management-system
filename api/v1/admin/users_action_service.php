<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/functions.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_employee_provisioning.php';

final class AkAdminUserActionException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message
    ) {
        parent::__construct($message);
    }
}

function ak_api_admin_user_action(array $input, int $actorId): array
{
    $action = trim((string)($input['action'] ?? ''));
    if ($action === 'create') {
        try {
            $created = hrCreateUserWithEmployee(db(), [
                'full_name' => trim((string)($input['full_name'] ?? '')),
                'username' => trim((string)($input['username'] ?? '')),
                'role_code' => trim((string)($input['role_code'] ?? '')),
                'email' => trim((string)($input['email'] ?? '')),
                'phone' => trim((string)($input['phone'] ?? '')),
                'gender' => $input['gender'] ?? null,
                'department_id' => $input['department_id'] ?? null,
                'manager_id' => $input['manager_id'] ?? null,
                'hire_date' => trim((string)($input['hire_date'] ?? '')),
                'basic_salary' => $input['basic_salary'] ?? null,
            ], $actorId);
        } catch (InvalidArgumentException $e) {
            throw new AkAdminUserActionException(422, 'user_creation_failed', $e->getMessage());
        }
        return [
            'message' => 'User and linked employee profile created. Share the temporary password securely.',
            'user_id' => (int)$created['user_id'],
            'employee_id' => (int)$created['employee_id'],
            'temporary_password' => (string)$created['temporary_password'],
        ];
    }

    $id = (int)($input['user_id'] ?? 0);
    if ($id <= 0) {
        throw new AkAdminUserActionException(422, 'invalid_user', 'A valid user ID is required.');
    }
    if (!in_array($action, ['update', 'toggle', 'reset_password'], true)) {
        throw new AkAdminUserActionException(422, 'invalid_action', 'Unsupported user action.');
    }
    if ($action !== 'update' && $id === $actorId) {
        throw new AkAdminUserActionException(403, 'self_action_forbidden', 'You cannot disable or reset your own account here.');
    }

    if ($action === 'update') {
        $fullName = trim((string)($input['full_name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $roleCode = trim((string)($input['role_code'] ?? ''));
        $departmentId = !empty($input['department_id']) ? (int)$input['department_id'] : null;
        $managerId = !empty($input['manager_id']) ? (int)$input['manager_id'] : null;
        $gender = in_array(($input['gender'] ?? null), ['male', 'female'], true) ? $input['gender'] : null;
        $isActive = filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        if ($fullName === '') {
            throw new AkAdminUserActionException(422, 'full_name_required', 'Full name is required.');
        }
        $role = dbFetchOne('SELECT id FROM roles WHERE code = ? LIMIT 1', [$roleCode]);
        if (!$role) {
            throw new AkAdminUserActionException(422, 'invalid_role', 'The selected role is not valid.');
        }

        $pdo = db();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $existing = dbFetchOne('SELECT id FROM users WHERE id = ? FOR UPDATE', [$id]);
            if (!$existing) {
                throw new AkAdminUserActionException(404, 'user_not_found', 'User account was not found.');
            }
            dbExecute(
                'UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, department_id = ?, manager_id = ?, gender = ?, is_active = ?, updated_at = NOW() WHERE id = ?',
                [$fullName, $email !== '' ? $email : null, $phone !== '' ? $phone : null, (int)$role['id'], $departmentId, $managerId, $gender, $isActive, $id]
            );
            hrSyncEmployeeIdentityFromUser($pdo, $id, [
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'gender' => $gender,
                'department_id' => $departmentId,
            ]);
            if ($ownsTransaction) $pdo->commit();
            return ['message' => 'User account and linked employee identity updated.'];
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    $newPassword = null;
    if ($action === 'reset_password') {
        $newPassword = (string)($input['new_password'] ?? '');
        if (strlen($newPassword) < 6) {
            throw new AkAdminUserActionException(422, 'password_too_short', 'The new password must contain at least 6 characters.');
        }
    }

    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $user = dbFetchOne('SELECT id, is_active FROM users WHERE id = ? FOR UPDATE', [$id]);
        if (!$user) {
            throw new AkAdminUserActionException(404, 'user_not_found', 'User account was not found.');
        }

        if ($action === 'toggle') {
            $active = (int)$user['is_active'] === 1 ? 0 : 1;
            dbExecute('UPDATE users SET is_active = ? WHERE id = ?', [$active, $id]);
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return ['message' => 'User status updated.', 'is_active' => $active === 1];
        }

        dbExecute(
            'UPDATE users SET password_hash = ?, password_change_required = 1 WHERE id = ?',
            [password_hash((string)$newPassword, PASSWORD_DEFAULT), $id]
        );
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return ['message' => 'Password reset. The user must change it at next sign-in.'];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
