<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_payroll_policy.php';

Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pdo = db();
$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        }
        if (($_POST['action'] ?? '') !== 'create_policy') {
            throw new InvalidArgumentException('الإجراء غير صالح.');
        }

        $policy = hrPayrollPolicyValidate($_POST);
        $effective = $policy['effective_from'];
        $today = date('Y-m-d');
        if ($effective <= $today) {
            throw new InvalidArgumentException('لا يمكن إنشاء نسخة جديدة بتاريخ سريان اليوم أو تاريخ سابق. استخدم تاريخاً مستقبلياً.');
        }

        $pdo->beginTransaction();
        try {
            $existing = dbFetchOne(
                "SELECT id, version_no, effective_from FROM hr_payroll_policy_versions
                 WHERE effective_from = ?
                 LIMIT 1",
                [$effective]
            );
            if ($existing) {
                throw new InvalidArgumentException('يوجد إصدار سياسة بنفس تاريخ السريان بالفعل.');
            }

            $next = dbFetchOne("SELECT COALESCE(MAX(version_no), 0) + 1 AS next_version FROM hr_payroll_policy_versions");
            $versionNo = (int)$next['next_version'];
            $stmt = $pdo->prepare(
                "INSERT INTO hr_payroll_policy_versions
                 (version_no, effective_from, absence_enabled, absence_deduction_percent,
                  unpaid_leave_enabled, unpaid_leave_deduction_percent, paid_leave_deduction_percent,
                  late_enabled, early_departure_enabled, overtime_enabled, overtime_multiplier,
                  daily_deduction_method, rounding_decimals, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $versionNo, $policy['effective_from'], $policy['absence_enabled'],
                $policy['absence_deduction_percent'], $policy['unpaid_leave_enabled'],
                $policy['unpaid_leave_deduction_percent'], $policy['paid_leave_deduction_percent'],
                $policy['late_enabled'], $policy['early_departure_enabled'], $policy['overtime_enabled'],
                $policy['overtime_multiplier'], $policy['daily_deduction_method'],
                $policy['rounding_decimals'], $policy['notes'], Session::getUserID()
            ]);
            $pdo->commit();
            $message = 'تم إنشاء إصدار سياسة الرواتب رقم ' . $versionNo . ' بتاريخ سريان ' . e($effective) . '. لم يتم تعديل أي مسير رواتب سابق.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$todayPolicy = hrPayrollPolicyGetActive($pdo);
$policies = hrPayrollPolicyGetAll($pdo);
$pageTitle = 'إعدادات وسياسات الرواتب';
$active = 'payroll_policy';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper" style="margin-right:250px;padding:20px;background:#f4f6f9;min-height:100vh;">
    <div class="ak-card p-4 mb-4" style="border-right:4px solid #1b4d8f;">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h4 class="mb-1 fw-bold" style="color:#1b4d8f;"><i class="fas fa-sliders-h me-2"></i>إعدادات وسياسات الرواتب</h4>
                <p class="text-muted mb-0">المرحلة الأولى: تعريف السياسات وإصداراتها وتواريخ سريانها فقط. لا يتم احتساب الخصومات أو الإضافات من هذه الصفحة.</p>
            </div>
            <span class="badge bg-warning text-dark p-2">الإعدادات الافتراضية — يجب اعتمادها من إدارة الجمعية</span>
        </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo e($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error); ?></div><?php endif; ?>

    <div class="ak-card p-4 mb-4">
        <h5 class="fw-bold mb-3" style="color:#1b4d8f;">السياسة السارية حالياً</h5>
        <?php if ($todayPolicy): ?>
            <div class="row g-3">
                <div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">الإصدار</small><strong>V<?php echo (int)$todayPolicy['version_no']; ?></strong></div></div>
                <div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">سارية من</small><strong><?php echo e($todayPolicy['effective_from']); ?></strong></div></div>
                <div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">الغياب</small><strong><?php echo (int)$todayPolicy['absence_enabled'] ? 'مفعل' : 'معطل'; ?> — <?php echo number_format((float)$todayPolicy['absence_deduction_percent'], 2); ?>%</strong></div></div>
                <div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">الإجازة غير المدفوعة</small><strong><?php echo (int)$todayPolicy['unpaid_leave_enabled'] ? 'مفعل' : 'معطل'; ?> — <?php echo number_format((float)$todayPolicy['unpaid_leave_deduction_percent'], 2); ?>%</strong></div></div>
            </div>
        <?php else: ?><div class="alert alert-warning mb-0">لا توجد سياسة سارية حتى تاريخ اليوم.</div><?php endif; ?>
    </div>

    <div class="ak-card p-4 mb-4">
        <h5 class="fw-bold mb-3" style="color:#1b4d8f;">إنشاء إصدار سياسة مستقبلي</h5>
        <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>الإصدار الفعال لا يُعدّل ولا يُحذف. أي تغيير بعد سريانه يجب أن يكون إصداراً جديداً بتاريخ سريان جديد.</div>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create_policy">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label fw-bold">تاريخ السريان *</label><input type="date" name="effective_from" class="form-control" min="<?php echo e(date('Y-m-d', strtotime('+1 day'))); ?>" required></div>
                <div class="col-md-3"><label class="form-label fw-bold">خصم الغياب %</label><input type="number" name="absence_deduction_percent" class="form-control" min="0" max="100" step="0.0001" value="100"></div>
                <div class="col-md-3"><label class="form-label fw-bold">خصم الإجازة غير المدفوعة %</label><input type="number" name="unpaid_leave_deduction_percent" class="form-control" min="0" max="100" step="0.0001" value="100"></div>
                <div class="col-md-3"><label class="form-label fw-bold">خصم الإجازة المدفوعة %</label><input type="number" name="paid_leave_deduction_percent" class="form-control" min="0" max="100" step="0.0001" value="0"></div>
                <div class="col-md-3"><label class="form-label fw-bold">معامل العمل الإضافي</label><input type="number" name="overtime_multiplier" class="form-control" min="0.0001" step="0.0001" value="1"></div>
                <div class="col-md-3"><label class="form-label fw-bold">التقريب (منازل عشرية)</label><input type="number" name="rounding_decimals" class="form-control" min="0" max="4" step="1" value="2"></div>
                <div class="col-md-6 d-flex align-items-end gap-3 flex-wrap">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="absence_enabled" checked> <span class="form-check-label">تفعيل خصم الغياب</span></label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="unpaid_leave_enabled" checked> <span class="form-check-label">تفعيل خصم الإجازة غير المدفوعة</span></label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="late_enabled"> <span class="form-check-label">تفعيل التأخير</span></label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="early_departure_enabled"> <span class="form-check-label">تفعيل الانصراف المبكر</span></label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="overtime_enabled"> <span class="form-check-label">تفعيل العمل الإضافي</span></label>
                </div>
                <div class="col-12"><label class="form-label fw-bold">ملاحظات</label><textarea name="notes" class="form-control" rows="2" maxlength="1000">الإعدادات الافتراضية — يجب اعتمادها من إدارة الجمعية</textarea></div>
                <div class="col-12"><button class="btn btn-primary"><i class="fas fa-plus me-1"></i>إنشاء إصدار السياسة</button></div>
            </div>
        </form>
    </div>

    <div class="ak-card p-4">
        <h5 class="fw-bold mb-3" style="color:#1b4d8f;">سجل إصدارات السياسات</h5>
        <div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>الإصدار</th><th>ساري من</th><th>الغياب</th><th>غير المدفوع</th><th>المدفوع</th><th>التأخير</th><th>الانصراف المبكر</th><th>الإضافي</th><th>التقريب</th><th>الحالة</th></tr></thead><tbody>
        <?php foreach ($policies as $p): $effective = $p['effective_from'] <= date('Y-m-d'); ?>
            <tr>
                <td class="fw-bold">V<?php echo (int)$p['version_no']; ?></td>
                <td><?php echo e($p['effective_from']); ?></td>
                <td><?php echo (int)$p['absence_enabled'] ? 'مفعل' : 'معطل'; ?> / <?php echo number_format((float)$p['absence_deduction_percent'],2); ?>%</td>
                <td><?php echo (int)$p['unpaid_leave_enabled'] ? 'مفعل' : 'معطل'; ?> / <?php echo number_format((float)$p['unpaid_leave_deduction_percent'],2); ?>%</td>
                <td><?php echo number_format((float)$p['paid_leave_deduction_percent'],2); ?>%</td>
                <td><?php echo (int)$p['late_enabled'] ? 'مفعل' : 'معطل'; ?></td>
                <td><?php echo (int)$p['early_departure_enabled'] ? 'مفعل' : 'معطل'; ?></td>
                <td><?php echo (int)$p['overtime_enabled'] ? 'مفعل' : 'معطل'; ?> / <?php echo number_format((float)$p['overtime_multiplier'],2); ?></td>
                <td><?php echo (int)$p['rounding_decimals']; ?></td>
                <td><?php if ($effective): ?><span class="badge bg-success">سارية — غير قابلة للتعديل</span><?php else: ?><span class="badge bg-info text-dark">مستقبلية</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>

<style>.ak-card{border:none;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.05);background:#fff}.form-check{margin-bottom:0}.table th{white-space:nowrap}</style>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
