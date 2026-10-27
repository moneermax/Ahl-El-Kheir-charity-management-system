<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)($user['raw_role_code'] ?? $user['role_code'] ?? '');
if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'administration'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Available-family listing is not allowed for this role.',
    ]]);
}
try {
    $rows = dbFetchAll(
        "SELECT f.id, f.family_code, f.mother_name, f.children_count, f.monthly_need_amount, f.city
         FROM families f
         WHERE f.status IN ('active','pending')
           AND NOT EXISTS (
               SELECT 1
               FROM sponsorships sp
               JOIN family_children fc ON fc.id = sp.child_id
               WHERE fc.family_id = f.id AND sp.status = 'active'
           )
         ORDER BY f.children_count DESC, f.monthly_need_amount DESC"
    );
    $items = array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'family_code' => (string)($row['family_code'] ?? ''),
        'mother_name' => (string)($row['mother_name'] ?? ''),
        'children_count' => (int)($row['children_count'] ?? 0),
        'monthly_need_amount' => (float)($row['monthly_need_amount'] ?? 0),
        'city' => (string)($row['city'] ?? ''),
    ], $rows);
    ak_api_json(200, ['ok' => true, 'data' => ['items' => $items, 'total' => count($items)]]);
} catch (Throwable $e) {
    error_log('Available-family API failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'available_families_unavailable',
        'message' => 'Available families could not be loaded.',
    ]]);
}
