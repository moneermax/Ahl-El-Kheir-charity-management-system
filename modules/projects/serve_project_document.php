<?php
// modules/projects/serve_project_document.php - permission-checked project document delivery
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
Session::start();

$documentId = (int)($_GET['id'] ?? 0);
$document = dbFetchOne('SELECT * FROM project_documents WHERE id = ?', [$documentId]);
if (!$document || !akp_can_view_project((int)$document['project_id'])) {
    http_response_code(404);
    exit('الوثيقة غير موجودة.');
}

$root = realpath(dirname(__DIR__, 2));
$expectedPrefix = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . ((int)$document['project_id']) . DIRECTORY_SEPARATOR;
$path = realpath($root . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$document['file_path']), DIRECTORY_SEPARATOR));
if (!$path || !is_file($path) || strpos($path, $expectedPrefix) !== 0) {
    http_response_code(404);
    exit('الملف غير موجود.');
}

$mime = (string)($document['mime_type'] ?: 'application/octet-stream');
$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$document['original_name']) ?: 'project-document';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
