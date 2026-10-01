<?php
declare(strict_types=1);

// Authenticated streamer for salary-advance payment receipts.
// Files are resolved through the database record; arbitrary filesystem paths are never served.

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
$allowed = ['admin', 'financial_manager', 'fm'];
$isPrivileged = in_array($role, $allowed, true);

$documentId = (int)($_GET['id'] ?? 0);
if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid receipt reference');
}

$row = dbFetchOne(
    "SELECT d.id, d.file_path, d.original_name, d.mime_type, d.file_size,
            r.status, r.disbursement_journal_entry_id
     FROM hr_salary_advance_documents d
     JOIN hr_salary_advance_requests r ON r.id = d.salary_advance_request_id
     WHERE d.id = ? AND d.document_type = 'payment_receipt'
     LIMIT 1",
    [$documentId]
);

if (!$row || !in_array((string)$row['status'], ['disbursed', 'settled'], true) || (int)$row['disbursement_journal_entry_id'] <= 0) {
    http_response_code(404);
    exit('Receipt not found');
}

if (!$isPrivileged) {
    $employeeAccess = dbFetchOne(
        "SELECT e.id
         FROM employees e
         JOIN hr_salary_advance_requests r ON r.employee_id = e.id
         JOIN hr_salary_advance_documents d ON d.salary_advance_request_id = r.id
         WHERE e.user_id = ?
           AND d.id = ?
           AND d.document_type = 'payment_receipt'
         LIMIT 1",
        [(int)Session::getUserId(), $documentId]
    );
    if (!$employeeAccess) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$root = realpath(dirname(__DIR__, 2));
$relative = ltrim((string)$row['file_path'], '/\\');
$real = $root !== false ? realpath($root . DIRECTORY_SEPARATOR . $relative) : false;

if ($real === false || $root === false ||
    strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($real)) {
    http_response_code(404);
    exit('Receipt not found');
}

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf',
];
$mime = (string)$row['mime_type'];
if (!isset($allowedMimes[$mime])) {
    http_response_code(404);
    exit('Receipt type not supported');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($real));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="' . rawurlencode((string)$row['original_name']) . '"');
readfile($real);
exit;
