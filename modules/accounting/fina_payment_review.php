<?php
// modules/accounting/fina_payment_review.php - FM review for Fina Al-Khair intake
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once __DIR__.'/lib.php';
require_once __DIR__.'/lib_fina.php';
require_once __DIR__.'/lib_transaction_review.php';
Session::start();
$role=Session::getUserRole();$uid=(int)Session::getUserId();$allowed=['financial_manager','fm','finance','admin','sudo','general_manager','vice_general_manager'];
if(!in_array($role,$allowed,true)){header('Location: '.APP_URL.'index.php');exit();}
$pageTitle='مراجعة دفعات Fina Al-Khair';$active='fm_review';ak_ensure_tables();ak_seed_accounts();$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'&&verify_csrf()&&isset($_POST['approve_fina'])){
    $intakeId=(int)$_POST['approve_fina'];$pdo=db();
    try{ $pdo->beginTransaction();
        $q=dbFetchOne("SELECT fi.*,sp.*,s.full_name sponsor_name FROM fina_payment_intakes fi JOIN sponsor_payments sp ON sp.id=fi.sponsor_payment_id LEFT JOIN sponsors s ON s.id=sp.sponsor_id WHERE fi.id=? AND sp.status='pending' FOR UPDATE",[$intakeId]);
        if(!$q)throw new RuntimeException('طلب Fina غير موجود أو تمت معالجته.');
        $amount=round((float)$q['amount'],2);$fina=round((float)$q['fina_share_amount'],2);[$mode,$ahl,$fina]=ak_fina_validate_amounts($amount,$fina);
        $txnCode='SP-'.str_pad((string)$q['sponsor_payment_id'],6,'0','0');
        $txnType=$q['payment_type']==='monthly_sponsorship'?'sponsorship_payment':($q['payment_type']==='project_donation'?'project_donation':($q['payment_type']==='general_donation'?'general_donation':'other'));
        $policy=ak_get_admin_fee_policy((string)date('Y-m-d',strtotime($q['created_at'])));$calc=$txnType==='sponsorship_payment'?ak_fina_calculate_fee($amount,$fina,$policy):['method'=>'none','value'=>0.00,'amount'=>0.00,'net_amount'=>$amount-$fina,'policy_id'=>null];
        $desc='تحصيل Fina Al-Khair'.(!empty($q['payment_period'])?' للفترة: '.$q['payment_period']:'').(!empty($q['purpose_note'])?' — '.$q['purpose_note']:'');
        dbExecute("INSERT INTO transactions (sponsorship_id,amount,currency_code,payment_method,transaction_date,receipt_number,description,months_covered,transaction_type,receipt_path,unified_receipt_path,status,created_by,transaction_code,sponsor_id,project_id,payment_period,purpose_note,purpose,admin_fee_percent,admin_fee_amount,net_amount,admin_fee_method,admin_fee_value,admin_fee_policy_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",[$q['sponsorship_id'],$amount,$q['currency_code']?:'SDG',$q['payment_method']?:'cash',date('Y-m-d',strtotime($q['created_at'])),'SP-'.$q['sponsor_payment_id'],$desc,$txnType==='sponsorship_payment'?1.00:0.00,$txnType,$q['receipt_file_path'],$q['unified_receipt_path'],'posted',$uid,$txnCode,$q['sponsor_id'],$q['project_id'],$q['payment_period'],$q['purpose_note'],$q['payment_type'],0.00,$calc['amount'],round($amount-$calc['amount'],2),$calc['method'],$calc['value'],$calc['policy_id']]);
        $txnId=(int)dbLastInsertId();if($txnId<=0)throw new RuntimeException('تعذر إنشاء المعاملة.');
        $integration='FINA-ALLOC-TXN-'.$txnId;
        dbExecute("INSERT INTO fina_payment_allocations (transaction_id,sponsor_payment_id,allocation_mode,gross_amount,ahl_share_amount,fina_share_amount,ahl_admin_fee_amount,ahl_net_amount,settled_amount,currency_code,status,integration_reference,created_by) VALUES (?,?,?,?,?,?,?,?,0,?,'protected',?,?)",[$txnId,$q['sponsor_payment_id'],$mode,$amount,$ahl,$fina,$calc['amount'],round($ahl-$calc['amount'],2),$q['currency_code']?:'SDG',$integration,$uid]);
        $journalId=ak_fina_post_transaction_journal($txnId);if($journalId<=0)throw new RuntimeException('فشل إنشاء القيد المحاسبي.');
        dbExecute("UPDATE sponsor_payments SET status='approved',reviewed_by_user_id=?,reviewed_at=NOW(),transaction_id=? WHERE id=? AND status='pending'",[$uid,$txnId,$q['sponsor_payment_id']]);
        if(db()->rowCount()!==1)throw new RuntimeException('تعذر اعتماد سجل التحصيل.');
        ak_fina_update_obligation($txnId);
        db()->commit();flash('success','تم اعتماد دفعة Fina وإنشاء القيد المحاسبي وحماية حصة Fina بنجاح.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error','تعذر اعتماد دفعة Fina والقيد المحاسبي بشكل ذري.');}
    header('Location: '.APP_URL.'modules/accounting/fina_payment_review.php');exit();
}
if($_SERVER['REQUEST_METHOD']==='POST'&&verify_csrf()&&isset($_POST['return_fina'])){
    $intakeId=(int)$_POST['return_fina'];$note=trim($_POST['return_note']??'');if($note==='')flash('error','يجب كتابة سبب الإرجاع.');else{ $q=dbFetchOne("SELECT fi.sponsor_payment_id,sp.supervisor_id FROM fina_payment_intakes fi JOIN sponsor_payments sp ON sp.id=fi.sponsor_payment_id WHERE fi.id=? AND sp.status='pending'",[$intakeId]);if(!$q)flash('error','طلب Fina غير موجود أو تمت معالجته.');else{dbExecute("UPDATE sponsor_payments SET status='returned',reviewed_by_user_id=?,reviewed_at=NOW(),return_note=? WHERE id=? AND status='pending'",[$uid,$note,$q['sponsor_payment_id']]);flash('success','تم إرجاع دفعة Fina للمشرف للتعديل.');}}
    header('Location: '.APP_URL.'modules/accounting/fina_payment_review.php');exit();
}
$pending=dbFetchAll("SELECT fi.*,sp.amount,sp.currency_code,sp.payment_method,sp.payment_type,sp.payment_period,sp.purpose_note,sp.created_at,sp.sponsorship_id,s.full_name sponsor_name,u.full_name supervisor_name FROM fina_payment_intakes fi JOIN sponsor_payments sp ON sp.id=fi.sponsor_payment_id LEFT JOIN sponsors s ON s.id=sp.sponsor_id JOIN users u ON u.id=sp.supervisor_id WHERE sp.status='pending' ORDER BY sp.created_at ASC");
$history=dbFetchAll("SELECT sp.id,sp.amount,sp.currency_code,sp.payment_period,sp.status,sp.reviewed_at,sp.transaction_id,s.full_name sponsor_name,u.full_name supervisor_name,r.full_name reviewer_name,fi.fina_share_amount FROM sponsor_payments sp JOIN fina_payment_intakes fi ON fi.sponsor_payment_id=sp.id LEFT JOIN sponsors s ON s.id=sp.sponsor_id JOIN users u ON u.id=sp.supervisor_id LEFT JOIN users r ON r.id=sp.reviewed_by_user_id WHERE sp.status IN ('approved','returned') ORDER BY sp.reviewed_at DESC LIMIT 30");
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in"><h2><i class="fas fa-building-columns me-2"></i>مراجعة دفعات Fina Al-Khair</h2><p>مراجعة التخصيص قبل الترحيل. حصة Fina تصبح التزاماً على أهل الخير ولا تسجل كإيراد.</p></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<div class="card mb-4"><div class="card-header text-white" style="background:#1b4d8f">طلبات بانتظار الاعتماد (<?php echo count($pending); ?>)</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>الكفيل</th><th>المشرف</th><th>الفترة</th><th>الإجمالي</th><th>Fina</th><th>أهل الخير</th><th>طريقة الدفع</th><th>المراجعة</th></tr></thead><tbody><?php if(!$pending):?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد دفعات Fina معلقة.</td></tr><?php else:foreach($pending as $p):$ahl=max(0,(float)$p['amount']-(float)$p['fina_share_amount']);?><tr><td><?php echo e($p['sponsor_name']??'—'); ?></td><td><?php echo e($p['supervisor_name']); ?></td><td><?php echo e($p['payment_period']??'—'); ?></td><td><?php echo number_format((float)$p['amount'],2); ?> <?php echo e($p['currency_code']); ?></td><td><strong><?php echo number_format((float)$p['fina_share_amount'],2); ?></strong></td><td><?php echo number_format($ahl,2); ?></td><td><?php echo e($p['payment_method']); ?></td><td><form method="post" class="mb-1"><?php echo csrf_field(); ?><button name="approve_fina" value="<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-success w-100" onclick="return confirm('اعتماد دفعة Fina وإنشاء القيد؟')">اعتماد وترحيل</button></form><form method="post" class="d-flex gap-1"><?php echo csrf_field(); ?><input name="return_note" class="form-control form-control-sm" required placeholder="سبب الإرجاع"><button name="return_fina" value="<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-danger">إرجاع</button></form></td></tr><?php endforeach;endif;?></tbody></table></div></div></div>
<div class="card"><div class="card-header">سجل دفعات Fina الأخيرة</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm"><thead><tr><th>التاريخ</th><th>الكفيل</th><th>الإجمالي</th><th>Fina</th><th>الحالة</th><th>المراجع</th></tr></thead><tbody><?php foreach($history as $h):?><tr><td><?php echo e($h['reviewed_at']??''); ?></td><td><?php echo e($h['sponsor_name']??'—'); ?></td><td><?php echo number_format((float)$h['amount'],2); ?></td><td><?php echo number_format((float)$h['fina_share_amount'],2); ?></td><td><?php echo $h['status']==='approved'?'<span class="badge bg-success">معتمد</span>':'<span class="badge bg-danger">مرتجع</span>';?></td><td><?php echo e($h['reviewer_name']??'—'); ?></td></tr><?php endforeach;?></tbody></table></div></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>
