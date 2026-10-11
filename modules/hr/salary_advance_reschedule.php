<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_salary_advance_repayment.php';
require_once __DIR__ . '/../accounting/lib_transaction_review.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}
$role = (string)Session::getUserRole();
if (!in_array($role, ['financial_manager', 'fm'], true)) {
    flash('error', 'هذه الصفحة متاحة للمدير المالي المخول فقط.');
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$pdo = db();
$message = '';
$error = '';
$result = null;
$requestId = (int)($_GET['id'] ?? $_POST['request_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        }
        if (!isset($_POST['reschedule_submit'])) {
            throw new RuntimeException('الإجراء المطلوب غير صالح.');
        }
        $result = hrSalaryAdvanceScheduleReschedule(
            $pdo,
            $requestId,
            trim((string)($_POST['start_month'] ?? '')),
            (int)($_POST['duration_months'] ?? 0),
            (float)($_POST['monthly_amount'] ?? 0),
            trim((string)($_POST['reason'] ?? '')),
            (int)Session::getUserID()
        );
        $message = 'تم إنشاء خطة السداد الجديدة رقم ' . (int)$result['plan_no'] .
            ' بعد التحقق من الرصيد وتسجيل التغيير في سجل التدقيق.';
        $employeeUserId = (int)($result['employee_user_id'] ?? 0);
        if ($employeeUserId > 0) {
            try {
                ak_transaction_review_notify_event(
                    $employeeUserId,
                    'تمت إعادة جدولة سلفة الراتب',
                    'تم اعتماد خطة سداد جديدة للسلفة «' . (string)$result['request_no'] .
                        '». الرصيد القائم عند إنشاء الخطة: ' .
                        number_format((float)$result['outstanding_balance'], 2) . ' ج.س.',
                    APP_URL . 'modules/hr/salary_advance_request.php',
                    $requestId,
                    'salary_advance_repayment_rescheduled'
                );
            } catch (Throwable $notificationError) {
                // The financial transaction has already committed. Notification
                // failure must not misreport or roll back the saved repayment plan.
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$eligible = dbFetchAll(
    "SELECT r.id, r.request_no, r.outstanding_balance, r.approved_monthly_amount,
            r.approved_repayment_method, e.full_name AS employee_name, e.employee_code,
            p.maximum_repayment_months AS policy_maximum_months,
            p.maximum_monthly_deduction AS policy_monthly_cap,
            rp.id AS active_plan_id, rp.plan_no, rp.start_month,
            rp.maximum_repayment_months AS active_plan_months,
            DATE_ADD(rp.start_month, INTERVAL rp.maximum_repayment_months MONTH) AS plan_expires
     FROM hr_salary_advance_requests r
     JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
     JOIN employees e ON e.id = r.employee_id
     JOIN hr_salary_advance_repayment_plans rp
       ON rp.salary_advance_request_id = r.id AND rp.status = 'active'
     WHERE r.status = 'disbursed'
       AND COALESCE(r.outstanding_balance, 0) > 0
       AND p.allow_fm_repayment_rescheduling = 1
       AND p.maximum_repayment_months > 0
       AND p.maximum_monthly_deduction > 0
       AND rp.maximum_repayment_months > 0
       AND DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') >=
           DATE_ADD(rp.start_month, INTERVAL rp.maximum_repayment_months MONTH)
     ORDER BY rp.start_month ASC, r.id ASC"
);

$selected = null;
foreach ($eligible as $item) {
    if ((int)$item['id'] === $requestId) {
        $selected = $item;
        break;
    }
}
if ($requestId > 0 && !$selected && !$message) {
    $error = $error ?: 'الطلب غير متاح لإعادة الجدولة: تحقق من حالة السلفة، وانتهاء الخطة، وتفويض السياسة المحفوظة.';
}

$defaultStart = date('Y-m-01', strtotime(date('Y-m-01') . ' +1 month'));
$pageTitle = 'إعادة جدولة سلف الرواتب';
$active = 'salary_advance_portal';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid" style="max-width:1250px">
    <div class="p-4 mb-3 rounded-3 text-white" style="background:linear-gradient(135deg,#173f73,#2d67ad)">
        <h1 class="h4 mb-2">إعادة جدولة سلف الرواتب</h1>
        <p class="mb-0">للمدير المالي فقط — تظهر هنا السلف المصروفة ذات الرصيد القائم التي انتهت خطتها وتسمح سياستها المحفوظة بإعادة الجدولة.</p>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-header fw-bold">السلف المؤهلة لإعادة الجدولة</div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead><tr>
                    <th>رقم الطلب</th><th>الموظف</th><th>الخطة الحالية</th>
                    <th>انتهاء الخطة</th><th>الرصيد القائم</th><th></th>
                </tr></thead>
                <tbody>
                <?php if (!$eligible): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">لا توجد حالياً سلف مستوفية لشروط إعادة الجدولة.</td></tr>
                <?php else: foreach ($eligible as $item): ?>
                    <tr>
                        <td><?=e((string)$item['request_no'])?></td>
                        <td><?=e((string)$item['employee_code'])?> — <?=e((string)$item['employee_name'])?></td>
                        <td>الخطة <?= (int)$item['plan_no'] ?></td>
                        <td><?=e((string)$item['plan_expires'])?></td>
                        <td><?=number_format((float)$item['outstanding_balance'], 2)?></td>
                        <td><a class="btn btn-sm btn-primary" href="<?=e(APP_URL . 'modules/hr/salary_advance_reschedule.php?id=' . (int)$item['id'])?>">إعادة الجدولة</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($selected): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold">خطة جديدة للطلب <?=e((string)$selected['request_no'])?></div>
        <div class="card-body">
            <div class="alert alert-warning">
                الرصيد الحالي: <strong><?=number_format((float)$selected['outstanding_balance'], 2)?></strong>.
                يجب أن يغطي القسط كامل الرصيد ضمن المدة الجديدة، وألا يتجاوز الحد الشهري المحفوظ في السياسة. لن تُحذف الخطة السابقة أو سجلات السداد.
            </div>
            <form method="post" class="row g-3">
                <?=csrf_field()?>
                <input type="hidden" name="request_id" value="<?=(int)$selected['id']?>">
                <div class="col-md-4">
                    <label class="form-label" for="start_month">شهر بدء الخطة الجديدة</label>
                    <input class="form-control" type="date" id="start_month" name="start_month" min="<?=e($defaultStart)?>" value="<?=e($defaultStart)?>" required>
                    <div class="form-text">يجب أن يكون اليوم الأول من شهر مستقبلي.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="duration_months">مدة السداد بالأشهر</label>
                    <input class="form-control" type="number" id="duration_months" name="duration_months" min="1" max="<?=(int)$selected['policy_maximum_months']?>" value="<?=(int)$selected['policy_maximum_months']?>" required>
                    <div class="form-text">الحد الأقصى وفق السياسة: <?=(int)$selected['policy_maximum_months']?> شهرًا.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="monthly_amount">القسط الشهري المقترح</label>
                    <input class="form-control" type="number" id="monthly_amount" name="monthly_amount" min="0.01" step="0.01" max="<?= $selected['policy_monthly_cap'] !== null ? e((string)$selected['policy_monthly_cap']) : e((string)$selected['outstanding_balance']) ?>" value="<?=e((string)($selected['policy_monthly_cap'] !== null ? $selected['policy_monthly_cap'] : ($selected['approved_monthly_amount'] ?: $selected['outstanding_balance'])))?>" required>
                    <?php if ($selected['policy_monthly_cap'] !== null): ?><div class="form-text">حد الخصم الشهري: <?=number_format((float)$selected['policy_monthly_cap'], 2)?></div><?php else: ?><div class="form-text">السياسة لا تحدد سقفًا رقميًا شهريًا؛ لا يمكن أن يتجاوز القسط الرصيد القائم.</div><?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="reason">سبب إعادة الجدولة</label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" maxlength="1000" required></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit" name="reschedule_submit" value="1">التحقق وحفظ الخطة الجديدة</button>
                    <a class="btn btn-outline-secondary" href="<?=e(APP_URL . 'modules/hr/salary_advance_reschedule.php')?>">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
