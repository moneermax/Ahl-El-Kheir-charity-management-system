<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_vouchers.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$role = (string)Session::getUserRole();
$allowedRoles = ['admin', 'financial_manager', 'fm'];
$isPrivileged = in_array($role, $allowedRoles, true);

$repaymentId = (int)($_GET['id'] ?? 0);
if ($repaymentId <= 0) {
    http_response_code(400);
    exit('Invalid repayment reference');
}

$r = dbFetchOne(
    "SELECT d.id, d.repayment_amount, d.repayment_reference, d.repayment_date, d.notes,
            r.id AS request_id, r.request_no,
            e.full_name AS employee_name, e.employee_code,
            a.code AS repayment_account_code, a.name_ar AS repayment_account_name,
            je.entry_code, je.entry_date,
            u.full_name AS received_by_name
     FROM hr_salary_advance_direct_repayments d
     JOIN hr_salary_advance_requests r ON r.id = d.salary_advance_request_id
     JOIN employees e ON e.id = r.employee_id
     JOIN accounts a ON a.id = d.repayment_account_id
     JOIN journal_entries je ON je.id = d.accounting_entry_id
     LEFT JOIN users u ON u.id = d.received_by
     WHERE d.id = ?
     LIMIT 1",
    [$repaymentId]
);

if (!$r || empty($r['entry_code'])) {
    http_response_code(404);
    exit('Salary advance repayment voucher not found');
}

if (!$isPrivileged) {
    $employeeAccess = dbFetchOne(
        "SELECT e.id
         FROM employees e
         WHERE e.user_id = ? AND e.id = (
             SELECT employee_id
             FROM hr_salary_advance_requests
             WHERE id = ?
             LIMIT 1
         )
         LIMIT 1",
        [(int)Session::getUserId(), (int)$r['request_id']]
    );
    if (!$employeeAccess) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$amount = (float)$r['repayment_amount'];
$words = ak_voucher_amount_words($amount);
$printedByRow = dbFetchOne("SELECT full_name FROM users WHERE id = ?", [(int)Session::getUserId()]);
$printedBy = (string)($printedByRow['full_name'] ?? '');

$org = [];
foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $s) {
    $org[$s['setting_key']] = $s['setting_value'];
}
$orgAr = (string)(ak_catalog('ar')['common.organization_name'] ?? 'منظمة أهل الخير النسوية لكفالة الأيتام');
$orgEn = (string)(ak_catalog('en')['common.organization_name'] ?? 'Ahl El Kheir Women Organization for Orphan Sponsorship');
$orgAddr = trim((string)($org['org_address'] ?? ''));
$orgTel = trim((string)($org['support_phone'] ?? ''));

