<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

// database.php exposes the connection through db(); keep a local PDO handle
// because this page uses direct prepared statements in several legacy handlers.
$pdo = db();

$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$emp_id = (int)($_GET['id'] ?? 0);
$message = '';
$msg_type = 'success';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit', 'suspend', 'activate'])) {
    try {
        if ($action === 'suspend') {
            $emp_id = (int)$_POST['employee_id'];
            $pdo->prepare("UPDATE employees SET status = 'suspended' WHERE id = ?")->execute([$emp_id]);
            $message = "تم إيقاف الموظف مؤقتاً";
            $action = 'list';
        } elseif ($action === 'activate') {
            $emp_id = (int)$_POST['employee_id'];
            $pdo->prepare("UPDATE employees SET status = 'active' WHERE id = ?")->execute([$emp_id]);
            $message = "تم تفعيل الموظف";
            $action = 'list';
        } else {
            $full_name = trim($_POST['full_name']);
            $employee_code = trim($_POST['employee_code']);
            $national_id = trim($_POST['national_id']);
            $birth_date = $_POST['birth_date'] ?: null;
            $gender = $_POST['gender'] ?: null;
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);
            $hire_date = $_POST['hire_date'];
            $department_id = $_POST['department_id'] ?: null;
            $position = trim($_POST['position']);
            $employment_type = $_POST['employment_type'];
            $work_mode = $_POST['work_mode'];
            $basic_salary = (float)$_POST['basic_salary'];
            $bank_account = trim($_POST['bank_account']);
            $status = $_POST['status'];
            $create_account = isset($_POST['create_account']);

            if ($action === 'add') {
                $user_id = null;
                if ($create_account) {
                    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $full_name));
                    $username = substr($username, 0, 20);
                    if (strlen($username) < 3) $username = $username . '00';
                    
                    $check = dbFetchOne("SELECT id FROM users WHERE username = ?", [$username]);
                    if (!$check) {
                        $pass_hash = password_hash('admin123', PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (role_id, username, password_hash, full_name, email, phone, department_id, is_active) VALUES (11, ?, ?, ?, ?, ?, ?, 1)");
                        $stmt->execute([$username, $pass_hash, $full_name, $email, $phone, $department_id]);
                        $user_id = $pdo->lastInsertId();
                    }
                }
                
                $stmt = $pdo->prepare("INSERT INTO employees (user_id, full_name, employee_code, national_id, birth_date, gender, phone, email, address, hire_date, department_id, position, employment_type, work_mode, basic_salary, bank_account, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $full_name, $employee_code, $national_id, $birth_date, $gender, $phone, $email, $address, $hire_date, $department_id, $position, $employment_type, $work_mode, $basic_salary, $bank_account, $status, Session::getUserID()]);
                $emp_id = $pdo->lastInsertId();
                $message = "تم إضافة الموظف بنجاح" . ($user_id ? " (تم إنشاء حساب نظامي له)" : "");
            } else {
                $stmt = $pdo->prepare("UPDATE employees SET full_name=?, employee_code=?, national_id=?, birth_date=?, gender=?, phone=?, email=?, address=?, hire_date=?, department_id=?, position=?, employment_type=?, work_mode=?, basic_salary=?, bank_account=?, status=? WHERE id=?");
                $stmt->execute([$full_name, $employee_code, $national_id, $birth_date, $gender, $phone, $email, $address, $hire_date, $department_id, $position, $employment_type, $work_mode, $basic_salary, $bank_account, $status, $emp_id]);
                $message = "تم تحديث بيانات الموظف بنجاح";
            }
            
            // Handle file upload
            if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../storage/hr_contracts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file = $_FILES['contract_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (in_array($ext, $allowed)) {
                    $new_name = 'contract_' . $emp_id . '_' . time() . '.' . $ext;
                    $upload_path = $upload_dir . $new_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $contract_file_path = 'storage/hr_contracts/' . $new_name;
                        $pdo->prepare("UPDATE employees SET contract_file_path = ? WHERE id = ?")->execute([$contract_file_path, $emp_id]);
                    }
                }
            }
            
            $action = 'list'; 
        }
    } catch (Exception $e) {
        $message = "خطأ في قاعدة البيانات: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Handle Delete (Admin Only)
if ($action === 'delete' && $emp_id > 0) {
    $currentUserRole = Session::getUserRole();
    if (in_array($currentUserRole, ['admin', 'sudo'])) {
        try {
            $pdo->prepare("UPDATE employees SET status = 'terminated' WHERE id = ?")->execute([$emp_id]);
            $message = "تم تغيير حالة الموظف إلى 'منهي الخدمة'";
        } catch (Exception $e) {
            $message = "خطأ: " . $e->getMessage();
            $msg_type = 'error';
        }
    } else {
        $message = "غير مصرح لك بهذا الإجراء";
        $msg_type = 'error';
    }
    $action = 'list';
}

$departments = dbFetchAll("SELECT id, name_ar FROM departments ORDER BY name_ar", []);
$employee = null;
$employees = [];

if ($action === 'edit' && $emp_id > 0) {
    $employee = dbFetchOne("SELECT * FROM employees WHERE id = ?", [$emp_id]);
} else {
    $search = $_GET['search'] ?? '';
    $sql = "SELECT e.*, d.name_ar as dept_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.full_name LIKE ? OR e.employee_code LIKE ? OR e.position LIKE ? ORDER BY e.id DESC";
    $employees = dbFetchAll($sql, ["%$search%", "%$search%", "%$search%"]);
}

$pageTitle = 'إدارة الموظفين';
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
.badge-blue { background: #d1ecf1; color: #0c5460; }
.btn-fm { display: inline-block; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 0.8rem; margin: 2px; }
.btn-navy { background: #1b4d8f; color: #fff; }
.btn-navy:hover { background: #143a6b; color: #fff; }
.btn-ghost { background: transparent; color: #1b4d8f; border: 1px solid #1b4d8f; }
.btn-ghost:hover { background: #1b4d8f; color: #fff; }
.btn-warning { background: #ffc107; color: #000; }
.btn-warning:hover { background: #e0a800; color: #000; }
.btn-success { background: #28a745; color: #fff; }
.btn-success:hover { background: #218838; color: #fff; }
.btn-danger-ghost { background: transparent; color: #dc3545; border: 1px solid #dc3545; }
.btn-danger-ghost:hover { background: #dc3545; color: #fff; }
.form-label { font-weight: 600; color: #495057; margin-bottom: 0.5rem; }
.form-control, .form-select { border-radius: 6px; border: 1px solid #ced4da; padding: 0.6rem 0.9rem; }
.form-control:focus, .form-select:focus { border-color: #1b4d8f; box-shadow: 0 0 0 0.2rem rgba(27, 77, 143, 0.25); }
</style>

<div class="fm-header">
    <h1><i class="fas fa-users me-2"></i> <?php echo $action === 'list' ? 'قائمة الموظفين' : ($action === 'add' ? 'إضافة موظف جديد' : 'تعديل بيانات موظف'); ?></h1>
    <p>إدارة كاملة لبيانات الموظفين وحساباتهم النظامية</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius: 8px;">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="fm-card">
        <div class="fm-card-body" style="display:flex; gap:10px; flex-wrap:wrap; justify-content:space-between; align-items:center;">
            <form method="GET" class="d-flex gap-2" style="flex-grow: 1; max-width: 500px;">
                <input type="text" name="search" class="form-control" placeholder="بحث بالاسم، الكود، أو المسمى الوظيفي..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button class="btn-fm btn-navy" type="submit"><i class="fas fa-search"></i></button>
            </form>
            <a href="?action=add" class="btn-fm btn-navy"><i class="fas fa-user-plus me-1"></i> إضافة موظف</a>
        </div>
    </div>

    <div class="fm-card">
        <div class="fm-card-head">
            <span>📋 سجل الموظفين</span>
            <span class="badge-fm badge-green"><?php echo count($employees); ?> موظف</span>
        </div>
        <div class="fm-card-body">
            <div class="table-responsive">
                <table class="fm-table">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الاسم الكامل</th>
                            <th>القسم</th>
                            <th>المسمى الوظيفي</th>
                            <th>نمط العمل</th>
                            <th>الراتب</th>
                            <th>الحالة</th>
                            <th class="text-end">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">لا يوجد موظفين حالياً.</td></tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($emp['employee_code']); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($emp['dept_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td><?php echo ['remote' => 'عن بُعد', 'onsite' => 'في المقر', 'hybrid' => 'مختلط'][$emp['work_mode']] ?? $emp['work_mode']; ?></td>
                                <td><?php echo number_format((float)($emp['basic_salary'] ?? 0), 2); ?></td>
                                <td>
                                    <?php
                                    $status_colors = ['active' => 'green', 'suspended' => 'amber', 'on_leave' => 'blue', 'terminated' => 'red'];
                                    $status_labels = ['active' => 'نشط', 'suspended' => 'موقوف مؤقتاً', 'on_leave' => 'في إجازة', 'terminated' => 'منهي الخدمة'];
                                    $color = $status_colors[$emp['status']] ?? 'gray';
                                    $label = $status_labels[$emp['status']] ?? $emp['status'];
                                    ?>
                                    <span class="badge-fm badge-<?php echo $color; ?>"><?php echo $label; ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="?action=edit&id=<?php echo $emp['id']; ?>" class="btn-fm btn-ghost" style="font-size:0.75rem; padding:4px 8px;" title="تعديل"><i class="fas fa-edit"></i></a>
                                    <?php 
                                    $currentUserRole = Session::getUserRole();
                                    $isAdmin = in_array($currentUserRole, ['admin', 'sudo']);
                                    
                                    if ($emp['status'] !== 'terminated'): 
                                        if ($emp['status'] === 'active'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="suspend">
                                                <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                                                <button class="btn-fm btn-warning" style="font-size:0.75rem; padding:4px 8px;" 
                                                        title="إيقاف مؤقت" onclick="return confirm('هل أنت متأكد من إيقاف هذا الموظف مؤقتاً؟');">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($emp['status'] === 'suspended'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                                                <button class="btn-fm btn-success" style="font-size:0.75rem; padding:4px 8px;" title="تفعيل">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                        <?php endif;
                                        
                                        if ($isAdmin): ?>
                                            <a href="?action=delete&id=<?php echo $emp['id']; ?>" 
                                               class="btn-fm btn-danger-ghost" 
                                               style="font-size:0.75rem; padding:4px 8px;" 
                                               title="حذف نهائي"
                                               onclick="return confirm('تحذير: هذا الإجراء نهائي ولا يمكن التراجع عنه. هل أنت متأكد؟');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif;
                                    endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="fm-card">
        <div class="fm-card-head">
            <span>📝 بيانات الموظف</span>
            <a href="employees.php" class="btn-fm btn-ghost" style="background:#fff; color:#1b4d8f; font-size:0.8rem">العودة للقائمة</a>
        </div>
        <div class="fm-card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($employee['full_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">كود الموظف <span class="text-danger">*</span></label>
                        <input type="text" name="employee_code" class="form-control" required value="<?php echo htmlspecialchars($employee['employee_code'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الرقم القومي</label>
                        <input type="text" name="national_id" class="form-control" value="<?php echo htmlspecialchars($employee['national_id'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ الميلاد</label>
                        <input type="date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($employee['birth_date'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">النوع</label>
                        <select name="gender" class="form-select">
                            <option value="">-- اختر --</option>
                            <option value="male" <?php echo ($employee['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>ذكر</option>
                            <option value="female" <?php echo ($employee['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>أنثى</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">العنوان</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($employee['address'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ التعيين <span class="text-danger">*</span></label>
                        <input type="date" name="hire_date" class="form-control" required value="<?php echo htmlspecialchars($employee['hire_date'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">القسم</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- اختر القسم --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($employee['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name_ar']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">المسمى الوظيفي <span class="text-danger">*</span></label>
                        <input type="text" name="position" class="form-control" required value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">نوع التوظيف</label>
                        <select name="employment_type" class="form-select">
                            <option value="full_time" <?php echo ($employee['employment_type'] ?? '') === 'full_time' ? 'selected' : ''; ?>>دوام كامل</option>
                            <option value="part_time" <?php echo ($employee['employment_type'] ?? '') === 'part_time' ? 'selected' : ''; ?>>دوام جزئي</option>
                            <option value="contract" <?php echo ($employee['employment_type'] ?? '') === 'contract' ? 'selected' : ''; ?>>عقد مؤقت</option>
                            <option value="volunteer" <?php echo ($employee['employment_type'] ?? '') === 'volunteer' ? 'selected' : ''; ?>>متطوع</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">نمط العمل</label>
                        <select name="work_mode" class="form-select">
                            <option value="remote" <?php echo ($employee['work_mode'] ?? '') === 'remote' ? 'selected' : ''; ?>>عن بُعد</option>
                            <option value="onsite" <?php echo ($employee['work_mode'] ?? '') === 'onsite' ? 'selected' : ''; ?>>في المقر</option>
                            <option value="hybrid" <?php echo ($employee['work_mode'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>مختلط</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الراتب الأساسي</label>
                        <input type="number" step="0.01" name="basic_salary" class="form-control" value="<?php echo htmlspecialchars($employee['basic_salary'] ?? '0.00'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">رقم الحساب البنكي</label>
                        <input type="text" name="bank_account" class="form-control" value="<?php echo htmlspecialchars($employee['bank_account'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo ($employee['status'] ?? '') === 'active' ? 'selected' : ''; ?>>نشط</option>
                            <option value="suspended" <?php echo ($employee['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>موقوف</option>
                            <option value="on_leave" <?php echo ($employee['status'] ?? '') === 'on_leave' ? 'selected' : ''; ?>>إجازة</option>
                            <option value="terminated" <?php echo ($employee['status'] ?? '') === 'terminated' ? 'selected' : ''; ?>>منهي الخدمة</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">صورة العقد (PDF/صورة)</label>
                        <input type="file" name="contract_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">ارفع نسخة من العقد الموقع</small>
                        <?php if ($employee && $employee['contract_file_path']): ?>
                            <div class="mt-2">
                                <a href="<?php echo APP_URL . $employee['contract_file_path']; ?>" target="_blank" class="btn-fm btn-ghost btn-sm">
                                    <i class="fas fa-file-alt me-1"></i> عرض العقد الحالي
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($action === 'add'): ?>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_account" id="create_account">
                            <label class="form-check-label" for="create_account">إنشاء حساب نظامي للموظف</label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn-fm btn-navy"><i class="fas fa-save me-1"></i> <?php echo $action === 'add' ? 'إضافة الموظف' : 'حفظ التعديلات'; ?></button>
                    <a href="employees.php" class="btn-fm btn-ghost">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
