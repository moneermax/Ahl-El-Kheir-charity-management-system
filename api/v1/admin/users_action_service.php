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
        } catch (Throwable $e) {
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
    if ($id === $actorId) {
        throw new AkAdminUserActionException(403, 'self_action_forbidden', 'You cannot disable or reset your own account here.');
    }
    if (!in_array($action, ['toggle', 'reset_password'], true)) {
        throw new AkAdminUserActionException(422, 'invalid_action', 'Unsupported user action.');
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
