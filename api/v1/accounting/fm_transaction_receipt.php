<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_transaction_review.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];
if (!ak_transaction_review_is_fm($role)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Financial transaction receipts are not available for this account.']]);
}

$transactionId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($transactionId === false || $transactionId === null || $transactionId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_transaction_id', 'message' => 'A valid transaction ID is required.']]);
}
$kind = (string)($_GET['kind'] ?? 'own');
if (!in_array($kind, ['own', 'unified'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_receipt_kind', 'message' => 'Receipt kind must be own or unified.']]);
}

try {
    $transaction = dbFetchOne(
        'SELECT receipt_path, unified_receipt_path FROM transactions WHERE id = ? LIMIT 1',
        [(int)$transactionId]
    );
    if (!$transaction) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'receipt_not_found', 'message' => 'The transaction receipt was not found.']]);
    }
    $storedPath = trim((string)($kind === 'unified' ? ($transaction['unified_receipt_path'] ?? '') : ($transaction['receipt_path'] ?? '')));
    if ($storedPath === '') {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'receipt_not_found', 'message' => 'No receipt is attached to this transaction.']]);
    }

    $root = realpath(dirname(__DIR__, 3));
    if (!$root) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'receipt_file_missing', 'message' => 'The receipt file is unavailable.']]);
    }
    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($storedPath, '/\\'));
    $realPath = realpath($root . DIRECTORY_SEPARATOR . $relativePath);
    $prefix = $root . DIRECTORY_SEPARATOR;
    if (!$realPath || !is_file($realPath) || !str_starts_with($realPath, $prefix)) {
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'receipt_file_missing', 'message' => 'The receipt file is unavailable.']]);
    }

    $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    $mime = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ][$extension] ?? 'application/octet-stream';
    $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($realPath)) ?: 'transaction-receipt';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($realPath));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($realPath);
    exit;
} catch (Throwable $e) {
    error_log('Mobile ordinary transaction receipt delivery failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'receipt_delivery_failed', 'message' => 'The transaction receipt could not be opened.']]);
}
