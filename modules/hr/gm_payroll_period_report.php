<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_gm_payroll_period.php';

Session::start();
$role = (string)Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($role, ['general_manager', 'gm'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pdo = db();
$month = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['year'] ?? date('Y'))));
$state = hrGmPayrollPeriodState($pdo, $month, $year);
if ($state['complete']) {
    hrGmPayrollNotifyPeriodComplete($pdo, $month, $year);
}
$monthNames = [1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'];
$currency = defined('APP_CURRENCY_NAME_AR') ? APP_CURRENCY_NAME_AR : 'ج.س.';
$totalNet = 0.0;
$totalIncentives = 0.0;
foreach ($state['employees'] as $employee) {
    if ($employee['completion_paid']) {
        $totalNet += (float)$employee['net_salary'];
        $totalIncentives += (float)($employee['incentive_amount'] ?? 0);
    }
}
$totalPaid = $totalNet + $totalIncentives;
$pageTitle = 'تقرير صرف رواتب الموظفين';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.gmpr-wrap{max-width:1500px;margin:0 auto;color:#212529}.gmpr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;margin-bottom:20px}.gmpr-head h1{font-size:1.55rem;margin:0 0 6px;font-weight:800}.gmpr-muted{color:#667085}.gmpr-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;margin-bottom:16px}.gmpr-stats{display:flex;gap:12px;flex-wrap:wrap}.gmpr-stat{border:1px solid #e2e8f0;border-radius:9px;padding:12px 16px;min-width:170px}.gmpr-stat span{display:block;color:#667085;font-size:.82rem}.gmpr-stat strong{display:block;font-size:1.2rem;margin-top:5px}.gmpr-table{width:100%;border-collapse:collapse;font-size:.8rem}.gmpr-table th,.gmpr-table td{border:1px solid #cbd5e1;padding:8px;text-align:right;vertical-align:top}.gmpr-table th{background:#eef4fb;white-space:nowrap}.gmpr-table tfoot td{font-weight:800;background:#f8fafc}.gmpr-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.gmpr-btn{border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#1f2937;padding:8px 12px;text-decoration:none;cursor:pointer}.gmpr-success{background:#e7f6ec;border-right:4px solid #198754;padding:12px 14px;border-radius:6px}.gmpr-wait{background:#fff8e1;border-right:4px solid #d99a00;padding:12px 14px;border-radius:6px}.gmpr-money{white-space:nowrap;font-variant-numeric:tabular-nums}
@media print{.sidebar,.topbar,.navbar,.gmpr-actions,.no-print,footer{display:none!important}body{background:#fff!important}.main-content,.content,.container,.container-fluid{margin:0!important;padding:0!important;max-width:none!important;width:100%!important}.gmpr-wrap{max-width:none}.gmpr-card{box-shadow:none;border-color:#aaa;break-inside:avoid}.gmpr-table{font-size:9pt}.gmpr-table th,.gmpr-table td{padding:5px}.gmpr-head h1{font-size:18pt}}
</style>
<div class="gmpr-wrap" dir="rtl">
    <div class="gmpr-head">
        <div>
            <h1><i class="fas fa-file-invoice-dollar ms-2"></i>تقرير صرف رواتب الموظفين</h1>
            <div class="gmpr-muted">الفترة: <?= e($monthNames[$month] . ' ' . $year) ?> — تقرير للعرض والطباعة فقط</div>
        </div>
        <div class="gmpr-actions no-print">
            <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
                <div><label for="month" class="form-label">الشهر</label><select class="form-select" id="month" name="month"><?php foreach ($monthNames as $m => $label): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div><label for="year" class="form-label">السنة</label><input class="form-control" id="year" name="year" type="number" min="2020" max="2100" value="<?= $year ?>"></div>
                <button class="gmpr-btn" type="submit">عرض الفترة</button>
            </form>
            <?php if ($state['complete']): ?><button class="gmpr-btn" type="button" onclick="window.print()">طباعة التقرير</button><?php endif; ?>
        </div>
    </div>

    <?php if ($state['complete']): ?>
        <div class="gmpr-success mb-3">اكتمل صرف رواتب جميع الموظفين المؤهلين للفترة، وتم ترحيل القيود المحاسبية. التقرير للعرض والطباعة ولا يتضمن أي إجراء اعتماد أو تعديل.</div>
        <div class="gmpr-card">
            <div class="gmpr-stats">
                <div class="gmpr-stat"><span>عدد الموظفين</span><strong><?= (int)$state['eligible_count'] ?></strong></div>
                <div class="gmpr-stat"><span>إجمالي صافي الرواتب</span><strong><?= number_format($totalNet, 2) ?> <?= e($currency) ?></strong></div>
                <div class="gmpr-stat"><span>إجمالي الحوافز</span><strong><?= number_format($totalIncentives, 2) ?> <?= e($currency) ?></strong></div>
                <div class="gmpr-stat"><span>إجمالي المبالغ المصروفة</span><strong><?= number_format($totalPaid, 2) ?> <?= e($currency) ?></strong></div>
            </div>
        </div>
        <div class="gmpr-card">
            <table class="gmpr-table">
                <thead><tr>
                    <th>#</th><th>الموظف</th><th>الراتب الأساسي</th><th>البدلات</th><th>الإضافي</th><th>الخصومات العامة</th><th>خصم الحضور</th><th>خصم السلفة</th><th>صافي الراتب</th><th>الحوافز</th><th>إجمالي المصروف</th><th>تاريخ الصرف</th><th>قيد اليومية</th>
                </tr></thead>
                <tbody>
                <?php foreach ($state['employees'] as $i => $employee): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e((string)$employee['full_name']) ?></strong><br><small><?= e((string)$employee['employee_code']) ?></small></td>
                        <td class="gmpr-money"><?= number_format((float)$employee['basic_salary'], 2) ?></td>
                        <td class="gmpr-money"><?= number_format((float)$employee['allowances'], 2) ?></td>
                        <td class="gmpr-money"><?= number_format((float)$employee['overtime'], 2) ?></td>
                        <td class="gmpr-money"><?= number_format((float)$employee['deductions'], 2) ?></td>
                        <td class="gmpr-money"><?= number_format((float)$employee['attendance_deduction'], 2) ?></td>
                        <td class="gmpr-money"><?= number_format((float)$employee['salary_advance_deduction'], 2) ?></td>
                        <td class="gmpr-money"><?= number_format((float)$employee['net_salary'], 2) ?></td>
                        <td class="gmpr-money"><?= number_format((float)$employee['incentive_amount'], 2) ?></td>
                        <td class="gmpr-money"><strong><?= number_format((float)$employee['net_salary'] + (float)$employee['incentive_amount'], 2) ?></strong></td>
                        <td><?= e((string)$employee['payment_date']) ?></td>
                        <td><?= (int)$employee['accounting_entry_id'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="8">الإجمالي</td><td class="gmpr-money"><?= number_format($totalNet, 2) ?></td><td class="gmpr-money"><?= number_format($totalIncentives, 2) ?></td><td class="gmpr-money"><?= number_format($totalPaid, 2) ?></td><td colspan="2"></td></tr></tfoot>
            </table>
            <p class="gmpr-muted mt-3 mb-0">إجمالي المصروف لكل موظف = صافي الراتب المسجل + الحوافز المجدولة، وفق كشف الراتب الحالي.</p>
        </div>
    <?php elseif ($state['eligible_count'] === 0): ?>
        <div class="gmpr-wait">لا يوجد موظفون مؤهلون لصرف الرواتب وفق سجل الراتب التاريخي والحالة الوظيفية في نهاية هذه الفترة؛ لذلك لم يُنشأ إشعار اكتمال.</div>
    <?php else: ?>
        <div class="gmpr-wait mb-3">لم يكتمل صرف الفترة بعد. لن يُرسل إشعار الاكتمال إلى المدير العام إلا بعد أن تكون سجلات جميع الموظفين المؤهلين بحالة «مصروف» والقيود المحاسبية «مرحّلة».</div>
        <div class="gmpr-card">
            <p>اكتمل الصرف والترحيل لـ <?= (int)$state['paid_count'] ?> من أصل <?= (int)$state['eligible_count'] ?> موظفاً مؤهلاً.</p>
            <ul class="mb-0"><?php foreach ($state['employees'] as $employee): if (!$employee['completion_paid']): ?><li><?= e((string)$employee['full_name']) ?> (<?= e((string)$employee['employee_code']) ?>) — لم يكتمل الصرف والترحيل</li><?php endif; endforeach; ?></ul>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
