<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}

function ak_api_resolve_avatar_path(?string $storedPath, string $avatarsDir): ?string
{
    $storedPath = trim((string)$storedPath);
    if ($storedPath === '' || !str_starts_with($storedPath, 'storage/avatars/')) return null;
    $filename = basename($storedPath);
    if ($filename === '' || $filename === '.' || $filename === '..') return null;
    $realDir = realpath($avatarsDir);
    $realFile = realpath($avatarsDir . DIRECTORY_SEPARATOR . $filename);
    if ($realDir === false || $realFile === false || dirname($realFile) !== $realDir || !is_file($realFile)) return null;
    return $realFile;
}

$avatarsDir = APP_DIR . '/storage/avatars';
if ($method === 'GET') {
    $row = dbFetchOne('SELECT avatar_path FROM users WHERE id = ? LIMIT 1', [$userId]);
    $path = ak_api_resolve_avatar_path($row['avatar_path'] ?? null, $avatarsDir);
    if ($path === null) ak_api_json(404, ['ok' => false, 'error' => ['code' => 'avatar_not_found', 'message' => 'No profile photo is available.']]);
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || @getimagesize($path) === false) {
        ak_api_json(415, ['ok' => false, 'error' => ['code' => 'invalid_avatar_file', 'message' => 'The saved profile photo is not a supported image.']]);
    }
    header_remove('Content-Type');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    $extension = $mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : 'webp');
    header('Content-Disposition: inline; filename="profile-avatar.' . $extension . '"');
    readfile($path);
    exit;
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_starts_with($contentType, 'application/json')) {
    $input = ak_api_read_json();
    if (($input['action'] ?? '') !== 'remove_avatar') ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Unsupported profile photo action.']]);
    $row = dbFetchOne('SELECT avatar_path FROM users WHERE id = ? LIMIT 1', [$userId]);
    dbExecute('UPDATE users SET avatar_path = NULL WHERE id = ?', [$userId]);
    $oldPath = ak_api_resolve_avatar_path($row['avatar_path'] ?? null, $avatarsDir);
    if ($oldPath !== null) @unlink($oldPath);
    ak_api_json(200, ['ok' => true, 'data' => ['has_avatar' => false, 'message' => 'Profile photo removed.']]);
}

$file = $_FILES['avatar'] ?? null;
if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'avatar_upload_required', 'message' => 'Choose a profile photo to upload.']]);
}
if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 10 * 1024 * 1024) {
    ak_api_json(413, ['ok' => false, 'error' => ['code' => 'avatar_size_invalid', 'message' => 'The profile photo must not exceed 10 MB.']]);
}
$mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extensions[$mime]) || @getimagesize((string)$file['tmp_name']) === false) {
    ak_api_json(415, ['ok' => false, 'error' => ['code' => 'avatar_type_invalid', 'message' => 'Use a valid JPG, PNG, or WebP image.']]);
}
if (!is_dir($avatarsDir) && !@mkdir($avatarsDir, 0750, true) && !is_dir($avatarsDir)) {
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'avatar_storage_unavailable', 'message' => 'Profile photo storage is unavailable.']]);
}
$filename = 'user_' . $userId . '_' . bin2hex(random_bytes(10)) . '.' . $extensions[$mime];
$destination = $avatarsDir . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'avatar_save_failed', 'message' => 'The profile photo could not be saved.']]);
}
$oldRow = dbFetchOne('SELECT avatar_path FROM users WHERE id = ? LIMIT 1', [$userId]);
try {
    dbExecute('UPDATE users SET avatar_path = ? WHERE id = ?', ['storage/avatars/' . $filename, $userId]);
} catch (Throwable $e) {
    @unlink($destination);
    error_log('Mobile profile photo update failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'avatar_save_failed', 'message' => 'The profile photo could not be saved.']]);
}
$oldPath = ak_api_resolve_avatar_path($oldRow['avatar_path'] ?? null, $avatarsDir);
if ($oldPath !== null && $oldPath !== realpath($destination)) @unlink($oldPath);
ak_api_json(200, ['ok' => true, 'data' => ['has_avatar' => true, 'message' => 'Profile photo updated successfully.']]);
