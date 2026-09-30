<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_salary_advance_request.php';
require_once __DIR__ . '/lib_salary_advance_accounting.php';
require_once __DIR__ . '/../accounting/lib_transaction_review.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}

$role = (string)Session::getUserRole();
$canFm = hrSalaryAdvanceFmCanReview($role);
$canAccounting = hrSalaryAdvanceAccountingCan($role);

if (!$canFm && !$canAccounting) {
    flash('error', 'غير مصرح لك بمعالجة طلبات السلف على الراتب.');
    header('Location: ' . APP_URL . 'dashboard/staff_dashboard.php');
    exit;
}

$pdo = db();
$message = '';
$error = '';
$requestId = (int)($_GET['id'] ?? $_POST['request_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        }

        if (isset($_POST['fm_decision_submit'])) {
            if (!$canFm) {
                throw new RuntimeException('لا يملك المستخدم الحالي صلاحية اتخاذ قرار FM.');
            }

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
                $updateStmt = $pdo->prepare(
                    "UPDATE hr_salary_advance_requests
                     SET status='rejected', fm_decision='rejected', fm_rejection_reason=?,
                         fm_reviewed_by=?, fm_reviewed_at=NOW(), closed_at=NOW(), updated_at=NOW()
                     WHERE id=? AND status IN ('submitted','fm_review') AND closed_at IS NULL"
                );
                $updateStmt->execute([$decision['reason'], $uid, $requestId]);

                if ($updateStmt->rowCount() !== 1) {
                    throw new RuntimeException('تعذر إغلاق طلب السلفة بعد رفضه.');
                }

                $notificationTitle = 'تم رفض وإغلاق طلب السلفة';
                $notificationBody = 'تم رفض وإغلاق طلب السلفة «' . (string)$request['request_no'] . '». سبب الرفض: ' . $decision['reason'];
                $notificationType = 'salary_advance_request_rejection';
                $message = 'تم رفض طلب السلفة وإغلاقه وإبلاغ الموظف.';
            } else {
                $updateStmt = $pdo->prepare(
                    "UPDATE hr_salary_advance_requests
                     SET status='approved', fm_decision='approved', fm_rejection_reason=NULL,
                         approved_amount=?, approved_repayment_method=?, approved_monthly_amount=?,
                         approved_start_month=?, fm_customized=?, fm_customization_reason=?,
                         fm_reviewed_by=?, fm_reviewed_at=NOW(), updated_at=NOW()
                     WHERE id=? AND status IN ('submitted','fm_review') AND closed_at IS NULL"
                );
                $updateStmt->execute([
                    $decision['approved_amount'], $decision['approved_repayment_method'],
                    $decision['approved_monthly_amount'], $decision['approved_start_month'],
                    $decision['customized'], $decision['customization_reason'], $uid, $requestId
                ]);

                if ($updateStmt->rowCount() !== 1) {
                    throw new RuntimeException('تعذر حفظ قرار اعتماد طلب السلفة.');
                }

                $message = 'تم اعتماد طلب السلفة بنجاح.';
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

            $employeeRecipient = dbFetchOne(
                "SELECT user_id FROM employees WHERE id = ? LIMIT 1",
                [(int)$request['employee_id']]
            );
            $employeeUserId = (int)($employeeRecipient['user_id'] ?? 0);
            if ($employeeUserId <= 0) {
                $employeeUserId = (int)$request['submitted_by'];
            }
             if ($decision['decision'] === 'reject') {
                 ak_transaction_review_notify_event(
                     $employeeUserId,
                     $notificationTitle,
                     $notificationBody,
                     APP_URL . 'modules/hr/salary_advance_request.php',
                     $requestId,
                     $notificationType
                 );
             }
        } elseif (isset($_POST['accounting_verify']) || isset($_POST['accounting_reject'])) {
            if (!$canAccounting) {
                throw new RuntimeException('لا يملك المستخدم الحالي صلاحية التحقق المحاسبي.');
            }

            $decision = isset($_POST['accounting_verify']) ? 'verify' : 'reject';
            hrSalaryAdvanceAccountingVerify(
                $pdo,
                $requestId,
                (int)Session::getUserID(),
                $decision,
                trim((string)($_POST['accounting_rejection_reason'] ?? ''))
            );
            $message = $decision === 'verify'
                ? 'تم اعتماد التحقق المحاسبي بنجاح.'
                : 'تم رفض التحقق المحاسبي.';
        } elseif (isset($_POST['disburse_salary_advance'])) {
            if (!$canAccounting) {
                throw new RuntimeException('لا يملك المستخدم الحالي صلاحية صرف السلفة.');
            }

            $cashAccountId = (int)($_POST['disbursement_account_id'] ?? 0);
            $reference = trim((string)($_POST['disbursement_reference'] ?? ''));
            $entryId = hrSalaryAdvanceAccountingDisburse(
                $pdo,
                $requestId,
                (int)Session::getUserID(),
                $cashAccountId,
                $reference
            );
            $message = 'تم صرف السلفة وترحيل القيد المحاسبي رقم ' . $entryId . ' بنجاح. تم إنشاء جدول السداد.';
        } elseif (isset($_POST['upload_payment_receipt'])) {
            if (!$canAccounting) {
                throw new RuntimeException('لا يملك المستخدم الحالي صلاحية رفع إيصال الدفع.');
            }

            hrSalaryAdvanceAccountingUploadReceipt(
                $pdo,
                $requestId,
                (int)Session::getUserID(),
                $_FILES['payment_receipt'] ?? []
            );
            $message = 'تم رفع إيصال الدفع بنجاح.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$fmQueue = $canFm ? dbFetchAll(
    "SELECT r.id, r.request_no, r.requested_amount, r.requested_repayment_method,
            r.requested_start_month, r.status, r.submitted_at,
            e.full_name AS employee_name, e.employee_code
     FROM hr_salary_advance_requests r
     JOIN employees e ON e.id = r.employee_id
     WHERE r.status IN ('submitted','fm_review')
       AND r.closed_at IS NULL
     ORDER BY r.submitted_at ASC, r.id ASC"
) : [];

$accountingQueue = $canAccounting ? hrSalaryAdvanceAccountingQueue($pdo) : [];
$queueMap = [];

foreach ($fmQueue as $q) {
    $queueMap[(int)$q['id']] = [
        'id' => (int)$q['id'],
        'request_no' => $q['request_no'],
        'employee_name' => $q['employee_name'],
        'employee_code' => $q['employee_code'],
        'amount' => (float)$q['requested_amount'],
        'status' => $q['status'],
        'stage' => 'fm',
        'stage_label' => 'مراجعة FM',
        'date' => $q['submitted_at'],
    ];
}
foreach ($accountingQueue as $q) {
    $queueMap[(int)$q['id']] = [
        'id' => (int)$q['id'],
        'request_no' => $q['request_no'],
        'employee_name' => $q['employee_name'],
        'employee_code' => $q['employee_code'],
        'amount' => (float)$q['approved_amount'],
        'status' => $q['status'],
        'stage' => 'accounting',
        'stage_label' => $q['accounting_status'] === 'rejected' ? 'إعادة تحقق محاسبي' : 'التحقق المحاسبي والصرف',
        'date' => $q['fm_reviewed_at'],
    ];
}
$queue = array_values($queueMap);
usort($queue, static function(array $a, array $b): int {
    return strcmp((string)$a['date'], (string)$b['date']) ?: ($a['id'] <=> $b['id']);
});

$fmRequest = $requestId > 0 ? hrSalaryAdvanceGetRequestForFm($pdo, $requestId) : null;
$accountingRequest = $requestId > 0 ? hrSalaryAdvanceAccountingGetRequest($pdo, $requestId) : null;
$request = $fmRequest && $accountingRequest
    ? array_merge($fmRequest, $accountingRequest)
    : ($fmRequest ?: $accountingRequest);

$mismatches = ($request && $fmRequest) ? hrSalaryAdvanceFmPolicyMismatches($pdo, $fmRequest) : [];
$repaymentSchedule = $request && $request['status'] === 'disbursed'
    ? hrSalaryAdvanceScheduleGet($pdo, (int)$request['id'])
    : [];

$cashAccounts = [];
$balances = [];
if ($canAccounting) {
    $cashAccounts = dbFetchAll(
        "SELECT id, code, name_ar
         FROM accounts
         WHERE code IN ('1100','1200','1300') AND is_active = 1
         ORDER BY code"
    );
    foreach ($cashAccounts as $cash) {
        $balances[(int)$cash['id']] = ak_voucher_cash_balance((int)$cash['id']);
    }
}

$pageTitle = 'معالجة سلف الرواتب';
$active = 'salary_advance_portal';
$salaryAdvanceBackUrl = APP_URL . 'modules/hr/salary_advance_dashboard.php';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.salary-advance-process{max-width:1350px;margin:0 auto}
.salary-advance-process .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:22px 25px;margin-bottom:16px}
.salary-advance-process .hero h1{font-size:1.4rem;font-weight:800;margin:0}
.salary-advance-process .hero p{font-size:.82rem;margin:6px 0 0;opacity:.92}
.salary-advance-process .workflow-tabs{margin-bottom:16px;border-bottom:1px solid #dfe5ec}
.salary-advance-process .workflow-tabs .nav-link{font-weight:700;color:#667085}
.salary-advance-process .workflow-tabs .nav-link.active{color:#0d6efd;background:#eef5ff;border-color:#0d6efd #0d6efd #eef5ff;font-weight:800;box-shadow:inset 0 -3px 0 #0d6efd}
.salary-advance-process .workflow-tabs .nav-link.done{color:#176b3a}
.salary-advance-process .workflow-tab-panel{display:none}
.salary-advance-process .workflow-tab-panel.active{display:block}
.salary-advance-process .card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;box-shadow:0 2px 12px rgba(16,24,40,.05);margin-bottom:15px;overflow:hidden}
.salary-advance-process .card-body{padding:16px}
.salary-advance-process .original{background:#f8f9fa;border-radius:10px;padding:14px}
.salary-advance-process .mismatch{border-right:4px solid #dc3545;background:#fff5f5;padding:10px 12px;border-radius:8px;margin-bottom:8px}
.salary-advance-process .match{border-right:4px solid #198754;background:#f3fbf6;padding:10px 12px;border-radius:8px}
.salary-advance-process .stage-fm{color:#173f73;font-weight:700}
.salary-advance-process .stage-accounting{color:#176b3a;font-weight:700}
.salary-advance-process .ak-workflow-toast{
    position:fixed;
    top:90px;
    right:24px;
    z-index:1080;
    min-width:320px;
    max-width:460px;
    padding:13px 16px;
    border:1px solid #badbcc;
    border-radius:10px;
    background:#f0fff4;
    color:#146c43;
    box-shadow:0 8px 24px rgba(16,24,40,.14);
    font-weight:700;
    animation:akWorkflowToastIn .2s ease-out;
}
@keyframes akWorkflowToastIn{
    from{opacity:0;transform:translateY(-8px)}
    to{opacity:1;transform:translateY(0)}
}
@media (max-width:576px){
    .salary-advance-process .ak-workflow-toast{
        left:16px;
        right:16px;
        top:76px;
        min-width:0;
        max-width:none;
    }
}
</style>

<script>window.AK_PAGE_BACK_URL=<?php echo json_encode($salaryAdvanceBackUrl, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;</script>
<div class="salary-advance-process">
<section class="hero">
    <h1><i class="fas fa-hand-holding-dollar me-2"></i>معالجة سلف الرواتب</h1>
    <p>مسار موحد لمعالجة الطلب من مراجعة FM، إلى التحقق المحاسبي، ثم الصرف وإنشاء جدول السداد — دون الانتقال بين صفحات معالجة مختلفة.</p>
</section>

<?php if($message): ?><div class="ak-workflow-toast" role="status" aria-live="polite"><i class="fas fa-circle-check me-2"></i><span><?=e($message)?></span></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

<?php
$workflowStep = 1;
if ($request && in_array((string)$request['status'], ['disbursed','settled'], true)) {
    $workflowStep = 4;
} elseif ($request && (string)$request['status'] === 'approved') {
    $workflowStep = (($request['accounting_status'] ?? '') === 'verified') ? 3 : 2;
}
?>
<ul class="nav nav-tabs workflow-tabs" role="tablist">
    <li class="nav-item"><button type="button" class="nav-link <?= $workflowStep === 1 ? 'active' : '' ?> <?= $request && in_array((string)$request['status'], ['approved','disbursed','settled'], true) ? 'done' : '' ?>" data-workflow-tab="1"><i class="fas fa-user-check me-1"></i>1. مراجعة FM</button></li>
    <li class="nav-item"><button type="button" class="nav-link <?= $workflowStep === 2 ? 'active' : '' ?> <?= $request && ($request['accounting_status'] ?? '') === 'verified' ? 'done' : '' ?>" data-workflow-tab="2"><i class="fas fa-calculator me-1"></i>2. التحقق المحاسبي</button></li>
    <li class="nav-item"><button type="button" class="nav-link <?= $workflowStep === 3 ? 'active' : '' ?> <?= $request && in_array((string)$request['status'], ['disbursed','settled'], true) ? 'done' : '' ?>" data-workflow-tab="3"><i class="fas fa-money-bill-transfer me-1"></i>3. الصرف</button></li>
    <li class="nav-item"><button type="button" class="nav-link <?= $workflowStep === 4 ? 'active' : '' ?>" data-workflow-tab="4"><i class="fas fa-calendar-check me-1"></i>4. جدول السداد</button></li>
</ul>

<div class="card"><div class="card-body">
<h5 class="mb-3">طلبات السلف التي تتطلب إجراء</h5>
<div class="table-responsive"><table class="table table-sm align-middle mb-0">
<thead><tr><th>الطلب</th><th>الموظف</th><th>المبلغ</th><th>المرحلة الحالية</th><th>التاريخ</th><th></th></tr></thead>
<tbody>
<?php foreach($queue as $q): ?>
<tr>
    <td><strong><?=e($q['request_no'])?></strong></td>
    <td><?=e($q['employee_name'])?><div class="small text-muted"><?=e($q['employee_code'])?></div></td>
    <td><?=number_format((float)$q['amount'],2)?> SDG</td>
    <td class="<?=$q['stage']==='fm'?'stage-fm':'stage-accounting'?>"><?=e($q['stage_label'])?></td>
    <td><?=e($q['date'])?></td>
    <td><a class="btn btn-sm btn-primary" href="<?=e(APP_URL.'modules/hr/salary_advance_processing.php?id='.(int)$q['id'])?>">فتح الطلب</a></td>
</tr>
<?php endforeach; if(!$queue): ?>
<tr><td colspan="6" class="text-center text-muted py-3">لا توجد طلبات تتطلب إجراء حالياً.</td></tr>
<?php endif; ?>
</tbody></table></div>
</div></div>

<?php if($request): ?>
<div class="card"><div class="card-body">
<h5>تفاصيل الطلب <?=e($request['request_no'])?></h5>
<div class="row g-3 mt-1">
<div class="col-md-3"><strong>الموظف:</strong><br><?=e($request['employee_name'])?></div>
<div class="col-md-3"><strong>الكود:</strong><br><?=e($request['employee_code'])?></div>
<div class="col-md-3"><strong>الراتب الأساسي:</strong><br><?=number_format((float)($request['basic_salary'] ?? 0),2)?> SDG</div>
<div class="col-md-3"><strong>حالة الطلب:</strong><br><span class="badge bg-secondary"><?=e((string)$request['status'])?></span></div>
</div>
<hr>
<div class="original">
<h6>الطلب الأصلي المقدم من الموظف</h6>
<div class="row g-3">
<div class="col-md-3"><strong>المبلغ:</strong> <?=number_format((float)$request['requested_amount'],2)?> SDG</div>
<div class="col-md-3"><strong>السداد:</strong> <?=e(['fixed_monthly'=>'قسط شهري ثابت','full_eligible_salary'=>'كامل الراتب المؤهل','full_settlement'=>'تسوية كاملة','direct_repayment'=>'سداد مباشر'][$request['requested_repayment_method']]??$request['requested_repayment_method'])?></div>
<div class="col-md-3"><strong>القسط:</strong> <?=$request['requested_monthly_amount']!==null?number_format((float)$request['requested_monthly_amount'],2).' SDG':'—'?></div>
<div class="col-md-3"><strong>بدء السداد:</strong> <?=e($request['requested_start_month']??'—')?></div>
<div class="col-12"><strong>سبب الطلب:</strong> <?=e($request['request_reason']??'—')?></div>
</div>
</div>
</div></div>

<?php if($canFm && $fmRequest): ?>
<div class="workflow-tab-panel <?= $workflowStep === 1 ? 'active' : '' ?>" data-workflow-panel="1">
<div class="card"><div class="card-body">
<h6>مقارنة الطلب بالسياسة المرجعية V<?= (int)$fmRequest['version_no'] ?></h6>
<?php if($mismatches): foreach($mismatches as $m): ?><div class="mismatch"><i class="fas fa-triangle-exclamation me-1"></i><?=e($m)?></div><?php endforeach; else: ?><div class="match"><i class="fas fa-check me-1"></i>الطلب متوافق مع القواعد الظاهرة في السياسة المرجعية.</div><?php endif; ?>

<?php if(in_array((string)$request['status'],['submitted','fm_review'],true) && empty($request['closed_at'])): ?>
<hr>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="request_id" value="<?= (int)$request['id']?>">
<input type="hidden" name="fm_decision_submit" value="1">
<?php if($mismatches && (int)$fmRequest['allow_custom_repayment_terms']): ?>
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
<?php elseif($mismatches): ?>
<div class="alert alert-danger">الطلب غير متوافق مع السياسة المرجعية، والسياسة لا تسمح بتخصيص شروط السداد. يمكن رفض الطلب فقط.</div>
<?php endif; ?>
<div class="col-md-6 mt-3"><label class="form-label">سبب الرفض <span class="text-muted">(مطلوب عند الرفض)</span></label><textarea name="fm_rejection_reason" class="form-control" rows="2" maxlength="2000"></textarea></div>
<div class="d-flex gap-2 mt-3">
<?php $fmApproveDisabled = $mismatches && !(int)$fmRequest['allow_custom_repayment_terms']; ?>
<button class="btn btn-success" name="decision" value="approve" type="submit"<?= $fmApproveDisabled ? ' disabled' : '' ?>>اعتماد الطلب والانتقال للتحقق المحاسبي</button>
<button class="btn btn-danger" name="decision" value="reject" type="submit">رفض الطلب</button>
</div>
</form>
<?php elseif($request['status']==='approved'): ?>
<?php elseif($request['status']==='disbursed'): ?>
<?php endif; ?>
</div></div>
</div>
<?php endif; ?>

<?php if($canAccounting && $request['status']==='approved'): ?>
<div class="workflow-tab-panel <?= $workflowStep === 2 ? 'active' : '' ?>" data-workflow-panel="2">
<div class="card"><div class="card-body">
<div class="alert alert-info">
الحساب المستهدف للسلفة: <strong>1410 — ذمم سلف الموظفين</strong>.
لا يتم استخدام حساب مصروف. الصرف ينشئ ذمة على الموظف مقابل خفض حساب الصندوق/البنك/المحفظة.
</div>
<div class="row g-3">
<div class="col-md-4"><strong>المبلغ المعتمد:</strong><br><?=number_format((float)$request['approved_amount'],2)?> SDG</div>
<div class="col-md-4"><strong>طريقة السداد:</strong><br><?=e(['fixed_monthly'=>'قسط شهري ثابت','full_eligible_salary'=>'كامل الراتب المؤهل','full_settlement'=>'تسوية كاملة','direct_repayment'=>'سداد مباشر'][$request['approved_repayment_method']]??$request['approved_repayment_method'])?></div>
<div class="col-md-4"><strong>القسط الشهري:</strong><br><?=$request['approved_monthly_amount']!==null?number_format((float)$request['approved_monthly_amount'],2).' SDG':'—'?></div>
<div class="col-md-4"><strong>بدء السداد:</strong><br><?=e($request['approved_start_month']??'—')?></div>
<div class="col-md-4"><strong>حالة التحقق:</strong><br><?=e((string)$request['accounting_status'])?></div>
<?php if(!empty($request['accounting_rejection_reason'])): ?><div class="col-md-8 text-danger"><strong>سبب الرفض المحاسبي:</strong><br><?=e($request['accounting_rejection_reason'])?></div><?php endif; ?>
</div>

<?php if($request['accounting_status']!=='verified'): ?>
<hr>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="request_id" value="<?= (int)$request['id']?>">
<div class="mb-3"><label class="form-label">سبب الرفض المحاسبي <span class="text-muted">(مطلوب عند الرفض)</span></label><textarea name="accounting_rejection_reason" class="form-control" rows="2" maxlength="2000"></textarea></div>
<div class="d-flex gap-2">
<button type="submit" name="accounting_verify" value="1" class="btn btn-success">اعتماد التحقق المحاسبي</button>
<button type="submit" name="accounting_reject" value="1" class="btn btn-danger">رفض التحقق</button>
</div>
</form>
<?php else: ?>
<?php endif; ?>
</div></div>
</div>

<div class="workflow-tab-panel <?= $workflowStep === 3 ? 'active' : '' ?>" data-workflow-panel="3">
<div class="card"><div class="card-body">
<?php if((int)$request['require_accounting_verification']===1 && $request['accounting_status']!=='verified'): ?>
<div class="alert alert-warning mb-0">لا يمكن الصرف قبل اعتماد التحقق المحاسبي.</div>
<?php else: ?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="request_id" value="<?= (int)$request['id']?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">حساب الصرف *</label><select name="disbursement_account_id" class="form-select" required><option value="">— اختر الصندوق/البنك/المحفظة —</option><?php foreach($cashAccounts as $cash): ?><option value="<?= (int)$cash['id']?>"><?=e($cash['code'].' — '.$cash['name_ar'])?> — الرصيد <?=number_format((float)$balances[(int)$cash['id']],2)?> SDG</option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">مرجع الصرف <span class="text-muted">(اختياري)</span></label><input type="text" name="disbursement_reference" class="form-control" maxlength="100" value="<?=e((string)($request['disbursement_reference']??''))?>"></div>
</div>
<button type="submit" name="disburse_salary_advance" value="1" class="btn btn-primary mt-3"><i class="fas fa-money-bill-transfer me-1"></i>صرف وترحيل <?=number_format((float)$request['approved_amount'],2)?> SDG</button>
</form>
<?php endif; ?>
</div></div>
</div>
<?php endif; ?>

<?php if($request['status']==='disbursed'): ?>
<div class="workflow-tab-panel <?= $workflowStep === 4 ? 'active' : '' ?>" data-workflow-panel="4">
<div class="card"><div class="card-body">
<h5>النتيجة — تم الصرف</h5>
<div class="row g-3">
<div class="col-md-3"><strong>تاريخ الصرف:</strong><br><?=e($request['disbursed_at'])?></div>
<div class="col-md-3"><strong>صرف بواسطة:</strong><br><?=e($request['disbursed_by_name']??'—')?></div>
<div class="col-md-3"><strong>حساب الصرف:</strong><br><?=e(($request['disbursement_account_code']??'').' — '.($request['disbursement_account_name']??''))?></div>
<div class="col-md-3"><strong>القيد:</strong><br><?=e($request['disbursement_entry_code']??'—')?></div>
<div class="col-md-6"><strong>المرجع:</strong><br><?=e($request['disbursement_reference']??'—')?></div>
<div class="col-md-6"><strong>الرصيد القائم:</strong><br><?=number_format((float)$request['outstanding_balance'],2)?> SDG</div>
</div>
<div class="d-flex flex-wrap gap-2 mt-3">
<a class="btn btn-outline-primary" target="_blank" href="<?=e(APP_URL.'modules/hr/salary_advance_voucher_print.php?id='.(int)$request['id'])?>"><i class="fas fa-print me-1"></i>طباعة سند الصرف</a>
</div>
</div></div>

<div class="card"><div class="card-body">
<h5>جدول السداد</h5>
<?php if(!$repaymentSchedule): ?>
<div class="alert alert-warning mb-0">لم يتم إنشاء جدول سداد لهذه السلفة.</div>
<?php else: ?>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>القسط</th><th>شهر السداد</th><th>المبلغ المجدول</th><th>المبلغ المطبق</th><th>الحالة</th></tr></thead><tbody>
<?php foreach($repaymentSchedule as $installment): ?>
<?php $scheduleBadge=['pending'=>['معلق','secondary'],'partial'=>['جزئي','warning'],'paid'=>['مسدد','success'],'skipped'=>['متجاوز','danger']][$installment['status']]??[$installment['status'],'secondary']; ?>
<tr><td><?= (int)$installment['installment_no']?></td><td><?=e($installment['scheduled_month'])?></td><td><?=number_format((float)$installment['scheduled_amount'],2)?> SDG</td><td><?=number_format((float)($installment['applied_amount']??0),2)?> SDG</td><td><span class="badge bg-<?=e($scheduleBadge[1])?>"><?=e($scheduleBadge[0])?></span></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div></div>

<?php if($canAccounting): ?>
<div class="card"><div class="card-body">
<h5>إيصال الدفع</h5>
<?php if(!empty($request['payment_receipt_id'])): ?>
<div class="alert alert-success">تم رفع إيصال الدفع: <strong><?=e($request['payment_receipt_name'])?></strong><?php if(!empty($request['payment_receipt_uploaded_at'])): ?> — <?=e($request['payment_receipt_uploaded_at'])?><?php endif; ?></div>
<div class="d-flex flex-wrap gap-2">
<a class="btn btn-outline-primary" target="_blank" href="<?=e(APP_URL.'modules/hr/salary_advance_receipt.php?id='.(int)$request['payment_receipt_id'])?>"><i class="fas fa-file-arrow-up me-1"></i>عرض الإيصال</a>
<form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
<?=csrf_field()?><input type="hidden" name="request_id" value="<?= (int)$request['id']?>">
<input type="file" name="payment_receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
<button type="submit" name="upload_payment_receipt" value="1" class="btn btn-outline-secondary"><i class="fas fa-arrows-rotate me-1"></i>استبدال الإيصال</button>
</form>
</div>
<?php else: ?>
<div class="alert alert-warning">لم يتم رفع إيصال الدفع بعد.</div>
<form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
<?=csrf_field()?><input type="hidden" name="request_id" value="<?= (int)$request['id']?>">
<div class="col-md-8"><label class="form-label">إيصال الدفع *</label><input type="file" name="payment_receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required><div class="form-text">JPG / PNG / PDF — بحد أقصى 5 ميجابايت.</div></div>
<div class="col-md-4"><button type="submit" name="upload_payment_receipt" value="1" class="btn btn-success w-100"><i class="fas fa-upload me-1"></i>رفع الإيصال</button></div>
</form>
<?php endif; ?>
</div></div>
<?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
<script>
(function(){
 const toast=document.querySelector('.ak-workflow-toast');
 if(toast){
   window.setTimeout(function(){
     toast.style.transition='opacity .25s ease, transform .25s ease';
     toast.style.opacity='0';
     toast.style.transform='translateY(-8px)';
     window.setTimeout(function(){toast.remove();},260);
   },5000);
 }
 document.querySelectorAll('[data-workflow-tab]').forEach(function(tab){
   tab.addEventListener('click',function(){
     const target=this.getAttribute('data-workflow-tab');
     document.querySelectorAll('[data-workflow-tab]').forEach(function(t){t.classList.toggle('active',t===tab);});
     document.querySelectorAll('[data-workflow-panel]').forEach(function(panel){panel.classList.toggle('active',panel.getAttribute('data-workflow-panel')===target);});
   });
 });
 const c=document.getElementById('fm_customized');
 const fields=['approved_amount','approved_repayment_method','approved_monthly_amount','approved_start_month','fm_customization_reason'];
 function sync(){
   fields.forEach(function(n){const el=document.querySelector('[name="'+n+'"]'); if(el) el.disabled=!c.checked;});
 }
 c?.addEventListener('change',sync); sync();
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
