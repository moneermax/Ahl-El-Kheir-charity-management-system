<?php
// Authenticated streamer for Fina settlement transfer evidence.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'financial_manager') {
    http_response_code(403);
    exit('Forbidden');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid evidence reference');
}

$row = dbFetchOne("SELECT evidence_path FROM fina_settlements WHERE id=? LIMIT 1", [$id]);
if (!$row || trim((string) ($row['evidence_path'] ?? '')) === '') {
    http_response_code(404);
    exit('Evidence not found');
}

$root = realpath(dirname(__DIR__, 2));
$stored = ltrim(trim((string) $row['evidence_path']), '/\\');
$real = $root !== false ? realpath($root . '/' . $stored) : false;
$allowedRoot = $root !== false ? realpath($root . '/storage/receipts/fina_settlements') : false;

if ($real === false || $allowedRoot === false || strpos($real, $allowedRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
    http_response_code(404);
    exit('Evidence not found');
}

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mime = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
][$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($real));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="' . rawurlencode(basename($real)) . '"');
readfile($real);
exit;
