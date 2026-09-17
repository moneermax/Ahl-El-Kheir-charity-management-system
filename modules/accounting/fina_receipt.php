<?php
// modules/accounting/fina_receipt.php - Authenticated streamer for Fina collection receipts
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}

$uid = (int) Session::getUserId();
$role = (string) Session::getUserRole();
$allowed = in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant', 'accountant_staff', 'financial_manager', 'fm'], true);
if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$id = (int) ($_GET['id'] ?? 0);
$requestedPath = trim((string) ($_GET['path'] ?? ''));

if ($id > 0) {
    $row = dbFetchOne(
        "SELECT id, created_by, receipt_path
         FROM fina_collections
         WHERE id = ?",
        [$id]
    );
} elseif ($requestedPath !== '') {
    // Direct receipt URLs are rewritten here by storage/receipts/.htaccess.
    // Resolve the collection from the exact stored path; never serve an
    // arbitrary file merely because its path exists on disk.
    $pathVariants = [$requestedPath];
    $normalizedPath = ltrim($requestedPath, '/\\');
    if ($normalizedPath !== $requestedPath) {
        $pathVariants[] = $normalizedPath;
    }

    $row = null;
    foreach ($pathVariants as $pathVariant) {
        $row = dbFetchOne(
            "SELECT id, created_by, receipt_path
             FROM fina_collections
             WHERE receipt_path = ?
             LIMIT 1",
            [$pathVariant]
        );
        if ($row) {
            break;
        }
    }
} else {
    http_response_code(400);
    exit('Invalid receipt reference');
}

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

// Supervisors are view-only and may access only their own Fina submissions.
if ($role === 'supervisor' && (int) $row['created_by'] !== $uid) {
    http_response_code(403);
    exit('Forbidden');
}

$path = trim((string) ($row['receipt_path'] ?? ''));
if ($path === '') {
    http_response_code(404);
    exit('Receipt not found');
}

$root = realpath(dirname(__DIR__, 2));
$real = $root !== false ? realpath($root . '/' . ltrim($path, '/\\')) : false;
if ($real === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
    http_response_code(404);
    exit('Receipt not found');
}

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mime = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
][$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($real));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="' . rawurlencode(basename($real)) . '"');
readfile($real);
exit;
