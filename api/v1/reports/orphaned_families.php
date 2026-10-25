<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/reports/report_registry.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)$user['raw_role_code'];
if (!ak_report_can_access('orphaned', $role)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'The orphaned-families report is not permitted for this role.',
    ]]);
}

$city = trim((string)($_GET['city'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'active'));
if (strlen($city) > 100 || !in_array($status, ['active', 'pending', 'all'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_filter',
        'message' => 'The city or status filter is invalid.',
    ]]);
}

$where = " WHERE f.status IN ('active','pending')
  AND EXISTS (SELECT 1 FROM family_children fc_exists WHERE fc_exists.family_id = f.id AND fc_exists.is_active = 1)
  AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON sp.child_id = fc.id
                  WHERE fc.family_id = f.id AND sp.status = 'active')";
$params = [];
if ($city !== '') {
    $where .= ' AND f.city = ?';
    $params[] = $city;
}
if ($status !== 'all') {
    $where .= ' AND f.status = ?';
    $params[] = $status;
}

$rows = dbFetchAll(
    'SELECT f.id, f.family_code, f.mother_name, f.city, f.monthly_need_amount, f.status,
        (SELECT COUNT(*) FROM family_children fc_all WHERE fc_all.family_id = f.id) AS actual_children_count,
        (SELECT COUNT(*) FROM family_children fc_active WHERE fc_active.family_id = f.id AND fc_active.is_active = 1) AS active_children
     FROM families f' . $where . ' ORDER BY f.city ASC, f.mother_name ASC',
    $params
);
$cities = array_column(dbFetchAll("SELECT DISTINCT city FROM families WHERE city IS NOT NULL AND city <> '' ORDER BY city"), 'city');
$items = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'family_code' => (string)($row['family_code'] ?? ''),
    'mother_name' => (string)($row['mother_name'] ?? ''),
    'city' => (string)($row['city'] ?? ''),
    'monthly_need_amount' => (float)($row['monthly_need_amount'] ?? 0),
    'status' => (string)($row['status'] ?? ''),
    'actual_children_count' => (int)($row['actual_children_count'] ?? 0),
    'active_children' => (int)($row['active_children'] ?? 0),
], $rows);
ak_api_json(200, ['ok' => true, 'data' => [
    'items' => $items,
    'total' => count($items),
    'active_children_waiting' => array_sum(array_column($items, 'active_children')),
    'cities' => array_values(array_map('strval', $cities)),
    'city' => $city,
    'status' => $status,
]]);
