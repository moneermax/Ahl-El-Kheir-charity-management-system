<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../accounting/lib.php';
require_once __DIR__ . '/../accounting/lib_vouchers.php';
require_once __DIR__ . '/lib_salary_advance_waiver.php';

Session::start();
$role=(string)Session::getUserRole();
if(!hrSalaryAdvanceWaiverCanFM($role)){header('Location: '.APP_URL.'index.php');exit;}
$pdo=db(); $message=''; $error='';
$tablesReady=(int)dbFetchOne("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('hr_salary_advance_waiver_decisions','hr_salary_advance_waiver_items','hr_salary_advance_waiver_schedule_items')")['c']===3;

if($_SERVER['REQUEST_METHOD']==='POST' && $tablesReady){
 try{
  if(!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة.');
  $action=(string)($_POST['action']??'');
  $decisionId=(int)($_POST['decision_id']??0);
  if($action==='prepare'){
   $resultId=hrSalaryAdvanceWaiverCreateDecision(
    $pdo,
    (string)($_POST['decision_type']??''),
    (string)($_POST['effective_month']??''),
    trim((string)($_POST['reason']??'')),
    (int)Session::getUserID(),
    ($_POST['decision_type']??'')==='individual' ? (int)($_POST['employee_id']??0) : null,
    (int)($_POST['refund_account_id']??0),
    (int)($_POST['waiver_expense_account_id']??0)
   );
   $message='تم تجهيز قرار الإعفاء وإحالته إلى المدير العام للاعتماد.';
  } elseif($action==='execute'){
   $result=hrSalaryAdvanceWaiverExecute($pdo,$decisionId,(int)Session::getUserID());
   $message='تم تنفيذ قرار الإعفاء بعد اعتماد المدير العام. إجمالي الرد: '.number_format($result['refund_total'],2).' ج.س. وإجمالي الإعفاء: '.number_format($result['waiver_total'],2).' ج.س.';
  } else { throw new RuntimeException('إجراء غير صالح.'); }
 }catch(Throwable $e){$error=$e->getMessage();}
}
$decisions=$tablesReady?dbFetchAll("SELECT d.*,u.full_name AS creator_name,g.full_name AS gm_name,
 (SELECT COUNT(*) FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) item_count,
 (SELECT COALESCE(SUM(wi.current_period_repayment),0) FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) refund_preview,
 (SELECT COALESCE(SUM(wi.balance_before),0) FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) balance_preview
 FROM hr_salary_advance_waiver_decisions d
 LEFT JOIN users u ON u.id=d.created_by
 LEFT JOIN users g ON g.id=d.gm_approved_by
 WHERE d.status IN ('pending_gm','approved_by_gm') ORDER BY d.id ASC"):[]; 
$cash=$tablesReady?dbFetchAll("SELECT id,code,name_ar FROM accounts WHERE code IN ('1100','1200','1300') AND is_active=1 ORDER BY code"):[];
$expenses=$tablesReady?dbFetchAll("SELECT id,code,name_ar FROM accounts WHERE account_type='expense' AND is_active=1 ORDER BY code"):[];
$pageTitle='إعفاء سلف الرواتب — التنفيذ المالي';$active='salary_advance_waiver_fm';
require_once __DIR__.'/../../includes/header.php';
?>
<div class="container-fluid" style="max-width:1350px">
<h1 class="h4 mb-1">إعفاء سلف الرواتب — المدير المالي</h1>
<div class="text-muted small mb-3">FM يجهز القرار والحسابات، ثم يعتمد GM القرار، ثم يعود التنفيذ النهائي إلى FM.</div>
<?php if(!$tablesReady): ?><div class="alert alert-warning">ميزة الإعفاء لم تُفعّل في قاعدة البيانات بعد.</div><?php endif; ?>
<?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

<?php if($tablesReady): ?>
<div class="card mb-3"><div class="card-body">
<h5 class="mb-3">تجهيز قرار جديد</h5>
<form method="post" class="row g-3"><?=csrf_field()?>
<input type="hidden" name="action" value="prepare">
<div class="col-md-3"><label class="form-label">نوع القرار</label><select name="decision_type" class="form-select" required><option value="blanket">إعفاء جماعي — جميع السلف القائمة</option><option value="individual">إعفاء فردي</option></select></div>
<div class="col-md-3"><label class="form-label">شهر السريان</label><input type="date" name="effective_month" class="form-control" value="<?=e(date('Y-m-01'))?>" required></div>
<div class="col-md-3"><label class="form-label">الموظف عند الإعفاء الفردي</label><select name="employee_id" class="form-select"><option value="">اختر الموظف</option>
<?php $employees=dbFetchAll("SELECT DISTINCT e.id,e.employee_code,e.full_name FROM employees e JOIN hr_salary_advance_requests r ON r.employee_id=e.id WHERE ((r.status='disbursed' AND COALESCE(r.outstanding_balance,0)>0) OR (r.status IN ('submitted','fm_review','approved') AND r.closed_at IS NULL)) ORDER BY e.full_name"); foreach($employees as $e): ?><option value="<?=$e['id']?>"><?=e($e['employee_code'].' — '.$e['full_name'])?></option><?php endforeach; ?>
</select></div>
<div class="col-md-3"><label class="form-label">حساب رد الخصم</label><select name="refund_account_id" class="form-select" required><?php foreach($cash as $a): ?><option value="<?=$a['id']?>"><?=e($a['code'].' — '.$a['name_ar'])?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">حساب مصروف الإعفاء</label><select name="waiver_expense_account_id" class="form-select" required><option value="">اختر الحساب</option><?php foreach($expenses as $a): ?><option value="<?=$a['id']?>"><?=e($a['code'].' — '.$a['name_ar'])?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">سبب/نص قرار الإعفاء *</label><textarea name="reason" class="form-control" rows="2" maxlength="2000" required></textarea></div>
<div class="col-12"><button class="btn btn-primary"><i class="fas fa-file-signature me-1"></i>تجهيز القرار وإرساله إلى GM</button></div>
</form>
</div></div>

<?php foreach($decisions as $d): ?>
<div class="card mb-3"><div class="card-body">
<div class="d-flex justify-content-between"><h5><?=e($d['decision_no'])?></h5><span class="badge text-bg-<?=($d['status']==='approved_by_gm'?'success':'warning')?>"><?=($d['status']==='approved_by_gm'?'معتمد من GM — جاهز للتنفيذ':'بانتظار اعتماد GM')?></span></div>
<p class="mb-2"><?=e($d['reason'])?></p>
<div class="row g-2 mb-3"><div class="col-md-3"><strong>النوع:</strong> <?=e($d['decision_type']==='blanket'?'إعفاء جماعي':'إعفاء فردي')?></div><div class="col-md-3"><strong>الشهر:</strong> <?=e($d['effective_month'])?></div><div class="col-md-3"><strong>عدد السلف:</strong> <?=number_format((int)$d['item_count'])?></div><div class="col-md-3"><strong>الرصيد:</strong> <?=number_format((float)$d['balance_preview'],2)?> ج.س.</div></div>
<div class="alert alert-info small">رد الخصم المرحّل: <strong><?=number_format((float)$d['refund_preview'],2)?></strong> ج.س.</div>
<?php if($d['status']==='approved_by_gm'): ?>
<form method="post" class="row g-3"><?=csrf_field()?><input type="hidden" name="action" value="execute"><input type="hidden" name="decision_id" value="<?=$d['id']?>">
<div class="col-md-9 small text-muted align-self-center">تم اعتماد القرار من المدير العام. الحسابات المالية المحفوظة في القرار ستستخدم عند التنفيذ.</div>
<div class="col-md-3"><button class="btn btn-success w-100" onclick="return confirm('سيتم رد الخصومات وترحيل قيود الإعفاء وتصفير الأرصدة. هل تريد التنفيذ؟')"><i class="fas fa-check me-1"></i>تنفيذ نهائي</button></div>
</form>
<?php else: ?><div class="small text-muted">بانتظار اعتماد المدير العام.</div><?php endif; ?>
</div></div>
<?php endforeach; if(!$decisions): ?><div class="alert alert-light border">لا توجد قرارات قيد التجهيز أو بانتظار الاعتماد.</div><?php endif; ?>
<?php endif; ?>
</div></div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>