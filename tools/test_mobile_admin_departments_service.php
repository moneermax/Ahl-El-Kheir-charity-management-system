<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/admin/departments_service.php';

$pdo = db();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectError = static function (string $code, callable $callback) use ($assert): AkAdminDepartmentException {
    try {
        $callback();
    } catch (AkAdminDepartmentException $e) {
        $assert($e->errorCode === $code, 'Expected error ' . $code . ', got ' . $e->errorCode . '.');
        return $e;
    }
    throw new RuntimeException('Expected department service error: ' . $code);
};

$existingUser = dbFetchOne('SELECT id, department_id FROM users ORDER BY id LIMIT 1');
$assert($existingUser !== null, 'A user fixture is required for department deletion guard.');
$actorId = (int)$existingUser['id'];
$originalDepartmentId = $existingUser['department_id'] === null ? null : (int)$existingUser['department_id'];
$suffix = bin2hex(random_bytes(5));
$nameAr = 'اختبار قسم ' . $suffix;
$nameEn = 'Department Test ' . $suffix;
$pdo->beginTransaction();
try {
    $invalid = $expectError('names_required', static fn() => ak_api_admin_department_action([
        'action' => 'create',
        'name_ar' => '',
        'name_en' => '',
    ]));
    $assert($invalid->httpStatus === 422, 'Missing department names must return HTTP 422.');

    $created = ak_api_admin_department_action([
        'action' => 'create',
        'name_ar' => $nameAr,
        'name_en' => $nameEn,
        'description' => 'Rollback-only native API test',
    ]);
    $assert((int)$created['http_status'] === 201, 'Department create did not return HTTP 201.');

    $department = dbFetchOne('SELECT id, name_ar, name_en FROM departments WHERE name_en = ?', [$nameEn]);
    $assert($department !== null, 'Created department was not persisted.');
    $departmentId = (int)$department['id'];

    $listed = ak_api_admin_list_departments();
    $listedIds = array_map(static fn(array $item): int => (int)$item['id'], $listed['items']);
    $assert(in_array($departmentId, $listedIds, true), 'Department list omitted the created department.');

    $expectError('duplicate_department', static fn() => ak_api_admin_department_action([
        'action' => 'create',
        'name_ar' => $nameAr,
        'name_en' => $nameEn,
    ]));

    $updatedNameAr = $nameAr . ' معدل';
    $updatedNameEn = $nameEn . ' Updated';
    $updated = ak_api_admin_department_action([
        'action' => 'update',
        'id' => $departmentId,
        'name_ar' => $updatedNameAr,
        'name_en' => $updatedNameEn,
        'description' => 'Updated rollback-only fixture',
    ]);
    $assert((int)$updated['http_status'] === 200, 'Department update did not return HTTP 200.');
    $fresh = dbFetchOne('SELECT name_ar, name_en FROM departments WHERE id = ?', [$departmentId]);
    $assert($fresh !== null && $fresh['name_ar'] === $updatedNameAr && $fresh['name_en'] === $updatedNameEn, 'Department update values were not saved.');

    dbExecute('UPDATE users SET department_id = ? WHERE id = ?', [$departmentId, $actorId]);
    $blocked = $expectError('department_in_use', static fn() => ak_api_admin_department_action([
        'action' => 'delete',
        'id' => $departmentId,
    ]));
    $assert((int)($blocked->extra['users_count'] ?? 0) >= 1, 'Delete guard did not report assigned users.');

    dbExecute('UPDATE users SET department_id = ? WHERE id = ?', [$originalDepartmentId, $actorId]);
    $deleted = ak_api_admin_department_action(['action' => 'delete', 'id' => $departmentId]);
    $assert((int)$deleted['http_status'] === 200, 'Unused department could not be deleted.');
    $assert(dbFetchOne('SELECT id FROM departments WHERE id = ?', [$departmentId]) === null, 'Deleted department still exists.');

    $expectError('department_not_found', static fn() => ak_api_admin_department_action([
        'action' => 'delete',
        'id' => $departmentId,
    ]));

    echo 'PASS | mobile admin department service | create, list, duplicate guard, update, assigned-user delete guard, delete, rollback=PASS' . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
