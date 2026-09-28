<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_salary_advance_accounting.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}

$role = (string)Session::getUserRole();
if (!in_array($role, ['financial_manager', 'admin', 'fm'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$pdo = db();

$history = hrSalaryAdvanceAccountingHistory($pdo);

$counts = dbFetchOne(
    "SELECT
        SUM(CASE WHEN status IN ('submitted','fm_review') THEN 1 ELSE 0 END) AS fm_pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS accounting_pending,
        SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) AS disbursed,
        SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) AS settled
     FROM hr_salary_advance_requests"
) ?: [];

$pageTitle = 'إدارة سلف الرواتب';
$active = 'salary_advance_portal';

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.salary-advance-portal{max-width:1450px;margin:0 auto}
.salary-advance-portal .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:24px 26px;margin-bottom:18px}
.salary-advance-portal .hero h1{font-size:1.45rem;font-weight:800;margin:0}
.salary-advance-portal .hero p{margin:7px 0 0;opacity:.92;font-size:.86rem;line-height:1.55}
.salary-advance-portal .option-card{height:100%;background:#fff;border:1px solid #e5eaf0;border-radius:14px;box-shadow:0 2px 12px rgba(16,24,40,.06);transition:transform .15s ease,box-shadow .15s ease;border-top:4px solid #1b4d8f}
.salary-advance-portal .option-card:hover{transform:translateY(-2px);box-shadow:0 5px 18px rgba(16,24,40,.1)}
.salary-advance-portal .option-body{padding:22px}
.salary-advance-portal .option-icon{width:52px;height:52px;border-radius:12px;background:#eaf2fb;color:#1b4d8f;display:flex;align-items:center;justify-content:center;font-size:1.35rem;margin-bottom:14px}
.salary-advance-portal .option-title{font-size:1.05rem;font-weight:800;color:#173f73;margin-bottom:7px}
.salary-advance-portal .option-desc{color:#687385;font-size:.84rem;line-height:1.65;min-height:55px}
.salary-advance-portal .stat-card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;padding:15px;text-align:center;height:100%}
.salary-advance-portal .stat-value{font-size:1.45rem;font-weight:800;color:#173f73}
.salary-advance-portal .stat-label{font-size:.78rem;color:#687385;margin-top:4px}
</style>

<div class="salary-advance-portal">
    <section class="hero">
        <h1><i class="fas fa-hand-holding-dollar me-2"></i><?php echo e($pageTitle); ?></h1>
        <p>بوابة موحدة لإدارة دورة سلف الرواتب من السياسة والمراجعة والاعتماد حتى التحقق المحاسبي والصرف.</p>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-value"><?php echo (int)($counts['fm_pending'] ?? 0); ?></div>
                <div class="stat-label">طلبات بانتظار مراجعة FM</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-value"><?php echo (int)($counts['accounting_pending'] ?? 0); ?></div>
                <div class="stat-label">طلبات بانتظار المعالجة المحاسبية</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-value"><?php echo (int)($counts['disbursed'] ?? 0); ?></div>
                <div class="stat-label">سلف مصروفة</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-value"><?php echo (int)($counts['settled'] ?? 0); ?></div>
                <div class="stat-label">سلف مسددة</div>
            </div>
        </div>
    </div>

    
    <div class="row g-4">
        <div class="col-md-6 col-xl-4">
            <a href="<?php echo e(APP_URL . 'modules/hr/salary_advance_policy.php'); ?>" class="text-decoration-none text-reset">
                <div class="option-card">
                    <div class="option-body">
                        <div class="option-icon"><i class="fas fa-file-signature"></i></div>
                        <div class="option-title">سياسة السلف</div>
                        <div class="option-desc">إنشاء وإدارة إصدارات سياسة السلف، حدود المبالغ، طرق السداد، الأهلية، والتحقق المحاسبي.</div>
                        <span class="btn btn-outline-primary btn-sm mt-2">فتح السياسة <i class="fas fa-arrow-left ms-1"></i></span>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-4">
            <a href="<?php echo e(APP_URL . 'modules/hr/salary_advance_fm_review.php'); ?>" class="text-decoration-none text-reset">
                <div class="option-card">
                    <div class="option-body">
                        <div class="option-icon"><i class="fas fa-user-check"></i></div>
                        <div class="option-title">مراجعة طلبات الموظفين</div>
                        <div class="option-desc">مراجعة طلبات السلف المقدمة من الموظفين، التحقق من توافقها مع السياسة، وتخصيص شروط السداد عند الحاجة.</div>
                        <span class="btn btn-outline-primary btn-sm mt-2">فتح المراجعة <i class="fas fa-arrow-left ms-1"></i></span>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-4">
            <a href="<?php echo e(APP_URL . 'modules/hr/salary_advance_accounting.php'); ?>" class="text-decoration-none text-reset">
                <div class="option-card" style="border-top-color:#198754">
                    <div class="option-body">
                        <div class="option-icon" style="background:#eaf7ef;color:#198754"><i class="fas fa-calculator"></i></div>
                        <div class="option-title">التحقق المحاسبي والصرف</div>
                        <div class="option-desc">التحقق المحاسبي للسلف المعتمدة، اختيار حساب الصرف، وترحيل قيد السلفة على حساب ذمم سلف الموظفين.</div>
                        <span class="btn btn-outline-success btn-sm mt-2">فتح المعالجة المحاسبية <i class="fas fa-arrow-left ms-1"></i></span>
                    </div>
                </div>
            </a>
        </div>
    </div>
<div class="card fade-in mb-4">
        <div class="card-header fw-bold">سجل السلف المعالجة والطلبات السابقة</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>الطلب</th>
                            <th>الموظف</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                            <th>تاريخ المعالجة</th>
                            <th>الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">لا توجد طلبات معالجة سابقة.</td></tr>
                    <?php else: foreach ($history as $h): ?>
                        <?php
                        $statusMeta = [
                            'disbursed' => ['تم الصرف', 'success'],
                            'settled' => ['تمت التسوية', 'primary'],
                            'rejected' => ['مرفوض', 'danger'],
                            'cancelled' => ['ملغى', 'secondary'],
                        ][$h['status']] ?? [$h['status'], 'secondary'];
                        $processedAt = $h['disbursed_at'] ?: ($h['settled_at'] ?? null);
                        ?>
                        <tr>
                            <td><strong><?php echo e($h['request_no']); ?></strong></td>
                            <td><?php echo e($h['employee_name']); ?><div class="small text-muted"><?php echo e($h['employee_code']); ?></div></td>
                            <td><?php echo number_format((float)$h['approved_amount'], 2); ?> SDG</td>
                            <td><span class="badge bg-<?php echo e($statusMeta[1]); ?>"><?php echo e($statusMeta[0]); ?></span></td>
                            <td><?php echo e($processedAt ?: '—'); ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo e(APP_URL . 'modules/hr/salary_advance_accounting.php?id=' . (int)$h['id']); ?>">عرض</a>
                                <?php if ($h['status'] === 'disbursed' || $h['status'] === 'settled'): ?>
                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo e(APP_URL . 'modules/hr/salary_advance_voucher_print.php?id=' . (int)$h['id']); ?>">السند</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
