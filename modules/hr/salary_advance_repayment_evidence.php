<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}

$role = (string)Session::getUserRole();
if (!in_array($role, ['admin','financial_manager','fm'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

$documentId = (int)($_GET['id'] ?? 0);
if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid evidence reference');
}

$row = dbFetchOne(
    "SELECT d.file_path, d.original_name, d.mime_type, d.file_size
     FROM hr_salary_advance_direct_repayment_documents d
     JOIN hr_salary_advance_direct_repayments r ON r.id = d.direct_repayment_id
     WHERE d.id = ?
     LIMIT 1",
    [$documentId]
);
if (!$row) {
    http_response_code(404);
    exit('Evidence not found');
}

$root = realpath(dirname(__DIR__, 2));
$relative = ltrim((string)$row['file_path'], '/\\');
$real = $root !== false ? realpath($root . DIRECTORY_SEPARATOR . $relative) : false;
if ($real === false || $root === false ||
    strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($real)) {
    http_response_code(404);
    exit('Evidence not found');
}

$allowedMimes = ['image/jpeg','image/png','application/pdf'];
$mime = (string)$row['mime_type'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(404);
    exit('Evidence type not supported');
}

header('Content-Type: ' . $mime);
header('Content-Length: (string)filesize($real));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="' . rawurlencode((string)$row['original_name']) . '"');
readfile($real);
exit;
