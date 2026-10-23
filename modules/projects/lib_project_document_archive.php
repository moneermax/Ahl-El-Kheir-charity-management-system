<?php
declare(strict_types=1);

/**
 * Project-document archive adapter.
 *
 * Call only inside the same database transaction as the business-record write.
 * Physical uploads are immutable; replacements add an archive version and never
 * delete the prior file. Existing business endpoints remain the access boundary.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

if (!function_exists('akp_archive_register_project_file')) {
    function akp_archive_register_project_file(
        string $sourceTable,
        int $sourceId,
        int $projectId,
        string $relativePath,
        string $originalName,
        string $mimeType,
        int $byteSize,
        ?string $displayTitle = null
    ): int {
        if (!db()->inTransaction()) {
            throw new RuntimeException('Project archive registration requires the source-record transaction.');
        }
        if ($sourceId <= 0 || $projectId <= 0 || $relativePath === '' || $originalName === '') {
            throw new RuntimeException('Project archive source metadata is incomplete.');
        }

        $pathColumn = '';
        $documentKind = '';
        if ($sourceTable === 'project_documents') {
            $pathColumn = 'file_path';
            $documentKind = 'project_document';
            $source = dbFetchOne(
                'SELECT id, project_id, file_path, title, original_name, mime_type, file_size FROM project_documents WHERE id = ? AND project_id = ?',
                [$sourceId, $projectId]
            );
        } elseif ($sourceTable === 'project_payment_evidence') {
            $pathColumn = 'receipt_file_path';
            $documentKind = 'project_payment_receipt';
            $source = dbFetchOne(
                'SELECT id, project_id, receipt_file_path, receipt_original_name, receipt_mime_type, status FROM project_payment_evidence WHERE id = ? AND project_id = ?',
                [$sourceId, $projectId]
            );
        } else {
            throw new RuntimeException('Unsupported project archive source table.');
        }

        if (!$source) {
            throw new RuntimeException('Project archive source record was not found.');
        }
        $sourcePath = (string)($source[$pathColumn] ?? '');
        $normalizedPath = str_replace('\\\\', '/', trim($relativePath));
        if ($sourcePath === '' || $sourcePath !== $normalizedPath || $relativePath !== $normalizedPath) {
            throw new RuntimeException('Project archive path does not match the committed source record.');
        }

        $expectedPrefix = 'storage/documents/projects/' . $projectId . '/';
        if (!str_starts_with($normalizedPath, $expectedPrefix)
            || str_starts_with($normalizedPath, '/')
            || preg_match('/^[A-Za-z]:\\//', $normalizedPath)
            || in_array('..', explode('/', $normalizedPath), true)
            || str_contains($normalizedPath, "\\0")) {
            throw new RuntimeException('Project archive path is outside the permitted project storage root.');
        }
        if ($sourceTable === 'project_payment_evidence'
            && !str_starts_with($normalizedPath, $expectedPrefix . 'payments/')) {
            throw new RuntimeException('Project payment evidence must remain under its payments storage directory.');
        }

        $root = realpath(dirname(__DIR__, 2));
        $projectRoot = $root === false ? false : realpath(
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
            . 'documents' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $projectId
        );
        $absolutePath = $root === false ? false : realpath(
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath)
        );
        if ($projectRoot === false || $absolutePath === false || !is_file($absolutePath)
            || strpos($absolutePath, rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) {
            throw new RuntimeException('Project archive file is missing or resolves outside project storage.');
        }

        $actualSize = filesize($absolutePath);
        $actualMime = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: '';
        $actualHash = hash_file('sha256', $absolutePath);
        if ($actualSize === false || $actualHash === false || $actualMime === '') {
            throw new RuntimeException('Unable to verify project archive file integrity.');
        }
        if ((int)$actualSize !== $byteSize || strtolower($actualMime) !== strtolower($mimeType)) {
            throw new RuntimeException('Uploaded file metadata does not match the stored project archive file.');
        }

        $actorId = (int)Session::getUserId();
        $blob = dbFetchOne(
            'SELECT id, sha256, byte_size, detected_mime_type, storage_status FROM document_archive_blobs WHERE storage_key = ? LIMIT 1',
            [$normalizedPath]
        );
        if ($blob) {
            if ((string)$blob['storage_status'] !== 'present'
                || (string)$blob['sha256'] !== (string)$actualHash
                || (int)$blob['byte_size'] !== (int)$actualSize
                || strtolower((string)$blob['detected_mime_type']) !== strtolower($actualMime)) {
                throw new RuntimeException('Existing archive blob metadata conflicts with the uploaded file; reconciliation is required.');
            }
            $blobId = (int)$blob['id'];
        } else {
            dbExecute(
                'INSERT INTO document_archive_blobs (storage_key, sha256, byte_size, detected_mime_type, storage_status, last_verified_at) VALUES (?, ?, ?, ?, \'present\', NOW())',
                [$normalizedPath, $actualHash, (int)$actualSize, $actualMime]
            );
            $blobId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
            if ($blobId <= 0) throw new RuntimeException('Unable to register project archive blob.');
        }

        $link = dbFetchOne(
            'SELECT id, document_id, current_version_id, source_path_snapshot, link_status FROM document_archive_source_links WHERE source_table = ? AND source_record_id = ? AND source_path_column = ? LIMIT 1',
            [$sourceTable, $sourceId, $pathColumn]
        );
        $isNewVersion = false;
        if ($link) {
            $documentId = (int)$link['document_id'];
            if ((string)$link['source_path_snapshot'] === $normalizedPath
                && (string)$link['link_status'] === 'active') {
                $current = dbFetchOne(
                    'SELECT v.id, v.blob_id FROM document_archive_versions v WHERE v.id = ? AND v.document_id = ?',
                    [(int)$link['current_version_id'], $documentId]
                );
                if ($current && (int)$current['blob_id'] === $blobId) return $documentId;
                throw new RuntimeException('Existing project archive link is inconsistent with its current version.');
            }
            $nextVersion = (int)(dbFetchOne(
                'SELECT COALESCE(MAX(version_no), 0) + 1 AS next_version FROM document_archive_versions WHERE document_id = ?',
                [$documentId]
            )['next_version'] ?? 0);
            $isNewVersion = true;
        } else {
            dbExecute(
                'INSERT INTO document_archive_documents (document_kind, display_title, created_by_user_id) VALUES (?, ?, ?)',
                [$documentKind, $displayTitle, $actorId > 0 ? $actorId : null]
            );
            $documentId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
            if ($documentId <= 0) throw new RuntimeException('Unable to register project archive document.');
            $nextVersion = 1;
        }

        $versionMime = $actualMime;
        $safeOriginalName = function_exists('mb_substr')
            ? mb_substr($originalName, 0, 255, 'UTF-8')
            : substr($originalName, 0, 255);
        dbExecute(
            'INSERT INTO document_archive_versions (document_id, blob_id, version_no, original_name, mime_type_snapshot, byte_size_snapshot, sha256_snapshot, uploaded_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$documentId, $blobId, $nextVersion, $safeOriginalName, $versionMime, (int)$actualSize, $actualHash, $actorId > 0 ? $actorId : null]
        );
        $versionId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
        if ($versionId <= 0) throw new RuntimeException('Unable to register project archive version.');

        if ($link) {
            dbExecute(
                'UPDATE document_archive_source_links SET current_version_id = ?, source_path_snapshot = ?, link_status = \'active\', linked_by_user_id = ?, linked_at = NOW() WHERE id = ?',
                [$versionId, $normalizedPath, $actorId > 0 ? $actorId : null, (int)$link['id']]
            );
        } else {
            dbExecute(
                'INSERT INTO document_archive_source_links (document_id, current_version_id, source_table, source_record_id, source_path_column, source_path_snapshot, link_status, linked_by_user_id) VALUES (?, ?, ?, ?, ?, ?, \'active\', ?)',
                [$documentId, $versionId, $sourceTable, $sourceId, $pathColumn, $normalizedPath, $actorId > 0 ? $actorId : null]
            );
        }

        $eventType = $isNewVersion ? 'version_registered' : 'upload_registered';
        $metadata = json_encode(
            ['project_id' => $projectId, 'storage_key' => $normalizedPath, 'byte_size' => (int)$actualSize, 'mime_type' => $actualMime],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        dbExecute(
            'INSERT INTO document_archive_events (event_type, document_id, version_id, blob_id, source_table, source_record_id, source_path_column, actor_user_id, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$eventType, $documentId, $versionId, $blobId, $sourceTable, $sourceId, $pathColumn, $actorId > 0 ? $actorId : null, $metadata]
        );

        return $documentId;
    }
}

if (!function_exists('akp_archive_unlink_project_source')) {
    function akp_archive_unlink_project_source(string $sourceTable, int $sourceId, int $projectId): void
    {
        if (!db()->inTransaction()) {
            throw new RuntimeException('Project archive unlink requires the source-record transaction.');
        }
        $pathColumn = $sourceTable === 'project_documents' ? 'file_path'
            : ($sourceTable === 'project_payment_evidence' ? 'receipt_file_path' : '');
        if ($pathColumn === '' || $sourceId <= 0 || $projectId <= 0) {
            throw new RuntimeException('Invalid project archive unlink source.');
        }
        $link = dbFetchOne(
            'SELECT l.id, l.document_id, l.current_version_id, l.source_path_snapshot FROM document_archive_source_links l WHERE l.source_table = ? AND l.source_record_id = ? AND l.source_path_column = ? LIMIT 1',
            [$sourceTable, $sourceId, $pathColumn]
        );
        if (!$link) return;
        $actorId = (int)Session::getUserId();
        dbExecute(
            'UPDATE document_archive_source_links SET link_status = \'unlinked\', linked_by_user_id = ? WHERE id = ?',
            [$actorId > 0 ? $actorId : null, (int)$link['id']]
        );
        $metadata = json_encode(['project_id' => $projectId, 'source_unlinked' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        dbExecute(
            'INSERT INTO document_archive_events (event_type, document_id, version_id, source_table, source_record_id, source_path_column, actor_user_id, metadata_json) VALUES (\'source_unlinked\', ?, ?, ?, ?, ?, ?, ?)',
            [(int)$link['document_id'], (int)$link['current_version_id'], $sourceTable, $sourceId, $pathColumn, $actorId > 0 ? $actorId : null, $metadata]
        );
    }
}
