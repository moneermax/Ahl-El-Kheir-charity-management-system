<?php
// modules/accounting/my_nannies.php — Assigned nannies & verification overview (Package 6)
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
if (!in_array($role, ['admin', 'accountant', 'accountant_staff', 'financial_manager'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = 'الحاضنات المُعيّنات';
$active = 'my_nannies';
ak_out_ensure_schema();
$vmonth = (string)($_GET['vmonth'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $vmonth)) $vmonth = date('Y-m');
$scope = ak_out_scoped_nanny_ids(); // null = unrestricted (admin / head accountant)

$nannies = [];
if ($scope === null || $scope) {
    $sql = "SELECT u.id, u.full_name, u.phone, ";
    $sql .= "(SELECT COUNT(*) FROM families f WHERE f.nanny_id = u.id AND f.status = 'active') active_families, ";
    $sql .= "(SELECT COUNT(*) FROM nanny_family_verifications v WHERE v.nanny_id = u.id AND v.month = ? AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1) verified_now, ";
    $sql .= "(SELECT d2.status FROM monthly_disbursements d2 WHERE d2.nanny_id = u.id AND d2.month = ? ORDER BY d2.id DESC LIMIT 1) batch_status ";
    $sql .= "FROM users u JOIN roles r ON r.id = u.role_id ";
    $sql .= "WHERE r.code = 'nanny' AND u.is_active = 1 ";
    if ($scope !== null) $sql .= 'AND u.id IN (' . implode(',', $scope) . ') ';
    $sql .= 'ORDER BY u.full_name';
    $nannies = dbFetchAll($sql, [$vmonth, $vmonth]);
}
$nannyId = (int)($_GET['nanny'] ?? 0);
$detail = null; $detailFams = [];
if ($nannyId > 0 && ($scope === null || in_array($nannyId, $scope, true))) {
    $detail = dbFetchOne("SELECT id, full_name, phone FROM users WHERE id = ?", [$nannyId]);
    if ($detail) $detailFams = ak_out_nanny_active_families($nannyId);
}

$akCss = '#ak-mn-page{--navy:#1b4d8f;--line:#e3e8ef;--muted:#6b7280;font-size:.95rem}#ak-mn-page .ak-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}#ak-mn-page .ak-title{font-size:1.3rem;font-weight:800;color:var(--navy);margin:0}#ak-mn-page .ak-card{background:#fff;border:1px solid var(--line);border-radius:12px;margin-bottom:16px;overflow:hidden}#ak-mn-page .ak-card-head{background:var(--navy);color:#fff;padding:10px 14px;font-weight:700;font-size:.9rem}#ak-mn-page .ak-card-body{padding:14px}#ak-mn-page .ak-table{width:100%;border-collapse:collapse;background:#fff}#ak-mn-page .ak-table th{background:var(--navy);color:#fff;font-weight:600;font-size:.83rem;padding:10px 12px;text-align:right}#ak-mn-page .ak-table td{padding:10px 12px;border-bottom:1px solid var(--line);font-size:.88rem;vertical-align:middle}#ak-mn-page .ak-table tr:hover td{background:#f8fafc}#ak-mn-page .ak-btn{border:0;border-radius:8px;padding:8px 14px;font-size:.85rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}#ak-mn-page .ak-btn-sm{padding:5px 10px;font-size:.78rem}#ak-mn-page .ak-btn-navy{background:var(--navy);color:#fff}#ak-mn-page .ak-btn-ghost{background:#fff;border:1px solid var(--line);color:#374151}#ak-mn-page .ak-btn-ghost:hover{background:#f3f4f6}#ak-mn-page .ak-input{border:1px solid var(--line);border-radius:8px;padding:6px 10px;font-size:.88rem;background:#fff;color:#111827}#ak-mn-page .ak-badge{border-radius:99px;padding:3px 10px;font-size:.75rem;font-weight:700;display:inline-block}#ak-mn-page .ak-b-gray{background:#e5e7eb;color:#374151}#ak-mn-page .ak-b-amber{background:#fef3c7;color:#92400e}#ak-mn-page .ak-b-green{background:#dcfce7;color:#166534}#ak-mn-page .ak-b-navy{background:#1b4d8f;color:#fff}';
$akHeader = dirname(__DIR__, 2) . '/includes/header.php';
$akFooter = dirname(__DIR__, 2) . '/includes/footer.php';
$useLayout = is_file($akHeader) && is_file($akFooter);
if ($useLayout) { require $akHeader; echo '<main id="ak-mn-page" class="container-fluid py-4"><style>' . $akCss . '</style>'; }
else { echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>الحاضنات المُعيّنات</title></head><body style="background:#f4f7fb"><main id="ak-mn-page" class="container-fluid py-4"><style>' . $akCss . '</style>'; }
?>
<div class="ak-head">
  <div><h1 class="ak-title">الحاضنات المُعيّنات</h1>
  <div style="color:#6b7280;font-size:.82rem">متابعة حالة التوثيق الشهري والدفعات لكل أخصائية تابعة لك.</div></div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <form method="get" style="display:flex;gap:6px;align-items:center">
      <input type="month" name="vmonth" class="ak-input" value="<?= e($vmonth) ?>">
      <?php if ($nannyId): ?><input type="hidden" name="nanny" value="<?= $nannyId ?>"><?php endif; ?>
      <button class="ak-btn ak-btn-sm ak-btn-ghost" type="submit">عرض</button>
    </form>
    <a class="ak-btn ak-btn-navy" href="<?= APP_URL; ?>modules/accounting/disbursements.php">التحويلات الشهرية</a>
  </div>
</div>

<div class="ak-card"><div class="ak-card-head">الأخصائيات (<?= count($nannies) ?>) — شهر <?= e($vmonth) ?></div>
<div style="overflow-x:auto"><table class="ak-table">
<thead><tr><th>الأخصائية</th><th>الهاتف</th><th>أسر نشطة</th><th>موثّقة</th><th>دفعة الشهر</th><th>إجراءات</th></tr></thead>
<tbody>
<?php if (!$nannies): ?><tr><td colspan="6" style="text-align:center;color:#6b7280;padding:24px">لا توجد أخصائيات ضمن نطاقك.</td></tr><?php endif; ?>
<?php foreach ($nannies as $n): ?>
<tr>
<td><strong><?= e($n['full_name']) ?></strong></td>
<td><?= e($n['phone'] ?? '') ?></td>
<td><?= (int)$n['active_families'] ?></td>
<td><?php $vf = (int)$n['verified_now']; $af = (int)$n['active_families'];
if ($af > 0 && $vf >= $af): ?><span class="ak-badge ak-b-green"><?= $vf ?>/<?= $af ?> مكتمل</span>
<?php elseif ($vf > 0): ?><span class="ak-badge ak-b-amber"><?= $vf ?>/<?= $af ?></span>
<?php else: ?><span class="ak-badge ak-b-gray">0/<?= $af ?></span><?php endif; ?></td>
<td><?= $n['batch_status'] ? ak_out_status_badge((string)$n['batch_status']) : '<span class="ak-badge ak-b-gray">لا توجد دفعة</span>' ?></td>
<td><div style="display:flex;gap:6px">
<a class="ak-btn ak-btn-sm ak-btn-ghost" href="?vmonth=<?= e($vmonth) ?>&nanny=<?= (int)$n['id'] ?>">تفاصيل الأسر</a>
</div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>

<?php if ($detail): ?>
<div class="ak-card"><div class="ak-card-head">أسر <?= e($detail['full_name']) ?> النشطة — توثيق <?= e($vmonth) ?></div>
<div style="overflow-x:auto"><table class="ak-table">
<thead><tr><th>الأسرة</th><th>الأم</th><th>الهاتف</th><th>البنك</th><th>التوثيق</th><th>ملاحظات</th></tr></thead>
<tbody>
<?php if (!$detailFams): ?><tr><td colspan="6" style="text-align:center;color:#6b7280;padding:20px">لا توجد أسر نشطة.</td></tr><?php endif; ?>
<?php foreach ($detailFams as $f):
    $v = ak_out_get_verification((int)$f['id'], $vmonth);
    $done = $v && ak_out_is_verified_row($v);
?>
<tr>
<td><?= e($f['family_code']) ?></td>
<td><?= e($f['mother_name']) ?></td>
<td><?= e($f['mother_phone'] ?? '—') ?></td>
<td><?= e(trim(($f['bank_name'] ?? '') . ' ' . ($f['bank_account_number'] ?? '')) ?: '—') ?></td>
<td><?php if ($done): ?><span class="ak-badge ak-b-green">موثّقة ✓</span>
<?php elseif ($v): ?><span class="ak-badge ak-b-amber">جزئي</span>
<?php else: ?><span class="ak-badge ak-b-gray">لم تُوثّق</span><?php endif; ?></td>
<td style="font-size:.78rem;color:#6b7280"><?= e((string)($v['verification_notes'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php
if ($useLayout) { echo '</main>'; require $akFooter; }
else { echo '</main></body></html>'; }