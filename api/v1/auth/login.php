<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$input = ak_api_read_json();
$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '' || strlen($username) > 190 || strlen($password) > 4096) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_credentials_input', 'message' => 'Enter a username and password.']]);
}

$user = dbFetchOne(
    "SELECT u.id, u.username, u.full_name, u.password_hash, u.is_active,
            u.password_change_required, r.code AS role_code
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.username = ?
     LIMIT 1",
    [$username]
);

if (!$user || !password_verify($password, (string)$user['password_hash']) || (int)$user['is_active'] !== 1) {
    ak_api_json(401, ['ok' => false, 'error' => ['code' => 'invalid_credentials', 'message' => 'The username or password is incorrect, or the account is unavailable.']]);
}

if ((int)$user['password_change_required'] === 1) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'password_change_required', 'message' => 'Sign in to the existing website and change your password before using the mobile app.']]);
}

/*
 * Keep the same successful-login side effects as the browser login, including
 * the existing attendance policy helper. Optional attendance/audit failures
 * do not turn a valid login into a failed login.
 */
dbExecute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int)$user['id']]);

try {
    require_once dirname(__DIR__, 3) . '/modules/hr/lib_attendance_policy.php';
    require_once dirname(__DIR__, 3) . '/modules/hr/lib_attendance_integrity.php';
    hrAttendanceAutoCheckInForUser((int)$user['id']);
} catch (Throwable $attendanceError) {
    // Match the existing browser-login failure isolation.
}

try {
    dbExecute(
        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at)
         VALUES (?, 'LOGIN', 'users', ?, ?, ?, NOW())",
        [
            (int)$user['id'],
            (int)$user['id'],
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]
    );
} catch (Throwable $auditError) {
    // Match the existing browser-login failure isolation.
}

$rawToken = bin2hex(random_bytes(32));
dbExecute(
    'INSERT INTO mobile_api_sessions (user_id, token_hash, expires_at, ip_address, user_agent, created_at, last_used_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 12 HOUR), ?, ?, NOW(), NOW())',
    [
        (int)$user['id'],
        hash('sha256', $rawToken),
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]
);
$sessionExpiry = dbFetchOne('SELECT expires_at FROM mobile_api_sessions WHERE token_hash = ? LIMIT 1', [hash('sha256', $rawToken)]);
$expiresAt = (string)($sessionExpiry['expires_at'] ?? '');

ak_api_json(200, [
    'ok' => true,
    'data' => [
        'access_token' => $rawToken,
        'token_type' => 'Bearer',
        'expires_at' => $expiresAt,
        'user' => [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'full_name' => (string)$user['full_name'],
            'role_code' => (string)$user['role_code'],
        ],
    ],
]);
