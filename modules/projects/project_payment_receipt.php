<?php
// modules/projects/project_payment_receipt.php - Authenticated project payment receipt viewer
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
Session::start();

$id = (int)($_GET['id'] ?? 0);
$payment = $id ? dbFetchOne(
    "SELECT pe.*, p.project_code, p.name
     FROM project_payment_evidence pe
     INNER JOIN other_projects p ON p.id = pe.project_id
     WHERE pe.id = ?",
    [$id]
) : null;

if (!$payment || empty($payment['receipt_file_path']) || (string)$payment['status'] !== 'documented') {
    http_response_code(404);
    exit('إيصال الدفع غير موجود.');
}

$role = akp_role();
if (!in_array($role, ['admin','financial_manager','general_manager','vice_general_manager','projects_manager','accountant','accountant_staff'], true) || !akp_can_view_project((int)$payment['project_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$root = realpath(dirname(__DIR__, 2));
$expectedPrefix = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . ((int)$payment['project_id']) . DIRECTORY_SEPARATOR . 'payments' . DIRECTORY_SEPARATOR;
$path = realpath($root . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$payment['receipt_file_path']), DIRECTORY_SEPARATOR));

if (!$path || !is_file($path) || strpos($path, $expectedPrefix) !== 0) {
    http_response_code(404);
    exit('ملف الإيصال غير موجود.');
}

$mime = (string)($payment['receipt_mime_type'] ?: 'application/octet-stream');
$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($payment['receipt_original_name'] ?: basename($path))) ?: 'project-payment-receipt';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
