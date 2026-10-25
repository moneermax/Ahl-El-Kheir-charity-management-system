<?php
declare(strict_types=1);

require_once __DIR__ . '/departments_service.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'admin') {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Department management is limited to system administrators.',
    ]]);
}

try {
    ak_api_json(200, ['ok' => true, 'data' => ak_api_admin_list_departments()]);
} catch (Throwable $e) {
    error_log('Mobile department list failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'department_list_failed',
        'message' => 'The department list could not be loaded.',
    ]]);
}
