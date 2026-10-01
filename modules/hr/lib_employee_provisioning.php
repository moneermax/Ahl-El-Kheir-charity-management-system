<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_employment.php';

/**
 * Provision the employee profile that belongs to a newly-created user account.
 *
 * User accounts and employee profiles represent the same person. Personal
 * contact fields may be empty at account creation and can be completed later.
 * Required HR columns receive safe system defaults until HR completes the record.
 */
function hrProvisionEmployeeForUser(PDO $pdo, int $userId, array $data, int $createdBy): int
{
    if ($userId <= 0) {
        throw new InvalidArgumentException('حساب المستخدم غير صالح لإنشاء ملف الموظف.');
    }

    $existing = dbFetchOne(
        "SELECT id FROM employees WHERE user_id = ? LIMIT 1",
        [$userId]
    );
    if ($existing) {
        return (int)$existing['id'];
    }

    $fullName = trim((string)($data['full_name'] ?? ''));
    if ($fullName === '') {
        throw new InvalidArgumentException('اسم الموظف مطلوب لإنشاء ملف الموظف.');
    }

    $departmentId = array_key_exists('department_id', $data)
        ? (!empty($data['department_id']) ? (int)$data['department_id'] : null)
        : null;
    $email = !empty($data['email']) ? trim((string)$data['email']) : null;
    $phone = !empty($data['phone']) ? trim((string)$data['phone']) : null;
    $gender = in_array(($data['gender'] ?? null), ['male', 'female'], true)
        ? $data['gender']
        : null;

    // Employee codes are system-generated and never reused.
    $lastCodeRow = dbFetchOne(
        "SELECT employee_code
         FROM employees
         WHERE employee_code REGEXP '^EMP-[0-9]+$'
         ORDER BY CAST(SUBSTRING(employee_code, 5) AS UNSIGNED) DESC
         LIMIT 1"
    );

    $nextNumber = 1;
    if ($lastCodeRow && preg_match('/^EMP-(\d+)$/', (string)$lastCodeRow['employee_code'], $m)) {
        $nextNumber = (int)$m[1] + 1;
    }

    do {
        $employeeCode = 'EMP-' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
        $exists = dbFetchOne(
            'SELECT id FROM employees WHERE employee_code = ? LIMIT 1',
            [$employeeCode]
        );
        $nextNumber++;
    } while ($exists);

    /*
     * These are only technical defaults required by the current employees
     * schema. HR can complete/correct them later. Personal contact fields
     * intentionally remain nullable.
     */
    $hireDate = date('Y-m-d');
    $position = 'موظف';
    $employmentType = 'full_time';
    $workMode = 'onsite';
    $basicSalary = 0.00;

    $pdo->prepare(
        "INSERT INTO employees
         (user_id, full_name, gender, phone, email, department_id,
          employee_code, hire_date, position, employment_type, work_mode,
          basic_salary, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)"
    )->execute([
        $userId,
        $fullName,
        $gender,
        $phone,
        $email,
        $departmentId,
        $employeeCode,
        $hireDate,
        $position,
        $employmentType,
        $workMode,
        $basicSalary,
        $createdBy,
    ]);

    $employeeId = (int)$pdo->lastInsertId();

    hrSyncEmployeeSalaryHistory(
        $employeeId,
        $basicSalary,
        $hireDate,
        'initial'
    );

    if (function_exists('hrInitializeEmploymentState')) {
        hrInitializeEmploymentState(
            $employeeId,
            'active',
            $createdBy,
            null,
            'Initial employee profile provisioned with user account'
        );
    }

    return $employeeId;
}

/**
 * Keep the canonical employee profile synchronized with editable user
 * identity fields when an account is updated by an authorized manager.
 */
function hrSyncEmployeeIdentityFromUser(PDO $pdo, int $userId, array $data): void
{
    if ($userId <= 0) {
        return;
    }

    $employee = dbFetchOne(
        "SELECT id FROM employees WHERE user_id = ? LIMIT 1",
        [$userId]
    );
    if (!$employee) {
        return;
    }

    $fullName = trim((string)($data['full_name'] ?? ''));
    if ($fullName === '') {
        return;
    }

    $email = !empty($data['email']) ? trim((string)$data['email']) : null;
    $phone = !empty($data['phone']) ? trim((string)$data['phone']) : null;
    $gender = in_array(($data['gender'] ?? null), ['male', 'female'], true)
        ? $data['gender']
        : null;
    $birthDate = !empty($data['birth_date']) ? (string)$data['birth_date'] : null;
    $address = !empty($data['address']) ? trim((string)$data['address']) : null;

    /*
     * Department is administrative assignment data. Preserve the existing
     * employee department unless the caller explicitly supplies it.
     * Self-service profile updates do not own this field and therefore omit it.
     */
    $fields = [
        'full_name = ?',
        'email = ?',
        'phone = ?',
        'gender = ?',
        'birth_date = ?',
        'address = ?',
    ];
    $params = [
        $fullName,
        $email,
        $phone,
        $gender,
        $birthDate,
        $address,
    ];

    if (array_key_exists('department_id', $data)) {
        $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        $fields[] = 'department_id = ?';
        $params[] = $departmentId;
    }

    $params[] = (int)$employee['id'];

    dbExecute(
        "UPDATE employees
         SET " . implode(', ', $fields) . "
         WHERE id = ?",
        $params
    );
}


/**
 * Create the canonical user + employee pair in one transaction.
 *
 * This is the single account-creation path used by both Admin and HR.
 * Authorization is handled by the calling page; the data/provisioning
 * behavior is intentionally identical.
 */
function hrCreateUserWithEmployee(PDO $pdo, array $data, int $createdBy): array
{
    $fullName = trim((string)($data['full_name'] ?? ''));
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $roleCode = trim((string)($data['role_code'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $phone = trim((string)($data['phone'] ?? ''));
    $gender = in_array(($data['gender'] ?? null), ['male', 'female'], true) ? $data['gender'] : null;
    $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
    $managerId = !empty($data['manager_id']) ? (int)$data['manager_id'] : null;

    if ($fullName === '') throw new InvalidArgumentException('الاسم الكامل مطلوب.');
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        throw new InvalidArgumentException('اسم المستخدم غير صالح.');
    }
    if (strlen($password) < 6) throw new InvalidArgumentException('كلمة المرور 6 أحرف على الأقل.');

    $role = dbFetchOne("SELECT id FROM roles WHERE code = ? LIMIT 1", [$roleCode]);
    if (!$role) throw new InvalidArgumentException('الدور المحدد غير صالح.');
    if (dbFetchOne("SELECT id FROM users WHERE username = ? LIMIT 1", [$username])) {
        throw new InvalidArgumentException('اسم المستخدم موجود.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users
             (role_id, username, password_hash, full_name, email, phone,
              is_active, password_change_required, created_by, department_id, manager_id, gender)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$role['id'],
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $fullName,
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
            $createdBy,
            $departmentId,
            $managerId,
            $gender,
        ]);

        $userId = (int)$pdo->lastInsertId();
        $employeeId = hrProvisionEmployeeForUser($pdo, $userId, [
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'gender' => $gender,
            'department_id' => $departmentId,
        ], $createdBy);

        $pdo->commit();

        return [
            'user_id' => $userId,
            'employee_id' => $employeeId,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
