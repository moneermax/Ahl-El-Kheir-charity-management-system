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

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_status') {
            $id = (int)$_POST['payroll_id'];
            $status = $_POST['status'];
            $payment_date = $status === 'paid' ? date('Y-m-d') : null;
            $pdo->prepare("UPDATE payroll SET status = ?, payment_date = ? WHERE id = ?")->execute([$status, $payment_date, $id]);
            $message = "تم تحديث حالة الراتب";
        }
    } catch (Exception $e) {
        $message = "خطأ: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Fetch Data
$payrolls = dbFetchAll("SELECT p.*, e.full_name as emp_name FROM payroll p JOIN employees e ON p.employee_id = e.id ORDER BY p.year DESC, p.month DESC, p.id DESC", []);

$pageTitle = 'كشف الرواتب';
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
.badge-blue { background: #d1ecf1; color: #0c5460; }
.btn-fm { display: inline-block; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 0.8rem; margin: 2px; }
.btn-navy { background: #1b4d8f; color: #fff; }
.btn-navy:hover { background: #143a6b; color: #fff; }
.btn-success { background: #28a745; color: #fff; }
.btn-success:hover { background: #218838; color: #fff; }
</style>

<div class="fm-header">
    <h1><i class="fas fa-money-bill-wave me-2"></i> كشف الرواتب</h1>
    <p>إدارة رواتب الموظفين الشهرية</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" style="border-radius: 8px;">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="fm-card">
    <div class="fm-card-head">
        <span>📋 سجل الرواتب</span>
        <span class="badge-fm badge-blue"><?php echo count($payrolls); ?> سجل</span>
    </div>
    <div class="fm-card-body">
        <div class="table-responsive">
            <table class="fm-table">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>الشهر / السنة</th>
                        <th>الأساسي</th>
                        <th>الإضافي</th>
                        <th>الخصومات</th>
                        <th>الصافي</th>
                        <th>الحالة</th>
                        <th class="text-end">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payrolls)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">لا توجد سجلات رواتب.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payrolls as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['emp_name']); ?></strong></td>
                            <td><?php echo $p['month']; ?> / <?php echo $p['year']; ?></td>
                            <td><?php echo number_format($p['basic_salary'], 0); ?></td>
                            <td class="text-success">+<?php echo number_format($p['allowances'] + $p['overtime'], 0); ?></td>
                            <td class="text-danger">-<?php echo number_format($p['deductions'], 0); ?></td>
                            <td><strong><?php echo number_format($p['net_salary'], 0); ?></strong></td>
                            <td>
                                <?php
                                $status_map = ['draft' => ['badge-amber', 'مسودة'], 'approved' => ['badge-blue', 'معتمد'], 'paid' => ['badge-green', 'مدفوع']];
                                $cls = $status_map[$p['status']][0] ?? 'badge-gray';
                                $lbl = $status_map[$p['status']][1] ?? $p['status'];
                                ?>
                                <span class="badge-fm <?php echo $cls; ?>"><?php echo $lbl; ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($p['status'] === 'draft'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="payroll_id" value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button class="btn-fm btn-navy">اعتماد</button>
                                    </form>
                                <?php elseif ($p['status'] === 'approved'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="payroll_id" value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="status" value="paid">
                                        <button class="btn-fm btn-success">صرف</button>
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