<?php
// modules/reports/sponsorship.php - Sponsorship Reports
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
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'supervisor'];
if (!in_array($role, $allowed_roles, true)) {
    $_SESSION['flash'][] = ['type' => 'error', 'message' => 'ليس لديك صلاحية الوصول لهذه الصفحة'];
    header('Location: ' . APP_URL . 'modules/reports/index.php');
    exit();
}

// معالجة فلاتر التاريخ
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));
$report_type = trim($_GET['report'] ?? 'coverage');

$pageTitle = 'تقارير الكفالات';
$active = 'reports';

// ==================== التقرير 1: تغطية الأيتام ====================
$coverage_data = [];
if ($report_type === 'coverage') {
    $total_children = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM family_children WHERE is_active = 1")['c'] ?? 0);
    $sponsored_children = (int)(dbFetchOne("SELECT COUNT(DISTINCT child_id) AS c FROM sponsorships WHERE status = 'active' AND child_id IS NOT NULL")['c'] ?? 0);
    
    try {
        $orphaned_families = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM vw_families_without_sponsorship")['c'] ?? 0);
    } catch (Exception $e) {
        $orphaned_families = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families f WHERE f.status IN ('active','pending') AND f.children_count > 0 AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON sp.child_id = fc.id WHERE fc.family_id = f.id AND sp.status = 'active')")['c'] ?? 0);
    }
    
    $total_families = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families WHERE status IN ('active','pending')")['c'] ?? 0);
    
    $coverage_data = [
        'total_children' => $total_children,
        'sponsored_children' => $sponsored_children,
        'unsponsored_children' => max(0, $total_children - $sponsored_children),
        'coverage_percent' => $total_children > 0 ? round(($sponsored_children / $total_children) * 100, 1) : 0,
        'orphaned_families' => $orphaned_families,
        'total_families' => $total_families,
        'covered_families' => max(0, $total_families - $orphaned_families)
    ];
}

// ==================== التقرير 2: نسبة الاحتفاظ بالكفلاء ====================
$retention_data = [];
if ($report_type === 'retention') {
    $total_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors")['c'] ?? 0);
    $active_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'active'")['c'] ?? 0);
    $cancelled_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'cancelled'")['c'] ?? 0);
    $completed_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'completed'")['c'] ?? 0);
    $new_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE created_at BETWEEN '{$from}' AND '{$to}'")['c'] ?? 0);
    
    $retention_data = [
        'total' => $total_sponsors,
        'active' => $active_sponsors,
        'cancelled' => $cancelled_sponsors,
        'completed' => $completed_sponsors,
        'new_in_period' => $new_sponsors,
        'retention_rate' => $total_sponsors > 0 ? round(($active_sponsors / $total_sponsors) * 100, 1) : 0
    ];
}

// ==================== التقرير 3: التوزيع حسب الخطاب/المشرف ====================
$distribution_data = [];
if ($report_type === 'distribution') {
    $by_letter_sql = "SELECT 
        l.name_ar as letter_name,
        l.code as letter_code,
        COUNT(s.id) as sponsor_count,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_count
        FROM letters l
        LEFT JOIN sponsors s ON s.first_letter_id = l.id
        GROUP BY l.id, l.name_ar, l.code
        ORDER BY sponsor_count DESC";
    $distribution_data['by_letter'] = dbFetchAll($by_letter_sql);
    
    $by_supervisor_sql = "SELECT 
        u.full_name as supervisor_name,
        COUNT(s.id) as sponsor_count,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_count
        FROM users u
        LEFT JOIN sponsors s ON s.supervisor_id = u.id
        WHERE u.role_id = 4
        GROUP BY u.id, u.full_name
        ORDER BY sponsor_count DESC";
    $distribution_data['by_supervisor'] = dbFetchAll($by_supervisor_sql);
}