function repaymentRow(string $ar, string $en, string $value, bool $strong = false): string {
    return '<tr><th>' . e($ar) . '<small>' . e($en) . '</small></th><td' . ($strong ? ' class="strong"' : '') . '>' . ($value !== '' ? e($value) : '—') . '</td></tr>';
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>سند قبض سداد سلفة راتب <?php echo e($r['request_no']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#eef1f5;color:#111;font-family:Cairo,Tahoma,sans-serif}.controls,.back-row{max-width:210mm;margin:12px auto;display:flex;gap:8px;justify-content:center}.back-row{justify-content:flex-start}.back-row.bottom{justify-content:flex-end;margin-top:20px}.btn{padding:8px 18px;border:1px solid #166534;border-radius:8px;background:#fff;color:#166534;text-decoration:none;cursor:pointer;font-family:inherit}.primary{background:#166534;color:#fff}.sheet{width:210mm;min-height:297mm;margin:0 auto 20px;background:#fff;padding:12mm;box-shadow:0 2px 14px rgba(0,0,0,.12)}.voucher{border:2px solid #166534;border-radius:4mm;padding:7mm}.head{display:flex;align-items:center;gap:5mm;border-bottom:2px solid #166534;padding-bottom:4mm}.logo{width:22mm;height:22mm;border-radius:50%;object-fit:cover;border:1px solid #aaa}.org{flex:1;text-align:center}.org-ar{font-size:15pt;font-weight:800;color:#166534}.org-en{font-size:8.5pt}.no{width:48mm;border:1px solid #166534;border-radius:2mm;text-align:center;padding:2mm}.no b{display:block;font-size:11pt;color:#166534;direction:ltr}.title{margin:5mm 0;text-align:center;background:#166534;color:#fff;padding:3mm;font-weight:800}.title-ar{font-size:16pt}.title-en{font-size:11pt;margin-top:1mm}.amount{display:flex;justify-content:center;align-items:center;gap:5mm;font-size:18pt;margin:4mm 0}.amount b{font-size:20pt}.table{width:100%;border-collapse:collapse}.table th,.table td{border:1px solid #bbb;padding:3mm}.table th{width:35%;background:#f4f4f4;text-align:right}.table th small{display:block;color:#666;font-weight:400;font-size:7pt}.strong{font-weight:800}.journal{margin-top:5mm;padding:4mm;border:1px solid #bbb;background:#fafafa}.sign{display:grid;grid-template-columns:repeat(3,1fr);gap:8mm;margin-top:18mm;text-align:center}.sign div{min-height:25mm;border-top:1px solid #555;padding-top:2mm}.foot{margin-top:12mm;padding-top:3mm;border-top:1px solid #bbb;font-size:8pt;display:flex;justify-content:space-between;gap:5mm}.muted{color:#666;font-size:8pt}@media print{body{background:#fff}.controls,.back-row{display:none}.sheet{width:auto;min-height:auto;margin:0;box-shadow:none;padding:0}.voucher{page-break-inside:avoid}}
</style>
<script>
function akRepaymentVoucherBack(){try{if(window.opener&&!window.opener.closed){window.close();return false;}window.close();}catch(e){}return false;}
</script>
</head>
<body>
<div class="back-row"><a class="btn" href="<?php echo e(APP_URL . 'modules/hr/salary_advance_processing.php?id=' . (int)$r['request_id']); ?>" onclick="return akRepaymentVoucherBack();">العودة ←</a></div>
<div class="controls"><button class="btn primary" onclick="window.print()">طباعة سند القبض / Print</button></div>
<main class="sheet">
<section class="voucher">
<header class="head">
<img class="logo" src="<?php echo APP_URL; ?>assets/img/logo.png" alt="">
<div class="org"><div class="org-ar"><?php echo e($orgAr); ?></div><div class="org-en"><?php echo e($orgEn); ?></div><?php if ($orgAddr || $orgTel): ?><div class="muted"><?php echo e(trim($orgAddr . ($orgAddr && $orgTel ? ' | ' : '') . $orgTel)); ?></div><?php endif; ?></div>
<div class="no"><span>رقم الطلب / Request</span><b><?php echo e($r['request_no']); ?></b><span>تاريخ السداد / Date</span><b><?php echo e((string)$r['repayment_date']); ?></b></div>
</header>
<div class="title"><div class="title-ar">سند قبض سداد سلفة راتب</div><div class="title-en" dir="ltr">SALARY ADVANCE REPAYMENT RECEIPT</div></div>
<div class="amount"><span>المبلغ</span><b><?php echo number_format($amount,2); ?></b><span>ج.س</span></div>
<table class="table">
<?php
echo repaymentRow('الموظف', 'Employee', (string)$r['employee_name'], true);
echo repaymentRow('الكود الوظيفي', 'Employee Code', (string)$r['employee_code']);
echo repaymentRow('المبلغ كتابةً', 'Amount in Words', $words);
echo repaymentRow('الغرض', 'Purpose', 'سداد سلفة راتب للموظف');
echo repaymentRow('حساب الاستلام', 'Receiving Account', (string)$r['repayment_account_code'] . ' — ' . (string)$r['repayment_account_name']);
echo repaymentRow('مرجع السداد', 'Repayment Reference', (string)($r['repayment_reference'] ?? ''), true);
echo repaymentRow('القيد المحاسبي', 'Journal Entry', (string)$r['entry_code'], true);
echo repaymentRow('استلم بواسطة', 'استلم بواسطة', (string)($r['received_by_name'] ?? ''));
if (trim((string)($r['notes'] ?? '')) !== '') echo repaymentRow('ملاحظات', 'Notes', (string)$r['notes']);
?>
</table>
<div class="journal">تم تسجيل السداد وترحيل القيد المحاسبي: <strong><?php echo e($r['entry_code']); ?></strong>. القيد يعالج سداد الذمة على حساب 1410 — ذمم سلف الموظفين مقابل حساب الاستلام المحدد.</div>
<div class="sign"><div>الموظف<br><br>التوقيع: __________________</div><div>المُعد<br><br><?php echo e($r['received_by_name'] ?? ''); ?></div><div>المدير المالي<br><br>التوقيع: __________________</div></div>
<footer class="foot"><span>تمت الطباعة بواسطة: <?php echo e($printedBy); ?></span><span>حالة السداد: مرحّل / Posted</span><span>القيد: <?php echo e($r['entry_code']); ?></span></footer>
</section>
</main>
<div class="back-row bottom"><a class="btn" href="<?php echo e(APP_URL . 'modules/hr/salary_advance_processing.php?id=' . (int)$r['request_id']); ?>" onclick="return akRepaymentVoucherBack();">العودة ←</a></div>
</body>
</html>
