<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_transaction_review.php';
ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$role = (string)($user['raw_role_code'] ?? '');
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
$input = str_contains($contentType, 'multipart/form-data') ? $_POST : ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$actionRoles = [
    'confirm_item' => ['nanny'],
    'create_batch' => ['admin', 'financial_manager', 'general_manager', 'accountant_staff'],
    'transfer_with_receipt' => ['admin', 'financial_manager', 'general_manager'],
    'close_with_return' => ['nanny'],
];
if (!isset($actionRoles[$action])) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_action','message'=>'Unsupported disbursement action.']]);
}
if (!in_array($role, $actionRoles[$action], true)) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'This role cannot perform the requested disbursement action.']]);
}

if ($action === 'create_batch') {
    $month = trim((string)($input['month'] ?? ''));
    if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_month','message'=>'Month must use YYYY-MM format.']]);
    }
    $rawGroupIds = $input['group_ids'] ?? null;
    if (!is_array($rawGroupIds) || !$rawGroupIds) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'groups_required','message'=>'Select at least one eligible group.']]);
    }
    if (count($rawGroupIds) > 200) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'too_many_groups','message'=>'Select no more than 200 groups at once.']]);
    }
    $groupIds = [];
    foreach ($rawGroupIds as $rawId) {
        $groupId = filter_var($rawId, FILTER_VALIDATE_INT);
        if (!$groupId || $groupId < 1) {
            ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_group_id','message'=>'Each selected group ID must be valid.']]);
        }
        $groupIds[] = (int)$groupId;
    }
    $groupIds = array_values(array_unique($groupIds));
    $created = [];
    $skipped = [];
    $userId = (int)$user['id'];
    foreach ($groupIds as $groupId) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $lockedGroup = dbFetchOne('SELECT id, verification_status FROM orphan_groups WHERE id=? FOR UPDATE', [$groupId]);
            if (!$lockedGroup) {
                $pdo->rollBack();
                $skipped[] = ['group_id'=>$groupId,'reason'=>'group_not_found'];
                continue;
            }
            if (!in_array((string)$lockedGroup['verification_status'], ['submitted','approved','pending'], true)) {
                $pdo->rollBack();
                $skipped[] = ['group_id'=>$groupId,'reason'=>'group_not_eligible'];
                continue;
            }
            $exists = dbFetchOne(
                "SELECT id FROM monthly_disbursements WHERE group_id=? AND month=? AND status NOT IN ('voided','cancelled') LIMIT 1",
                [$groupId,$month]
            );
            if ($exists) {
                $pdo->rollBack();
                $skipped[] = ['group_id'=>$groupId,'reason'=>'batch_exists'];
                continue;
            }
            $groupData = dbFetchOne(
                "SELECT nga.nanny_id, og.id AS group_id,
                        COUNT(DISTINCT fc.family_id) AS verified_families,
                        COALESCE(SUM(sp.monthly_amount),0) AS total_amount
                 FROM orphan_groups og
                 LEFT JOIN nanny_group_assignments nga ON nga.group_id=og.id AND nga.end_date IS NULL
                 LEFT JOIN group_children gc ON gc.group_id=og.id AND gc.left_date IS NULL
                 LEFT JOIN family_children fc ON fc.id=gc.child_id
                 INNER JOIN nanny_family_verifications v ON v.family_id=fc.family_id AND v.month=?
                    AND v.is_orphan_verified=1 AND v.is_mother_contact_verified=1 AND v.is_bank_verified=1
                 LEFT JOIN sponsorships sp ON sp.child_id=gc.child_id AND sp.status='active'
                 WHERE og.id=?
                 GROUP BY og.id, nga.nanny_id",
                [$month,$groupId]
            );
            if (!$groupData || empty($groupData['nanny_id'])) {
                $pdo->rollBack();
                $skipped[] = ['group_id'=>$groupId,'reason'=>'nanny_not_assigned'];
                continue;
            }
            if ($role === 'accountant_staff'
                && !dbFetchOne('SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id=? AND nanny_id=? LIMIT 1', [$userId,(int)$groupData['nanny_id']])) {
                $pdo->rollBack();
                $skipped[] = ['group_id'=>$groupId,'reason'=>'outside_assignment'];
                continue;
            }
            if ((int)$groupData['verified_families'] < 1) {
                $pdo->rollBack();
                $skipped[] = ['group_id'=>$groupId,'reason'=>'no_fully_verified_families'];
                continue;
            }
            dbExecute(
                "INSERT INTO monthly_disbursements
                 (month,nanny_id,group_id,total_amount,status,expense_account_code,created_by,submitted_by,submitted_at)
                 VALUES (?,?,?,?,'pending_approval','5110',?,?,NOW())",
                [$month,(int)$groupData['nanny_id'],$groupId,(float)$groupData['total_amount'],$userId,$userId]
            );
            $disbursementId = (int)$pdo->lastInsertId();
            $families = dbFetchAll(
                "SELECT fc.family_id, COALESCE(SUM(sp.monthly_amount),0) AS amount
                 FROM group_children gc
                 INNER JOIN family_children fc ON fc.id=gc.child_id
                 INNER JOIN nanny_family_verifications v ON v.family_id=fc.family_id
                    AND v.month=? AND v.is_orphan_verified=1 AND v.is_mother_contact_verified=1 AND v.is_bank_verified=1
                 LEFT JOIN sponsorships sp ON sp.child_id=gc.child_id AND sp.status='active'
                 WHERE gc.group_id=? AND gc.left_date IS NULL
                 GROUP BY fc.family_id",
                [$month,$groupId]
            );
            if (!$families) {
                $pdo->rollBack();
                $skipped[] = ['group_id'=>$groupId,'reason'=>'verified_family_list_empty'];
                continue;
            }
            foreach ($families as $family) {
                dbExecute(
                    "INSERT INTO disbursement_items
                     (disbursement_id,family_id,amount,status,is_orphan_verified,is_mother_contact_verified,is_bank_verified)
                     VALUES (?,?,?,'pending',1,1,1)",
                    [$disbursementId,(int)$family['family_id'],(float)$family['amount']]
                );
            }
            try {
                dbExecute(
                    "INSERT INTO audit_log (user_id,action,entity_type,entity_id,new_values,ip_address,user_agent,created_at)
                     VALUES (?,'DISBURSEMENT_CREATE','monthly_disbursements',?,?,?,?,NOW())",
                    [$userId,$disbursementId,json_encode(['month'=>$month,'group_id'=>$groupId,'items'=>count($families),'total_amount'=>(float)$groupData['total_amount']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??'')]
                );
            } catch (Throwable $auditError) {
                error_log('Mobile disbursement creation audit failed: '.$auditError->getMessage());
            }
            $pdo->commit();
            $created[] = ['group_id'=>$groupId,'disbursement_id'=>$disbursementId,'items'=>count($families),'total_amount'=>(float)$groupData['total_amount']];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Mobile disbursement batch creation failed: '.$e->getMessage());
            ak_api_json(500, ['ok'=>false,'error'=>['code'=>'batch_creation_failed','message'=>'A disbursement batch could not be created. No batch was committed for the failing group.']]);
        }
    }
    ak_api_json(200, ['ok'=>true,'data'=>['month'=>$month,'created'=>$created,'skipped'=>$skipped,'created_count'=>count($created),'skipped_count'=>count($skipped)]]);
}

