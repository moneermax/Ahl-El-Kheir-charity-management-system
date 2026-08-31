<<<<<<< HEAD
<?php
// modules/reports/operational.php - Operational Reports
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
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny', 'financial_manager'];
if (!in_array($role, $allowed_roles, true)) {
    $_SESSION['flash'][] = ['type' => 'error', 'message' => 'ليس لديك صلاحية الوصول لهذه الصفحة'];
    header('Location: ' . APP_URL . 'modules/reports/index.php');
    exit();
}

// معالجة فلاتر التاريخ
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));
$report_type = trim($_GET['report'] ?? 'supervisor');

$pageTitle = 'التقارير التشغيلية';
$active = 'reports';

// ==================== التقرير 1: أداء المشرفين ====================
$supervisor_data = [];
if ($report_type === 'supervisor') {
    $supervisor_sql = "SELECT 
        u.id as supervisor_id,
        u.full_name,
        COUNT(DISTINCT s.id) as total_sponsors,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_sponsors,
        COALESCE(SUM(CASE WHEN t.status = 'posted' THEN t.amount ELSE 0 END), 0) as total_collected,
        COUNT(DISTINCT CASE WHEN t.status = 'posted' THEN t.id END) as transaction_count
        FROM users u
        LEFT JOIN sponsors s ON s.supervisor_id = u.id
        LEFT JOIN transactions t ON t.sponsor_id = s.id AND t.transaction_date BETWEEN '{$from}' AND '{$to}'
        WHERE u.role_id = 4
        GROUP BY u.id, u.full_name
        ORDER BY total_collected DESC";
    
    $supervisor_data = dbFetchAll($supervisor_sql);
    
    // إحصائيات عامة للمشرفين
    $total_supervisors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM users WHERE role_id = 4")['c'] ?? 0);
    $total_sponsors_all = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors")['c'] ?? 0);
    $active_sponsors_all = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'active'")['c'] ?? 0);
}

// ==================== التقرير 2: أداء الكافلات ====================
$nanny_data = [];
if ($report_type === 'nanny') {
    $nanny_sql = "SELECT 
        u.id as nanny_id,
        u.full_name,
        COUNT(DISTINCT md.id) as disbursement_count,
        COALESCE(SUM(md.total_amount), 0) as total_amount,
        SUM(CASE WHEN di.status = 'paid' THEN 1 ELSE 0 END) as paid_families,
        SUM(CASE WHEN di.status = 'returned' THEN 1 ELSE 0 END) as returned_families,
        SUM(CASE WHEN di.status = 'pending' THEN 1 ELSE 0 END) as pending_families,
        COALESCE(SUM(CASE WHEN di.status = 'returned' THEN di.amount ELSE 0 END), 0) as returned_amount
        FROM users u
        LEFT JOIN monthly_disbursements md ON md.nanny_id = u.id AND md.month BETWEEN SUBSTRING('{$from}', 1, 7) AND SUBSTRING('{$to}', 1, 7)
        LEFT JOIN disbursement_items di ON di.disbursement_id = md.id
        WHERE u.role_id = 7
        GROUP BY u.id, u.full_name
        ORDER BY total_amount DESC";
    
    $nanny_data = dbFetchAll($nanny_sql);
    
    // إحصائيات عامة للكافلات
    $total_nannies = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM users WHERE role_id = 7")['c'] ?? 0);
}

