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

// 2. Handle Date Selection
$selected_date = $_GET['date'] ?? date('Y-m-d');

// 3. Handle Actions (Check In, Check Out, Mark Absent)
$message = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    
    try {
        if ($action === 'check_in') {
            $work_mode = $_POST['work_mode'] ?? 'remote';
            $check_in_time = date('H:i:s');
            $status = 'present'; // Could add logic for 'late' if after 9:00 AM
            
            $sql = "INSERT INTO attendance (employee_id, date, check_in, work_mode, status) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), work_mode = VALUES(work_mode), status = VALUES(status)";
            $pdo->prepare($sql)->execute([$emp_id, $selected_date, $check_in_time, $work_mode, $status]);
            $message = "تم تسجيل الحضور بنجاح";
        } 
        elseif ($action === 'check_out') {
            $check_out_time = date('H:i:s');
            $sql = "UPDATE attendance SET check_out = ? WHERE employee_id = ? AND date = ?";
            $pdo->prepare($sql)->execute([$check_out_time, $emp_id, $selected_date]);
            $message = "تم تسجيل الانصراف بنجاح";
        } 
        elseif ($action === 'mark_absent') {
            $sql = "INSERT INTO attendance (employee_id, date, status) 
                    VALUES (?, ?, 'absent') 
                    ON DUPLICATE KEY UPDATE status = 'absent'";
            $pdo->prepare($sql)->execute([$emp_id, $selected_date]);
            $message = "تم تسجيل الغياب";
        }
        elseif ($action === 'mark_leave') {
            $sql = "INSERT INTO attendance (employee_id, date, status) 
                    VALUES (?, ?, 'on_leave') 
                    ON DUPLICATE KEY UPDATE status = 'on_leave'";
            $pdo->prepare($sql)->execute([$emp_id, $selected_date]);
            $message = "تم تسجيل الإجازة";
        }
    } catch (Exception $e) {
        $message = "خطأ: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// 4. Fetch Data
$employees = dbFetchAll("SELECT e.id, e.full_name, e.department_id, d.name_ar as dept_name 
                         FROM employees e 
                         LEFT JOIN departments d ON e.department_id = d.id 
                         WHERE e.status = 'active' 
                         ORDER BY e.full_name", []);

$attendance_records = [];
$att_list = dbFetchAll("SELECT * FROM attendance WHERE date = ?", [$selected_date]);
foreach ($att_list as $att) {
    $attendance_records[$att['employee_id']] = $att;
}

// Calculate Stats
$stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'on_leave' => 0, 'remote' => 0, 'onsite' => 0];
foreach ($attendance_records as $att) {
    if (isset($stats[$att['status']])) $stats[$att['status']]++;
    if (isset($stats[$att['work_mode']])) $stats[$att['work_mode']]++;
}

$pageTitle = 'تسجيل الحضور والانصراف';
require_once __DIR__ . '/../../includes/header.php';
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
.fm-table { width: 100%; border-collapse: collapse; }
.fm-table th, .fm-table td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: right; font-size: 0.9rem; }
.fm-table th { background: #f8f9fa; font-weight: 700; color: #1b4d8f; }
.fm-table tr:hover { background: #f8f9fa; }
.badge-fm { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
.badge-green { background: #d4edda; color: #155724; }
.badge-red { background: #f8d7da; color: #721c24; }
.badge-amber { background: #fff3cd; color: #856404; }
.badge-blue { background: #d1ecf1; color: #0c5460; }
.badge-gray { background: #e9ecef; color: #6c757d; }
.btn-fm { display: inline-block; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 0.8rem; margin: 2px; }
.btn-navy { background: #1b4d8f; color: #fff; }
.btn-navy:hover { background: #143a6b; color: #fff; }
.btn-success { background: #28a745; color: #fff; }
.btn-success:hover { background: #218838; color: #fff; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; color: #fff; }
.btn-warning { background: #ffc107; color: #000; }
.btn-warning:hover { background: #e0a800; color: #000; }
.form-control, .form-select { border-radius: 6px; border: 1px solid #ced4da; padding: 0.4rem 0.6rem; font-size: 0.85rem; }
.grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
</style>

<div class="fm-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1><i class="fas fa-clock me-2"></i> تسجيل الحضور والانصراف</h1>
            <p>متابعة دوام الموظفين — <?php echo date('Y-m-d', strtotime($selected_date)); ?></p>
        </div>
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" style="width: auto;">
            <button type="submit" class="btn-fm btn-navy">عرض</button>
        </form>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius: 8px;">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="grid-4">
    <div class="stat-box green">
        <div class="stat-value"><?php echo $stats['present']; ?></div>
        <div class="stat-label">حاضر</div>
    </div>
    <div class="stat-box amber">
        <div class="stat-value"><?php echo $stats['late']; ?></div>
        <div class="stat-label">متأخر</div>
    </div>
    <div class="stat-box red">
        <div class="stat-value"><?php echo $stats['absent']; ?></div>
        <div class="stat-label">غائب</div>
    </div>
    <div class="stat-box blue">
        <div class="stat-value"><?php echo $stats['on_leave']; ?></div>
        <div class="stat-label">في إجازة</div>
    </div>
</div>

<!-- Attendance Table -->
<div class="fm-card">
    <div class="fm-card-head">
        <span> سجل الموظفين ليوم <?php echo date('Y-m-d', strtotime($selected_date)); ?></span>
        <span class="badge-fm badge-blue"><?php echo count($employees); ?> موظف نشط</span>
    </div>
    <div class="fm-card-body">
        <div class="table-responsive">
            <table class="fm-table">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>القسم</th>
                        <th>الحضور</th>
                        <th>الانصراف</th>
                        <th>نمط العمل</th>
                        <th>الحالة</th>
                        <th class="text-end">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">لا يوجد موظفين نشطين.</td></tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): 
                            $att = $attendance_records[$emp['id']] ?? null;
                            $status = $att['status'] ?? 'absent';
                            $work_mode = $att['work_mode'] ?? '';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($emp['dept_name'] ?? '-'); ?></td>
                            <td>
                                <?php if ($att && $att['check_in']): ?>
                                    <span class="badge-fm badge-green"><?php echo substr($att['check_in'], 0, 5); ?></span>
                                <?php else: ?>
                                    <span class="badge-fm badge-gray">--:--</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($att && $att['check_out']): ?>
                                    <span class="badge-fm badge-red"><?php echo substr($att['check_out'], 0, 5); ?></span>
                                <?php else: ?>
                                    <span class="badge-fm badge-gray">--:--</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $modes = ['remote' => 'عن بُعد', 'onsite' => 'في المقر', 'hybrid' => 'مختلط'];
                                echo $att ? ($modes[$work_mode] ?? $work_mode) : '-'; 
                                ?>
                            </td>
                            <td>
                                <?php
                                $status_colors = ['present' => 'green', 'late' => 'amber', 'absent' => 'red', 'on_leave' => 'blue', 'half_day' => 'gray'];
                                $status_labels = ['present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب', 'on_leave' => 'إجازة', 'half_day' => 'نصف يوم'];
                                $color = $status_colors[$status] ?? 'gray';
                                $label = $status_labels[$status] ?? $status;
                                ?>
                                <span class="badge-fm badge-<?php echo $color; ?>"><?php echo $label; ?></span>
                            </td>
                            <td class="text-end">
                                <?php if (!$att || !$att['check_in']): ?>
                                    <form method="POST" style="display:inline-flex; gap:5px; align-items:center;">
                                        <input type="hidden" name="action" value="check_in">
                                        <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                                        <select name="work_mode" class="form-select" required>
                                            <option value="remote">عن بُعد</option>
                                            <option value="onsite">في المقر</option>
                                        </select>
                                        <button type="submit" class="btn-fm btn-success">تسجيل حضور</button>
                                    </form>
                                <?php elseif ($att && !$att['check_out']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="check_out">
                                        <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                                        <button type="submit" class="btn-fm btn-danger">تسجيل انصراف</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.8rem;">مكتمل</span>
                                <?php endif; ?>
                                
                                <?php if (!$att || $att['status'] === 'absent'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="mark_leave">
                                        <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                                        <button type="submit" class="btn-fm btn-warning" title="تسجيل إجازة">إجازة</button>
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