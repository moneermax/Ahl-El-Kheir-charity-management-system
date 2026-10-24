<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$token = ak_api_bearer_token();
if ($token === null) {
    ak_api_json(401, ['ok' => false, 'error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.']]);
}

dbExecute(
    'UPDATE mobile_api_sessions SET revoked_at = NOW(), last_used_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL',
    [hash('sha256', $token)]
);

ak_api_json(200, ['ok' => true, 'data' => ['signed_out' => true]]);
