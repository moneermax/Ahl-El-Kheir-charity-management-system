<?php
declare(strict_types=1);

require_once __DIR__ . '/recovery_service.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
if (!in_array((string)$user['role_code'], ['admin', 'hr_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Password recovery review is limited to system administrators and HR managers.',
    ]]);
}

$input = ak_api_read_json();
try {
    ak_api_json(200, ['ok' => true, 'data' => ak_api_admin_recovery_action($input, (int)$user['id'])]);
} catch (AkAdminRecoveryException $e) {
    ak_api_json($e->httpStatus, ['ok' => false, 'error' => [
        'code' => $e->errorCode,
        'message' => $e->getMessage(),
    ]]);
} catch (Throwable $e) {
    error_log('Mobile recovery action failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'recovery_action_failed',
        'message' => 'The recovery request could not be processed.',
    ]]);
}
