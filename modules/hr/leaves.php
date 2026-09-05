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

$hrRoles = ['hr_manager', 'hr_staff'];
// Keep both common GM role keys for compatibility with existing deployments.
$gmRoles = ['general_manager', 'gm'];
$isHrUser = in_array($userRole, $hrRoles, true);
$isGmUser = in_array($userRole, $gmRoles, true);
$isLeaveApprover = $isHrUser || $isGmUser;

if (!$isLoggedIn) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

if (!$isRequestMode && !$isLeaveApprover) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$message = '';
$msg_type = 'success';
$filter = $_GET['status'] ?? 'all';

// users stores the role through role_id; the role code lives in roles.code.
$currentEmployee = dbFetchOne(
    "SELECT id, full_name FROM employees WHERE user_id = ? LIMIT 1",
    [Session::getUserID()]
);

/**
 * Create the attendance rows for an approved leave.
 * This is deliberately executed after the single final approval stage.
 */
function createLeaveAttendance(array $leave): void
{
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    $stmt = db()->prepare(
        "INSERT INTO attendance (employee_id, date, status, notes)
         VALUES (?, ?, 'on_leave', 'إجازة معتمدة')
         ON DUPLICATE KEY UPDATE status = 'on_leave'"
    );

    foreach ($period as $date) {
        $stmt->execute([(int)$leave['employee_id'], $date->format('Y-m-d')]);
    }
}

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
                header('Location: ' . APP_URL . 'modules/hr/leaves.php?action=request');
                exit();
            }

            db()->prepare(
                "INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days_count, reason, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')"
            )->execute([$emp_id, $type, $start, $end, $days, $reason]);

            header('Location: ' . APP_URL . 'modules/hr/leaves.php?action=request');
            exit();
        }

        if ($action === 'approve_hr' || $action === 'approve_gm') {
            $id = (int)($_POST['leave_id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('طلب الإجازة غير صالح.');
            }

            if ($action === 'approve_hr') {
                if (!$isHrUser) {
                    throw new RuntimeException('غير مصرح لك باعتماد طلبات الإجازة للموظفين.');
                }

                // HR approves every normal employee request in one stage.
                // HR requests themselves are excluded so HR can never approve their own leave.
                $leave = dbFetchOne(
                    "SELECT l.*
                     FROM leaves l
                     JOIN employees e ON e.id = l.employee_id
                     JOIN users u ON u.id = e.user_id
                     JOIN roles r ON r.id = u.role_id
                     WHERE l.id = ?
                       AND l.status = 'pending'
                       AND r.code NOT IN ('hr_manager', 'hr_staff')
                     LIMIT 1",
                    [$id]
                );

                if (!$leave) {
                    throw new RuntimeException('طلب الإجازة غير موجود أو لا يخضع لاعتماد الموارد البشرية.');
                }

                $updated = db()->prepare(
                    "UPDATE leaves
                     SET status = 'hr_approved', hr_approved_by = ?, hr_approved_at = NOW()
                     WHERE id = ? AND status = 'pending'"
                );
                $updated->execute([Session::getUserID(), $id]);

                if ($updated->rowCount() !== 1) {
                    throw new RuntimeException('تعذر اعتماد طلب الإجازة لأنه لم يعد قيد المراجعة.');
                }

                createLeaveAttendance($leave);
                $message = t('hr.leave_approved_final');
            } else {
                if (!$isGmUser) {
                    throw new RuntimeException('غير مصرح لك باعتماد طلبات إجازة الموارد البشرية.');
                }

                // Only the General Manager approves HR employees' own leave requests.
                $leave = dbFetchOne(
                    "SELECT l.*
                     FROM leaves l
                     JOIN employees e ON e.id = l.employee_id
                     JOIN users u ON u.id = e.user_id
                     JOIN roles r ON r.id = u.role_id
                     WHERE l.id = ?
                       AND l.status = 'pending'
                       AND r.code IN ('hr_manager', 'hr_staff')
                     LIMIT 1",
                    [$id]
                );

                if (!$leave) {
                    throw new RuntimeException('طلب الإجازة غير موجود أو لا يخضع لاعتماد المدير العام.');
                }

                // Keep the existing manager approval columns for schema compatibility/history,
                // but this is now the final GM approval stage, not an intermediate stage.
                $updated = db()->prepare(
                    "UPDATE leaves
                     SET status = 'hr_approved', manager_approved_by = ?, manager_approved_at = NOW()
                     WHERE id = ? AND status = 'pending'"
                );
                $updated->execute([Session::getUserID(), $id]);

                if ($updated->rowCount() !== 1) {
                    throw new RuntimeException('تعذر اعتماد طلب الإجازة لأنه لم يعد قيد المراجعة.');
                }

                createLeaveAttendance($leave);
                $message = t('hr.leave_approved_final');
            }

            // Prevent browser refresh from replaying the approval POST.
            header('Location: ' . APP_URL . 'modules/hr/leaves.php?status=pending');
            exit();
        }

        if ($action === 'reject') {
            $id = (int)($_POST['leave_id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('طلب الإجازة غير صالح.');
            }

            if (!$isLeaveApprover) {
                throw new RuntimeException('غير مصرح لك برفض طلبات الإجازة.');
            }

            // Reject only requests this role is responsible for approving.
            $roleCondition = $isHrUser
                ? "r.code NOT IN ('hr_manager', 'hr_staff')"
                : "r.code IN ('hr_manager', 'hr_staff')";

            $leave = dbFetchOne(
                "SELECT l.id
                 FROM leaves l
                 JOIN employees e ON e.id = l.employee_id
                 JOIN users u ON u.id = e.user_id
                 JOIN roles r ON r.id = u.role_id
                 WHERE l.id = ?
                   AND l.status = 'pending'
                   AND {$roleCondition}
                 LIMIT 1",
                [$id]
            );

            if (!$leave) {
                throw new RuntimeException('طلب الإجازة غير موجود أو لا يخضع لصلاحياتك.');
            }

            $updated = db()->prepare(
                "UPDATE leaves SET status = 'rejected' WHERE id = ? AND status = 'pending'"
            );
            $updated->execute([$id]);

            if ($updated->rowCount() !== 1) {
                throw new RuntimeException('تعذر رفض طلب الإجازة لأنه لم يعد قيد المراجعة.');
            }

            header('Location: ' . APP_URL . 'modules/hr/leaves.php?status=pending');
            exit();
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
                $statusMap = ['pending'=>['badge-amber','قيد المراجعة'],'manager_approved'=>['badge-blue','مرحلة اعتماد سابقة'],'hr_approved'=>['badge-green','معتمدة'],'rejected'=>['badge-red','مرفوضة']];
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

// Management view: HR sees normal employees' pending requests; GM sees HR requests.
$roleCondition = $isHrUser
    ? "r.code NOT IN ('hr_manager', 'hr_staff')"
    : "r.code IN ('hr_manager', 'hr_staff')";

$sql = "SELECT l.*, e.full_name AS emp_name, d.name_ar AS dept_name,
               u1.full_name AS mgr_name, u2.full_name AS hr_name,
               r.code AS employee_role
        FROM leaves l
        JOIN employees e ON l.employee_id = e.id
        JOIN users u ON u.id = e.user_id
        JOIN roles r ON r.id = u.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users u1 ON l.manager_approved_by = u1.id
        LEFT JOIN users u2 ON l.hr_approved_by = u2.id
        WHERE {$roleCondition}";

if ($filter !== 'all') {
    $sql .= " AND l.status = ? ORDER BY l.created_at DESC";
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
<a href="?status=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">الكل</a>
<a href="?status=pending" class="tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">قيد المراجعة</a>
<a href="?status=hr_approved" class="tab <?php echo $filter === 'hr_approved' ? 'active' : ''; ?>">المعتمدة</a>
<a href="?status=rejected" class="tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">المرفوضة</a>
</div>

<div class="fm-card"><div class="fm-card-head"><span>📋 <?php echo e(t('hr.request_records')); ?></span><span class="badge-fm badge-blue"><?php echo e(t('hr.request_count', ['count' => count($leaves)])); ?></span></div><div class="fm-card-body"><div class="table-responsive"><table class="fm-table"><thead><tr><th><?php echo e(t('hr.employee')); ?></th><th><?php echo e(t('hr.leave_type')); ?></th><th><?php echo e(t('hr.date_range')); ?></th><th><?php echo e(t('hr.days')); ?></th><th><?php echo e(t('common.status')); ?></th><th class="text-end"><?php echo e(t('common.actions')); ?></th></tr></thead><tbody>
<?php if (empty($leaves)): ?><tr><td colspan="6" class="text-center py-4 text-muted"><?php echo e(t('hr.no_requests_category')); ?></td></tr><?php else: foreach ($leaves as $l): ?>
<tr>
<td><strong><?php echo htmlspecialchars($l['emp_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($l['dept_name'] ?? ''); ?></small></td>
<td><?php $types=['annual'=>'hr.leave_type_short_annual','sick'=>'hr.leave_type_short_sick','emergency'=>'hr.leave_type_short_emergency','unpaid'=>'hr.leave_type_short_unpaid','remote_work_request'=>'hr.leave_type_short_remote']; echo e(t($types[$l['leave_type']] ?? 'hr.leave_type')); ?></td>
<td><small><?php echo e($l['start_date']); ?><br><?php echo e(t('common.to')); ?><br><?php echo e($l['end_date']); ?></small></td>
<td><?php echo e((string)$l['days_count']); ?></td>
<td><?php $status_map=['pending'=>['badge-amber','قيد المراجعة'],'manager_approved'=>['badge-blue','مرحلة اعتماد سابقة'],'hr_approved'=>['badge-green','hr.final_approved'],'rejected'=>['badge-red','hr.rejected']]; $statusData=$status_map[$l['status']]??['badge-gray',(string)$l['status']]; $statusLabel=$statusData[1]; ?><span class="badge-fm <?php echo e($statusData[0]); ?>"><?php echo strpos($statusLabel, 'hr.') === 0 ? e(t($statusLabel)) : e($statusLabel); ?></span></td>
<td class="text-end">
<?php if ($l['status'] === 'pending'): ?>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="<?php echo $isHrUser ? 'approve_hr' : 'approve_gm'; ?>"><input type="hidden" name="leave_id" value="<?php echo (int)$l['id']; ?>"><button class="btn-fm btn-success"><?php echo $isHrUser ? 'اعتماد الموارد البشرية' : 'اعتماد المدير العام'; ?></button></form>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="reject"><input type="hidden" name="leave_id" value="<?php echo (int)$l['id']; ?>"><button class="btn-fm btn-danger" onclick="return confirm('<?php echo e(t('hr.confirm_reject')); ?>');"><?php echo e(t('hr.reject')); ?></button></form>
<?php endif; ?>
</td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>