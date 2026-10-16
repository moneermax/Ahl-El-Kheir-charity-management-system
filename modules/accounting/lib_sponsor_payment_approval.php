<?php
declare(strict_types=1);
/** FM sponsor-payment approval operation. No DDL; caller owns transaction boundaries. */
require_once __DIR__ . '/lib.php';
require_once dirname(__DIR__, 2) . '/modules/hr/lib_employee_incentives.php';
if (!function_exists('ak_approve_sponsor_payment')) {
function ak_approve_sponsor_payment(PDO $pdo,int $paymentId,int $reviewerUserId,?callable $newSponsorRecorder=null): array {
    if (!$pdo->inTransaction()) throw new LogicException('اعتماد التحصيل يتطلب معاملة قاعدة بيانات مفتوحة.');
    if ($pdo !== db()) throw new LogicException('اعتماد التحصيل يجب أن يستخدم اتصال قاعدة البيانات نفسه.');
    if ($paymentId<=0||$reviewerUserId<=0) throw new InvalidArgumentException('معرّف التحصيل أو المراجع غير صالح.');
    $purposeLabels=['monthly_sponsorship'=>'كفالة شهرية','school_fees'=>'رسوم دراسية','medicine'=>'علاج وأدوية','gift'=>'هدية/عيدية','other'=>'أخرى','admin_fee'=>'رسوم إدارية','general_donation'=>'تبرع عام','project_donation'=>'تبرع مشروع'];
    $typeMap=['monthly_sponsorship'=>'sponsorship_payment','school_fees'=>'general_donation','medicine'=>'general_donation','gift'=>'general_donation','other'=>'other','admin_fee'=>'admin_fee','general_donation'=>'general_donation','project_donation'=>'project_donation'];
    $sp=dbFetchOne("SELECT * FROM sponsor_payments WHERE id=? AND status='pending' FOR UPDATE",[$paymentId]);
    if(!$sp) throw new RuntimeException('الدفعة غير موجودة أو لم تعد معلقة.');
    $purposeLabel=$purposeLabels[$sp['payment_type']]??$sp['payment_type'];
    $transactionType=$typeMap[$sp['payment_type']]??'other';
    $description='تحصيل مشرف ('.$purposeLabel.')'.(!empty($sp['payment_period'])?' للفترة: '.$sp['payment_period']:'').(!empty($sp['purpose_note'])?' — '.$sp['purpose_note']:'');
    $transactionCode='SP-'.str_pad((string)$sp['id'],6,'0',STR_PAD_LEFT);
    $amount=round((float)$sp['amount'],2);
    if($amount<=0) throw new RuntimeException('مبلغ التحصيل غير صالح.');
    $policy=ak_get_admin_fee_policy_prepared((string)date('Y-m-d',strtotime((string)$sp['created_at'])));
    $calc=ak_calculate_admin_fee_prepared($amount,$policy);
    if($transactionType!=='sponsorship_payment'&&$transactionType!=='admin_fee') $calc=['method'=>'none','value'=>0.00,'amount'=>0.00,'net_amount'=>$amount,'policy_id'=>null];
    $sql="INSERT INTO transactions (sponsorship_id,amount,currency_code,payment_method,transaction_date,receipt_number,description,months_covered,transaction_type,receipt_path,unified_receipt_path,status,created_by,transaction_code,sponsor_id,project_id,payment_period,purpose_note,purpose,admin_fee_percent,admin_fee_amount,net_amount,admin_fee_method,admin_fee_value,admin_fee_policy_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    dbExecute($sql,[$sp['sponsorship_id'],$amount,$sp['currency_code']?:'SDG',$sp['payment_method']?:'cash',date('Y-m-d',strtotime((string)$sp['created_at'])),$transactionCode,$description,1.00,$transactionType,$sp['receipt_file_path'],$sp['unified_receipt_path'],'posted',$reviewerUserId,$transactionCode,$sp['sponsor_id'],$sp['project_id'],$sp['payment_period'],$sp['purpose_note'],$sp['payment_type'],0.00,$calc['amount'],$calc['net_amount'],$calc['method'],$calc['value'],$calc['policy_id']]);
    $transactionId=(int)dbLastInsertId();
    if($transactionId<=0) throw new RuntimeException('تعذر إنشاء المعاملة المحاسبية.');
    $journalId=ak_post_transaction_journal_prepared($transactionId,$reviewerUserId);
    if($journalId<=0) throw new RuntimeException('فشل إنشاء القيد المحاسبي.');
    $affected=dbExecute("UPDATE sponsor_payments SET status='approved',reviewed_by_user_id=?,reviewed_at=NOW(),transaction_id=? WHERE id=? AND status='pending'",[$reviewerUserId,$transactionId,$paymentId]);
    if($affected!==1) throw new RuntimeException('تعذر اعتماد الدفعة.');
    try {
        dbExecute("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?, 'APPROVE','sponsor_payments',?,?,?,?,?)",[$reviewerUserId,$paymentId,json_encode(['status'=>'pending'],JSON_UNESCAPED_UNICODE),json_encode(['status'=>'approved','txn_id'=>$transactionId,'journal_id'=>$journalId,'admin_fee_amount'=>$calc['amount'],'admin_fee_method'=>$calc['method'],'payment_method'=>$sp['payment_method']],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
    } catch(Throwable $auditError) {}
    if((string)$sp['payment_type']==='monthly_sponsorship'){
        $confirmedAtStmt=$pdo->prepare('SELECT reviewed_at FROM sponsor_payments WHERE id=?');$confirmedAtStmt->execute([$paymentId]);$confirmedAt=(string)$confirmedAtStmt->fetchColumn();
        if($confirmedAt==='') throw new RuntimeException('تعذر التحقق من وقت اعتماد الدفعة.');
        $collectorUserId=(int)$sp['supervisor_id'];$collectorEmployeeId=hrIncentiveEmployeeIdForUser($pdo,$collectorUserId);
        if($collectorEmployeeId>0) hrIncentiveRecordSourceEvent($pdo,'sponsor_payments',$paymentId,'collection',$confirmedAt,$amount,(string)($sp['currency_code']?:'SDG'),$reviewerUserId,$collectorUserId,$collectorEmployeeId);
        else error_log('Incentive collection event skipped: supervisor has no unique employee mapping. Payment ID='.$paymentId);
        if($newSponsorRecorder!==null) $newSponsorRecorder($pdo,$paymentId,$reviewerUserId);
        else hrIncentiveRecordNewSponsorPaymentEvent($pdo,$paymentId,$reviewerUserId);
        hrIncentiveRecordReactivationPaymentEvent($pdo,$paymentId,$reviewerUserId);
    }
    return ['payment_id'=>$paymentId,'payment_type'=>(string)$sp['payment_type'],'purpose_label'=>(string)$purposeLabel,'amount'=>$amount,'transaction_id'=>$transactionId,'journal_id'=>$journalId,'admin_fee'=>$calc];
}
}