// ==================== التقرير 3: حالة التحقق من العائلات ====================
$verification_data = [];
if ($report_type === 'verification') {
    // ملخص حالة التحقق من nanny_family_verifications
    $verification_sql = "SELECT 
        COUNT(*) as total_verifications,
        SUM(is_orphan_verified) as orphan_verified,
        SUM(is_mother_contact_verified) as mother_verified,
        SUM(is_bank_verified) as bank_verified,
        SUM(CASE WHEN is_orphan_verified = 1 AND is_mother_contact_verified = 1 AND is_bank_verified = 1 THEN 1 ELSE 0 END) as fully_verified
        FROM nanny_family_verifications
        WHERE created_at BETWEEN '{$from}' AND '{$to}'";
    
    $verification_data['summary'] = dbFetchOne($verification_sql);
    
    // تفاصيل حسب الكافلة
    $verification_by_nanny_sql = "SELECT 
        u.full_name as nanny_name,
        COUNT(nfv.id) as total_families,
        SUM(nfv.is_orphan_verified) as orphan_verified,
        SUM(nfv.is_mother_contact_verified) as mother_verified,
        SUM(nfv.is_bank_verified) as bank_verified,
        SUM(CASE WHEN nfv.is_orphan_verified = 1 AND nfv.is_mother_contact_verified = 1 AND nfv.is_bank_verified = 1 THEN 1 ELSE 0 END) as fully_verified
        FROM nanny_family_verifications nfv
        JOIN users u ON nfv.nanny_id = u.id
        WHERE nfv.created_at BETWEEN '{$from}' AND '{$to}'
        GROUP BY u.id, u.full_name
        ORDER BY total_families DESC";
    
    $verification_data['by_nanny'] = dbFetchAll($verification_by_nanny_sql);
    
    // تفاصيل حسب الشهر
    $verification_by_month_sql = "SELECT 
        nfv.month,
        COUNT(*) as total_families,
        SUM(nfv.is_orphan_verified) as orphan_verified,
        SUM(nfv.is_mother_contact_verified) as mother_verified,
        SUM(nfv.is_bank_verified) as bank_verified,
        SUM(CASE WHEN nfv.is_orphan_verified = 1 AND nfv.is_mother_contact_verified = 1 AND nfv.is_bank_verified = 1 THEN 1 ELSE 0 END) as fully_verified
        FROM nanny_family_verifications nfv
        WHERE nfv.created_at BETWEEN '{$from}' AND '{$to}'
        GROUP BY nfv.month
        ORDER BY nfv.month DESC";
    
    $verification_data['by_month'] = dbFetchAll($verification_by_month_sql);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-tasks me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">تقارير الأداء التشغيلي — <?php echo e($from); ?> → <?php echo e($to); ?></p>
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
        'supervisor' => 'أداء المشرفين',
        'nanny' => 'أداء الكافلات',
        'verification' => 'حالة التحقق من العائلات'
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
    
    <?php if ($report_type === 'supervisor'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-user-tie me-2"></i> أداء المشرفين
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي المشرفين</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($total_supervisors); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي الكفلاء</h6>
                                <div class="display-6 fw-bold text-info"><?php echo number_format($total_sponsors_all); ?></div>
                                <small class="text-success fw-bold"><?php echo number_format($active_sponsors_all); ?> نشط</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">نسبة النشاط</h6>
                                <div class="display-6 fw-bold text-success">
                                    <?php echo $total_sponsors_all > 0 ? round(($active_sponsors_all / $total_sponsors_all) * 100, 1) : 0; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table">
                    <thead class="table-light">
                        <tr>
                            <th>المشرف</th>
                            <th>إجمالي الكفلاء</th>
                            <th>الكفلاء النشطين</th>
                            <th>عدد التحصيلات</th>
                            <th>إجمالي المحصّل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supervisor_data as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['full_name']); ?></td>
                            <td><?php echo number_format($row['total_sponsors']); ?></td>
                            <td>
                                <span class="badge bg-success"><?php echo number_format($row['active_sponsors']); ?></span>
                            </td>
                            <td><?php echo number_format($row['transaction_count']); ?></td>
                            <td class="text-success fw-bold"><?php echo number_format($row['total_collected'], 2); ?> ج.س</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'nanny'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-hands-helping me-2"></i> أداء الكافلات
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي الكافلات</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($total_nannies); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">دفعات الصرف</h6>
                                <div class="display-6 fw-bold text-info">
                                    <?php echo number_format(array_sum(array_column($nanny_data, 'disbursement_count'))); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">أسر تم صرفها</h6>
                                <div class="display-6 fw-bold text-success">
                                    <?php echo number_format(array_sum(array_column($nanny_data, 'paid_families'))); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">مبالغ مرتجعة</h6>
                                <div class="display-6 fw-bold text-danger">
                                    <?php echo number_format(array_sum(array_column($nanny_data, 'returned_amount')), 0); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table">
                    <thead class="table-light">
                        <tr>
                            <th>الكافلة</th>
                            <th>عدد الدفعات</th>
                            <th>إجمالي المبلغ</th>
                            <th>تم صرفه</th>
                            <th>معلّق</th>
                            <th>مرتجع</th>
                            <th>مبلغ مرتجع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nanny_data as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['full_name']); ?></td>
                            <td><?php echo number_format($row['disbursement_count']); ?></td>
                            <td class="fw-bold"><?php echo number_format($row['total_amount'], 2); ?> ج.س</td>
                            <td><span class="badge bg-success"><?php echo number_format($row['paid_families']); ?></span></td>
                            <td><span class="badge bg-warning text-dark"><?php echo number_format($row['pending_families']); ?></span></td>
                            <td><span class="badge bg-danger"><?php echo number_format($row['returned_families']); ?></span></td>
                            <td class="text-danger"><?php echo number_format($row['returned_amount'], 2); ?> ج.س</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'verification'): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-clipboard-check me-2"></i> ملخص حالة التحقق
            </div>
            <div class="card-body">
                <?php $vs = $verification_data['summary'] ?? []; ?>
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي عمليات التحقق</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($vs['total_verifications'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">تم التحقق من اليتيم</h6>
                                <div class="display-6 fw-bold text-success"><?php echo number_format($vs['orphan_verified'] ?? 0); ?></div>
                                <small class="text-muted">
                                    <?php echo ($vs['total_verifications'] ?? 0) > 0 ? round((($vs['orphan_verified'] ?? 0) / ($vs['total_verifications'] ?? 1)) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">تم التحقق من الأم</h6>
                                <div class="display-6 fw-bold text-info"><?php echo number_format($vs['mother_verified'] ?? 0); ?></div>
                                <small class="text-muted">
                                    <?php echo ($vs['total_verifications'] ?? 0) > 0 ? round((($vs['mother_verified'] ?? 0) / ($vs['total_verifications'] ?? 1)) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">تم التحقق الكامل</h6>
                                <div class="display-6 fw-bold" style="color: #6f42c1;"><?php echo number_format($vs['fully_verified'] ?? 0); ?></div>
                                <small class="text-muted">
                                    <?php echo ($vs['total_verifications'] ?? 0) > 0 ? round((($vs['fully_verified'] ?? 0) / ($vs['total_verifications'] ?? 1)) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <canvas id="verificationChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-user-check me-2"></i> التحقق حسب الكافلة
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-nanny">
                    <thead class="table-light">
                        <tr>
                            <th>الكافلة</th>
                            <th>إجمالي الأسر</th>
                            <th>يتيم ✓</th>
                            <th>أم ✓</th>
                            <th>بنك ✓</th>
                            <th>تحقق كامل</th>
                            <th>نسبة التحقق الكامل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($verification_data['by_nanny'] as $row): 
                            $rate = $row['total_families'] > 0 ? round(($row['fully_verified'] / $row['total_families']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['nanny_name']); ?></td>
                            <td><?php echo number_format($row['total_families']); ?></td>
                            <td class="text-success"><?php echo number_format($row['orphan_verified']); ?></td>
                            <td class="text-info"><?php echo number_format($row['mother_verified']); ?></td>
                            <td class="text-primary"><?php echo number_format($row['bank_verified']); ?></td>
                            <td><span class="badge bg-success"><?php echo number_format($row['fully_verified']); ?></span></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $rate; ?>%">
                                        <?php echo $rate; ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-calendar-alt me-2"></i> التحقق حسب الشهر
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-month">
                    <thead class="table-light">
                        <tr>
                            <th>الشهر</th>
                            <th>إجمالي الأسر</th>
                            <th>يتيم ✓</th>
                            <th>أم ✓</th>
                            <th>بنك ✓</th>
                            <th>تحقق كامل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($verification_data['by_month'] as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['month']); ?></td>
                            <td><?php echo number_format($row['total_families']); ?></td>
                            <td class="text-success"><?php echo number_format($row['orphan_verified']); ?></td>
                            <td class="text-info"><?php echo number_format($row['mother_verified']); ?></td>
                            <td class="text-primary"><?php echo number_format($row['bank_verified']); ?></td>
                            <td><span class="badge bg-success"><?php echo number_format($row['fully_verified']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
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
// رسم بياني لحالة التحقق
<?php if ($report_type === 'verification' && !empty($verification_data['summary'])): ?>
const vs = <?php echo json_encode($verification_data['summary']); ?>;
const verCtx = document.getElementById('verificationChart').getContext('2d');
new Chart(verCtx, {
    type: 'bar',
    data: {
        labels: ['يتيم', 'أم', 'بنك', 'تحقق كامل'],
        datasets: [{
            label: 'عدد عمليات التحقق',
            data: [
                parseInt(vs.orphan_verified || 0),
                parseInt(vs.mother_verified || 0),
                parseInt(vs.bank_verified || 0),
                parseInt(vs.fully_verified || 0)
            ],
            backgroundColor: [
                'rgba(40, 167, 69, 0.7)',
                'rgba(23, 162, 184, 0.7)',
                'rgba(27, 77, 143, 0.7)',
                'rgba(111, 66, 193, 0.7)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            title: { display: true, text: 'توزيع عمليات التحقق', font: { size: 14 } }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
<?php endif; ?>

// تصدير إلى Excel
function exportToExcel() {
    const tables = document.querySelectorAll('#report-content table');
    if (tables.length === 0) {
        alert('لا يوجد جدول للتصدير');
        return;
    }
    const wb = XLSX.utils.book_new();
    tables.forEach((table, index) => {
        const ws = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws, `Sheet${index + 1}`);
    });
    XLSX.writeFile(wb, 'operational_report_<?php echo date("Y-m-d"); ?>.xlsx');
}

// تصدير إلى PDF
function exportToPDF() {
    const element = document.getElementById('report-content');
    const opt = {
        margin: 0.5,
        filename: 'operational_report_<?php echo date("Y-m-d"); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

=======
<?php
// modules/reports/operational.php - Operational Reports
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
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny', 'financial_manager'];
if (!in_array($role, $allowed_roles, true)) {
    $_SESSION['flash'][] = ['type' => 'error', 'message' => 'ليس لديك صلاحية الوصول لهذه الصفحة'];
    header('Location: ' . APP_URL . 'modules/reports/index.php');
    exit();
}

// معالجة فلاتر التاريخ
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));
$report_type = trim($_GET['report'] ?? 'supervisor');

$pageTitle = 'التقارير التشغيلية';
$active = 'reports';

// ==================== التقرير 1: أداء المشرفين ====================
$supervisor_data = [];
if ($report_type === 'supervisor') {
    $supervisor_sql = "SELECT 
        u.id as supervisor_id,
        u.full_name,
        COUNT(DISTINCT s.id) as total_sponsors,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_sponsors,
        COALESCE(SUM(CASE WHEN t.status = 'posted' THEN t.amount ELSE 0 END), 0) as total_collected,
        COUNT(DISTINCT CASE WHEN t.status = 'posted' THEN t.id END) as transaction_count
        FROM users u
        LEFT JOIN sponsors s ON s.supervisor_id = u.id
        LEFT JOIN transactions t ON t.sponsor_id = s.id AND t.transaction_date BETWEEN '{$from}' AND '{$to}'
        WHERE u.role_id = 4
        GROUP BY u.id, u.full_name
        ORDER BY total_collected DESC";
    
    $supervisor_data = dbFetchAll($supervisor_sql);
    
    // إحصائيات عامة للمشرفين
    $total_supervisors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM users WHERE role_id = 4")['c'] ?? 0);
    $total_sponsors_all = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors")['c'] ?? 0);
    $active_sponsors_all = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'active'")['c'] ?? 0);
}

// ==================== التقرير 2: أداء الكافلات ====================
$nanny_data = [];
if ($report_type === 'nanny') {
    $nanny_sql = "SELECT 
        u.id as nanny_id,
        u.full_name,
        COUNT(DISTINCT md.id) as disbursement_count,
        COALESCE(SUM(md.total_amount), 0) as total_amount,
        SUM(CASE WHEN di.status = 'paid' THEN 1 ELSE 0 END) as paid_families,
        SUM(CASE WHEN di.status = 'returned' THEN 1 ELSE 0 END) as returned_families,
        SUM(CASE WHEN di.status = 'pending' THEN 1 ELSE 0 END) as pending_families,
        COALESCE(SUM(CASE WHEN di.status = 'returned' THEN di.amount ELSE 0 END), 0) as returned_amount
        FROM users u
        LEFT JOIN monthly_disbursements md ON md.nanny_id = u.id AND md.month BETWEEN SUBSTRING('{$from}', 1, 7) AND SUBSTRING('{$to}', 1, 7)
        LEFT JOIN disbursement_items di ON di.disbursement_id = md.id
        WHERE u.role_id = 7
        GROUP BY u.id, u.full_name
        ORDER BY total_amount DESC";
    
    $nanny_data = dbFetchAll($nanny_sql);
    
    // إحصائيات عامة للكافلات
    $total_nannies = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM users WHERE role_id = 7")['c'] ?? 0);
}

// ==================== التقرير 3: حالة التحقق من العائلات ====================
$verification_data = [];
if ($report_type === 'verification') {
    // ملخص حالة التحقق من nanny_family_verifications
    $verification_sql = "SELECT 
        COUNT(*) as total_verifications,
        SUM(is_orphan_verified) as orphan_verified,
        SUM(is_mother_contact_verified) as mother_verified,
        SUM(is_bank_verified) as bank_verified,
        SUM(CASE WHEN is_orphan_verified = 1 AND is_mother_contact_verified = 1 AND is_bank_verified = 1 THEN 1 ELSE 0 END) as fully_verified
        FROM nanny_family_verifications
        WHERE created_at BETWEEN '{$from}' AND '{$to}'";
    
    $verification_data['summary'] = dbFetchOne($verification_sql);
    
    // تفاصيل حسب الكافلة
    $verification_by_nanny_sql = "SELECT 
        u.full_name as nanny_name,
        COUNT(nfv.id) as total_families,
        SUM(nfv.is_orphan_verified) as orphan_verified,
        SUM(nfv.is_mother_contact_verified) as mother_verified,
        SUM(nfv.is_bank_verified) as bank_verified,
        SUM(CASE WHEN nfv.is_orphan_verified = 1 AND nfv.is_mother_contact_verified = 1 AND nfv.is_bank_verified = 1 THEN 1 ELSE 0 END) as fully_verified
        FROM nanny_family_verifications nfv
        JOIN users u ON nfv.nanny_id = u.id
        WHERE nfv.created_at BETWEEN '{$from}' AND '{$to}'
        GROUP BY u.id, u.full_name
        ORDER BY total_families DESC";
    
    $verification_data['by_nanny'] = dbFetchAll($verification_by_nanny_sql);
    
    // تفاصيل حسب الشهر
    $verification_by_month_sql = "SELECT 
        nfv.month,
        COUNT(*) as total_families,
        SUM(nfv.is_orphan_verified) as orphan_verified,
        SUM(nfv.is_mother_contact_verified) as mother_verified,
        SUM(nfv.is_bank_verified) as bank_verified,
        SUM(CASE WHEN nfv.is_orphan_verified = 1 AND nfv.is_mother_contact_verified = 1 AND nfv.is_bank_verified = 1 THEN 1 ELSE 0 END) as fully_verified
        FROM nanny_family_verifications nfv
        WHERE nfv.created_at BETWEEN '{$from}' AND '{$to}'
        GROUP BY nfv.month
        ORDER BY nfv.month DESC";
    
    $verification_data['by_month'] = dbFetchAll($verification_by_month_sql);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-tasks me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">تقارير الأداء التشغيلي — <?php echo e($from); ?> → <?php echo e($to); ?></p>
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
        'supervisor' => 'أداء المشرفين',
        'nanny' => 'أداء الكافلات',
        'verification' => 'حالة التحقق من العائلات'
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
    
    <?php if ($report_type === 'supervisor'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-user-tie me-2"></i> أداء المشرفين
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي المشرفين</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($total_supervisors); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي الكفلاء</h6>
                                <div class="display-6 fw-bold text-info"><?php echo number_format($total_sponsors_all); ?></div>
                                <small class="text-success fw-bold"><?php echo number_format($active_sponsors_all); ?> نشط</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">نسبة النشاط</h6>
                                <div class="display-6 fw-bold text-success">
                                    <?php echo $total_sponsors_all > 0 ? round(($active_sponsors_all / $total_sponsors_all) * 100, 1) : 0; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table">
                    <thead class="table-light">
                        <tr>
                            <th>المشرف</th>
                            <th>إجمالي الكفلاء</th>
                            <th>الكفلاء النشطين</th>
                            <th>عدد التحصيلات</th>
                            <th>إجمالي المحصّل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supervisor_data as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['full_name']); ?></td>
                            <td><?php echo number_format($row['total_sponsors']); ?></td>
                            <td>
                                <span class="badge bg-success"><?php echo number_format($row['active_sponsors']); ?></span>
                            </td>
                            <td><?php echo number_format($row['transaction_count']); ?></td>
                            <td class="text-success fw-bold"><?php echo number_format($row['total_collected'], 2); ?> ج.س</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'nanny'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-hands-helping me-2"></i> أداء الكافلات
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي الكافلات</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($total_nannies); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">دفعات الصرف</h6>
                                <div class="display-6 fw-bold text-info">
                                    <?php echo number_format(array_sum(array_column($nanny_data, 'disbursement_count'))); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">أسر تم صرفها</h6>
                                <div class="display-6 fw-bold text-success">
                                    <?php echo number_format(array_sum(array_column($nanny_data, 'paid_families'))); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">مبالغ مرتجعة</h6>
                                <div class="display-6 fw-bold text-danger">
                                    <?php echo number_format(array_sum(array_column($nanny_data, 'returned_amount')), 0); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table">
                    <thead class="table-light">
                        <tr>
                            <th>الكافلة</th>
                            <th>عدد الدفعات</th>
                            <th>إجمالي المبلغ</th>
                            <th>تم صرفه</th>
                            <th>معلّق</th>
                            <th>مرتجع</th>
                            <th>مبلغ مرتجع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nanny_data as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['full_name']); ?></td>
                            <td><?php echo number_format($row['disbursement_count']); ?></td>
                            <td class="fw-bold"><?php echo number_format($row['total_amount'], 2); ?> ج.س</td>
                            <td><span class="badge bg-success"><?php echo number_format($row['paid_families']); ?></span></td>
                            <td><span class="badge bg-warning text-dark"><?php echo number_format($row['pending_families']); ?></span></td>
                            <td><span class="badge bg-danger"><?php echo number_format($row['returned_families']); ?></span></td>
                            <td class="text-danger"><?php echo number_format($row['returned_amount'], 2); ?> ج.س</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'verification'): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-clipboard-check me-2"></i> ملخص حالة التحقق
            </div>
            <div class="card-body">
                <?php $vs = $verification_data['summary'] ?? []; ?>
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي عمليات التحقق</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($vs['total_verifications'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">تم التحقق من اليتيم</h6>
                                <div class="display-6 fw-bold text-success"><?php echo number_format($vs['orphan_verified'] ?? 0); ?></div>
                                <small class="text-muted">
                                    <?php echo ($vs['total_verifications'] ?? 0) > 0 ? round((($vs['orphan_verified'] ?? 0) / ($vs['total_verifications'] ?? 1)) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">تم التحقق من الأم</h6>
                                <div class="display-6 fw-bold text-info"><?php echo number_format($vs['mother_verified'] ?? 0); ?></div>
                                <small class="text-muted">
                                    <?php echo ($vs['total_verifications'] ?? 0) > 0 ? round((($vs['mother_verified'] ?? 0) / ($vs['total_verifications'] ?? 1)) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">تم التحقق الكامل</h6>
                                <div class="display-6 fw-bold" style="color: #6f42c1;"><?php echo number_format($vs['fully_verified'] ?? 0); ?></div>
                                <small class="text-muted">
                                    <?php echo ($vs['total_verifications'] ?? 0) > 0 ? round((($vs['fully_verified'] ?? 0) / ($vs['total_verifications'] ?? 1)) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <canvas id="verificationChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-user-check me-2"></i> التحقق حسب الكافلة
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-nanny">
                    <thead class="table-light">
                        <tr>
                            <th>الكافلة</th>
                            <th>إجمالي الأسر</th>
                            <th>يتيم ✓</th>
                            <th>أم ✓</th>
                            <th>بنك ✓</th>
                            <th>تحقق كامل</th>
                            <th>نسبة التحقق الكامل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($verification_data['by_nanny'] as $row): 
                            $rate = $row['total_families'] > 0 ? round(($row['fully_verified'] / $row['total_families']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['nanny_name']); ?></td>
                            <td><?php echo number_format($row['total_families']); ?></td>
                            <td class="text-success"><?php echo number_format($row['orphan_verified']); ?></td>
                            <td class="text-info"><?php echo number_format($row['mother_verified']); ?></td>
                            <td class="text-primary"><?php echo number_format($row['bank_verified']); ?></td>
                            <td><span class="badge bg-success"><?php echo number_format($row['fully_verified']); ?></span></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $rate; ?>%">
                                        <?php echo $rate; ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-calendar-alt me-2"></i> التحقق حسب الشهر
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-month">
                    <thead class="table-light">
                        <tr>
                            <th>الشهر</th>
                            <th>إجمالي الأسر</th>
                            <th>يتيم ✓</th>
                            <th>أم ✓</th>
                            <th>بنك ✓</th>
                            <th>تحقق كامل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($verification_data['by_month'] as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['month']); ?></td>
                            <td><?php echo number_format($row['total_families']); ?></td>
                            <td class="text-success"><?php echo number_format($row['orphan_verified']); ?></td>
                            <td class="text-info"><?php echo number_format($row['mother_verified']); ?></td>
                            <td class="text-primary"><?php echo number_format($row['bank_verified']); ?></td>
                            <td><span class="badge bg-success"><?php echo number_format($row['fully_verified']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
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
// رسم بياني لحالة التحقق
<?php if ($report_type === 'verification' && !empty($verification_data['summary'])): ?>
const vs = <?php echo json_encode($verification_data['summary']); ?>;
const verCtx = document.getElementById('verificationChart').getContext('2d');
new Chart(verCtx, {
    type: 'bar',
    data: {
        labels: ['يتيم', 'أم', 'بنك', 'تحقق كامل'],
        datasets: [{
            label: 'عدد عمليات التحقق',
            data: [
                parseInt(vs.orphan_verified || 0),
                parseInt(vs.mother_verified || 0),
                parseInt(vs.bank_verified || 0),
                parseInt(vs.fully_verified || 0)
            ],
            backgroundColor: [
                'rgba(40, 167, 69, 0.7)',
                'rgba(23, 162, 184, 0.7)',
                'rgba(27, 77, 143, 0.7)',
                'rgba(111, 66, 193, 0.7)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            title: { display: true, text: 'توزيع عمليات التحقق', font: { size: 14 } }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
<?php endif; ?>

// تصدير إلى Excel
function exportToExcel() {
    const tables = document.querySelectorAll('#report-content table');
    if (tables.length === 0) {
        alert('لا يوجد جدول للتصدير');
        return;
    }
    const wb = XLSX.utils.book_new();
    tables.forEach((table, index) => {
        const ws = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws, `Sheet${index + 1}`);
    });
    XLSX.writeFile(wb, 'operational_report_<?php echo date("Y-m-d"); ?>.xlsx');
}

// تصدير إلى PDF
function exportToPDF() {
    const element = document.getElementById('report-content');
    const opt = {
        margin: 0.5,
        filename: 'operational_report_<?php echo date("Y-m-d"); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>