<?php
// Permission-checked retrieval of a specific archived project-file version.
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}

$documentId = (int)($_GET['document_id'] ?? 0);
$versionId = (int)($_GET['version_id'] ?? 0);
if ($documentId <= 0 || $versionId <= 0) {
    http_response_code(404);
    exit('النسخة المؤرشفة غير موجودة.');
}

$version = dbFetchOne(
    "SELECT l.source_table, l.source_record_id, l.source_path_column,
            l.source_path_snapshot, l.link_status, d.document_kind,
            v.version_no, v.original_name, v.mime_type_snapshot,
            v.byte_size_snapshot, v.sha256_snapshot,
            b.storage_key, b.sha256, b.byte_size, b.detected_mime_type,
            b.storage_status
     FROM document_archive_source_links l
     INNER JOIN document_archive_documents d ON d.id = l.document_id
     INNER JOIN document_archive_versions v ON v.document_id = d.id
     INNER JOIN document_archive_blobs b ON b.id = v.blob_id
     WHERE l.document_id = ? AND v.id = ?
     ORDER BY l.id
     LIMIT 1",
    [$documentId, $versionId]
);

if (!$version || (string)$version['storage_status'] !== 'present') {
    http_response_code(404);
    exit('النسخة المؤرشفة غير متاحة.');
}

$sourceTable = (string)$version['source_table'];
$sourceId = (int)$version['source_record_id'];
$projectId = 0;
$sourceExists = false;

if ($sourceTable === 'project_documents' && (string)$version['source_path_column'] === 'file_path') {
    $source = dbFetchOne('SELECT project_id, file_path FROM project_documents WHERE id = ?', [$sourceId]);
    if ($source) {
        $sourceExists = true;
        $projectId = (int)$source['project_id'];
        if ((string)$version['link_status'] === 'active'
            && (string)$source['file_path'] !== (string)$version['source_path_snapshot']) {
            http_response_code(404);
            exit('رابط المستند المؤرشف يحتاج إلى تسوية.');
        }
    }
} elseif ($sourceTable === 'project_payment_evidence' && (string)$version['source_path_column'] === 'receipt_file_path') {
    $source = dbFetchOne('SELECT project_id, receipt_file_path, status FROM project_payment_evidence WHERE id = ?', [$sourceId]);
    if ($source) {
        $sourceExists = true;
        $projectId = (int)$source['project_id'];
        if ((string)$version['link_status'] === 'active'
            && (string)$source['receipt_file_path'] !== (string)$version['source_path_snapshot']) {
            http_response_code(404);
            exit('رابط إيصال الدفع المؤرشف يحتاج إلى تسوية.');
        }
    }
} else {
    http_response_code(404);
    exit('نوع مستند الأرشيف غير مدعوم.');
}

// A deleted source row may still have retained archive bytes. Resolve its project
// from the archive's own source event, then apply the same project authorization.
if (!$sourceExists) {
    $events = dbFetchAll(
        'SELECT metadata_json FROM document_archive_events WHERE document_id = ? AND source_table = ? AND source_record_id = ? ORDER BY id DESC LIMIT 20',
        [$documentId, $sourceTable, $sourceId]
    );
    foreach ($events as $event) {
        $metadata = json_decode((string)($event['metadata_json'] ?? ''), true);
        if (is_array($metadata) && isset($metadata['project_id'])) {
            $projectId = (int)$metadata['project_id'];
            if ($projectId > 0) break;
        }
    }
}

if ($projectId <= 0 || !akp_can_view_project($projectId)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($sourceTable === 'project_payment_evidence'
    && !in_array(akp_role(), ['admin','financial_manager','general_manager','vice_general_manager','projects_manager','accountant','accountant_staff'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

$normalizedStorageKey = str_replace(chr(92), '/', (string)$version['storage_key']);
$expectedPrefix = 'storage/documents/projects/' . $projectId . '/';
if (!str_starts_with($normalizedStorageKey, $expectedPrefix)
    || str_starts_with($normalizedStorageKey, '/')
    || in_array('..', explode('/', $normalizedStorageKey), true)
    || str_contains($normalizedStorageKey, "\0")) {
    http_response_code(404);
    exit('مسار النسخة المؤرشفة غير صالح.');
}
if ($sourceTable === 'project_payment_evidence'
    && !str_starts_with($normalizedStorageKey, $expectedPrefix . 'payments/')) {
    http_response_code(404);
    exit('مسار إيصال الدفع المؤرشف غير صالح.');
}

$root = realpath(dirname(__DIR__, 2));
$projectRoot = $root === false ? false : realpath(
    $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
    . 'documents' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $projectId
);
$path = $root === false ? false : realpath(
    $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedStorageKey)
);
if ($projectRoot === false || $path === false || !is_file($path)
    || strpos($path, rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('ملف النسخة المؤرشفة غير موجود.');
}

$actualSize = filesize($path);
$actualHash = hash_file('sha256', $path);
if ($actualSize === false || $actualHash === false
    || (int)$actualSize !== (int)$version['byte_size']
    || (string)$actualHash !== (string)$version['sha256']) {
    http_response_code(409);
    exit('تعذر تقديم النسخة لأن فحص سلامة الملف لم ينجح.');
}

$mime = trim((string)($version['mime_type_snapshot'] ?: $version['detected_mime_type']));
if ($mime === '') $mime = 'application/octet-stream';
$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$version['original_name']) ?: 'project-document';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$actualSize);
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
