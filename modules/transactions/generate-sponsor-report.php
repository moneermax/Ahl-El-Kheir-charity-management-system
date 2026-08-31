<?php
// modules/transactions/sponsor-report-preview.php - HTML Preview for Sponsor Report
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
Session::start();

// Access guard
$allowedRoles = ['nanny', 'financial_manager', 'admin', 'general_manager', 'vice_general_manager'];
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), $allowedRoles, true)) {
    die('Unauthorized access');
}

$sponsorId = isset($_POST['sponsor_id']) ? (int)$_POST['sponsor_id'] : 0;
$reportMonth = isset($_POST['report_month']) ? $_POST['report_month'] : '';

if ($sponsorId <= 0 || empty($reportMonth)) {
    die('Invalid parameters');
}

// Get sponsor info
$sponsor = dbFetchOne("SELECT id, full_name, sponsor_code, phone, email FROM sponsors WHERE id = ?", [$sponsorId]);
if (!$sponsor) {
    die('Sponsor not found');
}

// Get ACTUAL paid transactions for this sponsor in the selected month
$transactions = dbFetchAll("
    SELECT 
        t.id,
        t.amount,
        t.transaction_date,
        t.payment_method,
        t.transaction_code,
        t.child_id,
        fc.family_id,
        f.family_code
    FROM transactions t
    INNER JOIN sponsorships sp ON sp.sponsor_id = t.sponsor_id AND sp.child_id = t.child_id
    INNER JOIN family_children fc ON fc.id = sp.child_id AND fc.is_active = 1
    INNER JOIN families f ON f.id = fc.family_id
    WHERE t.sponsor_id = ?
    AND t.status = 'posted'
    AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ?
    ORDER BY fc.family_id, t.transaction_date
", [$sponsorId, $reportMonth]);

// Group by child
$byChild = [];
$totalPaid = 0;
foreach ($transactions as $t) {
    $key = $t['child_id'];
    if (!isset($byChild[$key])) {
        $byChild[$key] = [
            'child_id' => $t['child_id'],
            'family_code' => $t['family_code'],
            'transactions' => [],
            'total_paid' => 0
        ];
    }
    $byChild[$key]['transactions'][] = $t;
    $byChild[$key]['total_paid'] += (float)$t['amount'];
    $totalPaid += (float)$t['amount'];
}

$pageTitle = 'معاينة تقرير الكفيل';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h2><i class="fas fa-file-invoice me-2"></i>معاينة تقرير الكفيل الشهري</h2>
        <div>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i> طباعة / حفظ كـ PDF</button>
            <a href="<?php echo APP_URL; ?>modules/transactions/sponsor-monthly-report.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> رجوع</a>
        </div>
    </div>

    <div class="card" id="report-content">
        <div class="card-body">
            <!-- Header with Logo -->
            <div class="text-center mb-4 border-bottom pb-3">
                <img src="<?php echo APP_URL; ?>assets/img/logo.png" alt="شعار أهل الخير" style="max-width: 100px; height: auto;">
                <h3 class="mt-2" style="color: #1b4d8f;">منظمة أهل الخير الخيرية</h3>
                <h4>تقرير الكفيل الشهري</h4>
            </div>

            <!-- Sponsor Info -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <p><strong>اسم الكفيل:</strong> <?php echo e($sponsor['full_name']); ?></p>
                    <p><strong>رقم الكفيل:</strong> <?php echo e($sponsor['sponsor_code']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>الشهر:</strong> <?php echo date('F Y', strtotime($reportMonth . '-01')); ?></p>
                    <p><strong>تاريخ الطباعة:</strong> <?php echo date('Y-m-d H:i'); ?></p>
                </div>
            </div>

            <!-- Summary -->
            <div class="alert alert-info">
                <h5>ملخص الدفعات الفعلية للشهر</h5>
                <p class="mb-0"><strong>إجمالي المبلغ المدفوع فعلياً:</strong> <span class="fs-4 text-primary"><?php echo number_format($totalPaid, 2); ?> ج.س</span></p>
                <small class="text-muted">يعرض هذا التقرير المبالغ التي تم تحصيلها فعلياً من الكفيل خلال الشهر المحدد.</small>
            </div>

            <!-- Detailed Table -->
            <?php if (empty($byChild)): ?>
                <div class="alert alert-warning">لا توجد مدفوعات مسجلة لهذا الكفيل في الشهر المحدد.</div>
            <?php else: ?>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>رقم الأسرة</th>
                        <th>رقم اليتيم</th>
                        <th>تاريخ الدفعة</th>
                        <th>طريقة الدفع</th>
                        <th>رقم المعاملة</th>
                        <th class="text-end">المبلغ المدفوع (ج.س)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byChild as $child): ?>
                        <?php $first = true; foreach ($child['transactions'] as $t): ?>
                        <tr>
                            <?php if ($first): ?>
                                <td rowspan="<?php echo count($child['transactions']); ?>"><?php echo e($child['family_code']); ?></td>
                                <td rowspan="<?php echo count($child['transactions']); ?>"><strong><?php echo $child['child_id']; ?></strong></td>
                            <?php endif; ?>
                            <td><?php echo date('Y-m-d', strtotime($t['transaction_date'])); ?></td>
                            <td><?php echo e($t['payment_method']); ?></td>
                            <td><?php echo e($t['transaction_code'] ?? '-'); ?></td>
                            <td class="text-end"><?php echo number_format($t['amount'], 2); ?></td>
                        </tr>
                        <?php $first = false; endforeach; ?>
                        <tr class="table-secondary">
                            <td colspan="5" class="text-end"><strong>المجموع الفرعي لهذا اليتيم:</strong></td>
                            <td class="text-end"><strong><?php echo number_format($child['total_paid'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-primary">
                    <tr>
                        <th colspan="5" class="text-end fs-5">الإجمالي الكلي المدفوع:</th>
                        <th class="text-end fs-5"><?php echo number_format($totalPaid, 2); ?> ج.س</th>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>

            <!-- Signatures -->
            <div class="row mt-5 pt-4 border-top">
                <div class="col-md-6 text-center">
                    <p class="mb-4"><strong>توقيع المدير المالي (FM)</strong></p>
                    <p>_________________________</p>
                    <p class="text-muted">التاريخ: ____/____/________</p>
                </div>
                <div class="col-md-6 text-center">
                    <p class="mb-4"><strong>توقيع المدير العام (GM)</strong></p>
                    <p>_________________________</p>
                    <p class="text-muted">التاريخ: ____/____/________</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    body { background: white !important; }
    .container { max-width: 100% !important; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>