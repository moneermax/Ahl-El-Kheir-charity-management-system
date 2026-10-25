<?php
declare(strict_types=1);

require_once __DIR__ . '/recovery_service.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)$user['role_code'], ['admin', 'hr_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Password recovery review is limited to system administrators and HR managers.',
    ]]);
}

try {
    ak_api_json(200, ['ok' => true, 'data' => ak_api_admin_recovery_list()]);
} catch (Throwable $e) {
    error_log('Mobile recovery queue failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'recovery_list_failed',
        'message' => 'The recovery request list could not be loaded.',
    ]]);
}
