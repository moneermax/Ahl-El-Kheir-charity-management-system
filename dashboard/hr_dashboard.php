<<<<<<< HEAD
<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

Session::start();

$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'لوحة موارد البشرية';
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
    $table_check = dbFetchOne("SHOW TABLES LIKE 'employees'", []);
    if ($table_check) {
        $stats['total_employees'] = dbFetchOne("SELECT COUNT(*) as c FROM employees WHERE status = 'active'", [])['c'] ?? 0;
        $stats['pending_leaves'] = dbFetchOne("SELECT COUNT(*) as c FROM leaves WHERE status IN ('pending', 'manager_approved')", [])['c'] ?? 0;
        $stats['expiring_contracts'] = dbFetchOne("SELECT COUNT(*) as c FROM contracts WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [])['c'] ?? 0;
        $stats['monthly_payroll'] = dbFetchOne("SELECT COALESCE(SUM(basic_salary), 0) as s FROM employees WHERE status = 'active'", [])['s'] ?? 0;

        $today_attendance = dbFetchAll("SELECT work_mode, COUNT(*) as count FROM attendance WHERE date = CURDATE() AND status IN ('present', 'remote_work', 'late') GROUP BY work_mode", []);
        if ($today_attendance) {
            foreach ($today_attendance as $row) {
                if ($row['work_mode'] === 'remote') $stats['remote_today'] = $row['count'];
                if ($row['work_mode'] === 'onsite') $stats['onsite_today'] = $row['count'];
            }
        }

        $stats['recent_leaves'] = dbFetchAll("SELECT l.id, e.full_name, l.leave_type, l.start_date, l.end_date, l.status FROM leaves l JOIN employees e ON l.employee_id = e.id WHERE l.status IN ('pending', 'manager_approved') ORDER BY l.created_at DESC LIMIT 5", []);
    }
} catch (Throwable $e) {
    // Silent fail
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fm-header { background: linear-gradient(135deg, #1b4d8f 0%, #2c5aa0 100%); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fm-header h1 { margin: 0; font-size: 1.8rem; }
.fm-header p { margin: 5px 0 0; opacity: 0.9; }
.fm-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 20px; overflow: hidden; }
.fm-card-head { background: #1b4d8f; color: #fff; padding: 12px 18px; font-weight: 700; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; }
.fm-card-body { padding: 20px; }
.stat-box { background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-top: 4px solid #1b4d8f; text-align: center; }
.stat-box.green { border-top-color: #28a745; }
.stat-box.red { border-top-color: #dc3545; }
.stat-box.amber { border-top-color: #ffc107; }
.stat-box.blue { border-top-color: #17a2b8; }
.stat-value { font-size: 1.6rem; font-weight: 700; color: #1b4d8f; margin: 8px 0; }
.stat-label { color: #666; font-size: 0.85rem; }
.stat-sub { color: #999; font-size: 0.75rem; margin-top: 4px; }
.grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
.grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
</style>

<div class="fm-header">
    <h1>مرحباً، <?php echo e(Session::getUserName() ?? 'مدير الموارد البشرية'); ?></h1>
    <p>نظرة شاملة على الموظفين والحضور والإجازات — <?php echo date('Y-m-d'); ?></p>
</div>

<div class="grid-4">
    <div class="stat-box">
        <div class="stat-label">إجمالي الموظفين النشطين</div>
        <div class="stat-value"><?php echo number_format((int)$stats['total_employees']); ?></div>
        <div class="stat-sub">موظف مسجل في النظام</div>
    </div>
    <div class="stat-box amber">
        <div class="stat-label">طلبات إجازة معلقة</div>
        <div class="stat-value"><?php echo number_format((int)$stats['pending_leaves']); ?></div>
        <div class="stat-sub">بانتظار الاعتماد</div>
    </div>
    <div class="stat-box red">
        <div class="stat-label">عقود تنتهي قريباً</div>
        <div class="stat-value"><?php echo number_format((int)$stats['expiring_contracts']); ?></div>
        <div class="stat-sub">خلال 30 يوم</div>
    </div>
    <div class="stat-box green">
        <div class="stat-label">إجمالي الرواتب الشهرية</div>
        <div class="stat-value"><?php echo number_format((float)$stats['monthly_payroll'], 0); ?></div>
        <div class="stat-sub">جنيه سوداني</div>
    </div>
</div>

<div class="grid-2">
    <div class="fm-card">
        <div class="fm-card-head"><span>💻 حالة العمل اليوم</span></div>
        <div class="fm-card-body">
            <div class="grid-2" style="gap: 10px;">
                <div class="stat-box blue" style="margin-bottom:0;">
                    <div class="stat-value"><?php echo (int)$stats['remote_today']; ?></div>
                    <div class="stat-label">يعملون عن بُعد</div>
                </div>
                <div class="stat-box green" style="margin-bottom:0;">
                    <div class="stat-value"><?php echo (int)$stats['onsite_today']; ?></div>
                    <div class="stat-label">في المقر</div>
                </div>
            </div>
        </div>
    </div>

    <div class="fm-card">
        <div class="fm-card-head">
            <span> آخر طلبات الإجازة المعلقة</span>
            <a href="<?php echo APP_URL; ?>modules/hr/leaves.php" class="btn btn-sm btn-outline-light">عرض الكل</a>
        </div>
        <div class="fm-card-body">
            <?php if (empty($stats['recent_leaves'])): ?>
                <div class="text-center py-4 text-muted">لا توجد طلبات معلقة حالياً.</div>
            <?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th>الموظف</th><th>النوع</th><th>من - إلى</th><th>الحالة</th></tr></thead>
                    <tbody>
                        <?php foreach ($stats['recent_leaves'] as $leave): ?>
                        <tr>
                            <td><strong><?php echo e($leave['full_name']); ?></strong></td>
                            <td><?php echo e($leave['leave_type']); ?></td>
                            <td><small><?php echo e($leave['start_date']); ?> إلى <?php echo e($leave['end_date']); ?></small></td>
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
            <?php endif; ?>
        </div>
    </div>
</div>

=======
<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

Session::start();

$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'لوحة موارد البشرية';
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
    $table_check = dbFetchOne("SHOW TABLES LIKE 'employees'", []);
    if ($table_check) {
        $stats['total_employees'] = dbFetchOne("SELECT COUNT(*) as c FROM employees WHERE status = 'active'", [])['c'] ?? 0;
        $stats['pending_leaves'] = dbFetchOne("SELECT COUNT(*) as c FROM leaves WHERE status IN ('pending', 'manager_approved')", [])['c'] ?? 0;
        $stats['expiring_contracts'] = dbFetchOne("SELECT COUNT(*) as c FROM contracts WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [])['c'] ?? 0;
        $stats['monthly_payroll'] = dbFetchOne("SELECT COALESCE(SUM(basic_salary), 0) as s FROM employees WHERE status = 'active'", [])['s'] ?? 0;

        $today_attendance = dbFetchAll("SELECT work_mode, COUNT(*) as count FROM attendance WHERE date = CURDATE() AND status IN ('present', 'remote_work', 'late') GROUP BY work_mode", []);
        if ($today_attendance) {
            foreach ($today_attendance as $row) {
                if ($row['work_mode'] === 'remote') $stats['remote_today'] = $row['count'];
                if ($row['work_mode'] === 'onsite') $stats['onsite_today'] = $row['count'];
            }
        }

        $stats['recent_leaves'] = dbFetchAll("SELECT l.id, e.full_name, l.leave_type, l.start_date, l.end_date, l.status FROM leaves l JOIN employees e ON l.employee_id = e.id WHERE l.status IN ('pending', 'manager_approved') ORDER BY l.created_at DESC LIMIT 5", []);
    }
} catch (Throwable $e) {
    // Silent fail
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fm-header { background: linear-gradient(135deg, #1b4d8f 0%, #2c5aa0 100%); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fm-header h1 { margin: 0; font-size: 1.8rem; }
.fm-header p { margin: 5px 0 0; opacity: 0.9; }
.fm-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 20px; overflow: hidden; }
.fm-card-head { background: #1b4d8f; color: #fff; padding: 12px 18px; font-weight: 700; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; }
.fm-card-body { padding: 20px; }
.stat-box { background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-top: 4px solid #1b4d8f; text-align: center; }
.stat-box.green { border-top-color: #28a745; }
.stat-box.red { border-top-color: #dc3545; }
.stat-box.amber { border-top-color: #ffc107; }
.stat-box.blue { border-top-color: #17a2b8; }
.stat-value { font-size: 1.6rem; font-weight: 700; color: #1b4d8f; margin: 8px 0; }
.stat-label { color: #666; font-size: 0.85rem; }
.stat-sub { color: #999; font-size: 0.75rem; margin-top: 4px; }
.grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
.grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
</style>

<div class="fm-header">
    <h1>مرحباً، <?php echo e(Session::getUserName() ?? 'مدير الموارد البشرية'); ?></h1>
    <p>نظرة شاملة على الموظفين والحضور والإجازات — <?php echo date('Y-m-d'); ?></p>
</div>

<div class="grid-4">
    <div class="stat-box">
        <div class="stat-label">إجمالي الموظفين النشطين</div>
        <div class="stat-value"><?php echo number_format((int)$stats['total_employees']); ?></div>
        <div class="stat-sub">موظف مسجل في النظام</div>
    </div>
    <div class="stat-box amber">
        <div class="stat-label">طلبات إجازة معلقة</div>
        <div class="stat-value"><?php echo number_format((int)$stats['pending_leaves']); ?></div>
        <div class="stat-sub">بانتظار الاعتماد</div>
    </div>
    <div class="stat-box red">
        <div class="stat-label">عقود تنتهي قريباً</div>
        <div class="stat-value"><?php echo number_format((int)$stats['expiring_contracts']); ?></div>
        <div class="stat-sub">خلال 30 يوم</div>
    </div>
    <div class="stat-box green">
        <div class="stat-label">إجمالي الرواتب الشهرية</div>
        <div class="stat-value"><?php echo number_format((float)$stats['monthly_payroll'], 0); ?></div>
        <div class="stat-sub">جنيه سوداني</div>
    </div>
</div>

<div class="grid-2">
    <div class="fm-card">
        <div class="fm-card-head"><span>💻 حالة العمل اليوم</span></div>
        <div class="fm-card-body">
            <div class="grid-2" style="gap: 10px;">
                <div class="stat-box blue" style="margin-bottom:0;">
                    <div class="stat-value"><?php echo (int)$stats['remote_today']; ?></div>
                    <div class="stat-label">يعملون عن بُعد</div>
                </div>
                <div class="stat-box green" style="margin-bottom:0;">
                    <div class="stat-value"><?php echo (int)$stats['onsite_today']; ?></div>
                    <div class="stat-label">في المقر</div>
                </div>
            </div>
        </div>
    </div>

    <div class="fm-card">
        <div class="fm-card-head">
            <span> آخر طلبات الإجازة المعلقة</span>
            <a href="<?php echo APP_URL; ?>modules/hr/leaves.php" class="btn btn-sm btn-outline-light">عرض الكل</a>
        </div>
        <div class="fm-card-body">
            <?php if (empty($stats['recent_leaves'])): ?>
                <div class="text-center py-4 text-muted">لا توجد طلبات معلقة حالياً.</div>
            <?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th>الموظف</th><th>النوع</th><th>من - إلى</th><th>الحالة</th></tr></thead>
                    <tbody>
                        <?php foreach ($stats['recent_leaves'] as $leave): ?>
                        <tr>
                            <td><strong><?php echo e($leave['full_name']); ?></strong></td>
                            <td><?php echo e($leave['leave_type']); ?></td>
                            <td><small><?php echo e($leave['start_date']); ?> إلى <?php echo e($leave['end_date']); ?></small></td>
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
            <?php endif; ?>
        </div>
    </div>
</div>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php require_once __DIR__ . '/../includes/footer.php'; ?>