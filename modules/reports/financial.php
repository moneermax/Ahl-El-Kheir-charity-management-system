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
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'financial_manager'];
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
        $all_months = array_unique(array_merge(array_column($income_expense_data['income'], 'month'), array_column($income_expense_data['expense'], 'month'))); sort($all_months);
        foreach ($all_months as $month):
            $income_row = current(array_filter($income_expense_data['income'], fn($r) => $r['month'] === $month));
            $expense_row = current(array_filter($income_expense_data['expense'], fn($r) => $r['month'] === $month));
            $total_income = $income_row ? (float)$income_row['total_income'] : 0;
            $total_expense = $expense_row ? (float)$expense_row['total_expense'] : 0;
            $net = $total_income - $total_expense;
        ?>
            <tr><td><?php echo e($month); ?></td>
            <td><?php echo number_format((float)($income_row['sponsorship_income'] ?? 0), 2); ?></td><td><?php echo number_format((float)($income_row['donation_income'] ?? 0), 2); ?></td><td><?php echo number_format((float)($income_row['admin_fee_income'] ?? 0), 2); ?></td><td><?php echo number_format((float)($income_row['other_income'] ?? 0), 2); ?></td>
            <td class="text-success fw-bold"><?php echo number_format($total_income, 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td><td class="text-danger fw-bold"><?php echo number_format($total_expense, 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td><td class="<?php echo $net >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold"><?php echo number_format($net, 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?><div class="alert alert-info"><?php echo e(t('reports.no_period_data')); ?></div><?php endif; ?>
    </div></div>

<?php elseif ($report_type === 'treasury'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-wallet me-2"></i><?php echo e(t('reports.treasury_balances')); ?></div><div class="card-body">
        <div class="row g-4 mb-4"><?php foreach ($treasury_balances as $balance): ?><div class="col-md-4"><div class="card border-0 shadow-sm text-center"><div class="card-body">
            <h6 class="text-muted"><?php echo e($balance['name_ar']); ?></h6><div class="display-6 fw-bold" style="color:#1b4d8f;"><?php echo number_format($balance['balance'], 2); ?> <span class="fs-6"><?php echo e(t('accounting.currency_sdg')); ?></span></div><small class="text-muted"><?php echo e(t('reports.account_code')); ?>: <?php echo e($balance['code']); ?></small>
        </div></div></div><?php endforeach; ?></div>
        <div class="table-responsive"><table class="table table-bordered" id="report-table"><thead class="table-light"><tr><th><?php echo e(t('reports.account_code')); ?></th><th><?php echo e(t('reports.account_name_ar')); ?></th><th><?php echo e(t('reports.account_name_en')); ?></th><th><?php echo e(t('reports.current_balance')); ?></th></tr></thead><tbody>
        <?php foreach ($treasury_balances as $balance): ?><tr><td><?php echo e($balance['code']); ?></td><td><?php echo e($balance['name_ar']); ?></td><td><?php echo e($balance['name_en']); ?></td><td class="fw-bold"><?php echo number_format($balance['balance'], 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div></div>

<?php elseif ($report_type === 'disbursement'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-hand-holding-usd me-2"></i><?php echo e(t('reports.disbursement_summary')); ?></div><div class="card-body">
    <?php if (!empty($disbursement_summary)): ?><div class="table-responsive"><table class="table table-bordered table-hover" id="report-table"><thead class="table-light"><tr>
        <th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('reports.nanny')); ?></th><th><?php echo e(t('reports.families_count')); ?></th><th><?php echo e(t('reports.total_amount')); ?></th><th><?php echo e(t('reports.paid')); ?></th><th><?php echo e(t('reports.returned_amount')); ?></th><th><?php echo e(t('reports.status')); ?></th>
    </tr></thead><tbody>
    <?php foreach ($disbursement_summary as $row): [$statusKey, $statusClass] = $status_badges[$row['disb_status']] ?? [null, 'bg-secondary']; $statusLabel = $statusKey ? t($statusKey) : (string)$row['disb_status']; ?>
        <tr><td><?php echo e($row['month']); ?></td><td><?php echo e($row['nanny_name']); ?></td><td><?php echo number_format((float)($row['families_count'] ?? 0)); ?></td><td><?php echo number_format((float)($row['total_amount'] ?? 0), 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td><td><?php echo number_format((float)($row['paid_count'] ?? 0)); ?></td><td class="text-danger"><?php echo number_format((float)($row['returned_amount'] ?? 0), 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td><td><span class="badge <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php else: ?><div class="alert alert-info"><?php echo e(t('reports.no_period_data')); ?></div><?php endif; ?>
    </div></div>

<?php elseif ($report_type === 'returns_voids'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-undo me-2"></i><?php echo e(t('reports.returns_voids')); ?></div><div class="card-body">
    <?php if (!empty($returns_voids)): ?><div class="table-responsive"><table class="table table-bordered table-hover" id="report-table"><thead class="table-light"><tr>
        <th><?php echo e(t('reports.type')); ?></th><th><?php echo e(t('reports.date')); ?></th><th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('reports.family_sponsor')); ?></th><th><?php echo e(t('reports.total_amount')); ?></th><th><?php echo e(t('reports.reason')); ?></th><th><?php echo e(t('reports.journal_reference')); ?></th>
    </tr></thead><tbody>
    <?php foreach ($returns_voids as $row): ?><tr class="<?php echo $row['type'] === 'void' ? 'table-warning' : 'table-danger'; ?>"><td>
        <?php if ($row['type'] === 'void'): ?><span class="badge bg-warning text-dark"><?php echo e(t('reports.void')); ?></span><?php else: ?><span class="badge bg-danger"><?php echo e(t('reports.return')); ?></span><?php endif; ?>
    </td><td><?php echo e($row['date'] ?? '-'); ?></td><td><?php echo e($row['month'] ?? '-'); ?></td><td><?php echo e($row['entity_name'] ?? '-'); ?></td><td><?php echo number_format((float)$row['amount'], 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td><td><?php echo e($row['reason'] ?? '-'); ?></td><td><code><?php echo e($row['journal_ref']); ?></code></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="alert alert-info"><?php echo e(t('reports.no_returns_voids')); ?></div><?php endif; ?>
    </div></div>

<?php elseif ($report_type === 'cashflow'): ?>
    <div class="card shadow-sm"><div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-exchange-alt me-2"></i><?php echo e(t('reports.cashflow')); ?></div><div class="card-body">
    <?php if (!empty($cashflow_data)): ?><canvas id="cashflowChart" height="80"></canvas><hr><div class="table-responsive"><table class="table table-bordered table-hover mt-3" id="report-table"><thead class="table-light"><tr><th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('reports.inflow')); ?></th><th><?php echo e(t('reports.outflow')); ?></th><th><?php echo e(t('reports.net_cashflow')); ?></th></tr></thead><tbody>
        <?php foreach ($cashflow_data as $row): $net = (float)$row['inflow'] - (float)$row['outflow']; ?><tr><td><?php echo e($row['month']); ?></td><td class="text-success"><?php echo number_format($row['inflow'], 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td><td class="text-danger"><?php echo number_format($row['outflow'], 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td><td class="<?php echo $net >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold"><?php echo number_format($net, 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php else: ?><div class="alert alert-info"><?php echo e(t('reports.no_period_data')); ?></div><?php endif; ?>
    </div></div>
<?php endif; ?>
</div>

<div class="mt-4 text-center"><a href="<?php echo APP_URL; ?>modules/reports/index.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><?php echo e(t('reports.back_to_center')); ?></a></div>

<script>
<?php if ($report_type === 'income_expense' && (!empty($income_expense_data['income']) || !empty($income_expense_data['expense']))):
    $chart_months = array_unique(array_merge(array_column($income_expense_data['income'], 'month'), array_column($income_expense_data['expense'], 'month'))); sort($chart_months);
    $incomeMap = []; foreach ($income_expense_data['income'] as $r) $incomeMap[$r['month']] = $r;
    $expenseMap = []; foreach ($income_expense_data['expense'] as $r) $expenseMap[$r['month']] = $r;
?>
new Chart(document.getElementById('incomeExpenseChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_values($chart_months), JSON_UNESCAPED_UNICODE); ?>,
        datasets: [
            {label: <?php echo json_encode(t('reports.sponsorship_income_chart'), JSON_UNESCAPED_UNICODE); ?>, data: <?php echo json_encode(array_map(fn($m) => (float)($incomeMap[$m]['sponsorship_income'] ?? 0), $chart_months)); ?>},
            {label: <?php echo json_encode(t('reports.general_donations_chart'), JSON_UNESCAPED_UNICODE); ?>, data: <?php echo json_encode(array_map(fn($m) => (float)($incomeMap[$m]['donation_income'] ?? 0), $chart_months)); ?>},
            {label: <?php echo json_encode(t('reports.admin_fees_chart'), JSON_UNESCAPED_UNICODE); ?>, data: <?php echo json_encode(array_map(fn($m) => (float)($incomeMap[$m]['admin_fee_income'] ?? 0), $chart_months)); ?>},
            {label: <?php echo json_encode(t('reports.expense_chart'), JSON_UNESCAPED_UNICODE); ?>, data: <?php echo json_encode(array_map(fn($m) => (float)($expenseMap[$m]['total_expense'] ?? 0), $chart_months)); ?>}
        ]
    }, options: {responsive:true, plugins:{legend:{position:'bottom'}}, scales:{y:{beginAtZero:true}}}
});
<?php endif; ?>

<?php if ($report_type === 'cashflow' && !empty($cashflow_data)): ?>
new Chart(document.getElementById('cashflowChart').getContext('2d'), {
    type:'line',
    data:{labels:<?php echo json_encode(array_column($cashflow_data, 'month'), JSON_UNESCAPED_UNICODE); ?>,datasets:[
        {label:<?php echo json_encode(t('reports.inflow_chart'), JSON_UNESCAPED_UNICODE); ?>,data:<?php echo json_encode(array_map(fn($r)=>(float)$r['inflow'],$cashflow_data)); ?>,fill:true,tension:.3},
        {label:<?php echo json_encode(t('reports.outflow_chart'), JSON_UNESCAPED_UNICODE); ?>,data:<?php echo json_encode(array_map(fn($r)=>(float)$r['outflow'],$cashflow_data)); ?>,fill:true,tension:.3}
    ]},options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}
});
<?php endif; ?>

function exportToExcel(){
    const table=document.querySelector('#report-content table');
    if(!table){alert(<?php echo json_encode(t('reports.no_table_export'), JSON_UNESCAPED_UNICODE); ?>);return;}
    const wb=XLSX.utils.table_to_book(table,{sheet:'Report'});XLSX.writeFile(wb,'financial_report_<?php echo date('Y-m-d'); ?>.xlsx');
}
function exportToPDF(){
    const element=document.getElementById('report-content');
    const opt={margin:.5,filename:'financial_report_<?php echo date('Y-m-d'); ?>.pdf',image:{type:'jpeg',quality:.98},html2canvas:{scale:2,useCORS:true},jsPDF:{unit:'in',format:'a4',orientation:'landscape'}};
    html2pdf().set(opt).from(element).save();
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
