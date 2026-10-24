<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];

if (!in_array($role, ['admin', 'vice_general_manager'], true)) {
    ak_api_json(403, [
        'ok' => false,
        'error' => [
            'code' => 'forbidden',
            'message' => 'Supervisor management is not allowed for this account.',
        ],
    ]);
}

$query = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($query, 'UTF-8') > 100) {
    ak_api_json(422, [
        'ok' => false,
        'error' => ['code' => 'invalid_search', 'message' => 'Search text is too long.'],
    ]);
}

$showArchivedRaw = (string)($_GET['show_archived'] ?? '0');
if (!in_array($showArchivedRaw, ['0', '1'], true)) {
    ak_api_json(422, [
        'ok' => false,
        'error' => ['code' => 'invalid_archive_filter', 'message' => 'Invalid archive filter.'],
    ]);
}
$showArchived = $showArchivedRaw === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$where = ["r.code = 'supervisor'"];
$params = [];
if (!$showArchived) {
    $where[] = "COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) NOT IN ('departed', 'archived')";
}
if ($query !== '') {
    $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $query . '%';
    array_push($params, $like, $like, $like);
}

$from = " FROM users u JOIN roles r ON r.id = u.role_id WHERE " . implode(' AND ', $where);
$count = dbFetchOne('SELECT COUNT(*) AS total' . $from, $params);
$total = (int)($count['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = dbFetchAll(
    "SELECT u.id, u.username, u.full_name, u.phone, u.email, u.is_active, u.last_login_at,
            COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status,
            (SELECT GROUP_CONCAT(
                CONCAT(l.code, CASE sl.gender WHEN 'male' THEN '(ذ)' WHEN 'female' THEN '(إ)' ELSE '' END)
                ORDER BY l.sort_order SEPARATOR '، '
             )
             FROM supervisor_letters sl
             JOIN letters l ON l.id = sl.letter_id
             WHERE sl.supervisor_id = u.id) AS letters
     " . $from . "
     ORDER BY u.full_name, u.id
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset,
    $params
);

$items = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'username' => (string)$row['username'],
    'full_name' => (string)$row['full_name'],
    'phone' => (string)($row['phone'] ?? ''),
    'email' => (string)($row['email'] ?? ''),
    'is_active' => (int)$row['is_active'] === 1,
    'supervisor_status' => (string)$row['supervisor_status'],
    'letters' => (string)($row['letters'] ?? ''),
    'last_login_at' => (string)($row['last_login_at'] ?? ''),
], $rows);

ak_api_json(200, [
    'ok' => true,
    'data' => [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'show_archived' => $showArchived,
    ],
]);
