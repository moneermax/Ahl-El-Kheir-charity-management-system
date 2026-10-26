<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$rawRole = (string)($user['raw_role_code'] ?? $user['role_code'] ?? '');
$webRole = in_array($rawRole, ['fm', 'finance'], true) ? 'financial_manager' : $rawRole;

require_once dirname(__DIR__, 3) . '/config/search_permissions.php';
require_once dirname(__DIR__, 3) . '/modules/projects/project_lib.php';

if (!ak_search_can_type('documents', $rawRole)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'This role cannot access archived documents.']]);
}
$versionId = max(0, (int)($_GET['version_id'] ?? 0));
if ($versionId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_version_id', 'message' => 'A valid archive version ID is required.']]);
}

try {
    $sql = "
        SELECT l.source_table, l.source_record_id, l.source_path_column, l.source_path_snapshot, l.link_status,
               v.id AS version_id, v.original_name, v.mime_type_snapshot,
               b.storage_key, b.detected_mime_type, b.storage_status,
               p.id AS project_id, pe.status AS payment_status, pe.receipt_file_path
        FROM document_archive_source_links l
        INNER JOIN document_archive_documents d ON d.id = l.document_id
        INNER JOIN document_archive_versions v ON v.document_id = l.document_id
        INNER JOIN document_archive_blobs b ON b.id = v.blob_id
        LEFT JOIN project_documents pd ON l.source_table = 'project_documents'
            AND l.source_path_column = 'file_path'
            AND l.source_record_id = pd.id
        LEFT JOIN project_payment_evidence pe ON l.source_table = 'project_payment_evidence'
            AND l.source_path_column = 'receipt_file_path'
            AND l.source_record_id = pe.id
        LEFT JOIN (
            SELECT e.document_id, e.source_table, e.source_record_id,
                   MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(e.metadata_json, '$.project_id')) AS UNSIGNED)) AS project_id
            FROM document_archive_events e
            WHERE e.metadata_json IS NOT NULL
              AND JSON_EXTRACT(e.metadata_json, '$.project_id') IS NOT NULL
            GROUP BY e.document_id, e.source_table, e.source_record_id
        ) ep ON ep.document_id = l.document_id
            AND ep.source_table = l.source_table
            AND ep.source_record_id = l.source_record_id
        INNER JOIN other_projects p ON p.id = COALESCE(pd.project_id, pe.project_id, ep.project_id)
        WHERE v.id = ? AND b.storage_status = 'present'
          AND (
            (l.link_status = 'active' AND (
              (l.source_table = 'project_documents' AND pd.id IS NOT NULL AND l.source_path_snapshot = pd.file_path)
              OR
              (l.source_table = 'project_payment_evidence' AND pe.id IS NOT NULL
               AND l.source_path_snapshot = pe.receipt_file_path AND pe.status = 'documented'
               AND pe.receipt_file_path IS NOT NULL AND TRIM(pe.receipt_file_path) <> '')
            ))
            OR (l.link_status = 'unlinked' AND pd.id IS NULL AND pe.id IS NULL)
          )
        LIMIT 1
    ";
    $row = dbFetchOne($sql, [$versionId]);
    if (!$row) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'document_not_found', 'message' => 'The archived document version was not found.']]);
    }

    $paymentReceiptRoles = ['admin', 'financial_manager', 'general_manager', 'vice_general_manager', 'projects_manager', 'accountant', 'accountant_staff'];
    if ($row['source_table'] === 'project_payment_evidence' && !in_array($webRole, $paymentReceiptRoles, true)) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'document_not_found', 'message' => 'The archived document version was not found.']]);
    }
    $projectId = (int)$row['project_id'];
    if (!akp_can_view_project($projectId, $webRole, $userId)) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'document_not_found', 'message' => 'The archived document version was not found.']]);
    }

    $root = realpath(dirname(__DIR__, 3));
    if (!$root) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'document_file_missing', 'message' => 'The archived file is unavailable.']]);
    }
    $relativeKey = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim((string)$row['storage_key'], '/\\'));
    $path = realpath($root . DIRECTORY_SEPARATOR . $relativeKey);
    $expectedPrefix = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents'
        . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR;
    if (!$path || !is_file($path) || strpos($path, $expectedPrefix) !== 0) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'document_file_missing', 'message' => 'The archived file is unavailable.']]);
    }
    if ($row['source_table'] === 'project_payment_evidence') {
        $paymentPrefix = $expectedPrefix . 'payments' . DIRECTORY_SEPARATOR;
        if (strpos($path, $paymentPrefix) !== 0) {
            ak_api_json(404, ['ok' => false, 'error' => ['code' => 'document_file_missing', 'message' => 'The archived file is unavailable.']]);
        }
    }

    $detectedMime = strtolower(trim((string)($row['detected_mime_type'] ?: $row['mime_type_snapshot'] ?: 'application/octet-stream')));
    $inlineMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $isInline = in_array($detectedMime, $inlineMimes, true);
    $mime = $isInline ? $detectedMime : 'application/octet-stream';
    $name = basename((string)$row['original_name']);
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'archived-document';
    $disposition = $isInline ? 'inline' : 'attachment';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
} catch (Throwable $e) {
    error_log('Mobile archived document delivery failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'document_delivery_failed', 'message' => 'The archived document could not be opened.']]);
}
