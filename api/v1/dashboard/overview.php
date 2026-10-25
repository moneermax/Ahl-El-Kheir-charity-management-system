<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/overview_service.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];
$userId = (int)$user['id'];

if (in_array($role, ['admin', 'sudo', 'super_admin'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'use_admin_overview', 'message' => 'Use the administrator overview endpoint for this role.']]);
}

try {
    $metrics = ak_mobile_dashboard_metrics($role, $userId);
} catch (DomainException $e) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'dashboard_not_available', 'message' => 'A native overview is not available for this role.']]);
} catch (Throwable $e) {
    error_log('Mobile dashboard overview failed for role ' . $role . ': ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'dashboard_overview_failed', 'message' => 'Dashboard data could not be loaded.']]);
}

ak_api_json(200, ['ok' => true, 'data' => ['role_code' => $user['raw_role_code'] ?? $role, 'metrics' => $metrics]]);
