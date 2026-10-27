<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_returned_funds.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'nanny') {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Returned-funds redelivery is limited to nanny accounts.']]);
}
$nannyId = (int)$user['id'];

try {
    returned_funds_ensure_table();
} catch (Throwable $e) {
    error_log('nanny returned-funds schema check failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'schema_unavailable', 'message' => 'Returned-funds service is not available because its required schema is missing.']]);
}

if ($method === 'GET') {
    $rows = dbFetchAll(
        "SELECT r.id, r.disbursement_item_id, r.amount, r.original_month,
                r.authorized_at, r.authorization_reason,
                f.family_code, f.mother_name, d.group_id, og.group_name
         FROM returned_disbursement_reissues r
         INNER JOIN disbursement_items i ON i.id = r.disbursement_item_id
         INNER JOIN monthly_disbursements d ON d.id = r.disbursement_id
         INNER JOIN families f ON f.id = r.family_id
         LEFT JOIN orphan_groups og ON og.id = d.group_id
         WHERE d.nanny_id = ? AND r.status = 'authorized' AND i.status = 'returned'
         ORDER BY r.authorized_at DESC, r.id DESC",
        [$nannyId]
    );
    $items = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'item_id' => (int)$row['disbursement_item_id'],
            'amount' => (float)$row['amount'],
            'month' => (string)$row['original_month'],
            'authorized_at' => (string)($row['authorized_at'] ?? ''),
            'authorization_reason' => (string)($row['authorization_reason'] ?? ''),
            'family_code' => (string)($row['family_code'] ?? ''),
            'mother_name' => (string)($row['mother_name'] ?? ''),
            'group_name' => (string)($row['group_name'] ?? ''),
        ];
    }, $rows);
    ak_api_json(200, ['ok' => true, 'data' => ['items' => $items, 'count' => count($items)]]);
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
if (!str_contains($contentType, 'multipart/form-data')) {
    ak_api_json(415, ['ok' => false, 'error' => ['code' => 'multipart_required', 'message' => 'A multipart request with a redelivery receipt is required.']]);
}
$reissueId = filter_var($_POST['reissue_id'] ?? null, FILTER_VALIDATE_INT);
if (!$reissueId || $reissueId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_reissue_id', 'message' => 'A valid returned-funds record is required.']]);
}
$record = dbFetchOne(
    "SELECT r.id, r.disbursement_item_id, r.family_id
     FROM returned_disbursement_reissues r
     INNER JOIN disbursement_items i ON i.id = r.disbursement_item_id
     INNER JOIN monthly_disbursements d ON d.id = r.disbursement_id
     WHERE r.id = ? AND r.status = 'authorized' AND i.status = 'returned' AND d.nanny_id = ?
     LIMIT 1",
    [(int)$reissueId, $nannyId]
);
if (!$record) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'redelivery_not_available', 'message' => 'This returned-funds record is no longer available for your account.']]);
}
$file = $_FILES['redelivery_receipt'] ?? null;
if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'receipt_required', 'message' => 'A family receipt is required to confirm redelivery.']]);
}
$upload = returned_funds_upload_receipt($file, (int)$record['disbursement_item_id'], (int)$record['family_id']);
if (empty($upload['success'])) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_receipt', 'message' => (string)($upload['message'] ?? 'The receipt could not be saved.')]]);
}
$result = returned_funds_redeliver((int)$reissueId, $nannyId, $nannyId, (string)$upload['path']);
if (empty($result['success'])) {
    if (!empty($upload['absolute_path']) && is_file((string)$upload['absolute_path'])) {
        @unlink((string)$upload['absolute_path']);
    }
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'redelivery_failed', 'message' => (string)($result['message'] ?? 'Redelivery could not be completed.')]]);
}
ak_api_json(200, [
    'ok' => true,
    'data' => [
        'id' => (int)$reissueId,
        'status' => 'redelivered',
        'transaction_id' => (int)$result['transaction_id'],
        'journal_id' => (int)$result['journal_id'],
    ],
]);
