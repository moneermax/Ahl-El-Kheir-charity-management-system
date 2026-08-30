<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

// 1. Security Check: Only HR Manager, HR Staff, or Admin can access this
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'لوحة موارد البشرية';
$active = 'hr';

// 2. Fetch Dashboard Statistics
$stats = [
    'total_employees' => 0,
    'pending_leaves' => 0,
    'expiring_contracts' => 0,
    'monthly_payroll' => 0,
    'remote_today' => 0,
    'onsite_today' => 0,
    'recent_leaves' => []
];

try {
    // Check if HR tables exist
    $table_check = $pdo->query("SHOW TABLES LIKE 'employees'")->fetch();
    
    if ($table_check) {
        $stats['total_employees'] = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn() ?: 0;
        $stats['pending_leaves'] = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status IN ('pending', 'manager_approved')")->fetchColumn() ?: 0;
        $stats['expiring_contracts'] = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
        $stats['monthly_payroll'] = $pdo->query("SELECT COALESCE(SUM(basic_salary), 0) FROM employees WHERE status = 'active'")->fetchColumn() ?: 0;

        $today_attendance = $pdo->query("SELECT work_mode, COUNT(*) as count FROM attendance WHERE date = CURDATE() AND status IN ('present', 'remote_work', 'late') GROUP BY work_mode")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($today_attendance as $row) {
            if ($row['work_mode'] === 'remote') $stats['remote_today'] = $row['count'];
            if ($row['work_mode'] === 'onsite') $stats['onsite_today'] = $row['count'];
        }

        $stats['recent_leaves'] = $pdo->query("SELECT l.id, e.full_name, l.leave_type, l.start_date, l.end_date, l.status FROM leaves l JOIN employees e ON l.employee_id = e.id WHERE l.status IN ('pending', 'manager_approved') ORDER BY l.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $db_error = "تنبيه: تعذر جلب بعض بيانات الموارد البشرية. تأكد من تنفيذ ملف SQL الخاص بوحدة الموارد البشرية.";
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Main Content Wrapper -->
<div class="content-wrapper" style="margin-right: 250px; padding: 20px; background-color: #f4f6f9; min-height: 100vh;">
    
    <!-- Sticky Quick Actions Bar -->
    <div class="ak-card p-3 mb-4 d-flex justify-content-between align-items-center flex-wrap" style="position: sticky; top: 0; z-index: 1020; border-right: 4px solid #1b4d8f; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <h5 class="mb-0 text-navy fw-bold"><i class="fas fa-users-cog me-2"></i> لوحة موارد البشرية</h5>
        <div class="d-flex gap-2 flex-wrap">
            <a href="employees.php" class="btn btn-navy btn-sm"><i class="fas fa-user-plus me-1"></i> إضافة موظف</a>
            <a href="attendance.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-clock me-1"></i> الحضور</a>
            <a href="leaves.php" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-calendar-alt me-1"></i> الإجازات 
                <?php if ($stats['pending_leaves'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?php echo (int)$stats['pending_leaves']; ?></span>
                <?php endif; ?>
            </a>
            <a href="payroll.php" class="btn btn-outline-success btn-sm"><i class="fas fa-money-bill-wave me-1"></i> الرواتب</a>
            <a href="contracts.php" class="btn btn-outline-info btn-sm"><i class="fas fa-file-contract me-1"></i> العقود</a>
        </div>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo e($db_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="ak-card p-3 h-100" style="border-right: 4px solid #1b4d8f;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">إجمالي الموظفين النشطين</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo number_format((int)$stats['total_employees']); ?></h3>
                    </div>
                    <div class="text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #1b4d8f;"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="ak-card p-3 h-100" style="border-right: 4px solid #ffc107;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">طلبات إجازة معلقة</h6>
                        <h3 class="mb-0 fw-bold text-warning"><?php echo number_format((int)$stats['pending_leaves']); ?></h3>
                    </div>
                    <div class="text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #fff3cd;"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="ak-card p-3 h-100" style="border-right: 4px solid #dc3545;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">عقود تنتهي قريباً (30 يوم)</h6>
                        <h3 class="mb-0 fw-bold text-danger"><?php echo number_format((int)$stats['expiring_contracts']); ?></h3>
                    </div>
                    <div class="text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #f8d7da;"><i class="fas fa-file-signature"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="ak-card p-3 h-100" style="border-right: 4px solid #198754;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">إجمالي الرواتب الشهرية</h6>
                        <h4 class="mb-0 fw-bold text-success"><?php echo number_format((float)$stats['monthly_payroll'], 2); ?> <small class="fs-6">ج.س</small></h4>
                    </div>
                    <div class="text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #d1e7dd;"><i class="fas fa-coins"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Remote vs Onsite & Recent Leaves -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="ak-card p-4 h-100">
                <h5 class="mb-3 text-navy fw-bold"><i class="fas fa-laptop-house me-2"></i> حالة العمل اليوم</h5>
                <div class="d-flex justify-content-around text-center py-3">
                    <div>
                        <h2 class="text-primary fw-bold mb-0"><?php echo (int)$stats['remote_today']; ?></h2>
                        <p class="text-muted mb-0">يعملون عن بُعد</p>
                    </div>
                    <div class="vr mx-3"></div>
                    <div>
                        <h2 class="text-success fw-bold mb-0"><?php echo (int)$stats['onsite_today']; ?></h2>
                        <p class="text-muted mb-0">في المقر</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="ak-card p-4 h-100">
                <h5 class="mb-3 text-navy fw-bold"><i class="fas fa-bell me-2"></i> آخر طلبات الإجازة المعلقة</h5>
                <?php if (empty($stats['recent_leaves'])): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                        <p class="mb-0">لا توجد طلبات إجازة معلقة حالياً.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>الموظف</th><th>النوع</th><th>من - إلى</th><th>الحالة</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['recent_leaves'] as $leave): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo e($leave['full_name']); ?></td>
                                    <td><span class="badge bg-info text-dark"><?php echo e($leave['leave_type']); ?></span></td>
                                    <td><small class="text-muted"><?php echo e($leave['start_date']); ?> إلى <?php echo e($leave['end_date']); ?></small></td>
                                    <td>
                                        <?php if($leave['status'] == 'pending'): ?>
                                            <span class="badge bg-warning text-dark">بانتظار المدير</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">بانتظار HR</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<!-- Custom CSS for Navy Theme consistency -->
<style>
    .text-navy { color: #1b4d8f !important; }
    .btn-navy { background-color: #1b4d8f; color: white; border: none; }
    .btn-navy:hover { background-color: #133a6d; color: white; }
    .ak-card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s; background: white; }
    .ak-card:hover { transform: translateY(-3px); }
    @media (max-width: 768px) { .content-wrapper { margin-right: 0 !important; } }
</style>