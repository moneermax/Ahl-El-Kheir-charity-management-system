<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$user['role_code'] = (string)($user['raw_role_code'] ?? $user['role_code']);
unset($user['raw_role_code'], $user['mobile_session_id']);

ak_api_json(200, ['ok' => true, 'data' => ['user' => $user]]);
