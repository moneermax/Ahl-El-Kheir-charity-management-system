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
        if ($effective <= date('Y-m-d')) {
            throw new InvalidArgumentException('لا يمكن إنشاء نسخة جديدة بتاريخ سريان اليوم أو تاريخ سابق. استخدم تاريخاً مستقبلياً.');
        }

        $pdo->beginTransaction();
        try {
            $existing = dbFetchOne(
                "SELECT id FROM hr_payroll_policy_versions WHERE effective_from = ? LIMIT 1",
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
$nextPolicy = dbFetchOne(
    "SELECT * FROM hr_payroll_policy_versions WHERE effective_from > ? ORDER BY effective_from ASC, version_no ASC LIMIT 1",
    [date('Y-m-d')]
);
$pageTitle = 'إعدادات وسياسات الرواتب';
$active = 'payroll_policy';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.hr-policy{max-width:1450px;margin:0 auto}.hr-policy .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:22px 25px;margin-bottom:16px}.hr-policy .hero-row{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.hr-policy h1{font-size:1.35rem;font-weight:800;margin:0}.hr-policy .hero p{font-size:.74rem;margin:6px 0 0;opacity:.9}.hr-policy .notice{background:#fff3cd;color:#664d03;border:1px solid #ffecb5;border-radius:9px;padding:8px 12px;font-size:.72rem;font-weight:800;white-space:nowrap}.hr-policy .card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;box-shadow:0 2px 12px rgba(16,24,40,.05);margin-bottom:15px;overflow:hidden}.hr-policy .card-head{padding:13px 17px;border-bottom:1px solid #edf0f3;display:flex;align-items:center;justify-content:space-between;gap:10px}.hr-policy .card-head h2{font-size:.9rem;font-weight:800;color:#173f73;margin:0}.hr-policy .card-body{padding:16px}.hr-policy .current-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.hr-policy .metric{border:1px solid #e5eaf0;border-radius:9px;padding:11px 13px;background:#fafbfd}.hr-policy .metric small{display:block;color:#667085;font-size:.65rem;font-weight:700}.hr-policy .metric strong{display:block;color:#173f73;font-size:.92rem;margin-top:3px}.hr-policy .scheduled{background:#eef7ff;border:1px solid #cfe4f8;color:#174f80;border-radius:9px;padding:10px 12px;font-size:.72rem}.hr-policy .form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.hr-policy label{display:block;font-size:.69rem;font-weight:800;color:#475467;margin-bottom:5px}.hr-policy input,.hr-policy textarea{width:100%;border:1px solid #d5dce5;border-radius:8px;padding:8px 9px;font-size:.73rem;background:#fff}.hr-policy .check-grid{display:flex;gap:14px;flex-wrap:wrap;align-items:center;padding-top:22px}.hr-policy .check{display:flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;color:#344054}.hr-policy .check input{width:auto}.hr-policy .full{grid-column:1/-1}.hr-policy .info{background:#f8fafc;border:1px solid #e7ebf0;border-radius:9px;padding:9px 11px;color:#667085;font-size:.68rem;margin-bottom:13px}.hr-policy .btn-main{border:0;background:#173f73;color:#fff;border-radius:8px;padding:9px 14px;font-size:.72rem;font-weight:800}.hr-policy .table-wrap{overflow-x:auto}.hr-policy table{width:100%;border-collapse:collapse}.hr-policy th{background:#f7f9fb;color:#526071;font-size:.65rem;padding:9px 8px;text-align:right;white-space:nowrap}.hr-policy td{border-top:1px solid #edf0f3;padding:9px 8px;font-size:.68rem;color:#344054;white-space:nowrap}.hr-policy .badge{display:inline-block;padding:4px 7px;border-radius:999px;font-size:.61rem;font-weight:800}.hr-policy .b-active{background:#e9f8ee;color:#187a35}.hr-policy .b-future{background:#e8f4ff;color:#17608f}.hr-policy .empty{padding:25px;text-align:center;color:#98a2b3;font-size:.72rem}@media(max-width:1000px){.hr-policy .current-grid,.hr-policy .form-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.hr-policy .current-grid,.hr-policy .form-grid{grid-template-columns:1fr}.hr-policy .hero{padding:17px}.hr-policy .notice{white-space:normal}}
</style>

<div class="hr-policy">
    <section class="hero">
        <div class="hero-row">
            <div>
                <h1><i class="fas fa-sliders-h me-2"></i>إعدادات وسياسات الرواتب</h1>
                <p>المرحلة الأولى: تعريف السياسات وإصداراتها وتواريخ سريانها فقط. لا يتم احتساب الخصومات أو الإضافات من هذه الصفحة.</p>
            </div>
            <div class="notice">الإعدادات الافتراضية — يجب اعتمادها من إدارة الجمعية</div>
        </div>
    </section>

    <?php if ($message): ?><div class="alert alert-success py-2 px-3" style="font-size:.73rem;border-radius:8px;"><?php echo e($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2 px-3" style="font-size:.73rem;border-radius:8px;"><?php echo e($error); ?></div><?php endif; ?>

    <section class="card">
        <div class="card-head"><h2>السياسة السارية حالياً</h2></div>
        <div class="card-body">
            <?php if ($todayPolicy): ?>
                <div class="current-grid">
                    <div class="metric"><small>الإصدار</small><strong>V<?php echo (int)$todayPolicy['version_no']; ?></strong></div>
                    <div class="metric"><small>سارية من</small><strong><?php echo e($todayPolicy['effective_from']); ?></strong></div>
                    <div class="metric"><small>الغياب</small><strong><?php echo (int)$todayPolicy['absence_enabled'] ? 'مفعل' : 'معطل'; ?> — <?php echo number_format((float)$todayPolicy['absence_deduction_percent'],2); ?>%</strong></div>
                    <div class="metric"><small>الإجازة غير المدفوعة</small><strong><?php echo (int)$todayPolicy['unpaid_leave_enabled'] ? 'مفعل' : 'معطل'; ?> — <?php echo number_format((float)$todayPolicy['unpaid_leave_deduction_percent'],2); ?>%</strong></div>
                </div>
            <?php else: ?>
                <div class="scheduled"><i class="fas fa-calendar-check me-1"></i>لا توجد سياسة سارية حتى تاريخ اليوم. <?php if ($nextPolicy): ?>أقرب إصدار مجدول هو <strong>V<?php echo (int)$nextPolicy['version_no']; ?></strong> بتاريخ <strong><?php echo e($nextPolicy['effective_from']); ?></strong>.<?php endif; ?></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2>إنشاء إصدار سياسة مستقبلي</h2></div>
        <div class="card-body">
            <div class="info"><i class="fas fa-info-circle me-1"></i>الإصدار الفعال لا يُعدّل ولا يُحذف. أي تغيير بعد سريانه يجب أن يكون إصداراً جديداً بتاريخ سريان جديد.</div>
            <form method="post">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="create_policy">
                <div class="form-grid">
                    <div><label>تاريخ السريان *</label><input type="date" name="effective_from" min="<?php echo e(date('Y-m-d', strtotime('+1 day'))); ?>" required></div>
                    <div><label>خصم الغياب %</label><input type="number" name="absence_deduction_percent" min="0" max="100" step="0.0001" value="100"></div>
                    <div><label>خصم الإجازة غير المدفوعة %</label><input type="number" name="unpaid_leave_deduction_percent" min="0" max="100" step="0.0001" value="100"></div>
                    <div><label>خصم الإجازة المدفوعة %</label><input type="number" name="paid_leave_deduction_percent" min="0" max="100" step="0.0001" value="0"></div>
                    <div><label>معامل العمل الإضافي</label><input type="number" name="overtime_multiplier" min="0.0001" step="0.0001" value="1"></div>
                    <div><label>التقريب (منازل عشرية)</label><input type="number" name="rounding_decimals" min="0" max="4" step="1" value="2"></div>
                    <div class="check-grid full">
                        <label class="check"><input type="checkbox" name="absence_enabled" checked> تفعيل خصم الغياب</label>
                        <label class="check"><input type="checkbox" name="unpaid_leave_enabled" checked> تفعيل خصم الإجازة غير المدفوعة</label>
                        <label class="check"><input type="checkbox" name="late_enabled"> تفعيل التأخير</label>
                        <label class="check"><input type="checkbox" name="early_departure_enabled"> تفعيل الانصراف المبكر</label>
                        <label class="check"><input type="checkbox" name="overtime_enabled"> تفعيل العمل الإضافي</label>
                    </div>
                    <div class="full"><label>ملاحظات</label><textarea name="notes" rows="2" maxlength="1000">الإعدادات الافتراضية — يجب اعتمادها من إدارة الجمعية</textarea></div>
                    <div class="full"><button type="submit" class="btn-main"><i class="fas fa-plus me-1"></i>إنشاء إصدار السياسة</button></div>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2>سجل إصدارات السياسات</h2></div>
        <div class="table-wrap"><table><thead><tr><th>الإصدار</th><th>ساري من</th><th>الغياب</th><th>غير المدفوع</th><th>المدفوع</th><th>التأخير</th><th>الانصراف المبكر</th><th>الإضافي</th><th>التقريب</th><th>الحالة</th></tr></thead><tbody>
        <?php if (!$policies): ?><tr><td colspan="10" class="empty">لا توجد إصدارات سياسة.</td></tr><?php endif; ?>
        <?php foreach ($policies as $p): $effective = $p['effective_from'] <= date('Y-m-d'); ?>
            <tr>
                <td><strong>V<?php echo (int)$p['version_no']; ?></strong></td>
                <td><?php echo e($p['effective_from']); ?></td>
                <td><?php echo (int)$p['absence_enabled'] ? 'مفعل' : 'معطل'; ?> / <?php echo number_format((float)$p['absence_deduction_percent'],2); ?>%</td>
                <td><?php echo (int)$p['unpaid_leave_enabled'] ? 'مفعل' : 'معطل'; ?> / <?php echo number_format((float)$p['unpaid_leave_deduction_percent'],2); ?>%</td>
                <td><?php echo number_format((float)$p['paid_leave_deduction_percent'],2); ?>%</td>
                <td><?php echo (int)$p['late_enabled'] ? 'مفعل' : 'معطل'; ?></td>
                <td><?php echo (int)$p['early_departure_enabled'] ? 'مفعل' : 'معطل'; ?></td>
                <td><?php echo (int)$p['overtime_enabled'] ? 'مفعل' : 'معطل'; ?> / <?php echo number_format((float)$p['overtime_multiplier'],2); ?></td>
                <td><?php echo (int)$p['rounding_decimals']; ?></td>
                <td><?php if ($effective): ?><span class="badge b-active">سارية — غير قابلة للتعديل</span><?php else: ?><span class="badge b-future">مستقبلية</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>


<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="modules/hr/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