if ($action === 'transfer_with_receipt') {
    $batchId = filter_var($input['batch_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$batchId || $batchId < 1) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_batch_id','message'=>'A valid disbursement batch ID is required.']]);
    }
    $file = $_FILES['transfer_receipt'] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'receipt_required','message'=>'A transfer receipt is required before the batch can be transferred.']]);
    }
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'receipt_upload_failed','message'=>'The transfer receipt upload could not be processed.']]);
    }
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 10 * 1024 * 1024) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_receipt_size','message'=>'Transfer receipt must be no larger than 10 MB.']]);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $transferMimes = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if (!is_string($mime) || !isset($transferMimes[$mime])) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_receipt_type','message'=>'Transfer receipt must be PDF, JPG, or PNG.']]);
    }
    $receiptDir = dirname(__DIR__, 3) . '/storage/receipts';
    if (!is_dir($receiptDir) && !mkdir($receiptDir, 0775, true) && !is_dir($receiptDir)) {
        ak_api_json(500, ['ok'=>false,'error'=>['code'=>'receipt_storage_unavailable','message'=>'Receipt storage is unavailable.']]);
    }
    $fullPath = null;
    $receiptPath = null;
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $batch = dbFetchOne('SELECT * FROM monthly_disbursements WHERE id=? FOR UPDATE', [(int)$batchId]);
        if (!$batch) {
            $pdo->rollBack();
            ak_api_json(404, ['ok'=>false,'error'=>['code'=>'batch_not_found','message'=>'The disbursement batch was not found.']]);
        }
        if ((string)$batch['status'] !== 'pending_approval') {
            $pdo->rollBack();
            ak_api_json(409, ['ok'=>false,'error'=>['code'=>'batch_not_pending_approval','message'=>'Only a batch awaiting approval can be transferred.']]);
        }
        $expenseCode = trim((string)($batch['expense_account_code'] ?: '5110'));
        $expenseAccount = dbFetchOne('SELECT id FROM accounts WHERE code=? LIMIT 1', [$expenseCode]);
        $cashAccount = dbFetchOne("SELECT id FROM accounts WHERE code='1100' LIMIT 1");
        if (!$expenseAccount || !$cashAccount) {
            $pdo->rollBack();
            ak_api_json(409, ['ok'=>false,'error'=>['code'=>'account_configuration_missing','message'=>'The expense or cash account required for this transfer is missing.']]);
        }
        $filename = 'transfer_receipt_' . (int)$batchId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $transferMimes[$mime];
        $fullPath = $receiptDir . '/' . $filename;
        $receiptPath = 'storage/receipts/' . $filename;
        if (!move_uploaded_file((string)$file['tmp_name'], $fullPath)) {
            $pdo->rollBack();
            ak_api_json(500, ['ok'=>false,'error'=>['code'=>'receipt_save_failed','message'=>'The transfer receipt could not be saved.']]);
        }
        $updated = dbExecute(
            "UPDATE monthly_disbursements
             SET status='transferred',transferred_at=NOW(),receipt_file_path=?,transfer_receipt_by_user_id=?,transfer_receipt_at=NOW()
             WHERE id=? AND status='pending_approval'",
            [$receiptPath,(int)$user['id'],(int)$batchId]
        );
        if ($updated !== 1) throw new RuntimeException('The batch state changed before transfer.');
        dbExecute(
            "INSERT INTO transactions
             (transaction_type,reference_number,description,amount,currency_code,payment_method,transaction_date,status,created_by,created_at,payment_period,purpose_note,admin_fee_percent,admin_fee_amount,net_amount)
             VALUES ('disbursement',CONCAT('DISB-OUT-',?),?,?, 'SDG','bank_transfer',CURDATE(),'posted',?,NOW(),?,'صرف دفعة كفالات',0,0,?)",
            [(int)$batchId,'صرف دفعة #' . (int)$batchId . ' شهر ' . (string)$batch['month'],(float)$batch['total_amount'],(int)$user['id'],(string)$batch['month'],(float)$batch['total_amount']]
        );
        $transactionId = (int)$pdo->lastInsertId();
        $entryCode = 'JE-DISB-' . (int)$batchId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        dbExecute(
            "INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by,created_at)
             VALUES (?,CURDATE(),?,'disbursement',?,'posted',?,NOW())",
            [$entryCode,'قيد صرف دفعة #' . (int)$batchId . ' - شهر ' . (string)$batch['month'],(int)$batchId,(int)$user['id']]
        );
        $journalId = (int)$pdo->lastInsertId();
        dbExecute(
            "INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,0,?)",
            [$journalId,(int)$expenseAccount['id'],(float)$batch['total_amount'],'صرف دفعة كفالات شهر ' . (string)$batch['month']]
        );
        dbExecute(
            "INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,0,?,?)",
            [$journalId,(int)$cashAccount['id'],(float)$batch['total_amount'],'خصم من الصندوق/البنك']
        );
        dbExecute('UPDATE monthly_disbursements SET transaction_id=? WHERE id=?', [$transactionId,(int)$batchId]);
        try {
            dbExecute(
                "INSERT INTO audit_log (user_id,action,entity_type,entity_id,new_values,ip_address,user_agent,created_at)
                 VALUES (?,'DISBURSEMENT_TRANSFER','monthly_disbursements',?,?,?,?,NOW())",
                [(int)$user['id'],(int)$batchId,json_encode(['status'=>'transferred','amount'=>(float)$batch['total_amount'],'receipt'=>$receiptPath,'transaction_id'=>$transactionId,'journal_id'=>$journalId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??'')]
            );
        } catch (Throwable $auditError) {
            error_log('Mobile disbursement transfer audit failed: '.$auditError->getMessage());
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($fullPath !== null && is_file($fullPath)) @unlink($fullPath);
        error_log('Mobile disbursement transfer failed: '.$e->getMessage());
        ak_api_json(500, ['ok'=>false,'error'=>['code'=>'transfer_failed','message'=>'The disbursement transfer could not be completed.']]);
    }
    try {
        ak_transaction_review_notify_event(
            (int)$batch['nanny_id'],
            'تم تحويل دفعة الكفالة وأصبحت متاحة للتنفيذ',
            'تم اعتماد تحويل دفعة الكفالة ويمكنك الآن متابعة وتأكيد بنود الأسر.',
            APP_URL . 'modules/accounting/disbursements.php?view=' . (int)$batchId,
            (int)$batchId,
            'disbursement_transferred'
        );
    } catch (Throwable $notificationError) {
        error_log('Mobile disbursement transfer notification failed: '.$notificationError->getMessage());
    }
    ak_api_json(200, ['ok'=>true,'data'=>['batch_id'=>(int)$batchId,'status'=>'transferred','transaction_id'=>$transactionId,'journal_id'=>$journalId,'receipt_file_path'=>$receiptPath]]);
}

