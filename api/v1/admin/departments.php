<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'admin') {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Department management is limited to system administrators.',
    ]]);
}

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

ak_api_json(200, ['ok' => true, 'data' => ['items' => $items, 'total' => count($items)]]);
