<?php
// modules/accounting/fm_review_queue.php - Financial Manager Review Queue
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_transaction_review.php';
Session::start();

$role=Session::getUserRole();
$allowed_fm=['financial_manager','fm','finance','admin','sudo','general_manager'];
if(!in_array($role,$allowed_fm,true)){header('Location: '.APP_URL.'index.php');exit();}
$pageTitle='طابور المراجعة المالية';$active='fm_review';
ak_ensure_tables();ak_seed_accounts();ak_ensure_admin_fee_policy_table();

$purposeLabels=['monthly_sponsorship'=>'كفالة شهرية','school_fees'=>'رسوم دراسية','medicine'=>'علاج وأدوية','gift'=>'هدية/عيدية','other'=>'أخرى','admin_fee'=>'رسوم إدارية','general_donation'=>'تبرع عام','project_donation'=>'تبرع مشروع'];
$methodLabels=['cash'=>'نقدي','bank_transfer'=>'تحويل بنكي','credit_card'=>'بطاقة','mobile'=>'محفظة إلكترونية','other'=>'أخرى'];
$typeMap=['monthly_sponsorship'=>'sponsorship_payment','school_fees'=>'general_donation','medicine'=>'general_donation','gift'=>'general_donation','other'=>'other','admin_fee'=>'admin_fee','general_donation'=>'general_donation','project_donation'=>'project_donation'];
$uid=(int)Session::getUserId();