if ($action === 'close_with_return') {
    $batchId = filter_var($input['batch_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$batchId || $batchId < 1) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_batch_id','message'=>'A valid disbursement batch ID is required.']]);
    }
    $reasonType = trim((string)($input['return_reason_type'] ?? ''));
    $reasonDetails = trim((string)($input['return_reason_details'] ?? ''));
    $reasonMap = [
        'انقطاع الاتصال مع الأم' => 'انقطاع الاتصال مع الأم',
        'تعذر الوصول للأسرة' => 'تعذر الوصول للأسرة',
        'رفض استلام المبلغ' => 'رفض استلام المبلغ',
        'تغيير بيانات الحساب البنكي' => 'تغيير بيانات الحساب البنكي',
        'انتقال الأسرة' => 'انتقال الأسرة',
        'وفاة المستفيد' => 'وفاة المستفيد',
        'إيقاف الكفالة' => 'إيقاف الكفالة',
        'سبب آخر' => 'سبب آخر',
    ];
    if (!isset($reasonMap[$reasonType])) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'return_reason_required','message'=>'Choose a clear reason for returning the outstanding amount.']]);
    }
    if ($reasonType === 'سبب آخر' && $reasonDetails === '') {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'return_reason_details_required','message'=>'Details are required when the reason is “Other”.']]);
    }
    $reason = $reasonMap[$reasonType];
    if ($reasonDetails !== '') $reason .= ' — ' . $reasonDetails;

    $file = $_FILES['return_receipt'] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'receipt_required','message'=>'A return receipt is required to close a partially paid batch.']]);
    }
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'receipt_upload_failed','message'=>'The return receipt upload could not be processed.']]);
    }
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 10 * 1024 * 1024) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_receipt_size','message'=>'Return receipt must be no larger than 10 MB.']]);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $returnMimes = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
    if (!is_string($mime) || !isset($returnMimes[$mime])) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_receipt_type','message'=>'Return receipt must be PDF, JPG, PNG, or GIF.']]);
    }
    $receiptDir = dirname(__DIR__, 3) . '/storage/receipts/returns';
    if (!is_dir($receiptDir) && !mkdir($receiptDir, 0775, true) && !is_dir($receiptDir)) {
        ak_api_json(500, ['ok'=>false,'error'=>['code'=>'receipt_storage_unavailable','message'=>'Return receipt storage is unavailable.']]);
    }

    $fullPath = null;
    $receiptPath = null;
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $batch = dbFetchOne(
            'SELECT id,month,nanny_id,group_id,total_amount,status,expense_account_code FROM monthly_disbursements WHERE id=? FOR UPDATE',
            [(int)$batchId]
        );
        if (!$batch || (int)$batch['nanny_id'] !== (int)$user['id']) {
            $pdo->rollBack();
            ak_api_json(404, ['ok'=>false,'error'=>['code'=>'batch_not_found','message'=>'The disbursement batch was not found.']]);
        }
        if ((string)$batch['status'] !== 'transferred') {
            $pdo->rollBack();
            ak_api_json(409, ['ok'=>false,'error'=>['code'=>'batch_not_returnable','message'=>'Only a transferred batch can be closed with a return.']]);
        }
        $pendingItems = dbFetchAll(
            "SELECT id,family_id,amount FROM disbursement_items WHERE disbursement_id=? AND status='pending' FOR UPDATE",
            [(int)$batchId]
        );
        if (!$pendingItems) {
            $pdo->rollBack();
            ak_api_json(409, ['ok'=>false,'error'=>['code'=>'no_pending_items','message'=>'There are no outstanding family amounts to return.']]);
        }
        $returnAmount = 0.0;
        foreach ($pendingItems as $pendingItem) $returnAmount += (float)$pendingItem['amount'];
        if ($returnAmount <= 0) {
            $pdo->rollBack();
            ak_api_json(409, ['ok'=>false,'error'=>['code'=>'invalid_return_amount','message'=>'The outstanding amount to return must be greater than zero.']]);
        }

        $originalExpense = dbFetchOne(
            "SELECT jl.account_id,a.code
             FROM journal_entries je
             INNER JOIN journal_lines jl ON jl.entry_id=je.id
             INNER JOIN accounts a ON a.id=jl.account_id
             WHERE je.reference_type='disbursement' AND je.reference_id=? AND je.status='posted' AND jl.debit>0
             ORDER BY jl.id ASC LIMIT 1",
            [(int)$batchId]
        );
        if ($originalExpense) {
            $expenseAccountId = (int)$originalExpense['account_id'];
            $expenseAccountCode = (string)$originalExpense['code'];
        } else {
            $fallbackCode = trim((string)($batch['expense_account_code'] ?? '5100'));
            $expenseAccount = dbFetchOne('SELECT id,code FROM accounts WHERE code=? LIMIT 1', [$fallbackCode]);
            if (!$expenseAccount) {
                $pdo->rollBack();
                ak_api_json(409, ['ok'=>false,'error'=>['code'=>'expense_account_missing','message'=>'The expense account for this batch could not be identified.']]);
            }
            $expenseAccountId = (int)$expenseAccount['id'];
            $expenseAccountCode = (string)$expenseAccount['code'];
        }
        $cashAccount = dbFetchOne("SELECT id,code FROM accounts WHERE code='1100' LIMIT 1");
        if (!$cashAccount) {
            $pdo->rollBack();
            ak_api_json(409, ['ok'=>false,'error'=>['code'=>'cash_account_missing','message'=>'Cash account 1100 is missing.']]);
        }
        $filename = 'return_receipt_' . (int)$batchId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $returnMimes[$mime];
        $fullPath = $receiptDir . '/' . $filename;
        $receiptPath = 'storage/receipts/returns/' . $filename;
        if (!move_uploaded_file((string)$file['tmp_name'], $fullPath)) {
            $pdo->rollBack();
            ak_api_json(500, ['ok'=>false,'error'=>['code'=>'receipt_save_failed','message'=>'The return receipt could not be saved.']]);
        }

        $entryCode = 'JE-RET-DISB-' . (int)$batchId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $description = 'قيد إرجاع المبلغ غير المصروف للدفعة #' . (int)$batchId . ' - شهر ' . (string)$batch['month'];
        dbExecute(
            "INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by,created_at)
             VALUES (?,CURDATE(),?,'disbursement_return',?,'posted',?,NOW())",
            [$entryCode,$description,(int)$batchId,(int)$user['id']]
        );
        $journalId = (int)$pdo->lastInsertId();
        dbExecute(
            'INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,0,?)',
            [$journalId,(int)$cashAccount['id'],$returnAmount,'إرجاع المبلغ غير المصروف إلى الصندوق - دفعة #' . (int)$batchId]
        );
        dbExecute(
            'INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,0,?,?)',
            [$journalId,$expenseAccountId,$returnAmount,'عكس المصروف غير المصروف - حساب ' . $expenseAccountCode]
        );
        foreach ($pendingItems as $pendingItem) {
            dbExecute(
                "UPDATE disbursement_items
                 SET status='returned',return_reason=?,returned_at=NOW(),returned_by_user_id=?,reversal_journal_id=?
                 WHERE id=? AND disbursement_id=? AND status='pending'",
                [$reason,(int)$user['id'],$journalId,(int)$pendingItem['id'],(int)$batchId]
            );
        }
        $updated = dbExecute(
            "UPDATE monthly_disbursements
             SET status='returned',return_note=?,returned_amount=?,return_receipt_file_path=?,
                 returned_by_user_id=?,returned_at=NOW(),return_receipt_by_user_id=?,return_receipt_at=NOW(),reversal_journal_id=?
             WHERE id=? AND nanny_id=? AND status='transferred'",
            [$reason,$returnAmount,$receiptPath,(int)$user['id'],(int)$user['id'],$journalId,(int)$batchId,(int)$user['id']]
        );
        $updatedBatch = dbFetchOne('SELECT status,returned_amount FROM monthly_disbursements WHERE id=?', [(int)$batchId]);
        if ($updated !== 1 || !$updatedBatch || (string)$updatedBatch['status'] !== 'returned'
            || abs((float)$updatedBatch['returned_amount'] - $returnAmount) > 0.005) {
            throw new RuntimeException('The returned batch could not be verified after the update.');
        }
        if (!empty($batch['group_id'])) {
            dbExecute(
                "UPDATE orphan_groups SET verification_status='closed',closed_at=NOW(),closed_by=? WHERE id=?",
                [(int)$user['id'],(int)$batch['group_id']]
            );
        }
        try {
            dbExecute(
                "INSERT INTO audit_log (user_id,action,entity_type,entity_id,new_values,ip_address,user_agent,created_at)
                 VALUES (?,'DISBURSEMENT_CLOSE_WITH_RETURN','monthly_disbursements',?,?,?,?,NOW())",
                [(int)$user['id'],(int)$batchId,json_encode(['month'=>$batch['month'],'group_id'=>$batch['group_id'],'returned_amount'=>$returnAmount,'pending_items_returned'=>count($pendingItems),'return_reason'=>$reason,'return_receipt'=>$receiptPath,'reversal_journal_id'=>$journalId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??'')]
            );
        } catch (Throwable $auditError) {
            error_log('Mobile disbursement return audit failed: '.$auditError->getMessage());
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($fullPath !== null && is_file($fullPath)) @unlink($fullPath);
        error_log('Mobile disbursement return failed: '.$e->getMessage());
        ak_api_json(500, ['ok'=>false,'error'=>['code'=>'return_failed','message'=>'The outstanding amount could not be returned.']]);
    }
    try {
        ak_transaction_review_notify_fm_event(
            (int)$batchId,
            'disbursement_returned',
            'تم إرجاع دفعة كفالة إلى الصندوق',
            'أقفلت الحاضنة الدفعة جزئياً وأُعيد المبلغ غير المصروف إلى الصندوق. يرجى مراجعة الإجراء.',
            APP_URL . 'modules/accounting/disbursements.php?view=' . (int)$batchId,
            (int)$user['id']
        );
    } catch (Throwable $notificationError) {
        error_log('Mobile disbursement return notification failed: '.$notificationError->getMessage());
    }
    ak_api_json(200, ['ok'=>true,'data'=>['batch_id'=>(int)$batchId,'status'=>'returned','returned_amount'=>$returnAmount,'journal_id'=>$journalId,'return_receipt_file_path'=>$receiptPath]]);
}

if ($action !== 'confirm_item') {
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
