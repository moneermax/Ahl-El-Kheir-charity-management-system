<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib_contract_salary.php';

Session::start();

$allowedRoles = ['hr_manager', 'hr_staff', 'admin'];
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), $allowedRoles, true)) {
    http_response_code(403);
    exit('ليس لديك صلاحية عرض ملفات عقود العمل.');
}

$contractId = filter_input(INPUT_GET, 'contract_id', FILTER_VALIDATE_INT);
if (!$contractId || $contractId <= 0) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$contract = hrGetEmployeeContract((int)$contractId);
if (!$contract || empty($contract['contract_file_path'])) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$relativePath = str_replace('\\', '/', (string)$contract['contract_file_path']);
$expectedPrefix = 'storage/hr_contracts/';
if (!str_starts_with($relativePath, $expectedPrefix)) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$fileName = substr($relativePath, strlen($expectedPrefix));
if ($fileName === '' || basename($fileName) !== $fileName || str_contains($fileName, '..')) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$storageDirectory = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'hr_contracts');
$filePath = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
if ($storageDirectory === false || $filePath === false || !is_file($filePath)) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$storagePrefix = rtrim($storageDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strncmp($filePath, $storagePrefix, strlen($storagePrefix)) !== 0) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedMimeTypes = [
    'pdf' => ['application/pdf'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
];
if (!isset($allowedMimeTypes[$extension])) {
    http_response_code(415);
    exit('صيغة ملف العقد غير مدعومة.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);
if (!is_string($mimeType) || !in_array($mimeType, $allowedMimeTypes[$extension], true)) {
    http_response_code(415);
    exit('محتوى ملف العقد لا يطابق صيغته المسجلة.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($filePath);
exit;
