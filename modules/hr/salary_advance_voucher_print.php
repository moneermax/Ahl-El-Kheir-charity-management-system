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
$allowedRoles = ['admin','financial_manager','accountant_staff','general_manager','vice_general_manager'];
$isPrivileged = in_array($role, $allowedRoles, true);

$requestId = (int)($_GET['id'] ?? 0);
if ($requestId <= 0) {
    http_response_code(400);
    exit('Invalid salary advance request');
}

$r = dbFetchOne(
    "SELECT r.request_no, r.status, r.approved_amount, r.outstanding_balance,
            r.disbursed_at, r.disbursement_reference,
            e.full_name AS employee_name, e.employee_code,
            c.code AS cash_code, c.name_ar AS cash_name,
            je.entry_code, je.entry_date,
            av.full_name AS verifier_name, dv.full_name AS disburser_name,
            d.id AS receipt_id
     FROM hr_salary_advance_requests r
     JOIN employees e ON e.id = r.employee_id
     LEFT JOIN accounts c ON c.id = r.disbursement_account_id
     LEFT JOIN journal_entries je ON je.id = r.disbursement_journal_entry_id
     LEFT JOIN users av ON av.id = r.accounting_verified_by
     LEFT JOIN users dv ON dv.id = r.disbursed_by
     LEFT JOIN hr_salary_advance_documents d
       ON d.salary_advance_request_id = r.id
      AND d.document_type = 'payment_receipt'
     WHERE r.id = ? AND r.status = 'disbursed'
     LIMIT 1",
    [$requestId]
);

if (!$r || empty($r['entry_code'])) {
    http_response_code(404);
    exit('Salary advance voucher not found');
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
        [(int)Session::getUserId(), $requestId]
    );
    if (!$employeeAccess) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$amount = (float)$r['approved_amount'];
$words = ak_voucher_amount_words($amount);
$printedBy = (string)(dbFetchOne("SELECT full_name FROM users WHERE id = ?", [(int)Session::getUserId()])['full_name'] ?? '');

$org = [];
foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $s) {
    $org[$s['setting_key']] = $s['setting_value'];
}
$orgAr = (string)(ak_catalog('ar')['common.organization_name'] ?? 'منظمة أهل الخير النسوية لكفالة الأيتام');
$orgEn = (string)(ak_catalog('en')['common.organization_name'] ?? 'Ahl El Kheir Women Organization for Orphan Sponsorship');
$orgAddr = trim((string)($org['org_address'] ?? ''));
$orgTel = trim((string)($org['support_phone'] ?? ''));


