<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

final class AkAdminDepartmentException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message,
        public readonly array $extra = []
    ) {
        parent::__construct($message);
    }
}

function ak_api_admin_list_departments(): array
{
    $rows = dbFetchAll(
        'SELECT d.id, d.name_ar, d.name_en, d.description, d.created_at,
                (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS users_count,
                (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id) AS employees_count
         FROM departments d
         ORDER BY d.id'
    );
    $items = array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'name_ar' => (string)($row['name_ar'] ?? ''),
        'name_en' => (string)($row['name_en'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'users_count' => (int)($row['users_count'] ?? 0),
        'employees_count' => (int)($row['employees_count'] ?? 0),
    ], $rows);

    return ['items' => $items, 'total' => count($items)];
}

function ak_api_admin_department_action(array $input): array
{
    $action = trim((string)($input['action'] ?? ''));

    if ($action === 'create' || $action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $nameAr = trim((string)($input['name_ar'] ?? ''));
        $nameEn = trim((string)($input['name_en'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));

        if ($action === 'update' && $id <= 0) {
            throw new AkAdminDepartmentException(422, 'invalid_department', 'A valid department ID is required.');
        }
        if ($nameAr === '' || $nameEn === '') {
            throw new AkAdminDepartmentException(422, 'names_required', 'Arabic and English department names are required.');
        }
        if (mb_strlen($nameAr, 'UTF-8') > 100 || mb_strlen($nameEn, 'UTF-8') > 100) {
            throw new AkAdminDepartmentException(422, 'name_too_long', 'Department names must not exceed 100 characters.');
        }

        $duplicate = $action === 'create'
            ? dbFetchOne('SELECT id FROM departments WHERE name_ar = ? OR name_en = ?', [$nameAr, $nameEn])
            : dbFetchOne('SELECT id FROM departments WHERE (name_ar = ? OR name_en = ?) AND id <> ?', [$nameAr, $nameEn, $id]);
        if ($duplicate) {
            throw new AkAdminDepartmentException(409, 'duplicate_department', 'A department with that Arabic or English name already exists.');
        }

        if ($action === 'create') {
            dbExecute(
                'INSERT INTO departments (name_ar, name_en, description) VALUES (?, ?, ?)',
                [$nameAr, $nameEn, $description !== '' ? $description : null]
            );
            return ['http_status' => 201, 'message' => 'Department created.'];
        }

        $existing = dbFetchOne('SELECT id FROM departments WHERE id = ?', [$id]);
        if (!$existing) {
            throw new AkAdminDepartmentException(404, 'department_not_found', 'Department not found.');
        }
        dbExecute(
            'UPDATE departments SET name_ar = ?, name_en = ?, description = ? WHERE id = ?',
            [$nameAr, $nameEn, $description !== '' ? $description : null, $id]
        );
        return ['http_status' => 200, 'message' => 'Department updated.'];
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            throw new AkAdminDepartmentException(422, 'invalid_department', 'A valid department ID is required.');
        }
        $existing = dbFetchOne('SELECT id FROM departments WHERE id = ?', [$id]);
        if (!$existing) {
            throw new AkAdminDepartmentException(404, 'department_not_found', 'Department not found.');
        }
        $usage = dbFetchOne(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE department_id = ?) AS users_count,
                (SELECT COUNT(*) FROM employees WHERE department_id = ?) AS employees_count',
            [$id, $id]
        );
        $usersCount = (int)($usage['users_count'] ?? 0);
        $employeesCount = (int)($usage['employees_count'] ?? 0);
        if ($usersCount > 0 || $employeesCount > 0) {
            throw new AkAdminDepartmentException(
                409,
                'department_in_use',
                'This department cannot be deleted while users or employees are assigned to it.',
                ['users_count' => $usersCount, 'employees_count' => $employeesCount]
            );
        }
        dbExecute('DELETE FROM departments WHERE id = ?', [$id]);
        return ['http_status' => 200, 'message' => 'Department deleted.'];
    }

    throw new AkAdminDepartmentException(422, 'invalid_action', 'Unsupported department action.');
}
