<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/session.php';

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

Session::start();

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/mobile_web_handoff.php'));
$basePath = rtrim(dirname($scriptName), '/');
if ($basePath === '.' || $basePath === '/') $basePath = '';
$loginLocation = $basePath . '/index.php';

$rejectHandoff = static function () use ($loginLocation): never {
    flash('error', 'رابط الدخول من التطبيق غير صالح أو انتهت صلاحيته. افتح الصفحة من التطبيق مرة أخرى.');
    header('Location: ' . $loginLocation, true, 302);
    exit();
};

$rawToken = strtolower(trim((string)($_GET['token'] ?? '')));
if (!preg_match('/^[a-f0-9]{64}$/D', $rawToken)) $rejectHandoff();

$pdo = db();
try {
    $pdo->beginTransaction();
    $handoff = dbFetchOne(
        'SELECT h.id, h.user_id, h.mobile_session_id, h.target_path, h.target_query_json,
                h.expires_at, h.consumed_at,
                ms.revoked_at, ms.expires_at AS mobile_expires_at,
                u.username, u.full_name, u.is_active, u.password_change_required,
                r.code AS role_code
         FROM mobile_web_handoffs h
         JOIN mobile_api_sessions ms ON ms.id = h.mobile_session_id
         JOIN users u ON u.id = h.user_id
         JOIN roles r ON r.id = u.role_id
         WHERE h.token_hash = ?
           AND h.consumed_at IS NULL
           AND h.expires_at > NOW()
           AND ms.revoked_at IS NULL
           AND ms.expires_at > NOW()
           AND u.is_active = 1
           AND u.password_change_required = 0
         LIMIT 1 FOR UPDATE',
        [hash('sha256', $rawToken)]
    );

    if (
        !$handoff
        || !empty($handoff['consumed_at'])
        || !empty($handoff['revoked_at'])
        || (int)$handoff['is_active'] !== 1
        || (int)$handoff['password_change_required'] === 1
    ) {
        $pdo->rollBack();
        $rejectHandoff();
    }

    $targetPath = (string)$handoff['target_path'];
    if (
        !preg_match('~^(?:modules|dashboard)/[A-Za-z0-9_./-]+\.php$~D', $targetPath)
        || str_contains($targetPath, '..')
        || str_contains($targetPath, '\\')
        || str_contains($targetPath, '//')
    ) {
        $pdo->rollBack();
        $rejectHandoff();
    }

    $query = json_decode((string)($handoff['target_query_json'] ?? '{}'), true);
    if (!is_array($query)) $query = [];
    $targetQuery = [];
    foreach ($query as $key => $value) {
        if (is_string($key) && preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $key) && is_scalar($value)) {
            $targetQuery[$key] = (string)$value;
        }
    }

    $updated = dbExecute(
        'UPDATE mobile_web_handoffs SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL AND expires_at > NOW()',
        [(int)$handoff['id']]
    );
    if ($updated !== 1) {
        $pdo->rollBack();
        $rejectHandoff();
    }
    $pdo->commit();

    // Replace any pre-existing browser identity with the account that initiated
    // this one-time handoff from the authenticated mobile app.
    $_SESSION = [];
    if (!session_regenerate_id(true)) {
        $rejectHandoff();
    }
    $_SESSION['user_id'] = (int)$handoff['user_id'];
    $_SESSION['user_name'] = (string)$handoff['full_name'];
    $_SESSION['user_username'] = (string)$handoff['username'];
    $_SESSION['user_role'] = (string)$handoff['role_code'];
    $_SESSION['_auth_session_rotated'] = true;

    session_write_close();

    $location = $basePath . '/' . $targetPath;
    if ($targetQuery) $location .= '?' . http_build_query($targetQuery);
    header('Location: ' . $location, true, 302);
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mobile website handoff failed: ' . $e->getMessage());
    $rejectHandoff();
}
