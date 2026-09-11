<?php
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once __DIR__.'/lib.php';
Session::start();

if(!Session::isLoggedIn()||!in_array(Session::getUserRole(),['admin','financial_manager','general_manager','vice_general_manager'],true)){
    header('Location: '.APP_URL.'index.php'); exit();
}
$role=Session::getUserRole();
$canManage=in_array($role,['admin','financial_manager'],true);
$canVoidManual=in_array($role,['admin','financial_manager'],true);
$pageTitle=t('accounting.journal_title');
$active='journal';
ak_ensure_tables(); ak_seed_accounts();
$automatedReferenceTypes=['transaction','transaction_void','disbursement','disbursement_return','disbursement_void','item_return','voucher','payroll','manual_void'];

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['void_entry'])&&$canVoidManual){
    if(verify_csrf()){
        $eid=(int)$_POST['void_entry'];
        $reason=trim($_POST['void_reason']??'')?:t('accounting.entry_cancelled');
        $pdo=db();
        try{
            $pdo->beginTransaction();
            $existing=dbFetchOne("SELECT id,entry_code,entry_date,description,reference_type,reference_id,created_by,status FROM journal_entries WHERE id=? FOR UPDATE",[$eid]);
            if(!$existing) throw new RuntimeException(t('accounting.entry_cancelled'));
            if(in_array($existing['reference_type'],$automatedReferenceTypes,true)) throw new RuntimeException(t('accounting.automated_transaction'));
            if($existing['status']!=='posted') throw new RuntimeException(t('accounting.entry_cancelled'));
            if((int)$existing['created_by']===(int)Session::getUserId()) throw new RuntimeException('لا يجوز لمنشئ القيد إبطاله بنفسه. يجب أن يقوم مستخدم مخوّل آخر بالمراجعة والإبطال.');

            $lines=dbFetchAll("SELECT account_id,debit,credit,description FROM journal_lines WHERE entry_id=? ORDER BY id",[$eid]);
            if(count($lines)<2) throw new RuntimeException('القيد المحاسبي لا يحتوي على أسطر كافية للإبطال.');
            $sumD=0.0; $sumC=0.0;
            foreach($lines as $line){
                $d=round((float)$line['debit'],2); $c=round((float)$line['credit'],2);
                if($d<0||$c<0||($d>0&&$c>0)) throw new RuntimeException('القيد المحاسبي الأصلي يحتوي على سطر غير صالح.');
                $sumD+=$d; $sumC+=$c;
            }
            if(round($sumD,2)!==round($sumC,2)||round($sumD,2)<=0) throw new RuntimeException('القيد المحاسبي الأصلي غير متوازن أو صفري.');

            $existingReversal=dbFetchOne("SELECT id FROM journal_entries WHERE reference_type='manual_void' AND reference_id=? LIMIT 1 FOR UPDATE",[$eid]);
            if($existingReversal) throw new RuntimeException('يوجد قيد إلغاء سابق لهذا القيد المحاسبي.');

            dbExecute("UPDATE journal_entries SET status='voided',voided_at=NOW(),voided_by=?,void_reason=? WHERE id=? AND status='posted'",[Session::getUserId(),$reason,$eid]);
            if(db()->rowCount()!==1) throw new RuntimeException('تعذر إبطال القيد المحاسبي الأصلي.');

            $code='JE-VOID-MANUAL-'.$eid;
            $codeExists=dbFetchOne("SELECT id FROM journal_entries WHERE entry_code=? LIMIT 1 FOR UPDATE",[$code]);
            if($codeExists) throw new RuntimeException('رمز قيد الإلغاء موجود مسبقاً لهذا القيد.');
            dbExecute("INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by) VALUES (?,?,?,?,?,'posted',?)",[
                $code,$existing['entry_date'],'عكس القيد المحاسبي '.$existing['entry_code'].' بسبب الإبطال','manual_void',$eid,Session::getUserId()
            ]);
            $reversalId=(int)dbLastInsertId();
            foreach($lines as $line){
                dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)",[
                    $reversalId,(int)$line['account_id'],round((float)$line['credit'],2),round((float)$line['debit'],2),'عكس: '.($line['description']??'')
                ]);
            }
            $rt=dbFetchOne("SELECT COALESCE(SUM(debit),0) debit_total,COALESCE(SUM(credit),0) credit_total,COUNT(*) line_count FROM journal_lines WHERE entry_id=?",[$reversalId]);
            if((int)$rt['line_count']!==count($lines)||round((float)$rt['debit_total'],2)!==round((float)$rt['credit_total'],2)||round((float)$rt['debit_total'],2)!==round($sumD,2)) throw new RuntimeException('فشل التحقق من قيد الإلغاء المحاسبي.');
            $pdo->commit();
            flash('success',t('accounting.entry_cancelled'));
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            flash('error',$e->getMessage()?:t('accounting.entry_cancelled'));
        }
    }
    header('Location: '.APP_URL.'modules/accounting/journal.php'); exit();
}

