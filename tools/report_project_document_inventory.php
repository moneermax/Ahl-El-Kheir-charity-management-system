<?php
declare(strict_types=1);

/**
 * Read-only inventory of project documents and project payment receipts.
 *
 * Usage from the repository root:
 *   D:\\xampp\\php\\php.exe tools\\report_project_document_inventory.php
 *
 * Reads only. Does not update the database, create report files, or modify files.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
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

$rows = [];

$documents = $pdo->query(
    "SELECT id, project_id, document_type AS category, title,
            file_path AS stored_path, original_name,
            mime_type AS recorded_mime, file_size AS recorded_size
     FROM project_documents
     ORDER BY project_id, id"
)->fetchAll();

foreach ($documents as $row) {
    $row['source_table'] = 'project_documents';
    $row['source_id'] = (int)$row['id'];
    $row['project_id'] = (int)$row['project_id'];
    $rows[] = $row;
}

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

foreach ($payments as $row) {
    $row['source_table'] = 'project_payment_evidence';
    $row['source_id'] = (int)$row['id'];
    $row['project_id'] = (int)$row['project_id'];
    $rows[] = $row;
}

$pathCounts = [];
foreach ($rows as $row) {
    $key = str_replace('\\', '/', trim((string)$row['stored_path']));
    if ($key !== '') {
        $pathCounts[$key] = ($pathCounts[$key] ?? 0) + 1;
    }
}

$hashCounts = [];
foreach ($rows as $index => $row) {
    $storedPath = trim((string)$row['stored_path']);
    $normalized = str_replace('\\', '/', $storedPath);
    $projectPrefix = 'storage/documents/projects/' . (int)$row['project_id'] . '/';
    $issues = [];
    $resolvedPath = null;
    $actualSize = null;
    $actualMime = null;
    $sha256 = null;

    if ($storedPath === '') {
        $issues[] = 'empty_path';
    } elseif (
        str_starts_with($normalized, '/') ||
        preg_match('/^[A-Za-z]:\\//', $normalized) ||
        str_contains($normalized, "\0")
    ) {
        $issues[] = 'absolute_or_invalid_path';
    } else {
        $segments = explode('/', $normalized);
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            $issues[] = 'path_traversal_segment';
        }
        if (!str_starts_with($normalized, $projectPrefix)) {
            $issues[] = 'path_outside_expected_project_prefix';
        }
    }

    if (!$issues && $storedPath !== '') {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
        $expectedDirectory = realpath(
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR .
            'documents' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR .
            (int)$row['project_id']
        );

        if ($candidate === false || !is_file($candidate)) {
            $issues[] = 'missing_file';
        } elseif ($expectedDirectory === false) {
            $issues[] = 'project_storage_directory_missing';
        } else {
            $expectedPrefix = rtrim($expectedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strpos($candidate, $expectedPrefix) !== 0) {
                $issues[] = 'resolved_path_outside_project_storage';
            } else {
                $resolvedPath = $candidate;
                $actualSize = filesize($candidate);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $actualMime = $finfo->file($candidate) ?: null;
                $sha256 = hash_file('sha256', $candidate) ?: null;

                if ($actualSize === false) {
                    $actualSize = null;
                }
                if ($row['recorded_size'] !== null && $actualSize !== null &&
                    (int)$row['recorded_size'] !== (int)$actualSize) {
                    $issues[] = 'file_size_mismatch';
                }
                $recordedMime = strtolower(trim((string)$row['recorded_mime']));
                $detectedMime = strtolower(trim((string)$actualMime));
                if ($recordedMime !== '' && $detectedMime !== '' && $recordedMime !== $detectedMime) {
                    $issues[] = 'mime_type_mismatch';
                }
                if ($sha256 !== null) {
                    $hashCounts[$sha256] = ($hashCounts[$sha256] ?? 0) + 1;
                }
            }
        }
    }

    $rows[$index]['inventory'] = [
        'normalized_path' => $normalized,
        'path_reference_count' => $pathCounts[$normalized] ?? 0,
        'resolved_path' => $resolvedPath,
        'file_exists_within_project_storage' => $resolvedPath !== null,
        'actual_size' => $actualSize,
        'actual_mime' => $actualMime,
        'sha256' => $sha256,
        'issues' => array_values(array_unique($issues)),
    ];
}

$summary = [
    'database' => DB_NAME,
    'read_only' => true,
    'source_tables' => ['project_documents', 'project_payment_evidence'],
    'project_document_rows' => count($documents),
    'payment_receipts_with_paths' => count($payments),
    'total_references' => count($rows),
    'missing_or_invalid_references' => 0,
    'shared_path_reference_rows' => 0,
    'duplicate_content_reference_rows' => 0,
    'metadata_issue_rows' => 0,
];

foreach ($rows as $row) {
    $inventory = $row['inventory'];
    if (!$inventory['file_exists_within_project_storage']) {
        $summary['missing_or_invalid_references']++;
    }
    if ($inventory['path_reference_count'] > 1) {
        $summary['shared_path_reference_rows']++;
    }
    if (!empty($inventory['issues'])) {
        $summary['metadata_issue_rows']++;
    }
    if ($inventory['sha256'] !== null && ($hashCounts[$inventory['sha256']] ?? 0) > 1) {
        $summary['duplicate_content_reference_rows']++;
    }
}

foreach ($rows as &$row) {
    $hash = $row['inventory']['sha256'];
    $row['inventory']['same_content_reference_count'] =
        $hash !== null ? ($hashCounts[$hash] ?? 0) : 0;
}
unset($row);

echo json_encode(
    ['summary' => $summary, 'records' => $rows],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
), PHP_EOL;
