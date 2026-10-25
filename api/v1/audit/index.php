<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
// This screen's existing web guard checks the persisted role exactly. Do not use
// the normalized role here: sudo/super_admin normalize to admin for routing, but
// the web audit page itself does not grant them access.
if (!in_array((string)$user['raw_role_code'], ['admin', 'general_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Audit log access is limited to system administrators and the General Manager.']]);
}
$query = trim((string)($_GET['q'] ?? ''));
if (strlen($query) > 120) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'query_too_long', 'message' => 'Search query must not exceed 120 bytes.']]);
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$where = '';
$params = [];
if ($query !== '') {
    $where = ' WHERE (a.action LIKE ? OR a.entity_type LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)';
    $term = '%' . $query . '%';
    $params = [$term, $term, $term, $term];
}
$count = dbFetchOne('SELECT COUNT(*) AS total FROM audit_log a LEFT JOIN users u ON u.id = a.user_id' . $where, $params);
$total = (int)($count['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$rows = dbFetchAll(
    'SELECT a.id, a.created_at, a.action, a.entity_type, a.entity_id, a.user_id, u.username, u.full_name AS user_name
     FROM audit_log a LEFT JOIN users u ON u.id = a.user_id' . $where .
    ' ORDER BY a.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
    $params
);
$items = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'created_at' => (string)($row['created_at'] ?? ''),
    'action' => (string)($row['action'] ?? ''),
    'entity_type' => (string)($row['entity_type'] ?? ''),
    'entity_id' => isset($row['entity_id']) ? (int)$row['entity_id'] : null,
    'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
    'username' => (string)($row['username'] ?? ''),
    'user_name' => (string)($row['user_name'] ?? ''),
], $rows);
ak_api_json(200, ['ok' => true, 'data' => ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage]]);
