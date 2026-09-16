<?php
// modules/accounting/fina_payment_review.php - FM review for standalone Fina Al-Khair collections
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once __DIR__.'/lib.php';
require_once __DIR__.'/lib_transaction_review.php';
require_once dirname(__DIR__,2).'/config/session.php';
Session::start();

$role=Session::getUserRole();
$uid=(int)Session::getUserId();
if(!ak_transaction_review_is_fm($role)){header('Location: '.APP_URL.'index.php');exit();}
$pageTitle='مراجعة دفعات فينا الخير';$active='fm_review';
ak_ensure_tables();ak_seed_accounts();

function fina_external_source_data($note): array{
    $data=json_decode((string)$note,true);
    return is_array($data)?$data:[];
}

function fina_external_source_label($note){
    $data=fina_external_source_data($note);
    if(!$data)return 'مصدر خارجي';
    $type=['person'=>'شخص','organization'=>'منظمة / جهة','other'=>'أخرى'][$data['source_type']??'other']??'أخرى';
    $name=trim((string)($data['source_name']??''));
    return $type.($name!==''?' — '.$name:'');
}

function fina_ensure_liability_account(): int{
    $row=dbFetchOne("SELECT id,account_type FROM accounts WHERE code='2300' LIMIT 1");
    if($row){
        if($row['account_type']!=='liability')throw new RuntimeException('الحساب 2300 موجود لكنه ليس حساب التزام.');
        return (int)$row['id'];
    }
    dbExecute("INSERT INTO accounts (code,name_ar,name_en,account_type,is_active,description) VALUES ('2300','التزام مستحق لفينا الخير','Fina Al-Khair Payable','liability',1,'100% of standalone Fina Al-Khair collections belongs to Fina Al-Khair')");
    $id=(int)dbLastInsertId();
    if($id<=0)throw new RuntimeException('تعذر إنشاء حساب الالتزام 2300 لفينا الخير.');
    return $id;
}

function fina_post_collection_journal(array $p,int $txnId,int $reviewerId): int{
    $existing=dbFetchOne("SELECT id FROM journal_entries WHERE reference_type='fina_collection' AND reference_id=? AND status='posted' LIMIT 1",[$txnId]);
    if($existing)return (int)$existing['id'];
    $liabilityId=fina_ensure_liability_account();
    $assetCode=ak_cash_code((string)($p['payment_method']??'cash'));
    $assetId=ak_account_id($assetCode);
    if($assetId<=0)throw new RuntimeException('الحساب المالي لطريقة الدفع غير موجود: '.$assetCode);
    $amount=round((float)$p['amount'],2);
    if($amount<=0)throw new RuntimeException('مبلغ تحصيل فينا الخير غير صالح.');
    $currency=(string)($p['currency_code']??'SDG');
    $entryNo=(int)(dbFetchOne("SELECT COALESCE(MAX(CASE WHEN entry_code REGEXP '^JE-[0-9]+$' THEN CAST(SUBSTRING(entry_code,4) AS UNSIGNED) ELSE 0 END),0) n FROM journal_entries")['n']??0)+1;
    $entryCode='JE-'.str_pad((string)$entryNo,6,'0',STR_PAD_LEFT);
    $date=!empty($p['payment_date'])?$p['payment_date']:date('Y-m-d',strtotime($p['created_at']));
    $desc='تحصيل فينا الخير — 100% التزام لصالح فينا الخير — '.$currency;
    dbExecute("INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by) VALUES (?,?,?,?,?,'posted',?)",[$entryCode,$date,$desc,'fina_collection',$txnId,$reviewerId]);
    $entryId=(int)dbLastInsertId();
    if($entryId<=0)throw new RuntimeException('تعذر إنشاء رأس القيد المحاسبي لفينا الخير.');
    dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)",[$entryId,$assetId,$amount,0,'استلام أموال مخصصة لفينا الخير']);
    dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)",[$entryId,$liabilityId,0,$amount,'التزام مستحق لفينا الخير']);
    return $entryId;
}

