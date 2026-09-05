<?php
// modules/reports/financial.php - Financial Reports
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'financial_manager', 'accountant_staff'];
if (!in_array($role, $allowed_roles, true)) {
    $_SESSION['flash'][] = ['type' => 'error', 'message' => t('reports.permission_denied')];
    header('Location: ' . APP_URL . 'modules/reports/index.php');
    exit();
}

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));
$report_type = trim($_GET['report'] ?? 'income_expense');
$allowed_reports = ['income_expense', 'cashflow', 'treasury', 'disbursement', 'returns_voids'];
if (!in_array($report_type, $allowed_reports, true)) $report_type = 'income_expense';

$pageTitle = t('reports.financial_title');

$income_expense_data = [];
if ($report_type === 'income_expense') {
    $income_expense_data['income'] = dbFetchAll("SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        SUM(CASE WHEN purpose = 'monthly_sponsorship' THEN amount ELSE 0 END) as sponsorship_income,
        SUM(CASE WHEN purpose = 'general_donation' THEN amount ELSE 0 END) as donation_income,
        SUM(CASE WHEN purpose = 'admin_fee' THEN amount ELSE 0 END) as admin_fee_income,
        SUM(CASE WHEN purpose NOT IN ('monthly_sponsorship','general_donation','admin_fee') THEN amount ELSE 0 END) as other_income,
        SUM(amount) as total_income
        FROM transactions
        WHERE status = 'posted' AND transaction_date BETWEEN '{$from}' AND '{$to}'
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m') ORDER BY month");

    $income_expense_data['expense'] = dbFetchAll("SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        SUM(amount) as total_expense
        FROM transactions
        WHERE status = 'posted' AND transaction_type = 'outflow'
        AND transaction_date BETWEEN '{$from}' AND '{$to}'
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m') ORDER BY month");
}

$treasury_balances = [];
if ($report_type === 'treasury') {
    $treasury_balances = dbFetchAll("SELECT
        a.code, a.name_ar, a.name_en,
        COALESCE(SUM(jl.debit - jl.credit), 0) as balance
        FROM accounts a
        LEFT JOIN journal_lines jl ON a.id = jl.account_id
        LEFT JOIN journal_entries je ON jl.entry_id = je.id AND je.status = 'posted'
        WHERE a.code IN ('1100', '1200', '1300') AND a.is_active = 1
        GROUP BY a.id, a.code, a.name_ar, a.name_en ORDER BY a.code");
}

$disbursement_summary = [];
if ($report_type === 'disbursement') {
    $from_month = substr($from, 0, 7);
    $to_month = substr($to, 0, 7);
    $disbursement_summary = dbFetchAll("SELECT
        md.month, u.full_name as nanny_name, COUNT(di.id) as families_count,
        SUM(di.amount) as total_amount,
        SUM(CASE WHEN di.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN di.status = 'returned' THEN di.amount ELSE 0 END) as returned_amount,
        md.status as disb_status
        FROM monthly_disbursements md
        JOIN users u ON md.nanny_id = u.id
        LEFT JOIN disbursement_items di ON md.id = di.disbursement_id
        WHERE md.month BETWEEN '{$from_month}' AND '{$to_month}'
        GROUP BY md.id, md.month, md.nanny_id, u.full_name, md.status
        ORDER BY md.month DESC, u.full_name");
}

$returns_voids = [];
if ($report_type === 'returns_voids') {
    // The two UNION branches read text from different tables whose collations
    // may differ (for example families.mother_name vs sponsors.full_name).
    // Normalize every textual UNION column explicitly so MariaDB never has to
    // choose between incompatible collations.
    $returns_voids = dbFetchAll("SELECT
        CONVERT('return' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type,
        di.id,
        CONVERT(di.return_reason USING utf8mb4) COLLATE utf8mb4_unicode_ci as reason,
        di.amount,
        di.returned_at as date,
        CONVERT(f.mother_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as entity_name,
        CONVERT(md.month USING utf8mb4) COLLATE utf8mb4_unicode_ci as month,
        CONVERT(CONCAT('JE-RET-', di.id) USING utf8mb4) COLLATE utf8mb4_unicode_ci as journal_ref
        FROM disbursement_items di
        JOIN monthly_disbursements md ON di.disbursement_id = md.id
        JOIN families f ON di.family_id = f.id
        WHERE di.status = 'returned' AND di.returned_at BETWEEN '{$from}' AND '{$to}'
        UNION ALL
        SELECT
        CONVERT('void' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type,
        t.id,
        CONVERT(t.void_reason USING utf8mb4) COLLATE utf8mb4_unicode_ci as reason,
        t.amount,
        t.voided_at as date,
        CONVERT(COALESCE(s.full_name, f.mother_name, 'غير محدد') USING utf8mb4) COLLATE utf8mb4_unicode_ci as entity_name,
        CONVERT(DATE_FORMAT(t.transaction_date, '%Y-%m') USING utf8mb4) COLLATE utf8mb4_unicode_ci as month,
        CONVERT(CONCAT('JE-REV-', t.id) USING utf8mb4) COLLATE utf8mb4_unicode_ci as journal_ref
        FROM transactions t
        LEFT JOIN sponsorships sp ON t.sponsorship_id = sp.id
        LEFT JOIN sponsors s ON sp.sponsor_id = s.id
        LEFT JOIN families f ON t.family_id = f.id
        WHERE t.status = 'voided' AND t.voided_at BETWEEN '{$from}' AND '{$to}'
        ORDER BY date DESC");
}

$cashflow_data = [];
if ($report_type === 'cashflow') {
    $cashflow_data = dbFetchAll("SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        COALESCE(SUM(CASE WHEN transaction_type != 'outflow' AND status = 'posted' THEN amount ELSE 0 END), 0) as inflow,
        COALESCE(SUM(CASE WHEN transaction_type = 'outflow' AND status = 'posted' THEN amount ELSE 0 END), 0) as outflow
        FROM transactions WHERE transaction_date BETWEEN '{$from}' AND '{$to}'
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m') ORDER BY month");
}

$reports = [
    'income_expense' => 'reports.income_expense',
    'cashflow' => 'reports.cashflow',
    'treasury' => 'reports.treasury',
    'disbursement' => 'reports.disbursement',
    'returns_voids' => 'reports.returns_voids',
];

$status_badges = [
    'draft' => ['reports.status_draft', 'bg-secondary'],
    'pending_approval' => ['reports.status_pending_approval', 'bg-warning text-dark'],
    'approved' => ['reports.status_approved', 'bg-primary'],
    'transferred' => ['reports.status_transferred', 'bg-info'],
    'received' => ['reports.status_received', 'bg-success'],
    'returned' => ['reports.status_returned', 'bg-danger'],
    'cancelled' => ['reports.status_cancelled', 'bg-dark'],
    'voided' => ['reports.status_voided', 'bg-danger'],
];

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-coins me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted"><?php echo e(t('reports.financial_subtitle', ['from' => $from, 'to' => $to])); ?></p>
</div>

<div class="card mb-4 fade-in shadow-sm"><div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="report" value="<?php echo e($report_type); ?>">
        <div class="col-md-3"><label class="form-label fw-bold"><?php echo e(t('accounting.from')); ?></label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>" required></div>
        <div class="col-md-3"><label class="form-label fw-bold"><?php echo e(t('accounting.to')); ?></label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>" required></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i><?php echo e(t('reports.apply_filter')); ?></button></div>
    </form>
</div></div>

<ul class="nav nav-pills mb-4 fade-in flex-wrap gap-2">
<?php foreach ($reports as $key => $labelKey): ?>
    <li class="nav-item"><a class="nav-link <?php echo $report_type === $key ? 'active' : ''; ?>" href="?report=<?php echo e($key); ?>&from=<?php echo e($from); ?>&to=<?php echo e($to); ?>"><?php echo e(t($labelKey)); ?></a></li>
<?php endforeach; ?>
</ul>

<div class="mb-3 text-end">
    <button onclick="exportToExcel()" class="btn btn-success me-2"><i class="fas fa-file-excel me-1"></i><?php echo e(t('reports.export_excel')); ?></button>
    <button onclick="exportToPDF()" class="btn btn-danger"><i class="fas fa-file-pdf me-1"></i><?php echo e(t('reports.export_pdf')); ?></button>
</div>

<div id="report-content" class="fade-in">
<?php if ($report_type === 'income_expense'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-chart-bar me-2"></i><?php echo e(t('reports.income_expense')); ?></div><div class="card-body">
    <?php if (!empty($income_expense_data['income']) || !empty($income_expense_data['expense'])): ?>
        <canvas id="incomeExpenseChart" height="80"></canvas><hr>
        <div class="table-responsive"><table class="table table-bordered table-hover mt-3" id="report-table"><thead class="table-light"><tr>
            <th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('reports.sponsorship_income')); ?></th><th><?php echo e(t('reports.general_donations')); ?></th><th><?php echo e(t('reports.admin_fees')); ?></th><th><?php echo e(t('reports.other_income')); ?></th><th><?php echo e(t('reports.total_income')); ?></th><th><?php echo e(t('reports.total_expense')); ?></th><th><?php echo e(t('reports.net_profit_loss')); ?></th>
        </tr></thead><tbody>
        <?php
        $all_months = array_unique(array_merge(array_column($income_expense_data['income'], 'month'), array_column($income_expense_data['expense'], 'month')));
        sort($all_months);
        foreach ($all_months as $month):
            $income_row = current(array_filter($income_expense_data['income'], fn($r) => $r['month'] === $month));
            $expense_row = current(array_filter($income_expense_data['expense'], fn($r) => $r['month'] === $month));
            $total_income = $income_row ? (float)$income_row['total_income'] : 0;
            $total_expense = $expense_row ? (float)$expense_row['total_expense'] : 0;
        ?>
            <tr>
                <td><?php echo e($month); ?></td>
                <td><?php echo number_format((float)($income_row['sponsorship_income'] ?? 0), 2); ?></td>
                <td><?php echo number_format((float)($income_row['donation_income'] ?? 0), 2); ?></td>
                <td><?php echo number_format((float)($income_row['admin_fee_income'] ?? 0), 2); ?></td>
                <td><?php echo number_format((float)($income_row['other_income'] ?? 0), 2); ?></td>
                <td><?php echo number_format($total_income, 2); ?></td>
                <td><?php echo number_format($total_expense, 2); ?></td>
                <td class="fw-bold <?php echo ($total_income - $total_expense) >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($total_income - $total_expense, 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="alert alert-info mb-0"><?php echo e(t('reports.no_data')); ?></div>
    <?php endif; ?>
    </div></div>
<?php elseif ($report_type === 'cashflow'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold"><i class="fas fa-water me-2"></i><?php echo e(t('reports.cashflow')); ?></div><div class="card-body">
        <?php if ($cashflow_data): ?>
            <div class="table-responsive"><table class="table table-bordered table-hover" id="report-table"><thead class="table-light"><tr><th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('reports.inflow')); ?></th><th><?php echo e(t('reports.outflow')); ?></th><th><?php echo e(t('reports.net_cashflow')); ?></th></tr></thead><tbody>
            <?php foreach ($cashflow_data as $r): $net = (float)$r['inflow'] - (float)$r['outflow']; ?><tr><td><?php echo e($r['month']); ?></td><td><?php echo number_format((float)$r['inflow'], 2); ?></td><td><?php echo number_format((float)$r['outflow'], 2); ?></td><td class="fw-bold <?php echo $net >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($net, 2); ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><div class="alert alert-info mb-0"><?php echo e(t('reports.no_data')); ?></div><?php endif; ?>
    </div></div>
<?php elseif ($report_type === 'treasury'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold"><i class="fas fa-vault me-2"></i><?php echo e(t('reports.treasury')); ?></div><div class="card-body">
        <?php if ($treasury_balances): ?><div class="table-responsive"><table class="table table-bordered table-hover" id="report-table"><thead class="table-light"><tr><th><?php echo e(t('accounting.account_code')); ?></th><th><?php echo e(t('accounting.account_name')); ?></th><th><?php echo e(t('reports.balance')); ?></th></tr></thead><tbody><?php foreach ($treasury_balances as $r): ?><tr><td><?php echo e($r['code']); ?></td><td><?php echo e(AK_LANG === 'en' ? $r['name_en'] : $r['name_ar']); ?></td><td class="fw-bold"><?php echo number_format((float)$r['balance'], 2); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="alert alert-info mb-0"><?php echo e(t('reports.no_data')); ?></div><?php endif; ?>
    </div></div>
<?php elseif ($report_type === 'disbursement'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold"><i class="fas fa-hand-holding-dollar me-2"></i><?php echo e(t('reports.disbursement')); ?></div><div class="card-body">
        <?php if ($disbursement_summary): ?><div class="table-responsive"><table class="table table-bordered table-hover" id="report-table"><thead class="table-light"><tr><th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('reports.nanny')); ?></th><th><?php echo e(t('reports.families_count')); ?></th><th><?php echo e(t('reports.total_amount')); ?></th><th><?php echo e(t('reports.paid_count')); ?></th><th><?php echo e(t('reports.returned_amount')); ?></th><th><?php echo e(t('common.status')); ?></th></tr></thead><tbody><?php foreach ($disbursement_summary as $r): $badge = $status_badges[$r['disb_status']] ?? ['common.unknown', 'bg-secondary']; ?><tr><td><?php echo e($r['month']); ?></td><td><?php echo e($r['nanny_name']); ?></td><td><?php echo (int)$r['families_count']; ?></td><td><?php echo number_format((float)$r['total_amount'], 2); ?></td><td><?php echo (int)$r['paid_count']; ?></td><td><?php echo number_format((float)$r['returned_amount'], 2); ?></td><td><span class="badge <?php echo e($badge[1]); ?>"><?php echo e(t($badge[0])); ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="alert alert-info mb-0"><?php echo e(t('reports.no_data')); ?></div><?php endif; ?>
    </div></div>
<?php elseif ($report_type === 'returns_voids'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold"><i class="fas fa-rotate-left me-2"></i><?php echo e(t('reports.returns_voids')); ?></div><div class="card-body">
        <?php if ($returns_voids): ?><div class="table-responsive"><table class="table table-bordered table-hover" id="report-table"><thead class="table-light"><tr><th><?php echo e(t('common.type')); ?></th><th><?php echo e(t('common.reference')); ?></th><th><?php echo e(t('common.reason')); ?></th><th><?php echo e(t('common.amount')); ?></th><th><?php echo e(t('common.date')); ?></th><th><?php echo e(t('common.entity')); ?></th><th><?php echo e(t('common.month')); ?></th></tr></thead><tbody><?php foreach ($returns_voids as $r): ?><tr><td><span class="badge <?php echo $r['type'] === 'return' ? 'bg-warning text-dark' : 'bg-danger'; ?>"><?php echo e(t($r['type'] === 'return' ? 'reports.return' : 'reports.void')); ?></span></td><td><?php echo e($r['journal_ref']); ?></td><td><?php echo e($r['reason'] ?: t('common.not_available')); ?></td><td><?php echo number_format((float)$r['amount'], 2); ?></td><td><?php echo e($r['date']); ?></td><td><?php echo e($r['entity_name']); ?></td><td><?php echo e($r['month']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="alert alert-info mb-0"><?php echo e(t('reports.no_data')); ?></div><?php endif; ?>
    </div></div>
<?php endif; ?>
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('report-table');
    if (!table || typeof XLSX === 'undefined') return;
    const wb = XLSX.utils.table_to_book(table, {sheet: 'Report'});
    XLSX.writeFile(wb, 'financial-report.xlsx');
}
function exportToPDF() {
    const content = document.getElementById('report-content');
    if (!content || typeof html2pdf === 'undefined') return;
    html2pdf().set({margin: 0.35, filename: 'financial-report.pdf', image: {type: 'jpeg', quality: 0.95}, html2canvas: {scale: 2}, jsPDF: {unit: 'in', format: 'a4', orientation: 'landscape'}}).from(content).save();
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
