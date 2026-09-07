<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_contract_salary.php';

Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pdo = db();
$message = '';
$msgType = 'success';

// Audit layer for payroll reversals. The original payroll remains paid and immutable.
dbExecute("CREATE TABLE IF NOT EXISTS hr_payroll_reversals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_id INT UNSIGNED NOT NULL,
    original_entry_id INT UNSIGNED NOT NULL,
    reversal_entry_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    reversed_by INT UNSIGNED NULL,
    reversed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hr_payroll_reversal_payroll (payroll_id),
    KEY idx_hr_payroll_reversal_entry (reversal_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reverse') {
        $payrollId = (int)($_POST['payroll_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($payrollId <= 0) throw new RuntimeException('سجل مسير الراتب غير صالح.');
        if ($reason === '') throw new RuntimeException('سبب العكس مطلوب لأغراض التدقيق المحاسبي.');
        if (mb_strlen($reason) > 255) throw new RuntimeException('سبب العكس طويل جداً.');

        $payroll = dbFetchOne("SELECT p.*, e.full_name AS employee_name, e.employee_code
                               FROM payroll p
                               JOIN employees e ON e.id = p.employee_id
                               WHERE p.id = ? LIMIT 1", [$payrollId]);
        if (!$payroll) throw new RuntimeException('سجل مسير الراتب غير موجود.');
        if ($payroll['status'] !== 'paid') throw new RuntimeException('لا يمكن عكس مسير غير مصروف.');

        $audit = dbFetchOne("SELECT * FROM hr_payroll_reversals WHERE payroll_id = ? LIMIT 1", [$payrollId]);
        if ($audit) throw new RuntimeException('تم عكس هذا المسير مسبقاً ولا يمكن إنشاء عكس ثانٍ.');

        $original = dbFetchOne("SELECT id FROM journal_entries
                               WHERE reference_type = 'payroll' AND reference_id = ? AND status = 'posted'
                               ORDER BY id ASC LIMIT 1", [$payrollId]);
        if (!$original) throw new RuntimeException('لا يوجد قيد محاسبي أصلي مصروف مرتبط بهذا المسير.');

        $pdo->beginTransaction();
        try {
            $reversalEntryId = hrReversePaidPayroll($payrollId, (int)Session::getUserId(), $reason);
            $pdo->prepare("INSERT INTO hr_payroll_reversals
                (payroll_id, original_entry_id, reversal_entry_id, reason, reversed_by)
                VALUES (?, ?, ?, ?, ?)")
                ->execute([$payrollId, (int)$original['id'], $reversalEntryId, $reason, (int)Session::getUserId()]);
            $pdo->commit();
            $message = 'تم عكس القيد المحاسبي لمسير الراتب بنجاح. بقي سجل المسير الأصلي محفوظاً وغير قابل للتعديل.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
} catch (Throwable $e) {
    $message = 'خطأ: ' . $e->getMessage();
    $msgType = 'error';
}

$rows = dbFetchAll("SELECT p.id, p.month, p.year, p.basic_salary, p.allowances, p.overtime, p.deductions,
                           p.net_salary, p.status, p.payment_date,
                           e.employee_code, e.full_name AS employee_name,
                           r.reversal_entry_id, r.reason AS reversal_reason, r.reversed_at
                    FROM payroll p
                    JOIN employees e ON e.id = p.employee_id
                    LEFT JOIN hr_payroll_reversals r ON r.payroll_id = p.id
                    WHERE p.status = 'paid'
                    ORDER BY p.year DESC, p.month DESC, e.full_name ASC");

$pageTitle = 'عكس مسيرات الرواتب';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.prr-wrap{max-width:1450px;margin:auto}.prr-header{background:linear-gradient(135deg,#7f1d1d,#991b1b);color:#fff;padding:22px;border-radius:12px;margin-bottom:18px}.prr-header h1{margin:0;font-size:1.55rem}.prr-header p{margin:5px 0 0;opacity:.9}.prr-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden}.prr-head{padding:14px 18px;border-bottom:1px solid #e9ecef}.prr-body{padding:18px}.prr-table{width:100%;border-collapse:collapse}.prr-table th,.prr-table td{padding:10px;border-bottom:1px solid #edf0f2;text-align:right;vertical-align:middle;font-size:.84rem}.prr-table th{background:#f8f9fa;font-weight:800;white-space:nowrap}.prr-btn{border:0;border-radius:7px;padding:7px 11px;font-size:.76rem;font-weight:800;cursor:pointer}.prr-danger{background:#991b1b;color:#fff}.prr-done{background:#e2e3e5;color:#495057}.prr-muted{font-size:.72rem;color:#6c757d}.prr-locked{background:#fff4f4;border-right:3px solid #991b1b;padding:10px 13px;border-radius:7px;color:#5b1a1a;font-size:.82rem}
</style>
<div class="prr-wrap">
    <div class="prr-header">
        <h1><i class="fas fa-rotate-left me-2"></i> عكس مسيرات الرواتب</h1>
        <p>إجراء تصحيحي محاسبي مستقل — لا يعيد فتح المسير الأصلي ولا يغيّر بيانات الصرف التاريخية.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $msgType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show">
            <i class="fas <?php echo $msgType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="prr-card">
        <div class="prr-head"><strong><i class="fas fa-shield-alt text-danger me-1"></i> المسيرات المصروفة</strong></div>
        <div class="prr-body">
            <div class="prr-locked mb-3"><i class="fas fa-lock me-1"></i> بعد الصرف، تبقى بيانات الموظف والفترة والمبالغ والحالة الأصلية ثابتة. العكس ينشئ قيداً محاسبياً جديداً يعكس القيد الأصلي، مع الاحتفاظ بسجل التدقيق.</div>
            <div class="table-responsive">
                <table class="prr-table">
                    <thead><tr><th>الموظف</th><th>الفترة</th><th>الصافي</th><th>تاريخ الصرف</th><th>الحالة المحاسبية</th><th>الإجراء</th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">لا توجد مسيرات مصروفة.</td></tr>
                    <?php else: foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['employee_name']); ?></strong><div class="prr-muted"><?php echo htmlspecialchars($row['employee_code'] ?? ''); ?></div></td>
                            <td><?php echo htmlspecialchars(sprintf('%04d-%02d', (int)$row['year'], (int)$row['month'])); ?></td>
                            <td><strong><?php echo number_format((float)$row['net_salary'], 2); ?></strong> <span class="prr-muted"><?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></span></td>
                            <td><?php echo htmlspecialchars($row['payment_date'] ?: '—'); ?></td>
                            <td>
                                <?php if (!empty($row['reversal_entry_id'])): ?>
                                    <span class="badge text-bg-warning">تم العكس</span>
                                    <div class="prr-muted">قيد العكس #<?php echo (int)$row['reversal_entry_id']; ?></div>
                                <?php else: ?>
                                    <span class="badge text-bg-success">مصروف — سليم</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['reversal_entry_id'])): ?>
                                    <button class="prr-btn prr-done" type="button" disabled><i class="fas fa-check me-1"></i> تم العكس</button>
                                <?php else: ?>
                                    <button class="prr-btn prr-danger" type="button" onclick="openReverse(<?php echo (int)$row['id']; ?>, <?php echo htmlspecialchars(json_encode($row['employee_name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo sprintf('%04d-%02d', (int)$row['year'], (int)$row['month']); ?>', '<?php echo number_format((float)$row['net_salary'], 2); ?>')"><i class="fas fa-rotate-left me-1"></i> عكس المسير</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reverseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" dir="rtl">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-triangle-exclamation text-danger me-2"></i> تأكيد عكس مسير الراتب</h5><button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button></div>
            <form method="POST" onsubmit="return validateReverse()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reverse">
                    <input type="hidden" name="payroll_id" id="reversePayrollId">
                    <p class="mb-2">سيتم إنشاء قيد محاسبي عكسي للمسير:</p>
                    <div class="bg-light rounded p-3 mb-3"><strong id="reverseEmployee"></strong><div id="reversePeriod" class="prr-muted"></div><div class="mt-1">الصافي: <strong id="reverseAmount"></strong> <?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></div></div>
                    <div class="alert alert-warning py-2 small"><i class="fas fa-info-circle me-1"></i> لا يمكن التراجع عن هذا الإجراء من خلال إعادة فتح المسير. سيتم الاحتفاظ بالقيد الأصلي وسجل التدقيق.</div>
                    <label class="form-label fw-bold">سبب العكس <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="reason" id="reverseReason" rows="3" maxlength="255" required placeholder="مثال: تصحيح خطأ في صرف الراتب"></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-danger"><i class="fas fa-rotate-left me-1"></i> تنفيذ العكس</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function openReverse(id, employee, period, amount) {
    document.getElementById('reversePayrollId').value = id;
    document.getElementById('reverseEmployee').textContent = employee;
    document.getElementById('reversePeriod').textContent = 'الفترة: ' + period;
    document.getElementById('reverseAmount').textContent = amount;
    document.getElementById('reverseReason').value = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('reverseModal')).show();
}
function validateReverse() {
    const reason = document.getElementById('reverseReason').value.trim();
    if (!reason) { document.getElementById('reverseReason').focus(); return false; }
    return confirm('هل أنت متأكد من تنفيذ عكس مسير الراتب؟');
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
