<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$user_id = Session::getUserId();
$message = '';
$msg_type = 'success';

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
        $message = t('hr.request_submitted');
    } catch (Exception $e) {
        $message = t('hr.leave_request_error', ['message' => $e->getMessage()]);
        $msg_type = 'error';
    }
}

$pageTitle = t('hr.leave_request_title');
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
    <h1><i class="fas fa-calendar-plus me-2"></i> <?php echo e(t('hr.leave_request_title')); ?></h1>
    <p><?php echo e(t('hr.leave_request_intro')); ?></p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius: 8px;">
        <?php echo e($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$employee): ?>
    <div class="fm-card">
        <div class="fm-card-body text-center py-5">
            <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
            <h4><?php echo e(t('hr.no_employee_profile')); ?></h4>
            <p class="text-muted"><?php echo e(t('hr.contact_hr')); ?></p>
            <a href="<?php echo APP_URL; ?>modules/users/profile.php" class="btn-fm btn-navy"><?php echo e(t('hr.back_to_profile')); ?></a>
        </div>
    </div>
<?php else: ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="fm-card">
                <div class="fm-card-head">
                    <span>📝 <?php echo e(t('hr.request_data')); ?></span>
                    <span class="badge bg-light text-dark"><?php echo e(t('hr.employee_label')); ?>: <?php echo e($employee['full_name']); ?></span>
                </div>
                <div class="fm-card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label"><?php echo e(t('hr.leave_type_label')); ?> <span class="text-danger">*</span></label>
                            <select name="leave_type" class="form-select" required>
                                <option value="annual"><?php echo e(t('hr.leave_type_annual')); ?></option>
                                <option value="sick"><?php echo e(t('hr.leave_type_sick')); ?></option>
                                <option value="emergency"><?php echo e(t('hr.leave_type_emergency')); ?></option>
                                <option value="unpaid"><?php echo e(t('hr.leave_type_unpaid')); ?></option>
                                <option value="remote_work_request"><?php echo e(t('hr.leave_type_remote_work')); ?></option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php echo e(t('hr.from_date')); ?> <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo e(t('hr.to_date')); ?> <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label"><?php echo e(t('hr.leave_reason')); ?> <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="4" placeholder="<?php echo e(t('hr.leave_reason_placeholder')); ?>" required></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-fm btn-navy"><i class="fas fa-paper-plane me-1"></i> <?php echo e(t('hr.submit_request')); ?></button>
                            <a href="<?php echo APP_URL; ?>modules/hr/leaves.php" class="btn-fm" style="background:#e9ecef; color:#495057;"><i class="fas fa-arrow-right me-1"></i> <?php echo e(t('hr.my_requests')); ?></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
