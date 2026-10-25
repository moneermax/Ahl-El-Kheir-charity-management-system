<?php
declare(strict_types=1);

require_once __DIR__ . '/departments_service.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'admin') {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Department management is limited to system administrators.',
    ]]);
}

$input = ak_api_read_json();
try {
    $result = ak_api_admin_department_action($input);
    $status = (int)$result['http_status'];
    unset($result['http_status']);
    ak_api_json($status, ['ok' => true, 'data' => $result]);
} catch (AkAdminDepartmentException $e) {
    ak_api_json($e->httpStatus, ['ok' => false, 'error' => [
        'code' => $e->errorCode,
        'message' => $e->getMessage(),
    ] + $e->extra]);
} catch (Throwable $e) {
    error_log('Mobile department action failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'department_action_failed',
        'message' => 'The department operation could not be completed.',
    ]]);
}
