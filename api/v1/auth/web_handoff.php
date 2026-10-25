<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$input = ak_api_read_json();

$targetPath = trim((string)($input['path'] ?? ''));
if (
    $targetPath === ''
    || strlen($targetPath) > 255
    || !preg_match('~^(?:modules|dashboard)/[A-Za-z0-9_./-]+\.php$~D', $targetPath)
    || str_contains($targetPath, '..')
    || str_contains($targetPath, '\\')
    || str_contains($targetPath, '//')
) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_target_path',
        'message' => 'A valid internal website page path is required.',
    ]]);
}

$query = $input['query'] ?? [];
if (!is_array($query) || count($query) > 12) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_target_query',
        'message' => 'The website page query parameters are invalid.',
    ]]);
}
$cleanQuery = [];
foreach ($query as $key => $value) {
    if (
        !is_string($key)
        || !preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $key)
        || !is_scalar($value)
        || strlen((string)$value) > 500
    ) {
        ak_api_json(422, ['ok' => false, 'error' => [
            'code' => 'invalid_target_query',
            'message' => 'The website page query parameters are invalid.',
        ]]);
    }
    $cleanQuery[$key] = (string)$value;
}
$queryJson = json_encode($cleanQuery, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
if (strlen($queryJson) > 4096) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'target_query_too_large',
        'message' => 'The website page query parameters are too large.',
    ]]);
}

$rawToken = bin2hex(random_bytes(32));
dbExecute(
    'INSERT INTO mobile_web_handoffs
        (mobile_session_id, user_id, token_hash, target_path, target_query_json,
         expires_at, ip_address, user_agent, created_at)
     VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND), ?, ?, NOW())',
    [
        (int)$user['mobile_session_id'],
        (int)$user['id'],
        hash('sha256', $rawToken),
        $targetPath,
        $queryJson,
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]
);

ak_api_json(200, ['ok' => true, 'data' => [
    'handoff_token' => $rawToken,
    'expires_in_seconds' => 60,
]]);