$viewId=(int)($_GET['view']??0);
if($viewId){
    $entry=dbFetchOne("SELECT je.*,u.full_name creator FROM journal_entries je LEFT JOIN users u ON u.id=je.created_by WHERE je.id=?",[$viewId]);
    if(!$entry){ flash('error',t('accounting.no_entries')); header('Location: '.APP_URL.'modules/accounting/journal.php'); exit(); }
    $lines=dbFetchAll("SELECT jl.*,a.code,a.name_ar FROM journal_lines jl JOIN accounts a ON a.id=jl.account_id WHERE jl.entry_id=? ORDER BY jl.id",[$viewId]);
    $sumD=0; $sumC=0; foreach($lines as $l){$sumD+=(float)$l['debit'];$sumC+=(float)$l['credit'];}
    include dirname(__DIR__,2).'/includes/header.php'; ?>
<div class="welcome-section fade-in"><h2><?php echo e(t('accounting.journal_title').' '.($entry['entry_code']??'')); ?></h2><p><?php echo e($entry['description']??''); ?> · <?php echo e($entry['entry_date']); ?> <?php echo ($entry['status']??'')==='voided'?'<span class="badge bg-danger">'.e(t('accounting.voided')).': '.e($entry['void_reason']??'').'</span>':'<span class="badge bg-success">'.e(t('accounting.posted')).'</span>'; ?></p><div class="quick-actions mt-3"><a href="<?php echo APP_URL; ?>modules/accounting/journal.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i><?php echo e(t('common.back')); ?></a><?php if($canVoidManual&&$entry['status']==='posted'&&!in_array($entry['reference_type'],$automatedReferenceTypes,true)&&(int)$entry['created_by']!==(int)Session::getUserId()): ?><form method="post" class="d-inline ak-void-form" data-confirm-msg="<?php echo e(t('accounting.void_confirm')); ?>"><?php echo csrf_field(); ?><input type="hidden" name="void_entry" value="<?php echo $viewId; ?>"><input type="text" name="void_reason" class="form-control form-control-sm d-inline-block w-auto" placeholder="<?php echo e(t('accounting.void_reason')); ?>" required><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-ban me-1"></i><?php echo e(t('accounting.void')); ?></button></form><?php elseif($entry['status']==='posted'&&$canVoidManual&&(int)$entry['created_by']===(int)Session::getUserId()&&!in_array($entry['reference_type'],$automatedReferenceTypes,true)): ?><span class="badge bg-warning text-dark">لا يمكن لمنشئ القيد إبطاله بنفسه.</span><?php elseif(in_array($entry['reference_type'],$automatedReferenceTypes,true)): ?><span class="badge bg-info"><?php echo e(t('accounting.automated_transaction')); ?></span><?php endif; ?></div></div>
<div class="card fade-in"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th><?php echo e(t('accounting.account')); ?></th><th><?php echo e(t('accounting.description')); ?></th><th>مدين</th><th>دائن</th></tr></thead><tbody><?php foreach($lines as $l): ?><tr><td><code><?php echo e($l['code']); ?></code> <?php echo e($l['name_ar']); ?></td><td><small><?php echo e($l['description']??''); ?></small></td><td><?php echo (float)$l['debit']>0?number_format((float)$l['debit'],2):'—'; ?></td><td><?php echo (float)$l['credit']>0?number_format((float)$l['credit'],2):'—'; ?></td></tr><?php endforeach; ?><tr class="table-active fw-bold"><td colspan="2"><?php echo e(t('accounting.total')); ?></td><td><?php echo number_format($sumD,2); ?></td><td><?php echo number_format($sumC,2); ?></td></tr></tbody></table></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script>document.querySelectorAll('.ak-void-form').forEach(function(form){form.addEventListener('submit',function(e){e.preventDefault();var msg=form.dataset.confirmMsg||<?php echo json_encode(t('accounting.void_confirm')); ?>;function proceed(){form.submit();}if(typeof Swal!=='undefined'&&typeof Swal.fire==='function'){Swal.fire({title:<?php echo json_encode(t('accounting.void_confirm_title')); ?>,text:msg,icon:'warning',showCancelButton:true,confirmButtonColor:'#dc3545',cancelButtonColor:'#6c757d',confirmButtonText:<?php echo json_encode(t('accounting.confirm_void')); ?>,cancelButtonText:<?php echo json_encode(t('common.cancel')); ?>,reverseButtons:true}).then(function(r){if(r.isConfirmed)proceed();});}else if(confirm(msg))proceed();});});</script><?php include dirname(__DIR__,2).'/includes/footer.php'; exit();
}

