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
$canViewLedger = in_array($role, ['admin','financial_manager'], true);
if (!$canViewLedger) {
    $_SESSION['flash'][] = ['type'=>'error','message'=>'ليس لديك صلاحية عرض كشف الحساب.'];
    header('Location: '.APP_URL.'modules/accounting/accounts.php');
    exit();
}

$pageTitle = 'كشف حساب';
$active = 'accounts';
ak_ensure_tables();
ak_seed_accounts();

$accountId = (int)($_GET['account_id'] ?? 0);
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}

$account = null;
$lines = [];
$openingBalance = 0.0;
$periodDebit = 0.0;
$periodCredit = 0.0;
$closingBalance = 0.0;

if ($accountId > 0) {
    $account = dbFetchOne('SELECT id,code,name_ar,name_en,account_type,is_active FROM accounts WHERE id=?', [$accountId]);
    if (!$account) {
        $_SESSION['flash'][] = ['type'=>'error','message'=>'الحساب المطلوب غير موجود.'];
        header('Location: '.APP_URL.'modules/accounting/accounts.php');
        exit();
    }

    if ($from !== '') {
        $openingRow = dbFetchOne(
            "SELECT COALESCE(SUM(jl.debit-jl.credit),0) balance
             FROM journal_lines jl
             JOIN journal_entries je ON je.id=jl.entry_id
             WHERE jl.account_id=? AND je.status='posted' AND je.entry_date < ?",
            [$accountId, $from]
        );
        $openingBalance = (float)($openingRow['balance'] ?? 0);
    }

    $sql =
        "SELECT je.id entry_id, je.entry_code, je.entry_date, je.description entry_description,
                je.reference_type, je.status, je.voided_at, jl.id line_id, jl.description line_description,
                jl.debit, jl.credit, u.full_name creator
         FROM journal_lines jl
         JOIN journal_entries je ON je.id=jl.entry_id
         LEFT JOIN users u ON u.id=je.created_by
         WHERE jl.account_id=? AND je.status='posted'";
    $params = [$accountId];
    if ($from !== '') {
        $sql .= ' AND je.entry_date >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $sql .= ' AND je.entry_date <= ?';
        $params[] = $to;
    }
    $sql .= ' ORDER BY je.entry_date ASC, je.id ASC, jl.id ASC';
    $lines = dbFetchAll($sql, $params);

    foreach ($lines as $line) {
        $periodDebit += (float)$line['debit'];
        $periodCredit += (float)$line['credit'];
    }
    $closingBalance = $openingBalance + $periodDebit - $periodCredit;
}

include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-book-open me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">عرض تفصيلي للقيود المُرحّلة المؤثرة على الحساب. هذا العرض للقراءة والمراجعة فقط.</p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/accounting/accounts.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-right me-1"></i>العودة إلى دليل الحسابات
        </a>
    </div>
</div>

<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>

<?php if (!$account): ?>
<div class="card fade-in">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-bold">الحساب</label>
                <select name="account_id" class="form-select" required>
                    <option value="">اختر الحساب</option>
                    <?php $allAccounts = dbFetchAll('SELECT id,code,name_ar FROM accounts ORDER BY code'); ?>
                    <?php foreach ($allAccounts as $a): ?>
                        <option value="<?php echo (int)$a['id']; ?>"><?php echo e($a['code'].' — '.$a['name_ar']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">من تاريخ</label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>"></div>
            <div class="col-md-2"><label class="form-label">إلى تاريخ</label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>عرض الكشف</button></div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card mb-4 fade-in shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><small class="text-muted d-block">رمز الحساب</small><strong><code><?php echo e($account['code']); ?></code></strong></div>
            <div class="col-md-3"><small class="text-muted d-block">الحساب</small><strong><?php echo e($account['name_ar']); ?></strong></div>
            <div class="col-md-3"><small class="text-muted d-block">النوع</small><strong><?php echo e($account['account_type']); ?></strong></div>
            <div class="col-md-3"><small class="text-muted d-block">الحالة</small><strong><?php echo (int)$account['is_active']===1 ? 'نشط' : 'غير نشط'; ?></strong></div>
        </div>
    </div>
</div>

<div class="card mb-4 fade-in">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="account_id" value="<?php echo (int)$accountId; ?>">
            <div class="col-md-3"><label class="form-label fw-bold">من تاريخ</label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>"></div>
            <div class="col-md-3"><label class="form-label fw-bold">إلى تاريخ</label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>تطبيق</button></div>
            <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?php echo APP_URL; ?>modules/accounting/account_ledger.php?account_id=<?php echo (int)$accountId; ?>">كل الحركات</a></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4 fade-in">
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">الرصيد الافتتاحي</small><div class="fs-4 fw-bold"><?php echo number_format($openingBalance,2); ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">إجمالي المدين</small><div class="fs-4 fw-bold"><?php echo number_format($periodDebit,2); ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">إجمالي الدائن</small><div class="fs-4 fw-bold"><?php echo number_format($periodCredit,2); ?></div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">الرصيد الختامي</small><div class="fs-4 fw-bold"><?php echo number_format($closingBalance,2); ?></div></div></div></div>
</div>

<div class="card fade-in">
    <div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-list me-2"></i>حركة الحساب — القيود المُرحّلة فقط</div>
    <div class="card-body">
        <div class="alert alert-info py-2">القيود الملغاة/المبطلة لا تدخل في الرصيد. لا توجد عمليات تعديل أو حذف من هذه الصفحة.</div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light"><tr><th>التاريخ</th><th>القيد</th><th>البيان</th><th>المرجع</th><th>المدين</th><th>الدائن</th><th>الرصيد الجاري</th><th>المنشئ</th></tr></thead>
                <tbody>
                <?php $runningBalance = $openingBalance; ?>
                <?php if (!$lines): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">لا توجد حركات مُرحّلة لهذا الحساب ضمن الفترة المحددة.</td></tr>
                <?php else: foreach ($lines as $line): $runningBalance += (float)$line['debit'] - (float)$line['credit']; ?>
                    <tr>
                        <td><?php echo e($line['entry_date']); ?></td>
                        <td><a href="<?php echo APP_URL; ?>modules/accounting/journal.php?view=<?php echo (int)$line['entry_id']; ?>"><code><?php echo e($line['entry_code']); ?></code></a></td>
                        <td><?php echo e($line['line_description'] ?: $line['entry_description']); ?></td>
                        <td><?php echo e($line['reference_type'] ?: 'manual'); ?></td>
                        <td><?php echo (float)$line['debit'] > 0 ? number_format((float)$line['debit'],2) : '—'; ?></td>
                        <td><?php echo (float)$line['credit'] > 0 ? number_format((float)$line['credit'],2) : '—'; ?></td>
                        <td class="fw-bold"><?php echo number_format($runningBalance,2); ?></td>
                        <td><small><?php echo e($line['creator'] ?? ''); ?></small></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if ($lines): ?><tfoot class="table-light fw-bold"><tr><td colspan="4" class="text-end">الإجمالي</td><td><?php echo number_format($periodDebit,2); ?></td><td><?php echo number_format($periodCredit,2); ?></td><td><?php echo number_format($closingBalance,2); ?></td><td></td></tr></tfoot><?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>
