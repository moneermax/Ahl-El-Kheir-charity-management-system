<?php
declare(strict_types=1);

/*
 * Mobile API v1 bootstrap.
 * API requests use opaque bearer tokens; PHP browser sessions are not used.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function ak_api_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ak_api_normalize_role_code(string $roleCode): string
{
    return match ($roleCode) {
        'sudo', 'super_admin' => 'admin',
        'gm' => 'general_manager',
        'vgm' => 'vice_general_manager',
        'fm', 'finance' => 'financial_manager',
        default => $roleCode,
    };
}

function ak_api_require_method(string $method): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
        header('Allow: ' . strtoupper($method));
        ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
    }
}

function ak_api_read_json(): array
{
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 16384) {
        ak_api_json(413, ['ok' => false, 'error' => ['code' => 'request_too_large', 'message' => 'Request body is too large.']]);
    }

    $raw = file_get_contents('php://input');
    if ($raw !== false && strlen($raw) > 16384) {
        ak_api_json(413, ['ok' => false, 'error' => ['code' => 'request_too_large', 'message' => 'Request body is too large.']]);
    }
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        ak_api_json(400, ['ok' => false, 'error' => ['code' => 'invalid_json', 'message' => 'A valid JSON object is required.']]);
    }

    return $decoded;
}

function ak_api_bearer_token(): ?string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($header), $matches)) {
        return null;
    }

    return strtolower($matches[1]);
}

/**
 * Re-check the account and role against the database on every authenticated
 * request. The raw bearer token is never stored in the database.
 */
function ak_api_authenticated_user(): array
{
    $token = ak_api_bearer_token();
    if ($token === null) {
        ak_api_json(401, ['ok' => false, 'error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.']]);
    }

    $session = dbFetchOne(
        "SELECT s.id AS mobile_session_id, u.id, u.username, u.full_name,
                u.is_active, u.password_change_required, r.code AS role_code
         FROM mobile_api_sessions s
         JOIN users u ON u.id = s.user_id
         JOIN roles r ON r.id = u.role_id
         WHERE s.token_hash = ? AND s.revoked_at IS NULL
           AND s.expires_at > NOW()
         LIMIT 1",
        [hash('sha256', $token)]
    );

    if (!$session || (int)$session['is_active'] !== 1) {
        ak_api_json(401, ['ok' => false, 'error' => ['code' => 'invalid_or_expired_token', 'message' => 'The session is invalid or expired.']]);
    }

    if ((int)$session['password_change_required'] === 1) {
        ak_api_json(403, ['ok' => false, 'error' => ['code' => 'password_change_required', 'message' => 'Change your password through the existing website, then sign in again.']]);
    }

    dbExecute('UPDATE mobile_api_sessions SET last_used_at = NOW() WHERE id = ?', [(int)$session['mobile_session_id']]);

    $rawRoleCode = (string)$session['role_code'];

    return [
        'id' => (int)$session['id'],
        'username' => (string)$session['username'],
        'full_name' => (string)$session['full_name'],
        // Use the canonical web-router role for authorization checks in API endpoints.
        'role_code' => ak_api_normalize_role_code($rawRoleCode),
        // Keep the persisted role for auth/me so the client retains the user's real role label.
        'raw_role_code' => $rawRoleCode,
        'mobile_session_id' => (int)$session['mobile_session_id'],
    ];
}
