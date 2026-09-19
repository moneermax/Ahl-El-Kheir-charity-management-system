<?php
/**
 * modules/accounting/opening_balance.php
 * Opening balance + organization administrative-fee policy activation point.
 * Currency: SDG.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'financial_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'الرصيد الافتتاحي';
$active = 'opening_balance';
ak_ensure_tables(); ak_seed_accounts(); ak_ensure_admin_fee_policy_table();
$uid = (int)Session::getUserId();
$errors = [];

$existingCount = (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_entries WHERE reference_type='opening_balance' AND status='posted'")['c'] ?? 0);
$existingEntries = dbFetchAll("SELECT je.id, je.entry_code, je.entry_date, je.status,
    (SELECT COALESCE(SUM(jl.debit),0) FROM journal_lines jl WHERE jl.entry_id=je.id) total
    FROM journal_entries je WHERE je.reference_type='opening_balance' ORDER BY je.id DESC");
$policy = ak_get_admin_fee_draft();
$activePolicy = ak_get_admin_fee_policy();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    if (isset($_POST['save_admin_fee_policy'])) {
        try {
            if (ak_has_live_opening_balance()) throw new RuntimeException('لا يمكن تعديل السياسة بعد تفعيل الرصيد الافتتاحي.');
            $method = trim((string)($_POST['admin_fee_method'] ?? 'none'));
            $value = (float)str_replace(',', '', (string)($_POST['admin_fee_value'] ?? 0));
            $effectiveFrom = trim((string)($_POST['admin_fee_effective_from'] ?? date('Y-m-d')));
            ak_save_admin_fee_policy($method, $value, $effectiveFrom, $uid);
            flash('success','تم حفظ سياسة الرسوم الإدارية كمسودة. ستصبح نافذة عند تفعيل الرصيد الافتتاحي.');
        } catch (Throwable $e) { flash('error',$e->getMessage()); }
        header('Location: '.APP_URL.'modules/accounting/opening_balance.php'); exit();
    }

    if (isset($_POST['save_opening_balance'])) {
        $cash=(float)str_replace(',','',(string)($_POST['cash']??0));
        $bank=(float)str_replace(',','',(string)($_POST['bank']??0));
        $wallet=(float)str_replace(',','',(string)($_POST['wallet']??0));
        $date=trim((string)($_POST['as_of_date']??''))?:date('Y-m-d');
        $confirmed=isset($_POST['confirm_understood']);
        if($cash<0||$bank<0||$wallet<0)$errors[]='لا يمكن إدخال مبلغ سالب.';
        $total=round($cash+$bank+$wallet,2);
        if($total<=0)$errors[]='أدخل مبلغاً واحداً على الأقل أكبر من صفر.';
        if(!$confirmed)$errors[]='يرجى تأكيد أن هذا هو الرصيد الفعلي الحقيقي قبل المتابعة.';
        if($existingCount>0)$errors[]='يوجد بالفعل رصيد افتتاحي فعال. لا يمكن إنشاء رصيد افتتاحي آخر من هذه الصفحة.';
        if(!$errors){
            $cashId=ak_account_id('1100');$bankId=ak_account_id('1200');$walletId=ak_account_id('1300');$obId=ak_account_id('3100');
            if(!$cashId||!$bankId||!$walletId||!$obId)$errors[]='أحد الحسابات الأساسية (1100/1200/1300/3100) غير موجود في دليل الحسابات.';
            else{
                $pdo=db();
                try{
                    $pdo->beginTransaction();
                    $policy=ak_get_admin_fee_draft();
                    if(!$policy){
                        dbExecute("INSERT INTO accounting_admin_fee_policies (policy_name,method,value,currency_code,effective_from,status,created_by) VALUES ('سياسة الرسوم الإدارية','none',0,'SDG',?,'draft',?)",[$date,$uid]);
                        $policy=ak_get_admin_fee_draft();
                    }
                    if(!$policy)throw new RuntimeException('تعذر إنشاء مسودة سياسة الرسوم الإدارية.');
                    $lastNo=(int)(dbFetchOne("SELECT COALESCE(MAX(CASE WHEN entry_code REGEXP '^JE-[0-9]+$' THEN CAST(SUBSTRING(entry_code,4) AS UNSIGNED) ELSE 0 END),0) max_no FROM journal_entries")['max_no']??0);
                    $code='JE-OB-'.str_pad((string)($lastNo+1),6,'0',STR_PAD_LEFT);
                    dbExecute("INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,status,created_by) VALUES (?,?,?,'opening_balance','posted',?)",[$code,$date,'الرصيد الافتتاحي الحقيقي للمنظمة',$uid]);
                    $eid=(int)dbLastInsertId();if($eid<=0)throw new RuntimeException('تعذر إنشاء رأس القيد المحاسبي.');
                    if($cash>0)dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,0,?)",[$eid,$cashId,$cash,'رصيد افتتاحي — نقدي']);
                    if($bank>0)dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,0,?)",[$eid,$bankId,$bank,'رصيد افتتاحي — بنكي']);
                    if($wallet>0)dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,0,?)",[$eid,$walletId,$wallet,'رصيد افتتاحي — محفظة إلكترونية']);
                    dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,0,?,?)",[$eid,$obId,$total,'الأرصدة الافتتاحية']);
                    $check=dbFetchOne("SELECT COALESCE(SUM(debit),0) d,COALESCE(SUM(credit),0) c,COUNT(*) n FROM journal_lines WHERE entry_id=?",[$eid]);
                    if((int)$check['n']<2||round((float)$check['d'],2)!==round((float)$check['c'],2)||round((float)$check['d'],2)!==$total)throw new RuntimeException('فشل التحقق من توازن الرصيد الافتتاحي بعد الحفظ.');
                    ak_activate_admin_fee_policy((int)$policy['id'],$eid,$uid);
                    try{dbExecute("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?, 'CREATE_OPENING_BALANCE','journal_entries',?,NULL,?,?,?)",[$uid,$eid,json_encode(['cash'=>$cash,'bank'=>$bank,'wallet'=>$wallet,'admin_fee_method'=>$policy['method'],'admin_fee_value'=>$policy['value'],'currency'=>'SDG'],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);}catch(Throwable $auditError){}
                    $pdo->commit();
                    flash('success','تم تفعيل الرصيد الافتتاحي وسياسة الرسوم الإدارية بنجاح: '.number_format($total,2).' ج.س ('.$code.')');
                    header('Location: '.APP_URL.'modules/accounting/journal.php?view='.$eid);exit();
                }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors[]='تعذر تسجيل الرصيد الافتتاحي بالكامل. لم يتم حفظ أي جزء منه.';}
            }
        }
    }
}

$policy=ak_get_admin_fee_draft();$activePolicy=ak_get_admin_fee_policy();
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-vault me-2"></i>الرصيد الافتتاحي</h2>
    <p>هذه الصفحة هي نقطة تفعيل الأساس المحاسبي للمنظمة. قبل التفعيل يمكنك تحديد سياسة الرسوم الإدارية التي ستستخدمها الحسابات المستقبلية. جميع المبالغ بالنظام بعملة <strong>ج.س (SDG)</strong>.</p>
</div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<?php if($errors): ?><div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach($errors as $er)echo '<li>'.e($er).'</li>'; ?></ul></div><?php endif; ?>

<div class="card mb-4 fade-in border-primary">
    <div class="card-header bg-primary text-white"><i class="fas fa-percent me-2"></i>سياسة الرسوم الإدارية</div>
    <div class="card-body">
        <?php if($activePolicy): ?>
            <div class="alert alert-success mb-0"><strong>السياسة مفعلة ومقفلة.</strong> الطريقة: <?php echo e(ak_admin_fee_method_label($activePolicy['method'])); ?><?php if($activePolicy['method']!=='none'): ?> — القيمة <?php echo number_format((float)$activePolicy['value'],2); ?><?php echo $activePolicy['method']==='percentage'?'%':' ج.س'; ?><?php endif; ?>. تم تفعيلها مع الرصيد الافتتاحي، ولا يمكن تعديلها حفاظاً على تاريخ القيود.</div>
        <?php else: ?>
            <div class="alert alert-info"><strong>الحالة: مسودة قابلة للتعديل.</strong> اختر ما تريده المنظمة. عند نشر/تفعيل الرصيد الافتتاحي ستصبح السياسة فعالة ومقفلة، ولن تتغير حسابات المعاملات التاريخية.</div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4"><label class="form-label">طريقة الرسوم</label><select name="admin_fee_method" id="adminFeeMethod" class="form-select"><option value="none" <?php echo (($policy['method']??'none')==='none')?'selected':''; ?>>بدون رسوم</option><option value="fixed" <?php echo (($policy['method']??'')==='fixed')?'selected':''; ?>>مبلغ ثابت (ج.س)</option><option value="percentage" <?php echo (($policy['method']??'')==='percentage')?'selected':''; ?>>نسبة مئوية (%)</option></select></div>
                    <div class="col-md-3"><label class="form-label">القيمة</label><input type="number" min="0" step="0.01" name="admin_fee_value" id="adminFeeValue" class="form-control" value="<?php echo e((string)($policy['value']??0)); ?>"><div class="form-text">لـ"بدون رسوم" تكون القيمة صفراً.</div></div>
                    <div class="col-md-3"><label class="form-label">تاريخ السريان</label><input type="date" name="admin_fee_effective_from" class="form-control" value="<?php echo e($policy['effective_from']??date('Y-m-d')); ?>"></div>
                    <div class="col-md-2"><button type="submit" name="save_admin_fee_policy" value="1" class="btn btn-outline-primary w-100"><i class="fas fa-save me-1"></i>حفظ السياسة</button></div>
                </div>
            </form>
        <?php endif; ?>
        <div class="mt-3 small text-muted">المعالجة المحاسبية: إجمالي التحصيل يدخل إلى الصندوق/البنك/المحفظة، ثم يُصنف الجزء الصافي كإيراد كفالات والرسوم كإيراد رسوم إدارية في الحساب <strong>4200 — الرسوم الإدارية</strong>. لا يوجد حساب نقدي منفصل للرسوم؛ الحساب 4200 يوضحها كمصدر إيراد مستقل.</div>
    </div>
</div>

<?php if($existingCount>0): ?><div class="alert alert-success fade-in"><i class="fas fa-lock me-1"></i><strong>الرصيد الافتتاحي فعال.</strong> تم قفل إعداد السياسة والرصيد. أي تغيير مستقبلي في سياسة الرسوم يجب أن يكون سياسة جديدة بتاريخ سريان جديد، وليس تعديلاً للتاريخ السابق.</div><?php endif; ?>
<?php if($existingEntries): ?>
<div class="card mb-4 fade-in"><div class="card-header bg-white"><i class="fas fa-clock-rotate-left me-2"></i>سجل الأرصدة الافتتاحية</div><div class="card-body p-0"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>الكود</th><th>التاريخ</th><th>المبلغ</th><th>الحالة</th><th></th></tr></thead><tbody><?php foreach($existingEntries as $en): ?><tr><td><code><?php echo e($en['entry_code']); ?></code></td><td><?php echo e($en['entry_date']); ?></td><td><?php echo number_format((float)$en['total'],2); ?> ج.س</td><td><?php echo $en['status']==='posted'?'<span class="badge bg-success">مرحّل</span>':'<span class="badge bg-danger">مبطل</span>'; ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>modules/accounting/journal.php?view=<?php echo (int)$en['id']; ?>">عرض</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>

<?php if(!$existingCount): ?>
<div class="card fade-in"><div class="card-header bg-white"><i class="fas fa-plus me-2"></i>تفعيل الرصيد الافتتاحي</div><div class="card-body"><form method="post"><?php echo csrf_field(); ?><div class="row g-3 mb-3"><div class="col-md-4"><label class="form-label">النقدي في الصندوق (1100)</label><input type="number" step="0.01" min="0" name="cash" class="form-control form-control-lg" placeholder="0.00"></div><div class="col-md-4"><label class="form-label">الرصيد في البنك (1200)</label><input type="number" step="0.01" min="0" name="bank" class="form-control form-control-lg" placeholder="0.00"></div><div class="col-md-4"><label class="form-label">المحفظة الإلكترونية (1300)</label><input type="number" step="0.01" min="0" name="wallet" class="form-control form-control-lg" placeholder="0.00"></div></div><div class="mb-3"><label class="form-label">تاريخ الرصيد</label><input type="date" name="as_of_date" class="form-control" style="max-width:220px" value="<?php echo date('Y-m-d'); ?>"></div><div class="alert alert-light border"><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_understood" id="confirmBox" required><label class="form-check-label" for="confirmBox">أؤكد أن هذه هي المبالغ الحقيقية الفعلية الموجودة، وأنني أدخلها لأول مرة.</label></div></div><button type="submit" name="save_opening_balance" value="1" class="btn btn-primary btn-lg"><i class="fas fa-lock me-1"></i> نشر وتفعيل الرصيد والسياسة</button></form></div></div>
<?php endif; ?>
<script>document.addEventListener('DOMContentLoaded',function(){var m=document.getElementById('adminFeeMethod'),v=document.getElementById('adminFeeValue');if(!m||!v)return;function sync(){v.disabled=m.value==='none';if(v.disabled)v.value='0';}m.addEventListener('change',sync);sync();});</script>

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/accounting/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>
