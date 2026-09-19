<?php
// modules/reports/my_financial.php - Accountant Staff work report
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/report_registry.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
ak_report_require_access('my_financial');

$uid = (int)Session::getUserId();
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

$transactions = dbFetchAll(
    "SELECT t.*, s.full_name AS sponsor, f.mother_name AS family
     FROM transactions t
     LEFT JOIN sponsors s ON s.id = t.sponsor_id
     LEFT JOIN families f ON f.id = t.family_id
     WHERE t.created_by = ?
       AND t.transaction_date BETWEEN ? AND ?
     ORDER BY t.transaction_date DESC, t.id DESC",
    [$uid, $from, $to]
);

$summary = [
    'count' => count($transactions),
    'posted' => 0,
    'returned' => 0,
    'pending' => 0,
    'voided' => 0,
    'other' => 0,
    'amount' => 0.0,
];

foreach ($transactions as $transaction) {
    $status = (string)($transaction['status'] ?? '');
    $amount = (float)($transaction['amount'] ?? 0);
    $summary['amount'] += $amount;
    if (array_key_exists($status, $summary)) {
        $summary[$status]++;
    } else {
        $summary['other']++;
    }
}

$pageTitle = 'تقاريري المالية';
$active = 'reports';
include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-file-invoice-dollar me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">يعرض هذا التقرير المعاملات المالية التي أنشأتها أنت فقط.</p>
</div>

<div class="card shadow-sm mb-4 fade-in">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label fw-bold">من</label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>" required></div>
            <div class="col-md-3"><label class="form-label fw-bold">إلى</label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>" required></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>تطبيق</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4 fade-in">
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center"><div class="display-6 fw-bold text-primary"><?php echo number_format($summary['count']); ?></div><div class="text-muted">عدد المعاملات</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center"><div class="display-6 fw-bold text-success"><?php echo number_format($summary['posted']); ?></div><div class="text-muted">مرحّلة</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center"><div class="display-6 fw-bold text-warning"><?php echo number_format($summary['returned']); ?></div><div class="text-muted">معادة</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body text-center"><div class="display-6 fw-bold" style="color:#1b4d8f;"><?php echo number_format($summary['amount'], 2); ?></div><div class="text-muted">إجمالي قيمة المعاملات</div></div></div></div>
</div>

<div class="card shadow-sm fade-in">
    <div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-list me-2"></i>معاملاتي خلال الفترة</div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="alert alert-info mb-0">لا توجد معاملات أنشأتها خلال الفترة المحددة.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light"><tr><th>التاريخ</th><th>رقم المعاملة</th><th>النوع</th><th>المبلغ</th><th>الحالة</th><th>الطرف</th></tr></thead>
                    <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <?php
                        $status = (string)($transaction['status'] ?? '');
                        $badge = 'bg-secondary';
                        if ($status === 'posted') $badge = 'bg-success';
                        elseif ($status === 'returned') $badge = 'bg-warning text-dark';
                        elseif ($status === 'pending_approval') $badge = 'bg-info text-dark';
                        elseif ($status === 'voided') $badge = 'bg-danger';
                        elseif ($status === 'cancelled') $badge = 'bg-dark';
                        $party = $transaction['sponsor'] ?: ($transaction['family'] ?: '—');
                        ?>
                        <tr>
                            <td><?php echo e($transaction['transaction_date']); ?></td>
                            <td><code><?php echo e($transaction['transaction_code'] ?: ('#' . $transaction['id'])); ?></code></td>
                            <td><?php echo e($transaction['transaction_type'] ?? '—'); ?></td>
                            <td class="fw-bold"><?php echo number_format((float)$transaction['amount'], 2); ?></td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo e($status ?: '—'); ?></span></td>
                            <td><?php echo e($party); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>


<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/reports/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
