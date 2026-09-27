<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_employment.php';
require_once __DIR__ . '/lib_salary_advance_policy.php';
require_once __DIR__ . '/lib_salary_advance_request.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'login.php'); exit; }

$pdo = db();
$userId = (int)Session::getUserID();
$employee = hrSalaryAdvanceGetEmployeeForUser($pdo, $userId);
$message = '';
$error = '';

if (!$employee) {
    $error = 'لا يوجد ملف موظف مرتبط بحساب المستخدم الحالي.';
} else {
    $activePolicy = hrSalaryAdvancePolicyGetActive($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
            if (!$activePolicy) throw new RuntimeException('لا توجد سياسة سلف سارية حالياً.');
            $eligibilityErrors = hrSalaryAdvanceValidateEmployeeEligibility($employee, $activePolicy);
            if ($eligibilityErrors) throw new RuntimeException(implode(' ', $eligibilityErrors));

            $validated = hrSalaryAdvanceValidateRequest($_POST, $activePolicy);
            if (!(int)$activePolicy['allow_multiple_active_advances']) {
                $existing = dbFetchOne(
                    "SELECT id FROM hr_salary_advance_requests
                     WHERE employee_id = ?
                       AND status IN ('submitted','fm_review','approved')
                     LIMIT 1",
                    [(int)$employee['id']]
                );
                if ($existing) throw new RuntimeException('لديك سلفة أو طلب سلفة نشط بالفعل وفق السياسة السارية.');
            }

            $requestNo = hrSalaryAdvanceNextRequestNo($pdo);
            $pdo->prepare(
                "INSERT INTO hr_salary_advance_requests
                 (request_no, employee_id, policy_version_id, requested_amount, requested_repayment_method,
                  requested_monthly_amount, requested_start_month, request_reason, status, submitted_by, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, NOW())"
            )->execute([
                $requestNo, (int)$employee['id'], (int)$activePolicy['id'],
                $validated['requested_amount'], $validated['requested_repayment_method'],
                $validated['requested_monthly_amount'], $validated['requested_start_month'],
                $validated['request_reason'], $userId
            ]);
            $message = 'تم إرسال طلب السلفة رقم ' . $requestNo . ' إلى المدير المالي للمراجعة.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    $requests = hrSalaryAdvanceGetEmployeeRequests($pdo, (int)$employee['id']);
    $activePolicy = hrSalaryAdvancePolicyGetActive($pdo);
    $eligibilityErrors = $activePolicy ? hrSalaryAdvanceValidateEmployeeEligibility($employee, $activePolicy) : [];
}

$pageTitle = 'طلب سلفة على الراتب';
$active = 'salary_advance_request';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.salary-advance-request{max-width:1100px;margin:0 auto}.salary-advance-request .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:22px 25px;margin-bottom:16px}.salary-advance-request .hero h1{font-size:1.35rem;font-weight:800;margin:0}.salary-advance-request .hero p{font-size:.74rem;margin:6px 0 0;opacity:.9}.salary-advance-request .card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;box-shadow:0 2px 12px rgba(16,24,40,.05);margin-bottom:15px;overflow:hidden}.salary-advance-request .card-body{padding:16px}
</style>
<div class="salary-advance-request">
<section class="hero"><h1><i class="fas fa-hand-holding-dollar me-2"></i>طلب سلفة على الراتب</h1><p>تقديم طلب سلفة وفق السياسة السارية، مع الاحتفاظ بطلبك الأصلي كما قدمته.</p></section>

<?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

<?php if($employee): ?>
<div class="card"><div class="card-body">
<div class="row g-3">
<div class="col-md-4"><strong>الموظف:</strong> <?=e($employee['full_name'])?></div>
<div class="col-md-4"><strong>الكود:</strong> <?=e($employee['employee_code'])?></div>
<div class="col-md-4"><strong>الراتب الأساسي:</strong> <?=number_format((float)$employee['basic_salary'],2)?> SDG</div>
</div>
</div></div>

