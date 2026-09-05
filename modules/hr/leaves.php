<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

// This module uses the project's db() connection helper. Keep a local PDO
// handle because the leave workflow performs prepared statements directly.
$pdo = db();

$userRole = Session::getUserRole();
$isLoggedIn = Session::isLoggedIn();
$isRequestMode = ($_GET['action'] ?? '') === 'request';

/*
 * Leave requests are personal actions for every authenticated employee.
 * HR management remains restricted to HR/Admin when viewing the normal
 * leave-management screen.
 */
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

/* Resolve the employee record belonging to the logged-in user. */
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

            $sql = "INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days_count, reason, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $pdo->prepare($sql)->execute([$emp_id, $type, $start, $end, $days, $reason]);

            $message = t('hr.request_submitted');
        } elseif ($action === 'approve_manager') {
            $id = (int)$_POST['leave_id'];
            $sql = "UPDATE leaves SET status = 'manager_approved', manager_approved_by = ?, manager_approved_at = NOW() WHERE id = ? AND status = 'pending'";
            $pdo->prepare($sql)->execute([Session::getUserID(), $id]);
            $message = t('hr.leave_approved_manager');
        } elseif ($action === 'approve_hr') {
            $id = (int)$_POST['leave_id'];
            $leave = dbFetchOne("SELECT * FROM leaves WHERE id = ? AND status = 'manager_approved'", [$id]);
            if ($leave) {
                $pdo->prepare("UPDATE leaves SET status = 'hr_approved', hr_approved_by = ?, hr_approved_at = NOW() WHERE id = ?")->execute([Session::getUserID(), $id]);
                $start = new DateTime($leave['start_date']);
                $end = new DateTime($leave['end_date']);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, date, status, notes) VALUES (?, ?, 'on_leave', 'إجازة معتمدة') ON DUPLICATE KEY UPDATE status = 'on_leave'");
                foreach ($period as $date) {
                    $stmt->execute([$leave['employee_id'], $date->format('Y-m-d')]);
                }
                $message = t('hr.leave_approved_final');
            }
        } elseif ($action === 'reject') {
            $id = (int)$_POST['leave_id'];
            $pdo->prepare("UPDATE leaves SET status = 'rejected' WHERE id = ?")->execute([$id]);
            $message = t('hr.leave_rejected');
        }
    } catch (Exception $e) {
        $message = t('hr.error', ['message' => $e->getMessage()]);
        $msg_type = 'error';
    }
}

/*
 * Personal request mode: show only the logged-in user's requests.
 * HR/Admin management mode: preserve the existing management listing.
 */
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

    <div class="fm-header">
        <h1><i class="fas fa-calendar-plus me-2"></i> طلب إجازة</h1>
        <p>تقديم ومتابعة طلبات الإجازة الخاصة بك.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius:8px;">
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$currentEmployee): ?>
        <div class="alert alert-warning">
            لا يوجد سجل موظف مرتبط بحسابك في النظام. يرجى التواصل مع إدارة الموارد البشرية قبل تقديم طلب إجازة.
        </div>
    <?php else: ?>
        <div class="fm-card">
            <div class="fm-card-head">
                <span><i class="fas fa-file-signature me-2"></i>طلب إجازة جديد</span>
                <span><?php echo e($currentEmployee['full_name']); ?></span>
            </div>
            <div class="fm-card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="request">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">نوع الإجازة</label>
                            <select name="leave_type" class="form-select" required>
                                <option value="">اختر نوع الإجازة</option>
                                <option value="annual">إجازة سنوية</option>
                                <option value="sick">إجازة مرضية</option>
                                <option value="emergency">إجازة طارئة</option>
                                <option value="unpaid">إجازة بدون راتب</option>
                                <option value="remote_work_request">طلب عمل عن بُعد</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">من</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-3">