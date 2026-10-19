<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_employee_identity.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

$pdo = db();
$employee = hrGetEmployeeForUser($pdo, (int)Session::getUserID());
$payrollId = max(0, (int)($_GET['id'] ?? 0));
$statement = null;

if ($employee && $payrollId > 0) {
    $statement = dbFetchOne(
        "SELECT p.id, p.employee_id, p.month, p.year, p.basic_salary, p.allowances,
                p.overtime, p.deductions, p.attendance_deduction,
                p.salary_advance_deduction, p.incentive_amount, p.net_salary,
                p.payment_date, p.accounting_entry_id, p.accounting_status,
                e.employee_code, e.full_name AS employee_name,
                s.salary_currency
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         LEFT JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id
             FROM hr_employee_salary_history s2
             WHERE s2.employee_id = p.employee_id
               AND s2.effective_from <= LAST_DAY(STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d'))
               AND (s2.effective_to IS NULL OR s2.effective_to >= LAST_DAY(STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d')))
             ORDER BY s2.effective_from DESC, s2.id DESC
             LIMIT 1
         )
         WHERE p.id = ?
           AND p.employee_id = ?
           AND p.status = 'paid'
           AND p.accounting_status = 'posted'
         LIMIT 1",
        [$payrollId, (int)$employee['id']]
    );
}

if (!$statement) {
    http_response_code(404);
    $pageTitle = AK_LANG === 'en' ? 'Salary statement unavailable' : 'كشف الراتب غير متاح';
    include __DIR__ . '/../../includes/header.php';
    $message = AK_LANG === 'en'
        ? 'This statement is unavailable for this account or its accounting entry has not been posted.'
        : 'كشف الراتب غير متاح لهذا الحساب أو لم يكتمل ترحيله المحاسبي.';
    echo '<main class="container py-5"><div class="alert alert-warning text-center" role="alert">' . e($message) . '</div></main>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$isEnglish = AK_LANG === 'en';
$label = static fn(string $ar, string $en): string => $isEnglish ? $en : $ar;
$monthNames = $isEnglish
    ? [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December']
    : [1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'];
$month = (int)$statement['month'];
$period = ($monthNames[$month] ?? (string)$month) . ' ' . (int)$statement['year'];
$currency = trim((string)($statement['salary_currency'] ?? ''));
if ($currency === '') {
    $currency = defined('APP_CURRENCY_CODE') ? APP_CURRENCY_CODE : 'SDG';
}
$money = static fn($amount): string => number_format((float)$amount, 2) . ' ' . $currency;
$netSalary = round((float)$statement['net_salary'], 2);
$incentiveAmount = round((float)($statement['incentive_amount'] ?? 0), 2);
$totalPaid = round($netSalary + $incentiveAmount, 2);
$paymentDate = trim((string)($statement['payment_date'] ?? ''));
$pageTitle = $label('بيان تفاصيل الراتب', 'Salary Statement');
$logoUrl = APP_URL . 'assets/img/logo.png';
?>
<!doctype html>
<html lang="<?php echo e(AK_LANG); ?>" dir="<?php echo e(AK_DIR); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle . ' — ' . $period); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#172b4d;--navy:#17365d;--gold:#b38a3e;--muted:#66758a;--line:#c6d0de;--pale:#f4f7fb}
        *{box-sizing:border-box}
        body{margin:0;background:#edf1f6;color:var(--ink);font-family:'Cairo',Tahoma,Arial,sans-serif;font-size:12px;line-height:1.45}
        .statement-shell{width:min(748px,calc(100% - 24px));margin:14px auto}
        .statement{background:#fff;border:2px solid var(--navy);border-radius:10px;overflow:hidden;box-shadow:0 8px 22px rgba(23,54,93,.08)}
        .statement-top{position:relative;padding:12px 20px 10px;background:linear-gradient(120deg,#142b4b,#234a75);color:#fff;border-bottom:3px solid var(--gold)}
        .brandline{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:62px;gap:5px}
        .brand{display:flex;align-items:center;justify-content:center;gap:10px;text-align:center;font-weight:800;font-size:16px;line-height:1.25}
        .brand img{width:54px;height:54px;object-fit:contain;background:#fff;border-radius:50%;padding:3px;border:1px solid rgba(255,255,255,.65);flex-shrink:0}
        .brand-name{display:block}
        .doc-kind{position:static;transform:none;max-width:none;text-align:center;border:0;padding:0;margin:0;flex-shrink:0}
        .doc-kind strong{display:block;font-size:12px;letter-spacing:.35px}
        .doc-kind span{display:block;font-size:11px;opacity:.9}
        .period-ribbon{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.2)}
        .period-label{font-size:10px;opacity:.86}
        .period{font-size:16px;font-weight:800}
        .status{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.3);padding:4px 9px;border-radius:5px;font-size:10px;font-weight:700}
        .statement-body{padding:12px 16px 10px}
        .section-title{display:flex;align-items:center;gap:7px;font-weight:800;font-size:12px;margin:0 0 7px;color:var(--navy)}
        .section-title:before{content:"";width:3px;height:14px;background:var(--gold);border-radius:3px;flex-shrink:0}
        .employee-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-bottom:12px}
        .info-cell{background:var(--pale);border:1px solid #c6d0de;border-radius:5px;padding:6px 9px;min-width:0}
        .info-label{font-size:10px;color:var(--muted);margin-bottom:1px}
        .info-value{font-weight:700;font-size:12px;overflow-wrap:anywhere}
        .detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;align-items:start}
        .detail-panel{min-width:0}
        .pay-table{width:100%;border-collapse:collapse;border:1px solid var(--line);margin:0 0 9px}
        .pay-table th{background:#eef3f9;color:var(--navy);font-size:10px;font-weight:800;padding:5px 8px;text-align:start;border-bottom:1px solid var(--line)}
        .pay-table td{padding:5px 8px;border-top:1px solid #dbe2eb;vertical-align:middle}
        .pay-table .amount{font-weight:700;white-space:nowrap;text-align:end;direction:ltr}
        .pay-table .subhead td{background:#e9eef5;color:var(--navy);font-size:10px;font-weight:800;padding:5px 8px}
        .totals{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:3px}
        .total-card{border:1px solid var(--line);border-radius:6px;padding:7px 11px}
        .total-label{font-size:10px;color:var(--muted)}
        .total-value{font-size:16px;font-weight:800;margin-top:2px;direction:ltr;text-align:start;white-space:nowrap}
        .total-card.net{background:#f4f7fb}
        .total-card.paid{background:#f8f5ed;border-color:#b9a16c}
        .total-card.paid .total-value{font-size:18px;color:var(--navy)}
        .payment-meta{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;padding:8px 0 0;margin-top:9px;border-top:1px solid var(--line);font-size:10px;color:var(--muted)}
        .payment-meta strong{color:var(--ink)}
        .statement-foot{padding:7px 16px;background:#f3f6fa;border-top:1px solid var(--navy);color:#52627a;font-size:9px;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap}
        .actions{display:flex;justify-content:center;gap:8px;margin:10px auto}
        .actions button{border:0;border-radius:5px;padding:7px 14px;font:700 11px 'Cairo',sans-serif;cursor:pointer}
        .print-btn{background:var(--navy);color:#fff}.back-btn{background:#fff;color:var(--navy);border:1px solid var(--navy)!important}
        @media(max-width:620px){.statement-shell{width:calc(100% - 12px);margin:6px auto}.statement-top{padding:10px}.brandline{grid-template-columns:1fr;min-height:0}.brand{grid-column:1;flex-direction:column;font-size:14px}.brand img{width:44px;height:44px}.doc-kind{max-width:none;text-align:center;margin-top:5px;border:0;padding:0;margin-inline-start:0}.period-ribbon{margin-top:8px}.statement-body{padding:10px}.employee-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.detail-grid{grid-template-columns:1fr}.totals{grid-template-columns:repeat(2,minmax(0,1fr))}.statement-foot{padding:7px 10px}}
        @page{size:A4 portrait;margin:6mm}
        @media print{
            html,body{width:100%;height:auto;background:#fff;font-size:9px;line-height:1.25}
            .statement-shell{width:100%;max-width:none;margin:0;padding:0}
            .statement{border:2px solid var(--navy);border-radius:0;box-shadow:none;break-inside:avoid}
            .statement-top{padding:7px 12px 6px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .brandline{min-height:42px}
            .brand{font-size:12px;gap:6px}
            .brand img{width:38px;height:38px}
            .doc-kind{padding-inline-start:8px;margin-inline-start:0}
            .doc-kind strong{font-size:9px}.doc-kind span{font-size:8px}
            .period-ribbon{margin-top:4px;padding-top:4px}
            .period-label{font-size:8px}.period{font-size:12px}.status{font-size:8px;padding:2px 6px}
            .statement-body{padding:7px 10px 6px}
            .section-title{font-size:9px;margin-bottom:4px}
            .section-title:before{height:11px;width:2px}
            .employee-grid{gap:4px;margin-bottom:7px}
            .info-cell{padding:4px 6px}
            .info-label{font-size:8px}.info-value{font-size:9px}
            .detail-grid{gap:7px}
            .pay-table{margin-bottom:5px}
            .pay-table th{font-size:8px;padding:3px 5px}
            .pay-table td{padding:3px 5px}
            .pay-table .subhead td{font-size:8px;padding:3px 5px}
            .totals{gap:5px}
            .total-card{padding:4px 7px}
            .total-label{font-size:8px}.total-value{font-size:12px}
            .total-card.paid .total-value{font-size:14px}
            .payment-meta{font-size:8px;padding-top:5px;margin-top:5px}
            .statement-foot{padding:5px 10px;font-size:7px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .actions{display:none!important}
            .pay-table th,.pay-table .subhead td,.total-card.paid{-webkit-print-color-adjust:exact;print-color-adjust:exact}
        }
    </style>
</head>
<body>
<main class="statement-shell">
    <article class="statement">
        <header class="statement-top">
            <div class="brandline">
                <div class="brand">
                    <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($label('شعار جمعية أهل الخير', 'Ahl El Kheir logo')); ?>">
                    <span class="brand-name"><?php echo e($label('جمعية أهل الخير', 'Ahl El Kheir Charity')); ?></span>
                </div>
                <div class="doc-kind">
                    <strong><?php echo e($label('بيان تفاصيل الراتب', 'SALARY STATEMENT')); ?></strong>
                    <span><?php echo e($label('كشف راتب رسمي', 'Official payroll statement')); ?></span>
                </div>
            </div>
            <div class="period-ribbon">
                <div><div class="period-label"><?php echo e($label('فترة الراتب', 'PAY PERIOD')); ?></div><div class="period"><?php echo e($period); ?></div></div>
                <div class="status"><?php echo e($label('تم الصرف والترحيل المحاسبي', 'PAID & ACCOUNTING-POSTED')); ?></div>
            </div>
        </header>
        <div class="statement-body">
            <h2 class="section-title"><?php echo e($label('بيانات الموظف', 'EMPLOYEE DETAILS')); ?></h2>
            <div class="employee-grid">
                <div class="info-cell"><div class="info-label"><?php echo e($label('اسم الموظف', 'Employee name')); ?></div><div class="info-value"><?php echo e((string)$statement['employee_name']); ?></div></div>
                <div class="info-cell"><div class="info-label"><?php echo e($label('الرقم الوظيفي', 'Employee ID')); ?></div><div class="info-value"><?php echo e((string)$statement['employee_code']); ?></div></div>
                <div class="info-cell"><div class="info-label"><?php echo e($label('العملة', 'Currency')); ?></div><div class="info-value"><?php echo e($currency); ?></div></div>
            </div>

            <div class="detail-grid">
                <section class="detail-panel">
                    <h2 class="section-title"><?php echo e($label('الاستحقاقات', 'EARNINGS')); ?></h2>
                    <table class="pay-table">
                        <thead><tr><th><?php echo e($label('البيان', 'Description')); ?></th><th class="amount"><?php echo e($label('المبلغ', 'Amount')); ?></th></tr></thead>
                        <tbody>
                            <tr><td><?php echo e($label('الراتب الأساسي', 'Basic salary')); ?></td><td class="amount"><?php echo e($money($statement['basic_salary'])); ?></td></tr>
                            <tr><td><?php echo e($label('البدلات', 'Allowances')); ?></td><td class="amount"><?php echo e($money($statement['allowances'])); ?></td></tr>
                            <tr><td><?php echo e($label('العمل الإضافي', 'Overtime')); ?></td><td class="amount"><?php echo e($money($statement['overtime'])); ?></td></tr>
                            <?php if ($incentiveAmount > 0): ?>
                            <tr><td><?php echo e($label('الحوافز المصروفة', 'Incentives paid')); ?></td><td class="amount"><?php echo e($money($incentiveAmount)); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
                <section class="detail-panel">
                    <h2 class="section-title"><?php echo e($label('الاستقطاعات', 'DEDUCTIONS')); ?></h2>
                    <table class="pay-table">
                        <thead><tr><th><?php echo e($label('البيان', 'Description')); ?></th><th class="amount"><?php echo e($label('المبلغ', 'Amount')); ?></th></tr></thead>
                        <tbody>
                            <tr><td><?php echo e($label('الاستقطاعات العامة', 'General deductions')); ?></td><td class="amount">− <?php echo e($money($statement['deductions'])); ?></td></tr>
                            <tr><td><?php echo e($label('استقطاع الحضور والإجازات', 'Attendance / leave deduction')); ?></td><td class="amount">− <?php echo e($money($statement['attendance_deduction'])); ?></td></tr>
                            <tr><td><?php echo e($label('استقطاع سلفة الراتب', 'Salary advance repayment')); ?></td><td class="amount">− <?php echo e($money($statement['salary_advance_deduction'])); ?></td></tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <h2 class="section-title"><?php echo e($label('ملخص الصرف', 'PAYMENT SUMMARY')); ?></h2>
            <div class="totals">
                <div class="total-card net"><div class="total-label"><?php echo e($label('صافي الراتب المصروف', 'NET SALARY PAID')); ?></div><div class="total-value"><?php echo e($money($netSalary)); ?></div></div>
                <div class="total-card paid"><div class="total-label"><?php echo e($label('إجمالي المبلغ المصروف', 'TOTAL PAID')); ?></div><div class="total-value"><?php echo e($money($totalPaid)); ?></div></div>
            </div>
            <div class="payment-meta">
                <span><?php echo e($label('تاريخ الصرف', 'Payment date')); ?>: <strong><?php echo e($paymentDate !== '' ? $paymentDate : '—'); ?></strong></span>
                <span><?php echo e($label('مرجع القيد المحاسبي', 'Journal reference')); ?>: <strong><?php echo e((string)($statement['accounting_entry_id'] ?? '—')); ?></strong></span>
            </div>
        </div>
        <footer class="statement-foot">
            <span><?php echo e($label('كشف إلكتروني صادر بعد اكتمال تسجيل الصرف والترحيل المحاسبي.', 'Electronic statement issued after disbursement and accounting posting.')); ?></span>
            <span><?php echo e((string)$statement['employee_code']); ?> · <?php echo e(sprintf('%04d-%02d', (int)$statement['year'], $month)); ?></span>
        </footer>
    </article>
    <div class="actions">
        <button type="button" class="print-btn" onclick="window.print()"><?php echo e($label('طباعة / حفظ PDF', 'Print / Save PDF')); ?></button>
        <button type="button" class="back-btn" onclick="window.close()"><?php echo e($label('إغلاق الكشف', 'Close statement')); ?></button>
    </div>
</main>
</body>
</html>
