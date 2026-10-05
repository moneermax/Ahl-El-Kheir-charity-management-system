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
$tablesReady=(bool)dbFetchOne("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('hr_salary_advance_waiver_decisions','hr_salary_advance_waiver_items')")['c']===2;

if($_SERVER['REQUEST_METHOD']==='POST' && $tablesReady){
 try{
  if(!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة.');
  if((string)($_POST['action']??'execute')==='reject'){ hrSalaryAdvanceWaiverReject($pdo,(int)$_POST['decision_id'],(int)Session::getUserID(),trim((string)$_POST['rejection_reason'])); $message='تم رفض قرار الإعفاء دون أي أثر مالي.'; }
  else { $result=hrSalaryAdvanceWaiverExecute($pdo,(int)$_POST['decision_id'],(int)Session::getUserID(),(int)$_POST['refund_account_id'],(int)$_POST['waiver_expense_account_id']); $message='تم تنفيذ قرار الإعفاء. إجمالي الرد: '.number_format($result['refund_total'],2).' ج.س. وإجمالي الإعفاء: '.number_format($result['waiver_total'],2).' ج.س.'; }
 }catch(Throwable $e){$error=$e->getMessage();}
}
$decisions=$tablesReady?dbFetchAll("SELECT d.*,u.full_name AS creator_name,
 (SELECT COUNT(*) FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) item_count,
 (SELECT COALESCE(SUM(wi.current_period_repayment),0) FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) refund_preview,
 (SELECT COALESCE(SUM(wi.balance_before),0) FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) balance_preview
 FROM hr_salary_advance_waiver_decisions d LEFT JOIN users u ON u.id=d.created_by
 WHERE d.status='pending_fm' ORDER BY d.id ASC"):[]; 
$cash=$tablesReady?dbFetchAll("SELECT id,code,name_ar FROM accounts WHERE code IN ('1100','1200','1300') AND is_active=1 ORDER BY code"):[];
$expenses=$tablesReady?dbFetchAll("SELECT id,code,name_ar FROM accounts WHERE account_type='expense' AND is_active=1 ORDER BY code"):[];
$pageTitle='إعفاء سلف الرواتب — التنفيذ المالي';$active='salary_advance_waiver_fm';
require_once __DIR__.'/../../includes/header.php';
?>
<div class="container-fluid" style="max-width:1350px"><h1 class="h4 mb-1">تنفيذ إعفاء سلف الرواتب</h1><div class="text-muted small mb-3">قرارات المدير العام — التنفيذ والتأكيد المالي لدى FM.</div>
<?php if(!$tablesReady): ?><div class="alert alert-warning">ميزة الإعفاء لم تُفعّل في قاعدة البيانات بعد.</div><?php endif; ?>
<?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php foreach($decisions as $d): ?>
<div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between"><h5><?=e($d['decision_no'])?></h5><span class="badge text-bg-warning">بانتظار التنفيذ</span></div>
<p class="mb-2"><?=e($d['reason'])?></p><div class="row g-2 mb-3"><div class="col-md-3"><strong>النوع:</strong> <?=e($d['decision_type']==='blanket'?'إعفاء جماعي':'إعفاء فردي')?></div><div class="col-md-3"><strong>الشهر:</strong> <?=e($d['effective_month'])?></div><div class="col-md-3"><strong>عدد السلف:</strong> <?=number_format((int)$d['item_count'])?></div><div class="col-md-3"><strong>الرصيد الملتقط:</strong> <?=number_format((float)$d['balance_preview'],2)?> ج.س.</div></div>
<div class="alert alert-info small">رد الخصومات المرحّلة للشهر: <strong><?=number_format((float)$d['refund_preview'],2)?> ج.س.</strong>. التنفيذ يعيد هذا المبلغ ثم يعفي الرصيد المستعاد بالكامل.</div>
<form method="post" class="row g-3"><?=csrf_field()?><input type="hidden" name="decision_id" value="<?=$d['id']?>">
<div class="col-md-4"><label class="form-label">حساب رد الخصم</label><select name="refund_account_id" class="form-select" required><?php foreach($cash as $a): ?><option value="<?=$a['id']?>"><?=e($a['code'].' — '.$a['name_ar'])?></option><?php endforeach; ?></select></div>
<div class="col-md-5"><label class="form-label">حساب مصروف الإعفاء</label><select name="waiver_expense_account_id" class="form-select" required><option value="">اختر الحساب</option><?php foreach($expenses as $a): ?><option value="<?=$a['id']?>"><?=e($a['code'].' — '.$a['name_ar'])?></option><?php endforeach; ?></select></div>
<div class="col-md-3 d-flex align-items-end"><button name="action" value="execute" class="btn btn-success w-100" onclick="return confirm('سيتم ترحيل القيود وتصفير الأرصدة المشمولة. هل تريد المتابعة؟')"><i class="fas fa-check me-1"></i>تأكيد التنفيذ المالي</button></div><div class="col-md-12"><label class="form-label">سبب الرفض عند الحاجة</label><input name="rejection_reason" class="form-control" maxlength="2000"><button name="action" value="reject" class="btn btn-outline-danger mt-2" onclick="return confirm('سيتم رفض القرار دون أي أثر مالي. هل تريد المتابعة؟')"><i class="fas fa-xmark me-1"></i>رفض القرار</button></div>
</form></div></div>
<?php endforeach; if(!$decisions): ?><div class="alert alert-light border">لا توجد قرارات بانتظار التنفيذ المالي.</div><?php endif; ?>
</div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>