// ==================== التقرير 4: ملخص حالة الكفالات ====================
$status_summary = [];
if ($report_type === 'status_summary') {
    $status_sql = "SELECT 
        status,
        COUNT(*) as count,
        SUM(monthly_amount) as total_monthly_commitment
        FROM sponsorships
        GROUP BY status
        ORDER BY count DESC";
    $status_summary = dbFetchAll($status_sql);
    
    $total_commitment = (float)(dbFetchOne("SELECT COALESCE(SUM(monthly_amount), 0) AS s FROM sponsorships WHERE status = 'active'")['s'] ?? 0);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-hand-holding-heart me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">تقارير الكفالات والتغطية — <?php echo e($from); ?> → <?php echo e($to); ?></p>
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
        'coverage' => 'تغطية الأيتام',
        'retention' => 'نسبة الاحتفاظ بالكفلاء',
        'distribution' => 'التوزيع (خطابات/مشرفين)',
        'status_summary' => 'حالة الكفالات'
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
    
    <?php if ($report_type === 'coverage'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-users me-2"></i> تغطية الأيتام والأسر
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي الأطفال</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($coverage_data['total_children']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">الأطفال المكفولين</h6>
                                <div class="display-6 fw-bold text-success"><?php echo number_format($coverage_data['sponsored_children']); ?></div>
                                <small class="text-success fw-bold"><?php echo $coverage_data['coverage_percent']; ?>% تغطية</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">الأطفال غير المكفولين</h6>
                                <div class="display-6 fw-bold text-danger"><?php echo number_format($coverage_data['unsponsored_children']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">الأسر بلا كفالة</h6>
                                <div class="display-6 fw-bold text-warning"><?php echo number_format($coverage_data['orphaned_families']); ?></div>
                                <small class="text-muted">من أصل <?php echo number_format($coverage_data['total_families']); ?> أسرة</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- الرسم البياني بحجم مناسب -->
                <div class="row justify-content-center">
                    <div class="col-md-6 col-lg-5">
                        <canvas id="coverageChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'retention'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-chart-pie me-2"></i> نسبة الاحتفاظ بالكفلاء
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي الكفلاء</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($retention_data['total']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">كفلاء نشطين</h6>
                                <div class="display-6 fw-bold text-success"><?php echo number_format($retention_data['active']); ?></div>
                                <small class="text-success fw-bold"><?php echo $retention_data['retention_rate']; ?>% نسبة الاحتفاظ</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">كفلاء ملغين</h6>
                                <div class="display-6 fw-bold text-danger"><?php echo number_format($retention_data['cancelled']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">كفلاء جدد (الفترة)</h6>
                                <div class="display-6 fw-bold text-info"><?php echo number_format($retention_data['new_in_period']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- الرسم البياني بحجم مناسب -->
                <div class="row justify-content-center">
                    <div class="col-md-6 col-lg-5">
                        <canvas id="retentionChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'distribution'): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-envelope me-2"></i> التوزيع حسب الخطاب
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-letters">
                    <thead class="table-light">
                        <tr>
                            <th>الخطاب</th>
                            <th>الكود</th>
                            <th>إجمالي الكفلاء</th>
                            <th>الكفلاء النشطين</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distribution_data['by_letter'] as $row): ?>
                        <tr>
                            <td><?php echo e($row['letter_name']); ?></td>
                            <td><code><?php echo e($row['letter_code']); ?></code></td>
                            <td><?php echo number_format($row['sponsor_count']); ?></td>
                            <td class="text-success fw-bold"><?php echo number_format($row['active_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-user-tie me-2"></i> التوزيع حسب المشرف
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-supervisors">
                    <thead class="table-light">
                        <tr>
                            <th>المشرف</th>
                            <th>إجمالي الكفلاء</th>
                            <th>الكفلاء النشطين</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distribution_data['by_supervisor'] as $row): ?>
                        <tr>
                            <td><?php echo e($row['supervisor_name']); ?></td>
                            <td><?php echo number_format($row['sponsor_count']); ?></td>
                            <td class="text-success fw-bold"><?php echo number_format($row['active_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'status_summary'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-list-alt me-2"></i> ملخص حالة الكفالات
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>إجمالي الالتزام الشهري للكفالات النشطة:</strong> 
                    <span class="fw-bold fs-5"><?php echo number_format($total_commitment, 2); ?> ج.س</span>
                </div>
                
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-status">
                    <thead class="table-light">
                        <tr>
                            <th>الحالة</th>
                            <th>العدد</th>
                            <th>إجمالي الالتزام الشهري</th>
                            <th>النسبة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_sponsorships = array_sum(array_column($status_summary, 'count'));
                        $status_labels = [
                            'active' => ['نشطة', 'success'],
                            'paused' => ['متوقفة مؤقتاً', 'warning'],
                            'completed' => ['مكتملة', 'info'],
                            'cancelled' => ['ملغاة', 'danger']
                        ];
                        foreach ($status_summary as $row): 
                            [$label, $color] = $status_labels[$row['status']] ?? [$row['status'], 'secondary'];
                            $percent = $total_sponsorships > 0 ? round(($row['count'] / $total_sponsorships) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><span class="badge bg-<?php echo $color; ?>"><?php echo e($label); ?></span></td>
                            <td class="fw-bold"><?php echo number_format($row['count']); ?></td>
                            <td><?php echo number_format($row['total_monthly_commitment'] ?? 0, 2); ?> ج.س</td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percent; ?>%">
                                        <?php echo $percent; ?>%
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
    <?php endif; ?>
    
</div>

<!-- زر العودة -->
<div class="mt-4 text-center">
    <a href="<?php echo APP_URL; ?>modules/reports/index.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-right me-1"></i> العودة إلى مركز التقارير
    </a>
</div>

<script>
// رسم بياني لتغطية الأيتام (بحجم أصغر)
<?php if ($report_type === 'coverage'): ?>
const covCtx = document.getElementById('coverageChart').getContext('2d');
new Chart(covCtx, {
    type: 'doughnut',
    data: {
        labels: ['مكفولين', 'غير مكفولين'],
        datasets: [{
            data: [<?php echo $coverage_data['sponsored_children']; ?>, <?php echo $coverage_data['unsponsored_children']; ?>],
            backgroundColor: ['rgba(40, 167, 69, 0.8)', 'rgba(220, 53, 69, 0.8)'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } },
            title: { display: true, text: 'نسبة تغطية الأطفال بالكفالات', font: { size: 14 } }
        },
        cutout: '60%'
    }
});
<?php endif; ?>

// رسم بياني للاحتفاظ بالكفلاء (بحجم أصغر)
<?php if ($report_type === 'retention'): ?>
const retCtx = document.getElementById('retentionChart').getContext('2d');
new Chart(retCtx, {
    type: 'pie',
    data: {
        labels: ['نشط', 'ملغي', 'مكتمل'],
        datasets: [{
            data: [<?php echo $retention_data['active']; ?>, <?php echo $retention_data['cancelled']; ?>, <?php echo $retention_data['completed']; ?>],
            backgroundColor: ['rgba(40, 167, 69, 0.8)', 'rgba(220, 53, 69, 0.8)', 'rgba(23, 162, 184, 0.8)'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } },
            title: { display: true, text: 'توزيع الكفلاء حسب الحالة', font: { size: 14 } }
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
    XLSX.writeFile(wb, 'sponsorship_report_<?php echo date("Y-m-d"); ?>.xlsx');
}

// تصدير إلى PDF
function exportToPDF() {
    const element = document.getElementById('report-content');
    const opt = {
        margin: 0.5,
        filename: 'sponsorship_report_<?php echo date("Y-m-d"); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>