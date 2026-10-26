<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/fina_lib.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_transaction_review.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$role = (string)($user['role_code'] ?? '');
if (!in_array($role, ['admin', 'financial_manager'], true)) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Fina collection registration is restricted to Finance Manager and Administrator.']]);
}

$input = ak_api_read_json();
$sourceType = trim((string)($input['source_type'] ?? 'person'));
$sponsorId = (int)($input['sponsor_id'] ?? 0);
$sourceName = trim((string)($input['source_name'] ?? ''));
$sourcePhone = trim((string)($input['source_phone'] ?? ''));
$sourceAltPhone = trim((string)($input['source_alt_phone'] ?? ''));
$sourceEmail = trim((string)($input['source_email'] ?? ''));
$sourceAddress = trim((string)($input['source_address'] ?? ''));
$sourceIdNumber = trim((string)($input['source_id_number'] ?? ''));
$sourceReference = trim((string)($input['source_reference'] ?? ''));
$contactPerson = trim((string)($input['contact_person'] ?? ''));
$sourceDetails = trim((string)($input['source_details'] ?? ''));
$amountRaw = trim((string)($input['amount'] ?? ''));
$method = trim((string)($input['method'] ?? 'cash'));
$date = trim((string)($input['date'] ?? date('Y-m-d')));
$purpose = trim((string)($input['purpose_note'] ?? ''));
$description = trim((string)($input['description'] ?? ''));

$fail = static function(int $status, string $code, string $message): never {
    ak_api_json($status, ['ok'=>false,'error'=>['code'=>$code,'message'=>$message]]);
};
if (!in_array($sourceType, ['sponsor','person','organization','other'], true)) $fail(422,'invalid_source_type','Unsupported source type.');
if (!in_array($method, ['cash','bank_transfer','credit_card','mobile','other'], true)) $fail(422,'invalid_payment_method','Unsupported payment method.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) $fail(422,'invalid_date','Collection date must be a valid YYYY-MM-DD date.');
if ($amountRaw === '' || !is_numeric(str_replace(',','',$amountRaw))) $fail(422,'invalid_amount','Amount must be a number greater than zero.');
$amount = round((float)str_replace(',','',$amountRaw), 2);
if ($amount <= 0 || $amount > 999999999999.99) $fail(422,'invalid_amount','Amount must be a valid number greater than zero.');
if (strlen($sourceName)>255 || strlen($sourcePhone)>80 || strlen($sourceAltPhone)>80 || strlen($sourceEmail)>255 || strlen($sourceAddress)>1000 || strlen($sourceIdNumber)>120 || strlen($sourceReference)>255 || strlen($contactPerson)>255 || strlen($sourceDetails)>5000 || strlen($purpose)>2000 || strlen($description)>5000) $fail(422,'field_too_long','One or more fields exceed the allowed length.');
if ($sourceEmail !== '' && !filter_var($sourceEmail, FILTER_VALIDATE_EMAIL)) $fail(422,'invalid_email','Source email address is invalid.');

try {
    fina_ensure_tables();
    $source = null;
    if ($sourceType === 'sponsor') {
        if ($sponsorId < 1) $fail(422,'sponsor_required','Select a valid active sponsor.');
        $source = fina_source_from_sponsor($sponsorId);
        if (!$source || (string)($source['status'] ?? '') !== 'active') $fail(422,'invalid_sponsor','The selected sponsor is not active or does not exist.');
        $sourceName = (string)$source['full_name'];
        $sourcePhone = (string)($source['phone'] ?? '');
        $sourceAltPhone = (string)($source['alt_phone'] ?? '');
        $sourceEmail = (string)($source['email'] ?? '');
        $sourceAddress = (string)($source['address'] ?? '');
    } elseif ($sourceName === '') {
        $fail(422,'source_name_required','Source name is required.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sourceId = 0;
        if ($sourceType === 'sponsor') {
            $existing = dbFetchOne("SELECT id FROM fina_sources WHERE source_type='sponsor' AND sponsor_id=? LIMIT 1", [$sponsorId]);
            $sourceId = (int)($existing['id'] ?? 0);
        }
        if ($sourceId < 1) {
            $isSponsor = $sourceType === 'sponsor';
            dbExecute(
                "INSERT INTO fina_sources (source_type,sponsor_id,source_name,source_phone,source_alt_phone,source_email,source_address,source_id_number,source_reference,contact_person,source_details,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [$sourceType,$isSponsor?$sponsorId:null,$isSponsor?null:($sourceName?:null),$isSponsor?null:($sourcePhone?:null),$isSponsor?null:($sourceAltPhone?:null),$isSponsor?null:($sourceEmail?:null),$isSponsor?null:($sourceAddress?:null),$isSponsor?null:($sourceIdNumber?:null),$isSponsor?null:($sourceReference?:null),$isSponsor?null:($contactPerson?:null),$isSponsor?null:($sourceDetails?:null),(int)$user['id']]
            );
            $sourceId = (int)dbLastInsertId();
        }
        if ($sourceId < 1) throw new RuntimeException('Unable to create Fina source.');
        dbExecute(
            "INSERT INTO fina_collections (fina_source_id,amount,currency_code,payment_method,collection_date,receipt_path,purpose_note,description,status,created_by) VALUES (?,?,?,?,?,NULL,?,?,'pending',?)",
            [$sourceId,$amount,APP_CURRENCY_CODE,$method,$date,$purpose?:null,$description?:null,(int)$user['id']]
        );
        $collectionId = (int)dbLastInsertId();
        if ($collectionId < 1) throw new RuntimeException('Unable to create Fina collection.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // Notification is deliberately isolated from the committed collection.
    ak_transaction_review_notify_fm_event(
        $collectionId, 'fina_collection', 'تحصيل فينا الخير بانتظار المراجعة',
        'يوجد تحصيل جديد لصالح فينا الخير بانتظار مراجعة واعتماد المدير المالي.',
        APP_URL . 'modules/accounting/fina_payment_review.php', (int)$user['id']
    );
    ak_api_json(201, ['ok'=>true,'data'=>['id'=>$collectionId,'status'=>'pending','receipt_supported'=>false]]);
} catch (Throwable $e) {
    error_log('Mobile Fina collection creation failed: '.$e->getMessage());
    $fail(500,'fina_collection_failed','The Fina collection could not be saved.');
}
