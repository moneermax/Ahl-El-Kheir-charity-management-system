<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = t('hr.dashboard_title');
$active = 'hr';
$stats = ['total_employees'=>0,'pending_leaves'=>0,'expiring_contracts'=>0,'monthly_payroll'=>0,'remote_today'=>0,'onsite_today'=>0,'recent_leaves'=>[]];
try {
    if (dbFetchOne("SHOW TABLES LIKE 'employees'", [])) {
        $stats['total_employees'] = dbFetchOne("SELECT COUNT(*) c FROM employees WHERE status='active'", [])['c'] ?? 0;
        $stats['pending_leaves'] = dbFetchOne("SELECT COUNT(*) c FROM leaves WHERE status IN ('pending','manager_approved')", [])['c'] ?? 0;
        $stats['expiring_contracts'] = dbFetchOne("SELECT COUNT(*) c FROM hr_employee_contracts WHERE status='active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [])['c'] ?? 0;
        $stats['monthly_payroll'] = dbFetchOne("SELECT COALESCE(SUM(basic_salary),0) s FROM employees WHERE status='active'", [])['s'] ?? 0;
        foreach (dbFetchAll("SELECT work_mode, COUNT(*) count FROM attendance WHERE date=CURDATE() AND status IN ('present','remote_work','late') GROUP BY work_mode", []) as $row) {
            if ($row['work_mode'] === 'remote') $stats['remote_today'] = $row['count'];
            if ($row['work_mode'] === 'onsite') $stats['onsite_today'] = $row['count'];
        }
        $stats['recent_leaves'] = dbFetchAll("SELECT l.id,e.full_name,l.leave_type,l.start_date,l.end_date,l.status FROM leaves l JOIN employees e ON l.employee_id=e.id WHERE l.status IN ('pending','manager_approved') ORDER BY l.created_at DESC LIMIT 5", []);
    }
} catch (Throwable $e) {}
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.fm-header{background:linear-gradient(135deg,#1b4d8f 0%,#2c5aa0 100%);color:#fff;padding:25px;border-radius:12px;margin-bottom:20px}.fm-header h1{margin:0;font-size:1.8rem}.fm-header p{margin:5px 0 0;opacity:.9}.fm-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:20px;overflow:hidden}.fm-card-head{background:#1b4d8f;color:#fff;padding:12px 18px;font-weight:700;font-size:1rem;display:flex;justify-content:space-between;align-items:center}.fm-card-body{padding:20px}.stat-box{background:#fff;border-radius:10px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.05);border-top:4px solid #1b4d8f;text-align:center}.stat-box.green{border-top-color:#28a745}.stat-box.red{border-top-color:#dc3545}.stat-box.amber{border-top-color:#ffc107}.stat-box.blue{border-top-color:#17a2b8}.stat-value{font-size:1.6rem;font-weight:700;color:#1b4d8f;margin:8px 0}.stat-label{color:#666;font-size:.85rem}.stat-sub{color:#999;font-size:.75rem;margin-top:4px}.grid-4{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;margin-bottom:20px}.grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px}
.hr-dashboard-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}.hr-action-card{position:relative;display:flex;align-items:center;min-width:0;min-height:82px;padding:12px 14px;gap:10px;border:1px solid #e5e9ee;border-radius:10px;background:#fff;color:#212529;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.045);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.hr-action-card:hover{color:#212529;transform:translateY(-2px);border-color:#cbd5e1;box-shadow:0 5px 14px rgba(0,0,0,.08)}.hr-action-icon{width:40px;height:40px;min-width:40px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;background:#f4f6f8;font-size:16px}.hr-action-content{min-width:0;display:flex;flex-direction:column;line-height:1.25}.hr-action-title{font-size:.86rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.hr-action-subtitle{margin-top:3px;color:#6c757d;font-size:.69rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.hr-action-badge{position:absolute;top:7px;left:7px;min-width:21px}.hr-financial-tools{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;min-width:0;min-height:82px;padding:12px 14px;border:1px solid #e5e9ee;border-right:4px solid #dc3545;border-radius:10px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.045);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;margin:0}.hr-financial-tools:hover{border-color:#f0c4c8;box-shadow:0 5px 14px rgba(0,0,0,.08)}.hr-financial-tools .tool-title{display:flex;align-items:center;gap:10px;min-width:0;font-weight:800;color:#344054}.hr-financial-tools .tool-title-icon{width:40px;height:40px;min-width:40px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;background:#fff1f2;color:#dc3545}.hr-financial-tools .tool-title-text{min-width:0;display:flex;flex-direction:column;line-height:1.25}.hr-financial-tools .tool-subtitle{display:block;color:#6c757d;font-size:.69rem;font-weight:400;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.hr-financial-tools .tool-link{white-space:nowrap}.qa-top-bar .qa-actions{display:none !important}
@media (max-width:1399.98px){.hr-dashboard-actions{grid-template-columns:repeat(4,minmax(0,1fr))}.hr-financial-tools{flex-direction:column;align-items:stretch}.hr-financial-tools .d-flex{width:100%}.hr-financial-tools .tool-link{flex:1;text-align:center}}
@media (max-width:991.98px){.hr-dashboard-actions{grid-template-columns:repeat(2,minmax(0,1fr))}.hr-financial-tools{flex-direction:row;align-items:center}.hr-financial-tools .d-flex{width:auto}.hr-financial-tools .tool-link{flex:none}.grid-2{grid-template-columns:1fr}}
@media (max-width:767.98px){.hr-dashboard-actions{grid-template-columns:1fr 1fr}.hr-action-card{min-height:76px}.hr-financial-tools{flex-direction:column;align-items:stretch}.hr-financial-tools .d-flex{width:100%}.hr-financial-tools .tool-link{flex:1;text-align:center}}
@media (max-width:480px){.hr-dashboard-actions{grid-template-columns:1fr}.hr-action-card{min-height:68px}.hr-financial-tools .d-flex{flex-direction:column}.hr-financial-tools .tool-link{width:100%}}
</style>
<div class="fm-header"><h1><?php echo e(t('dashboard.welcome_user',['name'=>Session::getUserName() ?? t('hr.dashboard_title')])); ?></h1><p><?php echo e(t('hr.overview_today',['date'=>date('Y-m-d')])); ?></p></div>

<div class="hr-dashboard-actions">
<a href="<?php echo APP_URL; ?>modules/hr/employees.php" class="hr-action-card"><span class="hr-action-icon text-navy"><i class="fas fa-user-plus"></i></span><span class="hr-action-content"><span class="hr-action-title"><?php echo e(t('hr.add_employee')); ?></span><span class="hr-action-subtitle">إدارة بيانات الموظفين</span></span></a>
<a href="<?php echo APP_URL; ?>modules/hr/employment_states.php" class="hr-action-card"><span class="hr-action-icon text-primary"><i class="fas fa-id-badge"></i></span><span class="hr-action-content"><span class="hr-action-title">حالات التوظيف</span><span class="hr-action-subtitle">إدارة دورة حياة الموظف</span></span></a>
<a href="<?php echo APP_URL; ?>modules/hr/attendance.php" class="hr-action-card"><span class="hr-action-icon text-secondary"><i class="fas fa-clock"></i></span><span class="hr-action-content"><span class="hr-action-title"><?php echo e(t('hr.attendance')); ?></span><span class="hr-action-subtitle">متابعة الحضور والانصراف</span></span></a>
<a href="<?php echo APP_URL; ?>modules/hr/leaves.php" class="hr-action-card"><span class="hr-action-icon text-warning"><i class="fas fa-calendar-alt"></i></span><span class="hr-action-content"><span class="hr-action-title"><?php echo e(t('hr.leaves')); ?></span><span class="hr-action-subtitle">مراجعة طلبات الإجازات</span></span><?php if ($stats['pending_leaves'] > 0): ?><span class="badge bg-danger hr-action-badge"><?php echo (int)$stats['pending_leaves']; ?></span><?php endif; ?></a>
<a href="<?php echo APP_URL; ?>modules/hr/payroll.php" class="hr-action-card"><span class="hr-action-icon text-success"><i class="fas fa-money-bill-wave"></i></span><span class="hr-action-content"><span class="hr-action-title"><?php echo e(t('hr.payroll')); ?></span><span class="hr-action-subtitle">كشف ومتابعة الرواتب</span></span></a>
<a href="<?php echo APP_URL; ?>modules/hr/contracts.php" class="hr-action-card"><span class="hr-action-icon text-info"><i class="fas fa-file-contract"></i></span><span class="hr-action-content"><span class="hr-action-title"><?php echo e(t('hr.contracts')); ?></span><span class="hr-action-subtitle">إدارة عقود الموظفين</span></span></a>
<a href="<?php echo APP_URL; ?>modules/hr/payroll_policy.php" class="hr-action-card"><span class="hr-action-icon text-dark"><i class="fas fa-sliders-h"></i></span><span class="hr-action-content"><span class="hr-action-title">سياسات الرواتب</span><span class="hr-action-subtitle">إدارة قواعد وسياسات الرواتب</span></span></a>
<?php if (in_array($userRole, ['hr_manager', 'admin'], true)): ?>
<div class="hr-financial-tools">
    <div class="tool-title"><span class="tool-title-icon"><i class="fas fa-rotate-left"></i></span><span class="tool-title-text"><span>التصحيحات المالية للرواتب</span><span class="tool-subtitle">عكس القيود المحاسبية للمسيرات المصروفة</span></span></div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo APP_URL; ?>modules/hr/payroll_reversal.php" class="btn btn-outline-danger btn-sm tool-link"><i class="fas fa-rotate-left me-1"></i> عكس</a>
        <a href="<?php echo APP_URL; ?>modules/hr/payroll_integrity.php" class="btn btn-outline-primary btn-sm tool-link"><i class="fas fa-shield-halved me-1"></i> فحص</a>
    </div>
</div>
<?php endif; ?>
</div>

<div class="grid-4">
<div class="stat-box"><div class="stat-label"><?php echo e(t('hr.active_employees_total')); ?></div><div class="stat-value"><?php echo number_format((int)$stats['total_employees']); ?></div><div class="stat-sub"><?php echo e(t('hr.registered_employees')); ?></div></div>
<div class="stat-box amber"><div class="stat-label"><?php echo e(t('hr.pending_leave_requests')); ?></div><div class="stat-value"><?php echo number_format((int)$stats['pending_leaves']); ?></div><div class="stat-sub"><?php echo e(t('hr.awaiting_approval')); ?></div></div>
<div class="stat-box red"><div class="stat-label"><?php echo e(t('hr.expiring_contracts')); ?></div><div class="stat-value"><?php echo number_format((int)$stats['expiring_contracts']); ?></div><div class="stat-sub"><?php echo e(t('hr.within_30_days')); ?></div></div>
<div class="stat-box green"><div class="stat-label"><?php echo e(t('hr.monthly_payroll_total')); ?></div><div class="stat-value"><?php echo number_format((float)$stats['monthly_payroll'],0); ?></div><div class="stat-sub"><?php echo e(t('hr.currency_sdg')); ?></div></div>
</div>
<div class="grid-2">
<div class="fm-card"><div class="fm-card-head"><span><?php echo e(t('hr.today_work_status')); ?></span></div><div class="fm-card-body"><div class="grid-2" style="gap:10px"><div class="stat-box blue" style="margin-bottom:0"><div class="stat-value"><?php echo (int)$stats['remote_today']; ?></div><div class="stat-label"><?php echo e(t('hr.remote_workers')); ?></div></div><div class="stat-box green" style="margin-bottom:0"><div class="stat-value"><?php echo (int)$stats['onsite_today']; ?></div><div class="stat-label"><?php echo e(t('hr.onsite_workers')); ?></div></div></div></div></div>
<div class="fm-card"><div class="fm-card-head"><span><?php echo e(t('hr.latest_pending_leaves')); ?></span><a href="<?php echo APP_URL; ?>modules/hr/leaves.php" class="btn btn-sm btn-outline-light"><?php echo e(t('common.view')); ?></a></div><div class="fm-card-body"><?php if (empty($stats['recent_leaves'])): ?><div class="text-center py-4 text-muted"><?php echo e(t('hr.no_pending_leaves')); ?></div><?php else: ?><table class="table table-sm"><thead><tr><th><?php echo e(t('hr.employee')); ?></th><th><?php echo e(t('hr.leave_type')); ?></th><th><?php echo e(t('hr.date_range')); ?></th><th><?php echo e(t('common.status')); ?></th></tr></thead><tbody><?php foreach ($stats['recent_leaves'] as $leave): ?><tr><td><strong><?php echo e($leave['full_name']); ?></strong></td><td><?php echo e(t('hr.leave_type_'.$leave['leave_type'])); ?></td><td><small><?php echo e($leave['start_date']); ?> <?php echo e(t('common.to')); ?> <?php echo e($leave['end_date']); ?></small></td><td><?php if($leave['status']==='pending'): ?><span class="badge bg-warning text-dark"><?php echo e(t('hr.waiting_manager')); ?></span><?php else: ?><span class="badge bg-primary"><?php echo e(t('hr.waiting_hr')); ?></span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
