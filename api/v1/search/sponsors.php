<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$rawRole = (string)($user['raw_role_code'] ?? $user['role_code'] ?? '');

require_once dirname(__DIR__, 3) . '/config/search_permissions.php';
if (!ak_search_can_type('sponsors', $rawRole)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'This role cannot search sponsors.']]);
}

// This search-only route fills a web-search gap for roles that may open sponsor
// details but are intentionally excluded from the general sponsor directory.
$role = ak_api_normalize_role_code($rawRole);
if (!in_array($role, ['financial_manager', 'accountant'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'This sponsor search route is limited to Finance Manager and Accountant roles.']]);
}

$query = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($query, 'UTF-8') > 100) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'query_too_long', 'message' => 'Search text must not exceed 100 characters.']]);
}
$status = trim((string)($_GET['status'] ?? ''));
if ($status !== '' && !in_array($status, ['active', 'inactive', 'suspended', 'cancelled'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_status', 'message' => 'Invalid sponsor status filter.']]);
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

try {
    $where = [];
    $params = [];
    if ($query !== '') {
        $like = '%' . $query . '%';
        $where[] = '(s.sponsor_code LIKE ? OR s.full_name LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)';
        $params = [$like, $like, $like, $like];
    }
    if ($status !== '') {
        $where[] = 's.status = ?';
        $params[] = $status;
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $rows = dbFetchAll(
        "SELECT s.id, s.sponsor_code, s.full_name, s.phone, s.status, s.sponsor_type,
                (SELECT COUNT(*) FROM sponsorships sp
                 WHERE sp.sponsor_id = s.id AND sp.status = 'active') AS active_sponsorship_count
         FROM sponsors s" . $whereSql . "
         ORDER BY s.updated_at DESC",
        $params
    );
    $total = count($rows);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $items = array_slice($rows, ($page - 1) * $perPage, $perPage);
    $items = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'sponsor_code' => (string)($row['sponsor_code'] ?? ''),
            'full_name' => (string)$row['full_name'],
            'phone' => (string)($row['phone'] ?? ''),
            'status' => (string)$row['status'],
            'sponsor_type' => (string)($row['sponsor_type'] ?? ''),
            'active_sponsorship_count' => (int)$row['active_sponsorship_count'],
        ];
    }, $items);

    ak_api_json(200, ['ok' => true, 'data' => [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
    ]]);
} catch (Throwable $e) {
    error_log('Native sponsor search failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'sponsor_search_failed', 'message' => 'Sponsors could not be searched.']]);
}
