<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_salary_advance_request.php';
require_once __DIR__ . '/../accounting/lib_transaction_review.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'login.php'); exit; }
$role = (string)Session::getUserRole();
if (!hrSalaryAdvanceFmCanReview($role)) {
    flash('error', 'غير مصرح لك بمراجعة طلبات السلف على الراتب.');
    header('Location: ' . APP_URL . 'dashboard/staff_dashboard.php');
    exit;
}

$pdo = db();
$message = '';
$error = '';
$requestId = (int)($_GET['id'] ?? $_POST['request_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fm_decision_submit'])) {
    try {
        if (!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        $request = hrSalaryAdvanceGetRequestForFm($pdo, $requestId);
        if (!$request) throw new RuntimeException('طلب السلفة المطلوب غير موجود.');
        if (!in_array((string)$request['status'], ['submitted','fm_review'], true) || !empty($request['closed_at'])) {
            throw new RuntimeException('هذا الطلب لم يعد متاحاً لمراجعة FM.');
        }

        $decision = hrSalaryAdvanceFmValidateDecision($pdo, $request, $_POST);
        $uid = (int)Session::getUserID();

        $pdo->beginTransaction();
        $notificationTitle = '';
        $notificationBody = '';
        $notificationType = '';

        if ($decision['decision'] === 'reject') {
            $pdo->prepare(
                "UPDATE hr_salary_advance_requests
                 SET status='rejected', fm_decision='rejected', fm_rejection_reason=?,
                     fm_reviewed_by=?, fm_reviewed_at=NOW(), closed_at=NOW(), updated_at=NOW()
                 WHERE id=? AND status IN ('submitted','fm_review') AND closed_at IS NULL"
            )->execute([$decision['reason'], $uid, $requestId]);

            if ($pdo->rowCount() !== 1) {
                throw new RuntimeException('تعذر إغلاق طلب السلفة بعد رفضه.');
            }

            $notificationTitle = 'تم رفض وإغلاق طلب السلفة';
            $notificationBody = 'تم رفض وإغلاق طلب السلفة «' . (string)$request['request_no'] . '». سبب الرفض: ' . $decision['reason'];
            $notificationType = 'salary_advance_request_rejection';
            $message = 'تم رفض طلب السلفة وإغلاقه وإبلاغ الموظف.';
        } else {
            $pdo->prepare(
                "UPDATE hr_salary_advance_requests
                 SET status='approved', fm_decision='approved', fm_rejection_reason=NULL,
                     approved_amount=?, approved_repayment_method=?, approved_monthly_amount=?,
                     approved_start_month=?, fm_customized=?, fm_customization_reason=?,
                     fm_reviewed_by=?, fm_reviewed_at=NOW(), updated_at=NOW()
                 WHERE id=? AND status IN ('submitted','fm_review') AND closed_at IS NULL"
            )->execute([
                $decision['approved_amount'], $decision['approved_repayment_method'],
                $decision['approved_monthly_amount'], $decision['approved_start_month'],
                $decision['customized'], $decision['customization_reason'], $uid, $requestId
            ]);

            if ($pdo->rowCount() !== 1) {
                throw new RuntimeException('تعذر حفظ قرار اعتماد طلب السلفة.');
            }

            $customText = $decision['customized'] ? ' بعد تخصيص شروط الطلب من قبل المدير المالي.' : '';
            $notificationTitle = 'تم اعتماد طلب السلفة';
            $notificationBody = 'تم اعتماد طلب السلفة «' . (string)$request['request_no'] . '».' . $customText;
            $notificationType = 'salary_advance_request_approval';
            $message = 'تم اعتماد طلب السلفة وإبلاغ الموظف. لم يتم تنفيذ أي صرف أو قيد محاسبي في هذه المرحلة.';
        }

        $pdo->prepare(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, 'hr_salary_advance_request', ?, ?, ?, ?, ?)"
        )->execute([
            $uid,
            $decision['decision'] === 'approve' ? 'HR_SALARY_ADVANCE_FM_APPROVE' : 'HR_SALARY_ADVANCE_FM_REJECT',
            $requestId,
            json_encode([
                'status' => $request['status'],
                'closed_at' => $request['closed_at'] ?? null,
                'requested_amount' => $request['requested_amount'],
                'requested_repayment_method' => $request['requested_repayment_method'],
                'requested_monthly_amount' => $request['requested_monthly_amount'],
                'requested_start_month' => $request['requested_start_month'],
            ], JSON_UNESCAPED_UNICODE),
            json_encode(array_merge($decision, $decision['decision'] === 'reject' ? [
                'status' => 'rejected',
                'closed' => true,
            ] : [
                'status' => 'approved',
            ]), JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        $pdo->commit();

        // Notify the employee account after the database transaction is committed.
        // Use employees.user_id as the canonical employee-account link. Keep
        // submitted_by only as a legacy fallback for older employee records.
        $employeeRecipient = dbFetchOne(
            "SELECT user_id FROM employees WHERE id = ? LIMIT 1",
            [(int)$request['employee_id']]
        );
        $employeeUserId = (int)($employeeRecipient['user_id'] ?? 0);
        if ($employeeUserId <= 0) {
            $employeeUserId = (int)$request['submitted_by'];
        }

        ak_transaction_review_notify_event(
            $employeeUserId,
            $notificationTitle,
            $notificationBody,
            APP_URL . 'modules/hr/salary_advance_request.php',
            $requestId,
            $notificationType
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$request = $requestId > 0 ? hrSalaryAdvanceGetRequestForFm($pdo, $requestId) : null;
$mismatches = $request ? hrSalaryAdvanceFmPolicyMismatches($pdo, $request) : [];
$queue = dbFetchAll(
    "SELECT r.id, r.request_no, r.requested_amount, r.requested_repayment_method,
            r.requested_start_month, r.status, r.submitted_at,
            e.full_name AS employee_name, e.employee_code
     FROM hr_salary_advance_requests r
     JOIN employees e ON e.id = r.employee_id
     WHERE r.status IN ('submitted','fm_review')
       AND r.closed_at IS NULL
     ORDER BY r.submitted_at ASC, r.id ASC"
);

$pageTitle = 'مراجعة سلف الرواتب';
$active = 'fm_dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.salary-advance-fm{max-width:1250px;margin:0 auto}.salary-advance-fm .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:22px 25px;margin-bottom:16px}.salary-advance-fm .hero h1{font-size:1.35rem;font-weight:800;margin:0}.salary-advance-fm .card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;box-shadow:0 2px 12px rgba(16,24,40,.05);margin-bottom:15px;overflow:hidden}.salary-advance-fm .card-body{padding:16px}.salary-advance-fm .original{background:#f8f9fa;border-radius:10px;padding:14px}.salary-advance-fm .mismatch{border-right:4px solid #dc3545;background:#fff5f5;padding:10px 12px;border-radius:8px;margin-bottom:8px}.salary-advance-fm .match{border-right:4px solid #198754;background:#f3fbf6;padding:10px 12px;border-radius:8px}
</style>
<div class="salary-advance-fm">
<section class="hero"><h1><i class="fas fa-hand-holding-dollar me-2"></i>مراجعة سلف الرواتب</h1><div class="mt-1 opacity-75 small">مراجعة طلب الموظف واتخاذ قرار FM دون تعديل السياسة العامة أو تنفيذ الصرف المحاسبي.</div></section>
<?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

<div class="card"><div class="card-body">
<h5 class="mb-3">طلبات بانتظار مراجعة FM</h5>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>الطلب</th><th>الموظف</th><th>المبلغ المطلوب</th><th>طريقة السداد</th><th>التاريخ</th><th></th></tr></thead><tbody>
<?php foreach($queue as $q): ?>
<tr><td><strong><?=e($q['request_no'])?></strong><div class="small text-muted"><?=e($q['status'])?></div></td><td><?=e($q['employee_name'])?><div class="small text-muted"><?=e($q['employee_code'])?></div></td><td><?=number_format((float)$q['requested_amount'],2)?> SDG</td><td><?=e(['fixed_monthly'=>'قسط شهري ثابت','full_eligible_salary'=>'كامل الراتب المؤهل','full_settlement'=>'تسوية كاملة','direct_repayment'=>'سداد مباشر'][$q['requested_repayment_method']]??$q['requested_repayment_method'])?></td><td><?=e($q['submitted_at'])?></td><td><a class="btn btn-sm btn-primary" href="<?=e(APP_URL.'modules/hr/salary_advance_fm_review.php?id='.(int)$q['id'])?>">مراجعة</a></td></tr>
<?php endforeach; if(!$queue): ?><tr><td colspan="6" class="text-center text-muted py-3">لا توجد طلبات بانتظار مراجعة FM.</td></tr><?php endif; ?>
</tbody></table></div></div></div>

<?php if($request): ?>
<div class="card"><div class="card-body">
<h5>تفاصيل الطلب <?=e($request['request_no'])?></h5>
<div class="row g-3 mt-1">
<div class="col-md-4"><strong>الموظف:</strong> <?=e($request['employee_name'])?></div>
<div class="col-md-4"><strong>الكود:</strong> <?=e($request['employee_code'])?></div>
<div class="col-md-4"><strong>الراتب الأساسي:</strong> <?=number_format((float)$request['basic_salary'],2)?> SDG</div>
</div>
<hr>
<div class="original">
<h6>الطلب الأصلي المقدم من الموظف</h6>
<div class="row g-3">
<div class="col-md-3"><strong>المبلغ:</strong> <?=number_format((float)$request['requested_amount'],2)?> SDG</div>
<div class="col-md-3"><strong>السداد:</strong> <?=e(['fixed_monthly'=>'قسط شهري ثابت','full_eligible_salary'=>'كامل الراتب المؤهل','full_settlement'=>'تسوية كاملة','direct_repayment'=>'سداد مباشر'][$request['requested_repayment_method']]??$request['requested_repayment_method'])?></div>
<div class="col-md-3"><strong>القسط:</strong> <?= $request['requested_monthly_amount']!==null ? number_format((float)$request['requested_monthly_amount'],2).' SDG' : '—' ?></div>
<div class="col-md-3"><strong>بدء السداد:</strong> <?=e($request['requested_start_month']??'—')?></div>
<div class="col-12"><strong>سبب الطلب:</strong> <?=e($request['request_reason']??'—')?></div>
</div></div>
</div></div>

<div class="card"><div class="card-body">
<h5>مقارنة الطلب بالسياسة المرجعية V<?= (int)$request['version_no'] ?></h5>
<div class="small text-muted mb-3">السياسة المستخدمة للمقارنة هي السياسة المرتبطة بالطلب وقت تقديمه. لا يتم تعديلها من هذه الصفحة.</div>
<?php if($mismatches): foreach($mismatches as $m): ?><div class="mismatch"><i class="fas fa-triangle-exclamation me-1"></i><?=e($m)?></div><?php endforeach; else: ?><div class="match"><i class="fas fa-check me-1"></i>الطلب متوافق مع القواعد الظاهرة في السياسة المرجعية.</div><?php endif; ?>
</div></div>

<?php if(in_array((string)$request['status'],['submitted','fm_review'],true) && empty($request['closed_at'])): ?>
<div class="card"><div class="card-body">
<h5>قرار المدير المالي</h5>
<form method="post" id="fmDecisionForm">
<?=csrf_field()?>
<input type="hidden" name="request_id" value="<?= (int)$request['id']?>">
<input type="hidden" name="fm_decision_submit" value="1">
<?php if($mismatches): ?>
<?php if((int)$request['allow_custom_repayment_terms']): ?>
<div class="alert alert-warning"><strong>الطلب غير متوافق مع السياسة المرجعية.</strong> يجب على FM إما تخصيص شروط هذا الطلب فقط أو رفض الطلب.</div>
<div class="form-check mb-3">
<input class="form-check-input" type="checkbox" name="fm_customized" id="fm_customized" value="1" checked>
<label class="form-check-label" for="fm_customized"><strong>تخصيص شروط هذا الطلب فقط</strong></label>
</div>
<div class="row g-3">
<div class="col-md-3"><label class="form-label">المبلغ المعتمد</label><input type="number" step="0.01" min="0.01" name="approved_amount" class="form-control" value="<?=e((string)$request['requested_amount'])?>"></div>
<div class="col-md-3"><label class="form-label">طريقة السداد المعتمدة</label><select name="approved_repayment_method" class="form-select"><option value="fixed_monthly">قسط شهري ثابت</option><option value="full_eligible_salary">كامل الراتب المؤهل</option><option value="full_settlement">تسوية كاملة</option><option value="direct_repayment">سداد مباشر</option></select></div>
<div class="col-md-3"><label class="form-label">القسط الشهري المعتمد</label><input type="number" step="0.01" min="0.01" name="approved_monthly_amount" class="form-control" value="<?=e((string)($request['requested_monthly_amount']??''))?>"></div>
<div class="col-md-3"><label class="form-label">شهر بدء السداد</label><input type="date" name="approved_start_month" class="form-control" value="<?=e((string)($request['requested_start_month']??''))?>"></div>
<div class="col-12"><label class="form-label">سبب التخصيص</label><textarea name="fm_customization_reason" class="form-control" rows="2" maxlength="2000"></textarea></div>
</div>
<?php else: ?>
<div class="alert alert-danger">الطلب غير متوافق مع السياسة المرجعية، والسياسة لا تسمح بتخصيص شروط السداد. يمكن رفض الطلب فقط.</div>
<?php endif; ?>
<?php endif; ?>
<div class="col-md-6"><label class="form-label">سبب الرفض <span class="text-muted">(مطلوب عند الرفض)</span></label><textarea name="fm_rejection_reason" class="form-control" rows="2" maxlength="2000"></textarea></div>
<div class="col-12 d-flex gap-2">
<button class="btn btn-success" name="decision" value="approve" type="submit">اعتماد الطلب</button>
<button class="btn btn-danger" name="decision" value="reject" type="submit">رفض الطلب</button>
</div>
</div></form>
</div></div>
<?php endif; ?>
<?php endif; ?>
</div>
<script>
(function(){
 const c=document.getElementById('fm_customized');
 const fields=['approved_amount','approved_repayment_method','approved_monthly_amount','approved_start_month','fm_customization_reason'];
 function sync(){
   fields.forEach(function(n){const el=document.querySelector('[name="'+n+'"]'); if(el) el.disabled=!c.checked;});
 }
 c?.addEventListener('change',sync); sync();
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
