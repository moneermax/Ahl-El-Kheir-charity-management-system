<?php
// modules/reports/hr.php - HR Reports (Complete - All 4 Reports)
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
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'hr_manager', 'hr_staff'];
if (!in_array($role, $allowed_roles, true)) {
    $_SESSION['flash'][] = ['type' => 'error', 'message' => 'ليس لديك صلاحية الوصول لهذه الصفحة'];
    header('Location: ' . APP_URL . 'modules/reports/index.php');
    exit();
}

// معالجة فلاتر التاريخ
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));
$report_type = trim($_GET['report'] ?? 'headcount');

$pageTitle = 'تقارير الموارد البشرية';
$active = 'reports';

// ==================== التقرير 1: إحصائيات الموظفين (Headcount) ====================
$headcount_data = [];
if ($report_type === 'headcount') {
    $by_status_sql = "SELECT status, COUNT(*) as count FROM employees GROUP BY status ORDER BY count DESC";
    $headcount_data['by_status'] = dbFetchAll($by_status_sql);
    
    $by_type_sql = "SELECT employment_type, COUNT(*) as count FROM employees GROUP BY employment_type ORDER BY count DESC";
    $headcount_data['by_type'] = dbFetchAll($by_type_sql);
    
    $by_work_mode_sql = "SELECT work_mode, COUNT(*) as count FROM employees GROUP BY work_mode ORDER BY count DESC";
    $headcount_data['by_work_mode'] = dbFetchAll($by_work_mode_sql);
    
    $total_employees = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM employees")['c'] ?? 0);
    $active_employees = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM employees WHERE status = 'active'")['c'] ?? 0);
}

// ==================== التقرير 2: الحضور والانصراف ====================
$attendance_data = [];
if ($report_type === 'attendance') {
    $summary_sql = "SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_day,
        SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave,
        SUM(CASE WHEN status = 'remote_work' THEN 1 ELSE 0 END) as remote_work
        FROM attendance
        WHERE date BETWEEN '{$from}' AND '{$to}'";
    $attendance_data['summary'] = dbFetchOne($summary_sql);
    
    $by_employee_sql = "SELECT 
        e.full_name,
        e.employee_code,
        COUNT(a.id) as total_days,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN a.status = 'half_day' THEN 1 ELSE 0 END) as half_day,
        SUM(CASE WHEN a.status = 'on_leave' THEN 1 ELSE 0 END) as on_leave,
        SUM(CASE WHEN a.status = 'remote_work' THEN 1 ELSE 0 END) as remote_work
        FROM employees e
        LEFT JOIN attendance a ON e.id = a.employee_id AND a.date BETWEEN '{$from}' AND '{$to}'
        WHERE e.status = 'active'
        GROUP BY e.id, e.full_name, e.employee_code
        ORDER BY present DESC";
    $attendance_data['by_employee'] = dbFetchAll($by_employee_sql);
    
    $by_month_sql = "SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM attendance
        WHERE date BETWEEN '{$from}' AND '{$to}'
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month";
    $attendance_data['by_month'] = dbFetchAll($by_month_sql);
}

// ==================== التقرير 3: استهلاك الإجازات ====================
$leave_data = [];
if ($report_type === 'leaves') {
    $by_type_sql = "SELECT 
        leave_type,
        COUNT(*) as count,
        COALESCE(SUM(days_count), 0) as total_days,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'manager_approved' THEN 1 ELSE 0 END) as manager_approved,
        SUM(CASE WHEN status = 'hr_approved' THEN 1 ELSE 0 END) as hr_approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM leaves
        WHERE start_date BETWEEN '{$from}' AND '{$to}'
        GROUP BY leave_type
        ORDER BY count DESC";
    $leave_data['by_type'] = dbFetchAll($by_type_sql);
    
    $by_employee_sql = "SELECT 
        e.full_name,
        e.employee_code,
        COUNT(l.id) as total_leaves,
        COALESCE(SUM(l.days_count), 0) as total_days,
        SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN l.status IN ('manager_approved', 'hr_approved') THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN l.status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM employees e
        LEFT JOIN leaves l ON e.id = l.employee_id AND l.start_date BETWEEN '{$from}' AND '{$to}'
        WHERE e.status = 'active'
        GROUP BY e.id, e.full_name, e.employee_code
        ORDER BY total_days DESC";
    $leave_data['by_employee'] = dbFetchAll($by_employee_sql);
    
    $pending_sql = "SELECT 
        l.id,
        l.leave_type,
        l.start_date,
        l.end_date,
        l.days_count,
        l.reason,
        l.status,
        e.full_name as employee_name,
        e.employee_code
        FROM leaves l
        JOIN employees e ON l.employee_id = e.id
        WHERE l.status IN ('pending', 'manager_approved')
        ORDER BY l.created_at DESC
        LIMIT 50";
    $leave_data['pending'] = dbFetchAll($pending_sql);
}

