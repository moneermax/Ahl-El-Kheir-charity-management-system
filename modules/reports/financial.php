<?php
// modules/reports/financial.php - Financial Reports (Corrected)
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
$uid = Session::getUserId();

// التحقق من الصلاحية
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'financial_manager', 'accountant_staff'];
if (!in_array($role, $allowed_roles, true)) {
    $_SESSION['flash'][] = ['type' => 'error', 'message' => 'ليس لديك صلاحية الوصول لهذه الصفحة'];
    header('Location: ' . APP_URL . 'modules/reports/index.php');
    exit();
}

// معالجة فلاتر التاريخ
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));
$report_type = trim($_GET['report'] ?? 'income_expense');

$pageTitle = 'التقارير المالية';
$active = 'reports';

// ==================== التقرير 1: الإيرادات مقابل المصروفات ====================
$income_expense_data = [];
if ($report_type === 'income_expense') {
    // الإيرادات
    $income_sql = "SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        SUM(CASE WHEN purpose = 'monthly_sponsorship' THEN amount ELSE 0 END) as sponsorship_income,
        SUM(CASE WHEN purpose = 'general_donation' THEN amount ELSE 0 END) as donation_income,
        SUM(CASE WHEN purpose = 'admin_fee' THEN amount ELSE 0 END) as admin_fee_income,
        SUM(CASE WHEN purpose NOT IN ('monthly_sponsorship','general_donation','admin_fee') THEN amount ELSE 0 END) as other_income,
        SUM(amount) as total_income
        FROM transactions 
        WHERE status = 'posted' 
        AND transaction_date BETWEEN '{$from}' AND '{$to}'
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month";
    
    $income_expense_data['income'] = dbFetchAll($income_sql);
    
    // المصروفات (outflow)
    $expense_sql = "SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        SUM(amount) as total_expense
        FROM transactions 
        WHERE status = 'posted' 
        AND transaction_type = 'outflow'
        AND transaction_date BETWEEN '{$from}' AND '{$to}'
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month";
    
    $income_expense_data['expense'] = dbFetchAll($expense_sql);
}

// ==================== التقرير 2: أرصدة الخزائن ====================
$treasury_balances = [];
if ($report_type === 'treasury') {
    $treasury_sql = "SELECT 
        a.code,
        a.name_ar,
        a.name_en,
        COALESCE(SUM(jl.debit - jl.credit), 0) as balance
        FROM accounts a
        LEFT JOIN journal_lines jl ON a.id = jl.account_id
        LEFT JOIN journal_entries je ON jl.entry_id = je.id AND je.status = 'posted'
        WHERE a.code IN ('1100', '1200', '1300')
        AND a.is_active = 1
        GROUP BY a.id, a.code, a.name_ar, a.name_en
        ORDER BY a.code";
    
    $treasury_balances = dbFetchAll($treasury_sql);
}

// ==================== التقرير 3: ملخص الصرف حسب الكافلة/الشهر ====================
$disbursement_summary = [];
if ($report_type === 'disbursement') {
    $from_month = substr($from, 0, 7);
    $to_month = substr($to, 0, 7);
    
    $disb_sql = "SELECT 
        md.month,
        u.full_name as nanny_name,
        COUNT(di.id) as families_count,
        SUM(di.amount) as total_amount,
        SUM(CASE WHEN di.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN di.status = 'returned' THEN di.amount ELSE 0 END) as returned_amount,
        md.status as disb_status
        FROM monthly_disbursements md
        JOIN users u ON md.nanny_id = u.id
        LEFT JOIN disbursement_items di ON md.id = di.disbursement_id
        WHERE md.month BETWEEN '{$from_month}' AND '{$to_month}'
        GROUP BY md.id, md.month, md.nanny_id, u.full_name, md.status
        ORDER BY md.month DESC, u.full_name";
    
    $disbursement_summary = dbFetchAll($disb_sql);
}

// ==================== التقرير 4: سجل المرتجعات والإلغاءات ====================
$returns_voids = [];
if ($report_type === 'returns_voids') {
    $returns_sql = "SELECT 
        'return' as type,
        di.id,
        di.return_reason as reason,
        di.amount,
        di.returned_at as date,
        f.mother_name as entity_name,
        md.month,
        CONCAT('JE-RET-', di.id) as journal_ref
        FROM disbursement_items di
        JOIN monthly_disbursements md ON di.disbursement_id = md.id
        JOIN families f ON di.family_id = f.id
        WHERE di.status = 'returned'
        AND di.returned_at BETWEEN '{$from}' AND '{$to}'
        
        UNION ALL
        
        SELECT 
        'void' as type,
        t.id,
        t.void_reason as reason,
        t.amount,
        t.voided_at as date,
        COALESCE(s.full_name, f.mother_name, 'غير محدد') as entity_name,
        DATE_FORMAT(t.transaction_date, '%Y-%m') as month,
        CONCAT('JE-REV-', t.id) as journal_ref
        FROM transactions t
        LEFT JOIN sponsorships sp ON t.sponsorship_id = sp.id
        LEFT JOIN sponsors s ON sp.sponsor_id = s.id
        LEFT JOIN families f ON t.family_id = f.id
        WHERE t.status = 'voided'
        AND t.voided_at BETWEEN '{$from}' AND '{$to}'
        
        ORDER BY date DESC";
    
    $returns_voids = dbFetchAll($returns_sql);
}

