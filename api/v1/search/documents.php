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
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'This role cannot search archived documents.']]);
}

$query = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
if (strlen($query) > 100) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'query_too_long', 'message' => 'Search text must not exceed 100 characters.']]);
}

try {
    $archiveSql = "
        SELECT archive_rows.*
        FROM (
            SELECT l.source_table, l.source_record_id, l.source_path_column,
                   l.document_id, l.current_version_id, l.source_path_snapshot,
                   v.id AS version_id, v.version_no,
                   CASE WHEN v.id = l.current_version_id THEN 1 ELSE 0 END AS is_current_version,
                   v.original_name, v.mime_type_snapshot, v.created_at AS version_created_at,
                   b.storage_status, d.document_kind,
                   COALESCE(NULLIF(d.display_title, ''), NULLIF(pd.title, ''), v.original_name) AS display_title,
                   p.id AS project_id, p.project_code, p.name AS project_name,
                   pd.document_type AS source_category
            FROM document_archive_source_links l
            INNER JOIN document_archive_documents d ON d.id = l.document_id
            INNER JOIN document_archive_versions v ON v.document_id = l.document_id
            INNER JOIN document_archive_blobs b ON b.id = v.blob_id
            LEFT JOIN project_documents pd ON l.source_table = 'project_documents'
                AND l.source_path_column = 'file_path'
                AND l.source_record_id = pd.id
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
            INNER JOIN other_projects p ON p.id = COALESCE(pd.project_id, ep.project_id)
            WHERE b.storage_status = 'present'
              AND ((l.link_status = 'active' AND pd.id IS NOT NULL AND l.source_path_snapshot = pd.file_path)
                OR (l.link_status = 'unlinked' AND pd.id IS NULL))

            UNION ALL

            SELECT l.source_table, l.source_record_id, l.source_path_column,
                   l.document_id, l.current_version_id, l.source_path_snapshot,
                   v.id AS version_id, v.version_no,
                   CASE WHEN v.id = l.current_version_id THEN 1 ELSE 0 END AS is_current_version,
                   v.original_name, v.mime_type_snapshot, v.created_at AS version_created_at,
                   b.storage_status, d.document_kind,
                   COALESCE(NULLIF(d.display_title, ''), CONCAT('إيصال دفع #', pe.id)) AS display_title,
                   p.id AS project_id, p.project_code, p.name AS project_name,
                   pe.payment_method AS source_category
            FROM document_archive_source_links l
            INNER JOIN document_archive_documents d ON d.id = l.document_id
            INNER JOIN document_archive_versions v ON v.document_id = l.document_id
            INNER JOIN document_archive_blobs b ON b.id = v.blob_id
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
            INNER JOIN other_projects p ON p.id = COALESCE(pe.project_id, ep.project_id)
            WHERE b.storage_status = 'present'
              AND ((l.link_status = 'active' AND pe.id IS NOT NULL
                    AND l.source_path_snapshot = pe.receipt_file_path
                    AND pe.status = 'documented'
                    AND pe.receipt_file_path IS NOT NULL
                    AND TRIM(pe.receipt_file_path) <> '')
                OR (l.link_status = 'unlinked' AND pe.id IS NULL))
        ) archive_rows
        WHERE (? = '' OR archive_rows.display_title LIKE ?
            OR archive_rows.original_name LIKE ?
            OR archive_rows.project_code LIKE ?
            OR archive_rows.project_name LIKE ?
            OR archive_rows.document_kind LIKE ?
            OR archive_rows.source_category LIKE ?)
        ORDER BY archive_rows.version_created_at DESC, archive_rows.document_id DESC
    ";
    $like = '%' . $query . '%';
    $candidates = dbFetchAll($archiveSql, [$query, $like, $like, $like, $like, $like, $like]);

    // Match the existing web Search Center and project document/receipt guards.
    $paymentReceiptRoles = ['admin', 'financial_manager', 'general_manager', 'vice_general_manager', 'projects_manager', 'accountant', 'accountant_staff'];
    $projectAccessCache = [];
    $visible = [];
    foreach ($candidates as $row) {
        if ($row['source_table'] === 'project_payment_evidence' && !in_array($webRole, $paymentReceiptRoles, true)) {
            continue;
        }
        $projectId = (int)$row['project_id'];
        if (!array_key_exists($projectId, $projectAccessCache)) {
            $projectAccessCache[$projectId] = akp_can_view_project($projectId, $webRole, $userId);
        }
        if (!$projectAccessCache[$projectId]) {
            continue;
        }
        $row['id'] = (int)$row['version_id'];
        $row['version_id'] = (int)$row['version_id'];
        $row['document_id'] = (int)$row['document_id'];
        $row['version_no'] = (int)$row['version_no'];
        $row['project_id'] = $projectId;
        $row['is_current_version'] = (int)$row['is_current_version'] === 1;
        $visible[] = $row;
    }

    $total = count($visible);
    $perPage = 20;
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $items = array_slice($visible, ($page - 1) * $perPage, $perPage);
    $items = array_map(static function (array $row): array {
        return [
            'id' => $row['id'],
            'version_id' => $row['version_id'],
            'document_id' => $row['document_id'],
            'version_no' => $row['version_no'],
            'is_current_version' => $row['is_current_version'],
            'original_name' => (string)$row['original_name'],
            'mime_type' => (string)($row['mime_type_snapshot'] ?? ''),
            'created_at' => (string)$row['version_created_at'],
            'document_kind' => (string)$row['document_kind'],
            'display_title' => (string)($row['display_title'] ?? ''),
            'project_id' => $row['project_id'],
            'project_code' => (string)$row['project_code'],
            'project_name' => (string)$row['project_name'],
            'source_category' => (string)($row['source_category'] ?? ''),
            'source_table' => (string)$row['source_table'],
        ];
    }, $items);

    ak_api_json(200, ['ok' => true, 'data' => [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile archived document search failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'document_search_failed', 'message' => 'Archived documents could not be searched.']]);
}
