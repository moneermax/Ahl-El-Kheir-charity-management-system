<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'admin') {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Department management is limited to system administrators.',
    ]]);
}

$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));

try {
    if ($action === 'create' || $action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $nameAr = trim((string)($input['name_ar'] ?? ''));
        $nameEn = trim((string)($input['name_en'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));

        if ($action === 'update' && $id <= 0) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_department', 'message' => 'A valid department ID is required.']]);
        }
        if ($nameAr === '' || $nameEn === '') {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'names_required', 'message' => 'Arabic and English department names are required.']]);
        }
        if (mb_strlen($nameAr, 'UTF-8') > 100 || mb_strlen($nameEn, 'UTF-8') > 100) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'name_too_long', 'message' => 'Department names must not exceed 100 characters.']]);
        }
        $duplicate = $action === 'create'
            ? dbFetchOne('SELECT id FROM departments WHERE name_ar = ? OR name_en = ?', [$nameAr, $nameEn])
            : dbFetchOne('SELECT id FROM departments WHERE (name_ar = ? OR name_en = ?) AND id <> ?', [$nameAr, $nameEn, $id]);
        if ($duplicate) {
            ak_api_json(409, ['ok' => false, 'error' => ['code' => 'duplicate_department', 'message' => 'A department with that Arabic or English name already exists.']]);
        }

        if ($action === 'create') {
            dbExecute(
                'INSERT INTO departments (name_ar, name_en, description) VALUES (?, ?, ?)',
                [$nameAr, $nameEn, $description !== '' ? $description : null]
            );
            ak_api_json(201, ['ok' => true, 'data' => ['message' => 'Department created.']]);
        }

        $existing = dbFetchOne('SELECT id FROM departments WHERE id = ?', [$id]);
        if (!$existing) {
            ak_api_json(404, ['ok' => false, 'error' => ['code' => 'department_not_found', 'message' => 'Department not found.']]);
        }
        dbExecute(
            'UPDATE departments SET name_ar = ?, name_en = ?, description = ? WHERE id = ?',
            [$nameAr, $nameEn, $description !== '' ? $description : null, $id]
        );
        ak_api_json(200, ['ok' => true, 'data' => ['message' => 'Department updated.']]);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_department', 'message' => 'A valid department ID is required.']]);
        }
        $usage = dbFetchOne(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE department_id = ?) AS users_count,
                (SELECT COUNT(*) FROM employees WHERE department_id = ?) AS employees_count',
            [$id, $id]
        );
        if (!$usage || ((int)($usage['users_count'] ?? 0) === 0 && (int)($usage['employees_count'] ?? 0) === 0)) {
            $existing = dbFetchOne('SELECT id FROM departments WHERE id = ?', [$id]);
            if (!$existing) {
                ak_api_json(404, ['ok' => false, 'error' => ['code' => 'department_not_found', 'message' => 'Department not found.']]);
            }
            dbExecute('DELETE FROM departments WHERE id = ?', [$id]);
            ak_api_json(200, ['ok' => true, 'data' => ['message' => 'Department deleted.']]);
        }
        ak_api_json(409, ['ok' => false, 'error' => [
            'code' => 'department_in_use',
            'message' => 'This department cannot be deleted while users or employees are assigned to it.',
            'users_count' => (int)($usage['users_count'] ?? 0),
            'employees_count' => (int)($usage['employees_count'] ?? 0),
        ]]);
    }

    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Unsupported department action.']]);
} catch (Throwable $e) {
    error_log('Mobile department action failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'department_action_failed', 'message' => 'The department operation could not be completed.']]);
}
