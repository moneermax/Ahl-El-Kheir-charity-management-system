<?php
// modules/accounting/accounts.php - Chart of Accounts
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'accountant', 'financial_manager', 'general_manager', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$canManage = in_array(Session::getUserRole(), ['admin', 'accountant', 'financial_manager'], true);
$pageTitle = 'شجرة الحسابات';
$active    = 'accounts';
ak_ensure_tables(); ak_seed_accounts();

$types = ['asset' => 'أصول', 'liability' => 'التزامات', 'equity' => 'أرصدة/حقوق', 'revenue' => 'إيرادات', 'expense' => 'مصروفات'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!verify_csrf()) $errors[] = 'انتهت صلاحية الجلسة.';
    elseif (isset($_POST['add_account'])) {
        $code = trim($_POST['code'] ?? ''); $name = trim($_POST['name_ar'] ?? '');
        $type = $_POST['account_type'] ?? '';
        if (!preg_match('/^\d{3,6}$/', $code)) $errors[] = 'رمز الحساب رقمي (3-6 أرقام).';
        if ($name === '') $errors[] = 'اسم الحساب مطلوب.';
        if (!isset($types[$type])) $errors[] = 'نوع الحساب غير صالح.';
        if (!$errors && dbFetchOne("SELECT id FROM accounts WHERE code = ?", [$code])) $errors[] = 'الرمز مستخدم بالفعل.';
        if (!$errors) {
            dbExecute("INSERT INTO accounts (code, name_ar, name_en, account_type) VALUES (?,?,?,?)",
                [$code, $name, trim($_POST['name_en'] ?? '') ?: null, $type]);
            flash('success', 'تمت إضافة الحساب: ' . $code . ' — ' . $name);
            header('Location: ' . APP_URL . 'modules/accounting/accounts.php'); exit();
        }
    } elseif (isset($_POST['toggle_account'])) {
        $id = (int)$_POST['toggle_account'];
        $a = dbFetchOne("SELECT id, is_active FROM accounts WHERE id = ?", [$id]);
        if ($a) { dbExecute("UPDATE accounts SET is_active = ? WHERE id = ?", [(int)$a['is_active'] ? 0 : 1, $id]); flash('success', 'تم تحديث الحالة.'); }
        header('Location: ' . APP_URL . 'modules/accounting/accounts.php'); exit();
    }
}

$accounts = dbFetchAll("SELECT a.*, (SELECT COALESCE(SUM(jl.debit - jl.credit),0) FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id WHERE jl.account_id = a.id AND je.status = 'posted') AS balance
                        FROM accounts a ORDER BY a.code");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>دليل الحسابات</h2>
    <p>دليل الحسابات المعتمد للقيد المزدوج</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div><?php endif; ?>

<?php if ($canManage): ?>
<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-plus me-2"></i>إضافة حساب</div>
    <div class="card-body">
        <form method="post" class="row g-2">
            <?php echo csrf_field(); ?>
            <div class="col-md-2"><input type="text" name="code" class="form-control" placeholder="الرمز *" dir="ltr" required></div>
            <div class="col-md-4"><input type="text" name="name_ar" class="form-control" placeholder="الاسم (عربي) *" required></div>
            <div class="col-md-3"><input type="text" name="name_en" class="form-control" placeholder="الاسم (إنجليزي)" dir="ltr"></div>
            <div class="col-md-2">
                <select name="account_type" class="form-select" required>
                    <?php foreach ($types as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1"><button name="add_account" value="1" class="btn btn-primary w-100"><i class="fas fa-plus"></i></button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>الرمز</th><th>الحساب</th><th>النوع</th><th>الرصيد (مدين-دائن)</th><th>الحالة</th><?php if ($canManage): ?><th class="text-center">إجراء</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($accounts as $a): ?>
                    <tr>
                        <td><code><?php echo e($a['code']); ?></code></td>
                        <td><strong><?php echo e($a['name_ar']); ?></strong> <small class="text-muted"><?php echo e($a['name_en'] ?? ''); ?></small></td>
                        <td><span class="badge bg-light text-dark border"><?php echo $types[$a['account_type']]; ?></span></td>
                        <td><?php echo number_format((float)$a['balance'], 2); ?></td>
                        <td><?php echo (int)$a['is_active'] === 1 ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-secondary">موقوف</span>'; ?></td>
                        <?php if ($canManage): ?>
                        <td class="text-center">
                            <form method="post" class="d-inline"><?php echo csrf_field(); ?>
                                <button name="toggle_account" value="<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-toggle-on"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>