function row(string $ar, string $en, string $value, bool $strong = false): string {
    return '<tr><th>' . e($ar) . '<small>' . e($en) . '</small></th><td' . ($strong ? ' class="strong"' : '') . '>' . ($value !== '' ? e($value) : '—') . '</td></tr>';
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>سند صرف سلفة راتب <?php echo e($r['request_no']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#eef1f5;color:#111;font-family:Cairo,Tahoma,sans-serif}.controls{max-width:210mm;margin:12px auto;display:flex;gap:8px;justify-content:center}.btn{padding:8px 18px;border:1px solid #9a3412;border-radius:8px;background:#fff;color:#9a3412;text-decoration:none;cursor:pointer;font-family:inherit}.primary{background:#9a3412;color:#fff}.sheet{width:210mm;min-height:297mm;margin:0 auto 20px;background:#fff;padding:12mm;box-shadow:0 2px 14px rgba(0,0,0,.12)}.voucher{border:2px solid #9a3412;border-radius:4mm;padding:7mm}.head{display:flex;align-items:center;gap:5mm;border-bottom:2px solid #9a3412;padding-bottom:4mm}.logo{width:22mm;height:22mm;border-radius:50%;object-fit:cover;border:1px solid #aaa}.org{flex:1;text-align:center}.org-ar{font-size:15pt;font-weight:800;color:#9a3412}.org-en{font-size:8.5pt}.no{width:42mm;border:1px solid #9a3412;border-radius:2mm;text-align:center;padding:2mm}.no b{display:block;font-size:12pt;color:#9a3412;direction:ltr}.title{margin:5mm 0;text-align:center;background:#9a3412;color:#fff;padding:3mm;font-size:16pt;font-weight:800}.amount{display:flex;justify-content:center;align-items:center;gap:5mm;font-size:18pt;margin:4mm 0}.amount b{font-size:20pt}.table{width:100%;border-collapse:collapse}.table th,.table td{border:1px solid #bbb;padding:3mm}.table th{width:35%;background:#f4f4f4;text-align:right}.table th small{display:block;color:#666;font-weight:400;font-size:7pt}.strong{font-weight:800}.journal{margin-top:5mm;padding:4mm;border:1px solid #bbb;background:#fafafa}.sign{display:grid;grid-template-columns:repeat(3,1fr);gap:8mm;margin-top:18mm;text-align:center}.sign div{min-height:25mm;border-top:1px solid #555;padding-top:2mm}.foot{margin-top:12mm;padding-top:3mm;border-top:1px solid #bbb;font-size:8pt;display:flex;justify-content:space-between;gap:5mm}.receipt{margin-top:5mm}.muted{color:#666;font-size:8pt}@media print{body{background:#fff}.controls{display:none}.sheet{width:auto;min-height:auto;margin:0;box-shadow:none;padding:0}.voucher{page-break-inside:avoid}}
</style>
</head>
<body>
<div class="controls"><button class="btn primary" onclick="window.print()">طباعة السند / Print</button></div>
<main class="sheet">
<section class="voucher">
<header class="head">
<img class="logo" src="<?php echo APP_URL; ?>assets/img/logo.png" alt="">
<div class="org"><div class="org-ar"><?php echo e($orgAr); ?></div><div class="org-en"><?php echo e($orgEn); ?></div><?php if ($orgAddr || $orgTel): ?><div class="muted"><?php echo e(trim($orgAddr . ($orgAddr && $orgTel ? ' | ' : '') . $orgTel)); ?></div><?php endif; ?></div>
<div class="no"><span>رقم الطلب / Request</span><b><?php echo e($r['request_no']); ?></b><span>التاريخ / Date</span><b><?php echo e($r['entry_date'] ?? $r['disbursed_at']); ?></b></div>
</header>
<div class="title">سند صرف سلفة راتب <span dir="ltr">/ SALARY ADVANCE PAYMENT VOUCHER</span></div>
<div class="amount"><span>المبلغ / Amount</span><b><?php echo number_format($amount,2); ?></b><span>ج.س / SDG</span></div>
<table class="table">
<?php
echo row('الموظف', 'Employee', (string)$r['employee_name'], true);
echo row('الكود الوظيفي', 'Employee Code', (string)$r['employee_code']);
echo row('المبلغ كتابةً', 'Amount in Words', $words);
echo row('الغرض', 'Purpose', 'صرف سلفة راتب للموظف');
echo row('حساب الذمة', 'Receivable Account', '1410 — ذمم سلف الموظفين');
echo row('حساب الصرف', 'Payment Source', (string)$r['cash_code'] . ' — ' . (string)$r['cash_name']);
echo row('مرجع الصرف', 'Disbursement Reference', (string)($r['disbursement_reference'] ?? ''));
echo row('القيد المحاسبي', 'Journal Entry', (string)$r['entry_code'], true);
?>
</table>
<div class="sign"><div>المستفيد / Employee<br><br>التوقيع: __________________</div><div>المُعد / Prepared by<br><br><?php echo e($r['disburser_name'] ?? ''); ?></div><div>المدير المالي / Financial Manager<br><br>التوقيع: __________________</div></div>
<footer class="foot"><span>تمت الطباعة بواسطة: <?php echo e($printedBy); ?></span><span>حالة السند: مرحّل / Posted</span><span>القيد: <?php echo e($r['entry_code']); ?></span></footer>
</section>
</main>
</body>
</html>
