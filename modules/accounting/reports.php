<?php
// modules/accounting/reports.php - Trial balance / Income statement / Balance sheet / Cash book
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/lib.php';
Session::start();

$reportRole = Session::getUserRole();

$allowedReportRoles = [
    'admin',
    'financial_manager',
    'accountant',
    'general_manager',
    'vice_general_manager'
];

if (!Session::isLoggedIn() || !in_array($reportRole, $allowedReportRoles, true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$pageTitle = 'التقارير المالية';
$active    = 'acct_reports';
ak_ensure_tables(); ak_seed_accounts();

$tabs = ['trial' => 'ميزان المراجعة', 'income' => 'قائمة الدخل', 'balance' => 'المركز المالي', 'cash' => 'دفتر النقد'];
$tab = $_GET['tab'] ?? 'trial';
if (!isset($tabs[$tab])) $tab = 'trial';
$from = trim($_GET['from'] ?? ''); $to = trim($_GET['to'] ?? ''); $asof = trim($_GET['asof'] ?? '') ?: date('Y-m-d');
$cashId = (int)($_GET['cash'] ?? 0) ?: ak_account_id('1100');
$export = (($_GET['export'] ?? '') === '1');

/* join with posted-entries filter (+ optional dates in the JOIN so LEFT stays correct) */
function ak_lines_join(string $mode, array &$params, string $from, string $to, string $asof): string {
    $j = "LEFT JOIN journal_lines jl ON jl.account_id = a.id
          LEFT JOIN journal_entries je ON je.id = jl.entry_id AND je.status = 'posted'";
    if ($mode === 'period') {
        if ($from !== '') { $j .= " AND je.entry_date >= ?"; $params[] = $from; }
        if ($to !== '')   { $j .= " AND je.entry_date <= ?"; $params[] = $to; }
    } else {
        $j .= " AND je.entry_date <= ?"; $params[] = $asof;
    }
    return $j;
}

$data = [];
if ($tab === 'trial' || $tab === 'income' || $tab === 'balance') {
    $params = [];
    $join = ak_lines_join('period', $params, $from, $to, $asof);
    if ($tab === 'balance') { $params = []; $join = ak_lines_join('asof', $params, $from, $to, $asof); }
    $data = dbFetchAll("SELECT a.code, a.name_ar, a.account_type,
                        COALESCE(SUM(jl.debit),0) d, COALESCE(SUM(jl.credit),0) c
                        FROM accounts a $join
                        WHERE a.is_active = 1
                        GROUP BY a.id, a.code, a.name_ar, a.account_type
                        ORDER BY a.code", $params);
}
if ($tab === 'cash') {
    $params = [$cashId];
    $where = '';
    if ($from !== '') { $where .= " AND je.entry_date >= ?"; $params[] = $from; }
    if ($to !== '')   { $where .= " AND je.entry_date <= ?"; $params[] = $to; }
    $cashLines = dbFetchAll("SELECT je.entry_date, je.entry_code, je.description, jl.debit, jl.credit
                             FROM journal_lines jl
                             JOIN journal_entries je ON je.id = jl.entry_id AND je.status = 'posted'
                             WHERE jl.account_id = ? $where
                             ORDER BY je.entry_date, je.id", $params);
    $cashAcc = dbFetchOne("SELECT code, name_ar FROM accounts WHERE id = ?", [$cashId]);
    $cashAccounts = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE code IN ('1100','1200','1300') ORDER BY code");
}

/* ---------- CSV export ---------- */
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=\"acct_' . $tab . '.csv\"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($tab === 'cash') {
        fputcsv($out, ['التاريخ', 'القيد', 'البيان', 'داخل', 'خارج', 'الرصيد']);
        $bal = 0;
        foreach ($cashLines as $l) { $bal += (float)$l['debit'] - (float)$l['credit']; fputcsv($out, [$l['entry_date'], $l['entry_code'], $l['description'], $l['debit'], $l['credit'], number_format($bal, 2, '.', '')]); }
    } else {
        fputcsv($out, ['الحساب', 'الاسم', 'مدين', 'دائن', 'الرصيد']);
        foreach ($data as $r) { $bal = (float)$r['d'] - (float)$r['c']; fputcsv($out, [$r['code'], $r['name_ar'], $r['d'], $r['c'], number_format($bal, 2, '.', '')]); }
    }
    fclose($out); exit();
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>التقارير المالية</h2>
    <p>تُحتسب آلياً من القيود المرحّلة فقط (المبطلة مستبعدة)</p>
</div>
<ul class="nav nav-pills mb-3 flex-wrap gap-1 fade-in">
    <?php foreach ($tabs as $k => $label): ?>
        <li class="nav-item"><a class="nav-link <?php echo $tab === $k ? 'active' : ''; ?>"
            style="<?php echo $tab === $k ? 'background:#1b4d8f' : 'color:#1b4d8f'; ?>"
            href="<?php echo APP_URL; ?>modules/accounting/reports.php?tab=<?php echo $k; ?>"><?php echo $label; ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
            <?php if ($tab === 'cash'): ?>
                <div class="col-md-3">
                    <label class="form-label">مكان النقد</label>
                    <select name="cash" class="form-select">
                        <?php foreach ($cashAccounts as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $cashId === (int)$c['id'] ? 'selected' : ''; ?>><?php echo e($c['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($tab === 'balance'): ?>
                <div class="col-md-3"><label class="form-label">كما في تاريخ</label><input type="date" name="asof" class="form-control" value="<?php echo e($asof); ?>"></div>
            <?php else: ?>
                <div class="col-md-3"><label class="form-label">من</label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>"></div>
                <div class="col-md-3"><label class="form-label">إلى</label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>"></div>
            <?php endif; ?>
            <div class="col-md-2"><button class="btn btn-primary w-100">عرض</button></div>
            <div class="col-md-2"><a class="btn btn-success w-100" href="<?php echo APP_URL; ?>modules/accounting/reports.php?tab=<?php echo e($tab); ?>&from=<?php echo e($from); ?>&to=<?php echo e($to); ?>&asof=<?php echo e($asof); ?>&cash=<?php echo $cashId; ?>&export=1"><i class="fas fa-file-csv me-1"></i>CSV</a></div>
        </form>
    </div>
</div>

<?php if ($tab === 'cash'): ?>
<div class="card fade-in">
    <div class="card-header"><i class="fas fa-book me-2"></i>دفتر: <?php echo e($cashAcc['name_ar'] ?? ''); ?> (<?php echo e($cashAcc['code'] ?? ''); ?>)</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead><tr><th>التاريخ</th><th>القيد</th><th>البيان</th><th>داخل</th><th>خارج</th><th>الرصيد</th></tr></thead>
            <tbody>
            <?php $bal = 0; if (!$cashLines): ?><tr><td colspan="6" class="text-center text-muted py-3">لا حركات.</td></tr><?php endif; ?>
            <?php foreach ($cashLines as $l): $bal += (float)$l['debit'] - (float)$l['credit']; ?>
                <tr>
                    <td><?php echo e($l['entry_date']); ?></td>
                    <td><code><?php echo e($l['entry_code']); ?></code></td>
                    <td><small><?php echo e($l['description'] ?? ''); ?></small></td>
                    <td><?php echo (float)$l['debit'] > 0 ? number_format((float)$l['debit'], 2) : '—'; ?></td>
                    <td><?php echo (float)$l['credit'] > 0 ? number_format((float)$l['credit'], 2) : '—'; ?></td>
                    <td><strong><?php echo number_format($bal, 2); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($cashLines): ?><tr class="table-active fw-bold"><td colspan="5">الرصيد الختامي</td><td><?php echo number_format($bal, 2); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($tab === 'trial'): ?>
<div class="card fade-in">
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead><tr><th>الحساب</th><th>الاسم</th><th>مدين</th><th>دائن</th><th>الرصيد المدين</th><th>الرصيد الدائن</th></tr></thead>
            <tbody>
            <?php $td = 0; $tc = 0; $has = false; foreach ($data as $r): $d = (float)$r['d']; $c = (float)$r['c']; if ($d == 0 && $c == 0) continue; $has = true; $td += $d; $tc += $c; $bal = $d - $c; ?>
                <tr>
                    <td><code><?php echo e($r['code']); ?></code></td><td><?php echo e($r['name_ar']); ?></td>
                    <td><?php echo $d > 0 ? number_format($d, 2) : '—'; ?></td>
                    <td><?php echo $c > 0 ? number_format($c, 2) : '—'; ?></td>
                    <td><?php echo $bal > 0 ? number_format($bal, 2) : '—'; ?></td>
                    <td><?php echo $bal < 0 ? number_format(-$bal, 2) : '—'; ?></td>
                </tr>
            <?php endforeach; if (!$has): ?><tr><td colspan="6" class="text-center text-muted py-3">لا قيود في الفترة.</td></tr><?php endif; ?>
            <tr class="table-active fw-bold"><td colspan="2">الإجمالي</td><td><?php echo number_format($td, 2); ?></td><td><?php echo number_format($tc, 2); ?></td><td colspan="2"><?php echo abs($td - $tc) < 0.01 ? 'متوازن ✔' : 'فرق: ' . number_format(abs($td - $tc), 2); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($tab === 'income'): ?>
<div class="card fade-in">
    <div class="card-body table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>الحساب</th><th>الاسم</th><th class="text-center">المبلغ</th></tr></thead>
            <tbody>
            <tr class="table-secondary fw-bold"><td colspan="3">الإيرادات</td></tr>
            <?php $totRev = 0; foreach ($data as $r): if ($r['account_type'] !== 'revenue') continue; $v = (float)$r['c'] - (float)$r['d']; $totRev += $v; ?>
                <tr><td><code><?php echo e($r['code']); ?></code></td><td><?php echo e($r['name_ar']); ?></td><td><?php echo number_format($v, 2); ?></td></tr>
            <?php endforeach; ?>
            <tr class="fw-bold"><td colspan="2">إجمالي الإيرادات</td><td><?php echo number_format($totRev, 2); ?></td></tr>
            <tr class="table-secondary fw-bold"><td colspan="3">المصروفات</td></tr>
            <?php $totExp = 0; foreach ($data as $r): if ($r['account_type'] !== 'expense') continue; $v = (float)$r['d'] - (float)$r['c']; $totExp += $v; ?>
                <tr><td><code><?php echo e($r['code']); ?></code></td><td><?php echo e($r['name_ar']); ?></td><td><?php echo number_format($v, 2); ?></td></tr>
            <?php endforeach; ?>
            <tr class="fw-bold"><td colspan="2">إجمالي المصروفات</td><td><?php echo number_format($totExp, 2); ?></td></tr>
            <tr class="table-active fw-bold"><td colspan="2">صافي الفائض / (العجز)</td><td style="color:<?php echo ($totRev - $totExp) >= 0 ? '#198754' : '#dc3545'; ?>"><?php echo number_format($totRev - $totExp, 2); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>
<?php else: /* balance */ ?>
<?php
$assets = []; $liabs = []; $equity = 0; $rev = 0; $exp = 0;
foreach ($data as $r) {
    $bal = (float)$r['d'] - (float)$r['c'];
    if ($r['account_type'] === 'asset' && abs($bal) > 0.009) $assets[] = [$r['code'], $r['name_ar'], $bal];
    if ($r['account_type'] === 'liability' && abs($bal) > 0.009) $liabs[] = [$r['code'], $r['name_ar'], -$bal];
    if ($r['account_type'] === 'equity') $equity += -$bal;
    if ($r['account_type'] === 'revenue') $rev += (float)$r['c'] - (float)$r['d'];
    if ($r['account_type'] === 'expense') $exp += (float)$r['d'] - (float)$r['c'];
}
$net = $rev - $exp;
$totA = array_sum(array_column($assets, 2));
$totLE = array_sum(array_column($liabs, 2)) + $equity + $net;
?>
<div class="row g-4 fade-in">
    <div class="col-md-6">
        <div class="card"><div class="card-header fw-bold">الأصول</div>
        <div class="card-body table-responsive"><table class="table table-sm align-middle"><tbody>
            <?php foreach ($assets as $a): ?><tr><td><code><?php echo e($a[0]); ?></code> <?php echo e($a[1]); ?></td><td><?php echo number_format($a[2], 2); ?></td></tr><?php endforeach; ?>
            <tr class="table-active fw-bold"><td>إجمالي الأصول</td><td><?php echo number_format($totA, 2); ?></td></tr>
        </tbody></table></div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header fw-bold">الالتزامات وحقوق المنظمة</div>
        <div class="card-body table-responsive"><table class="table table-sm align-middle"><tbody>
            <?php foreach ($liabs as $a): ?><tr><td><code><?php echo e($a[0]); ?></code> <?php echo e($a[1]); ?></td><td><?php echo number_format($a[2], 2); ?></td></tr><?php endforeach; ?>
            <tr><td>الأرصدة الافتتاحية / الفائض المدور</td><td><?php echo number_format($equity, 2); ?></td></tr>
            <tr><td>فائض الفترة (من قائمة الدخل)</td><td><?php echo number_format($net, 2); ?></td></tr>
            <tr class="table-active fw-bold"><td>الإجمالي</td><td><?php echo number_format($totLE, 2); ?></td></tr>
        </tbody></table></div></div>
    </div>
</div>
<div class="alert mt-3 <?php echo abs($totA - $totLE) < 0.01 ? 'alert-success' : 'alert-danger'; ?> fade-in">
    <?php echo abs($totA - $totLE) < 0.01 ? 'المعادلة محققة: الأصول = الالتزامات + الحقوق ✔' : 'تنبيه: فرق ' . number_format(abs($totA - $totLE), 2) . ' — راجع الأرصدة الافتتاحية.'; ?>
</div>
<?php endif; ?>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>