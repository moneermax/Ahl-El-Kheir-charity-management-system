<?php
// modules/accounting/fm_transaction_review.php - Ordinary transaction FM review queue
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_transaction_review.php';
Session::start();

$role = Session::getUserRole();
$uid = (int)Session::getUserId();
if (!ak_transaction_review_is_fm($role)) { header('Location: ' . APP_URL . 'index.php'); exit(); }

$pageTitle = 'مراجعة الدفعات';
$active = 'fm_review';
ak_ensure_tables(); ak_seed_accounts();

$purposeLabels = [
    'monthly_sponsorship'=>'كفالة شهرية','school_fees'=>'رسوم دراسية','medicine'=>'علاج وأدوية','gift'=>'هدية/عيدية',
    'admin_fee'=>'رسوم إدارية','general_donation'=>'تبرع عام','project_donation'=>'تبرع مشروع','other'=>'أخرى'
];

$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()) {
    if (isset($_POST['approve_transaction'])) {
        $tid=(int)$_POST['approve_transaction'];
        $t=dbFetchOne("SELECT * FROM transactions WHERE id=? AND status='pending_fm_review'",[$tid]);
        if (!$t) flash('error','الدفعة غير موجودة أو لم تعد بانتظار المراجعة.');
        elseif ((int)$t['created_by']===$uid) flash('error','لا يجوز للمنشئ اعتماد معاملته بنفسه.');
        else {
            try {
                db()->beginTransaction();
                $old=['status'=>$t['status'],'fm_reviewed_by'=>$t['fm_reviewed_by'],'fm_reviewed_at'=>$t['fm_reviewed_at'],'fm_review_reason'=>$t['fm_review_reason']];
                $affected = dbExecute("UPDATE transactions SET status='posted', fm_reviewed_by=?, fm_reviewed_at=NOW(), fm_review_reason=NULL WHERE id=? AND status='pending_fm_review'",[$uid,$tid]);
                if ($affected!==1) throw new RuntimeException('تعذر اعتماد الدفعة.');
                $journalId=ak_post_transaction_journal($tid);
                if ($journalId<=0) throw new RuntimeException('فشل إنشاء القيد المحاسبي.');
                ak_transaction_review_audit($uid,'FM_APPROVE',$tid,$old,['status'=>'posted','journal_id'=>$journalId]);
                db()->commit();
                ak_transaction_review_notify_user((int)$t['created_by'],'تم اعتماد الدفعة','تم اعتماد الدفعة ' . ($t['transaction_code']??('#'.$tid)) . ' وترحيلها للقيد المحاسبي.','modules/transactions/index.php');
                flash('success','تم اعتماد الدفعة وترحيل القيد المحاسبي بنجاح.');
            } catch(Throwable $e) {
                if(db()->inTransaction()) db()->rollBack();
                flash('error','تعذر اعتماد الدفعة والقيد المحاسبي بشكل ذري. لم يتم اعتماد أي جزء.');
            }
        }
        header('Location: '.APP_URL.'modules/accounting/fm_transaction_review.php'); exit();
    }
    if (isset($_POST['return_transaction'])) {
        $tid=(int)$_POST['return_transaction']; $reason=trim($_POST['return_reason_'.$tid]??'');
        if($reason==='') flash('error','يجب كتابة سبب الإرجاع.');
        else {
            $t=dbFetchOne("SELECT * FROM transactions WHERE id=? AND status='pending_fm_review'",[$tid]);
            if(!$t) flash('error','الدفعة غير موجودة أو لم تعد بانتظار المراجعة.');
            elseif((int)$t['created_by']===$uid) flash('error','لا يجوز للمنشئ إرجاع معاملته بنفسه.');
            else {
                try {
                    db()->beginTransaction();
                    $affected = dbExecute("UPDATE transactions SET status='returned', fm_reviewed_by=?, fm_reviewed_at=NOW(), fm_review_reason=? WHERE id=? AND status='pending_fm_review'",[$uid,$reason,$tid]);
                    if($affected!==1) throw new RuntimeException('تعذر إرجاع الدفعة.');
                    ak_transaction_review_audit($uid,'FM_RETURN',$tid,['status'=>'pending_fm_review'],['status'=>'returned','reason'=>$reason]);
                    db()->commit();
                    ak_transaction_review_notify_user((int)$t['created_by'],'تم إرجاع الدفعة للتعديل','تم إرجاع الدفعة ' . ($t['transaction_code']??('#'.$tid)) . ' للتعديل. السبب: '.$reason,APP_URL.'modules/transactions/edit_returned.php?id='.(int)$tid);
                    flash('success','تم إرجاع الدفعة للمنشئ مع حفظ سبب الإرجاع.');
                } catch(Throwable $e) {
                    if(db()->inTransaction()) db()->rollBack();
                    flash('error','تعذر إرجاع الدفعة بشكل ذري.');
                }
            }
        }
        header('Location: '.APP_URL.'modules/accounting/fm_transaction_review.php'); exit();
    }
}