if($_SERVER['REQUEST_METHOD']==='POST'&&verify_csrf()&&isset($_POST['approve_fina'])){
    $paymentId=(int)$_POST['approve_fina'];$pdo=db();
    try{
        $pdo->beginTransaction();
        $q=dbFetchOne("SELECT sp.*,u.full_name supervisor_name FROM sponsor_payments sp JOIN users u ON u.id=sp.supervisor_id WHERE sp.id=? AND sp.status='pending' AND sp.payment_type='other' AND sp.sponsorship_id IS NULL AND sp.sponsor_id IS NULL AND sp.notes LIKE 'FINA_ONLY|%' FOR UPDATE",[$paymentId]);
        if(!$q)throw new RuntimeException('طلب فينا الخير غير موجود أو تمت معالجته.');
        if((int)$q['supervisor_id']===$uid)throw new RuntimeException('لا يجوز للمنشئ اعتماد تحصيله بنفسه.');
        $amount=round((float)$q['amount'],2);$currency=(string)($q['currency_code']??'SDG');
        $txnCode='SP-'.str_pad((string)$paymentId,6,'0',STR_PAD_LEFT);
        $paymentDate=!empty($q['payment_date'])?$q['payment_date']:date('Y-m-d',strtotime($q['created_at']));
        dbExecute("INSERT INTO transactions (sponsorship_id,amount,currency_code,payment_method,transaction_date,receipt_number,description,months_covered,transaction_type,receipt_path,unified_receipt_path,status,created_by,transaction_code,sponsor_id,project_id,payment_period,purpose_note,purpose,admin_fee_percent,admin_fee_amount,net_amount,admin_fee_method,admin_fee_value,admin_fee_policy_id) VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",[$amount,$currency,$q['payment_method']?:'cash',$paymentDate,'SP-'.$paymentId,'تحصيل فينا الخير — 100% مخصص لفينا الخير',0.00,'other',$q['receipt_file_path'],null,'posted',$uid,$txnCode,null,null,null,$q['purpose_note'],'fina_only',0.00,0.00,$amount,'none',0.00,null]);
        $txnId=(int)dbLastInsertId();if($txnId<=0)throw new RuntimeException('تعذر إنشاء المعاملة المحاسبية.');
        fina_post_collection_journal($q,$txnId,$uid);
        $affected=dbExecute("UPDATE sponsor_payments SET status='approved',reviewed_by_user_id=?,reviewed_at=NOW(),transaction_id=? WHERE id=? AND status='pending'",[$uid,$txnId,$paymentId]);
        if($affected!==1)throw new RuntimeException('تعذر اعتماد سجل تحصيل فينا الخير.');
        $pdo->commit();flash('success','تم اعتماد تحصيل فينا الخير وترحيله كالتزام مستقل بنسبة 100%.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error','تعذر اعتماد تحصيل فينا الخير والقيد المحاسبي بشكل ذري.');}
    header('Location: '.APP_URL.'modules/accounting/fina_payment_review.php');exit();
}

if($_SERVER['REQUEST_METHOD']==='POST'&&verify_csrf()&&isset($_POST['return_fina'])){
    $paymentId=(int)$_POST['return_fina'];$note=trim($_POST['return_note']??'');
    if($note===''){flash('error','يجب كتابة سبب الإرجاع.');}
    else{
        $q=dbFetchOne("SELECT id,supervisor_id FROM sponsor_payments WHERE id=? AND status='pending' AND payment_type='other' AND sponsorship_id IS NULL AND sponsor_id IS NULL AND notes LIKE 'FINA_ONLY|%'",[$paymentId]);
        if(!$q)flash('error','طلب فينا الخير غير موجود أو تمت معالجته.');
        elseif((int)$q['supervisor_id']===$uid)flash('error','لا يجوز للمنشئ إرجاع تحصيله بنفسه.');
        else{dbExecute("UPDATE sponsor_payments SET status='returned',reviewed_by_user_id=?,reviewed_at=NOW(),return_note=? WHERE id=? AND status='pending'",[$uid,$note,$paymentId]);flash('success','تم إرجاع دفعة فينا الخير للمشرف.');}
    }
    header('Location: '.APP_URL.'modules/accounting/fina_payment_review.php');exit();
}

