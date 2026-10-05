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
$tablesReady = (bool)dbFetchOne("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('hr_salary_advance_waiver_decisions','hr_salary_advance_waiver_items','hr_salary_advance_waiver_schedule_items')")['c'] === 3;

if ($_SERVER['REQUEST_METHOD']==='POST' && $tablesReady) {
    try {
        if (!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة.');
        $action=(string)($_POST['action']??'');
        if (!in_array($action,['approve','reject'],true)) throw new RuntimeException('إجراء غير صالح.');
        hrSalaryAdvanceWaiverGMReview(
            $pdo,
            (int)($_POST['decision_id']??0),
            (int)Session::getUserID(),
            $action==='approve',
            trim((string)($_POST['reason']??''))
        );
        $message=$action==='approve'
            ? 'تم اعتماد قرار الإعفاء وإعادته إلى المدير المالي لإتمام التنفيذ.'
            : 'تم رفض قرار الإعفاء دون أي أثر مالي.';
    } catch(Throwable $e){ $error=$e->getMessage(); }
}

$month=date('Y-m-01');
$employees=[];
$rows=$tablesReady ? hrSalaryAdvanceWaiverEligibleRows($pdo,$month) : [];
$pending=$tablesReady ? dbFetchAll("SELECT d.*,u.full_name AS creator_name,p.full_name AS preparer_name
 FROM hr_salary_advance_waiver_decisions d
 LEFT JOIN users u ON u.id=d.created_by
 LEFT JOIN users p ON p.id=d.prepared_by
 WHERE d.status='pending_gm' ORDER BY d.id DESC") : [];
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
<h5 class="mb-3">قرارات معدّة بانتظار اعتماد المدير العام</h5>
<div class="text-muted small">المدير المالي يجهز القرار والحسابات أولاً. دور المدير العام هنا هو الاعتماد أو الرفض فقط.</div>
</div></div><div class="card mb-3"><div class="card-body">
<h5>المراجعة والاعتماد</h5>
<?php foreach($pending as $d): ?>
<div class="border rounded p-3 mb-3">
<div class="d-flex justify-content-between"><strong><?=e($d['decision_no'])?></strong><span class="badge text-bg-warning">بانتظار اعتماد GM</span></div>
<div class="small mt-2"><strong>النوع:</strong> <?=e($d['decision_type']==='blanket'?'إعفاء جماعي':'إعفاء فردي')?> · <strong>الشهر:</strong> <?=e($d['effective_month'])?></div>
<p class="mt-2 mb-2"><?=e($d['reason'])?></p>
<?php
$preview=dbFetchOne("SELECT COUNT(*) item_count, COALESCE(SUM(balance_before),0) balance_total, COALESCE(SUM(current_period_repayment),0) refund_total FROM hr_salary_advance_waiver_items WHERE decision_id=?",[(int)$d['id']]);
?>
<div class="alert alert-info small mb-3">السلف المشمولة: <strong><?=number_format((int)$preview['item_count'])?></strong> · الرصيد المراد إعفاؤه بعد رد خصم الشهر: <strong><?=number_format((float)$preview['balance_total'],2)?></strong> ج.س. · رد الخصم: <strong><?=number_format((float)$preview['refund_total'],2)?></strong> ج.س.</div>
<form method="post" class="row g-2"><?=csrf_field()?><input type="hidden" name="decision_id" value="<?=$d['id']?>">
<div class="col-md-9"><input name="reason" class="form-control" maxlength="2000" placeholder="سبب الرفض (مطلوب عند الرفض)"></div>
<div class="col-md-3 d-flex gap-2"><button name="action" value="approve" class="btn btn-success flex-fill" onclick="return confirm('اعتماد قرار الإعفاء وإعادته إلى FM للتنفيذ؟')">اعتماد</button><button name="action" value="reject" class="btn btn-outline-danger flex-fill" onclick="return confirm('رفض قرار الإعفاء دون أثر مالي؟')">رفض</button></div>
</form>
</div>
<?php endforeach; if(!$pending): ?><div class="text-center text-muted py-3">لا توجد قرارات بانتظار اعتماد المدير العام.</div><?php endif; ?>
</div></div><?php endif; ?>
</tbody></table></div></div></div>
<?php endif; ?>
</div>
<script>
(function(){void 0;})();
</script>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>