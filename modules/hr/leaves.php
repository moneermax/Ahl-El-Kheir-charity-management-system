<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

// 1. Security Check
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$message = '';
$msg_type = 'success';
$filter = $_GET['status'] ?? 'all';

// 2. Handle Actions (Request, Approve, Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'request') {
            $emp_id = (int)$_POST['employee_id'];
            $type = $_POST['leave_type'];
            $start = $_POST['start_date'];
            $end = $_POST['end_date'];
            $reason = trim($_POST['reason']);
            
            $d1 = new DateTime($start);
            $d2 = new DateTime($end);
            $days = $d1->diff($d2)->days + 1;

            $sql = "INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days_count, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $pdo->prepare($sql)->execute([$emp_id, $type, $start, $end, $days, $reason]);
            $message = "تم تقديم طلب الإجازة بنجاح وبانتظار اعتماد المدير.";
        } 
        elseif ($action === 'approve_manager') {
            $id = (int)$_POST['leave_id'];
            $sql = "UPDATE leaves SET status = 'manager_approved', manager_approved_by = ?, manager_approved_at = NOW() WHERE id = ? AND status = 'pending'";
            $pdo->prepare($sql)->execute([Session::getUserID(), $id]);
            $message = "تم اعتماد الإجازة من قبل المدير. بانتظار اعتماد الموارد البشرية.";
        } 
        elseif ($action === 'approve_hr') {
            $id = (int)$_POST['leave_id'];
            $leave = dbFetchOne("SELECT * FROM leaves WHERE id = ? AND status = 'manager_approved'", [$id]);
            if ($leave) {
                // 1. Update leave status
                $pdo->prepare("UPDATE leaves SET status = 'hr_approved', hr_approved_by = ?, hr_approved_at = NOW() WHERE id = ?")
                    ->execute([Session::getUserID(), $id]);
                
                // 2. Auto-update attendance table for the leave period
                $start = new DateTime($leave['start_date']);
                $end = new DateTime($leave['end_date']);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, date, status, notes) VALUES (?, ?, 'on_leave', 'إجازة معتمدة') ON DUPLICATE KEY UPDATE status = 'on_leave'");
                foreach ($period as $date) {
                    $stmt->execute([$leave['employee_id'], $date->format('Y-m-d')]);
                }
                $message = "تم اعتماد الإجازة نهائياً من الموارد البشرية، وتحديث سجل الحضور.";
            }
        } 
        elseif ($action === 'reject') {
            $id = (int)$_POST['leave_id'];
            $sql = "UPDATE leaves SET status = 'rejected' WHERE id = ?";
            $pdo->prepare($sql)->execute([$id]);
            $message = "تم رفض طلب الإجازة.";
        }
    } catch (Exception $e) {
        $message = "خطأ: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// 3. Fetch Data
$employees = dbFetchAll("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name", []);

$sql = "SELECT l.*, e.full_name as emp_name, d.name_ar as dept_name, 
        u1.full_name as mgr_name, u2.full_name as hr_name 
        FROM leaves l 
        JOIN employees e ON l.employee_id = e.id 
        LEFT JOIN departments d ON e.department_id = d.id 
        LEFT JOIN users u1 ON l.manager_approved_by = u1.id 
        LEFT JOIN users u2 ON l.hr_approved_by = u2.id";
        
if ($filter !== 'all') {
    $sql .= " WHERE l.status = ?";
    $leaves = dbFetchAll($sql, [$filter]);
} else {
    $sql .= " ORDER BY l.created_at DESC";
    $leaves = dbFetchAll($sql, []);
}

$pageTitle = 'إدارة الإجازات';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.fm-header { background: linear-gradient(135deg, #1b4d8f 0%, #2c5aa0 100%); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fm-header h1 { margin: 0; font-size: 1.8rem; }
.fm-header p { margin: 5px 0 0; opacity: 0.9; }
.fm-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 20px; overflow: hidden; }
.fm-card-head { background: #1b4d8f; color: #fff; padding: 12px 18px; font-weight: 700; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; }
.fm-card-body { padding: 20px; }
.fm-table { width: 100%; border-collapse: collapse; }
.fm-table th, .fm-table td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: right; font-size: 0.9rem; }
.fm-table th { background: #f8f9fa; font-weight: 700; color: #1b4d8f; }
.fm-table tr:hover { background: #f8f9fa; }
.badge-fm { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
.badge-amber { background: #fff3cd; color: #856404; }
.badge-blue { background: #d1ecf1; color: #0c5460; }
.badge-green { background: #d4edda; color: #155724; }
.badge-red { background: #f8d7da; color: #721c24; }
.badge-gray { background: #e9ecef; color: #6c757d; }
.btn-fm { display: inline-block; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 0.8rem; margin: 2px; }
.btn-navy { background: #1b4d8f; color: #fff; }
.btn-navy:hover { background: #143a6b; color: #fff; }
.btn-success { background: #28a745; color: #fff; }
.btn-danger { background: #dc3545; color: #fff; }
.form-label { font-weight: 600; color: #495057; margin-bottom: 0.5rem; }
.form-control, .form-select { border-radius: 6px; border: 1px solid #ced4da; padding: 0.5rem 0.8rem; }
.tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.tab { padding: 8px 16px; border-radius: 6px 6px 0 0; text-decoration: none; color: #666; font-weight: 600; }
.tab.active { background: #1b4d8f; color: #fff; }
</style>

<div class="fm-header">
    <h1><i class="fas fa-calendar-alt me-2"></i> إدارة الإجازات</h1>
    <p>متابعة واعتماد طلبات الإجازات بنظام الاعتماد المزدوج</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius: 8px;">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
    <a href="?status=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">الكل</a>
    <a href="?status=pending" class="tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">بانتظار المدير</a>
    <a href="?status=manager_approved" class="tab <?php echo $filter === 'manager_approved' ? 'active' : ''; ?>">بانتظار HR</a>
    <a href="?status=hr_approved" class="tab <?php echo $filter === 'hr_approved' ? 'active' : ''; ?>">معتمدة نهائياً</a>
    <a href="?status=rejected" class="tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">مرفوضة</a>
</div>

<div class="fm-card">
    <div class="fm-card-head">
        <span>📋 سجل الطلبات</span>
        <span class="badge-fm badge-blue"><?php echo count($leaves); ?> طلب</span>
    </div>
    <div class="fm-card-body">
        <div class="table-responsive">
            <table class="fm-table">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>النوع</th>
                        <th>من - إلى</th>
                        <th>الأيام</th>
                        <th>الحالة</th>
                        <th class="text-end">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">لا توجد طلبات في هذا التصنيف.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leaves as $l): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($l['emp_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($l['dept_name'] ?? ''); ?></small></td>
                            <td><?php echo htmlspecialchars($l['leave_type']); ?></td>
                            <td><small><?php echo $l['start_date']; ?><br>إلى<br><?php echo $l['end_date']; ?></small></td>
                            <td><?php echo $l['days_count']; ?></td>
                            <td>
                                <?php
                                $status_map = [
                                    'pending' => ['badge-amber', 'بانتظار المدير'],
                                    'manager_approved' => ['badge-blue', 'بانتظار HR'],
                                    'hr_approved' => ['badge-green', 'معتمدة'],
                                    'rejected' => ['badge-red', 'مرفوضة']
                                ];
                                $cls = $status_map[$l['status']][0] ?? 'badge-gray';
                                $lbl = $status_map[$l['status']][1] ?? $l['status'];
                                ?>
                                <span class="badge-fm <?php echo $cls; ?>"><?php echo $lbl; ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($l['status'] === 'pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="approve_manager">
                                        <input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>">
                                        <button class="btn-fm btn-navy">اعتماد مبدئي</button>
                                    </form>
                                <?php elseif ($l['status'] === 'manager_approved'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="approve_hr">
                                        <input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>">
                                        <button class="btn-fm btn-success">اعتماد نهائي</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($l['status'] !== 'hr_approved' && $l['status'] !== 'rejected'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>">
                                        <button class="btn-fm btn-danger" onclick="return confirm('هل أنت متأكد من رفض هذا الطلب؟');">رفض</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
