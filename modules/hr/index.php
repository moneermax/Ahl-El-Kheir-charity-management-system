<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = t('hr.dashboard_title');
$active = 'hr';

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
    $db_error = t('hr.db_warning');
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper" style="margin-right: 250px; padding: 20px; background-color: #f4f6f9; min-height: 100vh;">
    <div class="ak-card p-3 mb-3 d-flex align-items-center" style="border-right: 4px solid #1b4d8f; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <h5 class="mb-0 text-navy fw-bold"><i class="fas fa-users-cog me-2"></i> <?php echo e(t('hr.dashboard_title')); ?></h5>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo e($db_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo e(t('common.close')); ?>"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4 hr-dashboard-actions">
        <div class="col-12 col-sm-6 col-lg">
            <a href="employees.php" class="hr-action-card">
                <span class="hr-action-icon text-navy"><i class="fas fa-user-plus"></i></span>
                <span class="hr-action-content">
                    <span class="hr-action-title"><?php echo e(t('hr.add_employee')); ?></span>
                    <span class="hr-action-subtitle">إدارة بيانات الموظفين</span>
                </span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg">
            <a href="attendance.php" class="hr-action-card">
                <span class="hr-action-icon text-secondary"><i class="fas fa-clock"></i></span>
                <span class="hr-action-content">
                    <span class="hr-action-title"><?php echo e(t('hr.attendance')); ?></span>
                    <span class="hr-action-subtitle">متابعة الحضور والانصراف</span>
                </span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg">
            <a href="leaves.php" class="hr-action-card">
                <span class="hr-action-icon text-warning"><i class="fas fa-calendar-alt"></i></span>
                <span class="hr-action-content">
                    <span class="hr-action-title"><?php echo e(t('hr.leaves')); ?></span>
                    <span class="hr-action-subtitle">مراجعة طلبات الإجازات</span>
                </span>
                <?php if ($stats['pending_leaves'] > 0): ?>
                    <span class="badge bg-danger hr-action-badge"><?php echo (int)$stats['pending_leaves']; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg">
            <a href="payroll.php" class="hr-action-card">
                <span class="hr-action-icon text-success"><i class="fas fa-money-bill-wave"></i></span>
                <span class="hr-action-content">
                    <span class="hr-action-title"><?php echo e(t('hr.payroll')); ?></span>
                    <span class="hr-action-subtitle">كشف ومتابعة الرواتب</span>
                </span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg">
            <a href="contracts.php" class="hr-action-card">
                <span class="hr-action-icon text-info"><i class="fas fa-file-contract"></i></span>
                <span class="hr-action-content">
                    <span class="hr-action-title"><?php echo e(t('hr.contracts')); ?></span>
                    <span class="hr-action-subtitle">إدارة عقود الموظفين</span>
                </span>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="ak-card p-3 h-100" style="border-right: 4px solid #1b4d8f;"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1"><?php echo e(t('hr.active_employees_total')); ?></h6><h3 class="mb-0 fw-bold text-dark"><?php echo number_format((int)$stats['total_employees']); ?></h3></div><div class="text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #1b4d8f;"><i class="fas fa-users"></i></div></div></div></div>
        <div class="col-md-3"><div class="ak-card p-3 h-100" style="border-right: 4px solid #ffc107;"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1"><?php echo e(t('hr.pending_leave_requests')); ?></h6><h3 class="mb-0 fw-bold text-warning"><?php echo number_format((int)$stats['pending_leaves']); ?></h3></div><div class="text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #fff3cd;"><i class="fas fa-hourglass-half"></i></div></div></div></div>
        <div class="col-md-3"><div class="ak-card p-3 h-100" style="border-right: 4px solid #dc3545;"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1"><?php echo e(t('hr.expiring_contracts')); ?></h6><h3 class="mb-0 fw-bold text-danger"><?php echo number_format((int)$stats['expiring_contracts']); ?></h3></div><div class="text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #f8d7da;"><i class="fas fa-file-signature"></i></div></div></div></div>
        <div class="col-md-3"><div class="ak-card p-3 h-100" style="border-right: 4px solid #198754;"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1"><?php echo e(t('hr.monthly_payroll_total')); ?></h6><h4 class="mb-0 fw-bold text-success"><?php echo number_format((float)$stats['monthly_payroll'], 2); ?> <small class="fs-6"><?php echo e(t('hr.currency_sdg')); ?></small></h4></div><div class="text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #d1e7dd;"><i class="fas fa-coins"></i></div></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-md-6"><div class="ak-card p-4 h-100"><h5 class="mb-3 text-navy fw-bold"><i class="fas fa-laptop-house me-2"></i> <?php echo e(t('hr.today_work_status')); ?></h5><div class="d-flex justify-content-around text-center py-3"><div><h2 class="text-primary fw-bold mb-0"><?php echo (int)$stats['remote_today']; ?></h2><p class="text-muted mb-0"><?php echo e(t('hr.remote_workers')); ?></p></div><div class="vr mx-3"></div><div><h2 class="text-success fw-bold mb-0"><?php echo (int)$stats['onsite_today']; ?></h2><p class="text-muted mb-0"><?php echo e(t('hr.onsite_workers')); ?></p></div></div></div></div>
        <div class="col-md-6"><div class="ak-card p-4 h-100"><h5 class="mb-3 text-navy fw-bold"><i class="fas fa-bell me-2"></i> <?php echo e(t('hr.latest_pending_leaves')); ?></h5>
            <?php if (empty($stats['recent_leaves'])): ?><div class="text-center py-4 text-muted"><i class="fas fa-check-circle fa-2x mb-2 text-success"></i><p class="mb-0"><?php echo e(t('hr.no_pending_leaves')); ?></p></div>
            <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th><?php echo e(t('hr.employee')); ?></th><th><?php echo e(t('hr.leave_type')); ?></th><th><?php echo e(t('hr.date_range')); ?></th><th><?php echo e(t('common.status')); ?></th></tr></thead><tbody>
                <?php foreach ($stats['recent_leaves'] as $leave): ?><tr><td class="fw-bold"><?php echo e($leave['full_name']); ?></td><td><span class="badge bg-info text-dark"><?php echo e($leave['leave_type']); ?></span></td><td><small class="text-muted"><?php echo e($leave['start_date']); ?> → <?php echo e($leave['end_date']); ?></small></td><td><?php if($leave['status'] === 'pending'): ?><span class="badge bg-warning text-dark"><?php echo e(t('hr.waiting_manager')); ?></span><?php else: ?><span class="badge bg-primary"><?php echo e(t('hr.waiting_hr')); ?></span><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div></div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<style>
    .text-navy { color: #1b4d8f !important; }
    .ak-card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s; background: white; }
    .ak-card:hover { transform: translateY(-2px); }
    .hr-dashboard-actions { align-items: stretch; }
    .hr-dashboard-actions > div { display: flex; }
    .hr-action-card {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
        min-height: 78px;
        padding: 12px 14px;
        gap: 11px;
        border: 1px solid #e7ebef;
        border-radius: 10px;
        background: #fff;
        color: #212529;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.045);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .hr-action-card:hover {
        color: #212529;
        transform: translateY(-2px);
        border-color: #cfd8e3;
        box-shadow: 0 5px 14px rgba(0,0,0,0.08);
    }
    .hr-action-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f4f6f8;
        font-size: 17px;
    }
    .hr-action-content {
        min-width: 0;
        display: flex;
        flex-direction: column;
        line-height: 1.25;
    }
    .hr-action-title {
        font-size: 0.9rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .hr-action-subtitle {
        margin-top: 3px;
        color: #6c757d;
        font-size: 0.72rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .hr-action-badge {
        position: absolute;
        top: 8px;
        left: 8px;
        min-width: 22px;
    }
    @media (max-width: 991.98px) {
        .hr-action-card { min-height: 74px; }
    }
    @media (max-width: 768px) {
        .content-wrapper { margin-right: 0 !important; }
    }
</style>