$pending=dbFetchAll("SELECT sp.*,u.full_name supervisor_name FROM sponsor_payments sp JOIN users u ON u.id=sp.supervisor_id WHERE sp.status='pending' AND sp.payment_type='other' AND sp.sponsorship_id IS NULL AND sp.sponsor_id IS NULL AND sp.notes LIKE 'FINA_ONLY|%' ORDER BY COALESCE(sp.payment_date,DATE(sp.created_at)) ASC,sp.id ASC");
$history=dbFetchAll("SELECT sp.id,sp.amount,sp.currency_code,sp.payment_date,sp.status,sp.reviewed_at,sp.transaction_id,sp.other_source_note,r.full_name reviewer_name,u.full_name supervisor_name FROM sponsor_payments sp JOIN users u ON u.id=sp.supervisor_id LEFT JOIN users r ON r.id=sp.reviewed_by_user_id WHERE sp.payment_type='other' AND sp.sponsorship_id IS NULL AND sp.sponsor_id IS NULL AND sp.notes LIKE 'FINA_ONLY|%' AND sp.status IN ('approved','returned') ORDER BY sp.reviewed_at DESC LIMIT 30");
include dirname(__DIR__,2).'/includes/header.php';?>
<style>
.fina-review-table .fina-label{display:block;color:#6c757d;font-size:.74rem;font-weight:700;margin-bottom:2px}.fina-review-table .fina-value{font-weight:600}.fina-review-table .fina-review-actions{min-width:220px}.fina-review-table .fina-finance-grid{display:grid;grid-template-columns:repeat(2,minmax(140px,1fr));gap:10px}.fina-review-table .fina-finance-item{padding:8px 10px;border:1px solid #e5e9ef;border-radius:8px;background:#fff}.fina-review-table .fina-fina{color:#1b4d8f}.fina-review-table .fina-total{color:#212529}.fina-source-meta{font-size:.78rem;color:#495057;margin-top:6px;padding-top:6px;border-top:1px dashed #dfe3e8}.fina-source-meta span{display:inline-block;margin-inline-end:14px;margin-bottom:3px}.fina-source-meta i{width:16px;text-align:center;color:#6c757d}@media(max-width:991.98px){.fina-review-table .fina-finance-grid{grid-template-columns:1fr}.fina-review-table .fina-review-actions{min-width:190px}}
</style>
<div class="welcome-section fade-in"><h2><i class="fas fa-building-columns me-2"></i>مراجعة دفعات فينا الخير</h2><p>مراجعة التحصيلات الخارجية قبل الترحيل. كل مبلغ هنا مملوك لفينا الخير بنسبة 100% ولا يسجل كإيراد أو كفالة لأهل الخير.</p><div class="mt-2"><a class="btn btn-sm btn-secondary" href="<?php echo APP_URL; ?>modules/accounting/fm_dashboard.php"><i class="fas fa-arrow-right me-1"></i>العودة إلى لوحة المدير المالي</a></div></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php';?>
<div class="card mb-4"><div class="card-header text-white" style="background:#1b4d8f">طلبات بانتظار الاعتماد (<?php echo count($pending);?>)</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0 fina-review-table"><thead class="table-light"><tr><th>بيانات التحصيل</th><th>القيمة</th><th>الإيصال</th><th class="text-center">المراجعة</th></tr></thead><tbody><?php if(!$pending):?><tr><td colspan="4" class="text-center text-muted py-4">لا توجد دفعات فينا الخير معلقة.</td></tr><?php else:foreach($pending as $p):$source=fina_external_source_label($p['other_source_note']);$sourceData=fina_external_source_data($p['other_source_note']);$description=preg_replace('/^FINA_ONLY\|/','',(string)$p['notes']);?><tr><td><div class="row g-2"><div class="col-md-3"><span class="fina-label">المصدر</span><span class="fina-value"><?php echo e($source);?></span><div class="fina-source-meta"><?php if(!empty($sourceData['source_phone'])):?><span><i class="fas fa-phone"></i><?php echo e($sourceData['source_phone']);?></span><?php endif;?><?php if(!empty($sourceData['source_alt_phone'])):?><span><i class="fas fa-phone-volume"></i><?php echo e($sourceData['source_alt_phone']);?></span><?php endif;?><?php if(!empty($sourceData['source_email'])):?><span><i class="fas fa-envelope"></i><?php echo e($sourceData['source_email']);?></span><?php endif;?></div></div><div class="col-md-3"><span class="fina-label">بيانات إضافية للمصدر</span><span class="fina-value"><?php echo e($sourceData['contact_person']??'—');?></span><div class="fina-source-meta"><?php if(!empty($sourceData['source_id_number'])):?><span><i class="fas fa-id-card"></i><?php echo e($sourceData['source_id_number']);?></span><?php endif;?><?php if(!empty($sourceData['source_reference'])):?><span><i class="fas fa-hashtag"></i><?php echo e($sourceData['source_reference']);?></span><?php endif;?></div></div><div class="col-md-2"><span class="fina-label">العنوان / الموقع</span><span class="fina-value"><?php echo e($sourceData['source_address']??'—');?></span></div><div class="col-md-2"><span class="fina-label">المشرف</span><span class="fina-value"><?php echo e($p['supervisor_name']);?></span><div class="fina-source-meta"><?php echo e($p['payment_date']??date('Y-m-d',strtotime($p['created_at'])));?></div></div><div class="col-md-2"><span class="fina-label">ملاحظات المصدر</span><span class="fina-value"><?php echo e(($sourceData['source_details']??'')?:'—');?></span><div class="fina-source-meta"><?php echo e($description?:'—');?></div></div></div></td><td><div class="fina-finance-grid"><div class="fina-finance-item fina-total"><span class="fina-label">الإجمالي</span><strong><?php echo number_format((float)$p['amount'],2).' '.e($p['currency_code']);?></strong></div><div class="fina-finance-item fina-fina"><span class="fina-label">التزام فينا</span><strong><?php echo number_format((float)$p['amount'],2).' '.e($p['currency_code']);?></strong></div></div></td><td class="text-center align-middle"><?php if(!empty($p['receipt_file_path'])):?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo APP_URL;?>modules/transactions/receipt_sp.php?id=<?php echo (int)$p['id'];?>"><i class="fas fa-eye"></i><span class="d-none d-md-inline ms-1">عرض</span></a><?php else:?><span class="text-muted">لا يوجد</span><?php endif;?></td><td class="fina-review-actions align-middle"><form method="post" class="mb-2"><?php echo csrf_field();?><button name="approve_fina" value="<?php echo(int)$p['id'];?>" class="btn btn-sm btn-success w-100" onclick="return confirm('اعتماد تحصيل فينا الخير وترحيله كالتزام 100%؟')"><i class="fas fa-check me-1"></i>اعتماد وترحيل</button></form><form method="post" class="d-flex gap-1"><input name="return_note" class="form-control form-control-sm" required placeholder="سبب الإرجاع"><?php echo csrf_field();?><button name="return_fina" value="<?php echo(int)$p['id'];?>" class="btn btn-sm btn-danger"><i class="fas fa-undo"></i> إرجاع</button></form></td></tr><?php endforeach;endif;?></tbody></table></div></div></div>
<div class="card"><div class="card-header">سجل دفعات فينا الخير الأخيرة</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm"><thead><tr><th>تاريخ التحصيل</th><th>المصدر</th><th>بيانات الاتصال</th><th>المبلغ</th><th>العملة</th><th>الحالة</th><th>المراجع</th></tr></thead><tbody><?php if(!$history):?><tr><td colspan="7" class="text-center text-muted py-3">لا توجد دفعات معالجة بعد.</td></tr><?php else:foreach($history as $h):$historySource=fina_external_source_data($h['other_source_note']);?><tr><td><?php echo e($h['payment_date']??'');?></td><td><?php echo e(fina_external_source_label($h['other_source_note']));?></td><td><?php $contact=[];if(!empty($historySource['source_phone']))$contact[]=$historySource['source_phone'];if(!empty($historySource['source_email']))$contact[]=$historySource['source_email'];echo e(implode(' | ',$contact)?:'—');?></td><td><?php echo number_format((float)$h['amount'],2);?></td><td><?php echo e($h['currency_code']);?></td><td><?php echo $h['status']==='approved'?'<span class="badge bg-success">معتمد</span>':'<span class="badge bg-danger">مرتجع</span>';?></td><td><?php echo e($h['reviewer_name']??'—');?></td></tr><?php endforeach;endif;?></tbody></table></div></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php';?>