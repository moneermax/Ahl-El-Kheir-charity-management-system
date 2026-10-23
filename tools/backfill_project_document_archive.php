<?php
declare(strict_types=1);

/**
 * Register existing project-document and project-payment-evidence paths in the
 * archive catalogue without moving, rewriting, or deleting physical files.
 *
 * Dry-run is the default. Add --apply to write catalogue metadata.
 *
 * Usage from the repository root:
 *   D:\\xampp\\php\\php.exe tools\\backfill_project_document_archive.php
 *   D:\\xampp\\php\\php.exe tools\\backfill_project_document_archive.php --apply
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo "Project document archive backfill\n";
    echo "Default: report-only dry run. Use --apply to register catalogue metadata.\n";
    exit(0);
}

$apply = in_array('--apply', $argv, true);
$unknownArgs = array_values(array_diff(array_slice($argv, 1), ['--apply']));
if ($unknownArgs !== []) {
    fwrite(STDERR, "Unknown argument(s): " . implode(', ', $unknownArgs) . "\n");
    exit(2);
}

if (DB_NAME !== 'ahl_el_kheir') {
    fwrite(STDERR, "Refusing to run: configured database is '" . DB_NAME . "', expected 'ahl_el_kheir'.\n");
    exit(2);
}

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(2);
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function archiveBackfillPathInspection(array $row, string $root): array
{
    $rawPath = (string)($row['stored_path'] ?? '');
    $path = trim($rawPath);
    $normalized = str_replace('\\', '/', $path);
    $projectId = (int)$row['project_id'];
    $expectedPrefix = 'storage/documents/projects/' . $projectId . '/';
    $issues = [];

    if ($path === '') {
        return [
            'eligible' => false,
            'storage_key' => null,
            'status' => 'skipped',
            'resolved_path' => null,
            'sha256' => null,
            'byte_size' => null,
            'detected_mime' => null,
            'issues' => ['empty_path'],
        ];
    }
    if ($rawPath !== $path) {
        $issues[] = 'path_has_outer_whitespace';
    }
    if (
        str_starts_with($normalized, '/') ||
        preg_match('/^[A-Za-z]:\//', $normalized) ||
        str_contains($normalized, "\0")
    ) {
        $issues[] = 'absolute_or_invalid_path';
    }

    $segments = explode('/', $normalized);
    if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
        $issues[] = 'path_traversal_segment';
    }
    if (!str_starts_with($normalized, $expectedPrefix)) {
        $issues[] = 'path_outside_expected_project_prefix';
    }

    if ($issues !== []) {
        return [
            'eligible' => false,
            'storage_key' => null,
            'status' => 'skipped',
            'resolved_path' => null,
            'sha256' => null,
            'byte_size' => null,
            'detected_mime' => null,
            'issues' => array_values(array_unique($issues)),
        ];
    }

    $candidatePath = $root . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $candidate = realpath($candidatePath);
    $projectDirectory = realpath(
        $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'documents' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR
        . $projectId
    );

    if ($candidate === false || !is_file($candidate)) {
        return [
            'eligible' => true,
            'storage_key' => $normalized,
            'status' => 'missing',
            'resolved_path' => null,
            'sha256' => null,
            'byte_size' => null,
            'detected_mime' => null,
            'issues' => ['missing_file'],
        ];
    }

    if ($projectDirectory === false) {
        return [
            'eligible' => false,
            'storage_key' => null,
            'status' => 'skipped',
            'resolved_path' => null,
            'sha256' => null,
            'byte_size' => null,
            'detected_mime' => null,
            'issues' => ['project_storage_directory_missing_for_existing_file'],
        ];
    }

    $expectedRealPrefix = rtrim($projectDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($candidate, $expectedRealPrefix) !== 0) {
        return [
            'eligible' => false,
            'storage_key' => null,
            'status' => 'skipped',
            'resolved_path' => null,
            'sha256' => null,
            'byte_size' => null,
            'detected_mime' => null,
            'issues' => ['resolved_path_outside_project_storage'],
        ];
    }

    $size = filesize($candidate);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($candidate) ?: null;
    $hash = hash_file('sha256', $candidate);

    if ($size === false) {
        $size = null;
        $issues[] = 'file_size_unavailable';
    }
    if ($hash === false) {
        $hash = null;
        $issues[] = 'sha256_unavailable';
    }

    if ($row['recorded_size'] !== null && $size !== null
        && (int)$row['recorded_size'] !== (int)$size) {
        $issues[] = 'file_size_mismatch';
    }
    $recordedMime = strtolower(trim((string)($row['recorded_mime'] ?? '')));
    $detectedMime = strtolower(trim((string)$mime));
    if ($recordedMime !== '' && $detectedMime !== '' && $recordedMime !== $detectedMime) {
        $issues[] = 'mime_type_mismatch';
    }

    return [
        'eligible' => true,
        'storage_key' => $normalized,
        'status' => 'present',
        'resolved_path' => $candidate,
        'sha256' => $hash,
        'byte_size' => $size,
        'detected_mime' => $mime,
        'issues' => array_values(array_unique($issues)),
    ];
}

function archiveBackfillName(array $row): string
{
    $name = trim((string)($row['original_name'] ?? ''));
    if ($name === '') {
        $name = basename(str_replace('\\', '/', (string)($row['stored_path'] ?? '')));
    }
    if ($name === '') {
        $name = 'document-' . (int)$row['source_id'];
    }

    return function_exists('mb_substr') ? mb_substr($name, 0, 255, 'UTF-8') : substr($name, 0, 255);
}

function archiveBackfillTitle(array $row): string
{
    $title = trim((string)($row['title'] ?? ''));
    if ($title !== '') {
        return function_exists('mb_substr') ? mb_substr($title, 0, 255, 'UTF-8') : substr($title, 0, 255);
    }

    $fallback = $row['source_table'] === 'project_payment_evidence'
        ? 'Project payment receipt #' . (int)$row['source_id']
        : 'Project document #' . (int)$row['source_id'];

    return $fallback;
}

$documents = $pdo->query(
    "SELECT id, project_id, document_type AS category, title,
            file_path AS stored_path, original_name,
            mime_type AS recorded_mime, file_size AS recorded_size
     FROM project_documents
     ORDER BY project_id, id"
)->fetchAll();

$payments = $pdo->query(
    "SELECT id, project_id, status AS category,
            CONCAT('Project payment receipt #', id) AS title,
            receipt_file_path AS stored_path,
            receipt_original_name AS original_name,
            receipt_mime_type AS recorded_mime,
            NULL AS recorded_size
     FROM project_payment_evidence
     WHERE receipt_file_path IS NOT NULL AND TRIM(receipt_file_path) <> ''
     ORDER BY project_id, id"
)->fetchAll();

$rows = [];
foreach ($documents as $row) {
    $row['source_table'] = 'project_documents';
    $row['source_id'] = (int)$row['id'];
    $row['source_path_column'] = 'file_path';
    $row['project_id'] = (int)$row['project_id'];
    $rows[] = $row;
}
foreach ($payments as $row) {
    $row['source_table'] = 'project_payment_evidence';
    $row['source_id'] = (int)$row['id'];
    $row['source_path_column'] = 'receipt_file_path';
    $row['project_id'] = (int)$row['project_id'];
    $rows[] = $row;
}

$sourceLookup = $pdo->prepare(
    "SELECT id, document_id, current_version_id, source_path_snapshot
     FROM document_archive_source_links
     WHERE source_table = ? AND source_record_id = ? AND source_path_column = ?
     LIMIT 1"
);
$blobLookup = $pdo->prepare(
    "SELECT id, storage_status, sha256, byte_size, detected_mime_type
     FROM document_archive_blobs
     WHERE storage_key = ?
     LIMIT 1"
);

$plan = [];
foreach ($rows as $row) {
    $inspection = archiveBackfillPathInspection($row, $root);
    $sourceLookup->execute([
        $row['source_table'],
        $row['source_id'],
        $row['source_path_column'],
    ]);
    $existingLink = $sourceLookup->fetch() ?: null;

    $action = 'would_register';
    if (!$inspection['eligible']) {
        $action = 'skipped_invalid_path';
    } elseif ($existingLink !== null) {
        $action = ((string)$existingLink['source_path_snapshot'] === (string)$row['stored_path'])
            ? 'already_registered'
            : 'existing_source_path_mismatch';
    }

    $existingBlob = null;
    if ($inspection['eligible']) {
        $blobLookup->execute([$inspection['storage_key']]);
        $existingBlob = $blobLookup->fetch() ?: null;
    }

    if ($existingLink !== null && $inspection['eligible']
        && (string)$existingLink['source_path_snapshot'] === (string)$row['stored_path']
        && $existingBlob !== null && $existingBlob['storage_status'] === 'missing'
        && $inspection['status'] === 'present') {
        $action = 'already_registered_needs_reconciliation';
    } elseif ($existingLink === null && $existingBlob !== null && $inspection['eligible']) {
        $metadataMismatch =
            (string)$existingBlob['storage_status'] !== (string)$inspection['status']
            || ($inspection['sha256'] !== null && (string)$existingBlob['sha256'] !== (string)$inspection['sha256'])
            || ($inspection['byte_size'] !== null && (int)$existingBlob['byte_size'] !== (int)$inspection['byte_size'])
            || ($inspection['detected_mime'] !== null
                && (string)$existingBlob['detected_mime_type'] !== (string)$inspection['detected_mime']);
        if ($metadataMismatch) {
            $action = 'existing_blob_metadata_mismatch';
        }
    }

    $plan[] = [
        'row' => $row,
        'inspection' => $inspection,
        'existing_link' => $existingLink,
        'existing_blob' => $existingBlob,
        'action' => $action,
    ];
}

$summary = [
    'database' => DB_NAME,
    'mode' => $apply ? 'apply' : 'dry_run',
    'source_tables' => ['project_documents', 'project_payment_evidence'],
    'project_document_rows' => count($documents),
    'payment_receipts_with_paths' => count($payments),
    'references_inspected' => count($plan),
    'would_register' => 0,
    'registered' => 0,
    'already_registered' => 0,
    'missing_file_references' => 0,
    'skipped_invalid_path' => 0,
    'existing_source_path_mismatch' => 0,
    'existing_blob_metadata_mismatch' => 0,
    'already_registered_needs_reconciliation' => 0,
    'warnings' => 0,
];

foreach ($plan as $item) {
    $action = $item['action'];
    if (array_key_exists($action, $summary)) {
        $summary[$action]++;
    }
    if ($item['inspection']['status'] === 'missing' && $item['inspection']['eligible']) {
        $summary['missing_file_references']++;
    }
    if ($item['inspection']['issues'] !== []) {
        $summary['warnings']++;
    }
}

if ($apply) {
    try {
        $pdo->beginTransaction();

        $findBlob = $pdo->prepare(
            "SELECT id FROM document_archive_blobs WHERE storage_key = ? LIMIT 1"
        );
        $insertBlob = $pdo->prepare(
            "INSERT INTO document_archive_blobs
                (storage_key, sha256, byte_size, detected_mime_type, storage_status, last_verified_at)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insertDocument = $pdo->prepare(
            "INSERT INTO document_archive_documents (document_kind, display_title, created_by_user_id)
             VALUES (?, ?, NULL)"
        );
        $insertVersion = $pdo->prepare(
            "INSERT INTO document_archive_versions
                (document_id, blob_id, version_no, original_name, mime_type_snapshot,
                 byte_size_snapshot, sha256_snapshot, uploaded_by_user_id)
             VALUES (?, ?, 1, ?, ?, ?, ?, NULL)"
        );
        $insertSource = $pdo->prepare(
            "INSERT INTO document_archive_source_links
                (document_id, current_version_id, source_table, source_record_id,
                 source_path_column, source_path_snapshot, link_status, linked_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NULL)"
        );
        $insertEvent = $pdo->prepare(
            "INSERT INTO document_archive_events
                (event_type, document_id, version_id, blob_id, source_table,
                 source_record_id, source_path_column, actor_user_id, metadata_json)
             VALUES ('backfill_registered', ?, ?, ?, ?, ?, ?, NULL, ?)"
        );

        foreach ($plan as $item) {
            if ($item['action'] !== 'would_register') {
                continue;
            }

            $row = $item['row'];
            $inspection = $item['inspection'];
            $blobId = null;

            $findBlob->execute([$inspection['storage_key']]);
            $blob = $findBlob->fetch() ?: null;
            if ($blob !== null) {
                $blobId = (int)$blob['id'];
            } else {
                $lastVerified = $inspection['status'] === 'present' ? date('Y-m-d H:i:s') : null;
                $insertBlob->execute([
                    $inspection['storage_key'],
                    $inspection['sha256'],
                    $inspection['byte_size'],
                    $inspection['detected_mime'],
                    $inspection['status'],
                    $lastVerified,
                ]);
                $blobId = (int)$pdo->lastInsertId();
            }

            $documentKind = $row['source_table'] === 'project_payment_evidence'
                ? 'project_payment_receipt'
                : 'project_document';
            $insertDocument->execute([$documentKind, archiveBackfillTitle($row)]);
            $documentId = (int)$pdo->lastInsertId();

            $versionMime = $inspection['detected_mime'];
            if ($versionMime === null || $versionMime === '') {
                $versionMime = trim((string)($row['recorded_mime'] ?? '')) ?: null;
            }

            $insertVersion->execute([
                $documentId,
                $blobId,
                archiveBackfillName($row),
                $versionMime,
                $inspection['byte_size'],
                $inspection['sha256'],
            ]);
            $versionId = (int)$pdo->lastInsertId();

            $insertSource->execute([
                $documentId,
                $versionId,
                $row['source_table'],
                $row['source_id'],
                $row['source_path_column'],
                (string)$row['stored_path'],
            ]);

            $eventMetadata = [
                'mode' => 'initial_metadata_backfill',
                'file_status' => $inspection['status'],
                'project_id' => (int)$row['project_id'],
                'recorded_mime_type' => $row['recorded_mime'],
                'recorded_byte_size' => $row['recorded_size'],
                'inventory_issues' => $inspection['issues'],
            ];
            $insertEvent->execute([
                $documentId,
                $versionId,
                $blobId,
                $row['source_table'],
                $row['source_id'],
                $row['source_path_column'],
                json_encode($eventMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ]);

            $summary['registered']++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "FAIL | project document archive backfill rolled back | " . $e->getMessage() . "\n");
        exit(1);
    }
}

$outputRows = [];
foreach ($plan as $item) {
    $row = $item['row'];
    $inspection = $item['inspection'];
    $action = $item['action'];
    if ($apply && $action === 'would_register') {
        $action = 'registered';
    }

    $outputRows[] = [
        'source_table' => $row['source_table'],
        'source_id' => (int)$row['source_id'],
        'project_id' => (int)$row['project_id'],
        'source_path_column' => $row['source_path_column'],
        'stored_path' => $row['stored_path'],
        'action' => $action,
        'file_status' => $inspection['status'],
        'storage_key' => $inspection['storage_key'],
        'sha256' => $inspection['sha256'],
        'byte_size' => $inspection['byte_size'],
        'detected_mime_type' => $inspection['detected_mime'],
        'issues' => $inspection['issues'],
    ];
}

echo json_encode(
    ['summary' => $summary, 'records' => $outputRows],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
), PHP_EOL;
