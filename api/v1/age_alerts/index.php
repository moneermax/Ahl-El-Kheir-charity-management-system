<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];

$aliases = [
    'sudo' => 'admin',
    'super_admin' => 'admin',
    'gm' => 'general_manager',
    'vgm' => 'vice_general_manager',
];
$role = $aliases[$role] ?? $role;

if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'nanny'], true)) {
    ak_api_json(403, [
        'ok' => false,
        'error' => [
            'code' => 'forbidden',
            'message' => 'Legal age alerts are not available for this account.',
        ],
    ]);
}

$scopeSql = '';
$params = [];
if ($role === 'nanny') {
    $scopeSql = ' AND f.nanny_id = ?';
    $params[] = $userId;
}

$rows = dbFetchAll(
    "SELECT fc.id AS child_id, fc.child_name, fc.birth_date,
            f.id AS family_id, f.family_code, f.mother_name,
            DATEDIFF(DATE_ADD(fc.birth_date, INTERVAL 18 YEAR), CURDATE()) AS days_until_18
     FROM family_children fc
     INNER JOIN families f ON f.id = fc.family_id
     WHERE fc.is_active = 1
       AND fc.birth_date IS NOT NULL
       AND fc.birth_date <= DATE_SUB(DATE_ADD(CURDATE(), INTERVAL 90 DAY), INTERVAL 18 YEAR)
       AND fc.birth_date >= DATE_SUB(CURDATE(), INTERVAL 21 YEAR)
       {$scopeSql}
     ORDER BY fc.birth_date ASC",
    $params
);

$items = array_map(static fn(array $row): array => [
    'child_id' => (int)$row['child_id'],
    'child_name' => (string)$row['child_name'],
    'birth_date' => (string)$row['birth_date'],
    'family_id' => (int)$row['family_id'],
    'family_code' => (string)$row['family_code'],
    'mother_name' => (string)$row['mother_name'],
    'days_until_18' => (int)$row['days_until_18'],
], $rows);

ak_api_json(200, ['ok' => true, 'data' => ['items' => $items, 'total' => count($items)]]);
