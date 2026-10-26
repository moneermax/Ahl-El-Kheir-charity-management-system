<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Use GET or POST.']]);
}
$user = ak_api_authenticated_user();
if ((string)($user['raw_role_code'] ?? '') !== 'admin') {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Database backup access is restricted to the administrator role.']]);
}

$backupsDir = dirname(__DIR__, 3) . '/storage/backups';
$validFilename = static fn(string $filename): bool =>
    preg_match('/^backup_[0-9]{8}_[0-9]{6}(?:_[a-f0-9]{6})?\.sql$/', $filename) === 1;

$metadata = static function (string $path): array {
    return [
        'filename' => basename($path),
        'size_bytes' => (int)filesize($path),
        'size_kb' => round(((int)filesize($path)) / 1024, 1),
        'modified_at' => date('Y-m-d H:i:s', (int)filemtime($path)),
    ];
};

if ($method === 'GET') {
    if (isset($_GET['download'])) {
        $filename = basename((string)$_GET['download']);
        if (!$validFilename($filename) || !is_file($backupsDir . '/' . $filename)) {
            ak_api_json(404, ['ok' => false, 'error' => ['code' => 'backup_not_found', 'message' => 'The requested backup file does not exist.']]);
        }
        $path = $backupsDir . '/' . $filename;
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/sql; charset=binary');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string)filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit();
    }

    $backups = [];
    foreach ((glob($backupsDir . '/backup_*.sql') ?: []) as $path) {
        if (is_file($path) && $validFilename(basename($path))) $backups[] = $metadata($path);
    }
    usort($backups, static fn(array $a, array $b): int => strcmp($b['modified_at'], $a['modified_at']));
    ak_api_json(200, ['ok' => true, 'data' => [
        'database_name' => defined('DB_NAME') ? (string)DB_NAME : (string)(getenv('AHL_DB_NAME') ?: 'ahl_el_kheir'),
        'backups' => $backups,
    ]]);
}

$body = ak_api_read_json();
$action = (string)($body['action'] ?? '');
if ($action === 'delete') {
    $filename = basename((string)($body['filename'] ?? ''));
    if (!$validFilename($filename)) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_filename', 'message' => 'The backup filename is invalid.']]);
    }
    $path = $backupsDir . '/' . $filename;
    if (!is_file($path)) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'backup_not_found', 'message' => 'The requested backup file does not exist.']]);
    }
    if (!@unlink($path)) {
        ak_api_json(500, ['ok' => false, 'error' => ['code' => 'backup_delete_failed', 'message' => 'The backup file could not be deleted.']]);
    }
    dbExecute(
        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
         VALUES (?, 'DELETE', 'backup', NULL, NULL, ?, ?, ?)",
        [(int)$user['id'], json_encode(['file' => $filename], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
    );
    ak_api_json(200, ['ok' => true, 'data' => ['deleted' => $filename]]);
}
if ($action !== 'create') {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Choose create or delete.']]);
}
if (!function_exists('exec')) {
    ak_api_json(503, ['ok' => false, 'error' => ['code' => 'backup_unavailable', 'message' => 'Database backup is unavailable because command execution is disabled on this host.']]);
}
$disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
if (in_array('exec', $disabled, true)) {
    ak_api_json(503, ['ok' => false, 'error' => ['code' => 'backup_unavailable', 'message' => 'Database backup is unavailable because command execution is disabled on this host.']]);
}
if (!is_dir($backupsDir) && !@mkdir($backupsDir, 0700, true) && !is_dir($backupsDir)) {
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'backup_storage_unavailable', 'message' => 'The backup storage directory could not be created.']]);
}
$dbCfg = [
    'host' => defined('DB_HOST') ? DB_HOST : (defined('DB_SERVER') ? DB_SERVER : '127.0.0.1'),
    'user' => defined('DB_USER') ? DB_USER : (defined('DB_USERNAME') ? DB_USERNAME : 'root'),
    'pass' => defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : ''),
    'name' => defined('DB_NAME') ? DB_NAME : (defined('DB_DATABASE') ? DB_DATABASE : (string)(getenv('AHL_DB_NAME') ?: 'ahl_el_kheir')),
];
$mysqldump = is_file('D:/xampp/mysql/bin/mysqldump.exe') ? 'D:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump';
$filename = 'backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.sql';
$path = $backupsDir . '/' . $filename;
$command = escapeshellarg($mysqldump)
    . ' --host=' . escapeshellarg((string)$dbCfg['host'])
    . ' --user=' . escapeshellarg((string)$dbCfg['user'])
    . ((string)$dbCfg['pass'] !== '' ? ' --password=' . escapeshellarg((string)$dbCfg['pass']) : '')
    . ' --default-character-set=utf8mb4 --single-transaction --routines --triggers '
    . escapeshellarg((string)$dbCfg['name'])
    . ' > ' . escapeshellarg($path) . ' 2>&1';
$out = [];
$ret = 1;
exec($command, $out, $ret);
if ($ret !== 0 || !is_file($path) || (int)filesize($path) <= 500) {
    @unlink($path);
    error_log('Mobile database backup command failed; exit code ' . $ret);
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'backup_create_failed', 'message' => 'The database backup could not be created. Check mysqldump availability and database permissions.']]);
}
$details = $metadata($path);
dbExecute(
    "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
     VALUES (?, 'EXPORT', 'database', NULL, NULL, ?, ?, ?)",
    [(int)$user['id'], json_encode(['file' => $filename, 'size' => $details['size_bytes']], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
);
ak_api_json(201, ['ok' => true, 'data' => ['backup' => $details]]);
