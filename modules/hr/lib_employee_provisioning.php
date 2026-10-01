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

    $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
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
    if ($lastCodeRow && preg_match('/^EMP-(d+)$/', (string)$lastCodeRow['employee_code'], $m)) {
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

    $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
    $email = !empty($data['email']) ? trim((string)$data['email']) : null;
    $phone = !empty($data['phone']) ? trim((string)$data['phone']) : null;
    $gender = in_array(($data['gender'] ?? null), ['male', 'female'], true)
        ? $data['gender']
        : null;

    dbExecute(
        "UPDATE employees
         SET full_name = ?,
             email = ?,
             phone = ?,
             gender = ?,
             department_id = ?
         WHERE id = ?",
        [
            $fullName,
            $email,
            $phone,
            $gender,
            $departmentId,
            (int)$employee['id'],
        ]
    );
}