// ==================== التقرير 5: التدفق النقدي ====================
$cashflow_data = [];
if ($report_type === 'cashflow') {
    $cashflow_sql = "SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        COALESCE(SUM(CASE WHEN transaction_type != 'outflow' AND status = 'posted' THEN amount ELSE 0 END), 0) as inflow,
        COALESCE(SUM(CASE WHEN transaction_type = 'outflow' AND status = 'posted' THEN amount ELSE 0 END), 0) as outflow
        FROM transactions
        WHERE transaction_date BETWEEN '{$from}' AND '{$to}'
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month";
    
    $cashflow_data = dbFetchAll($cashflow_sql);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-coins me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">تقارير مالية شاملة — <?php echo e($from); ?> → <?php echo e($to); ?></p>
</div>

<!-- نموذج الفلتر -->
<div class="card mb-4 fade-in shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="report" value="<?php echo e($report_type); ?>">
            <div class="col-md-3">
                <label class="form-label fw-bold">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100" style="background-color: #1b4d8f; border-color: #1b4d8f;">
                    <i class="fas fa-filter me-1"></i> تطبيق
                </button>
            </div>
        </form>
    </div>
</div>

<!-- اختيار نوع التقرير -->
<ul class="nav nav-pills mb-4 fade-in flex-wrap gap-2">
    <?php 
    $reports = [
        'income_expense' => 'الإيرادات مقابل المصروفات',
        'cashflow' => 'بيان التدفق النقدي',
        'treasury' => 'أرصدة الخزائن',
        'disbursement' => 'ملخص الصرف',
        'returns_voids' => 'المرتجعات والإلغاءات'
    ];
    foreach ($reports as $key => $label): 
    ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $report_type === $key ? 'active' : ''; ?>" 
               href="?report=<?php echo $key; ?>&from=<?php echo e($from); ?>&to=<?php echo e($to); ?>"
               style="<?php echo $report_type === $key ? 'background-color: #1b4d8f; color: white;' : 'color: #1b4d8f; background-color: #f8f9fa;'; ?>">
                <?php echo e($label); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- أزرار التصدير -->
<div class="mb-3 text-end">
    <button onclick="exportToExcel()" class="btn btn-success me-2">
        <i class="fas fa-file-excel me-1"></i> تصدير Excel
    </button>
    <button onclick="exportToPDF()" class="btn btn-danger">
        <i class="fas fa-file-pdf me-1"></i> تصدير PDF
    </button>
</div>

<!-- محتوى التقرير -->
<div id="report-content" class="fade-in">
    
    <?php if ($report_type === 'income_expense'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-chart-bar me-2"></i> الإيرادات مقابل المصروفات
            </div>
            <div class="card-body">
                <?php if (!empty($income_expense_data['income'])): ?>
                    <canvas id="incomeExpenseChart" height="80"></canvas>
                    <hr>
                    <div class="table-responsive">
                    <table class="table table-bordered table-hover mt-3" id="report-table">
                        <thead class="table-light">
                            <tr>
                                <th>الشهر</th>
                                <th>إيرادات الكفالات</th>
                                <th>تبرعات عامة</th>
                                <th>رسوم إدارية</th>
                                <th>أخرى</th>
                                <th>إجمالي الإيرادات</th>
                                <th>إجمالي المصروفات</th>
                                <th>صافي الربح/الخسارة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $all_months = array_unique(array_merge(
                                array_column($income_expense_data['income'], 'month'),
                                array_column($income_expense_data['expense'], 'month')
                            ));
                            sort($all_months);
                            
                            foreach ($all_months as $month): 
                                $income_row = current(array_filter($income_expense_data['income'], fn($r) => $r['month'] === $month));
                                $expense_row = current(array_filter($income_expense_data['expense'], fn($r) => $r['month'] === $month));
                                
                                $total_income = $income_row ? (float)$income_row['total_income'] : 0;
                                $total_expense = $expense_row ? (float)$expense_row['total_expense'] : 0;
                                $net = $total_income - $total_expense;
                            ?>
                            <tr>
                                <td><?php echo e($month); ?></td>
                                <td><?php echo $income_row ? number_format((float)$income_row['sponsorship_income'], 2) : '0.00'; ?></td>
<td><?php echo $income_row ? number_format((float)$income_row['donation_income'], 2) : '0.00'; ?></td>
<td><?php echo $income_row ? number_format((float)$income_row['admin_fee_income'], 2) : '0.00'; ?></td>
<td><?php echo $income_row ? number_format((float)$income_row['other_income'], 2) : '0.00'; ?></td>
                                <td class="text-success fw-bold"><?php echo number_format($total_income, 2); ?> ج.س</td>
                                <td class="text-danger fw-bold"><?php echo number_format($total_expense, 2); ?> ج.س</td>
                                <td class="<?php echo $net >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                    <?php echo number_format($net, 2); ?> ج.س
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">لا توجد بيانات للفترة المحددة</div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($report_type === 'treasury'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-wallet me-2"></i> أرصدة الخزائن
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <?php foreach ($treasury_balances as $balance): ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h6 class="text-muted"><?php echo e($balance['name_ar']); ?></h6>
                                <div class="display-6 fw-bold" style="color: #1b4d8f;">
                                    <?php echo number_format($balance['balance'], 2); ?> <span class="fs-6">ج.س</span>
                                </div>
                                <small class="text-muted">كود: <?php echo e($balance['code']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="table-responsive">
                <table class="table table-bordered" id="report-table">
                    <thead class="table-light">
                        <tr>
                            <th>الكود</th>
                            <th>اسم الحساب (عربي)</th>
                            <th>اسم الحساب (إنجليزي)</th>
                            <th>الرصيد الحالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treasury_balances as $balance): ?>
                        <tr>
                            <td><?php echo e($balance['code']); ?></td>
                            <td><?php echo e($balance['name_ar']); ?></td>
                            <td><?php echo e($balance['name_en']); ?></td>
                            <td class="fw-bold"><?php echo number_format($balance['balance'], 2); ?> ج.س</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'disbursement'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-hand-holding-usd me-2"></i> ملخص الصرف حسب الكافلة/الشهر
            </div>
            <div class="card-body">
                <?php if (!empty($disbursement_summary)): ?>
                    <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="report-table">
                        <thead class="table-light">
                            <tr>
                                <th>الشهر</th>
                                <th>الكافلة</th>
                                <th>عدد الأسر</th>
                                <th>إجمالي المبلغ</th>
                                <th>تم صرفه</th>
                                <th>تم ارتجاعه</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disbursement_summary as $row): 
                                $status_badges = [
                                    'draft' => ['مسودة', 'bg-secondary'],
                                    'pending_approval' => ['بانتظار الموافقة', 'bg-warning text-dark'],
                                    'approved' => ['معتمد', 'bg-primary'],
                                    'transferred' => ['محول', 'bg-info'],
                                    'received' => ['مستلم', 'bg-success'],
                                    'returned' => ['مرتجع', 'bg-danger'],
                                    'cancelled' => ['ملغي', 'bg-dark'],
                                    'voided' => ['مبطل', 'bg-danger']
                                ];
                                [$status_label, $status_class] = $status_badges[$row['disb_status']] ?? [$row['disb_status'], 'bg-secondary'];
                            ?>
                            <tr>
                                <td><?php echo e($row['month']); ?></td>
                                <td><?php echo e($row['nanny_name']); ?></td>
                                <td><?php echo number_format((float)($row['families_count'] ?? 0)); ?></td>
<td><?php echo number_format((float)($row['total_amount'] ?? 0), 2); ?> ج.س</td>
<td><?php echo number_format((float)($row['paid_count'] ?? 0)); ?> أسرة</td>
<td class="text-danger"><?php echo number_format((float)($row['returned_amount'] ?? 0), 2); ?> ج.س</td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo e($status_label); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">لا توجد بيانات للفترة المحددة</div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($report_type === 'returns_voids'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-undo me-2"></i> سجل المرتجعات والإلغاءات
            </div>
            <div class="card-body">
                <?php if (!empty($returns_voids)): ?>
                    <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="report-table">
                        <thead class="table-light">
                            <tr>
                                <th>النوع</th>
                                <th>التاريخ</th>
                                <th>الشهر</th>
                                <th>الأسرة/الكفيل</th>
                                <th>المبلغ</th>
                                <th>السبب</th>
                                <th>المرجع المحاسبي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returns_voids as $row): ?>
                            <tr class="<?php echo $row['type'] === 'void' ? 'table-warning' : 'table-danger'; ?>">
                                <td>
                                    <?php if ($row['type'] === 'void'): ?>
                                        <span class="badge bg-warning text-dark">إلغاء</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">ارتجاع</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($row['date'] ?? '-'); ?></td>
                                <td><?php echo e($row['month'] ?? '-'); ?></td>
                                <td><?php echo e($row['entity_name'] ?? '-'); ?></td>
                                <td><?php echo number_format($row['amount'], 2); ?> ج.س</td>
                                <td><?php echo e($row['reason'] ?? '-'); ?></td>
                                <td><code><?php echo e($row['journal_ref']); ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">لا توجد مرتجعات أو إلغاءات للفترة المحددة</div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($report_type === 'cashflow'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-exchange-alt me-2"></i> بيان التدفق النقدي
            </div>
            <div class="card-body">
                <?php if (!empty($cashflow_data)): ?>
                    <canvas id="cashflowChart" height="80"></canvas>
                    <hr>
                    <div class="table-responsive">
                    <table class="table table-bordered table-hover mt-3" id="report-table">
                        <thead class="table-light">
                            <tr>
                                <th>الشهر</th>
                                <th>التدفق الداخل</th>
                                <th>التدفق الخارج</th>
                                <th>صافي التدفق</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cashflow_data as $row): 
                                $net = (float)$row['inflow'] - (float)$row['outflow'];
                            ?>
                            <tr>
                                <td><?php echo e($row['month']); ?></td>
                                <td class="text-success"><?php echo number_format($row['inflow'], 2); ?> ج.س</td>
                                <td class="text-danger"><?php echo number_format($row['outflow'], 2); ?> ج.س</td>
                                <td class="<?php echo $net >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                    <?php echo number_format($net, 2); ?> ج.س
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">لا توجد بيانات للفترة المحددة</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
</div>

