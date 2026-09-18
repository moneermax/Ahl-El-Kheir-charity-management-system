<?php
// modules/accounting/gm_reconciliation.php — v2 GM oversight (inflows vs outflows + open-batch aging + returns log)
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_outflows.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
$uid = (int)Session::getUserId();
if (!in_array($role, ['admin', 'financial_manager','general_manager'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = 'تقرير المصالحة';
$active = 'reconciliation';
ak_out_ensure_schema();
ak_out_audit($uid, 'OPEN', 0, null, ['report' => 'gm_reconciliation']);

[$dr, $cr] = ak_out_dr_cr();
$tables = array_map(fn($r) => (string)reset($r), dbFetchAll("SHOW TABLES"));
$hasAccounts = in_array('accounts', $tables, true);
$months = []; $totIn = 0.0; $totOut = 0.0; $totCash = 0.0;
if ($hasAccounts) {
    try {
        $sql = "SELECT DATE_FORMAT(j.entry_date, '%Y-%m') m, ";
        $sql .= "COALESCE(SUM(CASE WHEN a.code LIKE '4%' THEN (jl.`$cr` - jl.`$dr`) ELSE 0 END),0) inflow, ";
        $sql .= "COALESCE(SUM(CASE WHEN a.code LIKE '5%' THEN (jl.`$dr` - jl.`$cr`) ELSE 0 END),0) outflow, ";
        $sql .= "COALESCE(SUM(CASE WHEN a.code LIKE '1%' THEN (jl.`$dr` - jl.`$cr`) ELSE 0 END),0) cashnet ";
        $sql .= "FROM journal_lines jl ";
        $sql .= "JOIN journal_entries j ON j.id = jl.entry_id ";
        $sql .= "JOIN accounts a ON a.id = jl.account_id ";
        $sql .= "WHERE j.status = 'posted' ";
        $sql .= "GROUP BY m ORDER BY m DESC";
        $months = dbFetchAll($sql);
        foreach ($months as $r) { $totIn += (float)$r['inflow']; $totOut += (float)$r['outflow']; $totCash += (float)$r['cashnet']; }
    } catch (Throwable $ex) { $months = []; }
}
$disbMonths = [];
try {
    $disbMonths = dbFetchAll("SELECT month, COUNT(*) c, COALESCE(SUM(total_amount),0) s,
        COALESCE(SUM(CASE WHEN status = 'voided' THEN total_amount ELSE 0 END),0) voided_s
        FROM monthly_disbursements GROUP BY month ORDER BY month DESC");
} catch (Throwable $ex) {}
$voided = [];
try {
    $voided = dbFetchAll("SELECT d.id, d.month, d.total_amount, d.void_reason, d.voided_at, u.full_name, d.reversal_journal_id
        FROM monthly_disbursements d LEFT JOIN users u ON u.id = d.voided_by_user_id
        WHERE d.status = 'voided' ORDER BY d.voided_at DESC LIMIT 50");
} catch (Throwable $ex) {}
/* NEW: open batches aging */
$open = [];
try {
    $open = dbFetchAll("SELECT d.id, d.month, d.status, d.created_at, d.total_amount, n.full_name nanny_name,
        (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id) items,
        (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status IN ('pending','return_requested')) open_items
        FROM monthly_disbursements d LEFT JOIN users n ON n.id = d.nanny_id
        WHERE d.status IN ('draft','pending','pending_approval','approved','transferred')
        ORDER BY d.created_at ASC");
} catch (Throwable $ex) {}
/* NEW: returns log */
$returns = [];
try {
    $returns = dbFetchAll("SELECT i.id, i.amount, i.return_reason, i.returned_at, i.reversal_journal_id, d.month, n.full_name nanny_name, u.full_name returned_by
        FROM disbursement_items i
        JOIN monthly_disbursements d ON d.id = i.disbursement_id
        LEFT JOIN users n ON n.id = d.nanny_id
        LEFT JOIN users u ON u.id = i.returned_by_user_id
        WHERE i.status = 'returned' ORDER BY i.returned_at DESC LIMIT 50");
} catch (Throwable $ex) {}

$akCss = '#ak-recon-page{--navy:#1b4d8f;--line:#e3e8ef;--muted:#6b7280;font-size:.95rem}#ak-recon-page .ak-title{font-size:1.3rem;font-weight:800;color:var(--navy);margin:0 0 14px}#ak-recon-page .ak-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px}#ak-recon-page .ak-stat{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;border-top:4px solid var(--navy)}#ak-recon-page .ak-stat-num{font-size:1.3rem;font-weight:800}#ak-recon-page .ak-stat-label{color:var(--muted);font-size:.8rem;font-weight:600}#ak-recon-page .ak-card{background:#fff;border:1px solid var(--line);border-radius:12px;margin-bottom:16px;overflow:hidden}#ak-recon-page .ak-card-head{background:var(--navy);color:#fff;padding:10px 14px;font-weight:700;font-size:.9rem}#ak-recon-page .ak-table{width:100%;border-collapse:collapse}#ak-recon-page .ak-table th{background:var(--navy);color:#fff;padding:10px 12px;font-size:.83rem;text-align:right}#ak-recon-page .ak-table td{padding:10px 12px;border-bottom:1px solid var(--line);font-size:.88rem}#ak-recon-page .pos{color:#166534;font-weight:700}#ak-recon-page .neg{color:#991b1b;font-weight:700}#ak-recon-page .ak-badge{border-radius:99px;padding:3px 10px;font-size:.72rem;font-weight:700;display:inline-block}#ak-recon-page .ak-b-amber{background:#fef3c7;color:#92400e}#ak-recon-page .ak-b-red{background:#fee2e2;color:#991b1b}';
$akHeader = dirname(__DIR__, 2) . '/includes/header.php';
$akFooter = dirname(__DIR__, 2) . '/includes/footer.php';
$useLayout = is_file($akHeader) && is_file($akFooter);
if ($useLayout) { require $akHeader; echo '<main id="ak-recon-page" class="container-fluid py-4"><style>' . $akCss . '</style>'; }
else { echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>تقرير المصالحة</title></head><body style="background:#f4f7fb"><main id="ak-recon-page" class="container-fluid py-4"><style>' . $akCss . '</style>'; }
?>
<h1 class="ak-title">تقرير المصالحة — الداخل مقابل الخارج (دفتر الأستاذ)</h1>
<div class="ak-stats">
  <div class="ak-stat"><div class="ak-stat-num pos"><?= e(number_format($totIn, 0)) ?></div><div class="ak-stat-label">إجمالي الداخل (4xxx)</div></div>
  <div class="ak-stat"><div class="ak-stat-num neg"><?= e(number_format($totOut, 0)) ?></div><div class="ak-stat-label">إجمالي الخارج (5xxx)</div></div>
  <div class="ak-stat"><div class="ak-stat-num"><?= e(number_format($totCash, 0)) ?></div><div class="ak-stat-label">صافي حركة النقد/البنك (1xxx)</div></div>
  <div class="ak-stat"><div class="ak-stat-num"><?= e(number_format($totIn - $totOut, 0)) ?></div><div class="ak-stat-label">الفرق (داخل − خارج)</div></div>
</div>

<div class="ak-card"><div class="ak-card-head">دفعات مفتوحة — تقادم المتابعة (الأقدم أولًا)</div>
<table class="ak-table"><thead><tr><th>عمر (أيام)</th><th>الشهر</th><th>الأخصائية</th><th>الإجمالي</th><th>بنود معلّقة</th><th>الحالة</th></tr></thead><tbody>
<?php if (!$open): ?><tr><td colspan="6" style="text-align:center;color:#6b7280;padding:20px">لا توجد دفعات مفتوحة — كل الدفعات محسومة.</td></tr><?php endif; ?>
<?php foreach ($open as $o): $age = (int)floor((time() - strtotime($o['created_at'])) / 86400); ?>
<tr><td class="<?= $age > 7 ? 'neg' : '' ?>"><?= $age ?></td><td><?= e($o['month']) ?></td><td><?= e($o['nanny_name'] ?? '') ?></td><td><?= e(number_format((float)$o['total_amount'], 0)) ?></td><td><?= (int)$o['open_items'] ?>/<?= (int)$o['items'] ?></td><td><?= ak_out_status_badge((string)$o['status']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<div class="ak-card"><div class="ak-card-head">سجل الإرجاعات (نقد مرتد + قيود عكسية جزئية)</div>
<table class="ak-table"><thead><tr><th>بند</th><th>الشهر</th><th>الأخصائية</th><th>المبلغ</th><th>السبب</th><th>أكّده</th><th>قيد عكسي</th><th>التاريخ</th></tr></thead><tbody>
<?php if (!$returns): ?><tr><td colspan="8" style="text-align:center;color:#6b7280;padding:20px">لا توجد إرجاعات.</td></tr><?php endif; ?>
<?php foreach ($returns as $r): ?>
<tr><td>#<?= (int)$r['id'] ?></td><td><?= e($r['month']) ?></td><td><?= e($r['nanny_name'] ?? '') ?></td><td class="neg"><?= e(number_format((float)$r['amount'], 0)) ?></td><td><?= e((string)$r['return_reason']) ?></td><td><?= e($r['returned_by'] ?? '') ?></td><td><?= $r['reversal_journal_id'] ? '#' . (int)$r['reversal_journal_id'] : '—' ?></td><td><?= e((string)$r['returned_at']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<div class="ak-card"><div class="ak-card-head">شهريًا من القيود المرحّلة</div>
<table class="ak-table"><thead><tr><th>الشهر</th><th>داخل</th><th>خارج</th><th>صافي النقد</th><th>الفرق</th></tr></thead><tbody>
<?php if (!$months): ?><tr><td colspan="5" style="text-align:center;color:#6b7280;padding:20px">لا توجد قيود بعد.</td></tr><?php endif; ?>
<?php foreach ($months as $r): ?>
<tr><td><strong><?= e($r['m']) ?></strong></td><td class="pos"><?= e(number_format((float)$r['inflow'], 0)) ?></td><td class="neg"><?= e(number_format((float)$r['outflow'], 0)) ?></td><td><?= e(number_format((float)$r['cashnet'], 0)) ?></td><td><?= e(number_format((float)$r['inflow'] - (float)$r['outflow'], 0)) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<div class="ak-card"><div class="ak-card-head">الدفعات الشهرية (مصدر الخارج)</div>
<table class="ak-table"><thead><tr><th>الشهر</th><th>عدد الدفعات</th><th>الإجمالي</th><th>المُبطَل منها</th></tr></thead><tbody>
<?php foreach ($disbMonths as $r): ?>
<tr><td><strong><?= e($r['month']) ?></strong></td><td><?= (int)$r['c'] ?></td><td><?= e(number_format((float)$r['s'], 0)) ?></td><td class="neg"><?= e(number_format((float)$r['voided_s'], 0)) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<div class="ak-card"><div class="ak-card-head">سجل الإبطالات الكاملة</div>
<table class="ak-table"><thead><tr><th>دفعة</th><th>الشهر</th><th>المبلغ</th><th>السبب</th><th>بواسطة</th><th>قيد عكسي</th><th>التاريخ</th></tr></thead><tbody>
<?php if (!$voided): ?><tr><td colspan="7" style="text-align:center;color:#6b7280;padding:20px">لا توجد إبطالات.</td></tr><?php endif; ?>
<?php foreach ($voided as $v): ?>
<tr><td>#<?= (int)$v['id'] ?></td><td><?= e($v['month']) ?></td><td><?= e(number_format((float)$v['total_amount'], 0)) ?></td><td><?= e((string)$v['void_reason']) ?></td><td><?= e($v['full_name'] ?? '') ?></td><td><?= $v['reversal_journal_id'] ? '#' . (int)$v['reversal_journal_id'] : '—' ?></td><td><?= e((string)$v['voided_at']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
if ($useLayout) { echo '</main>'; require $akFooter; }
else { echo '</main></body></html>'; }