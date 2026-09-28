<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_employment.php';
require_once __DIR__ . '/lib_salary_advance_policy.php';
require_once __DIR__ . '/lib_salary_advance_request.php';
require_once __DIR__ . '/../accounting/lib_transaction_review.php';

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
    $policyReference = hrSalaryAdvancePolicyGetRequestReference($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
            if (!$policyReference) throw new RuntimeException('لا توجد سياسة سلف منشورة يمكن استخدامها كمرجع للطلب حالياً.');

            $activeUnsettled = hrSalaryAdvanceGetActiveUnsettledRequest(
                $pdo,
                (int)$employee['id'],
                (bool)(int)($policyReference['allow_multiple_active_advances'] ?? 0)
            );
            if ($activeUnsettled) {
                throw new RuntimeException(
                    'لا يمكن تقديم طلب سلفة جديد حالياً. لديك سلفة معتمدة سابقة غير مسددة: ' .
                    (string)$activeUnsettled['request_no'] .
                    '. يجب تسوية السلفة السابقة أولاً.'
                );
            }

            $validated = hrSalaryAdvanceValidateRequest($_POST, $policyReference);
            $requestNo = hrSalaryAdvanceNextRequestNo($pdo);
            $pdo->prepare(
                "INSERT INTO hr_salary_advance_requests
                 (request_no, employee_id, policy_version_id, requested_amount, requested_repayment_method,
                  requested_monthly_amount, requested_start_month, request_reason, status, submitted_by, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, NOW())"
            )->execute([
                $requestNo, (int)$employee['id'], (int)$policyReference['id'],
                $validated['requested_amount'], $validated['requested_repayment_method'],
                $validated['requested_monthly_amount'], $validated['requested_start_month'],
                $validated['request_reason'], $userId
            ]);
            ak_transaction_review_notify_fm_event(
                (int)$pdo->lastInsertId(),
                'salary_advance_request',
                'طلب سلفة على الراتب بانتظار المراجعة',
                'طلب السلفة «' . $requestNo . '» للموظف «' . (string)$employee['full_name'] . '» بانتظار مراجعة المدير المالي.',
                APP_URL . 'modules/hr/salary_advance_fm_review.php?id=' . (int)$pdo->lastInsertId()
            );
            $message = 'تم إرسال طلب السلفة رقم ' . $requestNo . ' إلى المدير المالي للمراجعة.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    $requests = hrSalaryAdvanceGetEmployeeRequests($pdo, (int)$employee['id']);
    $receiptIds = [];
    $requestIds = array_values(array_filter(array_map(static function ($item) {
        return (int)($item['id'] ?? 0);
    }, $requests)));
    if ($requestIds) {
        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $receiptRows = dbFetchAll(
            "SELECT d.salary_advance_request_id, d.id AS receipt_id
             FROM hr_salary_advance_documents d
             JOIN hr_salary_advance_requests r ON r.id = d.salary_advance_request_id
             WHERE d.document_type = 'payment_receipt'
               AND r.employee_id = ?
               AND r.status = 'disbursed'
               AND r.id IN ($placeholders)",
            array_merge([(int)$employee['id']], $requestIds)
        );
        foreach ($receiptRows as $receiptRow) {
            $receiptIds[(int)$receiptRow['salary_advance_request_id']] = (int)$receiptRow['receipt_id'];
        }
    }
}

$pageTitle = 'طلب سلفة على الراتب';
$active = 'salary_advance_request';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.salary-advance-request{max-width:1100px;margin:0 auto}.salary-advance-request .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:22px 25px;margin-bottom:16px}.salary-advance-request .hero h1{font-size:1.35rem;font-weight:800;margin:0}.salary-advance-request .hero p{font-size:.74rem;margin:6px 0 0;opacity:.9}.salary-advance-request .card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;box-shadow:0 2px 12px rgba(16,24,40,.05);margin-bottom:15px;overflow:hidden}.salary-advance-request .card-body{padding:16px}
</style>
<div class="salary-advance-request">
<section class="hero"><h1><i class="fas fa-hand-holding-dollar me-2"></i>طلب سلفة على الراتب</h1><p>تقديم طلب سلفة وفق السياسة العامة، مع الاحتفاظ بطلبك الأصلي كما قدمته.</p></section>

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
<?php else: ?><div class="alert alert-warning">لا توجد سياسة سلف منشورة يمكن استخدامها كمرجع للطلب حالياً.</div><?php endif; ?>

<div class="card"><div class="card-body"><h5>طلباتي السابقة</h5><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>الطلب</th><th>التاريخ</th><th>المبلغ</th><th>السداد</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>
<?php foreach($requests as $r): ?><?php $displayStatus = !empty($r['closed_at']) ? 'مغلق — مرفوض' : (['submitted'=>'مرسل','fm_review'=>'قيد مراجعة FM','approved'=>'معتمد','disbursed'=>'تم الصرف','rejected'=>'مرفوض','cancelled'=>'ملغى'][$r['status']]??$r['status']); ?><tr><td><strong><?=e($r['request_no'])?></strong></td><td><?=e($r['submitted_at'])?></td><td><?=number_format((float)$r['requested_amount'],2)?></td><td><?=e(['fixed_monthly'=>'قسط شهري ثابت','full_eligible_salary'=>'كامل الراتب المؤهل','full_settlement'=>'تسوية كاملة','direct_repayment'=>'سداد مباشر'][$r['requested_repayment_method']]??$r['requested_repayment_method'])?></td><td><?=e($displayStatus)?></td><td><?php if (($r['status'] ?? '') === 'disbursed' && !empty($r['id'])): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?=e(APP_URL . 'modules/hr/salary_advance_voucher_print.php?id=' . (int)$r['id'])?>"><i class="fas fa-print me-1"></i>عرض/طباعة السند</a><?php if (!empty($receiptIds[(int)$r['id']])): ?><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?=e(APP_URL . 'modules/hr/salary_advance_receipt.php?id=' . (int)$receiptIds[(int)$r['id']])?>"><i class="fas fa-receipt me-1"></i>عرض إيصال الدفع</a><?php endif; ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></td></tr><?php endforeach; if(!$requests): ?><tr><td colspan="6" class="text-center text-muted py-3">لا توجد طلبات سابقة.</td></tr><?php endif; ?></tbody></table></div></div></div>
</div>
<script>
(function(){
 const method=document.getElementById('repayment_method'), wrap=document.getElementById('monthly_amount_wrap');
 function sync(){ if(!method||!wrap)return; wrap.style.display=method.value==='fixed_monthly'?'block':'none'; }
 method?.addEventListener('change',sync); sync();
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
