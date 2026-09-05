<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

$userRole = Session::getUserRole();
$isLoggedIn = Session::isLoggedIn();
$isRequestMode = ($_GET['action'] ?? '') === 'request';

if (!$isLoggedIn) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

if (!$isRequestMode && !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$message = '';
$msg_type = 'success';
$filter = $_GET['status'] ?? 'all';

$currentEmployee = dbFetchOne(
    "SELECT id, full_name FROM employees WHERE user_id = ? LIMIT 1",
    [Session::getUserID()]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'request') {
            if (!$currentEmployee) {
                throw new RuntimeException('لا يوجد سجل موظف مرتبط بحسابك في النظام. يرجى التواصل مع إدارة الموارد البشرية.');
            }

            $emp_id = (int)$currentEmployee['id'];
            $type = trim((string)($_POST['leave_type'] ?? ''));
            $start = trim((string)($_POST['start_date'] ?? ''));
            $end = trim((string)($_POST['end_date'] ?? ''));
            $reason = trim((string)($_POST['reason'] ?? ''));

            $allowedTypes = ['annual', 'sick', 'emergency', 'unpaid', 'remote_work_request'];
            if (!in_array($type, $allowedTypes, true)) {
                throw new InvalidArgumentException('نوع الإجازة غير صالح.');
            }
            if ($start === '' || $end === '') {
                throw new InvalidArgumentException('يرجى تحديد تاريخ بداية ونهاية الإجازة.');
            }

            $d1 = new DateTime($start);
            $d2 = new DateTime($end);
            if ($d2 < $d1) {
                throw new InvalidArgumentException('تاريخ نهاية الإجازة يجب أن يكون بعد تاريخ البداية أو مساوياً له.');
            }

            $days = $d1->diff($d2)->days + 1;

            // Prevent accidental double submission/replay of the same pending request.
            $duplicate = dbFetchOne(
                "SELECT id FROM leaves
                 WHERE employee_id = ?
                   AND leave_type = ?
                   AND start_date = ?
                   AND end_date = ?
                   AND status = 'pending'
                 ORDER BY id ASC
                 LIMIT 1",
                [$emp_id, $type, $start, $end]
            );

            if ($duplicate) {
                // The browser may replay the original POST after a refresh.
                // Treat the existing request as the successful submission and redirect to GET.
                header('Location: ' . APP_URL . 'modules/hr/leaves.php?action=request');
                exit();
            }

            $sql = "INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days_count, reason, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            db()->prepare($sql)->execute([$emp_id, $type, $start, $end, $days, $reason]);

            // POST/Redirect/GET prevents browser refresh from replaying the INSERT.
            header('Location: ' . APP_URL . 'modules/hr/leaves.php?action=request');
            exit();
        } elseif ($action === 'approve_manager') {
            $id = (int)$_POST['leave_id'];
            $sql = "UPDATE leaves SET status = 'manager_approved', manager_approved_by = ?, manager_approved_at = NOW() WHERE id = ? AND status = 'pending'";
            db()->prepare($sql)->execute([Session::getUserID(), $id]);
            $message = t('hr.leave_approved_manager');
        } elseif ($action === 'approve_hr') {
            $id = (int)$_POST['leave_id'];
            $leave = dbFetchOne("SELECT * FROM leaves WHERE id = ? AND status = 'manager_approved'", [$id]);
            if ($leave) {
                db()->prepare("UPDATE leaves SET status = 'hr_approved', hr_approved_by = ?, hr_approved_at = NOW() WHERE id = ?")->execute([Session::getUserID(), $id]);
                $start = new DateTime($leave['start_date']);
                $end = new DateTime($leave['end_date']);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                $stmt = db()->prepare("INSERT INTO attendance (employee_id, date, status, notes) VALUES (?, ?, 'on_leave', 'إجازة معتمدة') ON DUPLICATE KEY UPDATE status = 'on_leave'");
                foreach ($period as $date) {
                    $stmt->execute([$leave['employee_id'], $date->format('Y-m-d')]);
                }
                $message = t('hr.leave_approved_final');
            }
        } elseif ($action === 'reject') {
            $id = (int)$_POST['leave_id'];
            db()->prepare("UPDATE leaves SET status = 'rejected' WHERE id = ?")->execute([$id]);
            $message = t('hr.leave_rejected');
        }
    } catch (Exception $e) {
        $message = t('hr.error', ['message' => $e->getMessage()]);
        $msg_type = 'error';
    }
}