$pending=dbFetchAll("SELECT t.*, s.full_name AS sponsor_name, u.full_name AS creator_name FROM transactions t LEFT JOIN sponsors s ON s.id=t.sponsor_id LEFT JOIN users u ON u.id=t.created_by WHERE t.status='pending_fm_review' ORDER BY t.submitted_at ASC, t.id ASC");
$history=dbFetchAll("SELECT t.*, s.full_name AS sponsor_name, u.full_name AS creator_name, r.full_name AS reviewer_name FROM transactions t LEFT JOIN sponsors s ON s.id=t.sponsor_id LEFT JOIN users u ON u.id=t.created_by LEFT JOIN users r ON r.id=t.fm_reviewed_by WHERE t.status IN ('returned','posted','cancelled') AND t.fm_reviewed_at IS NOT NULL ORDER BY t.fm_reviewed_at DESC LIMIT 50");

include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in">
<h2><i class="fas fa-clipboard-check me-2"></i>مراجعة الدفعات المالية</h2>
<p>اعتماد أو إرجاع الدفعات العادية قبل إنشاء أي قيد محاسبي.</p>
<div class="mt-2"><a class="btn btn-sm btn-secondary" href="<?php echo APP_URL; ?>modules/accounting/fm_review_queue.php"><i class="fas fa-arrow-right me-1"></i>تحصيلات المشرفين</a></div>
</div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<div class="card mb-4 fade-in"><div class="card-header text-white" style="background:#1b4d8f"><i class="fas fa-hourglass-half me-2"></i>دفعات بانتظار الاعتماد (<?php echo count($pending); ?>)</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>الكود</th><th>التاريخ</th><th>المنشئ</th><th>الكفيل</th><th>الغرض</th><th>المبلغ</th><th>الإيصال</th><th class="text-center">المراجعة</th></tr></thead><tbody>
<?php if(!$pending): ?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد دفعات معلقة حالياً.</td></tr><?php else: foreach($pending as $t): ?>
<tr><td><code><?php echo e($t['transaction_code']); ?></code></td><td><?php echo e($t['submitted_at']??$t['transaction_date']); ?></td><td><?php echo e($t['creator_name']??'—'); ?></td><td><?php echo e($t['sponsor_name']??'—'); ?></td><td><?php echo e($purposeLabels[$t['purpose']??'']??($t['transaction_type']??'')); ?><br><small class="text-muted"><?php echo e($t['payment_period']??''); ?></small></td><td><strong><?php echo number_format((float)$t['amount'],2); ?></strong> <?php echo e($t['currency_code']); ?></td><td><?php if(!empty($t['receipt_path'])):?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo APP_URL; ?>modules/transactions/receipt_file.php?id=<?php echo (int)$t['id']; ?>"><i class="fas fa-eye"></i></a><?php else:?><span class="text-muted">—</span><?php endif;?></td><td style="min-width:230px"><form method="post" class="mb-1"><?php echo csrf_field(); ?><input type="hidden" name="approve_transaction" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-success w-100" type="submit" onclick="return confirm('اعتماد الدفعة وإنشاء القيد المحاسبي؟')"><i class="fas fa-check me-1"></i>اعتماد وترحيل</button></form><form method="post" class="d-flex gap-1"><?php echo csrf_field(); ?><input class="form-control form-control-sm" name="return_reason_<?php echo (int)$t['id']; ?>" placeholder="سبب الإرجاع" required><button class="btn btn-sm btn-danger" name="return_transaction" value="<?php echo (int)$t['id']; ?>" type="submit" onclick="return confirm('إرجاع الدفعة للمنشئ؟')"><i class="fas fa-undo"></i></button></form></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div>
<div class="card fade-in"><div class="card-header"><i class="fas fa-history me-2"></i>سجل المراجعات الأخيرة</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light"><tr><th>التاريخ</th><th>الكود</th><th>المنشئ</th><th>المراجع</th><th>المبلغ</th><th>الحالة</th><th>السبب</th></tr></thead><tbody>
<?php if(!$history): ?><tr><td colspan="7" class="text-center text-muted py-4">لا يوجد سجل مراجعات.</td></tr><?php else: foreach($history as $t): ?><tr><td><?php echo e($t['fm_reviewed_at']); ?></td><td><code><?php echo e($t['transaction_code']); ?></code></td><td><?php echo e($t['creator_name']??'—'); ?></td><td><?php echo e($t['reviewer_name']??'—'); ?></td><td><?php echo number_format((float)$t['amount'],2); ?></td><td><?php echo $t['status']==='posted'?'<span class="badge bg-success">مرحّلة</span>':($t['status']==='returned'?'<span class="badge bg-warning text-dark">مُعادة</span>':'<span class="badge bg-secondary">ملغاة</span>'); ?></td><td><?php echo e($t['fm_review_reason']??'—'); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>