<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/assignment_service.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];

if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Sponsor assignment is not allowed for this account.']]);
}

ak_api_json(200, ['ok' => true, 'data' => ak_mobile_sponsor_assignment_options()]);
