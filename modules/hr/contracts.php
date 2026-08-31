<<<<<<< HEAD
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

$message = '';
$msg_type = 'success';
$filter = $_GET['status'] ?? 'all';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $emp_id = (int)$_POST['employee_id'];
            $type = trim($_POST['contract_type']);
            $start = $_POST['start_date'];
            $end = $_POST['end_date'] ?: null;
            $salary = (float)$_POST['salary'];
            $terms = trim($_POST['terms']);
            
            $stmt = $pdo->prepare("INSERT INTO contracts (employee_id, contract_type, start_date, end_date, salary, terms, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$emp_id, $type, $start, $end, $salary, $terms]);
            $message = "تم إضافة العقد بنجاح";
        } elseif ($action === 'update_status') {
            $id = (int)$_POST['contract_id'];
            $status = $_POST['status'];
            $pdo->prepare("UPDATE contracts SET status = ? WHERE id = ?")->execute([$status, $id]);
            $message = "تم تحديث حالة العقد";
        }
    } catch (Exception $e) {
        $message = "خطأ: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Fetch Data
$employees = dbFetchAll("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name", []);

$sql = "SELECT c.*, e.full_name as emp_name FROM contracts c JOIN employees e ON c.employee_id = e.id";
if ($filter !== 'all') {
    $sql .= " WHERE c.status = ? ORDER BY c.end_date ASC";
    $contracts = dbFetchAll($sql, [$filter]);
} else {
    $sql .= " ORDER BY c.end_date ASC";
    $contracts = dbFetchAll($sql, []);
}

$pageTitle = 'إدارة العقود';
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
.badge-green { background: #d4edda; color: #155724; }
.badge-amber { background: #fff3cd; color: #856404; }
.badge-red { background: #f8d7da; color: #721c24; }
.badge-gray { background: #e9ecef; color: #6c757d; }
.btn-fm { display: inline-block; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 0.8rem; margin: 2px; }
.btn-navy { background: #1b4d8f; color: #fff; }
.btn-navy:hover { background: #143a6b; color: #fff; }
.btn-danger { background: #dc3545; color: #fff; }
.form-label { font-weight: 600; color: #495057; margin-bottom: 0.5rem; }
.form-control, .form-select { border-radius: 6px; border: 1px solid #ced4da; padding: 0.5rem 0.8rem; }
.tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.tab { padding: 8px 16px; border-radius: 6px 6px 0 0; text-decoration: none; color: #666; font-weight: 600; }
.tab.active { background: #1b4d8f; color: #fff; }
</style>

<div class="fm-header">
    <h1><i class="fas fa-file-contract me-2"></i> إدارة العقود</h1>
    <p>متابعة عقود الموظفين وتجديدها</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius: 8px;">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="tabs">
    <a href="?status=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">الكل</a>
    <a href="?status=active" class="tab <?php echo $filter === 'active' ? 'active' : ''; ?>">نشطة</a>
    <a href="?status=expired" class="tab <?php echo $filter === 'expired' ? 'active' : ''; ?>">منتهية</a>
    <a href="?status=terminated" class="tab <?php echo $filter === 'terminated' ? 'active' : ''; ?>">ملغاة</a>
</div>

<div class="fm-card">
    <div class="fm-card-head">
        <span>📋 سجل العقود</span>
        <span class="badge-fm badge-blue"><?php echo count($contracts); ?> عقد</span>
    </div>
    <div class="fm-card-body">
        <div class="table-responsive">
            <table class="fm-table">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>النوع</th>
                        <th>البداية</th>
                        <th>النهاية</th>
                        <th>الأيام المتبقية</th>
                        <th>الحالة</th>
                        <th class="text-end">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contracts)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">لا توجد عقود.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $c): 
                            $days_left = $c['end_date'] ? (new DateTime($c['end_date']))->diff(new DateTime())->days : null;
                            $is_expiring = $days_left !== null && $days_left <= 30 && $c['status'] === 'active';
                        ?>
                        <tr style="<?php echo $is_expiring ? 'background-color: #fff3cd;' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($c['emp_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($c['contract_type']); ?></td>
                            <td><?php echo $c['start_date']; ?></td>
                            <td><?php echo $c['end_date'] ?: 'مفتوح'; ?></td>
                            <td>
                                <?php if ($days_left !== null): ?>
                                    <span class="badge-fm <?php echo $is_expiring ? 'badge-amber' : 'badge-green'; ?>">
                                        <?php echo $c['status'] === 'active' ? $days_left . ' يوم' : '-'; ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_map = ['active' => ['badge-green', 'نشط'], 'expired' => ['badge-amber', 'منتهي'], 'terminated' => ['badge-red', 'ملغي']];
                                $cls = $status_map[$c['status']][0] ?? 'badge-gray';
                                $lbl = $status_map[$c['status']][1] ?? $c['status'];
                                ?>
                                <span class="badge-fm <?php echo $cls; ?>"><?php echo $lbl; ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($c['status'] === 'active'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="status" value="terminated">
                                        <button class="btn-fm btn-danger" onclick="return confirm('هل أنت متأكد من إلغاء هذا العقد؟');">إلغاء</button>
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

<div class="row">
    <div class="col-md-6">
        <div class="fm-card">
            <div class="fm-card-head"><span>➕ إضافة / تجديد عقد</span></div>
            <div class="fm-card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">الموظف</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">-- اختر الموظف --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">نوع العقد</label>
                        <select name="contract_type" class="form-select" required>
                            <option value="دائم">دائم</option>
                            <option value="مؤقت">مؤقت</option>
                            <option value="عقد مشروع">عقد مشروع</option>
                            <option value="تدريب">تدريب</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تاريخ البداية</label>
                        <input type="date" name="start_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تاريخ النهاية</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الراتب في العقد</label>
                        <input type="number" step="0.01" name="salary" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الشروط / ملاحظات</label>
                        <textarea name="terms" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn-fm btn-navy" style="width:100%;">حفظ العقد</button>
                </form>
            </div>
        </div>
    </div>
</div>

=======
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

$message = '';
$msg_type = 'success';
$filter = $_GET['status'] ?? 'all';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $emp_id = (int)$_POST['employee_id'];
            $type = trim($_POST['contract_type']);
            $start = $_POST['start_date'];
            $end = $_POST['end_date'] ?: null;
            $salary = (float)$_POST['salary'];
            $terms = trim($_POST['terms']);
            
            $stmt = $pdo->prepare("INSERT INTO contracts (employee_id, contract_type, start_date, end_date, salary, terms, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$emp_id, $type, $start, $end, $salary, $terms]);
            $message = "تم إضافة العقد بنجاح";
        } elseif ($action === 'update_status') {
            $id = (int)$_POST['contract_id'];
            $status = $_POST['status'];
            $pdo->prepare("UPDATE contracts SET status = ? WHERE id = ?")->execute([$status, $id]);
            $message = "تم تحديث حالة العقد";
        }
    } catch (Exception $e) {
        $message = "خطأ: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Fetch Data
$employees = dbFetchAll("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name", []);

$sql = "SELECT c.*, e.full_name as emp_name FROM contracts c JOIN employees e ON c.employee_id = e.id";
if ($filter !== 'all') {
    $sql .= " WHERE c.status = ? ORDER BY c.end_date ASC";
    $contracts = dbFetchAll($sql, [$filter]);
} else {
    $sql .= " ORDER BY c.end_date ASC";
    $contracts = dbFetchAll($sql, []);
}

$pageTitle = 'إدارة العقود';
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
.badge-green { background: #d4edda; color: #155724; }
.badge-amber { background: #fff3cd; color: #856404; }
.badge-red { background: #f8d7da; color: #721c24; }
.badge-gray { background: #e9ecef; color: #6c757d; }
.btn-fm { display: inline-block; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 0.8rem; margin: 2px; }
.btn-navy { background: #1b4d8f; color: #fff; }
.btn-navy:hover { background: #143a6b; color: #fff; }
.btn-danger { background: #dc3545; color: #fff; }
.form-label { font-weight: 600; color: #495057; margin-bottom: 0.5rem; }
.form-control, .form-select { border-radius: 6px; border: 1px solid #ced4da; padding: 0.5rem 0.8rem; }
.tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.tab { padding: 8px 16px; border-radius: 6px 6px 0 0; text-decoration: none; color: #666; font-weight: 600; }
.tab.active { background: #1b4d8f; color: #fff; }
</style>

<div class="fm-header">
    <h1><i class="fas fa-file-contract me-2"></i> إدارة العقود</h1>
    <p>متابعة عقود الموظفين وتجديدها</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius: 8px;">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="tabs">
    <a href="?status=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">الكل</a>
    <a href="?status=active" class="tab <?php echo $filter === 'active' ? 'active' : ''; ?>">نشطة</a>
    <a href="?status=expired" class="tab <?php echo $filter === 'expired' ? 'active' : ''; ?>">منتهية</a>
    <a href="?status=terminated" class="tab <?php echo $filter === 'terminated' ? 'active' : ''; ?>">ملغاة</a>
</div>

<div class="fm-card">
    <div class="fm-card-head">
        <span>📋 سجل العقود</span>
        <span class="badge-fm badge-blue"><?php echo count($contracts); ?> عقد</span>
    </div>
    <div class="fm-card-body">
        <div class="table-responsive">
            <table class="fm-table">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>النوع</th>
                        <th>البداية</th>
                        <th>النهاية</th>
                        <th>الأيام المتبقية</th>
                        <th>الحالة</th>
                        <th class="text-end">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contracts)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">لا توجد عقود.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $c): 
                            $days_left = $c['end_date'] ? (new DateTime($c['end_date']))->diff(new DateTime())->days : null;
                            $is_expiring = $days_left !== null && $days_left <= 30 && $c['status'] === 'active';
                        ?>
                        <tr style="<?php echo $is_expiring ? 'background-color: #fff3cd;' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($c['emp_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($c['contract_type']); ?></td>
                            <td><?php echo $c['start_date']; ?></td>
                            <td><?php echo $c['end_date'] ?: 'مفتوح'; ?></td>
                            <td>
                                <?php if ($days_left !== null): ?>
                                    <span class="badge-fm <?php echo $is_expiring ? 'badge-amber' : 'badge-green'; ?>">
                                        <?php echo $c['status'] === 'active' ? $days_left . ' يوم' : '-'; ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_map = ['active' => ['badge-green', 'نشط'], 'expired' => ['badge-amber', 'منتهي'], 'terminated' => ['badge-red', 'ملغي']];
                                $cls = $status_map[$c['status']][0] ?? 'badge-gray';
                                $lbl = $status_map[$c['status']][1] ?? $c['status'];
                                ?>
                                <span class="badge-fm <?php echo $cls; ?>"><?php echo $lbl; ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($c['status'] === 'active'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="status" value="terminated">
                                        <button class="btn-fm btn-danger" onclick="return confirm('هل أنت متأكد من إلغاء هذا العقد؟');">إلغاء</button>
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

<div class="row">
    <div class="col-md-6">
        <div class="fm-card">
            <div class="fm-card-head"><span>➕ إضافة / تجديد عقد</span></div>
            <div class="fm-card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">الموظف</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">-- اختر الموظف --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">نوع العقد</label>
                        <select name="contract_type" class="form-select" required>
                            <option value="دائم">دائم</option>
                            <option value="مؤقت">مؤقت</option>
                            <option value="عقد مشروع">عقد مشروع</option>
                            <option value="تدريب">تدريب</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تاريخ البداية</label>
                        <input type="date" name="start_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تاريخ النهاية</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الراتب في العقد</label>
                        <input type="number" step="0.01" name="salary" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الشروط / ملاحظات</label>
                        <textarea name="terms" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn-fm btn-navy" style="width:100%;">حفظ العقد</button>
                </form>
            </div>
        </div>
    </div>
</div>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>