<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)$user['role_code'], ['hr_manager', 'hr_staff', 'admin'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Employee directory access is limited to HR and system administrators.',
    ]]);
}

$query = trim((string)($_GET['q'] ?? ''));
if (strlen($query) > 200) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'query_too_long',
        'message' => 'Search query must not exceed 200 bytes.',
    ]]);
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where = '';
$params = [];
if ($query !== '') {
    $where = ' WHERE e.full_name LIKE ? OR e.employee_code LIKE ? OR e.position LIKE ?';
    $term = '%' . $query . '%';
    $params = [$term, $term, $term];
}
$count = dbFetchOne('SELECT COUNT(*) AS total FROM employees e' . $where, $params);
$total = (int)($count['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = dbFetchAll(
    'SELECT e.id, e.employee_code, e.full_name, e.position, e.hire_date,
            e.employment_type, e.work_mode,
            d.name_ar AS department_name_ar, d.name_en AS department_name_en,
            s.code AS employment_state_code, s.name_ar AS employment_state_name,
            s.category AS employment_state_category
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     LEFT JOIN hr_employment_states s ON s.id = e.employment_state_id' .
    $where .
    ' ORDER BY e.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
    $params
);
$items = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'employee_code' => (string)($row['employee_code'] ?? ''),
    'full_name' => (string)($row['full_name'] ?? ''),
    'position' => (string)($row['position'] ?? ''),
    'hire_date' => (string)($row['hire_date'] ?? ''),
    'employment_type' => (string)($row['employment_type'] ?? ''),
    'work_mode' => (string)($row['work_mode'] ?? ''),
    'department_name_ar' => (string)($row['department_name_ar'] ?? ''),
    'department_name_en' => (string)($row['department_name_en'] ?? ''),
    'employment_state_code' => (string)($row['employment_state_code'] ?? ''),
    'employment_state_name' => (string)($row['employment_state_name'] ?? ''),
    'employment_state_category' => (string)($row['employment_state_category'] ?? ''),
], $rows);

ak_api_json(200, ['ok' => true, 'data' => [
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'pages' => $pages,
]]);
