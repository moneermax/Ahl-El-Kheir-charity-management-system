<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$checks = [];
$errors = [];

function integrityTableExists(string $table): bool
{
    return (bool)dbFetchOne(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
        [$table]
    );
}

function integrityColumnExists(string $table, string $column): bool
{
    return (bool)dbFetchOne(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1",
        [$table, $column]
    );
}

function integrityAddCheck(array &$checks, string $key, string $title, bool $ok, string $summary, array $rows = []): void
{
    $checks[$key] = ['title' => $title, 'ok' => $ok, 'summary' => $summary, 'rows' => $rows];
}

try {
    if (!integrityTableExists('payroll')) {
        throw new RuntimeException('جدول payroll غير موجود.');
    }

    $schemaOk = integrityColumnExists('payroll', 'accounting_status')
        && integrityColumnExists('payroll', 'accounting_entry_id')
        && integrityColumnExists('payroll', 'payment_account_id');
    integrityAddCheck(
        $checks,
        'schema',
        'تكامل أعمدة المحاسبة',
        $schemaOk,
        $schemaOk ? 'أعمدة حالة المحاسبة والقيد وحساب الدفع موجودة في payroll.' : 'أعمدة تكامل المحاسبة المطلوبة غير مكتملة.'
    );

    $paidMissingJournal = dbFetchAll(
        "SELECT p.id,e.employee_code,e.full_name,p.month,p.year,p.net_salary,p.accounting_status,p.accounting_entry_id
         FROM payroll p JOIN employees e ON e.id=p.employee_id
         LEFT JOIN journal_entries j ON j.reference_type='payroll' AND j.reference_id=p.id AND j.status='posted'
         WHERE p.status='paid' AND p.net_salary > 0 AND j.id IS NULL
         ORDER BY p.year DESC,p.month DESC,e.full_name ASC"
    );
    integrityAddCheck($checks, 'paid_missing_journal', 'المسيرات المصروفة غير المرحلة',
        count($paidMissingJournal) === 0,
        count($paidMissingJournal) === 0 ? 'كل مسير مصروف بصافي موجب له قيد محاسبي مرحل.' : 'يوجد مسير مصروف بصافي موجب بدون قيد محاسبي مرحل.',
        $paidMissingJournal
    );

    $paidWrongStatus = dbFetchAll(
        "SELECT p.id,e.employee_code,e.full_name,p.month,p.year,p.accounting_status,p.accounting_entry_id
         FROM payroll p JOIN employees e ON e.id=p.employee_id
         WHERE p.status='paid' AND (COALESCE(p.accounting_status,'none') <> 'posted' OR COALESCE(p.accounting_entry_id,0)=0)
         ORDER BY p.year DESC,p.month DESC,e.full_name ASC"
    );
    integrityAddCheck($checks, 'paid_status', 'حالة المحاسبة للمسيرات المصروفة',
        count($paidWrongStatus) === 0,
        count($paidWrongStatus) === 0 ? 'كل المسيرات المصروفة تحمل حالة posted ورقم قيد محاسبي.' : 'توجد مسيرات مصروفة بحالة أو قيد محاسبي غير مكتمل.',
        $paidWrongStatus
    );

    $approvedWrongStatus = dbFetchAll(
        "SELECT p.id,e.employee_code,e.full_name,p.month,p.year,p.accounting_status
         FROM payroll p JOIN employees e ON e.id=p.employee_id
         WHERE p.status='approved' AND COALESCE(p.accounting_status,'none') <> 'ready'
         ORDER BY p.year DESC,p.month DESC,e.full_name ASC"
    );
    integrityAddCheck($checks, 'approved_status', 'حالة المحاسبة للمسيرات المعتمدة',
        count($approvedWrongStatus) === 0,
        count($approvedWrongStatus) === 0 ? 'كل المسيرات المعتمدة جاهزة للمحاسبة.' : 'توجد مسيرات معتمدة ليست في حالة ready.',
        $approvedWrongStatus
    );

    $draftPosted = dbFetchAll(
        "SELECT p.id,e.employee_code,e.full_name,p.month,p.year,j.id AS journal_id
         FROM payroll p JOIN employees e ON e.id=p.employee_id
         JOIN journal_entries j ON j.reference_type='payroll' AND j.reference_id=p.id AND j.status='posted'
         WHERE p.status='draft'
         ORDER BY p.year DESC,p.month DESC,e.full_name ASC"
    );
    integrityAddCheck($checks, 'draft_posted', 'المسودات المرتبطة بقيود مرحّلة',
        count($draftPosted) === 0,
        count($draftPosted) === 0 ? 'لا توجد مسودة مرتبطة بقيد محاسبي مرحل.' : 'توجد مسودة مرتبطة بقيد مرحل.',
        $draftPosted
    );

    $duplicates = dbFetchAll(
        "SELECT employee_id,month,year,COUNT(*) AS row_count
         FROM payroll GROUP BY employee_id,month,year HAVING COUNT(*) > 1
         ORDER BY year DESC,month DESC,employee_id ASC"
    );
    integrityAddCheck($checks, 'duplicates', 'التكرار في مسيرات الموظفين',
        count($duplicates) === 0,
        count($duplicates) === 0 ? 'لا يوجد أكثر من مسير لنفس الموظف والفترة.' : 'يوجد تكرار في نفس الموظف والفترة.',
        $duplicates
    );

    $unbalanced = dbFetchAll(
        "SELECT j.id,j.entry_code,j.reference_type,j.reference_id,
                ROUND(COALESCE(SUM(l.debit),0),2) AS total_debit,
                ROUND(COALESCE(SUM(l.credit),0),2) AS total_credit
         FROM journal_entries j JOIN journal_lines l ON l.entry_id=j.id
         WHERE j.status='posted' AND j.reference_type IN ('payroll','payroll_reversal')
         GROUP BY j.id,j.entry_code,j.reference_type,j.reference_id
         HAVING ROUND(COALESCE(SUM(l.debit),0),2) <> ROUND(COALESCE(SUM(l.credit),0),2)
         ORDER BY j.id DESC"
    );
    integrityAddCheck($checks, 'balance', 'توازن قيود الرواتب',
        count($unbalanced) === 0,
        count($unbalanced) === 0 ? 'كل قيود الرواتب والعكس المرحّلة متوازنة.' : 'يوجد قيد رواتب غير متوازن.',
        $unbalanced
    );

    if (integrityTableExists('hr_payroll_reversals')) {
        $reversalMismatch = dbFetchAll(
            "SELECT r.id,r.payroll_id,r.original_entry_id,r.reversal_entry_id,
                    CASE WHEN op.id IS NULL THEN 1 ELSE 0 END AS original_missing,
                    CASE WHEN rv.id IS NULL THEN 1 ELSE 0 END AS reversal_missing
             FROM hr_payroll_reversals r
             LEFT JOIN journal_entries op ON op.id=r.original_entry_id AND op.status='posted'
             LEFT JOIN journal_entries rv ON rv.id=r.reversal_entry_id AND rv.status='posted' AND rv.reference_type='payroll_reversal' AND rv.reference_id=r.payroll_id
             WHERE op.id IS NULL OR rv.id IS NULL
             ORDER BY r.id DESC"
        );
        integrityAddCheck($checks, 'reversal_audit', 'سلامة سجل عكس الرواتب',
            count($reversalMismatch) === 0,
            count($reversalMismatch) === 0 ? 'كل عمليات العكس المسجلة مرتبطة بالقيد الأصلي وقيد العكس.' : 'يوجد سجل عكس غير مكتمل الارتباط بالقيود المحاسبية.',
            $reversalMismatch
        );
    } else {
        integrityAddCheck($checks, 'reversal_audit', 'سلامة سجل عكس الرواتب', true, 'جدول سجل العكس لم يُنشأ بعد؛ لا توجد بيانات تدقيق لفحصها.');
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$failed = count(array_filter($checks, static fn(array $c): bool => !$c['ok']));
$pageTitle = 'فحص سلامة الرواتب';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.pi-wrap{max-width:1450px;margin:auto}.pi-header{background:linear-gradient(135deg,#173f5f,#20639b);color:#fff;padding:22px;border-radius:12px;margin-bottom:18px}.pi-header h1{margin:0;font-size:1.55rem}.pi-header p{margin:5px 0 0;opacity:.9}.pi-summary{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px}.pi-stat{background:#fff;border:1px solid #e5e9ee;border-radius:10px;padding:14px 18px;min-width:180px;box-shadow:0 2px 8px rgba(0,0,0,.045)}.pi-stat strong{display:block;font-size:1.35rem}.pi-ok{color:#198754}.pi-bad{color:#dc3545}.pi-card{background:#fff;border:1px solid #e5e9ee;border-radius:12px;margin-bottom:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04)}.pi-card-head{padding:13px 16px;display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:1px solid #edf0f2}.pi-card-body{padding:14px 16px}.pi-badge{border-radius:999px;padding:4px 9px;font-size:.72rem;font-weight:800}.pi-pass{background:#d1e7dd;color:#0f5132}.pi-fail{background:#f8d7da;color:#842029}.pi-muted{font-size:.76rem;color:#6c757d}.pi-table{width:100%;border-collapse:collapse;font-size:.8rem}.pi-table th,.pi-table td{padding:7px 8px;border-bottom:1px solid #edf0f2;text-align:right}.pi-table th{background:#f8f9fa;font-weight:800}.pi-alert{background:#fff3cd;color:#664d03;border-right:3px solid #ffc107;padding:10px 12px;border-radius:7px;font-size:.8rem}
</style>
<div class="pi-wrap"><div class="pi-header"><h1><i class="fas fa-shield-halved me-2"></i> فحص سلامة الرواتب</h1><p>فحص تشخيصي للربط بين HR والرواتب والمحاسبة — لا يقوم بتعديل أي سجل.</p></div>
<?php if($errors): ?><div class="alert alert-danger"><?php echo htmlspecialchars(implode(' | ',$errors)); ?></div><?php endif; ?>
<div class="pi-summary"><div class="pi-stat"><span class="pi-muted">إجمالي الفحوص</span><strong><?php echo count($checks); ?></strong></div><div class="pi-stat"><span class="pi-muted">سليمة</span><strong class="pi-ok"><?php echo count($checks)-$failed; ?></strong></div><div class="pi-stat"><span class="pi-muted">تحتاج معالجة</span><strong class="pi-bad"><?php echo $failed; ?></strong></div></div>
<?php foreach($checks as $check): ?><div class="pi-card"><div class="pi-card-head"><div><strong><?php echo htmlspecialchars($check['title']); ?></strong><div class="pi-muted mt-1"><?php echo htmlspecialchars($check['summary']); ?></div></div><span class="pi-badge <?php echo $check['ok']?'pi-pass':'pi-fail'; ?>"><?php echo $check['ok']?'سليم':'يحتاج معالجة'; ?></span></div><?php if(!$check['ok'] || !empty($check['rows'])): ?><div class="pi-card-body"><?php if(!$check['ok'] && empty($check['rows'])): ?><div class="pi-alert">راجع بنية قاعدة البيانات والربط الإجرائي للرواتب.</div><?php elseif(!empty($check['rows'])): ?><div class="table-responsive"><table class="pi-table"><thead><tr><?php foreach(array_keys($check['rows'][0]) as $col): ?><th><?php echo htmlspecialchars($col); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($check['rows'] as $row): ?><tr><?php foreach($row as $value): ?><td><?php echo htmlspecialchars((string)$value); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><?php endif; ?></div><?php endforeach; ?></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>