<!-- زر العودة -->
<div class="mt-4 text-center">
    <a href="<?php echo APP_URL; ?>modules/reports/index.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-right me-1"></i> العودة إلى مركز التقارير
    </a>
</div>

<script>
// رسم بياني للإيرادات مقابل المصروفات
<?php if ($report_type === 'income_expense' && !empty($income_expense_data['income'])): ?>
const ieCtx = document.getElementById('incomeExpenseChart').getContext('2d');
new Chart(ieCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(fn($m) => "'".e($m['month'])."'", $income_expense_data['income'])); ?>],
        datasets: [
            {
                label: 'إيرادات الكفالات',
                data: [<?php echo implode(',', array_map(fn($r) => $r['sponsorship_income'] ?? 0, $income_expense_data['income'])); ?>],
                backgroundColor: 'rgba(27, 77, 143, 0.7)',
            },
            {
                label: 'تبرعات عامة',
                data: [<?php echo implode(',', array_map(fn($r) => $r['donation_income'] ?? 0, $income_expense_data['income'])); ?>],
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
            },
            {
                label: 'رسوم إدارية',
                data: [<?php echo implode(',', array_map(fn($r) => $r['admin_fee_income'] ?? 0, $income_expense_data['income'])); ?>],
                backgroundColor: 'rgba(255, 193, 7, 0.7)',
            },
            {
                label: 'مصروفات',
                data: [<?php 
                    $expense_data = [];
                    foreach ($income_expense_data['income'] as $inc) {
                        $exp_row = current(array_filter($income_expense_data['expense'], fn($e) => $e['month'] === $inc['month']));
                        $expense_data[] = $exp_row ? ($exp_row['total_expense'] ?? 0) : 0;
                    }
                    echo implode(',', $expense_data);
                ?>],
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
<?php endif; ?>

// رسم بياني للتدفق النقدي
<?php if ($report_type === 'cashflow' && !empty($cashflow_data)): ?>
const cfCtx = document.getElementById('cashflowChart').getContext('2d');
new Chart(cfCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(fn($m) => "'".e($m['month'])."'", $cashflow_data)); ?>],
        datasets: [
            {
                label: 'التدفق الداخل',
                data: [<?php echo implode(',', array_map(fn($r) => $r['inflow'], $cashflow_data)); ?>],
                borderColor: 'rgba(40, 167, 69, 1)',
                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                fill: true,
                tension: 0.3
            },
            {
                label: 'التدفق الخارج',
                data: [<?php echo implode(',', array_map(fn($r) => $r['outflow'], $cashflow_data)); ?>],
                borderColor: 'rgba(220, 53, 69, 1)',
                backgroundColor: 'rgba(220, 53, 69, 0.2)',
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
<?php endif; ?>

// تصدير إلى Excel
function exportToExcel() {
    const table = document.querySelector('#report-content table');
    if (!table) {
        alert('لا يوجد جدول للتصدير');
        return;
    }
    const wb = XLSX.utils.table_to_book(table, {sheet: "Report"});
    XLSX.writeFile(wb, 'financial_report_<?php echo date("Y-m-d"); ?>.xlsx');
}

// تصدير إلى PDF
function exportToPDF() {
    const element = document.getElementById('report-content');
    const opt = {
        margin: 0.5,
        filename: 'financial_report_<?php echo date("Y-m-d"); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>