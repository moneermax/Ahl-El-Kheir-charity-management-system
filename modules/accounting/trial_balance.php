<?php
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once __DIR__.'/lib.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: '.APP_URL.'index.php');
    exit();
}

$role = Session::getUserRole();
$canViewTrialBalance = in_array($role, ['admin','financial_manager'], true);
if (!$canViewTrialBalance) {
    $_SESSION['flash'][] = ['type'=>'error','message'=>'ليس لديك صلاحية عرض ميزان المراجعة.'];
    header('Location: '.APP_URL.'modules/accounting/accounts.php');
    exit();
}

$pageTitle = 'ميزان المراجعة';
$active = 'accounts';
ak_ensure_tables();
ak_seed_accounts();

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}

$where = "je.status='posted'";
$params = [];
if ($from !== '') {
    $where .= ' AND je.entry_date >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where .= ' AND je.entry_date <= ?';
    $params[] = $to;
}

$accounts = dbFetchAll(
    "SELECT a.id, a.code, a.name_ar, a.name_en, a.account_type,
            COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.debit ELSE 0 END),0) debit_total,
            COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.credit ELSE 0 END),0) credit_total
     FROM accounts a
     LEFT JOIN journal_lines jl ON jl.account_id=a.id
     LEFT JOIN journal_entries je ON je.id=jl.entry_id AND {$where}
     GROUP BY a.id, a.code, a.name_ar, a.name_en, a.account_type
     HAVING COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.debit ELSE 0 END),0) <> 0
         OR COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.credit ELSE 0 END),0) <> 0
     ORDER BY a.code",
    $params
);

// The totals are calculated independently from the account rows so the audit
// assertion is directly against every posted journal line in the selected period.
$totals = dbFetchOne(
    "SELECT COALESCE(SUM(jl.debit),0) total_debit,
            COALESCE(SUM(jl.credit),0) total_credit,
            COUNT(DISTINCT je.id) journal_count,
            COUNT(jl.id) line_count
     FROM journal_lines jl
     JOIN journal_entries je ON je.id=jl.entry_id
     WHERE {$where}",
    $params
);

$totalDebit = (float)($totals['total_debit'] ?? 0);
$totalCredit = (float)($totals['total_credit'] ?? 0);
$difference = round($totalDebit - $totalCredit, 2);
$isBalanced = ($difference === 0.0);

include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-scale-balanced me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">ملخص أرصدة الحسابات اعتماداً على القيود المُرحّلة فقط. هذا التقرير للقراءة والمراجعة فقط.</p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/accounting/accounts.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-right me-1"></i>العودة إلى دليل الحسابات
        </a>
    </div>
</div>

<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>

<div class="card mb-4 fade-in">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>تطبيق</button>
            </div>
            <div class="col-md-2">
                <a class="btn btn-outline-secondary w-100" href="<?php echo APP_URL; ?>modules/accounting/trial_balance.php">كل القيود المُرحّلة</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4 fade-in">
    <div class="col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body">
            <small class="text-muted">إجمالي المدين</small>
            <div class="fs-4 fw-bold"><?php echo number_format($totalDebit,2); ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body">
            <small class="text-muted">إجمالي الدائن</small>
            <div class="fs-4 fw-bold"><?php echo number_format($totalCredit,2); ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body">
            <small class="text-muted">الفرق</small>
            <div class="fs-4 fw-bold"><?php echo number_format($difference,2); ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body">
            <small class="text-muted">حالة الميزان</small>
            <div class="fs-4 fw-bold <?php echo $isBalanced ? 'text-success' : 'text-danger'; ?>">
                <?php echo $isBalanced ? 'متوازن' : 'غير متوازن'; ?>
            </div>
        </div></div>
    </div>
</div>

<div class="alert <?php echo $isBalanced ? 'alert-success' : 'alert-danger'; ?> fade-in">
    <strong><?php echo $isBalanced ? '✓ الميزان متوازن.' : '⚠ الميزان غير متوازن.'; ?></strong>
    إجمالي المدين يجب أن يساوي إجمالي الدائن عبر جميع أسطر القيود المُرحّلة.
    <span class="ms-2">عدد القيود: <?php echo (int)($totals['journal_count'] ?? 0); ?> — عدد الأسطر: <?php echo (int)($totals['line_count'] ?? 0); ?></span>
</div>

<div class="card fade-in">
    <div class="card-header bg-white fw-bold" style="color:#1b4d8f;">
        <i class="fas fa-list-ol me-2"></i>أرصدة الحسابات — القيود المُرحّلة فقط
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2">
            القيود <strong>المبطلة</strong> لا تدخل في الميزان، كما أن المعاملات الملغاة التي لا تملك قيداً محاسبياً لا تظهر فيه. القيود العادية، قيود الإلغاء المرحّلة، والقيود اليدوية المرحّلة تدخل في الإجمالي.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>رمز الحساب</th>
                        <th>الحساب</th>
                        <th>النوع</th>
                        <th>إجمالي المدين</th>
                        <th>إجمالي الدائن</th>
                        <th>الرصيد الصافي</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$accounts): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">لا توجد حركات مُرحّلة ضمن الفترة المحددة.</td></tr>
                <?php else: foreach ($accounts as $a):
                    $debit = (float)$a['debit_total'];
                    $credit = (float)$a['credit_total'];
                    $net = $debit - $credit;
                ?>
                    <tr>
                        <td><code><?php echo e($a['code']); ?></code></td>
                        <td><strong><?php echo e($a['name_ar']); ?></strong></td>
                        <td><?php echo e($a['account_type']); ?></td>
                        <td><?php echo number_format($debit,2); ?></td>
                        <td><?php echo number_format($credit,2); ?></td>
                        <td class="fw-bold"><?php echo number_format($net,2); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if ($accounts): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">الإجمالي</td>
                        <td><?php echo number_format($totalDebit,2); ?></td>
                        <td><?php echo number_format($totalCredit,2); ?></td>
                        <td><?php echo number_format($difference,2); ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>