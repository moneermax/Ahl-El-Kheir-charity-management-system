<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];
$allowedRoles = ['admin', 'vice_general_manager', 'general_manager', 'administration', 'staff', 'social_media'];
if (!in_array($role, $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Sponsor request access is not allowed.']]);
}

$query = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$allowedStatuses = ['new', 'contacted', 'converted', 'lost'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_status', 'message' => 'Invalid sponsor request status.']]);
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'r.status = ?';
    $params[] = $status;
}
if ($query !== '') {
    $where[] = '(r.sponsor_name LIKE ? OR r.phone LIKE ? OR r.source LIKE ? OR r.sponsor_name LIKE ?)';
    $like = '%' . $query . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)(dbFetchOne('SELECT COUNT(*) AS total FROM sponsor_requests r' . $whereSql, $params)['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$items = dbFetchAll(
    'SELECT r.id, r.sponsor_name, r.phone, r.gender, r.source, r.brought_by, r.brought_by_name, r.notes, r.status, r.created_at, r.created_sponsor_id, r.assigned_supervisor_id, u.full_name AS brought_by_user FROM sponsor_requests r LEFT JOIN users u ON u.id = r.brought_by' .
    $whereSql . ' ORDER BY r.created_at DESC, r.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);
ak_api_json(200, ['ok' => true, 'data' => [
    'items' => array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'sponsor_name' => (string)$row['sponsor_name'],
        'phone' => (string)($row['phone'] ?? ''),
        'gender' => (string)($row['gender'] ?? ''),
        'source' => (string)($row['source'] ?? ''),
        'brought_by_name' => (string)($row['brought_by_name'] ?? ''),
        'brought_by_user' => (string)($row['brought_by_user'] ?? ''),
        'notes' => (string)($row['notes'] ?? ''),
        'status' => (string)$row['status'],
        'created_at' => (string)($row['created_at'] ?? ''),
        'created_sponsor_id' => $row['created_sponsor_id'] === null ? null : (int)$row['created_sponsor_id'],
        'assigned_supervisor_id' => $row['assigned_supervisor_id'] === null ? null : (int)$row['assigned_supervisor_id'],
    ], $items),
    'total' => $total,
    'page' => $page,
    'pages' => $pages,
]]);