if ($isRequestMode) {
    $leaves = [];
    if ($currentEmployee) {
        $leaves = dbFetchAll(
            "SELECT l.*, e.full_name AS emp_name, d.name_ar AS dept_name,
                    u1.full_name AS mgr_name, u2.full_name AS hr_name
             FROM leaves l
             JOIN employees e ON l.employee_id = e.id
             LEFT JOIN departments d ON e.department_id = d.id
             LEFT JOIN users u1 ON l.manager_approved_by = u1.id
             LEFT JOIN users u2 ON l.hr_approved_by = u2.id
             WHERE l.employee_id = ?
             ORDER BY l.created_at DESC",
            [$currentEmployee['id']]
        );
    }

    $pageTitle = t('hr.leaves_title');
    require_once __DIR__ . '/../../includes/header.php';
    ?>

    <style>
    .fm-header { background: linear-gradient(135deg, #1b4d8f 0%, #2c5aa0 100%); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
    .fm-header h1 { margin: 0; font-size: 1.8rem; }.fm-header p { margin: 5px 0 0; opacity: .9 }.fm-card { background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:20px;overflow:hidden }.fm-card-head{background:#1b4d8f;color:#fff;padding:12px 18px;font-weight:700;font-size:1rem;display:flex;justify-content:space-between;align-items:center}.fm-card-body{padding:20px}.fm-table{width:100%;border-collapse:collapse}.fm-table th,.fm-table td{padding:10px 12px;border-bottom:1px solid #eee;text-align:right;font-size:.9rem}.fm-table th{background:#f8f9fa;font-weight:700;color:#1b4d8f}.fm-table tr:hover{background:#f8f9fa}.badge-fm{padding:4px 10px;border-radius:12px;font-size:.75rem;font-weight:700}.badge-amber{background:#fff3cd;color:#856404}.badge-blue{background:#d1ecf1;color:#0c5460}.badge-green{background:#d4edda;color:#155724}.badge-red{background:#f8d7da;color:#721c24}.badge-gray{background:#e9ecef;color:#6c757d}.btn-fm{display:inline-block;padding:6px 12px;border-radius:6px;border:none;cursor:pointer;font-weight:700;text-decoration:none;font-size:.8rem;margin:2px}.btn-navy{background:#1b4d8f;color:#fff}.btn-success{background:#28a745;color:#fff}.btn-danger{background:#dc3545;color:#fff}
    </style>

    <div class="fm-header"><h1><i class="fas fa-calendar-plus me-2"></i> طلب إجازة</h1><p>تقديم ومتابعة طلبات الإجازة الخاصة بك.</p></div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius:8px;"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if (!$currentEmployee): ?>
        <div class="alert alert-warning">لا يوجد سجل موظف مرتبط بحسابك في النظام. يرجى التواصل مع إدارة الموارد البشرية قبل تقديم طلب إجازة.</div>
    <?php else: ?>
        <div class="fm-card">
            <div class="fm-card-head"><span><i class="fas fa-file-signature me-2"></i>طلب إجازة جديد</span><span><?php echo e($currentEmployee['full_name']); ?></span></div>
            <div class="fm-card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="request">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-bold">نوع الإجازة</label><select name="leave_type" class="form-select" required><option value="">اختر نوع الإجازة</option><option value="annual">إجازة سنوية</option><option value="sick">إجازة مرضية</option><option value="emergency">إجازة طارئة</option><option value="unpaid">إجازة بدون راتب</option><option value="remote_work_request">طلب عمل عن بُعد</option></select></div>
                        <div class="col-md-3"><label class="form-label fw-bold">من</label><input type="date" name="start_date" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label fw-bold">إلى</label><input type="date" name="end_date" class="form-control" required></div>
                        <div class="col-12"><label class="form-label fw-bold">السبب / الملاحظات</label><textarea name="reason" class="form-control" rows="4" maxlength="1000" placeholder="اكتب سبب طلب الإجازة إن وجد"></textarea></div>
                        <div class="col-12 text-end"><button type="submit" class="btn btn-primary px-4"><i class="fas fa-paper-plane me-1"></i> إرسال طلب الإجازة</button></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="fm-card">
            <div class="fm-card-head"><span><i class="fas fa-clock-rotate-left me-2"></i>طلباتي السابقة</span><span class="badge-fm badge-blue"><?php echo count($leaves); ?></span></div>
            <div class="fm-card-body"><div class="table-responsive"><table class="fm-table"><thead><tr><th>نوع الإجازة</th><th>الفترة</th><th>الأيام</th><th>الحالة</th></tr></thead><tbody>
            <?php if (empty($leaves)): ?>
                <tr><td colspan="4" class="text-center py-4 text-muted">لا توجد طلبات إجازة سابقة.</td></tr>
            <?php else: foreach ($leaves as $l): ?>
                <?php
                $types = ['annual'=>'إجازة سنوية','sick'=>'إجازة مرضية','emergency'=>'إجازة طارئة','unpaid'=>'إجازة بدون راتب','remote_work_request'=>'عمل عن بُعد'];
                $statusMap = ['pending'=>['badge-amber','قيد المراجعة'],'manager_approved'=>['badge-blue','موافقة المدير'],'hr_approved'=>['badge-green','معتمدة'],'rejected'=>['badge-red','مرفوضة']];
                $status = $statusMap[$l['status']] ?? ['badge-gray',(string)$l['status']];
                ?>
                <tr><td><?php echo e($types[$l['leave_type']] ?? $l['leave_type']); ?></td><td><?php echo e($l['start_date']); ?> → <?php echo e($l['end_date']); ?></td><td><?php echo e((string)$l['days_count']); ?></td><td><span class="badge-fm <?php echo e($status[0]); ?>"><?php echo e($status[1]); ?></span></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div></div>
        </div>
    <?php endif; ?>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    <?php exit();
}

$employees = dbFetchAll("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name", []);
$sql = "SELECT l.*, e.full_name as emp_name, d.name_ar as dept_name, u1.full_name as mgr_name, u2.full_name as hr_name FROM leaves l JOIN employees e ON l.employee_id = e.id LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN users u1 ON l.manager_approved_by = u1.id LEFT JOIN users u2 ON l.hr_approved_by = u2.id";
if ($filter !== 'all') {
    $sql .= " WHERE l.status = ?";
    $leaves = dbFetchAll($sql, [$filter]);
} else {
    $sql .= " ORDER BY l.created_at DESC";
    $leaves = dbFetchAll($sql, []);
}

$pageTitle = t('hr.leaves_title');
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.fm-header { background: linear-gradient(135deg, #1b4d8f 0%, #2c5aa0 100%); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fm-header h1 { margin: 0; font-size: 1.8rem; }.fm-header p { margin: 5px 0 0; opacity: .9 }.fm-card { background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:20px;overflow:hidden }.fm-card-head{background:#1b4d8f;color:#fff;padding:12px 18px;font-weight:700;font-size:1rem;display:flex;justify-content:space-between;align-items:center}.fm-card-body{padding:20px}.fm-table{width:100%;border-collapse:collapse}.fm-table th,.fm-table td{padding:10px 12px;border-bottom:1px solid #eee;text-align:right;font-size:.9rem}.fm-table th{background:#f8f9fa;font-weight:700;color:#1b4d8f}.fm-table tr:hover{background:#f8f9fa}.badge-fm{padding:4px 10px;border-radius:12px;font-size:.75rem;font-weight:700}.badge-amber{background:#fff3cd;color:#856404}.badge-blue{background:#d1ecf1;color:#0c5460}.badge-green{background:#d4edda;color:#155724}.badge-red{background:#f8d7da;color:#721c24}.badge-gray{background:#e9ecef;color:#6c757d}.btn-fm{display:inline-block;padding:6px 12px;border-radius:6px;border:none;cursor:pointer;font-weight:700;text-decoration:none;font-size:.8rem;margin:2px}.btn-navy{background:#1b4d8f;color:#fff}.btn-success{background:#28a745;color:#fff}.btn-danger{background:#dc3545;color:#fff}.tabs{display:flex;gap:5px;margin-bottom:20px;border-bottom:2px solid #eee;padding-bottom:10px}.tab{padding:8px 16px;border-radius:6px 6px 0 0;text-decoration:none;color:#666;font-weight:600}.tab.active{background:#1b4d8f;color:#fff}
</style>

<div class="fm-header"><h1><i class="fas fa-calendar-alt me-2"></i> <?php echo e(t('hr.leaves_title')); ?></h1><p><?php echo e(t('hr.leaves_intro')); ?></p></div>
<?php if ($message): ?><div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius:8px;"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="tabs">
<a href="?status=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>"><?php echo e(t('hr.all')); ?></a>
<a href="?status=pending" class="tab <?php echo $filter === 'pending' ? 'active' : ''; ?>"><?php echo e(t('hr.pending_manager')); ?></a>
<a href="?status=manager_approved" class="tab <?php echo $filter === 'manager_approved' ? 'active' : ''; ?>"><?php echo e(t('hr.pending_hr')); ?></a>
<a href="?status=hr_approved" class="tab <?php echo $filter === 'hr_approved' ? 'active' : ''; ?>"><?php echo e(t('hr.final_approved')); ?></a>
<a href="?status=rejected" class="tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>"><?php echo e(t('hr.rejected')); ?></a>
</div>

<div class="fm-card"><div class="fm-card-head"><span>📋 <?php echo e(t('hr.request_records')); ?></span><span class="badge-fm badge-blue"><?php echo e(t('hr.request_count', ['count' => count($leaves)])); ?></span></div><div class="fm-card-body"><div class="table-responsive"><table class="fm-table"><thead><tr><th><?php echo e(t('hr.employee')); ?></th><th><?php echo e(t('hr.leave_type')); ?></th><th><?php echo e(t('hr.date_range')); ?></th><th><?php echo e(t('hr.days')); ?></th><th><?php echo e(t('common.status')); ?></th><th class="text-end"><?php echo e(t('common.actions')); ?></th></tr></thead><tbody>
<?php if (empty($leaves)): ?><tr><td colspan="6" class="text-center py-4 text-muted"><?php echo e(t('hr.no_requests_category')); ?></td></tr><?php else: foreach ($leaves as $l): ?>
<tr><td><strong><?php echo htmlspecialchars($l['emp_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($l['dept_name'] ?? ''); ?></small></td>
<td><?php $types=['annual'=>'hr.leave_type_short_annual','sick'=>'hr.leave_type_short_sick','emergency'=>'hr.leave_type_short_emergency','unpaid'=>'hr.leave_type_short_unpaid','remote_work_request'=>'hr.leave_type_short_remote']; echo e(t($types[$l['leave_type']] ?? 'hr.leave_type')); ?></td>
<td><small><?php echo $l['start_date']; ?><br><?php echo e(t('common.to')); ?><br><?php echo $l['end_date']; ?></small></td><td><?php echo $l['days_count']; ?></td>
<td><?php $status_map=['pending'=>['badge-amber','hr.pending_manager'],'manager_approved'=>['badge-blue','hr.pending_hr'],'hr_approved'=>['badge-green','hr.final_approved'],'rejected'=>['badge-red','hr.rejected']]; $cls=$status_map[$l['status']][0]??'badge-gray'; $lbl=$status_map[$l['status']][1]??null; ?><span class="badge-fm <?php echo $cls; ?>"><?php echo $lbl ? e(t($lbl)) : e((string)$l['status']); ?></span></td>
<td class="text-end"><?php if($l['status']==='pending'): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_manager"><input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>"><button class="btn-fm btn-navy"><?php echo e(t('hr.initial_approve')); ?></button></form><?php elseif($l['status']==='manager_approved'): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_hr"><input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>"><button class="btn-fm btn-success"><?php echo e(t('hr.final_approve')); ?></button></form><?php endif; ?><?php if($l['status']!=='hr_approved'&&$l['status']!=='rejected'): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="reject"><input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>"><button class="btn-fm btn-danger" onclick="return confirm('<?php echo e(t('hr.confirm_reject')); ?>');"><?php echo e(t('hr.reject')); ?></button></form><?php endif; ?></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>