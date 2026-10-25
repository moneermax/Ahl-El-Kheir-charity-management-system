<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)$user['role_code'], ['admin', 'sudo', 'super_admin', 'hr_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'User directory access is limited to system administrators and HR managers.',
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
    $where = ' WHERE u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR r.name_ar LIKE ? OR r.name_en LIKE ?';
    $term = '%' . $query . '%';
    $params = [$term, $term, $term, $term, $term];
}
$count = dbFetchOne(
    'SELECT COUNT(*) AS total FROM users u JOIN roles r ON r.id = u.role_id' . $where,
    $params
);
$total = (int)($count['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = dbFetchAll(
    'SELECT u.id, u.username, u.full_name, u.email, u.phone, u.is_active,
            u.last_login_at, u.gender, r.code AS role_code,
            r.name_ar AS role_name, r.name_en AS role_en,
            d.name_ar AS dept_name, d.name_en AS dept_en,
            m.full_name AS manager_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN departments d ON d.id = u.department_id
     LEFT JOIN users m ON m.id = u.manager_id' .
    $where .
    ' ORDER BY u.id LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
    $params
);

$items = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'username' => (string)($row['username'] ?? ''),
    'full_name' => (string)($row['full_name'] ?? ''),
    'email' => (string)($row['email'] ?? ''),
    'phone' => (string)($row['phone'] ?? ''),
    'is_active' => (int)($row['is_active'] ?? 0) === 1,
    'last_login_at' => (string)($row['last_login_at'] ?? ''),
    'gender' => (string)($row['gender'] ?? ''),
    'role_code' => (string)($row['role_code'] ?? ''),
    'role_name' => (string)($row['role_name'] ?? ''),
    'role_en' => (string)($row['role_en'] ?? ''),
    'dept_name' => (string)($row['dept_name'] ?? ''),
    'dept_en' => (string)($row['dept_en'] ?? ''),
    'manager_name' => (string)($row['manager_name'] ?? ''),
], $rows);

ak_api_json(200, ['ok' => true, 'data' => [
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'pages' => $pages,
]]);
