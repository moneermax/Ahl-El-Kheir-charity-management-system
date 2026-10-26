<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/fina_lib.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)($user['role_code'] ?? '');
$allowed = ['admin','vice_general_manager','general_manager','supervisor','accountant','accountant_staff','financial_manager'];
if (!in_array($role, $allowed, true)) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Fina receipt access is not permitted.']]);
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_collection_id','message'=>'A valid collection ID is required.']]);
}

try {
    fina_ensure_tables();
    $row = dbFetchOne(
        'SELECT id,created_by,receipt_path FROM fina_collections WHERE id=? LIMIT 1',
        [$id]
    );
    if (!$row) {
        ak_api_json(404, ['ok'=>false,'error'=>['code'=>'collection_not_found','message'=>'Fina collection was not found.']]);
    }
    if ($role === 'supervisor' && (int)$row['created_by'] !== (int)$user['id']) {
        ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Supervisors may view only receipts for their own submissions.']]);
    }

    $relativePath = trim((string)($row['receipt_path'] ?? ''));
    if ($relativePath === '') {
        ak_api_json(404, ['ok'=>false,'error'=>['code'=>'receipt_not_found','message'=>'No receipt is attached to this collection.']]);
    }

    $root = realpath(dirname(__DIR__, 3));
    $real = $root !== false ? realpath($root . '/' . ltrim($relativePath, '/\\')) : false;
    if ($root === false || $real === false ||
        strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
        ak_api_json(404, ['ok'=>false,'error'=>['code'=>'receipt_not_found','message'=>'The receipt file is not available.']]);
    }

    $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg'=>'image/jpeg',
        'jpeg'=>'image/jpeg',
        'png'=>'image/png',
        'gif'=>'image/gif',
        'webp'=>'image/webp',
        'pdf'=>'application/pdf',
    ];
    $mime = $mimeTypes[$extension] ?? null;
    if ($mime === null) {
        ak_api_json(415, ['ok'=>false,'error'=>['code'=>'unsupported_receipt_type','message'=>'This receipt format cannot be displayed.']]);
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($real));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . rawurlencode(basename($real)) . '"');
    readfile($real);
    exit;
} catch (Throwable $e) {
    error_log('Mobile Fina receipt delivery failed: '.$e->getMessage());
    ak_api_json(500, ['ok'=>false,'error'=>['code'=>'fina_receipt_failed','message'=>'The receipt could not be retrieved.']]);
}
