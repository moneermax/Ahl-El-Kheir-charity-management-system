<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('POST');
$user = ak_api_authenticated_user();
if ((string)($user['raw_role_code'] ?? '') !== 'nanny') {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Only the assigned nanny can confirm a family receipt.']]);
}
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
$input = str_contains($contentType, 'multipart/form-data') ? $_POST : ak_api_read_json();
if (trim((string)($input['action'] ?? '')) !== 'confirm_item') {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_action','message'=>'Unsupported disbursement action.']]);
}
$itemId = filter_var($input['item_id'] ?? null, FILTER_VALIDATE_INT);
if (!$itemId || $itemId < 1) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_item_id','message'=>'A valid family item ID is required.']]);
}
$file = $_FILES['receipt_file'] ?? null;
if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'receipt_required','message'=>'A family receipt image is required.']]);
}
if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'receipt_upload_failed','message'=>'The receipt upload could not be processed.']]);
}
if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 10 * 1024 * 1024) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_receipt_size','message'=>'Receipt must be no larger than 10 MB.']]);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
$allowedMimes = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
if (!is_string($mime) || !isset($allowedMimes[$mime])) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_receipt_type','message'=>'Receipt must be a JPG, PNG, or GIF image.']]);
}
$receiptDir = dirname(__DIR__, 3) . '/storage/receipts/family';
if (!is_dir($receiptDir) && !mkdir($receiptDir, 0775, true) && !is_dir($receiptDir)) {
    ak_api_json(500, ['ok'=>false,'error'=>['code'=>'receipt_storage_unavailable','message'=>'Receipt storage is unavailable.']]);
}
$fullPath = null;
$receiptPath = null;
$pdo = db();
try {
    $pdo->beginTransaction();
    $item = dbFetchOne(
        "SELECT i.id, i.family_id, i.disbursement_id, i.status AS item_status, d.nanny_id, d.status AS batch_status
         FROM disbursement_items i JOIN monthly_disbursements d ON d.id=i.disbursement_id
         WHERE i.id=? FOR UPDATE",
        [(int)$itemId]
    );
    if (!$item || (int)$item['nanny_id'] !== (int)$user['id']) {
        $pdo->rollBack();
        ak_api_json(404, ['ok'=>false,'error'=>['code'=>'item_not_found','message'=>'The family item was not found.']]);
    }
    if ((string)$item['item_status'] !== 'pending' || !in_array((string)$item['batch_status'], ['transferred','received'], true)) {
        $pdo->rollBack();
        ak_api_json(409, ['ok'=>false,'error'=>['code'=>'item_not_confirmable','message'=>'This family item is no longer available for confirmation.']]);
    }
    $filename = 'family_receipt_item_' . (int)$itemId . '_' . (int)$item['family_id'] . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimes[$mime];
    $fullPath = $receiptDir . '/' . $filename;
    $receiptPath = 'storage/receipts/family/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $fullPath)) {
        $pdo->rollBack();
        ak_api_json(500, ['ok'=>false,'error'=>['code'=>'receipt_save_failed','message'=>'The receipt file could not be saved.']]);
    }
    if (dbExecute("UPDATE disbursement_items SET status='paid', confirmed_at=NOW(), receipt_file_path=? WHERE id=? AND status='pending'", [$receiptPath,(int)$itemId]) !== 1) {
        throw new RuntimeException('The family item changed before confirmation could be saved.');
    }
    try {
        dbExecute(
            "INSERT INTO audit_log (user_id,action,entity_type,entity_id,new_values,ip_address,user_agent,created_at)
             VALUES (?,'DISBURSEMENT_CONFIRM_ITEM','monthly_disbursements',?,?,?, ?,NOW())",
            [(int)$user['id'],(int)$item['disbursement_id'],json_encode(['item_id'=>(int)$itemId,'family_id'=>(int)$item['family_id'],'receipt'=>$receiptPath,'status'=>'paid'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??'')]
        );
    } catch (Throwable $auditError) {
        error_log('Mobile disbursement confirmation audit failed: '.$auditError->getMessage());
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($fullPath !== null && is_file($fullPath)) @unlink($fullPath);
    error_log('Mobile disbursement item confirmation failed: '.$e->getMessage());
    ak_api_json(500, ['ok'=>false,'error'=>['code'=>'confirmation_failed','message'=>'The family receipt could not be confirmed.']]);
}
ak_api_json(200, ['ok'=>true,'data'=>['item_id'=>(int)$itemId,'disbursement_id'=>(int)$item['disbursement_id'],'status'=>'paid','message'=>'Family receipt confirmed.']]);
