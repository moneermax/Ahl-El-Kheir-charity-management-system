<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

// Any logged-in user can access this page
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$user_id = Session::getUserId();
$message = '';
$msg_type = 'success';

// Find the employee record linked to this user account
$employee = dbFetchOne("SELECT id, full_name FROM employees WHERE user_id = ?", [$user_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $employee) {
    try {
        $type = $_POST['leave_type'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $reason = trim($_POST['reason']);
        
        $d1 = new DateTime($start);
        $d2 = new DateTime($end);
        $days = $d1->diff($d2)->days + 1;

        $sql = "INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days_count, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $pdo->prepare($sql)->execute([$employee['id'], $type, $start, $end, $days, $reason]);
        $message = "تم تقديم طلب الإجازة بنجاح وبانتظار اعتماد المدير.";
    } catch (Exception $e) {
        $message = "خطأ: " . $e->getMessage();
        $msg_type = 'error';
    }
}

$pageTitle = 'طلب إجازة';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.fm-header { background: linear-gradient(135deg, #1b4d8f 0%, #2c5aa0 100%); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fm-header h1 { margin: 0; font-size: 1.8rem; }
.fm-header p { margin: 5px 0 0; opacity: 0.9; }
.fm-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 20px; overflow: hidden; }
.fm-card-head { background: #1b4d8f; color: #fff; padding: 12px 18px; font-weight: 700; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; }
.fm-card-body { padding: 20px; }
.form-label { font-weight: 600; color: #495057; margin-bottom: 0.5rem; }
.form-control, .form-select { border-radius: 6px; border: 1px solid #ced4da; padding: 0.6rem 0.9rem; }
.form-control:focus, .form-select:focus { border-color: #1b4d8f; box-shadow: 0 0 0 0.2rem rgba(27, 77, 143, 0.25); }
.btn-fm { display: inline-block; padding: 10px 24px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 1rem; }
.btn-navy { background: #1b4d8f; color: #fff; }
.btn-navy:hover { background: #143a6b; color: #fff; }
</style>

<div class="fm-header">
    <h1><i class="fas fa-calendar-plus me-2"></i> تقديم طلب إجازة</h1>
    <p>يمكنك تقديم طلب إجازة جديد وسيتم تحويله تلقائياً لمديرك المباشر للاعتماد</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius: 8px;">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$employee): ?>
    <div class="fm-card">
        <div class="fm-card-body text-center py-5">
            <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
            <h4>عذراً، لا يوجد ملف موظف مرتبط بحسابك</h4>
            <p class="text-muted">يرجى التواصل مع إدارة الموارد البشرية لربط حسابك ببيانات الموظف.</p>
            <a href="<?php echo APP_URL; ?>modules/users/profile.php" class="btn-fm btn-navy">العودة للملف الشخصي</a>
        </div>
    </div>
<?php else: ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="fm-card">
                <div class="fm-card-head">
                    <span>📝 بيانات الطلب</span>
                    <span class="badge bg-light text-dark">الموظف: <?php echo e($employee['full_name']); ?></span>
                </div>
                <div class="fm-card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">نوع الإجازة <span class="text-danger">*</span></label>
                            <select name="leave_type" class="form-select" required>
                                <option value="annual">سنوية</option>
                                <option value="sick">مرضية</option>
                                <option value="emergency">طارئة</option>
                                <option value="unpaid">بدون راتب</option>
                                <option value="remote_work_request">طلب عمل عن بُعد</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">من تاريخ <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">إلى تاريخ <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">سبب الإجازة <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="4" placeholder="اشرح سبب الطلب باختصار..." required></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-fm btn-navy">
                                <i class="fas fa-paper-plane me-1"></i> إرسال الطلب
                            </button>
                            <a href="<?php echo APP_URL; ?>modules/hr/leaves.php" class="btn-fm" style="background:#e9ecef; color:#495057;">
                                <i class="fas fa-arrow-right me-1"></i> متابعة طلباتي
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>