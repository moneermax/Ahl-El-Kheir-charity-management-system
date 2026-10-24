<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)$user['role_code'], ['hr_manager', 'hr_staff', 'admin'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Employee details are limited to HR and system administrators.',
    ]]);
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_employee_id',
        'message' => 'A valid employee ID is required.',
    ]]);
}

$row = dbFetchOne(
    'SELECT e.id, e.employee_code, e.full_name, e.position, e.phone, e.email,
            e.hire_date, e.employment_type, e.work_mode,
            d.name_ar AS department_name_ar, d.name_en AS department_name_en,
            s.code AS employment_state_code, s.name_ar AS employment_state_name,
            s.name_en AS employment_state_name_en,
            s.category AS employment_state_category
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     LEFT JOIN hr_employment_states s ON s.id = e.employment_state_id
     WHERE e.id = ? LIMIT 1',
    [(int)$id]
);
if (!$row) {
    ak_api_json(404, ['ok' => false, 'error' => [
        'code' => 'employee_not_found',
        'message' => 'Employee not found.',
    ]]);
}

ak_api_json(200, ['ok' => true, 'data' => ['employee' => [
    'id' => (int)$row['id'],
    'employee_code' => (string)($row['employee_code'] ?? ''),
    'full_name' => (string)($row['full_name'] ?? ''),
    'position' => (string)($row['position'] ?? ''),
    'phone' => (string)($row['phone'] ?? ''),
    'email' => (string)($row['email'] ?? ''),
    'hire_date' => (string)($row['hire_date'] ?? ''),
    'employment_type' => (string)($row['employment_type'] ?? ''),
    'work_mode' => (string)($row['work_mode'] ?? ''),
    'department_name_ar' => (string)($row['department_name_ar'] ?? ''),
    'department_name_en' => (string)($row['department_name_en'] ?? ''),
    'employment_state_code' => (string)($row['employment_state_code'] ?? ''),
    'employment_state_name' => (string)($row['employment_state_name'] ?? ''),
    'employment_state_name_en' => (string)($row['employment_state_name_en'] ?? ''),
    'employment_state_category' => (string)($row['employment_state_category'] ?? ''),
]]]);
