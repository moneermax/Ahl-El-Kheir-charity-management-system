<<<<<<< HEAD
<?php
// modules/accounting/voucher_print.php - Printable receipt/payment voucher
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'financial_manager','accountant', 'general_manager', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
ak_ensure_tables();

$v = dbFetchOne("SELECT v.*, c1.name_ar cash_name, c2.name_ar other_name, u.full_name creator
                 FROM vouchers v
                 JOIN accounts c1 ON c1.id = v.cash_account_id
                 JOIN accounts c2 ON c2.id = v.other_account_id
                 LEFT JOIN users u ON u.id = v.created_by
                 WHERE v.id = ?", [(int)($_GET['id'] ?? 0)]);
if (!$v) { echo 'السند غير موجود'; exit(); }

$org = [];
foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $r) $org[$r['setting_key']] = $r['setting_value'];
$orgName = $org['org_name_ar'] ?? 'أهل الخير';
$isReceipt = $v['voucher_type'] === 'receipt';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?php echo e($v['voucher_no']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    body{font-family:'Cairo',sans-serif;color:#111;padding:24px}
    .box{border:2px solid #1b4d8f;border-radius:14px;padding:20px;max-width:800px;margin:auto}
    .head{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #1b4d8f;padding-bottom:12px;margin-bottom:16px}
    .title{font-size:1.4rem;font-weight:800;color:#1b4d8f}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    td{padding:10px;border:1px solid #cfd8e8;font-size:.95rem}
    .lbl{background:#eef2f9;font-weight:700;width:18%}
    .sig{display:flex;justify-content:space-between;margin-top:48px;text-align:center}
    .sig div{width:30%;border-top:1px dashed #555;padding-top:6px;font-weight:700}
    @media print{ .noprint{display:none} }
</style>
</head>
<body>
<div class="noprint" style="text-align:center;margin-bottom:12px">
    <button onclick="window.print()" style="padding:8px 24px;font-family:'Cairo';background:#1b4d8f;color:#fff;border:none;border-radius:8px;cursor:pointer">🖨 طباعة</button>
</div>
<div class="box">
    <div class="head">
        <div>
            <div style="font-weight:800;font-size:1.1rem"><?php echo e($orgName); ?></div>
            <div style="font-size:.8rem"><?php echo e($org['org_address'] ?? ''); ?></div>
        </div>
        <div class="title"><?php echo $isReceipt ? 'سند قبض' : 'سند صرف'; ?></div>
        <div style="text-align:left">
            <div>رقم: <strong><?php echo e($v['voucher_no']); ?></strong></div>
            <div>التاريخ: <?php echo e($v['voucher_date']); ?></div>
        </div>
    </div>
    <table>
        <tr><td class="lbl"><?php echo $isReceipt ? 'استلمنا من' : 'صرفنا إلى'; ?></td><td><?php echo e($v['party_name'] ?? '—'); ?></td>
            <td class="lbl">مبلغاً وقدره</td><td><strong><?php echo number_format((float)$v['amount'], 2); ?> ج.س</strong></td></tr>
        <tr><td class="lbl">فقط لا غير</td><td colspan="3"><?php echo e(ak_tafqit((int)round((float)$v['amount']))) . ' جنيهاً سودانياً فقط لا غير'; ?></td></tr>
        <tr><td class="lbl">وذلك لحساب</td><td><?php echo e($v['other_name']); ?></td>
            <td class="lbl">مكان النقد</td><td><?php echo e($v['cash_name']); ?></td></tr>
        <tr><td class="lbl">البيان</td><td colspan="3"><?php echo e($v['description'] ?? ''); ?></td></tr>
        <?php if (!empty($v['reference_number'])): ?>
        <tr><td class="lbl">مرجع</td><td colspan="3"><?php echo e($v['reference_number']); ?></td></tr>
        <?php endif; ?>
    </table>
    <div class="sig">
        <div>المحاسب</div>
        <div><?php echo $isReceipt ? 'المستلم' : 'المستفيد'; ?></div>
        <div>المدير العام</div>
    </div>
    <div style="margin-top:14px;font-size:.75rem;color:#666">أنشأه: <?php echo e($v['creator'] ?? ''); ?> · الحالة: <?php echo $v['status'] === 'posted' ? 'مرحّل' : 'مبطل'; ?></div>
</div>
</body>
=======
<?php
// modules/accounting/voucher_print.php - Printable receipt/payment voucher
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'financial_manager','accountant', 'general_manager', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
ak_ensure_tables();

$v = dbFetchOne("SELECT v.*, c1.name_ar cash_name, c2.name_ar other_name, u.full_name creator
                 FROM vouchers v
                 JOIN accounts c1 ON c1.id = v.cash_account_id
                 JOIN accounts c2 ON c2.id = v.other_account_id
                 LEFT JOIN users u ON u.id = v.created_by
                 WHERE v.id = ?", [(int)($_GET['id'] ?? 0)]);
if (!$v) { echo 'السند غير موجود'; exit(); }

$org = [];
foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $r) $org[$r['setting_key']] = $r['setting_value'];
$orgName = $org['org_name_ar'] ?? 'أهل الخير';
$isReceipt = $v['voucher_type'] === 'receipt';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?php echo e($v['voucher_no']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    body{font-family:'Cairo',sans-serif;color:#111;padding:24px}
    .box{border:2px solid #1b4d8f;border-radius:14px;padding:20px;max-width:800px;margin:auto}
    .head{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #1b4d8f;padding-bottom:12px;margin-bottom:16px}
    .title{font-size:1.4rem;font-weight:800;color:#1b4d8f}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    td{padding:10px;border:1px solid #cfd8e8;font-size:.95rem}
    .lbl{background:#eef2f9;font-weight:700;width:18%}
    .sig{display:flex;justify-content:space-between;margin-top:48px;text-align:center}
    .sig div{width:30%;border-top:1px dashed #555;padding-top:6px;font-weight:700}
    @media print{ .noprint{display:none} }
</style>
</head>
<body>
<div class="noprint" style="text-align:center;margin-bottom:12px">
    <button onclick="window.print()" style="padding:8px 24px;font-family:'Cairo';background:#1b4d8f;color:#fff;border:none;border-radius:8px;cursor:pointer">🖨 طباعة</button>
</div>
<div class="box">
    <div class="head">
        <div>
            <div style="font-weight:800;font-size:1.1rem"><?php echo e($orgName); ?></div>
            <div style="font-size:.8rem"><?php echo e($org['org_address'] ?? ''); ?></div>
        </div>
        <div class="title"><?php echo $isReceipt ? 'سند قبض' : 'سند صرف'; ?></div>
        <div style="text-align:left">
            <div>رقم: <strong><?php echo e($v['voucher_no']); ?></strong></div>
            <div>التاريخ: <?php echo e($v['voucher_date']); ?></div>
        </div>
    </div>
    <table>
        <tr><td class="lbl"><?php echo $isReceipt ? 'استلمنا من' : 'صرفنا إلى'; ?></td><td><?php echo e($v['party_name'] ?? '—'); ?></td>
            <td class="lbl">مبلغاً وقدره</td><td><strong><?php echo number_format((float)$v['amount'], 2); ?> ج.س</strong></td></tr>
        <tr><td class="lbl">فقط لا غير</td><td colspan="3"><?php echo e(ak_tafqit((int)round((float)$v['amount']))) . ' جنيهاً سودانياً فقط لا غير'; ?></td></tr>
        <tr><td class="lbl">وذلك لحساب</td><td><?php echo e($v['other_name']); ?></td>
            <td class="lbl">مكان النقد</td><td><?php echo e($v['cash_name']); ?></td></tr>
        <tr><td class="lbl">البيان</td><td colspan="3"><?php echo e($v['description'] ?? ''); ?></td></tr>
        <?php if (!empty($v['reference_number'])): ?>
        <tr><td class="lbl">مرجع</td><td colspan="3"><?php echo e($v['reference_number']); ?></td></tr>
        <?php endif; ?>
    </table>
    <div class="sig">
        <div>المحاسب</div>
        <div><?php echo $isReceipt ? 'المستلم' : 'المستفيد'; ?></div>
        <div>المدير العام</div>
    </div>
    <div style="margin-top:14px;font-size:.75rem;color:#666">أنشأه: <?php echo e($v['creator'] ?? ''); ?> · الحالة: <?php echo $v['status'] === 'posted' ? 'مرحّل' : 'مبطل'; ?></div>
</div>
</body>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
</html>