$from=trim($_GET['from']??''); $to=trim($_GET['to']??'');
$sql="SELECT je.*,u.full_name creator,(SELECT COALESCE(SUM(jl.debit),0) FROM journal_lines jl WHERE jl.entry_id=je.id) total FROM journal_entries je LEFT JOIN users u ON u.id=je.created_by WHERE 1=1";
$params=[]; if($from){$sql.=" AND je.entry_date>=?";$params[]=$from;} if($to){$sql.=" AND je.entry_date<=?";$params[]=$to;}
$sql.=" ORDER BY je.entry_date DESC,je.id DESC LIMIT 300"; $entries=dbFetchAll($sql,$params);
include dirname(__DIR__,2).'/includes/header.php'; ?>
<div class="welcome-section fade-in"><h2><?php echo e(t('accounting.journal_title')); ?></h2><p><?php echo e(t('accounting.journal_automated_manual')); ?></p><div class="quick-actions mt-3"><?php if($canManage): ?><a href="<?php echo APP_URL; ?>modules/accounting/journal_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i><?php echo e(t('accounting.new_manual_entry')); ?></a><?php endif; ?></div></div><?php include dirname(__DIR__,2).'/includes/alerts.php'; ?><div class="card mb-3 fade-in"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label"><?php echo e(t('accounting.from')); ?></label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>"></div><div class="col-md-3"><label class="form-label"><?php echo e(t('accounting.to')); ?></label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>"></div><div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-search"></i> <?php echo e(t('accounting.show')); ?></button></div></form></div></div><div class="card fade-in"><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><?php echo e(t('accounting.code')); ?></th><th><?php echo e(t('accounting.date')); ?></th><th><?php echo e(t('accounting.description')); ?></th><th><?php echo e(t('accounting.reference')); ?></th><th><?php echo e(t('accounting.value')); ?></th><th><?php echo e(t('common.status')); ?></th><th><?php echo e(t('accounting.created_by')); ?></th><th class="text-center"><?php echo e(t('common.view')); ?></th></tr></thead><tbody><?php if(!$entries): ?><tr><td colspan="8" class="text-center text-muted py-4"><?php echo e(t('accounting.no_entries')); ?></td></tr><?php else: foreach($entries as $en): ?><tr><td><code><?php echo e($en['entry_code']); ?></code></td><td><?php echo e($en['entry_date']); ?></td><td><?php echo e($en['description']??''); ?></td><td><?php echo e($en['reference_type']??t('accounting.manual')); ?></td><td><strong><?php echo number_format((float)$en['total'],2); ?></strong></td><td><?php echo $en['status']==='posted'?'<span class="badge bg-success">'.e(t('accounting.posted')).'</span>':'<span class="badge bg-danger">'.e(t('accounting.voided')).'</span>'; ?></td><td><small><?php echo e($en['creator']??''); ?></small></td><td class="text-center"><a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>modules/accounting/journal.php?view=<?php echo (int)$en['id']; ?>"><i class="fas fa-eye"></i></a></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div><?php include dirname(__DIR__,2).'/includes/footer.php'; ?>