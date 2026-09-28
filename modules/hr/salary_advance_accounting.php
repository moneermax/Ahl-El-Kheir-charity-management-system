<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_salary_advance_accounting.php';

Session::start();

if (!Session::isLoggedIn() || !hrSalaryAdvanceAccountingCan((string)Session::getUserRole())) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$role = (string)Session::getUserRole();
$uid = (int)Session::getUserId();
$pdo = db();
$requestId = (int)($_GET['id'] ?? $_POST['request_id'] ?? 0);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        }

        if (isset($_POST['accounting_verify']) || isset($_POST['accounting_reject'])) {
            $decision = isset($_POST['accounting_verify']) ? 'verify' : 'reject';
            hrSalaryAdvanceAccountingVerify(
                $pdo,
                $requestId,
                $uid,
                $decision,
                trim((string)($_POST['accounting_rejection_reason'] ?? ''))
            );
            $message = $decision === 'verify'
                ? 'تم اعتماد التحقق المحاسبي. أصبحت السلفة جاهزة للصرف.'
                : 'تم رفض التحقق المحاسبي. لن يمكن صرف السلفة حتى تتم مراجعتها واعتمادها محاسبياً.';
        } elseif (isset($_POST['disburse_salary_advance'])) {
            $cashAccountId = (int)($_POST['disbursement_account_id'] ?? 0);
            $reference = trim((string)($_POST['disbursement_reference'] ?? ''));
            $entryId = hrSalaryAdvanceAccountingDisburse($pdo, $requestId, $uid, $cashAccountId, $reference);
            $message = 'تم صرف السلفة وترحيل القيد المحاسبي رقم ' . $entryId . ' بنجاح.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$request = $requestId > 0 ? hrSalaryAdvanceAccountingGetRequest($pdo, $requestId) : null;
$queue = hrSalaryAdvanceAccountingQueue($pdo);

$cashAccounts = dbFetchAll(
    "SELECT id, code, name_ar
     FROM accounts
     WHERE code IN ('1100','1200','1300') AND is_active = 1
     ORDER BY code"
);

$balances = [];
foreach ($cashAccounts as $cash) {
    $balances[(int)$cash['id']] = ak_voucher_cash_balance((int)$cash['id']);
}

$pageTitle = 'التحقق المحاسبي وصرف سلف الرواتب';
$active = 'fm_dashboard';

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-2">
    <div class="d-flex justify-content-start mb-3">
        <a href="<?php echo e(APP_URL . 'modules/accounting/fm_dashboard.php'); ?>"
           class="btn btn-outline-secondary"
           onclick="return akGoBack(this.href);">
            <i class="fas fa-arrow-right me-1"></i> العودة
        </a>
    </div>

    <div class="welcome-section fade-in">
        <h2><i class="fas fa-hand-holding-dollar me-2"></i><?php echo e($pageTitle); ?></h2>
        <p class="mb-0">
            اعتماد FM يسبق التحقق المحاسبي. الصرف الفعلي ليس مصروفاً؛ بل ينشئ ذمة على الموظف
            مقابل خفض حساب الصندوق/البنك/المحفظة.
        </p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success fade-in"><?php echo e($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger fade-in"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="card fade-in mb-4">
        <div class="card-header fw-bold">طلبات السلف المعتمدة بانتظار المعالجة المحاسبية</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>الطلب</th>
                            <th>الموظف</th>
                            <th>المبلغ المعتمد</th>
                            <th>حالة التحقق</th>
                            <th>يتطلب تحققاً محاسبياً</th>
                            <th>الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$queue): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">لا توجد سلف معتمدة بانتظار المعالجة.</td></tr>
                    <?php else: foreach ($queue as $q): ?>
                        <tr>
                            <td><strong><?php echo e($q['request_no']); ?></strong></td>
                            <td><?php echo e($q['employee_name']); ?><div class="small text-muted"><?php echo e($q['employee_code']); ?></div></td>
                            <td><?php echo number_format((float)$q['approved_amount'], 2); ?> SDG</td>
                            <td>
                                <?php
                                $badge = [
                                    'pending' => ['بانتظار التحقق', 'warning'],
                                    'verified' => ['تم التحقق', 'success'],
                                    'rejected' => ['مرفوض محاسبياً', 'danger']
                                ][$q['accounting_status']] ?? [$q['accounting_status'], 'secondary'];
                                ?>
                                <span class="badge bg-<?php echo e($badge[1]); ?>"><?php echo e($badge[0]); ?></span>
                            </td>
                            <td><?php echo (int)$q['require_accounting_verification'] === 1 ? 'نعم' : 'لا'; ?></td>
                            <td>
                                <a class="btn btn-sm btn-primary"
                                   href="<?php echo e(APP_URL . 'modules/hr/salary_advance_accounting.php?id=' . (int)$q['id']); ?>">
                                    <i class="fas fa-eye me-1"></i>مراجعة
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($request): ?>
    <div class="card fade-in mb-4">
        <div class="card-header fw-bold">تفاصيل الطلب <?php echo e($request['request_no']); ?></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><strong>الموظف:</strong><br><?php echo e($request['employee_name']); ?></div>
                <div class="col-md-3"><strong>الكود:</strong><br><?php echo e($request['employee_code']); ?></div>
                <div class="col-md-3"><strong>المبلغ المعتمد:</strong><br><?php echo number_format((float)$request['approved_amount'], 2); ?> SDG</div>
                <div class="col-md-3"><strong>حالة الطلب:</strong><br><span class="badge bg-success"><?php echo e($request['status']); ?></span></div>
                <div class="col-md-4"><strong>طريقة السداد:</strong><br><?php echo e([
                    'fixed_monthly'=>'قسط شهري ثابت',
                    'full_eligible_salary'=>'كامل الراتب المؤهل',
                    'full_settlement'=>'تسوية كاملة',
                    'direct_repayment'=>'سداد مباشر'
                ][$request['approved_repayment_method']] ?? $request['approved_repayment_method']); ?></div>
                <div class="col-md-4"><strong>القسط الشهري:</strong><br><?php echo $request['approved_monthly_amount'] !== null ? number_format((float)$request['approved_monthly_amount'], 2) . ' SDG' : '—'; ?></div>
                <div class="col-md-4"><strong>بدء السداد:</strong><br><?php echo e($request['approved_start_month'] ?? '—'); ?></div>
            </div>
        </div>
    </div>

    <div class="card fade-in mb-4">
        <div class="card-header fw-bold">التحقق المحاسبي</div>
        <div class="card-body">
            <div class="alert alert-info">
                الحساب المستهدف للسلفة: <strong>1410 — ذمم سلف الموظفين</strong>.
                لا يتم استخدام حساب مصروف، ولا يتم إصدار سند صرف عادي للسلفة.
            </div>

            <dl class="row mb-0">
                <dt class="col-sm-4">حالة التحقق</dt>
                <dd class="col-sm-8">
                    <?php echo e($request['accounting_status']); ?>
                    <?php if (!empty($request['accounting_verified_at'])): ?>
                        — <?php echo e($request['accounting_verified_at']); ?>
                        <?php if (!empty($request['accounting_verified_by_name'])): ?>
                            — <?php echo e($request['accounting_verified_by_name']); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </dd>
                <?php if (!empty($request['accounting_rejection_reason'])): ?>
                    <dt class="col-sm-4">سبب الرفض المحاسبي</dt>
                    <dd class="col-sm-8 text-danger"><?php echo e($request['accounting_rejection_reason']); ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ($request['status'] === 'approved' && $request['accounting_status'] !== 'verified'): ?>
            <form method="post" class="mt-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                <div class="mb-3">
                    <label class="form-label">سبب الرفض المحاسبي <span class="text-muted">(مطلوب عند الرفض)</span></label>
                    <textarea name="accounting_rejection_reason" class="form-control" rows="2" maxlength="2000"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="accounting_verify" value="1" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>اعتماد التحقق المحاسبي
                    </button>
                    <button type="submit" name="accounting_reject" value="1" class="btn btn-danger">
                        <i class="fas fa-xmark me-1"></i>رفض التحقق
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($request['status'] === 'approved'): ?>
    <div class="card fade-in mb-4">
        <div class="card-header fw-bold">الصرف الفعلي</div>
        <div class="card-body">
            <?php if ((int)$request['require_accounting_verification'] === 1 && $request['accounting_status'] !== 'verified'): ?>
                <div class="alert alert-warning mb-0">لا يمكن الصرف قبل اعتماد التحقق المحاسبي.</div>
            <?php else: ?>
                <div class="alert alert-warning">
                    عند الصرف سيتم ترحيل قيد مزدوج:
                    <strong>مدين 1410 — ذمم سلف الموظفين</strong> /
                    <strong>دائن حساب النقد المختار</strong>.
                </div>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">حساب الصرف *</label>
                            <select name="disbursement_account_id" class="form-select" required>
                                <option value="">— اختر الصندوق/البنك/المحفظة —</option>
                                <?php foreach ($cashAccounts as $cash): ?>
                                    <option value="<?php echo (int)$cash['id']; ?>">
                                        <?php echo e($cash['code'] . ' — ' . $cash['name_ar']); ?>
                                        — الرصيد <?php echo number_format((float)$balances[(int)$cash['id']], 2); ?> SDG
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">مرجع الصرف <span class="text-muted">(اختياري)</span></label>
                            <input type="text" name="disbursement_reference" class="form-control" maxlength="100"
                                   value="<?php echo e((string)($request['disbursement_reference'] ?? '')); ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="disburse_salary_advance" value="1" class="btn btn-primary">
                            <i class="fas fa-money-bill-transfer me-1"></i>
                            صرف وترحيل <?php echo number_format((float)$request['approved_amount'], 2); ?> SDG
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($request['status'] === 'disbursed'): ?>
    <div class="card fade-in mb-4">
        <div class="card-header fw-bold">بيانات الصرف</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><strong>تاريخ الصرف:</strong><br><?php echo e($request['disbursed_at']); ?></div>
                <div class="col-md-3"><strong>صرف بواسطة:</strong><br><?php echo e($request['disbursed_by_name'] ?? '—'); ?></div>
                <div class="col-md-3"><strong>حساب الصرف:</strong><br><?php echo e(($request['disbursement_account_code'] ?? '') . ' — ' . ($request['disbursement_account_name'] ?? '')); ?></div>
                <div class="col-md-3"><strong>القيد:</strong><br><?php echo e($request['disbursement_entry_code'] ?? '—'); ?></div>
                <div class="col-md-6"><strong>المرجع:</strong><br><?php echo e($request['disbursement_reference'] ?? '—'); ?></div>
                <div class="col-md-6"><strong>الرصيد القائم:</strong><br><?php echo number_format((float)$request['outstanding_balance'], 2); ?> SDG</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <div class="d-flex justify-content-start mt-4 mb-4">
        <a href="<?php echo e(APP_URL . 'modules/accounting/fm_dashboard.php'); ?>"
           class="btn btn-outline-secondary"
           onclick="return akGoBack(this.href);">
            <i class="fas fa-arrow-right me-1"></i> العودة
        </a>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