if($_SERVER['REQUEST_METHOD']==='POST'&&verify_csrf()){
    if(isset($_POST['approve_payment'])){
        $sp_id=(int)$_POST['approve_payment'];
        $pdo=db();
        try{
            $pdo->beginTransaction();
            $sp=dbFetchOne("SELECT * FROM sponsor_payments WHERE id=? AND status='pending' FOR UPDATE",[$sp_id]);
            if(!$sp)throw new RuntimeException('الدفعة غير موجودة أو لم تعد معلقة.');
            $purLabel=$purposeLabels[$sp['payment_type']]??$sp['payment_type'];
            $txnType=$typeMap[$sp['payment_type']]??'other';
            $desc='تحصيل مشرف ('.$purLabel.')'.(!empty($sp['payment_period'])?' للفترة: '.$sp['payment_period']:'').(!empty($sp['purpose_note'])?' — '.$sp['purpose_note']:'');
            $txnCode='SP-'.str_pad((string)$sp['id'],6,'0',STR_PAD_LEFT);
            $amount=round((float)$sp['amount'],2);
            if($amount<=0)throw new RuntimeException('مبلغ التحصيل غير صالح.');
            $policy=ak_get_admin_fee_policy((string)date('Y-m-d',strtotime($sp['created_at'])));
            $calc=ak_calculate_admin_fee($amount,$policy);
            if($txnType!=='sponsorship_payment'&&$txnType!=='admin_fee'){$calc=['method'=>'none','value'=>0.00,'amount'=>0.00,'net_amount'=>$amount,'policy_id'=>null];}
            $sql="INSERT INTO transactions (sponsorship_id,amount,currency_code,payment_method,transaction_date,receipt_number,description,months_covered,transaction_type,receipt_path,unified_receipt_path,status,created_by,transaction_code,sponsor_id,project_id,payment_period,purpose_note,purpose,admin_fee_percent,admin_fee_amount,net_amount,admin_fee_method,admin_fee_value,admin_fee_policy_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            dbExecute($sql,[$sp['sponsorship_id'],$amount,$sp['currency_code']?:'SDG',$sp['payment_method']?:'cash',date('Y-m-d',strtotime($sp['created_at'])),$txnCode,$desc,1.00,$txnType,$sp['receipt_file_path'],$sp['unified_receipt_path'],'posted',$uid,$txnCode,$sp['sponsor_id'],$sp['project_id'],$sp['payment_period'],$sp['purpose_note'],$sp['payment_type'],0.00,$calc['amount'],$calc['net_amount'],$calc['method'],$calc['value'],$calc['policy_id']]);
            $txnId=(int)dbLastInsertId();if($txnId<=0)throw new RuntimeException('تعذر إنشاء المعاملة المحاسبية.');
            $journalId=ak_post_transaction_journal($txnId);if($journalId<=0)throw new RuntimeException('فشل إنشاء القيد المحاسبي.');
            $affected=dbExecute("UPDATE sponsor_payments SET status='approved',reviewed_by_user_id=?,reviewed_at=NOW(),transaction_id=? WHERE id=? AND status='pending'",[$uid,$txnId,$sp_id]);
            if($affected!==1)throw new RuntimeException('تعذر اعتماد الدفعة.');
            try{dbExecute("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?, 'APPROVE','sponsor_payments',?,?,?,?,?)",[$uid,$sp_id,json_encode(['status'=>'pending'],JSON_UNESCAPED_UNICODE),json_encode(['status'=>'approved','txn_id'=>$txnId,'journal_id'=>$journalId,'admin_fee_amount'=>$calc['amount'],'admin_fee_method'=>$calc['method'],'payment_method'=>$sp['payment_method']],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);}catch(Throwable $auditError){}
            $pdo->commit();
            try{$gmUsers=dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id=r.id WHERE r.code = 'general_manager' AND u.is_active=1");foreach($gmUsers as $u)ak_transaction_review_notify_event((int)$u['id'],'اعتماد تحصيل مشرف #'.$sp_id,'تم اعتماد تحصيل ('.$purLabel.') بمبلغ '.number_format($amount,2).' ج.س بواسطة المدير المالي.',APP_URL.'modules/accounting/fm_review_queue.php?payment_id='.$sp_id,$sp_id,'sponsor_payment_approved');}catch(Throwable $e){}
            flash('success','تم اعتماد الدفعة وترحيلها للخزينة والقيد المحاسبي بنجاح.');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error','تعذر اعتماد الدفعة والقيد المحاسبي بشكل ذري. لم يتم اعتماد أي جزء.');}
        header('Location: '.APP_URL.'modules/accounting/fm_review_queue.php');exit();
    }
    if(isset($_POST['return_payment'])){
        $sp_id=(int)$_POST['return_payment'];$note=trim($_POST['return_note_'.$sp_id]??'');
        if($note===''){flash('error','يجب كتابة سبب الإرجاع.');}
        else{
            $sp=dbFetchOne("SELECT * FROM sponsor_payments WHERE id=? AND status='pending'",[$sp_id]);
            if(!$sp)flash('error','الدفعة غير موجودة أو لم تعد معلقة.');
            else{
                $affected=dbExecute("UPDATE sponsor_payments SET status='returned',reviewed_by_user_id=?,reviewed_at=NOW(),return_note=? WHERE id=? AND status='pending'",[$uid,$note,$sp_id]);
                if($affected===1){try{dbExecute("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?, 'RETURN','sponsor_payments',?,?,?,?,?)",[$uid,$sp_id,json_encode(['status'=>'pending'],JSON_UNESCAPED_UNICODE),json_encode(['status'=>'returned','note'=>$note],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);}catch(Throwable $e){}try{ak_transaction_review_notify_event((int)$sp['supervisor_id'],'تم إرجاع تحصيل مشرف #'.$sp_id,'تم إرجاع التحصيل للمراجعة والتعديل. سبب الإرجاع: '.$note,APP_URL.'modules/transactions/create.php?sponsor_id='.(int)($sp['sponsor_id']??0).'&returned_payment='.$sp_id,$sp_id,'sponsor_payment_returned');}catch(Throwable $e){}flash('success','تم إرجاع الدفعة للمشرف.');}
                else flash('error','تعذر إرجاع الدفعة لأنها تغيرت بالفعل.');
            }
        }
        header('Location: '.APP_URL.'modules/accounting/fm_review_queue.php');exit();
    }
}

$payments=dbFetchAll("SELECT sp.*,u.full_name supervisor_name,spn.full_name sponsor_name,spn.sponsor_code,fc.child_name FROM sponsor_payments sp JOIN users u ON u.id=sp.supervisor_id LEFT JOIN sponsorships spon ON spon.id=sp.sponsorship_id LEFT JOIN sponsors spn ON spn.id=COALESCE(sp.sponsor_id,spon.sponsor_id) LEFT JOIN family_children fc ON fc.id=spon.child_id WHERE sp.status='pending' ORDER BY sp.created_at ASC");
$history=dbFetchAll("SELECT sp.*,u.full_name supervisor_name,spn.full_name sponsor_name,rev.full_name reviewer_name FROM sponsor_payments sp JOIN users u ON u.id=sp.supervisor_id LEFT JOIN sponsors spn ON spn.id=sp.sponsor_id LEFT JOIN users rev ON rev.id=sp.reviewed_by_user_id WHERE sp.status IN ('approved','returned') ORDER BY sp.reviewed_at DESC LIMIT 20");
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in"><h2><i class="fas fa-clipboard-check me-2"></i>طابور المراجعة المالية</h2><p>مراجعة واعتماد تحصيلات المشرفين. لا تصبح الدفعة معتمدة إلا بعد نجاح المعاملة والقيد المحاسبي معاً.</p></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<div class="card mb-4 fade-in"><div class="card-header text-white" style="background:#1b4d8f"><i class="fas fa-hourglass-half me-2"></i>التحصيلات المعلقة بانتظار الاعتماد (<?php echo count($payments); ?>)</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>التاريخ</th><th>المشرف</th><th>الكفيل / اليتيم</th><th>الغرض / الفترة</th><th>طريقة التحصيل</th><th>المبلغ</th><th>الإيصالات</th><th class="text-center" style="width:25%">المراجعة</th></tr></thead><tbody>
<?php if(!$payments): ?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد تحصيلات معلقة حالياً.</td></tr><?php else: foreach($payments as $p): ?><tr><td><?php echo date('Y-m-d H:i',strtotime($p['created_at'])); ?></td><td><strong><?php echo e($p['supervisor_name']); ?></strong></td><td><?php if($p['sponsor_name']): ?><small class="text-muted">كفيل:</small> <?php echo e($p['sponsor_name']); ?> <code><?php echo e($p['sponsor_code']??''); ?></code><br><?php endif; ?><?php if(!empty($p['child_name'])):?><small class="text-muted">يتيم:</small> <?php echo e($p['child_name']); ?><?php endif; ?></td><td><small><?php echo e($purposeLabels[$p['payment_type']]??$p['payment_type']); ?></small><?php if(!empty($p['payment_period'])):?><small class="text-muted d-block"><?php echo e($p['payment_period']); ?></small><?php endif; ?></td><td><?php echo e($methodLabels[$p['payment_method']??'cash']??'نقدي'); ?></td><td><strong><?php echo number_format((float)$p['amount'],2); ?></strong> ج.س</td><td><?php if($p['receipt_file_path']):?><a href="<?php echo APP_URL.'modules/transactions/receipt_sp.php?id='.(int)$p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a><?php endif;?><?php if(!empty($p['unified_receipt_path'])):?><a href="<?php echo APP_URL.'modules/transactions/receipt_sp.php?id='.(int)$p['id'].'&kind=unified'; ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-layer-group"></i></a><?php endif;?></td><td><form method="post" class="mb-1"><?php echo csrf_field(); ?><input type="hidden" name="approve_payment" value="<?php echo (int)$p['id']; ?>"><button type="submit" class="btn btn-sm btn-success w-100" onclick="return confirm('اعتماد الدفعة وإنشاء القيد المحاسبي؟')"><i class="fas fa-check"></i> اعتماد وترحيل</button></form><form method="post" class="d-flex gap-1"><?php echo csrf_field(); ?><input type="text" name="return_note_<?php echo (int)$p['id']; ?>" class="form-control form-control-sm" placeholder="سبب الإرجاع..." required><button type="submit" name="return_payment" value="<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-danger"><i class="fas fa-undo"></i></button></form></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
<div class="card fade-in"><div class="card-header"><i class="fas fa-history me-2"></i>سجل المراجعات الأخيرة</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light"><tr><th>التاريخ</th><th>المشرف</th><th>الكفيل</th><th>الغرض</th><th>طريقة التحصيل</th><th>المبلغ</th><th>الحالة</th><th>المراجع</th><th>ملاحظات</th></tr></thead><tbody><?php if(!$history):?><tr><td colspan="9" class="text-center text-muted py-3">لا يوجد سجل.</td></tr><?php else:foreach($history as $h):?><tr><td><?php echo e($h['reviewed_at']??''); ?></td><td><?php echo e($h['supervisor_name']); ?></td><td><?php echo e($h['sponsor_name']??'—'); ?></td><td><?php echo e($purposeLabels[$h['payment_type']]??$h['payment_type']); ?></td><td><?php echo e($methodLabels[$h['payment_method']??'cash']??'نقدي'); ?></td><td><?php echo number_format((float)$h['amount'],2); ?> ج.س</td><td><?php echo $h['status']==='approved'?'<span class="badge bg-success">معتمد</span>':'<span class="badge bg-danger">مرتجع</span>'; ?></td><td><?php echo e($h['reviewer_name']??'—'); ?></td><td><?php echo e($h['return_note']??'—'); ?></td></tr><?php endforeach;endif;?></tbody></table></div></div></div>

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/accounting/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>
