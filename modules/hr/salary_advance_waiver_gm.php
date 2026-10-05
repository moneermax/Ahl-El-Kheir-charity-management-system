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
$role = (string)Session::getUserRole();
if (!hrSalaryAdvanceWaiverCanGM($role)) { header('Location: '.APP_URL.'index.php'); exit; }

$pdo=db();
$message=''; $error='';
$tablesReady = (bool)dbFetchOne("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('hr_salary_advance_waiver_decisions','hr_salary_advance_waiver_items')")['c'] === 2;

if ($_SERVER['REQUEST_METHOD']==='POST' && $tablesReady) {
    try {
        if (!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة.');
        $type=(string)($_POST['decision_type']??'');
        $month=(string)($_POST['effective_month']??'');
        $reason=trim((string)($_POST['reason']??''));
        $employeeId=$type==='individual' ? (int)($_POST['employee_id']??0) : null;
        $id=hrSalaryAdvanceWaiverCreateDecision($pdo,$type,$month,$reason,(int)Session::getUserID(),$employeeId);
        $message='تم إنشاء قرار الإعفاء وإحالته إلى المدير المالي للتنفيذ المالي.';
    } catch(Throwable $e){ $error=$e->getMessage(); }
}

$month=date('Y-m-01');
$employees=dbFetchAll("SELECT DISTINCT e.id,e.employee_code,e.full_name
 FROM employees e JOIN hr_salary_advance_requests r ON r.employee_id=e.id
 WHERE r.status='disbursed' AND COALESCE(r.outstanding_balance,0)>0
 ORDER BY e.full_name");
$rows=$tablesReady ? hrSalaryAdvanceWaiverEligibleRows($pdo,$month) : [];
$pending=$tablesReady ? dbFetchAll("SELECT d.*,u.full_name AS creator_name
 FROM hr_salary_advance_waiver_decisions d LEFT JOIN users u ON u.id=d.created_by
 WHERE d.status='pending_fm' ORDER BY d.id DESC") : [];
$pageTitle='إعفاء سلف الرواتب — قرار المدير العام'; $active='salary_advance_waiver_gm';
require_once __DIR__.'/../../includes/header.php';
?>
<div class="container-fluid" style="max-width:1350px">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-1">إعفاء سلف الرواتب</h1><div class="text-muted small">قرار المدير العام فقط — التنفيذ المالي لدى المدير المالي.</div></div></div>
<?php if(!$tablesReady): ?><div class="alert alert-warning">ميزة الإعفاء لم تُفعّل في قاعدة البيانات بعد. يجب تطبيق migration الخاصة بها أولاً.</div><?php endif; ?>
<?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if($tablesReady): ?>
<div class="card mb-3"><div class="card-body">
<h5 class="mb-3">إنشاء قرار جديد</h5>
<form method="post" class="row g-3">
<?=csrf_field()?>
<div class="col-md-3"><label class="form-label">نوع القرار</label><select name="decision_type" id="waiver_type" class="form-select" required><option value="blanket">إعفاء جماعي — جميع السلف القائمة</option><option value="individual">إعفاء فردي</option></select></div>
<div class="col-md-3"><label class="form-label">شهر السريان</label><input type="date" name="effective_month" class="form-control" value="<?=e($month)?>" required></div>
<div class="col-md-3" id="employee_wrap"><label class="form-label">الموظف</label><select name="employee_id" class="form-select"><option value="">اختر الموظف</option><?php foreach($employees as $e): ?><option value="<?=$e['id']?>"><?=e($e['employee_code'].' — '.$e['full_name'])?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">سبب/نص قرار المدير العام *</label><textarea name="reason" class="form-control" rows="3" maxlength="2000" required></textarea></div>
<div class="col-12"><button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>إحالة القرار إلى FM</button></div>
</form>
</div></div>
<div class="card mb-3"><div class="card-body"><h5>السلف القائمة التي سيشملها القرار</h5>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>الموظف</th><th>السلفة</th><th>الرصيد القائم</th><th>خصم الشهر المرحّل</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=e($r['employee_code'].' — '.$r['employee_name'])?></td><td><?=e($r['request_no'])?></td><td><?=number_format((float)$r['outstanding_balance'],2)?></td><td><?=number_format((float)$r['current_period_repayment'],2)?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="4" class="text-center text-muted">لا توجد سلف قائمة لهذا الشهر.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
<div class="card"><div class="card-body"><h5>قرارات بانتظار FM</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>القرار</th><th>النوع</th><th>الشهر</th><th>أنشأه</th><th>الحالة</th></tr></thead><tbody>
<?php foreach($pending as $d): ?><tr><td><?=e($d['decision_no'])?></td><td><?=e($d['decision_type']==='blanket'?'جماعي':'فردي')?></td><td><?=e($d['effective_month'])?></td><td><?=e($d['creator_name']??'—')?></td><td>بانتظار التنفيذ المالي</td></tr><?php endforeach; if(!$pending): ?><tr><td colspan="5" class="text-center text-muted">لا توجد قرارات معلقة.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
<?php endif; ?>
</div>
<script>
(function(){const t=document.getElementById('waiver_type'),w=document.getElementById('employee_wrap');function s(){w.style.display=t.value==='individual'?'block':'none';}t?.addEventListener('change',s);s();})();
</script>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>