// ==================== التقرير 4: ملخص الرواتب ====================
$payroll_data = [];
if ($report_type === 'payroll') {
    $by_month_sql = "SELECT 
        p.year,
        p.month,
        COUNT(*) as employee_count,
        COALESCE(SUM(p.basic_salary), 0) as total_basic,
        COALESCE(SUM(p.allowances), 0) as total_allowances,
        COALESCE(SUM(p.deductions), 0) as total_deductions,
        COALESCE(SUM(p.overtime), 0) as total_overtime,
        COALESCE(SUM(p.net_salary), 0) as total_net,
        SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) as paid_count
        FROM payroll p
        WHERE (p.year > YEAR('{$from}') OR (p.year = YEAR('{$from}') AND p.month >= MONTH('{$from}')))
        AND (p.year < YEAR('{$to}') OR (p.year = YEAR('{$to}') AND p.month <= MONTH('{$to}')))
        GROUP BY p.year, p.month
        ORDER BY p.year DESC, p.month DESC";
    $payroll_data['by_month'] = dbFetchAll($by_month_sql);
    
    $by_employee_sql = "SELECT 
        e.full_name,
        e.employee_code,
        p.year,
        p.month,
        p.basic_salary,
        p.allowances,
        p.deductions,
        p.overtime,
        p.net_salary,
        p.status,
        p.payment_date
        FROM payroll p
        JOIN employees e ON p.employee_id = e.id
        WHERE (p.year > YEAR('{$from}') OR (p.year = YEAR('{$from}') AND p.month >= MONTH('{$from}')))
        AND (p.year < YEAR('{$to}') OR (p.year = YEAR('{$to}') AND p.month <= MONTH('{$to}')))
        ORDER BY p.year DESC, p.month DESC, e.full_name";
    $payroll_data['by_employee'] = dbFetchAll($by_employee_sql);
    
    $total_paid = (float)(dbFetchOne("SELECT COALESCE(SUM(net_salary), 0) AS s FROM payroll WHERE status = 'paid'")['s'] ?? 0);
    $total_pending = (float)(dbFetchOne("SELECT COALESCE(SUM(net_salary), 0) AS s FROM payroll WHERE status IN ('draft', 'approved')")['s'] ?? 0);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-users-cog me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">تقارير الموارد البشرية — <?php echo e($from); ?> → <?php echo e($to); ?></p>
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
        'headcount' => 'إحصائيات الموظفين',
        'attendance' => 'الحضور والانصراف',
        'leaves' => 'استهلاك الإجازات',
        'payroll' => 'ملخص الرواتب'
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
    
    <?php if ($report_type === 'headcount'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-chart-pie me-2"></i> إحصائيات الموظفين
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي الموظفين</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($total_employees); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">الموظفين النشطين</h6>
                                <div class="display-6 fw-bold text-success"><?php echo number_format($active_employees); ?></div>
                                <small class="text-success fw-bold">
                                    <?php echo $total_employees > 0 ? round(($active_employees / $total_employees) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-4">
                        <h6 class="fw-bold mb-3">حسب الحالة</h6>
                        <canvas id="statusChart" style="max-height: 250px;"></canvas>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold mb-3">حسب نوع التوظيف</h6>
                        <canvas id="typeChart" style="max-height: 250px;"></canvas>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold mb-3">حسب وضع العمل</h6>
                        <canvas id="workModeChart" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'attendance'): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-calendar-check me-2"></i> ملخص الحضور في الفترة
            </div>
            <div class="card-body">
                <?php $as = $attendance_data['summary'] ?? []; ?>
                <div class="row g-4 mb-4">
                    <div class="col-md-2">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي السجلات</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format((int)($as['total_records'] ?? 0)); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">حضور</h6>
                                <div class="display-6 fw-bold text-success"><?php echo number_format((int)($as['present'] ?? 0)); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">غياب</h6>
                                <div class="display-6 fw-bold text-danger"><?php echo number_format((int)($as['absent'] ?? 0)); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">تأخير</h6>
                                <div class="display-6 fw-bold text-warning"><?php echo number_format((int)($as['late'] ?? 0)); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">نصف يوم</h6>
                                <div class="display-6 fw-bold text-info"><?php echo number_format((int)($as['half_day'] ?? 0)); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">عمل عن بُعد</h6>
                                <div class="display-6 fw-bold" style="color: #6f42c1;"><?php echo number_format((int)($as['remote_work'] ?? 0)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <canvas id="attendanceChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-user-check me-2"></i> الحضور حسب الموظف
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-attendance">
                    <thead class="table-light">
                        <tr>
                            <th>الموظف</th>
                            <th>الكود</th>
                            <th>إجمالي الأيام</th>
                            <th>حضور</th>
                            <th>غياب</th>
                            <th>تأخير</th>
                            <th>نصف يوم</th>
                            <th>إجازة</th>
                            <th>عن بُعد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_data['by_employee'] as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['full_name']); ?></td>
                            <td><code><?php echo e($row['employee_code']); ?></code></td>
                            <td><?php echo number_format((int)($row['total_days'] ?? 0)); ?></td>
                            <td class="text-success"><?php echo number_format((int)($row['present'] ?? 0)); ?></td>
                            <td class="text-danger"><?php echo number_format((int)($row['absent'] ?? 0)); ?></td>
                            <td class="text-warning"><?php echo number_format((int)($row['late'] ?? 0)); ?></td>
                            <td class="text-info"><?php echo number_format((int)($row['half_day'] ?? 0)); ?></td>
                            <td><?php echo number_format((int)($row['on_leave'] ?? 0)); ?></td>
                            <td><?php echo number_format((int)($row['remote_work'] ?? 0)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'leaves'): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-umbrella-beach me-2"></i> ملخص الإجازات حسب النوع
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-leaves-type">
                    <thead class="table-light">
                        <tr>
                            <th>نوع الإجازة</th>
                            <th>العدد</th>
                            <th>إجمالي الأيام</th>
                            <th>قيد الانتظار</th>
                            <th>موافقة المدير</th>
                            <th>موافقة HR</th>
                            <th>مرفوضة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $leave_type_labels = [
                            'annual' => 'سنوية',
                            'sick' => 'مرضية',
                            'emergency' => 'طارئة',
                            'unpaid' => 'بدون راتب',
                            'remote_work_request' => 'عمل عن بُعد'
                        ];
                        foreach ($leave_data['by_type'] as $row): 
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($leave_type_labels[$row['leave_type']] ?? $row['leave_type']); ?></td>
                            <td><?php echo number_format((int)($row['count'] ?? 0)); ?></td>
                            <td><?php echo number_format((int)($row['total_days'] ?? 0)); ?> يوم</td>
                            <td><span class="badge bg-warning text-dark"><?php echo number_format((int)($row['pending'] ?? 0)); ?></span></td>
                            <td><span class="badge bg-info"><?php echo number_format((int)($row['manager_approved'] ?? 0)); ?></span></td>
                            <td><span class="badge bg-success"><?php echo number_format((int)($row['hr_approved'] ?? 0)); ?></span></td>
                            <td><span class="badge bg-danger"><?php echo number_format((int)($row['rejected'] ?? 0)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-user-clock me-2"></i> الإجازات حسب الموظف
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-leaves-employee">
                    <thead class="table-light">
                        <tr>
                            <th>الموظف</th>
                            <th>الكود</th>
                            <th>عدد الإجازات</th>
                            <th>إجمالي الأيام</th>
                            <th>قيد الانتظار</th>
                            <th>موافق عليها</th>
                            <th>مرفوضة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_data['by_employee'] as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['full_name']); ?></td>
                            <td><code><?php echo e($row['employee_code']); ?></code></td>
                            <td><?php echo number_format((int)($row['total_leaves'] ?? 0)); ?></td>
                            <td><?php echo number_format((int)($row['total_days'] ?? 0)); ?> يوم</td>
                            <td><span class="badge bg-warning text-dark"><?php echo number_format((int)($row['pending'] ?? 0)); ?></span></td>
                            <td><span class="badge bg-success"><?php echo number_format((int)($row['approved'] ?? 0)); ?></span></td>
                            <td><span class="badge bg-danger"><?php echo number_format((int)($row['rejected'] ?? 0)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <?php if (!empty($leave_data['pending'])): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-hourglass-half me-2"></i> الإجازات قيد الانتظار (آخر 50)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-pending-leaves">
                    <thead class="table-light">
                        <tr>
                            <th>الموظف</th>
                            <th>الكود</th>
                            <th>النوع</th>
                            <th>من تاريخ</th>
                            <th>إلى تاريخ</th>
                            <th>الأيام</th>
                            <th>الحالة</th>
                            <th>السبب</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_data['pending'] as $row): 
                            $status_badge = $row['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-info';
                            $status_label = $row['status'] === 'pending' ? 'بانتظار المدير' : 'بانتظار HR';
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['employee_name']); ?></td>
                            <td><code><?php echo e($row['employee_code']); ?></code></td>
                            <td><?php echo e($leave_type_labels[$row['leave_type']] ?? $row['leave_type']); ?></td>
                            <td><?php echo e($row['start_date']); ?></td>
                            <td><?php echo e($row['end_date']); ?></td>
                            <td><?php echo number_format((int)($row['days_count'] ?? 0)); ?></td>
                            <td><span class="badge <?php echo $status_badge; ?>"><?php echo e($status_label); ?></span></td>
                            <td><?php echo e($row['reason'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($report_type === 'payroll'): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-money-bill-wave me-2"></i> ملخص الرواتب
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">إجمالي الرواتب المدفوعة</h6>
                                <div class="display-6 fw-bold text-success"><?php echo number_format($total_paid, 0); ?> <span class="fs-6">ج.س</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">رواتب قيد المعالجة</h6>
                                <div class="display-6 fw-bold text-warning"><?php echo number_format($total_pending, 0); ?> <span class="fs-6">ج.س</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">الإجمالي الكلي</h6>
                                <div class="display-6 fw-bold text-primary"><?php echo number_format($total_paid + $total_pending, 0); ?> <span class="fs-6">ج.س</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-payroll-month">
                    <thead class="table-light">
                        <tr>
                            <th>السنة</th>
                            <th>الشهر</th>
                            <th>عدد الموظفين</th>
                            <th>الرواتب الأساسية</th>
                            <th>البدلات</th>
                            <th>الخصومات</th>
                            <th>الإضافي</th>
                            <th>صافي الرواتب</th>
                            <th>مسودة</th>
                            <th>معتمدة</th>
                            <th>مدفوعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payroll_data['by_month'] as $row): ?>
                        <tr>
                            <td><?php echo e($row['year']); ?></td>
                            <td><?php echo e($row['month']); ?></td>
                            <td><?php echo number_format((int)($row['employee_count'] ?? 0)); ?></td>
                            <td><?php echo number_format((float)($row['total_basic'] ?? 0), 2); ?></td>
                            <td class="text-success"><?php echo number_format((float)($row['total_allowances'] ?? 0), 2); ?></td>
                            <td class="text-danger"><?php echo number_format((float)($row['total_deductions'] ?? 0), 2); ?></td>
                            <td class="text-info"><?php echo number_format((float)($row['total_overtime'] ?? 0), 2); ?></td>
                            <td class="fw-bold"><?php echo number_format((float)($row['total_net'] ?? 0), 2); ?> ج.س</td>
                            <td><span class="badge bg-secondary"><?php echo number_format((int)($row['draft_count'] ?? 0)); ?></span></td>
                            <td><span class="badge bg-info"><?php echo number_format((int)($row['approved_count'] ?? 0)); ?></span></td>
                            <td><span class="badge bg-success"><?php echo number_format((int)($row['paid_count'] ?? 0)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
                <i class="fas fa-user-tie me-2"></i> تفاصيل الرواتب حسب الموظف
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table-payroll-employee">
                    <thead class="table-light">
                        <tr>
                            <th>الموظف</th>
                            <th>الكود</th>
                            <th>السنة</th>
                            <th>الشهر</th>
                            <th>الراتب الأساسي</th>
                            <th>البدلات</th>
                            <th>الخصومات</th>
                            <th>الإضافي</th>
                            <th>الصافي</th>
                            <th>الحالة</th>
                            <th>تاريخ الدفع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $payroll_status_labels = [
                            'draft' => ['مسودة', 'bg-secondary'],
                            'approved' => ['معتمدة', 'bg-info'],
                            'paid' => ['مدفوعة', 'bg-success']
                        ];
                        foreach ($payroll_data['by_employee'] as $row): 
                            [$status_label, $status_class] = $payroll_status_labels[$row['status']] ?? [$row['status'], 'bg-secondary'];
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($row['full_name']); ?></td>
                            <td><code><?php echo e($row['employee_code']); ?></code></td>
                            <td><?php echo e($row['year']); ?></td>
                            <td><?php echo e($row['month']); ?></td>
                            <td><?php echo number_format((float)($row['basic_salary'] ?? 0), 2); ?></td>
                            <td class="text-success"><?php echo number_format((float)($row['allowances'] ?? 0), 2); ?></td>
                            <td class="text-danger"><?php echo number_format((float)($row['deductions'] ?? 0), 2); ?></td>
                            <td class="text-info"><?php echo number_format((float)($row['overtime'] ?? 0), 2); ?></td>
                            <td class="fw-bold"><?php echo number_format((float)($row['net_salary'] ?? 0), 2); ?> ج.س</td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo e($status_label); ?></span></td>
                            <td><?php echo e($row['payment_date'] ?? '-'); ?></td>
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
// رسم بياني لإحصائيات الموظفين
<?php if ($report_type === 'headcount'): ?>
const statusLabels = <?php echo json_encode(array_map(function($r) {
    $labels = ['active' => 'نشط', 'on_leave' => 'في إجازة', 'terminated' => 'منتهي', 'suspended' => 'موقوف'];
    return $labels[$r['status']] ?? $r['status'];
}, $headcount_data['by_status'])); ?>;
const statusData = <?php echo json_encode(array_map(function($r) { return (int)($r['count'] ?? 0); }, $headcount_data['by_status'])); ?>;
new Chart(document.getElementById('statusChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{ data: statusData, backgroundColor: ['rgba(40,167,69,0.8)', 'rgba(255,193,7,0.8)', 'rgba(220,53,69,0.8)', 'rgba(108,117,125,0.8)'] }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
});

const typeLabels = <?php echo json_encode(array_map(function($r) {
    $labels = ['full_time' => 'دوام كامل', 'part_time' => 'دوام جزئي', 'contract' => 'عقد', 'volunteer' => 'متطوع'];
    return $labels[$r['employment_type']] ?? $r['employment_type'];
}, $headcount_data['by_type'])); ?>;
const typeData = <?php echo json_encode(array_map(function($r) { return (int)($r['count'] ?? 0); }, $headcount_data['by_type'])); ?>;
new Chart(document.getElementById('typeChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{ data: typeData, backgroundColor: ['rgba(27,77,143,0.8)', 'rgba(23,162,184,0.8)', 'rgba(111,66,193,0.8)', 'rgba(253,126,20,0.8)'] }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
});

const workModeLabels = <?php echo json_encode(array_map(function($r) {
    $labels = ['remote' => 'عن بُعد', 'onsite' => 'في الموقع', 'hybrid' => 'هجين'];
    return $labels[$r['work_mode']] ?? $r['work_mode'];
}, $headcount_data['by_work_mode'])); ?>;
const workModeData = <?php echo json_encode(array_map(function($r) { return (int)($r['count'] ?? 0); }, $headcount_data['by_work_mode'])); ?>;
new Chart(document.getElementById('workModeChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: workModeLabels,
        datasets: [{ data: workModeData, backgroundColor: ['rgba(40,167,69,0.8)', 'rgba(220,53,69,0.8)', 'rgba(255,193,7,0.8)'] }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
});
<?php endif; ?>

// رسم بياني للحضور
<?php if ($report_type === 'attendance' && !empty($attendance_data['by_month'])): ?>
const attCtx = document.getElementById('attendanceChart').getContext('2d');
new Chart(attCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($attendance_data['by_month'], 'month')); ?>,
        datasets: [
            { label: 'حضور', data: <?php echo json_encode(array_map(function($r) { return (int)($r['present'] ?? 0); }, $attendance_data['by_month'])); ?>, backgroundColor: 'rgba(40,167,69,0.7)' },
            { label: 'غياب', data: <?php echo json_encode(array_map(function($r) { return (int)($r['absent'] ?? 0); }, $attendance_data['by_month'])); ?>, backgroundColor: 'rgba(220,53,69,0.7)' },
            { label: 'تأخير', data: <?php echo json_encode(array_map(function($r) { return (int)($r['late'] ?? 0); }, $attendance_data['by_month'])); ?>, backgroundColor: 'rgba(255,193,7,0.7)' }
        ]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
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
    XLSX.writeFile(wb, 'hr_report_<?php echo date("Y-m-d"); ?>.xlsx');
}

// تصدير إلى PDF
function exportToPDF() {
    const element = document.getElementById('report-content');
    const opt = {
        margin: 0.5,
        filename: 'hr_report_<?php echo date("Y-m-d"); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>