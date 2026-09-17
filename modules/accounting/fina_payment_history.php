<?php
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once __DIR__.'/fina_lib.php';
Session::start();
if(!Session::isLoggedIn()){header('Location: '.APP_URL.'index.php');exit();}
$role=Session::getUserRole();
if(!in_array($role,['financial_manager','fm','supervisor','admin','vice_general_manager'],true)){header('Location: '.APP_URL.'index.php');exit();}
$uid=(int)Session::getUserId();$supervisorOwnOnly=($role==='supervisor');
fina_ensure_tables();
$pageTitle='سجل تحصيلات فينا الخير';
$active='fina_history';
$collectionId=(int)($_GET['id']??0);
$status=trim($_GET['status']??'');
$sourceType=trim($_GET['source_type']??'');
$from=trim($_GET['from']??'');
$to=trim($_GET['to']??'');
$where=["fc.status IN ('approved','returned')"];$params=[];
if($supervisorOwnOnly){$where[]='fc.created_by=?';$params[]=$uid;}
if($collectionId>0){$where[]='fc.id=?';$params[]=$collectionId;}
if(in_array($status,['approved','returned'],true)){$where[]='fc.status=?';$params[]=$status;}
if(in_array($sourceType,['sponsor','person','organization','other'],true)){$where[]='fs.source_type=?';$params[]=$sourceType;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='fc.collection_date>=?';$params[]=$from;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='fc.collection_date<=?';$params[]=$to;}
$sql="SELECT fc.id,fc.amount,fc.currency_code,fc.payment_method,fc.collection_date,fc.status,fc.created_at,fc.reviewed_at,fc.return_note,fc.accounting_journal_id,fs.source_type,fs.source_name,s.full_name sponsor_name,s.sponsor_code,cu.full_name creator_name,ru.full_name reviewer_name FROM fina_collections fc JOIN fina_sources fs ON fs.id=fc.fina_source_id LEFT JOIN sponsors s ON s.id=fs.sponsor_id JOIN users cu ON cu.id=fc.created_by LEFT JOIN users ru ON ru.id=fc.reviewed_by WHERE ".implode(' AND ',$where)." ORDER BY fc.reviewed_at DESC,fc.id DESC LIMIT 500";
$rows=dbFetchAll($sql,$params);
$methodLabels=['cash'=>'نقدي','bank_transfer'=>'تحويل بنكي','credit_card'=>'بطاقة','mobile'=>'محفظة إلكترونية','other'=>'أخرى'];
$statusLabels=['approved'=>'معتمد','returned'=>'مرتجع'];
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="container-fluid py-4">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><div><h2><i class="fas fa-clock-rotate-left me-2"></i>سجل تحصيلات فينا الخير</h2><div class="text-muted">السجل التاريخي للطلبات التي تمت مراجعتها فقط. الطلبات المعلقة تظهر في شاشة مراجعة فينا.<?php if($supervisorOwnOnly):?> أنت ترى فقط التحصيلات التي سجلتها بنفسك.<?php endif;?></div></div><div class="d-flex gap-2"><a class="btn btn-primary" href="<?php echo APP_URL;?>modules/accounting/fina_payment_review.php"><i class="fas fa-clipboard-check me-1"></i>مراجعة فينا</a><a class="btn btn-outline-primary" href="<?php echo APP_URL;?>modules/accounting/fina_payment_report.php"><i class="fas fa-file-chart-column me-1"></i>تقرير فينا</a></div></div>
<?php if($collectionId>0):?><div class="alert alert-info"><i class="fas fa-circle-info me-1"></i>عرض مباشر لتحصيل فينا الخير رقم #<?php echo $collectionId;?> من الإشعار.</div><?php endif;?>
<div class="card mb-3"><div class="card-body"><form class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">الحالة</label><select name="status" class="form-select"><option value="">المعتمد والمرتجع</option><?php foreach($statusLabels as $k=>$v):?><option value="<?php echo e($k);?>" <?php echo $status===$k?'selected':'';?>><?php echo e($v);?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">نوع المصدر</label><select name="source_type" class="form-select"><option value="">الكل</option><option value="sponsor" <?php echo $sourceType==='sponsor'?'selected':'';?>>كفيل من أهل الخير</option><option value="person" <?php echo $sourceType==='person'?'selected':'';?>>شخص خارجي</option><option value="organization" <?php echo $sourceType==='organization'?'selected':'';?>>منظمة / جهة</option><option value="other" <?php echo $sourceType==='other'?'selected':'';?>>أخرى</option></select></div><div class="col-md-3"><label class="form-label">من تاريخ</label><input type="date" name="from" value="<?php echo e($from);?>" class="form-control"></div><div class="col-md-3"><label class="form-label">إلى تاريخ</label><input type="date" name="to" value="<?php echo e($to);?>" class="form-control"></div><div class="col-12"><div class="form-text"><i class="fas fa-coins me-1"></i>عملة النظام الموحدة: <strong><?php echo e(APP_CURRENCY_CODE);?></strong>. لا يوجد اختيار عملة في سجل التحصيلات.</div></div><div class="col-12"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>تطبيق</button></div></form></div></div>
<div class="card"><div class="card-header"><strong><?php echo $supervisorOwnOnly?'سجلك التاريخي بعد المراجعة':'السجل التاريخي بعد المراجعة';?></strong> <span class="badge bg-secondary"><?php echo count($rows);?></span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>#</th><th>تاريخ التحصيل</th><th>المصدر</th><th>المبلغ</th><th>طريقة الدفع</th><th>الحالة</th><th>المراجع</th><th>وقت المراجعة</th><th>القيد</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="9" class="text-center text-muted py-4">لا توجد تحصيلات مراجعة مطابقة.</td></tr><?php else:foreach($rows as $r):$source=($r['source_type']==='sponsor'?'كفيل — '.($r['sponsor_code']??'').' — '.($r['sponsor_name']??''):(['person'=>'شخص خارجي','organization'=>'منظمة / جهة','other'=>'أخرى'][$r['source_type']]??'أخرى').' — '.($r['source_name']??''));?><tr><td><?php echo(int)$r['id'];?></td><td><?php echo e($r['collection_date']);?></td><td><?php echo e($source);?></td><td><strong><?php echo number_format((float)$r['amount'],2);?></strong> <?php echo e($r['currency_code']);?></td><td><?php echo e($methodLabels[$r['payment_method']]??$r['payment_method']);?></td><td><?php echo $r['status']==='approved'?'<span class="badge bg-success">معتمد</span>':'<span class="badge bg-danger">مرتجع</span>';?></td><td><?php echo e($r['reviewer_name']??'—');?></td><td><?php echo e($r['reviewed_at']??'—');?></td><td><?php echo $r['accounting_journal_id']?'<span class="badge bg-primary">JE #'.(int)$r['accounting_journal_id'].'</span>':'—';?></td></tr><?php endforeach;endif;?></tbody></table></div></div></div>
</div>
<?php include dirname(__DIR__,2).'/includes/footer.php';?>