<?php if($activePolicy): ?>
<div class="card"><div class="card-body">
<h5 class="mb-3">السياسة السارية</h5>
<div class="row g-3 small">
<div class="col-md-4"><strong>الإصدار:</strong> V<?= (int)$activePolicy['version_no']?></div>
<div class="col-md-4"><strong>السريان:</strong> <?=e($activePolicy['effective_from'])?></div>
<div class="col-md-4"><strong>المبلغ:</strong> <?= (int)$activePolicy['allow_any_request_amount'] ? 'أي مبلغ' : number_format((float)$activePolicy['minimum_request_amount'],2).' — '.number_format((float)$activePolicy['maximum_request_amount'],2)?></div>
</div>
</div></div>

<?php if(!$eligibilityErrors): ?>
<div class="card"><div class="card-body">
<h5 class="mb-3">بيانات الطلب</h5>
<form method="post">
<?=csrf_field()?>
<div class="row g-3">
<div class="col-md-4"><label class="form-label">المبلغ المطلوب *</label><input type="number" step="0.01" min="0.01" name="requested_amount" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">طريقة السداد المطلوبة *</label><select name="requested_repayment_method" id="repayment_method" class="form-select" required>
<option value="fixed_monthly">قسط شهري ثابت</option>
<option value="full_eligible_salary">خصم كامل الراتب المؤهل</option>
<option value="full_settlement">تسوية كامل الرصيد من الراتب</option>
<option value="direct_repayment">سداد مباشر</option>
</select></div>
<div class="col-md-4" id="monthly_amount_wrap"><label class="form-label">القسط الشهري المطلوب</label><input type="number" step="0.01" min="0.01" name="requested_monthly_amount" class="form-control"></div>
<div class="col-md-4"><label class="form-label">شهر بدء السداد</label><input type="date" name="requested_start_month" class="form-control" value="<?=e(date('Y-m-01',strtotime('+1 month')))?>"></div>
<div class="col-12"><label class="form-label">سبب الطلب</label><textarea name="request_reason" class="form-control" rows="3" maxlength="2000"></textarea></div>
<div class="col-12"><button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane me-1"></i>إرسال طلب السلفة</button></div>
</div></form>
</div></div>
<?php else: ?><div class="alert alert-warning"><?=e(implode(' ', $eligibilityErrors))?></div><?php endif; ?>

<?php else: ?><div class="alert alert-warning">لا توجد سياسة سلف سارية حالياً. يمكن تقديم الطلب بعد بدء سريان إصدار السياسة.</div><?php endif; ?>

<div class="card"><div class="card-body"><h5>طلباتي السابقة</h5><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>الطلب</th><th>التاريخ</th><th>المبلغ</th><th>السداد</th><th>الحالة</th></tr></thead><tbody>
<?php foreach($requests as $r): ?><tr><td><strong><?=e($r['request_no'])?></strong></td><td><?=e($r['submitted_at'])?></td><td><?=number_format((float)$r['requested_amount'],2)?></td><td><?=e(['fixed_monthly'=>'قسط شهري ثابت','full_eligible_salary'=>'كامل الراتب المؤهل','full_settlement'=>'تسوية كاملة','direct_repayment'=>'سداد مباشر'][$r['requested_repayment_method']]??$r['requested_repayment_method'])?></td><td><?=e(['submitted'=>'مرسل','fm_review'=>'قيد مراجعة FM','approved'=>'معتمد','rejected'=>'مرفوض','cancelled'=>'ملغى'][$r['status']]??$r['status'])?></td></tr><?php endforeach; if(!$requests): ?><tr><td colspan="5" class="text-center text-muted py-3">لا توجد طلبات سابقة.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php endif; ?>
</div>
<script>
(function(){
 const method=document.getElementById('repayment_method'), wrap=document.getElementById('monthly_amount_wrap');
 function sync(){ if(!method||!wrap)return; wrap.style.display=method.value==='fixed_monthly'?'block':'none'; }
 method?.addEventListener('change',sync); sync();
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
