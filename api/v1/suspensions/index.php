<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/case_service.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();

try {
    $data = ak_api_list_suspension_cases((int)$user['id'], (string)$user['role_code']);
} catch (AkSuspensionLiftForbidden $e) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Suspension cases are not available for this account.']]);
} catch (Throwable $e) {
    error_log('Mobile suspension case list failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'suspension_list_failed', 'message' => 'Suspension cases could not be loaded.']]);
}

ak_api_json(200, ['ok' => true, 'data' => $data]);
