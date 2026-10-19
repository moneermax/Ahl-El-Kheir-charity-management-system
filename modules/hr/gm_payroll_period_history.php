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
$monthNames = [1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'];
$periodRows = dbFetchAll(
    "SELECT DISTINCT year, month
     FROM payroll
     WHERE status = 'paid'
     ORDER BY year DESC, month DESC"
);
$history = [];
foreach ($periodRows as $periodRow) {
    $month = (int)$periodRow['month'];
    $year = (int)$periodRow['year'];
    $state = hrGmPayrollPeriodState($pdo, $month, $year);
    $net = 0.0;
    $incentives = 0.0;
    foreach ($state['employees'] as $employee) {
        if (!$employee['completion_paid']) {
            continue;
        }
        $net += (float)$employee['net_salary'];
        $incentives += (float)($employee['incentive_amount'] ?? 0);
    }
    $history[] = [
        'month' => $month,
        'year' => $year,
        'state' => $state,
        'net' => $net,
        'incentives' => $incentives,
        'total' => $net + $incentives,
    ];
}
$pageTitle = 'سجل صرف الرواتب';
$active = 'gm_payroll_period_history';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.gmph-wrap{max-width:1250px;margin:0 auto}.gmph-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px}.gmph-head h1{font-size:1.5rem;font-weight:800;margin:0 0 6px}.gmph-muted{color:#667085}.gmph-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:16px}.gmph-table{width:100%;border-collapse:collapse;font-size:.9rem}.gmph-table th,.gmph-table td{border-bottom:1px solid #e2e8f0;padding:11px 9px;text-align:right;vertical-align:middle}.gmph-table th{background:#f4f7fb;color:#173f73;white-space:nowrap}.gmph-status{display:inline-block;padding:4px 9px;border-radius:999px;font-size:.78rem;font-weight:700;white-space:nowrap}.gmph-complete{background:#dff4e6;color:#146c43}.gmph-incomplete{background:#fff1cf;color:#875d00}.gmph-link{display:inline-block;padding:7px 11px;border:1px solid #cbd5e1;border-radius:7px;text-decoration:none;color:#173f73;white-space:nowrap}.gmph-link:hover{background:#f4f7fb;color:#173f73}.gmph-money{white-space:nowrap;font-variant-numeric:tabular-nums}
@media(max-width:767px){.gmph-table{font-size:.8rem}.gmph-table th,.gmph-table td{padding:8px 6px}}
@media print{.sidebar,.topbar,.navbar,footer,.gmph-actions{display:none!important}.main-content,.content,.container,.container-fluid{margin:0!important;padding:0!important;max-width:none!important;width:100%!important}.gmph-card{border:0;padding:0}.gmph-link{display:none}}
</style>
<div class="gmph-wrap" dir="rtl">
    <div class="gmph-head">
        <div>
            <h1><i class="fas fa-clock-rotate-left ms-2"></i>سجل صرف الرواتب</h1>
            <div class="gmph-muted">الفترات التي تحتوي على سجلات رواتب مصروفة. اختر أي فترة لفتح كشف الموظفين وتفاصيل الصرف والقيود المحاسبية.</div>
        </div>
    </div>
    <div class="gmph-card">
        <?php if (!$history): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-file-invoice-dollar fa-2x mb-3 d-block"></i>
                لا توجد فترات صرف مسجلة حتى الآن.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="gmph-table">
                    <thead><tr>
                        <th>الفترة</th><th>حالة الفترة</th><th>الموظفون المصروف لهم والمرحلون</th><th>إجمالي المؤهلين</th>
                        <th>صافي الرواتب المرحّل</th><th>الحوافز المرحلة</th><th>إجمالي المصروف المرحّل</th><th>التقرير</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($history as $item): $state = $item['state']; ?>
                        <tr>
                            <td><strong><?= e($monthNames[$item['month']] . ' ' . $item['year']) ?></strong></td>
                            <td><?php if ($state['complete']): ?><span class="gmph-status gmph-complete">مكتمل</span><?php else: ?><span class="gmph-status gmph-incomplete">غير مكتمل</span><?php endif; ?></td>
                            <td><?= (int)$state['paid_count'] ?></td><td><?= (int)$state['eligible_count'] ?></td>
                            <td class="gmph-money"><?= number_format($item['net'], 2) ?></td><td class="gmph-money"><?= number_format($item['incentives'], 2) ?></td>
                            <td class="gmph-money"><strong><?= number_format($item['total'], 2) ?></strong></td>
                            <td><a class="gmph-link" href="<?= e(APP_URL . 'modules/hr/gm_payroll_period_report.php?month=' . $item['month'] . '&year=' . $item['year']) ?>">عرض الكشف <i class="fas fa-arrow-up-right-from-square me-1"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="gmph-muted small mt-3 mb-0">المبالغ المعروضة هنا تشمل الموظفين المؤهلين الذين سُجل صرف رواتبهم ورُحّلت قيودهم المحاسبية فقط. عند عدم اكتمال الفترة، افتح التقرير لمعرفة الموظفين الذين لم يكتمل صرفهم وترحيلهم.</p>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
